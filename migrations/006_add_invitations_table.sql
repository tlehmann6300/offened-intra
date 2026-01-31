-- Migration: Add invitations table for token-based registration
-- Purpose: Store invitation tokens for secure user registration
-- Date: 2026-01-31

-- Create invitations table
CREATE TABLE IF NOT EXISTS `invitations` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL COMMENT 'Email address of the invited user',
  `token` VARCHAR(64) NOT NULL COMMENT 'Cryptographic token (SHA256 hash)',
  `role` VARCHAR(50) NOT NULL DEFAULT 'alumni' COMMENT 'Role to assign after registration',
  `created_by` INT(11) NOT NULL COMMENT 'User ID of admin who created the invitation',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When invitation was created',
  `expires_at` TIMESTAMP NOT NULL COMMENT 'When invitation expires',
  `accepted_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'When invitation was accepted (NULL = pending)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_token` (`token`),
  KEY `idx_email` (`email`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_expires_at` (`expires_at`),
  KEY `idx_accepted_at` (`accepted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Token-based invitation system for user registration';

-- Add index for finding pending invitations
CREATE INDEX `idx_pending_invitations` ON `invitations` (`email`, `accepted_at`, `expires_at`);
