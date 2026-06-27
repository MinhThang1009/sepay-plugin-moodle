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
 * Xử lý truy vấn trạng thái thanh toán tự động qua SePay QR.
 *
 * @module     enrol_sepay/payment_poll
 * @copyright  2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax', 'core/log'], function($, ajax, Log) {
    return {
        init: function(courseId, currentState) {
            let isChecking = false;
            let pollinterval;

            /**
             * Gọi webservice kiểm tra trạng thái giao dịch; reload trang khi trạng thái đổi.
             */
            function checkStatusChange() {
                // Bỏ qua khi đang có request hoặc tab đang ẩn (đỡ ~20 request/phút lúc không xem).
                if (isChecking || document.hidden) {
                    return;
                }
                isChecking = true;

                ajax.call([{
                    methodname: 'enrol_sepay_check_transaction_status',
                    args: { courseid: courseId }
                }])[0].done(function(data) {
                    let newState = 'none';
                    if (data.enrolled) {
                        newState = 'enrolled';
                    } else if (data.processed) {
                        newState = 'processed';
                    } else if (data.rejected) {
                        newState = 'rejected';
                    } else if (data.pending) {
                        newState = 'pending';
                    }

                    if (newState !== currentState) {
                        clearInterval(pollinterval);
                        window.location.reload();
                    }
                    isChecking = false;
                }).fail(function(ex) {
                    Log.error('Lỗi khi tải trạng thái sepay: ' + ex);
                    isChecking = false;
                });
            }

            pollinterval = setInterval(checkStatusChange, 3000);
            checkStatusChange();
        }
    };
});
