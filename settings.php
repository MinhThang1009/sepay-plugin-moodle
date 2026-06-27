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
 * Cài đặt và cấu hình hiện tại của plugin enrol_sepay.
 *
 * @package    enrol_sepay
 * @copyright  2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Thêm trang quản lý giao dịch vào menu Enrolments, ngay cạnh trang cài đặt.
$ADMIN->add('enrolments', new admin_externalpage(
    'enrol_sepay_transactions',
    get_string('manage_transactions', 'enrol_sepay'),
    $CFG->wwwroot . '/enrol/sepay/transactions.php',
    // Capability phải khớp với require_capability trong transactions.php.
    'enrol/sepay:manage'
));

// Thêm trang cài đặt thông báo vào menu Enrolments.
$ADMIN->add('enrolments', new admin_externalpage(
    'enrol_sepay_notification_settings',
    get_string('notification_settings', 'enrol_sepay'),
    $CFG->wwwroot . '/enrol/sepay/notification_settings.php',
    'enrol/sepay:manage'
));

if ($ADMIN->fulltree) {
    // Mô tả plugin ở đầu trang settings, giống PayPal.
    $settings->add(new admin_setting_heading(
        'enrol_sepay_pluginname',
        '',
        get_string('pluginname_desc', 'enrol_sepay')
    ));

    // Trường nhập API Key — dùng password field để không hiển thị plaintext.
    $settings->add(new admin_setting_configpasswordunmask(
        'enrol_sepay/apikey',
        get_string('apikey', 'enrol_sepay'),
        get_string('apikey_desc', 'enrol_sepay'),
        ''
    ));

    // PATTERN
    $settings->add(new admin_setting_configtext(
        'enrol_sepay/pattern',
        get_string('pattern', 'enrol_sepay'),
        get_string('pattern_desc', 'enrol_sepay'),
        '',
        PARAM_TEXT
    ));

    // SEPARATOR
    $settings->add(new admin_setting_configtext(
        'enrol_sepay/separator',
        get_string('separator', 'enrol_sepay'),
        get_string('separator_desc', 'enrol_sepay'),
        '',
        PARAM_TEXT
    ));

    // Account (tài khoản nhận thanh toán) - phần cấu hình tổng, giống "business email" của PayPal.
    $settings->add(new admin_setting_configtext(
        'enrol_sepay/account',
        get_string('account', 'enrol_sepay'),
        get_string('account_desc', 'enrol_sepay'),
        '',
        PARAM_TEXT
    ));

    // Ngân hàng (dữ liệu được tải thông qua scheduled task enrol_sepay\task\update_banks)
    $bank_options = [];
    if ($cached_response = get_config('enrol_sepay', 'bank_list_json')) {
        $bank_api_response = json_decode($cached_response, true);
        if (is_array($bank_api_response) && !empty($bank_api_response['data']) && is_array($bank_api_response['data'])) {
            foreach ($bank_api_response['data'] as $bank) {
                if (empty($bank['supported'])) {
                    continue;
                }
                $shortname = $bank['short_name'] ?? '';
                $fullname  = $bank['name'] ?? '';
                if ($shortname === '') {
                    continue;
                }
                $bank_options[$shortname] = $shortname . ' - ' . $fullname;
            }
        }
    }

    // Nếu vì lý do nào đó không lấy được danh sách ngân hàng, vẫn nên có ít nhất một lựa chọn mặc định.
    if (empty($bank_options)) {
        $bank_options['MBBank'] = 'MBBank';
    }

    $settings->add(new admin_setting_configselect(
        'enrol_sepay/bank',
        get_string('bank', 'enrol_sepay'),
        get_string('bank_desc', 'enrol_sepay'),
        'MBBank',
        $bank_options
    ));

    // Template hiển thị
    $settings->add(new admin_setting_configselect(
        'enrol_sepay/template',
        get_string('template', 'enrol_sepay'),
        get_string('template_desc', 'enrol_sepay'),
        'compact',
        [
            'compact' => get_string('setting_template_compact', 'enrol_sepay'),
            '' => get_string('setting_template_default', 'enrol_sepay'),
            'qronly' => get_string('setting_template_qronly', 'enrol_sepay'),
        ]
    ));


    $settings->add(new admin_setting_configcheckbox(
        'enrol_sepay/mailstudents',
        get_string('mailstudents', 'enrol_sepay'),
        '',
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'enrol_sepay/mailteachers',
        get_string('mailteachers', 'enrol_sepay'),
        '',
        0
    ));

    $settings->add(new admin_setting_configcheckbox(
        'enrol_sepay/mailadmins',
        get_string('mailadmins', 'enrol_sepay'),
        '',
        0
    ));


    // Cài đặt mặc định cho instance mới
    $settings->add(new admin_setting_heading(
        'enrol_sepay_defaults',
        get_string('enrolinstancedefaults', 'admin'),
        get_string('enrolinstancedefaults_desc', 'admin')
    ));

    $options = [
        ENROL_INSTANCE_ENABLED  => get_string('yes'),
        ENROL_INSTANCE_DISABLED => get_string('no'),
    ];
    $settings->add(new admin_setting_configselect(
        'enrol_sepay/status',
        get_string('status', 'enrol_sepay'),
        get_string('status_desc', 'enrol_sepay'),
        ENROL_INSTANCE_DISABLED,
        $options
    ));

    // Duyệt thủ công (Manual Enrolment) - giá trị mặc định cho instance mới
    $settings->add(new admin_setting_configselect(
        'enrol_sepay/manual_enrol',
        get_string('manual_enrol', 'enrol_sepay'),
        get_string('manual_enrol_desc', 'enrol_sepay'),
        0,
        [
            0 => get_string('no'),
            1 => get_string('yes'),
        ]
    ));

    // Giá mặc định nếu admin không đặt ở mỗi khóa học
    $settings->add(new admin_setting_configtext(
        'enrol_sepay/cost',
        get_string('defaultcost', 'enrol_sepay'),
        '',
        0,
        PARAM_INT,
        10
    ));

    // Currency: đơn vị tiền tệ hiển thị trong form ghi danh (mặc định VND),
    // đặt trong nhóm defaults để UI giống với PayPal, nhưng dùng select để tránh nhập sai.
    $currencyoptions = ['VND' => 'VND'];
    $settings->add(new admin_setting_configselect(
        'enrol_sepay/currency',
        get_string('currency', 'enrol_sepay'),
        '',
        'VND',
        $currencyoptions
    ));

    if (!during_initial_install()) {
        $options = get_default_enrol_roles(context_system::instance());
        $student = get_archetype_roles('student');
        $student = reset($student);
        $settings->add(new admin_setting_configselect(
            'enrol_sepay/roleid',
            get_string('defaultrole', 'enrol_sepay'),
            get_string('defaultrole_desc', 'enrol_sepay'),
            $student->id ?? null,
            $options
        ));
    }

    $settings->add(new admin_setting_configduration(
        'enrol_sepay/enrolperiod',
        get_string('enrolperiod', 'enrol_sepay'),
        get_string('enrolperiod_desc', 'enrol_sepay'),
        0
    ));

    // Hành động khi ghi danh hết hạn - giống PayPal.
    $options = [
        ENROL_EXT_REMOVED_KEEP           => get_string('extremovedkeep', 'enrol'),
        ENROL_EXT_REMOVED_SUSPEND        => get_string('extremovedsuspend', 'enrol'),
        ENROL_EXT_REMOVED_SUSPENDNOROLES => get_string('extremovedsuspendnoroles', 'enrol'),
        ENROL_EXT_REMOVED_UNENROL        => get_string('extremovedunenrol', 'enrol'),
    ];
    $settings->add(new admin_setting_configselect(
        'enrol_sepay/expiredaction',
        get_string('expiredaction', 'enrol_sepay'),
        get_string('expiredaction_help', 'enrol_sepay'),
        ENROL_EXT_REMOVED_SUSPENDNOROLES,
        $options
    ));

    // Cài đặt lưu trữ dữ liệu
    $settings->add(new admin_setting_heading(
        'enrol_sepay_data_retention',
        get_string('data_retention_heading', 'enrol_sepay'),
        get_string('data_retention_heading_desc', 'enrol_sepay')
    ));

    // Bật/tắt dọn dẹp tự động
    $settings->add(new admin_setting_configselect(
        'enrol_sepay/auto_cleanup_enabled',
        get_string('auto_cleanup_enabled', 'enrol_sepay'),
        get_string('auto_cleanup_enabled_desc', 'enrol_sepay'),
        1,
        [
            0 => get_string('no'),
            1 => get_string('yes'),
        ]
    ));

    // Thời gian lưu trữ tính bằng ngày
    $settings->add(new admin_setting_configtext(
        'enrol_sepay/retention_days',
        get_string('retention_days', 'enrol_sepay'),
        get_string('retention_days_desc', 'enrol_sepay'),
        365,
        PARAM_INT
    ));

    // Chiến lược lưu trữ
    $settings->add(new admin_setting_configselect(
        'enrol_sepay/archive_strategy',
        get_string('archive_strategy', 'enrol_sepay'),
        get_string('archive_strategy_desc', 'enrol_sepay'),
        'archive',
        [
            'archive' => get_string('archive_strategy_archive', 'enrol_sepay'),
            'delete' => get_string('archive_strategy_delete', 'enrol_sepay'),
        ]
    ));

    // Thời gian lưu trữ bản lưu (chỉ áp dụng khi chiến lược là 'archive')
    $settings->add(new admin_setting_configtext(
        'enrol_sepay/archive_retention_days',
        get_string('archive_retention_days', 'enrol_sepay'),
        get_string('archive_retention_days_desc', 'enrol_sepay'),
        365,
        PARAM_INT
    ));

    // Thời gian giữ giao dịch chờ xử lý trước khi tự động từ chối.
    $settings->add(new admin_setting_configtext(
        'enrol_sepay/pending_retention_days',
        get_string('pending_retention_days', 'enrol_sepay'),
        get_string('pending_retention_days_desc', 'enrol_sepay'),
        30,
        PARAM_INT
    ));
}
