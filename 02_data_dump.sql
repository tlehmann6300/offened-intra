-- =====================================================================
-- IBC-INTRANET TEST-DATEN (DATA DUMP)
-- Version: 2.0
-- MySQL Version: 8.0+
-- Erstellt: 2026-01-31
-- =====================================================================
-- 
-- INHALT:
-- 
-- 1. USER-DATENBANK (dbs15253086)
--    - Administrator/1. Vorstand: Tom Lehmann
--    
-- 2. CONTENT-DATENBANK (dbs15161271)
--    - Inventar-Kategorien (Getränke, Becher, Kostüme, Tische)
--    - Test-Event: Sommerfest mit 2 Helfer-Slots
--    - Beispiel-Projekt
--    - Beispiel-News
-- 
-- =====================================================================

-- =====================================================================
-- USER-DATENBANK (dbs15253086) - TEST-DATEN
-- =====================================================================

-- Hinweis: Für die Ausführung dieser SQL-Datei müssen Sie zunächst
-- zur User-Datenbank wechseln:
-- USE dbs15253086;

-- ---------------------------------------------------------------------
-- Test-Benutzer: Tom Lehmann (Administrator / 1. Vorstand)
-- ---------------------------------------------------------------------
-- E-Mail: tom.lehmann@business-consulting.de
-- Passwort: AdminPass2024! (Bcrypt-Hash)
-- Rolle: admin (entspricht 1. Vorstand)
-- Geburtstag: 15. März 1995
-- 2FA: Deaktiviert für erste Anmeldung
-- ---------------------------------------------------------------------

INSERT INTO `users` (
  `id`,
  `email`,
  `password`,
  `firstname`,
  `lastname`,
  `role`,
  `birthdate`,
  `notify_birthday`,
  `tfa_secret`,
  `tfa_enabled`,
  `totp_verified_at`,
  `is_alumni_validated`,
  `alumni_status_requested_at`,
  `created_at`,
  `updated_at`
) VALUES (
  1,
  'tom.lehmann@business-consulting.de',
  '$2y$10$USbK6zAhoQA8oLyJs3mSV.oDYitcV4/XvwzSkMpYEJgfogC7LkvsS',
  'Tom',
  'Lehmann',
  'admin',
  '1995-03-15',
  1,
  NULL,
  0,
  NULL,
  1,
  NULL,
  '2026-01-01 10:00:00',
  '2026-01-01 10:00:00'
) ON DUPLICATE KEY UPDATE
  `email` = VALUES(`email`),
  `firstname` = VALUES(`firstname`),
  `lastname` = VALUES(`lastname`);


-- =====================================================================
-- CONTENT-DATENBANK (dbs15161271) - TEST-DATEN
-- =====================================================================

-- Hinweis: Für die Ausführung dieses Teils müssen Sie zur Content-Datenbank
-- wechseln:
-- USE dbs15161271;

-- ---------------------------------------------------------------------
-- Inventar-Kategorien
-- ---------------------------------------------------------------------

INSERT INTO `inventory_categories` (`id`, `key_name`, `display_name`, `is_active`, `created_at`) VALUES
(1, 'drinks', 'Getränke', 1, '2026-01-01 10:00:00'),
(2, 'cups', 'Becher', 1, '2026-01-01 10:00:00'),
(3, 'costumes', 'Kostüme', 1, '2026-01-01 10:00:00'),
(4, 'tables', 'Tische', 1, '2026-01-01 10:00:00'),
(5, 'chairs', 'Stühle', 1, '2026-01-01 10:00:00'),
(6, 'decoration', 'Dekoration', 1, '2026-01-01 10:00:00'),
(7, 'technology', 'Technik', 1, '2026-01-01 10:00:00'),
(8, 'office', 'Büromaterial', 1, '2026-01-01 10:00:00')
ON DUPLICATE KEY UPDATE
  `display_name` = VALUES(`display_name`),
  `is_active` = VALUES(`is_active`);

-- ---------------------------------------------------------------------
-- Inventar-Standorte
-- ---------------------------------------------------------------------

INSERT INTO `inventory_locations` (`id`, `name`, `is_active`, `created_at`) VALUES
(1, 'Hauptlager', 1, '2026-01-01 10:00:00'),
(2, 'Büro München', 1, '2026-01-01 10:00:00'),
(3, 'Eventlager', 1, '2026-01-01 10:00:00'),
(4, 'Externe Lagerung', 1, '2026-01-01 10:00:00')
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `is_active` = VALUES(`is_active`);

-- ---------------------------------------------------------------------
-- Test-Event: Sommerfest 2026
-- ---------------------------------------------------------------------

INSERT INTO `events` (
  `id`,
  `title`,
  `description`,
  `event_date`,
  `location`,
  `max_participants`,
  `image_path`,
  `created_by`,
  `created_at`,
  `updated_at`
) VALUES (
  1,
  'Sommerfest 2026',
  '<p>Unser jährliches Sommerfest findet auch dieses Jahr wieder statt!</p>
<p>Wir freuen uns auf einen tollen Tag mit leckerem Essen, Getränken und guter Musik.</p>
<p><strong>Programm:</strong></p>
<ul>
<li>14:00 Uhr - Begrüßung</li>
<li>14:30 Uhr - Grillbuffet</li>
<li>16:00 Uhr - Spiele und Aktivitäten</li>
<li>18:00 Uhr - Live-Musik</li>
<li>22:00 Uhr - Ausklang</li>
</ul>
<p>Wir suchen noch Helfer für verschiedene Aufgaben. Meldet euch gerne an!</p>',
  '2026-07-15 14:00:00',
  'Biergarten am Englischen Garten, München',
  100,
  NULL,
  1,
  '2026-01-01 10:00:00',
  '2026-01-01 10:00:00'
) ON DUPLICATE KEY UPDATE
  `title` = VALUES(`title`),
  `description` = VALUES(`description`),
  `event_date` = VALUES(`event_date`),
  `location` = VALUES(`location`);

-- ---------------------------------------------------------------------
-- Helfer-Slots für Sommerfest
-- ---------------------------------------------------------------------

INSERT INTO `event_helper_slots` (
  `id`,
  `event_id`,
  `task_name`,
  `required_helpers`,
  `description`,
  `created_at`
) VALUES
(1, 1, 'Catering', 4, 'Unterstützung beim Aufbau und Betrieb des Grillbuffets. Helfer sollten zwischen 13:00 und 17:00 Uhr verfügbar sein.', '2026-01-01 10:00:00'),
(2, 1, 'Aufbau', 6, 'Aufbau von Tischen, Stühlen und Dekoration. Helfer werden von 12:00 bis 14:00 Uhr benötigt.', '2026-01-01 10:00:00'),
(3, 1, 'Abbau', 4, 'Abbau und Aufräumen nach dem Event. Helfer werden ab 22:00 Uhr für ca. 2 Stunden benötigt.', '2026-01-01 10:00:00')
ON DUPLICATE KEY UPDATE
  `task_name` = VALUES(`task_name`),
  `required_helpers` = VALUES(`required_helpers`),
  `description` = VALUES(`description`);

-- ---------------------------------------------------------------------
-- Beispiel-Projekt: Digitalisierungs-Workshop
-- ---------------------------------------------------------------------

INSERT INTO `projects` (
  `id`,
  `title`,
  `description`,
  `client`,
  `project_type`,
  `status`,
  `start_date`,
  `end_date`,
  `budget`,
  `team_size`,
  `project_lead_id`,
  `image_path`,
  `created_by`,
  `created_at`,
  `updated_at`
) VALUES (
  1,
  'Digitalisierungs-Workshop 2026',
  '<p>Durchführung eines mehrtägigen Workshops zum Thema Digitale Transformation für einen mittelständischen Kunden.</p>
<h3>Projektziele:</h3>
<ul>
<li>Vermittlung von Grundlagen der Digitalisierung</li>
<li>Entwicklung einer Digitalisierungsstrategie</li>
<li>Implementierung erster Quick Wins</li>
<li>Schulung der Mitarbeiter</li>
</ul>
<h3>Projektergebnis:</h3>
<p>Ausarbeitung einer umfassenden Roadmap für die digitale Transformation des Unternehmens mit konkreten Handlungsempfehlungen.</p>',
  'Mittelständisches Produktionsunternehmen',
  'Workshop',
  'active',
  '2026-02-01',
  '2026-04-30',
  15000.00,
  5,
  1,
  NULL,
  1,
  '2026-01-01 10:00:00',
  '2026-01-01 10:00:00'
) ON DUPLICATE KEY UPDATE
  `title` = VALUES(`title`),
  `description` = VALUES(`description`),
  `status` = VALUES(`status`);

-- ---------------------------------------------------------------------
-- Beispiel-News: Willkommen im neuen Intranet
-- ---------------------------------------------------------------------

INSERT INTO `news` (
  `id`,
  `title`,
  `content`,
  `author_id`,
  `image_path`,
  `is_published`,
  `created_at`,
  `updated_at`
) VALUES (
  1,
  'Willkommen im neuen IBC-Intranet!',
  '<h2>Herzlich Willkommen!</h2>
<p>Wir freuen uns, euch unser komplett überarbeitetes Intranet-System präsentieren zu können!</p>

<h3>Was ist neu?</h3>
<ul>
<li><strong>Moderne Benutzeroberfläche:</strong> Schneller, intuitiver und mobil-optimiert</li>
<li><strong>Zwei-Faktor-Authentifizierung:</strong> Erhöhte Sicherheit für alle Benutzerkonten</li>
<li><strong>Verbessertes Inventar-Management:</strong> Bessere Übersicht und Verwaltung unserer Ressourcen</li>
<li><strong>Event-Management:</strong> Einfache Planung und Helfer-Koordination für Events</li>
<li><strong>Projekt-Verwaltung:</strong> Zentrale Übersicht über alle laufenden Projekte</li>
<li><strong>Alumni-Netzwerk:</strong> Verbesserter Austausch mit unseren Alumni</li>
</ul>

<h3>Neue Features im Detail</h3>

<h4>🔐 Sicherheit</h4>
<p>Mit der neuen Zwei-Faktor-Authentifizierung (2FA) ist euer Konto noch besser geschützt. Die Einrichtung ist optional, wird aber dringend empfohlen.</p>

<h4>📦 Inventar</h4>
<p>Das Inventar-System wurde komplett überarbeitet. Ihr könnt jetzt:</p>
<ul>
<li>Artikel mit Fotos versehen</li>
<li>Einkaufspreise und Gesamtwerte tracken</li>
<li>Standorte und Kategorien flexibel verwalten</li>
<li>Verantwortliche Personen zuweisen</li>
</ul>

<h4>🎉 Events</h4>
<p>Die Event-Verwaltung ermöglicht jetzt:</p>
<ul>
<li>Detaillierte Event-Beschreibungen mit Bildern</li>
<li>Helfer-Slots mit konkreten Aufgaben</li>
<li>Einfache Anmeldung als Helfer</li>
<li>Automatische E-Mail-Benachrichtigungen</li>
</ul>

<h4>🎓 Alumni-Netzwerk</h4>
<p>Alumni können jetzt:</p>
<ul>
<li>Detaillierte Profile mit Karriereinformationen anlegen</li>
<li>Als Mentoren für aktive Mitglieder zur Verfügung stehen</li>
<li>Ihre Expertise und Kompetenzen teilen</li>
<li>Mit LinkedIn-Profilen verknüpfen</li>
</ul>

<h3>Erste Schritte</h3>
<p>Nach dem Login könnt ihr direkt loslegen:</p>
<ol>
<li>Vervollständigt euer Profil</li>
<li>Richtet optional die 2FA ein (empfohlen)</li>
<li>Schaut euch die bevorstehenden Events an</li>
<li>Stöbert im Alumni-Verzeichnis</li>
</ol>

<h3>Support</h3>
<p>Bei Fragen oder Problemen steht euch das IT-Team jederzeit zur Verfügung. Meldet euch einfach per E-Mail oder sprecht uns direkt an.</p>

<p><em>Viel Spaß mit dem neuen System!</em></p>
<p><strong>- Das IBC-Team</strong></p>',
  1,
  NULL,
  1,
  '2026-01-01 10:00:00',
  '2026-01-01 10:00:00'
) ON DUPLICATE KEY UPDATE
  `title` = VALUES(`title`),
  `content` = VALUES(`content`);

-- ---------------------------------------------------------------------
-- Beispiel Inventar-Artikel
-- ---------------------------------------------------------------------

INSERT INTO `inventory` (
  `id`,
  `name`,
  `category_id`,
  `location_id`,
  `quantity`,
  `purchase_price`,
  `responsible_user_id`,
  `status`,
  `description`,
  `image_path`,
  `created_by`,
  `created_at`,
  `updated_at`
) VALUES
(1, 'Biertischgarnitur komplett', 4, 3, 10, 89.99, 1, 'active', 'Komplette Biertischgarnituren mit Tisch und zwei Bänken. Ideal für Events.', NULL, 1, '2026-01-01 10:00:00', '2026-01-01 10:00:00'),
(2, 'Klappstühle weiß', 5, 3, 50, 12.50, 1, 'active', 'Weiße Klappstühle für Events und Veranstaltungen.', NULL, 1, '2026-01-01 10:00:00', '2026-01-01 10:00:00'),
(3, 'Einweg-Becher 0,4l', 2, 1, 500, 0.15, 1, 'active', 'Recyclebare Einweg-Becher für Getränke.', NULL, 1, '2026-01-01 10:00:00', '2026-01-01 10:00:00'),
(4, 'Mineralwasser 1,0l', 1, 1, 100, 0.35, 1, 'active', 'Mineralwasser in 1-Liter-Flaschen (Kiste à 12 Flaschen).', NULL, 1, '2026-01-01 10:00:00', '2026-01-01 10:00:00')
ON DUPLICATE KEY UPDATE
  `name` = VALUES(`name`),
  `quantity` = VALUES(`quantity`),
  `purchase_price` = VALUES(`purchase_price`);

-- =====================================================================
-- ENDE DER TEST-DATEN
-- =====================================================================

-- ---------------------------------------------------------------------
-- HINWEISE ZUR VERWENDUNG
-- ---------------------------------------------------------------------
-- 
-- 1. Passwort für Test-User 'tomlehmann':
--    E-Mail: tom.lehmann@business-consulting.de
--    Passwort: AdminPass2024!
-- 
-- 2. Die Daten verwenden ON DUPLICATE KEY UPDATE, sodass sie
--    mehrfach ausgeführt werden können ohne Fehler zu verursachen.
-- 
-- 3. User-IDs (created_by, responsible_user_id, etc.) verweisen
--    auf die User-Datenbank (dbs15253086).
-- 
-- 4. Für produktive Systeme sollten diese Test-Daten durch
--    echte Daten ersetzt werden.
-- 
-- ---------------------------------------------------------------------
