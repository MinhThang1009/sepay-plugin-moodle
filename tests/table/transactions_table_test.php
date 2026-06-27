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

namespace enrol_sepay\table;

/**
 * Kiểm thử các bộ định dạng cột của transactions_table.
 *
 * @package    enrol_sepay
 * @copyright  2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \enrol_sepay\table\transactions_table
 */
final class transactions_table_test extends \advanced_testcase {
    /**
     * Khởi tạo một bảng trống để gọi các col_* method.
     *
     * @return transactions_table
     */
    protected function make_table(): transactions_table {
        global $CFG;
        require_once($CFG->dirroot . '/enrol/sepay/classes/table/transactions_table.php');
        return new transactions_table('test', new \moodle_url('/enrol/sepay/transactions.php'), []);
    }

    /**
     * col_amount: định dạng số tiền + currency đã escape.
     */
    public function test_col_amount(): void {
        $this->resetAfterTest();
        $table = $this->make_table();
        $html = $table->col_amount((object)['amount' => 100000, 'currency' => 'VND']);
        $this->assertStringContainsString('100,000', $html);
        $this->assertStringContainsString('VND', $html);
    }

    /**
     * col_status: mỗi trạng thái cho ra badge class tương ứng.
     */
    public function test_col_status_badges(): void {
        $this->resetAfterTest();
        $table = $this->make_table();
        $this->assertStringContainsString('badge-warning', $table->col_status((object)['status' => 'pending']));
        $this->assertStringContainsString('badge-danger', $table->col_status((object)['status' => 'rejected']));
        $this->assertStringContainsString('badge-secondary', $table->col_status((object)['status' => 'unenrolled']));
        $this->assertStringContainsString('badge-success', $table->col_status((object)['status' => 'processed']));
    }

    /**
     * col_checkbox: chứa value=id và data-status đã escape.
     */
    public function test_col_checkbox(): void {
        $this->resetAfterTest();
        $table = $this->make_table();
        $html = $table->col_checkbox((object)['id' => 7, 'userid' => 3, 'status' => 'pending']);
        $this->assertStringContainsString('value="7"', $html);
        $this->assertStringContainsString('data-userid="3"', $html);
        $this->assertStringContainsString('data-status="pending"', $html);
    }

    /**
     * col_transaction_content: escape nội dung.
     */
    public function test_col_transaction_content_escaped(): void {
        $this->resetAfterTest();
        $table = $this->make_table();
        $html = $table->col_transaction_content((object)['transaction_content' => '<b>x</b>']);
        $this->assertStringNotContainsString('<b>', $html);
    }

    /**
     * col_timeprocessed: trả '-' khi chưa xử lý, ngược lại là ngày.
     */
    public function test_col_timeprocessed(): void {
        $this->resetAfterTest();
        $table = $this->make_table();
        $this->assertSame('-', $table->col_timeprocessed((object)['timeprocessed' => 0]));
        $this->assertNotSame('-', $table->col_timeprocessed((object)['timeprocessed' => time()]));
    }

    /**
     * col_ip_address: không có dữ liệu IP map thì hiển thị N/A.
     */
    public function test_col_ip_address_na(): void {
        $this->resetAfterTest();
        $table = $this->make_table();
        $html = $table->col_ip_address((object)['userid' => 99, 'ip_address' => '']);
        $this->assertStringContainsString('N/A', $html);
    }

    /**
     * col_actions: pending có nút duyệt/từ chối; processed có nút xóa.
     */
    public function test_col_actions(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $table = $this->make_table();
        $pending = $table->col_actions((object)['id' => 1, 'status' => 'pending']);
        $this->assertStringContainsString('action=approve', $pending);
        $this->assertStringContainsString('action=reject', $pending);
        $processed = $table->col_actions((object)['id' => 2, 'status' => 'processed']);
        $this->assertStringContainsString('action=delete', $processed);
    }

    /**
     * preload_ip_maps phát hiện IP dùng chung giữa nhiều user (cảnh báo trùng IP).
     */
    public function test_preload_ip_maps_duplicate(): void {
        global $DB;
        $this->resetAfterTest();
        if (!$DB->get_manager()->table_exists('logstore_standard_log')) {
            $this->markTestSkipped('logstore_standard_log chưa cài');
        }

        // 2 user đăng nhập cùng 1 IP.
        foreach ([10, 11] as $uid) {
            $DB->insert_record('logstore_standard_log', (object)[
                'eventname' => '\\core\\event\\user_loggedin',
                'component' => 'core',
                'action' => 'loggedin',
                'target' => 'user',
                'crud' => 'r',
                'edulevel' => 0,
                'contextid' => 1,
                'contextlevel' => 10,
                'contextinstanceid' => 0,
                'userid' => $uid,
                'courseid' => 0,
                'anonymous' => 0,
                'ip' => '203.0.113.7',
                'timecreated' => time(),
            ]);
        }

        $table = $this->make_table();
        $method = new \ReflectionMethod($table, 'preload_ip_maps');
        $method->setAccessible(true);
        $method->invoke($table, [10, 11]);

        $prop = new \ReflectionProperty($table, 'ipusermap');
        $prop->setAccessible(true);
        $ipusermap = $prop->getValue($table);

        $this->assertArrayHasKey('203.0.113.7', $ipusermap);
        $this->assertCount(2, $ipusermap['203.0.113.7']);
    }
}
