-- Migration: Add birthday-related fields to users table
-- Purpose: Support birthday notifications and privacy settings
-- Date: 2026-01-31

-- Add birthdate column to store user's birth date
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS birthdate DATE NULL COMMENT 'User birth date (day/month/year)';

-- Add notify_birthday column to store privacy preference
-- Default TRUE means birthdays are visible on dashboard by default (opt-out model)
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS notify_birthday TINYINT(1) DEFAULT 1 COMMENT 'Privacy: Show birthday on dashboard (1=yes, 0=no)';

-- Add index for efficient birthday queries (only month and day matter)
-- This helps the birthday check cron job run efficiently
ALTER TABLE users
ADD INDEX IF NOT EXISTS idx_users_birthdate (birthdate);

-- Verification queries (optional - for manual testing after migration)
-- SELECT COUNT(*) FROM users WHERE birthdate IS NOT NULL;
-- SELECT firstname, lastname, birthdate, notify_birthday FROM users WHERE birthdate IS NOT NULL LIMIT 5;
