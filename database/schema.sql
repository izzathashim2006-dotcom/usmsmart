-- USMSmart database schema (structure only, no rows).
--
-- Generated from a working installation with:
--     php tools/dump-schema.php > database/schema.sql
-- and safe to re-run: every statement uses CREATE TABLE IF NOT EXISTS.
--
-- New installations:
--     mysql -u root -p -e "CREATE DATABASE usmsmart_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
--     mysql -u root -p usmsmart_db < database/schema.sql
--
-- The application also creates any missing table at boot, so this file is the
-- reference for reviews, staging rebuilds and restores; it is not required for
-- the app to start. CI verifies that every table and column the PHP creates at
-- runtime is declared here:
--     node tools/schema-consistency.js
--
-- Tables fall into three groups:
--   usmsmart_*        accounts, audit, messaging, access policies, staff directory
--   students_view     local student/card register (a table, despite the name)
--   uda_*             Sysweb staging records and transport sessions

CREATE TABLE IF NOT EXISTS `students_view` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `matric` varchar(50) DEFAULT NULL,
  `school` varchar(150) DEFAULT NULL,
  `date_joined` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `usm_uid` varchar(50) DEFAULT NULL,
  `serial_no` varchar(50) DEFAULT NULL,
  `policy` varchar(50) DEFAULT NULL,
  `nationality` varchar(100) DEFAULT NULL,
  `education_level` varchar(100) DEFAULT NULL,
  `student_category` varchar(30) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usmsmart_serial_unique` (`serial_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `uda_system_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `matric` varchar(50) DEFAULT NULL,
  `cardholder_type` enum('student','staff') NOT NULL DEFAULT 'student',
  `converted_uid` varchar(50) DEFAULT NULL,
  `serial_no` varchar(50) DEFAULT NULL,
  `policy` varchar(50) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `matric` (`matric`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `uda_transport_sessions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `session_date` datetime DEFAULT current_timestamp(),
  `total_records` int(11) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT 0,
  `session_name` varchar(120) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `usmsmart_access_locations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `location_name` varchar(150) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` varchar(80) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_access_location_name` (`location_name`),
  KEY `idx_access_location_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `usmsmart_access_policies` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `policy_name` varchar(100) NOT NULL,
  `description` varchar(500) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_by` varchar(80) NOT NULL,
  `updated_by` varchar(80) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_access_policy_name` (`policy_name`),
  KEY `idx_access_policy_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `usmsmart_access_policy_locations` (
  `policy_id` bigint(20) unsigned NOT NULL,
  `location_id` bigint(20) unsigned NOT NULL,
  `created_by` varchar(80) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`policy_id`,`location_id`),
  KEY `fk_usm_apl_location` (`location_id`),
  CONSTRAINT `fk_usm_apl_location` FOREIGN KEY (`location_id`) REFERENCES `usmsmart_access_locations` (`id`),
  CONSTRAINT `fk_usm_apl_policy` FOREIGN KEY (`policy_id`) REFERENCES `usmsmart_access_policies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `usmsmart_audit_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `action` varchar(60) NOT NULL,
  `entity_type` varchar(60) NOT NULL,
  `entity_id` varchar(100) DEFAULT NULL,
  `matric` varchar(100) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `actor` varchar(190) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_created` (`created_at`),
  KEY `idx_audit_matric` (`matric`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `usmsmart_card_policy_assignments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cardholder_type` enum('student','staff') NOT NULL,
  `source_identifier` varchar(100) NOT NULL,
  `source_id` varchar(30) NOT NULL,
  `policy` varchar(100) NOT NULL,
  `assigned_by` varchar(80) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_policy_source` (`cardholder_type`,`source_identifier`),
  KEY `idx_policy_name` (`policy`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `usmsmart_change_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `request_type` varchar(40) NOT NULL,
  `payload` longtext NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `requested_by` bigint(20) unsigned NOT NULL,
  `requested_by_username` varchar(80) NOT NULL,
  `reviewed_by` bigint(20) unsigned DEFAULT NULL,
  `reviewed_by_username` varchar(80) DEFAULT NULL,
  `review_note` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_change_status_created` (`status`,`created_at`),
  KEY `idx_change_requester` (`requested_by`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `usmsmart_conversations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `admin_user_id` bigint(20) unsigned NOT NULL,
  `viewer_user_id` bigint(20) unsigned NOT NULL,
  `request_id` bigint(20) unsigned DEFAULT NULL,
  `subject` varchar(160) NOT NULL,
  `created_by` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_conversation_admin_updated` (`admin_user_id`,`updated_at`),
  KEY `idx_conversation_viewer_updated` (`viewer_user_id`,`updated_at`),
  KEY `idx_conversation_request` (`request_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `usmsmart_login_attempts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `username_key` varchar(80) NOT NULL,
  `ip_hash` char(64) NOT NULL,
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_login_attempt_lookup` (`username_key`,`ip_hash`,`attempted_at`),
  KEY `idx_login_attempt_ip` (`ip_hash`,`attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `usmsmart_messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint(20) unsigned NOT NULL,
  `sender_id` bigint(20) unsigned NOT NULL,
  `sender_username` varchar(80) NOT NULL,
  `recipient_id` bigint(20) unsigned NOT NULL,
  `message_body` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_message_conversation` (`conversation_id`,`id`),
  KEY `idx_message_recipient_read` (`recipient_id`,`is_read`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `usmsmart_notifications` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `title` varchar(160) NOT NULL,
  `message` varchar(500) NOT NULL,
  `request_id` bigint(20) unsigned DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_notification_user_read` (`user_id`,`is_read`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `usmsmart_staff_directory` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `staff_id` varchar(50) NOT NULL,
  `full_name` varchar(200) NOT NULL,
  `department` varchar(150) DEFAULT NULL,
  `job_title` varchar(150) DEFAULT NULL,
  `email` varchar(254) DEFAULT NULL,
  `phone` varchar(40) DEFAULT NULL,
  `employment_status` enum('Active','Inactive','On Leave') NOT NULL DEFAULT 'Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `staff_no` varchar(80) DEFAULT NULL,
  `name` varchar(180) DEFAULT NULL,
  `card_serial` varchar(100) DEFAULT NULL,
  `card_status` varchar(50) NOT NULL DEFAULT 'Tidak dinyatakan',
  `date_joined` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `card_uid` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_staff_id` (`staff_id`),
  KEY `idx_staff_name` (`full_name`),
  KEY `idx_staff_department` (`department`),
  KEY `idx_staff_status` (`employment_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `usmsmart_student_card_history` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `student_id` bigint(20) unsigned NOT NULL,
  `matric` varchar(100) DEFAULT NULL,
  `event_type` varchar(40) NOT NULL,
  `name_snapshot` varchar(255) DEFAULT NULL,
  `school_snapshot` varchar(255) DEFAULT NULL,
  `uid_snapshot` varchar(190) DEFAULT NULL,
  `serial_snapshot` varchar(100) DEFAULT NULL,
  `policy_snapshot` varchar(100) DEFAULT NULL,
  `joined_snapshot` date DEFAULT NULL,
  `expiry_snapshot` date DEFAULT NULL,
  `previous_snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`previous_snapshot`)),
  `actor` varchar(190) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_card_history_student` (`student_id`,`created_at`),
  KEY `idx_card_history_matric` (`matric`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `usmsmart_users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(80) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','viewer') NOT NULL DEFAULT 'viewer',
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_login_at` timestamp NULL DEFAULT NULL,
  `auth_version` int(10) unsigned NOT NULL DEFAULT 1,
  `display_name` varchar(100) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `avatar_updated_at` timestamp NULL DEFAULT NULL,
  `last_seen_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
