# Changelog

Tất cả thay đổi đáng chú ý của plugin được ghi ở đây.
Định dạng theo [Keep a Changelog](https://keepachangelog.com/vi/1.0.0/);
phiên bản hiển thị theo [SemVer](https://semver.org/lang/vi/) (khớp git tag `vX.Y.Z`),
kèm Moodle version (`YYYYMMDDXX`) trong `version.php`.

## [1.1.1](https://github.com/MinhThang1009/sepay-plugin-moodle/compare/v1.1.0...v1.1.1) (2026-06-29)


### Sửa lỗi

* sửa các bug logic từ audit (bulk UI, webhook, cleanup) ([#14](https://github.com/MinhThang1009/sepay-plugin-moodle/issues/14)) ([e16c573](https://github.com/MinhThang1009/sepay-plugin-moodle/commit/e16c5737ad454ad510f2a8b77d8ced58fa717451))

## [Unreleased]

## [1.1.0] - 2026-06-28

### Added
- **Gia hạn sau khi hết hạn**: học viên bị suspend (hết hạn `enrolperiod` với `expiredaction` = SUSPEND/SUSPENDNOROLES) thấy lại form QR và trả tiền để kích hoạt lại — cả chế độ auto lẫn manual. Observer mới cho `user_enrolment_updated`.
- Thực thi tự động dọn bell notification theo cấu hình (`delete_read_notifications_delay` / `delete_all_notifications_delay`) trong tác vụ `cleanup_transactions` — trước đây cấu hình được lưu nhưng không tiến trình nào thực thi (mặc định `never`).

### Fixed
- `enrol_user()` truyền `ENROL_USER_ACTIVE` (webhook/complete_enrol/cron) để re-activate user đang suspended khi gia hạn — trước đây giữ nguyên suspend.
- Chỉ đánh dấu `rejection_notified` khi thực sự gửi được thông báo từ chối (đồng bộ giữa duyệt thủ công và cron `process_rejections`).
- `transactions.php`: guard `strtotime()` cho bộ lọc ngày; hiển thị số giao dịch lỗi khi bulk approve/reject (trước đây nuốt thầm).
- `webhook.php`: từ chối (fail-closed, 503) khi chưa cấu hình tài khoản nhận.
- `external.php`: poll dùng `is_enrolled(onlyactive=true)` để reload đúng khi gia hạn.

### Changed
- Giới hạn **một** instance SePay mỗi khóa học (nội dung QR chỉ mã hóa course + user, không phân biệt nhiều instance → tránh enrol nhầm instance).
- Xóa các file dead không còn dùng: `manage.php`, `check_status.php`, `check_transaction_status.php`.
- `commitlint.config.js` → `.cjs` (sửa lỗi load ESM trong CI).

## [1.0.0] - 2026-06-28

### Fixed
- Chặn ghi danh khi `roleid` chưa cấu hình hợp lệ (tránh `enrol_user` với role = 0 → ghi danh không gán role) — webhook, cron, complete_enrol.
- Không đánh dấu "đã gửi" khi thông báo/email thực tế chưa gửi (khi tắt cấu hình mail) — tránh mất thông báo vĩnh viễn.
- Bulk unenrol: thêm `try/catch` per-iteration để 1 user lỗi không làm dừng cả batch.
- Cảnh báo + ghi log khi xóa giao dịch `processed` (ghi danh không tự hủy).
- Xóa BOM UTF-8 trong `lang/vi`; thay `error_log()`/`print_r()` bằng `debugging()` (tránh ghi payload ra log dùng chung).
- Đồng bộ `version.php` và savepoint `upgrade.php` (sửa lỗi chặn upgrade).

### Added
- GitHub Actions CI bằng `moodle-plugin-ci` (ma trận Moodle 4.0–5.2 × PHP 7.4–8.3 trên MariaDB).
- File chuẩn repo: `CHANGELOG`, `CONTRIBUTING`, `SECURITY`, `SUPPORT`, `CODE_OF_CONDUCT`, issue/PR templates, `.gitattributes`.
- Bộ test PHPUnit đầu tiên; `classes/email_templates.php` (tách builder HTML email).

## [2026010100]

### Added
- Phiên bản đầu tiên: ghi danh tự động qua webhook SePay (xác thực API Key, chống replay theo `transaction_ref`).
- Chế độ duyệt thủ công (theo instance hoặc toàn cục).
- Trang quản lý giao dịch: lọc, tìm kiếm, lọc theo chữ cái (tiếng Việt), thao tác hàng loạt, xuất CSV/Excel.
- Tác vụ nền: tự ghi danh, xử lý hết hạn, gửi thông báo từ chối, dọn dữ liệu, đồng bộ ngân hàng.
- Thông báo qua chuông Moodle + email; đa ngôn ngữ (vi/en).
