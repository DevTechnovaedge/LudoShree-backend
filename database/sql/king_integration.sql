-- =====================================================================
-- King WebSocket (Daddy King) integration - run this ONCE on the server
-- =====================================================================

-- 1. Link game challenges to King network tables
ALTER TABLE `game_challenges`
  ADD COLUMN `game_source` VARCHAR(20) NOT NULL DEFAULT 'local' COMMENT 'local | daddy_king' AFTER `status`,
  ADD COLUMN `king_table_id` VARCHAR(50) NULL DEFAULT NULL COMMENT 'King network table id e.g. DK-2-3' AFTER `game_source`,
  ADD COLUMN `king_sync_status` VARCHAR(20) NULL DEFAULT NULL COMMENT 'pending | synced | failed' AFTER `king_table_id`,
  ADD INDEX `idx_gc_king_table_id` (`king_table_id`);

-- 2. Proxy (ghost) accounts for Daddy King network players
ALTER TABLE `users`
  ADD COLUMN `is_king_player` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = proxy account for a Daddy King network player',
  ADD COLUMN `king_player_id` VARCHAR(64) NULL DEFAULT NULL COMMENT 'External player id e.g. 2-5',
  ADD UNIQUE INDEX `idx_users_king_player_id` (`king_player_id`);

-- 3. Mirror of every table known on the King network
CREATE TABLE `king_tables` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `king_table_id` VARCHAR(50) NOT NULL,
  `origin` VARCHAR(10) NOT NULL DEFAULT 'remote' COMMENT 'local = created by us, remote = created on another platform',
  `game_challenge_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `status` VARCHAR(20) NOT NULL DEFAULT 'Pending' COMMENT 'Pending | Start | View | Completed | Deleted | Missing',
  `created_by_id` VARCHAR(64) NULL DEFAULT NULL,
  `created_by_name` VARCHAR(191) NULL DEFAULT NULL,
  `joined_by_id` VARCHAR(64) NULL DEFAULT NULL,
  `joined_by_name` VARCHAR(191) NULL DEFAULT NULL,
  `room_code` VARCHAR(20) NULL DEFAULT NULL,
  `creator_result` VARCHAR(20) NULL DEFAULT NULL,
  `joiner_result` VARCHAR(20) NULL DEFAULT NULL,
  `raw` TEXT NULL DEFAULT NULL,
  `last_seen_at` DATETIME NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_kt_king_table_id` (`king_table_id`),
  KEY `idx_kt_game_challenge_id` (`game_challenge_id`),
  KEY `idx_kt_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Outbound message queue (HTTP requests only insert here; the daemon sends)
CREATE TABLE `king_outbox` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event` VARCHAR(50) NOT NULL,
  `payload` TEXT NULL DEFAULT NULL,
  `king_table_id` VARCHAR(50) NULL DEFAULT NULL,
  `game_challenge_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `acting_user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending | sent | success | failed | skipped',
  `attempts` INT NOT NULL DEFAULT 0,
  `response` TEXT NULL DEFAULT NULL,
  `error` VARCHAR(500) NULL DEFAULT NULL,
  `available_at` DATETIME NULL DEFAULT NULL,
  `sent_at` DATETIME NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ko_status_available` (`status`, `available_at`),
  KEY `idx_ko_king_table_id` (`king_table_id`),
  KEY `idx_ko_game_challenge_id` (`game_challenge_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Event / consistency audit log (admin panel reads warnings + errors here)
CREATE TABLE `king_event_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `direction` VARCHAR(10) NOT NULL DEFAULT 'in' COMMENT 'in | out | sys',
  `uri` VARCHAR(64) NULL DEFAULT NULL,
  `level` VARCHAR(10) NOT NULL DEFAULT 'info' COMMENT 'info | warning | error',
  `message` VARCHAR(500) NULL DEFAULT NULL,
  `payload` MEDIUMTEXT NULL DEFAULT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_kel_created_at` (`created_at`),
  KEY `idx_kel_level` (`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
