# Database Migration und MailService Integration

## Überblick

Diese Änderungen implementieren die Migration zu zwei separaten Datenbanken (Content-DB und User-DB) und integrieren einen zentralen MailService mit IONOS SMTP.

## Datenbankarchitektur

### Content-Datenbank (db5019375140.hosting-data.io)
**Datenbank:** dbs15161271  
**Benutzer:** dbu2067984  
**Port:** 3306

**Tabellen:**
- `inventory` - Inventarverwaltung
- `inventory_locations` - Standorte
- `inventory_categories` - Kategorien
- `events` - Veranstaltungen
- `event_helper_slots` - Helfer-Slots
- `event_helper_registrations` - Helfer-Anmeldungen
- `projects` - Projekte
- `news` - Nachrichten
- `news_subscribers` - Newsletter-Abonnenten

### User-Datenbank (db5019508945.hosting-data.io)
**Datenbank:** dbs15253086  
**Benutzer:** dbu4494103  
**Port:** 3306

**Tabellen:**
- `users` - Benutzerkonten
- `alumni_profiles` - Alumni-Profile
- `user_skills` - Benutzerfähigkeiten
- `user_preferences` - Benutzereinstellungen
- `user_sessions` - Benutzersitzungen
- `system_logs` - Systemprotokolle
- `login_attempts` - Anmeldeversuche

## Änderungen

### 1. config/db.php

**Neue Features:**
- Zwei separate PDO-Verbindungen
- `Database::getContentConnection()` - Gibt Content-DB-Verbindung zurück
- `Database::getUserConnection()` - Gibt User-DB-Verbindung zurück
- `Database::getConnection()` - Legacy-Methode (gibt Content-DB zurück)

**Verwendung:**
```php
$pdoContent = Database::getContentConnection();
$pdoUser = Database::getUserConnection();
```

### 2. api/global_search.php

**Refactoring:**
- Separate Abfragen an beide Datenbanken
- User-DB wird für users und alumni_profiles abgefragt
- Content-DB wird für inventory, events, projects und news abgefragt
- Ergebnisse werden in PHP zusammengeführt und nach Datum sortiert
- Pagination wird nach dem Merge angewendet

**Wichtig:** SQL UNION über zwei verschiedene Hosts ist nicht möglich, daher die PHP-Lösung.

### 3. src/MailService.php (NEU)

Zentrale E-Mail-Service-Klasse für konsistenten E-Mail-Versand.

**Features:**
- Verwendet PHPMailer mit IONOS SMTP
- Automatische Plaintext-Alternative aus HTML
- Zentrale Fehlerbehandlung und Logging
- Einfache API für den E-Mail-Versand

**SMTP-Konfiguration (config.php):**
```php
SMTP_HOST: smtp.ionos.de
SMTP_PORT: 587
SMTP_SECURE: tls
SMTP_USER: mail@test.business-consulting.de
SMTP_FROM_NAME: IBC Intranet
```

**Verwendung:**
```php
$mailService = new MailService();
$mailService->sendEmail(
    'empfaenger@example.com',
    'Betreff',
    '<h1>HTML-Inhalt</h1>',
    'Empfängername'
);
```

### 4. src/HelperService.php

**Änderungen:**
- Akzeptiert beide DB-Verbindungen (pdoContent und pdoUser)
- Nutzt MailService für E-Mail-Versand
- Alte PHPMailer-Direktintegration entfernt
- event_helper-Tabellen werden aus Content-DB abgefragt
- users-Tabelle wird aus User-DB abgefragt

**Neue Konstruktor-Signatur:**
```php
public function __construct(PDO $pdoContent, PDO $pdoUser, ?MailService $mailService = null)
```

### 5. templates/pages/events.php

**Änderungen:**
- Instanziiert MailService
- Holt beide Datenbankverbindungen
- Übergibt beide Verbindungen an HelperService

**Beispiel:**
```php
$pdoContent = Database::getContentConnection();
$pdoUser = Database::getUserConnection();
$mailService = new MailService();
$helperService = new HelperService($pdoContent, $pdoUser, $mailService);
```

### 6. config/config.php

**Änderung:**
- `SMTP_FROM_NAME` aktualisiert von "JE Alumni Connect" zu "IBC Intranet"

## Bestätigungsmail bei Helfer-Anmeldung

Wenn sich ein Helfer für einen Event-Slot anmeldet:
1. HelperService registriert den Helfer in `event_helper_registrations` (Content-DB)
2. HelperService ruft Benutzerdaten aus `users` ab (User-DB)
3. MailService sendet Bestätigungs-E-Mail über IONOS SMTP
4. E-Mail enthält Event-Details, Aufgabe, Datum/Zeit und Ort

## Kompatibilität

Die Änderungen sind größtenteils rückwärtskompatibel:
- `Database::getConnection()` gibt weiterhin eine PDO-Verbindung zurück (Content-DB)
- Bestehender Code, der nur eine Datenbank nutzt, funktioniert weiter
- Neue Code-Teile sollten explizit `getContentConnection()` oder `getUserConnection()` verwenden

## Zukünftige Verbesserungen

Die folgenden Klassen sollten in Zukunft auf die Zwei-Datenbank-Architektur migriert werden:
- `Auth` - Verwendet beide Datenbanken
- `SystemLogger` - Verwendet beide Datenbanken
- `Event` - Sollte Content-DB verwenden
- `Project` - Sollte Content-DB verwenden
- `Inventory` - Sollte Content-DB verwenden
- `Alumni` - Sollte User-DB verwenden

## Sicherheit

- Datenbank-Credentials sind in .env-Datei (nicht im Repository)
- Fallback-Werte in config/db.php für Entwicklung
- SMTP-Passwörter werden nie im Code geloggt
- Fehlerbehandlung ohne sensitive Daten in Ausgabe

## Testen

Um die Verbindung zu testen (wenn Datenbanken verfügbar sind):

```bash
php -r "
require 'config/config.php';
require 'config/db.php';
try {
    \$c = Database::getContentConnection();
    echo 'Content-DB: OK\n';
} catch (Exception \$e) {
    echo 'Content-DB: FEHLER\n';
}
try {
    \$u = Database::getUserConnection();
    echo 'User-DB: OK\n';
} catch (Exception \$e) {
    echo 'User-DB: FEHLER\n';
}
"
```

## Support

Bei Problemen oder Fragen:
- Logs prüfen: `/logs/app.log` und `/logs/mail.log`
- Datenbankverbindung testen
- SMTP-Konfiguration überprüfen
- Composer-Dependencies installieren: `composer install`
