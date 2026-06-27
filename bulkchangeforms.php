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
 * Form cho việc chỉnh sửa / hủy ghi danh hàng loạt của plugin enrol_sepay.
 *
 * Chỉ là subclass cụ thể của các form base trong enrol/bulkchange_forms.php
 * (base là abstract nên không thể khởi tạo trực tiếp), mirror theo enrol/manual.
 *
 * @package   enrol_sepay
 * @copyright 2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->dirroot/enrol/bulkchange_forms.php");

/**
 * Form thu thập thông tin khi chỉnh sửa ghi danh hàng loạt.
 *
 * @copyright 2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_sepay_editselectedusers_form extends enrol_bulk_enrolment_change_form {
}

/**
 * Form xác nhận ý định hủy ghi danh hàng loạt.
 *
 * @copyright 2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_sepay_deleteselectedusers_form extends enrol_bulk_enrolment_confirm_form {
}
