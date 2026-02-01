<?php

declare(strict_types=1);

namespace Simp\Pindrop\Services;

use DI\ContainerBuilder;
use Simp\Pindrop\Database\DatabaseService;
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
                return [
                    'host' => $this->envProvider->get('DB_HOST', 'localhost'),
                    'port' => $this->envProvider->get('DB_PORT', 3306),
                    'database' => $this->envProvider->get('DB_DATABASE', 'pindrop'),
                    'username' => $this->envProvider->get('DB_USERNAME', 'root'),
                    'password' => $this->envProvider->get('DB_PASSWORD', ''),
                    'charset' => $this->envProvider->get('DB_CHARSET', 'utf8mb4'),
                    'collation' => $this->envProvider->get('DB_COLLATION', 'utf8mb4_unicode_ci'),
                    'prefix' => $this->envProvider->get('DB_PREFIX', ''),
                    'strict' => $this->envProvider->get('DB_STRICT', true),
                    'engine' => $this->envProvider->get('DB_ENGINE', 'InnoDB'),
                    'options' => [
                        \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                        \PDO::ATTR_EMULATE_PREPARES => false,
                        \PDO::ATTR_PERSISTENT => $this->envProvider->get('DB_PERSISTENT', false),
                        \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                        \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
                        \PDO::ATTR_TIMEOUT => $this->envProvider->get('DB_TIMEOUT', 30),
                    ]
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

                return new DatabaseService($config, $logger, $container);
            },

            /**
             * Database service alias
             */
            'database' => \DI\get(DatabaseService::class),

            /**
             * PDO connection (for direct access if needed)
             */
            \PDO::class => function (\DI\Container $container) {
                $database = $container->get(DatabaseService::class);
                return $database->getConnection();
            },

            /**
             * Database connection factory
             */
            'database.connection_factory' => function (\DI\Container $container) {
                return function (array $config = null) use ($container) {
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
                return function (string $query, array $params = [], float $duration = null) use ($container) {
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

/**
 * Simple Query Builder Helper
 */
class QueryBuilderHelper
{
    private string $table;
    private DatabaseService $database;
    private array $wheres = [];
    private array $orders = [];
    private ?int $limit = null;
    private ?int $offset = null;

    public function __construct(string $table, DatabaseService $database)
    {
        $this->table = $table;
        $this->database = $database;
    }

    public function where(string $column, string $operator, mixed $value): self
    {
        $this->wheres[] = [$column, $operator, $value];
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orders[] = [$column, $direction];
        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    public function get(): array
    {
        $sql = $this->buildQuery();
        $params = $this->buildParams();
        
        return $this->database->fetchAll($sql, ...$params);
    }

    public function first(): ?array
    {
        $sql = $this->buildQuery();
        $params = $this->buildParams();
        
        return $this->database->fetch($sql, ...$params);
    }

    public function count(): int
    {
        $sql = "SELECT COUNT(*) as count FROM {$this->table}";
        $params = [];
        
        if (!empty($this->wheres)) {
            $sql .= " WHERE " . implode(' AND ', array_fill(0, count($this->wheres), '?'));
            foreach ($this->wheres as [$column, $operator, $value]) {
                $params[] = $value;
            }
        }
        
        $result = $this->database->fetch($sql, ...$params);
        return $result['count'] ?? 0;
    }

    public function insert(array $data): int
    {
        return $this->database->insert($this->table, $data);
    }

    public function update(array $data): int
    {
        $where = $this->buildWhereClause();
        $params = array_merge(array_values($data), $this->buildWhereParams());
        
        return $this->database->update($this->table, $data, $where, ...$params);
    }

    public function delete(): int
    {
        $where = $this->buildWhereClause();
        $params = $this->buildWhereParams();
        
        $sql = "DELETE FROM {$this->table} WHERE $where";
        $stmt = $this->database->query($sql, ...$params);
        
        return $stmt instanceof \PDOStatement ? $stmt->rowCount() : 0;
    }

    private function buildQuery(): string
    {
        $sql = "SELECT * FROM {$this->table}";
        
        if (!empty($this->wheres)) {
            $sql .= " WHERE " . implode(' AND ', array_fill(0, count($this->wheres), '?'));
        }
        
        if (!empty($this->orders)) {
            $orderClauses = array_map(function($order) {
                list($col, $dir) = $order;
                return "$col $dir";
            }, $this->orders);
            $sql .= " ORDER BY " . implode(', ', $orderClauses);
        }
        
        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
        }
        
        if ($this->offset !== null) {
            $sql .= " OFFSET {$this->offset}";
        }
        
        return $sql;
    }

    private function buildParams(): array
    {
        return $this->buildWhereParams();
    }

    private function buildWhereClause(): string
    {
        if (empty($this->wheres)) {
            return '1=1';
        }
        
        return implode(' AND ', array_fill(0, count($this->wheres), '?'));
    }

    private function buildWhereParams(): array
    {
        $params = [];
        foreach ($this->wheres as $where) {
            $params[] = $where[2];
        }
        return $params;
    }
}
