<?php
/**
 * مثبّت التطبيق - يُنشئ الجداول ويُنشئ حساب مسؤول النظام
 * يُنصح بحذف هذا الملف بعد التثبيت أو تقييد الوصول إليه.
 */
require __DIR__ . '/lib/bootstrap.php';

// التحقق: هل تم التثبيت سابقاً؟
$installed = false;
try {
    $installed = (int)Db::val('SELECT COUNT(*) FROM users WHERE role = "admin"') > 0;
} catch (Throwable $e) {
    $installed = false;
}

$done = false;
$error = '';

if ($installed && !isset($_GET['force'])) {
    $done = true;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !$installed) {
    csrf_check();
    $adminUser = trim(post('admin_user', 'admin'));
    $adminPass = post('admin_pass');
    $adminName = trim(post('admin_name', 'مسؤول النظام'));

    if (strlen($adminPass) < 6) {
        $error = 'كلمة السر يجب أن لا تقل عن 6 أحرف.';
    } else {
        try {
            // إنشاء الجداول من database.sql
            $sql = file_get_contents(__DIR__ . '/database.sql');
            $db = Db::conn();
            $db->exec($sql);

            // إعدادات افتراضية
            Settings::seedDefaults();

            // حساب مسؤول النظام
            $exists = Db::val('SELECT COUNT(*) FROM users WHERE username = ?', [$adminUser]);
            if (!$exists) {
                Db::insert('users', [
                    'username' => $adminUser,
                    'password_hash' => password_hash($adminPass, PASSWORD_DEFAULT),
                    'full_name' => $adminName,
                    'role' => 'admin',
                    'is_active' => 1,
                ]);
            }
            $done = true;
        } catch (Throwable $e) {
            $error = 'فشل الاتصال بقاعدة البيانات: ' . $e->getMessage()
                . '<br><strong>المضيف المستخدم:</strong> ' . e(DB_HOST)
                . '<br>تأكد من أن قاعدة البيانات جاهزة، وأن بيانات config.php صحيحة.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>تثبيت النظام | <?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css">
</head>
<body class="auth-body">
    <div class="auth-card">
        <div class="brand">
            <div class="brand-logo">ب</div>
            <h1><?= e(APP_NAME) ?></h1>
            <p><?= e(APP_DIRECTORATE) ?></p>
        </div>

        <?php if ($done): ?>
            <div class="alert success">تم تثبيت النظام بنجاح.</div>
            <p class="muted">قم بحذف ملف <code>install.php</code> من السيرفر، ثم سجّل الدخول.</p>
            <a class="btn primary block" href="index.php?page=login">تسجيل الدخول</a>
        <?php else: ?>
            <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
            <form method="post" class="stack">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <div class="form-group">
                    <label>اسم مستخدم مسؤول النظام</label>
                    <input class="input" name="admin_user" value="admin" required>
                </div>
                <div class="form-group">
                    <label>الاسم الكامل</label>
                    <input class="input" name="admin_name" value="مسؤول النظام">
                </div>
                <div class="form-group">
                    <label>كلمة السر</label>
                    <input class="input" type="password" name="admin_pass" required minlength="6" placeholder="6 أحرف على الأقل">
                </div>
                <button class="btn primary block" type="submit">تثبيت النظام</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
