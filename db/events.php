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
 * Đăng ký event observer cho plugin enrol_sepay.
 *
 * @package    enrol_sepay
 * @copyright  2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname'    => '\core\event\user_enrolment_deleted',
        'callback'     => '\enrol_sepay\observer::user_enrolment_deleted',
    ],
    [
        // Bắt suspend (hết hạn với expiredaction SUSPEND/SUSPENDNOROLES) — chỉ bắn _updated,
        // không bắn _deleted — để mở lại form QR gia hạn.
        'eventname'    => '\core\event\user_enrolment_updated',
        'callback'     => '\enrol_sepay\observer::user_enrolment_updated',
    ],
];
