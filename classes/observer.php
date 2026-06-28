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
 * Event observer cho plugin enrol_sepay.
 *
 * @package    enrol_sepay
 * @copyright  2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Commenting.InlineComment.NotCapital -- Comment tiếng Việt; sniff chỉ chấp nhận chữ hoa ASCII (Đ/Ư... bị coi là thường).

namespace enrol_sepay;

/**
 * Observer xử lý sự kiện ghi danh của plugin enrol_sepay.
 */
class observer {
    /**
     * Xử lý sự kiện khi một user bị unenrol khỏi khóa học.
     *
     * Khi admin (hoặc user tự) unenrol, đánh dấu tất cả transaction 'processed'
     * của user đó sang 'unenrolled' để tác vụ process_enrolments không tự enrol lại.
     * Lịch sử thanh toán vẫn được giữ nguyên trong bảng.
     *
     * @param \core\event\user_enrolment_deleted $event
     * @return void
     */
    public static function user_enrolment_deleted(\core\event\user_enrolment_deleted $event): void {
        global $DB, $CFG;

        // Chỉ xử lý khi unenrol từ enrol plugin sepay.
        if (($event->other['enrol'] ?? '') !== 'sepay') {
            return;
        }

        $userid     = $event->relateduserid;
        $courseid   = $event->courseid;
        $instanceid = $event->other['userenrolment']['enrolid'] ?? 0;

        if (!$userid || !$courseid || !$instanceid) {
            return;
        }

        // Đổi tất cả transaction 'processed' của user này trong instance này sang 'unenrolled'.
        // Đồng thời mark 'pending' → 'rejected' để tránh process_enrolments tự enrol lại.
        try {
            $DB->set_field('enrol_sepay_transactions', 'status', 'unenrolled', [
                'userid'     => $userid,
                'courseid'   => $courseid,
                'instanceid' => $instanceid,
                'status'     => 'processed',
            ]);
            $DB->set_field('enrol_sepay_transactions', 'status', 'rejected', [
                'userid'     => $userid,
                'courseid'   => $courseid,
                'instanceid' => $instanceid,
                'status'     => 'pending',
            ]);
        } catch (\dml_exception $e) {
            debugging('enrol_sepay observer: set_field failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        // Gửi thông báo hủy ghi danh cho student.
        try {
            $user   = $DB->get_record('user', ['id' => $userid], '*', IGNORE_MISSING);
            $course = $DB->get_record('course', ['id' => $courseid], '*', IGNORE_MISSING);
            if ($user && $course) {
                require_once($CFG->dirroot . '/enrol/sepay/classes/util.php');
                \enrol_sepay\util::send_unenrolment_notification($course, $user);
            }
        } catch (\Exception $e) {
            debugging('enrol_sepay observer: send_unenrolment_notification failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Xử lý khi một ghi danh được cập nhật.
     *
     * Khi ghi danh sepay chuyển sang SUSPENDED (vd hết hạn enrolperiod với expiredaction
     * SUSPEND/SUSPENDNOROLES — Moodle chỉ bắn event này, KHÔNG bắn user_enrolment_deleted),
     * đánh dấu các giao dịch 'processed' của user trong instance đó sang 'unenrolled'. Nhờ đó
     * trang ghi danh hiển thị lại form QR để user trả tiền gia hạn, và không cho gia hạn miễn
     * phí (chỉ giao dịch 'processed' MỚI mới kích hoạt enrol lại). KHÔNG gửi email (suspend
     * khác với hủy ghi danh). Bulk-edit admin suspend cũng vào đây — nhất quán: suspended ⇒ renew được.
     *
     * @param \core\event\user_enrolment_updated $event
     * @return void
     */
    public static function user_enrolment_updated(\core\event\user_enrolment_updated $event): void {
        global $DB;

        // Chỉ xử lý enrolment của plugin sepay.
        if (($event->other['enrol'] ?? '') !== 'sepay') {
            return;
        }

        // Event _updated KHÔNG mang enrolid/status trong 'other' (khác _deleted) → lấy lại ue.
        $ue = $DB->get_record('user_enrolments', ['id' => $event->objectid]);
        if (!$ue || (int)$ue->status !== ENROL_USER_SUSPENDED) {
            return;
        }

        // Chỉ đụng giao dịch 'processed' (đã tiêu thụ) → 'unenrolled'; KHÔNG đụng pending/rejected.
        try {
            $DB->set_field('enrol_sepay_transactions', 'status', 'unenrolled', [
                'userid'     => $event->relateduserid,
                'courseid'   => $event->courseid,
                'instanceid' => $ue->enrolid,
                'status'     => 'processed',
            ]);
        } catch (\dml_exception $e) {
            debugging('enrol_sepay observer: set_field (suspend) failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
}
