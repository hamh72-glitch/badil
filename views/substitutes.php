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
    redirect('index.php?page=substitutes');
}

$gender = get('gender');
$subject = get('subject');
$avail = get('avail');
$wants = get('wants');
$recType = get('rec_type');
$q = trim(get('q'));
$pageNo = (int)get('page_no', 1);

$where = [];
$params = [];
if ($gender !== '' && in_array($gender, ['ذكر', 'أنثى'])) {
    $where[] = 'gender = ?';
    $params[] = $gender;
}
if ($subject !== '') {
    $where[] = 'subject = ?';
    $params[] = $subject;
}
if ($avail !== '') {
    $where[] = 'available = ?';
    $params[] = $avail === 'yes' ? 1 : 0;
}
if ($wants !== '' && in_array($wants, ['yes', 'no'])) {
    $where[] = 'wants_work = ?';
    $params[] = $wants === 'yes' ? 1 : 0;
}
if ($recType !== '') {
    $where[] = 'record_type = ?';
    $params[] = $recType;
}
if ($q !== '') {
    $where[] = '(name LIKE ? OR national_id LIKE ? OR mobile LIKE ? OR specialization LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$total = (int)Db::val('SELECT COUNT(*) FROM substitutes' . $whereSql, $params);
$perPage = 25;
[$pageNo, $pages, $offset] = paginate($total, $perPage, $pageNo);

$rows = Db::all(
    'SELECT * FROM substitutes' . $whereSql . '
     ORDER BY subject ASC, record_type ASC, order_no ASC, gpa DESC
     LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset,
    $params
);

$subjectsList = Db::all('SELECT DISTINCT subject FROM substitutes WHERE subject != "" ORDER BY subject');

layout_header('سجل البدلاء');
?>
<div class="card">
    <div class="card-head wrap">
        <h3>سجل الموظفين المكلفين للعمل كبدلاء (<?= number_format($total) ?>)</h3>
    </div>

    <form method="get" class="filter-bar">
        <input type="hidden" name="page" value="substitutes">
        <select class="input" name="gender" onchange="this.form.submit()">
            <option value="">الجنس: الكل</option>
            <?php foreach (['ذكر', 'أنثى'] as $g): ?>
                <option value="<?= $g ?>" <?= $gender === $g ? 'selected' : '' ?>><?= $g ?></option>
            <?php endforeach; ?>
        </select>
        <select class="input" name="subject" onchange="this.form.submit()">
            <option value="">المبحث: الكل</option>
            <?php foreach ($subjectsList as $s): ?>
                <option value="<?= e($s['subject']) ?>" <?= $subject === $s['subject'] ? 'selected' : '' ?>><?= e($s['subject']) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="input" name="avail" onchange="this.form.submit()">
            <option value="">الحالة: الكل</option>
            <option value="yes" <?= $avail === 'yes' ? 'selected' : '' ?>>متاح للتكليف</option>
            <option value="no" <?= $avail === 'no' ? 'selected' : '' ?>>مكلف حالياً</option>
        </select>
        <select class="input" name="wants" onchange="this.form.submit()">
            <option value="">الرغبة: الكل</option>
            <option value="yes" <?= $wants === 'yes' ? 'selected' : '' ?>>يرغب</option>
            <option value="no" <?= $wants === 'no' ? 'selected' : '' ?>>لا يرغب</option>
        </select>
        <select class="input" name="rec_type" onchange="this.form.submit()">
            <option value="">نوع السجل: الكل</option>
            <?php foreach (Db::all('SELECT DISTINCT record_type FROM substitutes WHERE record_type != "" ORDER BY record_type') as $rt): ?>
                <option value="<?= e($rt['record_type']) ?>" <?= $recType === $rt['record_type'] ? 'selected' : '' ?>><?= e($rt['record_type']) ?></option>
            <?php endforeach; ?>
        </select>
        <input class="input grow" type="search" name="q" value="<?= e($q) ?>" placeholder="بحث بالاسم أو رقم الهوية أو الجوال...">
        <button class="btn" type="submit">بحث</button>
        <?php if ($q !== '' || $gender !== '' || $subject !== '' || $avail !== '' || $wants !== '' || $recType !== ''): ?>
            <a class="btn ghost" href="index.php?page=substitutes">مسح</a>
        <?php endif; ?>
    </form>

    <?php if (!$rows): ?>
        <p class="empty">لا توجد نتائج مطابقة.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>نوع السجل</th>
                        <th>الترتيب</th>
                        <th>الاسم</th>
                        <th>رقم الهوية</th>
                        <th>الجنس</th>
                        <th>المبحث</th>
                        <th>المعدل</th>
                        <th>الجوال</th>
                        <th>يرغب</th>
                        <th>الملاحظات</th>
                        <th>الحالة</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $s): ?>
                        <?php $rowClass = !$s['wants_work'] ? 'row-refuse' : (!$s['available'] ? 'row-busy' : ''); ?>
                        <tr class="<?= $rowClass ?>">
                            <td><span class="badge badge-record"><?= e($s['record_type']) ?></span></td>
                            <td><?= $s['order_no'] ?></td>
                            <td><div class="cell-main"><?= e($s['name']) ?></div>
                                <small class="muted"><?= e(mb_substr($s['qualification'], 0, 30)) ?></small></td>
                            <td><?= e($s['national_id']) ?></td>
                            <td><?= e($s['gender']) ?></td>
                            <td><?= e($s['subject']) ?></td>
                            <td><?= $s['gpa'] ?></td>
                            <td dir="ltr"><?= e($s['mobile']) ?: '—' ?></td>
                            <td>
                                <?php if (Auth::role() === 'admin'): ?>
                                    <button type="button" class="badge <?= $s['wants_work'] ? 'badge-open' : 'badge-refuse' ?>" style="cursor:pointer;border:none" onclick="openRefuseModal(<?= $s['id'] ?>, '<?= e($s['name']) ?>', <?= $s['wants_work'] ?>, '<?= e($s['refuse_date'] ?? '') ?>', '<?= e($s['notes'] ?? '') ?>')" title="اضغط للتعديل">
                                        <?= $s['wants_work'] ? 'يرغب ✓' : 'لا يرغب ✗' ?>
                                    </button>
                                <?php else: ?>
                                    <span class="badge <?= $s['wants_work'] ? 'badge-open' : 'badge-refuse' ?>">
                                        <?= $s['wants_work'] ? 'يرغب' : 'لا يرغب' ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><small><?= e(mb_substr($s['notes'] ?? '', 0, 40)) ?: '—' ?></small></td>
                            <td>
                                <?php if (!$s['wants_work']): ?>
                                    <span class="badge badge-refuse">🚫 لا يمكن التكليف</span>
                                <?php elseif ($s['available']): ?>
                                    <span class="badge badge-open">متاح</span>
                                <?php else: ?>
                                    <span class="badge badge-busy">🚫 مكلف حالياً</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a class="btn tiny" href="index.php?page=substitute&id=<?= $s['id'] ?>">ملف</a>
                                    <?php if ($s['mobile']): ?>
                                        <a class="btn tiny" href="<?= e(tel_link($s['mobile'])) ?>">📞</a>
                                        <a class="btn tiny accent" target="_blank" rel="noopener" href="<?= e(wa_link($s['mobile'])) ?>">💬</a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pages > 1): ?>
            <div class="pager">
                <?php for ($i = 1; $i <= $pages; $i++): ?>
                    <a class="pg <?= $i === $pageNo ? 'active' : '' ?>"
                       href="index.php?page=substitutes&page_no=<?= $i ?>&gender=<?= e($gender) ?>&subject=<?= e($subject) ?>&avail=<?= e($avail) ?>&wants=<?= e($wants) ?>&q=<?= e($q) ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<div class="modal" id="refuseModal">
    <div class="modal-box">
        <h3 style="margin-bottom:14px" id="refuseModalTitle">تعديل حالة الرغبة</h3>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="toggle_wants" id="refuseSubId" value="0">
            <input type="hidden" name="wants_value" id="refuseWantsVal" value="0">
            <div class="form-group">
                <label>الموظف</label>
                <input class="input" id="refuseSubName" disabled>
            </div>
            <div class="form-group" id="refuseFields">
                <label>تاريخ عدم الرغبة</label>
                <input class="input" type="date" name="refuse_date" id="refuseDate" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="form-group" id="notesFields">
                <label>الملاحظات</label>
                <textarea class="input" name="notes" id="refuseNotes" rows="3" placeholder="أدخل سبب عدم الرغبة أو ملاحظات..."></textarea>
            </div>
            <div class="form-actions">
                <button class="btn danger" type="submit" id="refuseSubmitBtn">تأكيد عدم الرغبة</button>
                <button class="btn ghost" type="button" onclick="closeRefuseModal()">إلغاء</button>
            </div>
        </form>
    </div>
</div>

<script>
function openRefuseModal(id, name, currentWants, date, notes) {
    document.getElementById('refuseSubId').value = id;
    document.getElementById('refuseSubName').value = name;
    document.getElementById('refuseDate').value = date || '<?= date('Y-m-d') ?>';
    document.getElementById('refuseNotes').value = notes || '';
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
