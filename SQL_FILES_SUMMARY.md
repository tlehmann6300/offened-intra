# SQL Setup Files - Summary

## Created Files

### 1. user_db_setup.sql
**Database:** dbs15253086  
**Size:** ~11 KB (222 lines)

#### Tables Created:
1. **users** - Benutzerverwaltung mit Authentication
   - Fields: id, email, password, firstname, lastname, role, birthday, notify_birthday
   - TOTP fields: totp_secret, totp_enabled, totp_verified_at
   - Roles: admin, 1v, 2v, 3v, ressortleiter, mitglied, alumni

2. **alumni_profiles** - Alumni-Profile mit Validierung
   - Fields: user_id, graduation_year, company, position, linkedin_url, bio, expertise
   - Validation: isWillingToMentor, isAlumniValidated

3. **login_attempts** - Rate-Limiting System
   - Fields: ip_address, email, attempt_time, success, user_agent

4. **invitations** - Token-basiertes Einladungssystem
   - Fields: email, token, role, created_by, expires_at, accepted_at

5. **system_logs** - Audit Trail
   - Fields: user_id, action, details, ip_address, timestamp

#### Test Data:
- User: Tom Lehmann (tomlehmann)
- Email: tom.lehmann@business-consulting.de
- Password: password (Hash: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi)
- Role: admin

---

### 2. content_db_setup.sql
**Database:** dbs15161271  
**Size:** ~20 KB (452 lines)

#### Tables Created:
1. **inventory_categories** - Inventar-Kategorien
   - Initial categories: Getränke, Becher, Kostüme, Tische, Stühle, Dekoration, Technik, Büromaterial

2. **inventory_locations** - Lagerorte
   - Initial locations: Hauptlager, Büro München, Eventlager, Externe Lagerung

3. **inventory** - Inventarverwaltung
   - Fields: name, category_id, location_id, quantity, purchase_price (DECIMAL), responsible_user_id, status
   - 4 example items with prices

4. **events** - Event-Verwaltung
   - Fields: title, description, date (DATETIME), location, max_participants, created_by

5. **event_helper_slots** - Helfer-Slots für Events
   - Fields: event_id, task_name, required_helpers, description

6. **event_helper_registrations** - Helfer-Anmeldungen
   - Fields: slot_id, user_id, registered_at

7. **projects** - Projektverwaltung
   - Fields: title, description, status (ENUM), client, budget (DECIMAL), project_lead_id

8. **news** - News und Ankündigungen
   - Fields: title, content (HTML/Quill), author_id, is_published

#### Test Data:
- Event: Sommerfest 2026 (15. Juli 2026)
- Helper Slots: 3 (Catering, Aufbau, Abbau)
- Categories: 8 (including required: Getränke, Becher, Kostüme, Tische)
- Locations: 4
- Inventory: 4 sample items
- Project: Digitalisierungs-Workshop 2026
- News: Welcome message

---

## Technical Details

### Data Types
- ✅ DECIMAL(10,2) for prices and budgets
- ✅ TIMESTAMP for created_at/updated_at
- ✅ DATETIME for event dates
- ✅ BOOLEAN for flags (TRUE/FALSE)
- ✅ ENUM for status fields

### Database Configuration
- ✅ Engine: InnoDB (all tables)
- ✅ Charset: utf8mb4
- ✅ Collation: utf8mb4_unicode_ci

### Cross-Database References
The following fields in content_db_setup.sql reference users.id from user_db_setup.sql:
- inventory.responsible_user_id
- inventory.created_by
- events.created_by
- event_helper_registrations.user_id
- projects.project_lead_id
- projects.created_by
- news.author_id

**Note:** No physical Foreign Keys between databases (different hosts), only logical references.

### Security Features
- ✅ Bcrypt password hashing
- ✅ TOTP/2FA support
- ✅ Rate limiting via login_attempts
- ✅ Token-based invitations
- ✅ Audit trail via system_logs
- ✅ Alumni validation workflow

---

## Usage

### User Database Setup
```bash
mysql -h db5019508945.hosting-data.io -u dbu4494103 -p dbs15253086 < user_db_setup.sql
```

### Content Database Setup
```bash
mysql -h db5019375140.hosting-data.io -u dbu2067984 -p dbs15161271 < content_db_setup.sql
```

### Test Login
- Email: tom.lehmann@business-consulting.de
- Username: tomlehmann
- Password: password

---

## Compliance with Requirements

✅ Separate SQL files for user and content databases  
✅ User DB includes all TOTP fields (totp_secret, totp_enabled, totp_verified_at)  
✅ Alumni validation system (isAlumniValidated, isWillingToMentor)  
✅ Role concept (admin, 1v-3v, ressortleiter, mitglied, alumni)  
✅ login_attempts table for rate limiting  
✅ invitations table with token system  
✅ system_logs table for audit trail  
✅ Test user 'tomlehmann' with specified password hash  
✅ Inventory with categories (Getränke, Becher, Tische, etc.)  
✅ Events with helper slots (Sommerfest with 3 slots)  
✅ Projects table with status field  
✅ News table with HTML/Quill content  
✅ InnoDB engine for all tables  
✅ utf8mb4 charset for all tables  
✅ DECIMAL for prices, TIMESTAMP for dates, BOOLEAN for flags  
✅ Consistent user_id column naming across databases  
✅ No physical Foreign Keys between databases (as required)  

