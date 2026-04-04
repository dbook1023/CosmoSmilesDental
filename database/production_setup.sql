-- Cosmo Smiles Dental Clinic - Production Database Setup
-- Includes Full Schema and Essential Seed Data (Admins, Staff, and Test Client PAT0001)

SET FOREIGN_KEY_CHECKS=0;

-- --------------------------------------------------------
-- Table structure and seeds for `admin_users`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `admin_users`;
CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dentist_id` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `role` enum('admin','staff') DEFAULT 'admin',
  `specialization` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `uniq_dentist_id` (`dentist_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `admin_users` (`id`, `dentist_id`, `username`, `email`, `password`, `first_name`, `last_name`, `role`, `status`, `created_at`) VALUES
('1', 'DENT0001', 'rhea.salcedo', 'dr.salcedo@cosmosmiles.com', 'e7648d6dcaffb3f51057b0196849b4ed8f9a1888423753720b6dbb828bdabef9bc333b25b5a503249e3dd3b461e707980545e180cc504a067f72154ed4f5464e', 'Rhea Ann', 'Salcedo', 'admin', 'active', '2025-11-10 22:21:25'),
('2', 'DENT0002', 'vincent.ompoc', 'dr.ompoc@cosmosmiles.com', '07bfb274764b5d555dfeb0203697fbb320a96e37a2a27dbc783640f130d37069e6db7038438c0bd10a0b113a6f34c3bd873b2f6bbb96c6f12852edd49056d6e7', 'Vincent Robert', 'Ompoc', 'admin', 'active', '2025-11-10 22:21:25');

-- --------------------------------------------------------
-- Table structure for `appointments`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `appointments`;
CREATE TABLE `appointments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `appointment_id` varchar(20) NOT NULL,
  `client_id` varchar(100) NOT NULL,
  `dentist_id` int(11) DEFAULT NULL,
  `service_id` int(11) NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `status` enum('pending','confirmed','completed','cancelled','no_show') DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `client_notes` text DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT 30,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `patient_first_name` varchar(50) NOT NULL,
  `patient_last_name` varchar(50) NOT NULL,
  `patient_phone` varchar(15) NOT NULL,
  `patient_email` varchar(100) NOT NULL,
  `payment_type` enum('cash','gcash') DEFAULT 'cash',
  `service_price` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_appointment_id` (`appointment_id`),
  KEY `dentist_id` (`dentist_id`),
  KEY `service_id` (`service_id`),
  KEY `fk_appointments_clients` (`client_id`),
  CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`dentist_id`) REFERENCES `dentists` (`id`) ON DELETE SET NULL,
  CONSTRAINT `appointments_ibfk_3` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_appointments_clients` FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- Table structure and seeds for `clients` (PAT0001 only)
-- --------------------------------------------------------
DROP TABLE IF EXISTS `clients`;
CREATE TABLE `clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` varchar(100) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `birthdate` date NOT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `address_line1` varchar(255) DEFAULT NULL,
  `address_line2` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(100) DEFAULT NULL,
  `postal_code` varchar(20) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'Philippines',
  `phone` varchar(15) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_minor` tinyint(1) DEFAULT 0,
  `parental_consent` tinyint(1) DEFAULT 0,
  `profile_image` varchar(255) DEFAULT NULL,
  `medical_history_status` enum('pending','completed') DEFAULT 'pending',
  `medical_history_edit_allowed` tinyint(1) DEFAULT 0,
  `parental_signature` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `uk_client_id` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `clients` (`client_id`, `first_name`, `last_name`, `birthdate`, `gender`, `phone`, `email`, `password`, `created_at`) VALUES
('PAT0001', 'Test', 'Patient', '1990-01-01', 'male', '09123456789', 'test.patient@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '2025-11-10 22:21:25');

-- --------------------------------------------------------
-- Table structure and seeds for `dentists`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `dentists`;
CREATE TABLE `dentists` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `specialization` varchar(255) DEFAULT NULL,
  `license_number` varchar(100) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `is_checked_in` tinyint(1) DEFAULT 0,
  `checked_in_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `dentists` (`id`, `first_name`, `last_name`, `email`, `phone`, `specialization`, `license_number`, `is_active`, `created_at`) VALUES
('1', 'Rhea Ann', 'Salcedo', 'dr.salcedo@cosmosmiles.com', '09283853751', 'General Dentistry', 'DENT0001', '1', '2025-11-10 12:52:52'),
('2', 'Vincent Robert', 'Ompoc', 'dr.ompoc@cosmosmiles.com', '09283853751', 'General Dentistry', 'DENT0002', '1', '2025-11-10 12:52:52');

-- --------------------------------------------------------
-- Table structure and seeds for `services`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `services`;
CREATE TABLE `services` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT 30,
  `price` decimal(10,2) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `services` (`id`, `name`, `description`, `duration_minutes`, `price`, `is_active`) VALUES
('1', 'Regular Check-up', 'Comprehensive dental examination and oral health assessment', '30', '500.00', '1'),
('2', 'Teeth Cleaning', 'Professional teeth cleaning and plaque removal', '60', '1200.00', '1'),
('3', 'Tooth Filling', 'Dental filling for cavities and tooth decay', '90', '1500.00', '1'),
('4', 'Teeth Whitening', 'Professional teeth whitening treatment', '120', '8000.00', '1'),
('5', 'Tooth Extraction', 'Tooth removal procedure', '60', '2000.00', '1'),
('6', 'Root Canal', 'Root canal treatment (per tooth)', '120', '6000.00', '1'),
('7', 'Braces Consultation', 'Orthodontic consultation and assessment', '45', '800.00', '1');

-- --------------------------------------------------------
-- Table structure and seeds for `staff_users`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `staff_users`;
CREATE TABLE `staff_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `role` enum('receptionist','assistant_dentist') NOT NULL,
  `specialization` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `staff_id` (`staff_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `staff_users` (`id`, `staff_id`, `email`, `password`, `first_name`, `last_name`, `role`, `department`, `status`, `created_at`) VALUES
('1', 'REC001', 'maria.santos@cosmosmiles.com', '48fdefa7586020d7a646fd8454ce634228abbe3885e22e0de9b8cd1c7ac03a06abb84908f263400f681ad6758e61217f5e10d608d7ba392df566506fbd82554b', 'Maria', 'Santos', 'receptionist', 'Front Desk', 'active', '2025-11-10 22:43:38');

-- --------------------------------------------------------
-- Table structure and seeds for `site_content`
-- --------------------------------------------------------
DROP TABLE IF EXISTS `site_content`;
CREATE TABLE `site_content` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `page` varchar(50) NOT NULL,
  `section_key` varchar(100) NOT NULL,
  `content_type` enum('text','image','icon') NOT NULL DEFAULT 'text',
  `content_value` text DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_page_section` (`page`,`section_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES
(1, 'home', 'hero_title', 'text', 'Home of the Perfect Smiles', '2026-04-01 23:48:28'),
(2, 'home', 'hero_subtitle', 'text', 'At Cosmo Smiles Dental Clinic, we combine advanced technology with compassionate care to deliver confident, healthy smiles.', '2026-04-02 00:09:16'),
(7, 'home', 'hours_week', 'text', 'Mon - Fri: 8:00 AM - 6:00 PM', '2026-04-01 21:27:58'),
(8, 'home', 'hours_sat', 'text', 'Sat: 9:00 AM - 3:00 PM', '2026-04-01 21:27:58'),
(9, 'home', 'hours_sun', 'text', 'No Clinic Operations', '2026-04-01 21:27:59'),
(25, 'clinic', 'name', 'text', 'Cosmo Smiles Dental Clinic', '2026-04-01 20:58:07'),
(26, 'clinic', 'address', 'text', '703 F national road, Tayuman, Binangonan, Rizal, Philippines', '2026-04-01 20:58:07'),
(27, 'clinic', 'email', 'text', 'info@cosmosmiles.com', '2026-04-01 20:53:39'),
(28, 'clinic', 'phone', 'text', '0999 888 7777', '2026-04-01 20:53:40');

-- --------------------------------------------------------
-- Full Structures for remaining tables (Empty)
-- --------------------------------------------------------

DROP TABLE IF EXISTS `appointment_feedbacks`;
CREATE TABLE `appointment_feedbacks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `appointment_id` varchar(20) NOT NULL,
  `rating` tinyint(4) NOT NULL CHECK (`rating` between 1 and 5),
  `feedback` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_featured` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `appointment_id` (`appointment_id`),
  CONSTRAINT `appointment_feedbacks_ibfk_1` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`appointment_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `identifier` varchar(255) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempt_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_successful` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `identifier` (`identifier`),
  KEY `ip_address` (`ip_address`),
  KEY `attempt_time` (`attempt_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `request_logs`;
CREATE TABLE `request_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `request_time` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ip_address` (`ip_address`),
  KEY `request_time` (`request_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `medical_edit_requests`;
CREATE TABLE `medical_edit_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` varchar(50) NOT NULL,
  `status` enum('pending','approved','denied') DEFAULT 'pending',
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `notes` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  CONSTRAINT `medical_edit_requests_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` varchar(100) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `message` text NOT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('unread','read','replied') DEFAULT 'unread',
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `token" varchar(255) NOT NULL,
  `user_type" enum('client','staff','admin') NOT NULL,
  `expires_at" datetime NOT NULL,
  `created_at" timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `token` (`token`),
  KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `patient_medical_history`;
CREATE TABLE `patient_medical_history` (
  `id" int(11) NOT NULL AUTO_INCREMENT,
  `client_id" varchar(50) NOT NULL,
  `heart_disease" tinyint(1) DEFAULT 0,
  `heart_disease_details" text DEFAULT NULL,
  `high_blood_pressure" tinyint(1) DEFAULT 0,
  `diabetes" tinyint(1) DEFAULT 0,
  `allergies" text DEFAULT NULL,
  `past_surgeries" text DEFAULT NULL,
  `current_medications" text DEFAULT NULL,
  `is_pregnant" tinyint(1) DEFAULT 0,
  `other_conditions" text DEFAULT NULL,
  `created_at" timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at" timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  CONSTRAINT `patient_medical_history_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Initial medical history for test client
INSERT INTO `patient_medical_history` (`client_id`, `created_at`) VALUES ('PAT0001', '2025-11-10 22:21:25');

DROP TABLE IF EXISTS `patient_records`;
CREATE TABLE `patient_records` (
  `id" int(11) NOT NULL AUTO_INCREMENT,
  `record_id" varchar(50) NOT NULL,
  `client_id" varchar(100) NOT NULL,
  `appointment_id" int(11) DEFAULT NULL,
  `record_type" enum('treatment','consultation','xray','prescription','followup','emergency') NOT NULL,
  `record_title" varchar(255) NOT NULL,
  `record_date" date NOT NULL,
  `record_time" time NOT NULL,
  `dentist" varchar(100) NOT NULL,
  `duration" varchar(50) DEFAULT NULL,
  `procedure" text NOT NULL,
  `description" text NOT NULL,
  `findings" text DEFAULT NULL,
  `notes" text DEFAULT NULL,
  `followup_instructions" text DEFAULT NULL,
  `files" text DEFAULT NULL,
  `tooth_numbers" text DEFAULT NULL,
  `surfaces" text DEFAULT NULL,
  `created_by" varchar(100) NOT NULL,
  `is_archived" tinyint(1) DEFAULT 0,
  `archived_by" varchar(100) DEFAULT NULL,
  `archive_reason" text DEFAULT NULL,
  `archived_at" timestamp NULL DEFAULT NULL,
  `created_at" timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at" timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `record_id` (`record_id`),
  CONSTRAINT `patient_records_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `reminder_logs`;
CREATE TABLE `reminder_logs` (
  `id" int(11) NOT NULL AUTO_INCREMENT,
  `staff_id" varchar(20) DEFAULT NULL,
  `client_id" varchar(100) DEFAULT NULL,
  `appointment_id" int(11) DEFAULT NULL,
  `message" text DEFAULT NULL,
  `sent_at" datetime DEFAULT NULL,
  `created_at" timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  CONSTRAINT `reminder_logs_ibfk_1` FOREIGN KEY (`staff_id`) REFERENCES `staff_users` (`staff_id`),
  CONSTRAINT `reminder_logs_ibfk_2` FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`),
  CONSTRAINT `reminder_logs_ibfk_3` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `verification_otps`;
CREATE TABLE `verification_otps` (
  `id" int(11) NOT NULL AUTO_INCREMENT,
  `email" varchar(255) DEFAULT NULL,
  `phone" varchar(20) DEFAULT NULL,
  `otp_code" varchar(6) NOT NULL,
  `type" enum('email','phone') NOT NULL,
  `expires_at" datetime NOT NULL,
  `verified" tinyint(1) DEFAULT 0,
  `created_at" timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET FOREIGN_KEY_CHECKS=1;
