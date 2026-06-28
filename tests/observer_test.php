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
 * Kiểm thử observer xử lý sự kiện ghi danh (suspend → mở lại renewal).
 *
 * @package    enrol_sepay
 * @copyright  2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \enrol_sepay\observer
 */
final class observer_test extends \advanced_testcase {
    /**
     * Tạo course, user và enrol instance sepay.
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
     * Chèn một giao dịch processed cho user/course/instance.
     *
     * @param \stdClass $user
     * @param \stdClass $course
     * @param \stdClass $instance
     * @return int id giao dịch
     */
    protected function insert_processed($user, $course, $instance): int {
        global $DB;
        return $DB->insert_record('enrol_sepay_transactions', (object)[
            'userid' => $user->id,
            'courseid' => $course->id,
            'instanceid' => $instance->id,
            'amount' => 100,
            'currency' => 'VND',
            'status' => 'processed',
            'timecreated' => time(),
            'timeprocessed' => time(),
        ]);
    }

    /**
     * Suspend ghi danh sepay (hết hạn) → giao dịch processed chuyển sang unenrolled.
     */
    public function test_suspend_marks_processed_unenrolled(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $user, $instance, $plugin] = $this->setup_fixture();
        $plugin->enrol_user($instance, $user->id, $instance->roleid, 0, 0, ENROL_USER_ACTIVE);
        $txnid = $this->insert_processed($user, $course, $instance);

        // Suspend → bắn user_enrolment_updated → observer xử lý.
        $plugin->update_user_enrol($instance, $user->id, ENROL_USER_SUSPENDED);

        $this->assertSame('unenrolled', $DB->get_field('enrol_sepay_transactions', 'status', ['id' => $txnid]));
    }

    /**
     * Re-activate (suspended → active) KHÔNG đánh dấu giao dịch (guard status !== SUSPENDED).
     */
    public function test_reactivate_does_not_mark(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $user, $instance, $plugin] = $this->setup_fixture();
        $plugin->enrol_user($instance, $user->id, $instance->roleid, 0, 0, ENROL_USER_SUSPENDED);
        $txnid = $this->insert_processed($user, $course, $instance);

        // Re-activate → updated với status ACTIVE → observer return sớm, không đụng giao dịch.
        $plugin->update_user_enrol($instance, $user->id, ENROL_USER_ACTIVE);

        $this->assertSame('processed', $DB->get_field('enrol_sepay_transactions', 'status', ['id' => $txnid]));
    }

    /**
     * Unenrol thật (xóa ghi danh) vẫn chuyển processed → unenrolled như trước.
     */
    public function test_delete_still_marks_unenrolled(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $user, $instance, $plugin] = $this->setup_fixture();
        $plugin->enrol_user($instance, $user->id, $instance->roleid, 0, 0, ENROL_USER_ACTIVE);
        $txnid = $this->insert_processed($user, $course, $instance);

        $plugin->unenrol_user($instance, $user->id);

        $this->assertSame('unenrolled', $DB->get_field('enrol_sepay_transactions', 'status', ['id' => $txnid]));
    }
}
