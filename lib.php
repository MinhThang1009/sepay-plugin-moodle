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
 * Các hàm chức năng cho plugin enrol_sepay.
 *
 * @package   enrol_sepay
 * @copyright 2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// phpcs:disable moodle.Commenting.InlineComment.NotCapital -- Comment tiếng Việt; sniff chỉ chấp nhận chữ hoa ASCII (Đ/Ư... bị coi là thường).

/**
 * Plugin ghi danh khóa học qua thanh toán chuyển khoản QR SePay.
 */
class enrol_sepay_plugin extends enrol_plugin {
    /**
     * Trả về icon hiển thị trong danh sách khóa học.
     *
     * Dùng để hiển thị tổng quan các tùy chọn ghi danh trong danh sách khóa học.
     *
     * @param array $instances tất cả instance enrol cùng loại trong 1 khóa học
     * @return array mảng pix_icon
     */
    public function get_info_icons(array $instances) {
        $found = false;
        foreach ($instances as $instance) {
            if ($instance->enrolstartdate != 0 && $instance->enrolstartdate > time()) {
                continue;
            }
            if ($instance->enrolenddate != 0 && $instance->enrolenddate < time()) {
                continue;
            }
            $found = true;
            break;
        }
        if ($found) {
            return [new pix_icon('icon', get_string('pluginname', 'enrol_sepay'), 'enrol_sepay')];
        }
        return [];
    }

    /**
     * Trả về true nếu các role do plugin gán được bảo vệ khỏi chỉnh sửa thủ công.
     *
     * @return bool false vì user có quyền gán role có thể thay đổi role sau
     */
    public function roles_protected() {
        // User có quyền gán role có thể thay đổi role sau.
        return false;
    }

    /**
     * Trả về true nếu cho phép gỡ ghi danh thủ công cho instance này.
     *
     * @param stdClass $instance instance ghi danh cần kiểm tra
     * @return bool true nếu cho phép gỡ ghi danh
     */
    public function allow_unenrol(stdClass $instance) {
        // User có quyền unenrol có thể gỡ ghi danh thủ công - cần enrol/sepay:unenrol.
        return true;
    }

    /**
     * Trả về true nếu cho phép quản lý ghi danh thủ công cho instance này.
     *
     * @param stdClass $instance instance ghi danh cần kiểm tra
     * @return bool true nếu cho phép quản lý
     */
    public function allow_manage(stdClass $instance) {
        // User có quyền manage có thể thay đổi thời hạn và trạng thái - cần enrol/sepay:manage.
        return true;
    }

    /**
     * Trả về true nếu nên hiển thị link tự ghi danh cho instance này.
     *
     * @param stdClass $instance instance ghi danh cần kiểm tra
     * @return bool true nếu instance đang được bật
     */
    public function show_enrolme_link(stdClass $instance) {
        return ($instance->status == ENROL_INSTANCE_ENABLED);
    }

    /**
     * Trả về URL để user tự hủy ghi danh (unenrolself), giống pattern của enrol_paypal.
     * Nếu không cho phép tự hủy ghi danh sẽ trả về null.
     *
     * @param stdClass $instance
     * @return moodle_url|null
     */
    public function get_unenrolself_link($instance) {
        global $USER;

        $context = context_course::instance($instance->courseid, MUST_EXIST);

        // Nếu user không có quyền unenrolself hoặc không đang được ghi danh thì không hiển thị link.
        if (
            !has_capability('enrol/sepay:unenrolself', $context, $USER) ||
            !is_enrolled($context, $USER)
        ) {
            return null;
        }

        // Trả về URL tới trang unenrolself của SePay.
        return new moodle_url('/enrol/sepay/unenrolself.php', ['enrolid' => $instance->id]);
    }

    /**
     * Kiểm tra user có thể thêm instance mới trong khóa học này không.
     * @param int $courseid
     * @return boolean
     */
    public function can_add_instance($courseid) {
        $context = context_course::instance($courseid, MUST_EXIST);

        if (!has_capability('moodle/course:enrolconfig', $context) || !has_capability('enrol/sepay:config', $context)) {
            return false;
        }

        // Hỗ trợ nhiều instance - giá khác nhau cho các role khác nhau.
        return true;
    }

    /**
     * Dùng UI/validation code path chuẩn của Moodle.
     *
     * @return boolean
     */
    public function use_standard_editing_ui() {
        return true;
    }

    /**
     * Khai báo các bulk operation hỗ trợ trên trang participants.
     *
     * Không có method này thì action "Xóa đăng kí" của plugin manual sẽ bỏ qua
     * mọi user ghi danh bằng SePay (vì họ không có manual enrolment) → bắn hàng
     * loạt "đã bị di chuyển khỏi lựa chọn" rồi báo "Chưa có ai được chọn".
     *
     * @param course_enrolment_manager $manager
     * @return array mảng các enrol_bulk_enrolment_operation theo identifier
     */
    public function get_bulk_operations(course_enrolment_manager $manager) {
        global $CFG;
        require_once($CFG->dirroot . '/enrol/sepay/locallib.php');
        $context = $manager->get_context();
        $bulkoperations = [];
        if (has_capability("enrol/sepay:manage", $context)) {
            $bulkoperations['editselectedusers'] = new enrol_sepay_editselectedusers_operation($manager, $this);
        }
        if (has_capability("enrol/sepay:unenrol", $context)) {
            $bulkoperations['deleteselectedusers'] = new enrol_sepay_deleteselectedusers_operation($manager, $this);
        }
        return $bulkoperations;
    }

    /**
     * Thêm instance mới của plugin enrol.
     *
     * @param object $course
     * @param array|null $fields các trường của instance
     * @return int id của instance mới, null nếu không tạo được
     */
    public function add_instance($course, ?array $fields = null) {
        if ($fields && !empty($fields['cost'])) {
            $fields['cost'] = unformat_float($fields['cost']);
        }
        return parent::add_instance($course, $fields);
    }

    /**
     * Cập nhật instance của plugin enrol.
     * @param stdClass $instance
     * @param stdClass $data các trường đã thay đổi
     * @return boolean
     */
    public function update_instance($instance, $data) {
        if ($data) {
            $data->cost = unformat_float($data->cost);
        }
        return parent::update_instance($instance, $data);
    }

    /**
     * Tạo form ghi danh, kiểm tra form đã gửi chưa,
     * và ghi danh user nếu cần. Có thể chuyển hướng.
     *
     * @param stdClass $instance Dữ liệu instance ghi danh.
     * @return string Nội dung HTML, thường là form trong hộp văn bản.
     */
    public function enrol_page_hook(stdClass $instance) {
        global $CFG, $USER, $OUTPUT, $PAGE, $DB;

        // Bắt đầu output buffering để lưu nội dung xuất ra.
        ob_start();

        // Kiểm tra user đã ghi danh chưa.
        if ($DB->record_exists('user_enrolments', ['userid' => $USER->id, 'enrolid' => $instance->id])) {
            return ob_get_clean(); // User đã ghi danh, dọn buffer và trả về.
        }

        // Kiểm tra ngày bắt đầu ghi danh đã đến chưa.
        if ($instance->enrolstartdate != 0 && $instance->enrolstartdate > time()) {
            return ob_get_clean(); // Chưa đến ngày ghi danh.
        }

        // Kiểm tra ngày kết thúc ghi danh đã qua chưa.
        if ($instance->enrolenddate != 0 && $instance->enrolenddate < time()) {
            return ob_get_clean(); // Hết hạn ghi danh.
        }

        // Lấy thông tin khóa học từ database.
        $course = $DB->get_record('course', ['id' => $instance->courseid], '*', IGNORE_MISSING);
        if (!$course) {
            return ob_get_clean();
        }

        // Xác định giá ghi danh.
        if ((float)$instance->cost <= 0) {
            $cost = (float)$this->get_config('cost'); // Dùng giá mặc định nếu instance không cấu hình.
        } else {
            $cost = (float)$instance->cost; // Dùng giá của instance.
        }

        // Xác định loại tiền tệ: ưu tiên theo thiết lập cấp instance, fallback về cấu hình global (mặc định VND).
        $currency = !empty($instance->currency) ? $instance->currency : ($this->get_config('currency') ?: 'VND');

        // Kiểm tra giá có quá nhỏ không (gần như miễn phí).
        // Nếu giá = 0 và cài đặt toàn cục cũng rỗng/null thì trả về trống — tránh vô tình cho học viên vào học miễn phí.
        if ($cost < 1) {
            if ($cost <= 0 && (float)$this->get_config('cost') <= 0) {
                return ob_get_clean(); // Chưa cấu hình giá (cả instance lẫn global) — không hiển thị gì.
            }
            // Không cấu hình giá hoặc giá quá nhỏ thì không hiển thị form thanh toán.
            echo '<p>' . get_string('nocost', 'enrol_sepay') . '</p>'; // Thông báo không cần thanh toán.
        } else {
            // Định dạng hiển thị giá tiền với loại tiền tệ đã xác định ở trên.
            $localisedcost = number_format($cost, 0, '.', ',') . ' ' . $currency;

            // Kiểm tra user là khách (guest).
            if (isguestuser()) {
                $wwwroot = $CFG->wwwroot; // URL trang web để chuyển hướng.
                // Thông báo cho khách về yêu cầu thanh toán và cung cấp link đăng nhập.
                echo '<div class="mdl-align"><p>' . get_string('paymentrequired') . '</p>';
                // Hiển thị chi phí với currency giống UI PayPal.
                echo '<p><b>' . get_string('cost', 'enrol_sepay') . ': ' . $localisedcost . '</b></p>';
                echo '<p><a href="' . $wwwroot . '/login/">' . get_string('loginsite') . '</a></p>';
                echo '</div>';
            } else {
                // Chuẩn bị dữ liệu cho form SePay.

                $qraccount = $this->get_config('account');
                $qrbank = $this->get_config('bank');
                $qrtemplate = $this->get_config('template');
                // Mẫu nội dung do Admin cấu hình: [pattern] + [courseid] + [separator] + [userid].
                // Lặp lại $qrpattern ở cuối làm terminator: khi ngân hàng ghép thêm số vào sau
                // (ví dụ timestamp "050526 23 03"), regex (\d+) dừng ngay tại chữ cái đầu của
                // pattern thay vì bắt luôn cả chuỗi số đó.
                $qrpattern = trim((string)$this->get_config('pattern', 'sepay'));
                $qrseparator = trim((string)$this->get_config('separator', 'sepay'));
                $qrcontent = $qrpattern . $course->id . trim($qrseparator) . $USER->id;

                // Kiểm tra xem user đã có transaction chưa; lấy mới nhất nếu có nhiều.
                $txnpending = $DB->get_records('enrol_sepay_transactions', [
                    'userid' => $USER->id, 'courseid' => $course->id, 'status' => 'pending',
                ], 'timecreated DESC', '*', 0, 1);
                $pendingtransaction = $txnpending ? reset($txnpending) : null;

                $txnprocessed = $DB->get_records('enrol_sepay_transactions', [
                    'userid' => $USER->id, 'courseid' => $course->id, 'status' => 'processed',
                ], 'timecreated DESC', '*', 0, 1);
                $processedtransaction = $txnprocessed ? reset($txnprocessed) : null;

                $txnrejected = $DB->get_records('enrol_sepay_transactions', [
                    'userid' => $USER->id, 'courseid' => $course->id, 'status' => 'rejected',
                ], 'timecreated DESC', '*', 0, 1);
                $rejectedtransaction = $txnrejected ? reset($txnrejected) : null;

                // Nếu đã có transaction pending hoặc processed, hiển thị thông báo thay vì QR code.
                if ($pendingtransaction) {
                    echo '<div class="alert alert-info sepay-alert-center">';
                    echo '<i class="fa-regular fa-clock sepay-status-icon sepay-icon-info"></i><br>';
                    echo '<strong>' . get_string('payment_pending_title', 'enrol_sepay') . '</strong><br>';
                    echo get_string('payment_pending_message', 'enrol_sepay');
                    echo '</div>';

                    // Thêm JavaScript để kiểm tra liên tục và reload lại trang khi admin phê duyệt/từ chối thông qua AMD.
                    $PAGE->requires->js_call_amd('enrol_sepay/payment_poll', 'init', [$course->id, 'pending']);
                } else if ($processedtransaction) {
                    // Kiểm tra xem instance có bật manual enrollment không.
                    // Giá trị customint1: 0 là default, 1 là manual, 2 là auto.
                    $instancemanual = $instance->customint1;
                    $globalmanual = $this->get_config('manual_enrol');

                    // Xác định xem có phải manual enrollment không.
                    $ismanualenrollment = false;
                    if ($instancemanual == 1) {
                        // Instance bật manual.
                        $ismanualenrollment = true;
                    } else if ($instancemanual == 2) {
                        // Instance tắt manual (auto).
                        $ismanualenrollment = false;
                    } else {
                        // Instance theo default → check global setting.
                        $ismanualenrollment = (bool)$globalmanual;
                    }

                    // Chọn string phù hợp
                    // Nếu là manual enrollment → "Xác nhận phê duyệt thành công!"
                    // Nếu là auto enrollment → "Xác nhận thanh toán thành công!".
                    $titlestring = $ismanualenrollment ? 'payment_approved_title' : 'payment_auto_approved_title';
                    $messagestring = $ismanualenrollment ? 'payment_approved_message' : 'payment_auto_approved_message';

                    echo '<div class="alert alert-success sepay-alert-center">';
                    echo '<i class="fa-regular fa-circle-check sepay-status-icon sepay-icon-success"></i><br>';
                    echo '<strong>' . get_string($titlestring, 'enrol_sepay') . '</strong><br>';
                    echo get_string($messagestring, 'enrol_sepay');
                    echo '<br><small class="text-muted">'
                        . get_string('redirecting_in', 'enrol_sepay')
                        . ' <span id="countdown">5</span> '
                        . get_string('seconds', 'enrol_sepay')
                        . '...</small>';
                    echo '</div>';

                    // Tự động chuyển hướng kèm đếm ngược - Chuyển sang complete_enrol.php để ghi danh user.
                    $enrolurl = new moodle_url('/enrol/sepay/complete_enrol.php', ['id' => $course->id, 'sesskey' => sesskey()]);
                    $PAGE->requires->js_call_amd('enrol_sepay/payment_countdown', 'init', [$course->id, $enrolurl->out(false)]);
                } else if ($rejectedtransaction) {
                    echo '<div class="alert alert-danger sepay-alert-center">';
                    echo '<i class="fa-regular fa-circle-xmark sepay-status-icon sepay-icon-danger"></i><br>';
                    echo '<strong>' . get_string('payment_rejected_title', 'enrol_sepay') . '</strong><br>';
                    echo get_string('payment_rejected_message', 'enrol_sepay');
                    echo '<div class="mt-3">';

                    // CSS cho nút nguy hiểm đã được định nghĩa trong styles.css (.sepay-danger-btn).

                    // Nút Thanh toán lại.
                    $retryurl = new moodle_url('/enrol/sepay/retry.php', [
                        'id' => $course->id,
                        'instance' => $instance->id,
                        'sesskey' => sesskey(),
                    ]);
                    echo '<a href="' . $retryurl . '" class="btn sepay-danger-btn mr-2 mb-2">';
                    echo get_string('retry_payment', 'enrol_sepay');
                    echo '</a>';

                    // Nút Liên hệ Admin.
                    $contacturl = (new moodle_url('/message/index.php', ['id' => get_admin()->id]))->out(false);
                    echo '<a href="' . $contacturl . '" target="_blank" class="btn sepay-danger-btn mr-2 mb-2">';
                    echo get_string('contact_admin', 'enrol_sepay');
                    echo '</a>';

                    // Nút Quay lại.
                    $courseurl = new moodle_url('/course/view.php', ['id' => $course->id]);
                    echo '<a href="' . $courseurl . '" class="btn sepay-danger-btn mb-2">';
                    echo get_string('back_to_course', 'enrol_sepay');
                    echo '</a>';

                    echo '</div>';
                    echo '</div>';
                } else {
                    // Chỉ hiển thị QR code khi chưa có giao dịch.
                    $data = [
                        'qr_account' => $qraccount,
                        'qr_bank' => $qrbank,
                        'cost' => $cost,
                        'qr_content' => $qrcontent,
                        'qr_template' => $qrtemplate,
                        'localisedcost' => $localisedcost,
                    ];
                    echo $OUTPUT->render_from_template('enrol_sepay/enrol', $data);

                    // Kích hoạt chuẩn AMD JS xử lý front-end giao diện QR.
                    $PAGE->requires->js_call_amd('enrol_sepay/payment_actions', 'init', [$course->id, $CFG->wwwroot]);
                    $PAGE->requires->js_call_amd('enrol_sepay/payment_poll', 'init', [$course->id, 'none']);
                }
            }
        }

        // Trả về nội dung đã lưu bên trong hộp.
        return $OUTPUT->box(ob_get_clean());
    }

    /**
     * Khôi phục instance và ánh xạ cài đặt.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $course
     * @param int $oldid
     */
    public function restore_instance(restore_enrolments_structure_step $step, stdClass $data, $course, $oldid) {
        global $DB;
        if ($step->get_task()->get_target() == backup::TARGET_NEW_COURSE) {
            $merge = false;
        } else {
            $merge = [
                'courseid'   => $data->courseid,
                'enrol'      => $this->get_name(),
                'roleid'     => $data->roleid,
                'cost'       => $data->cost,
                'currency'   => $data->currency,
            ];
        }
        if ($merge && $instances = $DB->get_records('enrol', $merge, 'id')) {
            $instance = reset($instances);
            $instanceid = $instance->id;
        } else {
            $instanceid = $this->add_instance($course, (array)$data);
        }
        $step->set_mapping('enrol', $oldid, $instanceid);
    }

    /**
     * Khôi phục ghi danh của user.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $instance
     * @param int $userid
     * @param int $oldinstancestatus
     */
    public function restore_user_enrolment(restore_enrolments_structure_step $step, $data, $instance, $userid, $oldinstancestatus) {
        $this->enrol_user($instance, $userid, null, $data->timestart, $data->timeend, $data->status);
    }

    /**
     * Trả về mảng các giá trị hợp lệ cho trạng thái.
     *
     * @return array
     */
    protected function get_status_options() {
        $options = [
            ENROL_INSTANCE_ENABLED  => get_string('yes'),
            ENROL_INSTANCE_DISABLED => get_string('no'),
        ];
        return $options;
    }

    /**
     * Trả về mảng các giá trị hợp lệ cho roleid.
     *
     * @param stdClass $instance
     * @param context $context
     * @return array
     */
    protected function get_roleid_options($instance, $context) {
        if ($instance->id) {
            $roles = get_default_enrol_roles($context, $instance->roleid);
        } else {
            $roles = get_default_enrol_roles($context, $this->get_config('roleid'));
        }
        return $roles;
    }


    /**
     * Thêm các phần tử vào form chỉnh sửa instance.
     *
     * @param stdClass $instance
     * @param MoodleQuickForm $mform
     * @param context $context
     * @return void
     */
    public function edit_instance_form($instance, MoodleQuickForm $mform, $context) {
        // Tên instance có chỉnh sửa (giống PayPal).
        $mform->addElement('text', 'name', get_string('custominstancename', 'enrol'));
        $mform->setType('name', PARAM_TEXT);

        // Cho phép các đăng ký của SePay.
        $options = $this->get_status_options();
        $mform->addElement('select', 'status', get_string('status', 'enrol_sepay'), $options);
        $mform->setDefault('status', $this->get_config('status'));

        // Duyệt thủ công cấp instance (customint1).
        $manualoptions = [
            0 => get_string('manual_enrol_default', 'enrol_sepay'),
            1 => get_string('manual_enrol_yes', 'enrol_sepay'),
            2 => get_string('manual_enrol_no', 'enrol_sepay'),
        ];
        $mform->addElement('select', 'customint1', get_string('manual_enrol_instance', 'enrol_sepay'), $manualoptions);
        $mform->setDefault('customint1', 0);
        $mform->addHelpButton('customint1', 'manual_enrol_instance', 'enrol_sepay');

        // Đăng kí giá (Enrol cost) với kích thước input giống PayPal.
        $mform->addElement('text', 'cost', get_string('cost', 'enrol_sepay'), ['size' => 4]);
        $mform->setType('cost', PARAM_RAW);
        $mform->setDefault('cost', $this->get_config('cost'));

        // Đơn vị tiền tệ (Currency) - chọn từ danh sách, mặc định theo cấu hình plugin.
        $currencyoptions = ['VND' => 'VND'];
        $mform->addElement('select', 'currency', get_string('currency', 'enrol_sepay'), $currencyoptions);
        $mform->setDefault('currency', $this->get_config('currency'));

        // Vai trò mặc định khi cấp quyền ghi danh.
        $roles = $this->get_roleid_options($instance, $context);
        $mform->addElement('select', 'roleid', get_string('assignrole', 'enrol_sepay'), $roles);
        $mform->setDefault('roleid', $this->get_config('roleid'));

        // Thời hạn ghi danh.
        $options = ['optional' => true, 'defaultunit' => 86400];
        $mform->addElement('duration', 'enrolperiod', get_string('enrolperiod', 'enrol_sepay'), $options);
        $mform->setDefault('enrolperiod', $this->get_config('enrolperiod'));
        $mform->addHelpButton('enrolperiod', 'enrolperiod', 'enrol_sepay');

        // Ngày bắt đầu và kết thúc thời gian ghi danh.
        $options = ['optional' => true];
        $mform->addElement('date_time_selector', 'enrolstartdate', get_string('enrolstartdate', 'enrol_sepay'), $options);
        $mform->setDefault('enrolstartdate', 0);
        $mform->addHelpButton('enrolstartdate', 'enrolstartdate', 'enrol_sepay');

        $options = ['optional' => true];
        $mform->addElement('date_time_selector', 'enrolenddate', get_string('enrolenddate', 'enrol_sepay'), $options);
        $mform->setDefault('enrolenddate', 0);
        $mform->addHelpButton('enrolenddate', 'enrolenddate', 'enrol_sepay');

        if (enrol_accessing_via_instance($instance)) {
            $warningtext = get_string('instanceeditselfwarningtext', 'core_enrol');
            $mform->addElement('static', 'selfwarn', get_string('instanceeditselfwarning', 'core_enrol'), $warningtext);
        }
    }

    /**
     * Kiểm tra dữ liệu khi chỉnh sửa instance.
     *
     * @param array $data mảng ("fieldname"=>value) dữ liệu đã gửi
     * @param array $files mảng file upload "element_name"=>tmp_file_path
     * @param object $instance Instance được load từ DB
     * @param context $context Context của instance đang chỉnh sửa
     * @return array mảng "element_name"=>"error_description" nếu có lỗi,
     *         hoặc mảng rỗng nếu không có lỗi.
     */
    public function edit_instance_validation($data, $files, $instance, $context) {
        $errors = [];

        if (!empty($data['enrolenddate']) && $data['enrolenddate'] < $data['enrolstartdate']) {
            $errors['enrolenddate'] = get_string('enrolenddaterror', 'enrol_sepay');
        }

        $cost = str_replace(get_string('decsep', 'langconfig'), '.', $data['cost']);
        if (!is_numeric($cost)) {
            $errors['cost'] = get_string('costerror', 'enrol_sepay');
        } else if ((float)$cost < 0) {
            // Giá âm không hợp lệ (tránh bị coi là miễn phí ở enrol_page_hook).
            $errors['cost'] = get_string('costerror', 'enrol_sepay');
        }

        $validstatus = array_keys($this->get_status_options());
        $validroles = array_keys($this->get_roleid_options($instance, $context));
        $tovalidate = [
            'name' => PARAM_TEXT,
            'status' => $validstatus,
            'roleid' => $validroles,
            'customint1' => [0, 1, 2],
            'enrolperiod' => PARAM_INT,
            'enrolstartdate' => PARAM_INT,
            'enrolenddate' => PARAM_INT,
        ];

        $typeerrors = $this->validate_param_types($data, $tovalidate);
        $errors = array_merge($errors, $typeerrors);

        return $errors;
    }

    /**
     * Thực thi đồng bộ.
     * @param progress_trace $trace
     * @return int mã thoát, 0 là thành công
     */
    public function sync(progress_trace $trace) {
        $this->process_expirations($trace);
        return 0;
    }

    /**
     * Có thể xóa instance enrol qua giao diện chuẩn không?
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_delete_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/sepay:config', $context);
    }

    /**
     * Có thể ẩn/hiển instance enrol qua giao diện chuẩn không?
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_hide_show_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/sepay:config', $context);
    }

    /**
     * Xử lý cleanup transactions khi admin xóa instance.
     *
     * @param stdClass $instance
     */
    public function delete_instance($instance) {
        global $DB;
        // Mark pending transactions as rejected.
        $DB->set_field_select(
            'enrol_sepay_transactions',
            'status',
            'rejected',
            "instanceid = ? AND status = 'pending'",
            [$instance->id]
        );
        // Mark processed-but-not-enrolled transactions as rejected.
        $DB->execute(
            "UPDATE {enrol_sepay_transactions}
                SET status = 'rejected'
              WHERE instanceid = ?
                AND status = 'processed'
                AND userid NOT IN (
                    SELECT userid FROM {user_enrolments} WHERE enrolid = ?
                )",
            [$instance->id, $instance->id]
        );
        // Suppress rejection notifications for all records — course is being deleted.
        $DB->set_field_select(
            'enrol_sepay_transactions',
            'rejection_notified',
            1,
            "instanceid = ? AND status = 'rejected' AND rejection_notified = 0",
            [$instance->id]
        );
        parent::delete_instance($instance);
    }
}
