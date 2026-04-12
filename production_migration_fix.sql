-- Cosmo Smiles Dental - Master Production Migration Fix
-- Use this script to add all missing columns to the production database

SET FOREIGN_KEY_CHECKS=0;

-- 1. Fix Clients Table
ALTER TABLE `clients` 
ADD COLUMN IF NOT EXISTS `gender` enum('male','female','other') DEFAULT NULL AFTER `birthdate`,
ADD COLUMN IF NOT EXISTS `address_line1` varchar(255) DEFAULT NULL AFTER `gender`,
ADD COLUMN IF NOT EXISTS `address_line2` varchar(255) DEFAULT NULL AFTER `address_line1`,
ADD COLUMN IF NOT EXISTS `city` varchar(100) DEFAULT NULL AFTER `address_line2`,
ADD COLUMN IF NOT EXISTS `state` varchar(100) DEFAULT NULL AFTER `city`,
ADD COLUMN IF NOT EXISTS `postal_code` varchar(20) DEFAULT NULL AFTER `state`,
ADD COLUMN IF NOT EXISTS `country` varchar(100) DEFAULT 'Philippines' AFTER `postal_code`,
ADD COLUMN IF NOT EXISTS `phone` varchar(15) NOT NULL AFTER `country`,
ADD COLUMN IF NOT EXISTS `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() AFTER `created_at`,
ADD COLUMN IF NOT EXISTS `is_minor` tinyint(1) DEFAULT 0 AFTER `updated_at`,
ADD COLUMN IF NOT EXISTS `parental_consent` tinyint(1) DEFAULT 0 AFTER `is_minor`,
ADD COLUMN IF NOT EXISTS `profile_image` varchar(255) DEFAULT NULL AFTER `parental_consent`,
ADD COLUMN IF NOT EXISTS `medical_history_status` enum('pending','completed') DEFAULT 'pending' AFTER `profile_image`,
ADD COLUMN IF NOT EXISTS `medical_history_edit_allowed` tinyint(1) DEFAULT 0 AFTER `medical_history_status`,
ADD COLUMN IF NOT EXISTS `parental_signature` varchar(255) DEFAULT NULL AFTER `medical_history_edit_allowed`;

-- 2. Fix Appointments Table
ALTER TABLE `appointments`
ADD COLUMN IF NOT EXISTS `dentist_id` int(11) DEFAULT NULL AFTER `client_id`,
ADD COLUMN IF NOT EXISTS `notes` text DEFAULT NULL AFTER `status`,
ADD COLUMN IF NOT EXISTS `client_notes` text DEFAULT NULL AFTER `notes`,
ADD COLUMN IF NOT EXISTS `admin_notes` text DEFAULT NULL AFTER `client_notes`,
ADD COLUMN IF NOT EXISTS `duration_minutes` int(11) DEFAULT 30 AFTER `admin_notes`,
ADD COLUMN IF NOT EXISTS `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() AFTER `created_at`,
ADD COLUMN IF NOT EXISTS `payment_type` enum('cash','gcash') DEFAULT 'cash' AFTER `updated_at`,
ADD COLUMN IF NOT EXISTS `service_price` decimal(10,2) DEFAULT NULL AFTER `payment_type`;

-- 3. Fix Patient Medical History Table
ALTER TABLE `patient_medical_history`
ADD COLUMN IF NOT EXISTS `heart_disease_details` text DEFAULT NULL AFTER `heart_disease`,
ADD COLUMN IF NOT EXISTS `high_blood_pressure` tinyint(1) DEFAULT 0 AFTER `heart_disease_details`,
ADD COLUMN IF NOT EXISTS `past_surgeries` text DEFAULT NULL AFTER `allergies`,
ADD COLUMN IF NOT EXISTS `current_medications` text DEFAULT NULL AFTER `past_surgeries`,
ADD COLUMN IF NOT EXISTS `is_pregnant` tinyint(1) DEFAULT 0 AFTER `current_medications`,
ADD COLUMN IF NOT EXISTS `other_conditions` text DEFAULT NULL AFTER `is_pregnant`,
ADD COLUMN IF NOT EXISTS `created_at` timestamp NOT NULL DEFAULT current_timestamp() AFTER `other_conditions`,
ADD COLUMN IF NOT EXISTS `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() AFTER `created_at`;

-- 4. Fix Verification OTPs Table
ALTER TABLE `verification_otps`
ADD COLUMN IF NOT EXISTS `phone` varchar(20) DEFAULT NULL AFTER `email`,
ADD COLUMN IF NOT EXISTS `verified` tinyint(1) DEFAULT 0 AFTER `expires_at`,
ADD COLUMN IF NOT EXISTS `created_at` timestamp NOT NULL DEFAULT current_timestamp() AFTER `verified`;

SET FOREIGN_KEY_CHECKS=1;
