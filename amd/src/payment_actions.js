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
 * Quản lý hành vi thanh toán Ghi danh qua SePay QR.
 *
 * @module     enrol_sepay/payment_actions
 * @copyright  2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/log'], function($, Log) {
    return {
        init: function() {
            /**
             * Copy text vào clipboard (Clipboard API hiện đại, fallback execCommand cho browser cũ).
             *
             * @param {string} text Nội dung cần copy
             * @param {HTMLElement} button Nút đã bấm
             */
            function copyToClipboard(text, button) {
                $('.enrol-sepay-wrapper .copy-btn.copied').removeClass('copied');
                $('.enrol-sepay-wrapper .info-value.copied-highlight').removeClass('copied-highlight');

                var infoValue = $(button).prev('.info-content').find('.info-value');

                /**
                 * Hiển thị hiệu ứng đánh dấu đã copy thành công.
                 */
                function onSuccess() {
                    $(button).addClass('copied');
                    if (infoValue.length) {
                        infoValue.addClass('copied-highlight');
                    }
                    setTimeout(function() {
                        $(button).removeClass('copied');
                        if (infoValue.length) {
                            infoValue.removeClass('copied-highlight');
                        }
                    }, 2000);
                }

                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text).then(onSuccess).catch(function() {
                        legacyCopy(text, button, onSuccess);
                    });
                } else {
                    legacyCopy(text, button, onSuccess);
                }
            }

            /**
             * Copy bằng execCommand cho browser không hỗ trợ Clipboard API.
             *
             * @param {string} text Nội dung cần copy
             * @param {HTMLElement} button Nút đã bấm
             * @param {Function} onSuccess Callback chạy khi copy xong
             */
            function legacyCopy(text, button, onSuccess) {
                var tempInput = document.createElement('input');
                tempInput.value = text;
                document.body.appendChild(tempInput);
                tempInput.select();
                tempInput.setSelectionRange(0, 99999);
                try {
                    document.execCommand('copy');
                    onSuccess();
                } catch (err) {
                    Log.error('Lỗi khi copy: ' + err);
                }
                document.body.removeChild(tempInput);
            }

            // Bắt sự kiện click cho các nút có tính năng copy
            $('.enrol-sepay-wrapper .copy-btn[data-action="copy"]').on('click', function(e) {
                e.preventDefault();
                var text = $(this).attr('data-clipboard-text');
                copyToClipboard(text, this);
            });

            /**
             * Tải ảnh QR về thiết bị qua canvas, fallback fetch nếu lỗi CORS.
             *
             * @param {Event} e Sự kiện click
             */
            function downloadQRCode(e) {
                e.preventDefault();
                var qrImage = document.getElementById('img_qr_code');
                var qrUrl = qrImage.src;

                var canvas = document.createElement('canvas');
                var ctx = canvas.getContext('2d');
                var img = new Image();
                img.crossOrigin = 'Anonymous';

                img.onload = function() {
                    try {
                        canvas.width = img.width;
                        canvas.height = img.height;
                        ctx.drawImage(img, 0, 0);
                        var dataUrl = canvas.toDataURL('image/png');
                        var a = document.createElement('a');
                        a.href = dataUrl;
                        a.download = 'qr-code-sepay.png';
                        document.body.appendChild(a);
                        a.click();
                        setTimeout(function() { document.body.removeChild(a); }, 100);
                    } catch (error) {
                        Log.error('Lỗi canvas: ' + error);
                        fallbackDownload(qrUrl);
                    }
                };
                img.onerror = function() {
                    Log.error('Lỗi load ảnh');
                    fallbackDownload(qrUrl);
                };
                img.src = qrUrl;
            }

            /**
             * Tải ảnh QR qua fetch + FileReader khi canvas thất bại.
             *
             * @param {string} url URL ảnh QR
             */
            function fallbackDownload(url) {
                fetch(url)
                  .then(response => response.blob())
                  .then(blob => {
                    var reader = new FileReader();
                    reader.onloadend = function() {
                        var a = document.createElement('a');
                        a.href = reader.result;
                        a.download = 'qr-code-sepay.png';
                        document.body.appendChild(a);
                        a.click();
                        setTimeout(() => document.body.removeChild(a), 100);
                    };
                    reader.readAsDataURL(blob);
                  })
                  .catch(error => {
                    Log.error('Lỗi fetch: ' + error);
                    alert('Vui lòng nhấn giữ ảnh QR và chọn "Lưu ảnh"');
                    window.open(url, '_blank');
                  });
            }

            $('.enrol-sepay-wrapper .download-qr-btn[data-action="download-qr"]').on('click', downloadQRCode);
        }
    };
});
