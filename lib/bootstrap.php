<?php
/**
 * تهيئة التطبيق
 */
require_once __DIR__ . '/../config.php';
require_once LIB_DIR . '/Db.php';
require_once LIB_DIR . '/Helpers.php';
require_once LIB_DIR . '/Settings.php';
require_once LIB_DIR . '/Auth.php';
require_once LIB_DIR . '/XlsxReader.php';
require_once LIB_DIR . '/Importer.php';

// عزل الجلسات عبر التطبيق
ini_set('session.cookie_httponly', '1');
session_name('BADIL_SESSID');
session_start();
date_default_timezone_set(APP_TIMEZONE);
mb_internal_encoding('UTF-8');

if (!is_dir(UPLOAD_DIR)) {
    @mkdir(UPLOAD_DIR, 0777, true);
}
