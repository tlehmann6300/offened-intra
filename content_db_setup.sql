-- =====================================================================
-- IBC-INTRANET CONTENT-DATENBANK SETUP
-- Datenbank: dbs15161271
-- Version: 2.0
-- MySQL Version: 8.0+
-- Erstellt: 2026-01-31
-- =====================================================================
-- 
-- DIESE DATEI ENTHÄLT:
-- - Komplette Schema-Definition für die Content-Datenbank
-- - Test-Daten und Beispiele
-- 
-- VERWENDUNG:
-- 1. Verbinden Sie sich mit der Datenbank dbs15161271
-- 2. Führen Sie diese SQL-Datei aus
-- 
-- =====================================================================

-- Datenbank auswählen (falls noch nicht geschehen)
USE `dbs15161271`;

-- =====================================================================
-- SCHEMA DEFINITION
-- =====================================================================

-- ---------------------------------------------------------------------
-- Tabelle: inventory_categories
-- Beschreibung: Kategorien für Inventar-Artikel
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inventory_categories` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `key_name` VARCHAR(100) NOT NULL COMMENT 'Technischer Schlüssel (z.B. drinks, cups)',
  `display_name` VARCHAR(255) NOT NULL COMMENT 'Anzeigename (z.B. Getränke, Becher)',
  `is_active` BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Kategorie aktiv',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellungszeitpunkt',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_key_name` (`key_name`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Inventar-Kategorien';

-- ---------------------------------------------------------------------
-- Tabelle: inventory_locations
-- Beschreibung: Lagerorte für Inventar-Artikel
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inventory_locations` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL COMMENT 'Standortname',
  `is_active` BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Standort aktiv',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellungszeitpunkt',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_name` (`name`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Inventar-Standorte';

-- ---------------------------------------------------------------------
-- Tabelle: inventory
-- Beschreibung: Inventarverwaltung mit Preisen und Mengen
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inventory` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL COMMENT 'Artikelname',
  `category_id` INT(11) NULL COMMENT 'Verknüpfung zu inventory_categories.id',
  `location_id` INT(11) NULL COMMENT 'Verknüpfung zu inventory_locations.id',
  `quantity` INT(11) NOT NULL DEFAULT 0 COMMENT 'Verfügbare Menge',
  `purchase_price` DECIMAL(10,2) NULL COMMENT 'Einkaufspreis pro Einheit in Euro',
  `responsible_user_id` INT(11) NULL COMMENT 'Verantwortlicher Benutzer (ID-Referenz zur User-DB users.id)',
  `status` ENUM('active', 'archived', 'broken') NOT NULL DEFAULT 'active' COMMENT 'Artikel-Status',
  `description` TEXT NULL COMMENT 'Artikelbeschreibung',
  `image_path` VARCHAR(500) NULL COMMENT 'Pfad zum Artikelbild',
  `created_by` INT(11) NOT NULL COMMENT 'Ersteller (ID-Referenz zur User-DB users.id)',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellungszeitpunkt',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Letzter Update-Zeitpunkt',
  PRIMARY KEY (`id`),
  KEY `idx_name` (`name`),
  KEY `idx_category_id` (`category_id`),
  KEY `idx_location_id` (`location_id`),
  KEY `idx_status` (`status`),
  KEY `idx_responsible_user_id` (`responsible_user_id`),
  KEY `idx_created_by` (`created_by`),
  CONSTRAINT `fk_inventory_category` FOREIGN KEY (`category_id`) REFERENCES `inventory_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_inventory_location` FOREIGN KEY (`location_id`) REFERENCES `inventory_locations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Inventarverwaltung mit Preisen und Mengen';

-- ---------------------------------------------------------------------
-- Tabelle: events
-- Beschreibung: Event-Verwaltung
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `events` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL COMMENT 'Event-Titel',
  `description` TEXT NULL COMMENT 'Event-Beschreibung (HTML)',
  `date` DATETIME NOT NULL COMMENT 'Event-Datum und Uhrzeit',
  `location` VARCHAR(255) NULL COMMENT 'Event-Ort/Veranstaltungsort',
  `max_participants` INT(11) NULL COMMENT 'Maximale Teilnehmerzahl',
  `image_path` VARCHAR(500) NULL COMMENT 'Pfad zum Event-Bild',
  `created_by` INT(11) NOT NULL COMMENT 'Ersteller (ID-Referenz zur User-DB users.id)',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellungszeitpunkt',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Letzter Update-Zeitpunkt',
  PRIMARY KEY (`id`),
  KEY `idx_date` (`date`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Event-Verwaltung';

-- ---------------------------------------------------------------------
-- Tabelle: event_helper_slots
-- Beschreibung: Helfer-Slots für Events mit Aufgabenbeschreibungen
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `event_helper_slots` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `event_id` INT(11) NOT NULL COMMENT 'Verknüpfung zu events.id',
  `task_name` VARCHAR(255) NOT NULL COMMENT 'Aufgabenbezeichnung (z.B. Catering, Aufbau, Abbau)',
  `required_helpers` INT(11) NOT NULL DEFAULT 1 COMMENT 'Benötigte Anzahl Helfer',
  `description` TEXT NULL COMMENT 'Detaillierte Aufgabenbeschreibung',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellungszeitpunkt',
  PRIMARY KEY (`id`),
  KEY `idx_event_id` (`event_id`),
  CONSTRAINT `fk_helper_slots_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Helfer-Slots für Events';

-- ---------------------------------------------------------------------
-- Tabelle: event_helper_registrations
-- Beschreibung: Helfer-Anmeldungen für Event-Slots
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `event_helper_registrations` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `slot_id` INT(11) NOT NULL COMMENT 'Verknüpfung zu event_helper_slots.id',
  `user_id` INT(11) NOT NULL COMMENT 'Angemeldeter Benutzer (ID-Referenz zur User-DB users.id)',
  `registered_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Anmeldezeitpunkt',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_slot_user` (`slot_id`, `user_id`),
  KEY `idx_slot_id` (`slot_id`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `fk_helper_registrations_slot` FOREIGN KEY (`slot_id`) REFERENCES `event_helper_slots` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Helfer-Anmeldungen für Event-Slots';

-- ---------------------------------------------------------------------
-- Tabelle: projects
-- Beschreibung: Projektverwaltung
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `projects` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL COMMENT 'Projekttitel',
  `description` TEXT NULL COMMENT 'Projektbeschreibung (HTML)',
  `status` ENUM('planning', 'active', 'on_hold', 'completed', 'cancelled') NOT NULL DEFAULT 'planning' COMMENT 'Projektstatus',
  `client` VARCHAR(255) NULL COMMENT 'Auftraggeber/Kunde',
  `project_type` VARCHAR(100) NULL COMMENT 'Projekttyp (z.B. Beratung, Workshop)',
  `start_date` DATE NULL COMMENT 'Startdatum',
  `end_date` DATE NULL COMMENT 'Enddatum',
  `budget` DECIMAL(10,2) NULL COMMENT 'Projektbudget in Euro',
  `team_size` INT(11) NULL COMMENT 'Teamgröße',
  `project_lead_id` INT(11) NULL COMMENT 'Projektleiter (ID-Referenz zur User-DB users.id)',
  `image_path` VARCHAR(500) NULL COMMENT 'Pfad zum Projektbild',
  `created_by` INT(11) NOT NULL COMMENT 'Ersteller (ID-Referenz zur User-DB users.id)',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellungszeitpunkt',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Letzter Update-Zeitpunkt',
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_project_lead_id` (`project_lead_id`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_start_date` (`start_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Projektverwaltung';

-- ---------------------------------------------------------------------
-- Tabelle: news
-- Beschreibung: News und Ankündigungen
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `news` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL COMMENT 'News-Titel',
  `content` TEXT NOT NULL COMMENT 'News-Inhalt (HTML/Quill-Format)',
  `author_id` INT(11) NOT NULL COMMENT 'Autor (ID-Referenz zur User-DB users.id)',
  `image_path` VARCHAR(500) NULL COMMENT 'Pfad zum News-Bild',
  `is_published` BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Veröffentlicht (FALSE=Entwurf, TRUE=veröffentlicht)',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellungszeitpunkt',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Letzter Update-Zeitpunkt',
  PRIMARY KEY (`id`),
  KEY `idx_title` (`title`),
  KEY `idx_author_id` (`author_id`),
  KEY `idx_is_published` (`is_published`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='News und Ankündigungen';

-- =====================================================================
-- TEST-DATEN / DUMP DATA
-- =====================================================================

-- ---------------------------------------------------------------------
-- Inventar-Kategorien
-- ---------------------------------------------------------------------

INSERT INTO `inventory_categories` (`id`, `key_name`, `display_name`, `is_active`, `created_at`) VALUES
(1, 'drinks', 'Getränke', TRUE, '2026-01-01 10:00:00'),
(2, 'cups', 'Becher', TRUE, '2026-01-01 10:00:00'),
(3, 'costumes', 'Kostüme', TRUE, '2026-01-01 10:00:00'),
(4, 'tables', 'Tische', TRUE, '2026-01-01 10:00:00'),
(5, 'chairs', 'Stühle', TRUE, '2026-01-01 10:00:00'),
(6, 'decoration', 'Dekoration', TRUE, '2026-01-01 10:00:00'),
(7, 'technology', 'Technik', TRUE, '2026-01-01 10:00:00'),
(8, 'office', 'Büromaterial', TRUE, '2026-01-01 10:00:00')
ON DUPLICATE KEY UPDATE
  `display_name` = VALUES(`display_name`),
  `is_active` = VALUES(`is_active`);

-- ---------------------------------------------------------------------
-- Inventar-Standorte
-- ---------------------------------------------------------------------

INSERT INTO `inventory_locations` (`id`, `name`, `is_active`, `created_at`) VALUES
(1, 'Hauptlager', TRUE, '2026-01-01 10:00:00'),
(2, 'Büro München', TRUE, '2026-01-01 10:00:00'),
(3, 'Eventlager', TRUE, '2026-01-01 10:00:00'),
(4, 'Externe Lagerung', TRUE, '2026-01-01 10:00:00')
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
  `date`,
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
  `date` = VALUES(`date`),
  `location` = VALUES(`location`);

-- ---------------------------------------------------------------------
-- Helfer-Slots für Sommerfest (mindestens 2 Slots wie gefordert)
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
-- Beispiel-Inventar-Artikel
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

<h3>Erste Schritte</h3>
<p>Nach dem Login könnt ihr direkt loslegen:</p>
<ol>
<li>Vervollständigt euer Profil</li>
<li>Richtet optional die 2FA ein (empfohlen)</li>
<li>Schaut euch die bevorstehenden Events an</li>
<li>Stöbert im Alumni-Verzeichnis</li>
</ol>

<p><em>Viel Spaß mit dem neuen System!</em></p>
<p><strong>- Das IBC-Team</strong></p>',
  1,
  NULL,
  TRUE,
  '2026-01-01 10:00:00',
  '2026-01-01 10:00:00'
) ON DUPLICATE KEY UPDATE
  `title` = VALUES(`title`),
  `content` = VALUES(`content`);

-- =====================================================================
-- ENDE DER CONTENT-DATENBANK SETUP
-- =====================================================================

-- ---------------------------------------------------------------------
-- HINWEISE ZUR VERWENDUNG
-- ---------------------------------------------------------------------
-- 
-- 1. Cross-Database-Referenzen:
--    - Felder wie created_by, author_id, user_id, responsible_user_id
--      und project_lead_id verweisen auf users.id in der User-Datenbank
--      (dbs15253086)
--    - Diese Referenzen sind logisch, aber nicht durch Foreign Keys
--      erzwungen (verschiedene Datenbank-Hosts)
--    - Referenzielle Integrität muss in der Anwendungslogik
--      sichergestellt werden
-- 
-- 2. Test-Daten:
--    - Alle Test-Daten verwenden user_id=1 (Tom Lehmann aus User-DB)
--    - Dies funktioniert nur, wenn die User-Datenbank bereits
--      eingerichtet wurde
-- 
-- 3. Datentypen:
--    - DECIMAL(10,2) für Preise und Budgets (Euro-Format)
--    - DATETIME für Event-Datum mit Uhrzeit
--    - TIMESTAMP für created_at/updated_at (automatisch)
--    - BOOLEAN für Flags (TRUE/FALSE statt 1/0)
--    - ENUM für Status-Felder mit vordefinierten Werten
-- 
-- 4. Inventar-System:
--    - Kategorien: Getränke, Becher, Kostüme, Tische (wie gefordert)
--    - Standorte: Hauptlager, Büro, Eventlager, Externe Lagerung
--    - Preise werden als DECIMAL gespeichert
-- 
-- 5. Event-System:
--    - Sommerfest mit 3 Helfer-Slots (Catering, Aufbau, Abbau)
--    - Helfer können sich über event_helper_registrations anmelden
-- 
-- ---------------------------------------------------------------------
