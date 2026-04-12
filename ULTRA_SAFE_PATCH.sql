-- COSMO SMILES DENTAL - ULTRA SAFE PRODUCTION PATCH
-- This script adds every missing column from the blueprint to your hosting database safely.

DELIMITER //

CREATE PROCEDURE UltraPatch()
BEGIN
    -- 1. FIX PATIENT RECORDS (WHERE 'is_archived' RECENTLY FAILED)
    IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME='patient_records' AND COLUMN_NAME='is_archived' AND TABLE_SCHEMA=DATABASE()) THEN
        ALTER TABLE `patient_records` 
        ADD COLUMN `record_time` time NOT NULL AFTER `record_date`,
        ADD COLUMN `findings` text DEFAULT NULL,
        ADD COLUMN `notes` text DEFAULT NULL,
        ADD COLUMN `followup_instructions` text DEFAULT NULL,
        ADD COLUMN `files` text DEFAULT NULL,
        ADD COLUMN `tooth_numbers` text DEFAULT NULL,
        ADD COLUMN `surfaces` text DEFAULT NULL,
        ADD COLUMN `created_by` varchar(100) NOT NULL,
        ADD COLUMN `is_archived` tinyint(1) DEFAULT 0,
        ADD COLUMN `archived_by` varchar(100) DEFAULT NULL,
        ADD COLUMN `archive_reason` text DEFAULT NULL,
        ADD COLUMN `archived_at` timestamp NULL DEFAULT NULL,
        ADD COLUMN `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp();
    END IF;

    -- 2. FIX CLIENTS TABLE (COMPREHENSIVE)
    IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME='clients' AND COLUMN_NAME='medical_history_status' AND TABLE_SCHEMA=DATABASE()) THEN
        ALTER TABLE `clients` 
        ADD COLUMN `gender` enum('male','female','other') DEFAULT NULL AFTER `birthdate`,
        ADD COLUMN `address_line1` varchar(255) DEFAULT NULL,
        ADD COLUMN `address_line2` varchar(255) DEFAULT NULL,
        ADD COLUMN `city` varchar(100) DEFAULT NULL,
        ADD COLUMN `state` varchar(100) DEFAULT NULL,
        ADD COLUMN `postal_code` varchar(20) DEFAULT NULL,
        ADD COLUMN `country` varchar(100) DEFAULT 'Philippines',
        ADD COLUMN `phone` varchar(15) NOT NULL,
        ADD COLUMN `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        ADD COLUMN `is_minor` tinyint(1) DEFAULT 0,
        ADD COLUMN `parental_consent` tinyint(1) DEFAULT 0,
        ADD COLUMN `profile_image` varchar(255) DEFAULT NULL,
        ADD COLUMN `medical_history_status` enum('pending','completed') DEFAULT 'pending',
        ADD COLUMN `medical_history_edit_allowed` tinyint(1) DEFAULT 0,
        ADD COLUMN `parental_signature` varchar(255) DEFAULT NULL;
    END IF;

    -- 3. FIX APPOINTMENTS TABLE
    IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME='appointments' AND COLUMN_NAME='duration_minutes' AND TABLE_SCHEMA=DATABASE()) THEN
        ALTER TABLE `appointments` 
        ADD COLUMN `dentist_id` int(11) DEFAULT NULL AFTER `client_id`,
        ADD COLUMN `notes` text DEFAULT NULL,
        ADD COLUMN `client_notes` text DEFAULT NULL,
        ADD COLUMN `admin_notes` text DEFAULT NULL,
        ADD COLUMN `duration_minutes` int(11) DEFAULT 30,
        ADD COLUMN `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        ADD COLUMN `payment_type` enum('cash','gcash') DEFAULT 'cash',
        ADD COLUMN `service_price` decimal(10,2) DEFAULT NULL;
    END IF;

    -- 4. FIX MEDICAL HISTORY
    IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME='patient_medical_history' AND COLUMN_NAME='high_blood_pressure' AND TABLE_SCHEMA=DATABASE()) THEN
        ALTER TABLE `patient_medical_history` 
        ADD COLUMN `heart_disease_details` text DEFAULT NULL AFTER `heart_disease`,
        ADD COLUMN `high_blood_pressure` tinyint(1) DEFAULT 0,
        ADD COLUMN `past_surgeries` text DEFAULT NULL AFTER `allergies`,
        ADD COLUMN `current_medications` text DEFAULT NULL AFTER `past_surgeries`,
        ADD COLUMN `is_pregnant` tinyint(1) DEFAULT 0,
        ADD COLUMN `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp();
    END IF;

    -- 5. FIX OTPs
    IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME='verification_otps' AND COLUMN_NAME='verified' AND TABLE_SCHEMA=DATABASE()) THEN
        ALTER TABLE `verification_otps` 
        ADD COLUMN `phone` varchar(20) DEFAULT NULL AFTER `email`,
        ADD COLUMN `verified` tinyint(1) DEFAULT 0,
        ADD COLUMN `created_at` timestamp NOT NULL DEFAULT current_timestamp();
    END IF;

END //

DELIMITER ;

-- Execute the Ultra Patch
CALL UltraPatch();

-- Cleanup
DROP PROCEDURE UltraPatch;
