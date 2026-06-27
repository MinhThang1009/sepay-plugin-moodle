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
 * Kiểm thử bulk operation chỉnh sửa hàng loạt (editselectedusers::process).
 *
 * @package    enrol_sepay
 * @copyright  2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \enrol_sepay_editselectedusers_operation
 */
final class locallib_test extends \advanced_testcase {
    /**
     * process() cập nhật status của user_enrolments cho user được chọn.
     */
    public function test_editselected_process_updates_status(): void {
        global $DB, $CFG, $PAGE;
        require_once($CFG->dirroot . '/enrol/locallib.php');
        require_once($CFG->dirroot . '/enrol/sepay/locallib.php');

        $this->resetAfterTest();
        $this->setAdminUser();

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
        $plugin->enrol_user($instance, $user->id, $studentrole->id, 0, 0, ENROL_USER_ACTIVE);

        $ue = $DB->get_record('user_enrolments', ['enrolid' => $instance->id, 'userid' => $user->id], '*', MUST_EXIST);
        $this->assertEquals(ENROL_USER_ACTIVE, (int)$ue->status);

        // Dựng cấu trúc $users như trang participants truyền vào bulk operation.
        $enrolment = (object)['id' => $ue->id, 'enrolmentinstance' => $instance];
        $userobj = (object)['id' => $user->id, 'enrolments' => [$enrolment]];
        $properties = (object)['status' => ENROL_USER_SUSPENDED, 'timestart' => 0, 'timeend' => 0];

        $PAGE->set_url('/user/index.php', ['id' => $course->id]);
        $manager = new \course_enrolment_manager($PAGE, $course);
        $op = new \enrol_sepay_editselectedusers_operation($manager, $plugin);

        $result = $op->process($manager, [$userobj], $properties);

        $this->assertTrue($result);
        $this->assertEquals(
            ENROL_USER_SUSPENDED,
            (int)$DB->get_field('user_enrolments', 'status', ['id' => $ue->id])
        );
    }

    /**
     * process() trả true mà không đổi gì khi properties không có trường cần cập nhật.
     */
    public function test_editselected_process_noop_returns_true(): void {
        global $DB, $CFG, $PAGE;
        require_once($CFG->dirroot . '/enrol/locallib.php');
        require_once($CFG->dirroot . '/enrol/sepay/locallib.php');

        $this->resetAfterTest();
        $this->setAdminUser();

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
        $plugin->enrol_user($instance, $user->id, $studentrole->id, 0, 0, ENROL_USER_ACTIVE);
        $ue = $DB->get_record('user_enrolments', ['enrolid' => $instance->id, 'userid' => $user->id], '*', MUST_EXIST);

        $enrolment = (object)['id' => $ue->id, 'enrolmentinstance' => $instance];
        $userobj = (object)['id' => $user->id, 'enrolments' => [$enrolment]];
        // Không status hợp lệ, không timestart/timeend → không có gì để cập nhật.
        $properties = (object)['status' => -1, 'timestart' => 0, 'timeend' => 0];

        $PAGE->set_url('/user/index.php', ['id' => $course->id]);
        $manager = new \course_enrolment_manager($PAGE, $course);
        $op = new \enrol_sepay_editselectedusers_operation($manager, $plugin);

        $this->assertTrue($op->process($manager, [$userobj], $properties));
        // Status giữ nguyên ACTIVE.
        $this->assertEquals(
            ENROL_USER_ACTIVE,
            (int)$DB->get_field('user_enrolments', 'status', ['id' => $ue->id])
        );
    }
}
