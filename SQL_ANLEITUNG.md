# IBC-Intranet: SQL-Datenbank-Setup

## Übersicht

Dieses IBC-Intranet-System verwendet eine **Multi-Datenbank-Architektur** mit zwei getrennten MySQL-Datenbanken für optimale Sicherheit und Performance.

### Datenbank-Architektur

```
┌─────────────────────────────────────────────────────────────┐
│                    IBC-INTRANET SYSTEM                      │
├─────────────────────────────────────────────────────────────┤
│                                                               │
│  ┌──────────────────────┐      ┌──────────────────────┐    │
│  │   USER-DATENBANK     │      │  CONTENT-DATENBANK   │    │
│  │   (dbs15253086)      │      │   (dbs15161271)      │    │
│  ├──────────────────────┤      ├──────────────────────┤    │
│  │ • users              │      │ • projects           │    │
│  │ • alumni_profiles    │      │ • inventory          │    │
│  │ • login_attempts     │      │ • inventory_*        │    │
│  │ • invitations        │      │ • events             │    │
│  │                      │      │ • event_helper_*     │    │
│  │                      │      │ • news               │    │
│  │                      │      │ • system_logs        │    │
│  └──────────────────────┘      └──────────────────────┘    │
│                                                               │
└─────────────────────────────────────────────────────────────┘
```

## Dateien

### dbs15253086.sql
**User-Datenbank Setup mit Schema und Test-Daten**

Diese Datei enthält die komplette Schema-Definition und Test-Daten für die User-Datenbank (dbs15253086):

- **users**: Benutzerverwaltung mit Authentication, 2FA und Rollenkonzept
- **alumni_profiles**: Erweiterte Profile für Alumni mit Karriereinformationen
- **login_attempts**: Rate-Limiting und Login-Tracking
- **invitations**: Token-basiertes Einladungssystem
- **Admin-User**: tom.lehmann@business-consulting.de (Passwort: AdminPass2024!)

### dbs15161271.sql
**Content-Datenbank Setup mit Schema und Test-Daten**

Diese Datei enthält die komplette Schema-Definition und Test-Daten für die Content-Datenbank (dbs15161271):

- **projects**: Projektverwaltung mit Status und Team-Information
- **inventory**: Inventarverwaltung mit Mengen und Preisen
- **inventory_categories**: Kategorien für Inventar-Artikel
- **inventory_locations**: Lagerorte für Inventar
- **events**: Event-Management mit Datum und Ort
- **event_helper_slots**: Helfer-Slots für Events
- **event_helper_registrations**: Helfer-Anmeldungen
- **news**: News und Ankündigungen
- **system_logs**: Audit-Logs für administrative Aktionen
- **Inventar-Kategorien**: 8 Kategorien (Getränke, Becher, Kostüme, Tische, etc.)
- **Inventar-Standorte**: 4 Standorte (Hauptlager, Büro, Eventlager, etc.)
- **Test-Event**: "Sommerfest 2026" mit 3 Helfer-Slots
- **Beispiel-Projekt**: Digitalisierungs-Workshop
- **Beispiel-News**: Willkommensmeldung
- **Beispiel-Inventar**: 4 Artikel mit Preisen und Mengen

## Installation

### Voraussetzungen

- MySQL 8.0 oder höher
- Zugriff auf beide Datenbanken:
  - `dbs15253086` (User-DB)
  - `dbs15161271` (Content-DB)

### Option 1: Über phpMyAdmin

#### Schritt 1: User-Datenbank einrichten

1. Melden Sie sich bei phpMyAdmin an
2. Wählen Sie die Datenbank `dbs15253086` aus
3. Klicken Sie auf den Tab "SQL"
4. Öffnen Sie die Datei `dbs15253086.sql`
5. Kopieren Sie den kompletten Inhalt und fügen Sie ihn ein
6. Klicken Sie auf "Go"

#### Schritt 2: Content-Datenbank einrichten

1. Wählen Sie die Datenbank `dbs15161271` aus
2. Klicken Sie auf den Tab "SQL"
3. Öffnen Sie die Datei `dbs15161271.sql`
4. Kopieren Sie den kompletten Inhalt und fügen Sie ihn ein
5. Klicken Sie auf "Go"

### Option 2: Über MySQL Command Line

```bash
# User-Datenbank: Schema und Test-Daten importieren
mysql -h db5019508945.hosting-data.io -u dbu4494103 -p dbs15253086 < dbs15253086.sql

# Content-Datenbank: Schema und Test-Daten importieren
mysql -h db5019375140.hosting-data.io -u dbu2067984 -p dbs15161271 < dbs15161271.sql
```

### Option 3: Über Import-Script

Ein Import-Script ist im Repository vorhanden:

```bash
./import_database.sh
```

## Test-Zugangsdaten

Nach der Installation der Test-Daten können Sie sich mit folgenden Credentials anmelden:

- **E-Mail**: tom.lehmann@business-consulting.de
- **Passwort**: AdminPass2024!
- **Rolle**: Administrator / 1. Vorstand
- **2FA**: Deaktiviert (kann nach Login aktiviert werden)

## Datenbank-Features

### 🔐 Sicherheit

- **Bcrypt Password-Hashing**: Sichere Passwort-Speicherung
- **Two-Factor Authentication (TOTP)**: Optional für alle Benutzer
- **Rate-Limiting**: Schutz vor Brute-Force-Angriffen
- **Token-basierte Einladungen**: Sichere Benutzer-Registrierung
- **Role-Based Access Control**: Hierarchisches Rollenkonzept

### 📊 Datentypen & Constraints

- **DECIMAL(10,2)**: Für Preise und Budgets (Euro-Format)
- **TIMESTAMP**: Mit automatischem created_at/updated_at
- **ENUM**: Für Status-Felder (projects.status, inventory.status)
- **Foreign Keys**: Mit CASCADE für automatische Updates/Deletes
- **UNIQUE Constraints**: Für E-Mails, Tokens, etc.
- **Indexes**: Für performante Suchen und Joins

### 🔗 Cross-Database-Referenzen

Einige Tabellen in der Content-DB referenzieren die User-DB:
- `projects.created_by` → `users.id`
- `projects.project_lead_id` → `users.id`
- `inventory.responsible_user_id` → `users.id`
- `inventory.created_by` → `users.id`
- `events.created_by` → `users.id`
- `event_helper_registrations.user_id` → `users.id`
- `news.author_id` → `users.id`
- `system_logs.user_id` → `users.id`

**Wichtig**: Diese Referenzen sind **logisch**, aber nicht durch Foreign-Key-Constraints erzwungen, da die Tabellen in verschiedenen Datenbanken liegen.

## Rollenkonzept

Das System implementiert eine hierarchische Rollen-Struktur:

```
admin (1. Vorstand)
  ↓
1v, 2v, 3v (Vorstand)
  ↓
ressortleiter
  ↓
mitglied
  ↓
alumni
```

### Rollen-Berechtigungen

| Rolle | Berechtigungen |
|-------|----------------|
| **admin/1v** | Vollzugriff: Benutzer verwalten, System-Einstellungen, alle CRUD-Operationen |
| **2v/3v** | Vorstand-Rechte: Projekte, Events, News, Inventar verwalten |
| **ressortleiter** | Ressort-spezifische Verwaltung |
| **mitglied** | Standardzugriff: Lesen, Event-Anmeldung, eigenes Profil bearbeiten |
| **alumni** | Eingeschränkter Zugriff: Keine aktiven Projekte, Alumni-Verzeichnis |

## Alumni-Workflow

Das System implementiert einen mehrstufigen Alumni-Validierungs-Prozess:

1. **Mitglied beantragt Alumni-Status**
   - Role wird auf 'alumni' gesetzt
   - `is_alumni_validated` = 0 (ausstehend)
   - `alumni_status_requested_at` = aktueller Zeitstempel

2. **Zugriffsbeschränkung wird sofort aktiv**
   - Kein Zugriff mehr auf aktive Projekte
   - Eingeschränkte Sichtbarkeit im System

3. **Vorstand validiert das Alumni-Profil**
   - Prüfung der Profildaten
   - `is_alumni_validated` = 1 (validiert)

4. **Profil wird im Alumni-Verzeichnis sichtbar**
   - Networking-Features werden freigeschaltet
   - Mentoring-Optionen verfügbar

## Wartung & Updates

### Schema-Updates

Für Schema-Änderungen können Sie SQL-Migrations-Skripte direkt auf den Datenbanken ausführen:

```bash
# User-Datenbank
mysql -h db5019508945.hosting-data.io -u dbu4494103 -p dbs15253086 < migration.sql

# Content-Datenbank
mysql -h db5019375140.hosting-data.io -u dbu2067984 -p dbs15161271 < migration.sql
```

### Backup erstellen

```bash
# User-Datenbank Backup
mysqldump -h db5019508945.hosting-data.io -u dbu4494103 -p dbs15253086 > backup_user_$(date +%Y%m%d).sql

# Content-Datenbank Backup
mysqldump -h db5019375140.hosting-data.io -u dbu2067984 -p dbs15161271 > backup_content_$(date +%Y%m%d).sql
```

### Datenbank-Bereinigung

Alte Login-Versuche bereinigen (älter als 30 Tage):

```sql
USE dbs15253086;
DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 30 DAY);
```

Abgelaufene Einladungen löschen:

```sql
USE dbs15253086;
DELETE FROM invitations WHERE expires_at < NOW() AND accepted_at IS NULL;
```

## Troubleshooting

### Problem: Foreign Key Constraint Fehler

```
ERROR 1452 (23000): Cannot add or update a child row: a foreign key constraint fails
```

**Lösung**: Stellen Sie sicher, dass referenzierte Einträge existieren (z.B. Category-ID muss in `inventory_categories` vorhanden sein, bevor Sie sie in `inventory` verwenden).

### Problem: Duplicate Entry Fehler

```
ERROR 1062 (23000): Duplicate entry 'email@example.com' for key 'unique_email'
```

**Lösung**: Die E-Mail-Adresse existiert bereits. Verwenden Sie `ON DUPLICATE KEY UPDATE` oder eine andere E-Mail.

### Problem: Cross-Database Joins zu langsam

**Lösung**: Verwenden Sie separate Queries für jede Datenbank und führen Sie die Daten in der Anwendungslogik zusammen.

## Best Practices

1. **Verwenden Sie Prepared Statements**: Niemals direkt SQL-Strings mit Variablen konkatenieren
2. **Foreign Keys prüfen**: Vor dem Löschen von Einträgen prüfen, ob Abhängigkeiten bestehen
3. **Transaktionen nutzen**: Bei mehreren zusammenhängenden INSERT/UPDATE-Operationen
4. **Indexes pflegen**: Regelmäßig `ANALYZE TABLE` ausführen für optimale Performance
5. **Regelmäßige Backups**: Mindestens täglich, vor Major-Updates zusätzlich

## Support

Bei Fragen oder Problemen:
- **Dokumentation**: Siehe `/docs` Verzeichnis
- **IT-Team kontaktieren**: Für Datenbank-spezifische Probleme
- **GitHub Issues**: Für Bug-Reports und Feature-Requests

## Changelog

### Version 2.0 (2026-01-31)
- ✨ Multi-Datenbank-Architektur implementiert
- ✨ Alumni-Validierungs-Workflow hinzugefügt
- ✨ Two-Factor Authentication (TOTP) integriert
- ✨ Event-Helper-Slots System implementiert
- ✨ Inventar mit Purchase-Price und Locations erweitert
- 🔒 Rate-Limiting für Login-Versuche
- 🔒 Token-basiertes Einladungssystem
- 📊 System-Logs für Audit-Trail

---

**Erstellt**: 2026-01-31  
**Version**: 2.0  
**MySQL Kompatibilität**: 8.0+
