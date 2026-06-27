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
 * Cron fallback: gửi rejection notification cho các transaction bị reject chưa được thông báo.
 *
 * @package    enrol_sepay
 * @copyright  2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_sepay\task;

defined('MOODLE_INTERNAL') || die();

class process_rejections extends \core\task\scheduled_task {
    public function get_name() {
        return get_string('task_process_rejections', 'enrol_sepay');
    }

    public function execute() {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/enrol/sepay/classes/util.php');

        $plugin = enrol_get_plugin('sepay');
        if (!$plugin || !$plugin->get_config('mailrejection', 1)) {
            return;
        }

        $transactions = $DB->get_records_select(
            'enrol_sepay_transactions',
            "status = 'rejected' AND rejection_notified = 0",
            [],
            'timeprocessed ASC',
            '*',
            0,
            200
        );

        if (empty($transactions)) {
            return;
        }

        $sent = 0;
        $fail = 0;

        foreach ($transactions as $txn) {
            $user   = $DB->get_record('user', ['id' => $txn->userid]);
            $course = $DB->get_record('course', ['id' => $txn->courseid]);

            if (!$user || !$course) {
                $DB->set_field('enrol_sepay_transactions', 'rejection_notified', 1, ['id' => $txn->id]);
                $fail++;
                continue;
            }

            try {
                // Chỉ đánh dấu đã thông báo khi gửi thực sự thành công.
                // send_rejection_notification trả false (không throw) khi mailstudents tắt / thiếu provider.
                if (\enrol_sepay\util::send_rejection_notification($course, $user)) {
                    $DB->set_field('enrol_sepay_transactions', 'rejection_notified', 1, ['id' => $txn->id]);
                    $sent++;
                    mtrace('enrol_sepay process_rejections: notified user ' . $user->id . ' for txn ' . $txn->id);
                } else {
                    // Chưa gửi → KHÔNG đánh dấu, để lần chạy sau gửi lại khi admin bật config.
                    mtrace('enrol_sepay process_rejections: bỏ qua txn ' . $txn->id . ' (chưa bật gửi thông báo).');
                }
            } catch (\Exception $e) {
                debugging('enrol_sepay process_rejections: failed for txn ' . $txn->id . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
                $fail++;
            }
        }

        mtrace("enrol_sepay process_rejections: sent=$sent, failed=$fail.");
    }
}
