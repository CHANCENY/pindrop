<?php

declare(strict_types=1);

namespace Simp\Pindrop\Logger;

use DI\ContainerBuilder;
use Simp\Pindrop\Services\EnvServiceProvider;
use Simp\Pindrop\Database\DatabaseService;

/**
 * Logger Service Provider
 * 
 * Provides logger services for dependency injection container.
 * Supports multiple logger implementations and configuration.
 */
class LoggerServiceProvider
{
    private EnvServiceProvider $envProvider;
    
    public function __construct(EnvServiceProvider $envProvider)
    {
        $this->envProvider = $envProvider;
    }
    
    /**
     * Configure DI container with logger services
     */
    public function configureContainer(ContainerBuilder $builder): void
    {
        $definitions = [
            // Logger configuration
            'logger.config' => \DI\factory([self::class, 'buildLoggerConfig']),
            
            // Null logger (default)
            'logger.null' => fn() => new NullLogger(),
            
            // File logger
            'logger.file' => fn(\DI\Container $c) => new FileLogger(
                $c->get('logger.config')['file'],
                $c->get('logger.config')['date_format'],
                $c->get('logger.config')['max_file_size'],
                $c->get('logger.config')['max_files'],
                $c->get('logger.config')['enabled']
            ),
            
            // Database logger
            'logger.database' => fn(\DI\Container $c) => new DatabaseLogger(
                $c->get('database.service')->getDatabase(),
                $c->get('logger.config')['channel'],
                $c->get('logger.config')['request_id'],
                $c->get('logger.config')['user_id'],
                $c->get('logger.config')['ip_address'],
                $c->get('logger.config')['user_agent'],
                $c->get('logger.config')['session_id'],
                $c->get('logger.config')['enabled']
            ),
            
            // Default logger (based on configuration)
            'logger' => \DI\factory([self::class, 'buildLogger']),
            
            // Database service
            'database.service' => fn(\DI\Container $c) => new DatabaseService(
                $c->get('database.config'),
                $c->get('logger'),
                $c
            ),
            
            // Database configuration
            'database.config' => \DI\factory([self::class, 'buildDatabaseConfig']),
            
            // Aliases for convenience
            LoggerInterface::class => fn(\DI\Container $c) => $c->get('logger'),
            NullLogger::class => fn(\DI\Container $c) => $c->get('logger.null'),
            FileLogger::class => fn(\DI\Container $c) => $c->get('logger.file'),
            DatabaseLogger::class => fn(\DI\Container $c) => $c->get('logger.database'),
            DatabaseService::class => fn(\DI\Container $c) => $c->get('database.service'),
        ];
        
        $builder->addDefinitions($definitions);
    }
    
    public static function buildLoggerConfig(): array
    {
        $enabled = (getenv('LOG_ENABLED') ?: 'true') === 'true';
        return [
            'driver'      => getenv('LOG_DRIVER')      ?: 'null',
            'file'        => getenv('LOG_FILE')        ?: (__DIR__ . '/../../logs/app.log'),
            'date_format' => getenv('LOG_DATE_FORMAT') ?: 'Y-m-d H:i:s',
            'max_file_size' => (int)(getenv('LOG_MAX_FILE_SIZE') ?: 10485760),
            'max_files'   => (int)(getenv('LOG_MAX_FILES') ?: 5),
            'enabled'     => $enabled,
            'level'       => getenv('LOG_LEVEL')   ?: 'info',
            'channel'     => getenv('LOG_CHANNEL') ?: 'app',
            'request_id'  => getenv('REQUEST_ID')  ?: null,
            'user_id'     => getenv('USER_ID')     ?: null,
            'ip_address'  => getenv('REMOTE_ADDR') ?: null,
            'user_agent'  => getenv('HTTP_USER_AGENT') ?: null,
            'session_id'  => getenv('SESSION_ID')  ?: null,
        ];
    }

    public static function buildDatabaseConfig(): array
    {
        return [
            'host'     => getenv('DB_HOST')     ?: 'localhost',
            'port'     => (int)(getenv('DB_PORT') ?: 3306),
            'database' => getenv('DB_DATABASE') ?: '',
            'username' => getenv('DB_USERNAME') ?: '',
            'password' => getenv('DB_PASSWORD') ?: '',
            'charset'  => getenv('DB_CHARSET')  ?: 'utf8mb4',
            'socket'   => getenv('DB_SOCKET')   ?: null,
        ];
    }

    public static function buildLogger(\DI\Container $c): LoggerInterface
    {
        $config = $c->get('logger.config');
        if (!$config['enabled']) { return $c->get('logger.null'); }
        return match(strtolower($config['driver'])) {
            'file'     => $c->get('logger.file'),
            'database' => $c->get('logger.database'),
            default    => $c->get('logger.null'),
        };
    }

    /**
     * Get logger configuration from environment variables (instance version kept for BC)
     */
    private function getLoggerConfig(): array
    {
        return [
            'driver' => $this->envProvider->get('LOG_DRIVER', 'null'),
            'file' => $this->envProvider->get('LOG_FILE', __DIR__ . '/../../logs/app.log'),
            'date_format' => $this->envProvider->get('LOG_DATE_FORMAT', 'Y-m-d H:i:s'),
            'max_file_size' => (int) $this->envProvider->get('LOG_MAX_FILE_SIZE', '10485760'), // 10MB
            'max_files' => (int) $this->envProvider->get('LOG_MAX_FILES', '5'),
            'enabled' => $this->envProvider->get('LOG_ENABLED', 'true') === 'true',
            'level' => $this->envProvider->get('LOG_LEVEL', 'info'),
            'channel' => $this->envProvider->get('LOG_CHANNEL', 'app'),
            'request_id' => $this->envProvider->get('REQUEST_ID', null),
            'user_id' => $this->envProvider->get('USER_ID', null),
            'ip_address' => $this->envProvider->get('REMOTE_ADDR', null),
            'user_agent' => $this->envProvider->get('HTTP_USER_AGENT', null),
            'session_id' => $this->envProvider->get('SESSION_ID', null),
        ];
    }
    
    /**
     * Get database configuration from environment variables
     */
    private function getDatabaseConfig(): array
    {
        return [
            'host' => $this->envProvider->get('DB_HOST', 'localhost'),
            'port' => (int) $this->envProvider->get('DB_PORT', '3306'),
            'database' => $this->envProvider->get('DB_DATABASE'),
            'username' => $this->envProvider->get('DB_USERNAME'),
            'password' => $this->envProvider->get('DB_PASSWORD'),
            'charset' => $this->envProvider->get('DB_CHARSET', 'utf8mb4'),
            'socket' => $this->envProvider->get('DB_SOCKET'),
        ];
    }
    
    /**
     * Create logger instance based on configuration
     */
    private function createLogger(\DI\Container $container): LoggerInterface
    {
        $config = $container->get('logger.config');

        // If logging is disabled, return null logger
        if (!$config['enabled']) {
            return $container->get('logger.null');
        }
        
        // Create logger based on driver
        switch (strtolower($config['driver'])) {
            case 'file':
                return $container->get('logger.file');
                
            case 'database':
                return $container->get('logger.database');
                
            case 'null':
            default:
                return $container->get('logger.null');
        }
    }
    
    /**
     * Register logger service with container
     */
    public function register(ContainerBuilder $builder): void
    {
        $this->configureContainer($builder);
    }
    
    /**
     * Get available logger drivers
     */
    public function getAvailableDrivers(): array
    {
        return [
            'null' => NullLogger::class,
            'file' => FileLogger::class,
            'database' => DatabaseLogger::class,
        ];
    }
    
    /**
     * Get logger configuration template
     */
    public function getConfigTemplate(): array
    {
        return [
            'LOG_DRIVER' => 'null|file|database',
            'LOG_FILE' => '/path/to/logfile.log',
            'LOG_DATE_FORMAT' => 'Y-m-d H:i:s',
            'LOG_MAX_FILE_SIZE' => '10485760', // 10MB in bytes
            'LOG_MAX_FILES' => '5',
            'LOG_ENABLED' => 'true|false',
            'LOG_LEVEL' => 'emergency|alert|critical|error|warning|notice|info|debug',
            'LOG_CHANNEL' => 'app|auth|database|mail|etc',
            'REQUEST_ID' => 'unique_request_identifier',
            'USER_ID' => 'current_user_id',
            'REMOTE_ADDR' => 'client_ip_address',
            'HTTP_USER_AGENT' => 'client_user_agent',
            'SESSION_ID' => 'session_identifier',
        ];
    }
}
