<div align="center">

# SePay Enrolment Plugin cho Moodle (`enrol_sepay`)

**Ghi danh Moodle tự động qua chuyển khoản QR SePay**

[![Moodle Plugin CI](https://github.com/MinhThang1009/sepay-plugin-moodle/actions/workflows/ci.yml/badge.svg)](https://github.com/MinhThang1009/sepay-plugin-moodle/actions/workflows/ci.yml)
[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)
![Moodle](https://img.shields.io/badge/Moodle-4.0--5.2-orange)
![PHP](https://img.shields.io/badge/PHP-7.4--8.3-777bb4)

</div>

Plugin ghi danh (enrolment method) cho Moodle, tự động mở khóa học cho học viên ngay khi nhận
được chuyển khoản qua **SePay** — phù hợp với trường học, trung tâm bán khóa học và thu học phí
tự động qua chuyển khoản ngân hàng.

> **Luồng chính:** Học viên vào trang khóa học → thấy mã QR → chuyển khoản đúng số tiền + nội dung.
> SePay gửi webhook về Moodle → plugin đối chiếu → tự ghi danh (hoặc chờ admin duyệt nếu bật chế độ thủ công).

---

## Mục lục

- [1. Tính năng](#1-tính-năng)
- [2. Yêu cầu](#2-yêu-cầu)
- [3. Cài đặt](#3-cài-đặt)
- [4. Cấu hình](#4-cấu-hình)
  - [4.1 Thiết lập chung](#41-thiết-lập-chung)
  - [4.2 Thiết lập webhook trên SePay](#42-thiết-lập-webhook-trên-sepay)
  - [4.3 Gán phương thức ghi danh vào khóa học](#43-gán-phương-thức-ghi-danh-vào-khóa-học)
  - [4.4 Cron](#44-cron)
- [5. Luồng xử lý](#5-luồng-xử-lý)
- [6. Ví dụ webhook](#6-ví-dụ-webhook)
- [7. Cấu trúc thư mục](#7-cấu-trúc-thư-mục)
- [8. Phát triển & CI](#8-phát-triển--ci)
- [9. Đóng góp](#9-đóng-góp)
- [10. Hỗ trợ và Bảo mật](#10-hỗ-trợ-và-bảo-mật)
- [11. Giấy phép](#11-giấy-phép)

## 1. Tính năng

- 💳 **Ghi danh tự động** qua webhook SePay — không cần thao tác thủ công.
- ✅ **Chế độ duyệt thủ công** (tùy chọn theo từng instance hoặc toàn cục): admin xem & duyệt giao dịch.
- 🔐 **Xác thực webhook** bằng API Key (`hash_equals`), chống replay theo `transaction_ref`.
- 📊 **Trang quản lý giao dịch**: lọc theo trạng thái, tìm theo tên/khóa học, lọc theo chữ cái (hỗ trợ tiếng Việt), thao tác hàng loạt (duyệt/từ chối/hủy ghi danh/xóa), xuất CSV/Excel.
- ⏱️ **Tác vụ nền (cron)**: tự ghi danh giao dịch đã duyệt, xử lý hết hạn, gửi thông báo từ chối, dọn dữ liệu cũ, đồng bộ danh sách ngân hàng.
- 🔔 **Thông báo** qua chuông Moodle + email (chào mừng, từ chối, hủy ghi danh).
- 🌐 **Đa ngôn ngữ**: tiếng Việt + tiếng Anh.

## 2. Yêu cầu

| Thành phần | Phiên bản |
|---|---|
| Moodle | 4.0 – 5.2 (`requires` = 2022041900) |
| PHP | 7.4 – 8.3 |
| Database | MySQL / MariaDB / PostgreSQL (qua XMLDB của Moodle) |
| Dịch vụ ngoài | Tài khoản [SePay](https://sepay.vn) + webhook |

## 3. Cài đặt

1. Đăng nhập Moodle bằng tài khoản `admin`.
2. Vào `Site administration` → `Plugins` → `Install plugins`.
3. Tải lên file `.zip` của plugin; đảm bảo loại plugin nhận diện là **Enrolment method (enrol)**.
4. Hoàn tất luồng cài đặt và chạy **Upgrade Moodle database now**.
5. Vào `Site administration` → `Plugins` → `Enrolments` → `Manage enrol plugins`, bật **SePay**.

Hoặc cài bằng Git (đặt vào thư mục `enrol/`):

```bash
git clone https://github.com/MinhThang1009/sepay-plugin-moodle.git enrol/sepay
```

## 4. Cấu hình

### 4.1 Thiết lập chung
`Site administration` → `Plugins` → `Enrolments` → `SePay`:

| Cài đặt | Mô tả |
|---|---|
| **Account** | Số tài khoản ngân hàng nhận thanh toán |
| **Bank** | Mã/tên ngân hàng nhận tiền |
| **Pattern** | Tiền tố mã thanh toán in trên QR (vd `SP`) |
| **API Key** | Khóa đối chiếu Moodle ↔ SePay (admin tự tạo, dán giống nhau ở cả 2 nơi) |
| **Manual Enrol** | Bật/tắt chế độ duyệt thủ công mặc định |

### 4.2 Thiết lập webhook trên SePay
1. Vào https://my.sepay.vn/webhooks.
2. Thêm webhook URL: `https://<domain-moodle>/enrol/sepay/webhook.php`.
3. Kiểu xác thực: **API Key** (nhập đúng chuỗi đã đặt trong cấu hình plugin).

### 4.3 Gán phương thức ghi danh vào khóa học
1. Vào tab `Participants` của khóa học → `Enrolment methods` → thêm **SePay**.
2. Nhập giá tại `Enrol cost`, chọn role, lưu.

### 4.4 Cron
Plugin dựa vào cron của Moodle (chạy mỗi phút là lý tưởng):

```bash
# Linux / macOS
sudo -u www-data php admin/cli/cron.php
```

## 5. Luồng xử lý

```mermaid
flowchart TD
    A[Học viên truy cập khóa học] --> B[Hiển thị QR Code thanh toán]
    B --> C[Học viên quét mã và chuyển tiền]
    C --> D[SePay phát sinh biến động số dư]
    D --> E[SePay gửi POST webhook về Moodle]
    E --> F{Plugin xác thực API Key + đối chiếu}
    F -- Khớp tiền & mã, Auto --> G[Lưu giao dịch: processed]
    F -- Khớp tiền & mã, Manual --> H[Lưu giao dịch: pending]
    H --> I[Admin duyệt trên trang quản lý]
    I --> G
    F -- Sai tiền / sai mã --> J[Lưu giao dịch: rejected]
    G --> K[Ghi danh học viên + gửi thông báo]
```

## 6. Ví dụ webhook

Khóa học `id=10`, học viên `id=55`, pattern `SP` → nội dung chuyển khoản: **`SP10U55`**.

SePay gửi `POST` (JSON, rút gọn):

```json
{
  "gateway": "Vietcombank",
  "accountNumber": "0123456789",
  "transferType": "in",
  "transferAmount": 500000,
  "referenceCode": "FT2026...",
  "content": "Nguyen Van A ck SP10U55"
}
```

Plugin xử lý:
1. Xác thực header `Authorization: Apikey <key>`.
2. Parse `content` → khóa học `10`, học viên `55`.
3. Đối chiếu `transferAmount` với học phí của instance.
4. Khớp → lưu `processed` và ghi danh (auto), hoặc `pending` (chờ duyệt); sai → `rejected`.

## 7. Cấu trúc thư mục

```text
enrol/sepay/
├── lib.php                  # Class enrol_sepay_plugin (core enrolment)
├── webhook.php              # Endpoint nhận POST từ SePay (xác thực + ghi danh)
├── complete_enrol.php       # Hoàn tất ghi danh sau countdown phía client
├── transactions.php         # Trang quản lý giao dịch (lọc/duyệt/xóa/xuất)
├── notification_settings.php# Trang dọn dẹp thông báo
├── download.php             # Xuất CSV/Excel
├── unenrolself.php          # Học viên tự hủy ghi danh
├── settings.php             # Cấu hình admin
├── locallib.php             # Bulk operations (sửa/hủy ghi danh hàng loạt)
├── classes/
│   ├── util.php             # Gửi thông báo/email, template HTML
│   ├── external.php         # Web service polling trạng thái (AJAX)
│   ├── observer.php         # Xử lý sự kiện hủy ghi danh
│   ├── table/               # Bảng giao dịch (table_sql)
│   └── task/                # Cron: process_enrolments, process_expirations,
│                            #       process_rejections, update_banks, cleanup_transactions
├── amd/src/                 # JS (AMD): QR, countdown, polling, bulk actions
├── db/                      # access, install.xml, upgrade, messages, tasks, services, events
├── lang/{en,vi}/            # Chuỗi ngôn ngữ
├── templates/               # Mustache (form QR)
└── cli/sync.php             # CLI đồng bộ
```

## 8. Phát triển & CI

Repo dùng **GitHub Actions** với [`moodle-plugin-ci`](https://moodlehq.github.io/moodle-plugin-ci/)
(ma trận Moodle 4.0–5.2 × PHP 7.4–8.3 trên MariaDB) — xem [`.github/workflows/ci.yml`](.github/workflows/ci.yml).

Mỗi push/PR tự chạy: `phplint`, `validate`, `savepoints`, `mustache`, `phpunit`, `behat`
(gate cứng) và `phpcs`, `phpmd`, `phpstan`, `grunt` (advisory).

Chạy kiểm tra local (cần PHP + [moodle-plugin-ci](https://moodlehq.github.io/moodle-plugin-ci/)):

```bash
moodle-plugin-ci phplint
moodle-plugin-ci phpcs
moodle-plugin-ci phpstan
```

Lịch sử thay đổi: xem [CHANGELOG.md](CHANGELOG.md).

## 9. Đóng góp

Hoan nghênh đóng góp! Đọc [CONTRIBUTING.md](CONTRIBUTING.md) (quy ước commit, branch, PR) và [Quy tắc ứng xử](CODE_OF_CONDUCT.md) trước khi mở PR. Mọi PR chạy qua CI `moodle-plugin-ci` (xem mục [8. Phát triển & CI](#8-phát-triển--ci)); `main` được bảo vệ, merge qua PR sau khi CI xanh.

## 10. Hỗ trợ và Bảo mật

- **Cần trợ giúp / báo lỗi / đề xuất tính năng**: xem [SUPPORT.md](SUPPORT.md) hoặc mở [issue](https://github.com/MinhThang1009/sepay-plugin-moodle/issues).
- **Lỗ hổng bảo mật**: KHÔNG mở issue công khai — xem [SECURITY.md](SECURITY.md).

## 11. Giấy phép

[GNU GPL v3 or later](https://www.gnu.org/licenses/gpl-3.0) — theo chuẩn plugin Moodle.

Phát triển & duy trì bởi **Quiz Văn Lang** (<quizvanlang@gmail.com>).
