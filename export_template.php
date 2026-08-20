<?php
require __DIR__ . '/lib/bootstrap.php';

Auth::requireRoles(['admin']);

$type = get('type', '');

if (!in_array($type, ['erecord', 'schools', 'subjects'], true)) {
    flash('نوع غير صحيح.', 'error');
    redirect('index.php?page=settings&tab=import');
}

switch ($type) {
    case 'erecord':
        $fileName = 'سجل_البدلاء.xlsx';
        $headers = [
            'الوحدة الإدارية', 'الجنس', 'المادة', 'نوع السجل', 'الترتيب',
            'الاسم', 'رقم الهوية', 'العنوان', '', 'المؤهل', 'التخصص',
            'المعدل', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '',
            '', '', 'الدرجة النهائية', '', 'الجوال', '', '', '', 'ملاحظات',
        ];
        $usedCols = [0,1,2,3,4,5,6,7,9,10,11,27,29,34];
        $rows = Db::all('SELECT admin_unit, gender, subject, record_type, order_no, name, national_id, address, qualification, specialization, gpa, final_score, mobile, notes FROM substitutes ORDER BY subject, record_type, order_no LIMIT 500');
        $data = [];
        foreach ($rows as $r) {
            $line = array_fill(0, 35, '');
            $line[0] = $r['admin_unit'];
            $line[1] = $r['gender'];
            $line[2] = $r['subject'];
            $line[3] = $r['record_type'];
            $line[4] = $r['order_no'];
            $line[5] = $r['name'];
            $line[6] = $r['national_id'];
            $line[7] = $r['address'];
            $line[9] = $r['qualification'];
            $line[10] = $r['specialization'];
            $line[11] = $r['gpa'];
            $line[27] = $r['final_score'];
            $line[29] = $r['mobile'];
            $line[34] = $r['notes'];
            $data[] = $line;
        }
        break;

    case 'schools':
        $fileName = 'المدارس_وكلمات_السر.xlsx';
        $headers = ['اسم المدرسة', 'اسم المستخدم (رقم الهوية)', 'كلمة السر'];
        $rows = Db::all('SELECT full_name, username FROM users WHERE role = "school" ORDER BY full_name');
        $data = [];
        foreach ($rows as $r) {
            $data[] = [$r['full_name'], $r['username'], ''];
        }
        break;

    case 'subjects':
        $fileName = 'المباحث.xlsx';
        $headers = ['المبحث'];
        $items = Settings::arr('subjects');
        $data = [];
        foreach ($items as $s) {
            $data[] = [$s];
        }
        break;
}

generateXlsx($headers, $data, $fileName);

function generateXlsx(array $headers, array $data, string $fileName): void
{
    $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_');
    $zip = new ZipArchive();
    $zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    // جمع كل النصوص في shared strings
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

    // بناء صفوف XML
    $xmlRows = '';

    // صف العناوين
    $cells = '';
    foreach ($headers as $i => $h) {
        if ($h !== '' && isset($stringIndex[$h])) {
            $cells .= '<c r="' . colLetter($i) . '1" t="s"><v>' . $stringIndex[$h] . '</v></c>';
        }
    }
    $xmlRows .= '<row r="1">' . $cells . '</row>';

    // صفوف البيانات
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
