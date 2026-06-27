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
 * Hoàn thành ghi danh sau khi đếm ngược.
 *
 * @package    enrol_sepay
 * @copyright  2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once(__DIR__ . '/lib.php');

$courseid = required_param('id', PARAM_INT);

require_login();
require_sesskey();

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$plugin = enrol_get_plugin('sepay');

// Khởi tạo PAGE để tránh các hàm gửi email làm văng cảnh báo làm kẹt lệnh Redirect
$PAGE->set_url(new moodle_url('/enrol/sepay/complete_enrol.php', ['id' => $course->id]));
$PAGE->set_context(\context_course::instance($course->id));

// Lấy processed transaction của user — lấy mới nhất nếu có nhiều.
$transactions = $DB->get_records('enrol_sepay_transactions', [
    'userid'   => $USER->id,
    'courseid' => $course->id,
    'status'   => 'processed',
], 'timecreated DESC', '*', 0, 1);
$transaction = $transactions ? reset($transactions) : null;

if (!$transaction) {
    redirect(new moodle_url('/course/view.php', ['id' => $course->id]));
}

// Lấy instance — dùng IGNORE_MISSING để xử lý gracefully nếu admin đã xóa instance.
$instance = $DB->get_record('enrol', ['id' => $transaction->instanceid], '*', IGNORE_MISSING);

if (!$instance) {
    redirect(new moodle_url('/course/view.php', ['id' => $course->id]),
        get_string('error_instance_deleted', 'enrol_sepay'),
        null, \core\output\notification::NOTIFY_ERROR);
}

// Bỏ qua nếu instance đang bị tắt.
if ((int)$instance->status !== ENROL_INSTANCE_ENABLED) {
    redirect(new moodle_url('/course/view.php', ['id' => $course->id]));
}

// Tính thời gian enrol
if (!empty($instance->enrolperiod)) {
    $timestart = time();
    $timeend = $timestart + (int)$instance->enrolperiod;
} else {
    $timestart = 0;
    $timeend = 0;
}

$roleid = !empty($instance->roleid) ? (int)$instance->roleid : (int)enrol_get_plugin('sepay')->get_config('roleid');

// Không ghi danh nếu role chưa cấu hình hợp lệ (tránh enrol_user với roleid=0 → không gán role).
if ($roleid <= 0) {
    \enrol_sepay\util::message_sepay_error_to_admin(
        'complete_enrol.php: roleid chưa cấu hình (<=0) — bỏ qua ghi danh để tránh enrol không có role.',
        ['userid' => $USER->id, 'courseid' => $course->id, 'instanceid' => $instance->id]
    );
    redirect(new moodle_url('/course/view.php', ['id' => $course->id]),
        get_string('error_enrol_failed', 'enrol_sepay'),
        null, \core\output\notification::NOTIFY_ERROR);
}

// Kiểm tra xem đã ghi danh chưa (chống gửi email đúp khi reload trang)
if (!$DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $USER->id])) {
    try {
        $plugin->enrol_user($instance, $USER->id, $roleid, $timestart, $timeend);
    } catch (\Exception $e) {
        debugging('enrol_sepay complete_enrol: enrol_user failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        \enrol_sepay\util::message_sepay_error_to_admin(
            'complete_enrol.php: enrol_user() threw exception — ' . $e->getMessage(),
            ['userid' => $USER->id, 'courseid' => $course->id, 'instanceid' => $instance->id]
        );
        redirect(new moodle_url('/course/view.php', ['id' => $course->id]),
            get_string('error_enrol_failed', 'enrol_sepay'),
            null, \core\output\notification::NOTIFY_ERROR);
    }
}

// Gửi welcome email nếu chưa gửi — webhook có thể đã gửi rồi (email_sent = 1), tránh gửi đúp.
if (!$transaction->email_sent) {
    try {
        require_once(__DIR__ . '/classes/util.php');
        // Chỉ đánh dấu email_sent khi thực sự gửi (send_welcome_messages trả true).
        if (\enrol_sepay\util::send_welcome_messages($course, $USER, $instance)) {
            $DB->set_field('enrol_sepay_transactions', 'email_sent', 1, ['id' => $transaction->id]);
        }
    } catch (\Exception $e) {
        debugging('enrol_sepay complete_enrol: send_welcome_messages failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }
}

// Chuyển hướng vào khóa học
redirect(new moodle_url('/course/view.php', ['id' => $course->id]));
