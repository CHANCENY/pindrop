CREATE TABLE `general_permissions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `role_key` VARCHAR(20) NOT NULL,
    `permission` JSON NULL,

    PRIMARY KEY (`id`)
);