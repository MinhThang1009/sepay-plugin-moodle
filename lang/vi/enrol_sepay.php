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
 * Chuỗi ngôn ngữ cho plugin enrol_sepay, tiếng Việt.
 *
 * @package    enrol_sepay
 * @copyright  2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'SePay';
$string['pluginname_desc'] = 'Mô-đun SePay cho phép bạn thiết lập các khóa học trả phí. Nếu chi phí của bất kỳ khóa học nào bằng 0, học viên sẽ không cần thanh toán để tham gia. Bạn có thể đặt một mức giá toàn site làm mặc định cho toàn bộ hệ thống, sau đó mỗi khóa học có thể có một mức giá riêng. Giá của khóa học sẽ ghi đè giá mặc định của site.';

// Cấu hình chung plugin.
$string['enrolcost'] = 'Giá ghi danh';
$string['sepay:config'] = 'Cấu hình các instance ghi danh SePay';

$string['manual_enrol_instance'] = 'Phê duyệt thủ công';
$string['manual_enrol_instance_help'] = 'Ghi đè cấu hình toàn hệ thống về phê duyệt thủ công cho instance khóa học cụ thể này. Chọn "Theo mặc định hệ thống" để sử dụng cài đặt chung, "Bật" để luôn yêu cầu duyệt thủ công cho khóa học này, hoặc "Tắt" để luôn tự động ghi danh cho khóa học này.';
$string['manual_enrol_instance_desc'] = 'Ghi đè cấu hình chung về duyệt thủ công.';
$string['manual_enrol_default'] = 'Theo mặc định hệ thống';
$string['manual_enrol_yes'] = 'Bật';
$string['manual_enrol_no'] = 'Tắt';

$string['sepay:manage'] = 'Quản lý ghi danh SePay';
$string['sepay:unenrol'] = 'Hủy ghi danh người dùng qua SePay';
$string['sepay:unenrolself'] = 'Tự hủy ghi danh qua SePay';

$string['paywithsepay'] = 'Thanh toán qua SePay';
$string['sendpaymentbutton'] = 'Thanh toán ngay qua SePay';

$string['apikey'] = 'API Key';
$string['apikey_desc'] = 'API Key dùng để xác thực webhook gửi từ SePay.';

$string['secretkey'] = 'Secret Key';
$string['secretkey_desc'] = 'Secret Key dùng để ký hoặc xác minh dữ liệu giữa Moodle và SePay.';

$string['currency'] = 'Đơn vị tiền tệ';
$string['defaultcost'] = 'Giá ghi danh';

$string['account'] = 'Số tài khoản';
$string['account_desc'] = 'Số tài khoản ngân hàng hoặc số điện thoại nhận thanh toán.';

$string['bank'] = 'Ngân hàng';
$string['bank_desc'] = 'Ngân hàng nhận thanh toán.';

$string['template'] = 'Giao diện QR';
$string['template_desc'] = 'Mẫu hiển thị mã VietQR thanh toán.';

$string['setting_template_compact'] = 'Khung VietQR';
$string['setting_template_default'] = 'QR kèm logo VietQR';
$string['setting_template_qronly'] = 'Chỉ hiển thị mã QR';

$string['defaultrole'] = 'Vai trò mặc định';
$string['defaultrole_desc'] = 'Vai trò sẽ được gán cho người dùng khi ghi danh qua SePay.';

$string['enrolenddate'] = 'Ngày kết thúc';
$string['enrolenddate_help'] = 'Nếu được bật, người dùng chỉ có thể được ghi danh tới ngày này.';
$string['enrolenddaterror'] = 'Ngày kết thúc không thể sớm hơn ngày bắt đầu.';

$string['enrolperiod'] = 'Thời hạn ghi danh';
$string['enrolperiod_desc'] = 'Thời gian mặc định mà ghi danh có hiệu lực. Nếu đặt 0, ghi danh sẽ không giới hạn thời gian.';
$string['enrolperiod_help'] = 'Thời gian ghi danh có hiệu lực, tính từ lúc người dùng được ghi danh. Nếu tắt, ghi danh sẽ không giới hạn thời gian.';

$string['status_desc'] = 'Cho phép người dùng sử dụng SePay để ghi danh vào khóa học theo mặc định.';

$string['expiredaction'] = 'Hành động khi ghi danh hết hạn';
$string['expiredaction_help'] = 'Chọn hành động sẽ thực hiện khi ghi danh của người dùng hết hạn. Lưu ý một số dữ liệu và thiết lập của người dùng trong khóa học có thể bị xóa khi hủy ghi danh.';

$string['cost'] = 'Giá ghi danh';
$string['assignrole'] = 'Gán vai trò';
$string['enrolstartdate'] = 'Ngày bắt đầu';
$string['enrolstartdate_help'] = 'Nếu được bật, người dùng chỉ có thể bắt đầu được ghi danh từ ngày này.';

$string['pattern'] = 'Mẫu nội dung chuyển khoản';
$string['pattern_desc'] = 'Tiền tố dùng trong nội dung chuyển khoản, theo sau là ID khóa học và ID người dùng.';
$string['separator'] = 'Ngăn cách CourseID/UserID';
$string['separator_desc'] = 'Chuỗi ký tự ngăn cách giữa ID khóa học và ID người dùng.';

// Thông báo khi không cần thanh toán.
$string['nocost'] = 'Khoá học này không yêu cầu thanh toán.';

// Thông báo thanh toán (dùng trong webhook).
$string['paymentthanks'] = 'Đã nhận thanh toán cho khóa học: {$a}';
$string['paymentthanks_desc'] = 'Hệ thống đã nhận thanh toán. Bạn hiện đã được ghi danh vào khóa học: {$a}.';
$string['paymentreceived'] = 'Đã nhận thanh toán SePay cho khóa học: {$a}';
$string['paymentreceived_desc'] = 'Một người dùng đã được ghi danh tự động qua SePay vào khóa học: {$a}.';

$string['email_welcome_subject'] = 'Bạn đã được ghi danh vào khóa học: {$a->coursename}';
$string['email_welcome_body'] = 'Xin chào {$a->username},

Đây là email biên lai tự động xác nhận bạn đã đăng ký và thanh toán thành công khóa học: "{$a->coursename}".
Hiện tại bạn đã có toàn quyền truy cập vào khóa học.

Vào khóa học tại đây: {$a->courseurl}

Cảm ơn bạn và chúc bạn có một trải nghiệm học tập tuyệt vời!

Trân trọng,
Ban quản trị hệ thống';

$string['email_teacher_subject'] = 'Ghi danh mới: {$a->coursename}';
$string['email_teacher_body'] = 'Xin chào,

Một học viên mới ({$a->username}) vừa ghi danh thành công vào khóa học: "{$a->coursename}" của bạn thông qua cổng SePay.

Link khóa học: {$a->courseurl}
Hồ sơ học viên: {$a->profileurl}';

$string['email_admin_subject'] = 'Ghi danh mới: {$a->coursename}';
$string['email_admin_body'] = 'Xin chào,

Một học viên mới ({$a->username}) vừa ghi danh thành công vào khóa học: "{$a->coursename}" thông qua cổng SePay.

Link khóa học: {$a->courseurl}
Hồ sơ học viên: {$a->profileurl}';

$string['costerror'] = 'Giá trị không hợp lệ';

// Các chuỗi mới cho mục Phê duyệt thủ công.
$string['manual_enrol'] = 'Phê duyệt thủ công';
$string['manual_enrol_desc'] = 'Mặc định cho các instance ghi danh mới. Nếu chọn "Có", các giao dịch sẽ được ghi lại ở trạng thái Chờ phê duyệt và cần quản trị viên phê duyệt thủ công trước khi người dùng được ghi danh.';
$string['manage_transactions'] = 'Quản lý giao dịch SePay';
$string['transaction_recorded'] = 'Giao dịch đã được ghi nhận và đang chờ phê duyệt.';
$string['transaction_details'] = 'Chi tiết giao dịch';
$string['timecreated'] = 'Thời gian tạo';
$string['ip_address'] = 'Địa chỉ IP';
$string['settings_comparison'] = 'Đối chiếu cài đặt';
$string['approve'] = 'Phê duyệt';
$string['reject'] = 'Từ chối';
$string['confirm_reject'] = 'Bạn có chắc chắn muốn từ chối giao dịch này?';
$string['transaction_approved'] = 'Giao dịch đã được phê duyệt thành công.';
$string['transaction_rejected'] = 'Giao dịch đã bị từ chối.';
$string['status_rejected'] = 'Đã từ chối';
$string['payment_pending_title'] = 'Đã ghi nhận thanh toán!';
$string['payment_pending_message'] = 'Bạn sẽ được ghi danh tự động vào khóa học sau khi quản trị viên phê duyệt.';
$string['payment_success_title'] = 'Thanh toán thành công!';
$string['payment_success_message'] = 'Bạn đã được ghi danh vào khóa học này.';
$string['payment_auto_approved_title'] = 'Xác nhận thanh toán thành công!';
$string['payment_auto_approved_message'] = 'Bạn đã được ghi danh vào khóa học.';
$string['payment_approved_title'] = 'Xác nhận phê duyệt thành công!';
$string['payment_approved_message'] = 'Bạn đã được ghi danh vào khóa học.';
$string['redirecting_in'] = 'Đang chuyển hướng trong';
$string['seconds'] = 'giây';
$string['payment_rejected_title'] = 'Xác nhận phê duyệt thất bại!';
$string['payment_rejected_message'] = 'Vui lòng chọn 1 trong các cách sau đây:';
$string['retry_payment'] = 'Thanh toán lại';
$string['contact_admin'] = 'Liên hệ quản trị viên';
$string['back_to_course'] = 'Quay lại khóa học';
$string['bulk_delete'] = 'Xóa nhiều';
$string['confirm_delete'] = 'Bạn có chắc chắn muốn xóa giao dịch này? Lưu ý: nếu là giao dịch đã xử lý, ghi danh của học viên sẽ KHÔNG bị tự động hủy.';
$string['confirm_bulk_delete'] = 'Bạn có chắc chắn muốn xóa các giao dịch đã chọn? Lưu ý: giao dịch đã xử lý bị xóa sẽ KHÔNG tự động hủy ghi danh.';
$string['transaction_deleted'] = 'Giao dịch đã được xóa thành công.';
$string['transactions_deleted'] = 'Đã xóa {$a} giao dịch thành công.';
$string['transaction_deleted_processed'] = 'Đã xóa giao dịch. Lưu ý: đây là giao dịch đã xử lý — ghi danh của học viên KHÔNG bị tự động hủy.';
$string['no_transactions_selected'] = 'Không có giao dịch nào được chọn.';
$string['select_transactions_to_delete'] = 'Chọn giao dịch để xóa';
$string['error_already_processed'] = 'Lỗi: Giao dịch này đã được xử lý trước đó.';
$string['no_pending_transactions'] = 'Không có giao dịch nào đang chờ xử lý.';
$string['no_transactions_found'] = 'Không tìm thấy giao dịch nào.';
$string['no_processed_transactions_found'] = 'Không tìm thấy giao dịch nào đã xử lý.';
$string['no_rejected_transactions_found'] = 'Không tìm thấy giao dịch bị từ chối nào.';
$string['no_unenrolled_transactions_found'] = 'Không tìm thấy giao dịch nào đã hủy ghi danh.';
$string['user'] = 'Người dùng';
$string['course'] = 'Khóa học';
$string['transaction_status'] = 'Trạng thái';
$string['status_pending'] = 'Chờ xử lý';
$string['status_processed'] = 'Đã xử lý';
$string['status_unenrolled'] = 'Đã hủy ghi danh';
$string['amount'] = 'Số tiền';
$string['trans_content'] = 'Nội dung';
$string['process_date'] = 'Ngày xử lý';

// Cấu hình Lưu trữ Dữ liệu.
$string['data_retention_heading'] = 'Quản lý lưu trữ dữ liệu';
$string['data_retention_heading_desc'] = 'Cấu hình tự động dọn dẹp và lưu trữ dữ liệu giao dịch cũ để tối ưu database.';
$string['auto_cleanup_enabled'] = 'Bật tự động dọn dẹp';
$string['auto_cleanup_enabled_desc'] = 'Tự động chuyển hoặc xóa các giao dịch cũ theo lịch định kỳ.';
$string['retention_days'] = 'Thời gian lưu trữ (ngày)';
$string['retention_days_desc'] = 'Số ngày lưu giữ giao dịch trong bảng chính. Giao dịch cũ hơn sẽ được xử lý theo chiến lược lưu trữ. Mặc định: 365 ngày.';
$string['archive_strategy'] = 'Chiến lược lưu trữ';
$string['archive_strategy_desc'] = 'Chọn cách xử lý giao dịch cũ: chuyển vào bảng lưu trữ hoặc xóa hoàn toàn.';
$string['archive_strategy_archive'] = 'Chuyển vào bảng lưu trữ';
$string['archive_strategy_delete'] = 'Xóa hoàn toàn';
$string['archive_retention_days'] = 'Thời gian lưu vào bảng lưu trữ (ngày)';
$string['archive_retention_days_desc'] = 'Số ngày lưu giữ giao dịch trong bảng lưu trữ. Giao dịch cũ hơn sẽ bị xóa vĩnh viễn. Chỉ áp dụng khi chiến lược là "Chuyển vào bảng lưu trữ". Mặc định: 365 ngày. Đặt 0 để giữ vô thời hạn.';

// Tác vụ Scheduled Task.
$string['task_cleanup_transactions'] = 'Dọn dẹp giao dịch SePay cũ';
$string['task_process_enrolments'] = 'Xử lý ghi danh SePay đang chờ';
$string['task_process_rejections'] = 'Gửi thông báo từ chối SePay';
$string['task_update_banks'] = 'Đồng bộ danh sách ngân hàng SePay';
$string['task_process_expirations'] = 'Xử lý gỡ bỏ ghi danh hết hạn SePay';

// Phát hiện trùng lặp IP.
$string['ip_duplicate_warning'] = 'Cảnh báo: {$a} người dùng khác nhau đang dùng chung IP này!';
$string['ip_duplicate_users'] = '{$a} người dùng';

$string['mailstudents'] = 'Gửi email cho học viên';
$string['mailteachers'] = 'Gửi email cho giáo viên';
$string['mailadmins'] = 'Gửi email cho quản trị viên';

// Tin nhắn thông báo.
$string['messageprovider:pending_transaction'] = 'Thông báo giao dịch chờ xử lý';
$string['messageprovider:sepay_enrolment'] = 'Biên lai ghi danh khóa học';
$string['notification_pending_title'] = 'Giao dịch mới đang chờ phê duyệt';
$string['notification_pending_body'] = 'Người dùng {$a->username} đã thanh toán {$a->amount} {$a->currency} cho khóa học "{$a->coursename}". Vui lòng kiểm tra và phê duyệt giao dịch.';
$string['notification_pending_small'] = 'Thanh toán mới: {$a->amount} {$a->currency}';
$string['notification_pending_url'] = 'Xem giao dịch';

// Tin nhắn báo lỗi.
$string['transaction_not_found'] = 'Không tìm thấy giao dịch';
$string['payment_amount_insufficient'] = 'Số tiền thanh toán không đủ. Vui lòng kiểm tra lại.';
$string['errdisabled'] = 'Plugin ghi danh SePay đã bị tắt';

// Cài đặt thông báo.
$string['notification_settings'] = 'Thiết đặt thông báo SePay';
$string['notification_statistics'] = 'Thống kê thông báo';
$string['total_notifications'] = 'Tổng số thông báo';
$string['read_notifications'] = 'Đã đọc';
$string['unread_notifications'] = 'Chưa đọc';
$string['delete_read_notifications'] = 'Xóa thông báo đã đọc';
$string['delete_read_notifications_desc'] = 'Xóa các thông báo đã đọc theo khoảng thời gian cụ thể.';
$string['delete_read_1day'] = 'Xóa đã đọc > 1 ngày';
$string['delete_read_1week'] = 'Xóa đã đọc > 1 tuần';
$string['delete_read_1month'] = 'Xóa đã đọc > 1 tháng';
$string['confirm_delete_read_1day'] = 'Bạn có chắc chắn muốn xóa tất cả thông báo đã đọc cách đây hơn 1 ngày?';
$string['confirm_delete_read_1week'] = 'Bạn có chắc chắn muốn xóa tất cả thông báo đã đọc cách đây hơn 1 tuần?';
$string['confirm_delete_read_1month'] = 'Bạn có chắc chắn muốn xóa tất cả thông báo đã đọc cách đây hơn 1 tháng?';
$string['delete_all_read_notifications'] = 'Xóa toàn bộ thông báo đã đọc';
$string['delete_all_read_notifications_desc'] = 'Xóa tất cả thông báo đã đọc, không phân biệt thời gian.';
$string['delete_all_read'] = 'Xóa tất cả đã đọc';
$string['confirm_delete_all_read'] = 'Bạn có chắc chắn muốn xóa TẤT CẢ thông báo đã đọc?';
$string['delete_all_notifications'] = 'Xóa toàn bộ thông báo';
$string['delete_all_notifications_desc'] = 'CẢNH BÁO: Xóa tất cả thông báo SePay, bao gồm cả thông báo chưa đọc. Hành động này không thể hoàn tác!';
$string['delete_all'] = 'Xóa tất cả';
$string['confirm_delete_all'] = 'CẢNH BÁO: Bạn có chắc chắn muốn xóa TẤT CẢ thông báo (bao gồm cả chưa đọc)? Hành động này không thể hoàn tác!';
$string['notifications_deleted_success'] = 'Đã xóa thông báo thành công.';
$string['all_notifications_deleted_success'] = 'Đã xóa tất cả thông báo thành công.';
$string['recent_notifications'] = 'Thông báo gần đây';
$string['recipient'] = 'Người nhận';
$string['subject'] = 'Tiêu đề';
$string['read'] = 'Đã đọc';
$string['unread'] = 'Chưa đọc';

// Tùy chọn xóa thông báo đã đọc.
$string['delete_read_1day_option'] = '1 ngày';
$string['delete_read_1week_option'] = '1 tuần';
$string['delete_read_1month_option'] = '1 tháng';
$string['delete_read_3months_option'] = '3 tháng';
$string['delete_read_6months_option'] = '6 tháng';
$string['delete_read_never_option'] = 'Chưa lần nào';
$string['delete_button'] = 'Xóa';
$string['confirm_delete_read_selected'] = 'Bạn có chắc chắn muốn xóa các thông báo đã đọc theo khoảng thời gian đã chọn?';

// Nhãn và mô tả cho Form.
$string['delete_read_time_label'] = 'Mặc định: 1 tuần';
$string['delete_all_time_label'] = 'Mặc định: 1 tháng';
$string['delete_all_notifications_label'] = 'Xóa toàn bộ thông báo';
$string['save_changes'] = 'Lưu các thay đổi';
$string['status'] = 'Trạng thái';
$string['actions'] = 'Hành động';
$string['delete'] = 'Xóa';
$string['confirm_delete_notification'] = 'Bạn có chắc chắn muốn xóa thông báo này?';
$string['notification_deleted'] = 'Đã xóa thông báo thành công.';
$string['sender'] = 'Người gửi';

// Giao diện bộ lọc và tìm kiếm giao dịch.
$string['transactions_found'] = 'Tìm thấy {$a} giao dịch';
$string['search_user'] = 'Tìm theo người dùng';
$string['search_course'] = 'Tìm theo khóa học';
$string['filter_date_from'] = 'Từ ngày';
$string['filter_date_to'] = 'Đến ngày';
$string['filter_amount_min'] = 'Số tiền tối thiểu';
$string['filter_amount_max'] = 'Số tiền tối đa';
$string['add_condition'] = 'Thêm điều kiện';
$string['clear_filter'] = 'Xóa bộ lọc';
$string['apply_filter'] = 'Áp dụng bộ lọc';
$string['total_transactions'] = 'Tổng giao dịch';
$string['filter_by_letter'] = 'Lọc theo họ';
$string['filter_firstname'] = 'Tên';
$string['filter_lastname'] = 'Họ';
$string['reset_table'] = 'Thiết lập lại bảng lựa chọn';
$string['stat_total'] = 'Tổng';
$string['stat_pending'] = 'Chờ xử lý';
$string['stat_processed'] = 'Đã xử lý';
$string['stat_rejected'] = 'Đã từ chối';
$string['stat_unenrolled'] = 'Đã hủy ghi danh';
$string['bulk_approve'] = 'Phê duyệt nhiều';
$string['bulk_reject'] = 'Từ chối nhiều';
$string['confirm_bulk_approve'] = 'Bạn có chắc chắn muốn phê duyệt các giao dịch đã chọn?';
$string['confirm_bulk_reject'] = 'Bạn có chắc chắn muốn từ chối các giao dịch đã chọn?';
$string['bulk_approved'] = 'Đã phê duyệt {$a} giao dịch thành công.';
$string['bulk_approved_partial'] = 'Đã phê duyệt {$a->ok} giao dịch, {$a->failed} giao dịch lỗi.';
$string['bulk_rejected'] = 'Đã từ chối {$a} giao dịch thành công.';
$string['bulk_rejected_partial'] = 'Đã từ chối {$a->ok} giao dịch, {$a->failed} giao dịch lỗi.';
$string['bulk_unenrol'] = 'Hủy ghi danh nhiều';
$string['confirm_bulk_unenrol'] = 'Bạn có chắc chắn muốn hủy ghi danh các học viên của các giao dịch đã chọn?';
$string['bulk_unenrolled'] = 'Đã hủy ghi danh {$a} học viên thành công.';

// Chuỗi bổ sung.
$string['error_instance_deleted'] = 'Instance ghi danh đã bị xóa.';
$string['error_enrol_failed'] = 'Ghi danh thất bại. Vui lòng liên hệ quản trị viên.';
$string['pending_retention_days'] = 'Thời gian giữ giao dịch chờ xử lý (ngày)';
$string['pending_retention_days_desc'] = 'Số ngày giữ giao dịch chờ xử lý trước khi tự động từ chối. Mặc định: 30 ngày.';

// Chuỗi thông báo từ chối.
$string['email_rejection_subject'] = 'Bạn đã bị từ chối ghi danh vào khóa học: {$a->coursename}';
$string['email_rejection_body'] = 'Xin chào {$a->username}, bạn đã bị từ chối ghi danh vào khóa học: {$a->coursename}. Vui lòng liên hệ với quản trị viên để biết thêm thông tin.';
$string['email_rejection_smallmessage'] = 'Bạn đã bị từ chối ghi danh vào khóa học: {$a->coursename}.';
$string['messageprovider:rejection_notification'] = 'Thông báo từ chối ghi danh';

// Chuỗi thông báo hủy ghi danh.
$string['email_unenrolment_subject'] = 'Bạn đã bị hủy ghi danh khỏi khóa học: {$a->coursename}';
$string['email_unenrolment_body'] = 'Xin chào {$a->username}, bạn đã bị hủy ghi danh khỏi khóa học: {$a->coursename}. Vui lòng liên hệ quản trị viên nếu bạn cho rằng đây là nhầm lẫn.';
$string['email_unenrolment_smallmessage'] = 'Bạn đã bị hủy ghi danh khỏi khóa học: {$a->coursename}.';
$string['messageprovider:unenrolment_notification'] = 'Thông báo hủy ghi danh';

// Xác nhận tự hủy ghi danh.
$string['unenrolselfconfirm'] = 'Bạn có thực sự muốn tự hủy ghi danh khỏi khóa học "{$a}"?';

// Tên tác vụ.
$string['task_process_expirations'] = 'Xử lý ghi danh SePay hết hạn';

// Chuỗi trang gửi email hàng loạt.
$string['mass_email_title'] = 'Gửi Lại Email Ghi Danh';
$string['mass_email_heading'] = 'Gửi Lại Email Kích Hoạt Tồn Đọng (SePay)';
$string['mass_email_desc'] = 'Tính năng này cho phép bạn quét các học viên đã mua khoá học trước đây nhưng chưa được gửi email thông báo tự động.';
$string['mass_email_allsent'] = 'Tất cả học viên đã nhận email đầy đủ.';
$string['mass_email_found'] = 'Phát hiện <b>{$a}</b> học viên chưa nhận email.';
$string['mass_email_limit_label'] = 'Số email mỗi lượt:';
$string['mass_email_submit'] = 'Đặt lịch gửi email trong nền';

// Các chuỗi trong trang quản lý giao dịch thủ công (manage.php).
$string['manage_gateway'] = 'Cổng thanh toán';
$string['manage_instance_cost'] = 'Giá instance';
$string['manage_global_cost'] = 'Giá toàn hệ thống';

// Trang xem trước email.
$string['preview_email_title'] = 'Xem trước mẫu Email';
$string['preview_email_heading'] = 'Xem trước Email SePay';

// Thao tác ghi danh hàng loạt (trang participants).
$string['editselectedusers'] = 'Sửa đăng kí của những người dùng đã được chọn';
$string['deleteselectedusers'] = 'Xóa những đăng kí của những người dùng đã được chọn';
$string['confirmbulkdeleteenrolment'] = 'Bạn có chắc chắn muốn xóa các đăng kí này không?';
$string['unenrolusers'] = 'Hủy ghi danh';
