# Hướng dẫn đóng góp

Cảm ơn bạn đã quan tâm đóng góp cho `enrol_sepay`.

## Môi trường

- Moodle 4.2+ và PHP 8.2 (khớp môi trường production).
- Tuân thủ [Moodle coding style](https://moodledev.io/general/development/policies/codingstyle).

## Quy trình

1. Fork repo và tạo nhánh từ `main`.
2. Đặt tên nhánh theo prefix: `feat/`, `fix/`, `refactor/`, `docs/`, `chore/`.
3. Commit theo [Conventional Commits](https://www.conventionalcommits.org/) (subject tiếng Việt):
   ```
   fix(webhook): chặn ghi danh khi roleid chưa cấu hình
   ```
4. Mở Pull Request về `main`, điền đầy đủ mô tả + cách kiểm thử.

## Kiểm tra trước khi gửi PR

CI (`moodle-plugin-ci`) sẽ chạy tự động trên mỗi PR. Nên chạy local trước:

```bash
moodle-plugin-ci phplint      # lint cú pháp
moodle-plugin-ci phpcs        # Moodle code checker
moodle-plugin-ci phpstan      # static analysis
moodle-plugin-ci phpunit      # unit test
```

Gate cứng (phải xanh để merge): `phplint`, `validate`, `savepoints`, `mustache`, `phpunit`, `behat`.
Advisory (khuyến khích, chưa bắt buộc): `phpcs`, `phpmd`, `phpstan`, `grunt`.

## Quy ước code

- Comment **tiếng Việt** giải thích *vì sao*; TODO/FIXME tag tiếng Anh.
- Tên biến/hàm/class/file: tiếng Anh theo convention Moodle.
- Không nuốt exception (empty catch); không dùng `error_log()`/`print_r()` — dùng `debugging()`.
- Thay đổi schema DB → bump `version.php` + thêm block trong `db/upgrade.php`.
- Giữ nguyên CSS/HTML/layout của trang QR, countdown, transactions trừ khi cần thiết.

## Báo lỗi & đề xuất

Dùng [Issues](../../issues) với template tương ứng. Lỗ hổng bảo mật: xem [SECURITY.md](SECURITY.md).
