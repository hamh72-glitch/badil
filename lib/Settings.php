<?php
/**
 * إدارة الإعدادات (المباحث، الصفوف، أسباب الشاغر، الترويسة، التذييل ...)
 */
class Settings
{
    private static ?array $cache = null;

    private static function load(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            foreach (Db::all('SELECT skey, svalue FROM settings') as $r) {
                self::$cache[$r['skey']] = $r['svalue'];
            }
        }
        return self::$cache;
    }

    public static function get(string $key, $default = null)
    {
        $data = self::load();
        return $data[$key] ?? $default;
    }

    public static function set(string $key, $value): void
    {
        if (is_array($value)) $value = json_set($value);
        $exists = Db::val('SELECT COUNT(*) FROM settings WHERE skey = ?', [$key]);
        if ($exists) {
            Db::q('UPDATE settings SET svalue = ? WHERE skey = ?', [$value, $key]);
        } else {
            Db::q('INSERT INTO settings (skey, svalue) VALUES (?, ?)', [$key, $value]);
        }
        self::$cache[$key] = $value;
    }

    public static function arr(string $key): array
    {
        $v = self::get($key);
        if (is_array($v)) return $v;
        return json_get((string)$v);
    }

    public static function text(string $key): string
    {
        return (string)self::get($key, '');
    }

    public static function seedDefaults(): void
    {
        $defaults = [
            'subjects' => [],
            'classes' => ['رياض أطفال', 'الأول', 'الثاني', 'الثالث', 'الرابع', 'الخامس', 'السادس', 'السابع', 'الثامن', 'التاسع', 'العاشر', 'الحادي عشر', 'الثاني عشر'],
            'reasons' => [
                'بدل تكليف - بديل سنوي', 'إجازة أمومة', 'إجازة مرضية', 'اعتقال',
                'إجازة بدون راتب', 'إجازة أمومة مبكرة', 'إجازة عارضة', 'إجازة الحج', 'إجازة استثنائية'
            ],
            'job_titles' => [
                'معلم/ة', 'معلم/ة رياض أطفال', 'معلم/ة مصادر', 'سكرتير/ة', 'مرشد/ة', 'مدير/ة', 'اذن/ة'
            ],
            'centers' => ['نصف مركز', 'مركز'],
            'letter_header' => "دولة فلسطين\nوزارة التربية والتعليم\nمديرية التربية والتعليم - يطا",
            'letter_header_en' => "State of Palestine\nMinistry of Education and Higher Education\nDirectorate of Education - Yatta",
            'letter_footer' => 'مدير التربية والتعليم',
            'directorate_name' => 'مديرية التربية والتعليم - يطا',
            'document_counter' => '1',
            'current_year' => date('Y'),
        ];
        foreach ($defaults as $k => $v) {
            if (self::get($k) === null) {
                self::set($k, $v);
            }
        }
    }
}
