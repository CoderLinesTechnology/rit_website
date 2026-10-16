<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;
use Throwable;

/**
 * Thin PDO wrapper. Every variable value goes through a prepared statement;
 * table and column names passed to insert()/update() must be identifiers
 * written in code (they are checked, never taken from user input).
 */
final class Database
{
    private static ?self $instance = null;

    private function __construct(private readonly PDO $pdo)
    {
    }

    public static function connection(): self
    {
        if (self::$instance === null) {
            self::$instance = new self(self::connect());
        }
        return self::$instance;
    }

    /** For tests: swap in another connection. */
    public static function setConnection(?self $db): void
    {
        self::$instance = $db;
    }

    public static function fromPdo(PDO $pdo): self
    {
        return new self($pdo);
    }

    private static function connect(): PDO
    {
        $cfg = (array) config('database');
        if (($cfg['name'] ?? '') === '' || ($cfg['user'] ?? '') === '') {
            throw new \RuntimeException('Database is not configured (DB_NAME / DB_USER).');
        }
        $dsn = ($cfg['socket'] ?? '') !== ''
            ? sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s', $cfg['socket'], $cfg['name'], $cfg['charset'])
            : sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $cfg['host'], $cfg['port'], $cfg['name'], $cfg['charset']);

        $pdo = new PDO($dsn, (string) $cfg['user'], (string) $cfg['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        // Store and compare all DATETIME values in UTC (Ghana is UTC+0 year-round anyway).
        $pdo->exec("SET time_zone = '+00:00', NAMES utf8mb4 COLLATE " . $cfg['collation']);
        return $pdo;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    /** @param array<string|int, mixed> $params */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $name = is_int($key) ? $key + 1 : (str_starts_with($key, ':') ? $key : ':' . $key);
            $type = match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_INT,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            $stmt->bindValue($name, is_bool($value) ? (int) $value : $value, $type);
        }
        $stmt->execute();
        return $stmt;
    }

    /**
     * @param array<string|int, mixed> $params
     * @return array<string, mixed>|null
     */
    public function fetch(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /**
     * @param array<string|int, mixed> $params
     * @return list<array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /** @param array<string|int, mixed> $params */
    public function fetchValue(string $sql, array $params = []): mixed
    {
        $value = $this->query($sql, $params)->fetchColumn();
        return $value === false ? null : $value;
    }

    /** @param array<string|int, mixed> $params */
    public function execute(string $sql, array $params = []): int
    {
        return $this->query($sql, $params)->rowCount();
    }

    /** @param array<string, mixed> $data */
    public function insert(string $table, array $data): int
    {
        self::assertIdentifier($table);
        $columns = array_keys($data);
        array_walk($columns, [self::class, 'assertIdentifier']);
        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $table,
            implode('`, `', $columns),
            implode(', ', array_map(static fn (string $c): string => ':' . $c, $columns))
        );
        $this->query($sql, $data);
        return (int) $this->pdo->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(string $table, int $id, array $data): int
    {
        self::assertIdentifier($table);
        if ($data === []) {
            return 0;
        }
        $sets = [];
        foreach (array_keys($data) as $column) {
            self::assertIdentifier($column);
            $sets[] = sprintf('`%s` = :%s', $column, $column);
        }
        $data['__id'] = $id;
        return $this->execute(sprintf('UPDATE `%s` SET %s WHERE id = :__id', $table, implode(', ', $sets)), $data);
    }

    public function delete(string $table, int $id): int
    {
        self::assertIdentifier($table);
        return $this->execute(sprintf('DELETE FROM `%s` WHERE id = :id', $table), ['id' => $id]);
    }

    /**
     * Runs $callback inside a transaction; commits on success and rolls back
     * (re-throwing) on any exception. Nested calls join the outer transaction.
     *
     * @template T
     * @param callable(self): T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        if ($this->pdo->inTransaction()) {
            return $callback($this);
        }
        $this->pdo->beginTransaction();
        try {
            $result = $callback($this);
            $this->pdo->commit();
            return $result;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function assertIdentifier(string $name): void
    {
        if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/', $name) && $name !== '__id') {
            throw new \InvalidArgumentException('Invalid SQL identifier.');
        }
    }

    /** Escapes LIKE wildcards in a user search term (bind the result as a parameter). */
    public static function likeTerm(string $term): string
    {
        return '%' . addcslashes($term, '%_\\') . '%';
    }
}
