# Changelog

Tất cả thay đổi đáng chú ý của plugin được ghi ở đây.
Định dạng theo [Keep a Changelog](https://keepachangelog.com/vi/1.0.0/);
phiên bản tuân theo quy ước version của Moodle (`YYYYMMDDXX`).

## [Unreleased]

### Fixed
- Chặn ghi danh khi `roleid` chưa cấu hình hợp lệ (tránh `enrol_user` với role = 0 → ghi danh không gán role) — webhook, cron, complete_enrol.
- Không đánh dấu "đã gửi" khi thông báo/email thực tế chưa gửi (khi tắt cấu hình mail) — tránh mất thông báo vĩnh viễn.
- Bulk unenrol: thêm `try/catch` per-iteration để 1 user lỗi không làm dừng cả batch.
- Cảnh báo + ghi log khi xóa giao dịch `processed` (ghi danh không tự hủy).
- Xóa BOM UTF-8 trong `lang/vi`; thay `error_log()`/`print_r()` bằng `debugging()` (tránh ghi payload ra log dùng chung).
- Đồng bộ `version.php` và savepoint `upgrade.php` về `2026010100` (sửa lỗi chặn upgrade).

### Added
- GitHub Actions CI bằng `moodle-plugin-ci` (Moodle 4.2 + PHP 8.2).
- File chuẩn repo: `CHANGELOG`, `CONTRIBUTING`, `SECURITY`, issue/PR templates, `.gitattributes`.

## [2026010100]

### Added
- Phiên bản đầu tiên: ghi danh tự động qua webhook SePay (xác thực API Key, chống replay theo `transaction_ref`).
- Chế độ duyệt thủ công (theo instance hoặc toàn cục).
- Trang quản lý giao dịch: lọc, tìm kiếm, lọc theo chữ cái (tiếng Việt), thao tác hàng loạt, xuất CSV/Excel.
- Tác vụ nền: tự ghi danh, xử lý hết hạn, gửi thông báo từ chối, dọn dữ liệu, đồng bộ ngân hàng.
- Thông báo qua chuông Moodle + email; đa ngôn ngữ (vi/en).
