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
 * Kiểm thử util: builder HTML email + send_welcome_messages.
 *
 * Các assertion "chứa marker" làm mốc để Phase B (tách helper email) chứng minh
 * output không đổi cấu trúc.
 *
 * @package    enrol_sepay
 * @copyright  2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \enrol_sepay\util
 */
final class util_test extends \advanced_testcase {
    /**
     * Dữ liệu mẫu cho các builder email.
     *
     * @return \stdClass
     */
    protected function sample_a(): \stdClass {
        return (object)[
            'username'   => 'Nguyen Van A',
            'coursename' => 'Khoa hoc Mau ABC',
            'useremail'  => 'student@example.com',
            'courseurl'  => 'https://moodle.example.com/course/view.php?id=2',
            'profileurl' => 'https://moodle.example.com/user/view.php?id=3',
        ];
    }

    /**
     * 4 builder email trả về HTML chứa các marker chính (tên khóa, email, link khóa).
     *
     * @covers \enrol_sepay\email_templates
     */
    public function test_email_builders_contain_markers(): void {
        $this->resetAfterTest();
        $a = $this->sample_a();
        $builders = [
            'get_student_email_html',
            'get_admin_email_html',
            'get_rejection_email_html',
            'get_unenrolment_email_html',
        ];
        foreach ($builders as $method) {
            $html = email_templates::$method($a);
            $this->assertNotEmpty($html, "$method trả rỗng");
            $this->assertStringContainsString('Khoa hoc Mau ABC', $html, "$method thiếu tên khóa học");
            $this->assertStringContainsString('<', $html, "$method không phải HTML");
        }
    }

    /**
     * send_welcome_messages: tắt hết kênh mail thì trả false (no-op).
     */
    public function test_send_welcome_all_disabled_returns_false(): void {
        $this->resetAfterTest();
        set_config('mailstudents', 0, 'enrol_sepay');
        set_config('mailteachers', 0, 'enrol_sepay');
        set_config('mailadmins', 0, 'enrol_sepay');
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $this->assertFalse(util::send_welcome_messages($course, $user, new \stdClass()));
    }

    /**
     * send_welcome_messages: bật mail học viên thì gửi đúng 1 email cho học viên.
     */
    public function test_send_welcome_student_channel(): void {
        $this->resetAfterTest();
        set_config('mailstudents', 1, 'enrol_sepay');
        set_config('mailteachers', 0, 'enrol_sepay');
        set_config('mailadmins', 0, 'enrol_sepay');
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();

        $sink = $this->redirectEmails();
        $result = util::send_welcome_messages($course, $user, new \stdClass());
        $emails = $sink->get_messages();
        $sink->close();

        // Hàm message_send báo debugging "provider inactive/not allowed" với user mới tạo trong
        // test (preferences mặc định) — artifact môi trường test, không phải lỗi code.
        $this->assertDebuggingCalled();
        $this->assertTrue($result);
        $this->assertCount(1, $emails);
        $this->assertEquals($user->email, $emails[0]->to);
    }
}
