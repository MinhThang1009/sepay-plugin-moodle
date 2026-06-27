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

namespace enrol_sepay\table;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/tablelib.php');

use html_writer;
use moodle_url;
use table_sql;
use xmldb_table;

/**
 * Bảng hiển thị giao dịch SePay.
 *
 * @package    enrol_sepay
 * @copyright  2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class transactions_table extends table_sql {
    /**
     * @var array $filterparams Các tham số lọc đang được áp dụng
     */
    protected $filterparams;

    /**
     * @var array $duplicateips Lưu trữ các IP bị trùng lặp user (IP => số lượng)
     */
    protected $duplicateips = [];

    /**
     * @var array $ipusermap Map IP đến các userid (dùng trong function format_row)
     */
    protected $ipusermap = [];

    /**
     * @var array $ipdetailmap Map userid đến danh sách IP records (batch preload trong query_db)
     */
    protected $ipdetailmap = [];

    /**
     * Hàm khởi tạo.
     *
     * @param string $uniqueid Khóa unique cho bảng
     * @param moodle_url $baseurl URL gốc của trang
     * @param array $filterparams Mảng chứa các tham số lọc hiện tại
     */
    public function __construct($uniqueid, $baseurl, $filterparams = []) {
        parent::__construct($uniqueid);

        $this->filterparams = $filterparams;
        $this->define_baseurl($baseurl);

        $this->set_attribute('class', 'generaltable table-sm');

        $this->define_columns([
            'checkbox',
            'id',
            'userid',
            'ip_address',
            'coursename',
            'amount',
            'transaction_content',
            'status',
            'timecreated',
            'timeprocessed',
            'actions',
        ]);

        $this->define_headers([
            html_writer::tag('input', '', ['type' => 'checkbox', 'id' => 'select-all', 'title' => get_string('selectall')]),
            'ID',
            get_string('user', 'enrol_sepay'),
            get_string('ip_address', 'enrol_sepay'),
            get_string('course', 'enrol_sepay'),
            get_string('amount', 'enrol_sepay'),
            get_string('trans_content', 'enrol_sepay'),
            get_string('transaction_status', 'enrol_sepay'),
            get_string('timecreated', 'enrol_sepay'),
            get_string('process_date', 'enrol_sepay'),
            get_string('action', 'moodle'),
        ]);

        $this->set_default_per_page(20);
        $this->sortable(true, 'timecreated', SORT_DESC);
        $this->no_sorting('checkbox');
        $this->no_sorting('ip_address');
        $this->no_sorting('transaction_content');
        $this->no_sorting('actions');
    }

    /**
     * Chạy query để setup dữ liệu IP trùng trước khi render các hàng
     *
     * @param int $pagesize Số bản ghi mỗi trang
     * @param bool $useinitialsbar Có hiển thị thanh lọc theo chữ cái hay không
     */
    public function query_db($pagesize, $useinitialsbar = true) {
        global $DB;

        parent::query_db($pagesize, $useinitialsbar);

        if (!$this->rawdata || !$DB->get_manager()->table_exists(new xmldb_table('logstore_standard_log'))) {
            return;
        }

        // 2 batch queries lấy dữ liệu IP cho tất cả users trên trang, tránh N queries trong vòng lặp.
        $userids = array_unique(array_column((array)$this->rawdata, 'userid'));
        if (!empty($userids)) {
            $this->preload_ip_maps($userids);
        }

        foreach ($this->ipusermap as $ip => $useridsforip) {
            if (count($useridsforip) > 1) {
                $this->duplicateips[$ip] = count($useridsforip);
            }
        }
    }

    /**
     * Nạp sẵn 2 map IP (ipusermap, ipdetailmap) bằng 2 batch query trên logstore.
     *
     * @param array $userids Danh sách userid trên trang hiện tại
     * @return void
     */
    private function preload_ip_maps(array $userids): void {
        global $DB;

        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');

        // Batch 1: build ipusermap — dùng get_recordset_sql để tránh duplicate-key warning.
        $sqlbatch1 = "SELECT userid, ip
                         FROM {logstore_standard_log}
                        WHERE userid $insql AND ip IS NOT NULL AND ip != ''
                     GROUP BY userid, ip";
        $rs1 = $DB->get_recordset_sql($sqlbatch1, $inparams);
        foreach ($rs1 as $row) {
            if (!isset($this->ipusermap[$row->ip])) {
                $this->ipusermap[$row->ip] = [];
            }
            if (!in_array($row->userid, $this->ipusermap[$row->ip])) {
                $this->ipusermap[$row->ip][] = $row->userid;
            }
        }
        $rs1->close();

        // Batch 2: build ipdetailmap (last 5 IPs per user by most recent).
        $sqlbatch2 = "SELECT userid, ip, MAX(timecreated) as lastused
                         FROM {logstore_standard_log}
                        WHERE userid $insql AND ip IS NOT NULL AND ip != ''
                     GROUP BY userid, ip
                     ORDER BY lastused DESC";
        $rs2 = $DB->get_recordset_sql($sqlbatch2, $inparams);
        foreach ($rs2 as $row) {
            if (!isset($this->ipdetailmap[$row->userid])) {
                $this->ipdetailmap[$row->userid] = [];
            }
            if (count($this->ipdetailmap[$row->userid]) < 5) {
                $this->ipdetailmap[$row->userid][] = $row;
            }
        }
        $rs2->close();
    }

    /**
     * Định dạng giá trị hiển thị cho ô của cột này.
     *
     * @param \stdClass $row
     * @return string
     */
    public function col_checkbox($row) {
        return '<input type="checkbox" name="deleteids[]" value="' . $row->id
            . '" class="transaction-checkbox" data-userid="' . $row->userid
            . '" data-status="' . s($row->status) . '">';
    }

    /**
     * Định dạng giá trị hiển thị cho ô của cột này.
     *
     * @param \stdClass $row
     * @return string
     */
    public function col_userid($row) {
        return html_writer::link(
            new moodle_url('/user/view.php', ['id' => $row->userid]),
            fullname($row)
        );
    }

    /**
     * Định dạng giá trị hiển thị cho ô của cột này.
     *
     * @param \stdClass $row
     * @return string
     */
    public function col_ip_address($row) {
        // Không query DB — dùng ipdetailmap đã build sẵn trong query_db().
        if (isset($this->ipdetailmap[$row->userid])) {
            $iplist = [];
            foreach ($this->ipdetailmap[$row->userid] as $iprecord) {
                $ip = s($iprecord->ip);
                if (isset($this->duplicateips[$iprecord->ip])) {
                    $numusers    = $this->duplicateips[$iprecord->ip];
                    $warningtext = get_string('ip_duplicate_warning', 'enrol_sepay', $numusers);
                    $userstext   = get_string('ip_duplicate_users', 'enrol_sepay', $numusers);
                    $iplist[] = '<div class="text-danger font-weight-bold" title="' . s($warningtext) . '">'
                               . $ip . ' <span class="badge badge-danger ml-1">' . $userstext . '</span></div>';
                } else {
                    $iplist[] = '<div>' . $ip . '</div>';
                }
            }
            return implode('', $iplist);
        }
        return s($row->ip_address) ?: '<em>N/A</em>';
    }

    /**
     * Định dạng giá trị hiển thị cho ô của cột này.
     *
     * @param \stdClass $row
     * @return string
     */
    public function col_coursename($row) {
        return html_writer::link(
            new moodle_url('/course/view.php', ['id' => $row->courseid]),
            format_string($row->coursename)
        );
    }

    /**
     * Định dạng giá trị hiển thị cho ô của cột này.
     *
     * @param \stdClass $row
     * @return string
     */
    public function col_amount($row) {
        return number_format($row->amount) . ' ' . s($row->currency);
    }

    /**
     * Định dạng giá trị hiển thị cho ô của cột này.
     *
     * @param \stdClass $row
     * @return string
     */
    public function col_transaction_content($row) {
        return s($row->transaction_content);
    }

    /**
     * Định dạng giá trị hiển thị cho ô của cột này.
     *
     * @param \stdClass $row
     * @return string
     */
    public function col_status($row) {
        if ($row->status === 'pending') {
            return html_writer::tag(
                'span',
                get_string('status_pending', 'enrol_sepay'),
                ['class' => 'badge badge-warning']
            );
        } else if ($row->status === 'rejected') {
            return html_writer::tag(
                'span',
                get_string('status_rejected', 'enrol_sepay'),
                ['class' => 'badge badge-danger']
            );
        } else if ($row->status === 'unenrolled') {
            return html_writer::tag(
                'span',
                get_string('status_unenrolled', 'enrol_sepay'),
                ['class' => 'badge badge-secondary']
            );
        } else {
            return html_writer::tag(
                'span',
                get_string('status_processed', 'enrol_sepay'),
                ['class' => 'badge badge-success']
            );
        }
    }

    /**
     * Định dạng giá trị hiển thị cho ô của cột này.
     *
     * @param \stdClass $row
     * @return string
     */
    public function col_timecreated($row) {
        return userdate($row->timecreated);
    }

    /**
     * Định dạng giá trị hiển thị cho ô của cột này.
     *
     * @param \stdClass $row
     * @return string
     */
    public function col_timeprocessed($row) {
        return $row->timeprocessed ? userdate($row->timeprocessed) : '-';
    }

    /**
     * Định dạng giá trị hiển thị cho ô của cột này.
     *
     * @param \stdClass $row
     * @return string
     */
    public function col_actions($row) {
        $actions = '';
        if ($row->status === 'pending') {
            $approveurl = new moodle_url(
                '/enrol/sepay/transactions.php',
                array_merge($this->filterparams, ['action' => 'approve', 'id' => $row->id, 'sesskey' => sesskey()])
            );
            $rejecturl  = new moodle_url(
                '/enrol/sepay/transactions.php',
                array_merge($this->filterparams, ['action' => 'reject', 'id' => $row->id, 'sesskey' => sesskey()])
            );

            $actions .= html_writer::link($approveurl, get_string('approve', 'enrol_sepay'), [
                'class' => 'btn btn-outline-success btn-sm mb-1',
            ]);
            $actions .= ' ';
            $actions .= html_writer::link($rejecturl, get_string('reject', 'enrol_sepay'), [
                'class'   => 'btn btn-outline-danger btn-sm mb-1',
                'onclick' => 'return confirm(' . json_encode(get_string('confirm_reject', 'enrol_sepay')) . ');',
            ]);
        }

        if ($row->status === 'processed' || $row->status === 'rejected' || $row->status === 'unenrolled') {
            $deleteurl = new moodle_url(
                '/enrol/sepay/transactions.php',
                array_merge($this->filterparams, ['action' => 'delete', 'id' => $row->id, 'sesskey' => sesskey()])
            );
            if ($actions !== '') {
                $actions .= ' ';
            }
            $actions .= html_writer::link($deleteurl, get_string('delete'), [
                'class'   => 'btn btn-secondary btn-sm mb-1',
                'onclick' => 'return confirm(' . json_encode(get_string('confirm_delete', 'enrol_sepay')) . ');',
            ]);
        }

        return $actions;
    }
}
