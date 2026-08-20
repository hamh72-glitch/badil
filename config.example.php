<?php
/**
 * إعدادات التطبيق - مديرية التربية والتعليم - يطا
 * ==============================================
 *
 * *** خطوات النشر ***
 * 1. انسخ هذا الملف إلى config.php
 * 2. غيّر القيم حسب بيانات استضافتك
 * 3. افتح الموقع للمُثبّت: yourdomain.com/install.php
 */

// ---- قاعدة البيانات ----
define('DB_HOST', 'localhost');               // localhost في الاستضافة المشتركة
define('DB_PORT', '3306');                    // 3306 افتراضياً
define('DB_NAME', 'YOUR_DB_NAME');            // غيّر هذا: اسم القاعدة
define('DB_USER', 'YOUR_DB_USER');            // غيّر هذا: اسم المستخدم
define('DB_PASS', 'YOUR_DB_PASS');            // غيّر هذا: كلمة السر

// ---- بيانات التطبيق ----
define('APP_NAME', 'نظام إدارة البدلاء');
define('APP_DIRECTORATE', 'مديرية التربية والتعليم - يطا');
define('APP_TIMEZONE', 'Asia/Hebron');
define('APP_VERSION', '1.0.0');

// ---- مسارات ----
define('BASE_PATH', __DIR__);
define('UPLOAD_DIR', BASE_PATH . '/uploads');
define('LIB_DIR', BASE_PATH . '/lib');
define('VIEWS_DIR', BASE_PATH . '/views');
