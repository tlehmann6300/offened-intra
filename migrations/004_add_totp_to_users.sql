-- Migration: Add TOTP 2FA support to users table
-- Purpose: Store TOTP secret keys for two-factor authentication
-- Date: 2026-01-31

-- Add totp_secret column to users table
ALTER TABLE `users` 
ADD COLUMN `totp_secret` VARCHAR(32) DEFAULT NULL COMMENT 'Base32-encoded TOTP secret for 2FA',
ADD COLUMN `totp_enabled` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0 = disabled, 1 = enabled',
ADD COLUMN `totp_verified_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'When 2FA was first verified';

-- Add index for TOTP lookups
CREATE INDEX `idx_totp_enabled` ON `users` (`totp_enabled`);
