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
 * Các bulk operation cho plugin enrol_sepay (chỉnh sửa / hủy ghi danh hàng loạt).
 *
 * Mirror theo enrol/manual/locallib.php để trang participants có thể thao tác
 * hàng loạt trên các enrolment do SePay tạo (trước đây không có nên action của
 * plugin manual bỏ qua hết user SePay → "đã bị di chuyển khỏi lựa chọn").
 *
 * @package   enrol_sepay
 * @copyright 2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Hai bulk operation (sửa/hủy hàng loạt) ở cùng file, mirror enrol/manual.
// phpcs:disable PSR1.Classes.ClassDeclaration.MultipleClasses

require_once($CFG->dirroot . '/enrol/locallib.php');

/**
 * Bulk operation: chỉnh sửa trạng thái / thời hạn ghi danh cho nhiều user cùng lúc.
 *
 * @copyright 2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_sepay_editselectedusers_operation extends enrol_bulk_enrolment_operation {
    /**
     * Tiêu đề hiển thị cho bulk operation này.
     *
     * @return string
     */
    public function get_title() {
        return get_string('editselectedusers', 'enrol_sepay');
    }

    /**
     * Định danh của bulk operation — dùng làm key trong mảng get_bulk_operations().
     *
     * @return string
     */
    public function get_identifier() {
        return 'editselectedusers';
    }

    /**
     * Xử lý chỉnh sửa hàng loạt cho danh sách user với các thuộc tính từ form.
     *
     * @param course_enrolment_manager $manager
     * @param array $users danh sách user kèm enrolment của họ (đã lọc theo SePay)
     * @param stdClass $properties dữ liệu form trả về
     * @return bool
     */
    public function process(course_enrolment_manager $manager, array $users, stdClass $properties) {
        global $DB, $USER;

        if (!has_capability("enrol/sepay:manage", $manager->get_context())) {
            return false;
        }

        // Gom toàn bộ user_enrolment id cần cập nhật.
        $ueids = [];
        $instances = [];
        foreach ($users as $user) {
            foreach ($user->enrolments as $enrolment) {
                $ueids[] = $enrolment->id;
                if (!array_key_exists($enrolment->id, $instances)) {
                    $instances[$enrolment->id] = $enrolment;
                }
            }
        }

        // Kiểm tra từng instance có cho phép user hiện tại quản lý không.
        foreach ($instances as $instance) {
            if (!$this->plugin->allow_manage($instance)) {
                return false;
            }
        }

        // Lấy các thuộc tính đã biết.
        $status = $properties->status;
        $timestart = $properties->timestart;
        $timeend = $properties->timeend;

        [$ueidsql, $params] = $DB->get_in_or_equal($ueids, SQL_PARAMS_NAMED);

        $updatesql = [];
        if ($status == ENROL_USER_ACTIVE || $status == ENROL_USER_SUSPENDED) {
            $updatesql[] = 'status = :status';
            $params['status'] = (int)$status;
        }
        if (!empty($timestart)) {
            $updatesql[] = 'timestart = :timestart';
            $params['timestart'] = (int)$timestart;
        }
        if (!empty($timeend)) {
            $updatesql[] = 'timeend = :timeend';
            $params['timeend'] = (int)$timeend;
        }
        if (empty($updatesql)) {
            return true;
        }

        // Cập nhật người sửa.
        $updatesql[] = 'modifierid = :modifierid';
        $params['modifierid'] = (int)$USER->id;

        // Cập nhật thời điểm sửa.
        $updatesql[] = 'timemodified = :timemodified';
        $params['timemodified'] = time();

        // Dựng câu lệnh SQL.
        $updatesql = join(', ', $updatesql);
        $sql = "UPDATE {user_enrolments}
                   SET $updatesql
                 WHERE id $ueidsql";

        if ($DB->execute($sql, $params)) {
            foreach ($users as $user) {
                foreach ($user->enrolments as $enrolment) {
                    $enrolment->courseid = $enrolment->enrolmentinstance->courseid;
                    $enrolment->enrol = 'sepay';
                    // Bắn event.
                    $event = \core\event\user_enrolment_updated::create(
                        [
                                'objectid' => $enrolment->id,
                                'courseid' => $enrolment->courseid,
                                'context' => context_course::instance($enrolment->courseid),
                                'relateduserid' => $user->id,
                                'other' => ['enrol' => 'sepay'],
                                ]
                    );
                    $event->trigger();
                }
            }
            // Xóa cache course contacts vì có thể bị ảnh hưởng.
            cache::make('core', 'coursecontacts')->delete($manager->get_context()->instanceid);
            return true;
        }

        return false;
    }

    /**
     * Trả về form thu thập thông tin cần thiết cho operation này.
     *
     * @param string|moodle_url|null $defaultaction
     * @param mixed $defaultcustomdata
     * @return enrol_sepay_editselectedusers_form
     */
    public function get_form($defaultaction = null, $defaultcustomdata = null) {
        global $CFG;
        require_once($CFG->dirroot . '/enrol/sepay/bulkchangeforms.php');
        return new enrol_sepay_editselectedusers_form($defaultaction, $defaultcustomdata);
    }
}

/**
 * Bulk operation: hủy ghi danh (xóa user_enrolments) cho nhiều user cùng lúc.
 *
 * @copyright 2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_sepay_deleteselectedusers_operation extends enrol_bulk_enrolment_operation {
    /**
     * Định danh của bulk operation — dùng làm key trong mảng get_bulk_operations().
     *
     * @return string
     */
    public function get_identifier() {
        return 'deleteselectedusers';
    }

    /**
     * Tiêu đề hiển thị cho bulk operation này.
     *
     * @return string
     */
    public function get_title() {
        return get_string('deleteselectedusers', 'enrol_sepay');
    }

    /**
     * Trả về form xác nhận trước khi hủy ghi danh hàng loạt.
     *
     * @param string|moodle_url|null $defaultaction
     * @param mixed $defaultcustomdata
     * @return enrol_sepay_deleteselectedusers_form
     */
    public function get_form($defaultaction = null, $defaultcustomdata = null) {
        global $CFG;
        require_once($CFG->dirroot . '/enrol/sepay/bulkchangeforms.php');
        if (!is_array($defaultcustomdata)) {
            $defaultcustomdata = [];
        }
        $defaultcustomdata['title'] = $this->get_title();
        $defaultcustomdata['message'] = get_string('confirmbulkdeleteenrolment', 'enrol_sepay');
        $defaultcustomdata['button'] = get_string('unenrolusers', 'enrol_sepay');
        return new enrol_sepay_deleteselectedusers_form($defaultaction, $defaultcustomdata);
    }

    /**
     * Xử lý hủy ghi danh hàng loạt cho danh sách user.
     *
     * @param course_enrolment_manager $manager
     * @param array $users danh sách user kèm enrolment của họ (đã lọc theo SePay)
     * @param stdClass $properties dữ liệu form trả về
     * @return bool
     */
    public function process(course_enrolment_manager $manager, array $users, stdClass $properties) {
        if (!has_capability("enrol/sepay:unenrol", $manager->get_context())) {
            return false;
        }
        $counter = 0;
        foreach ($users as $user) {
            foreach ($user->enrolments as $enrolment) {
                $plugin = $enrolment->enrolmentplugin;
                $instance = $enrolment->enrolmentinstance;
                if ($plugin->allow_unenrol_user($instance, $enrolment)) {
                    // Try/catch per-iteration: 1 user lỗi không làm dừng cả batch còn lại.
                    try {
                        $plugin->unenrol_user($instance, $user->id);
                        $counter++;
                    } catch (\Exception $e) {
                        debugging('enrol_sepay bulk unenrol: thất bại cho user ' . $user->id . ': ' . $e->getMessage(), DEBUG_DEVELOPER);
                    }
                }
            }
        }
        // Hiển thị thông báo sau khi hủy ghi danh hàng loạt.
        if ($counter > 0) {
            \core\notification::info(get_string('totalunenrolledusers', 'enrol', $counter));
        }
        return true;
    }
}
