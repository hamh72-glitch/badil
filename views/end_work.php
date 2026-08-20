<?php
require VIEWS_DIR . '/layout.php';

if (!Auth::canEndWork()) {
    flash('حساب مديرية النظام فقط هو المخول بإنهاء أعمال البدلاء.', 'error');
    redirect('index.php?page=requests');
}
if (Auth::isDirectorate() && !Auth::hasPermission('end_work')) {
    flash('ليس لديك صلاحية إنهاء أعمال البدلاء.', 'error');
    redirect('index.php?page=requests');
}

$id = (int)get('id', 0);
$v = Db::one('SELECT v.*, s.name AS sub_name, s.national_id AS sub_national_id, s.mobile AS sub_mobile, s.gender AS sub_gender
              FROM vacancies v
              LEFT JOIN substitutes s ON s.id = v.substitute_id
              WHERE v.id = ?', [$id]);
if (!$v || $v['status'] !== 'assigned' || !$v['substitute_id']) {
    flash('لا يوجد عمل قائم لإنهائه على هذا الطلب.', 'error');
    redirect('index.php?page=requests');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $endDate = trim(post('end_date'));
    $days = (int)post('days_worked');
    $reason = trim(post('end_reason'));

    if ($days <= 0) {
        $days = days_between($v['assigned_at'], $endDate);
    }
    if ($days < 1) $days = 1;

    try {
        Db::conn()->beginTransaction();
        Db::update('vacancies', [
            'status' => 'ended',
            'ended_at' => $endDate ? $endDate . ' ' . date('H:i:s') : date('Y-m-d H:i:s'),
            'days_worked' => $days,
            'end_reason' => $reason,
        ], 'id = ?', [$v['id']]);

        $sub = Db::one('SELECT total_work_days, assignments_count FROM substitutes WHERE id = ?', [$v['substitute_id']]);
        Db::update('substitutes', [
            'available' => 1,
            'current_vacancy_id' => null,
            'total_work_days' => (int)($sub['total_work_days'] ?? 0) + $days,
            'assignments_count' => (int)($sub['assignments_count'] ?? 0) + 1,
        ], 'id = ?', [$v['substitute_id']]);
        Db::conn()->commit();

        flash('تم إنهاء عمل البديل بنجاح وأصبح متاحاً للتكليف من جديد.');
        redirect('index.php?page=request_view&id=' . $v['id']);
    } catch (Throwable $e) {
        Db::conn()->rollBack();
        $error = 'خطأ: ' . $e->getMessage();
    }
}

$suggested = days_between($v['assigned_at'], date('Y-m-d'));

layout_header('إنهاء عمل البديل');
?>
<div class="card form-card">
    <div class="alert info">
        <strong>البديل:</strong> <?= e($v['sub_name']) ?> (<?= e($v['sub_national_id']) ?>) ·
        <strong>المدرسة:</strong> <?= e($v['school_name']) ?> ·
        <strong>الأصيل:</strong> <?= e($v['original_name']) ?> ·
        <strong>تاريخ التكليف:</strong> <?= e(fmt_date($v['assigned_at'])) ?>
    </div>

    <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

    <form method="post" class="stack">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" id="assignedAt" value="<?= e($v['assigned_at']) ?>">
        <div class="form-grid">
            <div class="form-group">
                <label>تاريخ إنهاء العمل</label>
                <input class="input" type="date" name="end_date" id="endDate" value="<?= date('Y-m-d') ?>" required onchange="calcDays()">
            </div>
            <div class="form-group">
                <label>عدد أيام العمل</label>
                <input class="input" type="number" name="days_worked" id="daysWorked" value="<?= $suggested ?>" min="1" readonly style="background:#f1f5f9">
            </div>
            <div class="form-group span-2">
                <label>سبب الإنهاء (اختياري)</label>
                <input class="input" name="end_reason" placeholder="مثال: عودة الموظف الأصلي من الإجازة">
            </div>
        </div>
        <div class="form-actions">
            <button class="btn danger" type="submit">تأكيد إنهاء العمل</button>
            <a class="btn ghost" href="index.php?page=request_view&id=<?= $v['id'] ?>">إلغاء</a>
        </div>
        <p class="muted small">عند الإنهاء يُعاد البديل لقائمة المتاحين ليكون قابلاً للتكليف من جديد، ويُسجل عدد أيام عمله في تقاريره.</p>
    </form>
</div>
<script>
function calcDays() {
    var from = document.getElementById('assignedAt').value;
    var to = document.getElementById('endDate').value;
    if (!from || !to) return;
    var d1 = new Date(from);
    var d2 = new Date(to);
    var diff = Math.round((d2 - d1) / 86400000) + 1;
    if (diff < 1) diff = 1;
    document.getElementById('daysWorked').value = diff;
}
</script>
<?php layout_footer(); ?>
