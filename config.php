<?php
/**
 * إعدادات التطبيق - مديرية التربية والتعليم - يطا
 * ==============================================
 *
 * يقرأ من Environment Variables مع fallback للقيم الافتراضية
 * يعمل على: استضافة مشتركة / Docker / Render / أي بيئة
 */

// ---- قاعدة البيانات ----
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'badil');
define('DB_USER', getenv('DB_USER') ?: 'badil');
define('DB_PASS', getenv('DB_PASS') ?: 'badil');

// ---- بيانات التطبيق ----
define('APP_NAME', getenv('APP_NAME') ?: 'نظام إدارة البدلاء');
define('APP_DIRECTORATE', getenv('APP_DIRECTORATE') ?: 'مديرية التربية والتعليم - يطا');
define('APP_TIMEZONE', getenv('APP_TIMEZONE') ?: 'Asia/Hebron');
define('APP_VERSION', getenv('APP_VERSION') ?: '1.0.0');

// ---- مسارات ----
define('BASE_PATH', __DIR__);
define('UPLOAD_DIR', BASE_PATH . '/uploads');
define('LIB_DIR', BASE_PATH . '/lib');
define('VIEWS_DIR', BASE_PATH . '/views');
