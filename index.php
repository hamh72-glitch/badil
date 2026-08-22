<?php
/**
 * وحدة التحكم الرئيسية - نظام إدارة البدلاء
 */
require __DIR__ . '/lib/bootstrap.php';

// التثبيت
$installed = false;
try {
    $installed = (int)Db::val('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = "users"') > 0;
} catch (Throwable $e) {
    $installed = false;
}
if (!$installed) {
    redirect('install.php');
}

$page = get('page', 'dashboard');

// صفحات عامة
if ($page === 'login') {
    require VIEWS_DIR . '/login.php';
    exit;
}

// صفحات محمية
Auth::requireLogin();

// تسجيل الخروج
if ($page === 'logout') {
    Auth::logout();
    redirect('index.php?page=login');
}

$role = Auth::role();

switch ($page) {
    case 'dashboard':
        require VIEWS_DIR . '/dashboard.php';
        break;
    case 'requests':
        require VIEWS_DIR . '/requests.php';
        break;
    case 'request':
        require VIEWS_DIR . '/request_form.php';
        break;
    case 'request_view':
        require VIEWS_DIR . '/request_view.php';
        break;
    case 'substitutes':
        require VIEWS_DIR . '/substitutes.php';
        break;
    case 'substitute':
        require VIEWS_DIR . '/substitute_view.php';
        break;
    case 'assign':
        require VIEWS_DIR . '/assign.php';
        break;
    case 'end_work':
        require VIEWS_DIR . '/end_work.php';
        break;
    case 'reports':
        require VIEWS_DIR . '/reports.php';
        break;
    case 'report':
        require VIEWS_DIR . '/report_view.php';
        break;
    case 'settings':
        Auth::requireRoles(['admin']);
        require VIEWS_DIR . '/settings.php';
        break;
    case 'users':
        Auth::requireRoles(['admin']);
        require VIEWS_DIR . '/users.php';
        break;
    case 'password':
        require VIEWS_DIR . '/password.php';
        break;
    case 'notifications':
        require VIEWS_DIR . '/notifications.php';
        break;
    default:
        require VIEWS_DIR . '/dashboard.php';
}
