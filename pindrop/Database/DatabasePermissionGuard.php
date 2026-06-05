<?php

declare(strict_types=1);

namespace Simp\Pindrop\Database;

use Simp\Pindrop\Entity\User\User;

/**
 * DatabasePermissionGuard
 *
 * The single authority for deciding whether the current user may perform
 * an operation on a table.  Called by QueryBuilder before every terminal
 * method (get, insert, update, delete, …).
 *
 * Decision chain for authorize(operation, table):
 *
 *  1. Global bypass active?               → allow  (migrations, CLI)
 *  2. No current user (system context)?   → allow  (boot, cron)
 *  3. super_admin?                        → allow  (always)
 *  4. Core system table?                  → only super_admin / system (already handled above)
 *  5. Table owned by a plugin?
 *       a. Resolve role's permission keys (from user.permissions.yml)
 *       b. Resolve allowed operations via db.permissions.yml entries
 *       c. Operation in allowed list?     → allow
 *       d. Not in list?                   → DatabasePermissionException (403)
 *  6. Table unknown (not registered)?     → deny by default
 */
class DatabasePermissionGuard
{
    private static bool $bypassed = false;

    /** Per-instance table bypass for internal framework operations */
    private array $tableBypass = [];

    public function __construct(
        private readonly CurrentUserResolver  $userResolver,
        private readonly PluginTableRegistry  $registry
    ) {}

    // ── Public API ───────────────────────────────────────────────────────────

    /**
     * Authorise an operation on a table.
     * Throws DatabasePermissionException on denial.
     */
    public function authorize(string $operation, string $table): void
    {
        if (self::$bypassed || isset($this->tableBypass[$table])) {
            return;
        }

        $op   = strtolower($operation);
        $user = $this->userResolver->getCurrentUser();
        // System context (CLI, migrations, boot) — no user
        if ($user === null) {
            return;
        }

        $role = method_exists($user, 'getRole') ? ($user->getRole() ?? 'anonymous') : 'anonymous';

        // super_admin bypasses everything
        if ($role === 'super_admin') {
            return;
        }

        // Core tables: only super_admin may write, admin may read
        $owner = $this->registry->getTableOwner($table);
        if ($owner === 'core') {
            $this->authorizeCoreTable($op, $table, $role);
            return;
        }

        // Unknown table — deny
        if ($owner === null) {
            throw new DatabasePermissionException(
                "Table '$table' is not registered to any plugin. Access denied.",
                $op, $table
            );
        }

        // Plugin-owned table — resolve via db.permissions.yml
        $rolePermKeys = $this->getRolePermissionKeys($user);
        $allowed      = $this->registry->resolveAllowedOperations($table, $rolePermKeys);

        if (empty($allowed)) {
            throw new DatabasePermissionException(
                "Role '$role' has no database permissions for table '$table'.",
                $op, $table
            );
        }

        if (!in_array($op, $allowed, true)) {
            throw new DatabasePermissionException(
                "Role '$role' cannot perform '$op' on table '$table'. "
                . "Allowed: [" . implode(', ', $allowed) . "].",
                $op, $table
            );
        }
    }

    /** Non-throwing version. */
    public function can(string $operation, string $table): bool
    {
        try {
            $this->authorize($operation, $table);
            return true;
        } catch (DatabasePermissionException) {
            return false;
        }
    }

    /** Global bypass — for migrations, CLI, SchemaHandler. */
    public static function bypass(bool $state): void
    {
        self::$bypassed = $state;
    }

    /** Per-table bypass — for internal framework writes (sessions, auth). */
    public function bypassTable(string $table, bool $state): void
    {
        if ($state) {
            $this->tableBypass[$table] = true;
        } else {
            unset($this->tableBypass[$table]);
        }
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function authorizeCoreTable(string $op, string $table, string $role): void
    {
        // admin may SELECT core tables (e.g. user list in admin UI)
        if ($role === 'admin' && $op === 'select') {
            return;
        }

        $userPermissions = $this->getRolePermissionKeys($this->userResolver->getCurrentUser());
        dump($op, $table, $role, $userPermissions);
        throw new DatabasePermissionException(
            "Role '$role' cannot perform '$op' on core table '$table'. "
            . "Only super_admin or system context may access core tables.",
            $op, $table
        );
    }

    /**
     * Get the permission keys held by the current user's role.
     * Returns flat array of permission key strings, e.g.
     *   ['db.wiki.read', 'db.wiki.write', 'can_create_wiki', ...]
     */
    private function getRolePermissionKeys(User $user): array
    {
        if (!method_exists($user, 'getPermissions')) {
            return [];
        }
        $perms = $user->getPermissions();
        // getPermissions() may return ['*'] for super_admin — handled above
        return is_array($perms) ? $perms : [];
    }
}
