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
 * Tác vụ tự động dọn dẹp các giao dịch SePay đã quá hạn.
 *
 * @package    enrol_sepay
 * @copyright  2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_sepay\task;

/**
 * Tác vụ tự động dọn dẹp các giao dịch SePay đã quá hạn.
 */
class cleanup_transactions extends \core\task\scheduled_task {
    /**
     * Lấy tên mô tả của task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('task_cleanup_transactions', 'enrol_sepay');
    }

    /**
     * Thực thi task.
     */
    public function execute() {
        global $DB;

        // Kiểm tra dọn dẹp tự động có được bật không.
        $enabled = get_config('enrol_sepay', 'auto_cleanup_enabled');
        if (!$enabled) {
            mtrace('Dọn dẹp tự động đang tắt. Bỏ qua...');
            return;
        }

        // Lấy thời gian lưu trữ tính bằng ngày.
        $retentiondays = get_config('enrol_sepay', 'retention_days');
        if (empty($retentiondays) || $retentiondays < 1) {
            $retentiondays = 365; // Mặc định 1 năm.
        }

        // Lấy chiến lược lưu trữ.
        $strategy = get_config('enrol_sepay', 'archive_strategy');
        if (empty($strategy)) {
            $strategy = 'archive'; // Mặc định là lưu trữ.
        }

        // Tính mốc thời gian giới hạn.
        $cutofftime = time() - ($retentiondays * 86400);

        mtrace('Bắt đầu dọn dẹp giao dịch SePay...');
        mtrace('Thời gian lưu trữ: ' . $retentiondays . ' ngày');
        mtrace('Mốc giới hạn: ' . userdate($cutofftime));
        mtrace('Chiến lược: ' . $strategy);

        // Tự động từ chối các pending transaction quá hạn để giữ DB sạch.
        $pendingretentiondays = (int)get_config('enrol_sepay', 'pending_retention_days');
        if ($pendingretentiondays < 1) {
            $pendingretentiondays = 30;
        }
        // Đảm bảo retention >= pending_retention: nếu admin cấu hình nghịch, pending vừa bị
        // reject (vẫn giữ timecreated cũ) sẽ bị dọn ngay trong cùng run → mất bản ghi sớm.
        if ($retentiondays < $pendingretentiondays) {
            $retentiondays = $pendingretentiondays;
            $cutofftime = time() - ($retentiondays * 86400);
            mtrace('retention_days < pending_retention_days → nâng retention lên ' . $retentiondays . ' ngày.');
        }
        $pendingcutoff = time() - ($pendingretentiondays * 86400);
        $DB->execute(
            "UPDATE {enrol_sepay_transactions}
                SET status = 'rejected', timeprocessed = :now
              WHERE status = 'pending'
                AND timecreated < :cutoff",
            ['now' => time(), 'cutoff' => $pendingcutoff]
        );
        mtrace('Đã từ chối các giao dịch pending quá ' . $pendingretentiondays . ' ngày.');

        // Tìm giao dịch cũ (đã xử lý, bị từ chối, hoặc đã hủy ghi danh — cũ hơn thời gian lưu trữ).
        // Giữ lại giao dịch pending bất kể thời gian.
        $sql = "SELECT *
                  FROM {enrol_sepay_transactions}
                 WHERE status IN ('processed', 'rejected', 'unenrolled')
                   AND timecreated < :cutoff";

        // Giới hạn mỗi lần chạy để không nạp toàn bộ record cũ vào RAM (phần dư xử lý ở các run sau).
        $oldtransactions = $DB->get_records_sql($sql, ['cutoff' => $cutofftime], 0, 500);

        if (empty($oldtransactions)) {
            mtrace('Không tìm thấy giao dịch cũ cần dọn dẹp.');
            return;
        }

        $count = count($oldtransactions);
        mtrace('Tìm thấy ' . $count . ' giao dịch cũ cần xử lý.');

        $archived = 0;
        $deleted = 0;

        foreach ($oldtransactions as $transaction) {
            if ($strategy === 'archive') {
                // Chuyển sang bảng lưu trữ (atomic: insert + delete trong cùng DB transaction).
                try {
                    $dbtransaction = $DB->start_delegated_transaction();
                    $archiverecord = clone $transaction;
                    unset($archiverecord->id); // Cho DB tự động cấp ID mới.
                    $archiverecord->timearchived = time();
                    $DB->insert_record('enrol_sepay_archive', $archiverecord);
                    $DB->delete_records('enrol_sepay_transactions', ['id' => $transaction->id]);
                    $dbtransaction->allow_commit();
                    $archived++;
                } catch (\Exception $e) {
                    // Start_delegated_transaction rollback tự động khi exception được throw.
                    mtrace('Lỗi khi lưu trữ giao dịch ID ' . $transaction->id . ': ' . $e->getMessage());
                }
            } else {
                // Xóa vĩnh viễn.
                try {
                    $DB->delete_records('enrol_sepay_transactions', ['id' => $transaction->id]);
                    $deleted++;
                } catch (\Exception $e) {
                    mtrace('Lỗi khi xóa giao dịch ID ' . $transaction->id . ': ' . $e->getMessage());
                }
            }
        }

        if ($strategy === 'archive') {
            mtrace('Đã lưu trữ thành công ' . $archived . ' giao dịch.');
        } else {
            mtrace('Đã xóa thành công ' . $deleted . ' giao dịch.');
        }

        mtrace('Hoàn thành dọn dẹp giao dịch SePay.');

        // Bước 2: Dọn dẹp bảng lưu trữ (dọn dẹp 2 tầng).
        if ($strategy === 'archive') {
            $this->cleanup_archive_table();
        }
    }

    /**
     * Dọn dẹp bản ghi cũ trong bảng lưu trữ.
     * Đây là tầng thứ 2 của quá trình dọn dẹp.
     */
    protected function cleanup_archive_table() {
        global $DB;

        mtrace('');
        mtrace('Bắt đầu dọn dẹp bảng lưu trữ...');

        // Lấy thời gian lưu trữ bản lưu.
        $archiveretentiondays = get_config('enrol_sepay', 'archive_retention_days');

        // Nếu giá trị là 0 hoặc rỗng, giữ lưu trữ vô thời hạn.
        if (empty($archiveretentiondays) || $archiveretentiondays < 1) {
            mtrace('Lưu trữ vô thời hạn. Bỏ qua dọn dẹp bảng lưu trữ.');
            return;
        }

        // Tính mốc giới hạn cho bảng lưu trữ.
        $archivecutoff = time() - ($archiveretentiondays * 86400);

        mtrace('Thời gian lưu trữ bản lưu: ' . $archiveretentiondays . ' ngày');
        mtrace('Mốc giới hạn bản lưu: ' . userdate($archivecutoff));

        // Tìm giao dịch lưu trữ cũ.
        $sql = "SELECT COUNT(*)
                  FROM {enrol_sepay_archive}
                 WHERE timearchived < :cutoff";

        $count = $DB->count_records_sql($sql, ['cutoff' => $archivecutoff]);

        if ($count == 0) {
            mtrace('Không tìm thấy giao dịch lưu trữ cũ cần xóa.');
            return;
        }

        mtrace('Tìm thấy ' . $count . ' giao dịch lưu trữ cũ cần xóa vĩnh viễn.');

        // Xóa giao dịch lưu trữ cũ.
        try {
            $DB->execute(
                "DELETE FROM {enrol_sepay_archive}
                  WHERE timearchived < :cutoff",
                ['cutoff' => $archivecutoff]
            );
            mtrace('Đã xóa vĩnh viễn ' . $count . ' giao dịch lưu trữ.');
        } catch (\Exception $e) {
            mtrace('Lỗi khi xóa giao dịch lưu trữ: ' . $e->getMessage());
        }

        mtrace('Hoàn thành dọn dẹp bảng lưu trữ.');
    }
}
