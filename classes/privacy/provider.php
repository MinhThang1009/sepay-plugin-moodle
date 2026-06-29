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
 * Hiện thực Privacy Subsystem cho plugin enrol_sepay.
 *
 * @package    enrol_sepay
 * @category   privacy
 * @copyright  2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_sepay\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Hiện thực Privacy Subsystem cho enrol_sepay.
 *
 * Các bảng giao dịch SePay lưu dữ liệu cá nhân (userid, nội dung chuyển khoản,
 * địa chỉ IP) gắn với từng khóa học, nên dữ liệu được quy về context khóa học.
 *
 * @copyright  2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Các bảng có lưu dữ liệu cá nhân; đều có cột userid + courseid.
     */
    private const TABLES = [
        'enrol_sepay_transactions',
        'enrol_sepay_archive',
        'enrol_sepay_pending_ips',
    ];

    /**
     * Mô tả dữ liệu cá nhân mà plugin lưu.
     *
     * @param collection $collection Tập hợp metadata cần bổ sung.
     * @return collection Tập hợp đã bổ sung mô tả dữ liệu.
     */
    public static function get_metadata(collection $collection): collection {
        $transactionfields = [
            'userid'              => 'privacy:metadata:enrol_sepay_transactions:userid',
            'courseid'            => 'privacy:metadata:enrol_sepay_transactions:courseid',
            'amount'              => 'privacy:metadata:enrol_sepay_transactions:amount',
            'currency'            => 'privacy:metadata:enrol_sepay_transactions:currency',
            'transaction_content' => 'privacy:metadata:enrol_sepay_transactions:transaction_content',
            'transaction_ref'     => 'privacy:metadata:enrol_sepay_transactions:transaction_ref',
            'gateway'             => 'privacy:metadata:enrol_sepay_transactions:gateway',
            'status'              => 'privacy:metadata:enrol_sepay_transactions:status',
            'ip_address'          => 'privacy:metadata:enrol_sepay_transactions:ip_address',
            'timecreated'         => 'privacy:metadata:enrol_sepay_transactions:timecreated',
        ];

        $collection->add_database_table(
            'enrol_sepay_transactions',
            $transactionfields,
            'privacy:metadata:enrol_sepay_transactions'
        );

        $collection->add_database_table(
            'enrol_sepay_archive',
            $transactionfields,
            'privacy:metadata:enrol_sepay_archive'
        );

        $collection->add_database_table(
            'enrol_sepay_pending_ips',
            [
                'userid'      => 'privacy:metadata:enrol_sepay_pending_ips:userid',
                'courseid'    => 'privacy:metadata:enrol_sepay_pending_ips:courseid',
                'ip_address'  => 'privacy:metadata:enrol_sepay_pending_ips:ip_address',
                'timecreated' => 'privacy:metadata:enrol_sepay_pending_ips:timecreated',
            ],
            'privacy:metadata:enrol_sepay_pending_ips'
        );

        return $collection;
    }

    /**
     * Trả về danh sách context chứa dữ liệu của người dùng.
     *
     * @param int $userid ID người dùng cần tìm.
     * @return contextlist Danh sách context khóa học có dữ liệu.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        foreach (self::TABLES as $table) {
            $sql = "SELECT ctx.id
                      FROM {" . $table . "} st
                      JOIN {context} ctx ON ctx.instanceid = st.courseid AND ctx.contextlevel = :contextcourse
                     WHERE st.userid = :userid";
            $contextlist->add_from_sql($sql, [
                'contextcourse' => CONTEXT_COURSE,
                'userid'        => $userid,
            ]);
        }

        return $contextlist;
    }

    /**
     * Trả về danh sách người dùng có dữ liệu trong một context.
     *
     * @param userlist $userlist Danh sách người dùng cần bổ sung.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();

        if (!$context instanceof \context_course) {
            return;
        }

        foreach (self::TABLES as $table) {
            $sql = "SELECT st.userid
                      FROM {" . $table . "} st
                     WHERE st.courseid = :courseid";
            $userlist->add_from_sql('userid', $sql, ['courseid' => $context->instanceid]);
        }
    }

    /**
     * Xuất toàn bộ dữ liệu cá nhân của người dùng trong các context đã duyệt.
     *
     * @param approved_contextlist $contextlist Các context được duyệt để xuất.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_course) {
                continue;
            }

            foreach (self::TABLES as $table) {
                $records = $DB->get_records($table, [
                    'userid'   => $user->id,
                    'courseid' => $context->instanceid,
                ]);

                if (empty($records)) {
                    continue;
                }

                $data = array_map([self::class, 'transform_record'], $records);
                writer::with_context($context)->export_data(
                    [get_string('privacy:metadata:' . $table, 'enrol_sepay')],
                    (object) ['transactions' => array_values($data)]
                );
            }
        }
    }

    /**
     * Xóa toàn bộ dữ liệu của mọi người dùng trong một context.
     *
     * @param \context $context Context cần xóa dữ liệu.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if (!$context instanceof \context_course) {
            return;
        }

        foreach (self::TABLES as $table) {
            $DB->delete_records($table, ['courseid' => $context->instanceid]);
        }
    }

    /**
     * Xóa dữ liệu của một người dùng trong các context đã duyệt.
     *
     * @param approved_contextlist $contextlist Các context và người dùng cần xóa.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        if (empty($contextlist->count())) {
            return;
        }

        $user = $contextlist->get_user();

        $courseids = [];
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_course) {
                $courseids[] = $context->instanceid;
            }
        }

        if (empty($courseids)) {
            return;
        }

        [$insql, $inparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED);
        $params = $inparams + ['userid' => $user->id];

        foreach (self::TABLES as $table) {
            $DB->delete_records_select($table, "userid = :userid AND courseid $insql", $params);
        }
    }

    /**
     * Xóa dữ liệu của nhiều người dùng trong một context.
     *
     * @param approved_userlist $userlist Context và danh sách người dùng cần xóa.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();

        if (!$context instanceof \context_course) {
            return;
        }

        [$usersql, $userparams] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params = $userparams + ['courseid' => $context->instanceid];

        foreach (self::TABLES as $table) {
            $DB->delete_records_select($table, "courseid = :courseid AND userid $usersql", $params);
        }
    }

    /**
     * Chuẩn hóa một bản ghi giao dịch để xuất: đổi timestamp sang dạng đọc được.
     *
     * @param \stdClass $record Bản ghi gốc từ DB.
     * @return \stdClass Bản ghi đã chuẩn hóa thời gian.
     */
    private static function transform_record(\stdClass $record): \stdClass {
        foreach (['timecreated', 'timeprocessed', 'timearchived'] as $field) {
            if (!empty($record->$field)) {
                $record->$field = transform::datetime($record->$field);
            }
        }
        return $record;
    }
}
