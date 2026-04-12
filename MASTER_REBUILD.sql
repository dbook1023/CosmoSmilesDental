-- COSMO SMILES DENTAL CLINIC - MASTER REBUILD SCRIPT
-- This script reconstructs the entire database schema and restores core clinic data.
-- WARNING: This will DROP and RECREATE all tables.

SET FOREIGN_KEY_CHECKS=0;

-- --------------------------------------------------------
-- 1. DROP AND RECREATE TABLES (THE PERFECT SCHEMA)
-- --------------------------------------------------------

-- Table: admin_users
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

-- Table: services
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

-- Table: dentists
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

-- Table: staff_users
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

-- Table: clients
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

-- Table: appointments
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

-- Other necessary tables...
DROP TABLE IF EXISTS `patient_medical_history`;
CREATE TABLE `patient_medical_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` varchar(50) NOT NULL,
  `heart_disease` tinyint(1) DEFAULT 0,
  `heart_disease_details` text DEFAULT NULL,
  `high_blood_pressure` tinyint(1) DEFAULT 0,
  `diabetes` tinyint(1) DEFAULT 0,
  `allergies` text DEFAULT NULL,
  `past_surgeries` text DEFAULT NULL,
  `current_medications` text DEFAULT NULL,
  `is_pregnant` tinyint(1) DEFAULT 0,
  `other_conditions` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  CONSTRAINT `patient_medical_history_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `patient_records`;
CREATE TABLE `patient_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `record_id` varchar(50) NOT NULL,
  `client_id` varchar(100) NOT NULL,
  `appointment_id` int(11) DEFAULT NULL,
  `record_type` enum('treatment','consultation','xray','prescription','followup','emergency') NOT NULL,
  `record_title` varchar(255) NOT NULL,
  `record_date` date NOT NULL,
  `record_time` time NOT NULL,
  `dentist` varchar(100) NOT NULL,
  `procedure` text NOT NULL,
  `description` text NOT NULL,
  `findings` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `files` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `record_id` (`record_id`),
  KEY `client_id` (`client_id`),
  CONSTRAINT `patient_records_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

DROP TABLE IF EXISTS `verification_otps`;
CREATE TABLE `verification_otps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `otp_code` varchar(6) NOT NULL,
  `type` enum('email','phone') NOT NULL,
  `expires_at` datetime NOT NULL,
  `verified` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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


-- --------------------------------------------------------
-- 2. RESTORE CORE CLINIC DATA (FROM PRODUCTION)
-- --------------------------------------------------------

-- Restore Services
INSERT INTO `services` (`id`, `name`, `description`, `duration_minutes`, `price`, `is_active`) VALUES
(1,'Regular Check-up','Comprehensive dental examination',30,500.00,1),
(2,'Teeth Cleaning','Professional teeth cleaning',60,1200.00,1),
(3,'Tooth Filling','Dental filling for cavities',90,1500.00,1),
(4,'Teeth Whitening','Professional whitening treatment',120,8000.00,1),
(5,'Tooth Extraction','Tooth removal procedure',60,2000.00,1),
(6,'Root Canal','Root canal treatment',120,6000.00,1),
(7,'Braces Consultation','Orthodontic assessment',45,800.00,1);

-- Restore Dentists
INSERT INTO `dentists` (`id`, `first_name`, `last_name`, `email`, `phone`, `specialization`, `license_number`, `is_active`) VALUES
(1, 'Rhea Ann', 'Salcedo', 'dr.salcedo@cosmosmiles.com', '09283853751', 'General Dentistry', 'DENT0001', 1),
(2, 'Vincent Robert', 'Ompoc', 'dr.ompoc@cosmosmiles.com', '09283853751', 'General Dentistry', 'DENT0002', 1);

-- Restore Staff
INSERT INTO `staff_users` (`id`, `staff_id`, `email`, `password`, `first_name`, `last_name`, `role`, `department`, `status`) VALUES
(1, 'REC001', 'maria.santos@cosmosmiles.com', '48fdefa7586020d7a646fd8454ce634228abbe3885e22e0de9b8cd1c7ac03a06abb84908f263400f681ad6758e61217f5e10d608d7ba392df566506fbd82554b', 'Maria', 'Santos', 'receptionist', 'Front Desk', 'active');

-- Restore Admins
INSERT INTO `admin_users` (`id`, `dentist_id`, `username`, `email`, `password`, `first_name`, `last_name`, `role`, `status`) VALUES
(1, 'DENT0001', 'rhea.salcedo', 'dr.salcedo@cosmosmiles.com', 'e7648d6dcaffb3f51057b0196849b4ed8f9a1888423753720b6dbb828bdabef9bc333b25b5a503249e3dd3b461e707980545e180cc504a067f72154ed4f5464e', 'Rhea Ann', 'Salcedo', 'admin', 'active'),
(2, 'DENT0002', 'vincent.ompoc', 'dr.ompoc@cosmosmiles.com', '07bfb274764b5d555dfeb0203697fbb320a96e37a2a27dbc783640f130d37069e6db7038438c0bd10a0b113a6f34c3bd873b2f6bbb96c6f12852edd49056d6e7', 'Vincent Robert', 'Ompoc', 'admin', 'active');

-- Restore Site Content
INSERT INTO `site_content` (`page`, `section_key`, `content_type`, `content_value`) VALUES
('home', 'hero_title', 'text', 'Home of the Perfect Smiles'),
('home', 'hero_subtitle', 'text', 'Professional dental care'),
('clinic', 'name', 'text', 'Cosmo Smiles Dental Clinic'),
('clinic', 'address', 'text', 'Binangonan, Rizal'),
('clinic', 'email', 'text', 'info@cosmosmiles.com'),
('clinic', 'phone', 'text', '0999 888 7777');

SET FOREIGN_KEY_CHECKS=1;
