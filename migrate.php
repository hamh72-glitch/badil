<?php
/**
 * ترحيل قاعدة البيانات - إضافة جدول الإشعارات
 * يُشغّل مرة واحدة فقط
 */
require __DIR__ . '/lib/bootstrap.php';

try {
    Db::q("CREATE TABLE IF NOT EXISTS `notifications` (
        `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `user_id` INT UNSIGNED NOT NULL,
        `type` VARCHAR(40) NOT NULL DEFAULT 'shared_request',
        `message` VARCHAR(500) NOT NULL,
        `vacancy_id` INT UNSIGNED DEFAULT NULL,
        `is_read` TINYINT(1) NOT NULL DEFAULT 0,
        `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_user_read` (`user_id`, `is_read`),
        KEY `idx_vacancy` (`vacancy_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "تم إنشاء جدول notifications بنجاح.\n";
} catch (Throwable $e) {
    echo "خطأ: " . $e->getMessage() . "\n";
}
