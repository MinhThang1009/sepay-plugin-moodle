# Chính sách bảo mật

Plugin xử lý luồng thanh toán + ghi danh nên báo lỗi bảo mật được ưu tiên cao.

## Phiên bản được hỗ trợ

| Phiên bản | Hỗ trợ |
|---|---|
| 2026010100 (mới nhất) | ✅ |
| Cũ hơn | ❌ |

## Báo cáo lỗ hổng

**KHÔNG** mở Issue công khai cho lỗ hổng bảo mật.

- Ưu tiên: dùng **GitHub Private Vulnerability Reporting** (tab *Security* → *Report a vulnerability*).
- Hoặc liên hệ riêng người duy trì repo (điền email/kênh liên hệ tại đây).

Khi báo cáo, nêu rõ: phiên bản, các bước tái hiện, ảnh hưởng, và (nếu có) bản vá đề xuất.
Cam kết phản hồi trong thời gian hợp lý và phối hợp công bố sau khi có bản vá.

## Lưu ý khi triển khai

- **API Key** webhook: đặt chuỗi mạnh, không commit vào repo, không log.
- Cấu hình HTTPS cho endpoint `webhook.php`.
- Không để lộ file backup (`*.zip`/`*.tgz`) hay `error_log` trong thư mục web.
- Chạy Moodle với chế độ `debugging` tắt trên production (tránh lộ thông tin).
