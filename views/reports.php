<?php
require VIEWS_DIR . '/layout.php';

if (!in_array(Auth::role(), ['admin', 'directorate'], true)) {
    flash('التقارير متاحة لحساب المديرية ومسؤول النظام.', 'error');
    redirect('index.php?page=dashboard');
}
if (Auth::isDirectorate() && !Auth::hasPermission('reports')) {
    flash('ليس لديك صلاحية التقارير.', 'error');
    redirect('index.php?page=dashboard');
}

$assigned = Db::all(
    'SELECT v.id, v.school_name, v.original_name, v.subject, v.document_no, s.name AS sub_name
     FROM vacancies v
     LEFT JOIN substitutes s ON s.id = v.substitute_id
     WHERE v.status IN ("assigned","ended") AND v.substitute_id IS NOT NULL
     ORDER BY v.assigned_at DESC'
);
$allRequests = Db::all(
    'SELECT v.id, v.school_name, v.original_name, v.subject, v.status
     FROM vacancies v ORDER BY v.created_at DESC'
);
$subs = Db::all('SELECT id, name, national_id, subject FROM substitutes ORDER BY name');
$schools = Db::all('SELECT DISTINCT school_name FROM vacancies ORDER BY school_name');

layout_header('التقارير والطباعة');
?>
<div class="report-cards">

    <div class="card report-card">
        <div class="report-ico">📄</div>
        <h3>1. كتاب تكليف للعمل</h3>
        <p class="muted small">كتاب رسمي بتكليف بديل يتضمن بيانات البديل والموظف الأصيل مع الترويسة والتذييل المعتمدين.</p>
        <form method="get" action="index.php" class="stack">
            <input type="hidden" name="page" value="report">
            <input type="hidden" name="type" value="assignment">
            <select class="input" name="id" required>
                <option value="">— اختر التكليف —</option>
                <?php foreach ($assigned as $a): ?>
                    <option value="<?= $a['id'] ?>">#<?= $a['id'] ?> · <?= e($a['school_name']) ?> · <?= e($a['sub_name']) ?><?= $a['document_no'] ? ' (' . e($a['document_no']) . ')' : '' ?></option>
                <?php endforeach; ?>
            </select>
            <div class="btn-row">
                <button class="btn primary" type="submit">طباعة الكتاب</button>
            </div>
        </form>
    </div>

    <div class="card report-card">
        <div class="report-ico">📋</div>
        <h3>2. تقرير بطلب البديل</h3>
        <p class="muted small">تقرير شامل لطلب بديل معين: بيانات الشاغر، الموظف الأصيل، البديل المكلف، وحالة الطلب.</p>
        <form method="get" action="index.php" class="stack">
            <input type="hidden" name="page" value="report">
            <input type="hidden" name="type" value="request">
            <select class="input" name="id" required>
                <option value="">— اختر الطلب —</option>
                <?php foreach ($allRequests as $r): ?>
                    <option value="<?= $r['id'] ?>">#<?= $r['id'] ?> · <?= e($r['school_name']) ?> · <?= e($r['original_name']) ?> · <?= e($r['subject']) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="btn-row">
                <button class="btn primary" type="submit">عرض التقرير</button>
            </div>
        </form>
    </div>

    <div class="card report-card">
        <div class="report-ico">👤</div>
        <h3>3. تقرير عمل بديل معين</h3>
        <p class="muted small">سجل كامل لأعمال بديل محدد: المدارس، الفترات، أيام العمل، والمجموع.</p>
        <form method="get" action="index.php" class="stack">
            <input type="hidden" name="page" value="report">
            <input type="hidden" name="type" value="substitute">
            <select class="input" name="id" required>
                <option value="">— اختر البديل —</option>
                <?php foreach ($subs as $s): ?>
                    <option value="<?= $s['id'] ?>"><?= e($s['name']) ?> (<?= e($s['subject']) ?>)</option>
                <?php endforeach; ?>
            </select>
            <div class="btn-row">
                <button class="btn primary" type="submit">عرض التقرير</button>
                <button class="btn accent" type="submit" formaction="report_export.php">تصدير Excel</button>
            </div>
        </form>
    </div>

    <div class="card report-card">
        <div class="report-ico">📊</div>
        <h3>4. تقرير جميع البدلاء</h3>
        <p class="muted small">ملخص إجمالي لأعمال جميع البدلاء مع أيام العمل وعدد مرات التكليف والحالة الحالية.</p>
        <div class="btn-row">
            <a class="btn primary" href="index.php?page=report&type=all">عرض التقرير</a>
            <a class="btn accent" href="report_export.php?type=all">تصدير Excel</a>
        </div>
    </div>

    <div class="card report-card">
        <div class="report-ico">🏫</div>
        <h3>5. تقرير مدرسة محددة</h3>
        <p class="muted small">طلبات البديل لمدرسة معينة مع البدلاء المكلفين وحالاتهم.</p>
        <form method="get" action="index.php" class="stack">
            <input type="hidden" name="page" value="report">
            <input type="hidden" name="type" value="school">
            <select class="input" name="school" required>
                <option value="">— اختر المدرسة —</option>
                <?php foreach ($schools as $sc): ?>
                    <option value="<?= e($sc['school_name']) ?>"><?= e($sc['school_name']) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="btn-row">
                <button class="btn primary" type="submit">عرض التقرير</button>
                <button class="btn accent" type="submit" formaction="report_export.php">تصدير Excel</button>
            </div>
        </form>
    </div>

    <div class="card report-card">
        <div class="report-ico">📅</div>
        <h3>6. تقرير المكلفين من تاريخ إلى تاريخ</h3>
        <p class="muted small">قائمة البدلاء المكلفين خلال فترة محددة مع بيانات التكليف.</p>
        <form method="get" class="stack">
            <input type="hidden" name="page" value="report">
            <input type="hidden" name="type" value="assigned_period">
            <div class="form-grid" style="grid-template-columns:1fr 1fr">
                <div class="form-group">
                    <label>من تاريخ</label>
                    <input class="input" type="date" name="date_from" required>
                </div>
                <div class="form-group">
                    <label>إلى تاريخ</label>
                    <input class="input" type="date" name="date_to" required>
                </div>
            </div>
            <div class="btn-row">
                <button class="btn primary" type="submit">عرض التقرير</button>
                <button class="btn accent" type="submit" formaction="report_export.php">تصدير Excel</button>
            </div>
        </form>
    </div>

    <div class="card report-card">
        <div class="report-ico">📋</div>
        <h3>7. تقرير طلبات البدلاء</h3>
        <p class="muted small">جميع طلبات البديل مرتبة حسب التاريخ مع الحالة والبديل المكلف.</p>
        <div class="btn-row">
            <a class="btn primary" href="index.php?page=report&type=all_requests">عرض التقرير</a>
            <a class="btn accent" href="report_export.php?type=all_requests">تصدير Excel</a>
        </div>
    </div>

    <div class="card report-card">
        <div class="report-ico">🏁</div>
        <h3>8. تقرير الانتهاءات</h3>
        <p class="muted small">البدلاء الذين انتهت أعمالهم مع عدد أيام العمل والسبب.</p>
        <div class="btn-row">
            <a class="btn primary" href="index.php?page=report&type=ended">عرض التقرير</a>
            <a class="btn accent" href="report_export.php?type=ended">تصدير Excel</a>
        </div>
    </div>

    <div class="card report-card">
        <div class="report-ico">👤</div>
        <h3>9. البدلاء المكلفين حالياً</h3>
        <p class="muted small">البدلاء المكلفين حالياً في المدارس مع مدة التكليف.</p>
        <div class="btn-row">
            <a class="btn primary" href="index.php?page=report&type=currently_assigned">عرض التقرير</a>
            <a class="btn accent" href="report_export.php?type=currently_assigned">تصدير Excel</a>
        </div>
    </div>
</div>
<?php layout_footer(); ?>
