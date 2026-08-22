<?php
/**
 * إعدادات التطبيق - مديرية التربية والتعليم - يطا
 * ==============================================
 *
 * *** خطوات النشر على الاستضافة ***
 * 1. أنشئ قاعدة بيانات MySQL من لوحة cPanel
 * 2. غيّر القيم أدناه حسب بيانات القاعدة التي أنشأتها
 * 3. ارفع الملفات إلى public_html
 * 4. افتح الموقع للمُثبّت: yourdomain.com/install.php
 */

// ---- قاعدة البيانات ----
define('DB_HOST', 'fdb1028.awardspace.net');               // دائماً localhost في الاستضافة المشتركة
define('DB_PORT', '3306');                    // 3306 افتراضياً
define('DB_NAME', '4784060_badil');            // غيّر هذا: اسم القاعدة من cPanel (مثلاً: username_badil)
define('DB_USER', '4784060_badil');            // غيّر هذا: اسم المستخدم من cPanel (مثلاً: username_badil)
define('DB_PASS', 'hamh1972');            // غيّر هذا: كلمة السر التي أنشأتها

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
