CREATE TABLE IF NOT EXISTS `php_sessions` (
    `session_id`   VARCHAR(128)  NOT NULL,
    `session_data` MEDIUMTEXT    NULL,
    `expires_at`   INT UNSIGNED  NOT NULL,
     `created_at`   TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`   TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`session_id`),
    INDEX `idx_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;