<?php
define('ROOT_PATH', __DIR__);
define('APP_PATH', ROOT_PATH . '/app');
define('CONFIG_PATH', APP_PATH . '/config');
require_once APP_PATH . '/core/Autoloader.php';
$autoloader = new \KronoConnect\Core\Autoloader();
$autoloader->register();

$db = \KronoConnect\Core\Database::getInstance()->getRawPdo();
$db->exec("
CREATE TABLE IF NOT EXISTS `kconnect_user_mfa_recovery_codes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `code_hash` VARCHAR(255) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `used_at` DATETIME NULL,
    FOREIGN KEY (`user_id`) REFERENCES `kconnect_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");
echo 'Migration 023 OK';
