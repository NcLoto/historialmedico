<?php
declare(strict_types=1);

namespace App\Core;

use PDO;

/** Conexión PDO única. Siempre sentencias preparadas, sin emulación. */
final class Database
{
    private static ?PDO $pdo = null;

    public static function dsn(bool $withDb = true): string
    {
        $dsn = sprintf('mysql:host=%s;port=%s;charset=utf8mb4',
            Env::get('DB_HOST', '127.0.0.1'), Env::get('DB_PORT', '3306'));
        return $withDb ? $dsn . ';dbname=' . Env::get('DB_DATABASE', 'sanitas_hme') : $dsn;
    }

    public static function connect(bool $withDb = true): PDO
    {
        return new PDO(self::dsn($withDb), Env::get('DB_USERNAME', 'root'), Env::get('DB_PASSWORD', ''), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    public static function pdo(): PDO
    {
        return self::$pdo ??= self::connect();
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(string $sql, array $params = []): array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        $row = $st->fetch();
        return $row === false ? null : $row;
    }

    public static function value(string $sql, array $params = []): mixed
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        $v = $st->fetchColumn();
        return $v === false ? null : $v;
    }

    public static function run(string $sql, array $params = []): int
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st->rowCount();
    }

    public static function insert(string $table, array $data): int
    {
        $cols = implode(',', array_map(fn($c) => "`$c`", array_keys($data)));
        $marks = implode(',', array_fill(0, count($data), '?'));
        self::run("INSERT INTO `$table` ($cols) VALUES ($marks)", array_values($data));
        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $set = implode(',', array_map(fn($c) => "`$c` = ?", array_keys($data)));
        return self::run("UPDATE `$table` SET $set WHERE $where", [...array_values($data), ...$whereParams]);
    }
}
