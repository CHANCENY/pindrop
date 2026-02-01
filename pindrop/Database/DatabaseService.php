<?php

declare(strict_types=1);

namespace Simp\Pindrop\Database;

use DI\Container;
use Simp\Pindrop\Logger\LoggerInterface;
use Simp\Pindrop\Logger\NullLogger;

/**
 * Database Service
 * 
 * Service layer for database operations with DI container integration.
 * Provides a high-level interface for database interactions with
 * logging, configuration management, and service discovery.
 */
class DatabaseService
{
    private Database $database;
    private ?LoggerInterface $logger;
    private Container $container;
    private array $config;
    
    public function __construct(
        array $config,
        ?LoggerInterface $logger = null,
        ?Container $container = null
    ) {
        $this->config = $config;
        $this->logger = $logger ?? new NullLogger();
        $this->container = $container;
        
        $this->initializeDatabase();
    }
    
    /**
     * Initialize database connection
     */
    private function initializeDatabase(): void
    {
        try {
            $this->database = new Database($this->config);
            $this->logger->info('Database service initialized', [
                'host' => $this->config['host'] ?? 'localhost',
                'database' => $this->config['database'] ?? 'unknown'
            ]);
            
        } catch (DatabaseException $e) {
            $this->logger->error('Failed to initialize database service', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Get database instance
     */
    public function getDatabase(): Database
    {
        return $this->database;
    }
    
    /**
     * Get PDO instance
     */
    public function getPdo(): \PDO
    {
        return $this->database->getPdo();
    }
    
    /**
     * Check if database is connected
     */
    public function isConnected(): bool
    {
        return $this->database->isConnected();
    }
    
    /**
     * Connect to database
     */
    public function connect(): bool
    {
        try {
            $result = $this->database->connect();
            
            if ($result) {
                $this->logger->info('Database connected successfully');
            }
            
            return $result;
            
        } catch (DatabaseException $e) {
            $this->logger->error('Database connection failed', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'operation' => $e->getOperation()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Disconnect from database
     */
    public function disconnect(): void
    {
        $this->database->disconnect();
        $this->logger->info('Database disconnected');
    }
    
    /**
     * Execute query with logging
     */
    public function query(string $sql, ...$params): \PDOStatement|bool
    {
        $this->logger->debug('Executing database query', [
            'sql' => $sql,
            'parameters' => $params
        ]);
        
        try {
            $result = $this->database->query($sql, ...$params);
            
            $this->logger->debug('Query executed successfully', [
                'sql' => $sql,
                'affected_rows' => $result instanceof \PDOStatement ? $result->rowCount() : 0
            ]);
            
            return $result;
            
        } catch (DatabaseException $e) {
            $this->logger->error('Database query failed', [
                'sql' => $sql,
                'parameters' => $params,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Fetch all results with logging
     */
    public function fetchAll(string $sql, ...$params): array
    {
        $this->logger->debug('Fetching all results', [
            'sql' => $sql,
            'parameters' => $params
        ]);
        
        try {
            $results = $this->database->fetchAll($sql, ...$params);
            
            $this->logger->debug('Results fetched successfully', [
                'sql' => $sql,
                'count' => count($results)
            ]);
            
            return $results;
            
        } catch (DatabaseException $e) {
            $this->logger->error('Fetch all failed', [
                'sql' => $sql,
                'parameters' => $params,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Fetch single result with logging
     */
    public function fetch(string $sql, ...$params): ?array
    {
        $this->logger->debug('Fetching single result', [
            'sql' => $sql,
            'parameters' => $params
        ]);
        
        try {
            $result = $this->database->fetch($sql, ...$params);
            
            $this->logger->debug('Result fetched successfully', [
                'sql' => $sql,
                'found' => $result !== null
            ]);
            
            return $result;
            
        } catch (DatabaseException $e) {
            $this->logger->error('Fetch failed', [
                'sql' => $sql,
                'parameters' => $params,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Begin transaction with logging
     */
    public function beginTransaction(): bool
    {
        $this->logger->debug('Beginning database transaction');
        
        try {
            $result = $this->database->beginTransaction();
            
            if ($result) {
                $this->logger->debug('Transaction started successfully');
            }
            
            return $result;
            
        } catch (DatabaseException $e) {
            $this->logger->error('Failed to begin transaction', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Commit transaction with logging
     */
    public function commit(): bool
    {
        $this->logger->debug('Committing database transaction');
        
        try {
            $result = $this->database->commit();
            
            if ($result) {
                $this->logger->info('Transaction committed successfully');
            }
            
            return $result;
            
        } catch (DatabaseException $e) {
            $this->logger->error('Failed to commit transaction', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Rollback transaction with logging
     */
    public function rollback(): bool
    {
        $this->logger->warning('Rolling back database transaction');
        
        try {
            $result = $this->database->rollback();
            
            if ($result) {
                $this->logger->info('Transaction rolled back successfully');
            }
            
            return $result;
            
        } catch (DatabaseException $e) {
            $this->logger->error('Failed to rollback transaction', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Insert data with logging
     */
    public function insert(string $table, array $data): int
    {
        $this->logger->debug('Inserting data', [
            'table' => $table,
            'data' => $data
        ]);
        
        try {
            $id = $this->database->insert($table, $data);
            
            $this->logger->info('Data inserted successfully', [
                'table' => $table,
                'id' => $id
            ]);
            
            return $id;
            
        } catch (DatabaseException $e) {
            $this->logger->error('Insert failed', [
                'table' => $table,
                'data' => $data,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Update data with logging
     */
    public function update(string $table, array $data, string $where, ...$whereParams): int
    {
        $this->logger->debug('Updating data', [
            'table' => $table,
            'data' => $data,
            'where' => $where,
            'parameters' => $whereParams
        ]);
        
        try {
            $affectedRows = $this->database->update($table, $data, $where, ...$whereParams);
            
            $this->logger->info('Data updated successfully', [
                'table' => $table,
                'affected_rows' => $affectedRows
            ]);
            
            return $affectedRows;
            
        } catch (DatabaseException $e) {
            $this->logger->error('Update failed', [
                'table' => $table,
                'data' => $data,
                'where' => $where,
                'parameters' => $whereParams,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Delete data with logging
     */
    public function delete(string $table, string $where, ...$params): int
    {
        $this->logger->debug('Deleting data', [
            'table' => $table,
            'where' => $where,
            'parameters' => $params
        ]);
        
        try {
            $affectedRows = $this->database->delete($table, $where, ...$params);
            
            $this->logger->info('Data deleted successfully', [
                'table' => $table,
                'affected_rows' => $affectedRows
            ]);
            
            return $affectedRows;
            
        } catch (DatabaseException $e) {
            $this->logger->error('Delete failed', [
                'table' => $table,
                'where' => $where,
                'parameters' => $params,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Check if table exists with logging
     */
    public function tableExists(string $table): bool
    {
        $this->logger->debug('Checking table existence', ['table' => $table]);
        
        try {
            $exists = $this->database->tableExists($table);
            
            $this->logger->debug('Table existence checked', [
                'table' => $table,
                'exists' => $exists
            ]);
            
            return $exists;
            
        } catch (DatabaseException $e) {
            $this->logger->error('Table existence check failed', [
                'table' => $table,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Get table columns with logging
     */
    public function getTableColumns(string $table): array
    {
        $this->logger->debug('Getting table columns', ['table' => $table]);
        
        try {
            $columns = $this->database->getTableColumns($table);
            
            $this->logger->debug('Table columns retrieved', [
                'table' => $table,
                'column_count' => count($columns)
            ]);
            
            return $columns;
            
        } catch (DatabaseException $e) {
            $this->logger->error('Failed to get table columns', [
                'table' => $table,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Get database configuration
     */
    public function getConfig(): array
    {
        return $this->database->getConfig();
    }
    
    /**
     * Get connection information
     */
    public function getConnectionInfo(): array
    {
        $info = $this->database->getConnectionInfo();
        $info['service_initialized'] = true;
        $info['logger_available'] = $this->logger !== null;
        $info['container_available'] = $this->container !== null;
        
        return $info;
    }
    
    /**
     * Execute raw SQL with logging
     */
    public function exec(string $sql): int
    {
        $this->logger->debug('Executing raw SQL', ['sql' => $sql]);
        
        try {
            $affectedRows = $this->database->exec($sql);
            
            $this->logger->info('Raw SQL executed successfully', [
                'sql' => $sql,
                'affected_rows' => $affectedRows
            ]);
            
            return $affectedRows;
            
        } catch (DatabaseException $e) {
            $this->logger->error('Raw SQL execution failed', [
                'sql' => $sql,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            
            throw $e;
        }
    }
    
    /**
     * Get last insert ID
     */
    public function lastInsertId(): int
    {
        return $this->database->lastInsertId();
    }
    
    /**
     * Quote string for safe SQL
     */
    public function quote(string $string): string
    {
        return $this->database->quote($string);
    }
    
    /**
     * Test database connection
     */
    public function testConnection(): bool
    {
        $this->logger->info('Testing database connection');
        
        try {
            $connected = $this->database->isConnected();
            
            if (!$connected) {
                $this->connect();
            }
            
            // Test with a simple query
            $this->database->query('SELECT 1');
            
            $this->logger->info('Database connection test successful');
            return true;
            
        } catch (DatabaseException $e) {
            $this->logger->error('Database connection test failed', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            
            return false;
        }
    }
    
    /**
     * Get service statistics
     */
    public function getStatistics(): array
    {
        return [
            'connected' => $this->isConnected(),
            'connection_info' => $this->getConnectionInfo(),
            'config' => $this->getConfig(),
            'logger_enabled' => $this->logger !== null && !($this->logger instanceof NullLogger),
            'container_available' => $this->container !== null,
        ];
    }
}
