<?php
require VIEWS_DIR . '/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_wants'])) {
    csrf_check();
    if (Auth::role() !== 'admin') {
        flash('فقط مسؤول النظام يمكنه تعديل الرغبة.', 'error');
    } else {
        $toggleId = (int)$_POST['toggle_wants'];
        $newVal = (int)($_POST['wants_value'] ?? 1);
        $updates = ['wants_work' => $newVal];
        if ($newVal === 0) {
            $updates['refuse_date'] = $_POST['refuse_date'] ?: date('Y-m-d');
            $updates['notes'] = trim($_POST['notes'] ?? '');
        } else {
            $updates['refuse_date'] = null;
        }
        Db::update('substitutes', $updates, 'id = ?', [$toggleId]);
        flash($newVal ? 'تم تفعيل الرغبة.' : 'تم تعطيل الرغبة.');
    }
    redirect('index.php?page=substitute&id=' . $toggleId);
}

$id = (int)get('id', 0);
$s = Db::one('SELECT * FROM substitutes WHERE id = ?', [$id]);
if (!$s) {
    flash('البديل غير موجود.', 'error');
    redirect('index.php?page=substitutes');
}

$history = Db::all(
    'SELECT * FROM vacancies WHERE substitute_id = ? AND status IN ("assigned","ended")
     ORDER BY assigned_at DESC', [$id]
);

layout_header('ملف البديل');
?>
<div class="page-tools">
    <?php back_btn('index.php?page=substitutes'); ?>
    <div class="row">
        <?php if ($s['mobile']): ?>
            <a class="btn" href="<?= e(tel_link($s['mobile'])) ?>">📞 اتصال</a>
            <a class="btn" target="_blank" rel="noopener" href="<?= e(wa_link($s['mobile'])) ?>">💬 واتساب</a>
        <?php endif; ?>
    </div>
</div>

<div class="profile-head card">
    <div class="profile-avatar"><?= e(mb_substr($s['name'], 0, 1)) ?></div>
    <div class="profile-info">
        <h3><?= e($s['name']) ?></h3>
        <p class="muted">
            رقم الهوية: <strong dir="ltr"><?= e($s['national_id']) ?></strong> ·
            الجنس: <?= e($s['gender']) ?> ·
            نوع السجل: <?= e($s['record_type']) ?> ·
            الترتيب: <?= $s['order_no'] ?>
        </p>
        <p class="muted">المبحث: <strong><?= e($s['subject']) ?></strong> · المؤهل: <?= e($s['qualification']) ?> · التخصص: <?= e($s['specialization']) ?></p>
        <p class="muted">المعدل: <strong><?= $s['gpa'] ?></strong> · المجموع: <?= $s['final_score'] ?> · العنوان: <?= e($s['address']) ?: '—' ?></p>
        <p class="muted">
            الرغبة:
            <?php if (Auth::role() === 'admin'): ?>
                <button type="button" class="badge <?= $s['wants_work'] ? 'badge-open' : 'badge-refuse' ?>" style="cursor:pointer;border:none" onclick="openRefuseModal(<?= $s['wants_work'] ?>)" title="اضغط للتعديل">
                    <?= $s['wants_work'] ? 'يرغب في العمل ✓' : 'لا يرغب في العمل ✗' ?>
                </button>
            <?php else: ?>
                <span class="badge <?= $s['wants_work'] ? 'badge-open' : 'badge-refuse' ?>">
                    <?= $s['wants_work'] ? 'يرغب في العمل' : 'لا يرغب في العمل' ?>
                </span>
            <?php endif; ?>
            <?php if ($s['refuse_date']): ?>
                · تاريخ الرفض: <strong><?= e($s['refuse_date']) ?></strong>
            <?php endif; ?>
        </p>
        <?php if ($s['notes']): ?><p class="muted small">ملاحظات: <?= e($s['notes']) ?></p><?php endif; ?>
    </div>
    <div class="profile-stat">
        <div class="big-stat"><?= $s['total_work_days'] ?></div>
        <span>يوم عمل</span>
        <div class="big-stat alt"><?= $s['assignments_count'] ?></div>
        <span>مرة تكليف</span>
    </div>
</div>

<div class="card">
    <div class="card-head">
        <h3>سجل أعمال البديل</h3>
        <?php if ($s['mobile']): ?><a class="link" href="index.php?page=report&type=substitute&id=<?= $s['id'] ?>">طباعة التقرير</a><?php endif; ?>
    </div>
    <?php if (!$history): ?>
        <p class="empty">لم يُكلف هذا البديل بأي عمل بعد.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>المدرسة</th>
                        <th>الموظف الأصيل</th>
                        <th>السبب</th>
                        <th>تاريخ التكليف</th>
                        <th>تاريخ الإنهاء</th>
                        <th>أيام العمل</th>
                        <th>الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $h): ?>
                        <tr>
                            <td><?= e($h['school_name']) ?></td>
                            <td><?= e($h['original_name']) ?></td>
                            <td><?= e($h['reason']) ?></td>
                            <td><?= e(fmt_date($h['assigned_at'])) ?></td>
                            <td><?= e(fmt_date($h['ended_at'])) ?: '—' ?></td>
                            <td><?= $h['days_worked'] ?: '—' ?></td>
                            <td>
                                <span class="badge badge-<?= e($h['status']) ?>">
                                    <?= ['assigned' => 'مكلف', 'ended' => 'منتهي'][$h['status']] ?? $h['status'] ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="modal" id="refuseModal">
    <div class="modal-box">
        <h3 style="margin-bottom:14px" id="refuseModalTitle">تعديل حالة الرغبة</h3>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="toggle_wants" value="<?= $s['id'] ?>">
            <input type="hidden" name="wants_value" id="refuseWantsVal" value="0">
            <div class="form-group">
                <label>الموظف</label>
                <input class="input" value="<?= e($s['name']) ?>" disabled>
            </div>
            <div class="form-group" id="refuseFields">
                <label>تاريخ عدم الرغبة</label>
                <input class="input" type="date" name="refuse_date" id="refuseDate" value="<?= e($s['refuse_date'] ?: date('Y-m-d')) ?>">
            </div>
            <div class="form-group" id="notesFields">
                <label>الملاحظات</label>
                <textarea class="input" name="notes" id="refuseNotes" rows="3" placeholder="أدخل سبب عدم الرغبة أو ملاحظات..."><?= e($s['notes'] ?? '') ?></textarea>
            </div>
            <div class="form-actions">
                <button class="btn danger" type="submit" id="refuseSubmitBtn">تأكيد عدم الرغبة</button>
                <button class="btn ghost" type="button" onclick="closeRefuseModal()">إلغاء</button>
            </div>
        </form>
    </div>
</div>

<script>
function openRefuseModal(currentWants) {
    var val = currentWants ? 0 : 1;
    document.getElementById('refuseWantsVal').value = val;
    if (val === 1) {
        document.getElementById('refuseModalTitle').textContent = 'تفعيل الرغبة';
        document.getElementById('refuseFields').style.display = 'none';
        document.getElementById('notesFields').style.display = 'none';
        document.getElementById('refuseSubmitBtn').textContent = 'تأكيد تفعيل الرغبة';
        document.getElementById('refuseSubmitBtn').className = 'btn primary';
    } else {
        document.getElementById('refuseModalTitle').textContent = 'تعطيل الرغبة';
        document.getElementById('refuseFields').style.display = '';
        document.getElementById('notesFields').style.display = '';
        document.getElementById('refuseSubmitBtn').textContent = 'تأكيد عدم الرغبة';
        document.getElementById('refuseSubmitBtn').className = 'btn danger';
    }
    document.getElementById('refuseModal').classList.add('open');
}
function closeRefuseModal() {
    document.getElementById('refuseModal').classList.remove('open');
}
</script>
<?php layout_footer(); ?>
