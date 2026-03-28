-- Logs Table Schema
-- Stores application logs from the logger system

CREATE TABLE `logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `level` VARCHAR(20) NOT NULL COMMENT 'Log level (emergency, alert, critical, error, warning, notice, info, debug)',
    `message` TEXT NOT NULL COMMENT 'Log message',
    `context` JSON NULL COMMENT 'Log context data as JSON',
    `channel` VARCHAR(100) NULL DEFAULT NULL COMMENT 'Log channel or source',
    `datetime` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Log timestamp',
    `memory_usage` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Memory usage in bytes',
    `request_id` VARCHAR(100) NULL DEFAULT NULL COMMENT 'Request identifier for tracking',
    `user_id` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'User ID if applicable',
    `ip_address` VARCHAR(45) NULL DEFAULT NULL COMMENT 'Client IP address',
    `user_agent` TEXT NULL DEFAULT NULL COMMENT 'User agent string',
    `session_id` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Session identifier',
    `file_path` VARCHAR(500) NULL DEFAULT NULL COMMENT 'Source file path',
    `line_number` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Source line number',
    `function_name` VARCHAR(100) NULL DEFAULT NULL COMMENT 'Function or method name',
    `class_name` VARCHAR(100) NULL DEFAULT NULL COMMENT 'Class name',
    `exception_class` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Exception class name if applicable',
    `exception_message` TEXT NULL DEFAULT NULL COMMENT 'Exception message if applicable',
    `exception_trace` LONGTEXT NULL DEFAULT NULL COMMENT 'Exception stack trace if applicable',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation timestamp',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Record update timestamp',
    
    PRIMARY KEY (`id`),
    INDEX `idx_level` (`level`),
    INDEX `idx_datetime` (`datetime`),
    INDEX `idx_channel` (`channel`),
    INDEX `idx_request_id` (`request_id`),
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_ip_address` (`ip_address`),
    INDEX `idx_session_id` (`session_id`),
    INDEX `idx_created_at` (`created_at`),
    
    CONSTRAINT `chk_level` CHECK (`level` IN ('emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Application logs table for storing logger output';

-- Add partitioning for better performance with large datasets (optional)
-- Uncomment if you have very large log volumes
-- ALTER TABLE `logs` PARTITION BY RANGE (TO_DAYS(`datetime`)) (
--     PARTITION p_current VALUES LESS THAN (TO_DAYS(CURRENT_DATE + INTERVAL 1 DAY)),
--     PARTITION p_future VALUES LESS THAN MAXVALUE
-- );
