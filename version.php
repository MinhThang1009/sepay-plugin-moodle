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
 * Khai báo phiên bản của plugin enrol_sepay.
 *
 * @package    enrol_sepay
 * @copyright  2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2026062800;        // Phiên bản plugin hiện tại.
$plugin->release   = '1.1.0';           // Phiên bản người đọc (SemVer): thêm tính năng gia hạn sau hết hạn.
$plugin->requires  = 2022041900;        // Moodle 4.0 tối thiểu (hỗ trợ 4.0+).
$plugin->supported = [400, 502];        // Dải Moodle đã verify trên CI: 4.0 → 5.2.
$plugin->component = 'enrol_sepay';     // Tên đầy đủ của plugin (dùng cho chẩn đoán).
