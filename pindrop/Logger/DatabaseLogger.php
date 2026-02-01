<?php

declare(strict_types=1);

namespace Simp\Pindrop\Logger;

use Simp\Pindrop\Database\Database;
use Exception;

/**
 * Database Logger Implementation
 * 
 * Stores log messages in the database logs table.
 * Supports all PSR-3 log levels with context storage.
 */
class DatabaseLogger implements LoggerInterface
{
    private Database $database;
    private string $channel;
    private ?string $requestId;
    private ?int $userId;
    private ?string $ipAddress;
    private ?string $userAgent;
    private ?string $sessionId;
    private bool $enabled;
    
    public function __construct(
        Database $database,
        string $channel = 'app',
        ?string $requestId = null,
        ?int $userId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $sessionId = null,
        bool $enabled = true
    ) {
        $this->database = $database;
        $this->channel = $channel;
        $this->requestId = $requestId ?? $this->generateRequestId();
        $this->userId = $userId;
        $this->ipAddress = $ipAddress;
        $this->userAgent = $userAgent;
        $this->sessionId = $sessionId;
        $this->enabled = $enabled;
    }
    
    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level The log level
     * @param string $message The log message
     * @param array $context The log context
     * @return void
     */
    public function log($level, string $message, array $context = []): void
    {
        if (!$this->enabled) {
            return;
        }
        
        $this->validateLevel($level);
        
        try {
            $this->storeLog($level, $message, $context);
        } catch (Exception $e) {
            // Fallback to error_log if database logging fails
            error_log("DatabaseLogger failed: " . $e->getMessage());
        }
    }
    
    /**
     * System is unusable.
     *
     * @param string $message The log message
     * @param array $context The log context
     * @return void
     */
    public function emergency(string $message, array $context = []): void
    {
        $this->log(self::EMERGENCY, $message, $context);
    }
    
    /**
     * Action must be taken immediately.
     *
     * @param string $message The log message
     * @param array $context The log context
     * @return void
     */
    public function alert(string $message, array $context = []): void
    {
        $this->log(self::ALERT, $message, $context);
    }
    
    /**
     * Critical conditions.
     *
     * @param string $message The log message
     * @param array $context The log context
     * @return void
     */
    public function critical(string $message, array $context = []): void
    {
        $this->log(self::CRITICAL, $message, $context);
    }
    
    /**
     * Error conditions.
     *
     * @param string $message The log message
     * @param array $context The log context
     * @return void
     */
    public function error(string $message, array $context = []): void
    {
        $this->log(self::ERROR, $message, $context);
    }
    
    /**
     * Exceptional occurrences that are not errors.
     *
     * @param string $message The log message
     * @param array $context The log context
     * @return void
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log(self::WARNING, $message, $context);
    }
    
    /**
     * Normal but significant conditions.
     *
     * @param string $message The log message
     * @param array $context The log context
     * @return void
     */
    public function notice(string $message, array $context = []): void
    {
        $this->log(self::NOTICE, $message, $context);
    }
    
    /**
     * Interesting events.
     *
     * @param string $message The log message
     * @param array $context The log context
     * @return void
     */
    public function info(string $message, array $context = []): void
    {
        $this->log(self::INFO, $message, $context);
    }
    
    /**
     * Detailed debug information.
     *
     * @param string $message The log message
     * @param array $context The log context
     * @return void
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log(self::DEBUG, $message, $context);
    }
    
    /**
     * Store log entry in database
     *
     * @param mixed $level The log level
     * @param string $message The log message
     * @param array $context The log context
     * @throws Exception If database operation fails
     */
    private function storeLog($level, string $message, array $context = []): void
    {
        // Extract debug information from context
        $debugInfo = $this->extractDebugInfo($context);
        
        // Prepare log data
        $logData = [
            'level' => $level,
            'message' => $message,
            'context' => !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : null,
            'channel' => $this->channel,
            'datetime' => date('Y-m-d H:i:s'),
            'memory_usage' => memory_get_usage(true),
            'request_id' => $this->requestId,
            'user_id' => $this->userId,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'session_id' => $this->sessionId,
            'file_path' => $debugInfo['file_path'],
            'line_number' => $debugInfo['line_number'],
            'function_name' => $debugInfo['function_name'],
            'class_name' => $debugInfo['class_name'],
            'exception_class' => $debugInfo['exception_class'],
            'exception_message' => $debugInfo['exception_message'],
            'exception_trace' => $debugInfo['exception_trace'],
        ];
        
        // Insert log entry
        $this->database->insert('logs', $logData);
    }
    
    /**
     * Extract debug information from context
     *
     * @param array $context The log context
     * @return array Debug information
     */
    private function extractDebugInfo(array $context): array
    {
        $debugInfo = [
            'file_path' => null,
            'line_number' => null,
            'function_name' => null,
            'class_name' => null,
            'exception_class' => null,
            'exception_message' => null,
            'exception_trace' => null,
        ];
        
        // Extract exception information if present
        if (isset($context['exception']) && $context['exception'] instanceof \Throwable) {
            $exception = $context['exception'];
            $debugInfo['exception_class'] = get_class($exception);
            $debugInfo['exception_message'] = $exception->getMessage();
            $debugInfo['exception_trace'] = $exception->getTraceAsString();
            $debugInfo['file_path'] = $exception->getFile();
            $debugInfo['line_number'] = $exception->getLine();
        }
        
        // Extract debug backtrace if present
        if (isset($context['trace']) && is_array($context['trace'])) {
            $trace = $context['trace'][0] ?? [];
            $debugInfo['file_path'] = $trace['file'] ?? null;
            $debugInfo['line_number'] = $trace['line'] ?? null;
            $debugInfo['function_name'] = $trace['function'] ?? null;
            $debugInfo['class_name'] = $trace['class'] ?? null;
        }
        
        // Extract individual debug fields
        foreach (['file_path', 'line_number', 'function_name', 'class_name'] as $field) {
            if (isset($context[$field])) {
                $debugInfo[$field] = $context[$field];
            }
        }
        
        return $debugInfo;
    }
    
    /**
     * Validate log level
     *
     * @param mixed $level The log level to validate
     * @throws \InvalidArgumentException If level is invalid
     */
    private function validateLevel($level): void
    {
        $validLevels = [
            self::EMERGENCY,
            self::ALERT,
            self::CRITICAL,
            self::ERROR,
            self::WARNING,
            self::NOTICE,
            self::INFO,
            self::DEBUG
        ];
        
        if (!in_array($level, $validLevels, true)) {
            throw new \InvalidArgumentException("Invalid log level: {$level}");
        }
    }
    
    /**
     * Generate unique request ID
     *
     * @return string Request ID
     */
    private function generateRequestId(): string
    {
        return uniqid('req_', true);
    }
    
    /**
     * Set user ID for logging
     *
     * @param int|null $userId User ID
     */
    public function setUserId(?int $userId): void
    {
        $this->userId = $userId;
    }
    
    /**
     * Set IP address for logging
     *
     * @param string|null $ipAddress IP address
     */
    public function setIpAddress(?string $ipAddress): void
    {
        $this->ipAddress = $ipAddress;
    }
    
    /**
     * Set user agent for logging
     *
     * @param string|null $userAgent User agent
     */
    public function setUserAgent(?string $userAgent): void
    {
        $this->userAgent = $userAgent;
    }
    
    /**
     * Set session ID for logging
     *
     * @param string|null $sessionId Session ID
     */
    public function setSessionId(?string $sessionId): void
    {
        $this->sessionId = $sessionId;
    }
    
    /**
     * Set channel for logging
     *
     * @param string $channel Channel name
     */
    public function setChannel(string $channel): void
    {
        $this->channel = $channel;
    }
    
    /**
     * Enable or disable logging
     *
     * @param bool $enabled Whether to enable logging
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }
    
    /**
     * Check if logging is enabled
     *
     * @return bool True if logging is enabled
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
    
    /**
     * Get current request ID
     *
     * @return string Request ID
     */
    public function getRequestId(): string
    {
        return $this->requestId;
    }
    
    /**
     * Get current channel
     *
     * @return string Channel name
     */
    public function getChannel(): string
    {
        return $this->channel;
    }
    
    /**
     * Get logger configuration
     *
     * @return array Logger configuration
     */
    public function getConfig(): array
    {
        return [
            'channel' => $this->channel,
            'request_id' => $this->requestId,
            'user_id' => $this->userId,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'session_id' => $this->sessionId,
            'enabled' => $this->enabled,
        ];
    }
    
    /**
     * Query logs from database
     *
     * @param array $filters Query filters
     * @param int $limit Limit results
     * @param int $offset Offset results
     * @return array Log entries
     */
    public function queryLogs(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $where = '1=1';
        $params = [];
        
        // Build WHERE clause from filters
        if (!empty($filters['level'])) {
            $where .= ' AND level = ?';
            $params[] = $filters['level'];
        }
        
        if (!empty($filters['channel'])) {
            $where .= ' AND channel = ?';
            $params[] = $filters['channel'];
        }
        
        if (!empty($filters['user_id'])) {
            $where .= ' AND user_id = ?';
            $params[] = $filters['user_id'];
        }
        
        if (!empty($filters['request_id'])) {
            $where .= ' AND request_id = ?';
            $params[] = $filters['request_id'];
        }
        
        if (!empty($filters['date_from'])) {
            $where .= ' AND datetime >= ?';
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where .= ' AND datetime <= ?';
            $params[] = $filters['date_to'];
        }
        
        // Query logs
        $sql = "SELECT * FROM logs WHERE {$where} ORDER BY datetime DESC LIMIT {$limit} OFFSET {$offset}";
        
        return $this->database->fetchAll($sql, ...$params);
    }
    
    /**
     * Count logs matching filters
     *
     * @param array $filters Query filters
     * @return int Log count
     */
    public function countLogs(array $filters = []): int
    {
        $where = '1=1';
        $params = [];
        
        // Build WHERE clause from filters
        if (!empty($filters['level'])) {
            $where .= ' AND level = ?';
            $params[] = $filters['level'];
        }
        
        if (!empty($filters['channel'])) {
            $where .= ' AND channel = ?';
            $params[] = $filters['channel'];
        }
        
        if (!empty($filters['user_id'])) {
            $where .= ' AND user_id = ?';
            $params[] = $filters['user_id'];
        }
        
        if (!empty($filters['request_id'])) {
            $where .= ' AND request_id = ?';
            $params[] = $filters['request_id'];
        }
        
        if (!empty($filters['date_from'])) {
            $where .= ' AND datetime >= ?';
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $where .= ' AND datetime <= ?';
            $params[] = $filters['date_to'];
        }
        
        // Count logs
        $sql = "SELECT COUNT(*) as count FROM logs WHERE {$where}";
        
        $result = $this->database->fetch($sql, ...$params);
        
        return (int) ($result['count'] ?? 0);
    }
    
    /**
     * Clear old logs
     *
     * @param int $daysOld Delete logs older than this many days
     * @return int Number of deleted logs
     */
    public function clearOldLogs(int $daysOld = 30): int
    {
        $sql = "DELETE FROM logs WHERE datetime < DATE_SUB(NOW(), INTERVAL ? DAY)";
        
        return $this->database->exec($sql, $daysOld);
    }
}
