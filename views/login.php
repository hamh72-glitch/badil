<?php
/** صفحة تسجيل الدخول */
$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = trim(post('username'));
    $password = post('password');

    if ($username === '' || $password === '') {
        $error = 'الرجاء إدخال اسم المستخدم وكلمة السر.';
    } else {
        $user = Auth::attempt($username, $password);
        if ($user) {
            Auth::login($user);
            flash('مرحباً بك، ' . $user['full_name']);
            redirect('index.php?page=dashboard');
        } else {
            $error = 'اسم المستخدم أو كلمة السر غير صحيحة.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>تسجيل الدخول | <?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css">
</head>
<body class="auth-body">
    <div class="login-wrap">
        <div class="login-brand">
            <?php $lg = logo_url(); ?>
            <div class="brand-logo large"><?php if ($lg): ?><img src="<?= e($lg) ?>" alt="شعار"><?php else: ?>ب<?php endif; ?></div>
            <h1><?= e(APP_NAME) ?></h1>
            <p><?= e(Settings::text('directorate_name')) ?></p>
            <p class="muted small">إدارة ملف البدلاء في مدارس المديرية</p>
        </div>

        <div class="auth-card">
            <h2 class="auth-title">تسجيل الدخول</h2>
            <?php if ($error): ?>
                <div class="alert error"><?= e($error) ?></div>
            <?php endif; ?>
            <form method="post" class="stack">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <div class="form-group">
                    <label>اسم المستخدم (الرقم الوطني للمدرسة)</label>
                    <input class="input" name="username" value="<?= e($username) ?>" required autofocus
                           placeholder="مثال: 23112014" autocomplete="username">
                </div>
                <div class="form-group">
                    <label>كلمة السر</label>
                    <div class="input-wrap">
                        <input class="input" type="password" name="password" id="passInput" required
                               placeholder="كلمة السر" autocomplete="current-password">
                        <button type="button" class="input-eye" onclick="togglePass()" aria-label="إظهار كلمة السر">👁</button>
                    </div>
                </div>
                <button class="btn primary block" type="submit">دخول</button>
            </form>
            <p class="muted small center">تتوفر حسابات المدارس مسبقاً، ويمكن لمسؤول النظام تعديل كلمات السر.</p>
        </div>
    </div>
<script>
function togglePass() {
    const i = document.getElementById('passInput');
    i.type = i.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>
