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
 * Manual transaction management page for enrol_sepay plugin.
 *
 * @package    enrol_sepay
 * @copyright  2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/enrol/sepay/lib.php');
require_once($CFG->dirroot . '/enrol/sepay/classes/util.php');

require_login();
admin_externalpage_setup('enrol_sepay_transactions');
require_capability('enrol/sepay:manage', context_system::instance());

$action = optional_param('action', '', PARAM_ALPHA);
$id     = optional_param('id', 0, PARAM_INT); // Transaction ID.

$plugin = enrol_get_plugin('sepay');

// Handle Process Action
if ($action === 'approve' && $id > 0 && confirm_sesskey()) {
    $transaction = $DB->get_record('enrol_sepay_transactions', ['id' => $id], '*', MUST_EXIST);
    if ($transaction->status !== 'pending') {
        redirect(new moodle_url('/enrol/sepay/manage.php'), get_string('error_already_processed', 'enrol_sepay'), null, core\output\notification::NOTIFY_ERROR);
    }

    // Validate Instance
    $instance = $DB->get_record('enrol', ['id' => $transaction->instanceid], '*', IGNORE_MISSING);
    if (!$instance) {
        redirect(new moodle_url('/enrol/sepay/manage.php'), get_string('error_instance_deleted', 'enrol_sepay'), null, core\output\notification::NOTIFY_ERROR);
    }

    // Course & Context
    $course = $DB->get_record('course', ['id' => $instance->courseid], '*', MUST_EXIST);
    $context = context_course::instance($course->id);

    // Check User
    $user = $DB->get_record('user', ['id' => $transaction->userid], '*', MUST_EXIST);

    // Calculate Timestart/Timeend
    if (!empty($instance->enrolperiod)) {
        $timestart = time();
        $timeend = $timestart + (int)$instance->enrolperiod;
    } else {
        $timestart = 0;
        $timeend = 0;
    }

    $roleid = !empty($instance->roleid) ? (int)$instance->roleid : (int)$plugin->get_config('roleid');

    // Enrol user trực tiếp khi admin approve
    // Đảm bảo user được ghi danh dù họ có rời trang countdown hay không
    if (!is_enrolled($context, $user)) {
        $plugin->enrol_user($instance, $user->id, $roleid, $timestart, $timeend);

        // Gửi email thông báo cho student, teacher, admin
        $mailstudents = (int)$plugin->get_config('mailstudents');
        $mailteachers = (int)$plugin->get_config('mailteachers');
        $mailadmins   = (int)$plugin->get_config('mailadmins');

        // Gửi email cho student thông báo đã được ghi danh
        if ($mailstudents) {
            $subject = get_string('paymentthanks', 'enrol_sepay', format_string($course->fullname));
            $message = get_string('paymentthanks_desc', 'enrol_sepay', format_string($course->fullname));
            email_to_user($user, core_user::get_support_user(), $subject, $message);
        }

        // Gửi email cho teacher và admin thông báo có student mới
        if ($mailteachers || $mailadmins) {
            $teachers = get_users_by_capability($context, 'moodle/course:update', 'u.*', 'u.id ASC');
            if ($teachers) {
                $teachers = sort_by_roleassignment_authority($teachers, $context);
            }
            // Gửi cho từng teacher
            if ($mailteachers && !empty($teachers)) {
                foreach ($teachers as $teacher) {
                    $subject = get_string('paymentreceived', 'enrol_sepay', format_string($course->fullname));
                    $message = get_string('paymentreceived_desc', 'enrol_sepay', fullname($user));
                    email_to_user($teacher, core_user::get_support_user(), $subject, $message);
                }
            }
            // Gửi cho từng admin
            if ($mailadmins) {
                $admins = get_admins();
                foreach ($admins as $admin) {
                    $subject = get_string('paymentreceived', 'enrol_sepay', format_string($course->fullname));
                    $message = get_string('paymentreceived_desc', 'enrol_sepay', fullname($user));
                    email_to_user($admin, core_user::get_support_user(), $subject, $message);
                }
            }
        }
    }

    // Update Transaction Status
    $transaction->status = 'processed';
    $transaction->timeprocessed = time();
    $DB->update_record('enrol_sepay_transactions', $transaction);
    \enrol_sepay\util::delete_pending_notifications($transaction);

    redirect(new moodle_url('/enrol/sepay/manage.php'), get_string('transaction_approved', 'enrol_sepay'), null, core\output\notification::NOTIFY_SUCCESS);
}

// Display Page
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manage_transactions', 'enrol_sepay'));

// Pending Transactions
$sql = "SELECT t.*, 
               u.id as uid, u.firstname, u.lastname, u.email,
               u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename,
               c.fullname as coursename, c.shortname as courseshortname, c.id as courseid
        FROM {enrol_sepay_transactions} t
        JOIN {user} u ON t.userid = u.id
        JOIN {course} c ON t.courseid = c.id
        WHERE t.status = :status
        ORDER BY t.timecreated DESC";
$params = ['status' => 'pending'];
$transactions = $DB->get_records_sql($sql, $params);

if (!$transactions) {
    echo $OUTPUT->notification(get_string('no_pending_transactions', 'enrol_sepay'), 'info');
} else {
    $table = new html_table();
    $table->head = [
        get_string('user'),
        get_string('course'),
        get_string('transaction_details', 'enrol_sepay'),
        get_string('timecreated', 'enrol_sepay'),
        get_string('ip_address', 'enrol_sepay'),
        get_string('settings_comparison', 'enrol_sepay'),
        get_string('action')
    ];

    foreach ($transactions as $t) {
        $userlink = html_writer::link(new moodle_url('/user/view.php', ['id' => $t->userid, 'course' => $t->courseid]), fullname($t));
        $courselink = html_writer::link(new moodle_url('/course/view.php', ['id' => $t->courseid]), format_string($t->coursename));
        
        $details = get_string('amount', 'enrol_sepay') . ": " . number_format($t->amount) . " " . $t->currency . "<br>";
        $details .= get_string('trans_content', 'enrol_sepay') . ": " . s($t->transaction_content) . "<br>";
        $details .= get_string('manage_gateway', 'enrol_sepay') . ": " . s($t->gateway);

        // Settings Comparison
        $instance = $DB->get_record('enrol', ['id' => $t->instanceid]);
        $instance_cost = $instance ? (float)$instance->cost : 0;
        $global_cost = (float)$plugin->get_config('cost');
        
        $settings_html = "<small>";
        $settings_html .= "<b>" . get_string('manage_instance_cost', 'enrol_sepay') . ":</b> " . number_format($instance_cost) . "<br>";
        $settings_html .= "<b>" . get_string('manage_global_cost', 'enrol_sepay') . ":</b> " . number_format($global_cost) . "<br>";
        $settings_html .= "</small>";

        $approve_url = new moodle_url('/enrol/sepay/manage.php', ['action' => 'approve', 'id' => $t->id, 'sesskey' => sesskey()]);
        $action_btn = $OUTPUT->single_button($approve_url, get_string('approve', 'enrol_sepay'));

        $table->data[] = [
            $userlink,
            $courselink,
            $details,
            userdate($t->timecreated),
            s($t->ip_address),
            $settings_html,
            $action_btn
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->footer();
