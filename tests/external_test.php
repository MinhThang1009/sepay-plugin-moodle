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

namespace enrol_sepay;

/**
 * Kiểm thử web service enrol_sepay\external::check_transaction_status.
 *
 * @package    enrol_sepay
 * @copyright  2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \enrol_sepay\external::check_transaction_status
 */
final class external_test extends \advanced_testcase {
    /**
     * Tạo course, user và một enrol instance sepay để test.
     *
     * @return array [course, user, instance, plugin]
     */
    protected function setup_fixture(): array {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $plugin = enrol_get_plugin('sepay');
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        $instanceid = $plugin->add_instance($course, [
            'status' => ENROL_INSTANCE_ENABLED,
            'cost' => 100,
            'currency' => 'VND',
            'roleid' => $studentrole->id,
        ]);
        $instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);
        return [$course, $user, $instance, $plugin];
    }

    /**
     * Chèn một giao dịch với trạng thái cho trước.
     *
     * @param \stdClass $user
     * @param \stdClass $course
     * @param \stdClass $instance
     * @param string $status
     * @return void
     */
    protected function insert_transaction($user, $course, $instance, string $status): void {
        global $DB;
        $DB->insert_record('enrol_sepay_transactions', (object)[
            'userid' => $user->id,
            'courseid' => $course->id,
            'instanceid' => $instance->id,
            'amount' => 100,
            'currency' => 'VND',
            'status' => $status,
            'timecreated' => time(),
            'timeprocessed' => 0,
        ]);
    }

    /**
     * Đã ghi danh, không có giao dịch: enrolled=true, các cờ khác false.
     */
    public function test_enrolled_no_transaction(): void {
        $this->resetAfterTest();
        [$course, $user, $instance, $plugin] = $this->setup_fixture();
        $plugin->enrol_user($instance, $user->id, $instance->roleid);
        $this->setUser($user);

        $result = external::check_transaction_status($course->id);

        $this->assertTrue($result['enrolled']);
        $this->assertFalse($result['pending']);
        $this->assertFalse($result['processed']);
        $this->assertFalse($result['rejected']);
        $this->assertFalse($result['unenrolled']);
    }

    /**
     * Có giao dịch pending, chưa ghi danh: pending=true, enrolled=false.
     */
    public function test_pending_not_enrolled(): void {
        $this->resetAfterTest();
        [$course, $user, $instance] = $this->setup_fixture();
        $this->insert_transaction($user, $course, $instance, 'pending');
        $this->setUser($user);

        $result = external::check_transaction_status($course->id);

        $this->assertFalse($result['enrolled']);
        $this->assertTrue($result['pending']);
    }

    /**
     * Có giao dịch processed: processed=true (không phụ thuộc enrolled).
     */
    public function test_processed(): void {
        $this->resetAfterTest();
        [$course, $user, $instance] = $this->setup_fixture();
        $this->insert_transaction($user, $course, $instance, 'processed');
        $this->setUser($user);

        $result = external::check_transaction_status($course->id);

        $this->assertTrue($result['processed']);
    }

    /**
     * Có giao dịch rejected, chưa ghi danh: rejected=true.
     */
    public function test_rejected_not_enrolled(): void {
        $this->resetAfterTest();
        [$course, $user, $instance] = $this->setup_fixture();
        $this->insert_transaction($user, $course, $instance, 'rejected');
        $this->setUser($user);

        $result = external::check_transaction_status($course->id);

        $this->assertFalse($result['enrolled']);
        $this->assertTrue($result['rejected']);
    }

    /**
     * Có giao dịch unenrolled, chưa ghi danh: unenrolled=true.
     */
    public function test_unenrolled_not_enrolled(): void {
        $this->resetAfterTest();
        [$course, $user, $instance] = $this->setup_fixture();
        $this->insert_transaction($user, $course, $instance, 'unenrolled');
        $this->setUser($user);

        $result = external::check_transaction_status($course->id);

        $this->assertFalse($result['enrolled']);
        $this->assertTrue($result['unenrolled']);
    }

    /**
     * Nhiều trạng thái cùng lúc (pending + processed), chưa ghi danh:
     * cả pending lẫn processed đều true (mỗi status xét độc lập).
     */
    public function test_multiple_statuses(): void {
        $this->resetAfterTest();
        [$course, $user, $instance] = $this->setup_fixture();
        $this->insert_transaction($user, $course, $instance, 'pending');
        $this->insert_transaction($user, $course, $instance, 'processed');
        $this->setUser($user);

        $result = external::check_transaction_status($course->id);

        $this->assertTrue($result['pending']);
        $this->assertTrue($result['processed']);
    }
}
