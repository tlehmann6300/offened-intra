# MailService Dokumentation

## Übersicht

Die `MailService`-Klasse bietet eine zentrale E-Mail-Versandlösung für das IBC-Intranet. Sie verwendet PHPMailer mit IONOS SMTP-Konfiguration und unterstützt vorkonfigurierte Templates für professionelle E-Mails im IBC-Design.

## SMTP-Konfiguration

Die Klasse nutzt folgende IONOS SMTP-Einstellungen:

- **Host:** smtp.ionos.de
- **Port:** 587
- **Verschlüsselung:** TLS
- **Absender:** mail@test.business-consulting.de

Diese Einstellungen werden über die Konstanten in `config/config.php` konfiguriert.

## Verfügbare Methoden

### 1. sendNotification()

Sendet eine generische Benachrichtigung mit automatischem IBC-Template-Wrapping.

```php
$mailService = new MailService();

$result = $mailService->sendNotification(
    to: 'empfaenger@example.com',
    subject: 'Wichtige Benachrichtigung',
    body: '<p>Dies ist eine wichtige Nachricht.</p>',
    recipientName: 'Max Mustermann'
);
```

**Parameter:**
- `to` (string): E-Mail-Adresse des Empfängers
- `subject` (string): Betreffzeile
- `body` (string): E-Mail-Inhalt (HTML oder Plain Text)
- `recipientName` (string, optional): Name des Empfängers

**Rückgabe:** `bool` - `true` bei Erfolg, `false` bei Fehler

---

### 2. sendHelperConfirmation()

Sendet eine Bestätigungsmail für Helfer-Anmeldungen bei Events.

```php
$mailService = new MailService();

$eventData = [
    'title' => 'Sommerfest 2024',
    'date' => '2024-07-15',
    'location' => 'Campus Gelände'
];

$slotData = [
    'task_name' => 'Getränkeausgabe',
    'start_time' => '10:00:00',
    'end_time' => '14:00:00'
];

$result = $mailService->sendHelperConfirmation(
    to: 'helfer@example.com',
    recipientName: 'Anna Schmidt',
    eventData: $eventData,
    slotData: $slotData
);
```

**Parameter:**
- `to` (string): E-Mail-Adresse des Helfers
- `recipientName` (string): Vollständiger Name des Helfers
- `eventData` (array): Event-Details
  - `title`: Event-Titel
  - `date`: Event-Datum (Format: YYYY-MM-DD)
  - `location`: Veranstaltungsort
- `slotData` (array): Schicht-Details
  - `task_name`: Aufgabenbeschreibung
  - `start_time`: Startzeit (Format: HH:MM:SS)
  - `end_time`: Endzeit (Format: HH:MM:SS)

**Rückgabe:** `bool` - `true` bei Erfolg, `false` bei Fehler

---

### 3. sendPasswordReset()

Sendet einen Passwort-Reset-Link an einen Benutzer.

```php
$mailService = new MailService();

$resetToken = bin2hex(random_bytes(32));
$resetLink = SITE_URL . '/index.php?page=reset_password&token=' . $resetToken;

$result = $mailService->sendPasswordReset(
    to: 'benutzer@example.com',
    recipientName: 'Thomas Müller',
    resetToken: $resetToken,
    resetLink: $resetLink
);
```

**Parameter:**
- `to` (string): E-Mail-Adresse des Benutzers
- `recipientName` (string): Vollständiger Name des Benutzers
- `resetToken` (string): Eindeutiger Reset-Token
- `resetLink` (string): Vollständiger Link zum Passwort-Reset

**Rückgabe:** `bool` - `true` bei Erfolg, `false` bei Fehler

---

### 4. sendAlumniNotification()

Sendet eine Willkommensnachricht an neue Alumni-Mitglieder.

```php
$mailService = new MailService();

$result = $mailService->sendAlumniNotification(
    to: 'alumni@example.com',
    recipientName: 'Dr. Lisa Weber',
    username: 'alumni@example.com',
    temporaryPassword: 'TempPass123!'  // Optional
);
```

**Parameter:**
- `to` (string): E-Mail-Adresse des Alumni
- `recipientName` (string): Vollständiger Name des Alumni
- `username` (string): Benutzername für den Login
- `temporaryPassword` (string, optional): Temporäres Passwort

**Rückgabe:** `bool` - `true` bei Erfolg, `false` bei Fehler

---

### 5. sendEmail()

Basis-Methode für den E-Mail-Versand (bereits existierend).

```php
$mailService = new MailService();

$htmlBody = '<html><body><h1>Hallo Welt</h1></body></html>';

$result = $mailService->sendEmail(
    to: 'empfaenger@example.com',
    subject: 'Test E-Mail',
    htmlBody: $htmlBody,
    recipientName: 'Test Empfänger',
    plainTextBody: 'Hallo Welt'  // Optional
);
```

---

### 6. sendTextEmail()

Vereinfachte Methode für Text-E-Mails (bereits existierend).

```php
$mailService = new MailService();

$result = $mailService->sendTextEmail(
    to: 'empfaenger@example.com',
    subject: 'Einfache Nachricht',
    message: 'Dies ist eine einfache Textnachricht.',
    recipientName: 'Test Empfänger'
);
```

## Template-Design

Alle E-Mail-Templates verwenden ein einheitliches IBC-Design mit folgenden Merkmalen:

- **Gradient Header:** Linear-Gradient von #667eea bis #764ba2
- **IBC Logo:** Automatisch eingebunden
- **Responsive Design:** Mobile-optimiert
- **Professional Layout:** Karten-basiertes Design mit Schatten
- **Brand Colors:** Konsistente Farbgebung
- **Footer:** Standardisierte Fußzeile mit Disclaimer

## Fehlerbehandlung

Die Klasse loggt alle E-Mail-Aktivitäten in `logs/mail.log`:

```
[2024-01-31 20:00:00] [MAIL-SERVICE] Email sent successfully to user@example.com: Test Subject
[2024-01-31 20:00:05] [MAIL-SERVICE] Failed to send email to invalid@: Invalid email address
```

## Sicherheitshinweise

1. **SMTP-Credentials:** Niemals in den Code hardcoden, immer über `.env` konfigurieren
2. **E-Mail-Validierung:** Alle E-Mail-Adressen werden validiert
3. **HTML-Escaping:** Alle Benutzereingaben werden mit `htmlspecialchars()` escaped
4. **Logging:** Passwörter und sensible Daten werden nicht geloggt

## Verwendungsbeispiele

### Helfer-Bestätigung nach Event-Anmeldung

```php
// In HelperService.php oder ähnlich
$mailService = new MailService();

$eventData = [
    'title' => $event['title'],
    'date' => $event['event_date'],
    'location' => $event['location']
];

$slotData = [
    'task_name' => $slot['task_name'],
    'start_time' => $slot['start_time'],
    'end_time' => $slot['end_time']
];

$mailService->sendHelperConfirmation(
    $user['email'],
    $user['firstname'] . ' ' . $user['lastname'],
    $eventData,
    $slotData
);
```

### Passwort-Reset-Anfrage

```php
// In Auth.php oder forgot_password.php
$mailService = new MailService();

// Token generieren und in DB speichern
$token = bin2hex(random_bytes(32));
$resetLink = SITE_URL . '/index.php?page=reset_password&token=' . $token;

// E-Mail senden
$mailService->sendPasswordReset(
    $user['email'],
    $user['firstname'] . ' ' . $user['lastname'],
    $token,
    $resetLink
);
```

### Alumni-Account-Erstellung

```php
// In create_alumni_account.php
$mailService = new MailService();

// Temporäres Passwort generieren
$tempPassword = bin2hex(random_bytes(8));

// Account erstellen und E-Mail senden
$auth->createAlumniAccount($email, $firstname, $lastname, $tempPassword);

$mailService->sendAlumniNotification(
    $email,
    $firstname . ' ' . $lastname,
    $email,
    $tempPassword
);
```

## Testing

Ein Test-Script ist verfügbar unter `docs/test_mailservice.php`:

```bash
php docs/test_mailservice.php
```

Dies überprüft:
- ✓ Instanziierung der MailService-Klasse
- ✓ Vorhandensein aller erforderlichen Methoden
- ✓ Korrekte Methodensignaturen
- ✓ Template-Datenstruktur-Validierung

## Weiterentwicklung

Mögliche zukünftige Erweiterungen:

1. **Anhänge:** Support für E-Mail-Anhänge
2. **Mehrsprachigkeit:** Templates in verschiedenen Sprachen
3. **Queue-System:** Asynchroner E-Mail-Versand
4. **Analytics:** Tracking von Öffnungsraten und Klicks
5. **A/B-Testing:** Template-Varianten testen

## Support

Bei Problemen oder Fragen:
- **Logs prüfen:** `logs/mail.log`
- **SMTP-Konfiguration:** `config/config.php`
- **PHPMailer Dokumentation:** https://github.com/PHPMailer/PHPMailer
