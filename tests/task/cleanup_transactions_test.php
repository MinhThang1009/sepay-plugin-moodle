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

namespace enrol_sepay\task;

/**
 * Kiểm thử scheduled task cleanup_transactions.
 *
 * @package    enrol_sepay
 * @copyright  2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \enrol_sepay\task\cleanup_transactions
 */
final class cleanup_transactions_test extends \advanced_testcase {
    /**
     * Chèn một giao dịch với status + tuổi (số ngày trước) cho trước.
     *
     * @param string $status
     * @param int $daysago số ngày trước hiện tại của timecreated
     * @return int id bản ghi
     */
    protected function insert_txn(string $status, int $daysago): int {
        global $DB;
        return $DB->insert_record('enrol_sepay_transactions', (object)[
            'userid' => 1,
            'courseid' => 1,
            'instanceid' => 1,
            'amount' => 100,
            'currency' => 'VND',
            'status' => $status,
            'timecreated' => time() - ($daysago * 86400),
            'timeprocessed' => 0,
        ]);
    }

    /**
     * Chạy task, nuốt output mtrace.
     *
     * @return void
     */
    protected function run_task(): void {
        $task = new cleanup_transactions();
        ob_start();
        $task->execute();
        ob_get_clean();
    }

    /**
     * Dọn dẹp đang tắt: không xóa gì.
     */
    public function test_disabled_skips(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('auto_cleanup_enabled', 0, 'enrol_sepay');
        $this->insert_txn('processed', 400);

        $this->run_task();

        $this->assertEquals(1, $DB->count_records('enrol_sepay_transactions'));
    }

    /**
     * Pending quá hạn pending_retention_days: bị mark rejected.
     */
    public function test_pending_expired_rejected(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('auto_cleanup_enabled', 1, 'enrol_sepay');
        set_config('retention_days', 365, 'enrol_sepay');
        set_config('pending_retention_days', 30, 'enrol_sepay');
        $id = $this->insert_txn('pending', 31);

        $this->run_task();

        $this->assertSame('rejected', $DB->get_field('enrol_sepay_transactions', 'status', ['id' => $id]));
    }

    /**
     * Chiến lược archive: giao dịch cũ chuyển sang bảng archive.
     */
    public function test_archive_strategy(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('auto_cleanup_enabled', 1, 'enrol_sepay');
        set_config('retention_days', 365, 'enrol_sepay');
        set_config('archive_strategy', 'archive', 'enrol_sepay');
        $this->insert_txn('processed', 400);

        $this->run_task();

        $this->assertEquals(0, $DB->count_records('enrol_sepay_transactions'));
        $this->assertEquals(1, $DB->count_records('enrol_sepay_archive'));
    }

    /**
     * Chiến lược delete: giao dịch cũ bị xóa vĩnh viễn (không vào archive).
     */
    public function test_delete_strategy(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('auto_cleanup_enabled', 1, 'enrol_sepay');
        set_config('retention_days', 365, 'enrol_sepay');
        set_config('archive_strategy', 'delete', 'enrol_sepay');
        $this->insert_txn('rejected', 400);

        $this->run_task();

        $this->assertEquals(0, $DB->count_records('enrol_sepay_transactions'));
        $this->assertEquals(0, $DB->count_records('enrol_sepay_archive'));
    }

    /**
     * Chèn một bell notification pending_transaction với tuổi cho trước.
     *
     * @param int $daysago số ngày trước hiện tại của timecreated/timeread
     * @param bool $read đã đọc chưa (set timeread)
     * @return void
     */
    protected function insert_notification(int $daysago, bool $read): void {
        global $DB;
        $when = time() - ($daysago * 86400);
        $DB->insert_record('notifications', (object)[
            'useridfrom' => 1,
            'useridto' => 2,
            'subject' => 'test',
            'fullmessage' => '',
            'fullmessageformat' => FORMAT_PLAIN,
            'fullmessagehtml' => '',
            'smallmessage' => '',
            'component' => 'enrol_sepay',
            'eventtype' => 'pending_transaction',
            'timecreated' => $when,
            'timeread' => $read ? $when : null,
        ]);
    }

    /**
     * Config 'never' (mặc định): không xóa notification dù cũ.
     */
    public function test_notification_cleanup_never_keeps(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('auto_cleanup_enabled', 0, 'enrol_sepay');
        set_config('delete_read_notifications_delay', 'never', 'enrol_sepay');
        set_config('delete_all_notifications_delay', 'never', 'enrol_sepay');
        $this->insert_notification(10, true);

        $this->run_task();

        $this->assertEquals(1, $DB->count_records('notifications', ['component' => 'enrol_sepay']));
    }

    /**
     * Config delete_read_1day: notification đã đọc cũ hơn 1 ngày bị xóa, chưa đọc thì giữ.
     */
    public function test_notification_cleanup_read_enforced(): void {
        global $DB;
        $this->resetAfterTest();
        set_config('auto_cleanup_enabled', 0, 'enrol_sepay');
        set_config('delete_read_notifications_delay', 'delete_read_1day', 'enrol_sepay');
        set_config('delete_all_notifications_delay', 'never', 'enrol_sepay');
        $this->insert_notification(2, true);  // đã đọc, 2 ngày → xóa.
        $this->insert_notification(2, false); // chưa đọc → giữ (chỉ xóa "read").

        $this->run_task();

        $this->assertEquals(1, $DB->count_records('notifications', ['component' => 'enrol_sepay']));
    }
}
