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
 * Ad-hoc task gửi email hàng loạt cho học viên SePay chưa nhận email.
 *
 * @package    enrol_sepay
 * @copyright  2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_sepay\task;

defined('MOODLE_INTERNAL') || die();

class send_mass_email extends \core\task\adhoc_task {
    public function execute() {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/enrol/sepay/classes/util.php');

        $data     = $this->get_custom_data();
        $limit    = isset($data->limit) ? (int)$data->limit : 50;
        $admin    = get_admin();

        // Tìm các giao dịch processed có email_sent = 0.
        // Dùng tham số phân trang của Moodle thay vì LIMIT trong raw SQL
        // vì LIMIT với named param không hoạt động trên mọi DB driver.
        $sql = "SELECT t.id, t.userid, t.courseid, t.instanceid
                  FROM {enrol_sepay_transactions} t
                 WHERE t.status = 'processed'
                   AND t.email_sent = 0
              ORDER BY t.timeprocessed ASC";

        $transactions = $DB->get_records_sql($sql, [], 0, $limit);

        if (empty($transactions)) {
            mtrace('enrol_sepay send_mass_email: no pending emails to send.');
            return;
        }

        $sent = 0;
        $fail = 0;

        foreach ($transactions as $tx) {
            $user   = $DB->get_record('user', ['id' => $tx->userid]);
            $course = $DB->get_record('course', ['id' => $tx->courseid]);

            if (!$user || !$course) {
                $DB->set_field('enrol_sepay_transactions', 'email_sent', 1, ['id' => $tx->id]);
                $fail++;
                continue;
            }

            $instance = $DB->get_record('enrol', ['id' => $tx->instanceid], '*', IGNORE_MISSING);

            try {
                // Chỉ đánh dấu email_sent khi send_welcome_messages gửi thực sự (trả true).
                // Trả false (không throw) khi tắt hết mail config → không đánh dấu để lần sau gửi lại.
                if (\enrol_sepay\util::send_welcome_messages($course, $user, $instance ?? new \stdClass())) {
                    $DB->set_field('enrol_sepay_transactions', 'email_sent', 1, ['id' => $tx->id]);
                    $sent++;
                    mtrace('enrol_sepay send_mass_email: sent to user ' . $user->id . ' course ' . $course->id);
                } else {
                    mtrace('enrol_sepay send_mass_email: bỏ qua user ' . $user->id . ' (chưa bật gửi email).');
                }
            } catch (\Exception $e) {
                debugging('enrol_sepay send_mass_email: failed for user ' . $user->id . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
                $fail++;
            }
        }

        mtrace("enrol_sepay send_mass_email: sent=$sent, failed=$fail.");
    }
}
