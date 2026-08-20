<?php
require VIEWS_DIR . '/layout.php';

$id = (int)get('id', 0);
$edit = false;
$vac = null;

if ($id) {
    $vac = Db::one('SELECT * FROM vacancies WHERE id = ?', [$id]);
    if (!$vac) {
        flash('الطلب غير موجود.', 'error');
        redirect('index.php?page=requests');
    }
    if (!Auth::canEditVacancy($vac)) {
        flash('ليس لديك صلاحية لتعديل هذا الطلب.', 'error');
        redirect('index.php?page=requests');
    }
    $edit = true;
}

// قوائم الإعدادات
$reasons = Settings::arr('reasons');
$jobTitles = Settings::arr('job_titles');
$subjects = Settings::arr('subjects');
$classes = Settings::arr('classes');
$centers = Settings::arr('centers');
$schoolList = Db::all('SELECT id, full_name FROM users WHERE role = "school" AND is_active = 1 ORDER BY full_name');

$d = [
    'school_name' => Auth::isSchool() ? Auth::user()['full_name'] : '',
    'shared_with' => '',
    'reason' => '',
    'center' => '',
    'vacancy_gender' => '',
    'original_national_id' => '',
    'original_name' => '',
    'original_gender' => '',
    'original_job_title' => '',
    'subject' => '',
    'classes' => [],
    'start_date' => '',
    'end_date' => '',
    'notes' => '',
];
if ($edit) {
    foreach ($d as $k => $v) {
        $d[$k] = $vac[$k] ?? $v;
    }
    $d['classes'] = json_get($vac['classes']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $in = [];
    foreach (array_keys($d) as $k) {
        if ($k === 'classes') {
            $in['classes'] = json_set($_POST['classes'] ?? []);
        } else {
            $in[$k] = trim(post($k));
        }
    }
    if (Auth::isSchool()) {
        $in['school_name'] = Auth::user()['full_name'];
    }
    if ($in['school_name'] === '' || $in['original_name'] === '' || $in['reason'] === '') {
        flash('يرجى تعبئة الحقول الأساسية: المدرسة، اسم الموظف الأصيل، سبب الشاغر.', 'error');
    } else {
        try {
            if ($edit) {
                Db::update('vacancies', $in, 'id = ?', [$id]);
                flash('تم تحديث الطلب بنجاح.');
                redirect('index.php?page=request&id=' . $id);
            } else {
                $in['status'] = 'open';
                $in['created_by'] = Auth::id();
                if (Auth::isSchool()) $in['school_user_id'] = Auth::id();
                $newId = Db::insert('vacancies', $in);
                flash('تم إرسال طلب البديل بنجاح.');
                redirect('index.php?page=request&id=' . $newId);
            }
        } catch (Throwable $e) {
            flash('حدث خطأ أثناء الحفظ: ' . $e->getMessage(), 'error');
        }
    }
    $d = $in;
    $d['classes'] = $_POST['classes'] ?? [];
}

layout_header($edit ? 'تعديل طلب بديل' : 'طلب بديل جديد');
?>
<form method="post" class="card form-card">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

    <div class="section-title">🏫 بيانات المدرسة</div>
    <div class="form-grid">
        <div class="form-group span-2">
            <label>المدرسة <span class="req">*</span></label>
            <?php if (Auth::isSchool()): ?>
                <input class="input" value="<?= e($d['school_name']) ?>" disabled>
            <?php else: ?>
                <select class="input" name="school_name" id="schoolNameSelect" onchange="filterShared()">
                    <option value="">— اختر المدرسة —</option>
                    <?php foreach ($schoolList as $s): ?>
                        <option value="<?= e($s['full_name']) ?>" <?= $d['school_name'] === $s['full_name'] ? 'selected' : '' ?>><?= e($s['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </div>
        <div class="form-group">
            <label>مشترك مع مدرسة أخرى (اختياري)</label>
            <select class="input" name="shared_with" id="sharedWithSelect" onchange="filterShared(); autoHalfCenter();">
                <option value="">— لا يوجد تشارك —</option>
                <?php foreach ($schoolList as $s): ?>
                    <option value="<?= e($s['full_name']) ?>" <?= $d['shared_with'] === $s['full_name'] ? 'selected' : '' ?>><?= e($s['full_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="section-title">🕳️ بيانات الشاغر</div>
    <div class="form-grid">
        <div class="form-group">
            <label>سبب الشاغر <span class="req">*</span></label>
            <select class="input" name="reason">
                <option value="">— اختر السبب —</option>
                <?php foreach ($reasons as $r): ?>
                    <option value="<?= e($r) ?>" <?= $d['reason'] === $r ? 'selected' : '' ?>><?= e($r) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>مركز الشاغر</label>
            <select class="input" name="center" id="centerSelect">
                <option value="">— اختر —</option>
                <?php foreach ($centers as $c): ?>
                    <option value="<?= e($c) ?>" <?= $d['center'] === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>جنس الشاغر</label>
            <select class="input" name="vacancy_gender">
                <option value="">— اختر —</option>
                <?php foreach (['ذكر', 'أنثى'] as $g): ?>
                    <option value="<?= $g ?>" <?= $d['vacancy_gender'] === $g ? 'selected' : '' ?>><?= $g ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>المادة الأساسية (المبحث)</label>
            <input class="input" name="subject" value="<?= e($d['subject']) ?>" list="subjectsList" placeholder="أو اكتب مبحثاً">
            <datalist id="subjectsList">
                <?php foreach ($subjects as $s): ?><option value="<?= e($s) ?>"><?php endforeach; ?>
            </datalist>
        </div>
    </div>

    <div class="section-title">👤 الموظف الأصيل</div>
    <div class="form-grid">
        <div class="form-group">
            <label>اسم الموظف الأصيل <span class="req">*</span></label>
            <input class="input" name="original_name" value="<?= e($d['original_name']) ?>" required>
        </div>
        <div class="form-group">
            <label>رقم الهوية</label>
            <input class="input" name="original_national_id" value="<?= e($d['original_national_id']) ?>" inputmode="numeric">
        </div>
        <div class="form-group">
            <label>الجنس</label>
            <select class="input" name="original_gender">
                <option value="">— اختر —</option>
                <?php foreach (['ذكر', 'أنثى'] as $g): ?>
                    <option value="<?= $g ?>" <?= $d['original_gender'] === $g ? 'selected' : '' ?>><?= $g ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>المسمى الوظيفي</label>
            <select class="input" name="original_job_title">
                <option value="">— اختر —</option>
                <?php foreach ($jobTitles as $jt): ?>
                    <option value="<?= e($jt) ?>" <?= $d['original_job_title'] === $jt ? 'selected' : '' ?>><?= e($jt) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="section-title">📚 الصفوف</div>
    <div class="chips">
        <?php foreach ($classes as $c): ?>
            <label class="chip">
                <input type="checkbox" name="classes[]" value="<?= e($c) ?>"
                       <?= in_array($c, (array)$d['classes']) ? 'checked' : '' ?>>
                <span><?= e($c) ?></span>
            </label>
        <?php endforeach; ?>
    </div>

    <div class="section-title">📅 الفترة المتوقعة</div>
    <div class="form-grid">
        <div class="form-group">
            <label>تاريخ البدء</label>
            <input class="input" type="date" name="start_date" value="<?= e($d['start_date']) ?>">
        </div>
        <div class="form-group">
            <label>تاريخ الانتهاء المتوقع</label>
            <input class="input" type="date" name="end_date" value="<?= e($d['end_date']) ?>">
        </div>
        <div class="form-group span-2">
            <label>ملاحظات</label>
            <textarea class="input" name="notes" rows="2"><?= e($d['notes']) ?></textarea>
        </div>
    </div>

    <div class="form-actions">
        <button class="btn primary" type="submit"><?= $edit ? 'حفظ التعديلات' : 'إرسال الطلب' ?></button>
        <a class="btn ghost" href="index.php?page=requests">إلغاء</a>
    </div>
</form>
<script>
function filterShared() {
    var sel = document.getElementById('schoolNameSelect');
    var shared = document.getElementById('sharedWithSelect');
    var val = sel ? sel.value : '<?= e($d['school_name']) ?>';
    for (var i = 0; i < shared.options.length; i++) {
        var opt = shared.options[i];
        if (opt.value && opt.value === val) {
            opt.hidden = true;
            opt.disabled = true;
        } else {
            opt.hidden = false;
            opt.disabled = false;
        }
    }
    if (shared.value === val) shared.value = '';
}
filterShared();
function autoHalfCenter() {
    var shared = document.getElementById('sharedWithSelect');
    var center = document.getElementById('centerSelect');
    if (!center) return;
    if (shared.value) {
        for (var i = 0; i < center.options.length; i++) {
            if (center.options[i].value === 'نصف مركز') {
                center.selectedIndex = i;
                break;
            }
        }
    } else {
        center.selectedIndex = 0;
    }
}
</script>
<?php layout_footer(); ?>
