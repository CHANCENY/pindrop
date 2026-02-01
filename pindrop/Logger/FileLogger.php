<?php

declare(strict_types=1);

namespace Simp\Pindrop\Logger;

use InvalidArgumentException;
use RuntimeException;

/**
 * File Logger Implementation
 * 
 * Simple file-based logger that writes log messages to a file.
 * Supports PSR-3 compatible logging levels.
 */
class FileLogger implements LoggerInterface
{
    private string $logFile;
    private string $dateFormat;
    private int $maxFileSize;
    private int $maxFiles;
    private bool $enabled;
    
    /**
     * Create a new file logger
     *
     * @param string $logFile Path to the log file
     * @param string $dateFormat Date format for log entries
     * @param int $maxFileSize Maximum file size in bytes (default: 10MB)
     * @param int $maxFiles Maximum number of backup files (default: 5)
     * @param bool $enabled Whether logging is enabled
     */
    public function __construct(
        string $logFile,
        string $dateFormat = 'Y-m-d H:i:s',
        int $maxFileSize = 10485760, // 10MB
        int $maxFiles = 5,
        bool $enabled = true
    ) {
        $this->logFile = $logFile;
        $this->dateFormat = $dateFormat;
        $this->maxFileSize = $maxFileSize;
        $this->maxFiles = $maxFiles;
        $this->enabled = $enabled;
        
        // Ensure log directory exists
        $this->ensureLogDirectory();
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
        
        $logEntry = $this->formatLogEntry($level, $message, $context);
        $this->writeLog($logEntry);
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
     * Validate log level
     *
     * @param mixed $level The log level to validate
     * @throws InvalidArgumentException If level is invalid
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
            throw new InvalidArgumentException("Invalid log level: {$level}");
        }
    }
    
    /**
     * Format log entry
     *
     * @param mixed $level The log level
     * @param string $message The log message
     * @param array $context The log context
     * @return string Formatted log entry
     */
    private function formatLogEntry($level, string $message, array $context = []): string
    {
        $timestamp = date($this->dateFormat);
        $levelUpper = strtoupper((string) $level);
        
        $logEntry = "[{$timestamp}] {$levelUpper}: {$message}";
        
        if (!empty($context)) {
            $contextString = json_encode($context, JSON_UNESCAPED_UNICODE);
            $logEntry .= " {$contextString}";
        }
        
        return $logEntry . PHP_EOL;
    }
    
    /**
     * Write log entry to file
     *
     * @param string $logEntry The log entry to write
     * @throws RuntimeException If unable to write to file
     */
    private function writeLog(string $logEntry): void
    {
        // Rotate log if needed
        $this->rotateLogIfNeeded();
        
        // Write to file
        $result = file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
        
        if ($result === false) {
            throw new RuntimeException("Failed to write to log file: {$this->logFile}");
        }
    }
    
    /**
     * Rotate log file if it exceeds maximum size
     */
    private function rotateLogIfNeeded(): void
    {
        if (!file_exists($this->logFile)) {
            return;
        }
        
        if (filesize($this->logFile) < $this->maxFileSize) {
            return;
        }
        
        // Rotate files
        for ($i = $this->maxFiles - 1; $i > 0; $i--) {
            $oldFile = $this->logFile . '.' . $i;
            $newFile = $this->logFile . '.' . ($i + 1);
            
            if (file_exists($oldFile)) {
                if ($i === $this->maxFiles - 1) {
                    unlink($oldFile); // Delete oldest
                } else {
                    rename($oldFile, $newFile);
                }
            }
        }
        
        // Move current file to .1
        rename($this->logFile, $this->logFile . '.1');
    }
    
    /**
     * Ensure log directory exists
     *
     * @throws RuntimeException If unable to create directory
     */
    private function ensureLogDirectory(): void
    {
        $directory = dirname($this->logFile);
        
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true)) {
                throw new RuntimeException("Failed to create log directory: {$directory}");
            }
        }
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
     * Get log file path
     *
     * @return string The log file path
     */
    public function getLogFile(): string
    {
        return $this->logFile;
    }
    
    /**
     * Get log file size
     *
     * @return int The current log file size in bytes
     */
    public function getLogFileSize(): int
    {
        return file_exists($this->logFile) ? filesize($this->logFile) : 0;
    }
    
    /**
     * Clear the log file
     */
    public function clearLog(): void
    {
        if (file_exists($this->logFile)) {
            unlink($this->logFile);
        }
    }
    
    /**
     * Get logger configuration
     *
     * @return array Logger configuration
     */
    public function getConfig(): array
    {
        return [
            'log_file' => $this->logFile,
            'date_format' => $this->dateFormat,
            'max_file_size' => $this->maxFileSize,
            'max_files' => $this->maxFiles,
            'enabled' => $this->enabled,
            'current_size' => $this->getLogFileSize(),
        ];
    }
}
