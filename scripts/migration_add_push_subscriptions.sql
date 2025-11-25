-- Migration to add table for storing Web Push subscriptions

CREATE TABLE IF NOT EXISTS `_push_subscriptions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `endpoint` TEXT NOT NULL,
    `p256dh` VARCHAR(255) NOT NULL,
    `auth` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `endpoint_idx` (`endpoint`(512)),
    FOREIGN KEY (`user_id`) REFERENCES `_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `_push_subscriptions` COMMENT = 'Stores Web Push notification subscriptions for users to enable auto-logout.';

INSERT INTO `_migrations` (`name`) VALUES ('migration_add_push_subscriptions.sql');
