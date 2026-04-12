SET FOREIGN_KEY_CHECKS=0;

-- 1. Services
DROP TABLE IF EXISTS `services`;
CREATE TABLE `services` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `duration_minutes` int(11) DEFAULT 30,
  `price` decimal(10,2) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `services` VALUES (1,'Regular Check-up','Comprehensive dental examination',30,500.00,1);
INSERT INTO `services` VALUES (2,'Teeth Cleaning','Professional teeth cleaning',60,1200.00,1);
INSERT INTO `services` VALUES (3,'Tooth Filling','Dental filling for cavities',90,1500.00,1);
INSERT INTO `services` VALUES (4,'Teeth Whitening','Professional whitening treatment',120,8000.00,1);
INSERT INTO `services` VALUES (5,'Tooth Extraction','Tooth removal procedure',60,2000.00,1);
INSERT INTO `services` VALUES (6,'Root Canal','Root canal treatment',120,6000.00,1);
INSERT INTO `services` VALUES (7,'Braces Consultation','Orthodontic assessment',45,800.00,1);

-- 2. Dentists
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `dentists` (`id`, `first_name`, `last_name`, `email`, `phone`, `specialization`, `license_number`, `is_active`) VALUES
(1, 'Rhea Ann', 'Salcedo', 'dr.salcedo@cosmosmiles.com', '09283853751', 'General Dentistry', 'DENT0001', 1),
(2, 'Vincent Robert', 'Ompoc', 'dr.ompoc@cosmosmiles.com', '09283853751', 'General Dentistry', 'DENT0002', 1);

-- 3. Staff Users
DROP TABLE IF EXISTS `staff_users`;
CREATE TABLE `staff_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` varchar(20) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `role` enum('receptionist','assistant_dentist') NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `staff_id` (`staff_id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `staff_users` (`id`, `staff_id`, `email`, `password`, `first_name`, `last_name`, `role`, `department`, `status`) VALUES
(1, 'REC001', 'maria.santos@cosmosmiles.com', '48fdefa7586020d7a646fd8454ce634228abbe3885e22e0de9b8cd1c7ac03a06abb84908f263400f681ad6758e61217f5e10d608d7ba392df566506fbd82554b', 'Maria', 'Santos', 'receptionist', 'Front Desk', 'active');

-- 4. Admin Users
DROP TABLE IF EXISTS `admin_users`;
CREATE TABLE `admin_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `dentist_id` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `role` enum('admin','staff') DEFAULT 'admin',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `uniq_dentist_id` (`dentist_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `admin_users` (`id`, `dentist_id`, `username`, `email`, `password`, `first_name`, `last_name`, `role`, `status`) VALUES
(1, 'DENT0001', 'rhea.salcedo', 'dr.salcedo@cosmosmiles.com', 'e7648d6dcaffb3f51057b0196849b4ed8f9a1888423753720b6dbb828bdabef9bc333b25b5a503249e3dd3b461e707980545e180cc504a067f72154ed4f5464e', 'Rhea Ann', 'Salcedo', 'admin', 'active'),
(2, 'DENT0002', 'vincent.ompoc', 'dr.ompoc@cosmosmiles.com', '07bfb274764b5d555dfeb0203697fbb320a96e37a2a27dbc783640f130d37069e6db7038438c0bd10a0b113a6f34c3bd873b2f6bbb96c6f12852edd49056d6e7', 'Vincent Robert', 'Ompoc', 'admin', 'active');

-- 5. Site Content
DROP TABLE IF EXISTS `site_content`;
CREATE TABLE `site_content` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `page` varchar(50) NOT NULL,
  `section_key` varchar(100) NOT NULL,
  `content_type` enum('text','image','icon') NOT NULL DEFAULT 'text',
  `content_value` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_page_section` (`page`,`section_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `site_content` (`page`, `section_key`, `content_type`, `content_value`) VALUES
('home', 'hero_title', 'text', 'Home of the Perfect Smiles'),
('home', 'hero_subtitle', 'text', 'Professional dental care'),
('clinic', 'name', 'text', 'Cosmo Smiles Dental Clinic'),
('clinic', 'address', 'text', 'Binangonan, Rizal'),
('clinic', 'email', 'text', 'info@cosmosmiles.com'),
('clinic', 'phone', 'text', '0999 888 7777');

-- 6. Clients & Appliances (Schema ONLY)
DROP TABLE IF EXISTS `clients`;
CREATE TABLE `clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` varchar(100) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `birthdate` date NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `uk_client_id` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
  `patient_first_name` varchar(50) NOT NULL,
  `patient_last_name` varchar(50) NOT NULL,
  `patient_phone` varchar(15) NOT NULL,
  `patient_email` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_appointment_id` (`appointment_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `appointment_feedbacks`;
CREATE TABLE `appointment_feedbacks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `appointment_id` varchar(20) NOT NULL,
  `rating` tinyint(4) NOT NULL,
  `feedback` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `patient_medical_history`;
CREATE TABLE `patient_medical_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` varchar(50) NOT NULL,
  `heart_disease` tinyint(1) DEFAULT 0,
  `diabetes` tinyint(1) DEFAULT 0,
  `allergies` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `patient_records`;
CREATE TABLE `patient_records` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `record_id` varchar(50) NOT NULL,
  `client_id` varchar(100) NOT NULL,
  `record_type` enum('treatment','consultation','xray','prescription','followup','emergency') NOT NULL,
  `record_title` varchar(255) NOT NULL,
  `record_date` date NOT NULL,
  `procedure` text NOT NULL,
  `description` text NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Backups and Logs
DROP TABLE IF EXISTS `appointments_backup`;
CREATE TABLE `appointments_backup` LIKE `appointments`;
DROP TABLE IF EXISTS `appointments_backup_2024`;
CREATE TABLE `appointments_backup_2024` LIKE `appointments`;
DROP TABLE IF EXISTS `clients_backup_20260305`;
CREATE TABLE `clients_backup_20260305` LIKE `clients`;

DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `identifier` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `attempt_time` timestamp DEFAULT CURRENT_TIMESTAMP,
  `is_successful` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `medical_edit_requests`;
CREATE TABLE `medical_edit_requests` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` varchar(50) DEFAULT NULL,
  `status` enum('pending','approved','denied') DEFAULT 'pending',
  `requested_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `submitted_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `password_resets`;
CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) DEFAULT NULL,
  `token` varchar(255) DEFAULT NULL,
  `user_type` enum('client','staff','admin') DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `reminder_logs`;
CREATE TABLE `reminder_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `staff_id` varchar(20) DEFAULT NULL,
  `client_id` varchar(100) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `sent_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `request_logs`;
CREATE TABLE `request_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) DEFAULT NULL,
  `request_time` timestamp DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DROP TABLE IF EXISTS `verification_otps`;
CREATE TABLE `verification_otps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) DEFAULT NULL,
  `otp_code` varchar(6) DEFAULT NULL,
  `type` enum('email','phone') DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS=1;