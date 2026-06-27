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
 * Lớp tiện ích cho plugin enrol_sepay: log lỗi webhook + gửi email/thông báo.
 *
 * @package    enrol_sepay
 * @copyright  2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_sepay;

defined('MOODLE_INTERNAL') || die();

/**
 * Lớp tiện ích hỗ trợ xử lý lỗi.
 */
class util {
    /**
     * Xóa thông báo "chờ xử lý" của một giao dịch cụ thể khỏi chuông thông báo của admin.
     *
     * @param \stdClass $txn Bản ghi giao dịch.
     * @return void
     */
    public static function delete_pending_notifications(\stdClass $txn): void {
        global $DB;
        try {
            // Xóa đúng chuông của giao dịch này (đối chiếu txnid nhúng trong customdata),
            // KHÔNG xóa toàn bộ pending của user — tránh admin bỏ sót các giao dịch khác.
            $like = $DB->sql_like('customdata', ':cd');
            $DB->delete_records_select(
                'notifications',
                "component = :comp AND eventtype = :evt AND $like",
                [
                    'comp' => 'enrol_sepay',
                    'evt'  => 'pending_transaction',
                    'cd'   => '%' . $DB->sql_like_escape('"txnid":"' . (int)$txn->id . '"') . '%',
                ]
            );
        } catch (\Exception $e) {
            // Không làm gián đoạn luồng chính; ghi log mức developer để truy vết.
            debugging('enrol_sepay: xóa pending notifications thất bại — ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Gửi thông báo lỗi liên quan đến webhook/thanh toán cho admin, và ghi log.
     *
     * @param string $msg Thông báo lỗi ngắn gọn.
     * @param array|object|null $data Dữ liệu đính kèm (payload webhook, v.v.).
     * @return void
     */
    public static function message_sepay_error_to_admin(string $msg, $data = null): void {
        global $CFG;

        // Ghi log mức developer (debugging) thay cho error_log — tránh ghi payload ra file log dùng chung.
        $logprefix = '[enrol_sepay] ';
        if ($data !== null) {
            $msg .= ' | data: ' . json_encode($data);
        }
        debugging($logprefix . $msg, DEBUG_DEVELOPER);

        // Gửi email cho admin site, tương tự cách Paypal thông báo lỗi.
        $admins = get_admins();
        if (!$admins) {
            return;
        }

        $site = get_site();
        $subject = 'SePay enrolment error on ' .
            format_string($site->fullname, true, ['context' => \context_system::instance()]);
        $body = "Thông báo lỗi từ plugin enrol_sepay:\n\n";
        $body .= $msg . "\n\n";
        if ($data !== null) {
            $body .= 'Payload:' . "\n" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        }

        foreach ($admins as $admin) {
            email_to_user($admin, \core_user::get_support_user(), $subject, $body);
        }
    }

    /**
     * Send welcome messages to students, teachers, and admins after successful enrolment.
     * Check plugin config before sending.
     *
     * @param \stdClass $course
     * @param \stdClass $user
     * @param \stdClass $instance
     * @return bool true nếu đã gửi (ít nhất 1 kênh mail bật), false nếu no-op (tắt hết mail config).
     */
    public static function send_welcome_messages($course, $user, $instance): bool {
        global $CFG;

        $plugin = enrol_get_plugin('sepay');
        if (!$plugin) {
            return false;
        }

        $mailstudents = $plugin->get_config('mailstudents', 0);
        $mailteachers = $plugin->get_config('mailteachers');
        $mailadmins   = $plugin->get_config('mailadmins');

        if (!$mailstudents && !$mailteachers && !$mailadmins) {
            return false;
        }

        $context = \context_course::instance($course->id);
        $courseurl = (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false);
        $profileurl = (new \moodle_url('/user/view.php', ['id' => $user->id, 'course' => $course->id]))->out(false);

        $a = new \stdClass();
        $a->coursename = format_string($course->fullname, true, ['context' => $context]);
        $a->profileurl = $profileurl;
        $a->username = fullname($user, true);
        $a->useremail = $user->email;
        $a->courseurl = $courseurl;

        // Tránh 1 người nhận 3 thông báo nếu họ vừa là Học viên, Giáo viên và Admin (Lọc trùng lặp)
        $notified_users = [];

        // Tạo Mẫu HTML thông qua Static Methods để tái sử dụng ở môi trường Live & Test
        $html_student = self::get_student_email_html($a);
        $html_alert   = self::get_admin_email_html($a);

        // 1. Mail to Student
        if ($mailstudents) {
            $contact = get_admin();

            // Bell/popup notification qua Moodle messaging.
            $msg = new \core\message\message();
            $msg->component         = 'enrol_sepay';
            $msg->name              = 'sepay_enrolment';
            $msg->userfrom          = $contact;
            $msg->userto            = $user;
            $msg->subject           = get_string('email_welcome_subject', 'enrol_sepay', $a);
            $msg->fullmessage       = get_string('email_welcome_body', 'enrol_sepay', $a);
            $msg->fullmessageformat = FORMAT_PLAIN;
            $msg->smallmessage      = $msg->subject;
            $msg->notification      = 1;
            $msg->contexturl        = $courseurl;
            $msg->contexturlname    = $a->coursename;
            $msg->courseid          = $course->id;
            message_send($msg);

            // Email trực tiếp với HTML tùy chỉnh — tránh Moodle wrapper và footer mobile app.
            email_to_user(
                $user,
                $contact,
                get_string('email_welcome_subject', 'enrol_sepay', $a),
                get_string('email_welcome_body', 'enrol_sepay', $a),
                $html_student
            );

            $notified_users[] = $user->id;
        }

        // 2. Mail to Teachers
        if ($mailteachers) {
            if ($teachers = get_enrolled_users($context, 'moodle/course:update', 0, 'u.*', null, 0, 0, true)) {
                $contact = get_admin();
                foreach ($teachers as $teacher) {
                    // Nếu giáo viên cũng chính là người vừa được ghi danh (hoặc đã nhận mail rồi) thì bỏ qua!
                    if (in_array($teacher->id, $notified_users)) {
                        continue;
                    }

                    // Bell notification.
                    $msg = new \core\message\message();
                    $msg->component         = 'enrol_sepay';
                    $msg->name              = 'sepay_enrolment';
                    $msg->userfrom          = $contact;
                    $msg->userto            = $teacher;
                    $msg->subject           = get_string('email_teacher_subject', 'enrol_sepay', $a);
                    $msg->fullmessage       = get_string('email_teacher_body', 'enrol_sepay', $a);
                    $msg->fullmessageformat = FORMAT_PLAIN;
                    $msg->smallmessage      = $msg->subject;
                    $msg->notification      = 1;
                    $msg->contexturl        = $profileurl;
                    $msg->contexturlname    = $a->username;
                    $msg->courseid          = $course->id;
                    message_send($msg);

                    // Email trực tiếp với HTML tùy chỉnh.
                    email_to_user(
                        $teacher,
                        $contact,
                        get_string('email_teacher_subject', 'enrol_sepay', $a),
                        get_string('email_teacher_body', 'enrol_sepay', $a),
                        $html_alert
                    );
                    $notified_users[] = $teacher->id;
                }
            }
        }

        // 3. Mail to Admins
        if ($mailadmins) {
            $admins = get_admins();
            $contact = get_admin();
            foreach ($admins as $admin) {
                // Nếu Admin cũng là Giáo viên hoặc là người đang test thì bỏ qua để tránh spam!
                if (in_array($admin->id, $notified_users)) {
                    continue;
                }

                // Bell notification.
                $msg = new \core\message\message();
                $msg->component         = 'enrol_sepay';
                $msg->name              = 'sepay_enrolment';
                $msg->userfrom          = $contact;
                $msg->userto            = $admin;
                $msg->subject           = get_string('email_teacher_subject', 'enrol_sepay', $a);
                $msg->fullmessage       = get_string('email_teacher_body', 'enrol_sepay', $a);
                $msg->fullmessageformat = FORMAT_PLAIN;
                $msg->smallmessage      = $msg->subject;
                $msg->notification      = 1;
                $msg->contexturl        = $profileurl;
                $msg->contexturlname    = $a->username;
                $msg->courseid          = $course->id;
                message_send($msg);

                // Email trực tiếp với HTML tùy chỉnh.
                email_to_user(
                    $admin,
                    $contact,
                    get_string('email_teacher_subject', 'enrol_sepay', $a),
                    get_string('email_teacher_body', 'enrol_sepay', $a),
                    $html_alert
                );
            }
        }

        return true;
    }

    /**
     * Lọc và trích xuất đúng phần Tên học viên từ các định dạng phức tạp
     * VD: "2273401210132 - Võ Lê Thu Phương - 71K28KDTM02" -> "Võ Lê Thu Phương"
     */
    public static function clean_student_name($raw_name) {
        $raw_name = trim($raw_name);
        if (strpos($raw_name, ' - ') !== false) {
            $parts = explode(' - ', $raw_name);
            $parts = array_map('trim', $parts);

            // Xóa đi các mẩu bị trống do dư dấu cách
            $parts = array_values(array_filter($parts, function ($val) {
                return $val !== '';
            }));

            if (count($parts) >= 3) {
                // Return phần giữa (Thường cấu trúc là ID - Tên - Lớp)
                return $parts[1];
            } else if (count($parts) == 2) {
                // Nếu là "ID - Tên"
                if (is_numeric($parts[0])) {
                    return $parts[1];
                }
                return $parts[0];
            }
        }
        return $raw_name;
    }

    /**
     * Trả về URL hosted cho ảnh logo trong pix/.
     * Dùng URL tuyệt đối từ wwwroot thay cho base64 data URI
     * vì Gmail và Outlook block embedded data URIs trong email.
     *
     * @param string $filename  Tên file trong pix/ (vd. gmail_icon.png)
     * @param int    $w         Không dùng, giữ lại để backward-compatible
     * @param int    $h         Không dùng, giữ lại để backward-compatible
     * @return string  URL tuyệt đối hoặc chuỗi rỗng nếu wwwroot chưa sẵn sàng.
     */
    public static function logo_data_uri(string $filename, int $w, int $h): string {
        global $CFG;
        if (empty($CFG->wwwroot)) {
            return '';
        }
        $path = __DIR__ . '/../pix/' . $filename;
        if (!file_exists($path)) {
            return '';
        }
        return rtrim($CFG->wwwroot, '/') . '/enrol/sepay/pix/' . rawurlencode($filename);
    }

    /**
     * Dò tìm nền tảng Email (Gmail / Outlook) và trả về label với logo hosted URL.
     */
    public static function get_email_platform_label($email) {
        if (stripos($email, '@vanlanguni.vn') !== false || stripos($email, '@outlook.') !== false || stripos($email, '@hotmail.') !== false) {
            $uri = self::logo_data_uri('outlook_icon.png', 16, 16);
            $img = $uri ? '<img src="' . $uri . '" width="16" height="16" style="vertical-align: middle; margin-right: 6px;" alt="Outlook">' : '';
            return $img . 'Outlook:';
        } else if (stripos($email, '@gmail.com') !== false) {
            $uri = self::logo_data_uri('gmail_icon.png', 16, 16);
            $img = $uri ? '<img src="' . $uri . '" width="16" height="16" style="vertical-align: middle; margin-right: 6px;" alt="Gmail">' : '';
            return $img . 'Gmail:';
        }
        return '📧 Email:';
    }

    /**
     * BẢN VẼ HTML UI SENIOR UX DÀNH CHO HỌC VIÊN
     */
    public static function get_student_email_html($a) {
        $clean_name     = htmlspecialchars(self::clean_student_name($a->username), ENT_QUOTES, 'UTF-8');
        $coursename     = htmlspecialchars($a->coursename, ENT_QUOTES, 'UTF-8');
        $useremail      = htmlspecialchars($a->useremail, ENT_QUOTES, 'UTF-8');
        $courseurl      = htmlspecialchars($a->courseurl, ENT_QUOTES, 'UTF-8');
        $platform_label = self::get_email_platform_label($a->useremail);
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="vi" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
        <head>
            <meta charset="UTF-8">
            <title>Thông báo từ hệ thống</title>
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
            <meta name="color-scheme" content="light dark">
            <meta name="supported-color-schemes" content="light dark">
            <!--[if mso]>
            <xml>
                <o:OfficeDocumentSettings>
                    <o:AllowPNG/>
                    <o:PixelsPerInch>96</o:PixelsPerInch>
                </o:OfficeDocumentSettings>
            </xml>
            <![endif]-->
            <style>
                :root {
                    color-scheme: light dark;
                    supported-color-schemes: light dark;
                }
                @media (prefers-color-scheme: dark) {
                    .body-bg { background-color: #020617 !important; }
                    .content-bg { background-color: #0f172a !important; border-color: #1e293b !important; border-bottom-color: #1e293b !important; }
                    .text-main { color: #f8fafc !important; text-shadow: none !important; }
                    .text-sub { color: #94a3b8 !important; }
                    .card-bg-red { background-color: #2e1014 !important; border-color: #4c1d24 !important; border-top-color: #3b1419 !important; border-bottom-color: #1a080b !important; box-shadow: none !important; }
                    .card-bg-blue { background-color: #0d1b2a !important; border-color: #1e3a8a !important; border-top-color: #172554 !important; border-bottom-color: #080f1a !important; box-shadow: none !important; }
                    .dashed-border { border-top-color: #334155 !important; border-bottom-color: #334155 !important; }
                    .footer-text { color: #64748b !important; }
                    .highlight-red { color: #ef4444 !important; }
                    .icon-bg { background-color: rgba(0,0,0,0.2) !important; border-color: rgba(255,255,255,0.1) !important; }
                    .divider-line { background-color: #334155 !important; }
                }
                /* Prevent font boosting in Gmail */
                body, table, td, p, a, li, blockquote {
                    -webkit-text-size-adjust: 100%;
                    -ms-text-size-adjust: 100%;
                }
                /* RESPONSIVE STYLES */
                @media screen and (max-width: 600px) {
                    .stack-mobile { display: block !important; width: 100% !important; min-width: 100% !important; max-width: 100% !important; float: none !important; text-align: left !important; box-sizing: border-box !important; }
                    .value-mobile { float: none !important; text-align: left !important; max-width: 100% !important; padding-top: 4px !important; }
                    .padding-mobile { padding: 25px 20px 20px 20px !important; }
                    .card-padding-mobile { padding: 0 20px 25px 20px !important; }
                    .wrapper-padding-mobile { padding: 20px 10px !important; }
                    .header-mobile { padding: 28px 20px !important; }
                    .cta-button { padding: 14px 28px !important; font-size: 15px !important; white-space: normal !important; }
                }
            </style>
        </head>
        <body class="body-bg" style="margin: 0; padding: 0; background-color: #f3f4f6; -webkit-font-smoothing: antialiased; word-break: break-word;">
            <div style="display:none;font-size:1px;color:#f3f4f6;line-height:1px;max-height:0px;max-width:0px;opacity:0;overflow:hidden;">Hệ thống đã xác nhận thanh toán và hoàn tất thủ tục ghi danh. Chúc mừng bạn đã chính thức tham gia khóa học...</div>
            <table class="body-bg" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="background-color: #f3f4f6; margin: 0; padding: 0; width: 100%; table-layout: fixed;">
                <tr>
                    <td align="center" class="wrapper-padding-mobile" style="padding: 25px 15px;">
                        <!--[if mso]>
                <table align="center" width="600" border="0" cellpadding="0" cellspacing="0" role="presentation">
                <tr><td>
                <![endif]-->
                <table class="content-bg" align="center" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="max-width: 600px; background-color: #ffffff; border-radius: 24px; overflow: hidden; border: 1px solid #e2e8f0; border-bottom: 4px solid #cbd5e1; box-shadow: 0 15px 35px rgba(0,0,0,0.05); border-collapse: separate; table-layout: fixed; mso-table-lspace: 0pt; mso-table-rspace: 0pt;">
                    
                    <!-- HEADER -->
                    <tr>
                        <td class="header-mobile" style="background-color: #d72134; background-image: linear-gradient(150deg, #f24f60 0%, #d72134 40%, #941322 100%); padding: 45px 25px; text-align: center; border-bottom: 5px solid #7d101c; border-top: 1px solid #ff8f9c; box-shadow: inset 0 -10px 20px rgba(0,0,0,0.15), inset 0 2px 10px rgba(255,255,255,0.3);">
                            <div class="icon-bg" style="background: rgba(255, 255, 255, 0.1); border-top: 2px solid rgba(255, 255, 255, 0.5); border-left: 2px solid rgba(255, 255, 255, 0.3); border-right: 1px solid rgba(255,255,255,0.05); border-bottom: 1px solid rgba(255,255,255,0.05); width: 76px; height: 76px; border-radius: 40%; display: inline-block; margin-bottom: 25px; box-shadow: 0 12px 25px rgba(125, 16, 28, 0.5), inset 0 4px 12px rgba(255,255,255,0.4); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);">
                                <p style="font-size: 38px; margin: 0; line-height: 76px; display: block; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3));">🚀</p>
                            </div>
                            <h1 style="color: #ffffff; font-size: 28px; font-weight: 700; margin: 0; letter-spacing: 1px; text-transform: uppercase; text-shadow: 0 2px 4px rgba(0,0,0,0.2); font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">XÁC NHẬN GHI DANH!</h1>
                        </td>
                    </tr>

                    <!-- CONTENT -->
                    <tr>
                        <td class="padding-mobile" style="padding: 35px 30px 25px 30px; font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                            <p class="text-main" style="font-size: 19px; color: #1e293b; margin: 0 0 15px 0; line-height: 1.5; font-weight: 700;">Xin chào <span class="highlight-red" style="color: #d72134;"><?= $clean_name ?></span> 👋,</p>
                            <p class="text-sub" style="font-size: 16px; color: #475569; margin: 0; line-height: 1.7; text-align: justify;">🎉 Hệ thống đã xác nhận thanh toán và hoàn tất thủ tục ghi danh. Chúc mừng bạn đã chính thức tham gia khóa học này, hãy sẵn sàng ôn tập thật tốt để đạt kết quả cao trong kỳ thi sắp tới cùng Quiz Văn Lang nhé!</p>
                        </td>
                    </tr>

                    <!-- COURSE CARD -->
                    <tr>
                        <td class="card-padding-mobile" style="padding: 0 30px 30px 30px; font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                            <div class="card-bg-red" style="background-color: #fce8ea; border: 1px solid #fad1d5; border-top: 2px solid #ffffff; border-bottom: 3px solid #f4a2ab; border-radius: 20px; padding: 25px; box-shadow: 0 4px 15px rgba(215, 33, 52, 0.05), inset 0 2px 0 rgba(255,255,255,0.6);">
                                
                                <div style="text-align: center; margin-bottom: 20px;">
                                    <span style="display: inline-block; background: #d72134; color: #ffffff; font-size: 13px; font-weight: 700; padding: 6px 16px; border-radius: 20px; text-transform: uppercase; letter-spacing: 1px; box-shadow: 0 4px 8px rgba(215, 33, 52, 0.2);">📚 Thông tin khóa học</span>
                                </div>
                                
                                <strong class="highlight-red" style="color: #bd1a2c; display: block; font-size: 24px; line-height: 1.4; font-weight: 700; text-align: center; margin-bottom: 25px; text-shadow: 0 1px 1px rgba(255,255,255,0.8); font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;"><?= $coursename ?></strong>
                                
                                <div class="dashed-border" style="border-top: 1px dashed #f4a2ab; padding: 12px 0; overflow: hidden; clear: both;">
                                    <!--[if mso]><table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation"><tr><td width="35%" align="left" valign="top"><![endif]-->
                                    <span class="stack-mobile" style="font-size: 15px; color: #64748b; font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">🗓️ Thời gian:</span>
                                    <!--[if mso]></td><td width="65%" align="right" valign="top"><![endif]-->
                                    <span class="stack-mobile value-mobile" style="font-size: 15px; font-weight: 700; color: #0f172a; float: right; text-align: right; word-break: break-word; max-width: 65%; font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;"><?= date('d/m/Y - H:i') ?></span>
                                    <!--[if mso]></td></tr></table><![endif]-->
                                </div>

                                <div class="dashed-border" style="border-top: 1px dashed #f4a2ab; padding: 12px 0; overflow: hidden; clear: both;">
                                    <!--[if mso]><table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation"><tr><td width="35%" align="left" valign="top"><![endif]-->
                                    <span class="stack-mobile" style="font-size: 15px; color: #64748b; font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">👤 Học viên:</span>
                                    <!--[if mso]></td><td width="65%" align="right" valign="top"><![endif]-->
                                    <span class="stack-mobile value-mobile" style="font-size: 15px; font-weight: 700; color: #0f172a; float: right; text-align: right; word-break: break-word; max-width: 65%; font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;"><?= $clean_name ?></span>
                                    <!--[if mso]></td></tr></table><![endif]-->
                                </div>

                                <div class="dashed-border" style="border-top: 1px dashed #f4a2ab; padding: 12px 0 0 0; overflow: hidden; clear: both;">
                                    <!--[if mso]><table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation"><tr><td width="35%" align="left" valign="top"><![endif]-->
                                    <span class="stack-mobile" style="font-size: 15px; color: #64748b; font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;"><?= $platform_label ?></span>
                                    <!--[if mso]></td><td width="65%" align="right" valign="top"><![endif]-->
                                    <span class="stack-mobile value-mobile" style="font-size: 15px; font-weight: 700; color: #0f172a; float: right; text-align: right; word-break: break-all; max-width: 65%; font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;"><?= $useremail ?></span>
                                    <!--[if mso]></td></tr></table><![endif]-->
                                </div>
                            </div>
                        </td>
                    </tr>

                    <!-- CTA -->
                    <tr>
                        <td class="card-padding-mobile" style="padding: 0 30px 40px 30px; text-align: center; font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                            <!--[if mso]>
                            <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="<?= $courseurl ?>" style="height:56px;v-text-anchor:middle;width:260px;" arcsize="50%" strokecolor="#941322" strokeweight="2px" fillcolor="#d72134">
                                <w:anchorlock/>
                                <center style="color:#ffffff;font-family:'Segoe UI', sans-serif;font-size:16px;font-weight:bold;">VÀO HỌC NGAY</center>
                            </v:roundrect>
                            <![endif]-->
                            <!--[if !mso]><!-->
                            <a href="<?= $courseurl ?>" class="cta-button" target="_blank" style="display: inline-block; background-color: #d72134; background-image: linear-gradient(135deg, rgba(255,255,255,0.4) 0%, rgba(255,255,255,0.05) 45%, rgba(0,0,0,0.05) 55%, rgba(0,0,0,0.15) 100%); color: #ffffff; text-decoration: none; font-size: 16px; font-weight: 700; padding: 15px 36px; border-radius: 50px; border: 1px solid rgba(255, 255, 255, 0.5); box-shadow: 0 10px 30px -5px rgba(215, 33, 52, 0.4), inset 0 2px 4px rgba(255, 255, 255, 0.6), inset 0 -4px 6px rgba(0, 0, 0, 0.2), inset 0 0 10px rgba(255,255,255,0.1); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); text-transform: uppercase; letter-spacing: 1.5px; transition: all 0.3s ease; white-space: nowrap;"><span style="text-shadow: 0 2px 4px rgba(0,0,0,0.3);">Vào Học Ngay</span></a>
                            <!--<![endif]-->
                        </td>
                    </tr>

                    <?php if (stripos($a->useremail, '@vanlanguni.vn') === false) : ?>
                    <!-- COMMUNITY BOX (ZALO) -->
                    <tr>
                        <td class="card-padding-mobile" style="padding: 0 30px 30px 30px; font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="border-collapse: separate; border-spacing: 0;">
                                <tr>
                                    <td class="card-bg-blue" style="background-color: #eff6ff; border: 1px solid #dbeafe; border-top: 2px solid #ffffff; border-bottom: 3px solid #bfdbfe; border-radius: 20px; padding: 30px 25px; text-align: center; box-shadow: 0 4px 15px rgba(59, 130, 246, 0.05), inset 0 2px 0 rgba(255,255,255,0.6);">
                                        <?php $zalo_uri = self::logo_data_uri('zalo_icon.png', 60, 60); ?>
                                        <?php if ($zalo_uri) : ?>
                                        <img src="<?= $zalo_uri ?>" width="60" height="60" alt="Zalo Logo" style="display: block; margin: 0 auto 15px auto; border-radius: 16px; filter: drop-shadow(0 4px 10px rgba(0,0,0,0.15));">
                                        <?php else : ?>
                                        <div style="width:60px;height:60px;margin:0 auto 15px auto;background:#0068ff;border-radius:16px;display:flex;align-items:center;justify-content:center;"><span style="color:#fff;font-size:28px;font-weight:900;line-height:60px;text-align:center;display:block;">Z</span></div>
                                        <?php endif; ?>
                                        <h4 class="text-main" style="margin: 0 0 10px 0; color: #1e3a8a; font-size: 18px; font-weight: 700; text-shadow: 0 1px 1px rgba(255,255,255,0.8);">Cộng Đồng Quiz Văn Lang</h4>
                                        <p class="text-sub" style="font-size: 15px; color: #3b82f6; margin: 0 0 20px 0; line-height: 1.5;">Tham gia cộng đồng bên dưới<br>để không bỏ lỡ các khóa học mới nhất nhé!</p>
                                        <!--[if mso]>
                                        <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="https://zalo.me/g/eds5dzskgzc7eu4essgc" style="height:44px;v-text-anchor:middle;width:180px;" arcsize="50%" strokecolor="#1e3a8a" strokeweight="2px" fillcolor="#2563eb">
                                            <w:anchorlock/>
                                            <center style="color:#ffffff;font-family:'Segoe UI', sans-serif;font-size:14px;font-weight:bold;">Tham Gia Ngay</center>
                                        </v:roundrect>
                                        <![endif]-->
                                        <!--[if !mso]><!-->
                                        <a href="https://zalo.me/g/eds5dzskgzc7eu4essgc" target="_blank" style="display: inline-block; background-color: #2563eb; background-image: linear-gradient(135deg, rgba(255,255,255,0.4) 0%, rgba(255,255,255,0.05) 45%, rgba(0,0,0,0.05) 55%, rgba(0,0,0,0.15) 100%); color: #ffffff; font-size: 14px; font-weight: 700; text-decoration: none; padding: 14px 35px; border-radius: 50px; border: 1px solid rgba(255, 255, 255, 0.5); box-shadow: 0 10px 25px -5px rgba(37, 99, 235, 0.4), inset 0 2px 4px rgba(255, 255, 255, 0.6), inset 0 -3px 5px rgba(0, 0, 0, 0.2), inset 0 0 10px rgba(255,255,255,0.1); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); text-transform: uppercase; letter-spacing: 1px; transition: all 0.3s ease;"><span style="text-shadow: 0 2px 4px rgba(0,0,0,0.3);">Tham Gia Ngay</span></a>
                                        <!--<![endif]-->
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <?php endif; ?>

                    <!-- FOOTER -->
                    <tr>
                        <td class="content-bg" style="background-color: #f8fafc; padding: 30px 25px; text-align: center; border-top: 1px solid #e2e8f0; font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                            <?php if (stripos($a->useremail, '@vanlanguni.vn') === false) : ?>
                            <p class="footer-text" style="font-size: 14px; color: #64748b; margin: 0; line-height: 1.5;">💡 Nếu chưa vào được khóa học, inbox Zalo Admin:<br><a href="https://zalo.me/0588325106" target="_blank" style="color: #2563eb; text-decoration: none; font-weight: 700; background: #e0e7ff; padding: 4px 12px; border-radius: 12px; display: inline-block; margin-top: 8px;">0588.325.106</a></p>
                            <?php else : ?>
                            <p class="footer-text" style="font-size: 14px; color: #64748b; margin: 0; line-height: 1.5;">💡 Nếu chưa vào được khóa học, liên hệ SĐT Zalo Admin:<br><strong style="color: #475569; display: inline-block; margin-top: 8px;">0588.325.106</strong></p>
                            <?php endif; ?>
                            <div class="divider-line" style="width: 50px; height: 2px; background-color: #cbd5e1; margin: 20px auto; border-radius: 2px;"></div>
                            <p class="footer-text" style="font-size: 12px; color: #94a3b8; margin: 0; letter-spacing: 0.5px; text-transform: uppercase;">&copy; <?= date('Y') ?> <strong>Quiz Văn Lang</strong>. Mọi quyền được bảo lưu.</p>
                        </td>
                    </tr>
                </table>
                <!--[if mso]>
                </td></tr>
                </table>
                <![endif]-->
                    </td>
                </tr>
            </table>
            
            <!-- HIDDEN MOODLE APP WRAPPER TRICK -->
            <div style="display: none !important; mso-hide: all; visibility: hidden; width: 0; height: 0; font-size: 0;">
                <table border="0" cellpadding="0" cellspacing="0" role="presentation"><tr><td>
                </td></tr></table>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * BẢN VẼ HTML UI SENIOR UX DÀNH CHO ADMIN
     */
    public static function get_admin_email_html($a) {
        $clean_name     = htmlspecialchars(self::clean_student_name($a->username), ENT_QUOTES, 'UTF-8');
        $coursename     = htmlspecialchars($a->coursename, ENT_QUOTES, 'UTF-8');
        $useremail      = htmlspecialchars($a->useremail, ENT_QUOTES, 'UTF-8');
        $profileurl     = htmlspecialchars($a->profileurl, ENT_QUOTES, 'UTF-8');
        $platform_label = self::get_email_platform_label($a->useremail);
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="vi" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
        <head>
            <meta charset="UTF-8">
            <title>Thông báo từ hệ thống</title>
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
            <meta name="color-scheme" content="light dark">
            <meta name="supported-color-schemes" content="light dark">
            <!--[if mso]>
            <xml>
                <o:OfficeDocumentSettings>
                    <o:AllowPNG/>
                    <o:PixelsPerInch>96</o:PixelsPerInch>
                </o:OfficeDocumentSettings>
            </xml>
            <![endif]-->
            <style>
                :root {
                    color-scheme: light dark;
                    supported-color-schemes: light dark;
                }
                @media (prefers-color-scheme: dark) {
                    .body-bg { background-color: #020617 !important; }
                    .content-bg { background-color: #0f172a !important; border-color: #1e293b !important; border-bottom-color: #1e293b !important; }
                    .text-main { color: #f8fafc !important; text-shadow: none !important; }
                    .text-sub { color: #94a3b8 !important; }
                    .card-bg-green { background-color: #022c1e !important; border-color: #064e3b !important; border-top-color: #033a28 !important; border-bottom-color: #011810 !important; box-shadow: none !important; }
                    .dashed-border { border-top-color: #334155 !important; border-bottom-color: #334155 !important; }
                    .footer-text { color: #64748b !important; }
                    .highlight-green { color: #34d399 !important; text-shadow: none !important; }
                    .icon-bg { background-color: rgba(0,0,0,0.2) !important; border-color: rgba(255,255,255,0.1) !important; }
                    .divider-line { background-color: #334155 !important; }
                }
                /* Prevent font boosting in Gmail */
                body, table, td, p, a, li, blockquote {
                    -webkit-text-size-adjust: 100%;
                    -ms-text-size-adjust: 100%;
                }
                /* RESPONSIVE STYLES */
                @media screen and (max-width: 600px) {
                    .stack-mobile { display: block !important; width: 100% !important; min-width: 100% !important; max-width: 100% !important; float: none !important; text-align: left !important; box-sizing: border-box !important; }
                    .value-mobile { float: none !important; text-align: left !important; max-width: 100% !important; padding-top: 4px !important; }
                    .padding-mobile { padding: 25px 20px 20px 20px !important; }
                    .card-padding-mobile { padding: 0 20px 25px 20px !important; }
                    .wrapper-padding-mobile { padding: 20px 10px !important; }
                    .header-mobile { padding: 28px 20px !important; }
                    .cta-button { padding: 14px 28px !important; font-size: 15px !important; white-space: normal !important; }
                }
            </style>
        </head>
        <body class="body-bg" style="margin: 0; padding: 0; background-color: #f3f4f6; -webkit-font-smoothing: antialiased; word-break: break-word;">
            <div style="display:none;font-size:1px;color:#f3f4f6;line-height:1px;max-height:0px;max-width:0px;opacity:0;overflow:hidden;">Hệ thống SePay vừa ghi nhận một khoản thanh toán mới và tự động kích hoạt tài khoản thành công...</div>
            <table class="body-bg" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="background-color: #f3f4f6; margin: 0; padding: 0; width: 100%; table-layout: fixed;">
                <tr>
                    <td align="center" class="wrapper-padding-mobile" style="padding: 25px 15px;">
                        <!--[if mso]>
                <table align="center" width="600" border="0" cellpadding="0" cellspacing="0" role="presentation">
                <tr><td>
                <![endif]-->
                <table class="content-bg" align="center" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="max-width: 600px; background-color: #ffffff; border-radius: 24px; overflow: hidden; border: 1px solid #e2e8f0; border-bottom: 4px solid #cbd5e1; box-shadow: 0 15px 35px rgba(0,0,0,0.05); border-collapse: separate; table-layout: fixed; mso-table-lspace: 0pt; mso-table-rspace: 0pt;">
                    
                    <!-- HEADER -->
                    <tr>
                        <td class="header-mobile" style="background-color: #059669; background-image: linear-gradient(150deg, #34d399 0%, #059669 40%, #064e3b 100%); padding: 45px 25px; text-align: center; border-bottom: 5px solid #022c1e; border-top: 1px solid #6ee7b7; box-shadow: inset 0 -10px 20px rgba(0,0,0,0.15), inset 0 2px 10px rgba(255,255,255,0.3);">
                            <div class="icon-bg" style="background: rgba(255, 255, 255, 0.1); border-top: 2px solid rgba(255, 255, 255, 0.5); border-left: 2px solid rgba(255, 255, 255, 0.3); border-right: 1px solid rgba(255,255,255,0.05); border-bottom: 1px solid rgba(255,255,255,0.05); width: 76px; height: 76px; border-radius: 40%; display: inline-block; margin-bottom: 25px; box-shadow: 0 12px 25px rgba(2, 44, 30, 0.5), inset 0 4px 12px rgba(255,255,255,0.4); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);">
                                <p style="font-size: 38px; margin: 0; line-height: 76px; display: block; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3));">💰</p>
                            </div>
                            <h1 style="color: #ffffff; font-size: 28px; font-weight: 700; margin: 0; letter-spacing: 1px; text-transform: uppercase; text-shadow: 0 2px 4px rgba(0,0,0,0.2); font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">DOANH THU MỚI!</h1>
                        </td>
                    </tr>

                    <!-- CONTENT -->
                    <tr>
                        <td class="padding-mobile" style="padding: 35px 30px 25px 30px; font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                            <p class="text-main" style="font-size: 19px; color: #1e293b; margin: 0 0 15px 0; line-height: 1.5; font-weight: 700;">Tin vui báo về 🚀,</p>
                            <p class="text-sub" style="font-size: 16px; color: #475569; margin: 0; line-height: 1.7; text-align: left;">Hệ thống SePay vừa ghi nhận một khoản thanh toán mới và tự động kích hoạt tài khoản thành công cho học viên. Dưới đây là thông tin chi tiết biên lai doanh thu của bạn.</p>
                        </td>
                    </tr>

                    <!-- TRANSACTION CARD -->
                    <tr>
                        <td class="card-padding-mobile" style="padding: 0 30px 30px 30px; font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                            <div class="card-bg-green" style="background-color: #ecfdf5; border: 1px solid #d1fae5; border-top: 2px solid #ffffff; border-bottom: 3px solid #a7f3d0; border-radius: 20px; padding: 25px; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.05), inset 0 2px 0 rgba(255,255,255,0.6);">
                                
                                <div style="text-align: center; margin-bottom: 20px;">
                                    <span style="display: inline-block; background: #10b981; color: #ffffff; font-size: 13px; font-weight: 700; padding: 6px 16px; border-radius: 20px; text-transform: uppercase; letter-spacing: 1px; box-shadow: 0 4px 8px rgba(16, 185, 129, 0.2);">🧾 Chi Tiết Giao Dịch</span>
                                </div>
                                
                                <strong class="highlight-green" style="color: #064e3b; display: block; font-size: 24px; line-height: 1.4; font-weight: 700; text-align: center; margin-bottom: 25px; text-shadow: 0 1px 1px rgba(255,255,255,0.8); font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;"><?= $coursename ?></strong>
                                
                                <div class="dashed-border" style="border-top: 1px dashed #a7f3d0; padding: 12px 0; overflow: hidden; clear: both;">
                                    <!--[if mso]><table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation"><tr><td width="35%" align="left" valign="top"><![endif]-->
                                    <span class="stack-mobile" style="font-size: 15px; color: #64748b; font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">🗓️ Thời gian:</span>
                                    <!--[if mso]></td><td width="65%" align="right" valign="top"><![endif]-->
                                    <span class="stack-mobile value-mobile" style="font-size: 15px; font-weight: 700; color: #0f172a; float: right; text-align: right; word-break: break-word; max-width: 65%; font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;"><?= date('d/m/Y - H:i') ?></span>
                                    <!--[if mso]></td></tr></table><![endif]-->
                                </div>

                                <div class="dashed-border" style="border-top: 1px dashed #a7f3d0; padding: 12px 0; overflow: hidden; clear: both;">
                                    <!--[if mso]><table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation"><tr><td width="35%" align="left" valign="top"><![endif]-->
                                    <span class="stack-mobile" style="font-size: 15px; color: #64748b; font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">👤 Học viên:</span>
                                    <!--[if mso]></td><td width="65%" align="right" valign="top"><![endif]-->
                                    <span class="stack-mobile value-mobile" style="font-size: 15px; font-weight: 700; color: #0f172a; float: right; text-align: right; word-break: break-word; max-width: 65%; font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;"><?= $clean_name ?></span>
                                    <!--[if mso]></td></tr></table><![endif]-->
                                </div>

                                <div class="dashed-border" style="border-top: 1px dashed #a7f3d0; padding: 12px 0 0 0; overflow: hidden; clear: both;">
                                    <!--[if mso]><table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation"><tr><td width="35%" align="left" valign="top"><![endif]-->
                                    <span class="stack-mobile" style="font-size: 15px; color: #64748b; font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;"><?= $platform_label ?></span>
                                    <!--[if mso]></td><td width="65%" align="right" valign="top"><![endif]-->
                                    <span class="stack-mobile value-mobile" style="font-size: 15px; font-weight: 700; color: #0f172a; float: right; text-align: right; word-break: break-all; max-width: 65%; font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;"><?= $useremail ?></span>
                                    <!--[if mso]></td></tr></table><![endif]-->
                                </div>
                            </div>
                        </td>
                    </tr>

                    <!-- CTA -->
                    <tr>
                        <td class="card-padding-mobile" style="padding: 0 30px 40px 30px; text-align: center; font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                            <!--[if mso]>
                            <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="<?= $profileurl ?>" style="height:56px;v-text-anchor:middle;width:280px;" arcsize="50%" strokecolor="#064e3b" strokeweight="2px" fillcolor="#059669">
                                <w:anchorlock/>
                                <center style="color:#ffffff;font-family:'Segoe UI', sans-serif;font-size:16px;font-weight:bold;">XEM HỒ SƠ HỌC VIÊN</center>
                            </v:roundrect>
                            <![endif]-->
                            <!--[if !mso]><!-->
                            <a href="<?= $profileurl ?>" class="cta-button" target="_blank" style="display: inline-block; background-color: #059669; background-image: linear-gradient(135deg, rgba(255,255,255,0.4) 0%, rgba(255,255,255,0.05) 45%, rgba(0,0,0,0.05) 55%, rgba(0,0,0,0.15) 100%); color: #ffffff; text-decoration: none; font-size: 16px; font-weight: 700; padding: 15px 36px; border-radius: 50px; border: 1px solid rgba(255, 255, 255, 0.5); box-shadow: 0 10px 30px -5px rgba(5, 150, 105, 0.4), inset 0 2px 4px rgba(255, 255, 255, 0.6), inset 0 -4px 6px rgba(0, 0, 0, 0.2), inset 0 0 10px rgba(255,255,255,0.1); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); text-transform: uppercase; letter-spacing: 1.5px; transition: all 0.3s ease; white-space: nowrap;"><span style="text-shadow: 0 2px 4px rgba(0,0,0,0.3);">Xem Hồ Sơ Học Viên</span></a>
                            <!--<![endif]-->
                        </td>
                    </tr>

                    <!-- FOOTER -->
                    <tr>
                        <td class="content-bg" style="background-color: #f8fafc; padding: 30px 25px; text-align: center; border-top: 1px solid #e2e8f0; font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                            <p class="footer-text" style="font-size: 14px; color: #64748b; margin: 0 0 12px 0; line-height: 1.5; font-weight: 600;">⚡ Hệ thống thanh toán tự động qua SePay</p>
                            <div class="divider-line" style="width: 50px; height: 2px; background-color: #cbd5e1; margin: 20px auto; border-radius: 2px;"></div>
                            <p class="footer-text" style="font-size: 12px; color: #94a3b8; margin: 0; letter-spacing: 0.5px; text-transform: uppercase;">&copy; <?= date('Y') ?> <strong>Hệ thống quản lý Quiz Văn Lang</strong>.<br>Bảo mật & Tự động hoàn toàn.</p>
                        </td>
                    </tr>
                </table>
                <!--[if mso]>
                </td></tr>
                </table>
                <![endif]-->
                    </td>
                </tr>
            </table>

            <!-- HIDDEN MOODLE APP WRAPPER TRICK -->
            <div style="display: none !important; mso-hide: all; visibility: hidden; width: 0; height: 0; font-size: 0;">
                <table border="0" cellpadding="0" cellspacing="0" role="presentation"><tr><td>
                </td></tr></table>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Gửi rejection notification (bell + email) cho student.
     *
     * @param \stdClass $course
     * @param \stdClass $user
     * @param \stdClass|null $instance
     * @return bool true nếu gửi thành công
     */
    public static function send_rejection_notification($course, $user, $instance = null): bool {
        global $DB;

        if (!$DB->record_exists('message_providers', ['component' => 'enrol_sepay', 'name' => 'rejection_notification'])) {
            return false;
        }

        $plugin = enrol_get_plugin('sepay');
        if ($plugin && !$plugin->get_config('mailstudents', 0)) {
            return false;
        }

        $context    = \context_course::instance($course->id);
        $courseurl  = (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false);
        $profileurl = (new \moodle_url('/user/view.php', ['id' => $user->id]))->out(false);

        $a = new \stdClass();
        $a->username   = fullname($user, true);
        $a->useremail  = $user->email;
        $a->coursename = format_string($course->fullname, true, ['context' => $context]);
        $a->courseurl  = $courseurl;
        $a->profileurl = $profileurl;

        $admin = get_admin();

        // Bell/popup notification qua Moodle messaging.
        $msg = new \core\message\message();
        $msg->component         = 'enrol_sepay';
        $msg->name              = 'rejection_notification';
        $msg->userfrom          = $admin;
        $msg->userto            = $user;
        $msg->subject           = get_string('email_rejection_subject', 'enrol_sepay', $a);
        $msg->fullmessage       = get_string('email_rejection_body', 'enrol_sepay', $a);
        $msg->fullmessageformat = FORMAT_PLAIN;
        $msg->smallmessage      = get_string('email_rejection_smallmessage', 'enrol_sepay', $a);
        $msg->notification      = 1;
        $msg->contexturl        = $courseurl;
        $msg->contexturlname    = format_string($course->fullname, true, ['context' => $context]);
        $msg->courseid          = $course->id;
        message_send($msg);

        // Email trực tiếp với HTML tùy chỉnh — tránh Moodle wrapper và footer mobile app.
        email_to_user(
            $user,
            $admin,
            get_string('email_rejection_subject', 'enrol_sepay', $a),
            get_string('email_rejection_body', 'enrol_sepay', $a),
            self::get_rejection_email_html($a)
        );

        return true;
    }

    /**
     * HTML email template cho rejection notification — màu cam, cùng chuẩn với enrollment template.
     */
    public static function get_rejection_email_html($a): string {
        $clean_name     = htmlspecialchars(self::clean_student_name($a->username), ENT_QUOTES, 'UTF-8');
        $coursename     = htmlspecialchars($a->coursename, ENT_QUOTES, 'UTF-8');
        $useremail      = htmlspecialchars($a->useremail, ENT_QUOTES, 'UTF-8');
        $courseurl      = htmlspecialchars($a->courseurl, ENT_QUOTES, 'UTF-8');
        $platform_label = self::get_email_platform_label($a->useremail);
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="vi" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
        <head>
            <meta charset="UTF-8">
            <title>Thông báo từ hệ thống</title>
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
            <meta name="color-scheme" content="light dark">
            <meta name="supported-color-schemes" content="light dark">
            <!--[if mso]>
            <xml><o:OfficeDocumentSettings><o:AllowPNG/><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml>
            <![endif]-->
            <style>
                :root { color-scheme: light dark; supported-color-schemes: light dark; }
                @media (prefers-color-scheme: dark) {
                    .body-bg    { background-color: #020617 !important; }
                    .content-bg { background-color: #0f172a !important; border-color: #1e293b !important; border-bottom-color: #1e293b !important; }
                    .text-main  { color: #f8fafc !important; text-shadow: none !important; }
                    .text-sub   { color: #94a3b8 !important; }
                    .card-bg    { background-color: #2a1a0a !important; border-color: #7c3a10 !important; border-bottom-color: #4a1f06 !important; box-shadow: none !important; }
                    .card-bg-blue { background-color: #0d1b2a !important; border-color: #1e3a8a !important; border-bottom-color: #080f1a !important; box-shadow: none !important; }
                    .dashed-border { border-top-color: #334155 !important; }
                    .divider-line { background-color: #334155 !important; }
                    .footer-text { color: #64748b !important; }
                    .highlight  { color: #fb923c !important; }
                    .icon-bg    { background-color: rgba(0,0,0,0.2) !important; }
                }
                body, table, td, p, a, li { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
                @media screen and (max-width: 600px) {
                    .stack-mobile { display: block !important; width: 100% !important; float: none !important; text-align: left !important; box-sizing: border-box !important; }
                    .value-mobile { float: none !important; text-align: left !important; max-width: 100% !important; padding-top: 4px !important; }
                    .padding-mobile  { padding: 25px 20px 20px 20px !important; }
                    .card-padding-mobile { padding: 0 20px 25px 20px !important; }
                    .wrapper-padding-mobile { padding: 20px 10px !important; }
                    .header-mobile { padding: 28px 20px !important; }
                    .cta-button { padding: 14px 28px !important; font-size: 15px !important; }
                }
            </style>
        </head>
        <body class="body-bg" style="margin:0;padding:0;background-color:#fff7ed;-webkit-font-smoothing:antialiased;word-break:break-word;">
            <div style="display:none;font-size:1px;color:#fff7ed;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">Rất tiếc, yêu cầu ghi danh khóa học của bạn đã bị từ chối...</div>
            <table class="body-bg" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="background-color:#fff7ed;margin:0;padding:0;width:100%;table-layout:fixed;">
                <tr>
                    <td align="center" class="wrapper-padding-mobile" style="padding:25px 15px;">
                        <!--[if mso]><table align="center" width="600" border="0" cellpadding="0" cellspacing="0" role="presentation"><tr><td><![endif]-->
                        <table class="content-bg" align="center" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="max-width:600px;background-color:#ffffff;border-radius:24px;overflow:hidden;border:1px solid #fed7aa;border-bottom:4px solid #fdba74;box-shadow:0 15px 35px rgba(0,0,0,0.05);border-collapse:separate;table-layout:fixed;mso-table-lspace:0pt;mso-table-rspace:0pt;">

                            <!-- HEADER -->
                            <tr>
                                <td class="header-mobile" style="background-color:#ea580c;background-image:linear-gradient(150deg,#fb923c 0%,#ea580c 40%,#9a3412 100%);padding:45px 25px;text-align:center;border-bottom:5px solid #7c2d12;border-top:1px solid #fba97a;box-shadow:inset 0 -10px 20px rgba(0,0,0,0.15),inset 0 2px 10px rgba(255,255,255,0.3);">
                                    <div class="icon-bg" style="background:rgba(255,255,255,0.1);border-top:2px solid rgba(255,255,255,0.5);border-left:2px solid rgba(255,255,255,0.3);border-right:1px solid rgba(255,255,255,0.05);border-bottom:1px solid rgba(255,255,255,0.05);width:76px;height:76px;border-radius:40%;display:inline-block;margin-bottom:25px;box-shadow:0 12px 25px rgba(124,45,18,0.5),inset 0 4px 12px rgba(255,255,255,0.4);">
                                        <p style="font-size:38px;margin:0;line-height:76px;display:block;filter:drop-shadow(0 4px 6px rgba(0,0,0,0.3));">⚠️</p>
                                    </div>
                                    <h1 style="color:#ffffff;font-size:28px;font-weight:700;margin:0;letter-spacing:1px;text-transform:uppercase;text-shadow:0 2px 4px rgba(0,0,0,0.2);font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;">YÊU CẦU BỊ TỪ CHỐI!</h1>
                                </td>
                            </tr>

                            <!-- CONTENT -->
                            <tr>
                                <td class="padding-mobile" style="padding:35px 30px 25px 30px;font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
                                    <p class="text-main" style="font-size:19px;color:#1e293b;margin:0 0 15px 0;line-height:1.5;font-weight:700;">Xin chào <span class="highlight" style="color:#ea580c;"><?= $clean_name ?></span> 👋,</p>
                                    <p class="text-sub" style="font-size:16px;color:#475569;margin:0;line-height:1.7;text-align:justify;">Rất tiếc, yêu cầu ghi danh khóa học của bạn đã bị từ chối. Vui lòng liên hệ với quản trị viên nếu bạn cho rằng đây là sự nhầm lẫn hoặc bạn muốn thử lại.</p>
                                </td>
                            </tr>

                            <!-- COURSE CARD -->
                            <tr>
                                <td class="card-padding-mobile" style="padding:0 30px 30px 30px;font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
                                    <div class="card-bg" style="background-color:#fff7ed;border:1px solid #fed7aa;border-top:2px solid #ffffff;border-bottom:3px solid #fdba74;border-radius:20px;padding:25px;box-shadow:0 4px 15px rgba(234,88,12,0.05),inset 0 2px 0 rgba(255,255,255,0.6);">
                                        <div style="text-align:center;margin-bottom:20px;">
                                            <span style="display:inline-block;background:#ea580c;color:#ffffff;font-size:13px;font-weight:700;padding:6px 16px;border-radius:20px;text-transform:uppercase;letter-spacing:1px;box-shadow:0 4px 8px rgba(234,88,12,0.2);">📚 Thông tin khóa học</span>
                                        </div>
                                        <strong class="highlight" style="color:#9a3412;display:block;font-size:22px;line-height:1.4;font-weight:700;text-align:center;margin-bottom:25px;font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;"><?= $coursename ?></strong>
                                        <div style="border-top:1px dashed #fdba74;padding:12px 0;overflow:hidden;clear:both;">
                                            <span class="stack-mobile" style="font-size:15px;color:#64748b;font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;">🗓️ Thời gian:</span>
                                            <span class="stack-mobile value-mobile" style="font-size:15px;font-weight:700;color:#0f172a;float:right;text-align:right;word-break:break-word;max-width:65%;font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;"><?= date('d/m/Y - H:i') ?></span>
                                        </div>
                                        <div style="border-top:1px dashed #fdba74;padding:12px 0;overflow:hidden;clear:both;">
                                            <span class="stack-mobile" style="font-size:15px;color:#64748b;font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;">👤 Học viên:</span>
                                            <span class="stack-mobile value-mobile" style="font-size:15px;font-weight:700;color:#0f172a;float:right;text-align:right;word-break:break-word;max-width:65%;font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;"><?= $clean_name ?></span>
                                        </div>
                                        <div style="border-top:1px dashed #fdba74;padding:12px 0 0 0;overflow:hidden;clear:both;">
                                            <span class="stack-mobile" style="font-size:15px;color:#64748b;font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;"><?= $platform_label ?></span>
                                            <span class="stack-mobile value-mobile" style="font-size:15px;font-weight:700;color:#0f172a;float:right;text-align:right;word-break:break-all;max-width:65%;font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;"><?= $useremail ?></span>
                                        </div>
                                    </div>
                                </td>
                            </tr>

                            <!-- CTA -->
                            <tr>
                                <td class="card-padding-mobile" style="padding:0 30px 40px 30px;text-align:center;font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
                                    <!--[if mso]>
                                    <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="<?= $courseurl ?>" style="height:56px;v-text-anchor:middle;width:260px;" arcsize="50%" strokecolor="#7c2d12" strokeweight="2px" fillcolor="#ea580c">
                                        <w:anchorlock/>
                                        <center style="color:#ffffff;font-family:'Segoe UI',sans-serif;font-size:16px;font-weight:bold;">XEM KHÓA HỌC</center>
                                    </v:roundrect>
                                    <![endif]-->
                                    <!--[if !mso]><!-->
                                    <a href="<?= $courseurl ?>" class="cta-button" target="_blank" style="display:inline-block;background-color:#ea580c;background-image:linear-gradient(135deg,rgba(255,255,255,0.4) 0%,rgba(255,255,255,0.05) 45%,rgba(0,0,0,0.05) 55%,rgba(0,0,0,0.15) 100%);color:#ffffff;text-decoration:none;font-size:16px;font-weight:700;padding:15px 36px;border-radius:50px;border:1px solid rgba(255,255,255,0.5);box-shadow:0 10px 30px -5px rgba(234,88,12,0.4),inset 0 2px 4px rgba(255,255,255,0.6),inset 0 -4px 6px rgba(0,0,0,0.2);text-transform:uppercase;letter-spacing:1.5px;white-space:nowrap;"><span style="text-shadow:0 2px 4px rgba(0,0,0,0.3);">Xem Khóa Học</span></a>
                                    <!--<![endif]-->
                                </td>
                            </tr>

                            <!-- ZALO COMMUNITY BOX -->
                            <?php if (stripos($a->useremail, '@vanlanguni.vn') === false) : ?>
                            <tr>
                                <td class="card-padding-mobile" style="padding:0 30px 30px 30px;font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
                                    <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="border-collapse:separate;border-spacing:0;">
                                        <tr>
                                            <td class="card-bg-blue" style="background-color:#eff6ff;border:1px solid #dbeafe;border-top:2px solid #ffffff;border-bottom:3px solid #bfdbfe;border-radius:20px;padding:30px 25px;text-align:center;box-shadow:0 4px 15px rgba(59,130,246,0.05),inset 0 2px 0 rgba(255,255,255,0.6);">
                                                <?php $zalo_uri = self::logo_data_uri('zalo_icon.png', 60, 60); ?>
                                                <?php if ($zalo_uri) : ?>
                                                <img src="<?= $zalo_uri ?>" width="60" height="60" alt="Zalo Logo" style="display:block;margin:0 auto 15px auto;border-radius:16px;filter:drop-shadow(0 4px 10px rgba(0,0,0,0.15));">
                                                <?php else : ?>
                                                <div style="width:60px;height:60px;margin:0 auto 15px auto;background:#0068ff;border-radius:16px;"><span style="color:#fff;font-size:28px;font-weight:900;line-height:60px;text-align:center;display:block;">Z</span></div>
                                                <?php endif; ?>
                                                <h4 class="text-main" style="margin:0 0 10px 0;color:#1e3a8a;font-size:18px;font-weight:700;">Cộng Đồng Quiz Văn Lang</h4>
                                                <p class="text-sub" style="font-size:15px;color:#3b82f6;margin:0 0 20px 0;line-height:1.5;">Tham gia cộng đồng bên dưới<br>để không bỏ lỡ các khóa học mới nhất nhé!</p>
                                                <!--[if mso]>
                                                <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="https://zalo.me/g/eds5dzskgzc7eu4essgc" style="height:44px;v-text-anchor:middle;width:180px;" arcsize="50%" strokecolor="#1e3a8a" strokeweight="2px" fillcolor="#2563eb">
                                                    <w:anchorlock/>
                                                    <center style="color:#ffffff;font-family:'Segoe UI',sans-serif;font-size:14px;font-weight:bold;">Tham Gia Ngay</center>
                                                </v:roundrect>
                                                <![endif]-->
                                                <!--[if !mso]><!-->
                                                <a href="https://zalo.me/g/eds5dzskgzc7eu4essgc" target="_blank" style="display:inline-block;background-color:#2563eb;background-image:linear-gradient(135deg,rgba(255,255,255,0.4) 0%,rgba(255,255,255,0.05) 45%,rgba(0,0,0,0.05) 55%,rgba(0,0,0,0.15) 100%);color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;padding:14px 35px;border-radius:50px;border:1px solid rgba(255,255,255,0.5);box-shadow:0 10px 25px -5px rgba(37,99,235,0.4),inset 0 2px 4px rgba(255,255,255,0.6),inset 0 -3px 5px rgba(0,0,0,0.2);text-transform:uppercase;letter-spacing:1px;"><span style="text-shadow:0 2px 4px rgba(0,0,0,0.3);">Tham Gia Ngay</span></a>
                                                <!--<![endif]-->
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <?php endif; ?>

                            <!-- FOOTER -->
                            <tr>
                                <td class="content-bg" style="background-color:#f8fafc;padding:30px 25px;text-align:center;border-top:1px solid #e2e8f0;font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
                                    <?php if (stripos($a->useremail, '@vanlanguni.vn') === false) : ?>
                                    <p class="footer-text" style="font-size:14px;color:#64748b;margin:0;line-height:1.5;">💡 Nếu muốn thử lại, inbox Zalo Admin:<br><a href="https://zalo.me/0588325106" target="_blank" style="color:#2563eb;text-decoration:none;font-weight:700;background:#e0e7ff;padding:4px 12px;border-radius:12px;display:inline-block;margin-top:8px;">0588.325.106</a></p>
                                    <?php else : ?>
                                    <p class="footer-text" style="font-size:14px;color:#64748b;margin:0;line-height:1.5;">💡 Nếu muốn thử lại, liên hệ SĐT Zalo Admin:<br><strong style="color:#475569;display:inline-block;margin-top:8px;">0588.325.106</strong></p>
                                    <?php endif; ?>
                                    <div class="divider-line" style="width:50px;height:2px;background-color:#cbd5e1;margin:20px auto;border-radius:2px;"></div>
                                    <p class="footer-text" style="font-size:12px;color:#94a3b8;margin:0;letter-spacing:0.5px;text-transform:uppercase;">&copy; <?= date('Y') ?> <strong>Quiz Văn Lang</strong>. Mọi quyền được bảo lưu.</p>
                                </td>
                            </tr>

                        </table>
                        <!--[if mso]></td></tr></table><![endif]-->
                    </td>
                </tr>
            </table>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Gửi unenrolment notification (bell + email) cho student khi bị hủy ghi danh.
     *
     * @param \stdClass $course
     * @param \stdClass $user
     * @return bool true nếu gửi thành công
     */
    public static function send_unenrolment_notification($course, $user): bool {
        global $DB;

        if (!$DB->record_exists('message_providers', ['component' => 'enrol_sepay', 'name' => 'unenrolment_notification'])) {
            return false;
        }

        $plugin = enrol_get_plugin('sepay');
        if ($plugin && !$plugin->get_config('mailstudents', 0)) {
            return false;
        }

        $context    = \context_course::instance($course->id);
        $courseurl  = (new \moodle_url('/course/view.php', ['id' => $course->id]))->out(false);

        $a = new \stdClass();
        $a->username   = fullname($user, true);
        $a->useremail  = $user->email;
        $a->coursename = format_string($course->fullname, true, ['context' => $context]);
        $a->courseurl  = $courseurl;

        $admin = get_admin();

        // Bell/popup notification qua Moodle messaging.
        $msg = new \core\message\message();
        $msg->component         = 'enrol_sepay';
        $msg->name              = 'unenrolment_notification';
        $msg->userfrom          = $admin;
        $msg->userto            = $user;
        $msg->subject           = get_string('email_unenrolment_subject', 'enrol_sepay', $a);
        $msg->fullmessage       = get_string('email_unenrolment_body', 'enrol_sepay', $a);
        $msg->fullmessageformat = FORMAT_PLAIN;
        $msg->smallmessage      = get_string('email_unenrolment_smallmessage', 'enrol_sepay', $a);
        $msg->notification      = 1;
        $msg->contexturl        = $courseurl;
        $msg->contexturlname    = format_string($course->fullname, true, ['context' => $context]);
        $msg->courseid          = $course->id;
        message_send($msg);

        // Email trực tiếp với HTML tùy chỉnh — tránh Moodle wrapper và footer mobile app.
        email_to_user(
            $user,
            $admin,
            get_string('email_unenrolment_subject', 'enrol_sepay', $a),
            get_string('email_unenrolment_body', 'enrol_sepay', $a),
            self::get_unenrolment_email_html($a)
        );

        return true;
    }

    /**
     * HTML email template cho unenrolment notification — màu slate, cùng chuẩn với enrollment template.
     */
    public static function get_unenrolment_email_html($a): string {
        $clean_name     = htmlspecialchars(self::clean_student_name($a->username), ENT_QUOTES, 'UTF-8');
        $coursename     = htmlspecialchars($a->coursename, ENT_QUOTES, 'UTF-8');
        $useremail      = htmlspecialchars($a->useremail, ENT_QUOTES, 'UTF-8');
        $courseurl      = htmlspecialchars($a->courseurl, ENT_QUOTES, 'UTF-8');
        $platform_label = self::get_email_platform_label($a->useremail);
        $contact_url    = htmlspecialchars(
            (new \moodle_url('/message/index.php', ['id' => get_admin()->id]))->out(false),
            ENT_QUOTES,
            'UTF-8'
        );
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="vi" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
        <head>
            <meta charset="UTF-8">
            <title>Thông báo từ hệ thống</title>
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
            <meta name="color-scheme" content="light dark">
            <meta name="supported-color-schemes" content="light dark">
            <!--[if mso]>
            <xml><o:OfficeDocumentSettings><o:AllowPNG/><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml>
            <![endif]-->
            <style>
                :root { color-scheme: light dark; supported-color-schemes: light dark; }
                @media (prefers-color-scheme: dark) {
                    .body-bg    { background-color: #020617 !important; }
                    .content-bg { background-color: #0f172a !important; border-color: #1e293b !important; border-bottom-color: #1e293b !important; }
                    .text-main  { color: #f8fafc !important; text-shadow: none !important; }
                    .text-sub   { color: #94a3b8 !important; }
                    .card-bg    { background-color: #0d1520 !important; border-color: #2d3748 !important; border-bottom-color: #1a2535 !important; box-shadow: none !important; }
                    .dashed-border { border-top-color: #334155 !important; }
                    .divider-line { background-color: #334155 !important; }
                    .footer-text { color: #64748b !important; }
                    .highlight  { color: #94a3b8 !important; }
                    .icon-bg    { background-color: rgba(0,0,0,0.2) !important; }
                }
                body, table, td, p, a, li { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
                @media screen and (max-width: 600px) {
                    .stack-mobile { display: block !important; width: 100% !important; float: none !important; text-align: left !important; box-sizing: border-box !important; }
                    .value-mobile { float: none !important; text-align: left !important; max-width: 100% !important; padding-top: 4px !important; }
                    .padding-mobile  { padding: 25px 20px 20px 20px !important; }
                    .card-padding-mobile { padding: 0 20px 25px 20px !important; }
                    .wrapper-padding-mobile { padding: 20px 10px !important; }
                    .header-mobile { padding: 28px 20px !important; }
                    .cta-button { padding: 14px 28px !important; font-size: 15px !important; }
                }
            </style>
        </head>
        <body class="body-bg" style="margin:0;padding:0;background-color:#f1f5f9;-webkit-font-smoothing:antialiased;word-break:break-word;">
            <div style="display:none;font-size:1px;color:#f1f5f9;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">Rất tiếc, bạn đã bị hủy ghi danh khỏi khóa học. Liên hệ quản trị viên nếu đây là nhầm lẫn...</div>
            <table class="body-bg" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="background-color:#f1f5f9;margin:0;padding:0;width:100%;table-layout:fixed;">
                <tr>
                    <td align="center" class="wrapper-padding-mobile" style="padding:25px 15px;">
                        <!--[if mso]><table align="center" width="600" border="0" cellpadding="0" cellspacing="0" role="presentation"><tr><td><![endif]-->
                        <table class="content-bg" align="center" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="max-width:600px;background-color:#ffffff;border-radius:24px;overflow:hidden;border:1px solid #e2e8f0;border-bottom:4px solid #cbd5e1;box-shadow:0 15px 35px rgba(0,0,0,0.05);border-collapse:separate;table-layout:fixed;mso-table-lspace:0pt;mso-table-rspace:0pt;">

                            <!-- HEADER -->
                            <tr>
                                <td class="header-mobile" style="background-color:#475569;background-image:linear-gradient(150deg,#64748b 0%,#475569 40%,#1e293b 100%);padding:45px 25px;text-align:center;border-bottom:5px solid #0f172a;border-top:1px solid #94a3b8;box-shadow:inset 0 -10px 20px rgba(0,0,0,0.15),inset 0 2px 10px rgba(255,255,255,0.15);">
                                    <div class="icon-bg" style="background:rgba(255,255,255,0.1);border-top:2px solid rgba(255,255,255,0.4);border-left:2px solid rgba(255,255,255,0.2);border-right:1px solid rgba(255,255,255,0.05);border-bottom:1px solid rgba(255,255,255,0.05);width:76px;height:76px;border-radius:40%;display:inline-block;margin-bottom:25px;box-shadow:0 12px 25px rgba(15,23,42,0.5),inset 0 4px 12px rgba(255,255,255,0.3);">
                                        <p style="font-size:38px;margin:0;line-height:76px;display:block;filter:drop-shadow(0 4px 6px rgba(0,0,0,0.3));">📋</p>
                                    </div>
                                    <h1 style="color:#ffffff;font-size:28px;font-weight:700;margin:0;letter-spacing:1px;text-transform:uppercase;text-shadow:0 2px 4px rgba(0,0,0,0.3);font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;">HỦY GHI DANH!</h1>
                                </td>
                            </tr>

                            <!-- CONTENT -->
                            <tr>
                                <td class="padding-mobile" style="padding:35px 30px 25px 30px;font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
                                    <p class="text-main" style="font-size:19px;color:#1e293b;margin:0 0 15px 0;line-height:1.5;font-weight:700;">Xin chào <span class="highlight" style="color:#475569;"><?= $clean_name ?></span> 👋,</p>
                                    <p class="text-sub" style="font-size:16px;color:#475569;margin:0;line-height:1.7;text-align:justify;">Rất tiếc, bạn đã bị hủy ghi danh khỏi khóa học này. Nếu bạn cho rằng đây là nhầm lẫn, vui lòng liên hệ quản trị viên để được hỗ trợ kịp thời.</p>
                                </td>
                            </tr>

                            <!-- COURSE CARD -->
                            <tr>
                                <td class="card-padding-mobile" style="padding:0 30px 30px 30px;font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
                                    <div class="card-bg" style="background-color:#f8fafc;border:1px solid #e2e8f0;border-top:2px solid #ffffff;border-bottom:3px solid #cbd5e1;border-radius:20px;padding:25px;box-shadow:0 4px 15px rgba(71,85,105,0.05),inset 0 2px 0 rgba(255,255,255,0.8);">
                                        <div style="text-align:center;margin-bottom:20px;">
                                            <span style="display:inline-block;background:#475569;color:#ffffff;font-size:13px;font-weight:700;padding:6px 16px;border-radius:20px;text-transform:uppercase;letter-spacing:1px;box-shadow:0 4px 8px rgba(71,85,105,0.2);">📚 Thông tin khóa học</span>
                                        </div>
                                        <strong class="highlight" style="color:#1e293b;display:block;font-size:22px;line-height:1.4;font-weight:700;text-align:center;margin-bottom:25px;font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;"><?= $coursename ?></strong>
                                        <div style="border-top:1px dashed #cbd5e1;padding:12px 0;overflow:hidden;clear:both;">
                                            <span class="stack-mobile" style="font-size:15px;color:#64748b;font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;">🗓️ Thời gian:</span>
                                            <span class="stack-mobile value-mobile" style="font-size:15px;font-weight:700;color:#0f172a;float:right;text-align:right;word-break:break-word;max-width:65%;font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;"><?= date('d/m/Y - H:i') ?></span>
                                        </div>
                                        <div style="border-top:1px dashed #cbd5e1;padding:12px 0;overflow:hidden;clear:both;">
                                            <span class="stack-mobile" style="font-size:15px;color:#64748b;font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;">👤 Học viên:</span>
                                            <span class="stack-mobile value-mobile" style="font-size:15px;font-weight:700;color:#0f172a;float:right;text-align:right;word-break:break-word;max-width:65%;font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;"><?= $clean_name ?></span>
                                        </div>
                                        <div style="border-top:1px dashed #cbd5e1;padding:12px 0 0 0;overflow:hidden;clear:both;">
                                            <span class="stack-mobile" style="font-size:15px;color:#64748b;font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;"><?= $platform_label ?></span>
                                            <span class="stack-mobile value-mobile" style="font-size:15px;font-weight:700;color:#0f172a;float:right;text-align:right;word-break:break-all;max-width:65%;font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;"><?= $useremail ?></span>
                                        </div>
                                    </div>
                                </td>
                            </tr>

                            <!-- CTA -->
                            <tr>
                                <td class="card-padding-mobile" style="padding:0 30px 40px 30px;text-align:center;font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
                                    <!--[if mso]>
                                    <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="<?= $contact_url ?>" style="height:56px;v-text-anchor:middle;width:260px;" arcsize="50%" strokecolor="#1e293b" strokeweight="2px" fillcolor="#475569">
                                        <w:anchorlock/>
                                        <center style="color:#ffffff;font-family:'Segoe UI',sans-serif;font-size:16px;font-weight:bold;">LIÊN HỆ HỖ TRỢ</center>
                                    </v:roundrect>
                                    <![endif]-->
                                    <!--[if !mso]><!-->
                                    <a href="<?= $contact_url ?>" class="cta-button" target="_blank" style="display:inline-block;background-color:#475569;background-image:linear-gradient(135deg,rgba(255,255,255,0.3) 0%,rgba(255,255,255,0.05) 45%,rgba(0,0,0,0.05) 55%,rgba(0,0,0,0.15) 100%);color:#ffffff;text-decoration:none;font-size:16px;font-weight:700;padding:15px 36px;border-radius:50px;border:1px solid rgba(255,255,255,0.4);box-shadow:0 10px 30px -5px rgba(71,85,105,0.4),inset 0 2px 4px rgba(255,255,255,0.5),inset 0 -4px 6px rgba(0,0,0,0.2);text-transform:uppercase;letter-spacing:1.5px;white-space:nowrap;"><span style="text-shadow:0 2px 4px rgba(0,0,0,0.3);">Liên Hệ Hỗ Trợ</span></a>
                                    <!--<![endif]-->
                                </td>
                            </tr>

                            <!-- FOOTER -->
                            <tr>
                                <td class="content-bg" style="background-color:#f8fafc;padding:30px 25px;text-align:center;border-top:1px solid #e2e8f0;font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
                                    <?php if (stripos($a->useremail, '@vanlanguni.vn') === false) : ?>
                                    <p class="footer-text" style="font-size:14px;color:#64748b;margin:0;line-height:1.5;">💡 Nếu đây là nhầm lẫn, inbox Zalo Admin:<br><a href="https://zalo.me/0588325106" target="_blank" style="color:#2563eb;text-decoration:none;font-weight:700;background:#e0e7ff;padding:4px 12px;border-radius:12px;display:inline-block;margin-top:8px;">0588.325.106</a></p>
                                    <?php else : ?>
                                    <p class="footer-text" style="font-size:14px;color:#64748b;margin:0;line-height:1.5;">💡 Nếu đây là nhầm lẫn, liên hệ SĐT Zalo Admin:<br><strong style="color:#475569;display:inline-block;margin-top:8px;">0588.325.106</strong></p>
                                    <?php endif; ?>
                                    <div class="divider-line" style="width:50px;height:2px;background-color:#cbd5e1;margin:20px auto;border-radius:2px;"></div>
                                    <p class="footer-text" style="font-size:12px;color:#94a3b8;margin:0;letter-spacing:0.5px;text-transform:uppercase;">&copy; <?= date('Y') ?> <strong>Quiz Văn Lang</strong>. Mọi quyền được bảo lưu.</p>
                                </td>
                            </tr>

                        </table>
                        <!--[if mso]></td></tr></table><![endif]-->
                    </td>
                </tr>
            </table>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}

