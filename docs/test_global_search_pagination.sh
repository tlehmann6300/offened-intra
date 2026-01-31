#!/bin/bash

# Test script for Global Search API pagination
# This script demonstrates how to use the new pagination features

echo "======================================"
echo "Global Search API - Pagination Tests"
echo "======================================"
echo ""

# Base URL - adjust this to your environment
BASE_URL="http://localhost"
API_ENDPOINT="${BASE_URL}/api/global_search.php"

echo "Note: This test script requires:"
echo "  1. A running PHP server"
echo "  2. Valid database connection"
echo "  3. Authenticated session (login required)"
echo ""
echo "To test manually, use these curl commands:"
echo ""

# Test 1: Basic search without pagination (uses defaults)
echo "Test 1: Basic search (default pagination)"
echo "curl -X GET '${API_ENDPOINT}?q=test'"
echo ""

# Test 2: Search with custom limit
echo "Test 2: Search with custom limit (10 results)"
echo "curl -X GET '${API_ENDPOINT}?q=test&limit=10'"
echo ""

# Test 3: Search with pagination (first page)
echo "Test 3: Paginated search - First page (20 results, offset 0)"
echo "curl -X GET '${API_ENDPOINT}?q=test&limit=20&offset=0'"
echo ""

# Test 4: Search with pagination (second page)
echo "Test 4: Paginated search - Second page (20 results, offset 20)"
echo "curl -X GET '${API_ENDPOINT}?q=test&limit=20&offset=20'"
echo ""

# Test 5: Search with maximum limit
echo "Test 5: Search with maximum limit (100 results)"
echo "curl -X GET '${API_ENDPOINT}?q=test&limit=100'"
echo ""

# Test 6: Test limit validation (should use default)
echo "Test 6: Invalid limit (150, should default to 50)"
echo "curl -X GET '${API_ENDPOINT}?q=test&limit=150'"
echo ""

# Test 7: Test negative offset (should default to 0)
echo "Test 7: Invalid offset (-10, should default to 0)"
echo "curl -X GET '${API_ENDPOINT}?q=test&offset=-10'"
echo ""

echo "======================================"
echo "Expected Response Format:"
echo "======================================"
cat << 'EOF'
{
  "success": true,
  "query": "test",
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
EOF

echo ""
echo "======================================"
echo "Testing Tips:"
echo "======================================"
echo "1. The API requires authentication (login first)"
echo "2. Use browser dev tools to inspect actual API calls"
echo "3. Check response pagination object for limit/offset values"
echo "4. Verify that 'returned' count matches actual results"
echo "5. Test with different search terms to see varying result counts"
echo ""
