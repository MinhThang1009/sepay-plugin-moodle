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
 * Xuất dữ liệu giao dịch SePay ra tệp (CSV, Excel, PDF, ...).
 *
 * @package    enrol_sepay
 * @copyright  2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Mục đích: Xuất dữ liệu giao dịch SePay ra file (CSV, Excel, PDF, ...)
// Chức năng: Đọc filter + danh sách ID từ POST, query DB, xuất file qua dataformat API.

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Kiểm tra đăng nhập và quyền.
require_login();
$context = context_system::instance();
require_capability('enrol/sepay:manage', $context);
require_sesskey();

// Lấy tham số.
$dataformat    = required_param('dataformat', PARAM_ALPHANUMEXT);
$filter        = optional_param('filter', 'all', PARAM_ALPHA);
$searchuser   = optional_param('search_user', '', PARAM_TEXT);
$searchcourse = optional_param('search_course', '', PARAM_TEXT);
$datefrom     = optional_param('date_from', '', PARAM_TEXT);
$dateto       = optional_param('date_to', '', PARAM_TEXT);
$letterraw    = optional_param('letter', '', PARAM_TEXT);
$letterfnraw = optional_param('letter_fn', '', PARAM_TEXT);

// Danh sách bảng chữ cái hợp lệ.
$vnalphabet = [
    'A', 'Ă', 'Â', 'B', 'C', 'D', 'Đ', 'E', 'Ê', 'G',
    'H', 'I', 'K', 'L', 'M', 'N', 'O', 'Ô', 'Ơ', 'P',
    'Q', 'R', 'S', 'T', 'U', 'Ư', 'V', 'X', 'Y',
];
$letter    = in_array(mb_strtoupper($letterraw, 'UTF-8'), $vnalphabet, true) ? mb_strtoupper($letterraw, 'UTF-8') : '';
$letterfn = in_array(mb_strtoupper($letterfnraw, 'UTF-8'), $vnalphabet, true) ? mb_strtoupper($letterfnraw, 'UTF-8') : '';

// Lấy danh sách ID được chọn từ form (nếu có).
$selectedids = optional_param_array('deleteids', [], PARAM_INT);

// Xây SQL WHERE.
$sqlwhere = "FROM {enrol_sepay_transactions} t
        JOIN {user}   u ON t.userid   = u.id
        JOIN {course} c ON t.courseid = c.id
        WHERE 1=1";
$params = [];

// Nếu có ID cụ thể được chọn thì chỉ xuất những dòng đó.
if (!empty($selectedids)) {
    [$insql, $inparams] = $DB->get_in_or_equal($selectedids, SQL_PARAMS_NAMED, 'sel');
    $sqlwhere .= " AND t.id $insql";
    $params = array_merge($params, $inparams);
} else {
    // Không chọn cụ thể → xuất theo filter hiện tại.
    if ($filter === 'pending' || $filter === 'processed' || $filter === 'rejected' || $filter === 'unenrolled') {
        $sqlwhere .= " AND t.status = :status";
        $params['status'] = $filter;
    }
    if ($searchuser !== '') {
        $sqlwhere .= " AND (" . $DB->sql_like('u.firstname', ':su1', false)
                   . " OR "  . $DB->sql_like('u.lastname', ':su2', false)
                   . " OR "  . $DB->sql_like('u.email', ':su3', false) . ")";
        $like = '%' . $DB->sql_like_escape($searchuser) . '%';
        $params['su1'] = $like;
        $params['su2'] = $like;
        $params['su3'] = $like;
    }
    if ($searchcourse !== '') {
        $sqlwhere .= " AND " . $DB->sql_like('c.fullname', ':sc', false);
        $params['sc'] = '%' . $DB->sql_like_escape($searchcourse) . '%';
    }
    if ($datefrom !== '') {
        $ts = strtotime($datefrom . ' 00:00:00');
        if ($ts !== false) {
            $sqlwhere .= " AND t.timecreated >= :date_from";
            $params['date_from'] = $ts;
        }
    }
    if ($dateto !== '') {
        $ts = strtotime($dateto . ' 23:59:59');
        if ($ts !== false) {
            $sqlwhere .= " AND t.timecreated <= :date_to";
            $params['date_to'] = $ts;
        }
    }
    if ($letter !== '') {
        $sqlwhere .= " AND " . $DB->sql_like('u.lastname', ':letter', false);
        $params['letter'] = $DB->sql_like_escape($letter) . '%';
    }
    if ($letterfn !== '') {
        $sqlwhere .= " AND " . $DB->sql_like('u.firstname', ':letter_fn', false);
        $params['letter_fn'] = $DB->sql_like_escape($letterfn) . '%';
    }
}

// Câu SQL đầy đủ — lấy tất cả cột cần xuất.
$sql = "SELECT t.id,
               u.lastname,
               u.firstname,
               u.email,
               c.fullname    AS coursename,
               t.amount,
               t.currency,
               t.transaction_content,
               t.status,
               t.timecreated,
               t.timeprocessed,
               t.ip_address
        " . $sqlwhere . "
        ORDER BY t.timecreated DESC";

$records = $DB->get_recordset_sql($sql, $params);

// Định nghĩa tiêu đề cột.
$columns = [
    'id'                  => 'ID',
    'lastname'            => get_string('lastname'),
    'firstname'           => get_string('firstname'),
    'email'               => get_string('email'),
    'coursename'          => get_string('course', 'enrol_sepay'),
    'amount'              => get_string('amount', 'enrol_sepay'),
    'transaction_content' => get_string('trans_content', 'enrol_sepay'),
    'status'              => get_string('transaction_status', 'enrol_sepay'),
    'timecreated'         => get_string('timecreated', 'enrol_sepay'),
    'timeprocessed'       => get_string('process_date', 'enrol_sepay'),
    'ip_address'          => get_string('ip_address', 'enrol_sepay'),
];

// Chuyển đổi dữ liệu thô trước khi ghi vào file.
$callback = function ($row) {
    // Gộp amount + currency thành 1 ô.
    $row->amount = number_format($row->amount) . ' ' . $row->currency;
    unset($row->currency);

    // Trạng thái dạng text.
    $statusmap = [
        'pending'    => get_string('status_pending', 'enrol_sepay'),
        'processed'  => get_string('status_processed', 'enrol_sepay'),
        'rejected'   => get_string('status_rejected', 'enrol_sepay'),
        'unenrolled' => get_string('status_unenrolled', 'enrol_sepay'),
    ];
    $row->status = $statusmap[$row->status] ?? $row->status;

    // Ngày tạo và ngày xử lý dạng text.
    $row->timecreated   = $row->timecreated ? userdate($row->timecreated) : '';
    $row->timeprocessed = $row->timeprocessed ? userdate($row->timeprocessed) : '';

    return $row;
};

// Xuất file — hàm này gửi header và nội dung file thẳng ra browser.
\core\dataformat::download_data(
    'sepay_transactions',
    $dataformat,
    $columns,
    $records,
    $callback
);

$records->close();
