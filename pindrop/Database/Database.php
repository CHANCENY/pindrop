<?php

declare(strict_types=1);

/**
 * The Database class provides an interface for interacting with the application's database.
 *
 * This class serves as a central hub for database-related operations, such as
 * establishing connections, executing queries, and managing transactions.
 * It is designed to facilitate seamless interaction with the underlying database system.
 */

namespace Simp\Pindrop\Database;

use PDO;
use PDOException;
use PDOStatement;

class Database
{
    private ?PDO $pdo = null;
    private array $config;
    private bool $connected = false;
    
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'host' => 'localhost',
            'port' => 3306,
            'database' => '',
            'username' => '',
            'password' => '',
            'charset' => 'utf8mb4',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => false,
            ]
        ], $config);

        if (empty($this->config['database']) || empty($this->config['username']) || empty($this->config['password'])) {
            die("Database configuration is missing.");
        }
    }
    
    /**
     * Establish database connection
     */
    public function connect(): bool
    {
        if ($this->connected && $this->pdo !== null) {
            return true;
        }
        
        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $this->config['host'],
                $this->config['port'],
                $this->config['database'],
                $this->config['charset']
            );
            
            $this->pdo = new PDO(
                $dsn,
                $this->config['username'],
                $this->config['password'],
                $this->config['options']
            );
            
            $this->connected = true;
            
            return true;
            
        } catch (PDOException $e) {
            $this->connected = false;
            $this->pdo = null;
            
            throw new DatabaseException(
                'Failed to connect to database: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }
    
    /**
     * Close database connection
     */
    public function disconnect(): void
    {
        $this->pdo = null;
        $this->connected = false;
    }
    
    /**
     * Get the PDO instance
     */
    public function getPdo(): PDO
    {
        if (!$this->connected || $this->pdo === null) {
            $this->connect();
        }
        
        return $this->pdo;
    }
    
    /**
     * Check if connected to database
     */
    public function isConnected(): bool
    {
        return $this->connected && $this->pdo !== null;
    }
    
    /**
     * Execute a query and return the statement
     */
    public function query(string $sql, ...$params): PDOStatement|bool
    {
        try {
            $pdo = $this->getPdo();
            
            if (empty($params)) {
                return $pdo->query($sql);
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            
            return $stmt;
            
        } catch (PDOException $e) {
            throw new DatabaseException(
                'Query failed: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }
    
    /**
     * Execute a query and fetch all results
     */
    public function fetchAll(string $sql, ...$params): array
    {
        $stmt = $this->query($sql, ...$params);
        
        if ($stmt instanceof PDOStatement) {
            return $stmt->fetchAll();
        }
        
        return [];
    }
    
    /**
     * Execute a query and fetch a single result
     */
    public function fetch(string $sql, ...$params): ?array
    {
        $stmt = $this->query($sql, ...$params);
        
        if ($stmt instanceof PDOStatement) {
            $result = $stmt->fetch();
            return $result !== false ? $result : null;
        }
        
        return null;
    }
    
    /**
     * Execute a query and fetch a single column
     */
    public function fetchColumn(string $sql, ...$params): mixed
    {
        $stmt = $this->query($sql, ...$params);
        
        if ($stmt instanceof PDOStatement) {
            return $stmt->fetchColumn();
        }
        
        return null;
    }
    
    /**
     * Insert data into a table
     */
    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $placeholders = str_repeat('?,', count($columns) - 1) . '?';
        
        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $table,
            implode('`, `', $columns),
            $placeholders
        );
        
        $this->query($sql, ...array_values($data));
        
        return (int) $this->getPdo()->lastInsertId();
    }
    
    /**
     * Update data in a table
     */
    public function update(string $table, array $data, string $where, ...$whereParams): int
    {
        $setParts = [];
        $params = [];
        
        foreach ($data as $column => $value) {
            $setParts[] = "`$column` = ?";
            $params[] = $value;
        }
        
        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE %s',
            $table,
            implode(', ', $setParts),
            $where
        );
        
        $params = array_merge($params, $whereParams);
        
        $stmt = $this->query($sql, ...$params);
        
        if ($stmt instanceof PDOStatement) {
            return $stmt->rowCount();
        }
        
        return 0;
    }
    
    /**
     * Delete data from a table
     */
    public function delete(string $table, string $where, ...$params): int
    {
        $sql = sprintf('DELETE FROM `%s` WHERE %s', $table, $where);
        
        $stmt = $this->query($sql, ...$params);
        
        if ($stmt instanceof PDOStatement) {
            return $stmt->rowCount();
        }
        
        return 0;
    }
    
    /**
     * Begin a transaction
     */
    public function beginTransaction(): bool
    {
        return $this->getPdo()->beginTransaction();
    }
    
    /**
     * Commit a transaction
     */
    public function commit(): bool
    {
        return $this->getPdo()->commit();
    }
    
    /**
     * Rollback a transaction
     */
    public function rollback(): bool
    {
        return $this->getPdo()->rollBack();
    }
    
    /**
     * Get the last inserted ID
     */
    public function lastInsertId(): int
    {
        return (int) $this->getPdo()->lastInsertId();
    }
    
    /**
     * Check if a table exists
     */
    public function tableExists(string $table): bool
    {
        $sql = sprintf(
            'SHOW TABLES LIKE %s',
            $this->getPdo()->quote($table)
        );
        
        $result = $this->fetch($sql);
        
        return !empty($result);
    }
    
    /**
     * Get table columns information
     */
    public function getTableColumns(string $table): array
    {
        $sql = sprintf('DESCRIBE `%s`', $table);
        
        return $this->fetchAll($sql);
    }
    
    /**
     * Get database configuration
     */
    public function getConfig(): array
    {
        return $this->config;
    }
    
    /**
     * Set database configuration
     */
    public function setConfig(array $config): void
    {
        $this->config = array_merge($this->config, $config);
        
        // Reset connection if config changed
        if ($this->connected) {
            $this->disconnect();
        }
    }
    
    /**
     * Get connection information
     */
    public function getConnectionInfo(): array
    {
        return [
            'connected' => $this->connected,
            'host' => $this->config['host'],
            'port' => $this->config['port'],
            'database' => $this->config['database'],
            'charset' => $this->config['charset'],
            'driver' => 'mysql',
        ];
    }

    /**
     * Execute raw SQL with error handling
     * @throws DatabaseException
     */
    public function exec(string $sql): int
    {
        try {
            $this->getPdo()->exec("SET FOREIGN_KEY_CHECKS = 0;");
            $results = $this->getPdo()->exec($sql);
            $this->getPdo()->exec("SET FOREIGN_KEY_CHECKS = 1;");
            return $results;
        } catch (PDOException $e) {
            throw new DatabaseException(
                'Execution failed: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }
    
    /**
     * Quote a string for safe SQL usage
     */
    public function quote(string $string): string
    {
        return $this->getPdo()->quote($string);
    }
}