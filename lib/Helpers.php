<?php
/**
 * دوال مساعدة عامة
 */

/** ترميز الإخراج */
function e($v): string
{
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

/** إعادة توجيه */
function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/** قيمة من POST */
function post(string $key, $default = '')
{
    return $_POST[$key] ?? $default;
}

/** قيمة من GET */
function get(string $key, $default = '')
{
    return $_GET[$key] ?? $default;
}

/** رسائل فلاش */
function flash(string $msg, string $type = 'success'): void
{
    $_SESSION['flash'][] = ['msg' => $msg, 'type' => $type];
}

function flash_all(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/** حماية CSRF */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): void
{
    $token = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(403);
        exit('خطأ في صلاحية الطلب. الرجاء إعادة المحاولة.');
    }
}

/** تنسيق التاريخ */
function fmt_date($d, string $format = 'Y/m/d'): string
{
    if (!$d || $d === '0000-00-00' || $d === '') return '—';
    $ts = ($d instanceof DateTime) ? $d->getTimestamp() : strtotime($d);
    return date($format, $ts);
}

function fmt_datetime($d): string
{
    if (!$d) return '—';
    return fmt_date($d, 'Y/m/d H:i');
}

/** عدد الأيام بين تاريخين (شامل) */
function days_between($from, $to): int
{
    $f = $from ? strtotime($from) : time();
    $t = $to ? strtotime($to) : time();
    if (!$f || !$t || $t < $f) return 0;
    return (int) round(($t - $f) / 86400) + 1;
}

/** روابط الاتصال بالبديل */
function tel_link(string $mobile): string
{
    $m = preg_replace('/\D+/', '', $mobile);
    if ($m === '') return '#';
    return 'tel:' . $m;
}

function wa_link(string $mobile): string
{
    $m = preg_replace('/\D+/', '', $mobile);
    if ($m === '') return '#';
    if (strlen($m) === 9 && $m[0] === '5') $m = '972' . $m;
    elseif (strlen($m) === 10 && $m[0] === '0') $m = '972' . substr($m, 1);
    return 'https://wa.me/' . $m;
}

/** صفوف موحدة من النص */
function lines(string $text): array
{
    $arr = preg_split('/\r\n|\r|\n/', $text);
    $arr = array_map('trim', $arr);
    return array_values(array_filter($arr, fn($v) => $v !== ''));
}

/** قراءة JSON من حقل */
function json_get(?string $value, $default = []): array
{
    if (!$value) return is_array($default) ? $default : [];
    $dec = json_decode($value, true);
    return is_array($dec) ? $dec : $default;
}

function json_set($value): string
{
    return json_encode(array_values($value), JSON_UNESCAPED_UNICODE);
}

/** مسار الشعار المعتمد */
function logo_url(): string
{
    $logo = Settings::text('logo_path');
    if ($logo && file_exists(__DIR__ . '/../uploads/' . $logo)) {
        return 'uploads/' . $logo;
    }
    return '';
}

/** الأرقام العربية ↔ الحروف أرقام للوثائق */
function num_ar($n): string
{
    $map = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
    return strtr((string)$n, ['0'=>$map[0],'1'=>$map[1],'2'=>$map[2],'3'=>$map[3],'4'=>$map[4],'5'=>$map[5],'6'=>$map[6],'7'=>$map[7],'8'=>$map[8],'9'=>$map[9]]);
}

/** معالجة الصفوف القادمة من الفلتر */
function paginate(int $total, int $perPage, int &$page): array
{
    $page = max(1, (int)($page ?? 1));
    $pages = max(1, (int)ceil($total / $perPage));
    $page = min($page, $pages);
    return [$page, $pages, ($page - 1) * $perPage];
}
