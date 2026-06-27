<!-- Tiêu đề PR theo Conventional Commits (tiếng Anh OK cho tool parse), vd: fix(webhook): ... -->

## Mục đích
<!-- 1-2 câu: PR này giải quyết gì? Link issue nếu có (Closes #123). -->

## Thay đổi chính
-

## Cách kiểm thử
<!-- Các bước tái hiện / lệnh test đã chạy. -->
-

## Checklist
- [ ] Đã chạy `moodle-plugin-ci phplint` / `phpcs` / `phpstan` (hoặc CI xanh).
- [ ] Có test cho thay đổi (nếu áp dụng).
- [ ] Thay đổi schema DB → đã bump `version.php` + thêm block `db/upgrade.php`.
- [ ] Cập nhật `CHANGELOG.md`.
- [ ] Không commit secret/API key; không thêm `error_log()`/`print_r()`.

## Breaking changes
<!-- Mô tả nếu có, hoặc "Không". -->
