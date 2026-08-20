<?php
/**
 * صفحة عرض وطباعة التقارير
 * type: assignment | substitute | all | school
 */
require VIEWS_DIR . '/layout.php';

if (!in_array(Auth::role(), ['admin', 'directorate'], true)) {
    flash('التقارير متاحة لحساب المديرية ومسؤول النظام.', 'error');
    redirect('index.php?page=dashboard');
}

$type = get('type', 'assignment');
$id = (int)get('id', 0);

// بيانات عامة للترويسة
$headerLines = lines(Settings::text('letter_header'));
$headerLinesEn = lines(Settings::text('letter_header_en'));
$footerText = Settings::text('letter_footer');
$directorate = Settings::text('directorate_name');

// تجهيز بيانات كل نوع
$doc = null;       // العنوان
$rows = null;      // صفوف الجداول
$data = null;      // بيانات خاصة

switch ($type) {
    case 'assignment':
        $vac = Db::one('SELECT v.*, s.name AS sub_name, s.national_id AS sub_national_id, s.gender AS sub_gender,
                        s.mobile AS sub_mobile, s.subject AS sub_subject, s.record_type AS sub_record_type,
                        s.address AS sub_address, s.qualification AS sub_qual
                        FROM vacancies v LEFT JOIN substitutes s ON s.id = v.substitute_id
                        WHERE v.id = ?', [$id]);
        if (!$vac || !$vac['substitute_id']) {
            flash('لم يتم العثور على التكليف.', 'error');
            redirect('index.php?page=reports');
        }
        $doc = 'كتاب تكليف للعمل';
        break;

    case 'request':
        $vac = Db::one('SELECT v.*, s.name AS sub_name, s.national_id AS sub_national_id, s.gender AS sub_gender,
                        s.mobile AS sub_mobile, s.subject AS sub_subject, s.record_type AS sub_record_type,
                        s.qualification AS sub_qual
                        FROM vacancies v LEFT JOIN substitutes s ON s.id = v.substitute_id
                        WHERE v.id = ?', [$id]);
        if (!$vac) {
            flash('الطلب غير موجود.', 'error');
            redirect('index.php?page=reports');
        }
        $doc = 'تقرير طلب البديل #' . $vac['id'];
        break;

    case 'substitute':
        $sub = Db::one('SELECT * FROM substitutes WHERE id = ?', [$id]);
        if (!$sub) { flash('البديل غير موجود.', 'error'); redirect('index.php?page=reports'); }
        $rows = Db::all(
            'SELECT * FROM vacancies WHERE substitute_id = ? AND status IN ("assigned","ended") ORDER BY assigned_at DESC',
            [$id]
        );
        $doc = 'تقرير عمل بديل معين';
        $data = $sub;
        break;

    case 'all':
        $rows = Db::all(
            'SELECT s.id, s.name, s.national_id, s.gender, s.subject, s.available, s.total_work_days, s.assignments_count,
                    s.record_type, s.order_no, s.mobile, s.notes, s.wants_work
             FROM substitutes s
             ORDER BY s.total_work_days DESC, s.name'
        );
        $doc = 'تقرير أعمال جميع البدلاء';
        break;

    case 'school':
        $schoolName = trim((string)get('school'));
        if (!$schoolName && $id) {
            $schoolName = Db::val('SELECT school_name FROM vacancies WHERE id = ?', [$id]);
        }
        if (!$schoolName) {
            flash('يرجى اختيار المدرسة.', 'error');
            redirect('index.php?page=reports');
        }
        $rows = Db::all(
            'SELECT v.*, s.name AS sub_name, s.mobile AS sub_mobile
             FROM vacancies v LEFT JOIN substitutes s ON s.id = v.substitute_id
             WHERE v.school_name = ?
             ORDER BY v.created_at DESC', [$schoolName]
        );
        $doc = 'تقرير مدرسة محددة';
        $data = ['school_name' => $schoolName];
        break;

    case 'assigned_period':
        $dateFrom = get('date_from', date('Y-m-01'));
        $dateTo = get('date_to', date('Y-m-d'));
        $rows = Db::all(
            'SELECT v.*, s.name AS sub_name, s.national_id AS sub_nid, s.gender AS sub_gender, s.mobile AS sub_mobile, s.subject AS sub_subject
             FROM vacancies v LEFT JOIN substitutes s ON s.id = v.substitute_id
             WHERE v.status IN ("assigned","ended") AND v.assigned_at BETWEEN ? AND ?
             ORDER BY v.assigned_at DESC', [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']
        );
        $doc = 'تقرير المكلفين من ' . $dateFrom . ' إلى ' . $dateTo;
        $data = ['date_from' => $dateFrom, 'date_to' => $dateTo];
        break;

    case 'all_requests':
        $rows = Db::all(
            'SELECT v.*, s.name AS sub_name
             FROM vacancies v LEFT JOIN substitutes s ON s.id = v.substitute_id
             ORDER BY v.created_at DESC'
        );
        $doc = 'تقرير طلبات البدلاء';
        break;

    case 'ended':
        $rows = Db::all(
            'SELECT v.*, s.name AS sub_name, s.national_id AS sub_nid, s.mobile AS sub_mobile
             FROM vacancies v LEFT JOIN substitutes s ON s.id = v.substitute_id
             WHERE v.status = "ended"
             ORDER BY v.ended_at DESC'
        );
        $doc = 'تقرير البدلاء المنتهية';
        break;

    case 'currently_assigned':
        $rows = Db::all(
            'SELECT v.*, s.name AS sub_name, s.national_id AS sub_nid, s.gender AS sub_gender, s.mobile AS sub_mobile, s.subject AS sub_subject
             FROM vacancies v LEFT JOIN substitutes s ON s.id = v.substitute_id
             WHERE v.status = "assigned"
             ORDER BY v.assigned_at DESC'
        );
        $doc = 'البدلاء المكلفين حالياً';
        break;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($doc) ?> | <?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/app.css">
    <?php if ($type === 'all'): ?>
    <style>@media print { @page { size: A4 landscape; margin: 10mm; } .compact-report, .compact-report * { font-family: 'Times New Roman', Times, serif !important; } }</style>
    <?php endif; ?>
</head>
<body class="report-body">

<div class="toolbar no-print">
    <div class="row">
        <a class="btn ghost" href="index.php?page=reports">→ العودة للتقارير</a>
        <button class="btn primary" onclick="window.print()">🖨️ طباعة</button>
    </div>
    <p class="muted small">استخدم زر الطباعة لحفظ التقرير PDF أو طباعته.</p>
</div>

<div class="print-area" id="printArea">

    <!-- الترويسة -->
    <div class="doc-header">
        <div class="doc-header-right">
            <?php foreach ($headerLines as $line): ?>
                <div class="doc-hline"><?= e($line) ?></div>
            <?php endforeach; ?>
        </div>
        <?php $lg = logo_url(); ?>
        <div class="doc-logo"><?php if ($lg): ?><img src="<?= e($lg) ?>" alt="شعار"><?php else: ?>ب<?php endif; ?></div>
        <div class="doc-header-left">
            <?php foreach ($headerLinesEn as $line): ?>
                <div class="doc-hline-en"><?= e($line) ?></div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="doc-meta">
        <div class="doc-meta-right">الرقم: <?= e($vac['document_no'] ?? '') ?></div>
        <div class="doc-meta-left">التاريخ: <?= date('Y/m/d') ?></div>
    </div>

    <h1 class="doc-title"><?= e($doc) ?></h1>

    <?php if ($type === 'assignment'): ?>
        <?php
        $classes = implode('، ', json_get($vac['classes']));
        $subLabel = in_array($vac['sub_gender'], ['أنثى'], true) ? 'المعلمة' : 'المعلم';
        $origLabel = in_array($vac['original_gender'], ['أنثى'], true) ? 'المعلمة' : 'المعلم';
        ?>
        <div class="doc-body">
            <p class="doc-to">السادة: <?= e($vac['school_name']) ?></p>
            <p>تحية طيبة وبعد،</p>
            <p class="doc-para">
                المطلوب تكليف <?= e($subLabel) ?> <strong><?= e($vac['sub_name']) ?></strong>
                (رقم الهوية: <strong><?= e($vac['sub_national_id']) ?></strong>) للعمل في مدرستكم كبديل/ة
                عن <?= e($origLabel) ?> <strong><?= e($vac['original_name']) ?></strong>
                (رقم الهوية: <?= e($vac['original_national_id']) ?: '—' ?>)
                ووفق البيانات التالية:
            </p>
            <table class="doc-table">
                <tr><th>بيان</th><th>التفاصيل</th></tr>
                <tr><td>المادة / المبحث</td><td><?= e($vac['subject']) ?></td></tr>
                <tr><td>سبب الشاغر</td><td><?= e($vac['reason']) ?></td></tr>
                <tr><td>مركز الشاغر</td><td><?= e($vac['center']) ?: '—' ?></td></tr>
                <tr><td>جنس الشاغر</td><td><?= e($vac['vacancy_gender']) ?: '—' ?></td></tr>
                <tr><td>الصفوف</td><td><?= e($classes) ?: '—' ?></td></tr>
                <tr><td>المسمى الوظيفي للأصيل</td><td><?= e($vac['original_job_title']) ?: '—' ?></td></tr>
                <tr><td>المؤهل العلمي للبديل</td><td><?= e($vac['sub_qual']) ?: '—' ?></td></tr>
                <tr><td>سجل البديل</td><td><?= e($vac['sub_record_type']) ?: '—' ?></td></tr>
                <tr><td>تاريخ المباشرة</td><td><?= e(fmt_date($vac['assigned_at'])) ?></td></tr>
            </table>
            <p class="doc-para">
                يرجى إبلاغ البديل/ة بموعد المباشرة، وحسب أصول العمل في الوزارة، مع ضرورة مراعاة حضور البديل/ة خلال مدة الغياب المحددة.
            </p>
            <p class="doc-para">والله ولي التوفيق،</p>
        </div>

    <?php elseif ($type === 'request'):
        $classes = implode('، ', json_get($vac['classes']));
        $subLabel = in_array(($vac['sub_gender'] ?? ''), ['أنثى'], true) ? 'المعلمة' : 'المعلم';
        $origLabel = in_array(($vac['original_gender'] ?? ''), ['أنثى'], true) ? 'المعلمة' : 'المعلم';
    ?>
        <div class="doc-body">
            <h3 class="doc-sub" style="text-align:center;text-decoration:underline">بيانات طلب البديل</h3>
            <table class="doc-table">
                <tr><th>بيان</th><th>التفاصيل</th></tr>
                <tr><td>رقم الطلب</td><td>#<?= $vac['id'] ?></td></tr>
                <tr><td>المدرسة</td><td><?= e($vac['school_name']) ?></td></tr>
                <tr><td>سبب الشاغر</td><td><?= e($vac['reason']) ?></td></tr>
                <tr><td>المادة / المبحث</td><td><?= e($vac['subject']) ?></td></tr>
                <tr><td>مركز الشاغر</td><td><?= e($vac['center']) ?: '—' ?></td></tr>
                <tr><td>جنس الشاغر</td><td><?= e($vac['vacancy_gender']) ?: '—' ?></td></tr>
                <tr><td>الصفوف</td><td><?= e($classes) ?: '—' ?></td></tr>
                <tr><td>تاريخ الإرسال</td><td><?= e(fmt_date($vac['created_at'])) ?></td></tr>
                <tr><td>تاريخ المطلوب</td><td><?= e(fmt_date($vac['start_date'])) ?> إلى <?= e(fmt_date($vac['end_date'])) ?></td></tr>
            </table>

            <h3 class="doc-sub" style="text-align:center;text-decoration:underline">بيانات الموظف الأصيل</h3>
            <table class="doc-table">
                <tr><th>بيان</th><th>التفاصيل</th></tr>
                <tr><td>الاسم</td><td><?= e($vac['original_name']) ?></td></tr>
                <tr><td>رقم الهوية</td><td><?= e($vac['original_national_id']) ?: '—' ?></td></tr>
                <tr><td>الجنس</td><td><?= e($vac['original_gender']) ?: '—' ?></td></tr>
                <tr><td>المسمى الوظيفي</td><td><?= e($vac['original_job_title']) ?: '—' ?></td></tr>
            </table>

            <?php if ($vac['substitute_id']): ?>
            <h3 class="doc-sub" style="text-align:center;text-decoration:underline">بيانات البديل المكلف</h3>
            <table class="doc-table">
                <tr><th>بيان</th><th>التفاصيل</th></tr>
                <tr><td>اسم <?= e($subLabel) ?></td><td><?= e($vac['sub_name']) ?></td></tr>
                <tr><td>رقم الهوية</td><td><?= e($vac['sub_national_id']) ?></td></tr>
                <tr><td>المبحث</td><td><?= e($vac['sub_subject']) ?></td></tr>
                <tr><td>رقم الكتاب</td><td><?= e($vac['document_no']) ?></td></tr>
                <tr><td>تاريخ التكليف</td><td><?= e(fmt_date($vac['assigned_at'])) ?></td></tr>
                <tr><td>تاريخ الإنهاء</td><td><?= e(fmt_date($vac['ended_at'])) ?: '—' ?></td></tr>
                <tr><td>عدد أيام العمل</td><td><?= $vac['days_worked'] ?: '—' ?></td></tr>
            </table>
            <?php else: ?>
                <p class="doc-para center" style="margin-top:16px;color:#94a3b8">لم يتم تكليف بديل بعد لهذا الشاغر.</p>
            <?php endif; ?>
        </div>

    <?php elseif ($type === 'substitute'): ?>
        <div class="doc-body">
            <table class="doc-table">
                <tr><th>بيان</th><th>التفاصيل</th></tr>
                <tr><td>اسم البديل</td><td><?= e($data['name']) ?></td></tr>
                <tr><td>رقم الهوية</td><td><?= e($data['national_id']) ?></td></tr>
                <tr><td>الجنس</td><td><?= e($data['gender']) ?></td></tr>
                <tr><td>المبحث</td><td><?= e($data['subject']) ?></td></tr>
                <tr><td>نوع السجل</td><td><?= e($data['record_type']) ?> (الترتيب <?= $data['order_no'] ?>)</td></tr>
                <tr><td>الجوال</td><td><?= e($data['mobile']) ?></td></tr>
                <tr><td>المعدل</td><td><?= $data['gpa'] ?></td></tr>
                <tr><td>الحالة الحالية</td><td><?= $data['available'] ? 'متاح للتكليف' : 'مكلف حالياً' ?></td></tr>
                <tr><td>إجمالي أيام العمل</td><td><strong><?= $data['total_work_days'] ?></strong></td></tr>
                <tr><td>عدد مرات التكليف</td><td><strong><?= $data['assignments_count'] ?></strong></td></tr>
            </table>

            <h3 class="doc-sub">تفاصيل الأعمال</h3>
            <table class="doc-table full">
                <thead>
                    <tr>
                        <th>المدرسة</th><th>الموظف الأصيل</th><th>السبب</th>
                        <th>تاريخ التكليف</th><th>تاريخ الإنهاء</th><th>أيام العمل</th><th>الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="7" class="center">لا توجد أعمال مسجلة</td></tr>
                    <?php else: foreach ($rows as $h): ?>
                        <tr>
                            <td><?= e($h['school_name']) ?></td>
                            <td><?= e($h['original_name']) ?></td>
                            <td><?= e($h['reason']) ?></td>
                            <td><?= e(fmt_date($h['assigned_at'])) ?></td>
                            <td><?= e(fmt_date($h['ended_at'])) ?></td>
                            <td><?= $h['days_worked'] ?: '—' ?></td>
                            <td><?= ['assigned' => 'قائم', 'ended' => 'منتهي'][$h['status']] ?? $h['status'] ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($type === 'all'): ?>
        <div class="doc-body compact-report">
            <p class="doc-para center">ملخص إجمالي لأعمال البدلاء المسجلين في مديرية التربية والتعليم - يطا، وعددهم: <strong><?= count($rows) ?></strong></p>
            <table class="doc-table full">
                <thead>
                    <tr>
                        <th>#</th><th>الاسم</th><th>رقم الهوية</th><th>الجنس</th><th>المبحث</th>
                        <th>نوع السجل</th><th>أيام العمل</th><th>مرات التكليف</th><th>الرغبة</th><th>الحالة</th><th>الملاحظات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="11" class="center">لا يوجد بدلاء</td></tr>
                    <?php else: $i = 1; foreach ($rows as $r): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= e($r['name']) ?></td>
                            <td><?= e($r['national_id']) ?></td>
                            <td><?= e($r['gender']) ?></td>
                            <td><?= e($r['subject']) ?></td>
                            <td><?= e($r['record_type']) ?></td>
                            <td><?= $r['total_work_days'] ?></td>
                            <td><?= $r['assignments_count'] ?></td>
                            <td><?= $r['wants_work'] ? 'يرغب' : 'لا يرغب' ?></td>
                            <td><?= $r['available'] ? 'متاح' : 'مكلف حالياً' ?></td>
                            <td><?= e($r['notes']) ?: '—' ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($type === 'school'): ?>
        <div class="doc-body">
            <p class="doc-para">المدرسة: <strong><?= e($data['school_name']) ?></strong></p>
            <table class="doc-table full">
                <thead>
                    <tr>
                        <th>#</th><th>الموظف الأصيل</th><th>المبحث</th><th>السبب</th>
                        <th>البديل</th><th>رقم الكتاب</th><th>تاريخ التكليف</th><th>أيام العمل</th><th>الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="9" class="center">لا توجد طلبات لهذه المدرسة</td></tr>
                    <?php else: $i = 1; foreach ($rows as $h): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= e($h['original_name']) ?></td>
                            <td><?= e($h['subject']) ?></td>
                            <td><?= e($h['reason']) ?></td>
                            <td><?= e($h['sub_name']) ?: '—' ?></td>
                            <td><?= e($h['document_no']) ?: '—' ?></td>
                            <td><?= e(fmt_date($h['assigned_at'])) ?></td>
                            <td><?= $h['days_worked'] ?: '—' ?></td>
                            <td><?= ['open' => 'مفتوح', 'assigned' => 'مكلف', 'ended' => 'منتهي', 'cancelled' => 'ملغي'][$h['status']] ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($type === 'assigned_period'): ?>
        <div class="doc-body">
            <p class="doc-para">البدلاء المكلفين من <strong><?= e($data['date_from']) ?></strong> إلى <strong><?= e($data['date_to']) ?></strong> — العدد: <strong><?= count($rows) ?></strong></p>
            <table class="doc-table full">
                <thead>
                    <tr>
                        <th>#</th><th>البديل</th><th>الجنس</th><th>المبحث</th><th>المدرسة</th>
                        <th>الموظف الأصيل</th><th>رقم الكتاب</th><th>تاريخ التكليف</th><th>الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="9" class="center">لا توجد تكليفات في هذه الفترة</td></tr>
                    <?php else: $i = 1; foreach ($rows as $h): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><strong><?= e($h['sub_name']) ?></strong></td>
                            <td><?= e($h['sub_gender']) ?></td>
                            <td><?= e($h['subject']) ?></td>
                            <td><?= e($h['school_name']) ?></td>
                            <td><?= e($h['original_name']) ?></td>
                            <td><?= e($h['document_no']) ?: '—' ?></td>
                            <td><?= e(fmt_date($h['assigned_at'])) ?></td>
                            <td><?= $h['status'] === 'ended' ? 'منتهي' : 'قائم' ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($type === 'all_requests'): ?>
        <div class="doc-body">
            <p class="doc-para center">جميع طلبات البديل — العدد: <strong><?= count($rows) ?></strong></p>
            <table class="doc-table full">
                <thead>
                    <tr>
                        <th>#</th><th>المدرسة</th><th>الموظف الأصيل</th><th>المبحث</th><th>السبب</th>
                        <th>البديل</th><th>تاريخ الإنشاء</th><th>الحالة</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="8" class="center">لا توجد طلبات</td></tr>
                    <?php else: $i = 1; foreach ($rows as $h): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= e($h['school_name']) ?></td>
                            <td><?= e($h['original_name']) ?></td>
                            <td><?= e($h['subject']) ?></td>
                            <td><?= e($h['reason']) ?></td>
                            <td><?= e($h['sub_name']) ?: '—' ?></td>
                            <td><?= e(fmt_date($h['created_at'])) ?></td>
                            <td><?= ['open' => 'مفتوح', 'assigned' => 'مكلف', 'ended' => 'منتهي', 'cancelled' => 'ملغي'][$h['status']] ?? $h['status'] ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($type === 'ended'): ?>
        <div class="doc-body">
            <p class="doc-para center">البدلاء الذين انتهت أعمالهم — العدد: <strong><?= count($rows) ?></strong></p>
            <table class="doc-table full">
                <thead>
                    <tr>
                        <th>#</th><th>البديل</th><th>المدرسة</th><th>الموظف الأصيل</th>
                        <th>تاريخ التكليف</th><th>تاريخ الإنهاء</th><th>أيام العمل</th><th>سبب الإنهاء</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="8" class="center">لا توجد أعمال منتهية</td></tr>
                    <?php else: $i = 1; foreach ($rows as $h): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><strong><?= e($h['sub_name']) ?></strong></td>
                            <td><?= e($h['school_name']) ?></td>
                            <td><?= e($h['original_name']) ?></td>
                            <td><?= e(fmt_date($h['assigned_at'])) ?></td>
                            <td><?= e(fmt_date($h['ended_at'])) ?></td>
                            <td><?= $h['days_worked'] ?: '—' ?></td>
                            <td><?= e($h['end_reason']) ?: '—' ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

    <?php elseif ($type === 'currently_assigned'): ?>
        <div class="doc-body">
            <p class="doc-para center">البدلاء المكلفين حالياً — العدد: <strong><?= count($rows) ?></strong></p>
            <table class="doc-table full">
                <thead>
                    <tr>
                        <th>#</th><th>البديل</th><th>الجنس</th><th>المبحث</th><th>المدرسة</th>
                        <th>الموظف الأصيل</th><th>رقم الكتاب</th><th>تاريخ التكليف</th><th>الجوال</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$rows): ?>
                        <tr><td colspan="9" class="center">لا يوجد بدلاء مكلفين حالياً</td></tr>
                    <?php else: $i = 1; foreach ($rows as $h): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><strong><?= e($h['sub_name']) ?></strong></td>
                            <td><?= e($h['sub_gender']) ?></td>
                            <td><?= e($h['subject']) ?></td>
                            <td><?= e($h['school_name']) ?></td>
                            <td><?= e($h['original_name']) ?></td>
                            <td><?= e($h['document_no']) ?: '—' ?></td>
                            <td><?= e(fmt_date($h['assigned_at'])) ?></td>
                            <td dir="ltr" style="text-align:end"><?= e($h['sub_mobile']) ?: '—' ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- اسم التوقيع -->
    <?php if ($signName = Settings::text('sign_name')): ?>
        <div class="doc-sign">
            <div class="doc-sign-title"><?= nl2br(e($signName)) ?></div>
        </div>
    <?php endif; ?>

    <!-- التذييل -->
    <div class="doc-footer">
        <div class="doc-footer-text"><?= e($footerText) ?></div>
    </div>
</div>

<script>
window.onload = function () {
    if (location.hash === '#print') window.print();
};
</script>
</body>
</html>
