<?php

declare(strict_types=1);

namespace Simp\Pindrop\Logger;

/**
 * Null Logger Implementation
 * 
 * This logger does nothing and simply discards all log messages.
 * Useful for testing or when logging is not needed.
 */
class NullLogger implements LoggerInterface
{
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
        // Do nothing - this is a null logger
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
        // Do nothing
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
        // Do nothing
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
        // Do nothing
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
        // Do nothing
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
        // Do nothing
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
        // Do nothing
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
        // Do nothing
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
        // Do nothing
    }
}
