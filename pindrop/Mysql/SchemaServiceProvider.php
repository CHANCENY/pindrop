<?php

declare(strict_types=1);

namespace Simp\Pindrop\Mysql;

use DI\ContainerBuilder;
use Simp\Pindrop\Services\EnvServiceProvider;

/**
 * Schema Service Provider
 * 
 * Provides schema services for dependency injection container.
 * Supports MySQL schema management and table creation.
 */
class SchemaServiceProvider
{
    private EnvServiceProvider $envProvider;
    
    public function __construct(EnvServiceProvider $envProvider)
    {
        $this->envProvider = $envProvider;
    }
    
    /**
     * Configure DI container with schema services
     */
    public function configureContainer(ContainerBuilder $builder): void
    {
        $definitions = [
            // Schema configuration
            'schema.config' => \DI\factory([self::class, 'buildSchemaConfig']),
            
            // Schema handler
            'schema.handler' => function(\DI\Container $c) { return new SchemaHandler($c->get('database.service')->getDatabase(), $c->get('logger'), $c->get('schema.config')['schema_path']); },
            
            // Aliases for convenience
            SchemaHandler::class => function(\DI\Container $c) { return $c->get('schema.handler'); },
        ];
        
        $builder->addDefinitions($definitions);
    }
    
    public static function buildSchemaConfig(): array
    {
        return [
            'schema_path'          => getenv('SCHEMA_PATH')          ?: (__DIR__ . '/schema'),
            'auto_create_tables'   => (getenv('AUTO_CREATE_TABLES')  ?: 'false') === 'true',
            'validate_schemas'     => (getenv('VALIDATE_SCHEMAS')    ?: 'true')  === 'true',
            'enable_partitioning'  => (getenv('ENABLE_PARTITIONING') ?: 'false') === 'true',
            'enable_fulltext_search' => (getenv('ENABLE_FULLTEXT_SEARCH') ?: 'true') === 'true',
        ];
    }

    /**
     * Get schema configuration from environment variables (instance version kept for BC)
     */
    private function getSchemaConfig(): array
    {
        return [
            'schema_path' => $this->envProvider->get('SCHEMA_PATH', __DIR__ . '/schema'),
            'auto_create_tables' => $this->envProvider->get('AUTO_CREATE_TABLES', 'false') === 'true',
            'validate_schemas' => $this->envProvider->get('VALIDATE_SCHEMAS', 'true') === 'true',
            'enable_partitioning' => $this->envProvider->get('ENABLE_PARTITIONING', 'false') === 'true',
            'enable_fulltext_search' => $this->envProvider->get('ENABLE_FULLTEXT_SEARCH', 'true') === 'true',
        ];
    }
    
    /**
     * Register schema service with container
     */
    public function register(ContainerBuilder $builder): void
    {
        $this->configureContainer($builder);
    }
    
    /**
     * Get available schema services
     */
    public function getAvailableServices(): array
    {
        return [
            'schema' => SchemaHandler::class,
        ];
    }
    
    /**
     * Get schema configuration template
     */
    public function getConfigTemplate(): array
    {
        return [
            'SCHEMA_PATH' => '/path/to/schema/directory',
            'AUTO_CREATE_TABLES' => 'true|false',
            'VALIDATE_SCHEMAS' => 'true|false',
            'ENABLE_PARTITIONING' => 'true|false',
            'ENABLE_FULLTEXT_SEARCH' => 'true|false',
        ];
    }
    
    /**
     * Auto-create tables if enabled
     */
    public function autoCreateTables(\DI\Container $container): array
    {
        $config = $container->get('schema.config');
        
        if (!$config['auto_create_tables']) {
            return ['skipped' => true, 'message' => 'Auto table creation disabled'];
        }
        
        $schemaHandler = $container->get('schema.handler');
        
        // Validate schemas first if enabled
        if ($config['validate_schemas']) {
            $validation = $schemaHandler->validateAllSchemaFiles();
            $invalidFiles = array_filter($validation, fn($result) => !$result['valid']);
            
            if (!empty($invalidFiles)) {
                return [
                    'success' => false,
                    'message' => 'Schema validation failed',
                    'invalid_files' => $invalidFiles
                ];
            }
        }
        
        // Create all tables
        return $schemaHandler->createTables();
    }
    
    /**
     * Setup database schema
     */
    public function setupDatabase(\DI\Container $container): array
    {
        $results = [];
        
        try {
            // Auto-create tables
            $tableResults = $this->autoCreateTables($container);
            $results['tables'] = $tableResults;
            
            // Enable full-text search if configured
            $config = $container->get('schema.config');
            if ($config['enable_fulltext_search']) {
                $schemaHandler = $container->get('schema.handler');
                $searchResults = $this->enableFullTextSearch($schemaHandler);
                $results['fulltext_search'] = $searchResults;
            }
            
            // Enable partitioning if configured
            if ($config['enable_partitioning']) {
                $schemaHandler = $container->get('schema.handler');
                $partitionResults = $this->enablePartitioning($schemaHandler);
                $results['partitioning'] = $partitionResults;
            }
            
            return [
                'success' => true,
                'message' => 'Database schema setup completed',
                'results' => $results
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'results' => $results
            ];
        }
    }
    
    /**
     * Enable full-text search indexes
     */
    private function enableFullTextSearch(SchemaHandler $schemaHandler): array
    {
        $results = [];
        
        // Add full-text index to users table
        try {
            $database = $schemaHandler->getDatabase();
            $database->exec('ALTER TABLE `users` ADD FULLTEXT `ft_search` (`username`, `email`, `full_name`, `display_name`, `bio`)');
            
            $results['users'] = [
                'success' => true,
                'message' => 'Full-text search index added to users table'
            ];
            
        } catch (\Exception $e) {
            $results['users'] = [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
        
        return $results;
    }
    
    /**
     * Enable table partitioning
     */
    private function enablePartitioning(SchemaHandler $schemaHandler): array
    {
        $results = [];
        
        // Add partitioning to logs table
        try {
            $database = $schemaHandler->getDatabase();
            $database->exec('ALTER TABLE `logs` PARTITION BY RANGE (TO_DAYS(`datetime`)) (
                PARTITION p_current VALUES LESS THAN (TO_DAYS(CURRENT_DATE + INTERVAL 1 DAY)),
                PARTITION p_future VALUES LESS THAN MAXVALUE
            )');
            
            $results['logs'] = [
                'success' => true,
                'message' => 'Partitioning enabled for logs table'
            ];
            
        } catch (\Exception $e) {
            $results['logs'] = [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
        
        return $results;
    }
}
