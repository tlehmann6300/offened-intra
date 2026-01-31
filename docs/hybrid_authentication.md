# Hybrid Authentication Model

## Overview

Das IBC-Intranet nutzt nun ein hybrides Authentifizierungsmodell:
- **Admins und Vorstand**: Nutzen weiterhin Microsoft SSO
- **Alumni**: Loggen sich mit E-Mail und Passwort ein

## Funktionsweise

### 1. Login-Prozess (Automatisch)

Die `login()` Methode in `src/Auth.php` erkennt automatisch die Authentifizierungsmethode:

```php
// Wenn ein Passwort in der Datenbank hinterlegt ist:
// → Passwort-Check wird durchgeführt

// Wenn KEIN Passwort hinterlegt ist:
// → Microsoft SSO wird verlangt
```

### 2. Alumni-Konten erstellen (Vorstand-Funktion)

Nur Vorstand und Admins können Alumni-Konten erstellen:

```php
$auth = new Auth($pdo, $systemLogger);

// Alumni-Konto erstellen
$result = $auth->createAlumniAccount(
    'alumni@example.com',  // E-Mail
    'Max',                 // Vorname
    'Mustermann',         // Nachname
    'Sicheres_Passwort123!' // Initiales Passwort
);

if ($result['success']) {
    echo "Alumni-Konto erstellt! User ID: " . $result['user_id'];
} else {
    echo "Fehler: " . $result['message'];
}
```

## Sicherheitsfeatures

### Password Hashing
- Passwörter werden mit `password_hash()` verschlüsselt
- Standard-Algorithmus: bcrypt (sicher und empfohlen)
- Nie Klartext-Passwörter in der Datenbank

### Validierung
- **E-Mail**: Format-Validierung mit `filter_var()`
- **Passwort**: Mindestlänge 8 Zeichen
- **Duplikate**: Prüfung ob E-Mail bereits existiert
- **Berechtigung**: Nur vorstand/admin können Alumni erstellen

### Logging
Alle Aktionen werden geloggt:
- Alumni-Konto-Erstellung
- Login-Versuche (erfolgreich/fehlgeschlagen)
- Fehlgeschlagene Berechtigungsprüfungen

## Datenbank-Struktur

Die `users` Tabelle enthält:
```sql
- id: INT (Primary Key)
- email: VARCHAR
- firstname: VARCHAR
- lastname: VARCHAR
- role: ENUM ('none', 'mitglied', 'alumni', 'vorstand', 'ressortleiter', 'admin')
- password: VARCHAR(255) -- Gehashtes Passwort (NULL für SSO-User)
- created_at: TIMESTAMP
- updated_at: TIMESTAMP
```

## Benutzertypen

| Rolle | Authentifizierung | Passwort in DB |
|-------|------------------|----------------|
| Admin | Microsoft SSO | NULL |
| Vorstand | Microsoft SSO | NULL |
| Ressortleiter | Microsoft SSO | NULL |
| Alumni | E-Mail + Passwort | Gehashed |
| Mitglied | Microsoft SSO | NULL |

## API Integration (Beispiel)

### API Endpoint: `/api/create_alumni_account.php`

Ein vollständig funktionsfähiger API-Endpunkt ist unter `api/create_alumni_account.php` verfügbar.

**Methode:** POST

**Erforderliche Parameter:**
- `csrf_token` - CSRF-Schutz-Token aus der Session
- `email` - E-Mail-Adresse des Alumni
- `firstname` - Vorname
- `lastname` - Nachname  
- `password` - Initiales Passwort (mind. 8 Zeichen)

**Antwort-Format:** JSON
```json
{
  "success": true|false,
  "message": "Erfolgs- oder Fehlermeldung",
  "user_id": 123  // nur bei Erfolg
}
```

**HTTP Status Codes:**
- `201 Created` - Konto erfolgreich erstellt
- `400 Bad Request` - Ungültige Eingabe
- `401 Unauthorized` - Nicht angemeldet
- `403 Forbidden` - Keine Berechtigung oder ungültiger CSRF-Token
- `405 Method Not Allowed` - Falsche HTTP-Methode
- `500 Internal Server Error` - Serverfehler

### JavaScript Beispiel

```javascript
async function createAlumniAccount(email, firstname, lastname, password) {
    const formData = new URLSearchParams({
        csrf_token: document.querySelector('[name="csrf_token"]').value,
        email: email,
        firstname: firstname,
        lastname: lastname,
        password: password
    });
    
    try {
        const response = await fetch('/api/create_alumni_account.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            console.log('Alumni-Konto erstellt:', data.user_id);
            return data;
        } else {
            console.error('Fehler:', data.message);
            throw new Error(data.message);
        }
    } catch (error) {
        console.error('Netzwerkfehler:', error);
        throw error;
    }
}

// Verwendung
createAlumniAccount(
    'alumni@example.com',
    'Max',
    'Mustermann',
    'SecurePassword123!'
)
.then(result => {
    alert('Konto erfolgreich erstellt!');
})
.catch(error => {
    alert('Fehler: ' + error.message);
});
```

### HTML Formular Beispiel

Ein vollständiges HTML-Formular-Beispiel ist unter `docs/create_alumni_form_example.html` verfügbar.

### PHP Beispiel (Direkte Verwendung)

Erstellen eines Alumni-Kontos direkt in PHP:

```php
// api/create_alumni.php
require_once '../config/config.php';
require_once '../config/db.php';
require_once '../src/Auth.php';
require_once '../src/SystemLogger.php';

session_start();

$auth = new Auth($pdo, new SystemLogger($pdo));

// Berechtigungsprüfung
if (!$auth->isLoggedIn() || !in_array($auth->getUserRole(), ['vorstand', 'admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Keine Berechtigung']);
    exit;
}

// CSRF-Token prüfen
if (!$auth->verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Ungültiger CSRF-Token']);
    exit;
}

// Alumni-Konto erstellen
$result = $auth->createAlumniAccount(
    $_POST['email'] ?? '',
    $_POST['firstname'] ?? '',
    $_POST['lastname'] ?? '',
    $_POST['password'] ?? ''
);

echo json_encode($result);
```

## Fehlerbehandlung

Die `createAlumniAccount()` Methode gibt immer ein Array zurück:

```php
[
    'success' => bool,    // true bei Erfolg, false bei Fehler
    'message' => string,  // Benutzerfreundliche Fehlermeldung
    'user_id' => int      // Nur bei Erfolg: Die neue User-ID
]
```

### Mögliche Fehlermeldungen:
- "Sie müssen angemeldet sein, um Alumni-Konten zu erstellen."
- "Keine Berechtigung. Nur Vorstand und Admins können Alumni-Konten erstellen."
- "Ungültige E-Mail-Adresse."
- "Alle Felder sind erforderlich (E-Mail, Vorname, Nachname, Passwort)."
- "Das Passwort muss mindestens 8 Zeichen lang sein."
- "Ein Konto mit dieser E-Mail-Adresse existiert bereits."

## Best Practices

1. **Sichere Passwörter**: Empfehlen Sie Alumni, starke Passwörter zu verwenden
2. **Initiale Passwörter**: Verwenden Sie sichere, zufällige Passwörter bei der Erstellung
3. **Passwort-Reset**: Implementieren Sie einen Passwort-Reset-Mechanismus für Alumni
4. **2FA (Optional)**: Erwägen Sie Zwei-Faktor-Authentifizierung für erhöhte Sicherheit

## Wartung

### Passwort ändern (Zukünftige Erweiterung)
```php
// Beispiel für eine zusätzliche Methode (noch nicht implementiert)
public function changePassword(int $userId, string $oldPassword, string $newPassword): array {
    // Implementierung für Passwort-Änderung
}
```

### Passwort zurücksetzen (Zukünftige Erweiterung)
```php
// Beispiel für eine zusätzliche Methode (noch nicht implementiert)
public function resetPassword(string $email): array {
    // Implementierung für Passwort-Reset
}
```
