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
 * Check enrolment status and payment errors for a user in a course.
 *
 * @package    enrol_sepay
 * @copyright  2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require("../../config.php");
require_login();

header('Content-Type: application/json');

$courseid = required_param('courseid', PARAM_INT);

$context = context_course::instance($courseid);

// Kiểm tra nếu đã ghi danh.
$enrolled = is_enrolled($context, $USER);

// Trả về thông tin trạng thái dạng JSON.
echo json_encode([
    'error'    => null,
    'enrolled' => $enrolled,
]);
