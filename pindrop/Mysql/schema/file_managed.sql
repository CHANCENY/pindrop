-- =========================
-- TABLE
-- =========================
CREATE TABLE IF NOT EXISTS `file_managed` (
                                              `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                                              `uuid` CHAR(36) NOT NULL,
    `filename` VARCHAR(255) NOT NULL,
    `uri` VARCHAR(500) NOT NULL,
    `filemime` VARCHAR(255) NOT NULL,
    `filesize` BIGINT UNSIGNED NOT NULL DEFAULT 0,
    `status` TINYINT NOT NULL DEFAULT 1,
    `timestamp` INT UNSIGNED NOT NULL,
    `uid` BIGINT UNSIGNED NULL DEFAULT NULL,
    `field_name` VARCHAR(100) NULL DEFAULT NULL,
    `entity_type` VARCHAR(100) NULL DEFAULT NULL,
    `entity_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `bundle` VARCHAR(100) NULL DEFAULT NULL,
    `langcode` VARCHAR(12) NOT NULL DEFAULT 'en',
    `alt` VARCHAR(512) NULL DEFAULT NULL,
    `title` VARCHAR(1024) NULL DEFAULT NULL,
    `description` TEXT NULL DEFAULT NULL,
    `width` INT UNSIGNED NULL DEFAULT NULL,
    `height` INT UNSIGNED NULL DEFAULT NULL,
    `duration` DECIMAL(10,3) NULL DEFAULT NULL,
    `checksum` VARCHAR(64) NULL DEFAULT NULL,
    `metadata` JSON NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_uuid` (`uuid`),
    UNIQUE KEY `uk_uri` (`uri`),

    KEY `idx_uid` (`uid`),
    KEY `idx_entity` (`entity_type`, `entity_id`),
    KEY `idx_created_at` (`created_at`),

    CONSTRAINT `fk_file_managed_user`
    FOREIGN KEY (`uid`)
    REFERENCES `users` (`id`)
                                                             ON DELETE SET NULL
                                                             ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =========================
-- INDEXES
-- =========================
CREATE INDEX `idx_user_files` ON `file_managed` (`uid`, `status`, `created_at`);
CREATE INDEX `idx_entity_files` ON `file_managed` (`entity_type`, `entity_id`, `bundle`, `status`);
CREATE INDEX `idx_mime_size` ON `file_managed` (`filemime`, `filesize`);

-- =========================
-- FULLTEXT SEARCH
-- =========================
ALTER TABLE `file_managed`
    ADD FULLTEXT `ft_search` (`filename`, `alt`, `title`, `description`);

-- =========================
-- TRIGGERS
-- =========================
DROP TRIGGER IF EXISTS `tr_file_managed_before_insert`;

CREATE TRIGGER `tr_file_managed_before_insert`
    BEFORE INSERT ON `file_managed`
    FOR EACH ROW
BEGIN
    IF NEW.uuid IS NULL OR NEW.uuid = '' THEN
        SET NEW.uuid = UUID();
END IF;

IF NEW.timestamp IS NULL OR NEW.timestamp = 0 THEN
        SET NEW.timestamp = UNIX_TIMESTAMP();
END IF;
END;

DROP TRIGGER IF EXISTS `tr_file_managed_before_update`;

CREATE TRIGGER `tr_file_managed_before_update`
    BEFORE UPDATE ON `file_managed`
    FOR EACH ROW
BEGIN
    IF OLD.uri <> NEW.uri OR OLD.filesize <> NEW.filesize THEN
        SET NEW.timestamp = UNIX_TIMESTAMP();
END IF;
END;

-- =========================
-- BASIC VIEW (optional but useful)
-- =========================
CREATE OR REPLACE VIEW `v_active_files` AS
SELECT * FROM `file_managed`
WHERE `deleted_at` IS NULL;