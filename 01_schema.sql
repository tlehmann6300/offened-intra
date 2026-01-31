-- =====================================================================
-- IBC-INTRANET MULTI-DATABASE SCHEMA
-- Version: 2.0
-- MySQL Version: 8.0+
-- Erstellt: 2026-01-31
-- =====================================================================
-- 
-- DATENBANK-ARCHITEKTUR:
-- 
-- 1. USER-DATENBANK (dbs15253086)
--    - Benutzerkonten und Authentication
--    - Alumni-Profile
--    - Login-Versuche (Rate Limiting)
--    - Einladungs-System
-- 
-- 2. CONTENT-DATENBANK (dbs15161271)
--    - Projekte
--    - Inventar (mit Kategorien und Standorten)
--    - Events (mit Helfer-Slots und Anmeldungen)
--    - News
--    - System-Logs
-- 
-- =====================================================================

-- =====================================================================
-- USER-DATENBANK (dbs15253086)
-- =====================================================================

-- Hinweis: Für die Ausführung dieser SQL-Datei müssen Sie zunächst
-- zur User-Datenbank wechseln:
-- USE dbs15253086;

-- ---------------------------------------------------------------------
-- Tabelle: users
-- Beschreibung: Zentrale Benutzerverwaltung mit Authentication
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL,
  `password` VARCHAR(255) NOT NULL COMMENT 'Bcrypt-Hash des Passworts',
  `firstname` VARCHAR(100) NOT NULL,
  `lastname` VARCHAR(100) NOT NULL,
  `role` ENUM('admin', '1v', '2v', '3v', 'ressortleiter', 'mitglied', 'alumni') NOT NULL DEFAULT 'mitglied' COMMENT 'Rollen-Hierarchie: admin=1V, 1v-3v=Vorstand',
  `birthdate` DATE NULL COMMENT 'Geburtsdatum des Benutzers',
  `notify_birthday` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Sichtbarkeit des Geburtstags auf Dashboard (1=sichtbar, 0=privat)',
  `tfa_secret` VARCHAR(32) DEFAULT NULL COMMENT 'Base32-kodierter TOTP-Secret für 2FA',
  `tfa_enabled` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Two-Factor Authentication aktiviert (0=deaktiviert, 1=aktiviert)',
  `totp_verified_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Zeitstempel der ersten 2FA-Verifizierung',
  `is_alumni_validated` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Alumni-Status validiert durch Vorstand (0=ausstehend, 1=validiert)',
  `alumni_status_requested_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Zeitstempel der Alumni-Status-Anfrage',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_email` (`email`),
  KEY `idx_role` (`role`),
  KEY `idx_users_firstname` (`firstname`),
  KEY `idx_users_lastname` (`lastname`),
  KEY `idx_users_birthdate` (`birthdate`),
  KEY `idx_totp_enabled` (`tfa_enabled`),
  KEY `idx_alumni_validation` (`role`, `is_alumni_validated`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Benutzerverwaltung mit Authentication und 2FA';

-- ---------------------------------------------------------------------
-- Tabelle: alumni_profiles
-- Beschreibung: Erweiterte Profile für Alumni-Mitglieder
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `alumni_profiles` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL COMMENT 'Verknüpfung zu users.id',
  `graduation_year` INT(4) NULL COMMENT 'Abschlussjahr',
  `company` VARCHAR(255) NULL COMMENT 'Aktuelles Unternehmen',
  `position` VARCHAR(255) NULL COMMENT 'Aktuelle Position',
  `linkedin_url` VARCHAR(500) NULL COMMENT 'LinkedIn-Profil URL',
  `bio` TEXT NULL COMMENT 'Kurze Biografie',
  `expertise` TEXT NULL COMMENT 'Fachgebiete und Kompetenzen',
  `is_willing_to_mentor` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Bereit für Mentoring (0=nein, 1=ja)',
  `is_alumni_validated` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Profil durch Vorstand validiert (0=ausstehend, 1=validiert)',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_id` (`user_id`),
  KEY `idx_graduation_year` (`graduation_year`),
  KEY `idx_is_willing_to_mentor` (`is_willing_to_mentor`),
  KEY `idx_is_alumni_validated` (`is_alumni_validated`),
  CONSTRAINT `fk_alumni_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Erweiterte Alumni-Profile mit Karriereinformationen';

-- ---------------------------------------------------------------------
-- Tabelle: login_attempts
-- Beschreibung: Tracking von Login-Versuchen für Rate-Limiting
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip_address` VARCHAR(45) NOT NULL COMMENT 'IPv4 oder IPv6 Adresse',
  `email` VARCHAR(255) DEFAULT NULL COMMENT 'E-Mail-Adresse (falls angegeben)',
  `attempt_time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `success` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0 = fehlgeschlagen, 1 = erfolgreich',
  `user_agent` VARCHAR(500) DEFAULT NULL COMMENT 'Browser User-Agent',
  PRIMARY KEY (`id`),
  KEY `idx_ip_time` (`ip_address`, `attempt_time`),
  KEY `idx_email_time` (`email`, `attempt_time`),
  KEY `idx_attempt_time` (`attempt_time`),
  KEY `idx_cleanup` (`attempt_time`, `success`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Login-Versuch-Tracking für Rate-Limiting';

-- ---------------------------------------------------------------------
-- Tabelle: invitations
-- Beschreibung: Token-basiertes Einladungs-System
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `invitations` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL COMMENT 'E-Mail-Adresse des eingeladenen Benutzers',
  `token` VARCHAR(64) NOT NULL COMMENT 'Kryptographischer Token (SHA256-Hash)',
  `role` VARCHAR(50) NOT NULL DEFAULT 'alumni' COMMENT 'Zuzuweisende Rolle nach Registrierung',
  `created_by` INT(11) NOT NULL COMMENT 'User-ID des Admin, der die Einladung erstellt hat',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellungszeitpunkt',
  `expires_at` TIMESTAMP NOT NULL COMMENT 'Ablaufzeitpunkt',
  `accepted_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Annahmezeitpunkt (NULL = ausstehend)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_token` (`token`),
  KEY `idx_email` (`email`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_expires_at` (`expires_at`),
  KEY `idx_accepted_at` (`accepted_at`),
  KEY `idx_pending_invitations` (`email`, `accepted_at`, `expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Token-basiertes Einladungs-System für Benutzerregistrierung';


-- =====================================================================
-- CONTENT-DATENBANK (dbs15161271)
-- =====================================================================

-- Hinweis: Für die Ausführung dieses Teils müssen Sie zur Content-Datenbank
-- wechseln:
-- USE dbs15161271;

-- ---------------------------------------------------------------------
-- Tabelle: projects
-- Beschreibung: Projektverwaltung
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `projects` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL COMMENT 'Projekttitel',
  `description` TEXT NULL COMMENT 'Projektbeschreibung',
  `client` VARCHAR(255) NULL COMMENT 'Auftraggeber/Kunde',
  `project_type` VARCHAR(100) NULL COMMENT 'Projekttyp (z.B. Beratung, Workshop)',
  `status` ENUM('planning', 'active', 'on_hold', 'completed', 'cancelled') NOT NULL DEFAULT 'planning' COMMENT 'Projektstatus',
  `start_date` DATE NULL COMMENT 'Startdatum',
  `end_date` DATE NULL COMMENT 'Enddatum',
  `budget` DECIMAL(10,2) NULL COMMENT 'Projektbudget in Euro',
  `team_size` INT(11) NULL COMMENT 'Teamgröße',
  `project_lead_id` INT(11) NULL COMMENT 'Projektleiter (Referenz auf User-DB users.id)',
  `image_path` VARCHAR(500) NULL COMMENT 'Pfad zum Projektbild',
  `created_by` INT(11) NOT NULL COMMENT 'Ersteller (Referenz auf User-DB users.id)',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_project_lead_id` (`project_lead_id`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_start_date` (`start_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Projektverwaltung';

-- ---------------------------------------------------------------------
-- Tabelle: inventory_categories
-- Beschreibung: Kategorien für Inventar
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inventory_categories` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `key_name` VARCHAR(100) NOT NULL COMMENT 'Technischer Schlüssel (z.B. drinks)',
  `display_name` VARCHAR(255) NOT NULL COMMENT 'Anzeigename (z.B. Getränke)',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Kategorie aktiv (0=inaktiv, 1=aktiv)',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_key_name` (`key_name`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inventar-Kategorien';

-- ---------------------------------------------------------------------
-- Tabelle: inventory_locations
-- Beschreibung: Lagerorte für Inventar
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inventory_locations` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL COMMENT 'Standortname',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Standort aktiv (0=inaktiv, 1=aktiv)',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_name` (`name`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inventar-Standorte';

-- ---------------------------------------------------------------------
-- Tabelle: inventory
-- Beschreibung: Inventarverwaltung
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `inventory` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL COMMENT 'Artikelname',
  `category_id` INT(11) NULL COMMENT 'Verknüpfung zu inventory_categories.id',
  `location_id` INT(11) NULL COMMENT 'Verknüpfung zu inventory_locations.id',
  `quantity` INT(11) NOT NULL DEFAULT 0 COMMENT 'Verfügbare Menge',
  `purchase_price` DECIMAL(10,2) NULL COMMENT 'Einkaufspreis pro Einheit in Euro',
  `responsible_user_id` INT(11) NULL COMMENT 'Verantwortlicher Benutzer (Referenz auf User-DB users.id)',
  `status` ENUM('active', 'archived', 'broken') NOT NULL DEFAULT 'active' COMMENT 'Artikel-Status',
  `description` TEXT NULL COMMENT 'Artikelbeschreibung',
  `image_path` VARCHAR(500) NULL COMMENT 'Pfad zum Artikelbild',
  `created_by` INT(11) NOT NULL COMMENT 'Ersteller (Referenz auf User-DB users.id)',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_inventory_name` (`name`),
  KEY `idx_category_id` (`category_id`),
  KEY `idx_location_id` (`location_id`),
  KEY `idx_status` (`status`),
  KEY `idx_responsible_user_id` (`responsible_user_id`),
  KEY `idx_created_by` (`created_by`),
  CONSTRAINT `fk_inventory_category` FOREIGN KEY (`category_id`) REFERENCES `inventory_categories` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_inventory_location` FOREIGN KEY (`location_id`) REFERENCES `inventory_locations` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Inventarverwaltung mit Transaktionsschutz';

-- ---------------------------------------------------------------------
-- Tabelle: events
-- Beschreibung: Event-Verwaltung
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `events` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL COMMENT 'Event-Titel',
  `description` TEXT NULL COMMENT 'Event-Beschreibung',
  `event_date` DATETIME NOT NULL COMMENT 'Event-Datum und Uhrzeit',
  `location` VARCHAR(255) NULL COMMENT 'Event-Ort',
  `max_participants` INT(11) NULL COMMENT 'Maximale Teilnehmerzahl',
  `image_path` VARCHAR(500) NULL COMMENT 'Pfad zum Event-Bild',
  `created_by` INT(11) NOT NULL COMMENT 'Ersteller (Referenz auf User-DB users.id)',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_event_date` (`event_date`),
  KEY `idx_created_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Event-Verwaltung';

-- ---------------------------------------------------------------------
-- Tabelle: event_helper_slots
-- Beschreibung: Helfer-Slots für Events
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `event_helper_slots` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `event_id` INT(11) NOT NULL COMMENT 'Verknüpfung zu events.id',
  `task_name` VARCHAR(255) NOT NULL COMMENT 'Aufgabenbezeichnung (z.B. Catering, Aufbau)',
  `required_helpers` INT(11) NOT NULL DEFAULT 1 COMMENT 'Benötigte Anzahl Helfer',
  `description` TEXT NULL COMMENT 'Aufgabenbeschreibung',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_event_id` (`event_id`),
  CONSTRAINT `fk_helper_slots_event` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Helfer-Slots für Events';

-- ---------------------------------------------------------------------
-- Tabelle: event_helper_registrations
-- Beschreibung: Helfer-Anmeldungen für Event-Slots
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `event_helper_registrations` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `slot_id` INT(11) NOT NULL COMMENT 'Verknüpfung zu event_helper_slots.id',
  `user_id` INT(11) NOT NULL COMMENT 'Angemeldeter Benutzer (Referenz auf User-DB users.id)',
  `registered_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_slot_user` (`slot_id`, `user_id`),
  KEY `idx_slot_id` (`slot_id`),
  KEY `idx_user_id` (`user_id`),
  CONSTRAINT `fk_helper_registrations_slot` FOREIGN KEY (`slot_id`) REFERENCES `event_helper_slots` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Helfer-Anmeldungen für Event-Slots';

-- ---------------------------------------------------------------------
-- Tabelle: news
-- Beschreibung: News/Ankündigungen
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `news` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(255) NOT NULL COMMENT 'News-Titel',
  `content` TEXT NOT NULL COMMENT 'News-Inhalt (HTML/Quill)',
  `author_id` INT(11) NOT NULL COMMENT 'Autor (Referenz auf User-DB users.id)',
  `image_path` VARCHAR(500) NULL COMMENT 'Pfad zum News-Bild',
  `is_published` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Veröffentlicht (0=Entwurf, 1=veröffentlicht)',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_news_title` (`title`),
  KEY `idx_author_id` (`author_id`),
  KEY `idx_is_published` (`is_published`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='News und Ankündigungen';

-- ---------------------------------------------------------------------
-- Tabelle: system_logs
-- Beschreibung: System-Logs für administrative Aktionen
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `system_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL COMMENT 'Benutzer, der die Aktion durchgeführt hat (Referenz auf User-DB users.id)',
  `action` VARCHAR(100) NOT NULL COMMENT 'Aktionstyp (create, update, delete)',
  `target_type` VARCHAR(100) NOT NULL COMMENT 'Zieltyp (inventory, news, alumni, project, event)',
  `target_id` INT(11) NOT NULL COMMENT 'ID des Zielobjekts',
  `details` TEXT NULL COMMENT 'Zusätzliche Details zur Aktion (JSON)',
  `ip_address` VARCHAR(45) NULL COMMENT 'IP-Adresse des Benutzers',
  `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_target_type` (`target_type`),
  KEY `idx_timestamp` (`timestamp`),
  KEY `idx_target_type_id` (`target_type`, `target_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='System-Logs für administrative Aktionen';

-- =====================================================================
-- ENDE DER SCHEMA-DEFINITION
-- =====================================================================
