# Inventory Purchase Price Migration

## Overview
This migration adds the `purchase_price` column to the `inventory` table if it doesn't already exist.

## Migration File
- **File**: `001_add_purchase_price_to_inventory.sql`
- **Column**: `purchase_price DECIMAL(10,2)`
- **Purpose**: Store the purchase price per item in euros

## How to Run

### Option 1: Using MySQL Command Line
```bash
mysql -u username -p database_name < migrations/001_add_purchase_price_to_inventory.sql
```

### Option 2: Using phpMyAdmin
1. Log into phpMyAdmin
2. Select your database
3. Go to the "SQL" tab
4. Copy and paste the contents of `001_add_purchase_price_to_inventory.sql`
5. Click "Go"

### Option 3: Manual SQL Execution
Connect to your database and run the migration file directly:
```sql
SOURCE /path/to/intra/migrations/001_add_purchase_price_to_inventory.sql;
```

## Important Notes

- **Safe to Run Multiple Times**: The migration checks if the column already exists before adding it
- **No Data Loss**: If the column already exists, the migration does nothing
- **Schema Already Contains Column**: If your database was created using `ibc_comprehensive_final.sql`, the column already exists and this migration will do nothing

## After Migration

Once the migration is applied (or if the column already exists), the admin dashboard will automatically display the calculated total inventory value based on:

```
Total Inventory Value = SUM(purchase_price × quantity) for all active items
```

## Testing

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
