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
 * Tác vụ tự động cập nhật danh sách ngân hàng hỗ trợ của SePay.
 *
 * @package    enrol_sepay
 * @copyright  2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_sepay\task;

/**
 * Scheduled task: cập nhật danh sách ngân hàng hỗ trợ từ SePay.
 */
class update_banks extends \core\task\scheduled_task {
    /**
     * Lấy tên mô tả của khối tác vụ này.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_update_banks', 'enrol_sepay');
    }

    /**
     * Thực thi tác vụ gọi API ngân hàng và lưu vào Cache.
     */
    public function execute() {
        global $CFG;

        mtrace('Bắt đầu cập nhật danh sách ngân hàng từ SePay...');

        $bankapiurl = 'https://qr.sepay.vn/banks.json';
        $raw = false;

        // Sử dụng Moodle curl thay cho curl thô nếu có thể, hoặc dùng thư viện native.
        require_once($CFG->libdir . '/filelib.php');
        $curl = new \curl();

        $curl->setopt([
            'CURLOPT_TIMEOUT' => 10,
            'CURLOPT_FOLLOWLOCATION' => true,
            'CURLOPT_SSL_VERIFYPEER' => true,
        ]);

        $raw = $curl->get($bankapiurl);

        if ($curl->get_errno() || $raw === false) {
            mtrace('Lỗi khi lấy danh sách ngân hàng: ' . $curl->error);
            return;
        }

        $decoded = json_decode($raw, true);

        if (is_array($decoded) && !empty($decoded['data']) && is_array($decoded['data'])) {
            set_config('bank_list_json', json_encode($decoded), 'enrol_sepay');
            mtrace('Đã cập nhật danh sách ' . count($decoded['data']) . ' ngân hàng thành công.');
        } else {
            mtrace('Dữ liệu API trả về không hợp lệ.');
        }
    }
}
