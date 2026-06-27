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
 * Công cụ lệnh CLI cho SePay.
 *
 * Công cụ CLI để chạy hàm sync() của plugin enrol_sepay, 
 * mô phỏng đúng pattern của enrol_paypal/cli/sync.php.
 *
 * @package    enrol_sepay
 * @copyright  2026 Quiz Van Lang <quizvanlang@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

// Lấy tham số CLI.
list($options, $unrecognized) = cli_get_params([
    'verbose' => false,
    'help'    => false,
], [
    'v' => 'verbose',
    'h' => 'help',
]);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if (!empty($options['help'])) {
    $help = "Chạy tác vụ sync() cho plugin enrol_sepay (giống PayPal)\n\n" .
"Tùy chọn:\n" .
"-v, --verbose         In chi tiết tiến trình\n" .
"-h, --help            Hiển thị trợ giúp này\n\n" .
"Ví dụ:\n" .
"$ sudo -u www-data /usr/bin/php enrol/sepay/cli/sync.php --verbose\n";

    echo $help;
    exit(0);
}

if (!enrol_is_enabled('sepay')) {
    cli_error('Plugin enrol_sepay dang bi tat');
}

if (empty($options['verbose'])) {
    $trace = new null_progress_trace();
} else {
    $trace = new text_progress_trace();
}

/** @var enrol_sepay_plugin $plugin */
$plugin = enrol_get_plugin('sepay');

$result = $plugin->sync($trace);

exit($result);
