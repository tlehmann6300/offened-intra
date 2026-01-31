-- ============================================================================
-- Migration: 002_add_search_indexes.sql
-- Purpose: Add database indexes to improve search performance
-- Date: 2026-01-31
-- ============================================================================
-- 
-- This migration creates indexes on frequently searched columns to avoid
-- full table scans and improve query performance in global_search.php
-- 
-- Indexes created:
-- 1. inventory.name - for inventory name searches
-- 2. users.firstname, users.lastname - for user name searches  
-- 3. news.title - for news title searches
--
-- These indexes will significantly improve performance of LIKE queries
-- when searching across multiple tables
-- ============================================================================

-- Set SQL mode and character set
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

-- ============================================================================
-- INDEX 1: inventory.name
-- ============================================================================
-- Check if index exists before creating
SET @index_exists_inventory_name = (
    SELECT COUNT(1) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE table_schema = DATABASE() 
    AND table_name = 'inventory' 
    AND index_name = 'idx_inventory_name'
);

-- Create index if it doesn't exist
SET @sql_inventory_name = IF(
    @index_exists_inventory_name = 0,
    'CREATE INDEX idx_inventory_name ON inventory(name(100))',
    'SELECT "Index idx_inventory_name already exists on inventory.name"'
);

PREPARE stmt FROM @sql_inventory_name;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- INDEX 2: users.firstname
-- ============================================================================
-- Check if index exists before creating
SET @index_exists_users_firstname = (
    SELECT COUNT(1) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE table_schema = DATABASE() 
    AND table_name = 'users' 
    AND index_name = 'idx_users_firstname'
);

-- Create index if it doesn't exist
SET @sql_users_firstname = IF(
    @index_exists_users_firstname = 0,
    'CREATE INDEX idx_users_firstname ON users(firstname(50))',
    'SELECT "Index idx_users_firstname already exists on users.firstname"'
);

PREPARE stmt FROM @sql_users_firstname;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- INDEX 3: users.lastname
-- ============================================================================
-- Check if index exists before creating
SET @index_exists_users_lastname = (
    SELECT COUNT(1) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE table_schema = DATABASE() 
    AND table_name = 'users' 
    AND index_name = 'idx_users_lastname'
);

-- Create index if it doesn't exist
SET @sql_users_lastname = IF(
    @index_exists_users_lastname = 0,
    'CREATE INDEX idx_users_lastname ON users(lastname(50))',
    'SELECT "Index idx_users_lastname already exists on users.lastname"'
);

PREPARE stmt FROM @sql_users_lastname;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- INDEX 4: news.title
-- ============================================================================
-- Check if index exists before creating
SET @index_exists_news_title = (
    SELECT COUNT(1) 
    FROM INFORMATION_SCHEMA.STATISTICS 
    WHERE table_schema = DATABASE() 
    AND table_name = 'news' 
    AND index_name = 'idx_news_title'
);

-- Create index if it doesn't exist
SET @sql_news_title = IF(
    @index_exists_news_title = 0,
    'CREATE INDEX idx_news_title ON news(title(100))',
    'SELECT "Index idx_news_title already exists on news.title"'
);

PREPARE stmt FROM @sql_news_title;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- VERIFICATION
-- ============================================================================
-- Display all indexes created by this migration
SELECT 
    'Verification: Indexes created/verified successfully' AS status,
    table_name,
    index_name,
    column_name,
    seq_in_index,
    CASE 
        WHEN non_unique = 0 THEN 'UNIQUE'
        ELSE 'NON-UNIQUE'
    END AS index_type
FROM INFORMATION_SCHEMA.STATISTICS
WHERE table_schema = DATABASE()
AND (
    (table_name = 'inventory' AND index_name = 'idx_inventory_name')
    OR (table_name = 'users' AND index_name = 'idx_users_firstname')
    OR (table_name = 'users' AND index_name = 'idx_users_lastname')
    OR (table_name = 'news' AND index_name = 'idx_news_title')
)
ORDER BY table_name, index_name, seq_in_index;
