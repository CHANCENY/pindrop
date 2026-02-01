<?php

declare(strict_types=1);

namespace Simp\Pindrop\Database;

use Exception;
use Throwable;

/**
 * Database Exception Class
 * 
 * Custom exception for database-related errors in the pindrop database system.
 * Provides additional context for database operations and error handling.
 */
class DatabaseException extends Exception
{
    /**
     * Previous exception that caused this exception
     */
    private ?Throwable $previousException = null;
    
    /**
     * Database operation that failed
     */
    private ?string $operation = null;
    
    /**
     * SQL query that caused the error
     */
    private ?string $sql = null;
    
    /**
     * Query parameters
     */
    private ?array $parameters = null;
    
    /**
     * Create a new DatabaseException
     *
     * @param string $message Error message
     * @param int $code Error code
     * @param Throwable|null $previous Previous exception
     * @param string|null $operation Database operation that failed
     * @param string|null $sql SQL query that caused error
     * @param array|null $parameters Query parameters
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        ?string $operation = null,
        ?string $sql = null,
        ?array $parameters = null
    ) {
        parent::__construct($message, $code, $previous);
        
        $this->previousException = $previous;
        $this->operation = $operation;
        $this->sql = $sql;
        $this->parameters = $parameters;
    }
    
    /**
     * Get the database operation that failed
     */
    public function getOperation(): ?string
    {
        return $this->operation;
    }
    
    /**
     * Set the database operation that failed
     */
    public function setOperation(string $operation): void
    {
        $this->operation = $operation;
    }
    
    /**
     * Get the SQL query that caused the error
     */
    public function getSql(): ?string
    {
        return $this->sql;
    }
    
    /**
     * Set the SQL query that caused the error
     */
    public function setSql(string $sql): void
    {
        $this->sql = $sql;
    }
    
    /**
     * Get the query parameters
     */
    public function getParameters(): ?array
    {
        return $this->parameters;
    }
    
    /**
     * Set the query parameters
     */
    public function setParameters(array $parameters): void
    {
        $this->parameters = $parameters;
    }
    
    /**
     * Get the previous exception
     */
    public function getPreviousException(): ?Throwable
    {
        return $this->previousException;
    }
    
    /**
     * Check if this is a connection error
     */
    public function isConnectionError(): bool
    {
        return $this->operation === 'connect' || 
               $this->code === 2002 || 
               $this->code === 1045 ||
               str_contains($this->message, 'Connection') ||
               str_contains($this->message, 'Access denied');
    }
    
    /**
     * Check if this is a query error
     */
    public function isQueryError(): bool
    {
        return $this->operation === 'query' || 
               $this->code >= 1000 && $this->code < 2000;
    }
    
    /**
     * Check if this is a constraint violation
     */
    public function isConstraintViolation(): bool
    {
        return $this->code === 1216 || 
               $this->code === 1217 ||
               $this->code === 1451 ||
               $this->code === 1452 ||
               str_contains($this->message, 'constraint');
    }
    
    /**
     * Check if this is a duplicate entry error
     */
    public function isDuplicateEntry(): bool
    {
        return $this->code === 1062 || 
               $this->code === 1586 ||
               str_contains($this->message, 'Duplicate entry');
    }
    
    /**
     * Check if this is a foreign key constraint error
     */
    public function isForeignKeyConstraint(): bool
    {
        return $this->code === 1216 || 
               $this->code === 1217 ||
               $this->code === 1451 ||
               str_contains($this->message, 'foreign key constraint');
    }
    
    /**
     * Check if this is a table not found error
     */
    public function isTableNotFound(): bool
    {
        return $this->code === 1146 || 
               str_contains($this->message, "Table") && 
               str_contains($this->message, "doesn't exist");
    }
    
    /**
     * Check if this is a column not found error
     */
    public function isColumnNotFound(): bool
    {
        return $this->code === 1054 || 
               str_contains($this->message, "Column") && 
               str_contains($this->message, "doesn't exist");
    }
    
    /**
     * Get a user-friendly error message
     */
    public function getUserMessage(): string
    {
        if ($this->isConnectionError()) {
            return 'Database connection failed. Please check your database configuration.';
        }
        
        if ($this->isDuplicateEntry()) {
            return 'Duplicate entry. This record already exists.';
        }
        
        if ($this->isForeignKeyConstraint()) {
            return 'Cannot delete or update this record because it is referenced by other records.';
        }
        
        if ($this->isTableNotFound()) {
            return 'Database table not found.';
        }
        
        if ($this->isColumnNotFound()) {
            return 'Database column not found.';
        }
        
        if ($this->isConstraintViolation()) {
            return 'Database constraint violation.';
        }
        
        return 'Database operation failed.';
    }
    
    /**
     * Get detailed error information for debugging
     */
    public function getDebugInfo(): array
    {
        return [
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'operation' => $this->operation,
            'sql' => $this->sql,
            'parameters' => $this->parameters,
            'file' => $this->getFile(),
            'line' => $this->getLine(),
            'trace' => $this->getTrace(),
            'previous' => $this->previousException ? [
                'message' => $this->previousException->getMessage(),
                'code' => $this->previousException->getCode(),
                'file' => $this->previousException->getFile(),
                'line' => $this->previousException->getLine(),
            ] : null,
            'error_types' => [
                'connection_error' => $this->isConnectionError(),
                'query_error' => $this->isQueryError(),
                'constraint_violation' => $this->isConstraintViolation(),
                'duplicate_entry' => $this->isDuplicateEntry(),
                'foreign_key_constraint' => $this->isForeignKeyConstraint(),
                'table_not_found' => $this->isTableNotFound(),
                'column_not_found' => $this->isColumnNotFound(),
            ]
        ];
    }
    
    /**
     * Convert exception to string for logging
     */
    public function __toString(): string
    {
        $output = "DatabaseException: {$this->message}\n";
        $output .= "Code: {$this->code}\n";
        
        if ($this->operation) {
            $output .= "Operation: {$this->operation}\n";
        }
        
        if ($this->sql) {
            $output .= "SQL: {$this->sql}\n";
        }
        
        if ($this->parameters) {
            $output .= "Parameters: " . json_encode($this->parameters) . "\n";
        }
        
        if ($this->file) {
            $output .= "File: {$this->file}:{$this->line}\n";
        }
        
        return $output;
    }
    
    /**
     * Create a connection error exception
     */
    public static function connectionError(string $message, int $code = 0, ?Throwable $previous = null): self
    {
        return new self($message, $code, $previous, 'connect');
    }
    
    /**
     * Create a query error exception
     */
    public static function queryError(string $message, string $sql = '', array $parameters = [], int $code = 0, ?Throwable $previous = null): self
    {
        return new self($message, $code, $previous, 'query', $sql, $parameters);
    }
    
    /**
     * Create an execution error exception
     */
    public static function executionError(string $message, string $sql = '', int $code = 0, ?Throwable $previous = null): self
    {
        return new self($message, $code, $previous, 'exec', $sql);
    }
    
    /**
     * Create a transaction error exception
     */
    public static function transactionError(string $message, int $code = 0, ?Throwable $previous = null): self
    {
        return new self($message, $code, $previous, 'transaction');
    }
}
