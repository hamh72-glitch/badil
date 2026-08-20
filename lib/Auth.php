<?php
/**
 * المصادقة والأدوار والصلاحيات
 * الأدوار: admin (مسؤول النظام) | directorate (حساب مديرية) | school (مدرسة)
 */
class Auth
{
    private static ?array $user = null;

    public static function attempt(string $username, string $password): ?array
    {
        $user = Db::one('SELECT * FROM users WHERE username = ? AND is_active = 1', [trim($username)]);
        if (!$user) return null;
        if (!password_verify($password, $user['password_hash'])) return null;
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            Db::q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }
        return $user;
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$user['id'];
        self::$user = $user;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function user(): ?array
    {
        if (self::$user !== null) return self::$user;
        if (empty($_SESSION['user_id'])) return null;
        self::$user = Db::one('SELECT * FROM users WHERE id = ? AND is_active = 1', [$_SESSION['user_id']]);
        if (!self::$user) {
            unset($_SESSION['user_id']);
            return null;
        }
        return self::$user;
    }

    public static function id(): int
    {
        return (int)(self::user()['id'] ?? 0);
    }

    public static function role(): string
    {
        return self::user()['role'] ?? 'guest';
    }

    public static function isRole(string $role): bool
    {
        return self::role() === $role;
    }

    public static function isAdmin(): bool { return self::isRole('admin'); }
    public static function isDirectorate(): bool { return self::isRole('directorate'); }
    public static function isSchool(): bool { return self::isRole('school'); }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            flash('الرجاء تسجيل الدخول أولاً', 'error');
            redirect('index.php?page=login');
        }
    }

    public static function requireRoles(array $roles): void
    {
        self::requireLogin();
        if (!in_array(self::role(), $roles, true)) {
            http_response_code(403);
            exit('ليس لديك صلاحية للوصول إلى هذه الصفحة.');
        }
    }

    /** هل يستطيع المستخدم إنهاء عمل بديل */
    public static function canEndWork(): bool
    {
        if (self::isAdmin()) return true;
        if (self::isDirectorate()) return self::hasPermission('end_work');
        return false;
    }

    /** هل يستطيع المستخدم التعديل/الحذف على طلب معين */
    public static function canEditVacancy(array $vacancy): bool
    {
        if (self::isAdmin()) return true;
        if (self::isSchool()) {
            return (int)($vacancy['school_user_id'] ?? 0) === self::id()
                && in_array($vacancy['status'], ['open', 'assigned'], true);
        }
        if (self::isDirectorate()) return in_array($vacancy['status'], ['open', 'assigned'], true);
        return false;
    }

    public static function canDeleteVacancy(): bool
    {
        return self::isAdmin();
    }

    public static function canAssign(): bool
    {
        return in_array(self::role(), ['admin', 'directorate'], true);
    }

    /** التحقق من صلاحية محددة لحساب المديرية */
    public static function hasPermission(string $perm): bool
    {
        if (self::isAdmin()) return true;
        $user = self::user();
        if (!$user) return false;
        $perms = json_decode($user['permissions'] ?? '[]', true);
        return is_array($perms) && in_array($perm, $perms, true);
    }

    /** قائمة الصلاحيات المتاحة */
    public static function availablePermissions(): array
    {
        return [
            'end_work' => 'إنهاء أعمال البدلاء',
            'call' => 'الاتصال بالبدلاء (عرض أرقام الهواتف)',
            'reports' => 'التقارير والطباعة',
            'assign' => 'تكليف البدلاء',
        ];
    }

    /** تسمية الدور */
    public static function roleLabel(string $role): string
    {
        return [
            'admin' => 'مسؤول النظام',
            'directorate' => 'حساب مديرية',
            'school' => 'مدرسة',
        ][$role] ?? $role;
    }
}
