-- Cosmo Smiles Dental Clinic Deployment Schema (Clean)
-- Generated: 2026-04-02 10:39:57

SET FOREIGN_KEY_CHECKS=0;


-- Table structure for table `admin_users` --
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
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `admin_users` --
INSERT INTO `admin_users` (`id`, `dentist_id`, `username`, `email`, `phone`, `password`, `first_name`, `last_name`, `role`, `specialization`, `status`, `last_login`, `created_at`, `updated_at`) VALUES ('1', 'DENT0001', 'rhea.salcedo', 'dr.salcedo@cosmosmiles.com', NULL, 'e7648d6dcaffb3f51057b0196849b4ed8f9a1888423753720b6dbb828bdabef9bc333b25b5a503249e3dd3b461e707980545e180cc504a067f72154ed4f5464e', 'Rhea Ann', 'Salcedo', 'admin', NULL, 'active', '2026-04-02 16:28:35', '2025-11-10 22:21:25', '2026-04-02 16:28:35');
INSERT INTO `admin_users` (`id`, `dentist_id`, `username`, `email`, `phone`, `password`, `first_name`, `last_name`, `role`, `specialization`, `status`, `last_login`, `created_at`, `updated_at`) VALUES ('2', 'DENT0002', 'vincent.ompoc', 'dr.ompoc@cosmosmiles.com', NULL, '07bfb274764b5d555dfeb0203697fbb320a96e37a2a27dbc783640f130d37069e6db7038438c0bd10a0b113a6f34c3bd873b2f6bbb96c6f12852edd49056d6e7', 'Vincent Robert', 'Ompoc', 'admin', NULL, 'active', '2026-03-30 18:48:37', '2025-11-10 22:21:25', '2026-03-30 18:48:37');
INSERT INTO `admin_users` (`id`, `dentist_id`, `username`, `email`, `phone`, `password`, `first_name`, `last_name`, `role`, `specialization`, `status`, `last_login`, `created_at`, `updated_at`) VALUES ('3', 'ADM0003', '', 'test.admin@gmail.com', NULL, '7f3dc26e0b2c81853608caeb65113305493889c2075ab37bfc96c391449a514054ba002aa03d86aeba0c3c8d1b5899ba278fd4c47c3d82c31a22535c11074585', 'Test', 'Admin', 'admin', NULL, 'inactive', '2026-03-26 14:18:54', '2026-03-26 14:15:56', '2026-03-30 17:59:59');


-- Table structure for table `appointment_feedbacks` --
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for table `appointments` --
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
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for table `clients` --
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
) ENGINE=InnoDB AUTO_INCREMENT=101 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for table `request_logs` --
DROP TABLE IF EXISTS `request_logs`;
CREATE TABLE `request_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `request_time` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `ip_address` (`ip_address`),
  KEY `request_time` (`request_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for table `dentists` --
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `dentists` --
INSERT INTO `dentists` (`id`, `first_name`, `last_name`, `email`, `phone`, `specialization`, `license_number`, `bio`, `is_active`, `is_checked_in`, `checked_in_at`, `created_at`, `updated_at`) VALUES ('1', 'Rhea Ann', 'Salcedo', 'dr.salcedo@cosmosmiles.com', '09283853751', 'General Dentistry', 'DENT0001', NULL, '1', '1', '2026-04-02 01:34:55', '2025-11-10 12:52:52', '2026-04-02 01:34:55');
INSERT INTO `dentists` (`id`, `first_name`, `last_name`, `email`, `phone`, `specialization`, `license_number`, `bio`, `is_active`, `is_checked_in`, `checked_in_at`, `created_at`, `updated_at`) VALUES ('2', 'Vincent Robert', 'Ompoc', 'dr.ompoc@cosmosmiles.com', '09283853751', 'General Dentistry', 'DENT0002', NULL, '1', '1', '2026-03-30 17:48:04', '2025-11-10 12:52:52', '2026-03-30 17:48:04');


-- Table structure for table `medical_edit_requests` --
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for table `messages` --
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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for table `password_resets` --
DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `user_type` enum('client','staff','admin') NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `token` (`token`),
  KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for table `patient_medical_history` --
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for table `patient_records` --
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
  `duration` varchar(50) DEFAULT NULL,
  `procedure` text NOT NULL,
  `description` text NOT NULL,
  `findings` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `followup_instructions` text DEFAULT NULL,
  `files` text DEFAULT NULL,
  `tooth_numbers` text DEFAULT NULL,
  `surfaces` text DEFAULT NULL,
  `created_by` varchar(100) NOT NULL,
  `is_archived` tinyint(1) DEFAULT 0,
  `archived_by` varchar(100) DEFAULT NULL,
  `archive_reason` text DEFAULT NULL,
  `archived_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `record_id` (`record_id`),
  KEY `idx_record_id` (`record_id`),
  KEY `idx_client_id` (`client_id`),
  KEY `idx_record_date` (`record_date`),
  KEY `idx_record_type` (`record_type`),
  KEY `idx_is_archived` (`is_archived`),
  CONSTRAINT `patient_records_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`client_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Table structure for table `services` --
DROP TABLE IF EXISTS `services`;
CREATE TABLE `services` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT 30,
  `price` decimal(10,2) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `services` --
INSERT INTO `services` (`id`, `name`, `description`, `duration_minutes`, `price`, `is_active`) VALUES ('1', 'Regular Check-up', 'Comprehensive dental examination and oral health assessment', '30', '500.00', '1');
INSERT INTO `services` (`id`, `name`, `description`, `duration_minutes`, `price`, `is_active`) VALUES ('2', 'Teeth Cleaning', 'Professional teeth cleaning and plaque removal', '60', '1200.00', '1');
INSERT INTO `services` (`id`, `name`, `description`, `duration_minutes`, `price`, `is_active`) VALUES ('3', 'Tooth Filling', 'Dental filling for cavities and tooth decay', '90', '1500.00', '1');
INSERT INTO `services` (`id`, `name`, `description`, `duration_minutes`, `price`, `is_active`) VALUES ('4', 'Teeth Whitening', 'Professional teeth whitening treatment', '120', '8000.00', '1');
INSERT INTO `services` (`id`, `name`, `description`, `duration_minutes`, `price`, `is_active`) VALUES ('5', 'Tooth Extraction', 'Tooth removal procedure', '60', '2000.00', '1');
INSERT INTO `services` (`id`, `name`, `description`, `duration_minutes`, `price`, `is_active`) VALUES ('6', 'Root Canal', 'Root canal treatment (per tooth)', '120', '6000.00', '1');
INSERT INTO `services` (`id`, `name`, `description`, `duration_minutes`, `price`, `is_active`) VALUES ('7', 'Braces Consultation', 'Orthodontic consultation and assessment', '45', '800.00', '1');
INSERT INTO `services` (`id`, `name`, `description`, `duration_minutes`, `price`, `is_active`) VALUES ('8', 'Test Service', 'test services description ', '30', '100.00', '1');


-- Table structure for table `site_content` --
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
) ENGINE=InnoDB AUTO_INCREMENT=381 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `site_content` --
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('1', 'home', 'hero_title', 'text', 'Home of the Perfect Smiles', '2026-04-01 23:48:28');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('2', 'home', 'hero_subtitle', 'text', 'At Cosmo Smiles Dental Clinic, we combine advanced technology with compassionate care to deliver confident, healthy smiles. Experience modern dentistry designed around your comfort, convenience, and long-term well-being.', '2026-04-02 00:09:16');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('7', 'home', 'hours_week', 'text', 'Mon - Fri: 8:00 AM - 6:00 PM', '2026-04-01 21:27:58');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('8', 'home', 'hours_sat', 'text', 'Sat: 9:00 AM - 3:00 PM', '2026-04-01 21:27:58');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('9', 'home', 'hours_sun', 'text', 'No Clinic Operations', '2026-04-01 21:27:59');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('25', 'clinic', 'name', 'text', 'Cosmo Smiles Dental Clinic', '2026-04-01 20:58:07');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('26', 'clinic', 'address', 'text', '703 F national road, Tayuman, Binangonan, Rizal, Philippines', '2026-04-01 20:58:07');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('27', 'clinic', 'email', 'text', 'info@cosmosmiles.com', '2026-04-01 20:53:39');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('28', 'clinic', 'phone', 'text', '0999 888 7777', '2026-04-01 20:53:40');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('35', 'home', 'promo_1_title', 'text', 'Accessible Care', '2026-04-01 21:28:00');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('36', 'home', 'promo_1_desc', 'text', 'Premium clinic services made affordable with flexible payment options.', '2026-04-01 21:28:00');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('37', 'home', 'promo_1_icon', 'icon', 'fas fa-hand-holding-medical', '2026-04-01 21:28:00');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('51', 'home', 'promo_2_title', 'text', 'Digital Diagnostics', '2026-04-01 21:28:00');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('52', 'home', 'promo_2_desc', 'text', 'Complimentary high-definition panoramic imaging for every new patient.', '2026-04-01 21:28:00');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('53', 'home', 'promo_2_icon', 'icon', 'fas fa-microscope', '2026-04-01 21:28:00');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('54', 'home', 'promo_3_title', 'text', 'Sample 3', '2026-04-02 01:37:28');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('55', 'home', 'promo_3_desc', 'text', 'Sample Promo Description', '2026-04-01 21:17:02');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('56', 'home', 'promo_3_icon', 'icon', 'fas fa-microscope', '2026-04-01 21:17:02');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('57', 'home', 'promo_4_title', 'text', 'Modern Techniques', '2026-04-02 00:09:16');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('58', 'home', 'promo_4_desc', 'text', 'We utilize the latest dental innovations and minimally invasive procedures for safer, faster, and more comfortable treatments.', '2026-04-02 00:09:16');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('59', 'home', 'promo_4_icon', 'icon', 'fas fa-notes-medical', '2026-04-02 00:09:16');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('60', 'home', 'promo_5_title', 'text', 'Personalized Care', '2026-04-02 00:09:16');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('61', 'home', 'promo_5_desc', 'text', 'Every smile is unique. We create customized treatment plans designed specifically for your goals and oral health needs.', '2026-04-02 00:09:16');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('62', 'home', 'promo_5_icon', 'icon', 'fas fa-smile', '2026-04-02 00:09:16');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('63', 'home', 'promo_6_title', 'text', 'Comfort First Experience', '2026-04-02 00:09:16');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('64', 'home', 'promo_6_desc', 'text', 'Relax in a calm, welcoming environment where your comfort is always our top priority.', '2026-04-02 00:09:16');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('65', 'home', 'promo_6_icon', 'icon', 'fas fa-heart', '2026-04-02 00:09:17');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('89', 'home', 'team_1_name', 'text', 'Dr. Rhea Ann Salcedo', '2026-04-01 21:18:45');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('90', 'home', 'team_1_role', 'text', 'General Dentistry', '2026-04-01 21:18:45');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('91', 'home', 'team_1_desc', 'text', 'Master of complex alignments and digital smile design solutions.', '2026-04-01 21:27:59');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('92', 'home', 'team_2_name', 'text', 'Dr. Vincent Robert Ompoc', '2026-04-01 21:18:45');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('93', 'home', 'team_2_role', 'text', 'General Dentistry', '2026-04-01 21:18:46');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('94', 'home', 'team_2_desc', 'text', 'Specialist in precision oral reconstruction and dental implantology.', '2026-04-01 21:27:59');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('98', 'about', 'about_tag', 'text', 'Established 2018', '2026-04-01 21:27:57');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('99', 'about', 'about_title', 'text', 'Pioneering Modern Family Dentistry', '2026-04-01 21:27:57');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('100', 'about', 'about_description', 'text', 'With nearly a decade of dedicated service, Cosmo Smiles Dental Clinic has been committed to providing exceptional dental care for every member of the family. Our team of board-certified clinicians delivers advanced, high-quality treatments in a safe, comfortable, and patient-centered environment.\r\n\r\nWe combine modern dental technology with proven techniques in implantology and aesthetic dentistry to ensure every procedure is precise, effective, and tailored to your individual needs. Our goal is to create confident, healthy smiles through personalized care you can trust.', '2026-04-02 00:11:45');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('101', 'about', 'stat_1_num', 'text', '6', '2026-04-01 21:33:52');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('102', 'about', 'stat_1_label', 'text', 'Years Clinical Mastery', '2026-04-01 21:27:57');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('103', 'about', 'stat_1_icon', 'icon', 'fas fa-stethoscope', '2026-04-01 21:27:57');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('104', 'about', 'stat_2_num', 'text', '2k+', '2026-04-01 21:33:53');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('105', 'about', 'stat_2_label', 'text', 'Transformations', '2026-04-01 21:27:57');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('106', 'about', 'stat_2_icon', 'icon', 'fas fa-certificate', '2026-04-01 21:27:57');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('107', 'about', 'vision_title', 'text', 'The Vision', '2026-04-01 21:27:57');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('108', 'about', 'vision_desc', 'text', 'To lead as the gold standard in community clinical care, recognized for elevating oral health through innovation and clinical integrity.', '2026-04-01 21:27:58');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('109', 'about', 'vision_icon', 'icon', 'fas fa-eye', '2026-04-01 21:27:58');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('110', 'about', 'mission_title', 'text', 'The Mission', '2026-04-01 21:27:58');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('111', 'about', 'mission_desc', 'text', 'To empower our community with the confidence of a healthy smile, provided through safe, transparent, and empathetic medical procedures.', '2026-04-01 21:27:58');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('112', 'about', 'mission_icon', 'icon', 'fas fa-bullseye', '2026-04-01 21:27:58');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('113', 'about', 'value_1_title', 'text', 'Human First', '2026-04-01 21:27:58');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('114', 'about', 'value_1_desc', 'text', 'Treating every patient with dignity, empathy, and personalized clinical attention.', '2026-04-01 21:27:58');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('115', 'about', 'value_1_icon', 'icon', 'fas fa-heart', '2026-04-01 21:27:58');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('116', 'about', 'value_2_title', 'text', 'Tech Precision', '2026-04-01 21:27:58');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('117', 'about', 'value_2_desc', 'text', 'Leveraging digital diagnostics for unmatched surgical and aesthetic accuracy.', '2026-04-01 21:27:58');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('118', 'about', 'value_2_icon', 'icon', 'fas fa-microscope', '2026-04-01 21:27:58');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('119', 'about', 'value_3_title', 'text', 'Clinical Safety', '2026-04-01 21:27:58');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('120', 'about', 'value_3_desc', 'text', 'Absolute adherence to global sterilization and bio-safety protocols.', '2026-04-01 21:27:58');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('121', 'about', 'value_3_icon', 'icon', 'fas fa-shield-halved', '2026-04-01 21:27:58');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('122', 'about', 'value_4_title', 'text', 'Integrity', '2026-04-01 21:27:58');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('123', 'about', 'value_4_desc', 'text', 'Unwavering transparency in pricing, treatment planning, and medical outcomes.', '2026-04-01 21:27:58');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('124', 'about', 'value_4_icon', 'icon', 'fas fa-handshake', '2026-04-01 21:27:58');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('136', 'home', 'team_3_name', 'text', '', '2026-04-01 23:45:19');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('137', 'home', 'team_3_role', 'text', '', '2026-04-01 23:45:19');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('138', 'home', 'team_3_desc', 'text', '', '2026-04-01 23:45:19');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('145', 'home', 'why_1_title', 'text', 'Board Certified', '2026-04-01 21:28:00');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('146', 'home', 'why_1_desc', 'text', 'Our specialists are well-trained professionals who continually improve their techniques to provide you with the highest quality care.', '2026-04-02 00:09:17');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('147', 'home', 'why_1_icon', 'icon', 'fas fa-user-md', '2026-04-01 21:28:00');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('148', 'home', 'why_2_title', 'text', 'Emergency Response', '2026-04-01 21:28:00');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('149', 'home', 'why_2_desc', 'text', 'Priority scheduling for urgent cases ensures you get relief and care fast.', '2026-04-01 21:28:00');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('150', 'home', 'why_2_icon', 'icon', 'fas fa-clock', '2026-04-01 21:28:00');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('321', 'home', 'team_3_img', 'image', '/assets/images/dynamic/img_69cd293b0bae5.jpg', '2026-04-01 22:18:35');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('323', 'home', 'hero_bg_pos', 'text', 'center', '2026-04-01 22:27:00');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('324', 'home', 'team_1_pos', 'text', 'center', '2026-04-01 22:27:00');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('325', 'home', 'team_2_pos', 'text', 'center', '2026-04-01 22:27:00');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('326', 'home', 'team_3_pos', 'text', 'top', '2026-04-01 22:27:20');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('327', 'home', 'team_4_pos', 'text', 'center', '2026-04-01 22:27:00');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('328', 'home', 'team_5_pos', 'text', 'center', '2026-04-01 22:27:00');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('329', 'home', 'team_6_pos', 'text', 'center', '2026-04-01 22:27:00');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('335', 'home', 'why_3_title', 'text', 'Trusted Care', '2026-04-02 00:09:17');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('336', 'home', 'why_3_desc', 'text', 'We build lasting relationships through honest communication, transparent treatment plans, and consistently excellent results.', '2026-04-02 00:09:17');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('337', 'home', 'why_3_icon', 'icon', 'fas fa-check-circle', '2026-04-02 00:09:17');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('347', 'home', 'team_1_img', 'image', '/assets/images/dynamic/img_69cd3d7260082.jpg', '2026-04-01 23:44:50');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('348', 'home', 'team_2_img', 'image', '/assets/images/dynamic/img_69cd3d726a66d.jpg', '2026-04-01 23:44:50');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('360', 'home', 'hero_bg_image', 'image', '/assets/images/dynamic/img_69cd3f2b0e17a.jpg', '2026-04-01 23:52:11');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('376', 'services', 'services_title', 'text', 'Our Premium Services', '2026-04-02 00:14:32');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('377', 'services', 'services_subtitle', 'text', 'Comprehensive care for your family.', '2026-04-02 00:14:32');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('378', 'services', 'tech_title', 'text', 'Modern Clinical Logistics', '2026-04-02 00:14:33');
INSERT INTO `site_content` (`id`, `page`, `section_key`, `content_type`, `content_value`, `updated_at`) VALUES ('379', 'services', 'tech_desc', 'text', 'We invest in the highest tiers of medical technology.', '2026-04-02 00:14:33');


-- Table structure for table `staff_users` --
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table `staff_users` --
INSERT INTO `staff_users` (`id`, `staff_id`, `email`, `password`, `first_name`, `last_name`, `role`, `specialization`, `department`, `phone`, `status`, `last_login`, `created_at`, `updated_at`) VALUES ('1', 'REC001', 'maria.santos@cosmosmiles.com', '48fdefa7586020d7a646fd8454ce634228abbe3885e22e0de9b8cd1c7ac03a06abb84908f263400f681ad6758e61217f5e10d608d7ba392df566506fbd82554b', 'Maria', 'Santos', 'receptionist', NULL, 'Front Desk', '', 'active', '2026-04-02 14:27:19', '2025-11-10 22:43:38', '2026-04-02 14:27:19');


-- Table structure for table `verification_otps` --
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
) ENGINE=InnoDB AUTO_INCREMENT=36 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET FOREIGN_KEY_CHECKS=1;
