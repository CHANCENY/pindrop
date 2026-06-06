<?php

declare(strict_types=1);

namespace Simp\Pindrop\Services;

use DI\ContainerBuilder;
use Simp\Pindrop\Database\DatabaseService;
use Simp\Pindrop\Database\DatabasePermissionGuard;
use Simp\Pindrop\Database\CurrentUserResolver;
use Simp\Pindrop\Database\PluginTableRegistry;
use Simp\Pindrop\Logger\LoggerInterface;

/**
 * Database Service Provider
 * 
 * Provides database service configuration and registration for DI container.
 */
class DatabaseServiceProvider
{
    private EnvServiceProvider $envProvider;

    public function __construct(EnvServiceProvider $envProvider)
    {
        $this->envProvider = $envProvider;
    }

    /**
     * Configure database services in DI container
     */
    public function configureContainer(ContainerBuilder $builder): void
    {
        $builder->addDefinitions([
            /**
             * Database configuration
             */
            'database.config' => function () {
                $persistent = (bool)(getenv('DB_PERSISTENT') ?: false);
                $charset    = getenv('DB_CHARSET')   ?: 'utf8mb4';
                $collation  = getenv('DB_COLLATION') ?: 'utf8mb4_unicode_ci';

                $options = [
                    \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                    \PDO::ATTR_EMULATE_PREPARES   => false,
                    \PDO::ATTR_PERSISTENT         => $persistent,
                    \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                    \PDO::ATTR_TIMEOUT            => (int)(getenv('DB_TIMEOUT') ?: 30),
                ];

                // MYSQL_ATTR_INIT_COMMAND runs only on NEW connections.
                // With persistent connections the underlying socket is reused
                // and init_command is silently skipped — the charset could be
                // whatever the previous request left it as.
                // We therefore skip init_command when persistent=true and
                // instead run SET NAMES explicitly inside Database::connect()
                // every time, which is safe for both persistent and non-persistent.
                if (!$persistent) {
                    $options[\PDO::MYSQL_ATTR_INIT_COMMAND] = "SET NAMES {$charset} COLLATE {$collation}";
                }

                return [
                    'host'       => getenv('DB_HOST')     ?: 'localhost',
                    'port'       => getenv('DB_PORT')     ?: 3306,
                    'database'   => getenv('DB_DATABASE') ?: 'pindrop',
                    'username'   => getenv('DB_USERNAME') ?: 'root',
                    'password'   => getenv('DB_PASSWORD') ?: '',
                    'charset'    => $charset,
                    'collation'  => $collation,
                    'prefix'     => getenv('DB_PREFIX')   ?: '',
                    'strict'     => getenv('DB_STRICT')   ?: true,
                    'engine'     => getenv('DB_ENGINE')   ?: 'InnoDB',
                    'persistent' => $persistent,
                    'options'    => $options,
                ];
            },

            /**
             * Database service
             */
            DatabaseService::class => function (\DI\Container $container) {
                $config = $container->get('database.config');
                $logger = $container->has(LoggerInterface::class)
                    ? $container->get(LoggerInterface::class)
                    : null;

                // The PluginTableRegistry is owned by PluginManager — it is
                // populated during plugin discovery (loadInstalledPlugins).
                // We retrieve the SAME instance from the container so that
                // DatabasePermissionGuard and PluginManager share one registry.
                // At this point PluginManager may not be built yet, so we pass
                // null and let bootstrap.inc inject the real instance after the
                // container is fully assembled.
                $guardEnabled = filter_var(getenv('DB_PERMISSION_GUARD') ?: 'true', FILTER_VALIDATE_BOOLEAN);
                $guard = $guardEnabled
                    ? new DatabasePermissionGuard(new CurrentUserResolver(), new PluginTableRegistry())
                    : null;

                $service = new DatabaseService($config, $logger, $container, $guard, null);
                return $service;
            },

            /**
             * Database service alias
             */
            'database' => \DI\get(DatabaseService::class),

            /**
             * PDO connection (for direct access if needed)
             */
            'database.table.registry' => function (\DI\Container $container) {
                // Return the PluginTableRegistry owned by PluginManager.
                // This is the authoritative registry populated during plugin
                // discovery — all permission lookups must use this instance.
                $pluginManager = $container->get('plugin.manager');
                return $pluginManager->getTableRegistry();
            },

            \PDO::class => function (\DI\Container $container) {
                return $container->get(DatabaseService::class)->getPdo();
            },

            /**
             * Database connection factory
             */
            'database.connection_factory' => function (\DI\Container $container) {
                return function (?array $config = null) use ($container) {
                    $dbConfig = $config ?? $container->get('database.config');
                    $logger = $container->has(LoggerInterface::class) 
                        ? $container->get(LoggerInterface::class) 
                        : null;

                    return new DatabaseService($dbConfig, $logger, $container);
                };
            },

            /**
             * Database query logger
             */
            'database.query_logger' => function (\DI\Container $container) {
                return function (string $query, array $params = [], ?float $duration = null) use ($container) {
                    if ($container->has(LoggerInterface::class)) {
                        $logger = $container->get(LoggerInterface::class);
                        
                        $context = [
                            'query' => $query,
                            'params' => $params,
                            'duration' => $duration ? round($duration * 1000, 2) . 'ms' : null
                        ];

                        if ($duration !== null) {
                            $logger->debug('Database query executed', $context);
                        } else {
                            $logger->debug('Database query', $context);
                        }
                    }
                };
            },

            /**
             * Database transaction manager
             */
            'database.transaction_manager' => function (\DI\Container $container) {
                return new class($container) {
                    private \DI\Container $container;
                    private array $transactions = [];

                    public function __construct(\DI\Container $container)
                    {
                        $this->container = $container;
                    }

                    public function beginTransaction(): bool
                    {
                        $database = $this->container->get(DatabaseService::class);
                        $result = $database->beginTransaction();
                        
                        if ($result) {
                            $this->transactions[] = true;
                        }

                        return $result;
                    }

                    public function commit(): bool
                    {
                        if (empty($this->transactions)) {
                            return false;
                        }

                        $database = $this->container->get(DatabaseService::class);
                        $result = $database->commit();
                        
                        if ($result) {
                            array_pop($this->transactions);
                        }

                        return $result;
                    }

                    public function rollback(): bool
                    {
                        if (empty($this->transactions)) {
                            return false;
                        }

                        $database = $this->container->get(DatabaseService::class);
                        $result = $database->rollback();
                        
                        if ($result) {
                            array_pop($this->transactions);
                        }

                        return $result;
                    }

                    public function inTransaction(): bool
                    {
                        $database = $this->container->get(DatabaseService::class);
                        return $database->inTransaction();
                    }

                    public function transaction(callable $callback): mixed
                    {
                        $database = $this->container->get(DatabaseService::class);
                        return $database->transaction($callback);
                    }
                };
            },

            /**
             * Database query builder factory
             */
            'database.query_builder' => function (\DI\Container $container) {
                return new class($container) {
                    private \DI\Container $container;

                    public function __construct(\DI\Container $container)
                    {
                        $this->container = $container;
                    }

                    public function table(string $table): QueryBuilderHelper
                    {
                        return new QueryBuilderHelper($table, $this->container->get(DatabaseService::class));
                    }
                };
            },

            /**
             * Database health checker
             */
            'database.health_checker' => function (\DI\Container $container) {
                return new class($container) {
                    private \DI\Container $container;

                    public function __construct(\DI\Container $container)
                    {
                        $this->container = $container;
                    }

                    public function check(): array
                    {
                        try {
                            $database = $this->container->get(DatabaseService::class);
                            $result = $database->fetch("SELECT 1 as health_check");
                            
                            return [
                                'status' => 'healthy',
                                'connection' => 'established',
                                'database' => $database->getDatabaseName(),
                                'timestamp' => date('Y-m-d H:i:s')
                            ];
                        } catch (\Exception $e) {
                            return [
                                'status' => 'unhealthy',
                                'connection' => 'failed',
                                'error' => $e->getMessage(),
                                'timestamp' => date('Y-m-d H:i:s')
                            ];
                        }
                    }

                    public function isHealthy(): bool
                    {
                        $check = $this->check();
                        return $check['status'] === 'healthy';
                    }
                };
            },
        ]);
    }

    /**
     * Get environment provider
     */
    public function getEnvProvider(): EnvServiceProvider
    {
        return $this->envProvider;
    }

    /**
     * Get database service name
     */
    public function getServiceName(): string
    {
        return 'database';
    }

    /**
     * Get database interface service name
     */
    public function getInterfaceServiceName(): string
    {
        return DatabaseService::class;
    }
}

