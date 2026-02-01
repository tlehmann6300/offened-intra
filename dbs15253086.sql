-- =====================================================================
-- IBC-INTRANET USER-DATENBANK SETUP
-- Datenbank: dbs15253086
-- Version: 2.0
-- MySQL Version: 8.0+
-- Erstellt: 2026-01-31
-- =====================================================================
-- 
-- DIESE DATEI ENTHÄLT:
-- - Komplette Schema-Definition für die User-Datenbank
-- - Test-Daten und Beispiel-User
-- 
-- VERWENDUNG:
-- 1. Verbinden Sie sich mit der Datenbank dbs15253086
-- 2. Führen Sie diese SQL-Datei aus
-- 
-- =====================================================================

-- Datenbank auswählen (falls noch nicht geschehen)
USE `dbs15253086`;

-- =====================================================================
-- SCHEMA DEFINITION
-- =====================================================================

-- ---------------------------------------------------------------------
-- Tabelle: users
-- Beschreibung: Zentrale Benutzerverwaltung mit Authentication
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL,
  `password` VARCHAR(255) NOT NULL COMMENT 'Bcrypt-Hash des Passworts',
  `firstname` VARCHAR(100) NOT NULL,
  `lastname` VARCHAR(100) NOT NULL,
  `role` ENUM('admin', '1v', '2v', '3v', 'ressortleiter', 'mitglied', 'alumni') NOT NULL DEFAULT 'mitglied' COMMENT 'Rollen-Hierarchie: admin=1V, 1v-3v=Vorstand',
  `birthdate` DATE NULL COMMENT 'Geburtsdatum des Benutzers',
  `notify_birthday` TINYINT NOT NULL DEFAULT 1 COMMENT 'Sichtbarkeit des Geburtstags auf Dashboard (1=sichtbar, 0=privat)',
  `tfa_secret` VARCHAR(32) DEFAULT NULL COMMENT 'Base32-kodierter TOTP-Secret für 2FA',
  `tfa_enabled` TINYINT NOT NULL DEFAULT 0 COMMENT 'Two-Factor Authentication aktiviert (0=deaktiviert, 1=aktiviert)',
  `totp_verified_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Zeitstempel der ersten 2FA-Verifizierung',
  `is_alumni_validated` TINYINT NOT NULL DEFAULT 0 COMMENT 'Alumni-Status validiert durch Vorstand (0=ausstehend, 1=validiert)',
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
  `id` INT NOT NULL AUTO_INCREMENT,
  `user_id` INT NOT NULL COMMENT 'Verknüpfung zu users.id',
  `graduation_year` INT NULL COMMENT 'Abschlussjahr',
  `company` VARCHAR(255) NULL COMMENT 'Aktuelles Unternehmen',
  `position` VARCHAR(255) NULL COMMENT 'Aktuelle Position',
  `linkedin_url` VARCHAR(500) NULL COMMENT 'LinkedIn-Profil URL',
  `bio` TEXT NULL COMMENT 'Kurze Biografie',
  `expertise` TEXT NULL COMMENT 'Fachgebiete und Kompetenzen',
  `is_willing_to_mentor` TINYINT NOT NULL DEFAULT 0 COMMENT 'Bereit für Mentoring (0=nein, 1=ja)',
  `is_alumni_validated` TINYINT NOT NULL DEFAULT 0 COMMENT 'Profil durch Vorstand validiert (0=ausstehend, 1=validiert)',
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
  `success` TINYINT NOT NULL DEFAULT 0 COMMENT '0 = fehlgeschlagen, 1 = erfolgreich',
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
  `id` INT NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL COMMENT 'E-Mail-Adresse des eingeladenen Benutzers',
  `token` VARCHAR(64) NOT NULL COMMENT 'Kryptographischer Token (SHA256-Hash)',
  `role` VARCHAR(50) NOT NULL DEFAULT 'alumni' COMMENT 'Zuzuweisende Rolle nach Registrierung',
  `created_by` INT NOT NULL COMMENT 'User-ID des Admin, der die Einladung erstellt hat',
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
-- TEST-DATEN / DUMP DATA
-- =====================================================================

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
) AS new_data
ON DUPLICATE KEY UPDATE
  `email` = new_data.`email`,
  `firstname` = new_data.`firstname`,
  `lastname` = new_data.`lastname`;

-- =====================================================================
-- ENDE DER USER-DATENBANK SETUP
-- =====================================================================

-- ---------------------------------------------------------------------
-- HINWEISE ZUR VERWENDUNG
-- ---------------------------------------------------------------------
-- 
-- 1. Passwort für Test-User:
--    E-Mail: tom.lehmann@business-consulting.de
--    Passwort: AdminPass2024!
-- 
-- 2. Die Daten verwenden ON DUPLICATE KEY UPDATE, sodass sie
--    mehrfach ausgeführt werden können ohne Fehler zu verursachen.
-- 
-- 3. Cross-Database-Referenzen:
--    - User-IDs aus dieser Datenbank werden von der Content-Datenbank
--      (dbs15161271) in Feldern wie created_by, author_id, etc. referenziert
--    - Diese Referenzen sind logisch, aber nicht durch Foreign Keys
--      erzwungen (verschiedene Datenbank-Hosts)
-- 
-- 4. 2FA (Two-Factor Authentication):
--    - Initial deaktiviert für einfache erste Anmeldung
--    - Kann nach Login im Profil aktiviert werden
-- 
-- 5. Alumni-System:
--    - Benutzer mit role='alumni' können ein alumni_profile anlegen
--    - is_alumni_validated muss durch Vorstand auf 1 gesetzt werden
--    - Erst dann wird das Profil im Alumni-Verzeichnis sichtbar
-- 
-- ---------------------------------------------------------------------
