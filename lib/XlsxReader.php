<?php
/**
 * قارئ ملفات XLSX خفيف بدون مكتبات خارجية
 * يعتمد على ZipArchive + SimpleXML المدمجين في PHP
 */
class XlsxReader
{
    /**
     * قراءة ملف XLSX وإرجاع مصفوفة صفوف (كل صف مصفوفة قيم فهرسي)
     * @throws Exception
     */
    public static function toArray(string $path): array
    {
        if (!file_exists($path)) {
            throw new Exception('الملف غير موجود: ' . $path);
        }
        if (!class_exists('ZipArchive')) {
            throw new Exception('إضافة Zip غير مفعلة على السيرفر، يلزم تفعيلها لاستيراد ملفات إكسل.');
        }
        if (!extension_loaded('SimpleXML') || !extension_loaded('libxml')) {
            throw new Exception('إضافة SimpleXML غير مفعلة.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new Exception('تعذر فتح ملف الإكسل (قد يكون تالفاً أو محمياً بكلمة مرور).');
        }

        // 1) النصوص المشتركة
        $strings = [];
        $ss = $zip->getFromName('xl/sharedStrings.xml');
        if ($ss !== false) {
            $ssXml = @simplexml_load_string($ss);
            if ($ssXml !== false) {
                foreach ($ssXml->si as $si) {
                    $txt = '';
                    if (isset($si->t) && trim((string)$si->t) !== '') {
                        $txt = (string)$si->t;
                    } elseif (isset($si->r)) {
                        foreach ($si->r as $r) {
                            $txt .= (string)$r->t;
                        }
                    }
                    $strings[] = $txt;
                }
            }
        }

        // 2) اسم أول ورقة عمل من rels
        $sheetPath = self::firstSheetPath($zip);
        if (!$sheetPath) {
            $zip->close();
            throw new Exception('لا توجد ورقة عمل في الملف.');
        }

        $sheetXml = $zip->getFromName($sheetPath);
        $zip->close();

        $xml = @simplexml_load_string($sheetXml);
        if ($xml === false) {
            throw new Exception('تعذر قراءة محتوى الورقة.');
        }

        $rows = [];
        if (!isset($xml->sheetData)) {
            return $rows;
        }

        foreach ($xml->sheetData->row as $row) {
            $cells = [];
            foreach ($row->c as $c) {
                $ref = (string)$c['r'];
                $colLetter = strtoupper(preg_replace('/\d+/', '', $ref));
                $colIdx = self::colIndex($colLetter);
                $type = (string)$c['t'];
                $val = '';

                if ($type === 's') {
                    $si = (int)trim((string)$c->v);
                    $val = $strings[$si] ?? '';
                } elseif ($type === 'inlineStr') {
                    if (isset($c->is->t)) $val = (string)$c->is->t;
                    else {
                        foreach ($c->is->r as $r) $val .= (string)$r->t;
                    }
                } elseif ($type === 'b') {
                    $val = (string)$c->v;
                } elseif (isset($c->v)) {
                    $val = (string)$c->v;
                } elseif (isset($c->f) && isset($c->v)) {
                    $val = (string)$c->v;
                }
                $cells[$colIdx] = trim($val);
            }

            if (!$cells) continue;

            // ترتيب الأعمدة حسب فهرس الحرف
            $maxCol = max(array_keys($cells));
            $line = [];
            for ($i = 0; $i <= $maxCol; $i++) {
                $line[] = $cells[$i] ?? '';
            }
            // تجاهل الصف الفارغ
            if (implode('', $line) === '') continue;
            $rows[] = $line;
        }

        return $rows;
    }

    /** تحديد مسار أول ورقة عمل من xl/workbook.xml + rels */
    private static function firstSheetPath(ZipArchive $zip): ?string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbook === false || $rels === false) return null;

        $wb = @simplexml_load_string($workbook);
        $wsRels = @simplexml_load_string($rels);
        if ($wb === false || $wsRels === false) return null;

        $wbNs = $wb->getNamespaces(true);
        $relNs = $wbNs['r'] ?? 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

        // أول sheet
        $target = null;
        if (isset($wb->sheets)) {
            foreach ($wb->sheets->sheet as $s) {
                $attrs = $s->attributes($relNs);
                $target = (string)($attrs['id'] ?? '');
                if ($target !== '') break;
            }
        }
        if (!$target) return null;

        // البحث في rels عن العلاقة المقابلة
        foreach ($wsRels->Relationship as $rel) {
            if ((string)$rel['Id'] === $target) {
                $t = (string)$rel['Target'];
                if (strpos($t, '/') === 0) {
                    return 'xl' . $t;
                }
                return 'xl/' . $t;
            }
        }
        return null;
    }

    /** تحويل حرف العمود (A, B, ... AA) إلى فهرس صفري */
    private static function colIndex(string $letters): int
    {
        $idx = 0;
        $len = strlen($letters);
        for ($i = 0; $i < $len; $i++) {
            $idx = $idx * 26 + (ord($letters[$i]) - 64);
        }
        return $idx - 1;
    }
}
