<?php

declare(strict_types=1);

namespace Simp\Pindrop\Mail;

use Simp\Pindrop\Database\DatabasePermissionGuard;
use Simp\Pindrop\Database\DatabaseService;
use Simp\Pindrop\Database\QueryBuilder;

/**
 * Stores mails in the database so development setups can keep (or only keep)
 * a copy of outgoing mail instead of using a real SMTP server.
 *
 * Follows the framework's database rules: all reads and writes go through
 * DatabaseService::table() (QueryBuilder, prepared statements, no raw SQL).
 * Only the one-off CREATE TABLE uses execRaw(), which is the framework's
 * DDL entry point.
 *
 * local_mails is a framework table, not a plugin table, so it has no entry in
 * the plugin permission registry. The guard is therefore bypassed for these
 * calls (see run()), exactly as DatabaseService does for its own internal work.
 */
class LocalMailServer
{
    private const TABLE = 'local_mails';
    private const CONTEXT = 'mail';

    private bool $tableReady = false;
    private string $lastError = '';

    public function __construct(protected readonly DatabaseService $database)
    {
    }

    public function save(LocalMail $mail): bool
    {
        try {
            $createdAt = date('Y-m-d H:i:s');

            $id = $this->run(fn(): int => $this->query()->insert([
                'subject' => $mail->subject,
                'body' => $mail->body,
                'to_address' => $mail->to,
                'reply_to' => $mail->reply_to,
                'cc' => json_encode($mail->cc, JSON_THROW_ON_ERROR),
                'bcc' => json_encode($mail->bcc, JSON_THROW_ON_ERROR),
                'attachments' => json_encode($mail->attachments, JSON_THROW_ON_ERROR),
                'is_html' => $mail->is_html ? 1 : 0,
                'created_at' => $createdAt,
            ]));

            $mail->id = $id;
            $mail->created_at = $createdAt;
            $this->lastError = '';

            return true;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * Get mails saved, newest first.
     *
     * @param int $limit
     * @param int $offset
     * @return LocalMail[]
     */
    public function getMails(int $limit = 10, int $offset = 0): array
    {
        try {
            $rows = $this->run(fn(): array => $this->query()
                ->orderBy('id', 'DESC')
                ->limit(max(1, $limit))
                ->offset(max(0, $offset))
                ->get());

            return array_map(fn(array $row): LocalMail => $this->hydrate($row), $rows);
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return [];
        }
    }

    /**
     * Total number of saved mails (for pagination).
     */
    public function countMails(): int
    {
        try {
            return $this->run(fn(): int => $this->query()->count());
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return 0;
        }
    }

    public function getMail(int $id): LocalMail|null
    {
        try {
            $row = $this->run(fn(): ?array => $this->query()
                ->where('id', '=', $id)
                ->first());

            return $row === null ? null : $this->hydrate($row);
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return null;
        }
    }

    public function delete(int $id): bool
    {
        try {
            return $this->run(fn(): int => $this->query()
                ->where('id', '=', $id)
                ->delete()) > 0;
        } catch (\Throwable $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    public function getLastError(): string
    {
        return $this->lastError;
    }

    /**
     * A fresh QueryBuilder per call (the builder mutates itself, so it is
     * never reused between operations).
     */
    private function query(): QueryBuilder
    {
        return $this->database->table(self::TABLE, self::CONTEXT);
    }

    /**
     * Run a database operation on the framework-owned table:
     * make sure the table exists and bypass the plugin permission guard.
     */
    private function run(callable $operation): mixed
    {
        $this->ensureTable();

        DatabasePermissionGuard::bypass(true);
        try {
            return $operation();
        } finally {
            DatabasePermissionGuard::bypass(false);
        }
    }

    private function hydrate(array $row): LocalMail
    {
        return LocalMail::fromArray([
            'id' => $row['id'],
            'subject' => $row['subject'],
            'body' => $row['body'],
            'to' => $row['to_address'],
            'reply_to' => $row['reply_to'],
            'cc' => json_decode((string) $row['cc'], true) ?: [],
            'bcc' => json_decode((string) $row['bcc'], true) ?: [],
            'attachments' => json_decode((string) $row['attachments'], true) ?: [],
            'is_html' => (bool) $row['is_html'],
            'created_at' => $row['created_at'],
        ]);
    }

    /**
     * Create the table on first use (DDL is the one thing that needs execRaw()).
     * Matches the utf8mb4 / InnoDB setup used by Database.
     */
    private function ensureTable(): void
    {
        if ($this->tableReady) {
            return;
        }

        if (!$this->database->tableExists(self::TABLE)) {
            $this->database->execRaw(
                'CREATE TABLE IF NOT EXISTS `' . self::TABLE . '` (
                    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `subject` VARCHAR(998) NOT NULL DEFAULT \'\',
                    `body` MEDIUMTEXT NOT NULL,
                    `to_address` VARCHAR(320) NOT NULL DEFAULT \'\',
                    `reply_to` VARCHAR(320) NOT NULL DEFAULT \'\',
                    `cc` TEXT NOT NULL,
                    `bcc` TEXT NOT NULL,
                    `attachments` TEXT NOT NULL,
                    `is_html` TINYINT(1) NOT NULL DEFAULT 0,
                    `created_at` DATETIME NOT NULL,
                    PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
            );
        }

        $this->tableReady = true;
    }
}
