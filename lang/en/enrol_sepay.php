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
 * Chuỗi ngôn ngữ cho plugin enrol_sepay, tiếng Anh.
 *
 * @package    enrol_sepay
 * @copyright  2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'SePay';
$string['pluginname_desc'] = 'The SePay module allows you to set up paid courses. If the cost for any course is zero, then students are not asked to pay for entry. There is a site-wide cost that you set here as a default for the whole site and then a course setting that you can set for each course individually. The course cost overrides the site cost.';

// General plugin settings.
$string['enrolcost'] = 'Enrol cost';
$string['sepay:config'] = 'Configure SePay enrol instances';

$string['manual_enrol_instance'] = 'Manual Enrollment';
$string['manual_enrol_instance_help'] = 'Override the global manual enrollment setting for this specific course instance. Choose "Use global default" to follow the site-wide setting, "Yes" to always require manual approval for this course, or "No" to always auto-enroll for this course.';
$string['manual_enrol_instance_desc'] = 'Override global setting for manual enrollment.';
$string['manual_enrol_default'] = 'Use global default';
$string['manual_enrol_yes'] = 'Yes';
$string['manual_enrol_no'] = 'No';

$string['sepay:manage'] = 'Manage SePay enrolments';
$string['sepay:unenrol'] = 'Unenrol users via SePay';
$string['sepay:unenrolself'] = 'Unenrol self via SePay';

$string['paywithsepay'] = 'Pay with SePay';
$string['sendpaymentbutton'] = 'Send payment via SePay';

$string['apikey'] = 'API Key';
$string['apikey_desc'] = 'API Key used to authenticate webhook calls from SePay.';

$string['secretkey'] = 'Secret Key';
$string['secretkey_desc'] = 'Secret key used to sign or verify data between Moodle and SePay.';

$string['currency'] = 'Currency';
$string['defaultcost'] = 'Enrol cost';

$string['account'] = 'Account number';
$string['account_desc'] = 'Bank account or phone number used to receive payments.';

$string['bank'] = 'Bank';
$string['bank_desc'] = 'Bank account to receive payments.';

$string['template'] = 'QR template';
$string['template_desc'] = 'Template used to render the VietQR payment code.';

$string['setting_template_compact'] = 'VietQR frame';
$string['setting_template_default'] = 'QR with VietQR logo';
$string['setting_template_qronly'] = 'QR only';

$string['defaultrole'] = 'Default role assignment';
$string['defaultrole_desc'] = 'Select role which should be assigned to users during SePay enrolments.';

$string['enrolenddate'] = 'End date';
$string['enrolenddate_help'] = 'If enabled, users can be enrolled until this date only.';
$string['enrolenddaterror'] = 'Enrolment end date cannot be earlier than start date';

$string['enrolperiod'] = 'Enrolment duration';
$string['enrolperiod_desc'] = 'Default length of time that the enrolment is valid. If set to zero, the enrolment duration will be unlimited by default.';
$string['enrolperiod_help'] = 'Length of time that the enrolment is valid, starting with the moment the user is enrolled. If disabled, the enrolment duration will be unlimited.';

$string['status_desc'] = 'Allow users to use SePay to enrol into a course by default.';

$string['expiredaction'] = 'Enrolment expiry action';
$string['expiredaction_help'] = 'Select action to carry out when user enrolment expires. Please note that some user data and settings are purged from course during course unenrolment.';

$string['cost'] = 'Enrol cost';
$string['assignrole'] = 'Assign role';
$string['enrolstartdate'] = 'Start date';
$string['enrolstartdate_help'] = 'If enabled, users can start being enrolled from this date only.';

$string['pattern'] = 'Payment content pattern';
$string['pattern_desc'] = 'Prefix used in bank transfer content, followed by courseid and userid.';
$string['separator'] = 'Separator CourseID/UserID';
$string['separator_desc'] = 'Separator used between courseid and userid in the generated pattern.';

// No payment required notification.
$string['nocost'] = 'No payment is required for this course.';

// Payment notification strings (used in webhook).
$string['paymentthanks'] = 'Payment received for course: {$a}';
$string['paymentthanks_desc'] = 'Your payment has been received. You are now enrolled in the course: {$a}.';
$string['paymentreceived'] = 'SePay payment received for course: {$a}';
$string['paymentreceived_desc'] = 'A user has been enrolled via SePay: {$a}.';

$string['email_welcome_subject'] = 'You have been enrolled in: {$a->coursename}';
$string['email_welcome_body'] = 'Hello {$a->username},

This is an automated receipt confirming your successful enrollment in the course: "{$a->coursename}".
You have full access to the course materials.

Course link: {$a->courseurl}

Thank you and we wish you a great learning experience.

Sincerely,
System Administrator';

$string['email_teacher_subject'] = 'New Enrollment: {$a->coursename}';
$string['email_teacher_body'] = 'Hello,

A new user ({$a->username}) has successfully enrolled in your course: "{$a->coursename}" via SePay.

Course link: {$a->courseurl}
User profile: {$a->profileurl}';

$string['email_admin_subject'] = 'New Enrollment: {$a->coursename}';
$string['email_admin_body'] = 'Hello,

A new user ({$a->username}) has successfully enrolled in the course: "{$a->coursename}" via SePay.

Course link: {$a->courseurl}
User profile: {$a->profileurl}';

$string['costerror'] = 'Invalid value';

// Manual approval strings.
$string['manual_enrol'] = 'Manual Enrollment';
$string['manual_enrol_desc'] = 'Default for new enrollment instances. If set to "Yes", transactions will be recorded as pending and must be manually approved by an admin before the user is enrolled.';
$string['manage_transactions'] = 'Manage SePay Transactions';
$string['transaction_recorded'] = 'Transaction recorded and waiting for approval.';
$string['transaction_details'] = 'Transaction Details';
$string['timecreated'] = 'Time Created';
$string['ip_address'] = 'IP Address';
$string['settings_comparison'] = 'Settings Comparison';
$string['approve'] = 'Approve';
$string['reject'] = 'Reject';
$string['confirm_reject'] = 'Are you sure you want to reject this transaction?';
$string['transaction_approved'] = 'Transaction approved successfully.';
$string['transaction_rejected'] = 'Transaction rejected successfully.';
$string['status_rejected'] = 'Rejected';
$string['payment_pending_title'] = 'Payment Received!';
$string['payment_pending_message'] = 'You will be automatically enrolled in the course after administrator approval.';
$string['payment_success_title'] = 'Payment Successful!';
$string['payment_success_message'] = 'You have been enrolled in this course.';
$string['payment_auto_approved_title'] = 'Payment Confirmation Successful!';
$string['payment_auto_approved_message'] = 'You have been enrolled in the course.';
$string['payment_approved_title'] = 'Approval Confirmation Successful!';
$string['payment_approved_message'] = 'You have been enrolled in the course.';
$string['redirecting_in'] = 'Redirecting in';
$string['seconds'] = 'seconds';
$string['payment_rejected_title'] = 'Approval Confirmation Failed!';
$string['payment_rejected_message'] = 'Please choose one of the following options:';
$string['retry_payment'] = 'Retry Payment';
$string['contact_admin'] = 'Contact Admin';
$string['back_to_course'] = 'Back to Course';
$string['bulk_delete'] = 'Bulk Delete';
$string['confirm_delete'] = 'Are you sure you want to delete this transaction? Note: if it is a processed transaction, the user enrolment will NOT be automatically removed.';
$string['confirm_bulk_delete'] = 'Are you sure you want to delete the selected transactions? Note: deleting processed transactions will NOT automatically remove enrolments.';
$string['transaction_deleted'] = 'Transaction deleted successfully.';
$string['transactions_deleted'] = '{$a} transactions deleted successfully.';
$string['transaction_deleted_processed'] = 'Transaction deleted. Note: this was a processed transaction — the user enrolment was NOT automatically removed.';
$string['no_transactions_selected'] = 'No transactions selected.';
$string['select_transactions_to_delete'] = 'Select transactions to delete';
$string['error_already_processed'] = 'Error: This transaction has already been processed.';
$string['no_pending_transactions'] = 'No pending transactions found.';
$string['no_transactions_found'] = 'No transactions found.';
$string['no_processed_transactions_found'] = 'No processed transactions found.';
$string['no_rejected_transactions_found'] = 'No rejected transactions found.';
$string['no_unenrolled_transactions_found'] = 'No unenrolled transactions found.';
$string['user'] = 'User';
$string['course'] = 'Course';
$string['transaction_status'] = 'Status';
$string['status_pending'] = 'Pending';
$string['status_processed'] = 'Processed';
$string['status_unenrolled'] = 'Unenrolled';
$string['amount'] = 'Amount';
$string['trans_content'] = 'Content';
$string['process_date'] = 'Processed Date';

// Data retention settings.
$string['data_retention_heading'] = 'Data Retention Management';
$string['data_retention_heading_desc'] = 'Configure automatic cleanup and archival of old transaction data to optimize database performance.';
$string['auto_cleanup_enabled'] = 'Enable auto cleanup';
$string['auto_cleanup_enabled_desc'] = 'Automatically archive or delete old transactions on a scheduled basis.';
$string['retention_days'] = 'Retention period (days)';
$string['retention_days_desc'] = 'Number of days to keep transactions in the main table. Older transactions will be processed according to the archive strategy. Default: 365 days.';
$string['archive_strategy'] = 'Archive strategy';
$string['archive_strategy_desc'] = 'Choose how to handle old transactions: move to archive table or delete permanently.';
$string['archive_strategy_archive'] = 'Move to archive table';
$string['archive_strategy_delete'] = 'Delete permanently';
$string['archive_retention_days'] = 'Archive retention period (days)';
$string['archive_retention_days_desc'] = 'Number of days to keep transactions in the archive table. Older transactions will be permanently deleted. Only applies when strategy is "Move to archive table". Default: 365 days. Set to 0 for unlimited retention.';

// Scheduled task names.
$string['task_cleanup_transactions'] = 'Clean up old SePay transactions';
$string['task_process_enrolments'] = 'Process pending SePay enrolments';
$string['task_process_rejections'] = 'Send SePay rejection notifications';
$string['task_update_banks'] = 'Sync SePay banks list';
$string['task_process_expirations'] = 'Process SePay enrolment expirations';

// IP duplicate detection.
$string['ip_duplicate_warning'] = 'Warning: {$a} different users are sharing this IP address!';
$string['ip_duplicate_users'] = '{$a} users';

$string['mailstudents'] = 'Notify students';
$string['mailteachers'] = 'Notify teachers';
$string['mailadmins'] = 'Notify admin';

// Notification message strings.
$string['messageprovider:pending_transaction'] = 'Pending transaction notification';
$string['messageprovider:sepay_enrolment'] = 'Course enrollment confirmation';
$string['notification_pending_title'] = 'New pending transaction';
$string['notification_pending_body'] = 'User {$a->username} has made a payment of {$a->amount} {$a->currency} for course "{$a->coursename}". Please review and approve the transaction.';
$string['notification_pending_small'] = 'New payment: {$a->amount} {$a->currency}';
$string['notification_pending_url'] = 'View transaction';

// Error messages.
$string['transaction_not_found'] = 'Transaction not found';
$string['payment_amount_insufficient'] = 'Payment amount insufficient. Please check again.';
$string['errdisabled'] = 'SePay enrolment plugin is disabled';

// Notification settings page.
$string['notification_settings'] = 'SePay Notification Settings';
$string['notification_statistics'] = 'Notification Statistics';
$string['total_notifications'] = 'Total notifications';
$string['read_notifications'] = 'Read';
$string['unread_notifications'] = 'Unread';
$string['delete_read_notifications'] = 'Delete read notifications';
$string['delete_read_notifications_desc'] = 'Delete read notifications by specific time period.';
$string['delete_read_1day'] = 'Delete read > 1 day';
$string['delete_read_1week'] = 'Delete read > 1 week';
$string['delete_read_1month'] = 'Delete read > 1 month';
$string['confirm_delete_read_1day'] = 'Are you sure you want to delete all notifications read more than 1 day ago?';
$string['confirm_delete_read_1week'] = 'Are you sure you want to delete all notifications read more than 1 week ago?';
$string['confirm_delete_read_1month'] = 'Are you sure you want to delete all notifications read more than 1 month ago?';
$string['delete_all_read_notifications'] = 'Delete all read notifications';
$string['delete_all_read_notifications_desc'] = 'Delete all read notifications regardless of time.';
$string['delete_all_read'] = 'Delete all read';
$string['confirm_delete_all_read'] = 'Are you sure you want to delete ALL read notifications?';
$string['delete_all_notifications'] = 'Delete all notifications';
$string['delete_all_notifications_desc'] = 'WARNING: Delete all SePay notifications, including unread ones. This action cannot be undone!';
$string['delete_all'] = 'Delete all';
$string['confirm_delete_all'] = 'WARNING: Are you sure you want to delete ALL notifications (including unread)? This action cannot be undone!';
$string['notifications_deleted_success'] = 'Notifications deleted successfully.';
$string['all_notifications_deleted_success'] = 'All notifications deleted successfully.';
$string['recent_notifications'] = 'Recent notifications';
$string['recipient'] = 'Recipient';
$string['subject'] = 'Subject';
$string['read'] = 'Read';
$string['unread'] = 'Unread';

// Delete notification time options.
$string['delete_read_1day_option'] = '1 day';
$string['delete_read_1week_option'] = '1 week';
$string['delete_read_1month_option'] = '1 month';
$string['delete_read_3months_option'] = '3 months';
$string['delete_read_6months_option'] = '6 months';
$string['delete_read_never_option'] = 'Never';
$string['delete_button'] = 'Delete';
$string['confirm_delete_read_selected'] = 'Are you sure you want to delete read notifications for the selected time period?';

// Form labels and descriptions.
$string['delete_read_time_label'] = 'Default: 1 week';
$string['delete_all_time_label'] = 'Default: 1 month';
$string['delete_all_notifications_label'] = 'Delete all notifications';
$string['save_changes'] = 'Save changes';
$string['status'] = 'Status';
$string['actions'] = 'Actions';
$string['delete'] = 'Delete';
$string['confirm_delete_notification'] = 'Are you sure you want to delete this notification?';
$string['notification_deleted'] = 'Notification deleted successfully.';
$string['sender'] = 'Sender';

// Transaction filter and search UI.
$string['transactions_found'] = 'Found {$a} transactions';
$string['search_user'] = 'Search by user';
$string['search_course'] = 'Search by course';
$string['filter_date_from'] = 'From date';
$string['filter_date_to'] = 'To date';
$string['filter_amount_min'] = 'Min amount';
$string['filter_amount_max'] = 'Max amount';
$string['add_condition'] = 'Add condition';
$string['clear_filter'] = 'Clear filter';
$string['apply_filter'] = 'Apply filter';
$string['total_transactions'] = 'Total transactions';
$string['filter_by_letter'] = 'Filter by last name';
$string['filter_firstname'] = 'First name';
$string['filter_lastname'] = 'Last name';
$string['reset_table'] = 'Reset table selection';
$string['stat_total'] = 'Total';
$string['stat_pending'] = 'Pending';
$string['stat_processed'] = 'Processed';
$string['stat_rejected'] = 'Rejected';
$string['stat_unenrolled'] = 'Unenrolled';
$string['bulk_approve'] = 'Approve selected';
$string['bulk_reject'] = 'Reject selected';
$string['confirm_bulk_approve'] = 'Are you sure you want to approve the selected transactions?';
$string['confirm_bulk_reject'] = 'Are you sure you want to reject the selected transactions?';
$string['bulk_approved'] = '{$a} transactions approved successfully.';
$string['bulk_approved_partial'] = '{$a->ok} transactions approved, {$a->failed} failed.';
$string['bulk_rejected'] = '{$a} transactions rejected successfully.';
$string['bulk_rejected_partial'] = '{$a->ok} transactions rejected, {$a->failed} failed.';
$string['bulk_unenrol'] = 'Unenrol selected';
$string['confirm_bulk_unenrol'] = 'Are you sure you want to unenrol the users of the selected transactions?';
$string['bulk_unenrolled'] = '{$a} users unenrolled successfully.';

// Additional strings.
$string['error_instance_deleted'] = 'Enrolment instance has been deleted.';
$string['error_enrol_failed'] = 'Enrolment failed. Please contact the administrator.';
$string['pending_retention_days'] = 'Pending transaction retention (days)';
$string['pending_retention_days_desc'] = 'Number of days to keep pending transactions before marking them as rejected. Default: 30 days.';

// Rejection notification strings.
$string['email_rejection_subject'] = 'You have been rejected from: {$a->coursename}';
$string['email_rejection_body'] = 'Hello {$a->username}, you have been rejected from: {$a->coursename}. Please contact the administrator for more information.';
$string['email_rejection_smallmessage'] = 'You have been rejected from: {$a->coursename}.';
$string['messageprovider:rejection_notification'] = 'Enrolment rejection notification';

// Unenrolment notification strings.
$string['email_unenrolment_subject'] = 'You have been unenrolled from: {$a->coursename}';
$string['email_unenrolment_body'] = 'Hello {$a->username}, you have been unenrolled from: {$a->coursename}. Please contact the administrator if you believe this is a mistake.';
$string['email_unenrolment_smallmessage'] = 'You have been unenrolled from: {$a->coursename}.';
$string['messageprovider:unenrolment_notification'] = 'Unenrolment notification';

// Self-unenrolment confirmation.
$string['unenrolselfconfirm'] = 'Do you really want to unenrol yourself from course "{$a}"?';

// Task names.
$string['task_process_expirations'] = 'Process expired SePay enrolments';

// Mass email tool strings.
$string['mass_email_title'] = 'Resend Enrollment Emails';
$string['mass_email_heading'] = 'Resend Enrollment Emails (SePay)';
$string['mass_email_desc'] = 'This tool allows you to scan previous students who have already enrolled but have not yet received an automated notification email.';
$string['mass_email_allsent'] = 'All students have already received their enrollment emails.';
$string['mass_email_found'] = 'Found <b>{$a}</b> students who have not received their email.';
$string['mass_email_limit_label'] = 'Number of emails per run:';
$string['mass_email_submit'] = 'Schedule background email sending';

// Manage page strings.
$string['manage_gateway'] = 'Gateway';
$string['manage_instance_cost'] = 'Instance Cost';
$string['manage_global_cost'] = 'Global Cost';

// Preview email page.
$string['preview_email_title'] = 'Preview Email Template';
$string['preview_email_heading'] = 'SePay Email Preview';

// Bulk enrolment operations (trang participants).
$string['editselectedusers'] = 'Edit selected user enrolments';
$string['deleteselectedusers'] = 'Delete selected user enrolments';
$string['confirmbulkdeleteenrolment'] = 'Are you sure you want to delete these user enrolments?';
$string['unenrolusers'] = 'Unenrol users';
