-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 27, 2026 at 05:42 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `child_vaccination_system`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_approve_appointment_request` (IN `p_request_id` INT, IN `p_admin_id` INT, IN `p_admin_notes` TEXT)   BEGIN
  DECLARE v_child_id    INT;
  DECLARE v_hospital_id INT;
  DECLARE v_vaccine_id  INT;
  DECLARE v_dose_number INT;
  DECLARE v_preferred_date DATE;
  DECLARE v_preferred_time TIME;
  DECLARE v_confirmation_code VARCHAR(20);

  START TRANSACTION;

  SELECT child_id, hospital_id, vaccine_id, dose_number, preferred_date, preferred_time
  INTO v_child_id, v_hospital_id, v_vaccine_id, v_dose_number, v_preferred_date, v_preferred_time
  FROM appointment_requests
  WHERE request_id = p_request_id AND request_status = 'pending';

  UPDATE appointment_requests
  SET request_status = 'approved',
      admin_notes    = p_admin_notes,
      processed_by   = p_admin_id,
      processed_at   = CURRENT_TIMESTAMP
  WHERE request_id = p_request_id;

  SET v_confirmation_code = CONCAT('VC', LPAD(p_request_id, 6, '0'));

  INSERT INTO vaccination_bookings (
    request_id, child_id, hospital_id, vaccine_id, dose_number,
    appointment_date, appointment_time, confirmation_code
  ) VALUES (
    p_request_id, v_child_id, v_hospital_id, v_vaccine_id, v_dose_number,
    v_preferred_date, v_preferred_time, v_confirmation_code
  );

  COMMIT;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `sp_complete_vaccination` (IN `p_booking_id` INT, IN `p_batch_number` VARCHAR(50), IN `p_administered_by` VARCHAR(100), IN `p_side_effects` TEXT, IN `p_notes` TEXT)   BEGIN
  DECLARE v_child_id    INT;
  DECLARE v_vaccine_id  INT;
  DECLARE v_dose_number INT;
  DECLARE v_hospital_id INT;

  START TRANSACTION;

  SELECT child_id, vaccine_id, dose_number, hospital_id
  INTO v_child_id, v_vaccine_id, v_dose_number, v_hospital_id
  FROM vaccination_bookings WHERE booking_id = p_booking_id;

  UPDATE vaccination_bookings SET booking_status = 'completed' WHERE booking_id = p_booking_id;

  INSERT INTO vaccination_records (
    booking_id, child_id, vaccine_id, dose_number, hospital_id,
    vaccination_date, vaccination_time, batch_number, administered_by, side_effects, notes
  ) VALUES (
    p_booking_id, v_child_id, v_vaccine_id, v_dose_number, v_hospital_id,
    CURDATE(), CURTIME(), p_batch_number, p_administered_by, p_side_effects, p_notes
  );

  UPDATE hospital_vaccine_inventory
  SET quantity_available = quantity_available - 1
  WHERE hospital_id = v_hospital_id AND vaccine_id = v_vaccine_id AND batch_number = p_batch_number;

  COMMIT;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `admins`
--

CREATE TABLE `admins` (
  `admin_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `role` varchar(50) DEFAULT 'Administrator',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admins`
--

INSERT INTO `admins` (`admin_id`, `user_id`, `full_name`, `role`, `created_at`) VALUES
(1, 1, 'System Administrator', 'Super Admin', '2026-02-04 05:02:23');

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `appointment_id` int(11) NOT NULL,
  `child_id` int(11) NOT NULL,
  `vaccine_id` int(11) NOT NULL,
  `hospital_id` int(11) NOT NULL,
  `doctor_id` int(11) NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `notes` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'scheduled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`appointment_id`, `child_id`, `vaccine_id`, `hospital_id`, `doctor_id`, `appointment_date`, `appointment_time`, `notes`, `status`, `created_at`) VALUES
(1, 2, 3, 1, 3, '2026-02-18', '14:00:00', '', 'scheduled', '2026-02-27 04:39:08');

-- --------------------------------------------------------

--
-- Table structure for table `appointment_requests`
--

CREATE TABLE `appointment_requests` (
  `request_id` int(11) NOT NULL,
  `child_id` int(11) NOT NULL,
  `hospital_id` int(11) NOT NULL,
  `vaccine_id` int(11) NOT NULL,
  `dose_number` int(11) NOT NULL,
  `preferred_date` date NOT NULL,
  `preferred_time` time DEFAULT NULL,
  `request_status` enum('pending','approved','rejected','cancelled') DEFAULT 'pending',
  `parent_notes` text DEFAULT NULL,
  `admin_notes` text DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `processed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `appointment_requests`
--

INSERT INTO `appointment_requests` (`request_id`, `child_id`, `hospital_id`, `vaccine_id`, `dose_number`, `preferred_date`, `preferred_time`, `request_status`, `parent_notes`, `admin_notes`, `processed_by`, `processed_at`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, 1, '2026-02-20', '22:30:00', 'approved', 'Hurry', 'Approved by admin', 1, '2026-02-06 05:22:58', '2026-02-27 04:39:08', '2026-02-27 04:39:08');

--
-- Triggers `appointment_requests`
--
DELIMITER $$
CREATE TRIGGER `trg_log_appointment_approval` AFTER UPDATE ON `appointment_requests` FOR EACH ROW BEGIN
  IF OLD.request_status = 'pending' AND NEW.request_status = 'approved' THEN
    INSERT INTO audit_logs (user_id, action_type, table_name, record_id, action_description)
    VALUES (NEW.processed_by, 'APPROVE_REQUEST', 'appointment_requests', NEW.request_id,
            CONCAT('Approved appointment request for child ID: ', NEW.child_id));
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action_type` varchar(50) NOT NULL,
  `table_name` varchar(50) DEFAULT NULL,
  `record_id` int(11) DEFAULT NULL,
  `action_description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `children`
--

CREATE TABLE `children` (
  `child_id` int(11) NOT NULL,
  `parent_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `date_of_birth` date NOT NULL,
  `gender` enum('Male','Female','Other') NOT NULL,
  `blood_group` varchar(5) DEFAULT NULL,
  `birth_weight` decimal(5,2) DEFAULT NULL,
  `birth_height` decimal(5,2) DEFAULT NULL,
  `medical_conditions` text DEFAULT NULL,
  `allergies` text DEFAULT NULL,
  `photo_url` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `children`
--

INSERT INTO `children` (`child_id`, `parent_id`, `full_name`, `date_of_birth`, `gender`, `blood_group`, `birth_weight`, `birth_height`, `medical_conditions`, `allergies`, `photo_url`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 'Test Child', '2025-01-08', 'Male', 'A+', 20.00, 90.00, 'Mental Issues', 'Humans', 'uploads/children/child_1770355302_6874.jfif', 1, '2026-02-27 04:39:08', '2026-02-27 04:39:08'),
(2, 1, 'test 2 child', '2025-12-16', 'Male', 'B+', 45.00, 55.00, 'nothing', 'nothing', 'uploads/children/child_1770356974_4850.jfif', 1, '2026-02-27 04:39:08', '2026-02-27 04:39:08'),
(3, 2, 'wasif', '2002-01-22', 'Male', 'A-', 55.00, 25.00, 'nothing', 'nothing', 'uploads/children/child_1770957286_1283.jfif', 1, '2026-02-27 04:39:08', '2026-02-27 04:39:08'),
(4, 1, 'faiz', '2025-11-04', 'Male', 'AB-', 5.20, 2.60, 'nothing', 'nothing', 'uploads/children/child_1770959630_5484.jfif', 1, '2026-02-27 04:39:08', '2026-02-27 04:39:08'),
(5, 1, 'eshal', '2025-02-05', 'Female', 'AB-', 5.49, 55.00, 'nothing', 'nothing', 'uploads/children/child_1772130927_7055.png', 1, '2026-02-27 04:39:08', '2026-02-27 04:39:08');

-- --------------------------------------------------------

--
-- Table structure for table `doctors`
--

CREATE TABLE `doctors` (
  `doctor_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `specialization` varchar(100) DEFAULT NULL,
  `hospital_id` int(11) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `doctors`
--

INSERT INTO `doctors` (`doctor_id`, `full_name`, `specialization`, `hospital_id`, `is_active`, `created_at`) VALUES
(1, 'Dr. Ahmed', 'Pediatrician', 1, 1, '2026-02-27 04:39:08'),
(2, 'Dr. Khan', 'Child Specialist', 1, 1, '2026-02-27 04:39:08'),
(3, 'Dr. Ali', 'Vaccination Expert', 1, 1, '2026-02-27 04:39:08'),
(4, 'Dr. Hussain', 'General Physician', 1, 1, '2026-02-27 04:39:08');

-- --------------------------------------------------------

--
-- Table structure for table `hospitals`
--

CREATE TABLE `hospitals` (
  `hospital_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `hospital_name` varchar(150) NOT NULL,
  `registration_number` varchar(50) DEFAULT NULL,
  `address` text NOT NULL,
  `city` varchar(50) NOT NULL,
  `state` varchar(50) NOT NULL,
  `postal_code` varchar(10) DEFAULT NULL,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `is_verified` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `hospitals`
--

INSERT INTO `hospitals` (`hospital_id`, `user_id`, `hospital_name`, `registration_number`, `address`, `city`, `state`, `postal_code`, `latitude`, `longitude`, `contact_person`, `is_verified`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 2, 'Aga Khan', '1122335', 'Dalmia, Karachi', 'Karachi', 'Sindh', '75550', 24.99000000, 25.33370000, 'Price Aga Khan', 1, 0, '2026-02-27 04:39:08', '2026-02-27 04:39:08');

-- --------------------------------------------------------

--
-- Table structure for table `hospital_vaccine_inventory`
--

CREATE TABLE `hospital_vaccine_inventory` (
  `inventory_id` int(11) NOT NULL,
  `hospital_id` int(11) NOT NULL,
  `vaccine_id` int(11) NOT NULL,
  `quantity_available` int(11) DEFAULT 0,
  `last_restocked_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `batch_number` varchar(50) DEFAULT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `hospital_vaccine_inventory`
--
DELIMITER $$
CREATE TRIGGER `trg_update_inventory_availability` BEFORE UPDATE ON `hospital_vaccine_inventory` FOR EACH ROW BEGIN
  IF NEW.quantity_available <= 0 THEN
    SET NEW.is_available = FALSE;
  ELSE
    SET NEW.is_available = TRUE;
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `notification_type` enum('vaccination_reminder','appointment_approved','appointment_rejected','booking_confirmation','vaccination_completed','system') NOT NULL,
  `title` varchar(200) NOT NULL,
  `message` text NOT NULL,
  `related_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`notification_id`, `user_id`, `notification_type`, `title`, `message`, `related_id`, `is_read`, `created_at`) VALUES
(1, 2, 'system', 'Hospital Verified', 'Your hospital Aga Khan has been verified by admin.', 1, 0, '2026-02-27 04:39:08'),
(2, 1, 'system', 'New Appointment Request', 'New vaccination appointment request for Test Child - BCG', 1, 0, '2026-02-27 04:39:08'),
(3, 3, 'appointment_approved', 'Appointment Approved', 'Your vaccination appointment request for Test Child has been approved!', 1, 0, '2026-02-27 04:39:08');

-- --------------------------------------------------------

--
-- Table structure for table `parents`
--

CREATE TABLE `parents` (
  `parent_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `state` varchar(50) DEFAULT NULL,
  `postal_code` varchar(10) DEFAULT NULL,
  `emergency_contact` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `parents`
--

INSERT INTO `parents` (`parent_id`, `user_id`, `full_name`, `address`, `city`, `state`, `postal_code`, `emergency_contact`, `created_at`, `updated_at`) VALUES
(1, 3, 'Test Parent', 'NN, Karachi', 'Karachi', 'Sindh', '76689', '03331234567', '2026-02-27 04:39:08', '2026-02-27 04:39:08'),
(2, 4, 'Hassam', 'NN, Karachi', 'Karachi', 'Sindh', '75550', '03331234567', '2026-02-27 04:39:08', '2026-02-27 04:39:08');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `user_type` enum('admin','parent','hospital') NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `email`, `password_hash`, `user_type`, `phone`, `is_active`, `created_at`, `updated_at`, `last_login`) VALUES
(1, 'admin@vaccination.com', '$2y$10$JMbtMyhSgFXlSTOpeEbkL.cPFYk.k3kWQe7oHPtzIfy181.PNE0DO', 'admin', '+923001234567', 1, '2026-02-04 05:02:23', '2026-02-06 04:32:20', '2026-02-06 04:32:20'),
(2, 'aku@mailinator.com', '$2y$10$PS0pwx5Mv0XPFegy5zi8xOibU6QfgCmv2SRIj6RE/T8ntBEgMuXPG', 'hospital', '03331234567', 0, '2026-02-06 05:08:26', '2026-02-06 05:57:04', NULL),
(3, 'vcsparent@mailinator.com', '$2y$10$KNCN.mdS5yjsP1Ie3vRmGuF3B43wnXIO3cbj5ED24fSYWXQogzZZa', 'parent', NULL, 1, '2026-02-06 05:18:10', '2026-02-06 05:18:10', NULL),
(4, 'hassam.4354+1@gmail.com', '$2y$10$Hmh32fAEBKIR6.MnTnlA2O4rV7bx6HzFVnnYUjYsKdJawXJ0TJY9i', 'parent', NULL, 1, '2026-02-13 04:33:18', '2026-02-13 04:33:18', NULL),
(5, 'hassam.4354@gmail.com', '$2y$10$at5DPEZRXaPbcnJ2Sz59ueaLphcVMbBCKz/i73qqrHvN.ggRKdTTa', 'hospital', NULL, 1, '2026-02-26 10:34:57', '2026-02-26 10:34:57', NULL),
(6, 'hassam.4354+hospital@gmail.com', '$2y$10$G1/WN9w/kE/vzn67iWHaNObwssu7k8gCZbR7Zf07Dk0SvOuI8Sxtu', 'hospital', NULL, 1, '2026-02-27 04:03:59', '2026-02-27 04:03:59', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `vaccination_bookings`
--

CREATE TABLE `vaccination_bookings` (
  `booking_id` int(11) NOT NULL,
  `request_id` int(11) NOT NULL,
  `child_id` int(11) NOT NULL,
  `hospital_id` int(11) NOT NULL,
  `vaccine_id` int(11) NOT NULL,
  `dose_number` int(11) NOT NULL,
  `appointment_date` date NOT NULL,
  `appointment_time` time NOT NULL,
  `booking_status` enum('scheduled','completed','cancelled','missed') DEFAULT 'scheduled',
  `confirmation_code` varchar(20) DEFAULT NULL,
  `reminder_sent` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vaccination_bookings`
--

INSERT INTO `vaccination_bookings` (`booking_id`, `request_id`, `child_id`, `hospital_id`, `vaccine_id`, `dose_number`, `appointment_date`, `appointment_time`, `booking_status`, `confirmation_code`, `reminder_sent`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, 1, 1, '2026-02-20', '22:30:00', 'scheduled', 'VC000001', 0, '2026-02-27 04:39:08', '2026-02-27 04:39:08');

-- --------------------------------------------------------

--
-- Table structure for table `vaccination_records`
--

CREATE TABLE `vaccination_records` (
  `record_id` int(11) NOT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `child_id` int(11) NOT NULL,
  `vaccine_id` int(11) NOT NULL,
  `dose_number` int(11) NOT NULL,
  `hospital_id` int(11) NOT NULL,
  `vaccination_date` date NOT NULL,
  `vaccination_time` time DEFAULT NULL,
  `batch_number` varchar(50) DEFAULT NULL,
  `administered_by` varchar(100) DEFAULT NULL,
  `next_dose_due_date` date DEFAULT NULL,
  `vaccination_status` enum('completed','partial','adverse_reaction') DEFAULT 'completed',
  `side_effects` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `certificate_url` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vaccination_schedule`
--

CREATE TABLE `vaccination_schedule` (
  `schedule_id` int(11) NOT NULL,
  `vaccine_id` int(11) NOT NULL,
  `dose_number` int(11) NOT NULL,
  `recommended_age_days` int(11) NOT NULL,
  `age_range_start_days` int(11) DEFAULT NULL,
  `age_range_end_days` int(11) DEFAULT NULL,
  `is_mandatory` tinyint(1) DEFAULT 1,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vaccination_schedule`
--

INSERT INTO `vaccination_schedule` (`schedule_id`, `vaccine_id`, `dose_number`, `recommended_age_days`, `age_range_start_days`, `age_range_end_days`, `is_mandatory`, `description`, `created_at`) VALUES
(1, 1, 1, 0, 0, 14, 1, NULL, '2026-02-27 04:39:08'),
(2, 2, 1, 0, 0, 14, 1, NULL, '2026-02-27 04:39:08'),
(3, 3, 1, 42, 42, 56, 1, NULL, '2026-02-27 04:39:08'),
(4, 3, 2, 70, 70, 84, 1, NULL, '2026-02-27 04:39:08'),
(5, 3, 3, 98, 98, 112, 1, NULL, '2026-02-27 04:39:08'),
(6, 4, 1, 42, 42, 56, 1, NULL, '2026-02-27 04:39:08'),
(7, 4, 2, 70, 70, 84, 1, NULL, '2026-02-27 04:39:08'),
(8, 4, 3, 98, 98, 112, 1, NULL, '2026-02-27 04:39:08'),
(9, 5, 1, 42, 42, 56, 1, NULL, '2026-02-27 04:39:08'),
(10, 5, 2, 70, 70, 84, 1, NULL, '2026-02-27 04:39:08'),
(11, 5, 3, 98, 98, 112, 1, NULL, '2026-02-27 04:39:08'),
(12, 6, 1, 98, 98, 112, 1, NULL, '2026-02-27 04:39:08'),
(13, 6, 2, 270, 270, 300, 1, NULL, '2026-02-27 04:39:08'),
(14, 7, 1, 270, 270, 300, 1, NULL, '2026-02-27 04:39:08'),
(15, 8, 1, 450, 450, 480, 1, NULL, '2026-02-27 04:39:08');

-- --------------------------------------------------------

--
-- Table structure for table `vaccines`
--

CREATE TABLE `vaccines` (
  `vaccine_id` int(11) NOT NULL,
  `vaccine_name` varchar(100) NOT NULL,
  `vaccine_code` varchar(20) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `manufacturer` varchar(100) DEFAULT NULL,
  `scheduled_age` varchar(50) DEFAULT NULL,
  `dosage_info` varchar(100) DEFAULT NULL,
  `storage_requirements` text DEFAULT NULL,
  `side_effects` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `vaccines`
--

INSERT INTO `vaccines` (`vaccine_id`, `vaccine_name`, `vaccine_code`, `description`, `manufacturer`, `scheduled_age`, `dosage_info`, `storage_requirements`, `side_effects`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'BCG', 'BCG-01', 'Bacillus Calmette-Guerin vaccine against tuberculosis', 'Serum Institute', NULL, NULL, NULL, NULL, 1, '2026-02-27 04:39:08', '2026-02-27 04:39:08'),
(2, 'Hepatitis B', 'HEP-B', 'Hepatitis B vaccine', 'GlaxoSmithKline', NULL, NULL, NULL, NULL, 1, '2026-02-27 04:39:08', '2026-02-27 04:39:08'),
(3, 'OPV', 'OPV-01', 'Oral Polio Vaccine', 'Bio-Med', NULL, NULL, NULL, NULL, 1, '2026-02-27 04:39:08', '2026-02-27 04:39:08'),
(4, 'Pentavalent', 'PENTA', 'DPT-HepB-Hib combination vaccine', 'Panacea Biotec', NULL, NULL, NULL, NULL, 1, '2026-02-27 04:39:08', '2026-02-27 04:39:08'),
(5, 'PCV', 'PCV-10', 'Pneumococcal Conjugate Vaccine', 'Pfizer', NULL, NULL, NULL, NULL, 1, '2026-02-27 04:39:08', '2026-02-27 04:39:08'),
(6, 'IPV', 'IPV-01', 'Inactivated Polio Vaccine', 'Sanofi Pasteur', NULL, NULL, NULL, NULL, 1, '2026-02-27 04:39:08', '2026-02-27 04:39:08'),
(7, 'Measles', 'MEASLES-1', 'Measles vaccine', 'Serum Institute', NULL, NULL, NULL, NULL, 1, '2026-02-27 04:39:08', '2026-02-27 04:39:08'),
(8, 'Measles-Rubella', 'MR-01', 'Measles-Rubella vaccine', 'Serum Institute', NULL, NULL, NULL, NULL, 1, '2026-02-27 04:39:08', '2026-02-27 04:39:08'),
(9, 'Vitamin A', 'VIT-A', 'Vitamin A supplementation', 'Various', NULL, NULL, NULL, NULL, 1, '2026-02-27 04:39:08', '2026-02-27 04:39:08'),
(10, 'DPT Booster', 'DPT-B', 'DPT Booster dose', 'Panacea Biotec', NULL, NULL, NULL, NULL, 1, '2026-02-27 04:39:08', '2026-02-27 04:39:08'),
(11, 'Polio vaccine', 'POL-676', 'uwefhwkjhw', '15-5-2025', NULL, NULL, NULL, NULL, 1, '2026-02-27 04:39:08', '2026-02-27 04:39:08'),
(12, 'Hepatitis B vaccine', 'HEP-B-44', 'Hepatitis B vaccine jo HBV se bachata hai', '18-5-2025', NULL, NULL, NULL, NULL, 1, '2026-02-27 04:39:08', '2026-02-27 04:39:08');

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_hospital_vaccine_availability`
-- (See below for the actual view)
--
CREATE TABLE `view_hospital_vaccine_availability` (
`hospital_id` int(11)
,`hospital_name` varchar(150)
,`city` varchar(50)
,`state` varchar(50)
,`vaccine_name` varchar(100)
,`quantity_available` int(11)
,`is_available` tinyint(1)
,`expiry_date` date
,`last_restocked_date` date
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_pending_requests`
-- (See below for the actual view)
--
CREATE TABLE `view_pending_requests` (
`request_id` int(11)
,`child_name` varchar(100)
,`parent_name` varchar(100)
,`hospital_name` varchar(150)
,`vaccine_name` varchar(100)
,`dose_number` int(11)
,`preferred_date` date
,`preferred_time` time
,`parent_notes` text
,`request_date` timestamp
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_todays_vaccinations`
-- (See below for the actual view)
--
CREATE TABLE `view_todays_vaccinations` (
`booking_id` int(11)
,`confirmation_code` varchar(20)
,`child_name` varchar(100)
,`parent_name` varchar(100)
,`hospital_name` varchar(150)
,`vaccine_name` varchar(100)
,`dose_number` int(11)
,`appointment_time` time
,`booking_status` enum('scheduled','completed','cancelled','missed')
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_upcoming_vaccinations`
-- (See below for the actual view)
--
CREATE TABLE `view_upcoming_vaccinations` (
`child_id` int(11)
,`child_name` varchar(100)
,`date_of_birth` date
,`parent_name` varchar(100)
,`parent_user_id` int(11)
,`vaccine_name` varchar(100)
,`dose_number` int(11)
,`due_date` date
,`days_until_due` int(7)
,`is_mandatory` tinyint(1)
);

-- --------------------------------------------------------

--
-- Structure for view `view_hospital_vaccine_availability`
--
DROP TABLE IF EXISTS `view_hospital_vaccine_availability`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_hospital_vaccine_availability`  AS SELECT `h`.`hospital_id` AS `hospital_id`, `h`.`hospital_name` AS `hospital_name`, `h`.`city` AS `city`, `h`.`state` AS `state`, `v`.`vaccine_name` AS `vaccine_name`, `hvi`.`quantity_available` AS `quantity_available`, `hvi`.`is_available` AS `is_available`, `hvi`.`expiry_date` AS `expiry_date`, `hvi`.`last_restocked_date` AS `last_restocked_date` FROM ((`hospitals` `h` join `hospital_vaccine_inventory` `hvi` on(`h`.`hospital_id` = `hvi`.`hospital_id`)) join `vaccines` `v` on(`hvi`.`vaccine_id` = `v`.`vaccine_id`)) WHERE `h`.`is_active` = 1 ORDER BY `h`.`hospital_name` ASC, `v`.`vaccine_name` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `view_pending_requests`
--
DROP TABLE IF EXISTS `view_pending_requests`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_pending_requests`  AS SELECT `ar`.`request_id` AS `request_id`, `c`.`full_name` AS `child_name`, `p`.`full_name` AS `parent_name`, `h`.`hospital_name` AS `hospital_name`, `v`.`vaccine_name` AS `vaccine_name`, `ar`.`dose_number` AS `dose_number`, `ar`.`preferred_date` AS `preferred_date`, `ar`.`preferred_time` AS `preferred_time`, `ar`.`parent_notes` AS `parent_notes`, `ar`.`created_at` AS `request_date` FROM ((((`appointment_requests` `ar` join `children` `c` on(`ar`.`child_id` = `c`.`child_id`)) join `parents` `p` on(`c`.`parent_id` = `p`.`parent_id`)) join `hospitals` `h` on(`ar`.`hospital_id` = `h`.`hospital_id`)) join `vaccines` `v` on(`ar`.`vaccine_id` = `v`.`vaccine_id`)) WHERE `ar`.`request_status` = 'pending' ORDER BY `ar`.`created_at` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `view_todays_vaccinations`
--
DROP TABLE IF EXISTS `view_todays_vaccinations`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_todays_vaccinations`  AS SELECT `vb`.`booking_id` AS `booking_id`, `vb`.`confirmation_code` AS `confirmation_code`, `c`.`full_name` AS `child_name`, `p`.`full_name` AS `parent_name`, `h`.`hospital_name` AS `hospital_name`, `v`.`vaccine_name` AS `vaccine_name`, `vb`.`dose_number` AS `dose_number`, `vb`.`appointment_time` AS `appointment_time`, `vb`.`booking_status` AS `booking_status` FROM ((((`vaccination_bookings` `vb` join `children` `c` on(`vb`.`child_id` = `c`.`child_id`)) join `parents` `p` on(`c`.`parent_id` = `p`.`parent_id`)) join `hospitals` `h` on(`vb`.`hospital_id` = `h`.`hospital_id`)) join `vaccines` `v` on(`vb`.`vaccine_id` = `v`.`vaccine_id`)) WHERE `vb`.`appointment_date` = curdate() AND `vb`.`booking_status` = 'scheduled' ORDER BY `vb`.`appointment_time` ASC ;

-- --------------------------------------------------------

--
-- Structure for view `view_upcoming_vaccinations`
--
DROP TABLE IF EXISTS `view_upcoming_vaccinations`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_upcoming_vaccinations`  AS SELECT `c`.`child_id` AS `child_id`, `c`.`full_name` AS `child_name`, `c`.`date_of_birth` AS `date_of_birth`, `p`.`full_name` AS `parent_name`, `p`.`user_id` AS `parent_user_id`, `v`.`vaccine_name` AS `vaccine_name`, `vs`.`dose_number` AS `dose_number`, `c`.`date_of_birth`+ interval `vs`.`recommended_age_days` day AS `due_date`, to_days(`c`.`date_of_birth` + interval `vs`.`recommended_age_days` day) - to_days(curdate()) AS `days_until_due`, `vs`.`is_mandatory` AS `is_mandatory` FROM (((`children` `c` join `parents` `p` on(`c`.`parent_id` = `p`.`parent_id`)) join `vaccination_schedule` `vs` on(1 = 1)) join `vaccines` `v` on(`vs`.`vaccine_id` = `v`.`vaccine_id`)) WHERE `c`.`is_active` = 1 AND `v`.`is_active` = 1 AND !exists(select 1 from `vaccination_records` `vr` where `vr`.`child_id` = `c`.`child_id` AND `vr`.`vaccine_id` = `v`.`vaccine_id` AND `vr`.`dose_number` = `vs`.`dose_number` limit 1) AND `c`.`date_of_birth` + interval `vs`.`recommended_age_days` day >= curdate() ORDER BY `c`.`date_of_birth`+ interval `vs`.`recommended_age_days` AS `day` ASC ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `uq_admin_user` (`user_id`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`appointment_id`),
  ADD KEY `idx_appt_child` (`child_id`),
  ADD KEY `idx_appt_vaccine` (`vaccine_id`),
  ADD KEY `idx_appt_hospital` (`hospital_id`),
  ADD KEY `idx_appt_doctor` (`doctor_id`),
  ADD KEY `idx_appt_date` (`appointment_date`);

--
-- Indexes for table `appointment_requests`
--
ALTER TABLE `appointment_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `idx_req_child` (`child_id`),
  ADD KEY `idx_req_hospital` (`hospital_id`),
  ADD KEY `idx_req_vaccine` (`vaccine_id`),
  ADD KEY `idx_req_proc_by` (`processed_by`),
  ADD KEY `idx_req_status` (`request_status`),
  ADD KEY `idx_req_date` (`preferred_date`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_audit_user` (`user_id`),
  ADD KEY `idx_audit_action` (`action_type`),
  ADD KEY `idx_audit_created_at` (`created_at`);

--
-- Indexes for table `children`
--
ALTER TABLE `children`
  ADD PRIMARY KEY (`child_id`),
  ADD KEY `idx_children_parent` (`parent_id`),
  ADD KEY `idx_children_dob` (`date_of_birth`);

--
-- Indexes for table `doctors`
--
ALTER TABLE `doctors`
  ADD PRIMARY KEY (`doctor_id`),
  ADD KEY `idx_doctor_hospital` (`hospital_id`);

--
-- Indexes for table `hospitals`
--
ALTER TABLE `hospitals`
  ADD PRIMARY KEY (`hospital_id`),
  ADD UNIQUE KEY `uq_hospital_user` (`user_id`),
  ADD UNIQUE KEY `uq_reg_number` (`registration_number`);

--
-- Indexes for table `hospital_vaccine_inventory`
--
ALTER TABLE `hospital_vaccine_inventory`
  ADD PRIMARY KEY (`inventory_id`),
  ADD UNIQUE KEY `uq_hospital_vaccine_batch` (`hospital_id`,`vaccine_id`,`batch_number`),
  ADD KEY `idx_inv_hospital_vaccine` (`hospital_id`,`vaccine_id`),
  ADD KEY `idx_inv_availability` (`is_available`),
  ADD KEY `fk_inventory_vaccine` (`vaccine_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_notif_user_read` (`user_id`,`is_read`),
  ADD KEY `idx_notif_created` (`created_at`);

--
-- Indexes for table `parents`
--
ALTER TABLE `parents`
  ADD PRIMARY KEY (`parent_id`),
  ADD UNIQUE KEY `uq_parent_user` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `uq_email` (`email`);

--
-- Indexes for table `vaccination_bookings`
--
ALTER TABLE `vaccination_bookings`
  ADD PRIMARY KEY (`booking_id`),
  ADD UNIQUE KEY `uq_request` (`request_id`),
  ADD UNIQUE KEY `uq_confirmation_code` (`confirmation_code`),
  ADD KEY `idx_book_child` (`child_id`),
  ADD KEY `idx_book_hospital` (`hospital_id`),
  ADD KEY `idx_book_vaccine` (`vaccine_id`),
  ADD KEY `idx_book_date` (`appointment_date`),
  ADD KEY `idx_book_status` (`booking_status`);

--
-- Indexes for table `vaccination_records`
--
ALTER TABLE `vaccination_records`
  ADD PRIMARY KEY (`record_id`),
  ADD KEY `idx_rec_booking` (`booking_id`),
  ADD KEY `idx_rec_child` (`child_id`),
  ADD KEY `idx_rec_vaccine` (`vaccine_id`),
  ADD KEY `idx_rec_hospital` (`hospital_id`),
  ADD KEY `idx_rec_date` (`vaccination_date`),
  ADD KEY `idx_rec_child_vac` (`child_id`,`vaccine_id`,`dose_number`);

--
-- Indexes for table `vaccination_schedule`
--
ALTER TABLE `vaccination_schedule`
  ADD PRIMARY KEY (`schedule_id`),
  ADD KEY `idx_sched_vaccine_dose` (`vaccine_id`,`dose_number`),
  ADD KEY `idx_sched_age` (`recommended_age_days`);

--
-- Indexes for table `vaccines`
--
ALTER TABLE `vaccines`
  ADD PRIMARY KEY (`vaccine_id`),
  ADD UNIQUE KEY `uq_vaccine_code` (`vaccine_code`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admins`
--
ALTER TABLE `admins`
  MODIFY `admin_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `appointment_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `appointment_requests`
--
ALTER TABLE `appointment_requests`
  MODIFY `request_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=100;

--
-- AUTO_INCREMENT for table `children`
--
ALTER TABLE `children`
  MODIFY `child_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `doctors`
--
ALTER TABLE `doctors`
  MODIFY `doctor_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `hospitals`
--
ALTER TABLE `hospitals`
  MODIFY `hospital_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `hospital_vaccine_inventory`
--
ALTER TABLE `hospital_vaccine_inventory`
  MODIFY `inventory_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `parents`
--
ALTER TABLE `parents`
  MODIFY `parent_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `vaccination_bookings`
--
ALTER TABLE `vaccination_bookings`
  MODIFY `booking_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `vaccination_records`
--
ALTER TABLE `vaccination_records`
  MODIFY `record_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vaccination_schedule`
--
ALTER TABLE `vaccination_schedule`
  MODIFY `schedule_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `vaccines`
--
ALTER TABLE `vaccines`
  MODIFY `vaccine_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admins`
--
ALTER TABLE `admins`
  ADD CONSTRAINT `fk_admins_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `fk_appt_child` FOREIGN KEY (`child_id`) REFERENCES `children` (`child_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_appt_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctors` (`doctor_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_appt_hospital` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`hospital_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_appt_vaccine` FOREIGN KEY (`vaccine_id`) REFERENCES `vaccines` (`vaccine_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `appointment_requests`
--
ALTER TABLE `appointment_requests`
  ADD CONSTRAINT `fk_req_child` FOREIGN KEY (`child_id`) REFERENCES `children` (`child_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_req_hospital` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`hospital_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_req_processed_by` FOREIGN KEY (`processed_by`) REFERENCES `admins` (`admin_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_req_vaccine` FOREIGN KEY (`vaccine_id`) REFERENCES `vaccines` (`vaccine_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `children`
--
ALTER TABLE `children`
  ADD CONSTRAINT `fk_children_parent` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`parent_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `doctors`
--
ALTER TABLE `doctors`
  ADD CONSTRAINT `fk_doctors_hospital` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`hospital_id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `hospitals`
--
ALTER TABLE `hospitals`
  ADD CONSTRAINT `fk_hospitals_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `hospital_vaccine_inventory`
--
ALTER TABLE `hospital_vaccine_inventory`
  ADD CONSTRAINT `fk_inventory_hospital` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`hospital_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_inventory_vaccine` FOREIGN KEY (`vaccine_id`) REFERENCES `vaccines` (`vaccine_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `parents`
--
ALTER TABLE `parents`
  ADD CONSTRAINT `fk_parents_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `vaccination_bookings`
--
ALTER TABLE `vaccination_bookings`
  ADD CONSTRAINT `fk_book_child` FOREIGN KEY (`child_id`) REFERENCES `children` (`child_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_book_hospital` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`hospital_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_book_request` FOREIGN KEY (`request_id`) REFERENCES `appointment_requests` (`request_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_book_vaccine` FOREIGN KEY (`vaccine_id`) REFERENCES `vaccines` (`vaccine_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `vaccination_records`
--
ALTER TABLE `vaccination_records`
  ADD CONSTRAINT `fk_rec_booking` FOREIGN KEY (`booking_id`) REFERENCES `vaccination_bookings` (`booking_id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rec_child` FOREIGN KEY (`child_id`) REFERENCES `children` (`child_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rec_hospital` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`hospital_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rec_vaccine` FOREIGN KEY (`vaccine_id`) REFERENCES `vaccines` (`vaccine_id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `vaccination_schedule`
--
ALTER TABLE `vaccination_schedule`
  ADD CONSTRAINT `fk_schedule_vaccine` FOREIGN KEY (`vaccine_id`) REFERENCES `vaccines` (`vaccine_id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
