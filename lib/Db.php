<?php
/**
 * اتصال قاعدة البيانات (PDO - MySQL)
 */
class Db
{
    private static ?PDO $pdo = null;

    public static function conn(): PDO
    {
        if (self::$pdo === null) {
            $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            self::$pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }
        return self::$pdo;
    }

    public static function q(string $sql, array $params = []): PDOStatement
    {
        $st = self::conn()->prepare($sql);
        $st->execute($params);
        return $st;
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::q($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::q($sql, $params)->fetchAll();
    }

    public static function val(string $sql, array $params = [])
    {
        return self::q($sql, $params)->fetchColumn();
    }

    public static function insert(string $table, array $data): int
    {
        $cols = array_keys($data);
        $sql = 'INSERT INTO `' . $table . '` (`' . implode('`,`', $cols) . '`) VALUES ('
            . implode(',', array_fill(0, count($cols), '?')) . ')';
        self::q($sql, array_values($data));
        return (int) self::conn()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): void
    {
        $set = [];
        $params = [];
        foreach ($data as $k => $v) {
            $set[] = "`$k` = ?";
            $params[] = $v;
        }
        $params = array_merge($params, $whereParams);
        self::q('UPDATE `' . $table . '` SET ' . implode(', ', $set) . ' WHERE ' . $where, $params);
    }

    public static function delete(string $table, string $where, array $whereParams = []): void
    {
        self::q('DELETE FROM `' . $table . '` WHERE ' . $where, $whereParams);
    }
}
