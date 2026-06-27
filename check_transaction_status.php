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
 * Check transaction status for current user and course.
 *
 * @package    enrol_sepay
 * @copyright  2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require("../../config.php");

header('Content-Type: application/json');

$courseid = required_param('courseid', PARAM_INT);

require_login();

$userid = $USER->id;

// Kiểm tra user có giao dịch đang chờ xử lý không
$pending = $DB->get_record('enrol_sepay_transactions', [
    'userid' => $userid,
    'courseid' => $courseid,
    'status' => 'pending',
], '*', IGNORE_MULTIPLE);

// Kiểm tra user có giao dịch đã xử lý không
$processed = $DB->get_record('enrol_sepay_transactions', [
    'userid' => $userid,
    'courseid' => $courseid,
    'status' => 'processed',
], '*', IGNORE_MULTIPLE);

// Kiểm tra user đã ghi danh chưa
$context = context_course::instance($courseid);
$enrolled = is_enrolled($context, $USER);

// Logic mới: Trả về processed=true khi có transaction processed, bất kể đã enrolled hay chưa
// Vì flow mới là: processed → countdown → enroll (qua complete_enrol.php)
echo json_encode([
    'enrolled' => $enrolled,
    'pending' => (!$enrolled && $pending) ? true : false, // Chỉ hiển thị pending khi chưa enrolled
    'processed' => $processed ? true : false, // Trả về true nếu có transaction processed
    'transaction' => $pending ? [
        'amount' => $pending->amount,
        'currency' => $pending->currency,
        'timecreated' => $pending->timecreated,
        'status' => 'pending',
    ] : ($processed ? [
        'amount' => $processed->amount,
        'currency' => $processed->currency,
        'timecreated' => $processed->timecreated,
        'timeprocessed' => $processed->timeprocessed,
        'status' => 'processed',
    ] : null),
]);
