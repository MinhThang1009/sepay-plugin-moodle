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
 * Tác vụ tự động xử lý các giao dịch SePay đã được phê duyệt nhưng thẻ trình duyệt bị đóng.
 *
 * Mặc định quét các giao dịch có trạng thái "processed" (đã xử lý/được phê duyệt)
 * nhưng người học trong bảng chưa có trạng thái is_enrolled thực tế.
 * Tác vụ này chạy mỗi 1 phút để bảo vệ dữ liệu chống mất mát khi rớt mạng.
 *
 * @package    enrol_sepay
 * @copyright  2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_sepay\task;

/**
 * Scheduled task: ghi danh các giao dịch đã duyệt nhưng chưa enrol (vd user đóng tab).
 */
class process_enrolments extends \core\task\scheduled_task {
    /**
     * Lấy tên mô tả của khối tác vụ này.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_process_enrolments', 'enrol_sepay');
    }

    /**
     * Thực thi tác vụ tự động ghi danh cho các giao dịch đã duyệt.
     */
    public function execute() {
        global $DB, $CFG;

        // Bắt buộc nạp thư viện plugin.
        require_once($CFG->dirroot . '/enrol/sepay/lib.php');
        $plugin = enrol_get_plugin('sepay');

        mtrace('Bắt đầu đồng bộ danh sách duyệt tự động...');

        // Truy vấn tất cả các giao dịch đã processed
        // Nhưng đối chiếu bảng user_enrolments để chắc chắn họ chưa thực sự được ghi danh.
        // LIMIT 100 tránh timeout khi có nhiều record tích tụ.
        // Dùng tham số phân trang của get_records_sql() thay vì LIMIT trong raw SQL
        // vì LIMIT với named param không hoạt động trên mọi DB driver (PostgreSQL, MSSQL).
        // JOIN có thêm ue.status = ACTIVE: giao dịch processed mà user CHƯA ghi danh ACTIVE
        // (chưa enrol, hoặc đã hết hạn/suspend và vừa gia hạn) đều được bắt để (re-)enrol.
        $sql = "SELECT t.*
                  FROM {enrol_sepay_transactions} t
             LEFT JOIN {user_enrolments} ue
                    ON t.userid = ue.userid AND t.instanceid = ue.enrolid AND ue.status = :active
                 WHERE t.status = 'processed'
                   AND ue.id IS NULL";

        $rs = $DB->get_records_sql($sql, ['active' => ENROL_USER_ACTIVE], 0, 100);

        if (!$rs) {
            mtrace('Không có giao dịch nào đang bị kẹt. Hệ thống sạch sẽ.');
            return;
        }

        $count = 0;
        foreach ($rs as $t) {
            if ($this->process_transaction($t, $plugin)) {
                $count++;
            }
        }

        mtrace("Hoàn tất quy trình đồng bộ. Tổng số học viên được thêm tự động: {$count}.");
    }

    /**
     * Xử lý một giao dịch processed: validate, ghi danh, gửi email.
     *
     * @param \stdClass $t Bản ghi giao dịch
     * @param \enrol_sepay_plugin $plugin
     * @return bool true nếu lần này đã ghi danh user
     */
    private function process_transaction($t, $plugin): bool {
        global $DB;

        $instance = $DB->get_record('enrol', ['id' => $t->instanceid], '*', IGNORE_MISSING);
        $user = $DB->get_record('user', ['id' => $t->userid], '*', IGNORE_MISSING);

        if (!$instance || !$user) {
            // Instance hoặc user bị xóa — mark 'rejected' để task không xử lý lại mãi.
            $DB->set_field('enrol_sepay_transactions', 'status', 'rejected', ['id' => $t->id]);
            mtrace("Giao dịch ID {$t->id}: instance/user không tồn tại, đã mark rejected.");
            return false;
        }

        // Bỏ qua nếu instance đang bị tắt.
        if ((int)$instance->status !== ENROL_INSTANCE_ENABLED) {
            return false;
        }

        // Tính toán thời gian ghi danh.
        if (!empty($instance->enrolperiod)) {
            $timestart = time();
            $timeend = $timestart + (int)$instance->enrolperiod;
        } else {
            $timestart = 0;
            $timeend = 0;
        }

        $roleid = !empty($instance->roleid) ? (int)$instance->roleid : (int)get_config('enrol_sepay', 'roleid');

        // Bỏ qua nếu role chưa cấu hình hợp lệ (tránh enrol_user với roleid=0 → ghi danh không gán role).
        // Không đổi status để task thử lại sau khi admin sửa config.
        if ($roleid <= 0) {
            mtrace("Giao dịch ID {$t->id}: roleid chưa cấu hình (<=0), bỏ qua để tránh ghi danh không có role.");
            return false;
        }

        try {
            // Ghi danh chính thức. ENROL_USER_ACTIVE để re-activate ue đang suspended (gia hạn).
            $plugin->enrol_user($instance, $user->id, $roleid, $timestart, $timeend, ENROL_USER_ACTIVE);
            mtrace("Đã Auto-Enrol học viên ID {$user->id} vào khóa học ID {$t->courseid}.");
        } catch (\Exception $e) {
            mtrace("Lỗi khi enrol giao dịch ID {$t->id} (user {$t->userid}, course {$t->courseid}): " . $e->getMessage());
            return false;
        }

        $this->send_welcome_if_needed($t, $user, $instance);
        return true;
    }

    /**
     * Gửi welcome email cho giao dịch nếu chưa gửi (lỗi email không block ghi danh).
     *
     * @param \stdClass $t Bản ghi giao dịch
     * @param \stdClass $user
     * @param \stdClass $instance
     * @return void
     */
    private function send_welcome_if_needed($t, $user, $instance): void {
        global $DB, $CFG;

        $course = $DB->get_record('course', ['id' => $t->courseid], '*', IGNORE_MISSING);
        if (!$course || $t->email_sent) {
            return;
        }
        try {
            require_once($CFG->dirroot . '/enrol/sepay/classes/util.php');
            // Gửi đúng một lần — chống gửi đúp khi webhook/complete_enrol chạy song song.
            \enrol_sepay\util::send_welcome_messages_once($t->id, $course, $user, $instance);
        } catch (\Exception $e) {
            mtrace("Lỗi gửi email giao dịch ID {$t->id}: " . $e->getMessage());
        }
    }
}
