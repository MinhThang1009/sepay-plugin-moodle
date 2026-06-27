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
 * Tác vụ xử lý các ghi danh hết hạn của SePay.
 *
 * @package    enrol_sepay
 * @copyright  2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_sepay\task;

defined('MOODLE_INTERNAL') || die();

class process_expirations extends \core\task\scheduled_task {
    /**
     * Tên tác vụ.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_process_expirations', 'enrol_sepay');
    }

    /**
     * Chạy tác vụ. Đồng bộ dữ liệu và hủy ghi danh khi cần.
     */
    public function execute() {
        global $CFG;

        require_once("$CFG->dirroot/enrol/sepay/lib.php");

        $trace = new \text_progress_trace();

        // Cập nhật các trạng thái hết hạn chuẩn của Moodle.
        $plugin = enrol_get_plugin('sepay');
        $plugin->sync($trace);
    }
}
