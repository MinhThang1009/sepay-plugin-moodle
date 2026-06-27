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
 * Trang cài đặt thông báo SePay.
 *
 * @package    enrol_sepay
 * @copyright  2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Kiểm tra đăng nhập và quyền truy cập.
require_login();
require_capability('enrol/sepay:manage', context_system::instance());

admin_externalpage_setup('enrol_sepay_notification_settings');

$PAGE->set_url('/enrol/sepay/notification_settings.php');
$PAGE->set_title(get_string('notification_settings', 'enrol_sepay'));
$PAGE->set_heading($SITE->fullname);

// Lấy tham số tùy chọn.
$action = optional_param('action', '', PARAM_ALPHANUMEXT);
$notificationid = optional_param('id', 0, PARAM_INT);
$delete_read_config = optional_param('delete_read_config', '', PARAM_ALPHANUMEXT);
$delete_all_config = optional_param('delete_all_config', '', PARAM_ALPHANUMEXT);

// Lưu cấu hình nếu có gửi lên
if ($delete_read_config && confirm_sesskey()) {
    set_config('delete_read_notifications_delay', $delete_read_config, 'enrol_sepay');
}
if ($delete_all_config && confirm_sesskey()) {
    set_config('delete_all_notifications_delay', $delete_all_config, 'enrol_sepay');
}

// Đọc cấu hình đã lưu
$saved_delete_read = get_config('enrol_sepay', 'delete_read_notifications_delay') ?: 'delete_read_1day';
$saved_delete_all = get_config('enrol_sepay', 'delete_all_notifications_delay') ?: 'delete_all_1day';

// Xử lý các hành động.
if ($action && confirm_sesskey()) {
    switch ($action) {
        case 'delete_notification':
            // Xóa một thông báo
            if ($notificationid > 0) {
                $DB->delete_records('notifications', ['id' => $notificationid]);
                redirect($PAGE->url, get_string('notification_deleted', 'enrol_sepay'));
            }
            break;
        case 'delete_read_1day':
            // Xóa thông báo đã đọc hơn 1 ngày.
            $timeread = time() - (1 * 24 * 60 * 60);
            $sql = "DELETE FROM {notifications} 
                    WHERE component = :component 
                    AND eventtype = :eventtype 
                    AND timeread IS NOT NULL 
                    AND timeread < :timeread";
            $params = [
                'component' => 'enrol_sepay',
                'eventtype' => 'pending_transaction',
                'timeread' => $timeread,
            ];
            $DB->execute($sql, $params);
            redirect($PAGE->url, get_string('notifications_deleted_success', 'enrol_sepay'));
            break;

        case 'delete_read_1week':
            // Xóa thông báo đã đọc hơn 1 tuần.
            $timeread = time() - (7 * 24 * 60 * 60);
            $sql = "DELETE FROM {notifications} 
                    WHERE component = :component 
                    AND eventtype = :eventtype 
                    AND timeread IS NOT NULL 
                    AND timeread < :timeread";
            $params = [
                'component' => 'enrol_sepay',
                'eventtype' => 'pending_transaction',
                'timeread' => $timeread,
            ];
            $DB->execute($sql, $params);
            redirect($PAGE->url, get_string('notifications_deleted_success', 'enrol_sepay'));
            break;

        case 'delete_read_1month':
            // Xóa thông báo đã đọc hơn 1 tháng.
            $timeread = time() - (30 * 24 * 60 * 60);
            $sql = "DELETE FROM {notifications} 
                    WHERE component = :component 
                    AND eventtype = :eventtype 
                    AND timeread IS NOT NULL 
                    AND timeread < :timeread";
            $params = [
                'component' => 'enrol_sepay',
                'eventtype' => 'pending_transaction',
                'timeread' => $timeread,
            ];
            $DB->execute($sql, $params);
            redirect($PAGE->url, get_string('notifications_deleted_success', 'enrol_sepay'));
            break;

        case 'delete_read_3months':
            // Xóa thông báo đã đọc hơn 3 tháng.
            $timeread = time() - (90 * 24 * 60 * 60);
            $sql = "DELETE FROM {notifications} 
                    WHERE component = :component 
                    AND eventtype = :eventtype 
                    AND timeread IS NOT NULL 
                    AND timeread < :timeread";
            $params = [
                'component' => 'enrol_sepay',
                'eventtype' => 'pending_transaction',
                'timeread' => $timeread,
            ];
            $DB->execute($sql, $params);
            redirect($PAGE->url, get_string('notifications_deleted_success', 'enrol_sepay'));
            break;

        case 'delete_read_6months':
            // Xóa thông báo đã đọc hơn 6 tháng.
            $timeread = time() - (180 * 24 * 60 * 60);
            $sql = "DELETE FROM {notifications} 
                    WHERE component = :component 
                    AND eventtype = :eventtype 
                    AND timeread IS NOT NULL 
                    AND timeread < :timeread";
            $params = [
                'component' => 'enrol_sepay',
                'eventtype' => 'pending_transaction',
                'timeread' => $timeread,
            ];
            $DB->execute($sql, $params);
            redirect($PAGE->url, get_string('notifications_deleted_success', 'enrol_sepay'));
            break;

        case 'delete_all_read':
            // Xóa tất cả thông báo đã đọc.
            $sql = "DELETE FROM {notifications} 
                    WHERE component = :component 
                    AND eventtype = :eventtype 
                    AND timeread IS NOT NULL";
            $params = [
                'component' => 'enrol_sepay',
                'eventtype' => 'pending_transaction',
            ];
            $DB->execute($sql, $params);
            redirect($PAGE->url, get_string('notifications_deleted_success', 'enrol_sepay'));
            break;

        case 'delete_all_1day':
            // Xóa tất cả thông báo (đã đọc và chưa đọc) cũ hơn 1 ngày.
            $timecreated = time() - (1 * 24 * 60 * 60);
            $sql = "DELETE FROM {notifications} 
                    WHERE component = :component 
                    AND eventtype = :eventtype 
                    AND timecreated < :timecreated";
            $params = [
                'component' => 'enrol_sepay',
                'eventtype' => 'pending_transaction',
                'timecreated' => $timecreated,
            ];
            $DB->execute($sql, $params);
            redirect($PAGE->url, get_string('notifications_deleted_success', 'enrol_sepay'));
            break;

        case 'delete_all_1week':
            // Xóa tất cả thông báo (đã đọc và chưa đọc) cũ hơn 1 tuần.
            $timecreated = time() - (7 * 24 * 60 * 60);
            $sql = "DELETE FROM {notifications} 
                    WHERE component = :component 
                    AND eventtype = :eventtype 
                    AND timecreated < :timecreated";
            $params = [
                'component' => 'enrol_sepay',
                'eventtype' => 'pending_transaction',
                'timecreated' => $timecreated,
            ];
            $DB->execute($sql, $params);
            redirect($PAGE->url, get_string('notifications_deleted_success', 'enrol_sepay'));
            break;

        case 'delete_all_1month':
            // Xóa tất cả thông báo (đã đọc và chưa đọc) cũ hơn 1 tháng.
            $timecreated = time() - (30 * 24 * 60 * 60);
            $sql = "DELETE FROM {notifications} 
                    WHERE component = :component 
                    AND eventtype = :eventtype 
                    AND timecreated < :timecreated";
            $params = [
                'component' => 'enrol_sepay',
                'eventtype' => 'pending_transaction',
                'timecreated' => $timecreated,
            ];
            $DB->execute($sql, $params);
            redirect($PAGE->url, get_string('notifications_deleted_success', 'enrol_sepay'));
            break;

        case 'delete_all_3months':
            // Xóa tất cả thông báo (đã đọc và chưa đọc) cũ hơn 3 tháng.
            $timecreated = time() - (90 * 24 * 60 * 60);
            $sql = "DELETE FROM {notifications} 
                    WHERE component = :component 
                    AND eventtype = :eventtype 
                    AND timecreated < :timecreated";
            $params = [
                'component' => 'enrol_sepay',
                'eventtype' => 'pending_transaction',
                'timecreated' => $timecreated,
            ];
            $DB->execute($sql, $params);
            redirect($PAGE->url, get_string('notifications_deleted_success', 'enrol_sepay'));
            break;

        case 'delete_all_6months':
            // Xóa tất cả thông báo (đã đọc và chưa đọc) cũ hơn 6 tháng.
            $timecreated = time() - (180 * 24 * 60 * 60);
            $sql = "DELETE FROM {notifications} 
                    WHERE component = :component 
                    AND eventtype = :eventtype 
                    AND timecreated < :timecreated";
            $params = [
                'component' => 'enrol_sepay',
                'eventtype' => 'pending_transaction',
                'timecreated' => $timecreated,
            ];
            $DB->execute($sql, $params);
            redirect($PAGE->url, get_string('notifications_deleted_success', 'enrol_sepay'));
            break;

        case 'delete_all':
            // Xóa tất cả thông báo (đã đọc và chưa đọc).
            $sql = "DELETE FROM {notifications} 
                    WHERE component = :component 
                    AND eventtype = :eventtype";
            $params = [
                'component' => 'enrol_sepay',
                'eventtype' => 'pending_transaction',
            ];
            $DB->execute($sql, $params);
            redirect($PAGE->url, get_string('all_notifications_deleted_success', 'enrol_sepay'));
            break;
    }
}

// Lấy thống kê thông báo bằng cách gọi count_records() riêng biệt — cross-DB (PostgreSQL, MySQL, MSSQL).
$stats_params = [
    'component' => 'enrol_sepay',
    'eventtype' => 'pending_transaction',
];
$total        = $DB->count_records('notifications', $stats_params);
$read_count   = $DB->count_records_select(
    'notifications',
    "component = :component AND eventtype = :eventtype AND timeread IS NOT NULL",
    $stats_params
);
$unread_count = $total - $read_count;

echo $OUTPUT->header();

// Tiêu đề trang
echo '<h2>' . get_string('notification_settings', 'enrol_sepay') . '</h2>';

// Hiển thị thống kê.
echo '<div class="alert alert-info">';
echo '<h4>' . get_string('notification_statistics', 'enrol_sepay') . '</h4>';
echo '<ul>';
echo '<li>' . get_string('total_notifications', 'enrol_sepay') . ': <strong>' . $total . '</strong></li>';
echo '<li>' . get_string('read_notifications', 'enrol_sepay') . ': <strong>' . $read_count . '</strong></li>';
echo '<li>' . get_string('unread_notifications', 'enrol_sepay') . ': <strong>' . $unread_count . '</strong></li>';
echo '</ul>';
echo '</div>';

// Bắt đầu form chứa 2 dropdown
echo '<form method="post" action="' . $PAGE->url . '" class="config-form" id="notification_config_form">';
echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';

// Phần xóa thông báo đã đọc.
echo '<div class="form-group row">';
echo '<label class="col-md-3 col-form-label">';
echo get_string('delete_read_notifications', 'enrol_sepay');
echo '</label>';
echo '<div class="col-md-9">';

echo '<select name="delete_read_config" class="custom-select mr-2 sepay-select-auto" id="delete_read_select">';
echo '<option value="delete_read_1day"' . ($saved_delete_read == 'delete_read_1day' ? ' selected' : '') . '>' . get_string('delete_read_1day_option', 'enrol_sepay') . '</option>';
echo '<option value="delete_read_1week"' . ($saved_delete_read == 'delete_read_1week' ? ' selected' : '') . '>' . get_string('delete_read_1week_option', 'enrol_sepay') . '</option>';
echo '<option value="delete_read_1month"' . ($saved_delete_read == 'delete_read_1month' ? ' selected' : '') . '>' . get_string('delete_read_1month_option', 'enrol_sepay') . '</option>';
echo '<option value="delete_read_3months"' . ($saved_delete_read == 'delete_read_3months' ? ' selected' : '') . '>' . get_string('delete_read_3months_option', 'enrol_sepay') . '</option>';
echo '<option value="delete_read_6months"' . ($saved_delete_read == 'delete_read_6months' ? ' selected' : '') . '>' . get_string('delete_read_6months_option', 'enrol_sepay') . '</option>';
echo '<option value="delete_all_read"' . ($saved_delete_read == 'delete_all_read' ? ' selected' : '') . '>' . get_string('delete_read_never_option', 'enrol_sepay') . '</option>';
echo '</select>';

echo '<span class="text-muted">' . get_string('delete_read_time_label', 'enrol_sepay') . '</span>';

echo '<div>';
echo get_string('delete_read_notifications_desc', 'enrol_sepay');
echo '</div>';

echo '</div>';
echo '</div>';

// Phần xóa tất cả thông báo.
echo '<div class="form-group row">';
echo '<label class="col-md-3 col-form-label">';
echo get_string('delete_all_notifications_label', 'enrol_sepay');
echo '</label>';
echo '<div class="col-md-9">';

echo '<select name="delete_all_config" class="custom-select mr-2 sepay-select-auto" id="delete_all_select">';
echo '<option value="delete_all_1day"' . ($saved_delete_all == 'delete_all_1day' ? ' selected' : '') . '>' . get_string('delete_read_1day_option', 'enrol_sepay') . '</option>';
echo '<option value="delete_all_1week"' . ($saved_delete_all == 'delete_all_1week' ? ' selected' : '') . '>' . get_string('delete_read_1week_option', 'enrol_sepay') . '</option>';
echo '<option value="delete_all_1month"' . ($saved_delete_all == 'delete_all_1month' ? ' selected' : '') . '>' . get_string('delete_read_1month_option', 'enrol_sepay') . '</option>';
echo '<option value="delete_all_3months"' . ($saved_delete_all == 'delete_all_3months' ? ' selected' : '') . '>' . get_string('delete_read_3months_option', 'enrol_sepay') . '</option>';
echo '<option value="delete_all_6months"' . ($saved_delete_all == 'delete_all_6months' ? ' selected' : '') . '>' . get_string('delete_read_6months_option', 'enrol_sepay') . '</option>';
echo '<option value="delete_all"' . ($saved_delete_all == 'delete_all' ? ' selected' : '') . '>' . get_string('delete_read_never_option', 'enrol_sepay') . '</option>';
echo '</select>';

echo '<span class="text-muted">' . get_string('delete_all_time_label', 'enrol_sepay') . '</span>';

echo '<div>';
echo get_string('delete_all_notifications_desc', 'enrol_sepay');
echo '</div>';

echo '</div>';
echo '</div>';

// Nút lưu
echo '<div class="form-group row">';
echo '<div class="col-md-3"></div>';
echo '<div class="col-md-9">';
echo '<button type="submit" class="btn btn-primary">';
echo get_string('save_changes', 'enrol_sepay');
echo '</button>';
echo '</div>';
echo '</div>';

// Kết thúc form
echo '</form>';

// Hiển thị danh sách thông báo gần đây.
if ($total > 0) {
    echo '<div class="card">';
    echo '<div class="card-body">';
    echo '<h5 class="card-title">' . get_string('recent_notifications', 'enrol_sepay') . '</h5>';

    // Phân trang
    $page = optional_param('page', 0, PARAM_INT);
    $perpage = 20;

    $sql = "SELECT n.*, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic,
                    u.middlename, u.alternatename, u.email
            FROM {notifications} n
            LEFT JOIN {user} u ON n.useridfrom = u.id
            WHERE n.component = :component
            AND n.eventtype = :eventtype
            ORDER BY n.timecreated DESC";

    $totalcount = $DB->count_records_sql(
        "SELECT COUNT(*) FROM {notifications} WHERE component = :component AND eventtype = :eventtype",
        $stats_params
    );

    $notifications = $DB->get_records_sql($sql, $stats_params, $page * $perpage, $perpage);

    // Batch-load recipients thay vì N queries trong loop.
    $recipient_ids = array_unique(array_column((array)$notifications, 'useridto'));
    $recipients_map = [];
    if (!empty($recipient_ids)) {
        $recipients_map = $DB->get_records_list('user', 'id', $recipient_ids, '', '*');
    }

    if ($notifications) {
        $table = new html_table();
        $table->head = [
            get_string('sender', 'enrol_sepay'),
            get_string('recipient', 'enrol_sepay'),
            get_string('subject', 'enrol_sepay'),
            get_string('timecreated', 'enrol_sepay'),
            get_string('status', 'enrol_sepay'),
            get_string('actions', 'enrol_sepay'),
        ];

        foreach ($notifications as $notification) {
            // Sender data already available from the JOIN — no extra query needed.
            $sendername = trim(($notification->firstname ?? '') . ' ' . ($notification->lastname ?? '')) ?: 'N/A';

            $recipient = $recipients_map[$notification->useridto] ?? null;
            $recipientname = $recipient ? fullname($recipient) : 'N/A';

            $status = $notification->timeread
                ? '<span class="badge badge-success">' . get_string('read', 'enrol_sepay') . '</span>'
                : '<span class="badge badge-danger">' . get_string('unread', 'enrol_sepay') . '</span>';

            // Nút xóa
            $deleteurl = new moodle_url($PAGE->url, [
                'action' => 'delete_notification',
                'id' => $notification->id,
                'sesskey' => sesskey(),
            ]);
            $deletebutton = html_writer::link(
                $deleteurl,
                get_string('delete', 'enrol_sepay'),
                [
                    'class' => 'btn btn-sm btn-danger',
                    'onclick' => 'return confirm(' . json_encode(get_string('confirm_delete_notification', 'enrol_sepay')) . ');',
                ]
            );

            $table->data[] = [
                $sendername,
                $recipientname,
                s($notification->subject),
                userdate($notification->timecreated),
                $status,
                $deletebutton,
            ];
        }

        echo html_writer::table($table);

        // Hiển thị thanh phân trang
        echo $OUTPUT->paging_bar($totalcount, $page, $perpage, $PAGE->url);
    }

    echo '</div>';
    echo '</div>';
}

echo $OUTPUT->footer();
