-- =========================================================
-- WIKI PAGES TABLE
-- =========================================================

CREATE TABLE `wiki_pages` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    -- Identity
    `uuid` VARCHAR(36) NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `slug` VARCHAR(255) NOT NULL,

    -- Content
    `summary` TEXT NULL,
    `content` LONGTEXT NULL,
    `css` LONGTEXT NULL,

    -- Structure
    `parent_id` BIGINT UNSIGNED NULL,
    `sort_order` INT NOT NULL DEFAULT 0,

    -- Metadata
    `author_id` BIGINT UNSIGNED NOT NULL,
    `status` ENUM(
        'draft',
        'published',
        'archived',
        'deleted'
    ) NOT NULL DEFAULT 'draft',

    `visibility` ENUM(
        'public',
        'private',
        'internal'
    ) NOT NULL DEFAULT 'public',

    -- Revision support
    `revision` INT UNSIGNED NOT NULL DEFAULT 1,

    -- SEO
    `meta_title` VARCHAR(255) NULL,
    `meta_description` TEXT NULL,

    -- Dates
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,
    `published_at` TIMESTAMP NULL,
    `deleted_at` TIMESTAMP NULL,

    PRIMARY KEY (`id`),

    UNIQUE KEY `uniq_wiki_uuid` (`uuid`),
    UNIQUE KEY `uniq_wiki_slug` (`slug`),

    KEY `idx_wiki_title` (`title`),
    KEY `idx_wiki_slug` (`slug`),
    KEY `idx_wiki_author` (`author_id`),
    KEY `idx_wiki_parent` (`parent_id`),
    KEY `idx_wiki_status` (`status`),
    KEY `idx_wiki_visibility` (`visibility`),
    KEY `idx_wiki_created` (`created_at`),
    KEY `idx_wiki_updated` (`updated_at`),

    FULLTEXT KEY `ft_wiki_search`
        (`title`, `summary`, `content`),

    CONSTRAINT `fk_wiki_author`
        FOREIGN KEY (`author_id`)
        REFERENCES `users` (`id`)
        ON DELETE CASCADE,

    CONSTRAINT `fk_wiki_parent`
        FOREIGN KEY (`parent_id`)
        REFERENCES `wiki_pages` (`id`)
        ON DELETE SET NULL

);

-- =========================================================
-- WIKI PAGE REVISIONS
-- =========================================================

CREATE TABLE `wiki_page_revisions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    `wiki_page_id` BIGINT UNSIGNED NOT NULL,
    `revision_number` INT UNSIGNED NOT NULL,

    `title` VARCHAR(255) NOT NULL,
    `summary` TEXT NULL,
    `content` LONGTEXT NULL,
    `css` LONGTEXT NULL,

    `author_id` BIGINT UNSIGNED NOT NULL,

    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),

    UNIQUE KEY `uniq_page_revision`
        (`wiki_page_id`, `revision_number`),

    KEY `idx_revision_page` (`wiki_page_id`),
    KEY `idx_revision_author` (`author_id`),

    CONSTRAINT `fk_revision_page`
        FOREIGN KEY (`wiki_page_id`)
        REFERENCES `wiki_pages` (`id`)
        ON DELETE CASCADE,

    CONSTRAINT `fk_revision_author`
        FOREIGN KEY (`author_id`)
        REFERENCES `users` (`id`)
        ON DELETE CASCADE

);