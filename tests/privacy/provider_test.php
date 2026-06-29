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

namespace enrol_sepay\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Kiểm thử Privacy Subsystem của enrol_sepay.
 *
 * @package    enrol_sepay
 * @copyright  2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \enrol_sepay\privacy\provider
 */
final class provider_test extends \advanced_testcase {
    /**
     * Tạo một bản ghi giao dịch SePay cho user trong course.
     *
     * @param int $userid
     * @param int $courseid
     * @param int $instanceid
     * @return int ID bản ghi vừa tạo
     */
    private function create_transaction(int $userid, int $courseid, int $instanceid): int {
        global $DB;
        return $DB->insert_record('enrol_sepay_transactions', (object) [
            'userid'              => $userid,
            'courseid'            => $courseid,
            'instanceid'          => $instanceid,
            'amount'              => 100,
            'currency'            => 'VND',
            'transaction_content' => 'Nguyen Van A ck SP' . $courseid . 'U' . $userid,
            'transaction_ref'     => 'REF' . $userid . $courseid,
            'gateway'             => 'Vietcombank',
            'status'              => 'processed',
            'ip_address'          => '127.0.0.1',
            'timecreated'         => time(),
            'timeprocessed'       => time(),
            'email_sent'          => 0,
            'rejection_notified'  => 0,
        ]);
    }

    /**
     * get_metadata phải khai báo ít nhất một mục dữ liệu.
     */
    public function test_get_metadata(): void {
        $collection = provider::get_metadata(new collection('enrol_sepay'));
        $this->assertNotEmpty($collection->get_collection());
    }

    /**
     * get_contexts_for_userid trả về context khóa học nơi user có giao dịch.
     */
    public function test_get_contexts_for_userid(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->create_transaction($user->id, $course->id, 0);

        $contextlist = provider::get_contexts_for_userid($user->id);
        $coursecontext = \context_course::instance($course->id);

        $this->assertContains((int) $coursecontext->id, $contextlist->get_contextids());
    }

    /**
     * get_users_in_context trả về user có giao dịch trong context khóa học.
     */
    public function test_get_users_in_context(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->create_transaction($user->id, $course->id, 0);

        $coursecontext = \context_course::instance($course->id);
        $userlist = new userlist($coursecontext, 'enrol_sepay');
        provider::get_users_in_context($userlist);

        $this->assertContains((int) $user->id, $userlist->get_userids());
    }

    /**
     * export_user_data ghi dữ liệu cho context được duyệt.
     */
    public function test_export_user_data(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->create_transaction($user->id, $course->id, 0);

        $coursecontext = \context_course::instance($course->id);
        $approved = new approved_contextlist($user, 'enrol_sepay', [$coursecontext->id]);
        provider::export_user_data($approved);

        $this->assertTrue(writer::with_context($coursecontext)->has_any_data());
    }

    /**
     * delete_data_for_all_users_in_context xóa mọi giao dịch của khóa học.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->create_transaction($user->id, $course->id, 0);

        provider::delete_data_for_all_users_in_context(\context_course::instance($course->id));

        $this->assertEquals(0, $DB->count_records('enrol_sepay_transactions', ['courseid' => $course->id]));
    }

    /**
     * delete_data_for_user chỉ xóa giao dịch của đúng user trong context.
     */
    public function test_delete_data_for_user(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->create_transaction($user1->id, $course->id, 0);
        $this->create_transaction($user2->id, $course->id, 0);

        $coursecontext = \context_course::instance($course->id);
        $approved = new approved_contextlist($user1, 'enrol_sepay', [$coursecontext->id]);
        provider::delete_data_for_user($approved);

        $this->assertEquals(0, $DB->count_records('enrol_sepay_transactions',
            ['userid' => $user1->id, 'courseid' => $course->id]));
        $this->assertEquals(1, $DB->count_records('enrol_sepay_transactions',
            ['userid' => $user2->id, 'courseid' => $course->id]));
    }

    /**
     * delete_data_for_users chỉ xóa các user được chỉ định trong context.
     */
    public function test_delete_data_for_users(): void {
        global $DB;
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $this->create_transaction($user1->id, $course->id, 0);
        $this->create_transaction($user2->id, $course->id, 0);

        $coursecontext = \context_course::instance($course->id);
        $approved = new approved_userlist($coursecontext, 'enrol_sepay', [$user1->id]);
        provider::delete_data_for_users($approved);

        $this->assertEquals(0, $DB->count_records('enrol_sepay_transactions',
            ['userid' => $user1->id, 'courseid' => $course->id]));
        $this->assertEquals(1, $DB->count_records('enrol_sepay_transactions',
            ['userid' => $user2->id, 'courseid' => $course->id]));
    }
}
