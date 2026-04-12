-- COSMO SMILES DENTAL - SAFE PRODUCTION PATCH
-- This script adds missing columns safely without deleting any data.

DELIMITER //

CREATE PROCEDURE SafeAddColumn()
BEGIN
    -- 1. FIX CLIENTS TABLE
    IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME='clients' AND COLUMN_NAME='gender' AND TABLE_SCHEMA=DATABASE()) THEN
        ALTER TABLE `clients` ADD COLUMN `gender` enum('male','female','other') DEFAULT NULL AFTER `birthdate`;
    END IF;

    IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME='clients' AND COLUMN_NAME='address_line1' AND TABLE_SCHEMA=DATABASE()) THEN
        ALTER TABLE `clients` ADD COLUMN `address_line1` varchar(255) DEFAULT NULL;
    END IF;

    IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME='clients' AND COLUMN_NAME='address_line2' AND TABLE_SCHEMA=DATABASE()) THEN
        ALTER TABLE `clients` ADD COLUMN `address_line2` varchar(255) DEFAULT NULL;
    END IF;

    IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME='clients' AND COLUMN_NAME='city' AND TABLE_SCHEMA=DATABASE()) THEN
        ALTER TABLE `clients` ADD COLUMN `city` varchar(100) DEFAULT NULL;
    END IF;

    IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME='clients' AND COLUMN_NAME='phone' AND TABLE_SCHEMA=DATABASE()) THEN
        ALTER TABLE `clients` ADD COLUMN `phone` varchar(15) NOT NULL;
    END IF;

    IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME='clients' AND COLUMN_NAME='medical_history_status' AND TABLE_SCHEMA=DATABASE()) THEN
        ALTER TABLE `clients` ADD COLUMN `medical_history_status` enum('pending','completed') DEFAULT 'pending';
    END IF;

    IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME='clients' AND COLUMN_NAME='medical_history_edit_allowed' AND TABLE_SCHEMA=DATABASE()) THEN
        ALTER TABLE `clients` ADD COLUMN `medical_history_edit_allowed` tinyint(1) DEFAULT 0;
    END IF;

    IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME='clients' AND COLUMN_NAME='profile_image' AND TABLE_SCHEMA=DATABASE()) THEN
        ALTER TABLE `clients` ADD COLUMN `profile_image` varchar(255) DEFAULT NULL;
    END IF;

    IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME='clients' AND COLUMN_NAME='is_minor' AND TABLE_SCHEMA=DATABASE()) THEN
        ALTER TABLE `clients` ADD COLUMN `is_minor` tinyint(1) DEFAULT 0;
    END IF;

    IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME='clients' AND COLUMN_NAME='parental_signature' AND TABLE_SCHEMA=DATABASE()) THEN
        ALTER TABLE `clients` ADD COLUMN `parental_signature` varchar(255) DEFAULT NULL;
    END IF;

    -- 2. FIX APPOINTMENTS TABLE
    IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME='appointments' AND COLUMN_NAME='payment_type' AND TABLE_SCHEMA=DATABASE()) THEN
        ALTER TABLE `appointments` ADD COLUMN `payment_type` enum('cash','gcash') DEFAULT 'cash';
    END IF;

    IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME='appointments' AND COLUMN_NAME='service_price' AND TABLE_SCHEMA=DATABASE()) THEN
        ALTER TABLE `appointments` ADD COLUMN `service_price` decimal(10,2) DEFAULT NULL;
    END IF;

    IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME='appointments' AND COLUMN_NAME='duration_minutes' AND TABLE_SCHEMA=DATABASE()) THEN
        ALTER TABLE `appointments` ADD COLUMN `duration_minutes` int(11) DEFAULT 30;
    END IF;

    -- 3. FIX MEDICAL HISTORY TABLE
    IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME='patient_medical_history' AND COLUMN_NAME='high_blood_pressure' AND TABLE_SCHEMA=DATABASE()) THEN
        ALTER TABLE `patient_medical_history` ADD COLUMN `high_blood_pressure` tinyint(1) DEFAULT 0;
    END IF;

    IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME='patient_medical_history' AND COLUMN_NAME='current_medications' AND TABLE_SCHEMA=DATABASE()) THEN
        ALTER TABLE `patient_medical_history` ADD COLUMN `current_medications` text DEFAULT NULL;
    END IF;

    -- 4. FIX OTP TABLE
    IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME='verification_otps' AND COLUMN_NAME='verified' AND TABLE_SCHEMA=DATABASE()) THEN
        ALTER TABLE `verification_otps` ADD COLUMN `verified` tinyint(1) DEFAULT 0;
    END IF;

END //

DELIMITER ;

-- Execute the patch
CALL SafeAddColumn();

-- Cleanup the temporary procedure
DROP PROCEDURE SafeAddColumn;
