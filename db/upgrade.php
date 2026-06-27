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

defined('MOODLE_INTERNAL') || die();

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
        $field = new \xmldb_field('transaction_ref', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'transaction_content');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        $index = new \xmldb_index('transaction_ref', XMLDB_INDEX_NOTUNIQUE, ['transaction_ref']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // Thêm cột transaction_ref vào enrol_sepay_archive.
        $table2 = new \xmldb_table('enrol_sepay_archive');
        $field2 = new \xmldb_field('transaction_ref', XMLDB_TYPE_CHAR, '255', null, null, null, null, 'transaction_content');
        if (!$dbman->field_exists($table2, $field2)) {
            $dbman->add_field($table2, $field2);
        }

        upgrade_plugin_savepoint(true, 2026010100, 'enrol', 'sepay');
    }

    return true;
}
