# Modular Architecture Documentation

## Overview

Das IBC-Intra-System verwendet eine strikte modulare Struktur mit zentralisierter Authentifizierung und Routing. Diese Architektur gewährleistet eine klare Trennung der Zuständigkeiten und ein nahtloses Single-Sign-On-Erlebnis über alle Module hinweg.

## Architekturkomponenten

### 1. Zentraler Router (index.php)

Der `index.php` fungiert als zentraler Entry Point und Controller für die gesamte Anwendung:

**Hauptaufgaben:**
- Laden der Konfiguration und Abhängigkeiten
- Initialisierung des Auth-Systems
- Verarbeitung der Routing-Logik
- Durchführung von Authentifizierungs- und Berechtigungsprüfungen
- Laden der entsprechenden Template-Dateien

**Routing-Flow:**
```
1. Request eingehend → index.php
2. Session-Validierung (Auth::isLoggedIn())
3. Session-Timeout-Prüfung (Auth::checkSessionTimeout())
4. Modul-Berechtigungsprüfung (Auth::checkPermission())
5. Template laden (templates/pages/{module}.php)
6. Ausgabe rendern (mit Header/Footer Layout)
```

### 2. Authentifizierungssystem (src/Auth.php)

Die `Auth`-Klasse steuert die zentrale Session-Validierung:

**Kernfunktionen:**
- `isLoggedIn()`: Prüft, ob ein Benutzer angemeldet ist
- `checkSessionTimeout()`: Validiert Session-Timeout und aktualisiert Activity-Timestamp
- `checkPermission($role)`: Prüft, ob der Benutzer die erforderliche Rolle besitzt
- `getUserRole()`: Gibt die aktuelle Benutzerrolle zurück
- `login()`: Manuelle E-Mail/Passwort-Authentifizierung
- `logout()`: Beendet die Session

**Rollenhierarchie:**
```
admin (Level 5)          ← Vollzugriff auf alle Funktionen
├── vorstand (Level 4)   ← Vorstandszugriff
├── ressortleiter (Level 3) ← Ressortleiter mit erweiterten Berechtigungen
├── alumni (Level 2)     ← Alumni-Mitglieder
├── mitglied (Level 1)   ← Reguläre Mitglieder
└── none (Level 0)       ← Kein Zugriff
```

### 3. Module (templates/pages/)

Jedes Modul hat seine eigene dedizierte Datei im `templates/pages/`-Verzeichnis:

#### Events-Modul
- **Datei:** `templates/pages/events.php`
- **Minimale Rolle:** `mitglied`
- **Funktionalität:** Anzeige und Verwaltung von Veranstaltungen
- **Klasse:** `src/Event.php`

#### Alumni-Modul
- **Datei:** `templates/pages/alumni.php`
- **Minimale Rolle:** `mitglied`
- **Funktionalität:** Alumni-Netzwerk und Profilsuche
- **Klasse:** `src/Alumni.php`

#### Inventar-Modul
- **Datei:** `templates/pages/inventory.php`
- **Minimale Rolle:** `mitglied`
- **Funktionalität:** Inventarverwaltung und -tracking
- **Klasse:** `src/Inventory.php`

## Single-Sign-On (SSO) Implementation

### Session-Sharing-Mechanismus

Das System unterstützt zwei Authentifizierungsmethoden mit identischer Session-Struktur:

#### 1. Microsoft SSO (templates/pages/microsoft_callback.php)
```php
$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['role'] = $user['role'];
$_SESSION['email'] = $user['email'];
$_SESSION['firstname'] = $user['firstname'];
$_SESSION['lastname'] = $user['lastname'];
$_SESSION['last_activity'] = time();
$_SESSION['auth_method'] = 'microsoft';
```

#### 2. E-Mail/Passwort-Login (Auth::login())
```php
$_SESSION['user_id'] = (int)$user['id'];
$_SESSION['role'] = $user['role'];
$_SESSION['email'] = $user['email'];
$_SESSION['firstname'] = $user['firstname'];
$_SESSION['lastname'] = $user['lastname'];
$_SESSION['last_activity'] = time();
$_SESSION['auth_method'] = 'manual';
```

### Nahtlose Modulzugriffe

Nach erfolgreicher Anmeldung (unabhängig von der Methode):
1. Die Session wird zentral von der `Auth`-Klasse validiert
2. Benutzer können auf alle Module zugreifen, für die sie berechtigt sind
3. Keine erneute Anmeldung erforderlich
4. Session-Timeout wird automatisch überwacht

## Berechtigungsprüfung

### Modul-Berechtigungen (index.php)

Die Modul-Berechtigungen sind zentral im Router definiert:

```php
$modulePermissions = [
    'events' => 'mitglied',              // Events-Modul
    'alumni' => 'mitglied',              // Alumni-Modul
    'inventory' => 'mitglied',           // Inventar-Modul
    'event_management' => 'ressortleiter', // Event-Verwaltung
    'inventory_config' => 'ressortleiter', // Inventar-Konfiguration
    'admin_dashboard' => 'admin',        // Admin-Dashboard
    // ... weitere Module
];
```

### Prüfungsablauf

```
1. Benutzer fordert Seite an: index.php?page=events
2. Router prüft: Ist Seite öffentlich? (Nein)
3. Router prüft: Ist Benutzer angemeldet? (Auth::isLoggedIn())
4. Router prüft: Session-Timeout? (Auth::checkSessionTimeout())
5. Router prüft: Rolle 'none'? → Umleitung zu Rollenauswahl
6. Router prüft: Modul-Berechtigung? (Auth::checkPermission('mitglied'))
7. Bei Erfolg: Template laden
8. Bei Fehler: 403 Fehlerseite mit Rolleninformation
```

## Sicherheitsfeatures

### 1. Zentrale Berechtigungsprüfung
- Alle geschützten Seiten werden durch den Router geprüft
- Keine Umgehung durch direkten Template-Zugriff möglich
- Konsistente Fehlerbehandlung

### 2. Session-Sicherheit
- Automatische Session-Regenerierung nach Login
- CSRF-Token-Generierung
- Session-Timeout-Überwachung
- Secure Session-Konfiguration

### 3. Rate Limiting
- Login-Versuche werden begrenzt (5 Versuche in 15 Minuten)
- IP-basierte Beschränkung
- Automatische Sperrung bei zu vielen Fehlversuchen

### 4. Fehlerbehandlung
- Detaillierte Fehlerprotokollierung (error_log)
- Benutzerfreundliche Fehlermeldungen
- Kein Exposure sensibler Systeminformationen

## Best Practices für Entwickler

### Neues Modul hinzufügen

1. **Erstelle Template-Datei:**
   ```
   templates/pages/mein_modul.php
   ```

2. **Füge Berechtigung hinzu (index.php):**
   ```php
   $modulePermissions = [
       // ... bestehende Module
       'mein_modul' => 'mitglied', // oder andere Rolle
   ];
   ```

3. **Erstelle Service-Klasse (optional):**
   ```
   src/MeinModul.php
   ```

4. **Teste Zugriff:**
   - Als verschiedene Rollen einloggen
   - Zugriffsrechte verifizieren
   - Fehlerbehandlung testen

### Berechtigungen in Templates prüfen

Innerhalb von Templates können zusätzliche Berechtigungsprüfungen durchgeführt werden:

```php
<?php
// Beispiel: Nur Admin kann diesen Button sehen
if ($auth->getUserRole() === 'admin'): ?>
    <button>Admin-Funktion</button>
<?php endif; ?>

// Beispiel: Rollenbasierte Anzeige
<?php if (in_array($auth->getUserRole(), ['admin', 'vorstand'], true)): ?>
    <div class="admin-panel">...</div>
<?php endif; ?>
```

### AJAX-Handler Sicherheit

AJAX-Endpunkte müssen ebenfalls Berechtigungen prüfen:

```php
// In template oder API-Datei
if (!$auth->isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Nicht angemeldet']);
    exit;
}

// Optional: Berechtigungsprüfung
if (!$auth->checkPermission('ressortleiter')) {
    echo json_encode(['success' => false, 'message' => 'Keine Berechtigung']);
    exit;
}
```

## Troubleshooting

### Problem: Benutzer wird auf Login umgeleitet
**Ursache:** Session nicht vorhanden oder abgelaufen
**Lösung:** 
- Session-Timeout in `config/config.php` prüfen (SESSION_LIFETIME)
- Session-Cookie-Konfiguration überprüfen

### Problem: 403 Zugriff verweigert
**Ursache:** Benutzer hat nicht die erforderliche Rolle
**Lösung:**
- Benutzerrolle in der Datenbank überprüfen
- Modul-Berechtigungen in `$modulePermissions` überprüfen
- Admin kann Rollen über das Admin-Dashboard anpassen

### Problem: Session funktioniert nicht nach SSO
**Ursache:** Session-Variablen nicht korrekt gesetzt
**Lösung:**
- Überprüfe `microsoft_callback.php` auf korrekte Session-Initialisierung
- Stelle sicher, dass alle erforderlichen Session-Variablen gesetzt werden
- Prüfe error_log für SSO-bezogene Fehler

## Wartung und Updates

### Session-Struktur ändern
Wenn Session-Variablen geändert werden müssen:
1. Aktualisiere `Auth::createUserSession()`
2. Aktualisiere `microsoft_callback.php`
3. Stelle Rückwärtskompatibilität sicher
4. Teste beide Authentifizierungsmethoden

### Neue Rolle hinzufügen
1. Erweitere `Auth::ROLE_HIERARCHY` mit neuer Rolle und Level
2. Aktualisiere `Auth::updateUserRole()` Validierung
3. Füge Rolle zu Datenbank-Schema hinzu
4. Teste Berechtigungsprüfungen

## Zusammenfassung

Die modulare Architektur des IBC-Intra bietet:
- ✅ Klare Trennung der Module (Events, Alumni, Inventory)
- ✅ Zentralisierte Authentifizierung und Session-Verwaltung
- ✅ Nahtloses Single-Sign-On über alle Module
- ✅ Rollenbasierte Zugriffskontrolle
- ✅ Skalierbare und wartbare Struktur
- ✅ Umfassende Sicherheitsfeatures

Durch die zentrale Steuerung im Router und die konsequente Verwendung der Auth-Klasse wird sichergestellt, dass alle Berechtigungen konsistent geprüft werden und Benutzer nach einmaligem Login (Microsoft SSO oder E-Mail) Zugriff auf alle autorisierten Module haben.
