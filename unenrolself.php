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
 * Trang cho phép người dùng tự hủy ghi danh khỏi các khóa học SePay.
 *
 * Mô phỏng pattern của enrol_paypal/unenrolself.php 
 * nhưng áp dụng cho plugin enrol_sepay.
 *
 * @package    enrol_sepay
 * @copyright  2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');

$enrolid = required_param('enrolid', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

// Lấy instance và course trước, sau đó require_login($course) để đưa đúng context.
$instance = $DB->get_record('enrol', ['id' => $enrolid, 'enrol' => 'sepay'], '*', MUST_EXIST);
$course   = $DB->get_record('course', ['id' => $instance->courseid], '*', MUST_EXIST);
$context  = context_course::instance($course->id, MUST_EXIST);

// require_login($course) theo đúng chuẩn Moodle — phải gọi sau khi có $course.
require_login($course);

if (!is_enrolled($context)) {
    // Nếu user không được ghi danh vào khóa học này thì quay về trang chủ.
    redirect(new moodle_url('/'));
}

/** @var enrol_sepay_plugin $plugin */
$plugin = enrol_get_plugin('sepay');

// Bảo mật logic hiển thị link unenrolself giống enrol_paypal.
if (!$plugin->get_unenrolself_link($instance)) {
    redirect(new moodle_url('/course/view.php', ['id' => $course->id]));
}

$PAGE->set_url('/enrol/sepay/unenrolself.php', ['enrolid' => $instance->id]);
$PAGE->set_title($plugin->get_instance_name($instance));
$PAGE->set_heading($course->fullname);

if ($confirm && confirm_sesskey()) {
    // Gỡ ghi danh chính user hiện tại khỏi instance SePay này.
    $plugin->unenrol_user($instance, $USER->id);

    redirect(new moodle_url('/index.php'));
}

echo $OUTPUT->header();

// Dùng cùng chuỗi confirm SePay
$yesurl = new moodle_url($PAGE->url, ['confirm' => 1, 'sesskey' => sesskey()]);
$nourl  = new moodle_url('/course/view.php', ['id' => $course->id]);
$message = get_string('unenrolselfconfirm', 'enrol_sepay', format_string($course->fullname, true));

echo $OUTPUT->confirm($message, $yesurl, $nourl);
echo $OUTPUT->footer();
