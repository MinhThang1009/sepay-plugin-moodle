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
 * Kiểm thử enrol_sepay_plugin, tập trung vào enrol_page_hook (chọn nhánh hiển thị).
 *
 * @package    enrol_sepay
 * @copyright  2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \enrol_sepay_plugin
 */
final class lib_test extends \advanced_testcase {
    /**
     * Tạo course, user và enrol instance sepay với giá cho trước.
     *
     * @param float $cost
     * @return array [course, user, instance, plugin]
     */
    protected function setup_fixture(float $cost = 100): array {
        global $DB;
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $plugin = enrol_get_plugin('sepay');
        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        $instanceid = $plugin->add_instance($course, [
            'status' => ENROL_INSTANCE_ENABLED,
            'cost' => $cost,
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
     * Đã ghi danh: hook trả về chuỗi rỗng (không hiển thị gì).
     */
    public function test_hook_enrolled_returns_empty(): void {
        $this->resetAfterTest();
        [, $user, $instance, $plugin] = $this->setup_fixture();
        $plugin->enrol_user($instance, $user->id, $instance->roleid);
        $this->setUser($user);

        $this->assertSame('', $plugin->enrol_page_hook($instance));
    }

    /**
     * Khách (guest) + có giá: hiển thị link đăng nhập.
     */
    public function test_hook_guest_shows_login(): void {
        $this->resetAfterTest();
        [, , $instance, $plugin] = $this->setup_fixture();
        $this->setGuestUser();

        $out = $plugin->enrol_page_hook($instance);

        $this->assertStringContainsString('/login/', $out);
    }

    /**
     * Giao dịch pending: hiển thị alert-info.
     */
    public function test_hook_pending(): void {
        $this->resetAfterTest();
        [$course, $user, $instance, $plugin] = $this->setup_fixture();
        $this->insert_transaction($user, $course, $instance, 'pending');
        $this->setUser($user);

        $this->assertStringContainsString('alert-info', $plugin->enrol_page_hook($instance));
    }

    /**
     * Giao dịch processed: hiển thị alert-success.
     */
    public function test_hook_processed(): void {
        $this->resetAfterTest();
        [$course, $user, $instance, $plugin] = $this->setup_fixture();
        $this->insert_transaction($user, $course, $instance, 'processed');
        $this->setUser($user);

        $this->assertStringContainsString('alert-success', $plugin->enrol_page_hook($instance));
    }

    /**
     * Giao dịch rejected: hiển thị alert-danger.
     */
    public function test_hook_rejected(): void {
        $this->resetAfterTest();
        [$course, $user, $instance, $plugin] = $this->setup_fixture();
        $this->insert_transaction($user, $course, $instance, 'rejected');
        $this->setUser($user);

        $this->assertStringContainsString('alert-danger', $plugin->enrol_page_hook($instance));
    }

    /**
     * Chưa có giao dịch + có giá: hiển thị form QR (không phải alert trạng thái).
     */
    public function test_hook_qr_form(): void {
        $this->resetAfterTest();
        [, $user, $instance, $plugin] = $this->setup_fixture();
        $this->setUser($user);

        $out = $plugin->enrol_page_hook($instance);

        $this->assertNotSame('', $out);
        $this->assertStringNotContainsString('alert-info', $out);
        $this->assertStringNotContainsString('alert-success', $out);
        $this->assertStringNotContainsString('alert-danger', $out);
    }

    /**
     * Giá chưa cấu hình (instance=0, global trống): hook trả về chuỗi rỗng.
     */
    public function test_hook_no_cost_returns_empty(): void {
        $this->resetAfterTest();
        [, $user, $instance, $plugin] = $this->setup_fixture(0);
        $this->setUser($user);

        $this->assertSame('', $plugin->enrol_page_hook($instance));
    }

    /**
     * can_add_instance: chỉ cho phép 1 instance sepay mỗi khóa học.
     */
    public function test_can_add_instance_limited_to_one(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $plugin = enrol_get_plugin('sepay');

        // Chưa có instance nào → cho phép thêm.
        $this->assertTrue($plugin->can_add_instance($course->id));

        $studentrole = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        $plugin->add_instance($course, [
            'status' => ENROL_INSTANCE_ENABLED,
            'cost' => 100,
            'currency' => 'VND',
            'roleid' => $studentrole->id,
        ]);

        // Đã có 1 instance sepay → chặn thêm instance thứ 2.
        $this->assertFalse($plugin->can_add_instance($course->id));
    }

    /**
     * Ghi danh ACTIVE (chưa hết hạn): hook trả rỗng (đã học, không hiện gì).
     */
    public function test_hook_active_returns_empty(): void {
        $this->resetAfterTest();
        [, $user, $instance, $plugin] = $this->setup_fixture();
        $plugin->enrol_user($instance, $user->id, $instance->roleid, 0, 0, ENROL_USER_ACTIVE);
        $this->setUser($user);

        $this->assertSame('', $plugin->enrol_page_hook($instance));
    }

    /**
     * Ghi danh bị SUSPEND (hết hạn với expiredaction suspend): hook KHÔNG rỗng → hiện lại form gia hạn.
     */
    public function test_hook_suspended_shows_renewal(): void {
        $this->resetAfterTest();
        [, $user, $instance, $plugin] = $this->setup_fixture();
        $plugin->enrol_user($instance, $user->id, $instance->roleid, 0, 0, ENROL_USER_SUSPENDED);
        $this->setUser($user);

        $this->assertNotSame('', $plugin->enrol_page_hook($instance));
    }

    /**
     * Ghi danh ACTIVE nhưng đã quá timeend (hết hạn): hook KHÔNG rỗng → cho gia hạn.
     */
    public function test_hook_expired_timeend_shows_renewal(): void {
        $this->resetAfterTest();
        [, $user, $instance, $plugin] = $this->setup_fixture();
        $plugin->enrol_user($instance, $user->id, $instance->roleid, time() - 100000, time() - 1, ENROL_USER_ACTIVE);
        $this->setUser($user);

        $this->assertNotSame('', $plugin->enrol_page_hook($instance));
    }
}
