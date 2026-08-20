<?php
/**
 * استيراد ملفات الإكسل إلى قاعدة البيانات
 */
class Importer
{
    /**
     * استيراد سجل الموظفين (بدلاء) من ملف Erecord.xlsx
     * @return array [inserted, updated, skipped, errors]
     */
    public static function importSubstitutes(string $xlsxPath): array
    {
        $rows = XlsxReader::toArray($xlsxPath);
        if (!$rows) {
            throw new Exception('الملف فارغ أو لا يحتوي على بيانات.');
        }
        // تجاهل صف العناوين
        if (isset($rows[0][5]) && !is_numeric($rows[0][5]) && mb_strlen($rows[0][5]) > 4) {
            array_shift($rows);
        }

        $inserted = $updated = $skipped = 0;
        $errors = [];

        $stCheck = Db::conn()->prepare('SELECT id FROM substitutes WHERE national_id = ?');
        $stIns = Db::conn()->prepare(
            'INSERT INTO substitutes
             (admin_unit, gender, subject, record_type, order_no, name, national_id, address,
              qualification, specialization, gpa, final_score, mobile, notes)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        );
        $stUpd = Db::conn()->prepare(
            'UPDATE substitutes SET
             admin_unit=?, gender=?, subject=?, record_type=?, order_no=?, name=?, address=?,
             qualification=?, specialization=?, gpa=?, final_score=?, mobile=?, notes=?
             WHERE national_id=?'
        );

        foreach ($rows as $i => $r) {
            $line = $i + 2;
            $nationalId = trim((string)($r[6] ?? ''));
            if ($nationalId === '' || strlen($nationalId) < 6) {
                $skipped++;
                continue;
            }
            $gpa = (float)($r[11] ?? 0);
            $score = (float)($r[27] ?? 0);

            $data = [
                trim((string)($r[0] ?? '')),
                trim((string)($r[1] ?? '')),
                trim((string)($r[2] ?? '')),
                trim((string)($r[3] ?? '')),
                (int)($r[4] ?? 0),
                trim((string)($r[5] ?? '')),
                $nationalId,
                trim((string)($r[7] ?? '')),
                trim((string)($r[9] ?? '')),
                trim((string)($r[10] ?? '')),
                $gpa,
                $score,
                trim((string)($r[29] ?? '')),
                trim((string)($r[34] ?? '')),
            ];

            if (mb_strlen(trim((string)($r[5] ?? ''))) < 5) {
                $skipped++;
                continue;
            }

            $stCheck->execute([$nationalId]);
            if ($stCheck->fetch()) {
                $stUpd->execute(array_merge($data, [$nationalId]));
                $updated++;
            } else {
                $stIns->execute($data);
                $inserted++;
            }
        }

        return compact('inserted', 'updated', 'skipped', 'errors');
    }

    /**
     * استيراد ملف المستخدمين وكلمات السر (المدارس)
     * @return array [created, updated, skipped]
     */
    public static function importSchools(string $xlsxPath): array
    {
        $rows = XlsxReader::toArray($xlsxPath);
        if (!$rows) {
            throw new Exception('الملف فارغ.');
        }
        if (mb_strpos((string)($rows[0][0] ?? ''), 'اسم المدرسة') !== false) {
            array_shift($rows);
        }

        $created = $updated = $skipped = 0;
        $stCheck = Db::conn()->prepare('SELECT id FROM users WHERE username = ?');
        $stUpd = Db::conn()->prepare('UPDATE users SET full_name = ?, phone = ? WHERE username = ?');
        $stIns = Db::conn()->prepare(
            'INSERT INTO users (username, password_hash, full_name, role, is_active) VALUES (?, ?, ?, "school", 1)'
        );

        foreach ($rows as $r) {
            $school = trim((string)($r[0] ?? ''));
            $username = trim((string)($r[1] ?? ''));
            $password = trim((string)($r[2] ?? ''));
            if ($school === '' || $username === '') {
                $skipped++;
                continue;
            }
            $hash = password_hash($password !== '' ? $password : $username, PASSWORD_DEFAULT);
            $stCheck->execute([$username]);
            if ($stCheck->fetch()) {
                $stUpd->execute([$school, '', $username]);
                $updated++;
            } else {
                $stIns->execute([$username, $hash, $school]);
                $created++;
            }
        }
        return compact('created', 'updated', 'skipped');
    }

    /**
     * استيراد ملف المباحث (الأول أعمدة)
     */
    public static function importSubjects(string $xlsxPath): array
    {
        $rows = XlsxReader::toArray($xlsxPath);
        $list = [];
        foreach ($rows as $r) {
            foreach ($r as $cell) {
                $v = trim((string)$cell);
                if ($v !== '' && mb_strlen($v) > 1) {
                    $list[$v] = true;
                }
            }
        }
        $items = array_keys($list);
        if (!$items) {
            throw new Exception('لا توجد مباحث في الملف.');
        }
        Settings::set('subjects', $items);
        return ['count' => count($items)];
    }
}
