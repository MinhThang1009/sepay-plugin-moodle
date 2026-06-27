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

namespace enrol_sepay;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use context_course;

defined('MOODLE_INTERNAL') || die();

// Tương thích ngược Moodle < 4.2: các lớp external_* còn ở global (lib/externallib.php),
// chưa thuộc namespace core_external. Nạp + alias sang core_external\ để dùng chung một kiểu.
if (!class_exists('core_external\\external_api')) {
    global $CFG;
    require_once($CFG->libdir . '/externallib.php');
    class_alias('external_api', 'core_external\\external_api');
    class_alias('external_function_parameters', 'core_external\\external_function_parameters');
    class_alias('external_value', 'core_external\\external_value');
    class_alias('external_single_structure', 'core_external\\external_single_structure');
}

/**
 * Lớp API dành cho Web Services của enrol_sepay.
 *
 * @package    enrol_sepay
 * @copyright  2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class external extends external_api {
    /**
     * Trả về chi tiết các tham số đầu vào của API
     * @return external_function_parameters
     */
    public static function check_transaction_status_parameters() {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'ID của khoá học cần kiểm tra trạng thái', VALUE_REQUIRED),
        ]);
    }

    /**
     * Kiểm tra trạng thái giao dịch hiện tại của user với khoá học.
     *
     * @param int $courseid
     * @return array
     */
    public static function check_transaction_status($courseid) {
        global $USER;

        $params = self::validate_parameters(self::check_transaction_status_parameters(), ['courseid' => $courseid]);
        $courseid = $params['courseid'];

        $context = context_course::instance($courseid);

        // Sử dụng context hệ thống cho WebService này, vì học viên chưa được ghi danh
        // nên nếu dùng context khoá học sẽ bị dính lỗi 'require_login_exception'.
        self::validate_context(\context_system::instance());

        $found = self::find_latest_transactions($USER->id, $courseid);
        $enrolled = is_enrolled($context, $USER);

        return self::build_status_response($enrolled, $found);
    }

    /**
     * Tìm giao dịch MỚI NHẤT cho mỗi trạng thái của user trong khóa học.
     *
     * @param int $userid
     * @param int $courseid
     * @return array map status (pending/processed/rejected/unenrolled) => bản ghi giao dịch hoặc null
     */
    private static function find_latest_transactions($userid, $courseid): array {
        global $DB;

        // Gộp thành 1 query để giảm số lần truy vấn DB; sắp xếp DESC nên bản ghi đầu mỗi status là mới nhất.
        $alltxns = $DB->get_records_select(
            'enrol_sepay_transactions',
            'userid = :uid AND courseid = :cid',
            ['uid' => $userid, 'cid' => $courseid],
            'timecreated DESC'
        );

        $found = ['pending' => null, 'processed' => null, 'rejected' => null, 'unenrolled' => null];
        foreach ($alltxns as $txn) {
            if (array_key_exists($txn->status, $found) && !$found[$txn->status]) {
                $found[$txn->status] = $txn;
            }
        }
        return $found;
    }

    /**
     * Dựng mảng kết quả trạng thái trả về cho web service.
     *
     * @param bool $enrolled User đã ghi danh chưa
     * @param array $found map status => giao dịch|null
     * @return array
     */
    private static function build_status_response(bool $enrolled, array $found): array {
        return [
            'enrolled'   => $enrolled,
            'pending'    => (!$enrolled && $found['pending']) ? true : false,
            'processed'  => $found['processed'] ? true : false,
            'rejected'   => (!$enrolled && $found['rejected']) ? true : false,
            'unenrolled' => (!$enrolled && $found['unenrolled']) ? true : false,
        ];
    }

    /**
     * Trả về định dạng cấu trúc kết quả đầu ra
     * @return external_single_structure
     */
    public static function check_transaction_status_returns() {
        return new external_single_structure([
            'enrolled'   => new external_value(PARAM_BOOL, 'Học viên đã được ghi danh chưa?'),
            'pending'    => new external_value(PARAM_BOOL, 'Có giao dịch nào đang chờ xử lý không?'),
            'processed'  => new external_value(PARAM_BOOL, 'Có giao dịch nào đã được xử lý không?'),
            'rejected'   => new external_value(PARAM_BOOL, 'Có giao dịch nào bị từ chối không?'),
            'unenrolled' => new external_value(PARAM_BOOL, 'Có giao dịch nào đã bị hủy ghi danh không?'),
        ]);
    }
}
