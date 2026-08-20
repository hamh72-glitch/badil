<?php
require VIEWS_DIR . '/layout.php';

Auth::requireRoles(['admin']);

$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = post('action');

    if ($action === 'general') {
        Settings::set('letter_header', trim(post('letter_header')));
        Settings::set('letter_header_en', trim(post('letter_header_en')));
        Settings::set('letter_footer', trim(post('letter_footer')));
        Settings::set('directorate_name', trim(post('directorate_name')));
        Settings::set('sign_name', trim(post('sign_name')));
        Settings::set('current_year', trim(post('current_year', date('Y'))));
        flash('تم حفظ بيانات الترويسة والتذييل.');
    }

    if ($action === 'lists') {
        foreach (['subjects', 'classes', 'reasons', 'job_titles', 'centers'] as $key) {
            Settings::set($key, lines(post($key)));
        }
        flash('تم حفظ القوائم بنجاح.');
    }

    if ($action === 'upload_logo') {
        $file = $_FILES['logo'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            flash('يرجى اختيار ملف صورة أولاً.', 'error');
        } else {
            $allowed = ['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'];
            if (!in_array($file['type'], $allowed, true)) {
                flash('الملف يجب أن يكون صورة (PNG, JPG, WEBP, SVG).', 'error');
            } else {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $name = 'logo_' . time() . '.' . $ext;
                $dest = __DIR__ . '/../uploads/' . $name;
                if (!is_dir(__DIR__ . '/../uploads')) mkdir(__DIR__ . '/../uploads', 0755, true);
                if (move_uploaded_file($file['tmp_name'], $dest)) {
                    $old = Settings::text('logo_path');
                    if ($old && file_exists(__DIR__ . '/../uploads/' . $old)) {
                        @unlink(__DIR__ . '/../uploads/' . $old);
                    }
                    Settings::set('logo_path', $name);
                    flash('تم رفع الشعار بنجاح.');
                } else {
                    flash('فشل حفظ الملف.', 'error');
                }
            }
        }
    }

    if ($action === 'delete_logo') {
        $old = Settings::text('logo_path');
        if ($old && file_exists(__DIR__ . '/../uploads/' . $old)) {
            @unlink(__DIR__ . '/../uploads/' . $old);
        }
        Settings::set('logo_path', '');
        flash('تم حذف الشعار.');
    }

    if ($action === 'import_erecord' || $action === 'import_schools' || $action === 'import_subjects') {
        $file = $_FILES['xlsx'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            flash('يرجى اختيار ملف إكسل أولاً.', 'error');
        } else {
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, ['xlsx', 'xls'], true)) {
                flash('الملف يجب أن يكون بصيغة إكسل (xlsx).', 'error');
            } else {
                try {
                    if ($action === 'import_erecord') {
                        $r = Importer::importSubstitutes($file['tmp_name']);
                        flash("تم استيراد سجل البدلاء: {$r['inserted']} جديد، {$r['updated']} محدث، {$r['skipped']} متخطى.");
                    } elseif ($action === 'import_schools') {
                        $r = Importer::importSchools($file['tmp_name']);
                        flash("تم استيراد المدارس: {$r['created']} مدرسة جديدة، {$r['updated']} محدثة، {$r['skipped']} متخطاة.");
                    } else {
                        $r = Importer::importSubjects($file['tmp_name']);
                        flash("تم استيراد المباحث: {$r['count']} مبحثاً.");
                    }
                } catch (Throwable $e) {
                    flash('فشل الاستيراد: ' . $e->getMessage(), 'error');
                }
            }
        }
    }
}

$letterHeader = Settings::text('letter_header');
$letterFooter = Settings::text('letter_footer');
$directorateName = Settings::text('directorate_name');
$letterHeaderEn = Settings::text('letter_header_en');
$signName = Settings::text('sign_name');
$year = Settings::text('current_year');

$subjects = Settings::arr('subjects');
$classes = Settings::arr('classes');
$reasons = Settings::arr('reasons');
$jobTitles = Settings::arr('job_titles');
$centers = Settings::arr('centers');

$counts = [
    'subs' => (int)Db::val('SELECT COUNT(*) FROM substitutes'),
    'schools' => (int)Db::val('SELECT COUNT(*) FROM users WHERE role = "school"'),
    'subjects' => count($subjects),
];

$tab = get('tab', 'general');

layout_header('الإعدادات');
?>
<div class="tabs">
    <a class="tab <?= $tab === 'general' ? 'active' : '' ?>" href="index.php?page=settings&tab=general">📜 الترويسة والتذييل</a>
    <a class="tab <?= $tab === 'logo' ? 'active' : '' ?>" href="index.php?page=settings&tab=logo">🖼️ الشعار</a>
    <a class="tab <?= $tab === 'lists' ? 'active' : '' ?>" href="index.php?page=settings&tab=lists">📚 القوائم</a>
    <a class="tab <?= $tab === 'import' ? 'active' : '' ?>" href="index.php?page=settings&tab=import">📥 استيراد الملفات</a>
</div>

<?php if ($tab === 'general'): ?>
    <div class="card form-card">
        <h3 class="section-title">📜 ترويسة الكتب المعتمدة والتذييل</h3>
        <p class="muted small">تُطبع هذه الترويسة أعلى كتب التكليف والتقارير، والتذييل أسفلها.</p>
        <form method="post" class="stack">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="general">
            <div class="form-grid">
                <div class="form-group span-2">
                    <label>اسم المديرية</label>
                    <input class="input" name="directorate_name" value="<?= e($directorateName) ?>">
                </div>
                <div class="form-group">
                    <label>الترويسة بالعربية (كل سطر في سطر مستقل)</label>
                    <textarea class="input" name="letter_header" rows="5"><?= e($letterHeader) ?></textarea>
                    <small class="muted">4 أسطر بالعربية تظهر يمين الشعار</small>
                </div>
                <div class="form-group">
                    <label>الترويسة بالإنجليزية (English Header)</label>
                    <textarea class="input" name="letter_header_en" rows="5" dir="ltr" style="text-align:left"><?= e($letterHeaderEn) ?></textarea>
                    <small class="muted">4 lines in English shown on the left side</small>
                </div>
                <div class="form-group span-2">
                    <label>التذييل (الصفحة الأولى + جميع التقارير)</label>
                    <textarea class="input" name="letter_footer" rows="2"><?= e($letterFooter) ?></textarea>
                    <small class="muted">سطران: السطر الأول المسمى الوظيفي، السطر الثاني الاسم</small>
                </div>
                <div class="form-group">
                    <label>اسم التوقيع (سطر 1: المسمى + سطر 2: الاسم)</label>
                    <textarea class="input" name="sign_name" rows="2" placeholder="مدير التربية والتعليم&#10;الاسم الرباعي"><?= e($signName) ?></textarea>
                </div>
                <div class="form-group">
                    <label>السنة المالية للكتب</label>
                    <input class="input" name="current_year" value="<?= e($year) ?>">
                </div>
            </div>
            <div class="form-actions">
                <button class="btn primary" type="submit">حفظ الترويسة والتذييل</button>
            </div>
        </form>
    </div>

<?php elseif ($tab === 'logo'): ?>
    <?php $currentLogo = logo_url(); ?>
    <div class="card form-card">
        <h3 class="section-title">🖼️ شعار المديرية المعتمد</h3>
        <p class="muted small">يظهر الشعار في الترويسة المعتمدة لجميع التقارير والكتب الرسمية، وصفحة تسجيل الدخول، والشريط الجانبي.</p>

        <div style="display:flex;gap:30px;align-items:flex-start;flex-wrap:wrap;margin-bottom:20px">
            <div>
                <label style="font-weight:700;margin-bottom:8px;display:block">الشعار الحالي:</label>
                <div class="logo-preview <?= $currentLogo ? '' : 'empty' ?>">
                    <?php if ($currentLogo): ?>
                        <img src="<?= e($currentLogo) ?>" alt="شعار المديرية">
                    <?php else: ?>
                        لا يوجد شعار
                    <?php endif; ?>
                </div>
            </div>
            <div style="flex:1;min-width:250px">
                <form method="post" enctype="multipart/form-data" class="stack">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="upload_logo">
                    <div class="form-group">
                        <label>اختر صورة الشعار (PNG, JPG, WEBP, SVG)</label>
                        <input class="input" type="file" name="logo" accept="image/png,image/jpeg,image/webp,image/svg+xml" required>
                    </div>
                    <div class="form-actions">
                        <button class="btn primary" type="submit">📤 رفع الشعار</button>
                    </div>
                </form>

                <?php if ($currentLogo): ?>
                    <form method="post" style="margin-top:12px">
                        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="delete_logo">
                        <button class="btn danger" type="submit" onclick="return confirm('هل أنت متأكد من حذف الشعار؟')">🗑️ حذف الشعار</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

<?php elseif ($tab === 'lists'): ?>
    <div class="card form-card">
        <h3 class="section-title">📚 القوائم</h3>
        <p class="muted small">اكتب كل قيمة في سطر مستقل.</p>
        <form method="post">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="lists">
            <div class="form-grid">
                <div class="form-group">
                    <label>المباحث / المواد</label>
                    <textarea class="input mono" name="subjects" rows="8"><?= e(implode("\n", $subjects)) ?></textarea>
                </div>
                <div class="form-group">
                    <label>الصفوف</label>
                    <textarea class="input mono" name="classes" rows="8"><?= e(implode("\n", $classes)) ?></textarea>
                </div>
                <div class="form-group">
                    <label>أسباب الشاغر</label>
                    <textarea class="input mono" name="reasons" rows="8"><?= e(implode("\n", $reasons)) ?></textarea>
                </div>
                <div class="form-group">
                    <label>المسميات الوظيفية</label>
                    <textarea class="input mono" name="job_titles" rows="8"><?= e(implode("\n", $jobTitles)) ?></textarea>
                </div>
                <div class="form-group">
                    <label>مركز الشاغر</label>
                    <textarea class="input mono" name="centers" rows="4"><?= e(implode("\n", $centers)) ?></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button class="btn primary" type="submit">حفظ القوائم</button>
            </div>
        </form>
    </div>

<?php else: ?>
    <div class="stats-grid">
        <div class="stat-card"><div class="stat-ico" style="background:#d1fae5;color:#047857">👥</div><div><strong><?= $counts['subs'] ?></strong><span>بديل في السجل</span></div></div>
        <div class="stat-card"><div class="stat-ico" style="background:#e0f2fe;color:#0369a1">🏫</div><div><strong><?= $counts['schools'] ?></strong><span>حساب مدرسة</span></div></div>
        <div class="stat-card"><div class="stat-ico" style="background:#fef3c7;color:#b45309">📚</div><div><strong><?= $counts['subjects'] ?></strong><span>مبحث</span></div></div>
    </div>

    <div class="card">
        <div class="card-head">
            <div class="section-title">📥 استيراد سجل البدلاء (ملف Erecord.xlsx)</div>
            <a class="btn ghost tiny" href="export_template.php?type=erecord" download>📥 تصدير سجل البدلاء</a>
        </div>
        <p class="muted small">يستورد أعمدة الملف بنفس ترتيبها (الجنس، المادة، نوع السجل، الترتيب، الاسم، الهوية، المعدل، الجوال، الملاحظات). يتم تحديث البيانات عند إعادة الاستيراد دون فقدان سجلات العمل.</p>
        <form method="post" enctype="multipart/form-data" class="stack">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="import_erecord">
            <div class="upload-row">
                <input class="input" type="file" name="xlsx" accept=".xlsx,.xls" required>
                <button class="btn primary" type="submit">استيراد</button>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-head">
            <div class="section-title">🏫 استيراد المدارس وكلمات السر (ملف المستخدمين وكلمات السر.xlsx)</div>
            <a class="btn ghost tiny" href="export_template.php?type=schools" download>📥 تصدير قائمة المدارس</a>
        </div>
        <p class="muted small">الأعمدة: اسم المدرسة، اسم المستخدم (الرقم الوطني)، كلمة السر الافتراضية (الرقم الوطني). تنشأ حسابات المدارس تلقائياً.</p>
        <form method="post" enctype="multipart/form-data" class="stack">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="import_schools">
            <div class="upload-row">
                <input class="input" type="file" name="xlsx" accept=".xlsx,.xls" required>
                <button class="btn primary" type="submit">استيراد</button>
            </div>
        </form>
    </div>

    <div class="card">
        <div class="card-head">
            <div class="section-title">📚 استيراد المباحث (ملف المباحث.xlsx)</div>
            <a class="btn ghost tiny" href="export_template.php?type=subjects" download>📥 تصدير قائمة المباحث</a>
        </div>
        <p class="muted small">يُستورد العمود الأول من الملف ويُحفظ في قائمة المباحث.</p>
        <form method="post" enctype="multipart/form-data" class="stack">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="import_subjects">
            <div class="upload-row">
                <input class="input" type="file" name="xlsx" accept=".xlsx,.xls" required>
                <button class="btn primary" type="submit">استيراد</button>
            </div>
        </form>
    </div>
<?php endif; ?>
<?php layout_footer(); ?>
