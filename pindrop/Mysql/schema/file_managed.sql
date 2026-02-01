-- File Managed Table Schema
-- Stores uploaded file information and metadata

CREATE TABLE IF NOT EXISTS `file_managed` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `uuid` CHAR(36) NOT NULL COMMENT 'Unique identifier for the file',
    `filename` VARCHAR(255) NOT NULL COMMENT 'Original filename',
    `uri` VARCHAR(500) NOT NULL COMMENT 'File URI (e.g., public://uploads/file.jpg)',
    `filemime` VARCHAR(255) NOT NULL COMMENT 'MIME type of the file',
    `filesize` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'File size in bytes',
    `status` TINYINT NOT NULL DEFAULT 1 COMMENT 'File status (0=temporary, 1=permanent)',
    `timestamp` INT UNSIGNED NOT NULL COMMENT 'Unix timestamp when file was uploaded',
    `uid` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'User ID who uploaded the file',
    `field_name` VARCHAR(100) NULL DEFAULT NULL COMMENT 'Form field name that uploaded the file',
    `entity_type` VARCHAR(100) NULL DEFAULT NULL COMMENT 'Entity type this file belongs to',
    `entity_id` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'Entity ID this file belongs to',
    `bundle` VARCHAR(100) NULL DEFAULT NULL COMMENT 'Bundle/type for entity-specific files',
    `langcode` VARCHAR(12) NOT NULL DEFAULT 'en' COMMENT 'Language code',
    `alt` VARCHAR(512) NULL DEFAULT NULL COMMENT 'Alt text for images',
    `title` VARCHAR(1024) NULL DEFAULT NULL COMMENT 'Title attribute for files',
    `description` TEXT NULL DEFAULT NULL COMMENT 'File description',
    `width` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Width for images/videos',
    `height` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Height for images/videos',
    `duration` DECIMAL(10,3) NULL DEFAULT NULL COMMENT 'Duration for audio/video files',
    `checksum` VARCHAR(64) NULL DEFAULT NULL COMMENT 'SHA-256 checksum of file content',
    `metadata` JSON NULL DEFAULT NULL COMMENT 'Additional file metadata as JSON',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation timestamp',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Record update timestamp',
    `deleted_at` DATETIME NULL DEFAULT NULL COMMENT 'Soft delete timestamp',
    
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_uuid` (`uuid`),
    UNIQUE KEY `uk_uri` (`uri`),
    KEY `idx_status` (`status`),
    KEY `idx_timestamp` (`timestamp`),
    KEY `idx_uid` (`uid`),
    KEY `idx_field_name` (`field_name`),
    KEY `idx_entity` (`entity_type`, `entity_id`),
    KEY `idx_langcode` (`langcode`),
    KEY `idx_filemime` (`filemime`),
    KEY `idx_created_at` (`created_at`),
    KEY `idx_deleted_at` (`deleted_at`),
    
    CONSTRAINT `fk_file_managed_user` 
        FOREIGN KEY (`uid`) 
        REFERENCES `users` (`id`) 
        ON DELETE SET NULL 
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Managed file storage and metadata';

-- Add full-text search index for file search
ALTER TABLE `file_managed` ADD FULLTEXT `ft_search` (`filename`, `alt`, `title`, `description`);

-- Create indexes for common queries
CREATE INDEX `idx_user_files` ON `file_managed` (`uid`, `status`, `created_at`);
CREATE INDEX `idx_entity_files` ON `file_managed` (`entity_type`, `entity_id`, `bundle`, `status`);
CREATE INDEX `idx_field_files` ON `file_managed` (`field_name`, `status`, `created_at`);
CREATE INDEX `idx_mime_size` ON `file_managed` (`filemime`, `filesize`);

-- Create trigger to generate UUID for new files
DELIMITER //

CREATE TRIGGER IF NOT EXISTS `tr_file_managed_before_insert`
BEFORE INSERT ON `file_managed`
FOR EACH ROW
BEGIN
    IF NEW.uuid IS NULL OR NEW.uuid = '' THEN
        SET NEW.uuid = UUID();
    END IF;
    
    IF NEW.timestamp IS NULL OR NEW.timestamp = 0 THEN
        SET NEW.timestamp = UNIX_TIMESTAMP();
    END IF;
END//

CREATE TRIGGER IF NOT EXISTS `tr_file_managed_before_update`
BEFORE UPDATE ON `file_managed`
FOR EACH ROW
BEGIN
    -- Update timestamp if file content changes
    IF OLD.uri <> NEW.uri OR OLD.filesize <> NEW.filesize THEN
        SET NEW.timestamp = UNIX_TIMESTAMP();
    END IF;
END//

DELIMITER ;

-- Create view for active files (not soft deleted)
CREATE OR REPLACE VIEW `v_active_files` AS
SELECT 
    `id`,
    `uuid`,
    `filename`,
    `uri`,
    `filemime`,
    `filesize`,
    `status`,
    `timestamp`,
    `uid`,
    `field_name`,
    `entity_type`,
    `entity_id`,
    `bundle`,
    `langcode`,
    `alt`,
    `title`,
    `description`,
    `width`,
    `height`,
    `duration`,
    `checksum`,
    `metadata`,
    `created_at`,
    `updated_at`
FROM `file_managed`
WHERE `deleted_at` IS NULL;

-- Create view for user files
CREATE OR REPLACE VIEW `v_user_files` AS
SELECT 
    fm.*,
    u.username,
    u.email,
    u.full_name as user_full_name
FROM `file_managed` fm
LEFT JOIN `users` u ON fm.uid = u.id
WHERE fm.deleted_at IS NULL;

-- Create view for entity files
CREATE OR REPLACE VIEW `v_entity_files` AS
SELECT 
    fm.*,
    CONCAT(fm.entity_type, ':', fm.entity_id) as entity_reference
FROM `file_managed` fm
WHERE fm.deleted_at IS NULL 
  AND fm.entity_type IS NOT NULL 
  AND fm.entity_id IS NOT NULL;

-- Insert sample data for testing
INSERT IGNORE INTO `file_managed` (
    `filename`, `uri`, `filemime`, `filesize`, `status`, `uid`, `field_name`, `alt`, `title`
) VALUES 
(
    'sample-avatar.jpg',
    'public://uploads/avatars/sample-avatar.jpg',
    'image/jpeg',
    245760,
    1,
    NULL,
    'avatar',
    'Sample avatar image',
    'Default user avatar'
);

-- Create procedure to clean up temporary files
DELIMITER //

CREATE PROCEDURE IF NOT EXISTS `cleanup_temp_files`(
    IN days_old INT DEFAULT 7
)
BEGIN
    DELETE FROM `file_managed` 
    WHERE `status` = 0 
      AND `created_at` < DATE_SUB(NOW(), INTERVAL days_old DAY)
      AND `deleted_at` IS NULL;
    
    SELECT ROW_COUNT() as files_removed;
END//

DELIMITER ;

-- Create function to get file URL
DELIMITER //

CREATE FUNCTION IF NOT EXISTS `get_file_url`(
    file_uri VARCHAR(500)
) RETURNS VARCHAR(500)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE file_url VARCHAR(500);
    
    -- Convert public:// to actual URL path
    IF file_uri LIKE 'public://%' THEN
        SET file_url = CONCAT('/files/', SUBSTRING(file_uri, 9));
    ELSE
        SET file_url = file_uri;
    END IF;
    
    RETURN file_url;
END//

DELIMITER ;

-- Create function to format file size
DELIMITER //

CREATE FUNCTION IF NOT EXISTS `format_file_size`(
    size_bytes BIGINT
) RETURNS VARCHAR(20)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE formatted VARCHAR(20);
    
    IF size_bytes < 1024 THEN
        SET formatted = CONCAT(size_bytes, ' B');
    ELSEIF size_bytes < 1048576 THEN
        SET formatted = CONCAT(ROUND(size_bytes / 1024, 1), ' KB');
    ELSEIF size_bytes < 1073741824 THEN
        SET formatted = CONCAT(ROUND(size_bytes / 1048576, 1), ' MB');
    ELSE
        SET formatted = CONCAT(ROUND(size_bytes / 1073741824, 2), ' GB');
    END IF;
    
    RETURN formatted;
END//

DELIMITER ;

-- Add comments for documentation
ALTER TABLE `file_managed` COMMENT = 'Managed file storage system - tracks all uploaded files with metadata';
ALTER TABLE `file_managed` MODIFY COLUMN `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT 'Primary key - auto-incremented file ID';
ALTER TABLE `file_managed` MODIFY COLUMN `uuid` CHAR(36) NOT NULL COMMENT 'Universally unique identifier for file';
ALTER TABLE `file_managed` MODIFY COLUMN `filename` VARCHAR(255) NOT NULL COMMENT 'Original filename from upload';
ALTER TABLE `file_managed` MODIFY COLUMN `uri` VARCHAR(500) NOT NULL COMMENT 'Storage URI (public://, private://, etc.)';
ALTER TABLE `file_managed` MODIFY COLUMN `filemime` VARCHAR(255) NOT NULL COMMENT 'MIME type for content-type header';
ALTER TABLE `file_managed` MODIFY COLUMN `filesize` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'File size in bytes';
ALTER TABLE `file_managed` MODIFY COLUMN `status` TINYINT NOT NULL DEFAULT 1 COMMENT '0=temporary, 1=permanent';
ALTER TABLE `file_managed` MODIFY COLUMN `timestamp` INT UNSIGNED NOT NULL COMMENT 'Unix timestamp of upload';
ALTER TABLE `file_managed` MODIFY COLUMN `uid` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'User who uploaded the file';
ALTER TABLE `file_managed` MODIFY COLUMN `field_name` VARCHAR(100) NULL DEFAULT NULL COMMENT 'Form field that uploaded this file';
ALTER TABLE `file_managed` MODIFY COLUMN `entity_type` VARCHAR(100) NULL DEFAULT NULL COMMENT 'Entity type (node, user, etc.)';
ALTER TABLE `file_managed` MODIFY COLUMN `entity_id` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT 'Entity ID this file belongs to';
ALTER TABLE `file_managed` MODIFY COLUMN `bundle` VARCHAR(100) NULL DEFAULT NULL COMMENT 'Bundle/type for entity-specific files';
ALTER TABLE `file_managed` MODIFY COLUMN `langcode` VARCHAR(12) NOT NULL DEFAULT 'en' COMMENT 'Language code for multilingual support';
ALTER TABLE `file_managed` MODIFY COLUMN `alt` VARCHAR(512) NULL DEFAULT NULL COMMENT 'Alt text for accessibility';
ALTER TABLE `file_managed` MODIFY COLUMN `title` VARCHAR(1024) NULL DEFAULT NULL COMMENT 'Title attribute for files';
ALTER TABLE `file_managed` MODIFY COLUMN `description` TEXT NULL DEFAULT NULL COMMENT 'File description';
ALTER TABLE `file_managed` MODIFY COLUMN `width` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Width for images/videos';
ALTER TABLE `file_managed` MODIFY COLUMN `height` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Height for images/videos';
ALTER TABLE `file_managed` MODIFY COLUMN `duration` DECIMAL(10,3) NULL DEFAULT NULL COMMENT 'Duration for audio/video in seconds';
ALTER TABLE `file_managed` MODIFY COLUMN `checksum` VARCHAR(64) NULL DEFAULT NULL COMMENT 'SHA-256 checksum for integrity';
ALTER TABLE `file_managed` MODIFY COLUMN `metadata` JSON NULL DEFAULT NULL COMMENT 'Additional metadata as JSON';
ALTER TABLE `file_managed` MODIFY COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation timestamp';
ALTER TABLE `file_managed` MODIFY COLUMN `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Record update timestamp';
ALTER TABLE `file_managed` MODIFY COLUMN `deleted_at` DATETIME NULL DEFAULT NULL COMMENT 'Soft delete timestamp';
