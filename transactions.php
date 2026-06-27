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
 * Trang quản lý giao dịch SePay.
 *
 * Mục đích: Trang quản lý giao dịch SePay
 * Chức năng: Hiển thị, lọc, tìm kiếm, phê duyệt, từ chối và xóa giao dịch
 *
 * @package    enrol_sepay
 * @copyright  2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/ddllib.php');
require_once($CFG->libdir . '/tablelib.php');
require_once($CFG->dirroot . '/enrol/sepay/lib.php');
require_once($CFG->dirroot . '/enrol/sepay/classes/util.php');

// Lấy tham số lọc và tìm kiếm từ URL
$action        = optional_param('action', '', PARAM_ALPHANUMEXT);
$id            = optional_param('id', 0, PARAM_INT);
$filter        = optional_param('filter', 'all', PARAM_ALPHA);
$search_user   = optional_param('search_user', '', PARAM_TEXT);
$search_course = optional_param('search_course', '', PARAM_TEXT);
$date_from     = optional_param('date_from', '', PARAM_TEXT);
$date_to       = optional_param('date_to', '', PARAM_TEXT);
$letter_raw    = optional_param('letter', '', PARAM_TEXT);
$letter_fn_raw = optional_param('letter_fn', '', PARAM_TEXT);
$reset_table   = optional_param('reset_table', 0, PARAM_INT);
// Số dòng mỗi trang: mặc định 20, cho phép 10/20/30/50/100/TABLE_SHOW_ALL_PAGE_SIZE
$perpage       = optional_param('perpage', 20, PARAM_INT);

// Bảng chữ cái tiếng Việt dùng cho bộ lọc họ và tên người dùng
$vn_alphabet = [
    'A', 'Ă', 'Â', 'B', 'C', 'D', 'Đ', 'E', 'Ê', 'G',
    'H', 'I', 'K', 'L', 'M', 'N', 'O', 'Ô', 'Ơ', 'P',
    'Q', 'R', 'S', 'T', 'U', 'Ư', 'V', 'X', 'Y',
];

// Kiểm tra letter (họ) có hợp lệ không (whitelist)
$letter = in_array(mb_strtoupper($letter_raw, 'UTF-8'), $vn_alphabet, true)
        ? mb_strtoupper($letter_raw, 'UTF-8')
        : '';

// Kiểm tra letter_fn (tên) có hợp lệ không (whitelist)
$letter_fn = in_array(mb_strtoupper($letter_fn_raw, 'UTF-8'), $vn_alphabet, true)
           ? mb_strtoupper($letter_fn_raw, 'UTF-8')
           : '';

// Yêu cầu đăng nhập và kiểm tra quyền
require_login();
$context = context_system::instance();

// Thiết lập URL và context trước khi gọi admin_externalpage_setup
$PAGE->set_url(new moodle_url('/enrol/sepay/transactions.php', [
    'filter'        => $filter,
    'search_user'   => $search_user,
    'search_course' => $search_course,
    'date_from'     => $date_from,
    'date_to'       => $date_to,
    'letter'        => $letter,
    'letter_fn'     => $letter_fn,
    'perpage'       => $perpage,
]));
$PAGE->set_context($context);

require_capability('enrol/sepay:manage', $context);

try {
    // Gọi thiết lập trang admin, ưu tiên pagelayout 'report' để mở rộng độ rộng bảng tối đa (giống loglive)
    admin_externalpage_setup('enrol_sepay_transactions', '', null, '', ['pagelayout' => 'report']);
} catch (moodle_exception $e) {
    if ($e->errorcode === 'accessdenied') {
        $PAGE->set_pagelayout('report');
    } else {
        throw $e;
    }
}

$PAGE->set_pagelayout('report');

$plugin = enrol_get_plugin('sepay');

$PAGE->set_title(get_string('manage_transactions', 'enrol_sepay'));
$PAGE->set_heading($SITE->fullname);

// Breadcrumb điều hướng
$PAGE->navbar->add(get_string('administrationsite'));
$PAGE->navbar->add(get_string('plugins', 'admin'));
$PAGE->navbar->add(get_string('enrolments', 'enrol'));
$PAGE->navbar->add(get_string('manage_transactions', 'enrol_sepay'));

// Xử lý action: Phê duyệt
if ($action === 'approve' && $id > 0 && confirm_sesskey()) {
    $transaction = $DB->get_record('enrol_sepay_transactions', ['id' => $id]);

    if ($transaction && $transaction->status === 'pending') {
        // Chỉ cập nhật trạng thái, chưa ghi danh ngay
        $transaction->status = 'processed';
        $transaction->timeprocessed = time();
        $DB->update_record('enrol_sepay_transactions', $transaction);
        \enrol_sepay\util::delete_pending_notifications($transaction);

        redirect($PAGE->url, get_string('transaction_approved', 'enrol_sepay'));
    } else {
        redirect($PAGE->url, get_string('error_already_processed', 'enrol_sepay'), null, core\output\notification::NOTIFY_ERROR);
    }
}

// Xử lý action: Từ chối
if ($action === 'reject' && $id > 0 && confirm_sesskey()) {
    $transaction = $DB->get_record('enrol_sepay_transactions', ['id' => $id]);

    if ($transaction && $transaction->status === 'pending') {
        $transaction->status = 'rejected';
        $transaction->timeprocessed = time();
        $DB->update_record('enrol_sepay_transactions', $transaction);
        \enrol_sepay\util::delete_pending_notifications($transaction);

        // Gửi rejection notification cho student.
        $user   = $DB->get_record('user',   ['id' => $transaction->userid]);
        $course = $DB->get_record('course', ['id' => $transaction->courseid]);
        if ($user && $course) {
            try {
                \enrol_sepay\util::send_rejection_notification($course, $user);
                $DB->set_field('enrol_sepay_transactions', 'rejection_notified', 1, ['id' => $transaction->id]);
            } catch (\Exception $e) {
                debugging('enrol_sepay reject: send_rejection_notification failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
            }
        }

        redirect($PAGE->url, get_string('transaction_rejected', 'enrol_sepay'));
    } else {
        redirect($PAGE->url, get_string('error_already_processed', 'enrol_sepay'), null, core\output\notification::NOTIFY_ERROR);
    }
}

// Xử lý action: Xóa một giao dịch
if ($action === 'delete' && $id > 0 && confirm_sesskey()) {
    $transaction = $DB->get_record('enrol_sepay_transactions', ['id' => $id]);

    if ($transaction) {
        \enrol_sepay\util::delete_pending_notifications($transaction);
        $was_processed = ($transaction->status === 'processed');
        $DB->delete_records('enrol_sepay_transactions', ['id' => $id]);
        if ($was_processed) {
            // Xóa giao dịch đã xử lý không tự hủy ghi danh — cảnh báo admin + ghi log.
            debugging('enrol_sepay: đã xóa giao dịch processed id=' . $id . ' — ghi danh không bị tự hủy.', DEBUG_DEVELOPER);
            redirect($PAGE->url, get_string('transaction_deleted_processed', 'enrol_sepay'), null, core\output\notification::NOTIFY_WARNING);
        }
        redirect($PAGE->url, get_string('transaction_deleted', 'enrol_sepay'));
    } else {
        redirect($PAGE->url, get_string('transaction_not_found', 'enrol_sepay'), null, core\output\notification::NOTIFY_ERROR);
    }
}

// Xử lý action: Xóa nhiều giao dịch
if ($action === 'bulk_delete' && confirm_sesskey()) {
    $deleteids = optional_param_array('deleteids', [], PARAM_INT);

    if (!empty($deleteids)) {
        list($insql, $params) = $DB->get_in_or_equal($deleteids);
        
        $txns = $DB->get_records_select('enrol_sepay_transactions', "id $insql", $params);
        $processed_count = 0;
        foreach ($txns as $txn) {
            \enrol_sepay\util::delete_pending_notifications($txn);
            if ($txn->status === 'processed') {
                $processed_count++;
            }
        }

        $DB->delete_records_select('enrol_sepay_transactions', "id $insql", $params);

        if ($processed_count > 0) {
            // Có giao dịch đã xử lý bị xóa — ghi danh không bị tự hủy, ghi log để truy vết.
            debugging('enrol_sepay bulk_delete: đã xóa ' . $processed_count . ' giao dịch processed — ghi danh không bị tự hủy.', DEBUG_DEVELOPER);
        }

        $redirecturl = new moodle_url('/enrol/sepay/transactions.php', [
            'filter'        => $filter,
            'search_user'   => $search_user,
            'search_course' => $search_course,
            'date_from'     => $date_from,
            'date_to'       => $date_to,
            'letter'        => $letter,
            'letter_fn'     => $letter_fn,
            'perpage'       => $perpage,
        ]);
        redirect($redirecturl, get_string('transactions_deleted', 'enrol_sepay', count($deleteids)));
    } else {
        $redirecturl = new moodle_url('/enrol/sepay/transactions.php', ['filter' => $filter]);
        redirect($redirecturl, get_string('no_transactions_selected', 'enrol_sepay'), null, core\output\notification::NOTIFY_WARNING);
    }
}

// Xử lý action: Phê duyệt nhiều giao dịch
if ($action === 'bulk_approve' && confirm_sesskey()) {
    // Checkbox trong form dùng name="deleteids[]" cho cả 3 bulk actions.
    $approveids = optional_param_array('deleteids', [], PARAM_INT);

    if (!empty($approveids)) {
        $approved_count = 0;
        $failed_count = 0;
        foreach ($approveids as $txn_id) {
            $txn = $DB->get_record('enrol_sepay_transactions', ['id' => $txn_id]);
            if ($txn && $txn->status === 'pending') {
                $txn->status        = 'processed';
                $txn->timeprocessed = time();
                try {
                    $DB->update_record('enrol_sepay_transactions', $txn);
                    \enrol_sepay\util::delete_pending_notifications($txn);
                    $approved_count++;
                } catch (\dml_exception $e) {
                    debugging('enrol_sepay bulk_approve: update_record failed for id=' . $txn_id . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
                    $failed_count++;
                }
            }
        }

        $redirecturl = new moodle_url('/enrol/sepay/transactions.php', [
            'filter'        => $filter,
            'search_user'   => $search_user,
            'search_course' => $search_course,
            'date_from'     => $date_from,
            'date_to'       => $date_to,
            'letter'        => $letter,
            'letter_fn'     => $letter_fn,
            'perpage'       => $perpage,
        ]);
        redirect($redirecturl, get_string('bulk_approved', 'enrol_sepay', $approved_count));
    } else {
        $redirecturl = new moodle_url('/enrol/sepay/transactions.php', ['filter' => $filter]);
        redirect($redirecturl, get_string('no_transactions_selected', 'enrol_sepay'), null, core\output\notification::NOTIFY_WARNING);
    }
}

// Xử lý action: Hủy ghi danh nhiều giao dịch
if ($action === 'bulk_unenrol' && confirm_sesskey()) {
    // Checkbox trong form dùng name="deleteids[]" cho cả các bulk actions.
    $unenrolids = optional_param_array('deleteids', [], PARAM_INT);

    if (!empty($unenrolids)) {
        $unenrolled_count = 0;
        foreach ($unenrolids as $txn_id) {
            $txn = $DB->get_record('enrol_sepay_transactions', ['id' => $txn_id]);
            if ($txn && $txn->status === 'processed') {
                // Lấy đúng instance SePay bằng instanceid từ giao dịch
                // (tránh exception khi 1 course có nhiều SePay instance)
                $instance = $DB->get_record('enrol', ['id' => $txn->instanceid, 'enrol' => 'sepay']);
                if (!$instance) {
                    // Instance đã bị xóa — bỏ qua, không đổi trạng thái
                    continue;
                }
                try {
                    $plugin->unenrol_user($instance, $txn->userid);
                } catch (\Exception $e) {
                    // Không crash toàn bộ vòng lặp — tiếp tục với giao dịch tiếp theo
                    debugging('bulk_unenrol: unenrol_user failed for txn ' . $txn_id . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
                    continue;
                }
                // Chỉ đổi trạng thái khi unenrol_user thành công
                $txn->status        = 'unenrolled';
                $txn->timeprocessed = time();
                try {
                    $DB->update_record('enrol_sepay_transactions', $txn);
                } catch (\dml_exception $dbe) {
                    // DB update thất bại — log nhưng cộng count vì user đã bị unenrol thật
                    debugging('bulk_unenrol: DB update failed for txn ' . $txn_id . ': ' . $dbe->getMessage(), DEBUG_DEVELOPER);
                }
                $unenrolled_count++;
            }
        }

        $redirecturl = new moodle_url('/enrol/sepay/transactions.php', [
            'filter'        => $filter,
            'search_user'   => $search_user,
            'search_course' => $search_course,
            'date_from'     => $date_from,
            'date_to'       => $date_to,
            'letter'        => $letter,
            'letter_fn'     => $letter_fn,
            'perpage'       => $perpage,
        ]);
        if ($unenrolled_count > 0) {
            redirect($redirecturl, get_string('bulk_unenrolled', 'enrol_sepay', $unenrolled_count));
        } else {
            // Có ID được chọn nhưng không unenrol được ai (instance bị xóa hoặc lỗi kỹ thuật)
            redirect($redirecturl, get_string('error_already_processed', 'enrol_sepay'), null, core\output\notification::NOTIFY_ERROR);
        }
    } else {
        $redirecturl = new moodle_url('/enrol/sepay/transactions.php', ['filter' => $filter]);
        redirect($redirecturl, get_string('no_transactions_selected', 'enrol_sepay'), null, core\output\notification::NOTIFY_WARNING);
    }
}

// Xử lý action: Từ chối nhiều giao dịch
if ($action === 'bulk_reject' && confirm_sesskey()) {
    // Checkbox trong form dùng name="deleteids[]" cho cả 3 bulk actions.
    $rejectids = optional_param_array('deleteids', [], PARAM_INT);

    if (!empty($rejectids)) {
        $rejected_count = 0;
        $failed_count = 0;
        foreach ($rejectids as $txn_id) {
            $txn = $DB->get_record('enrol_sepay_transactions', ['id' => $txn_id]);
            if ($txn && $txn->status === 'pending') {
                $txn->status        = 'rejected';
                $txn->timeprocessed = time();
                try {
                    $DB->update_record('enrol_sepay_transactions', $txn);
                    \enrol_sepay\util::delete_pending_notifications($txn);
                    $rejected_count++;

                    // Gửi rejection notification cho student.
                    $r_user   = $DB->get_record('user',   ['id' => $txn->userid]);
                    $r_course = $DB->get_record('course', ['id' => $txn->courseid]);
                    if ($r_user && $r_course) {
                        try {
                            \enrol_sepay\util::send_rejection_notification($r_course, $r_user);
                            $DB->set_field('enrol_sepay_transactions', 'rejection_notified', 1, ['id' => $txn->id]);
                        } catch (\Exception $e) {
                            debugging('enrol_sepay bulk_reject: notification failed for id=' . $txn_id . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
                        }
                    }
                } catch (\dml_exception $e) {
                    debugging('enrol_sepay bulk_reject: update_record failed for id=' . $txn_id . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
                    $failed_count++;
                }
            }
        }


        $redirecturl = new moodle_url('/enrol/sepay/transactions.php', [
            'filter'        => $filter,
            'search_user'   => $search_user,
            'search_course' => $search_course,
            'date_from'     => $date_from,
            'date_to'       => $date_to,
            'letter'        => $letter,
            'letter_fn'     => $letter_fn,
            'perpage'       => $perpage,
        ]);
        redirect($redirecturl, get_string('bulk_rejected', 'enrol_sepay', $rejected_count));
    } else {
        $redirecturl = new moodle_url('/enrol/sepay/transactions.php', ['filter' => $filter]);
        redirect($redirecturl, get_string('no_transactions_selected', 'enrol_sepay'), null, core\output\notification::NOTIFY_WARNING);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manage_transactions', 'enrol_sepay'));

    // Gỡ bỏ tùy chỉnh CSS rác để Moodle table layout hiển thị nguyên bản.

// Lấy số liệu thống kê giao dịch
$count_pending    = $DB->count_records('enrol_sepay_transactions', ['status' => 'pending']);
$count_processed  = $DB->count_records('enrol_sepay_transactions', ['status' => 'processed']);
$count_rejected   = $DB->count_records('enrol_sepay_transactions', ['status' => 'rejected']);
$count_unenrolled = $DB->count_records('enrol_sepay_transactions', ['status' => 'unenrolled']);
$count_total      = $count_pending + $count_processed + $count_rejected + $count_unenrolled;

// Hiển thị thẻ thống kê TỔNG — Hàng riêng, to nhất
echo '<div class="row mx-0 mb-2">';
echo '  <div class="col-12">';
echo '    <div class="card text-center sepay-stat-total-border">';
echo '      <div class="card-body py-3">';
echo '        <h2 class="sepay-stat-total font-weight-bold mb-0 sepay-stat-number">' . $count_total . '</h2>';
echo '        <p class="sepay-stat-total mb-0 font-weight-bold">' . get_string('stat_total', 'enrol_sepay') . '</p>';
echo '      </div>';
echo '    </div>';
echo '  </div>';
echo '</div>';

// Hiển thị 4 thẻ thống kê còn lại — Chia đều hàng
echo '<div class="row mx-0 mb-3">';
$stat_items = [
    [
        'count' => $count_pending,
        'label' => get_string('stat_pending', 'enrol_sepay'),
        'class' => 'text-warning',
        'border' => 'border-warning',
    ],
    [
        'count' => $count_processed,
        'label' => get_string('stat_processed', 'enrol_sepay'),
        'class' => 'text-success',
        'border' => 'border-success',
    ],
    [
        'count' => $count_rejected,
        'label' => get_string('stat_rejected', 'enrol_sepay'),
        'class' => 'text-danger',
        'border' => 'border-danger',
    ],
    [
        'count' => $count_unenrolled,
        'label' => get_string('stat_unenrolled', 'enrol_sepay'),
        'class' => 'text-dark',
        'border' => 'border-dark',
    ],
];

foreach ($stat_items as $stat) {
    echo '<div class="col-6 col-md-3 mb-2">';
    echo '<div class="card text-center h-100 ' . $stat['border'] . '">';
    echo '<div class="card-body py-2">';
    echo '<h3 class="card-title ' . $stat['class'] . ' font-weight-bold mb-0">' . $stat['count'] . '</h3>';
    echo '<p class="card-text ' . $stat['class'] . ' small mb-0">' . $stat['label'] . '</p>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
}
echo '</div>';

// Khu vực bộ lọc (Search Options)
echo '<div class="card mb-3">';
echo '<div class="card-body py-3">';
echo '<form method="get" action="" id="filter-form">';
echo '<input type="hidden" name="filter" value="' . s($filter) . '">';
echo '<input type="hidden" name="letter" value="' . s($letter) . '">';
echo '<input type="hidden" name="letter_fn" value="' . s($letter_fn) . '">';

// Hàng nhập bộ lọc — dùng form-group
echo '<div class="d-flex flex-wrap align-items-end">';

echo '<div class="form-group mr-3 mb-2">';
echo '<label class="col-form-label col-form-label-sm d-block">' . get_string('search_user', 'enrol_sepay') . '</label>';
echo '<input type="text" name="search_user" class="form-control form-control-sm sepay-filter-input"'
   . ' value="' . s($search_user) . '" placeholder="' . s(get_string('search_user', 'enrol_sepay')) . '">';
echo '</div>';

echo '<div class="form-group mr-3 mb-2">';
echo '<label class="col-form-label col-form-label-sm d-block">' . get_string('search_course', 'enrol_sepay') . '</label>';
echo '<input type="text" name="search_course" class="form-control form-control-sm"'
   . ' value="' . s($search_course) . '" placeholder="' . s(get_string('search_course', 'enrol_sepay')) . '">';
echo '</div>';

echo '<div class="form-group mr-3 mb-2">';
echo '<label class="col-form-label col-form-label-sm d-block">' . get_string('filter_date_from', 'enrol_sepay') . '</label>';
echo '<input type="date" name="date_from" class="form-control form-control-sm" value="' . s($date_from) . '">';
echo '</div>';

echo '<div class="form-group mr-3 mb-2">';
echo '<label class="col-form-label col-form-label-sm d-block">' . get_string('filter_date_to', 'enrol_sepay') . '</label>';
echo '<input type="date" name="date_to" class="form-control form-control-sm" value="' . s($date_to) . '">';
echo '</div>';

// Nút Clear và Apply - gộp vào cùng hàng cho tiết kiệm không gian
echo '<div class="form-group mb-2 ml-auto d-flex align-items-end">';
$clearurl = new moodle_url('/enrol/sepay/transactions.php', ['filter' => $filter]);
echo '<a href="' . $clearurl->out() . '" class="btn btn-sm btn-secondary mr-2">'
   . get_string('clear_filter', 'enrol_sepay') . '</a>';
echo '<button type="submit" class="btn btn-sm btn-primary">'
   . get_string('apply_filter', 'enrol_sepay') . '</button>';
echo '</div>';

echo '</div>';
echo '</form>';
echo '</div>';
echo '</div>';

// Tab lọc theo trạng thái
// Mỗi tab dùng class chuẩn của Bootstrap 4 để tương thích với Moodle theme
// Các class CSS cho stat cards và tab lọc đã được định nghĩa trong styles.css.

$status_tabs = [
    'all'        => [get_string('all', 'moodle'),                    'sepay-tab-all',         'sepay-tab-outline-all'],
    'pending'    => [get_string('status_pending', 'enrol_sepay'),    'bg-warning text-dark',  'sepay-tab-outline-warning'],
    'processed'  => [get_string('status_processed', 'enrol_sepay'),  'bg-success',            'sepay-tab-outline-success'],
    'rejected'   => [get_string('status_rejected', 'enrol_sepay'),   'bg-danger',             'sepay-tab-outline-danger'],
    'unenrolled' => [get_string('status_unenrolled', 'enrol_sepay'), 'bg-dark',               'sepay-tab-outline-dark'],
];

// Hiển thị 5 tab lọc trạng thái — Layout 1-cái-đầu-ngang, 4-cái-dưới-chia-đều
echo '<ul class="nav nav-pills d-flex flex-wrap mb-3 mx-n1">';
foreach ($status_tabs as $tab_filter => [$tab_label, $class_active, $class_inactive]) {
    $tab_url = new moodle_url('/enrol/sepay/transactions.php', [
        'filter'        => $tab_filter,
        'search_user'   => $search_user,
        'search_course' => $search_course,
        'date_from'     => $date_from,
        'date_to'       => $date_to,
        'letter'        => $letter,
        'letter_fn'     => $letter_fn,
        'perpage'       => $perpage,
    ]);
    $is_active = ($filter === $tab_filter);

    // Tab "Tất cả" (all) full width hàng đầu, các tab khác chia hàng 4/hàng 2
    $item_class = ($tab_filter === 'all') ? 'col-12 px-1 mb-2' : 'col-6 col-md-3 px-1 mb-1';

    echo '<li class="nav-item ' . $item_class . '">';
    if ($is_active) {
        echo '<a href="' . $tab_url->out() . '" class="nav-link active ' . $class_active . ' font-weight-bold text-center">' . $tab_label . '</a>';
    } else {
        echo '<a href="' . $tab_url->out() . '" class="nav-link border ' . $class_inactive . ' text-center">' . $tab_label . '</a>';
    }
    echo '</li>';
}
echo '</ul>';

// Bộ lọc chữ cái — 2 hàng: Tên (firstname) và Họ (lastname)
// Dùng pagination pagination-sm theo đúng chuẩn Moodle initials_bar template
$filter_base_params = [
    'filter'        => $filter,
    'search_user'   => $search_user,
    'search_course' => $search_course,
    'date_from'     => $date_from,
    'date_to'       => $date_to,
];

// Hàng 1: lọc theo Tên (firstname) — class firstinitial theo chuẩn Moodle
echo '<div class="initialbar firstinitial d-flex flex-wrap justify-content-start">';
echo '<span class="initialbarlabel mr-2">' . get_string('filter_firstname', 'enrol_sepay') . '</span>';
echo '<nav class="initialbargroups d-flex flex-wrap">';
echo '<ul class="pagination pagination-sm">';

// Nút "Tất cả"
$all_fn_url = new moodle_url('/enrol/sepay/transactions.php',
    array_merge($filter_base_params, ['letter_fn' => '', 'letter' => $letter]));
$all_fn_active = ($letter_fn === '') ? ' active' : '';
echo '<li class="initialbarall page-item' . $all_fn_active . '">';
echo '<a data-initial="" class="page-link" href="' . $all_fn_url->out() . '"'
   . ($all_fn_active ? ' aria-current="true"' : '') . '>'
   . get_string('all', 'moodle') . '</a>';
echo '</li>';

echo '</ul>';

// Mỗi chữ cái trong một nhóm ul riêng (giống template Moodle chia nhóm)
foreach (array_chunk($vn_alphabet, 10) as $chunk) {
    echo '<ul class="pagination pagination-sm">';
    foreach ($chunk as $l) {
        $l_url = new moodle_url('/enrol/sepay/transactions.php',
            array_merge($filter_base_params, ['letter_fn' => $l, 'letter' => $letter]));
        $l_active = ($letter_fn === $l) ? ' active' : '';
        echo '<li data-initial="' . s($l) . '" class="page-item' . $l_active . '">';
        echo '<a class="page-link" href="' . $l_url->out() . '"'
           . ($l_active ? ' aria-current="true"' : '') . '>' . s($l) . '</a>';
        echo '</li>';
    }
    echo '</ul>';
}
echo '</nav>';
echo '</div>';

// Hàng 2: lọc theo Họ (lastname) — class lastinitial theo chuẩn Moodle
echo '<div class="initialbar lastinitial d-flex flex-wrap justify-content-start mb-2">';
echo '<span class="initialbarlabel mr-2">' . get_string('filter_lastname', 'enrol_sepay') . '</span>';
echo '<nav class="initialbargroups d-flex flex-wrap">';
echo '<ul class="pagination pagination-sm">';

$all_url = new moodle_url('/enrol/sepay/transactions.php',
    array_merge($filter_base_params, ['letter' => '', 'letter_fn' => $letter_fn]));
$all_active = ($letter === '') ? ' active' : '';
echo '<li class="initialbarall page-item' . $all_active . '">';
echo '<a data-initial="" class="page-link" href="' . $all_url->out() . '"'
   . ($all_active ? ' aria-current="true"' : '') . '>'
   . get_string('all', 'moodle') . '</a>';
echo '</li>';

echo '</ul>';

foreach (array_chunk($vn_alphabet, 10) as $chunk) {
    echo '<ul class="pagination pagination-sm">';
    foreach ($chunk as $l) {
        $l_url = new moodle_url('/enrol/sepay/transactions.php',
            array_merge($filter_base_params, ['letter' => $l, 'letter_fn' => $letter_fn]));
        $l_active = ($letter === $l) ? ' active' : '';
        echo '<li data-initial="' . s($l) . '" class="page-item' . $l_active . '">';
        echo '<a class="page-link" href="' . $l_url->out() . '"'
           . ($l_active ? ' aria-current="true"' : '') . '>' . s($l) . '</a>';
        echo '</li>';
    }
    echo '</ul>';
}
echo '</nav>';
echo '</div>';


// Khởi tạo bảng giao dịch
require_once($CFG->dirroot . '/enrol/sepay/classes/table/transactions_table.php');

$table = new \enrol_sepay\table\transactions_table('sepay_transactions', $PAGE->url, [
    'filter'        => $filter,
    'search_user'   => $search_user,
    'search_course' => $search_course,
    'date_from'     => $date_from,
    'date_to'       => $date_to,
    'letter'        => $letter,
    'letter_fn'     => $letter_fn,
]);

// Xây dựng câu truy vấn
$fields = "t.id, t.status, t.amount, t.currency, t.transaction_content,
           t.timecreated, t.timeprocessed, t.userid, t.courseid, t.ip_address,
           u.id AS uid, u.firstname, u.lastname, u.email,
           u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename,
           c.fullname AS coursename";

$from = "{enrol_sepay_transactions} t
    JOIN {user}   u ON u.id = t.userid
    JOIN {course} c ON c.id = t.courseid";

$where_parts = ['1=1'];
$params = [];

if ($filter !== 'all' && $filter !== '') {
    $where_parts[] = "t.status = :status";
    $params['status'] = $filter;
}
if ($search_user !== '') {
    $where_parts[] = $DB->sql_like(
        $DB->sql_concat("u.firstname", "' '", "u.lastname"),
        ':search_user', false
    );
    $params['search_user'] = '%' . $DB->sql_like_escape($search_user) . '%';
}
if ($search_course !== '') {
    $where_parts[] = $DB->sql_like('c.fullname', ':search_course', false);
    $params['search_course'] = '%' . $DB->sql_like_escape($search_course) . '%';
}
if ($date_from !== '') {
    $where_parts[] = "t.timecreated >= :date_from";
    $params['date_from'] = strtotime($date_from . ' 00:00:00');
}
if ($date_to !== '') {
    $where_parts[] = "t.timecreated <= :date_to";
    $params['date_to'] = strtotime($date_to . ' 23:59:59');
}
if ($letter !== '') {
    $where_parts[] = $DB->sql_like('u.lastname', ':letter', false);
    $params['letter'] = $DB->sql_like_escape($letter) . '%';
}
if ($letter_fn !== '') {
    $where_parts[] = $DB->sql_like('u.firstname', ':letter_fn', false);
    $params['letter_fn'] = $DB->sql_like_escape($letter_fn) . '%';
}

$where = implode(' AND ', $where_parts);

// Đếm tổng số kết quả (cho phân trang)
$transaction_count = $DB->count_records_sql(
    "SELECT COUNT(*) FROM $from WHERE $where",
    $params
);

$table->set_sql($fields, $from, $where, $params);
$table->pagesize($perpage, $transaction_count);

// Hiển thị số kết quả
echo '<div class="d-flex justify-content-between align-items-baseline mb-1">';
echo html_writer::tag('p',
    get_string('transactions_found', 'enrol_sepay', $transaction_count),
    ['data-region' => 'participant-count']
);
echo '</div>';

// Bọc toàn bộ khu vực bảng vào ID cố định để AMD module fetch partial HTML.
echo '<div id="sepay-table-container">';

if ($transaction_count == 0) {
    if ($filter === 'pending') {
        echo $OUTPUT->notification(get_string('no_pending_transactions', 'enrol_sepay'), 'info');
    } elseif ($filter === 'processed') {
        echo $OUTPUT->notification(get_string('no_processed_transactions_found', 'enrol_sepay'), 'info');
    } elseif ($filter === 'rejected') {
        echo $OUTPUT->notification(get_string('no_rejected_transactions_found', 'enrol_sepay'), 'info');
    } elseif ($filter === 'unenrolled') {
        echo $OUTPUT->notification(get_string('no_unenrolled_transactions_found', 'enrol_sepay'), 'info');
    } else {
        echo $OUTPUT->notification(get_string('no_transactions_found', 'enrol_sepay'), 'info');
    }
} else {
    // Form bao ngoài bảng để xử lý các thao tác hàng loạt.
    echo '<form method="post" action="" id="bulk-delete-form">';
    echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
    echo '<input type="hidden" name="filter" value="' . s($filter) . '">';
    echo '<input type="hidden" name="search_user" value="' . s($search_user) . '">';
    echo '<input type="hidden" name="search_course" value="' . s($search_course) . '">';
    echo '<input type="hidden" name="date_from" value="' . s($date_from) . '">';
    echo '<input type="hidden" name="date_to" value="' . s($date_to) . '">';
    echo '<input type="hidden" name="letter" value="' . s($letter) . '">';
    echo '<input type="hidden" name="letter_fn" value="' . s($letter_fn) . '">';
    echo '<input type="hidden" name="perpage" value="' . (int)$perpage . '">';

    // Các nút bulk action (đầu bảng, disabled cho đến khi có checkbox được chọn).
    echo '<div class="d-flex flex-wrap mb-2">';
    if ($filter === 'all' || $filter === 'pending') {
        echo html_writer::tag('button', get_string('bulk_approve', 'enrol_sepay'), [
            'type'             => 'button',
            'class'            => 'btn btn-outline-secondary btn-sm mr-2 mb-1',
            'data-bulk-action' => 'bulk_approve',
            'disabled'         => 'disabled',
        ]);
        echo html_writer::tag('button', get_string('bulk_reject', 'enrol_sepay'), [
            'type'             => 'button',
            'class'            => 'btn btn-outline-secondary btn-sm mr-2 mb-1',
            'data-bulk-action' => 'bulk_reject',
            'disabled'         => 'disabled',
        ]);
    }
    if ($filter === 'all' || $filter === 'processed') {
        echo html_writer::tag('button', get_string('bulk_unenrol', 'enrol_sepay'), [
            'type'             => 'button',
            'class'            => 'btn btn-outline-secondary btn-sm mr-2 mb-1',
            'data-bulk-action' => 'bulk_unenrol',
            'disabled'         => 'disabled',
        ]);
    }
    echo html_writer::tag('button', get_string('bulk_delete', 'enrol_sepay'), [
        'type'             => 'button',
        'class'            => 'btn btn-outline-secondary btn-sm mr-2 mb-1',
        'data-bulk-action' => 'bulk_delete',
        'disabled'         => 'disabled',
    ]);
    echo '</div>';

    // Render bảng qua table_sql.
    $table->out($perpage, false);

    echo '<div class="sepay-table-margin-top">';

    // Link toggle "Xem tất cả X" / "Xem N mỗi trang" — AMD intercept qua data-perpage-toggle.
    if ((int)$perpage >= 1000) {
        $url = new moodle_url($PAGE->url);
        $url->param('perpage', $table->get_default_per_page());
        echo html_writer::link($url->out(), get_string('showperpage', '', $table->get_default_per_page()), [
            'class'              => 'text-danger mb-3 d-inline-block sepay-underline',
            'data-perpage-toggle' => '1',
        ]);
    } else if ($transaction_count > $perpage) {
        $url = new moodle_url($PAGE->url);
        $url->param('perpage', TABLE_SHOW_ALL_PAGE_SIZE);
        echo html_writer::link($url->out(), get_string('showall', '', $transaction_count), [
            'class'              => 'text-danger mb-3 d-inline-block sepay-underline',
            'data-perpage-toggle' => '1',
        ]);
    }

    // Khu vực "Chọn tất cả..." và dropdown "Với các mục được chọn...".
    echo '<br /><div class="buttons"><div class="form-inline">';

    echo html_writer::start_tag('div', ['class' => 'btn-group']);
    if ($transaction_count > $perpage && $transaction_count > 0) {
        $label = get_string('selectalluserswithcount', 'moodle', $transaction_count);
        $showall_url = new moodle_url($PAGE->url);
        $showall_url->param('perpage', TABLE_SHOW_ALL_PAGE_SIZE);
        echo html_writer::empty_tag('input', [
            'type'              => 'button',
            'class'             => 'btn btn-secondary',
            'value'             => $label,
            'data-checkall-btn' => '1',
            'data-href'         => $showall_url->out(false),
        ]);
    }
    echo html_writer::end_tag('div');

    if ($transaction_count > 0) {
        $displaylist = [];

        if (!empty($CFG->messaging) && has_all_capabilities(['moodle/site:sendmessage', 'moodle/course:bulkmessaging'], $context)) {
            $displaylist['#messageselect'] = get_string('messageselectadd');
        }

        $download_options = [];
        $formats = core_plugin_manager::instance()->get_plugins_of_type('dataformat');
        foreach ($formats as $format) {
            if ($format->is_enabled()) {
                $dl_url = new moodle_url('/enrol/sepay/download.php', [
                    'dataformat'    => $format->name,
                    'filter'        => $filter,
                    'search_user'   => $search_user,
                    'search_course' => $search_course,
                    'date_from'     => $date_from,
                    'date_to'       => $date_to,
                    'letter'        => $letter,
                    'letter_fn'     => $letter_fn,
                    'sesskey'       => sesskey(),
                ]);
                $download_options[$dl_url->out(false)] = get_string('dataformat', $format->component);
            }
        }
        if (!empty($download_options)) {
            $displaylist[] = [get_string('downloadas', 'table') => $download_options];
        }

        $label  = html_writer::tag('label', get_string('withselectedusers'), ['for' => 'formactionid', 'class' => 'col-form-label d-inline']);
        $select = html_writer::select($displaylist, 'action', '', ['' => 'choosedots'], [
            'id'               => 'formactionid',
            'class'            => 'ml-2',
            'data-action'      => 'toggle',
            'data-togglegroup' => 'participants-table',
            'data-toggle'      => 'action',
            'disabled'         => 'disabled',
        ]);
        echo html_writer::tag('div', $label . $select);
    }

    echo '</div></div></div>'; // Đóng form-inline, buttons, sepay-table-margin-top.

    echo '</form>';

    echo '</div>'; // Đóng div#sepay-table-container.

    // Khởi tạo AMD module xử lý bulk actions — CSP compliant, không dùng inline <script>.
    $confirm_string_keys = [
        ['action' => 'bulk_approve', 'key' => 'confirm_bulk_approve'],
        ['action' => 'bulk_reject',  'key' => 'confirm_bulk_reject'],
        ['action' => 'bulk_unenrol', 'key' => 'confirm_bulk_unenrol'],
        ['action' => 'bulk_delete',  'key' => 'confirm_bulk_delete'],
        ['action' => 'reject',       'key' => 'confirm_reject'],
        ['action' => 'delete',       'key' => 'confirm_delete'],
    ];
    $PAGE->requires->js_call_amd('enrol_sepay/transactions_bulk', 'init', [
        $confirm_string_keys,
        'no_transactions_selected',
    ]);
}

echo $OUTPUT->footer();
