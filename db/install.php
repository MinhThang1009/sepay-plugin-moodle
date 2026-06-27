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
 * Tạo tables khi install plugin enrol_sepay.
 * Chạy tự động khi vào admin/index.php sau khi upload plugin.
 *
 * @package    enrol_sepay
 * @copyright  2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_enrol_sepay_install() {
    global $DB;
    $dbman = $DB->get_manager();

    // ------------------------------------------------------------------ //
    // TABLE: enrol_sepay_transactions                                      //
    // ------------------------------------------------------------------ //
    if (!$dbman->table_exists('enrol_sepay_transactions')) {
        $table = new xmldb_table('enrol_sepay_transactions');

        // XMLDB_UNSIGNED đã được xóa — deprecated từ Moodle 2.3, gây warning trên Moodle 4.x.
        $table->add_field('id',                  XMLDB_TYPE_INTEGER, '10',    null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('userid',              XMLDB_TYPE_INTEGER, '10',    null, XMLDB_NOTNULL);
        $table->add_field('courseid',            XMLDB_TYPE_INTEGER, '10',    null, XMLDB_NOTNULL);
        $table->add_field('instanceid',          XMLDB_TYPE_INTEGER, '10',    null, XMLDB_NOTNULL);
        $table->add_field('amount',              XMLDB_TYPE_NUMBER,  '10, 2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('currency',            XMLDB_TYPE_CHAR,    '3',     null, XMLDB_NOTNULL, null, 'VND');
        $table->add_field('transaction_content', XMLDB_TYPE_TEXT,    null,    null, null);
        $table->add_field('transaction_ref',     XMLDB_TYPE_CHAR,    '255',   null, null);
        $table->add_field('gateway',             XMLDB_TYPE_CHAR,    '50',    null, null);
        $table->add_field('status',              XMLDB_TYPE_CHAR,    '20',    null, XMLDB_NOTNULL, null, 'pending');
        $table->add_field('ip_address',          XMLDB_TYPE_CHAR,    '45',    null, null);
        $table->add_field('timecreated',         XMLDB_TYPE_INTEGER, '10',    null, XMLDB_NOTNULL);
        $table->add_field('timeprocessed',       XMLDB_TYPE_INTEGER, '10',    null, XMLDB_NOTNULL, null, '0');
        $table->add_field('email_sent',          XMLDB_TYPE_INTEGER, '1',     null, XMLDB_NOTNULL, null, '0');
        $table->add_field('rejection_notified',  XMLDB_TYPE_INTEGER, '1',     null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        $table->add_index('userid',             XMLDB_INDEX_NOTUNIQUE, ['userid']);
        $table->add_index('courseid',           XMLDB_INDEX_NOTUNIQUE, ['courseid']);
        $table->add_index('status',             XMLDB_INDEX_NOTUNIQUE, ['status']);
        $table->add_index('timecreated',        XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
        $table->add_index('status_timecreated', XMLDB_INDEX_NOTUNIQUE, ['status', 'timecreated']);
        $table->add_index('transaction_ref',    XMLDB_INDEX_NOTUNIQUE, ['transaction_ref']);

        $dbman->create_table($table);
    }

    // ------------------------------------------------------------------ //
    // TABLE: enrol_sepay_archive                                           //
    // ------------------------------------------------------------------ //
    if (!$dbman->table_exists('enrol_sepay_archive')) {
        $table = new xmldb_table('enrol_sepay_archive');

        $table->add_field('id',                  XMLDB_TYPE_INTEGER, '10',    null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('userid',              XMLDB_TYPE_INTEGER, '10',    null, XMLDB_NOTNULL);
        $table->add_field('courseid',            XMLDB_TYPE_INTEGER, '10',    null, XMLDB_NOTNULL);
        $table->add_field('instanceid',          XMLDB_TYPE_INTEGER, '10',    null, XMLDB_NOTNULL);
        $table->add_field('amount',              XMLDB_TYPE_NUMBER,  '10, 2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('currency',            XMLDB_TYPE_CHAR,    '3',     null, XMLDB_NOTNULL, null, 'VND');
        $table->add_field('transaction_content', XMLDB_TYPE_TEXT,    null,    null, null);
        $table->add_field('transaction_ref',     XMLDB_TYPE_CHAR,    '255',   null, null);
        $table->add_field('gateway',             XMLDB_TYPE_CHAR,    '50',    null, null);
        $table->add_field('status',              XMLDB_TYPE_CHAR,    '20',    null, XMLDB_NOTNULL, null, 'pending');
        $table->add_field('ip_address',          XMLDB_TYPE_CHAR,    '45',    null, null);
        $table->add_field('timecreated',         XMLDB_TYPE_INTEGER, '10',    null, XMLDB_NOTNULL);
        $table->add_field('timeprocessed',       XMLDB_TYPE_INTEGER, '10',    null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timearchived',        XMLDB_TYPE_INTEGER, '10',    null, XMLDB_NOTNULL);
        $table->add_field('email_sent',          XMLDB_TYPE_INTEGER, '1',     null, XMLDB_NOTNULL, null, '0');
        $table->add_field('rejection_notified',  XMLDB_TYPE_INTEGER, '1',     null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        $table->add_index('userid',       XMLDB_INDEX_NOTUNIQUE, ['userid']);
        $table->add_index('courseid',     XMLDB_INDEX_NOTUNIQUE, ['courseid']);
        $table->add_index('status',       XMLDB_INDEX_NOTUNIQUE, ['status']);
        $table->add_index('timearchived', XMLDB_INDEX_NOTUNIQUE, ['timearchived']);

        $dbman->create_table($table);
    }

    // ------------------------------------------------------------------ //
    // TABLE: enrol_sepay_pending_ips                                       //
    // ------------------------------------------------------------------ //
    if (!$dbman->table_exists('enrol_sepay_pending_ips')) {
        $table = new xmldb_table('enrol_sepay_pending_ips');

        $table->add_field('id',          XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('userid',      XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL);
        $table->add_field('courseid',    XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL);
        $table->add_field('ip_address',  XMLDB_TYPE_CHAR,    '45',  null, XMLDB_NOTNULL);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10',  null, XMLDB_NOTNULL);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);

        $table->add_index('userid_courseid', XMLDB_INDEX_UNIQUE,    ['userid', 'courseid']);
        $table->add_index('timecreated',     XMLDB_INDEX_NOTUNIQUE, ['timecreated']);

        $dbman->create_table($table);
    }

    return true;
}
