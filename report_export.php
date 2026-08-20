<?php
require __DIR__ . '/lib/bootstrap.php';
Auth::requireLogin();

if (!in_array(Auth::role(), ['admin', 'directorate'], true)) {
    redirect('index.php?page=dashboard');
}
if (Auth::isDirectorate() && !Auth::hasPermission('reports')) {
    redirect('index.php?page=dashboard');
}

$type = get('type', '');

switch ($type) {
    case 'all':
        $fileName = 'تقرير_جميع_البدلاء.xlsx';
        $headers = ['#', 'الاسم', 'رقم الهوية', 'الجنس', 'المبحث', 'نوع السجل', 'الترتيب', 'أيام العمل', 'مرات التكليف', 'يرغب', 'الحالة', 'الملاحظات'];
        $rows = Db::all('SELECT id, name, national_id, gender, subject, record_type, order_no, total_work_days, assignments_count, wants_work, available, notes FROM substitutes ORDER BY total_work_days DESC, name');
        $data = [];
        $i = 1;
        foreach ($rows as $r) {
            $data[] = [$i++, $r['name'], $r['national_id'], $r['gender'], $r['subject'], $r['record_type'], $r['order_no'], $r['total_work_days'], $r['assignments_count'], $r['wants_work'] ? 'يرغب' : 'لا يرغب', $r['available'] ? 'متاح' : 'مكلف حالياً', $r['notes'] ?: ''];
        }
        break;

    case 'all_requests':
        $fileName = 'تقرير_طلبات_البدلاء.xlsx';
        $headers = ['#', 'المدرسة', 'الموظف الأصيل', 'المبحث', 'السبب', 'البديل', 'رقم الكتاب', 'تاريخ الإنشاء', 'الحالة'];
        $rows = Db::all('SELECT v.*, s.name AS sub_name FROM vacancies v LEFT JOIN substitutes s ON s.id = v.substitute_id ORDER BY v.created_at DESC');
        $data = [];
        $i = 1;
        $statusMap = ['open' => 'مفتوح', 'assigned' => 'مكلف', 'ended' => 'منتهي', 'cancelled' => 'ملغي'];
        foreach ($rows as $h) {
            $data[] = [$i++, $h['school_name'], $h['original_name'], $h['subject'], $h['reason'], $h['sub_name'] ?: '', $h['document_no'] ?: '', fmt_date($h['created_at']), $statusMap[$h['status']] ?? $h['status']];
        }
        break;

    case 'ended':
        $fileName = 'تقرير_الانتهاءات.xlsx';
        $headers = ['#', 'البديل', 'المدرسة', 'الموظف الأصيل', 'تاريخ التكليف', 'تاريخ الإنهاء', 'أيام العمل', 'سبب الإنهاء'];
        $rows = Db::all('SELECT v.*, s.name AS sub_name FROM vacancies v LEFT JOIN substitutes s ON s.id = v.substitute_id WHERE v.status = "ended" ORDER BY v.ended_at DESC');
        $data = [];
        $i = 1;
        foreach ($rows as $h) {
            $data[] = [$i++, $h['sub_name'] ?: '', $h['school_name'], $h['original_name'], fmt_date($h['assigned_at']), fmt_date($h['ended_at']), $h['days_worked'] ?: '', $h['end_reason'] ?: ''];
        }
        break;

    case 'currently_assigned':
        $fileName = 'البدلاء_المكلفين_حالياً.xlsx';
        $headers = ['#', 'البديل', 'الجنس', 'المبحث', 'المدرسة', 'الموظف الأصيل', 'رقم الكتاب', 'تاريخ التكليف', 'الجوال'];
        $rows = Db::all('SELECT v.*, s.name AS sub_name, s.gender AS sub_gender, s.mobile AS sub_mobile, s.subject AS sub_subject FROM vacancies v LEFT JOIN substitutes s ON s.id = v.substitute_id WHERE v.status = "assigned" ORDER BY v.assigned_at DESC');
        $data = [];
        $i = 1;
        foreach ($rows as $h) {
            $data[] = [$i++, $h['sub_name'] ?: '', $h['sub_gender'] ?: '', $h['subject'], $h['school_name'], $h['original_name'], $h['document_no'] ?: '', fmt_date($h['assigned_at']), $h['sub_mobile'] ?: ''];
        }
        break;

    case 'assigned_period':
        $fileName = 'تقرير_المكلفين.xlsx';
        $headers = ['#', 'البديل', 'الجنس', 'المبحث', 'المدرسة', 'الموظف الأصيل', 'رقم الكتاب', 'تاريخ التكليف', 'الحالة'];
        $dateFrom = get('date_from', date('Y-m-01'));
        $dateTo = get('date_to', date('Y-m-d'));
        $rows = Db::all(
            'SELECT v.*, s.name AS sub_name, s.gender AS sub_gender FROM vacancies v LEFT JOIN substitutes s ON s.id = v.substitute_id
             WHERE v.status IN ("assigned","ended") AND v.assigned_at BETWEEN ? AND ? ORDER BY v.assigned_at DESC',
            [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59']
        );
        $data = [];
        $i = 1;
        foreach ($rows as $h) {
            $data[] = [$i++, $h['sub_name'] ?: '', $h['sub_gender'] ?: '', $h['subject'], $h['school_name'], $h['original_name'], $h['document_no'] ?: '', fmt_date($h['assigned_at']), $h['status'] === 'ended' ? 'منتهي' : 'قائم'];
        }
        break;

    case 'school':
        $fileName = 'تقرير_مدرسة.xlsx';
        $schoolName = trim((string)get('school', ''));
        $headers = ['#', 'الموظف الأصيل', 'المبحث', 'السبب', 'البديل', 'رقم الكتاب', 'تاريخ التكليف', 'أيام العمل', 'الحالة'];
        $rows = Db::all(
            'SELECT v.*, s.name AS sub_name FROM vacancies v LEFT JOIN substitutes s ON s.id = v.substitute_id
             WHERE v.school_name = ? ORDER BY v.created_at DESC', [$schoolName]
        );
        $data = [];
        $i = 1;
        $statusMap = ['open' => 'مفتوح', 'assigned' => 'مكلف', 'ended' => 'منتهي', 'cancelled' => 'ملغي'];
        foreach ($rows as $h) {
            $data[] = [$i++, $h['original_name'], $h['subject'], $h['reason'], $h['sub_name'] ?: '', $h['document_no'] ?: '', fmt_date($h['assigned_at']), $h['days_worked'] ?: '', $statusMap[$h['status']] ?? $h['status']];
        }
        $fileName = 'تقرير_' . ($schoolName ?: 'مدرسة') . '.xlsx';
        break;

    case 'substitute':
        $fileName = 'تقرير_بديل.xlsx';
        $id = (int)get('id', 0);
        $sub = Db::one('SELECT * FROM substitutes WHERE id = ?', [$id]);
        if (!$sub) { redirect('index.php?page=reports'); }
        $fileName = 'تقرير_' . $sub['name'] . '.xlsx';
        $headers = ['المدرسة', 'الموظف الأصيل', 'السبب', 'تاريخ التكليف', 'تاريخ الإنهاء', 'أيام العمل', 'الحالة'];
        $rows = Db::all(
            'SELECT * FROM vacancies WHERE substitute_id = ? AND status IN ("assigned","ended") ORDER BY assigned_at DESC', [$id]
        );
        $data = [];
        foreach ($rows as $h) {
            $data[] = [$h['school_name'], $h['original_name'], $h['reason'], fmt_date($h['assigned_at']), fmt_date($h['ended_at']), $h['days_worked'] ?: '', $h['status'] === 'assigned' ? 'قائم' : 'منتهي'];
        }
        break;

    default:
        redirect('index.php?page=reports');
}

generateXlsx($headers, $data, $fileName);

function generateXlsx(array $headers, array $data, string $fileName): void
{
    $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_');
    $zip = new ZipArchive();
    $zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    $strings = [];
    $stringIndex = [];

    foreach ($headers as $h) {
        if ($h !== '' && !isset($stringIndex[$h])) {
            $stringIndex[$h] = count($strings);
            $strings[] = $h;
        }
    }
    foreach ($data as $row) {
        foreach ($row as $cell) {
            $v = trim((string)$cell);
            if ($v !== '' && !isset($stringIndex[$v])) {
                $stringIndex[$v] = count($strings);
                $strings[] = $v;
            }
        }
    }

    $ssItems = '';
    foreach ($strings as $s) {
        $ssItems .= '<si><t>' . htmlspecialchars($s, ENT_XML1, 'UTF-8') . '</t></si>';
    }
    $ssCount = count($strings);
    $zip->addFromString('xl/sharedStrings.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . $ssCount . '" uniqueCount="' . $ssCount . '">' . $ssItems . '</sst>');

    $xmlRows = '';
    $cells = '';
    foreach ($headers as $i => $h) {
        if ($h !== '' && isset($stringIndex[$h])) {
            $cells .= '<c r="' . colLetter($i) . '1" t="s"><v>' . $stringIndex[$h] . '</v></c>';
        }
    }
    $xmlRows .= '<row r="1">' . $cells . '</row>';

    foreach ($data as $rowIdx => $row) {
        $r = $rowIdx + 2;
        $cells = '';
        foreach ($row as $i => $cell) {
            $v = trim((string)$cell);
            if ($v !== '' && isset($stringIndex[$v])) {
                $cells .= '<c r="' . colLetter($i) . $r . '" t="s"><v>' . $stringIndex[$v] . '</v></c>';
            }
        }
        if ($cells) {
            $xmlRows .= '<row r="' . $r . '">' . $cells . '</row>';
        }
    }

    $zip->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' . $xmlRows . '</sheetData></worksheet>');

    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets></workbook>');

    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/></Relationships>');

    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/></Types>');

    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');

    $zip->close();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Content-Length: ' . filesize($tmpFile));
    readfile($tmpFile);
    @unlink($tmpFile);
    exit;
}

function colLetter(int $idx): string
{
    $result = '';
    $idx++;
    while ($idx > 0) {
        $idx--;
        $result = chr(65 + ($idx % 26)) . $result;
        $idx = intdiv($idx, 26);
    }
    return $result;
}
