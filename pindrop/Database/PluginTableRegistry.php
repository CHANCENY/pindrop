<?php

declare(strict_types=1);

namespace Simp\Pindrop\Database;

use Symfony\Component\Yaml\Yaml;

/**
 * PluginTableRegistry
 *
 * Tracks which database tables belong to which plugin.
 * Built at boot time by scanning each plugin's mysql/*.sql files.
 *
 * Purpose:
 *   - Enforces plugin isolation: plugin A cannot access plugin B's tables
 *     directly through DatabaseService (must use plugin B's public API).
 *   - Powers the db.permissions.yml ownership validation.
 *   - When a plugin is removed its tables are deregistered — other plugins
 *     that never registered those tables are unaffected.
 *
 * Core framework tables (users, sessions, etc.) are registered under
 * the reserved owner 'core'.
 */
class PluginTableRegistry
{
    /** ['table_name' => 'plugin_id'] */
    private array $tableOwners = [];

    /** ['plugin_id' => ['table1', 'table2', ...]] */
    private array $pluginTables = [];

    /** ['plugin_id' => ['permission_key' => ['table' => [operations]]]] */
    private array $dbPermissions = [];

    /** Core framework tables always owned by 'core' */
    private const CORE_TABLES = [
        'users', 'user_session', 'php_sessions', 'site_settings',
        'system_information', 'user_verification_tokens',
        'nodes', 'node_data', 'file_managed', 'logs',
        'logs', 'theme_library_assets','general_permissions'
    ];

    public function __construct()
    {
        foreach (self::CORE_TABLES as $table) {
            $this->tableOwners[$table]  = 'core';
            $this->pluginTables['core'][] = $table;
        }
    }

    // ── Registration ─────────────────────────────────────────────────────────

    /**
     * Register all tables defined in a plugin's mysql/*.sql files.
     * Called by PluginManager when it discovers an enabled plugin.
     */
    public function registerPluginTables(string $pluginId, string $pluginPath): void
    {
       
        $mysqlDir = $pluginPath . '/mysql';
        if (!is_dir($mysqlDir)) {
            return;
        }

        foreach (glob($mysqlDir . '/*.sql') ?: [] as $sqlFile) {
            $sql    = file_get_contents($sqlFile);
            $tables = $this->extractTableNamesFromSql($sql);
            foreach ($tables as $table) {
                // First plugin to register a table owns it.
                // Duplicate registration (e.g. two plugins defining the same
                // table name) throws so the developer catches it early.
                if (isset($this->tableOwners[$table]) && $this->tableOwners[$table] !== $pluginId) {
                    throw new \RuntimeException(
                        "Table '$table' is already registered by plugin '{$this->tableOwners[$table]}'. "
                        . "Plugin '$pluginId' cannot claim the same table. "
                        . "Rename the table or remove the duplicate schema."
                    );
                }
                $this->tableOwners[$table]   = $pluginId;
                $this->pluginTables[$pluginId][] = $table;
            }
        }
        
    }

    /**
     * Load and store a plugin's db.permissions.yml.
     * Called by PluginManager alongside user.permissions.yml loading.
     */
    public function registerDbPermissions(string $pluginId, string $pluginPath): void
    {
        $permFile = $pluginPath . '/db.permissions.yml';
        if (!file_exists($permFile)) {
            return;
        }

        $perms = Yaml::parseFile($permFile);
        if (!is_array($perms)) {
            return;
        }

        foreach ($perms as $permKey => $permDef) {
            $ownedBy = $permDef['owned_by'] ?? $pluginId;
            $tables  = $permDef['tables']   ?? [];

            // Validate: tables in db.permissions must be owned by this plugin
            foreach (array_keys($tables) as $table) {
                $actualOwner = $this->tableOwners[$table] ?? null;
                if ($actualOwner !== null && $actualOwner !== $ownedBy && $actualOwner !== 'core') {
                    throw new \RuntimeException(
                        "db.permissions.yml in plugin '$pluginId' references table '$table' "
                        . "which is owned by '$actualOwner'. "
                        . "Plugins may only define db permissions for their own tables."
                    );
                }
            }

            if (!isset($this->dbPermissions[$pluginId])) {
                $this->dbPermissions[$pluginId] = [];
            }
            $this->dbPermissions[$pluginId][$permKey] = [
                'owned_by'    => $ownedBy,
                'tables'      => $tables,
                'description' => $permDef['description'] ?? '',
            ];
        }
    }

    /**
     * Deregister all tables and permissions for a plugin.
     * Called when a plugin is uninstalled or disabled.
     */
    public function deregisterPlugin(string $pluginId): void
    {
        foreach ($this->pluginTables[$pluginId] ?? [] as $table) {
            unset($this->tableOwners[$table]);
        }
        unset($this->pluginTables[$pluginId]);
        unset($this->dbPermissions[$pluginId]);
    }

    // ── Lookups ──────────────────────────────────────────────────────────────

    /** Which plugin owns this table? Returns null for unregistered tables. */
    public function getTableOwner(string $table): ?string
    {
        return $this->tableOwners[$table] ?? null;
    }

    /** Does this table belong to this plugin (or 'core')? */
    public function tableOwnedBy(string $table, string $pluginId): bool
    {
        $owner = $this->tableOwners[$table] ?? null;
        return $owner === $pluginId || $owner === 'core';
    }

    /** All tables registered to a plugin. */
    public function getPluginTables(string $pluginId): array
    {
        return $this->pluginTables[$pluginId] ?? [];
    }

    /**
     * Resolve which operations a role may perform on a given table,
     * by scanning all loaded db.permissions entries that the role holds.
     *
     * $rolePermissionKeys — the permission keys held by the role
     *                       (from user.permissions.yml for that role).
     *
     * Returns the allowed operations, e.g. ['select', 'insert']
     * or ['*'] for super_admin.
     */
    public function resolveAllowedOperations(string $table, array $rolePermissionKeys): array
    {
        $allowed = [];

        foreach ($this->dbPermissions as $pluginId => $permissions) {
            foreach ($permissions as $permKey => $permDef) {
                // Does the role hold this permission key?
                if (!in_array($permKey, $rolePermissionKeys, true)) {
                    continue;
                }
                // Does this permission grant access to this table?
                $tableDef = $permDef['tables'][$table] ?? null;
                if ($tableDef !== null) {
                    foreach ($tableDef as $op) {
                        $allowed[] = strtolower($op);
                    }
                }
            }
        }

        return array_unique($allowed);
    }

    /**
     * Get the full merged db.permissions map across all plugins.
     * Used by the admin UI to display db permission definitions.
     */
    public function getAllDbPermissions(): array
    {
        return $this->dbPermissions;
    }

    /**
     * Get db.permissions for a specific plugin.
     */
    public function getPluginDbPermissions(string $pluginId): array
    {
        return $this->dbPermissions[$pluginId] ?? [];
    }

    /**
     * Full owner map — useful for diagnostics / admin UI.
     * ['table_name' => 'plugin_id']
     */
    public function getAllTableOwners(): array
    {
        return $this->tableOwners;
    }

    // ── SQL parsing ──────────────────────────────────────────────────────────

    /**
     * Extract CREATE TABLE names from a SQL file.
     * Handles both:
     *   CREATE TABLE `name` (...)
     *   CREATE TABLE IF NOT EXISTS `name` (...)
     */
    private function extractTableNamesFromSql(string $sql): array
    {
        $tables = [];
        // Strip comments
        $sql = preg_replace('/--[^\n]*\n/', "\n", $sql);
        $sql = preg_replace('/\/\*.*?\*\//s', '', $sql);

        preg_match_all(
            '/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?[`"]?(\w+)[`"]?\s*\(/i',
            $sql,
            $matches
        );

        foreach ($matches[1] ?? [] as $name) {
            $tables[] = strtolower($name);
        }

        return array_unique($tables);
    }
}
