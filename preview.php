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
 * Preview email templates cho plugin enrol_sepay.
 *
 * @package    enrol_sepay
 * @copyright  2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../config.php');
require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_url('/enrol/sepay/preview.php');
$PAGE->set_context($context);
$PAGE->set_title(get_string('preview_email_title', 'enrol_sepay'));
$PAGE->set_heading(get_string('preview_email_heading', 'enrol_sepay'));

$type = optional_param('type', 'student', PARAM_ALPHA);

// Dữ liệu mẫu cho preview.
$a = new stdClass();
$a->username   = fullname($USER);
$a->useremail  = $USER->email;
$a->coursename = 'Ôn thi THPTQG - Toán Nâng Cao 2026';
$a->courseurl  = (new moodle_url('/course/view.php', ['id' => 1]))->out(false);
$a->profileurl = (new moodle_url('/user/view.php', ['id' => $USER->id]))->out(false);

require_once($CFG->dirroot . '/enrol/sepay/classes/email_templates.php');

if ($type === 'admin') {
    $html = \enrol_sepay\email_templates::get_admin_email_html($a);
} else {
    $html = \enrol_sepay\email_templates::get_student_email_html($a);
}

echo $OUTPUT->header();
echo html_writer::tag(
    'div',
    html_writer::link(
        new moodle_url('/enrol/sepay/preview.php', ['type' => 'student']),
        'Student Email',
        ['class' => 'btn btn-sm btn-outline-primary mr-2']
    ) .
    html_writer::link(
        new moodle_url('/enrol/sepay/preview.php', ['type' => 'admin']),
        'Admin Email',
        ['class' => 'btn btn-sm btn-outline-secondary']
    ),
    ['class' => 'mb-3']
);
echo $html;
echo $OUTPUT->footer();
