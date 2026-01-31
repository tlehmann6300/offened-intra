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
-- Beschreibung: Zentrale Benutzerverwaltung mit Authentication und 2FA
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL COMMENT 'Eindeutige E-Mail-Adresse',
  `password` VARCHAR(255) NOT NULL COMMENT 'Bcrypt-Hash des Passworts',
  `firstname` VARCHAR(100) NOT NULL COMMENT 'Vorname',
  `lastname` VARCHAR(100) NOT NULL COMMENT 'Nachname',
  `role` ENUM('admin', '1v', '2v', '3v', 'ressortleiter', 'mitglied', 'alumni') NOT NULL DEFAULT 'mitglied' COMMENT 'Benutzerrolle im System',
  `birthday` DATE NULL COMMENT 'Geburtsdatum des Benutzers',
  `notify_birthday` BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Geburtstag auf Dashboard anzeigen',
  `totp_secret` VARCHAR(32) DEFAULT NULL COMMENT 'Base32-kodierter TOTP-Secret für Two-Factor Authentication',
  `totp_enabled` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Two-Factor Authentication aktiviert',
  `totp_verified_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Zeitstempel der ersten TOTP-Verifizierung',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellungszeitpunkt',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Letzter Update-Zeitpunkt',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_email` (`email`),
  KEY `idx_role` (`role`),
  KEY `idx_firstname` (`firstname`),
  KEY `idx_lastname` (`lastname`),
  KEY `idx_birthday` (`birthday`),
  KEY `idx_totp_enabled` (`totp_enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Benutzerverwaltung mit Authentication und TOTP-Login';

-- ---------------------------------------------------------------------
-- Tabelle: alumni_profiles
-- Beschreibung: Erweiterte Profile für Alumni-Mitglieder mit Validierung
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `alumni_profiles` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL COMMENT 'Verknüpfung zu users.id',
  `graduation_year` INT(4) NULL COMMENT 'Abschlussjahr',
  `company` VARCHAR(255) NULL COMMENT 'Aktuelles Unternehmen',
  `position` VARCHAR(255) NULL COMMENT 'Aktuelle Position/Jobtitel',
  `linkedin_url` VARCHAR(500) NULL COMMENT 'LinkedIn-Profil URL',
  `bio` TEXT NULL COMMENT 'Kurze Biografie/Selbstbeschreibung',
  `expertise` TEXT NULL COMMENT 'Fachgebiete, Kompetenzen und Expertisen',
  `isWillingToMentor` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Bereit für Mentoring aktiver Mitglieder',
  `isAlumniValidated` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Alumni-Status durch Vorstand validiert',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellungszeitpunkt',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Letzter Update-Zeitpunkt',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_user_id` (`user_id`),
  KEY `idx_graduation_year` (`graduation_year`),
  KEY `idx_is_willing_to_mentor` (`isWillingToMentor`),
  KEY `idx_is_alumni_validated` (`isAlumniValidated`),
  CONSTRAINT `fk_alumni_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Erweiterte Alumni-Profile mit Karriereinformationen und Validierungsstatus';

-- ---------------------------------------------------------------------
-- Tabelle: login_attempts
-- Beschreibung: Tracking von Login-Versuchen für Rate-Limiting
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ip_address` VARCHAR(45) NOT NULL COMMENT 'IPv4 oder IPv6 Adresse des Clients',
  `email` VARCHAR(255) DEFAULT NULL COMMENT 'E-Mail-Adresse bei Login-Versuch',
  `attempt_time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Zeitpunkt des Login-Versuchs',
  `success` BOOLEAN NOT NULL DEFAULT FALSE COMMENT 'Login erfolgreich (TRUE) oder fehlgeschlagen (FALSE)',
  `user_agent` VARCHAR(500) DEFAULT NULL COMMENT 'Browser User-Agent String',
  PRIMARY KEY (`id`),
  KEY `idx_ip_time` (`ip_address`, `attempt_time`),
  KEY `idx_email_time` (`email`, `attempt_time`),
  KEY `idx_attempt_time` (`attempt_time`),
  KEY `idx_cleanup` (`attempt_time`, `success`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Login-Versuch-Tracking für Rate-Limiting und Security-Monitoring';

-- ---------------------------------------------------------------------
-- Tabelle: invitations
-- Beschreibung: Token-basiertes Einladungs-System
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `invitations` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(255) NOT NULL COMMENT 'E-Mail-Adresse des eingeladenen Benutzers',
  `token` VARCHAR(64) NOT NULL COMMENT 'Kryptographischer Token (SHA256-Hash)',
  `role` ENUM('admin', '1v', '2v', '3v', 'ressortleiter', 'mitglied', 'alumni') NOT NULL DEFAULT 'alumni' COMMENT 'Rolle nach Registrierung',
  `created_by` INT(11) NOT NULL COMMENT 'User-ID des einladenden Administrators',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Erstellungszeitpunkt der Einladung',
  `expires_at` TIMESTAMP NOT NULL COMMENT 'Ablaufzeitpunkt der Einladung',
  `accepted_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Annahmezeitpunkt (NULL = noch ausstehend)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_token` (`token`),
  KEY `idx_email` (`email`),
  KEY `idx_created_by` (`created_by`),
  KEY `idx_expires_at` (`expires_at`),
  KEY `idx_accepted_at` (`accepted_at`),
  KEY `idx_pending_invitations` (`email`, `accepted_at`, `expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Token-basiertes Einladungs-System für sichere Benutzerregistrierung';

-- ---------------------------------------------------------------------
-- Tabelle: system_logs
-- Beschreibung: System-Logs für administrative Aktionen und Audit-Trail
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `system_logs` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) NOT NULL COMMENT 'Benutzer-ID, der die Aktion durchgeführt hat',
  `action` VARCHAR(100) NOT NULL COMMENT 'Aktionstyp (z.B. create, update, delete, login)',
  `details` TEXT NULL COMMENT 'Zusätzliche Details zur Aktion (z.B. JSON-Format)',
  `ip_address` VARCHAR(45) NULL COMMENT 'IP-Adresse des Benutzers',
  `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Zeitpunkt der Aktion',
  PRIMARY KEY (`id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_action` (`action`),
  KEY `idx_timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='System-Logs für Audit-Trail und Security-Monitoring';

-- =====================================================================
-- TEST-DATEN / DUMP DATA
-- =====================================================================

-- ---------------------------------------------------------------------
-- Test-Benutzer: Tom Lehmann (Administrator)
-- ---------------------------------------------------------------------
-- E-Mail: tom.lehmann@business-consulting.de
-- Username: tomlehmann
-- Passwort: password (Test-Passwort - Hash: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi)
-- Rolle: admin (entspricht 1. Vorstand)
-- 2FA: Deaktiviert für initiale Anmeldung
-- ---------------------------------------------------------------------

INSERT INTO `users` (
  `id`,
  `email`,
  `password`,
  `firstname`,
  `lastname`,
  `role`,
  `birthday`,
  `notify_birthday`,
  `totp_secret`,
  `totp_enabled`,
  `totp_verified_at`,
  `created_at`,
  `updated_at`
) VALUES (
  1,
  'tom.lehmann@business-consulting.de',
  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
  'Tom',
  'Lehmann',
  'admin',
  '1995-03-15',
  TRUE,
  NULL,
  FALSE,
  NULL,
  '2026-01-01 10:00:00',
  '2026-01-01 10:00:00'
) ON DUPLICATE KEY UPDATE
  `email` = VALUES(`email`),
  `firstname` = VALUES(`firstname`),
  `lastname` = VALUES(`lastname`),
  `role` = VALUES(`role`);

-- =====================================================================
-- ENDE DER USER-DATENBANK SETUP
-- =====================================================================

-- ---------------------------------------------------------------------
-- HINWEISE ZUR VERWENDUNG
-- ---------------------------------------------------------------------
-- 
-- 1. Test-Login-Daten:
--    E-Mail: tom.lehmann@business-consulting.de
--    Username: tomlehmann
--    Passwort: password
-- 
-- 2. TOTP (Two-Factor Authentication):
--    - Initial deaktiviert für einfache erste Anmeldung
--    - Kann nach Login im Profil aktiviert werden
-- 
-- 3. Cross-Database-Referenzen:
--    - Diese User-IDs werden von der Content-Datenbank (dbs15161271)
--      in Feldern wie created_by, author_id, user_id, etc. referenziert
--    - Physische Foreign Keys sind nicht möglich (verschiedene Hosts)
--    - Referenzielle Integrität muss in der Anwendungslogik
--      sichergestellt werden
-- 
-- 4. Alumni-System:
--    - Benutzer mit role='alumni' können ein alumni_profile anlegen
--    - isAlumniValidated muss durch Vorstand auf TRUE gesetzt werden
--    - Erst dann wird das Profil im Alumni-Verzeichnis sichtbar
-- 
-- 5. Sicherheit:
--    - login_attempts Tabelle für Rate-Limiting verwenden
--    - system_logs für Audit-Trail und Compliance
--    - invitations für sichere Token-basierte Registrierung
-- 
-- ---------------------------------------------------------------------
