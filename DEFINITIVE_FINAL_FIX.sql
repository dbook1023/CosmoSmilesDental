-- COSMO SMILES DENTAL - DEFINITIVE FINAL RECONCILIATION
-- This script safely bridges 100% of the gaps between your code and your database.
-- It fixes the 'Guest' booking crash and the 'Missing Column' errors forever.

DELIMITER //

CREATE PROCEDURE FinalSync()
BEGIN
    -- 1. FIX APPOINTMENTS (Allowing Guests + Adding all Notes/Price/Status)
    ALTER TABLE `appointments` MODIFY `client_id` varchar(100) NULL; -- ESSENTIAL FOR GUEST BOOKING
    
    IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME='appointments' AND COLUMN_NAME='notes' AND TABLE_SCHEMA=DATABASE()) THEN
        ALTER TABLE `appointments` ADD COLUMN `notes` text DEFAULT NULL;
    END IF;
    IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME='appointments' AND COLUMN_NAME='client_notes' AND TABLE_SCHEMA=DATABASE()) THEN
        ALTER TABLE `appointments` ADD COLUMN `client_notes` text DEFAULT NULL;
    END IF;
    IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME='appointments' AND COLUMN_NAME='admin_notes' AND TABLE_SCHEMA=DATABASE()) THEN
        ALTER TABLE `appointments` ADD COLUMN `admin_notes` text DEFAULT NULL;
    END IF;
    IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME='appointments' AND COLUMN_NAME='duration_minutes' AND TABLE_SCHEMA=DATABASE()) THEN
        ALTER TABLE `appointments` ADD COLUMN `duration_minutes` int(11) DEFAULT 30;
    END IF;
    IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME='appointments' AND COLUMN_NAME='payment_type' AND TABLE_SCHEMA=DATABASE()) THEN
        ALTER TABLE `appointments` ADD COLUMN `payment_type` enum('cash','gcash') DEFAULT 'cash';
    END IF;
    IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME='appointments' AND COLUMN_NAME='service_price' AND TABLE_SCHEMA=DATABASE()) THEN
        ALTER TABLE `appointments` ADD COLUMN `service_price` decimal(10,2) DEFAULT NULL;
    END IF;
    IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME='appointments' AND COLUMN_NAME='updated_at' AND TABLE_SCHEMA=DATABASE()) THEN
        ALTER TABLE `appointments` ADD COLUMN `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp();
    END IF;

    -- 2. FIX PATIENT RECORDS (Procedural & Archiving Gaps)
    IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME='patient_records' AND COLUMN_NAME='record_time' AND TABLE_SCHEMA=DATABASE()) THEN
        ALTER TABLE `patient_records` 
        ADD COLUMN `record_time` time NOT NULL AFTER `record_date`,
        ADD COLUMN `findings` text DEFAULT NULL,
        ADD COLUMN `notes` text DEFAULT NULL,
        ADD COLUMN `files` text DEFAULT NULL,
        ADD COLUMN `created_by` varchar(100) NOT NULL,
        ADD COLUMN `is_archived` tinyint(1) DEFAULT 0;
    END IF;

    -- 3. FIX CLIENTS (Full Profile Synchronization)
    IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME='clients' AND COLUMN_NAME='gender' AND TABLE_SCHEMA=DATABASE()) THEN
        ALTER TABLE `clients` 
        ADD COLUMN `gender` enum('male','female','other') DEFAULT NULL,
        ADD COLUMN `address_line1` varchar(255) DEFAULT NULL,
        ADD COLUMN `phone` varchar(15) NOT NULL,
        ADD COLUMN `is_minor` tinyint(1) DEFAULT 0,
        ADD COLUMN `medical_history_status` enum('pending','completed') DEFAULT 'pending',
        ADD COLUMN `medical_history_edit_allowed` tinyint(1) DEFAULT 0;
    END IF;

    -- 4. FIX MEDICAL HISTORY (Full Health Profile)
    IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME='patient_medical_history' AND COLUMN_NAME='heart_disease_details' AND TABLE_SCHEMA=DATABASE()) THEN
        ALTER TABLE `patient_medical_history` 
        ADD COLUMN `heart_disease_details` text DEFAULT NULL,
        ADD COLUMN `high_blood_pressure` tinyint(1) DEFAULT 0,
        ADD COLUMN `past_surgeries` text DEFAULT NULL,
        ADD COLUMN `current_medications` text DEFAULT NULL,
        ADD COLUMN `is_pregnant` tinyint(1) DEFAULT 0;
    END IF;

END //

DELIMITER ;

-- Execute
CALL FinalSync();

-- Cleanup
DROP PROCEDURE FinalSync;
