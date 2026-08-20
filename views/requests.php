<?php
require VIEWS_DIR . '/layout.php';

// معالجة الحذف (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('action') === 'delete_vacancy') {
    csrf_check();
    $vid = (int)post('id');
    $vac = Db::one('SELECT * FROM vacancies WHERE id = ?', [$vid]);
    if ($vac && Auth::canDeleteVacancy()) {
        if ($vac['substitute_id']) {
            Db::q('UPDATE substitutes SET available = 1, current_vacancy_id = NULL WHERE id = ?', [$vac['substitute_id']]);
        }
        Db::delete('vacancies', 'id = ?', [$vid]);
        flash('تم حذف الطلب.');
    }
    redirect('index.php?page=requests');
}

$isSchool = Auth::isSchool();
$status = get('status');
$schoolQ = get('school');
$q = trim(get('q'));
$pageNo = (int)get('page_no', 1);

$where = [];
$params = [];
if ($isSchool) {
    $where[] = 'v.school_user_id = ?';
    $params[] = Auth::id();
}
if ($status !== '' && in_array($status, ['open', 'assigned', 'ended', 'cancelled'])) {
    $where[] = 'v.status = ?';
    $params[] = $status;
}
if (!$isSchool && $schoolQ !== '') {
    $where[] = 'v.school_name = ?';
    $params[] = $schoolQ;
}
if ($q !== '') {
    $where[] = '(v.school_name LIKE ? OR v.original_name LIKE ? OR v.subject LIKE ? OR v.substitute_id IN (SELECT id FROM substitutes WHERE name LIKE ?))';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like, $like);
}
$whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

$total = (int)Db::val('SELECT COUNT(*) FROM vacancies v' . $whereSql, $params);
$perPage = 20;
[$pageNo, $pages, $offset] = paginate($total, $perPage, $pageNo);

$rows = Db::all(
    'SELECT v.*, s.name AS sub_name, s.gender AS sub_gender, s.mobile AS sub_mobile, s.subject AS sub_subject
     FROM vacancies v
     LEFT JOIN substitutes s ON s.id = v.substitute_id' . $whereSql . '
     ORDER BY FIELD(v.status,"open","assigned","ended","cancelled"), v.created_at DESC
     LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset,
    $params
);

// قائمة المدارس للفلتر
$schools = [];
if (!$isSchool) {
    $schools = Db::all('SELECT DISTINCT school_name FROM vacancies ORDER BY school_name');
}

layout_header('طلبات البديل');
?>

<div class="card">
    <div class="card-head wrap">
        <h3>طلبات البديل</h3>
        <div class="row">
            <a class="btn primary" href="index.php?page=request">+ طلب جديد</a>
        </div>
    </div>

    <form method="get" class="filter-bar">
        <input type="hidden" name="page" value="requests">
        <select class="input" name="status" onchange="this.form.submit()">
            <option value="">كل الحالات</option>
            <?php foreach (['open' => 'مفتوح', 'assigned' => 'مكلف', 'ended' => 'منتهي', 'cancelled' => 'ملغي'] as $sv => $sl): ?>
                <option value="<?= $sv ?>" <?= $status === $sv ? 'selected' : '' ?>><?= $sl ?></option>
            <?php endforeach; ?>
        </select>
        <?php if (!$isSchool): ?>
            <select class="input" name="school" onchange="this.form.submit()">
                <option value="">كل المدارس</option>
                <?php foreach ($schools as $s): ?>
                    <option value="<?= e($s['school_name']) ?>" <?= $schoolQ === $s['school_name'] ? 'selected' : '' ?>><?= e($s['school_name']) ?></option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
        <input class="input grow" type="search" name="q" value="<?= e($q) ?>" placeholder="بحث: مدرسة، أصيل، مبحث، بديل...">
        <button class="btn" type="submit">بحث</button>
    </form>

    <?php if (!$rows): ?>
        <p class="empty">لا توجد طلبات مطابقة.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>المدرسة</th>
                        <th>الموظف الأصيل</th>
                        <th>المبحث</th>
                        <th>السبب</th>
                        <th>البديل</th>
                        <th>الحالة</th>
                        <th>التاريخ</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rows as $v): ?>
                        <tr>
                            <td><?= $v['id'] ?></td>
                            <td><?= e($v['school_name']) ?></td>
                            <td>
                                <div class="cell-main"><?= e($v['original_name']) ?></div>
                                <small class="muted"><?= e($v['original_national_id']) ?></small>
                            </td>
                            <td><?= e($v['subject']) ?></td>
                            <td><?= e($v['reason']) ?></td>
                            <td>
                                <?php if ($v['substitute_id']): ?>
                                    <div class="cell-main"><?= e($v['sub_name']) ?></div>
                                    <small class="muted"><?= e($v['sub_mobile']) ?></small>
                                <?php else: ?>
                                    <span class="muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= e($v['status']) ?>">
                                    <?= ['open' => 'مفتوح', 'assigned' => 'مكلف', 'ended' => 'منتهي', 'cancelled' => 'ملغي'][$v['status']] ?>
                                </span>
                            </td>
                            <td><?= e(fmt_date($v['created_at'])) ?></td>
                            <td>
                                <div class="row-actions">
                                    <a class="btn tiny" href="index.php?page=request_view&id=<?= $v['id'] ?>">عرض</a>
                                    <?php if ($v['status'] === 'open' && Auth::canAssign()): ?>
                                        <a class="btn tiny accent" href="index.php?page=assign&id=<?= $v['id'] ?>">تكليف</a>
                                    <?php endif; ?>
                                    <?php if ($v['status'] === 'assigned' && Auth::canEndWork()): ?>
                                        <a class="btn tiny danger" href="index.php?page=end_work&id=<?= $v['id'] ?>">إنهاء</a>
                                    <?php endif; ?>
                                    <?php if (Auth::canEditVacancy($v)): ?>
                                        <a class="btn tiny ghost" href="index.php?page=request&id=<?= $v['id'] ?>">تعديل</a>
                                    <?php endif; ?>
                                    <?php if (Auth::canDeleteVacancy()): ?>
                                        <form method="post" action="index.php?page=requests" class="inline"
                                              onsubmit="return confirm('هل أنت متأكد من حذف هذا الطلب نهائياً؟');">
                                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="delete_vacancy">
                                            <input type="hidden" name="id" value="<?= $v['id'] ?>">
                                            <button class="btn tiny danger-ghost" type="submit">حذف</button>
                                        </form>
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
                       href="index.php?page=requests&page_no=<?= $i ?>&status=<?= e($status) ?>&school=<?= e($schoolQ) ?>&q=<?= e($q) ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
layout_footer();
?>
