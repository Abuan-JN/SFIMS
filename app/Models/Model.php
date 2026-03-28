<?php

namespace App\Models;

use PDO;
use PDOException;

abstract class Model
{
    protected static PDO $connection;
    protected static string $table;
    protected static array $fillable = [];
    protected static array $hidden = [];

    public function __construct()
    {
        if (!isset(self::$connection)) {
            self::connect();
        }
    }

    private static function connect(): void
    {
        try {
            $host = $_ENV['DB_HOST'] ?? 'localhost';
            $dbname = $_ENV['DB_NAME'] ?? 'sfims';
            $username = $_ENV['DB_USER'] ?? 'root';
            $password = $_ENV['DB_PASS'] ?? '';

            self::$connection = new PDO(
                "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
                $username,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            throw new \Exception("Database connection failed");
        }
    }

    public static function all(): array
    {
        $instance = new static();
        $stmt = self::$connection->query("SELECT * FROM " . static::$table);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $instance = new static();
        $stmt = self::$connection->prepare("SELECT * FROM " . static::$table . " WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public static function where(string $column, string $operator, $value): array
    {
        $instance = new static();
        $stmt = self::$connection->prepare("SELECT * FROM " . static::$table . " WHERE {$column} {$operator} ?");
        $stmt->execute([$value]);
        return $stmt->fetchAll();
    }

    public static function create(array $data): int
    {
        $instance = new static();
        $filtered = array_intersect_key($data, array_flip(static::$fillable));
        
        $columns = implode(', ', array_keys($filtered));
        $placeholders = implode(', ', array_fill(0, count($filtered), '?'));
        
        $stmt = self::$connection->prepare("INSERT INTO " . static::$table . " ({$columns}) VALUES ({$placeholders})");
        $stmt->execute(array_values($filtered));
        
        return (int) self::$connection->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $instance = new static();
        $filtered = array_intersect_key($data, array_flip(static::$fillable));
        
        $set = implode(' = ?, ', array_keys($filtered)) . ' = ?';
        
        $stmt = self::$connection->prepare("UPDATE " . static::$table . " SET {$set} WHERE id = ?");
        return $stmt->execute([...array_values($filtered), $id]);
    }

    public static function delete(int $id): bool
    {
        $instance = new static();
        $stmt = self::$connection->prepare("DELETE FROM " . static::$table . " WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public static function query(string $sql, array $params = []): array
    {
        $instance = new static();
        $stmt = self::$connection->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function count(string $where = '', array $params = []): int
    {
        $instance = new static();
        $sql = "SELECT COUNT(*) as count FROM " . static::$table;
        if ($where) {
            $sql .= " WHERE {$where}";
        }
        $stmt = self::$connection->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return (int) $result['count'];
    }

    public static function getConnection(): PDO
    {
        if (!isset(self::$connection)) {
            self::connect();
        }
        return self::$connection;
    }
}
