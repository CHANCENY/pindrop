<?php

declare(strict_types=1);

namespace Simp\Pindrop\Database;

use DI\Container;
use Simp\Pindrop\Logger\LoggerInterface;
use Simp\Pindrop\Logger\NullLogger;

/**
 * DatabaseService
 *
 * The ONLY database entry point for plugin developers.
 * No raw SQL is accepted — all queries go through QueryBuilder.
 *
 * Developer flow:
 *   $db->table('wiki_pages')
 *      ->where('status', '=', 'published')
 *      ->orderBy('created_at', 'DESC')
 *      ->limit(10)
 *      ->get();
 *
 * Permission chain:
 *   table() -> QueryBuilder -> terminal method
 *           -> DatabasePermissionGuard::authorize(op, table)
 *           -> PluginTableRegistry resolves allowed ops via db.permissions.yml
 *           -> Database executes prepared statement
 */
class DatabaseService
{
    private Database $database;
    private LoggerInterface $logger;
    private ?Container $container;
    private array $config;
    private ?DatabasePermissionGuard $guard;
    private ?PluginTableRegistry $registry;

    public function __construct(
        array $config,
        ?LoggerInterface $logger = null,
        ?Container $container = null,
        ?DatabasePermissionGuard $guard = null,
        ?PluginTableRegistry $registry = null
    ) {
        $this->config    = $config;
        $this->logger    = $logger   ?? new NullLogger();
        $this->container = $container;
        $this->guard     = $guard;
        $this->registry  = $registry;
        $this->initializeDatabase();
    }

    // ── Public developer API ─────────────────────────────────────────────────

    /**
     * Entry point for all queries. Returns a QueryBuilder for the given table.
     * Permission check fires on the terminal method (get/insert/update/delete).
     */
    public function table(string $table, string $pluginContext = ''): QueryBuilder
    {
        if ($this->guard === null) {
            throw new DatabaseException(
                'DatabasePermissionGuard is not configured. '
                . 'Inject a guard instance into DatabaseService.'
            );
        }
       
        $this->logger->debug('QueryBuilder created', [
            'table'   => $table,
            'context' => $pluginContext,
        ]);
        return new QueryBuilder($table, $this->database, $this->guard, $pluginContext);
    }

    /**
     * Execute a callable inside a transaction.
     * Rolls back automatically on any exception.
     */
    public function transaction(callable $callback): mixed
    {
        $this->database->beginTransaction();
        try {
            $result = $callback();
            $this->database->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->database->rollback();
            throw $e;
        }
    }

    public function beginTransaction(): bool  { return $this->database->beginTransaction(); }
    public function commit(): bool            { return $this->database->commit(); }
    public function rollback(): bool          { return $this->database->rollback(); }

    // ── Schema / meta (no guard — internal) ─────────────────────────────────

    public function tableExists(string $table): bool        { return $this->database->tableExists($table); }
    public function getTableColumns(string $table): array   { return $this->database->getTableColumns($table); }
    public function lastInsertId(): int                     { return $this->database->lastInsertId(); }

    // ── Registry helpers ─────────────────────────────────────────────────────

    public function getTableOwner(string $table): ?string
    {
        return $this->registry?->getTableOwner($table);
    }

    public function getPluginTables(string $pluginId): array
    {
        return $this->registry?->getPluginTables($pluginId) ?? [];
    }

    public function getAllTableOwners(): array
    {
        return $this->registry?->getAllTableOwners() ?? [];
    }

    // ── Guard / registry setters ─────────────────────────────────────────────

    public function setPermissionGuard(DatabasePermissionGuard $guard): void  { $this->guard    = $guard; }
    public function setTableRegistry(PluginTableRegistry $registry): void     { $this->registry = $registry; }

    /**
     * Disable guard for the duration of a callable (migrations/CLI).
     * Always restored — even if the callable throws.
     */
    public function withoutPermissions(callable $callback): mixed
    {
        $previous    = $this->guard;
        $this->guard = null;
        DatabasePermissionGuard::bypass(true);
        try {
            return $callback();
        } finally {
            $this->guard = $previous;
            DatabasePermissionGuard::bypass(false);
        }
    }

    // ── Raw execution (framework-internal ONLY) ──────────────────────────────

    /** DDL execution for SchemaHandler/installer. Never expose to plugin code. @internal */
    public function execRaw(string $sql): int
    {
        DatabasePermissionGuard::bypass(true);
        try {
            return $this->database->exec($sql);
        } finally {
            DatabasePermissionGuard::bypass(false);
        }
    }

    /** Raw SELECT for SchemaHandler/migrations. @internal */
    public function queryRaw(string $sql, mixed ...$params): \PDOStatement|bool
    {
        DatabasePermissionGuard::bypass(true);
        try {
            return $this->database->query($sql, ...$params);
        } finally {
            DatabasePermissionGuard::bypass(false);
        }
    }

    // ── Connection helpers ───────────────────────────────────────────────────

    public function isConnected(): bool     { return $this->database->isConnected(); }
    public function connect(): bool         { return $this->database->connect(); }
    public function disconnect(): void      { $this->database->disconnect(); }
    public function getConnectionInfo(): array { return $this->database->getConnectionInfo(); }
    public function getConfig(): array      { return $this->database->getConfig(); }

    /** @internal — SchemaHandler / migrations only */
    public function getPdo(): \PDO          { return $this->database->getPdo(); }

    /** @internal — SchemaHandler / migrations only */
    public function getDatabase(): Database { return $this->database; }

    public function testConnection(): bool
    {
        DatabasePermissionGuard::bypass(true);
        try {
            $this->database->query('SELECT 1');
            return true;
        } catch (\Throwable) {
            return false;
        } finally {
            DatabasePermissionGuard::bypass(false);
        }
    }

    public function getStatistics(): array
    {
        return [
            'connected'           => $this->isConnected(),
            'connection_info'     => $this->getConnectionInfo(),
            'guard_active'        => $this->guard !== null,
            'registry_table_count'=> count($this->registry?->getAllTableOwners() ?? []),
        ];
    }

    private function initializeDatabase(): void
    {
        try {
            $this->database = new Database($this->config);
            $this->logger->info('Database service initialised', [
                'host'     => $this->config['host']     ?? 'localhost',
                'database' => $this->config['database'] ?? 'unknown',
            ]);
        } catch (DatabaseException $e) {
            $this->logger->error('Failed to initialise database service', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}
