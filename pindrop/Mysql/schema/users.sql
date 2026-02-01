-- Users Table Schema
-- Stores user accounts and authentication data

CREATE TABLE IF NOT EXISTS `users` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `uuid` CHAR(36) NOT NULL COMMENT 'Unique identifier for the user',
    `username` VARCHAR(50) NOT NULL COMMENT 'Unique username',
    `email` VARCHAR(255) NOT NULL COMMENT 'User email address',
    `email_verified_at` DATETIME NULL DEFAULT NULL COMMENT 'Email verification timestamp',
    `password_hash` VARCHAR(255) NOT NULL COMMENT 'Hashed password',
    `password_salt` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Password salt for additional security',
    `password_reset_token` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Password reset token',
    `password_reset_expires` DATETIME NULL DEFAULT NULL COMMENT 'Password reset token expiration',
    `remember_token` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Remember me token',
    `remember_expires` DATETIME NULL DEFAULT NULL COMMENT 'Remember me token expiration',
    `first_name` VARCHAR(100) NULL DEFAULT NULL COMMENT 'User first name',
    `last_name` VARCHAR(100) NULL DEFAULT NULL COMMENT 'User last name',
    `full_name` VARCHAR(201) NULL DEFAULT NULL COMMENT 'Computed full name',
    `display_name` VARCHAR(100) NULL DEFAULT NULL COMMENT 'Display name for public view',
    `avatar_url` VARCHAR(500) NULL DEFAULT NULL COMMENT 'Profile avatar URL',
    `bio` TEXT NULL DEFAULT NULL COMMENT 'User biography',
    `phone` VARCHAR(20) NULL DEFAULT NULL COMMENT 'Phone number',
    `phone_verified_at` DATETIME NULL DEFAULT NULL COMMENT 'Phone verification timestamp',
    `timezone` VARCHAR(50) NULL DEFAULT NULL COMMENT 'User timezone',
    `language` VARCHAR(10) NULL DEFAULT NULL COMMENT 'User preferred language',
    `country` VARCHAR(2) NULL DEFAULT NULL COMMENT 'User country code (ISO 3166-1 alpha-2)',
    `date_of_birth` DATE NULL DEFAULT NULL COMMENT 'User date of birth',
    `gender` ENUM('male', 'female', 'other', 'prefer_not_to_say') NULL DEFAULT NULL COMMENT 'User gender',
    `status` ENUM('active', 'inactive', 'suspended', 'banned', 'pending') NOT NULL DEFAULT 'pending' COMMENT 'Account status',
    `role` ENUM('super_admin', 'admin', 'moderator', 'user', 'guest') NOT NULL DEFAULT 'user' COMMENT 'User role',
    `permissions` JSON NULL DEFAULT NULL COMMENT 'Additional permissions as JSON',
    `last_login_at` DATETIME NULL DEFAULT NULL COMMENT 'Last successful login timestamp',
    `last_login_ip` VARCHAR(45) NULL DEFAULT NULL COMMENT 'Last login IP address',
    `last_login_user_agent` TEXT NULL DEFAULT NULL COMMENT 'Last login user agent',
    `login_attempts` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Failed login attempts count',
    `locked_until` DATETIME NULL DEFAULT NULL COMMENT 'Account locked until timestamp',
    `email_notifications` BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Enable email notifications',
    `sms_notifications` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Enable SMS notifications',
    `push_notifications` BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Enable push notifications',
    `two_factor_enabled` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Two-factor authentication enabled',
    `two_factor_secret` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Two-factor secret key',
    `backup_codes` JSON NULL DEFAULT NULL COMMENT 'Two-factor backup codes',
    `profile_visibility` ENUM('public', 'private', 'friends_only') NOT NULL DEFAULT 'public' COMMENT 'Profile visibility setting',
    `is_verified` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Account verification status',
    `verified_at` DATETIME NULL DEFAULT NULL COMMENT 'Account verification timestamp',
    `verification_method` VARCHAR(50) NULL DEFAULT NULL COMMENT 'Verification method used',
    `metadata` JSON NULL DEFAULT NULL COMMENT 'Additional metadata as JSON',
    `preferences` JSON NULL DEFAULT NULL COMMENT 'User preferences as JSON',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Account creation timestamp',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Account update timestamp',
    `deleted_at` DATETIME NULL DEFAULT NULL COMMENT 'Soft delete timestamp',
    
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_uuid` (`uuid`),
    UNIQUE KEY `uk_username` (`username`),
    UNIQUE KEY `uk_email` (`email`),
    UNIQUE KEY `uk_password_reset_token` (`password_reset_token`),
    INDEX `idx_status` (`status`),
    INDEX `idx_role` (`role`),
    INDEX `idx_email_verified_at` (`email_verified_at`),
    INDEX `idx_last_login_at` (`last_login_at`),
    INDEX `idx_created_at` (`created_at`),
    INDEX `idx_deleted_at` (`deleted_at`),
    INDEX `idx_full_name` (`full_name`),
    INDEX `idx_display_name` (`display_name`),
    
    CONSTRAINT `chk_status` CHECK (`status` IN ('active', 'inactive', 'suspended', 'banned', 'pending')),
    CONSTRAINT `chk_role` CHECK (`role` IN ('super_admin', 'admin', 'moderator', 'user', 'guest')),
    CONSTRAINT `chk_gender` CHECK (`gender` IN ('male', 'female', 'other', 'prefer_not_to_say')),
    CONSTRAINT `chk_profile_visibility` CHECK (`profile_visibility` IN ('public', 'private', 'friends_only'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='User accounts table with authentication and profile data';

-- Triggers for automatic full name generation
DELIMITER //

CREATE TRIGGER IF NOT EXISTS `tr_users_before_insert`
BEFORE INSERT ON `users`
FOR EACH ROW
BEGIN
    IF NEW.full_name IS NULL THEN
        SET NEW.full_name = CONCAT_WS(' ', NEW.first_name, NEW.last_name);
    END IF;
END//

CREATE TRIGGER IF NOT EXISTS `tr_users_before_update`
BEFORE UPDATE ON `users`
FOR EACH ROW
BEGIN
    IF NEW.full_name IS NULL OR (NEW.first_name <> OLD.first_name OR NEW.last_name <> OLD.last_name) THEN
        SET NEW.full_name = CONCAT_WS(' ', NEW.first_name, NEW.last_name);
    END IF;
END//

DELIMITER ;

-- Add full-text search index for user search (optional)
-- ALTER TABLE `users` ADD FULLTEXT `ft_search` (`username`, `email`, `full_name`, `display_name`, `bio`);
