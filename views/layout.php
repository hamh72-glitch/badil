<?php
/**
 * التخطيط العام: رأس الصفحة والقائمة الجانبية
 */
function nav_items(): array
{
    $role = Auth::role();
    $items = [
        ['page' => 'dashboard', 'label' => 'لوحة التحكم', 'icon' => '🏠'],
        ['page' => 'requests', 'label' => 'طلبات البديل', 'icon' => '📋'],
    ];
    if ($role !== 'school') {
        $items[] = ['page' => 'substitutes', 'label' => 'سجل البدلاء', 'icon' => '👥'];
    }
    if (in_array($role, ['admin', 'directorate'])) {
        $items[] = ['page' => 'reports', 'label' => 'التقارير والطباعة', 'icon' => '🖨️'];
    }
    if ($role === 'admin') {
        $items[] = ['page' => 'settings', 'label' => 'الإعدادات', 'icon' => '⚙️'];
        $items[] = ['page' => 'users', 'label' => 'المدارس والمستخدمون', 'icon' => '🏫'];
    }
    return $items;
}

function layout_header(string $title, array $extra = []): void
{
    $user = Auth::user();
    $role = Auth::role();
    $current = get('page', 'dashboard');
    ?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= e($title) ?> | <?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css">
</head>
<body>
<div class="app">
    <aside class="sidebar" id="sidebar">
        <div class="side-brand">
            <?php $lg = logo_url(); ?>
            <div class="brand-logo"><?php if ($lg): ?><img src="<?= e($lg) ?>" alt="شعار"><?php else: ?>ب<?php endif; ?></div>
            <div class="side-brand-text">
                <strong><?= e(APP_NAME) ?></strong>
                <span><?= e(APP_DIRECTORATE) ?></span>
            </div>
        </div>
        <nav class="side-nav">
            <?php foreach (nav_items() as $it): ?>
                <a class="nav-item <?= $current === $it['page'] ? 'active' : '' ?>"
                   href="index.php?page=<?= e($it['page']) ?>">
                    <span class="nav-ico"><?= $it['icon'] ?></span>
                    <?= e($it['label']) ?>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="side-foot">
            <a class="nav-item" href="index.php?page=password"><span class="nav-ico">🔑</span> تغيير كلمة السر</a>
            <a class="nav-item" href="index.php?page=logout"><span class="nav-ico">🚪</span> تسجيل الخروج</a>
        </div>
    </aside>
    <div class="sidebar-overlay" id="sideOverlay"></div>

    <div class="main">
        <header class="topbar">
            <button class="icon-btn menu-btn" id="menuBtn" aria-label="القائمة">☰</button>
            <div class="topbar-title">
                <h2><?= e($title) ?></h2>
                <span><?= date('l، d F Y', time()) ?></span>
            </div>
            <div class="topbar-user">
                <a class="btn tiny ghost" href="index.php?page=logout" title="تسجيل الخروج">🚪 خروج</a>
                <div class="avatar"><?= e(mb_substr($user['full_name'], 0, 1)) ?></div>
                <div class="user-info">
                    <strong><?= e($user['full_name']) ?></strong>
                    <span class="role-badge role-<?= e($role) ?>"><?= e(Auth::roleLabel($role)) ?></span>
                </div>
            </div>
        </header>

        <main class="content">
        <?php
        foreach (flash_all() as $f) {
            echo '<div class="alert ' . e($f['type']) . '">' . e($f['msg']) . '</div>';
        }
}

function layout_footer(): void
{
        ?>
        </main>
        <nav class="mobile-nav">
            <?php foreach (nav_items() as $it): ?>
                <a class="m-nav-item <?= (get('page', 'dashboard') === $it['page']) ? 'active' : '' ?>"
                   href="index.php?page=<?= e($it['page']) ?>">
                    <span><?= $it['icon'] ?></span>
                    <small><?= e($it['label']) ?></small>
                </a>
            <?php endforeach; ?>
        </nav>
    </div>
</div>
<script src="assets/app.js"></script>
</body>
</html>
<?php
}

/** زر الطباعة */
function print_btn(string $selector = '.print-area', string $label = 'طباعة'): void
{
    echo '<button type="button" class="btn primary" onclick="printArea(\'' . e($selector) . '\')">🖨️ ' . e($label) . '</button>';
}

/** مسار رجوع */
function back_btn(string $href, string $label = 'رجوع'): void
{
    echo '<a class="btn ghost" href="' . e($href) . '">→ ' . e($label) . '</a>';
}
