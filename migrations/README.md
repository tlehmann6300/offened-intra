# Database Migrations

## Overview
This directory contains SQL migration scripts for database schema changes and optimizations.

## Migration Files

### 001_add_purchase_price_to_inventory.sql
- **Purpose**: Add the `purchase_price` column to the `inventory` table
- **Column**: `purchase_price DECIMAL(10,2)`
- **Use Case**: Store the purchase price per item in euros

### 002_add_search_indexes.sql
- **Purpose**: Add database indexes to improve search performance
- **Indexes Created**:
  - `idx_inventory_name` on `inventory(name)` - for inventory name searches
  - `idx_users_firstname` on `users(firstname)` - for user firstname searches
  - `idx_users_lastname` on `users(lastname)` - for user lastname searches
  - `idx_news_title` on `news(title)` - for news title searches
- **Use Case**: Optimize global search performance by avoiding full table scans

## How to Run

### Option 1: Using MySQL Command Line
```bash
mysql -u username -p database_name < migrations/002_add_search_indexes.sql
```

### Option 2: Using phpMyAdmin
1. Log into phpMyAdmin
2. Select your database
3. Go to the "SQL" tab
4. Copy and paste the contents of the migration file
5. Click "Go"

### Option 3: Manual SQL Execution
Connect to your database and run the migration file directly:
```sql
SOURCE /path/to/intra/migrations/002_add_search_indexes.sql;
```

## Important Notes

- **Safe to Run Multiple Times**: All migrations check if changes already exist before applying them
- **No Data Loss**: If the schema already contains the changes, the migration does nothing
- **Idempotent**: You can run migrations multiple times without side effects

## After Migration

### For 001_add_purchase_price_to_inventory.sql

Once the migration is applied (or if the column already exists), the admin dashboard will automatically display the calculated total inventory value based on:

```
Total Inventory Value = SUM(purchase_price × quantity) for all active items
```

### For 002_add_search_indexes.sql

After applying this migration, search queries in `api/global_search.php` will be significantly faster:
- Inventory name searches will use the index instead of full table scans
- User firstname/lastname searches will use indexes for better performance
- News title searches will be optimized with the index

## Testing

### Test Purchase Price Column (001)

To verify the migration worked:

```sql
-- Check if column exists
DESCRIBE inventory;

-- Check if there are items with purchase prices
SELECT name, purchase_price, quantity, (purchase_price * quantity) as item_value
FROM inventory 
WHERE purchase_price IS NOT NULL 
LIMIT 10;

-- Calculate total inventory value
SELECT SUM(purchase_price * quantity) as total_value
FROM inventory
WHERE status = 'active' AND purchase_price IS NOT NULL;
```

### Test Search Indexes (002)

To verify the indexes were created:

```sql
-- Show all indexes for the affected tables
SHOW INDEX FROM inventory WHERE Key_name = 'idx_inventory_name';
SHOW INDEX FROM users WHERE Key_name IN ('idx_users_firstname', 'idx_users_lastname');
SHOW INDEX FROM news WHERE Key_name = 'idx_news_title';

-- Or use INFORMATION_SCHEMA
SELECT 
    table_name,
    index_name,
    column_name,
    CASE WHEN non_unique = 0 THEN 'UNIQUE' ELSE 'NON-UNIQUE' END AS index_type
FROM INFORMATION_SCHEMA.STATISTICS
WHERE table_schema = DATABASE()
AND index_name IN (
    'idx_inventory_name',
    'idx_users_firstname', 
    'idx_users_lastname',
    'idx_news_title'
)
ORDER BY table_name, index_name;
```

To test query performance improvement:

```sql
-- Test with EXPLAIN to see index usage
EXPLAIN SELECT * FROM inventory WHERE name LIKE '%test%';
EXPLAIN SELECT * FROM users WHERE firstname LIKE '%john%';
EXPLAIN SELECT * FROM users WHERE lastname LIKE '%smith%';
EXPLAIN SELECT * FROM news WHERE title LIKE '%announcement%';
```

The EXPLAIN output should show "Using index" or reference the new index names in the "key" column.
