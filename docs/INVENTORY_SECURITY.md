# Inventory Security Implementation

## Bestandsanpassung Sicherheit (Stock Adjustment Security)

This document describes the security measures implemented in the `Inventory::adjustQuantity()` method to prevent race conditions and ensure data consistency during concurrent stock adjustments.

## Implementation Details

### 1. PDO Transactions

The `adjustQuantity` method uses PDO transactions to ensure atomicity of the stock adjustment operation:

```php
// Start transaction to ensure data consistency
$alreadyInTransaction = $this->pdo->inTransaction();
if (!$alreadyInTransaction) {
    $this->pdo->beginTransaction();
}

// ... perform operations ...

// Commit transaction only if we started it
if (!$alreadyInTransaction) {
    $this->pdo->commit();
}
```

**Benefits:**
- All operations (SELECT, UPDATE, INSERT into logs) are performed atomically
- If any operation fails, all changes are rolled back
- Prevents partial updates that could leave the system in an inconsistent state
- Proper handling of nested transactions to avoid issues when called within another transaction context

### 2. Row-Level Locking (SELECT ... FOR UPDATE)

The method implements row-level locking to prevent concurrent modifications:

```php
// Get current item with row lock
$stmt = $this->pdo->prepare("SELECT quantity, name FROM inventory WHERE id = ? FOR UPDATE");
$stmt->execute([$id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);
```

**How it works:**
1. `SELECT ... FOR UPDATE` acquires an exclusive lock on the selected row
2. Other transactions attempting to read the same row with `FOR UPDATE` will wait
3. The lock is held until the transaction is committed or rolled back
4. This ensures that only one process can modify the quantity at a time

**Benefits:**
- Prevents lost updates when multiple users adjust quantity simultaneously
- Eliminates race conditions in concurrent scenarios
- Ensures accurate stock calculations even under high load

### 3. Error Handling

The implementation includes comprehensive error handling:

```php
try {
    // ... operations ...
} catch (PDOException $e) {
    // Rollback on error only if we started the transaction
    if ($this->pdo->inTransaction() && !$alreadyInTransaction) {
        $this->pdo->rollBack();
    }
    $this->log("Error adjusting quantity: " . $e->getMessage(), $userId);
    return false;
}
```

**Error scenarios handled:**
- Item not found: Transaction is rolled back
- Negative inventory prevention: Transaction is rolled back with detailed logging
- Database errors: Transaction is rolled back and error is logged
- Nested transaction awareness: Only rolls back if the method started the transaction

## Concurrent Access Scenario

### Without Protection (Problematic):
```
Time | User A                    | User B                    | Stock
-----|---------------------------|---------------------------|------
t0   | Current: 10               | Current: 10               | 10
t1   | Calculate: 10 - 3 = 7     | Calculate: 10 - 5 = 5     | 10
t2   | UPDATE to 7               | UPDATE to 5               | 5 ❌
```
Result: Lost update! Stock should be 2, but is 5.

### With Protection (Correct):
```
Time | User A                    | User B                    | Stock
-----|---------------------------|---------------------------|------
t0   | SELECT ... FOR UPDATE     | Waiting...                | 10
t1   | Lock acquired, Current: 10| Still waiting...          | 10
t2   | Calculate: 10 - 3 = 7     | Still waiting...          | 10
t3   | UPDATE to 7               | Still waiting...          | 7
t4   | COMMIT (lock released)    | Lock acquired             | 7
t5   |                           | Current: 7                | 7
t6   |                           | Calculate: 7 - 5 = 2      | 7
t7   |                           | UPDATE to 2               | 2
t8   |                           | COMMIT                    | 2 ✅
```
Result: Correct! All updates are properly serialized.

## Usage

The method is called from the inventory management interface:

```php
// In templates/pages/inventory.php
if ($inventory->adjustQuantity($itemId, $adjustment, $comment, $auth->getUserId())) {
    // Success: quantity has been safely adjusted
    $updatedItem = $inventory->getById($itemId);
    // Use $updatedItem['quantity'] for the new value
}
```

## Database Requirements

For the row-level locking to work correctly:
- The database must support transactions (InnoDB engine for MySQL)
- The transaction isolation level should be READ COMMITTED or higher
- The `inventory` table must use a transactional storage engine

## Testing Concurrent Access

To verify the implementation works correctly under concurrent load, you can:

1. Open multiple browser tabs/windows
2. Navigate to the same inventory item in each
3. Click "Menge anpassen" (Adjust quantity) simultaneously in multiple tabs
4. Verify that all adjustments are properly applied without lost updates

## Performance Considerations

- Row locks are held only for the duration of the transaction
- The lock scope is minimal (single row)
- Lock wait timeout can be configured at the database level
- Consider implementing deadlock detection and retry logic for high-concurrency scenarios

## Security Benefits

1. **Data Integrity**: Prevents incorrect stock levels from concurrent modifications
2. **Dual Audit Trail**: All changes are logged in both `inventory_logs` and `system_logs` tables
   - `inventory_logs`: Detailed technical change log with item_id, user_id, change_amount, and comment
   - `system_logs`: Administrative audit log with human-readable German messages ('User [ID] hat Bestand von [Item] um [Anzahl] geändert')
3. **Negative Stock Prevention**: Validates that operations don't result in negative inventory
4. **Transaction Safety**: All-or-nothing execution prevents partial updates (quantity + both logs)
5. **Isolation**: Each operation sees a consistent view of the data

## References

- [MySQL SELECT ... FOR UPDATE Documentation](https://dev.mysql.com/doc/refman/8.0/en/innodb-locking-reads.html)
- [PDO Transactions Documentation](https://www.php.net/manual/en/pdo.transactions.php)
- [Database Transaction Isolation Levels](https://dev.mysql.com/doc/refman/8.0/en/innodb-transaction-isolation-levels.html)
- [Implementation Details](INVENTORY_ADJUSTMENT_OPTIMIZATION.md) - Complete documentation of the optimization
