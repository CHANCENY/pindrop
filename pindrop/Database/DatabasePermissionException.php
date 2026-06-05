<?php

declare(strict_types=1);

namespace Simp\Pindrop\Database;

/**
 * Thrown when a database operation is denied by DatabasePermissionGuard.
 *
 * Controllers should catch this and return a 403 response.
 * It is a RuntimeException so uncaught cases bubble to the global
 * error handler and produce a proper error page rather than a PHP fatal.
 */
class DatabasePermissionException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly string $operation,
        private readonly string $table,
        private readonly ?string $requiredPermission = null,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, 403, $previous);
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function getRequiredPermission(): ?string
    {
        return $this->requiredPermission;
    }
}
