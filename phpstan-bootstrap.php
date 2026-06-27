<?php
// phpcs:ignoreFile
// File bootstrap CHỈ dùng cho PHPStan, không nạp lúc chạy.
// Bootstrap tối thiểu cho PHPStan (KHÔNG kết nối DB, KHÔNG boot Moodle đầy đủ).
// Chỉ định nghĩa các hằng để các file có `defined('MOODLE_INTERNAL') || die();`
// không thoát sớm khi phân tích tĩnh.
define('MOODLE_INTERNAL', true);
define('AJAX_SCRIPT', false);
define('CLI_SCRIPT', false);
