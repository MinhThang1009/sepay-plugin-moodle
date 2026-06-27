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
 * Cho phép học viên thanh toán lại sau khi giao dịch bị từ chối.
 *
 * Xóa các giao dịch 'rejected' của chính user trong instance này để trang ghi danh
 * hiển thị lại form QR (enrol_page_hook chỉ hiện QR khi không còn giao dịch
 * pending/processed/rejected). Lần chuyển khoản kế tiếp sẽ tạo giao dịch mới.
 *
 * @package    enrol_sepay
 * @copyright  2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_once(__DIR__ . '/lib.php');

$courseid = required_param('id', PARAM_INT);
$instanceid = required_param('instance', PARAM_INT);

require_login();
require_sesskey();

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$instance = $DB->get_record(
    'enrol',
    ['id' => $instanceid, 'courseid' => $courseid, 'enrol' => 'sepay'],
    '*',
    MUST_EXIST
);

$courseurl = new moodle_url('/course/view.php', ['id' => $course->id]);

// Nếu đã ghi danh rồi thì không cần thanh toán lại.
$context = context_course::instance($course->id);
if (is_enrolled($context, $USER)) {
    redirect($courseurl);
}

// Xóa các giao dịch bị từ chối của CHÍNH user hiện tại trong instance này (scoped theo
// $USER->id nên không ảnh hưởng người khác) → trang ghi danh sẽ hiển thị lại form QR.
$DB->delete_records('enrol_sepay_transactions', [
    'userid'     => $USER->id,
    'courseid'   => $course->id,
    'instanceid' => $instance->id,
    'status'     => 'rejected',
]);

redirect($courseurl);
