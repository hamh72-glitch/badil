<?php
require VIEWS_DIR . '/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');
    if ($action === 'mark_read') {
        Notifications::markRead(Auth::id());
        flash('تم تعليم الكل كمقروء.');
        redirect('index.php?page=notifications');
    }
    if ($action === 'delete') {
        $nid = (int)post('nid');
        Notifications::delete($nid, Auth::id());
        redirect('index.php?page=notifications');
    }
}

$notifications = Notifications::latest(Auth::id(), 50);
Notifications::markRead(Auth::id());

layout_header('الإشعارات');
?>

<div class="card">
    <div class="card-head">
        <h3>الإشعارات</h3>
        <?php if ($notifications): ?>
            <form method="post" class="inline">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="mark_read">
                <button class="btn tiny ghost" type="submit">تعليم الكل كمقروء</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if (!$notifications): ?>
        <p class="empty">لا توجد إشعارات.</p>
    <?php else: ?>
        <div class="notif-list">
            <?php foreach ($notifications as $n): ?>
                <div class="notif-item <?= !$n['is_read'] ? 'unread' : '' ?>">
                    <div class="notif-icon">
                        <?php if ($n['type'] === 'shared_request'): ?>
                            🔗
                        <?php else: ?>
                            🔔
                        <?php endif; ?>
                    </div>
                    <div class="notif-body">
                        <p><?= e($n['message']) ?></p>
                        <small class="muted"><?= e(fmt_datetime($n['created_at'])) ?></small>
                    </div>
                    <div class="notif-actions">
                        <?php if ($n['vacancy_id']): ?>
                            <a class="btn tiny" href="index.php?page=request_view&id=<?= $n['vacancy_id'] ?>">عرض الطلب</a>
                        <?php endif; ?>
                        <form method="post" class="inline">
                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="nid" value="<?= $n['id'] ?>">
                            <button class="btn tiny danger-ghost" type="submit">✕</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php layout_footer(); ?>
