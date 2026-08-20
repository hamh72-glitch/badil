<?php
require VIEWS_DIR . '/layout.php';

if (!Auth::canAssign()) {
    flash('لا تملك صلاحية التكليف.', 'error');
    redirect('index.php?page=requests');
}
if (Auth::isDirectorate() && !Auth::hasPermission('assign')) {
    flash('ليس لديك صلاحية التكليف.', 'error');
    redirect('index.php?page=requests');
}

$id = (int)get('id', 0);
$vac = Db::one('SELECT * FROM vacancies WHERE id = ?', [$id]);
if (!$vac || !in_array($vac['status'], ['open', 'assigned'], true)) {
    flash('الطلب غير موجود أو لا يقبل تكليفاً جديداً.', 'error');
    redirect('index.php?page=requests');
}
if (Auth::isSchool() && (int)$vac['school_user_id'] !== Auth::id()) {
    flash('لا يمكنك التكليف على هذا الطلب.', 'error');
    redirect('index.php?page=requests');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $subId = (int)post('substitute_id');
    if (!$subId) {
        flash('يرجى اختيار البديل.', 'error');
    } else {
        $sub = Db::one('SELECT * FROM substitutes WHERE id = ? AND available = 1 AND wants_work = 1', [$subId]);
        if (!$sub) {
            flash('البديل غير متاح للتكليف حالياً أو لا يرغب في العمل.', 'error');
        } else {
            try {
                $docNo = Settings::text('document_counter');
                $year = Settings::text('current_year');
                $letterNo = str_pad($docNo, 4, '0', STR_PAD_LEFT);
                $document = 'ق/ ب/' . $year . '/' . $letterNo;

                Db::conn()->beginTransaction();
                Db::update('vacancies', [
                    'status' => 'assigned',
                    'substitute_id' => $subId,
                    'document_no' => $document,
                    'assigned_at' => date('Y-m-d H:i:s'),
                ], 'id = ?', [$vac['id']]);
                Db::update('substitutes', [
                    'available' => 0,
                    'current_vacancy_id' => $vac['id'],
                    'last_assigned_at' => date('Y-m-d H:i:s'),
                ], 'id = ?', [$subId]);
                Settings::set('document_counter', (int)$docNo + 1);
                Db::conn()->commit();

                flash('تم تكليف البديل بنجاح، وتم توليد كتاب التكليف.');
                redirect('index.php?page=report&type=assignment&id=' . $vac['id']);
            } catch (Throwable $e) {
                Db::conn()->rollBack();
                flash('خطأ أثناء التكليف: ' . $e->getMessage(), 'error');
            }
        }
    }
}

$fg = get('fg');
$fq = trim(get('fq'));
$fs = get('fs');
$fr = get('fr');
if ($fs === '' && $vac['subject']) $fs = $vac['subject'];
$subjectsList = Db::all('SELECT DISTINCT subject FROM substitutes WHERE subject != "" ORDER BY subject');

$where = [];
$params = [];
if ($fg !== '' && in_array($fg, ['ذكر', 'أنثى'])) { $where[] = 'gender = ?'; $params[] = $fg; }
if ($fs !== '') { $where[] = 'subject = ?'; $params[] = $fs; }
if ($fr !== '') { $where[] = 'record_type = ?'; $params[] = $fr; }
if ($fq !== '') {
    $where[] = '(name LIKE ? OR national_id LIKE ?)';
    $like = '%' . $fq . '%';
    array_push($params, $like, $like);
}
$whereSQL = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$allSubs = Db::all(
    'SELECT * FROM substitutes ' . $whereSQL . '
     ORDER BY subject ASC, record_type ASC, order_no ASC, gpa DESC', $params
);

$availCount = (int)Db::val('SELECT COUNT(*) FROM substitutes WHERE available = 1');

layout_header('تكليف بديل');
?>
<div class="page-tools">
    <?php back_btn('index.php?page=request_view&id=' . $vac['id'], 'عودة للطلب'); ?>
    <button class="btn accent" type="submit" form="assignForm">تأكيد التكليف وإنشاء كتاب التكليف</button>
</div>

<?php if ($vac['status'] === 'assigned'): ?>
    <div class="alert info">هذا الطلب مكلف فعلاً ببديل. يمكنك طباعة كتاب التكليف أو إنهاء العمل.</div>
<?php endif; ?>

<div class="assign-cards">
    <div class="card assign-card">
        <div class="section-title">بيانات الشاغر</div>
        <div class="kv-grid">
            <div class="kv"><dt>المدرسة</dt><dd><?= e($vac['school_name']) ?></dd></div>
            <div class="kv"><dt>المسمى الوظيفي</dt><dd><?= e($vac['original_job_title']) ?: '—' ?></dd></div>
            <div class="kv"><dt>المادة</dt><dd><?= e($vac['subject']) ?></dd></div>
            <div class="kv"><dt>السبب</dt><dd><?= e($vac['reason']) ?></dd></div>
            <div class="kv"><dt>المركز</dt><dd><?= e($vac['center']) ?: '—' ?></dd></div>
            <div class="kv"><dt>الصفوف</dt><dd><?= e(implode('، ', json_get($vac['classes']))) ?: '—' ?></dd></div>
        </div>
    </div>
    <div class="card assign-card card-original">
        <div class="section-title">الموظف الأصيل</div>
        <div class="kv-grid">
            <div class="kv"><dt>الاسم</dt><dd><?= e($vac['original_name']) ?></dd></div>
            <div class="kv"><dt>الجنس</dt><dd><?= e($vac['original_gender']) ?: '—' ?></dd></div>
            <div class="kv"><dt>المسمى الوظيفي</dt><dd><?= e($vac['original_job_title']) ?: '—' ?></dd></div>
            <div class="kv"><dt>الهوية</dt><dd><?= e($vac['original_national_id']) ?: '—' ?></dd></div>
            <div class="kv"><dt>المركز</dt><dd><?= e($vac['center']) ?: '—' ?></dd></div>
            <div class="kv"><dt>الصفوف</dt><dd><?= e(implode('، ', json_get($vac['classes']))) ?: '—' ?></dd></div>
        </div>
    </div>
</div>

<div class="card">
    <form method="get" class="filter-bar">
        <input type="hidden" name="page" value="assign">
        <input type="hidden" name="id" value="<?= $vac['id'] ?>">
        <select class="input" name="fs" onchange="this.form.submit()">
            <option value="">المبحث: الكل</option>
            <?php foreach ($subjectsList as $s): ?>
                <option value="<?= e($s['subject']) ?>" <?= $fs === $s['subject'] ? 'selected' : '' ?>><?= e($s['subject']) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="input" name="fg" onchange="this.form.submit()">
            <option value="">الجنس: الكل</option>
            <?php foreach (['ذكر', 'أنثى'] as $g): ?>
                <option value="<?= $g ?>" <?= $fg === $g ? 'selected' : '' ?>><?= $g ?></option>
            <?php endforeach; ?>
        </select>
        <select class="input" name="fr" onchange="this.form.submit()">
            <option value="">نوع السجل: الكل</option>
            <?php foreach (Db::all('SELECT DISTINCT record_type FROM substitutes WHERE record_type != "" ORDER BY record_type') as $rt): ?>
                <option value="<?= e($rt['record_type']) ?>" <?= $fr === $rt['record_type'] ? 'selected' : '' ?>><?= e($rt['record_type']) ?></option>
            <?php endforeach; ?>
        </select>
        <input class="input grow" type="search" name="fq" value="<?= e($fq) ?>" placeholder="بحث بالاسم أو رقم الهوية...">
        <button class="btn" type="submit">بحث</button>
        <?php if ($fq !== '' || $fg !== '' || $fs !== $vac['subject'] || $fr !== ''): ?>
            <a class="btn ghost" href="index.php?page=assign&id=<?= $vac['id'] ?>&fs=">عرض الكل</a>
        <?php endif; ?>
    </form>

    <?php if (!$allSubs): ?>
        <p class="empty">لا يوجد بدلاء مطابقين.</p>
    <?php else: ?>
        <form method="post" id="assignForm">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <div class="table-wrap">
                <table class="table assign-table">
                    <thead>
                        <tr>
                            <th class="th-pick">اختيار</th>
                            <th>المبحث</th>
                            <th>نوع السجل</th>
                            <th>الترتيب</th>
                            <th>الاسم</th>
                            <th>رقم الهوية</th>
                            <th>الجنس</th>
                            <th>المعدل</th>
                            <th>الجوال</th>
                            <th>الحالة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allSubs as $s): ?>
                            <?php $disabled = !$s['available'] || !$s['wants_work']; ?>
                            <tr class="<?= !$s['wants_work'] ? 'row-refuse' : ($disabled ? 'row-busy' : '') ?>">
                                <td class="td-pick">
                                    <?php if ($disabled): ?>
                                        <span class="busy-icon">🚫</span>
                                    <?php else: ?>
                                        <label class="pick-radio">
                                            <input type="radio" name="substitute_id" value="<?= $s['id'] ?>">
                                        </label>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($s['subject']) ?></td>
                                <td><span class="badge badge-record"><?= e($s['record_type']) ?></span></td>
                                <td><?= $s['order_no'] ?></td>
                                <td><strong><?= e($s['name']) ?></strong></td>
                                <td dir="ltr" style="text-align:end"><?= e($s['national_id']) ?></td>
                                <td><?= e($s['gender']) ?></td>
                                <td><?= $s['gpa'] ?></td>
                                <td>
                                    <?php if ($s['mobile']): ?>
                                        <span dir="ltr"><?= e($s['mobile']) ?></span>
                                        <a class="btn tiny" href="<?= e(tel_link($s['mobile'])) ?>" title="اتصال">📞</a>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!$s['wants_work']): ?>
                                        <span class="badge badge-refuse">🚫 لا يرغب</span>
                                    <?php elseif ($disabled): ?>
                                        <span class="badge badge-busy">🚫 مكلف حالياً</span>
                                    <?php else: ?>
                                        <span class="badge badge-open">متاح</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
    <?php endif; ?>
</div>
<?php layout_footer(); ?>
