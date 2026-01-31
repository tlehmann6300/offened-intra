-- Migration: Add login_attempts table for rate limiting
-- Purpose: Track failed login attempts for IP-based and account-based rate limiting
-- Date: 2026-01-31

-- Create login_attempts table
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip_address` VARCHAR(45) NOT NULL COMMENT 'IPv4 or IPv6 address',
  `email` VARCHAR(255) DEFAULT NULL COMMENT 'Email address (if provided)',
  `attempt_time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `success` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0 = failed, 1 = successful',
  `user_agent` VARCHAR(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_ip_time` (`ip_address`, `attempt_time`),
  KEY `idx_email_time` (`email`, `attempt_time`),
  KEY `idx_attempt_time` (`attempt_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Login attempt tracking for rate limiting';

-- Create index for cleanup queries (remove old entries)
CREATE INDEX `idx_cleanup` ON `login_attempts` (`attempt_time`, `success`);
