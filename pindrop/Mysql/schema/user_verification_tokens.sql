-- Create user_verification_tokens table for storing verification tokens
CREATE TABLE `user_verification_tokens` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT(11) NOT NULL,
    `token_type` ENUM('email_verification', 'password_reset') NOT NULL,
    `token` VARCHAR(64) NOT NULL UNIQUE,
    `email` VARCHAR(255) NOT NULL,
    `expires_at` TIMESTAMP NOT NULL,
    `used` TINYINT(1) NOT NULL DEFAULT 0,
    `used_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_token` (`token`),
    INDEX `idx_token_type` (`token_type`),
    INDEX `idx_email` (`email`),
    INDEX `idx_expires_at` (`expires_at`),
    INDEX `idx_used` (`used`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
