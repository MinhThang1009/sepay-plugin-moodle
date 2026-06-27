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
 * Gửi email hàng loạt cho học viên SePay chưa nhận email bằng cách queue ad-hoc task.
 *
 * @package    enrol_sepay
 * @copyright  2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_login();

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$PAGE->set_url('/enrol/sepay/mass_email.php');
$PAGE->set_context($context);
$PAGE->set_title(get_string('mass_email_title', 'enrol_sepay'));
$PAGE->set_heading(get_string('mass_email_heading', 'enrol_sepay'));

$execute = optional_param('execute', 0, PARAM_INT);
$limit   = max(1, min(500, optional_param('limit', 50, PARAM_INT)));

echo $OUTPUT->header();
echo html_writer::tag('h2', get_string('mass_email_title', 'enrol_sepay'));
echo html_writer::tag('p', get_string('mass_email_desc', 'enrol_sepay'));

// Đếm số giao dịch cần gửi email.
$pending_count = $DB->count_records_select(
    'enrol_sepay_transactions',
    "status = 'processed' AND email_sent = 0"
);

if ($pending_count === 0) {
    echo $OUTPUT->notification(get_string('mass_email_allsent', 'enrol_sepay'), 'success');
    echo $OUTPUT->footer();
    exit;
}

echo $OUTPUT->notification(get_string('mass_email_found', 'enrol_sepay', $pending_count), 'info');

if ($execute && confirm_sesskey()) {
    // Queue ad-hoc task thay vì gửi trực tiếp (không block HTTP request, không cần sleep).
    $task = new \enrol_sepay\task\send_mass_email();
    $task->set_custom_data(['limit' => $limit]);
    \core\task\manager::queue_adhoc_task($task, true);

    redirect(
        new moodle_url('/enrol/sepay/mass_email.php'),
        'Đã đặt lịch gửi ' . $limit . ' email trong nền. Kiểm tra kết quả trong Admin > Server > Scheduled tasks logs.',
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Form xác nhận với sesskey (không còn GET-triggered action).
$form_url = new moodle_url('/enrol/sepay/mass_email.php');
echo '<form method="post" action="' . $form_url . '" class="mt-4">';
echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
echo '<input type="hidden" name="execute" value="1">';
echo '<div class="form-group">';
echo '<label for="limit">' . get_string('mass_email_limit_label', 'enrol_sepay') . '</label>';
echo '<input type="number" id="limit" name="limit" value="' . $limit . '" min="1" max="500" class="form-control sepay-limit-input">';
echo '</div>';
echo '<button type="submit" class="btn btn-primary btn-lg">' . get_string('mass_email_submit', 'enrol_sepay') . '</button>';
echo '</form>';

echo $OUTPUT->footer();
