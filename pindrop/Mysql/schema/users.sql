-- Users Table Schema
CREATE TABLE IF NOT EXISTS `users` (
                                       `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                                       `uuid` CHAR(36) NOT NULL,
    `username` VARCHAR(50) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `email_verified_at` DATETIME NULL DEFAULT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `password_salt` VARCHAR(255) NULL DEFAULT NULL,
    `password_reset_token` VARCHAR(255) NULL DEFAULT NULL,
    `password_reset_expires` DATETIME NULL DEFAULT NULL,
    `remember_token` VARCHAR(255) NULL DEFAULT NULL,
    `remember_expires` DATETIME NULL DEFAULT NULL,
    `first_name` VARCHAR(100) NULL DEFAULT NULL,
    `last_name` VARCHAR(100) NULL DEFAULT NULL,
    `full_name` VARCHAR(201) NULL DEFAULT NULL,
    `display_name` VARCHAR(100) NULL DEFAULT NULL,
    `avatar_url` VARCHAR(500) NULL DEFAULT NULL,
    `bio` TEXT NULL DEFAULT NULL,
    `phone` VARCHAR(20) NULL DEFAULT NULL,
    `phone_verified_at` DATETIME NULL DEFAULT NULL,
    `timezone` VARCHAR(50) NULL DEFAULT NULL,
    `language` VARCHAR(10) NULL DEFAULT NULL,
    `country` VARCHAR(2) NULL DEFAULT NULL,
    `date_of_birth` DATE NULL DEFAULT NULL,
    `gender` ENUM('male','female','other','prefer_not_to_say') NULL DEFAULT NULL,
    `status` ENUM('active','inactive','suspended','banned','pending') NOT NULL DEFAULT 'pending',
    `role` VARCHAR(100) NOT NULL DEFAULT 'user',
    `permissions` JSON NULL DEFAULT NULL,
    `last_login_at` DATETIME NULL DEFAULT NULL,
    `last_login_ip` VARCHAR(45) NULL DEFAULT NULL,
    `last_login_user_agent` TEXT NULL DEFAULT NULL,
    `login_attempts` INT UNSIGNED NOT NULL DEFAULT 0,
    `locked_until` DATETIME NULL DEFAULT NULL,
    `email_notifications` BOOLEAN NOT NULL DEFAULT TRUE,
    `sms_notifications` BOOLEAN NOT NULL DEFAULT FALSE,
    `push_notifications` BOOLEAN NOT NULL DEFAULT TRUE,
    `two_factor_enabled` BOOLEAN NOT NULL DEFAULT FALSE,
    `two_factor_secret` VARCHAR(255) NULL DEFAULT NULL,
    `backup_codes` JSON NULL DEFAULT NULL,
    `profile_visibility` ENUM('public','private','friends_only') NOT NULL DEFAULT 'public',
    `is_verified` BOOLEAN NOT NULL DEFAULT FALSE,
    `verified_at` DATETIME NULL DEFAULT NULL,
    `verification_method` VARCHAR(50) NULL DEFAULT NULL,
    `metadata` JSON NULL DEFAULT NULL,
    `preferences` JSON NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,

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

    CONSTRAINT `chk_status` CHECK (`status` IN ('active','inactive','suspended','banned','pending')),
    CONSTRAINT `chk_role` CHECK (`role` IN ('super_admin','admin','moderator','user','guest')),
    CONSTRAINT `chk_gender` CHECK (`gender` IN ('male','female','other','prefer_not_to_say')),
    CONSTRAINT `chk_profile_visibility` CHECK (`profile_visibility` IN ('public','private','friends_only'))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Triggers (run as separate queries in PHP)

DROP TRIGGER IF EXISTS `tr_users_before_insert`;
CREATE TRIGGER `tr_users_before_insert`
    BEFORE INSERT ON `users`
    FOR EACH ROW
BEGIN
    IF NEW.full_name IS NULL THEN
        SET NEW.full_name = CONCAT_WS(' ', NEW.first_name, NEW.last_name);
END IF;
END;

DROP TRIGGER IF EXISTS `tr_users_before_update`;
CREATE TRIGGER `tr_users_before_update`
    BEFORE UPDATE ON `users`
    FOR EACH ROW
BEGIN
    IF NEW.full_name IS NULL
       OR (NEW.first_name <> OLD.first_name OR NEW.last_name <> OLD.last_name) THEN
        SET NEW.full_name = CONCAT_WS(' ', NEW.first_name, NEW.last_name);
END IF;
END;