<?php
require VIEWS_DIR . '/layout.php';

$user = Auth::user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $current = post('current_pass');
    $new = post('new_pass');
    $confirm = post('confirm_pass');

    if (!password_verify($current, $user['password_hash'])) {
        flash('كلمة السر الحالية غير صحيحة.', 'error');
    } elseif (strlen($new) < 6) {
        flash('كلمة السر الجديدة يجب أن لا تقل عن 6 أحرف.', 'error');
    } elseif ($new !== $confirm) {
        flash('تأكيد كلمة السر غير متطابق.', 'error');
    } else {
        Db::q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), Auth::id()]);
        flash('تم تغيير كلمة السر بنجاح.');
        redirect('index.php?page=password');
    }
}

layout_header('تغيير كلمة السر');
?>
<div class="card form-card narrow">
    <h3 class="section-title">🔑 تغيير كلمة السر</h3>
    <p class="muted small">الحساب: <?= e($user['full_name']) ?> (<?= e($user['username']) ?>)</p>
    <form method="post" class="stack">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <div class="form-group">
            <label>كلمة السر الحالية</label>
            <input class="input" type="password" name="current_pass" required autocomplete="current-password">
        </div>
        <div class="form-group">
            <label>كلمة السر الجديدة</label>
            <input class="input" type="password" name="new_pass" required minlength="6" autocomplete="new-password">
        </div>
        <div class="form-group">
            <label>تأكيد كلمة السر الجديدة</label>
            <input class="input" type="password" name="confirm_pass" required minlength="6">
        </div>
        <div class="form-actions">
            <button class="btn primary" type="submit">حفظ كلمة السر الجديدة</button>
        </div>
    </form>
</div>
<?php layout_footer(); ?>
