# Alumni Workflow und Rollen-Hierarchie

## Übersicht

Dieses Dokument beschreibt die Implementierung des neuen Alumni-Workflows und der erweiterten Rollen-Hierarchie im IBC Intranet-System.

## Rollen-Hierarchie

Die neue Rollen-Hierarchie wurde wie folgt implementiert:

```
Admin (8) > Vorstand (7) > 1V (6) > 2V (5) > 3V (4) > Ressortleiter (3) > Mitglied (2) > Alumni (1) > None (0)
```

**Hinweis**: Die Rollen 1V, 2V, 3V sind spezifische Vorstandspositionen unterhalb der allgemeinen "Vorstand"-Rolle. In der Praxis sollten Benutzer entweder "vorstand" (höchste Vorstandsrolle) ODER eine der spezifischen Positionen (1V, 2V, 3V) haben, nicht beides.

### Rollen-Beschreibung

- **Admin (8)**: Vollständiger Systemzugriff
- **Vorstand (7)**: Vorstandsmitglied (allgemein, höchste Vorstandsrolle)
- **1V (6)**: Erster Vorstand (spezifische Position)
- **2V (5)**: Zweiter Vorstand (spezifische Position)
- **3V (4)**: Dritter Vorstand (spezifische Position)
- **Ressortleiter (3)**: Abteilungsleiter
- **Mitglied (2)**: Reguläres Mitglied
- **Alumni (1)**: Alumni-Mitglied (niedrigste aktive Rolle, benötigt Validierung)
- **None (0)**: Kein Zugriff

## Alumni-Validierungs-Workflow

### 1. Alumni-Status Beantragen

Wenn ein Mitglied den Alumni-Status beantragt:

```php
$auth->requestAlumniStatus($userId);
```

**Was passiert:**
- Rolle wird auf 'alumni' geändert
- `is_alumni_validated` wird auf `FALSE` gesetzt
- `alumni_status_requested_at` wird auf aktuelle Zeit gesetzt
- **Zugriff auf aktive Projektdaten wird sofort entzogen**

### 2. Zugriffsbeschränkungen

Alumni-Benutzer (besonders nicht validierte) haben eingeschränkten Zugriff:

#### Projekt-Zugriff

```php
// Keine aktiven Projekte sichtbar
$projects = $project->getLatest(3, 'alumni');  // Gibt leeres Array zurück

// Keine Suche in aktiven Projekten
$results = $project->search('keyword', 10, 'alumni');  // Nur abgeschlossene Projekte

// Keine offenen Projekt-Positionen
$count = $project->countOpenPositions('alumni');  // Gibt 0 zurück
```

#### Berechtigungen

Die `checkPermission()` Methode prüft automatisch:
- Alumni-Benutzer mit `is_alumni_validated = FALSE` werden wie 'none' behandelt
- Zugriff wird verweigert, bis der Status validiert ist

### 3. Validierung durch Vorstand

Nur Benutzer mit Rollen `admin`, `vorstand`, `1v`, `2v`, oder `3v` können Alumni validieren:

```php
// Alumni validieren
$auth->validateAlumniStatus($alumniUserId, $validatorUserId);
```

**Was passiert:**
- `is_alumni_validated` wird auf `TRUE` gesetzt
- Alumni erhält vollen Alumni-Zugriff
- Profil wird im Verzeichnis sichtbar

### 4. Admin-Interface

**URL**: `index.php?page=alumni_validation`

Das Interface bietet:
- Liste aller ausstehenden Alumni-Validierungen
- Benutzerinformationen (Name, E-Mail, Antragsdatum)
- Ein-Klick-Validierung
- Workflow-Dokumentation

## API-Endpunkte

### request_alumni_status

**Methode**: POST  
**Berechtigung**: Beliebiger eingeloggter Benutzer  
**Beschreibung**: Beantragen des Alumni-Status

```javascript
fetch('api/router.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-CSRF-Token': csrfToken
    },
    body: 'action=request_alumni_status'
})
```

### validate_alumni

**Methode**: POST  
**Berechtigung**: vorstand, 1v, 2v, 3v, admin  
**Beschreibung**: Validieren eines Alumni-Benutzers

```javascript
fetch('api/router.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-CSRF-Token': csrfToken
    },
    body: 'action=validate_alumni&user_id=123'
})
```

### get_pending_alumni

**Methode**: GET  
**Berechtigung**: vorstand, 1v, 2v, 3v, admin  
**Beschreibung**: Liste aller ausstehenden Alumni-Validierungen abrufen

```javascript
fetch('api/router.php?action=get_pending_alumni')
```

## Datenbank-Schema

### Neue Felder in `users` Tabelle

```sql
-- Alumni-Validierungsstatus
is_alumni_validated TINYINT(1) DEFAULT 0

-- Zeitstempel der Alumni-Status-Anfrage
alumni_status_requested_at TIMESTAMP NULL DEFAULT NULL

-- Index für effiziente Abfragen
INDEX idx_alumni_validation (role, is_alumni_validated)
```

## Migration

**Datei**: `migrations/005_add_alumni_validation_fields.sql`

**Ausführen**:
```bash
mysql -u username -p database_name < migrations/005_add_alumni_validation_fields.sql
```

**Hinweis**: Bestehende Alumni-Benutzer werden automatisch als validiert markiert (grandfathered).

## Auth.php Methoden

### requestAlumniStatus($userId)
Beantragt Alumni-Status für einen Benutzer

### validateAlumniStatus($alumniUserId, $validatorUserId)
Validiert einen Alumni-Benutzer (nur Vorstand/Admin)

### getPendingAlumniValidations()
Gibt Liste aller ausstehenden Validierungen zurück

### isValidatedAlumni($userId)
Prüft, ob ein Benutzer ein validierter Alumni ist

### checkPermission($requiredRole)
**Erweitert**: Prüft nun auch `is_alumni_validated` Status für Alumni

## Project.php Methoden

Alle Projekt-Methoden wurden erweitert, um einen optionalen `$userRole` Parameter zu akzeptieren:

- `getAll($status, $limit, $offset, $userRole)`
- `getLatest($limit, $userRole)`
- `getById($id, $userRole)`
- `search($query, $limit, $userRole)`
- `countOpenPositions($userRole)`

**Alumni-Logik**: 
- Wenn `$userRole === 'alumni'`, werden nur abgeschlossene/abgebrochene Projekte zurückgegeben
- Aktive und geplante Projekte sind für Alumni nicht sichtbar

## Sicherheitsüberlegungen

1. **Sofortige Zugriffsentziehung**: Alumni verlieren sofort den Zugriff auf aktive Projekte bei Statuswechsel

2. **Validierungspflicht**: Nicht validierte Alumni haben praktisch keinen Zugriff

3. **Rollen-Hierarchie**: Strenge Hierarchie verhindert unbefugten Zugriff

4. **Datenbank-Level**: Zugriffsbeschränkungen werden auf Datenbankebene durchgesetzt

5. **API-Sicherheit**: Alle API-Endpunkte prüfen Berechtigungen serverseitig

## Testen

**Test-Datei**: `tests/test_alumni_workflow.php`

**Ausführen**:
```bash
php tests/test_alumni_workflow.php
```

**Prüft**:
- Datenbank-Schema (neue Felder)
- Rollen-Hierarchie
- Alumni-Workflow-Methoden
- Projekt-Zugriffskontrolle
- API-Endpunkte
- Admin-Interface

## Verwendungsbeispiele

### Benutzer zu Alumni machen

```php
// Als Admin/Vorstand
$auth->requestAlumniStatus($userId);

// Benutzer hat jetzt:
// - role = 'alumni'
// - is_alumni_validated = FALSE
// - Kein Zugriff auf aktive Projekte
```

### Alumni validieren

```php
// Im Admin-Interface oder programmatisch
$auth->validateAlumniStatus($alumniUserId, $currentUserId);

// Benutzer hat jetzt:
// - is_alumni_validated = TRUE
// - Zugriff auf Alumni-Bereich
// - Profil im Verzeichnis sichtbar
```

### Projekt-Zugriff prüfen

```php
// Projekte für Mitglied abrufen
$memberProjects = $project->getLatest(5, 'mitglied');  // Zeigt aktive Projekte

// Projekte für Alumni abrufen
$alumniProjects = $project->getLatest(5, 'alumni');    // Zeigt keine aktiven Projekte
```

## Bekannte Einschränkungen

1. **Vorhandene Code**: Code, der Projekt-Methoden ohne `$userRole` Parameter aufruft, funktioniert weiterhin (kein Breaking Change), wendet aber keine Alumni-Beschränkungen an.

2. **Alumni-Tabelle**: Die separate `alumni`-Tabelle (Profile) ist unabhängig von der `users`-Tabelle. Sichtbarkeit wird über `is_published` in der `alumni`-Tabelle gesteuert, nicht über `is_alumni_validated`.

3. **Manuelle Validierung**: Es gibt derzeit keine automatische Validierung. Alumni müssen manuell vom Vorstand geprüft werden.

## Zukünftige Verbesserungen

1. **Benachrichtigungen**: E-Mail-Benachrichtigungen bei Validierung
2. **Workflow-Automatisierung**: Automatische Validierung nach Kriterien
3. **Audit-Log**: Detailliertes Logging aller Alumni-Status-Änderungen
4. **Batch-Validierung**: Mehrere Alumni gleichzeitig validieren
5. **Ablehnungs-Workflow**: Möglichkeit, Alumni-Anträge abzulehnen

## Support

Bei Fragen oder Problemen:
1. Prüfen Sie die Migrations-Logs
2. Führen Sie den Test aus: `php tests/test_alumni_workflow.php`
3. Prüfen Sie die Logs in `/logs/app.log`
4. Kontaktieren Sie das Entwicklungsteam
