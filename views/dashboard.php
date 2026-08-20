<?php
require VIEWS_DIR . '/layout.php';
$role = Auth::role();
$user = Auth::user();
$isAdmin = Auth::isAdmin();
$isDirectorate = Auth::isDirectorate();
$isSchool = Auth::isSchool();

if (Auth::isSchool()) {
    $stats = [
        'all' => (int)Db::val('SELECT COUNT(*) FROM vacancies WHERE school_user_id = ?', [Auth::id()]),
        'open' => (int)Db::val('SELECT COUNT(*) FROM vacancies WHERE school_user_id = ? AND status = "open"', [Auth::id()]),
        'assigned' => (int)Db::val('SELECT COUNT(*) FROM vacancies WHERE school_user_id = ? AND status = "assigned"', [Auth::id()]),
        'ended' => (int)Db::val('SELECT COUNT(*) FROM vacancies WHERE school_user_id = ? AND status = "ended"', [Auth::id()]),
    ];
} else {
    $stats = [
        'all' => (int)Db::val('SELECT COUNT(*) FROM vacancies'),
        'open' => (int)Db::val('SELECT COUNT(*) FROM vacancies WHERE status = "open"'),
        'assigned' => (int)Db::val('SELECT COUNT(*) FROM vacancies WHERE status = "assigned"'),
        'ended' => (int)Db::val('SELECT COUNT(*) FROM vacancies WHERE status = "ended"'),
    ];
}
$subsTotal = (int)Db::val('SELECT COUNT(*) FROM substitutes');
$subsAvailable = (int)Db::val('SELECT COUNT(*) FROM substitutes WHERE available = 1');
$subsWorking = (int)Db::val('SELECT COUNT(*) FROM substitutes WHERE available = 0');

// أحدث الطلبات
if (Auth::isSchool()) {
    $recent = Db::all('SELECT * FROM vacancies WHERE school_user_id = ? ORDER BY created_at DESC LIMIT 5', [Auth::id()]);
} else {
    $recent = Db::all('SELECT * FROM vacancies ORDER BY created_at DESC LIMIT 5');
}

layout_header('لوحة التحكم');
?>
<div class="greet-card">
    <div>
        <h3>مرحباً، <?= e($user['full_name']) ?></h3>
        <p class="muted"><?= $isAdmin ? 'لديك صلاحيات كاملة لإدارة النظام والبدلاء والمدارس.' : ($isDirectorate ? 'يمكنك متابعة البدلاء وإنهاء أعمالهم وطباعة التقارير.' : 'يمكنك إرسال طلبات البديل ومتابعة البدلاء المكلفين في مدرستك.') ?></p>
    </div>
    <?php if (Auth::isSchool()): ?>
        <a class="btn primary" href="index.php?page=request">+ طلب بديل جديد</a>
    <?php elseif ($isDirectorate || $isAdmin): ?>
        <a class="btn primary" href="index.php?page=request">+ إضافة طلب شاغر</a>
    <?php endif; ?>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-ico" style="background:#e0f2fe;color:#0369a1">📋</div>
        <div><strong><?= $stats['all'] ?></strong><span>إجمالي الطلبات</span></div>
    </div>
    <div class="stat-card">
        <div class="stat-ico" style="background:#fef3c7;color:#b45309">🕓</div>
        <div><strong><?= $stats['open'] ?></strong><span>طلبات مفتوحة</span></div>
    </div>
    <div class="stat-card">
        <div class="stat-ico" style="background:#d1fae5;color:#047857">✅</div>
        <div><strong><?= $stats['assigned'] ?></strong><span>مكلف حالياً</span></div>
    </div>
    <div class="stat-card">
        <div class="stat-ico" style="background:#e2e8f0;color:#475569">🏁</div>
        <div><strong><?= $stats['ended'] ?></strong><span>منتهية</span></div>
    </div>
</div>

<?php if (!$isSchool): ?>
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-ico" style="background:#ede9fe;color:#6d28d9">👥</div>
        <div><strong><?= $subsTotal ?></strong><span>إجمالي البدلاء</span></div>
    </div>
    <div class="stat-card">
        <div class="stat-ico" style="background:#d1fae5;color:#047857">🟢</div>
        <div><strong><?= $subsAvailable ?></strong><span>متاحون للتكليف</span></div>
    </div>
    <div class="stat-card">
        <div class="stat-ico" style="background:#fee2e2;color:#b91c1c">🔄</div>
        <div><strong><?= $subsWorking ?></strong><span>مكلفون حالياً</span></div>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-head">
        <h3>أحدث طلبات البديل</h3>
        <a class="link" href="index.php?page=requests">عرض الكل</a>
    </div>
    <?php if (!$recent): ?>
        <p class="empty">لا توجد طلبات بعد.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>المدرسة</th>
                        <th>الموظف الأصيل</th>
                        <th>المبحث</th>
                        <th>السبب</th>
                        <th>الحالة</th>
                        <th>التاريخ</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $v): ?>
                        <tr>
                            <td><?= e($v['school_name']) ?></td>
                            <td><?= e($v['original_name']) ?></td>
                            <td><?= e($v['subject']) ?></td>
                            <td><?= e($v['reason']) ?></td>
                            <td><?php require VIEWS_DIR . '/_status_badge.php'; ?></td>
                            <td><?= e(fmt_date($v['created_at'])) ?></td>
                            <td><a class="btn tiny" href="index.php?page=request&id=<?= $v['id'] ?>">عرض</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php layout_footer(); ?>
