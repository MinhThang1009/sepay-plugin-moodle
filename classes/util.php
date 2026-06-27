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
     * Gửi welcome email đúng MỘT lần cho một giao dịch, an toàn với race.
     *
     * Nhiều luồng có thể cùng xử lý một giao dịch: webhook auto-enrol, trang
     * complete_enrol, cron process_enrolments, adhoc send_mass_email. Trước đây
     * mỗi luồng tự kiểm tra cờ email_sent rồi gửi (check-then-set) nên hai luồng
     * cùng đọc email_sent=0 sẽ gửi đúp. Ở đây dùng app-lock theo transaction id
     * để serialize: chỉ luồng giành được lock và đọc lại email_sent=0 TRONG lock
     * mới gửi rồi set 1.
     *
     * @param int $transactionid id bản ghi enrol_sepay_transactions
     * @param \stdClass $course
     * @param \stdClass $user
     * @param \stdClass $instance
     * @return bool true nếu lần gọi này đã gửi; false nếu đã gửi trước đó / mail tắt / không lấy được lock
     */
    public static function send_welcome_messages_once(int $transactionid, $course, $user, $instance): bool {
        global $DB;

        $lockfactory = \core\lock\lock_config::get_lock_factory('enrol_sepay_email');
        $lock = $lockfactory->get_lock('txn_' . $transactionid, 10);
        if (!$lock) {
            // Luồng khác đang giữ lock cho giao dịch này — để nó gửi, ta bỏ qua.
            return false;
        }

        try {
            // Kiểm tra lại cờ TRONG lock (không tin giá trị đã load trước đó) — chống TOCTOU.
            if ($DB->get_field('enrol_sepay_transactions', 'email_sent', ['id' => $transactionid])) {
                return false;
            }
            if (!self::send_welcome_messages($course, $user, $instance)) {
                // Mail tắt (no-op) — không đánh dấu để lần sau thử lại.
                return false;
            }
            $DB->set_field('enrol_sepay_transactions', 'email_sent', 1, ['id' => $transactionid]);
            return true;
        } finally {
            $lock->release();
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

        // Tránh 1 người nhận 3 thông báo nếu họ vừa là Học viên, Giáo viên và Admin (Lọc trùng lặp).
        $notifiedusers = [];

        // Tạo Mẫu HTML thông qua Static Methods để tái sử dụng ở môi trường Live & Test.
        $htmlstudent = email_templates::get_student_email_html($a);
        $htmlalert   = email_templates::get_admin_email_html($a);

        // Gửi theo từng kênh đã bật. $notifiedusers truyền tham chiếu để lọc trùng người nhận.
        if ($mailstudents) {
            self::notify_student($user, $course, $a, $courseurl, $htmlstudent, $notifiedusers);
        }
        if ($mailteachers) {
            self::notify_teachers($context, $course, $a, $profileurl, $htmlalert, $notifiedusers);
        }
        if ($mailadmins) {
            self::notify_admins($course, $a, $profileurl, $htmlalert, $notifiedusers);
        }

        return true;
    }

    /**
     * Gửi bell notification + email chào mừng cho học viên.
     *
     * @param \stdClass $user
     * @param \stdClass $course
     * @param \stdClass $a Dữ liệu template email
     * @param string $courseurl
     * @param string $htmlstudent HTML email học viên
     * @param int[] $notifiedusers Danh sách id đã nhận (cập nhật tham chiếu)
     * @return void
     */
    private static function notify_student(
        $user,
        $course,
        $a,
        string $courseurl,
        string $htmlstudent,
        array &$notifiedusers
    ): void {
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
            $htmlstudent
        );

        $notifiedusers[] = $user->id;
    }

    /**
     * Gửi bell notification + email cho giáo viên của khóa học (bỏ qua người đã nhận).
     *
     * @param \context_course $context
     * @param \stdClass $course
     * @param \stdClass $a Dữ liệu template email
     * @param string $profileurl
     * @param string $htmlalert HTML email thông báo
     * @param int[] $notifiedusers Danh sách id đã nhận (cập nhật tham chiếu)
     * @return void
     */
    private static function notify_teachers(
        $context,
        $course,
        $a,
        string $profileurl,
        string $htmlalert,
        array &$notifiedusers
    ): void {
        $teachers = get_enrolled_users($context, 'moodle/course:update', 0, 'u.*', null, 0, 0, true);
        if (!$teachers) {
            return;
        }
        $contact = get_admin();
        foreach ($teachers as $teacher) {
            // Nếu giáo viên cũng chính là người vừa được ghi danh (hoặc đã nhận mail rồi) thì bỏ qua!
            if (in_array($teacher->id, $notifiedusers)) {
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
                $htmlalert
            );
            $notifiedusers[] = $teacher->id;
        }
    }

    /**
     * Gửi bell notification + email cho các admin site (bỏ qua người đã nhận).
     *
     * @param \stdClass $course
     * @param \stdClass $a Dữ liệu template email
     * @param string $profileurl
     * @param string $htmlalert HTML email thông báo
     * @param int[] $notifiedusers Danh sách id đã nhận (cập nhật tham chiếu)
     * @return void
     */
    private static function notify_admins($course, $a, string $profileurl, string $htmlalert, array &$notifiedusers): void {
        $admins = get_admins();
        $contact = get_admin();
        foreach ($admins as $admin) {
            // Nếu Admin cũng là Giáo viên hoặc là người đang test thì bỏ qua để tránh spam!
            if (in_array($admin->id, $notifiedusers)) {
                continue;
            }

            // Bell notification.
            $msg = new \core\message\message();
            $msg->component         = 'enrol_sepay';
            $msg->name              = 'sepay_enrolment';
            $msg->userfrom          = $contact;
            $msg->userto            = $admin;
            $msg->subject           = get_string('email_admin_subject', 'enrol_sepay', $a);
            $msg->fullmessage       = get_string('email_admin_body', 'enrol_sepay', $a);
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
                get_string('email_admin_subject', 'enrol_sepay', $a),
                get_string('email_admin_body', 'enrol_sepay', $a),
                $htmlalert
            );
        }
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
            email_templates::get_rejection_email_html($a)
        );

        return true;
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
            email_templates::get_unenrolment_email_html($a)
        );

        return true;
    }
}
