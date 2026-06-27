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
 * Kiểm thử scheduled task process_enrolments.
 *
 * @package    enrol_sepay
 * @copyright  2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \enrol_sepay\task\process_enrolments
 */
final class process_enrolments_test extends \advanced_testcase {
    /**
     * Tạo course, user và enrol instance sepay.
     *
     * @param int|null $roleid roleid cho instance (null = role student)
     * @return array [course, user, instance]
     */
    protected function setup_fixture(?int $roleid = null): array {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $plugin = enrol_get_plugin('sepay');
        if ($roleid === null) {
            $roleid = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST)->id;
        }
        $instanceid = $plugin->add_instance($course, [
            'status' => ENROL_INSTANCE_ENABLED,
            'cost' => 100,
            'currency' => 'VND',
            'roleid' => $roleid,
        ]);
        $instance = $DB->get_record('enrol', ['id' => $instanceid], '*', MUST_EXIST);
        return [$course, $user, $instance];
    }

    /**
     * Chèn giao dịch processed cho user/course/instance.
     *
     * @param int $userid
     * @param \stdClass $course
     * @param \stdClass $instance
     * @return void
     */
    protected function insert_processed(int $userid, $course, $instance): void {
        global $DB;
        $DB->insert_record('enrol_sepay_transactions', (object)[
            'userid' => $userid,
            'courseid' => $course->id,
            'instanceid' => $instance->id,
            'amount' => 100,
            'currency' => 'VND',
            'status' => 'processed',
            'timecreated' => time(),
            'timeprocessed' => time(),
            'email_sent' => 1,
        ]);
    }

    /**
     * Chạy task, nuốt output mtrace.
     *
     * @return void
     */
    protected function run_task(): void {
        $task = new process_enrolments();
        ob_start();
        $task->execute();
        ob_get_clean();
    }

    /**
     * Không có giao dịch processed: chạy không lỗi.
     */
    public function test_no_processed_transactions(): void {
        $this->resetAfterTest();
        $this->run_task();
        $this->assertCount(0, $GLOBALS['DB']->get_records('user_enrolments'));
    }

    /**
     * Giao dịch processed hợp lệ: user được ghi danh.
     */
    public function test_processed_enrols_user(): void {
        $this->resetAfterTest();
        [$course, $user, $instance] = $this->setup_fixture();
        $this->insert_processed($user->id, $course, $instance);

        $this->run_task();

        $this->assertTrue(is_enrolled(\context_course::instance($course->id), $user));
    }

    /**
     * Instance bị tắt: không ghi danh.
     */
    public function test_disabled_instance_skipped(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $user, $instance] = $this->setup_fixture();
        $DB->set_field('enrol', 'status', ENROL_INSTANCE_DISABLED, ['id' => $instance->id]);
        $this->insert_processed($user->id, $course, $instance);

        $this->run_task();

        $this->assertFalse(is_enrolled(\context_course::instance($course->id), $user));
    }

    /**
     * User không tồn tại: giao dịch bị mark rejected.
     */
    public function test_missing_user_marks_rejected(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $user, $instance] = $this->setup_fixture();
        $this->insert_processed(99999, $course, $instance);

        $this->run_task();

        $txn = $DB->get_record('enrol_sepay_transactions', ['courseid' => $course->id]);
        $this->assertSame('rejected', $txn->status);
    }

    /**
     * roleid <= 0: bỏ qua, không ghi danh, status giữ nguyên processed.
     */
    public function test_roleid_zero_skipped(): void {
        global $DB;
        $this->resetAfterTest();
        [$course, $user, $instance] = $this->setup_fixture(0);
        set_config('roleid', 0, 'enrol_sepay');
        $this->insert_processed($user->id, $course, $instance);

        $this->run_task();

        $this->assertFalse(is_enrolled(\context_course::instance($course->id), $user));
        $txn = $DB->get_record('enrol_sepay_transactions', ['userid' => $user->id]);
        $this->assertSame('processed', $txn->status);
    }
}
