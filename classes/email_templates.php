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
 * Builder HTML cho các email thông báo của plugin enrol_sepay.
 *
 * @package    enrol_sepay
 * @copyright  2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_sepay;

// Các hàm get_*_email_html chứa HTML email (Gmail/Outlook): dòng dài, khoảng trắng cuối
// dòng, và nhiều lần chuyển đổi chế độ HTML/PHP (khiến sniff hiểu nhầm là thiếu file docblock).
// Đây là đặc thù email client, không thể sửa mà không vỡ layout nên tắt các sniff liên quan.
// Sub-method tách từ builder đặt PHP-tag ở cột 0 ngay tại ranh giới HTML email để
// giữ output byte-identical; indent các tag này sẽ chèn khoảng trắng vào email nên tắt ScopeIndent.
// phpcs:disable moodle.Files.LineLength.MaxExceeded, moodle.Files.LineLength.TooLong, moodle.WhiteSpace.WhiteSpaceInStrings.EndLine, moodle.Commenting.MissingDocblock.File, moodle.Commenting.FileExpectedTags.CopyrightTagMissing, moodle.Commenting.FileExpectedTags.LicenseTagMissing, Generic.WhiteSpace.ScopeIndent

/**
 * Dựng nội dung HTML cho email ghi danh, từ chối và hủy ghi danh.
 */
class email_templates {
    /**
     * Lọc và trích xuất đúng phần Tên học viên từ các định dạng phức tạp
     * VD: "2273401210132 - Võ Lê Thu Phương - 71K28KDTM02" -> "Võ Lê Thu Phương"
     *
     * @param string $rawname Tên thô, có thể kèm mã SV và lớp
     * @return string Tên học viên đã tách
     */
    public static function clean_student_name($rawname) {
        $rawname = trim($rawname);
        if (strpos($rawname, ' - ') !== false) {
            $parts = explode(' - ', $rawname);
            $parts = array_map('trim', $parts);

            // Xóa đi các mẩu bị trống do dư dấu cách.
            $parts = array_values(array_filter($parts, function ($val) {
                return $val !== '';
            }));

            if (count($parts) >= 3) {
                // Return phần giữa (Thường cấu trúc là ID - Tên - Lớp).
                return $parts[1];
            } else if (count($parts) == 2) {
                // Nếu là "ID - Tên".
                if (is_numeric($parts[0])) {
                    return $parts[1];
                }
                return $parts[0];
            }
        }
        return $rawname;
    }

    /**
     * Trả về URL hosted cho ảnh logo trong pix/.
     * Dùng URL tuyệt đối từ wwwroot thay cho base64 data URI
     * vì Gmail và Outlook block embedded data URIs trong email.
     *
     * @param string $filename  Tên file trong pix/ (vd. gmail_icon.png)
     * @param int    $w         Không dùng, giữ lại để backward-compatible
     * @param int    $h         Không dùng, giữ lại để backward-compatible
     * @return string  URL tuyệt đối hoặc chuỗi rỗng nếu wwwroot chưa sẵn sàng.
     */
    public static function logo_data_uri(string $filename, int $w, int $h): string {
        global $CFG;
        if (empty($CFG->wwwroot)) {
            return '';
        }
        $path = __DIR__ . '/../pix/' . $filename;
        if (!file_exists($path)) {
            return '';
        }
        return rtrim($CFG->wwwroot, '/') . '/enrol/sepay/pix/' . rawurlencode($filename);
    }

    /**
     * Dò tìm nền tảng Email (Gmail / Outlook) và trả về label với logo hosted URL.
     *
     * @param string $email Địa chỉ email của người nhận
     * @return string Label kèm logo (HTML) tương ứng nền tảng
     */
    public static function get_email_platform_label($email) {
        if (stripos($email, '@vanlanguni.vn') !== false || stripos($email, '@outlook.') !== false || stripos($email, '@hotmail.') !== false) {
            $uri = self::logo_data_uri('outlook_icon.png', 16, 16);
            $img = $uri ? '<img src="' . $uri . '" width="16" height="16" style="vertical-align: middle; margin-right: 6px;" alt="Outlook">' : '';
            return $img . 'Outlook:';
        } else if (stripos($email, '@gmail.com') !== false) {
            $uri = self::logo_data_uri('gmail_icon.png', 16, 16);
            $img = $uri ? '<img src="' . $uri . '" width="16" height="16" style="vertical-align: middle; margin-right: 6px;" alt="Gmail">' : '';
            return $img . 'Gmail:';
        }
        return '📧 Email:';
    }

    /**
     * Dựng HTML email gửi cho học viên sau khi ghi danh thành công.
     *
     * @param \stdClass $a Du lieu email (username, coursename, useremail, courseurl)
     * @return string Nội dung HTML của email
     */
    public static function get_student_email_html($a) {
        $cleanname     = htmlspecialchars(self::clean_student_name($a->username), ENT_QUOTES, 'UTF-8');
        $coursename     = htmlspecialchars($a->coursename, ENT_QUOTES, 'UTF-8');
        $useremail      = htmlspecialchars($a->useremail, ENT_QUOTES, 'UTF-8');
        $courseurl      = htmlspecialchars($a->courseurl, ENT_QUOTES, 'UTF-8');
        $platformlabel = self::get_email_platform_label($a->useremail);
        return self::student_head()
            . self::student_body($cleanname, $coursename, $useremail, $courseurl, $platformlabel)
            . self::student_zalo_box($a)
            . self::student_footer($a);
    }
    /**
     * Khối <head> + style của email học viên.
     *
     * @return string
     */
    private static function student_head(): string {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="vi" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
        <head>
            <meta charset="UTF-8">
            <title>Thông báo từ hệ thống</title>
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
            <meta name="color-scheme" content="light dark">
            <meta name="supported-color-schemes" content="light dark">
            <!--[if mso]>
            <xml>
                <o:OfficeDocumentSettings>
                    <o:AllowPNG/>
                    <o:PixelsPerInch>96</o:PixelsPerInch>
                </o:OfficeDocumentSettings>
            </xml>
            <![endif]-->
            <style>
                :root {
                    color-scheme: light dark;
                    supported-color-schemes: light dark;
                }
                @media (prefers-color-scheme: dark) {
                    .body-bg { background-color: #020617 !important; }
                    .content-bg { background-color: #0f172a !important; border-color: #1e293b !important; border-bottom-color: #1e293b !important; }
                    .text-main { color: #f8fafc !important; text-shadow: none !important; }
                    .text-sub { color: #94a3b8 !important; }
                    .card-bg-red { background-color: #2e1014 !important; border-color: #4c1d24 !important; border-top-color: #3b1419 !important; border-bottom-color: #1a080b !important; box-shadow: none !important; }
                    .card-bg-blue { background-color: #0d1b2a !important; border-color: #1e3a8a !important; border-top-color: #172554 !important; border-bottom-color: #080f1a !important; box-shadow: none !important; }
                    .dashed-border { border-top-color: #334155 !important; border-bottom-color: #334155 !important; }
                    .footer-text { color: #64748b !important; }
                    .highlight-red { color: #ef4444 !important; }
                    .icon-bg { background-color: rgba(0,0,0,0.2) !important; border-color: rgba(255,255,255,0.1) !important; }
                    .divider-line { background-color: #334155 !important; }
                }
                /* Prevent font boosting in Gmail */
                body, table, td, p, a, li, blockquote {
                    -webkit-text-size-adjust: 100%;
                    -ms-text-size-adjust: 100%;
                }
                /* RESPONSIVE STYLES */
                @media screen and (max-width: 600px) {
                    .stack-mobile { display: block !important; width: 100% !important; min-width: 100% !important; max-width: 100% !important; float: none !important; text-align: left !important; box-sizing: border-box !important; }
                    .value-mobile { float: none !important; text-align: left !important; max-width: 100% !important; padding-top: 4px !important; }
                    .padding-mobile { padding: 25px 20px 20px 20px !important; }
                    .card-padding-mobile { padding: 0 20px 25px 20px !important; }
                    .wrapper-padding-mobile { padding: 20px 10px !important; }
                    .header-mobile { padding: 28px 20px !important; }
                    .cta-button { padding: 14px 28px !important; font-size: 15px !important; white-space: normal !important; }
                }
            </style>
        </head>
<?php
        return ob_get_clean();
    }

    /**
     * Khối body chính (header, content, course card, CTA) của email học viên.
     *
     * @param string $cleanname Tên học viên đã escape
     * @param string $coursename Tên khóa học đã escape
     * @param string $useremail Email đã escape
     * @param string $courseurl URL khóa học đã escape
     * @param string $platformlabel Nhãn nền tảng email
     * @return string
     */
    private static function student_body($cleanname, $coursename, $useremail, $courseurl, $platformlabel): string {
        ob_start();
        ?>
        <body class="body-bg" style="margin: 0; padding: 0; background-color: #f3f4f6; -webkit-font-smoothing: antialiased; word-break: break-word;">
            <div style="display:none;font-size:1px;color:#f3f4f6;line-height:1px;max-height:0px;max-width:0px;opacity:0;overflow:hidden;">Hệ thống đã xác nhận thanh toán và hoàn tất thủ tục ghi danh. Chúc mừng bạn đã chính thức tham gia khóa học...</div>
            <table class="body-bg" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="background-color: #f3f4f6; margin: 0; padding: 0; width: 100%; table-layout: fixed;">
                <tr>
                    <td align="center" class="wrapper-padding-mobile" style="padding: 25px 15px;">
                        <!--[if mso]>
                <table align="center" width="600" border="0" cellpadding="0" cellspacing="0" role="presentation">
                <tr><td>
                <![endif]-->
                <table class="content-bg" align="center" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="max-width: 600px; background-color: #ffffff; border-radius: 24px; overflow: hidden; border: 1px solid #e2e8f0; border-bottom: 4px solid #cbd5e1; box-shadow: 0 15px 35px rgba(0,0,0,0.05); border-collapse: separate; table-layout: fixed; mso-table-lspace: 0pt; mso-table-rspace: 0pt;">
                    
                    <!-- HEADER -->
                    <tr>
                        <td class="header-mobile" style="background-color: #d72134; background-image: linear-gradient(150deg, #f24f60 0%, #d72134 40%, #941322 100%); padding: 45px 25px; text-align: center; border-bottom: 5px solid #7d101c; border-top: 1px solid #ff8f9c; box-shadow: inset 0 -10px 20px rgba(0,0,0,0.15), inset 0 2px 10px rgba(255,255,255,0.3);">
                            <div class="icon-bg" style="background: rgba(255, 255, 255, 0.1); border-top: 2px solid rgba(255, 255, 255, 0.5); border-left: 2px solid rgba(255, 255, 255, 0.3); border-right: 1px solid rgba(255,255,255,0.05); border-bottom: 1px solid rgba(255,255,255,0.05); width: 76px; height: 76px; border-radius: 40%; display: inline-block; margin-bottom: 25px; box-shadow: 0 12px 25px rgba(125, 16, 28, 0.5), inset 0 4px 12px rgba(255,255,255,0.4); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);">
                                <p style="font-size: 38px; margin: 0; line-height: 76px; display: block; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3));">🚀</p>
                            </div>
                            <h1 style="color: #ffffff; font-size: 28px; font-weight: 700; margin: 0; letter-spacing: 1px; text-transform: uppercase; text-shadow: 0 2px 4px rgba(0,0,0,0.2); font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">XÁC NHẬN GHI DANH!</h1>
                        </td>
                    </tr>

                    <!-- CONTENT -->
                    <tr>
                        <td class="padding-mobile" style="padding: 35px 30px 25px 30px; font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                            <p class="text-main" style="font-size: 19px; color: #1e293b; margin: 0 0 15px 0; line-height: 1.5; font-weight: 700;">Xin chào <span class="highlight-red" style="color: #d72134;"><?= $cleanname ?></span> 👋,</p>
                            <p class="text-sub" style="font-size: 16px; color: #475569; margin: 0; line-height: 1.7; text-align: justify;">🎉 Hệ thống đã xác nhận thanh toán và hoàn tất thủ tục ghi danh. Chúc mừng bạn đã chính thức tham gia khóa học này, hãy sẵn sàng ôn tập thật tốt để đạt kết quả cao trong kỳ thi sắp tới cùng Quiz Văn Lang nhé!</p>
                        </td>
                    </tr>

                    <!-- COURSE CARD -->
                    <tr>
                        <td class="card-padding-mobile" style="padding: 0 30px 30px 30px; font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                            <div class="card-bg-red" style="background-color: #fce8ea; border: 1px solid #fad1d5; border-top: 2px solid #ffffff; border-bottom: 3px solid #f4a2ab; border-radius: 20px; padding: 25px; box-shadow: 0 4px 15px rgba(215, 33, 52, 0.05), inset 0 2px 0 rgba(255,255,255,0.6);">
                                
                                <div style="text-align: center; margin-bottom: 20px;">
                                    <span style="display: inline-block; background: #d72134; color: #ffffff; font-size: 13px; font-weight: 700; padding: 6px 16px; border-radius: 20px; text-transform: uppercase; letter-spacing: 1px; box-shadow: 0 4px 8px rgba(215, 33, 52, 0.2);">📚 Thông tin khóa học</span>
                                </div>
                                
                                <strong class="highlight-red" style="color: #bd1a2c; display: block; font-size: 24px; line-height: 1.4; font-weight: 700; text-align: center; margin-bottom: 25px; text-shadow: 0 1px 1px rgba(255,255,255,0.8); font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;"><?= $coursename ?></strong>
                                
                                <div class="dashed-border" style="border-top: 1px dashed #f4a2ab; padding: 12px 0; overflow: hidden; clear: both;">
                                    <!--[if mso]><table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation"><tr><td width="35%" align="left" valign="top"><![endif]-->
                                    <span class="stack-mobile" style="font-size: 15px; color: #64748b; font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">🗓️ Thời gian:</span>
                                    <!--[if mso]></td><td width="65%" align="right" valign="top"><![endif]-->
                                    <span class="stack-mobile value-mobile" style="font-size: 15px; font-weight: 700; color: #0f172a; float: right; text-align: right; word-break: break-word; max-width: 65%; font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;"><?= date('d/m/Y - H:i') ?></span>
                                    <!--[if mso]></td></tr></table><![endif]-->
                                </div>

                                <div class="dashed-border" style="border-top: 1px dashed #f4a2ab; padding: 12px 0; overflow: hidden; clear: both;">
                                    <!--[if mso]><table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation"><tr><td width="35%" align="left" valign="top"><![endif]-->
                                    <span class="stack-mobile" style="font-size: 15px; color: #64748b; font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">👤 Học viên:</span>
                                    <!--[if mso]></td><td width="65%" align="right" valign="top"><![endif]-->
                                    <span class="stack-mobile value-mobile" style="font-size: 15px; font-weight: 700; color: #0f172a; float: right; text-align: right; word-break: break-word; max-width: 65%; font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;"><?= $cleanname ?></span>
                                    <!--[if mso]></td></tr></table><![endif]-->
                                </div>

                                <div class="dashed-border" style="border-top: 1px dashed #f4a2ab; padding: 12px 0 0 0; overflow: hidden; clear: both;">
                                    <!--[if mso]><table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation"><tr><td width="35%" align="left" valign="top"><![endif]-->
                                    <span class="stack-mobile" style="font-size: 15px; color: #64748b; font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;"><?= $platformlabel ?></span>
                                    <!--[if mso]></td><td width="65%" align="right" valign="top"><![endif]-->
                                    <span class="stack-mobile value-mobile" style="font-size: 15px; font-weight: 700; color: #0f172a; float: right; text-align: right; word-break: break-all; max-width: 65%; font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;"><?= $useremail ?></span>
                                    <!--[if mso]></td></tr></table><![endif]-->
                                </div>
                            </div>
                        </td>
                    </tr>

                    <!-- CTA -->
                    <tr>
                        <td class="card-padding-mobile" style="padding: 0 30px 40px 30px; text-align: center; font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                            <!--[if mso]>
                            <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="<?= $courseurl ?>" style="height:56px;v-text-anchor:middle;width:260px;" arcsize="50%" strokecolor="#941322" strokeweight="2px" fillcolor="#d72134">
                                <w:anchorlock/>
                                <center style="color:#ffffff;font-family:'Segoe UI', sans-serif;font-size:16px;font-weight:bold;">VÀO HỌC NGAY</center>
                            </v:roundrect>
                            <![endif]-->
                            <!--[if !mso]><!-->
                            <a href="<?= $courseurl ?>" class="cta-button" target="_blank" style="display: inline-block; background-color: #d72134; background-image: linear-gradient(135deg, rgba(255,255,255,0.4) 0%, rgba(255,255,255,0.05) 45%, rgba(0,0,0,0.05) 55%, rgba(0,0,0,0.15) 100%); color: #ffffff; text-decoration: none; font-size: 16px; font-weight: 700; padding: 15px 36px; border-radius: 50px; border: 1px solid rgba(255, 255, 255, 0.5); box-shadow: 0 10px 30px -5px rgba(215, 33, 52, 0.4), inset 0 2px 4px rgba(255, 255, 255, 0.6), inset 0 -4px 6px rgba(0, 0, 0, 0.2), inset 0 0 10px rgba(255,255,255,0.1); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); text-transform: uppercase; letter-spacing: 1.5px; transition: all 0.3s ease; white-space: nowrap;"><span style="text-shadow: 0 2px 4px rgba(0,0,0,0.3);">Vào Học Ngay</span></a>
                            <!--<![endif]-->
                        </td>
                    </tr>

<?php
        return ob_get_clean();
    }

    /**
     * Khối community box (Zalo) của email học viên — ẩn với email \@vanlanguni.vn.
     *
     * @param \stdClass $a Du lieu email
     * @return string Chuoi rong neu la email \@vanlanguni.vn
     */
    private static function student_zalo_box($a): string {
        ob_start();
        ?>
                    <?php if (stripos($a->useremail, '@vanlanguni.vn') === false) : ?>
                    <!-- COMMUNITY BOX (ZALO) -->
                    <tr>
                        <td class="card-padding-mobile" style="padding: 0 30px 30px 30px; font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                            <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="border-collapse: separate; border-spacing: 0;">
                                <tr>
                                    <td class="card-bg-blue" style="background-color: #eff6ff; border: 1px solid #dbeafe; border-top: 2px solid #ffffff; border-bottom: 3px solid #bfdbfe; border-radius: 20px; padding: 30px 25px; text-align: center; box-shadow: 0 4px 15px rgba(59, 130, 246, 0.05), inset 0 2px 0 rgba(255,255,255,0.6);">
                                        <?php $zalouri = self::logo_data_uri('zalo_icon.png', 60, 60); ?>
                                        <?php if ($zalouri) : ?>
                                        <img src="<?= $zalouri ?>" width="60" height="60" alt="Zalo Logo" style="display: block; margin: 0 auto 15px auto; border-radius: 16px; filter: drop-shadow(0 4px 10px rgba(0,0,0,0.15));">
                                        <?php else : ?>
                                        <div style="width:60px;height:60px;margin:0 auto 15px auto;background:#0068ff;border-radius:16px;display:flex;align-items:center;justify-content:center;"><span style="color:#fff;font-size:28px;font-weight:900;line-height:60px;text-align:center;display:block;">Z</span></div>
                                        <?php endif; ?>
                                        <h4 class="text-main" style="margin: 0 0 10px 0; color: #1e3a8a; font-size: 18px; font-weight: 700; text-shadow: 0 1px 1px rgba(255,255,255,0.8);">Cộng Đồng Quiz Văn Lang</h4>
                                        <p class="text-sub" style="font-size: 15px; color: #3b82f6; margin: 0 0 20px 0; line-height: 1.5;">Tham gia cộng đồng bên dưới<br>để không bỏ lỡ các khóa học mới nhất nhé!</p>
                                        <!--[if mso]>
                                        <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="https://zalo.me/g/eds5dzskgzc7eu4essgc" style="height:44px;v-text-anchor:middle;width:180px;" arcsize="50%" strokecolor="#1e3a8a" strokeweight="2px" fillcolor="#2563eb">
                                            <w:anchorlock/>
                                            <center style="color:#ffffff;font-family:'Segoe UI', sans-serif;font-size:14px;font-weight:bold;">Tham Gia Ngay</center>
                                        </v:roundrect>
                                        <![endif]-->
                                        <!--[if !mso]><!-->
                                        <a href="https://zalo.me/g/eds5dzskgzc7eu4essgc" target="_blank" style="display: inline-block; background-color: #2563eb; background-image: linear-gradient(135deg, rgba(255,255,255,0.4) 0%, rgba(255,255,255,0.05) 45%, rgba(0,0,0,0.05) 55%, rgba(0,0,0,0.15) 100%); color: #ffffff; font-size: 14px; font-weight: 700; text-decoration: none; padding: 14px 35px; border-radius: 50px; border: 1px solid rgba(255, 255, 255, 0.5); box-shadow: 0 10px 25px -5px rgba(37, 99, 235, 0.4), inset 0 2px 4px rgba(255, 255, 255, 0.6), inset 0 -3px 5px rgba(0, 0, 0, 0.2), inset 0 0 10px rgba(255,255,255,0.1); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); text-transform: uppercase; letter-spacing: 1px; transition: all 0.3s ease;"><span style="text-shadow: 0 2px 4px rgba(0,0,0,0.3);">Tham Gia Ngay</span></a>
                                        <!--<![endif]-->
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <?php endif; ?>
<?php
        return ob_get_clean();
    }

    /**
     * Khối footer + đóng thẻ của email học viên.
     *
     * @param \stdClass $a Du lieu email
     * @return string
     */
    private static function student_footer($a): string {
        ob_start();
        ?>

                    <!-- FOOTER -->
                    <tr>
                        <td class="content-bg" style="background-color: #f8fafc; padding: 30px 25px; text-align: center; border-top: 1px solid #e2e8f0; font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                            <?php if (stripos($a->useremail, '@vanlanguni.vn') === false) : ?>
                            <p class="footer-text" style="font-size: 14px; color: #64748b; margin: 0; line-height: 1.5;">💡 Nếu chưa vào được khóa học, inbox Zalo Admin:<br><a href="https://zalo.me/0588325106" target="_blank" style="color: #2563eb; text-decoration: none; font-weight: 700; background: #e0e7ff; padding: 4px 12px; border-radius: 12px; display: inline-block; margin-top: 8px;">0588.325.106</a></p>
                            <?php else : ?>
                            <p class="footer-text" style="font-size: 14px; color: #64748b; margin: 0; line-height: 1.5;">💡 Nếu chưa vào được khóa học, liên hệ SĐT Zalo Admin:<br><strong style="color: #475569; display: inline-block; margin-top: 8px;">0588.325.106</strong></p>
                            <?php endif; ?>
                            <div class="divider-line" style="width: 50px; height: 2px; background-color: #cbd5e1; margin: 20px auto; border-radius: 2px;"></div>
                            <p class="footer-text" style="font-size: 12px; color: #94a3b8; margin: 0; letter-spacing: 0.5px; text-transform: uppercase;">&copy; <?= date('Y') ?> <strong>Quiz Văn Lang</strong>. Mọi quyền được bảo lưu.</p>
                        </td>
                    </tr>
                </table>
                <!--[if mso]>
                </td></tr>
                </table>
                <![endif]-->
                    </td>
                </tr>
            </table>
            
            <!-- HIDDEN MOODLE APP WRAPPER TRICK -->
            <div style="display: none !important; mso-hide: all; visibility: hidden; width: 0; height: 0; font-size: 0;">
                <table border="0" cellpadding="0" cellspacing="0" role="presentation"><tr><td>
                </td></tr></table>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Dựng HTML email thông báo cho admin khi có giao dịch/ghi danh mới.
     *
     * @param \stdClass $a Du lieu email (username, coursename, useremail, profileurl)
     * @return string Nội dung HTML của email
     */
    public static function get_admin_email_html($a) {
        $cleanname     = htmlspecialchars(self::clean_student_name($a->username), ENT_QUOTES, 'UTF-8');
        $coursename     = htmlspecialchars($a->coursename, ENT_QUOTES, 'UTF-8');
        $useremail      = htmlspecialchars($a->useremail, ENT_QUOTES, 'UTF-8');
        $profileurl     = htmlspecialchars($a->profileurl, ENT_QUOTES, 'UTF-8');
        $platformlabel = self::get_email_platform_label($a->useremail);
        return self::admin_head()
            . self::admin_body($cleanname, $coursename, $useremail, $profileurl, $platformlabel);
    }
    /**
     * Khối <head> + style của email admin.
     *
     * @return string
     */
    private static function admin_head(): string {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="vi" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
        <head>
            <meta charset="UTF-8">
            <title>Thông báo từ hệ thống</title>
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
            <meta name="color-scheme" content="light dark">
            <meta name="supported-color-schemes" content="light dark">
            <!--[if mso]>
            <xml>
                <o:OfficeDocumentSettings>
                    <o:AllowPNG/>
                    <o:PixelsPerInch>96</o:PixelsPerInch>
                </o:OfficeDocumentSettings>
            </xml>
            <![endif]-->
            <style>
                :root {
                    color-scheme: light dark;
                    supported-color-schemes: light dark;
                }
                @media (prefers-color-scheme: dark) {
                    .body-bg { background-color: #020617 !important; }
                    .content-bg { background-color: #0f172a !important; border-color: #1e293b !important; border-bottom-color: #1e293b !important; }
                    .text-main { color: #f8fafc !important; text-shadow: none !important; }
                    .text-sub { color: #94a3b8 !important; }
                    .card-bg-green { background-color: #022c1e !important; border-color: #064e3b !important; border-top-color: #033a28 !important; border-bottom-color: #011810 !important; box-shadow: none !important; }
                    .dashed-border { border-top-color: #334155 !important; border-bottom-color: #334155 !important; }
                    .footer-text { color: #64748b !important; }
                    .highlight-green { color: #34d399 !important; text-shadow: none !important; }
                    .icon-bg { background-color: rgba(0,0,0,0.2) !important; border-color: rgba(255,255,255,0.1) !important; }
                    .divider-line { background-color: #334155 !important; }
                }
                /* Prevent font boosting in Gmail */
                body, table, td, p, a, li, blockquote {
                    -webkit-text-size-adjust: 100%;
                    -ms-text-size-adjust: 100%;
                }
                /* RESPONSIVE STYLES */
                @media screen and (max-width: 600px) {
                    .stack-mobile { display: block !important; width: 100% !important; min-width: 100% !important; max-width: 100% !important; float: none !important; text-align: left !important; box-sizing: border-box !important; }
                    .value-mobile { float: none !important; text-align: left !important; max-width: 100% !important; padding-top: 4px !important; }
                    .padding-mobile { padding: 25px 20px 20px 20px !important; }
                    .card-padding-mobile { padding: 0 20px 25px 20px !important; }
                    .wrapper-padding-mobile { padding: 20px 10px !important; }
                    .header-mobile { padding: 28px 20px !important; }
                    .cta-button { padding: 14px 28px !important; font-size: 15px !important; white-space: normal !important; }
                }
            </style>
        </head>
<?php
        return ob_get_clean();
    }

    /**
     * Khối body (header, content, transaction card, CTA, footer) của email admin.
     *
     * @param string $cleanname Tên học viên đã escape
     * @param string $coursename Tên khóa học đã escape
     * @param string $useremail Email đã escape
     * @param string $profileurl URL hồ sơ đã escape
     * @param string $platformlabel Nhãn nền tảng email
     * @return string
     */
    private static function admin_body($cleanname, $coursename, $useremail, $profileurl, $platformlabel): string {
        ob_start();
        ?>
        <body class="body-bg" style="margin: 0; padding: 0; background-color: #f3f4f6; -webkit-font-smoothing: antialiased; word-break: break-word;">
            <div style="display:none;font-size:1px;color:#f3f4f6;line-height:1px;max-height:0px;max-width:0px;opacity:0;overflow:hidden;">Hệ thống SePay vừa ghi nhận một khoản thanh toán mới và tự động kích hoạt tài khoản thành công...</div>
            <table class="body-bg" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="background-color: #f3f4f6; margin: 0; padding: 0; width: 100%; table-layout: fixed;">
                <tr>
                    <td align="center" class="wrapper-padding-mobile" style="padding: 25px 15px;">
                        <!--[if mso]>
                <table align="center" width="600" border="0" cellpadding="0" cellspacing="0" role="presentation">
                <tr><td>
                <![endif]-->
                <table class="content-bg" align="center" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="max-width: 600px; background-color: #ffffff; border-radius: 24px; overflow: hidden; border: 1px solid #e2e8f0; border-bottom: 4px solid #cbd5e1; box-shadow: 0 15px 35px rgba(0,0,0,0.05); border-collapse: separate; table-layout: fixed; mso-table-lspace: 0pt; mso-table-rspace: 0pt;">
                    
                    <!-- HEADER -->
                    <tr>
                        <td class="header-mobile" style="background-color: #059669; background-image: linear-gradient(150deg, #34d399 0%, #059669 40%, #064e3b 100%); padding: 45px 25px; text-align: center; border-bottom: 5px solid #022c1e; border-top: 1px solid #6ee7b7; box-shadow: inset 0 -10px 20px rgba(0,0,0,0.15), inset 0 2px 10px rgba(255,255,255,0.3);">
                            <div class="icon-bg" style="background: rgba(255, 255, 255, 0.1); border-top: 2px solid rgba(255, 255, 255, 0.5); border-left: 2px solid rgba(255, 255, 255, 0.3); border-right: 1px solid rgba(255,255,255,0.05); border-bottom: 1px solid rgba(255,255,255,0.05); width: 76px; height: 76px; border-radius: 40%; display: inline-block; margin-bottom: 25px; box-shadow: 0 12px 25px rgba(2, 44, 30, 0.5), inset 0 4px 12px rgba(255,255,255,0.4); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);">
                                <p style="font-size: 38px; margin: 0; line-height: 76px; display: block; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3));">💰</p>
                            </div>
                            <h1 style="color: #ffffff; font-size: 28px; font-weight: 700; margin: 0; letter-spacing: 1px; text-transform: uppercase; text-shadow: 0 2px 4px rgba(0,0,0,0.2); font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">DOANH THU MỚI!</h1>
                        </td>
                    </tr>

                    <!-- CONTENT -->
                    <tr>
                        <td class="padding-mobile" style="padding: 35px 30px 25px 30px; font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                            <p class="text-main" style="font-size: 19px; color: #1e293b; margin: 0 0 15px 0; line-height: 1.5; font-weight: 700;">Tin vui báo về 🚀,</p>
                            <p class="text-sub" style="font-size: 16px; color: #475569; margin: 0; line-height: 1.7; text-align: left;">Hệ thống SePay vừa ghi nhận một khoản thanh toán mới và tự động kích hoạt tài khoản thành công cho học viên. Dưới đây là thông tin chi tiết biên lai doanh thu của bạn.</p>
                        </td>
                    </tr>

                    <!-- TRANSACTION CARD -->
                    <tr>
                        <td class="card-padding-mobile" style="padding: 0 30px 30px 30px; font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                            <div class="card-bg-green" style="background-color: #ecfdf5; border: 1px solid #d1fae5; border-top: 2px solid #ffffff; border-bottom: 3px solid #a7f3d0; border-radius: 20px; padding: 25px; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.05), inset 0 2px 0 rgba(255,255,255,0.6);">
                                
                                <div style="text-align: center; margin-bottom: 20px;">
                                    <span style="display: inline-block; background: #10b981; color: #ffffff; font-size: 13px; font-weight: 700; padding: 6px 16px; border-radius: 20px; text-transform: uppercase; letter-spacing: 1px; box-shadow: 0 4px 8px rgba(16, 185, 129, 0.2);">🧾 Chi Tiết Giao Dịch</span>
                                </div>
                                
                                <strong class="highlight-green" style="color: #064e3b; display: block; font-size: 24px; line-height: 1.4; font-weight: 700; text-align: center; margin-bottom: 25px; text-shadow: 0 1px 1px rgba(255,255,255,0.8); font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;"><?= $coursename ?></strong>
                                
                                <div class="dashed-border" style="border-top: 1px dashed #a7f3d0; padding: 12px 0; overflow: hidden; clear: both;">
                                    <!--[if mso]><table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation"><tr><td width="35%" align="left" valign="top"><![endif]-->
                                    <span class="stack-mobile" style="font-size: 15px; color: #64748b; font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">🗓️ Thời gian:</span>
                                    <!--[if mso]></td><td width="65%" align="right" valign="top"><![endif]-->
                                    <span class="stack-mobile value-mobile" style="font-size: 15px; font-weight: 700; color: #0f172a; float: right; text-align: right; word-break: break-word; max-width: 65%; font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;"><?= date('d/m/Y - H:i') ?></span>
                                    <!--[if mso]></td></tr></table><![endif]-->
                                </div>

                                <div class="dashed-border" style="border-top: 1px dashed #a7f3d0; padding: 12px 0; overflow: hidden; clear: both;">
                                    <!--[if mso]><table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation"><tr><td width="35%" align="left" valign="top"><![endif]-->
                                    <span class="stack-mobile" style="font-size: 15px; color: #64748b; font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">👤 Học viên:</span>
                                    <!--[if mso]></td><td width="65%" align="right" valign="top"><![endif]-->
                                    <span class="stack-mobile value-mobile" style="font-size: 15px; font-weight: 700; color: #0f172a; float: right; text-align: right; word-break: break-word; max-width: 65%; font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;"><?= $cleanname ?></span>
                                    <!--[if mso]></td></tr></table><![endif]-->
                                </div>

                                <div class="dashed-border" style="border-top: 1px dashed #a7f3d0; padding: 12px 0 0 0; overflow: hidden; clear: both;">
                                    <!--[if mso]><table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation"><tr><td width="35%" align="left" valign="top"><![endif]-->
                                    <span class="stack-mobile" style="font-size: 15px; color: #64748b; font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;"><?= $platformlabel ?></span>
                                    <!--[if mso]></td><td width="65%" align="right" valign="top"><![endif]-->
                                    <span class="stack-mobile value-mobile" style="font-size: 15px; font-weight: 700; color: #0f172a; float: right; text-align: right; word-break: break-all; max-width: 65%; font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;"><?= $useremail ?></span>
                                    <!--[if mso]></td></tr></table><![endif]-->
                                </div>
                            </div>
                        </td>
                    </tr>

                    <!-- CTA -->
                    <tr>
                        <td class="card-padding-mobile" style="padding: 0 30px 40px 30px; text-align: center; font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                            <!--[if mso]>
                            <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="<?= $profileurl ?>" style="height:56px;v-text-anchor:middle;width:280px;" arcsize="50%" strokecolor="#064e3b" strokeweight="2px" fillcolor="#059669">
                                <w:anchorlock/>
                                <center style="color:#ffffff;font-family:'Segoe UI', sans-serif;font-size:16px;font-weight:bold;">XEM HỒ SƠ HỌC VIÊN</center>
                            </v:roundrect>
                            <![endif]-->
                            <!--[if !mso]><!-->
                            <a href="<?= $profileurl ?>" class="cta-button" target="_blank" style="display: inline-block; background-color: #059669; background-image: linear-gradient(135deg, rgba(255,255,255,0.4) 0%, rgba(255,255,255,0.05) 45%, rgba(0,0,0,0.05) 55%, rgba(0,0,0,0.15) 100%); color: #ffffff; text-decoration: none; font-size: 16px; font-weight: 700; padding: 15px 36px; border-radius: 50px; border: 1px solid rgba(255, 255, 255, 0.5); box-shadow: 0 10px 30px -5px rgba(5, 150, 105, 0.4), inset 0 2px 4px rgba(255, 255, 255, 0.6), inset 0 -4px 6px rgba(0, 0, 0, 0.2), inset 0 0 10px rgba(255,255,255,0.1); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); text-transform: uppercase; letter-spacing: 1.5px; transition: all 0.3s ease; white-space: nowrap;"><span style="text-shadow: 0 2px 4px rgba(0,0,0,0.3);">Xem Hồ Sơ Học Viên</span></a>
                            <!--<![endif]-->
                        </td>
                    </tr>

                    <!-- FOOTER -->
                    <tr>
                        <td class="content-bg" style="background-color: #f8fafc; padding: 30px 25px; text-align: center; border-top: 1px solid #e2e8f0; font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;">
                            <p class="footer-text" style="font-size: 14px; color: #64748b; margin: 0 0 12px 0; line-height: 1.5; font-weight: 600;">⚡ Hệ thống thanh toán tự động qua SePay</p>
                            <div class="divider-line" style="width: 50px; height: 2px; background-color: #cbd5e1; margin: 20px auto; border-radius: 2px;"></div>
                            <p class="footer-text" style="font-size: 12px; color: #94a3b8; margin: 0; letter-spacing: 0.5px; text-transform: uppercase;">&copy; <?= date('Y') ?> <strong>Hệ thống quản lý Quiz Văn Lang</strong>.<br>Bảo mật & Tự động hoàn toàn.</p>
                        </td>
                    </tr>
                </table>
                <!--[if mso]>
                </td></tr>
                </table>
                <![endif]-->
                    </td>
                </tr>
            </table>

            <!-- HIDDEN MOODLE APP WRAPPER TRICK -->
            <div style="display: none !important; mso-hide: all; visibility: hidden; width: 0; height: 0; font-size: 0;">
                <table border="0" cellpadding="0" cellspacing="0" role="presentation"><tr><td>
                </td></tr></table>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Dựng HTML email thông báo từ chối ghi danh — màu cam, cùng chuẩn với template ghi danh.
     *
     * @param \stdClass $a Du lieu email (username, coursename, useremail, courseurl)
     * @return string Nội dung HTML của email
     */
    public static function get_rejection_email_html($a): string {
        $cleanname     = htmlspecialchars(self::clean_student_name($a->username), ENT_QUOTES, 'UTF-8');
        $coursename     = htmlspecialchars($a->coursename, ENT_QUOTES, 'UTF-8');
        $useremail      = htmlspecialchars($a->useremail, ENT_QUOTES, 'UTF-8');
        $courseurl      = htmlspecialchars($a->courseurl, ENT_QUOTES, 'UTF-8');
        $platformlabel = self::get_email_platform_label($a->useremail);
        return self::rejection_head()
            . self::rejection_body($cleanname, $coursename, $useremail, $courseurl, $platformlabel)
            . self::rejection_zalo_box($a)
            . self::rejection_footer($a);
    }
    /**
     * Khối <head> + style của email từ chối.
     *
     * @return string
     */
    private static function rejection_head(): string {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="vi" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
        <head>
            <meta charset="UTF-8">
            <title>Thông báo từ hệ thống</title>
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
            <meta name="color-scheme" content="light dark">
            <meta name="supported-color-schemes" content="light dark">
            <!--[if mso]>
            <xml><o:OfficeDocumentSettings><o:AllowPNG/><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml>
            <![endif]-->
            <style>
                :root { color-scheme: light dark; supported-color-schemes: light dark; }
                @media (prefers-color-scheme: dark) {
                    .body-bg    { background-color: #020617 !important; }
                    .content-bg { background-color: #0f172a !important; border-color: #1e293b !important; border-bottom-color: #1e293b !important; }
                    .text-main  { color: #f8fafc !important; text-shadow: none !important; }
                    .text-sub   { color: #94a3b8 !important; }
                    .card-bg    { background-color: #2a1a0a !important; border-color: #7c3a10 !important; border-bottom-color: #4a1f06 !important; box-shadow: none !important; }
                    .card-bg-blue { background-color: #0d1b2a !important; border-color: #1e3a8a !important; border-bottom-color: #080f1a !important; box-shadow: none !important; }
                    .dashed-border { border-top-color: #334155 !important; }
                    .divider-line { background-color: #334155 !important; }
                    .footer-text { color: #64748b !important; }
                    .highlight  { color: #fb923c !important; }
                    .icon-bg    { background-color: rgba(0,0,0,0.2) !important; }
                }
                body, table, td, p, a, li { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
                @media screen and (max-width: 600px) {
                    .stack-mobile { display: block !important; width: 100% !important; float: none !important; text-align: left !important; box-sizing: border-box !important; }
                    .value-mobile { float: none !important; text-align: left !important; max-width: 100% !important; padding-top: 4px !important; }
                    .padding-mobile  { padding: 25px 20px 20px 20px !important; }
                    .card-padding-mobile { padding: 0 20px 25px 20px !important; }
                    .wrapper-padding-mobile { padding: 20px 10px !important; }
                    .header-mobile { padding: 28px 20px !important; }
                    .cta-button { padding: 14px 28px !important; font-size: 15px !important; }
                }
            </style>
        </head>
<?php
        return ob_get_clean();
    }

    /**
     * Khối body (header, content, course card, CTA) của email từ chối.
     *
     * @param string $cleanname Tên học viên đã escape
     * @param string $coursename Tên khóa học đã escape
     * @param string $useremail Email đã escape
     * @param string $courseurl URL khóa học đã escape
     * @param string $platformlabel Nhãn nền tảng email
     * @return string
     */
    private static function rejection_body($cleanname, $coursename, $useremail, $courseurl, $platformlabel): string {
        ob_start();
        ?>
        <body class="body-bg" style="margin:0;padding:0;background-color:#fff7ed;-webkit-font-smoothing:antialiased;word-break:break-word;">
            <div style="display:none;font-size:1px;color:#fff7ed;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">Rất tiếc, yêu cầu ghi danh khóa học của bạn đã bị từ chối...</div>
            <table class="body-bg" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="background-color:#fff7ed;margin:0;padding:0;width:100%;table-layout:fixed;">
                <tr>
                    <td align="center" class="wrapper-padding-mobile" style="padding:25px 15px;">
                        <!--[if mso]><table align="center" width="600" border="0" cellpadding="0" cellspacing="0" role="presentation"><tr><td><![endif]-->
                        <table class="content-bg" align="center" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="max-width:600px;background-color:#ffffff;border-radius:24px;overflow:hidden;border:1px solid #fed7aa;border-bottom:4px solid #fdba74;box-shadow:0 15px 35px rgba(0,0,0,0.05);border-collapse:separate;table-layout:fixed;mso-table-lspace:0pt;mso-table-rspace:0pt;">

                            <!-- HEADER -->
                            <tr>
                                <td class="header-mobile" style="background-color:#ea580c;background-image:linear-gradient(150deg,#fb923c 0%,#ea580c 40%,#9a3412 100%);padding:45px 25px;text-align:center;border-bottom:5px solid #7c2d12;border-top:1px solid #fba97a;box-shadow:inset 0 -10px 20px rgba(0,0,0,0.15),inset 0 2px 10px rgba(255,255,255,0.3);">
                                    <div class="icon-bg" style="background:rgba(255,255,255,0.1);border-top:2px solid rgba(255,255,255,0.5);border-left:2px solid rgba(255,255,255,0.3);border-right:1px solid rgba(255,255,255,0.05);border-bottom:1px solid rgba(255,255,255,0.05);width:76px;height:76px;border-radius:40%;display:inline-block;margin-bottom:25px;box-shadow:0 12px 25px rgba(124,45,18,0.5),inset 0 4px 12px rgba(255,255,255,0.4);">
                                        <p style="font-size:38px;margin:0;line-height:76px;display:block;filter:drop-shadow(0 4px 6px rgba(0,0,0,0.3));">⚠️</p>
                                    </div>
                                    <h1 style="color:#ffffff;font-size:28px;font-weight:700;margin:0;letter-spacing:1px;text-transform:uppercase;text-shadow:0 2px 4px rgba(0,0,0,0.2);font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;">YÊU CẦU BỊ TỪ CHỐI!</h1>
                                </td>
                            </tr>

                            <!-- CONTENT -->
                            <tr>
                                <td class="padding-mobile" style="padding:35px 30px 25px 30px;font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
                                    <p class="text-main" style="font-size:19px;color:#1e293b;margin:0 0 15px 0;line-height:1.5;font-weight:700;">Xin chào <span class="highlight" style="color:#ea580c;"><?= $cleanname ?></span> 👋,</p>
                                    <p class="text-sub" style="font-size:16px;color:#475569;margin:0;line-height:1.7;text-align:justify;">Rất tiếc, yêu cầu ghi danh khóa học của bạn đã bị từ chối. Vui lòng liên hệ với quản trị viên nếu bạn cho rằng đây là sự nhầm lẫn hoặc bạn muốn thử lại.</p>
                                </td>
                            </tr>

                            <!-- COURSE CARD -->
                            <tr>
                                <td class="card-padding-mobile" style="padding:0 30px 30px 30px;font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
                                    <div class="card-bg" style="background-color:#fff7ed;border:1px solid #fed7aa;border-top:2px solid #ffffff;border-bottom:3px solid #fdba74;border-radius:20px;padding:25px;box-shadow:0 4px 15px rgba(234,88,12,0.05),inset 0 2px 0 rgba(255,255,255,0.6);">
                                        <div style="text-align:center;margin-bottom:20px;">
                                            <span style="display:inline-block;background:#ea580c;color:#ffffff;font-size:13px;font-weight:700;padding:6px 16px;border-radius:20px;text-transform:uppercase;letter-spacing:1px;box-shadow:0 4px 8px rgba(234,88,12,0.2);">📚 Thông tin khóa học</span>
                                        </div>
                                        <strong class="highlight" style="color:#9a3412;display:block;font-size:22px;line-height:1.4;font-weight:700;text-align:center;margin-bottom:25px;font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;"><?= $coursename ?></strong>
                                        <div style="border-top:1px dashed #fdba74;padding:12px 0;overflow:hidden;clear:both;">
                                            <span class="stack-mobile" style="font-size:15px;color:#64748b;font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;">🗓️ Thời gian:</span>
                                            <span class="stack-mobile value-mobile" style="font-size:15px;font-weight:700;color:#0f172a;float:right;text-align:right;word-break:break-word;max-width:65%;font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;"><?= date('d/m/Y - H:i') ?></span>
                                        </div>
                                        <div style="border-top:1px dashed #fdba74;padding:12px 0;overflow:hidden;clear:both;">
                                            <span class="stack-mobile" style="font-size:15px;color:#64748b;font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;">👤 Học viên:</span>
                                            <span class="stack-mobile value-mobile" style="font-size:15px;font-weight:700;color:#0f172a;float:right;text-align:right;word-break:break-word;max-width:65%;font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;"><?= $cleanname ?></span>
                                        </div>
                                        <div style="border-top:1px dashed #fdba74;padding:12px 0 0 0;overflow:hidden;clear:both;">
                                            <span class="stack-mobile" style="font-size:15px;color:#64748b;font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;"><?= $platformlabel ?></span>
                                            <span class="stack-mobile value-mobile" style="font-size:15px;font-weight:700;color:#0f172a;float:right;text-align:right;word-break:break-all;max-width:65%;font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;"><?= $useremail ?></span>
                                        </div>
                                    </div>
                                </td>
                            </tr>

                            <!-- CTA -->
                            <tr>
                                <td class="card-padding-mobile" style="padding:0 30px 40px 30px;text-align:center;font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
                                    <!--[if mso]>
                                    <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="<?= $courseurl ?>" style="height:56px;v-text-anchor:middle;width:260px;" arcsize="50%" strokecolor="#7c2d12" strokeweight="2px" fillcolor="#ea580c">
                                        <w:anchorlock/>
                                        <center style="color:#ffffff;font-family:'Segoe UI',sans-serif;font-size:16px;font-weight:bold;">XEM KHÓA HỌC</center>
                                    </v:roundrect>
                                    <![endif]-->
                                    <!--[if !mso]><!-->
                                    <a href="<?= $courseurl ?>" class="cta-button" target="_blank" style="display:inline-block;background-color:#ea580c;background-image:linear-gradient(135deg,rgba(255,255,255,0.4) 0%,rgba(255,255,255,0.05) 45%,rgba(0,0,0,0.05) 55%,rgba(0,0,0,0.15) 100%);color:#ffffff;text-decoration:none;font-size:16px;font-weight:700;padding:15px 36px;border-radius:50px;border:1px solid rgba(255,255,255,0.5);box-shadow:0 10px 30px -5px rgba(234,88,12,0.4),inset 0 2px 4px rgba(255,255,255,0.6),inset 0 -4px 6px rgba(0,0,0,0.2);text-transform:uppercase;letter-spacing:1.5px;white-space:nowrap;"><span style="text-shadow:0 2px 4px rgba(0,0,0,0.3);">Xem Khóa Học</span></a>
                                    <!--<![endif]-->
                                </td>
                            </tr>
<?php
        return ob_get_clean();
    }

    /**
     * Khối ZALO community box của email từ chối — ẩn với email \@vanlanguni.vn.
     *
     * @param \stdClass $a Du lieu email
     * @return string Chuoi rong neu la email \@vanlanguni.vn
     */
    private static function rejection_zalo_box($a): string {
        ob_start();
        ?>

                            <!-- ZALO COMMUNITY BOX -->
                            <?php if (stripos($a->useremail, '@vanlanguni.vn') === false) : ?>
                            <tr>
                                <td class="card-padding-mobile" style="padding:0 30px 30px 30px;font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
                                    <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="border-collapse:separate;border-spacing:0;">
                                        <tr>
                                            <td class="card-bg-blue" style="background-color:#eff6ff;border:1px solid #dbeafe;border-top:2px solid #ffffff;border-bottom:3px solid #bfdbfe;border-radius:20px;padding:30px 25px;text-align:center;box-shadow:0 4px 15px rgba(59,130,246,0.05),inset 0 2px 0 rgba(255,255,255,0.6);">
                                                <?php $zalouri = self::logo_data_uri('zalo_icon.png', 60, 60); ?>
                                                <?php if ($zalouri) : ?>
                                                <img src="<?= $zalouri ?>" width="60" height="60" alt="Zalo Logo" style="display:block;margin:0 auto 15px auto;border-radius:16px;filter:drop-shadow(0 4px 10px rgba(0,0,0,0.15));">
                                                <?php else : ?>
                                                <div style="width:60px;height:60px;margin:0 auto 15px auto;background:#0068ff;border-radius:16px;"><span style="color:#fff;font-size:28px;font-weight:900;line-height:60px;text-align:center;display:block;">Z</span></div>
                                                <?php endif; ?>
                                                <h4 class="text-main" style="margin:0 0 10px 0;color:#1e3a8a;font-size:18px;font-weight:700;">Cộng Đồng Quiz Văn Lang</h4>
                                                <p class="text-sub" style="font-size:15px;color:#3b82f6;margin:0 0 20px 0;line-height:1.5;">Tham gia cộng đồng bên dưới<br>để không bỏ lỡ các khóa học mới nhất nhé!</p>
                                                <!--[if mso]>
                                                <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="https://zalo.me/g/eds5dzskgzc7eu4essgc" style="height:44px;v-text-anchor:middle;width:180px;" arcsize="50%" strokecolor="#1e3a8a" strokeweight="2px" fillcolor="#2563eb">
                                                    <w:anchorlock/>
                                                    <center style="color:#ffffff;font-family:'Segoe UI',sans-serif;font-size:14px;font-weight:bold;">Tham Gia Ngay</center>
                                                </v:roundrect>
                                                <![endif]-->
                                                <!--[if !mso]><!-->
                                                <a href="https://zalo.me/g/eds5dzskgzc7eu4essgc" target="_blank" style="display:inline-block;background-color:#2563eb;background-image:linear-gradient(135deg,rgba(255,255,255,0.4) 0%,rgba(255,255,255,0.05) 45%,rgba(0,0,0,0.05) 55%,rgba(0,0,0,0.15) 100%);color:#ffffff;font-size:14px;font-weight:700;text-decoration:none;padding:14px 35px;border-radius:50px;border:1px solid rgba(255,255,255,0.5);box-shadow:0 10px 25px -5px rgba(37,99,235,0.4),inset 0 2px 4px rgba(255,255,255,0.6),inset 0 -3px 5px rgba(0,0,0,0.2);text-transform:uppercase;letter-spacing:1px;"><span style="text-shadow:0 2px 4px rgba(0,0,0,0.3);">Tham Gia Ngay</span></a>
                                                <!--<![endif]-->
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <?php endif; ?>
<?php
        return ob_get_clean();
    }

    /**
     * Khối footer + đóng thẻ của email từ chối.
     *
     * @param \stdClass $a Du lieu email
     * @return string
     */
    private static function rejection_footer($a): string {
        ob_start();
        ?>

                            <!-- FOOTER -->
                            <tr>
                                <td class="content-bg" style="background-color:#f8fafc;padding:30px 25px;text-align:center;border-top:1px solid #e2e8f0;font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
                                    <?php if (stripos($a->useremail, '@vanlanguni.vn') === false) : ?>
                                    <p class="footer-text" style="font-size:14px;color:#64748b;margin:0;line-height:1.5;">💡 Nếu muốn thử lại, inbox Zalo Admin:<br><a href="https://zalo.me/0588325106" target="_blank" style="color:#2563eb;text-decoration:none;font-weight:700;background:#e0e7ff;padding:4px 12px;border-radius:12px;display:inline-block;margin-top:8px;">0588.325.106</a></p>
                                    <?php else : ?>
                                    <p class="footer-text" style="font-size:14px;color:#64748b;margin:0;line-height:1.5;">💡 Nếu muốn thử lại, liên hệ SĐT Zalo Admin:<br><strong style="color:#475569;display:inline-block;margin-top:8px;">0588.325.106</strong></p>
                                    <?php endif; ?>
                                    <div class="divider-line" style="width:50px;height:2px;background-color:#cbd5e1;margin:20px auto;border-radius:2px;"></div>
                                    <p class="footer-text" style="font-size:12px;color:#94a3b8;margin:0;letter-spacing:0.5px;text-transform:uppercase;">&copy; <?= date('Y') ?> <strong>Quiz Văn Lang</strong>. Mọi quyền được bảo lưu.</p>
                                </td>
                            </tr>

                        </table>
                        <!--[if mso]></td></tr></table><![endif]-->
                    </td>
                </tr>
            </table>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Dựng HTML email thông báo hủy ghi danh — màu slate, cùng chuẩn với template ghi danh.
     *
     * @param \stdClass $a Du lieu email (username, coursename, useremail, courseurl)
     * @return string Nội dung HTML của email
     */
    public static function get_unenrolment_email_html($a): string {
        $cleanname     = htmlspecialchars(self::clean_student_name($a->username), ENT_QUOTES, 'UTF-8');
        $coursename     = htmlspecialchars($a->coursename, ENT_QUOTES, 'UTF-8');
        $useremail      = htmlspecialchars($a->useremail, ENT_QUOTES, 'UTF-8');
        $platformlabel = self::get_email_platform_label($a->useremail);
        $contacturl    = htmlspecialchars(
            (new \moodle_url('/message/index.php', ['id' => get_admin()->id]))->out(false),
            ENT_QUOTES,
            'UTF-8'
        );
        return self::unenrolment_head()
            . self::unenrolment_body($cleanname, $coursename, $useremail, $contacturl, $platformlabel)
            . self::unenrolment_footer($a);
    }
    /**
     * Khối <head> + style của email hủy ghi danh.
     *
     * @return string
     */
    private static function unenrolment_head(): string {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="vi" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
        <head>
            <meta charset="UTF-8">
            <title>Thông báo từ hệ thống</title>
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <meta name="format-detection" content="telephone=no, date=no, address=no, email=no">
            <meta name="color-scheme" content="light dark">
            <meta name="supported-color-schemes" content="light dark">
            <!--[if mso]>
            <xml><o:OfficeDocumentSettings><o:AllowPNG/><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml>
            <![endif]-->
            <style>
                :root { color-scheme: light dark; supported-color-schemes: light dark; }
                @media (prefers-color-scheme: dark) {
                    .body-bg    { background-color: #020617 !important; }
                    .content-bg { background-color: #0f172a !important; border-color: #1e293b !important; border-bottom-color: #1e293b !important; }
                    .text-main  { color: #f8fafc !important; text-shadow: none !important; }
                    .text-sub   { color: #94a3b8 !important; }
                    .card-bg    { background-color: #0d1520 !important; border-color: #2d3748 !important; border-bottom-color: #1a2535 !important; box-shadow: none !important; }
                    .dashed-border { border-top-color: #334155 !important; }
                    .divider-line { background-color: #334155 !important; }
                    .footer-text { color: #64748b !important; }
                    .highlight  { color: #94a3b8 !important; }
                    .icon-bg    { background-color: rgba(0,0,0,0.2) !important; }
                }
                body, table, td, p, a, li { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
                @media screen and (max-width: 600px) {
                    .stack-mobile { display: block !important; width: 100% !important; float: none !important; text-align: left !important; box-sizing: border-box !important; }
                    .value-mobile { float: none !important; text-align: left !important; max-width: 100% !important; padding-top: 4px !important; }
                    .padding-mobile  { padding: 25px 20px 20px 20px !important; }
                    .card-padding-mobile { padding: 0 20px 25px 20px !important; }
                    .wrapper-padding-mobile { padding: 20px 10px !important; }
                    .header-mobile { padding: 28px 20px !important; }
                    .cta-button { padding: 14px 28px !important; font-size: 15px !important; }
                }
            </style>
        </head>
<?php
        return ob_get_clean();
    }

    /**
     * Khối body (header, content, course card, CTA) của email hủy ghi danh.
     *
     * @param string $cleanname Tên học viên đã escape
     * @param string $coursename Tên khóa học đã escape
     * @param string $useremail Email đã escape
     * @param string $contacturl URL liên hệ admin đã escape
     * @param string $platformlabel Nhãn nền tảng email
     * @return string
     */
    private static function unenrolment_body($cleanname, $coursename, $useremail, $contacturl, $platformlabel): string {
        ob_start();
        ?>
        <body class="body-bg" style="margin:0;padding:0;background-color:#f1f5f9;-webkit-font-smoothing:antialiased;word-break:break-word;">
            <div style="display:none;font-size:1px;color:#f1f5f9;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;">Rất tiếc, bạn đã bị hủy ghi danh khỏi khóa học. Liên hệ quản trị viên nếu đây là nhầm lẫn...</div>
            <table class="body-bg" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="background-color:#f1f5f9;margin:0;padding:0;width:100%;table-layout:fixed;">
                <tr>
                    <td align="center" class="wrapper-padding-mobile" style="padding:25px 15px;">
                        <!--[if mso]><table align="center" width="600" border="0" cellpadding="0" cellspacing="0" role="presentation"><tr><td><![endif]-->
                        <table class="content-bg" align="center" width="100%" border="0" cellpadding="0" cellspacing="0" role="presentation" style="max-width:600px;background-color:#ffffff;border-radius:24px;overflow:hidden;border:1px solid #e2e8f0;border-bottom:4px solid #cbd5e1;box-shadow:0 15px 35px rgba(0,0,0,0.05);border-collapse:separate;table-layout:fixed;mso-table-lspace:0pt;mso-table-rspace:0pt;">

                            <!-- HEADER -->
                            <tr>
                                <td class="header-mobile" style="background-color:#475569;background-image:linear-gradient(150deg,#64748b 0%,#475569 40%,#1e293b 100%);padding:45px 25px;text-align:center;border-bottom:5px solid #0f172a;border-top:1px solid #94a3b8;box-shadow:inset 0 -10px 20px rgba(0,0,0,0.15),inset 0 2px 10px rgba(255,255,255,0.15);">
                                    <div class="icon-bg" style="background:rgba(255,255,255,0.1);border-top:2px solid rgba(255,255,255,0.4);border-left:2px solid rgba(255,255,255,0.2);border-right:1px solid rgba(255,255,255,0.05);border-bottom:1px solid rgba(255,255,255,0.05);width:76px;height:76px;border-radius:40%;display:inline-block;margin-bottom:25px;box-shadow:0 12px 25px rgba(15,23,42,0.5),inset 0 4px 12px rgba(255,255,255,0.3);">
                                        <p style="font-size:38px;margin:0;line-height:76px;display:block;filter:drop-shadow(0 4px 6px rgba(0,0,0,0.3));">📋</p>
                                    </div>
                                    <h1 style="color:#ffffff;font-size:28px;font-weight:700;margin:0;letter-spacing:1px;text-transform:uppercase;text-shadow:0 2px 4px rgba(0,0,0,0.3);font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;">HỦY GHI DANH!</h1>
                                </td>
                            </tr>

                            <!-- CONTENT -->
                            <tr>
                                <td class="padding-mobile" style="padding:35px 30px 25px 30px;font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
                                    <p class="text-main" style="font-size:19px;color:#1e293b;margin:0 0 15px 0;line-height:1.5;font-weight:700;">Xin chào <span class="highlight" style="color:#475569;"><?= $cleanname ?></span> 👋,</p>
                                    <p class="text-sub" style="font-size:16px;color:#475569;margin:0;line-height:1.7;text-align:justify;">Rất tiếc, bạn đã bị hủy ghi danh khỏi khóa học này. Nếu bạn cho rằng đây là nhầm lẫn, vui lòng liên hệ quản trị viên để được hỗ trợ kịp thời.</p>
                                </td>
                            </tr>

                            <!-- COURSE CARD -->
                            <tr>
                                <td class="card-padding-mobile" style="padding:0 30px 30px 30px;font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
                                    <div class="card-bg" style="background-color:#f8fafc;border:1px solid #e2e8f0;border-top:2px solid #ffffff;border-bottom:3px solid #cbd5e1;border-radius:20px;padding:25px;box-shadow:0 4px 15px rgba(71,85,105,0.05),inset 0 2px 0 rgba(255,255,255,0.8);">
                                        <div style="text-align:center;margin-bottom:20px;">
                                            <span style="display:inline-block;background:#475569;color:#ffffff;font-size:13px;font-weight:700;padding:6px 16px;border-radius:20px;text-transform:uppercase;letter-spacing:1px;box-shadow:0 4px 8px rgba(71,85,105,0.2);">📚 Thông tin khóa học</span>
                                        </div>
                                        <strong class="highlight" style="color:#1e293b;display:block;font-size:22px;line-height:1.4;font-weight:700;text-align:center;margin-bottom:25px;font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;"><?= $coursename ?></strong>
                                        <div style="border-top:1px dashed #cbd5e1;padding:12px 0;overflow:hidden;clear:both;">
                                            <span class="stack-mobile" style="font-size:15px;color:#64748b;font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;">🗓️ Thời gian:</span>
                                            <span class="stack-mobile value-mobile" style="font-size:15px;font-weight:700;color:#0f172a;float:right;text-align:right;word-break:break-word;max-width:65%;font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;"><?= date('d/m/Y - H:i') ?></span>
                                        </div>
                                        <div style="border-top:1px dashed #cbd5e1;padding:12px 0;overflow:hidden;clear:both;">
                                            <span class="stack-mobile" style="font-size:15px;color:#64748b;font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;">👤 Học viên:</span>
                                            <span class="stack-mobile value-mobile" style="font-size:15px;font-weight:700;color:#0f172a;float:right;text-align:right;word-break:break-word;max-width:65%;font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;"><?= $cleanname ?></span>
                                        </div>
                                        <div style="border-top:1px dashed #cbd5e1;padding:12px 0 0 0;overflow:hidden;clear:both;">
                                            <span class="stack-mobile" style="font-size:15px;color:#64748b;font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;"><?= $platformlabel ?></span>
                                            <span class="stack-mobile value-mobile" style="font-size:15px;font-weight:700;color:#0f172a;float:right;text-align:right;word-break:break-all;max-width:65%;font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;"><?= $useremail ?></span>
                                        </div>
                                    </div>
                                </td>
                            </tr>

                            <!-- CTA -->
                            <tr>
                                <td class="card-padding-mobile" style="padding:0 30px 40px 30px;text-align:center;font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
                                    <!--[if mso]>
                                    <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word" href="<?= $contacturl ?>" style="height:56px;v-text-anchor:middle;width:260px;" arcsize="50%" strokecolor="#1e293b" strokeweight="2px" fillcolor="#475569">
                                        <w:anchorlock/>
                                        <center style="color:#ffffff;font-family:'Segoe UI',sans-serif;font-size:16px;font-weight:bold;">LIÊN HỆ HỖ TRỢ</center>
                                    </v:roundrect>
                                    <![endif]-->
                                    <!--[if !mso]><!-->
                                    <a href="<?= $contacturl ?>" class="cta-button" target="_blank" style="display:inline-block;background-color:#475569;background-image:linear-gradient(135deg,rgba(255,255,255,0.3) 0%,rgba(255,255,255,0.05) 45%,rgba(0,0,0,0.05) 55%,rgba(0,0,0,0.15) 100%);color:#ffffff;text-decoration:none;font-size:16px;font-weight:700;padding:15px 36px;border-radius:50px;border:1px solid rgba(255,255,255,0.4);box-shadow:0 10px 30px -5px rgba(71,85,105,0.4),inset 0 2px 4px rgba(255,255,255,0.5),inset 0 -4px 6px rgba(0,0,0,0.2);text-transform:uppercase;letter-spacing:1.5px;white-space:nowrap;"><span style="text-shadow:0 2px 4px rgba(0,0,0,0.3);">Liên Hệ Hỗ Trợ</span></a>
                                    <!--<![endif]-->
                                </td>
                            </tr>
<?php
        return ob_get_clean();
    }

    /**
     * Khối footer + đóng thẻ của email hủy ghi danh.
     *
     * @param \stdClass $a Du lieu email
     * @return string
     */
    private static function unenrolment_footer($a): string {
        ob_start();
        ?>

                            <!-- FOOTER -->
                            <tr>
                                <td class="content-bg" style="background-color:#f8fafc;padding:30px 25px;text-align:center;border-top:1px solid #e2e8f0;font-family:-apple-system,BlinkMacSystemFont,'Inter','Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
                                    <?php if (stripos($a->useremail, '@vanlanguni.vn') === false) : ?>
                                    <p class="footer-text" style="font-size:14px;color:#64748b;margin:0;line-height:1.5;">💡 Nếu đây là nhầm lẫn, inbox Zalo Admin:<br><a href="https://zalo.me/0588325106" target="_blank" style="color:#2563eb;text-decoration:none;font-weight:700;background:#e0e7ff;padding:4px 12px;border-radius:12px;display:inline-block;margin-top:8px;">0588.325.106</a></p>
                                    <?php else : ?>
                                    <p class="footer-text" style="font-size:14px;color:#64748b;margin:0;line-height:1.5;">💡 Nếu đây là nhầm lẫn, liên hệ SĐT Zalo Admin:<br><strong style="color:#475569;display:inline-block;margin-top:8px;">0588.325.106</strong></p>
                                    <?php endif; ?>
                                    <div class="divider-line" style="width:50px;height:2px;background-color:#cbd5e1;margin:20px auto;border-radius:2px;"></div>
                                    <p class="footer-text" style="font-size:12px;color:#94a3b8;margin:0;letter-spacing:0.5px;text-transform:uppercase;">&copy; <?= date('Y') ?> <strong>Quiz Văn Lang</strong>. Mọi quyền được bảo lưu.</p>
                                </td>
                            </tr>

                        </table>
                        <!--[if mso]></td></tr></table><![endif]-->
                    </td>
                </tr>
            </table>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
}
