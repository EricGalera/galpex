<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\DB;
use PDO;
use PDOStatement;
use Throwable;

/**
 * Acceso a MySQL/MariaDB. La conexión la abre Laravel (config/database.php, datos del .env)
 * y esta clase trabaja sobre ese mismo PDO. Todas las consultas usan sentencias preparadas
 * (protección contra SQL Injection).
 */
final class Database
{
    private static int $transactionDepth = 0;

    public static function connection(): PDO
    {
        $pdo = DB::connection()->getPdo();
        // Las filas llegan como arrays asociativos (Laravel fija su propio modo en sus consultas).
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $pdo;
    }

    /** Cierra la conexión para que la próxima consulta vuelva a conectar (por ejemplo, después de crear la base). */
    public static function reconnect(): void
    {
        DB::purge();
        self::$transactionDepth = 0;
    }

    /** @param array<int|string, mixed> $params */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::connection()->prepare($sql);
        foreach ($params as $key => $value) {
            $name = is_int($key) ? $key + 1 : (str_starts_with($key, ':') ? $key : ':' . $key);
            $type = match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $stmt->bindValue($name, is_bool($value) ? (int) $value : $value, $type);
        }
        $stmt->execute();

        return $stmt;
    }

    /**
     * @return array<string,mixed>|null
     * @param array<int|string, mixed> $params
     */
    public static function first(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @return list<array<string,mixed>>
     * @param array<int|string, mixed> $params
     */
    public static function all(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    /**
     * @param array<int|string, mixed> $params
     */
    public static function scalar(string $sql, array $params = []): mixed
    {
        $value = self::query($sql, $params)->fetchColumn();

        return $value === false ? null : $value;
    }

    /** @param array<string,mixed> $data */
    public static function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', array_map(fn($c) => "`$c`", $columns)),
            implode(', ', array_map(fn($c) => ":$c", $columns)),
        );
        self::query($sql, $data);

        return (int) self::connection()->lastInsertId();
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $where
     */
    public static function update(string $table, array $data, array $where): int
    {
        $set = implode(', ', array_map(fn($c) => "`$c` = :set_$c", array_keys($data)));
        $cond = implode(' AND ', array_map(fn($c) => "`$c` = :where_$c", array_keys($where)));
        $params = [];
        foreach ($data as $k => $v) {
            $params["set_$k"] = $v;
        }
        foreach ($where as $k => $v) {
            $params["where_$k"] = $v;
        }

        return self::query("UPDATE `$table` SET $set WHERE $cond", $params)->rowCount();
    }

    /** @param array<string,mixed> $where */
    public static function delete(string $table, array $where): int
    {
        $cond = implode(' AND ', array_map(fn($c) => "`$c` = :$c", array_keys($where)));

        return self::query("DELETE FROM `$table` WHERE $cond", $where)->rowCount();
    }

    /**
     * Ejecuta el callback dentro de una transacción. Soporta anidamiento
     * (solo la transacción externa hace COMMIT/ROLLBACK real).
     *
     * @template T
     * @param callable():T $callback
     * @return T
     */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::connection();
        if (self::$transactionDepth === 0) {
            $pdo->beginTransaction();
        }
        self::$transactionDepth++;

        try {
            $result = $callback();
            self::$transactionDepth--;
            if (self::$transactionDepth === 0) {
                $pdo->commit();
            }

            return $result;
        } catch (Throwable $e) {
            self::$transactionDepth--;
            if (self::$transactionDepth === 0 && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** Genera "?, ?, ?" para cláusulas IN. */
    public static function placeholders(int $count): string
    {
        return implode(', ', array_fill(0, max(1, $count), '?'));
    }
}
