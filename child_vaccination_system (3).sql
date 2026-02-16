-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Feb 16, 2026 at 05:37 AM
-- Server version: 10.4.27-MariaDB
-- PHP Version: 8.2.0

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
    DECLARE v_child_id INT;
    DECLARE v_hospital_id INT;
    DECLARE v_vaccine_id INT;
    DECLARE v_dose_number INT;
    DECLARE v_preferred_date DATE;
    DECLARE v_preferred_time TIME;
    DECLARE v_confirmation_code VARCHAR(20);
    
    -- Start transaction
    START TRANSACTION;
    
    -- Get request details
    SELECT child_id, hospital_id, vaccine_id, dose_number, preferred_date, preferred_time
    INTO v_child_id, v_hospital_id, v_vaccine_id, v_dose_number, v_preferred_date, v_preferred_time
    FROM appointment_requests
    WHERE request_id = p_request_id AND request_status = 'pending';
    
    -- Update request status
    UPDATE appointment_requests
    SET request_status = 'approved',
        admin_notes = p_admin_notes,
        processed_by = p_admin_id,
        processed_at = CURRENT_TIMESTAMP
    WHERE request_id = p_request_id;
    
    -- Generate confirmation code
    SET v_confirmation_code = CONCAT('VC', LPAD(p_request_id, 6, '0'));
    
    -- Create booking
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
    DECLARE v_child_id INT;
    DECLARE v_vaccine_id INT;
    DECLARE v_dose_number INT;
    DECLARE v_hospital_id INT;
    
    -- Start transaction
    START TRANSACTION;
    
    -- Get booking details
    SELECT child_id, vaccine_id, dose_number, hospital_id
    INTO v_child_id, v_vaccine_id, v_dose_number, v_hospital_id
    FROM vaccination_bookings
    WHERE booking_id = p_booking_id;
    
    -- Update booking status
    UPDATE vaccination_bookings
    SET booking_status = 'completed'
    WHERE booking_id = p_booking_id;
    
    -- Create vaccination record
    INSERT INTO vaccination_records (
        booking_id, child_id, vaccine_id, dose_number, hospital_id,
        vaccination_date, vaccination_time, batch_number, administered_by,
        side_effects, notes
    ) VALUES (
        p_booking_id, v_child_id, v_vaccine_id, v_dose_number, v_hospital_id,
        CURDATE(), CURTIME(), p_batch_number, p_administered_by,
        p_side_effects, p_notes
    );
    
    -- Update inventory
    UPDATE hospital_vaccine_inventory
    SET quantity_available = quantity_available - 1
    WHERE hospital_id = v_hospital_id AND vaccine_id = v_vaccine_id
        AND batch_number = p_batch_number;
    
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
(1, 2, 3, 2, 3, '2026-02-18', '14:00:00', '', 'scheduled', '2026-02-13 05:11:21');

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
(1, 1, 1, 1, 1, '2026-02-20', '22:30:00', 'approved', 'Hurry', 'Approved by admin', 1, '2026-02-06 05:22:58', '2026-02-06 05:22:23', '2026-02-06 05:22:58');

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

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`log_id`, `user_id`, `action_type`, `table_name`, `record_id`, `action_description`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'LOGIN', 'users', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-04 05:05:19'),
(2, 1, 'LOGIN', 'users', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 04:32:20'),
(3, 1, 'LOGIN', 'users', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 05:09:44'),
(4, 1, 'VERIFY_HOSPITAL', 'hospitals', 1, 'Verified hospital ID: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 05:09:53'),
(5, 1, 'LOGOUT', 'users', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 05:10:00'),
(6, 2, 'LOGIN', 'users', 2, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 05:10:06'),
(7, 3, 'LOGIN', 'users', 3, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 05:19:15'),
(8, 3, 'ADD_CHILD', 'children', 1, 'Added child: Test Child', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 05:21:42'),
(9, 3, 'APPOINTMENT_REQUEST', 'appointment_requests', 1, 'Submitted appointment request #1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 05:22:23'),
(10, 3, 'LOGOUT', 'users', 3, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 05:22:41'),
(11, 1, 'LOGIN', 'users', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 05:22:45'),
(12, 1, 'APPROVE_REQUEST', 'appointment_requests', 1, 'Approved appointment request for child ID: 1', NULL, NULL, '2026-02-06 05:22:58'),
(13, 1, 'LOGOUT', 'users', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 05:23:02'),
(14, 3, 'LOGIN', 'users', 3, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 05:23:13'),
(15, 3, 'LOGOUT', 'users', 3, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 05:23:20'),
(16, 2, 'LOGIN', 'users', 2, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 05:23:39'),
(17, 3, 'LOGIN', 'users', 3, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 05:29:57'),
(18, 1, 'LOGIN', 'users', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 05:30:22'),
(19, 1, 'LOGOUT', 'users', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 05:30:41'),
(20, 1, 'LOGIN', 'users', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 05:30:57'),
(21, 3, 'LOGIN', 'users', 3, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 05:43:11'),
(22, 3, 'LOGOUT', 'users', 3, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 05:46:16'),
(23, 3, 'LOGIN', 'users', 3, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 05:46:39'),
(24, 3, 'ADD_CHILD', 'children', 2, 'Added child: test 2 child', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 05:49:34'),
(25, 3, 'LOGOUT', 'users', 3, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 05:51:58'),
(26, 2, 'LOGIN', 'users', 2, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 05:52:07'),
(27, 1, 'LOGIN', 'users', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 05:52:59'),
(28, 1, 'LOGOUT', 'users', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 05:53:16'),
(29, 1, 'LOGIN', 'users', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 05:53:24'),
(30, 1, 'TOGGLE_HOSPITAL', 'hospitals', 1, 'Deactivated hospital ID: 1', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 05:57:04'),
(31, 1, 'LOGOUT', 'users', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 05:57:27'),
(32, 3, 'LOGIN', 'users', 3, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/144.0.0.0 Safari/537.36', '2026-02-06 05:57:36'),
(33, 1, 'LOGIN', 'users', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-11 04:06:42'),
(34, 1, 'LOGOUT', 'users', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-11 04:08:42'),
(35, 3, 'LOGIN', 'users', 3, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-11 04:10:58'),
(36, 3, 'LOGOUT', 'users', 3, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-11 04:12:05'),
(37, 1, 'LOGIN', 'users', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-11 04:14:11'),
(38, 1, 'LOGIN', 'users', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-13 03:52:54'),
(39, 3, 'LOGIN', 'users', 3, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-13 03:58:24'),
(40, 1, 'LOGIN', 'users', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-13 03:59:18'),
(41, 1, 'LOGIN', 'users', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-13 04:03:20'),
(42, 1, 'LOGOUT', 'users', 1, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-13 04:30:27'),
(43, 3, 'LOGIN', 'users', 3, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-13 04:30:39'),
(44, 3, 'LOGOUT', 'users', 3, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-13 04:30:58'),
(45, 4, 'LOGIN', 'users', 4, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-13 04:33:51'),
(46, 4, 'ADD_CHILD', 'children', 3, 'Added child: wasif', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-13 04:34:46'),
(47, 4, 'LOGOUT', 'users', 4, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-13 04:35:36'),
(48, 1, 'LOGIN', 'users', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-13 04:35:46'),
(49, 1, 'LOGIN', 'users', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-13 05:12:16'),
(50, 3, 'LOGIN', 'users', 3, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-13 05:12:58'),
(51, 3, 'ADD_CHILD', 'children', 4, 'Added child: areeba', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-13 05:13:50'),
(52, 3, 'LOGIN', 'users', 3, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-13 05:28:51'),
(53, 3, 'LOGOUT', 'users', 3, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-13 05:33:10'),
(54, 3, 'LOGIN', 'users', 3, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-13 05:34:14'),
(55, 3, 'LOGOUT', 'users', 3, 'User logged out', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-13 05:41:31'),
(56, 1, 'LOGIN', 'users', 1, 'User logged in', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/145.0.0.0 Safari/537.36', '2026-02-13 05:41:46');

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
(1, 1, 'Test Child', '2025-01-08', 'Male', 'A+', '20.00', '90.00', 'Mental Issues', 'Humans', 'uploads/children/child_1770355302_6874.jfif', 1, '2026-02-06 05:21:42', '2026-02-06 05:21:42'),
(2, 1, 'test 2 child', '2025-12-16', 'Male', 'B+', '45.00', '55.00', 'nothing', 'nothing', 'uploads/children/child_1770356974_4850.jfif', 1, '2026-02-06 05:49:34', '2026-02-06 05:49:34'),
(3, 2, 'wasif', '2002-01-22', 'Male', 'A-', '55.00', '25.00', 'nothing', 'nothing', 'uploads/children/child_1770957286_1283.jfif', 1, '2026-02-13 04:34:46', '2026-02-13 04:34:46'),
(4, 1, 'areeba', '2025-11-04', 'Female', 'AB-', '5.20', '2.60', 'nothing', 'nothing', 'uploads/children/child_1770959630_5484.jfif', 1, '2026-02-13 05:13:50', '2026-02-13 05:13:50');

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
(1, 2, 'Aga Khan', '1122335', 'Dalmia, Karachi', 'Karachi', 'Sindh', '75550', '24.99000000', '25.33370000', 'Price Aga Khan', 1, 0, '2026-02-06 05:09:25', '2026-02-06 05:57:04');

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
(1, 2, 'system', 'Hospital Verified', 'Your hospital Aga Khan has been verified by admin. You can now start managing appointments.', 1, 0, '2026-02-06 05:09:53'),
(2, 1, 'system', 'New Appointment Request', 'New vaccination appointment request for Test Child - BCG', 1, 0, '2026-02-06 05:22:23'),
(3, 3, 'appointment_approved', 'Appointment Approved', 'Your vaccination appointment request for Test Child has been approved!', 1, 0, '2026-02-06 05:22:58');

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
(1, 3, 'Test Parent', 'NN, Karachi', 'Karachi', 'Sindh', '76689', '03331234567', '2026-02-06 05:19:01', '2026-02-06 05:19:01'),
(2, 4, 'Hassam', 'NN, Karachi', 'Karachi', 'Sindh', '75550', '03331234567', '2026-02-13 04:33:33', '2026-02-13 04:33:33');

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
(4, 'hassam.4354+1@gmail.com', '$2y$10$Hmh32fAEBKIR6.MnTnlA2O4rV7bx6HzFVnnYUjYsKdJawXJ0TJY9i', 'parent', NULL, 1, '2026-02-13 04:33:18', '2026-02-13 04:33:18', NULL);

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
(1, 1, 1, 1, 1, 1, '2026-02-20', '22:30:00', 'scheduled', 'VC000001', 0, '2026-02-06 05:22:58', '2026-02-06 05:22:58');

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
(1, 1, 1, 0, 0, 14, 1, NULL, '2026-02-04 05:02:23'),
(2, 2, 1, 0, 0, 14, 1, NULL, '2026-02-04 05:02:23'),
(3, 3, 1, 42, 42, 56, 1, NULL, '2026-02-04 05:02:23'),
(4, 3, 2, 70, 70, 84, 1, NULL, '2026-02-04 05:02:23'),
(5, 3, 3, 98, 98, 112, 1, NULL, '2026-02-04 05:02:23'),
(6, 4, 1, 42, 42, 56, 1, NULL, '2026-02-04 05:02:23'),
(7, 4, 2, 70, 70, 84, 1, NULL, '2026-02-04 05:02:23'),
(8, 4, 3, 98, 98, 112, 1, NULL, '2026-02-04 05:02:23'),
(9, 5, 1, 42, 42, 56, 1, NULL, '2026-02-04 05:02:23'),
(10, 5, 2, 70, 70, 84, 1, NULL, '2026-02-04 05:02:23'),
(11, 5, 3, 98, 98, 112, 1, NULL, '2026-02-04 05:02:23'),
(12, 6, 1, 98, 98, 112, 1, NULL, '2026-02-04 05:02:23'),
(13, 6, 2, 270, 270, 300, 1, NULL, '2026-02-04 05:02:23'),
(14, 7, 1, 270, 270, 300, 1, NULL, '2026-02-04 05:02:23'),
(15, 8, 1, 450, 450, 480, 1, NULL, '2026-02-04 05:02:23');

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
(1, 'BCG', 'BCG-01', 'Bacillus Calmette–Guérin vaccine against tuberculosis', 'Serum Institute', NULL, NULL, NULL, NULL, 1, '2026-02-04 05:02:23', '2026-02-04 05:02:23'),
(2, 'Hepatitis B', 'HEP-B', 'Hepatitis B vaccine', 'GlaxoSmithKline', NULL, NULL, NULL, NULL, 1, '2026-02-04 05:02:23', '2026-02-04 05:02:23'),
(3, 'OPV', 'OPV-01', 'Oral Polio Vaccine', 'Bio-Med', NULL, NULL, NULL, NULL, 1, '2026-02-04 05:02:23', '2026-02-04 05:02:23'),
(4, 'Pentavalent', 'PENTA', 'DPT-HepB-Hib combination vaccine', 'Panacea Biotec', NULL, NULL, NULL, NULL, 1, '2026-02-04 05:02:23', '2026-02-04 05:02:23'),
(5, 'PCV', 'PCV-10', 'Pneumococcal Conjugate Vaccine', 'Pfizer', NULL, NULL, NULL, NULL, 1, '2026-02-04 05:02:23', '2026-02-04 05:02:23'),
(6, 'IPV', 'IPV-01', 'Inactivated Polio Vaccine', 'Sanofi Pasteur', NULL, NULL, NULL, NULL, 1, '2026-02-04 05:02:23', '2026-02-04 05:02:23'),
(7, 'Measles', 'MEASLES-1', 'Measles vaccine', 'Serum Institute', NULL, NULL, NULL, NULL, 1, '2026-02-04 05:02:23', '2026-02-04 05:02:23'),
(8, 'Measles-Rubella', 'MR-01', 'Measles-Rubella vaccine', 'Serum Institute', NULL, NULL, NULL, NULL, 1, '2026-02-04 05:02:23', '2026-02-04 05:02:23'),
(9, 'Vitamin A', 'VIT-A', 'Vitamin A supplementation', 'Various', NULL, NULL, NULL, NULL, 1, '2026-02-04 05:02:23', '2026-02-04 05:02:23'),
(10, 'DPT Booster', 'DPT-B', 'DPT Booster dose', 'Panacea Biotec', NULL, NULL, NULL, NULL, 1, '2026-02-04 05:02:23', '2026-02-04 05:02:23');

-- --------------------------------------------------------

--
-- Stand-in structure for view `view_child_vaccination_history`
-- (See below for the actual view)
--
CREATE TABLE `view_child_vaccination_history` (
`record_id` int(11)
,`child_id` int(11)
,`child_name` varchar(100)
,`parent_name` varchar(100)
,`vaccine_name` varchar(100)
,`dose_number` int(11)
,`vaccination_date` date
,`hospital_name` varchar(150)
,`vaccination_status` enum('completed','partial','adverse_reaction')
,`batch_number` varchar(50)
,`next_dose_due_date` date
);

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
-- Structure for view `view_child_vaccination_history`
--
DROP TABLE IF EXISTS `view_child_vaccination_history`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_child_vaccination_history`  AS SELECT `vr`.`record_id` AS `record_id`, `c`.`child_id` AS `child_id`, `c`.`full_name` AS `child_name`, `p`.`full_name` AS `parent_name`, `v`.`vaccine_name` AS `vaccine_name`, `vr`.`dose_number` AS `dose_number`, `vr`.`vaccination_date` AS `vaccination_date`, `h`.`hospital_name` AS `hospital_name`, `vr`.`vaccination_status` AS `vaccination_status`, `vr`.`batch_number` AS `batch_number`, `vr`.`next_dose_due_date` AS `next_dose_due_date` FROM ((((`vaccination_records` `vr` join `children` `c` on(`vr`.`child_id` = `c`.`child_id`)) join `parents` `p` on(`c`.`parent_id` = `p`.`parent_id`)) join `vaccines` `v` on(`vr`.`vaccine_id` = `v`.`vaccine_id`)) join `hospitals` `h` on(`vr`.`hospital_id` = `h`.`hospital_id`)) ORDER BY `vr`.`vaccination_date` AS `DESCdesc` ASC  ;

-- --------------------------------------------------------

--
-- Structure for view `view_hospital_vaccine_availability`
--
DROP TABLE IF EXISTS `view_hospital_vaccine_availability`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_hospital_vaccine_availability`  AS SELECT `h`.`hospital_id` AS `hospital_id`, `h`.`hospital_name` AS `hospital_name`, `h`.`city` AS `city`, `h`.`state` AS `state`, `v`.`vaccine_name` AS `vaccine_name`, `hvi`.`quantity_available` AS `quantity_available`, `hvi`.`is_available` AS `is_available`, `hvi`.`expiry_date` AS `expiry_date`, `hvi`.`last_restocked_date` AS `last_restocked_date` FROM ((`hospitals` `h` join `hospital_vaccine_inventory` `hvi` on(`h`.`hospital_id` = `hvi`.`hospital_id`)) join `vaccines` `v` on(`hvi`.`vaccine_id` = `v`.`vaccine_id`)) WHERE `h`.`is_active` = 1 ORDER BY `h`.`hospital_name` ASC, `v`.`vaccine_name` ASC  ;

-- --------------------------------------------------------

--
-- Structure for view `view_pending_requests`
--
DROP TABLE IF EXISTS `view_pending_requests`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_pending_requests`  AS SELECT `ar`.`request_id` AS `request_id`, `c`.`full_name` AS `child_name`, `p`.`full_name` AS `parent_name`, `h`.`hospital_name` AS `hospital_name`, `v`.`vaccine_name` AS `vaccine_name`, `ar`.`dose_number` AS `dose_number`, `ar`.`preferred_date` AS `preferred_date`, `ar`.`preferred_time` AS `preferred_time`, `ar`.`parent_notes` AS `parent_notes`, `ar`.`created_at` AS `request_date` FROM ((((`appointment_requests` `ar` join `children` `c` on(`ar`.`child_id` = `c`.`child_id`)) join `parents` `p` on(`c`.`parent_id` = `p`.`parent_id`)) join `hospitals` `h` on(`ar`.`hospital_id` = `h`.`hospital_id`)) join `vaccines` `v` on(`ar`.`vaccine_id` = `v`.`vaccine_id`)) WHERE `ar`.`request_status` = 'pending' ORDER BY `ar`.`created_at` ASC  ;

-- --------------------------------------------------------

--
-- Structure for view `view_todays_vaccinations`
--
DROP TABLE IF EXISTS `view_todays_vaccinations`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_todays_vaccinations`  AS SELECT `vb`.`booking_id` AS `booking_id`, `vb`.`confirmation_code` AS `confirmation_code`, `c`.`full_name` AS `child_name`, `p`.`full_name` AS `parent_name`, `h`.`hospital_name` AS `hospital_name`, `v`.`vaccine_name` AS `vaccine_name`, `vb`.`dose_number` AS `dose_number`, `vb`.`appointment_time` AS `appointment_time`, `vb`.`booking_status` AS `booking_status` FROM ((((`vaccination_bookings` `vb` join `children` `c` on(`vb`.`child_id` = `c`.`child_id`)) join `parents` `p` on(`c`.`parent_id` = `p`.`parent_id`)) join `hospitals` `h` on(`vb`.`hospital_id` = `h`.`hospital_id`)) join `vaccines` `v` on(`vb`.`vaccine_id` = `v`.`vaccine_id`)) WHERE `vb`.`appointment_date` = curdate() AND `vb`.`booking_status` = 'scheduled' ORDER BY `vb`.`appointment_time` ASC  ;

-- --------------------------------------------------------

--
-- Structure for view `view_upcoming_vaccinations`
--
DROP TABLE IF EXISTS `view_upcoming_vaccinations`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `view_upcoming_vaccinations`  AS SELECT `c`.`child_id` AS `child_id`, `c`.`full_name` AS `child_name`, `c`.`date_of_birth` AS `date_of_birth`, `p`.`full_name` AS `parent_name`, `p`.`user_id` AS `parent_user_id`, `v`.`vaccine_name` AS `vaccine_name`, `vs`.`dose_number` AS `dose_number`, `c`.`date_of_birth`+ interval `vs`.`recommended_age_days` day AS `due_date`, to_days(`c`.`date_of_birth` + interval `vs`.`recommended_age_days` day) - to_days(curdate()) AS `days_until_due`, `vs`.`is_mandatory` AS `is_mandatory` FROM (((`children` `c` join `parents` `p` on(`c`.`parent_id` = `p`.`parent_id`)) join `vaccination_schedule` `vs`) join `vaccines` `v` on(`vs`.`vaccine_id` = `v`.`vaccine_id`)) WHERE `c`.`is_active` = 1 AND `v`.`is_active` = 1 AND !exists(select 1 from `vaccination_records` `vr` where `vr`.`child_id` = `c`.`child_id` AND `vr`.`vaccine_id` = `v`.`vaccine_id` AND `vr`.`dose_number` = `vs`.`dose_number` limit 1) AND `c`.`date_of_birth` + interval `vs`.`recommended_age_days` day >= curdate() ORDER BY `c`.`date_of_birth`+ interval `vs`.`recommended_age_days` day AS `ASCday` ASC  ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admins`
--
ALTER TABLE `admins`
  ADD PRIMARY KEY (`admin_id`),
  ADD UNIQUE KEY `user_id` (`user_id`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`appointment_id`);

--
-- Indexes for table `appointment_requests`
--
ALTER TABLE `appointment_requests`
  ADD PRIMARY KEY (`request_id`),
  ADD KEY `hospital_id` (`hospital_id`),
  ADD KEY `vaccine_id` (`vaccine_id`),
  ADD KEY `processed_by` (`processed_by`),
  ADD KEY `idx_child_id` (`child_id`),
  ADD KEY `idx_status` (`request_status`),
  ADD KEY `idx_preferred_date` (`preferred_date`),
  ADD KEY `idx_requests_status_date` (`request_status`,`preferred_date`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action_type` (`action_type`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `children`
--
ALTER TABLE `children`
  ADD PRIMARY KEY (`child_id`),
  ADD KEY `idx_parent_id` (`parent_id`),
  ADD KEY `idx_dob` (`date_of_birth`),
  ADD KEY `idx_children_parent_active` (`parent_id`,`is_active`);

--
-- Indexes for table `doctors`
--
ALTER TABLE `doctors`
  ADD PRIMARY KEY (`doctor_id`),
  ADD KEY `hospital_id` (`hospital_id`);

--
-- Indexes for table `hospitals`
--
ALTER TABLE `hospitals`
  ADD PRIMARY KEY (`hospital_id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `registration_number` (`registration_number`),
  ADD KEY `idx_city` (`city`),
  ADD KEY `idx_is_active` (`is_active`),
  ADD KEY `idx_location` (`latitude`,`longitude`);

--
-- Indexes for table `hospital_vaccine_inventory`
--
ALTER TABLE `hospital_vaccine_inventory`
  ADD PRIMARY KEY (`inventory_id`),
  ADD UNIQUE KEY `unique_hospital_vaccine_batch` (`hospital_id`,`vaccine_id`,`batch_number`),
  ADD KEY `vaccine_id` (`vaccine_id`),
  ADD KEY `idx_hospital_vaccine` (`hospital_id`,`vaccine_id`),
  ADD KEY `idx_availability` (`is_available`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_user_read` (`user_id`,`is_read`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `parents`
--
ALTER TABLE `parents`
  ADD PRIMARY KEY (`parent_id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `idx_city` (`city`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_user_type` (`user_type`);

--
-- Indexes for table `vaccination_bookings`
--
ALTER TABLE `vaccination_bookings`
  ADD PRIMARY KEY (`booking_id`),
  ADD UNIQUE KEY `request_id` (`request_id`),
  ADD UNIQUE KEY `confirmation_code` (`confirmation_code`),
  ADD KEY `vaccine_id` (`vaccine_id`),
  ADD KEY `idx_child_id` (`child_id`),
  ADD KEY `idx_appointment_date` (`appointment_date`),
  ADD KEY `idx_booking_status` (`booking_status`),
  ADD KEY `idx_confirmation_code` (`confirmation_code`),
  ADD KEY `idx_bookings_hospital_date` (`hospital_id`,`appointment_date`);

--
-- Indexes for table `vaccination_records`
--
ALTER TABLE `vaccination_records`
  ADD PRIMARY KEY (`record_id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `vaccine_id` (`vaccine_id`),
  ADD KEY `hospital_id` (`hospital_id`),
  ADD KEY `idx_child_id` (`child_id`),
  ADD KEY `idx_vaccination_date` (`vaccination_date`),
  ADD KEY `idx_child_vaccine` (`child_id`,`vaccine_id`,`dose_number`),
  ADD KEY `idx_vaccination_records_child_date` (`child_id`,`vaccination_date`);

--
-- Indexes for table `vaccination_schedule`
--
ALTER TABLE `vaccination_schedule`
  ADD PRIMARY KEY (`schedule_id`),
  ADD KEY `idx_vaccine_dose` (`vaccine_id`,`dose_number`),
  ADD KEY `idx_age_range` (`recommended_age_days`);

--
-- Indexes for table `vaccines`
--
ALTER TABLE `vaccines`
  ADD PRIMARY KEY (`vaccine_id`),
  ADD UNIQUE KEY `vaccine_code` (`vaccine_code`),
  ADD KEY `idx_vaccine_code` (`vaccine_code`),
  ADD KEY `idx_is_active` (`is_active`);

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
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `children`
--
ALTER TABLE `children`
  MODIFY `child_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `doctors`
--
ALTER TABLE `doctors`
  MODIFY `doctor_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

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
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

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
  MODIFY `vaccine_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `admins`
--
ALTER TABLE `admins`
  ADD CONSTRAINT `admins_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `appointment_requests`
--
ALTER TABLE `appointment_requests`
  ADD CONSTRAINT `appointment_requests_ibfk_1` FOREIGN KEY (`child_id`) REFERENCES `children` (`child_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointment_requests_ibfk_2` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`hospital_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointment_requests_ibfk_3` FOREIGN KEY (`vaccine_id`) REFERENCES `vaccines` (`vaccine_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `appointment_requests_ibfk_4` FOREIGN KEY (`processed_by`) REFERENCES `admins` (`admin_id`) ON DELETE SET NULL;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `children`
--
ALTER TABLE `children`
  ADD CONSTRAINT `children_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `parents` (`parent_id`) ON DELETE CASCADE;

--
-- Constraints for table `doctors`
--
ALTER TABLE `doctors`
  ADD CONSTRAINT `doctors_ibfk_1` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`hospital_id`);

--
-- Constraints for table `hospitals`
--
ALTER TABLE `hospitals`
  ADD CONSTRAINT `hospitals_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `hospital_vaccine_inventory`
--
ALTER TABLE `hospital_vaccine_inventory`
  ADD CONSTRAINT `hospital_vaccine_inventory_ibfk_1` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`hospital_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `hospital_vaccine_inventory_ibfk_2` FOREIGN KEY (`vaccine_id`) REFERENCES `vaccines` (`vaccine_id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `parents`
--
ALTER TABLE `parents`
  ADD CONSTRAINT `parents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `vaccination_bookings`
--
ALTER TABLE `vaccination_bookings`
  ADD CONSTRAINT `vaccination_bookings_ibfk_1` FOREIGN KEY (`request_id`) REFERENCES `appointment_requests` (`request_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vaccination_bookings_ibfk_2` FOREIGN KEY (`child_id`) REFERENCES `children` (`child_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vaccination_bookings_ibfk_3` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`hospital_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vaccination_bookings_ibfk_4` FOREIGN KEY (`vaccine_id`) REFERENCES `vaccines` (`vaccine_id`) ON DELETE CASCADE;

--
-- Constraints for table `vaccination_records`
--
ALTER TABLE `vaccination_records`
  ADD CONSTRAINT `vaccination_records_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `vaccination_bookings` (`booking_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `vaccination_records_ibfk_2` FOREIGN KEY (`child_id`) REFERENCES `children` (`child_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vaccination_records_ibfk_3` FOREIGN KEY (`vaccine_id`) REFERENCES `vaccines` (`vaccine_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `vaccination_records_ibfk_4` FOREIGN KEY (`hospital_id`) REFERENCES `hospitals` (`hospital_id`) ON DELETE CASCADE;

--
-- Constraints for table `vaccination_schedule`
--
ALTER TABLE `vaccination_schedule`
  ADD CONSTRAINT `vaccination_schedule_ibfk_1` FOREIGN KEY (`vaccine_id`) REFERENCES `vaccines` (`vaccine_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
