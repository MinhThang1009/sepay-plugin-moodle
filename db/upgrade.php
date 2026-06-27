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
 * Khối lệnh nâng cấp cấu trúc Database cục bộ của plugin enrol_sepay.
 *
 * @package    enrol_sepay
 * @copyright  2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Thêm các cột vào bảng nếu chưa tồn tại (idempotent).
 *
 * Gom vòng lặp field_exists/add_field ra ngoài để giảm độ phức tạp của hàm
 * upgrade chính. Site cài mới đã có cột (field_exists -> no-op); site nâng cấp
 * từ bản cũ sẽ được thêm.
 *
 * @param \database_manager $dbman
 * @param \xmldb_table $table
 * @param \xmldb_field[] $fields
 * @return void
 */
function enrol_sepay_add_fields_if_missing($dbman, $table, array $fields) {
    foreach ($fields as $field) {
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
    }
}

/**
 * Thực thi nâng cấp plugin enrol_sepay.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_enrol_sepay_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026010100) {
        // Thêm cột transaction_ref vào enrol_sepay_transactions để chống replay attack.
        $table = new \xmldb_table('enrol_sepay_transactions');
        enrol_sepay_add_fields_if_missing($dbman, $table, [
            new \xmldb_field('transaction_ref', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'transaction_content'),
        ]);
        $index = new \xmldb_index('transaction_ref', XMLDB_INDEX_NOTUNIQUE, ['transaction_ref']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Thêm cột transaction_ref vào enrol_sepay_archive.
        $table2 = new \xmldb_table('enrol_sepay_archive');
        enrol_sepay_add_fields_if_missing($dbman, $table2, [
            new \xmldb_field('transaction_ref', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'transaction_content'),
        ]);

        upgrade_plugin_savepoint(true, 2026010100, 'enrol', 'sepay');
    }

    if ($oldversion < 2026062700) {
        // Bổ sung các cột được thêm vào install.xml sau bản 2026010100 nhưng thiếu
        // bước upgrade tương ứng — tránh lỗi "Unknown column" khi nâng cấp từ bản cũ.
        $table = new \xmldb_table('enrol_sepay_transactions');
        enrol_sepay_add_fields_if_missing($dbman, $table, [
            new \xmldb_field('gateway', XMLDB_TYPE_CHAR, '50', null, null, null, null, 'transaction_ref'),
            new \xmldb_field('ip_address', XMLDB_TYPE_CHAR, '45', null, null, null, null, 'status'),
            new \xmldb_field('email_sent', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'timeprocessed'),
            new \xmldb_field('rejection_notified', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'email_sent'),
        ]);

        // Bảng archive: bổ sung các cột tương ứng (nếu bảng đã tồn tại).
        $tablearchive = new \xmldb_table('enrol_sepay_archive');
        if ($dbman->table_exists($tablearchive)) {
            enrol_sepay_add_fields_if_missing($dbman, $tablearchive, [
                new \xmldb_field('gateway', XMLDB_TYPE_CHAR, '50', null, null, null, null, 'transaction_ref'),
                new \xmldb_field('ip_address', XMLDB_TYPE_CHAR, '45', null, null, null, null, 'status'),
                new \xmldb_field('email_sent', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'timearchived'),
                new \xmldb_field('rejection_notified', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'email_sent'),
            ]);
        }

        upgrade_plugin_savepoint(true, 2026062700, 'enrol', 'sepay');
    }

    return true;
}
