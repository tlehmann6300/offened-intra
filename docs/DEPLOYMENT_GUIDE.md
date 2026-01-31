# Deployment Guide - Alumni Workflow Implementation

## Overview
This guide provides step-by-step instructions for deploying the new alumni workflow and role hierarchy to production.

## Pre-Deployment Checklist

- [ ] Review all code changes in the PR
- [ ] Verify database backup is current
- [ ] Confirm staging environment testing is complete
- [ ] Notify vorstand members about new validation interface
- [ ] Schedule maintenance window (if needed)

## Deployment Steps

### 1. Database Migration

**File**: `migrations/005_add_alumni_validation_fields.sql`

**Execute on User Database**:
```bash
mysql -u username -p database_name < migrations/005_add_alumni_validation_fields.sql
```

**What this does**:
- Adds `is_alumni_validated` field (TINYINT(1), default 0)
- Adds `alumni_status_requested_at` field (TIMESTAMP NULL)
- Creates index `idx_alumni_validation` on (role, is_alumni_validated)
- Sets existing alumni users to validated status (grandfathered)

**Verification**:
```sql
-- Check fields exist
DESCRIBE users;

-- Check existing alumni are validated
SELECT id, email, role, is_alumni_validated, alumni_status_requested_at 
FROM users 
WHERE role = 'alumni' 
LIMIT 5;

-- Check index exists
SHOW INDEX FROM users WHERE Key_name = 'idx_alumni_validation';
```

### 2. Deploy Code Changes

**Files Changed**:
- `src/Auth.php` - Role hierarchy and alumni workflow methods
- `src/Project.php` - Access control for alumni
- `api/router.php` - Alumni workflow API endpoints
- `templates/pages/alumni_validation.php` - Admin interface
- `migrations/005_add_alumni_validation_fields.sql` - Database schema
- `docs/ALUMNI_WORKFLOW.md` - Documentation
- `docs/SECURITY_SUMMARY.md` - Security review
- `tests/test_alumni_workflow.php` - Test suite

**Deployment Method**:
```bash
# Pull latest changes
git pull origin main

# Verify no conflicts
git status

# Clear any PHP caches if applicable
# (e.g., opcache_reset(), restart php-fpm, etc.)
```

### 3. Verify Deployment

#### 3.1 Test Role Hierarchy
```bash
php tests/test_alumni_workflow.php
```

Expected output:
```
✓ users table has alumni validation fields
✓ ROLE_HIERARCHY constant exists
✓ All expected roles present in hierarchy
✓ All alumni workflow methods exist
✓ All Project methods accept userRole parameter
✓ Alumni validation template exists
✓ All alumni workflow API endpoints present
```

#### 3.2 Test Admin Interface

1. Log in as vorstand or admin user
2. Navigate to: `index.php?page=alumni_validation`
3. Verify page loads without errors
4. Check that pending alumni list displays correctly

#### 3.3 Test API Endpoints

**Test alumni status request** (as any user):
```javascript
fetch('/api/router.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-CSRF-Token': csrfToken
    },
    body: 'action=request_alumni_status'
})
```

**Expected**: Success message or appropriate error

**Test validation** (as vorstand):
```javascript
fetch('/api/router.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-CSRF-Token': csrfToken
    },
    body: 'action=validate_alumni&user_id=123'
})
```

**Expected**: Success message if user is alumni, error otherwise

**Test pending list** (as vorstand):
```javascript
fetch('/api/router.php?action=get_pending_alumni')
```

**Expected**: JSON with list of pending alumni

### 4. Post-Deployment Tasks

#### 4.1 Update Navigation (Optional)
Add link to alumni validation in admin menu if not already present:

```php
<?php if (in_array($userRole, ['admin', 'vorstand', '1v', '2v', '3v'])): ?>
    <a href="index.php?page=alumni_validation">
        <i class="fas fa-user-check"></i> Alumni Validierung
    </a>
<?php endif; ?>
```

#### 4.2 Train Vorstand Members

Provide documentation to vorstand members:
1. How to access alumni validation interface
2. What to check when validating alumni
3. Workflow for handling requests
4. Link to documentation: `docs/ALUMNI_WORKFLOW.md`

#### 4.3 Notify Active Alumni

Send notification to existing alumni users:
- They have been grandfathered as validated
- New alumni will need validation going forward
- No action required from existing alumni

#### 4.4 Monitor Logs

Check application logs for any issues:
```bash
tail -f logs/app.log | grep -i "alumni"
```

Expected log entries:
- Alumni status requests
- Alumni validations
- Permission checks

### 5. Rollback Plan (If Needed)

If critical issues are found:

#### 5.1 Database Rollback
```sql
-- Remove added fields
ALTER TABLE users DROP COLUMN is_alumni_validated;
ALTER TABLE users DROP COLUMN alumni_status_requested_at;

-- Remove index
DROP INDEX idx_alumni_validation ON users;
```

#### 5.2 Code Rollback
```bash
git revert <commit-hash>
git push origin main
```

**Note**: Existing alumni will retain their access, but validation workflow will not be available.

## Configuration Options

### Role Assignment
Update user roles via admin interface or directly in database:

```sql
-- Assign specific vorstand position
UPDATE users SET role = '1v' WHERE id = 123;
UPDATE users SET role = '2v' WHERE id = 124;
UPDATE users SET role = '3v' WHERE id = 125;

-- General vorstand (highest)
UPDATE users SET role = 'vorstand' WHERE id = 126;
```

### Alumni Validation
Validate alumni users manually if needed:

```sql
-- Validate specific alumni
UPDATE users 
SET is_alumni_validated = 1 
WHERE id = 123 AND role = 'alumni';

-- See pending validations
SELECT id, email, firstname, lastname, alumni_status_requested_at
FROM users 
WHERE role = 'alumni' AND is_alumni_validated = 0;
```

## Troubleshooting

### Issue: Alumni still see active projects
**Cause**: Code not calling methods with userRole parameter
**Solution**: Update calling code to pass user role:
```php
$projects = $project->getLatest(5, $auth->getUserRole());
```

### Issue: Alumni validation page not accessible
**Cause**: User doesn't have required role
**Solution**: Verify user has vorstand, 1v, 2v, 3v, or admin role
```sql
SELECT id, email, role FROM users WHERE id = 123;
```

### Issue: Index creation failed
**Cause**: Index might already exist or syntax error
**Solution**: Check if index exists:
```sql
SHOW INDEX FROM users WHERE Key_name = 'idx_alumni_validation';
```
If it exists, migration already applied successfully.

### Issue: CSRF token errors
**Cause**: Session or token generation issue
**Solution**: 
1. Check session is started in index.php
2. Verify CSRF token methods in Auth.php
3. Clear browser cookies and try again

## Monitoring

### Key Metrics to Track
1. Number of alumni status requests per day
2. Time to validate alumni (from request to validation)
3. Number of unvalidated alumni
4. Failed validation attempts

### Queries for Monitoring

**Pending validations**:
```sql
SELECT COUNT(*) as pending_count
FROM users 
WHERE role = 'alumni' AND is_alumni_validated = 0;
```

**Recent requests**:
```sql
SELECT id, email, firstname, lastname, alumni_status_requested_at
FROM users 
WHERE role = 'alumni' 
AND alumni_status_requested_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
ORDER BY alumni_status_requested_at DESC;
```

**Validated alumni**:
```sql
SELECT COUNT(*) as validated_count
FROM users 
WHERE role = 'alumni' AND is_alumni_validated = 1;
```

## Support

For issues or questions:
1. Check logs: `/logs/app.log`
2. Review documentation: `docs/ALUMNI_WORKFLOW.md`
3. Check security summary: `docs/SECURITY_SUMMARY.md`
4. Run test suite: `php tests/test_alumni_workflow.php`
5. Contact development team

## Documentation Links

- **User Documentation**: `docs/ALUMNI_WORKFLOW.md`
- **Security Review**: `docs/SECURITY_SUMMARY.md`
- **Migration Guide**: `migrations/README.md`
- **API Documentation**: See inline comments in `api/router.php`

---

**Deployment Date**: _______________
**Deployed By**: _______________
**Sign-off**: _______________
