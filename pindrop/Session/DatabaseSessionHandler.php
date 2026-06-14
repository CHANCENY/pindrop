<?php

declare(strict_types=1);

namespace Simp\Pindrop\Session;

use PDO;
use PDOException;
use SessionHandlerInterface;

/**
 * DatabaseSessionHandler
 *
 * Stores PHP $_SESSION data in MySQL so session state is shared
 * across every app server behind the load balancer — no more
 * per-server file locks, no sticky sessions required.
 *
 * This class is intentionally self-contained: it builds its own PDO
 * connection from environment variables because it must run BEFORE
 * the DI container is constructed in bootstrap.inc.
 *
 * Table: php_sessions  (see pindrop/Mysql/schema/php_sessions.sql)
 *
 * Usage — in bootstrap.inc, replace `@session_start()` with:
 *
 *   $handler = \Simp\Pindrop\Session\DatabaseSessionHandler::createFromEnv();
 *   session_set_save_handler($handler, true);
 *   session_start();
 */
class DatabaseSessionHandler implements SessionHandlerInterface
{
    private PDO $pdo;
    private int $lifetime;

    /**
     * @param PDO $pdo      A live PDO connection to the sessions database.
     * @param int $lifetime Session lifetime in seconds (default: php.ini session.gc_maxlifetime).
     */
    public function __construct(PDO $pdo, int $lifetime = 1440)
    {
        $this->pdo      = $pdo;
        $this->lifetime = $lifetime;
    }

    // -------------------------------------------------------------------------
    // Factory
    // -------------------------------------------------------------------------

    /**
     * Build a handler from .env / putenv variables.
     *
     * Called early in bootstrap.inc before the DI container exists, so we read
     * the same env vars that DatabaseServiceProvider later uses.
     *
     * @throws \RuntimeException when DB credentials are missing.
     */
    public static function createFromEnv(): self
    {
        $host     = self::env('DB_HOST',     'localhost');
        $port     = self::env('DB_PORT',     '3306');
        $dbname   = self::env('DB_DATABASE', '');
        $username = self::env('DB_USERNAME', '');
        $password = self::env('DB_PASSWORD', '');
        $charset  = self::env('DB_CHARSET',  'utf8mb4');

        if ($dbname === '' || $username === '') {
            throw new \RuntimeException(
                'DatabaseSessionHandler: DB_DATABASE and DB_USERNAME must be set in .env'
            );
        }

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => false,
        ]);

        $lifetime = (int)(ini_get('session.gc_maxlifetime') ?: 1440);

        return new self($pdo, $lifetime);
    }

    // -------------------------------------------------------------------------
    // SessionHandlerInterface
    // -------------------------------------------------------------------------

    /**
     * Called by PHP when session_start() opens a session.
     * Nothing to do — the PDO connection is already open.
     */
    public function open(string $savePath, string $sessionName): bool
    {
        // Auto-create the table on first use so the app works immediately
        // without needing AUTO_CREATE_TABLES=true or a manual migration step.
        $this->ensureTableExists();
        return true;
    }

    /**
     * Create the php_sessions table if it does not already exist.
     * Uses IF NOT EXISTS so this is a no-op on every subsequent request.
     */
    private function ensureTableExists(): void
    {
        
    }

    /**
     * Called by PHP when the session is closed at the end of the request.
     * We take the opportunity to probabilistically run garbage collection
     * (same 1 % chance as PHP's built-in gc_probability / gc_divisor).
     */
    public function close(): bool
    {
        if (random_int(1, 100) === 1) {
            $this->gc($this->lifetime);
        }
        return true;
    }

    /**
     * Read session data for the given session ID.
     * Returns an empty string when the session does not exist or has expired —
     * PHP then treats this as a fresh, empty session.
     */
    public function read(string $id): string|false
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT session_data
                   FROM php_sessions
                  WHERE session_id = :id
                    AND expires_at > :now
                  LIMIT 1'
            );
            $stmt->execute([':id' => $id, ':now' => time()]);
            $row = $stmt->fetch();

            return $row !== false ? (string)$row['session_data'] : '';

        } catch (PDOException $e) {
            // Return empty string so PHP can continue with a clean session
            error_log('DatabaseSessionHandler::read failed: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Write (upsert) session data.
     * Uses INSERT … ON DUPLICATE KEY UPDATE so concurrent writes on the same
     * session ID are safe — the last writer wins, which matches file-session
     * semantics.
     */
    public function write(string $id, string $data): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO php_sessions
                        (session_id, session_data, expires_at, updated_at)
                 VALUES (:id, :data, :expires, :nows)
                 ON DUPLICATE KEY UPDATE
                        session_data = VALUES(session_data),
                        expires_at   = VALUES(expires_at),
                        updated_at   = VALUES(updated_at)'
            );
           
           
            $result = $stmt->execute([
                ':id'      => $id,
                ':data'    => $data,
                ':expires' => strtotime( date('Y-m-d H:i:s', time() + $this->lifetime)),
                ':nows'     => date('Y-m-d H:i:s'),
            ]);
            return $result;

        } catch (PDOException $e) {
            // Log the real PDO error so it shows in PHP error log / Whoops
            // data tables rather than being silently swallowed.
            error_log('DatabaseSessionHandler::write PDO error: [' . $e->getCode() . '] ' . $e->getMessage());
            // Return true to prevent PHP from emitting E_WARNING which Whoops
            // converts to ErrorException — the session data is lost this cycle
            // but the app stays alive.  This is equivalent to file-session
            // behaviour when the disk is full.
            dd($e);
            return true;
        }
    }

    /**
     * Delete the session row when the user explicitly calls session_destroy().
     */
    public function destroy(string $id): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                'DELETE FROM php_sessions WHERE session_id = :id'
            );
            return $stmt->execute([':id' => $id]);

        } catch (PDOException $e) {
            error_log('DatabaseSessionHandler::destroy failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove all sessions whose expires_at is in the past.
     * PHP calls this automatically based on gc_probability / gc_divisor.
     * We also call it from close() with a 1 % probability.
     *
     * @return int|false Number of rows deleted, or false on failure.
     */
    public function gc(int $max_lifetime): int|false
    {
        try {
            $stmt = $this->pdo->prepare(
                'DELETE FROM php_sessions WHERE expires_at < :now'
            );
            $stmt->execute([':now' => time()]);
            return $stmt->rowCount();

        } catch (PDOException $e) {
            error_log('DatabaseSessionHandler::gc failed: ' . $e->getMessage());
            return false;
        }
    }

    // -------------------------------------------------------------------------
    // Extra helpers (not part of the interface, but useful in controllers)
    // -------------------------------------------------------------------------

    /**
     * Return how many active (non-expired) sessions exist in the DB.
     * Useful for admin dashboards.
     */
    public function countActiveSessions(): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM php_sessions WHERE expires_at > :now'
        );
        $stmt->execute([':now' => time()]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Destroy all sessions for a given user.
     * Call this when an admin bans a user or the user changes their password.
     *
     * NOTE: This works because write() stores the user ID alongside the data.
     * Pindrop writes user_id into $_SESSION when the user logs in via
     * auth_service::createSession(). We parse it out here using a
     * lightweight regex on the serialised session string — fragile but
     * dependency-free. If you store user_id in a dedicated column you can
     * replace this with a simple WHERE clause.
     */
    public function destroyUserSessions(int $userId): int
    {
        // Fetch all active session rows and destroy ones belonging to $userId
        $stmt = $this->pdo->prepare(
            'SELECT session_id, session_data FROM php_sessions WHERE expires_at > :now'
        );
        $stmt->execute([':now' => time()]);
        $rows = $stmt->fetchAll();

        $destroyed = 0;
        foreach ($rows as $row) {
            // PHP session data is serialised; user_id is stored as an integer
            if (preg_match('/user_id[^:]*:(\d+)/', $row['session_data'], $m)
                && (int)$m[1] === $userId) {
                $this->destroy($row['session_id']);
                $destroyed++;
            }
        }
        return $destroyed;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Read an environment variable, trying putenv() values first, then $_ENV.
     */
    private static function env(string $key, string $default = ''): string
    {
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }
        return $_ENV[$key] ?? $default;
    }
}
