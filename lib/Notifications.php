<?php
/**
 * نظام الإشعارات
 */
class Notifications
{
    public static function send(int $userId, string $type, string $message, int $vacancyId = 0): int
    {
        return Db::insert('notifications', [
            'user_id' => $userId,
            'type' => $type,
            'message' => $message,
            'vacancy_id' => $vacancyId,
        ]);
    }

    public static function sharedRequest(array $vacancy, string $schoolName): void
    {
        $sharedUser = Db::one('SELECT id FROM users WHERE full_name = ? AND role = "school" AND is_active = 1', [$schoolName]);
        if (!$sharedUser) return;

        $msg = 'مدرسة "' . $vacancy['school_name'] . '" طلبت بديل مشترك مع مدرستكم — ' . $vacancy['original_name'] . ' (' . $vacancy['subject'] . ')';
        self::send((int)$sharedUser['id'], 'shared_request', $msg, (int)$vacancy['id']);
    }

    public static function unreadCount(int $userId): int
    {
        return (int)Db::val('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$userId]);
    }

    public static function latest(int $userId, int $limit = 10): array
    {
        return Db::all('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?', [$userId, $limit]);
    }

    public static function markRead(int $userId): void
    {
        Db::q('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0', [$userId]);
    }

    public static function delete(int $id, int $userId): void
    {
        Db::delete('notifications', 'id = ? AND user_id = ?', [$id, $userId]);
    }
}
