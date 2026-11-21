CREATE TABLE IF NOT EXISTS `local_mails` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `subject`     VARCHAR(998) NOT NULL DEFAULT '',
    `body`        MEDIUMTEXT   NOT NULL,
    `to_address`  VARCHAR(320) NOT NULL DEFAULT '',
    `reply_to`    VARCHAR(320) NOT NULL DEFAULT '',
    `cc`          TEXT         NOT NULL,
    `bcc`         TEXT         NOT NULL,
    `attachments` TEXT         NOT NULL,
    `is_html`     TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`  DATETIME     NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;