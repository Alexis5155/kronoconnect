-- KronoConnect — Schéma Complet Unifié

CREATE TABLE IF NOT EXISTS `{PREFIX}sso_clients` (
    `id`                INT UNSIGNED                        NOT NULL AUTO_INCREMENT,
    `client_id`         VARCHAR(100)                        NOT NULL,
    `client_secret`     VARCHAR(255)                        NOT NULL,
    `client_secret_raw` VARCHAR(64)                         NULL,
    `name`              VARCHAR(200)                        NOT NULL,
    `redirect_uris`     JSON                                NOT NULL,
    `logout_url`        VARCHAR(500)                        NULL,
    `access_mode`       ENUM('open', 'group', 'manual')     NOT NULL DEFAULT 'open',
    `app_name`          VARCHAR(150)                        NULL,
    `app_description`   TEXT                                NULL,
    `app_icon`          VARCHAR(50)                         NULL,
    `app_color`         VARCHAR(7)                          NOT NULL DEFAULT '#3B82F6',
    `allowed_ips`       TEXT                                NULL COMMENT 'Liste d\'IPs ou de blocs CIDR séparés par des virgules',
    `manifest_synced_at` DATETIME                           NULL,
    `created_at`        DATETIME                            NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `sso_clients_client_id_unique` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}services` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `parent_id`   INT UNSIGNED NULL,
    `name`        VARCHAR(150) NOT NULL,
    `description` TEXT         NULL,
    `order_index` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_services_parent` FOREIGN KEY (`parent_id`) REFERENCES `{PREFIX}services` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}users` (
    `id`                    INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `email`                 VARCHAR(255)  NOT NULL,
    `password`              VARCHAR(255)  NOT NULL,
    `mfa_secret`            VARCHAR(255)  NULL,
    `mfa_enabled`           TINYINT(1)    NOT NULL DEFAULT 0,
    `nom`                   VARCHAR(100)  NOT NULL,
    `prenom`                VARCHAR(100)  NOT NULL,
    `phone`                 VARCHAR(20)   NULL,
    `service_id`            INT UNSIGNED  NULL,
    `is_active`             TINYINT(1)    NOT NULL DEFAULT 1,
    `status`                ENUM('actif','attente_validation','verification_mail','desactive') NOT NULL DEFAULT 'actif',
    `remember_token`        VARCHAR(64)   NULL,
    `sso_token`             VARCHAR(64)   NULL,
    `theme`                 VARCHAR(20)   NOT NULL DEFAULT 'system',
    `can_change_email`      TINYINT(1)    NOT NULL DEFAULT 0,
    `reset_token`           VARCHAR(64)   NULL,
    `reset_token_expires_at` DATETIME     NULL,
    `verification_token`    VARCHAR(10)   NULL,
    `created_at`            DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_activity_at`      DATETIME      NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `users_email_unique` (`email`),
    KEY `idx_users_status` (`status`),
    CONSTRAINT `fk_users_service` FOREIGN KEY (`service_id`) REFERENCES `{PREFIX}services` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}sso_auth_codes` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `client_id`  VARCHAR(100) NOT NULL,
    `user_id`    INT UNSIGNED NOT NULL,
    `code`       VARCHAR(64)  NOT NULL,
    `expires_at` DATETIME     NOT NULL,
    `used`       TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `sso_auth_codes_code_unique` (`code`),
    CONSTRAINT `fk_auth_codes_user`
        FOREIGN KEY (`user_id`) REFERENCES `{PREFIX}users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}sso_connection_logs` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `client_id`  VARCHAR(80)  NOT NULL,
    `ip`         VARCHAR(45)  NOT NULL DEFAULT '',
    `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_user_id`    (`user_id`),
    KEY `idx_client_id`  (`client_id`),
    KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}settings` (
    `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `setting_key`         VARCHAR(100) NOT NULL,
    `setting_value`       TEXT         NOT NULL,
    `allow_self_register` TINYINT(1)   NOT NULL DEFAULT 1,
    `updated_at`          DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `settings_key_unique` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `{PREFIX}settings` (`setting_key`, `setting_value`) VALUES
    ('app_name',          'KronoConnect'),
    ('subtitle',          'Serveur d\'authentification centralisé'),
    ('registration',      '1'),
    ('captcha_provider',  'none'),
    ('manual_approval',   '0'),
    ('gdpr_retention_accounts_months', '36'),
    ('gdpr_retention_logs_months', '6'),
    ('gdpr_privacy_url',  ''),
    ('gdpr_legal_url',    ''),
    ('portal_hero_sub',   'Accéder à toutes vos applications métier avec un seul compte.');

CREATE TABLE IF NOT EXISTS `{PREFIX}groups` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tech_name`   VARCHAR(50)  UNIQUE NULL,
    `name`        VARCHAR(100) NOT NULL,
    `description` TEXT         NULL,
    `is_system`   TINYINT(1)   NOT NULL DEFAULT 0,
    `require_mfa` TINYINT(1)   NOT NULL DEFAULT 0,
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `{PREFIX}groups` (`tech_name`, `name`, `description`, `is_system`) VALUES
    ('super_admin', 'Super-administrateur', 'A un accès complet à toutes les applications et toutes les permissions.', 1),
    ('admin',       'Administrateur',       'Peut administrer KronoConnect et gérer les utilisateurs.',                1),
    ('user',        'Utilisateur',          'Compte par défaut avec accès restreint aux applications autorisées.',     1);

CREATE TABLE IF NOT EXISTS `{PREFIX}group_members` (
    `group_id` INT UNSIGNED NOT NULL,
    `user_id`  INT UNSIGNED NOT NULL,
    PRIMARY KEY (`group_id`, `user_id`),
    FOREIGN KEY (`group_id`) REFERENCES `{PREFIX}groups`  (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`)  REFERENCES `{PREFIX}users`   (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}group_app_access` (
    `group_id`  INT UNSIGNED NOT NULL,
    `client_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`group_id`, `client_id`),
    FOREIGN KEY (`group_id`)  REFERENCES `{PREFIX}groups`      (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`client_id`) REFERENCES `{PREFIX}sso_clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}user_app_access` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED NOT NULL,
    `client_id`  INT UNSIGNED NOT NULL,
    `granted_by` INT UNSIGNED NOT NULL,
    `granted_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_access` (`user_id`, `client_id`),
    FOREIGN KEY (`user_id`)   REFERENCES `{PREFIX}users`       (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`client_id`) REFERENCES `{PREFIX}sso_clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}permissions` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `client_id`   INT UNSIGNED NOT NULL,
    `perm_key`    VARCHAR(100) NOT NULL,
    `label`       VARCHAR(150) NOT NULL,
    `description` TEXT         NULL,
    `parent_key`  VARCHAR(100) NULL,
    `active`      TINYINT(1)   NOT NULL DEFAULT 1,
    `synced_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_perm` (`client_id`, `perm_key`),
    FOREIGN KEY (`client_id`) REFERENCES `{PREFIX}sso_clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}group_permissions` (
    `id`        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `group_id`  INT UNSIGNED NOT NULL,
    `client_id` INT UNSIGNED NULL,
    `perm_key`  VARCHAR(100) NOT NULL,
    UNIQUE KEY `uk_group_client_perm` (`group_id`, `client_id`, `perm_key`),
    FOREIGN KEY (`group_id`)  REFERENCES `{PREFIX}groups`      (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`client_id`) REFERENCES `{PREFIX}sso_clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}user_permissions` (
    `id`        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`   INT UNSIGNED NOT NULL,
    `client_id` INT UNSIGNED NULL,
    `perm_key`  VARCHAR(100) NOT NULL,
    `granted`   TINYINT(1)   NOT NULL DEFAULT 1,
    UNIQUE KEY `uk_user_client_perm` (`user_id`, `client_id`, `perm_key`),
    FOREIGN KEY (`user_id`)   REFERENCES `{PREFIX}users`       (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`client_id`) REFERENCES `{PREFIX}sso_clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}api_nonces` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `nonce`      VARCHAR(64)     NOT NULL,
    `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `api_nonces_nonce_unique` (`nonce`),
    KEY `idx_nonces_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}api_logs` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ip_address` VARCHAR(45)     NOT NULL,
    `client_id`  VARCHAR(100)    NULL,
    `endpoint`   VARCHAR(255)    NOT NULL,
    `status`     INT UNSIGNED    NOT NULL,
    `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}logs` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `level`      VARCHAR(10)     NOT NULL DEFAULT 'info',
    `user_id`    INT UNSIGNED    NULL,
    `action`     VARCHAR(100)    NULL,
    `message`    TEXT            NULL,
    `context`    JSON            NULL,
    `ip_address` VARCHAR(45)     NULL,
    `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_level`      (`level`),
    KEY `idx_created_at` (`created_at`),
    FOREIGN KEY (`user_id`) REFERENCES `{PREFIX}users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}notifications` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`    INT UNSIGNED    NOT NULL,
    `client_id`  VARCHAR(100)    NULL,
    `is_hub`     TINYINT(1)      NOT NULL DEFAULT 0,
    `type`       VARCHAR(50)     NOT NULL DEFAULT 'info',
    `title`      VARCHAR(255)    NOT NULL,
    `message`    TEXT            NOT NULL,
    `url`        VARCHAR(500)    NULL,
    `read_at`    DATETIME        NULL,
    `created_at` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_notif_user_read` (`user_id`, `read_at`),
    KEY `idx_notif_client`    (`client_id`),
    KEY `idx_notif_created`   (`created_at`),
    KEY `idx_notif_user_read_recent` (`user_id`, `read_at`, `created_at`),
    CONSTRAINT `fk_notif_user`
        FOREIGN KEY (`user_id`) REFERENCES `{PREFIX}users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_notif_client`
        FOREIGN KEY (`client_id`) REFERENCES `{PREFIX}sso_clients` (`client_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}custom_links` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`       VARCHAR(255) NOT NULL,
    `url`         VARCHAR(255) NOT NULL,
    `icon`        VARCHAR(100) DEFAULT 'link-45deg',
    `color`       VARCHAR(7)   DEFAULT '#3b5fc0',
    `description` TEXT         NULL,
    `access_mode` ENUM('open', 'group', 'manual') NOT NULL DEFAULT 'open',
    `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}custom_link_group_access` (
    `link_id`  INT UNSIGNED NOT NULL,
    `group_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`link_id`, `group_id`),
    CONSTRAINT `fk_clga_link`  FOREIGN KEY (`link_id`)  REFERENCES `{PREFIX}custom_links` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_clga_group` FOREIGN KEY (`group_id`) REFERENCES `{PREFIX}groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}custom_link_user_access` (
    `link_id` INT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`link_id`, `user_id`),
    CONSTRAINT `fk_clua_link` FOREIGN KEY (`link_id`) REFERENCES `{PREFIX}custom_links` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_clua_user` FOREIGN KEY (`user_id`) REFERENCES `{PREFIX}users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}user_portal_order` (
    `user_id`   INT UNSIGNED NOT NULL,
    `item_type` ENUM('app', 'link') NOT NULL,
    `item_id`   VARCHAR(255) NOT NULL,
    `position`  INT UNSIGNED NOT NULL,
    PRIMARY KEY (`user_id`, `item_type`, `item_id`),
    CONSTRAINT `fk_upo_user` FOREIGN KEY (`user_id`) REFERENCES `{PREFIX}users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}kronoconnect_files` (
  `uuid` char(36) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `extension` varchar(10) NOT NULL,
  `size` int(11) NOT NULL,
  `module` varchar(50) NOT NULL,
  `uploaded_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`uuid`),
  KEY `module_idx` (`module`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}user_mfa_recovery_codes` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `code_hash` VARCHAR(255) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `used_at` DATETIME NULL,
    CONSTRAINT `fk_mfa_recovery_user` FOREIGN KEY (`user_id`) REFERENCES `{PREFIX}users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `{PREFIX}user_webauthn_credentials` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT UNSIGNED NOT NULL,
    `credential_id` VARCHAR(255) NOT NULL,
    `public_key` TEXT NOT NULL,
    `counter` INT UNSIGNED NOT NULL DEFAULT 0,
    `name` VARCHAR(100) NOT NULL DEFAULT 'Clé de sécurité',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_credential_id` (`credential_id`),
    CONSTRAINT `fk_webauthn_user` FOREIGN KEY (`user_id`) REFERENCES `{PREFIX}users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
