-- Migration: Add Alumni Validation Fields
-- Date: 2026-01-31
-- Purpose: Add fields to support alumni workflow and validation
--
-- This migration adds:
-- 1. is_alumni_validated: Boolean flag to track if alumni status has been validated by board
-- 2. alumni_status_requested_at: Timestamp to track when alumni transition was requested
--
-- Role Hierarchy Implementation:
-- Admin/1V-3V (vorstand) > Ressortleiter > Mitglied > Alumni
--
-- Workflow:
-- 1. Member requests alumni status (role changes to 'alumni', is_alumni_validated = FALSE)
-- 2. Access to active projects is immediately revoked
-- 3. Admin/Vorstand validates the profile
-- 4. Profile becomes visible in directory after validation (is_alumni_validated = TRUE)

-- Add is_alumni_validated field to users table
-- Default FALSE (0) - alumni must be explicitly validated
ALTER TABLE users 
ADD COLUMN is_alumni_validated TINYINT(1) DEFAULT 0 
COMMENT 'Flag indicating if alumni status has been validated by board (0=pending, 1=validated)';

-- Add alumni_status_requested_at timestamp
-- NULL for non-alumni or alumni who transitioned before this feature
ALTER TABLE users 
ADD COLUMN alumni_status_requested_at TIMESTAMP NULL DEFAULT NULL 
COMMENT 'Timestamp when user requested alumni status transition';

-- Create index for efficient alumni validation queries
-- Note: This index helps optimize queries filtering by role='alumni' and is_alumni_validated
CREATE INDEX idx_alumni_validation 
ON users(role, is_alumni_validated);

-- Update existing alumni users to validated status (grandfathered in)
-- This prevents breaking existing alumni accounts
UPDATE users 
SET is_alumni_validated = 1, 
    alumni_status_requested_at = NOW()
WHERE role = 'alumni';
