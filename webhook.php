<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Lắng nghe Webhook Thông báo Thanh toán Instant từ SePay.
 *
 * Script này đợi thông báo thanh toán từ SePay,
 * và thiết lập quyền ghi danh cho người dùng tương ứng.
 *
 * @package    enrol_sepay
 * @copyright  2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Commenting.InlineComment.NotCapital -- Comment tiếng Việt; sniff chỉ chấp nhận chữ hoa ASCII (Đ/Ư... bị coi là thường).

// Tắt các thông báo lỗi đặc thù của Moodle ở đầu ra,
// bỏ mã nguồn trống khi debug hoặc kiểm tra file log!
define('NO_DEBUG_DISPLAY', true);

// Script IPN/Webhook KHÔNG yêu cầu đăng nhập người dùng.
define('NO_MOODLE_COOKIES', true); // Không cần tạo session cho người dùng.

// Nạp config Moodle.
require('../../config.php'); // phpcs:ignore

// Nạp lib enrol.
require_once(__DIR__ . '/lib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once(__DIR__ . '/classes/util.php');

// Thiết lập trả về JSON cho webhook — phải đặt TRƯỚC mọi exception có thể throw.
header('Content-Type: application/json');

// Đảm bảo plugin enrol_sepay đang được bật.
if (!enrol_is_enabled('sepay')) {
    http_response_code(503);
    echo json_encode(['error' => 'SePay enrolment plugin is disabled']);
    exit;
}

// Lấy đối tượng plugin enrol_sepay.
$plugin = enrol_get_plugin('sepay');
if (!$plugin) {
    http_response_code(500);
    echo json_encode(['error' => 'Không tìm thấy plugin enrol_sepay']);
    exit;
}

// Lấy API Key cấu hình trong trang Settings của plugin.
$expectedkey = trim((string)$plugin->get_config('apikey'));

// Từ chối ngay nếu admin chưa cấu hình API Key — không cho phép webhook chạy khi chưa bảo mật.
if (empty($expectedkey)) {
    http_response_code(503);
    echo json_encode(['error' => 'Webhook chưa được cấu hình API Key']);
    exit;
}

// Xác thực Authorization header từ SePay.
// SePay gửi header: "Authorization: Apikey <API_KEY>".
$authheader = '';
if (function_exists('getallheaders')) {
    $allheaders = getallheaders();
    foreach ($allheaders as $name => $value) {
        if (strtolower($name) === 'authorization') {
            $authheader = $value;
            break;
        }
    }
}
// Fallback cho CGI/FastCGI server không có getallheaders().
if ($authheader === '') {
    $authheader = $_SERVER['HTTP_AUTHORIZATION']
                ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
                ?? '';
}

// Dùng hash_equals để tránh timing attack.
if (!hash_equals('Apikey ' . $expectedkey, $authheader)) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// 2. Đọc JSON input body do SePay gửi sang.
$input = file_get_contents('php://input');
$sepaydata = json_decode($input, true);

// Chỉ cần content hợp lệ; trường code có thể rỗng hoặc null.
if (!is_array($sepaydata) || empty($sepaydata['content'])) {
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Webhook endpoint is active']);
    exit;
}

// Lấy các trường quan trọng từ payload SePay.
$content          = (string)($sepaydata['content'] ?? '');
$transferamount   = (int)($sepaydata['transferAmount'] ?? 0);
$transfertype     = (string)($sepaydata['transferType'] ?? '');
$gateway          = (string)($sepaydata['gateway'] ?? '');
$accountnumber    = (string)($sepaydata['accountNumber'] ?? '');
$subaccount       = (string)($sepaydata['subAccount'] ?? '');
$accumulated      = (int)($sepaydata['accumulated'] ?? 0); // Lũy kế
// referenceCode = mã giao dịch duy nhất của SePay, dùng để chống replay attack.
$transactionref  = (string)($sepaydata['referenceCode'] ?? $sepaydata['id'] ?? '');

// Lấy thông tin tài khoản nhận và ngân hàng cấu hình trong plugin.
$bankaccount = (string)$plugin->get_config('account');
$configbank  = (string)$plugin->get_config('bank');

// 3. Lọc những giao dịch không phải chuyển "vào" tài khoản này hoặc sai ngân hàng.
if ($transfertype !== 'in') {
    http_response_code(200);
    echo json_encode(['message' => 'Bỏ qua: transferType khác "in"']);
    exit;
}

if ($gateway !== $configbank) {
    http_response_code(200);
    echo json_encode(['message' => 'Bỏ qua: gateway khác ngân hàng cấu hình']);
    exit;
}

if ($accountnumber !== $bankaccount && $subaccount !== $bankaccount) {
    http_response_code(200);
    echo json_encode(['message' => 'Bỏ qua: số tài khoản nhận không khớp cấu hình']);
    exit;
}

// 4. Trích xuất courseid và userid từ nội dung chuyển khoản (content).
// Mẫu nội dung do Admin cấu hình: [pattern] + [courseid] + [separator] + [userid].
// Ví dụ: "VLU_CoursesID_12_UserID_34" -> courseid=12, userid=34.
$pattern = trim((string)$plugin->get_config('pattern', 'sepay'));
$separator = trim((string)$plugin->get_config('separator', 'sepay'));

// Ngân hàng thường strip ký tự đặc biệt (như _) trong nội dung chuyển khoản.
// Giữ lại dấu cách và dấu chấm để làm terminator cho regex parse:
// - Dấu cách: hầu hết ngân hàng (MBBank, VCB, Techcombank...) tự thêm metadata
// sau một dấu cách, ví dụ "CourseID2UserID387 050526 23 03 ...".
// - Dấu chấm: phòng trường hợp một số ngân hàng giữ '.' nguyên hoặc tự chèn.
// Nếu cả hai terminator đều bị strip, lớp fallback DB shrinking bên dưới sẽ xử lý.
$cleanpattern = preg_replace('/[^A-Za-z0-9]/', '', $pattern);
$cleanseparator = preg_replace('/[^A-Za-z0-9]/', '', $separator);
$cleancontent = preg_replace('/[^A-Za-z0-9 .]/', '', $content);

$matches = [];
$parsedcourseid = null;
$parseduserid = null;

// Lớp 1+2: regex strict — yêu cầu kết thúc bằng '.', dấu cách, hoặc end-of-string.
// Đủ cho 99% giao dịch thực tế (bank giữ '.' hoặc giữ space).
$strictregex = '/' . preg_quote($cleanpattern, '/') . '(\d+)'
              . preg_quote($cleanseparator, '/') . '(\d+)(?=[.\s]|$)/i';
if (preg_match($strictregex, $cleancontent, $matches)) {
    $parsedcourseid = (int)$matches[1];
    $parseduserid   = (int)$matches[2];
} else {
    // Lớp 3 (fallback): regex loose + DB shrinking. Áp dụng khi ngân hàng strip cả
    // '.' lẫn space. Greedy '\d+' có thể nuốt thêm số metadata vào courseid/userid;
    // ta cắt dần từ phải và verify với DB để khôi phục giá trị thật.
    $looseregex = '/' . preg_quote($cleanpattern, '/') . '(\d+)'
                 . preg_quote($cleanseparator, '/') . '(\d+)/i';
    if (preg_match($looseregex, $cleancontent, $matches)) {
        $courseidraw = $matches[1];
        $useridraw = $matches[2];

        // Shrink courseid từ phải: lấy prefix dài nhất tồn tại trong bảng course.
        for ($i = strlen($courseidraw); $i > 0; $i--) {
            $candidate = (int)substr($courseidraw, 0, $i);
            if ($candidate > 0 && $DB->record_exists('course', ['id' => $candidate])) {
                $parsedcourseid = $candidate;
                break;
            }
        }

        // Shrink userid từ phải: lấy prefix dài nhất tồn tại và chưa bị xóa.
        for ($i = strlen($useridraw); $i > 0; $i--) {
            $candidate = (int)substr($useridraw, 0, $i);
            if ($candidate > 0 && $DB->record_exists('user', ['id' => $candidate, 'deleted' => 0])) {
                $parseduserid = $candidate;
                break;
            }
        }
    }
}

// Kiểm tra kết quả parse.
if ($parsedcourseid === null || $parseduserid === null) {
    http_response_code(200);
    echo json_encode(['message' => 'Bỏ qua: nội dung chuyển khoản không khớp pattern mong đợi']);
    exit;
}

$data = new stdClass();
$data->userid      = $parseduserid;
$data->courseid    = $parsedcourseid;
$data->timeupdated = time();

// 5. Tìm user và khóa học tương ứng trong Moodle.
$user = $DB->get_record('user', ['id' => $data->userid], '*', IGNORE_MISSING);
$course = $DB->get_record('course', ['id' => $data->courseid], '*', IGNORE_MISSING);

if (!$user || !$course) {
    http_response_code(404);
    echo json_encode(['error' => 'Không tìm thấy user hoặc khóa học tương ứng']);
    exit;
}

// 6. Tìm instance của enrol_sepay trong khóa học này.
$instances = enrol_get_instances($course->id, true);
$instance = null;

foreach ($instances as $inst) {
    if ($inst->enrol === 'sepay') {
        $instance = $inst;
        break;
    }
}

if (!$instance) {
    http_response_code(500);
    echo json_encode(['error' => 'Không tìm thấy instance enrol_sepay trong khóa học']);
    exit;
}

// 7. Kiểm tra số tiền chuyển có đủ so với config cost của instance hay không.
if ((float)$instance->cost <= 0) {
    // Nếu cost của instance <= 0 thì dùng cost mặc định cấu hình trong plugin.
    $cost = (float)$plugin->get_config('cost');
} else {
    $cost = (float)$instance->cost;
}

// Nếu chi tiết SePay gửi sang nhỏ hơn số tiền yêu cầu -> báo lỗi & không ghi danh.
if ($transferamount < $cost) {
    // Log lỗi và thông báo admin giống phong cách Paypal.
    \enrol_sepay\util::message_sepay_error_to_admin(
        "Số tiền thanh toán không đủ ({$transferamount} < {$cost})",
        $sepaydata
    );

    http_response_code(200);
    echo json_encode([
        'error'   => 'Số tiền thanh toán không đủ',
        'detail'  => "Đã nhận: {$transferamount}, yêu cầu: {$cost}",
    ]);
    exit;
}

// 8. Tính thời gian ghi danh (start/end) giống logic trong plugin enrol.
if (!empty($instance->enrolperiod)) {
    $timestart = time();
    $timeend = $timestart + (int)$instance->enrolperiod;
} else {
    $timestart = 0;
    $timeend = 0;
}

// Role mặc định lấy từ instance, fallback về config plugin.
// Nếu roleid <= 0 (chưa cấu hình): nhánh auto-enrol bên dưới (if (!$manualenrol) → if ($roleid <= 0))
// giữ giao dịch ở 'pending' + báo admin, nhất quán với complete_enrol/process_enrolments — KHÔNG
// auto-enrol với role đoán. Đừng thêm fallback role ở đây (sẽ preempt nhánh pending đó).
$roleid = !empty($instance->roleid) ? (int)$instance->roleid : (int)$plugin->get_config('roleid');

// 9. Thực hiện ghi danh hoặc lưu trạng thái chờ xử lý.
// Lấy config 'manual_enrol' từ instance (customint1) hoặc global.
// customint1: 0 = Default (Global), 1 = Manual, 2 = Auto.
$instancemanual = isset($instance->customint1) ? (int)$instance->customint1 : 0;

if ($instancemanual === 1) {
    $manualenrol = true;
} else if ($instancemanual === 2) {
    $manualenrol = false;
} else {
    // Dùng cấu hình global nếu instance không chỉ định.
    $manualenrol = (int)$plugin->get_config('manual_enrol');
}

// Xác định trạng thái và hành động.
$status = 'pending';
$message = get_string('transaction_recorded', 'enrol_sepay');

// Lấy IP của user từ bảng user (lastip - IP cuối cùng user đăng nhập vào Moodle).
// Đây là IP thực của người dùng, không phải IP của SePay server.
$userip = $user->lastip ?? '';

// Khóa chống race: 2 webhook SePay retry song song (hoặc user F5) cùng một giao dịch
// có thể cùng vượt qua check duplicate rồi insert 2 lần (index transaction_ref KHÔNG
// unique — cố ý, để cho phép mua lại sau khi bị reject). Serialize đoạn check+insert
// bằng app-lock theo khóa dedup để chỉ một request xử lý tại một thời điểm.
$lockfactory = \core\lock\lock_config::get_lock_factory('enrol_sepay_webhook');
$lockresource = ($transactionref !== '')
    ? 'ref_' . sha1($transactionref)
    : 'tuple_' . $user->id . '_' . $course->id . '_' . $instance->id;
$lock = $lockfactory->get_lock($lockresource, 10);
if (!$lock) {
    // Không lấy được lock trong 10s — một request khác đang xử lý cùng giao dịch này.
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Giao dịch đang được xử lý']);
    exit;
}

// Kiểm tra duplicate theo 2 lớp:
// Lớp 1 — cùng transaction_ref (referenceCode SePay): chặn replay dù status là gì.
$refstored = false;
if ($transactionref !== '') {
    try {
        $refexists = $DB->record_exists('enrol_sepay_transactions', ['transaction_ref' => $transactionref]);
        if ($refexists) {
            $lock->release();
            http_response_code(200);
            echo json_encode(['success' => true, 'message' => 'Giao dịch đã được ghi nhận trước đó']);
            exit;
        }
        $refstored = true; // DB có cột này và đã check thành công.
    } catch (\dml_exception $e) {
        // Column transaction_ref chưa tồn tại trong DB — fallback sang Lớp 2.
        debugging('enrol_sepay webhook: transaction_ref column missing — ' . $e->getMessage(), DEBUG_DEVELOPER);
        $refstored = false;
    }
}

// Lớp 2 — Chỉ chạy nếu không thể dùng Lớp 1 (DB cũ chưa upgrade, hoặc không có transaction_ref).
// Chặn theo (userid, courseid, instanceid) với status pending/processed để chặn retry từ SePay.
if (!$refstored) {
    $existing = $DB->get_record_select(
        'enrol_sepay_transactions',
        "userid = :uid AND courseid = :cid AND instanceid = :iid AND status IN ('pending', 'processed')",
        ['uid' => $user->id, 'cid' => $course->id, 'iid' => $instance->id]
    );
    if ($existing) {
        $lock->release();
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Giao dịch đã được ghi nhận trước đó (Fallback check)']);
        exit;
    }
}

// Lưu vào bảng enrol_sepay_transactions.
$record = new stdClass();
$record->userid             = $user->id;
$record->courseid           = $course->id;
$record->instanceid         = $instance->id;
$record->amount             = $transferamount;
$record->currency           = $plugin->get_config('currency') ?: 'VND';
$record->transaction_content = $content;
$record->transaction_ref    = $transactionref;
$record->gateway            = $gateway;
$record->status             = $status;
$record->ip_address         = $userip;
$record->timecreated        = time();
$record->timeprocessed      = ($status === 'processed') ? time() : 0;

// Nếu Lớp 1 đã xác định DB không có cột transaction_ref, bỏ nó ra khỏi record luôn.
// Nếu Lớp 1 xác định có, ta cứ insert bình thường.
if (!$refstored) {
    unset($record->transaction_ref);
}

try {
    $txnid = $DB->insert_record('enrol_sepay_transactions', $record);
} catch (\dml_exception $e) {
    // Đề phòng trường hợp chưa bắt được dml_exception ở Lớp 1 (edge case).
    if (isset($record->transaction_ref) && strpos($e->getMessage(), 'transaction_ref') !== false) {
        unset($record->transaction_ref);
        $refstored = false; // Mark lại là không có column.
        try {
            $txnid = $DB->insert_record('enrol_sepay_transactions', $record);
        } catch (\Exception $e2) {
            $lock->release();
            \enrol_sepay\util::message_sepay_error_to_admin('Không thể lưu transaction vào DB: ' . $e2->getMessage(), $sepaydata);
            http_response_code(500);
            echo json_encode(['error' => 'Lỗi lưu giao dịch', 'detail' => $e2->getMessage()]);
            exit;
        }
    } else {
        $lock->release();
        \enrol_sepay\util::message_sepay_error_to_admin('Không thể lưu transaction vào DB: ' . $e->getMessage(), $sepaydata);
        http_response_code(500);
        echo json_encode(['error' => 'Lỗi lưu giao dịch', 'detail' => $e->getMessage()]);
        exit;
    }
}

// Insert thành công — giải phóng lock. Request song song sau đây sẽ thấy bản ghi vừa
// tạo và thoát ở check duplicate, nên không cần giữ lock qua đoạn enrol/email.
$lock->release();

// 10. Thực hiện ghi danh ngay (auto enrol) hoặc thông báo chờ duyệt (manual enrol).
if (!$manualenrol) {
    // Nếu role chưa cấu hình hợp lệ, KHÔNG auto-enrol (tránh enrol_user với roleid=0).
    // Giữ giao dịch ở 'pending' cho admin duyệt thủ công + báo admin.
    if ($roleid <= 0) {
        \enrol_sepay\util::message_sepay_error_to_admin(
            'webhook auto-enrol bị bỏ qua: roleid chưa cấu hình (<=0). Giao dịch để pending cho admin duyệt.',
            $sepaydata
        );
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Giao dịch ghi nhận, chờ duyệt thủ công (role chưa cấu hình)']);
        exit;
    }

    // Ghi danh user (bắt buộc).
    // Nếu enrol_user() thất bại → báo admin + return 200 (tránh SePay retry vô tận).
    try {
        $plugin->enrol_user($instance, $user->id, $roleid, $timestart, $timeend);
    } catch (\Exception $e) {
        \enrol_sepay\util::message_sepay_error_to_admin(
            "Lỗi enrol_user() — user {$user->id}, course {$course->id}: " . $e->getMessage(),
            $sepaydata
        );
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Giao dịch ghi nhận thành công, ghi danh sẽ được xử lý thủ công']);
        exit;
    }

    // Cập nhật status='processed' trong DB (không bắt buộc).
    // Nếu lỗi ở đây, user vẫn đã được ghi danh — chỉ log, không abort.
    try {
        $now = time();
        if ($refstored) {
            $DB->set_field(
                'enrol_sepay_transactions',
                'status',
                'processed',
                ['transaction_ref' => $transactionref]
            );
            $DB->set_field(
                'enrol_sepay_transactions',
                'timeprocessed',
                $now,
                ['transaction_ref' => $transactionref]
            );
        } else {
            $DB->set_field(
                'enrol_sepay_transactions',
                'status',
                'processed',
                ['userid' => $user->id, 'courseid' => $course->id, 'instanceid' => $instance->id, 'status' => 'pending']
            );
            $DB->set_field(
                'enrol_sepay_transactions',
                'timeprocessed',
                $now,
                ['userid' => $user->id, 'courseid' => $course->id, 'instanceid' => $instance->id, 'status' => 'processed']
            );
        }
    } catch (\Exception $e) {
        // Log nhưng không abort (user đã ghi danh ở bước trên).
        debugging('enrol_sepay webhook: set status processed failed — ' . $e->getMessage(), DEBUG_DEVELOPER);
        \enrol_sepay\util::message_sepay_error_to_admin(
            "DB update processed failed (user đã enrol) — user {$user->id}, course {$course->id}: " . $e->getMessage(),
            $sepaydata
        );
    }

    // Gửi email chào mừng (không bắt buộc).
    // Lỗi email KHÔNG được làm abort flow — user đã ghi danh, chỉ thiếu mail.
    try {
        // Chỉ đánh dấu email_sent khi send_welcome_messages thực sự gửi (trả true).
        if (\enrol_sepay\util::send_welcome_messages($course, $user, $instance)) {
            if ($refstored) {
                $DB->set_field('enrol_sepay_transactions', 'email_sent', 1, ['transaction_ref' => $transactionref]);
            } else {
                $DB->set_field(
                    'enrol_sepay_transactions',
                    'email_sent',
                    1,
                    ['userid' => $user->id, 'courseid' => $course->id, 'instanceid' => $instance->id, 'status' => 'processed']
                );
            }
        }
    } catch (\Exception $e) {
        // Log nhưng không abort — ghi danh đã xong, email chỉ là phụ.
        debugging('enrol_sepay webhook: send_welcome_messages failed — ' . $e->getMessage(), DEBUG_DEVELOPER);
    }

    $status = 'processed';
    $message = get_string('paymentthanks', 'enrol_sepay', $course->fullname);
} else {
    // MANUAL ENROL: Gửi thông báo chờ duyệt cho admin.
    $admins = get_users_by_capability(context_system::instance(), 'enrol/sepay:manage');
    if (empty($admins)) {
        $admins = get_admins();
    }

    if (!empty($admins)) {
        $messagedata = new \core\message\message();
        $messagedata->component = 'enrol_sepay';
        $messagedata->name = 'pending_transaction';
        $messagedata->userfrom = $user;
        $messagedata->subject = get_string('notification_pending_title', 'enrol_sepay');

        $messagetext = new stdClass();
        $messagetext->username = fullname($user);
        $messagetext->amount = number_format($transferamount);
        $messagetext->currency = $record->currency;
        $messagetext->coursename = $course->fullname;

        $messagedata->fullmessage = get_string('notification_pending_body', 'enrol_sepay', $messagetext);
        $messagedata->fullmessageformat = FORMAT_PLAIN;
        $messagedata->fullmessagehtml = get_string('notification_pending_body', 'enrol_sepay', $messagetext);
        $messagedata->smallmessage = get_string('notification_pending_small', 'enrol_sepay', $messagetext);
        $messagedata->notification = 1;

        $messagedata->contexturl = new moodle_url('/enrol/sepay/transactions.php', ['filter' => 'pending']);
        $messagedata->contexturlname = get_string('notification_pending_url', 'enrol_sepay');

        // Nhúng id giao dịch vào customdata để sau này xóa ĐÚNG chuông của giao dịch này
        // (tránh xóa nhầm các pending khác của cùng user khi duyệt/xóa một giao dịch).
        // Lưu dạng chuỗi để khớp chính xác qua LIKE ("txnid":"12" không nhầm với "123").
        $messagedata->customdata = ['txnid' => (string)(int)$txnid];

        foreach ($admins as $admin) {
            $messagedata->userto = $admin;
            try {
                message_send($messagedata);
            } catch (\Exception $e) {
                debugging('enrol_sepay webhook: message_send failed for admin ' . $admin->id . ': '
                    . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }
    }
}

// Trả về JSON thành công cho SePay.
// Thứ tự: kết quả → user (parse đúng chưa?) → khóa học → tiền (đã thấy trên SePay UI).
$response = [
    'success'         => true, // Webhook xử lý thành công?
    'status'          => $status, // Processed | pending.
    'message'         => $message, // Mô tả kết quả.
    'userid'          => $user->id, // ID user được parse từ content.
    'username'        => fullname($user), // Tên user — verify parse đúng chưa.
    'courseid'        => $course->id, // ID khóa học được parse từ content.
    'coursename'      => $course->fullname, // Tên khóa học — verify parse đúng chưa.
    'amount_received' => $transferamount, // Số tiền thực nhận (VND).
    'amount_required' => $cost, // Số tiền yêu cầu (VND).
    'accumulated'     => $accumulated, // Số dư tài khoản sau giao dịch (Lũy kế).
];

http_response_code(200);
echo json_encode($response);
exit(); // Dừng script ngay sau khi trả về response.
