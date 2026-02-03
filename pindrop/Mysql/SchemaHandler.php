<?php

declare(strict_types=1);

namespace Simp\Pindrop\Mysql;

use Simp\Pindrop\Database\Database;
use Simp\Pindrop\Database\DatabaseException;
use Simp\Pindrop\Logger\LoggerInterface;

/**
 * Schema Handler
 * 
 * Handles MySQL schema creation and management.
 * Supports creating tables from SQL schema files.
 */
class SchemaHandler
{
    private Database $database;
    private ?LoggerInterface $logger;
    private string $schemaPath;
    
    public function __construct(
        Database $database,
        ?LoggerInterface $logger = null,
        string $schemaPath = __DIR__ . '/schema'
    ) {
        $this->database = $database;
        $this->logger = $logger;
        $this->schemaPath = $schemaPath;
    }
    
    /**
     * Create all schema tables
     *
     * @param array $tables List of tables to create (empty for all)
     * @return array Creation results
     */
    public function createTables(array $tables = []): array
    {
        $results = [];
        
        // Get available schema files
        $schemaFiles = $this->getSchemaFiles();
        
        // Filter tables if specified
        if (!empty($tables)) {
            $schemaFiles = array_filter($schemaFiles, function($file) use ($tables) {
                $tableName = $this->getTableNameFromFile($file);
                return in_array($tableName, $tables);
            });
        }
        
        // Create each table
        foreach ($schemaFiles as $file) {
            $tableName = $this->getTableNameFromFile($file);
            $results[$tableName] = $this->createTableFromFile($file, $tableName);
        }
        
        $this->logger?->info('Schema creation completed', [
            'tables_created' => count(array_filter($results, fn($r) => $r['success'])),
            'total_tables' => count($results),
            'results' => $results
        ]);
        
        return $results;
    }
    
    /**
     * Create a single table from schema file
     *
     * @param string $schemaFile Path to schema file
     * @param string $tableName Table name
     * @return array Creation result
     */
    public function createTableFromFile(string $schemaFile, string $tableName): array
    {
        try {
            $this->logger?->info('Creating table from schema file', [
                'table' => $tableName,
                'file' => $schemaFile
            ]);
            
            // Read schema file
            $sql = $this->readSchemaFile($schemaFile);
            
            if (empty($sql)) {
                throw new \RuntimeException("Schema file is empty: {$schemaFile}");
            }
            
            // Check if table already exists
            if ($this->database->tableExists($tableName)) {
                $this->logger?->warning('Table already exists', [
                    'table' => $tableName
                ]);
                
                return [
                    'success' => false,
                    'message' => "Table '{$tableName}' already exists",
                    'table' => $tableName,
                    'file' => $schemaFile
                ];
            }
            
            // Execute schema
            $this->database->exec($sql);
            
            $this->logger?->info('Table created successfully', [
                'table' => $tableName,
                'file' => $schemaFile
            ]);
            
            return [
                'success' => true,
                'message' => "Table '{$tableName}' created successfully",
                'table' => $tableName,
                'file' => $schemaFile
            ];
            
        } catch (DatabaseException $e) {
            $this->logger?->error('Failed to create table', [
                'table' => $tableName,
                'file' => $schemaFile,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'table' => $tableName,
                'file' => $schemaFile,
                'error_code' => $e->getCode()
            ];
            
        } catch (\Exception $e) {
            $this->logger?->error('Unexpected error creating table', [
                'table' => $tableName,
                'file' => $schemaFile,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'table' => $tableName,
                'file' => $schemaFile
            ];
        }
    }
    
    /**
     * Drop tables
     *
     * @param array $tables List of tables to drop
     * @return array Drop results
     */
    public function dropTables(array $tables): array
    {
        $results = [];
        
        foreach ($tables as $tableName) {
            $results[$tableName] = $this->dropTable($tableName);
        }
        
        $this->logger?->info('Table drop operation completed', [
            'tables_dropped' => count(array_filter($results, fn($r) => $r['success'])),
            'total_tables' => count($results),
            'results' => $results
        ]);
        
        return $results;
    }
    
    /**
     * Drop a single table
     *
     * @param string $tableName Table name
     * @return array Drop result
     */
    public function dropTable(string $tableName): array
    {
        try {
            if (!$this->database->tableExists($tableName)) {
                return [
                    'success' => false,
                    'message' => "Table '{$tableName}' does not exist",
                    'table' => $tableName
                ];
            }
            
            $sql = "DROP TABLE IF EXISTS `{$tableName}`";
            $this->database->exec($sql);
            
            $this->logger?->info('Table dropped successfully', ['table' => $tableName]);
            
            return [
                'success' => true,
                'message' => "Table '{$tableName}' dropped successfully",
                'table' => $tableName
            ];
            
        } catch (DatabaseException $e) {
            $this->logger?->error('Failed to drop table', [
                'table' => $tableName,
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'table' => $tableName,
                'error_code' => $e->getCode()
            ];
        }
    }
    
    /**
     * Get list of available schema files
     *
     * @return array Schema files
     */
    public function getSchemaFiles(): array
    {
        $files = [];
        
        if (!is_dir($this->schemaPath)) {
            return $files;
        }
        
        $iterator = new \DirectoryIterator($this->schemaPath);
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'sql') {
                $files[] = $file->getPathname();
            }
        }

        $schemaFiles = \getAppContainer()->get('plugin.manager')->getPluginMysqlSchemas();
        $files = array_merge($files, array_values($schemaFiles));

        sort($files);
        return $files;
    }
    
    /**
     * Get table name from schema file path
     *
     * @param string $filePath File path
     * @return string Table name
     */
    private function getTableNameFromFile(string $filePath): string
    {
        $filename = basename($filePath, '.sql');
        return strtolower($filename);
    }
    
    /**
     * Read schema file content
     *
     * @param string $filePath File path
     * @return string SQL content
     */
    private function readSchemaFile(string $filePath): string
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("Schema file not found: {$filePath}");
        }
        
        $content = file_get_contents($filePath);
        
        if ($content === false) {
            throw new \RuntimeException("Failed to read schema file: {$filePath}");
        }
        
        return $content;
    }
    
    /**
     * Check if table exists
     *
     * @param string $tableName Table name
     * @return bool Table exists
     */
    public function tableExists(string $tableName): bool
    {
        return $this->database->tableExists($tableName);
    }
    
    /**
     * Get table columns information
     *
     * @param string $tableName Table name
     * @return array Columns information
     */
    public function getTableColumns(string $tableName): array
    {
        return $this->database->getTableColumns($tableName);
    }
    
    /**
     * Get database schema information
     *
     * @return array Schema information
     */
    public function getSchemaInfo(): array
    {
        return [
            'schema_path' => $this->schemaPath,
            'schema_files' => $this->getSchemaFiles(),
            'available_tables' => array_map([$this, 'getTableNameFromFile'], $this->getSchemaFiles()),
            'database_info' => $this->database->getConnectionInfo()
        ];
    }
    
    /**
     * Validate schema file
     *
     * @param string $filePath File path
     * @return array Validation result
     */
    public function validateSchemaFile(string $filePath): array
    {
        try {
            $sql = $this->readSchemaFile($filePath);
            
            if (empty($sql)) {
                return [
                    'valid' => false,
                    'message' => 'Schema file is empty',
                    'file' => $filePath
                ];
            }
            
            // Basic SQL validation
            if (!preg_match('/CREATE\s+TABLE/i', $sql)) {
                return [
                    'valid' => false,
                    'message' => 'Schema file does not contain CREATE TABLE statement',
                    'file' => $filePath
                ];
            }
            
            return [
                'valid' => true,
                'message' => 'Schema file is valid',
                'file' => $filePath
            ];
            
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'message' => $e->getMessage(),
                'file' => $filePath
            ];
        }
    }
    
    /**
     * Validate all schema files
     *
     * @return array Validation results
     */
    public function validateAllSchemaFiles(): array
    {
        $results = [];
        $files = $this->getSchemaFiles();
        
        foreach ($files as $file) {
            $tableName = $this->getTableNameFromFile($file);
            $results[$tableName] = $this->validateSchemaFile($file);
        }
        
        return $results;
    }
}
