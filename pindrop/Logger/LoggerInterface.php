<?php

declare(strict_types=1);

namespace Simp\Pindrop\Logger;

/**
 * PSR-3 Compatible Logger Interface
 * 
 * Describes a logger instance.
 * This interface is compatible with PSR-3 Logger Interface.
 */
interface LoggerInterface
{
    /**
     * System is unusable.
     */
    public const EMERGENCY = 'emergency';
    
    /**
     * Action must be taken immediately.
     */
    public const ALERT = 'alert';
    
    /**
     * Critical conditions.
     */
    public const CRITICAL = 'critical';
    
    /**
     * Error conditions.
     */
    public const ERROR = 'error';
    
    /**
     * Exceptional occurrences that are not errors.
     */
    public const WARNING = 'warning';
    
    /**
     * Normal but significant conditions.
     */
    public const NOTICE = 'notice';
    
    /**
     * Interesting events.
     */
    public const INFO = 'info';
    
    /**
     * Detailed debug information.
     */
    public const DEBUG = 'debug';
    
    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level The log level
     * @param string $message The log message
     * @param array $context The log context
     * @return void
     */
    public function log($level, string $message, array $context = []): void;
    
    /**
     * System is unusable.
     *
     * @param string $message The log message
     * @param array $context The log context
     * @return void
     */
    public function emergency(string $message, array $context = []): void;
    
    /**
     * Action must be taken immediately.
     *
     * @param string $message The log message
     * @param array $context The log context
     * @return void
     */
    public function alert(string $message, array $context = []): void;
    
    /**
     * Critical conditions.
     *
     * @param string $message The log message
     * @param array $context The log context
     * @return void
     */
    public function critical(string $message, array $context = []): void;
    
    /**
     * Error conditions.
     *
     * @param string $message The log message
     * @param array $context The log context
     * @return void
     */
    public function error(string $message, array $context = []): void;
    
    /**
     * Exceptional occurrences that are not errors.
     *
     * @param string $message The log message
     * @param array $context The log context
     * @return void
     */
    public function warning(string $message, array $context = []): void;
    
    /**
     * Normal but significant conditions.
     *
     * @param string $message The log message
     * @param array $context The log context
     * @return void
     */
    public function notice(string $message, array $context = []): void;
    
    /**
     * Interesting events.
     *
     * @param string $message The log message
     * @param array $context The log context
     * @return void
     */
    public function info(string $message, array $context = []): void;
    
    /**
     * Detailed debug information.
     *
     * @param string $message The log message
     * @param array $context The log context
     * @return void
     */
    public function debug(string $message, array $context = []): void;
}
