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
 * Khai báo các kênh gửi tin nhắn cho plugin enrol_sepay.
 *
 * @package    enrol_sepay
 * @copyright  2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$messageproviders = [
    // Thông báo cho admin khi có giao dịch mới đang chờ xử lý.
    'pending_transaction' => [
        'capability' => 'enrol/sepay:manage',
        'defaults' => [
            'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
            'email' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
        ],
    ],
    // Ghi danh thành công — email được gửi trực tiếp qua email_to_user() để tránh Moodle wrapper.
    // Provider này chỉ dùng cho popup/bell notification.
    'sepay_enrolment' => [
        'defaults' => [
            'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
            'email' => MESSAGE_DISALLOWED,
        ],
    ],
    // Thông báo từ chối — email gửi trực tiếp, provider chỉ dùng cho popup/bell.
    'rejection_notification' => [
        'defaults' => [
            'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
            'email' => MESSAGE_DISALLOWED,
        ],
    ],
    // Thông báo hủy ghi danh — email gửi trực tiếp, provider chỉ dùng cho popup/bell.
    'unenrolment_notification' => [
        'defaults' => [
            'popup' => MESSAGE_PERMITTED + MESSAGE_DEFAULT_ENABLED,
            'email' => MESSAGE_DISALLOWED,
        ],
    ],
];
