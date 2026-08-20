<?php
require VIEWS_DIR . '/layout.php';

Auth::requireRoles(['admin']);
$me = Auth::id();
$allPerms = Auth::availablePermissions();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');

    if ($action === 'create_directorate') {
        $username = trim(post('username'));
        $fullName = trim(post('full_name'));
        $password = post('password');
        $perms = post('perms');
        if (!is_array($perms)) $perms = [];
        $perms = array_intersect($perms, array_keys($allPerms));
        if ($username === '' || $fullName === '' || strlen($password) < 6) {
            flash('يرجى تعبئة البيانات وكلمة سر لا تقل عن 6 أحرف.', 'error');
        } elseif (Db::val('SELECT COUNT(*) FROM users WHERE username = ?', [$username])) {
            flash('اسم المستخدم موجود مسبقاً.', 'error');
        } else {
            Db::insert('users', [
                'username' => $username,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'full_name' => $fullName,
                'role' => 'directorate',
                'permissions' => json_encode($perms),
                'is_active' => 1,
            ]);
            flash('تم إنشاء حساب المديرية بنجاح.');
        }
    }

    if ($action === 'edit_directorate') {
        $uid = (int)post('uid');
        $fullName = trim(post('full_name'));
        $perms = post('perms');
        if (!is_array($perms)) $perms = [];
        $perms = array_intersect($perms, array_keys($allPerms));
        Db::q('UPDATE users SET full_name = ?, permissions = ? WHERE id = ? AND role = "directorate"',
            [$fullName, json_encode($perms), $uid]);
        flash('تم تحديث حساب المديرية.');
    }

    if ($action === 'password') {
        $uid = (int)post('uid');
        $newPass = post('new_pass');
        if (strlen($newPass) < 6) {
            flash('كلمة السر يجب أن لا تقل عن 6 أحرف.', 'error');
        } else {
            Db::q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($newPass, PASSWORD_DEFAULT), $uid]);
            flash('تم تغيير كلمة السر بنجاح.');
        }
    }

    if ($action === 'toggle') {
        $uid = (int)post('uid');
        if ($uid !== $me) {
            Db::q('UPDATE users SET is_active = 1 - is_active WHERE id = ?', [$uid]);
            flash('تم تحديث حالة الحساب.');
        }
    }

    if ($action === 'delete') {
        $uid = (int)post('uid');
        if ($uid !== $me) {
            Db::delete('users', 'id = ?', [$uid]);
            flash('تم حذف الحساب.');
        }
    }
}

$directorates = Db::all('SELECT * FROM users WHERE role = "directorate" ORDER BY full_name');
$schools = Db::all('SELECT * FROM users WHERE role = "school" ORDER BY full_name');
$adminCount = Db::val('SELECT COUNT(*) FROM users WHERE role = "admin"');

layout_header('المدارس والمستخدمون');
?>

<?php if ($adminCount <= 1): ?>
    <div class="alert info">أنشئ حسابات المديرية لمنح صلاحيات محددة (إنهاء أعمال، تقارير، تكليف، اتصال).</div>
<?php endif; ?>

<!-- قسم حسابات المديرية -->
<div class="card">
    <div class="card-head">
        <h3>حسابات المديرية (<?= count($directorates) ?>)</h3>
    </div>

    <div class="card form-card" style="margin-bottom:18px">
        <div class="section-title">إنشاء حساب مديرية جديد</div>
        <form method="post" class="stack">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="create_directorate">
            <div class="form-grid">
                <div class="form-group">
                    <label>اسم المستخدم</label>
                    <input class="input" name="username" required placeholder="الرقم الوطني">
                </div>
                <div class="form-group">
                    <label>الاسم الكامل</label>
                    <input class="input" name="full_name" required placeholder="الاسم الرباعي">
                </div>
                <div class="form-group">
                    <label>كلمة السر</label>
                    <input class="input" type="password" name="password" required minlength="6">
                </div>
            </div>
            <div class="form-group">
                <label>الصلاحيات</label>
                <div class="chips">
                    <?php foreach ($allPerms as $key => $label): ?>
                        <label class="chip">
                            <input type="checkbox" name="perms[]" value="<?= $key ?>">
                            <span><?= e($label) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="form-actions">
                <button class="btn primary" type="submit">إنشاء الحساب</button>
            </div>
        </form>
    </div>

    <?php if (!$directorates): ?>
        <p class="empty">لا توجد حسابات مديرية بعد.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>الاسم</th>
                        <th>اسم المستخدم</th>
                        <th>الصلاحيات</th>
                        <th>الحالة</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($directorates as $u):
                        $perms = json_decode($u['permissions'] ?? '[]', true);
                        if (!is_array($perms)) $perms = [];
                    ?>
                        <tr>
                            <td><strong><?= e($u['full_name']) ?></strong></td>
                            <td dir="ltr"><?= e($u['username']) ?></td>
                            <td>
                                <div class="row-actions">
                                    <?php foreach ($allPerms as $k => $l): ?>
                                        <?php if (in_array($k, $perms, true)): ?>
                                            <span class="badge badge-open" style="font-size:11px"><?= e($l) ?></span>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                    <?php if (empty($perms)): ?>
                                        <span class="muted small">بدون صلاحيات</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?= $u['is_active'] ? 'badge-open' : 'badge-cancelled' ?>">
                                    <?= $u['is_active'] ? 'مفعل' : 'موقوف' ?>
                                </span>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <button class="btn tiny" onclick="openEditDir(<?= $u['id'] ?>, '<?= e($u['full_name']) ?>', <?= e(json_encode($perms)) ?>)">تعديل</button>
                                    <button class="btn tiny" onclick="openPass(<?= $u['id'] ?>, '<?= e($u['full_name']) ?>')">🔑 السر</button>
                                    <?php if ($u['id'] !== $me): ?>
                                        <form method="post" class="inline" onsubmit="return confirm('تأكيد؟');">
                                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="uid" value="<?= $u['id'] ?>">
                                            <button class="btn tiny ghost" type="submit"><?= $u['is_active'] ? 'إيقاف' : 'تفعيل' ?></button>
                                        </form>
                                        <form method="post" class="inline" onsubmit="return confirm('حذف الحساب نهائياً؟');">
                                            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="uid" value="<?= $u['id'] ?>">
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
    <?php endif; ?>
</div>

<!-- قسم حسابات المدارس -->
<div class="card">
    <div class="card-head">
        <h3>حسابات المدارس (<?= count($schools) ?>)</h3>
    </div>
    <p class="muted small" style="margin-bottom:14px">يتم إنشاء حسابات المدارس تلقائياً من ملف الاستيراد. يمكنك تعديل كلمة السر من هنا.</p>
    <?php if (!$schools): ?>
        <p class="empty">لا توجد حسابات مدارس. قم باستيراد ملف المدارس من الإعدادات.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>اسم المدرسة</th>
                        <th>اسم المستخدم</th>
                        <th>الحالة</th>
                        <th>إجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($schools as $u): ?>
                        <tr>
                            <td><strong><?= e($u['full_name']) ?></strong></td>
                            <td dir="ltr"><?= e($u['username']) ?></td>
                            <td>
                                <span class="badge <?= $u['is_active'] ? 'badge-open' : 'badge-cancelled' ?>">
                                    <?= $u['is_active'] ? 'مفعل' : 'موقوف' ?>
                                </span>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <button class="btn tiny" onclick="openPass(<?= $u['id'] ?>, '<?= e($u['full_name']) ?>')">🔑 تغيير السر</button>
                                    <form method="post" class="inline" onsubmit="return confirm('تأكيد؟');">
                                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="uid" value="<?= $u['id'] ?>">
                                        <button class="btn tiny ghost" type="submit"><?= $u['is_active'] ? 'إيقاف' : 'تفعيل' ?></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- نافذة تغيير كلمة السر -->
<div class="modal" id="passModal">
    <div class="modal-box">
        <h3 id="passTitle">تغيير كلمة السر</h3>
        <form method="post" class="stack">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="password">
            <input type="hidden" name="uid" id="passUid">
            <div class="form-group">
                <label>كلمة السر الجديدة</label>
                <input class="input" type="text" name="new_pass" required minlength="6" id="passInput2">
            </div>
            <div class="form-actions">
                <button class="btn primary" type="submit">حفظ</button>
                <button class="btn ghost" type="button" onclick="closeModal('passModal')">إلغاء</button>
            </div>
        </form>
    </div>
</div>

<!-- نافذة تعديل حساب المديرية -->
<div class="modal" id="editDirModal">
    <div class="modal-box">
        <h3 id="editDirTitle">تعديل حساب المديرية</h3>
        <form method="post" class="stack">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="edit_directorate">
            <input type="hidden" name="uid" id="editDirUid">
            <div class="form-group">
                <label>الاسم الكامل</label>
                <input class="input" name="full_name" id="editDirName" required>
            </div>
            <div class="form-group">
                <label>الصلاحيات</label>
                <div class="chips" id="editDirPerms">
                    <?php foreach ($allPerms as $key => $label): ?>
                        <label class="chip">
                            <input type="checkbox" name="perms[]" value="<?= $key ?>" id="perm_<?= $key ?>">
                            <span><?= e($label) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="form-actions">
                <button class="btn primary" type="submit">حفظ التعديلات</button>
                <button class="btn ghost" type="button" onclick="closeModal('editDirModal')">إلغاء</button>
            </div>
        </form>
    </div>
</div>

<script>
function openPass(uid, name) {
    document.getElementById('passUid').value = uid;
    document.getElementById('passTitle').textContent = 'تغيير كلمة السر - ' + name;
    document.getElementById('passInput2').value = '';
    document.getElementById('passModal').classList.add('open');
}
function openEditDir(uid, name, perms) {
    document.getElementById('editDirUid').value = uid;
    document.getElementById('editDirName').value = name;
    document.getElementById('editDirTitle').textContent = 'تعديل - ' + name;
    var allPerms = <?= json_encode(array_keys($allPerms)) ?>;
    allPerms.forEach(function(p) {
        document.getElementById('perm_' + p).checked = perms.indexOf(p) !== -1;
    });
    document.getElementById('editDirModal').classList.add('open');
}
function closeModal(id) {
    document.getElementById(id).classList.remove('open');
}
</script>
<?php layout_footer(); ?>
