# Global Search API Refactoring - Summary

## Overview
The `api/global_search.php` file has been completely refactored to work with the new multi-database architecture where tables are distributed across two separate database servers.

## Problem Statement
- Tables are now on two different database servers
- SQL UNION can no longer be used for cross-database queries
- Need to query User-DB (Members, Alumni) and Content-DB (Inventory, News, Events) separately
- Results must be merged in PHP, sorted by relevance, and returned as unified JSON

## Solution Implemented

### 1. Separate Database Connections
The API now uses the new `DatabaseManager` class to handle connections to both databases:

```php
$pdoContent = DatabaseManager::getContentConnection();
$pdoUser = DatabaseManager::getUserConnection();
```

**User-DB** (db5019508945.hosting-data.io):
- users
- alumni_profiles

**Content-DB** (db5019375140.hosting-data.io):
- inventory
- events
- projects
- news

### 2. Separate Queries
Instead of using SQL UNION across databases, we now execute two independent queries:

#### Query 1: User Database
```sql
SELECT ... FROM users u
LEFT JOIN alumni_profiles ap ON ...
WHERE (u.firstname LIKE :search OR ...)
```

#### Query 2: Content Database
```sql
-- Inventory
SELECT ... FROM inventory WHERE ...
UNION ALL
-- News
SELECT ... FROM news WHERE ...
UNION ALL
-- Events
SELECT ... FROM events WHERE ...
UNION ALL
-- Projects
SELECT ... FROM projects WHERE ...
```

Note: The UNION is used within the Content-DB query since all these tables are on the same server.

### 3. PHP-Based Result Merging
Results from both databases are merged using PHP:

```php
$allResults = array_merge($userResults, $contentResults);
```

### 4. Relevance-Based Sorting
Implemented an intelligent relevance scoring algorithm:

| Match Type | Points | Description |
|------------|--------|-------------|
| Exact title match | +10 | Query exactly matches the title |
| Partial title match | +5 | Query found within the title |
| Subtitle match | +3 | Query found in subtitle/category |
| Description match | +1 | Query found in extra_info/description |
| Recent item bonus | +2 | Item created within last 30 days |

**Sorting Logic:**
1. Primary: Sort by relevance score (higher = better)
2. Secondary: Sort by date (newer = better) as tiebreaker

**Implementation Details:**
- Uses `mb_strtolower()` and `mb_strpos()` for proper multibyte character handling (important for German umlauts: ä, ö, ü, ß)
- Validates `strtotime()` results to prevent errors with invalid dates
- Case-insensitive matching

### 5. Unified JSON Response
Results are returned as a single JSON object with grouped results:

```json
{
  "success": true,
  "query": "laptop",
  "total": 15,
  "counts": {
    "inventory": 5,
    "user": 3,
    "news": 4,
    "event": 2,
    "project": 1
  },
  "results": {
    "inventory": [...],
    "user": [...],
    "news": [...],
    "event": [...],
    "project": [...]
  },
  "pagination": {
    "limit": 50,
    "offset": 0,
    "returned": 15
  }
}
```

## Testing
Created comprehensive test suite: `docs/test_global_search_relevance.php`

**Test Coverage:**
- ✅ Exact title matches score highest
- ✅ Partial title matches score appropriately
- ✅ Subtitle matches add value
- ✅ Description matches count
- ✅ Recent items get bonus points
- ✅ Sorting works correctly (score DESC, then date DESC)

All tests pass successfully.

## Key Benefits

1. **Multi-Database Support**: Works seamlessly with tables on different servers
2. **Intelligent Ranking**: Results sorted by relevance, not just date
3. **Better User Experience**: Most relevant results appear first
4. **Multibyte Safe**: Proper handling of German characters
5. **Pagination Support**: Efficient pagination after merging and sorting
6. **Backward Compatible**: Maintains the same API interface

## Performance Considerations

- Separate queries run in parallel (no sequential dependency)
- Pagination applied after merging for accurate results
- Database indexes recommended:
  - `inventory(name)` - for inventory searches
  - `users(firstname, lastname)` - for user searches  
  - `news(title)` - for news searches

## Migration Notes

- No changes required to API consumers
- Same endpoint URL: `api/global_search.php?q=search_term`
- Same response format
- Improved result ordering (relevance-based instead of date-based)

## Files Modified

1. `api/global_search.php` - Complete refactoring with relevance scoring
2. `docs/test_global_search_relevance.php` - Test suite for relevance algorithm
3. `docs/global_search_refactoring_summary.md` - This documentation

## Code Quality

- ✅ PHP syntax validated
- ✅ Code review completed
- ✅ Multibyte string handling verified
- ✅ Date validation implemented
- ✅ Test suite passes
- ✅ No security vulnerabilities introduced
