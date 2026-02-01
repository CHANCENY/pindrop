-- Content Node Data Table
-- Core content storage for all content types
-- This table stores the fundamental properties that all content entities share

-- Ensure users table exists first
-- This table should be created after the users table

CREATE TABLE `node_data` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `uuid` VARCHAR(36) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NULL,
    `content` LONGTEXT NULL,
    `excerpt` TEXT NULL,
    `author_id` BIGINT UNSIGNED NOT NULL,
    `node_type` VARCHAR(100) NOT NULL DEFAULT 'page',
    `status` ENUM('draft', 'published', 'archived', 'trash') NOT NULL DEFAULT 'draft',
    `is_published` BOOLEAN NOT NULL DEFAULT FALSE,
    `featured` BOOLEAN NOT NULL DEFAULT FALSE,
    `sticky` BOOLEAN NOT NULL DEFAULT FALSE,
    `allow_comments` BOOLEAN NOT NULL DEFAULT TRUE,
    `password` VARCHAR(255) NULL,
    `template` VARCHAR(100) NULL,
    `language` VARCHAR(10) NOT NULL DEFAULT 'en',
    `parent_id` INT UNSIGNED NULL,
    `order` INT NOT NULL DEFAULT 0,
    `menu_order` INT NOT NULL DEFAULT 0,
    `meta_title` VARCHAR(255) NULL,
    `meta_description` TEXT NULL,
    `meta_keywords` VARCHAR(255) NULL,
    `canonical_url` VARCHAR(500) NULL,
    `redirect_url` VARCHAR(500) NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `published_at` TIMESTAMP NULL,
    `deleted_at` TIMESTAMP NULL,
    
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_uuid` (`uuid`),
    UNIQUE KEY `unique_slug` (`slug`),
    KEY `idx_author_id` (`author_id`),
    KEY `idx_node_type` (`node_type`),
    KEY `idx_status` (`status`),
    KEY `idx_is_published` (`is_published`),
    KEY `idx_featured` (`featured`),
    KEY `idx_sticky` (`sticky`),
    KEY `idx_parent_id` (`parent_id`),
    KEY `idx_order` (`order`),
    KEY `idx_menu_order` (`menu_order`),
    KEY `idx_language` (`language`),
    KEY `idx_created_at` (`created_at`),
    KEY `idx_updated_at` (`updated_at`),
    KEY `idx_published_at` (`published_at`),
    KEY `idx_deleted_at` (`deleted_at`),
    KEY `idx_node_type_status` (`node_type`, `status`),
    KEY `idx_author_status` (`author_id`, `status`),
    KEY `idx_published_featured` (`is_published`, `featured`),
    KEY `idx_language_status` (`language`, `status`),
    
    FOREIGN KEY (`author_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`parent_id`) REFERENCES `node_data` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert indexes for better search performance
CREATE FULLTEXT INDEX `ft_title_content` ON `node_data` (`title`, `content`);
CREATE FULLTEXT INDEX `ft_title` ON `node_data` (`title`);
CREATE FULLTEXT INDEX `ft_content` ON `node_data` (`content`);

-- Triggers for automatic slug generation
DELIMITER //
CREATE TRIGGER `node_data_before_insert` 
BEFORE INSERT ON `node_data`
FOR EACH ROW
BEGIN
    -- Generate UUID if not provided
    IF NEW.uuid IS NULL OR NEW.uuid = '' THEN
        SET NEW.uuid = UUID();
    END IF;
    
    -- Generate slug from title if not provided
    IF NEW.slug IS NULL OR NEW.slug = '' THEN
        SET NEW.slug = LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(NEW.title, ' ', '-'), '.', ''), ',', ''), '/', ''), '_', '-'));
        -- Remove multiple consecutive dashes
        SET NEW.slug = REGEXP_REPLACE(NEW.slug, '-+', '-');
        -- Remove leading/trailing dashes
        SET NEW.slug = TRIM(BOTH '-' FROM NEW.slug);
        
        -- Ensure unique slug by adding number if needed
        DECLARE slug_count INT DEFAULT 0;
        DECLARE original_slug VARCHAR(255) DEFAULT NEW.slug;
        WHILE slug_count > 0 OR (SELECT COUNT(*) FROM node_data WHERE slug = NEW.slug AND id != IFNULL(NEW.id, 0)) > 0 DO
            SET slug_count = slug_count + 1;
            SET NEW.slug = CONCAT(original_slug, '-', slug_count);
        END WHILE;
    END IF;
    
    -- Set published_at when publishing
    IF NEW.is_published = TRUE AND NEW.published_at IS NULL THEN
        SET NEW.published_at = CURRENT_TIMESTAMP;
    END IF;
    
    -- Set status based on is_published flag
    IF NEW.is_published = TRUE AND NEW.status = 'draft' THEN
        SET NEW.status = 'published';
    END IF;
END//
DELIMITER ;

DELIMITER //
CREATE TRIGGER `node_data_before_update` 
BEFORE UPDATE ON `node_data`
FOR EACH ROW
BEGIN
    -- Generate slug from title if slug is empty
    IF NEW.slug IS NULL OR NEW.slug = '' THEN
        SET NEW.slug = LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(NEW.title, ' ', '-'), '.', ''), ',', ''), '/', ''), '_', '-'));
        -- Remove multiple consecutive dashes
        SET NEW.slug = REGEXP_REPLACE(NEW.slug, '-+', '-');
        -- Remove leading/trailing dashes
        SET NEW.slug = TRIM(BOTH '-' FROM NEW.slug);
        
        -- Ensure unique slug by adding number if needed
        DECLARE slug_count INT DEFAULT 0;
        DECLARE original_slug VARCHAR(255) DEFAULT NEW.slug;
        WHILE slug_count > 0 OR (SELECT COUNT(*) FROM node_data WHERE slug = NEW.slug AND id != NEW.id) > 0 DO
            SET slug_count = slug_count + 1;
            SET NEW.slug = CONCAT(original_slug, '-', slug_count);
        END WHILE;
    END IF;
    
    -- Update published_at when publishing for first time
    IF NEW.is_published = TRUE AND OLD.is_published = FALSE AND NEW.published_at IS NULL THEN
        SET NEW.published_at = CURRENT_TIMESTAMP;
    END IF;
    
    -- Update status based on is_published flag
    IF NEW.is_published = TRUE AND NEW.status = 'draft' THEN
        SET NEW.status = 'published';
    END IF;
    
    -- Clear published_at when unpublishing
    IF NEW.is_published = FALSE AND OLD.is_published = TRUE THEN
        SET NEW.status = 'draft';
        -- Keep published_at for historical reference
    END IF;
END//
DELIMITER ;
