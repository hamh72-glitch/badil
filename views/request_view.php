<?php
require VIEWS_DIR . '/layout.php';

$id = (int)get('id', 0);
$v = Db::one('SELECT v.*, s.name AS sub_name, s.gender AS sub_gender, s.mobile AS sub_mobile,
              s.subject AS sub_subject, s.national_id AS sub_national_id, s.qualification AS sub_qual,
              s.record_type AS sub_record_type, s.gpa AS sub_gpa, s.final_score AS sub_score
              FROM vacancies v
              LEFT JOIN substitutes s ON s.id = v.substitute_id
              WHERE v.id = ?', [$id]);
if (!$v) {
    flash('الطلب غير موجود.', 'error');
    redirect('index.php?page=requests');
}

// صلاحية الاطلاع
if (Auth::isSchool()) {
    $myName = Auth::user()['full_name'];
    if ((int)$v['school_user_id'] !== Auth::id() && $v['shared_with'] !== $myName) {
        flash('لا يمكنك الاطلاع على هذا الطلب.', 'error');
        redirect('index.php?page=requests');
    }
}

$canEdit = Auth::canEditVacancy($v);
$canDelete = Auth::canDeleteVacancy();
$canEnd = Auth::canEndWork() && $v['status'] === 'assigned';
$classes = json_get($v['classes']);

layout_header('تفاصيل الطلب #' . $v['id']);
?>
<div class="page-tools">
    <?php back_btn('index.php?page=requests'); ?>
    <div class="row">
        <?php if ($v['status'] === 'open' && Auth::canAssign()): ?>
            <a class="btn accent" href="index.php?page=assign&id=<?= $v['id'] ?>">👤 تكليف بديل</a>
        <?php endif; ?>
        <?php if ($v['status'] === 'assigned'): ?>
            <a class="btn primary" href="index.php?page=report&type=assignment&id=<?= $v['id'] ?>">🖨️ طباعة كتاب التكليف</a>
        <?php endif; ?>
        <?php if (Auth::isSchool() || Auth::canAssign()): ?>
            <a class="btn" href="index.php?page=report&type=request&id=<?= $v['id'] ?>">📄 طباعة الطلب</a>
        <?php endif; ?>
        <?php if ($canEnd): ?>
            <a class="btn danger" href="index.php?page=end_work&id=<?= $v['id'] ?>">🏁 إنهاء عمل البديل</a>
        <?php endif; ?>
        <?php if ($canEdit): ?>
            <a class="btn ghost" href="index.php?page=request&id=<?= $v['id'] ?>">✏️ تعديل</a>
        <?php endif; ?>
        <?php if ($canDelete): ?>
            <form method="post" action="index.php?page=requests" class="inline"
                  onsubmit="return confirm('حذف هذا الطلب نهائياً؟ سيتم تحرير البديل إن وجد.');">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="delete_vacancy">
                <input type="hidden" name="id" value="<?= $v['id'] ?>">
                <button class="btn danger-ghost" type="submit">🗑️ حذف</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="detail-grid">
    <div class="card">
        <div class="section-title">🏫 المدرسة والشاغر</div>
        <div class="kv-grid">
            <div class="kv"><dt>المدرسة</dt><dd><?= e($v['school_name']) ?></dd></div>
            <div class="kv"><dt>مشترك مع</dt><dd><?= e($v['shared_with']) ?: '—' ?></dd></div>
            <div class="kv"><dt>سبب الشاغر</dt><dd><?= e($v['reason']) ?></dd></div>
            <div class="kv"><dt>مركز الشاغر</dt><dd><?= e($v['center']) ?: '—' ?></dd></div>
            <div class="kv"><dt>جنس الشاغر</dt><dd><?= e($v['vacancy_gender']) ?: '—' ?></dd></div>
            <div class="kv"><dt>المادة</dt><dd><?= e($v['subject']) ?: '—' ?></dd></div>
            <div class="kv span-2"><dt>الصفوف</dt><dd><?= $classes ? e(implode('، ', $classes)) : '—' ?></dd></div>
        </div>
    </div>

    <div class="card">
        <div class="section-title">👤 الموظف الأصيل</div>
        <div class="kv-grid">
            <div class="kv"><dt>الاسم</dt><dd><?= e($v['original_name']) ?></dd></div>
            <div class="kv"><dt>رقم الهوية</dt><dd><?= e($v['original_national_id']) ?: '—' ?></dd></div>
            <div class="kv"><dt>الجنس</dt><dd><?= e($v['original_gender']) ?: '—' ?></dd></div>
            <div class="kv"><dt>المسمى الوظيفي</dt><dd><?= e($v['original_job_title']) ?: '—' ?></dd></div>
            <div class="kv span-2"><dt>ملاحظات</dt><dd><?= nl2br(e($v['notes'])) ?: '—' ?></dd></div>
        </div>
    </div>

    <div class="card">
        <div class="section-title">🕓 فترة العمل</div>
        <div class="kv-grid">
            <div class="kv"><dt>تاريخ البدء</dt><dd><?= e(fmt_date($v['start_date'])) ?></dd></div>
            <div class="kv"><dt>تاريخ الانتهاء</dt><dd><?= e(fmt_date($v['end_date'])) ?></dd></div>
            <div class="kv"><dt>تاريخ الإرسال</dt><dd><?= e(fmt_datetime($v['created_at'])) ?></dd></div>
            <div class="kv"><dt>رقم الكتاب</dt><dd><?= e($v['document_no']) ?: '—' ?></dd></div>
            <div class="kv"><dt>الحالة</dt><dd><?php require VIEWS_DIR . '/_status_badge.php'; ?></dd></div>
        </div>
    </div>

    <div class="card">
        <div class="section-title">👥 البديل المكلف</div>
        <?php if (!$v['substitute_id']): ?>
            <p class="empty">لم يتم تكليف بديل بعد لهذا الشاغر.</p>
        <?php else: ?>
            <div class="kv-grid">
                <div class="kv"><dt>الاسم</dt><dd><?= e($v['sub_name']) ?></dd></div>
                <div class="kv"><dt>رقم الهوية</dt><dd><?= e($v['sub_national_id']) ?></dd></div>
                <div class="kv"><dt>الجنس</dt><dd><?= e($v['sub_gender']) ?></dd></div>
                <div class="kv"><dt>المبحث</dt><dd><?= e($v['sub_subject']) ?></dd></div>
                <div class="kv"><dt>الهاتف</dt><dd><?= e($v['sub_mobile']) ?: '—' ?></dd></div>
                <div class="kv"><dt>تاريخ التكليف</dt><dd><?= e(fmt_datetime($v['assigned_at'])) ?></dd></div>
                <div class="kv"><dt>تاريخ الانتهاء</dt><dd><?= e(fmt_datetime($v['ended_at'])) ?: '—' ?></dd></div>
                <div class="kv"><dt>عدد أيام العمل</dt><dd><?= $v['days_worked'] ?: '—' ?></dd></div>
                <div class="kv span-2"><dt>سبب الإنهاء</dt><dd><?= e($v['end_reason']) ?: '—' ?></dd></div>
            </div>
            <div class="row" style="margin-top:12px">
                <?php if ($v['sub_mobile']): ?>
                    <a class="btn" href="<?= e(tel_link($v['sub_mobile'])) ?>">📞 اتصال</a>
                    <a class="btn" target="_blank" rel="noopener" href="<?= e(wa_link($v['sub_mobile'])) ?>">💬 واتساب</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php layout_footer(); ?>
