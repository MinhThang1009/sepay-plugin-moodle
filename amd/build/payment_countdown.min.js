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
 * Xử lý chuyển hướng đếm ngược thanh toán SePay.
 *
 * @module     enrol_sepay/payment_countdown
 * @copyright  2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery'], function($) {
    return {
        init: function(courseId, enrolUrl) {
            const storageKey = "sepay_processed_countdown_" + courseId;
            let startTime = localStorage.getItem(storageKey);
            let remainingSeconds;

            if (startTime) {
                const elapsed = Math.floor((Date.now() - parseInt(startTime, 10)) / 1000);
                remainingSeconds = Math.max(0, 5 - elapsed);
            } else {
                startTime = Date.now();
                localStorage.setItem(storageKey, startTime);
                remainingSeconds = 5;
            }

            if (remainingSeconds <= 0) {
                localStorage.removeItem(storageKey);
                window.location.href = enrolUrl;
                return;
            }

            const countdownElement = document.getElementById("countdown");
            if (countdownElement) {
                countdownElement.textContent = remainingSeconds;
            }

            let seconds = remainingSeconds;
            const interval = setInterval(function() {
                seconds--;
                if (countdownElement) {
                    countdownElement.textContent = seconds;
                }

                if (seconds <= 0) {
                    clearInterval(interval);
                    localStorage.removeItem(storageKey);
                    window.location.href = enrolUrl;
                }
            }, 1000);
        }
    };
});
