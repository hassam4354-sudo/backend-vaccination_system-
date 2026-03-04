<?php
session_start();
if(!isset($_SESSION["logged_in"]) || $_SESSION["user_type"] != "parent"){
    header("Location: ../login.php");
    exit();
}

include("../dbconnection.php");

// Initialize response
$_SESSION['msg'] = "";
$_SESSION['msg_type'] = "";

if($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Get and sanitize inputs
    $child_id       = intval($_POST['child_id']);
    $hospital_id    = intval($_POST['hospital_id']);
    $vaccine_id     = intval($_POST['vaccine_id']);
    $dose_number    = intval($_POST['dose_number']);
    $preferred_date = mysqli_real_escape_string($connection, $_POST['preferred_date']);
    $preferred_time = mysqli_real_escape_string($connection, $_POST['preferred_time']);
    $parent_notes   = mysqli_real_escape_string($connection, trim($_POST['parent_notes'] ?? ''));
    
    // Validation array
    $errors = [];
    
    // Get parent_id from session user_id
    $user_id = $_SESSION['user_id'];
    $parent_query = mysqli_query($connection, 
        "SELECT parent_id FROM parents WHERE user_id = '$user_id'");
    
    if(mysqli_num_rows($parent_query) == 0) {
        $errors[] = "Parent record not found. Please contact admin.";
    } else {
        $parent_data = mysqli_fetch_assoc($parent_query);
        $parent_id = $parent_data['parent_id'];
    }
    
    // Validate child belongs to this parent
    if(empty($errors)) {
        $child_query = mysqli_query($connection,
            "SELECT child_id, full_name, date_of_birth FROM children 
             WHERE child_id = '$child_id' AND parent_id = '$parent_id' AND is_active = 1");
        
        if(mysqli_num_rows($child_query) == 0) {
            $errors[] = "Invalid child selected or child is not active.";
        } else {
            $child_data = mysqli_fetch_assoc($child_query);
            $child_name = $child_data['full_name'];
            $child_dob = $child_data['date_of_birth'];
        }
    }
    
    // Validate hospital exists, verified and active
    if(empty($errors)) {
        $hospital_query = mysqli_query($connection,
            "SELECT hospital_id, hospital_name, user_id FROM hospitals 
             WHERE hospital_id = '$hospital_id' AND is_verified = 1 AND is_active = 1");
        
        if(mysqli_num_rows($hospital_query) == 0) {
            $errors[] = "Selected hospital is not available or not verified.";
        } else {
            $hospital_data = mysqli_fetch_assoc($hospital_query);
            $hospital_name = $hospital_data['hospital_name'];
            $hospital_user_id = $hospital_data['user_id'];
        }
    }
    
    // Validate vaccine exists and is active
    if(empty($errors)) {
        $vaccine_query = mysqli_query($connection,
            "SELECT vaccine_id, vaccine_name FROM vaccines 
             WHERE vaccine_id = '$vaccine_id' AND is_active = 1");
        
        if(mysqli_num_rows($vaccine_query) == 0) {
            $errors[] = "Selected vaccine is not available.";
        } else {
            $vaccine_data = mysqli_fetch_assoc($vaccine_query);
            $vaccine_name = $vaccine_data['vaccine_name'];
        }
    }
    
    // Validate dose number (1-4)
    if($dose_number < 1 || $dose_number > 4) {
        $errors[] = "Invalid dose number selected.";
    }
    
    // Validate date (must be future date within 60 days)
    $today = date('Y-m-d');
    $max_date = date('Y-m-d', strtotime('+60 days'));
    
    if($preferred_date < date('Y-m-d', strtotime('+1 day'))) {
        $errors[] = "Preferred date must be at least tomorrow.";
    } elseif($preferred_date > $max_date) {
        $errors[] = "Preferred date cannot be more than 60 days in future.";
    }
    
    // Validate time (business hours 9 AM to 5 PM)
    if(!empty($preferred_time)) {
        $time_hour = intval(substr($preferred_time, 0, 2));
        if($time_hour < 9 || $time_hour > 17) {
            $errors[] = "Please select a time between 9:00 AM and 5:00 PM.";
        }
    } else {
        $errors[] = "Please select a preferred time.";
    }
    
    // Check for duplicate pending request
    if(empty($errors)) {
        $duplicate_query = mysqli_query($connection,
            "SELECT request_id, request_status, created_at FROM appointment_requests 
             WHERE child_id = '$child_id' 
             AND vaccine_id = '$vaccine_id' 
             AND dose_number = '$dose_number'
             AND request_status IN ('pending', 'approved')
             AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)");
        
        if(mysqli_num_rows($duplicate_query) > 0) {
            $dup = mysqli_fetch_assoc($duplicate_query);
            $status = $dup['request_status'];
            if($status == 'pending') {
                $errors[] = "You already have a PENDING request for this vaccine dose. Please wait for approval.";
            } else {
                $errors[] = "This vaccine dose has already been APPROVED. Please check your bookings.";
            }
        }
    }
    
    // Check child's age eligibility for this vaccine (optional but recommended)
    if(empty($errors)) {
        // Get recommended age for this dose
        $schedule_query = mysqli_query($connection,
            "SELECT recommended_age_days FROM vaccination_schedule 
             WHERE vaccine_id = '$vaccine_id' AND dose_number = '$dose_number'");
        
        if(mysqli_num_rows($schedule_query) > 0) {
            $schedule = mysqli_fetch_assoc($schedule_query);
            $rec_age_days = $schedule['recommended_age_days'];
            
            $child_age_days = floor((time() - strtotime($child_dob)) / 86400);
            
            if($child_age_days < $rec_age_days - 30) { // 30 days grace period
                $errors[] = "Child is too young for this vaccine dose. Recommended age: " . floor($rec_age_days/30) . " months.";
            }
        }
        // If no schedule found, skip age validation
    }
    
    // If no errors, proceed with insertion
    if(empty($errors)) {
        
        // Start transaction
        mysqli_begin_transaction($connection);
        
        try {
            // Insert appointment request
            $insert_query = "INSERT INTO appointment_requests 
                (child_id, hospital_id, vaccine_id, dose_number, 
                 preferred_date, preferred_time, parent_notes, 
                 request_status, created_at, updated_at) 
                VALUES 
                (?, ?, ?, ?, ?, ?, ?, 'pending', NOW(), NOW())";
            
            $stmt = mysqli_prepare($connection, $insert_query);
            mysqli_stmt_bind_param($stmt, "iiiisss", 
                $child_id, $hospital_id, $vaccine_id, $dose_number,
                $preferred_date, $preferred_time, $parent_notes);
            
            if(mysqli_stmt_execute($stmt)) {
                $request_id = mysqli_insert_id($connection);
                
                // Create notification for hospital
                $notify_query = "INSERT INTO notifications 
                    (user_id, notification_type, title, message, related_id, is_read, created_at)
                    VALUES 
                    (?, 'system', 'New Appointment Request', ?, ?, 0, NOW())";
                
                $notify_message = "New appointment request from parent for $child_name - $vaccine_name (Dose $dose_number)";
                
                $stmt2 = mysqli_prepare($connection, $notify_query);
                mysqli_stmt_bind_param($stmt2, "isi", $hospital_user_id, $notify_message, $request_id);
                mysqli_stmt_execute($stmt2);
                
                // Also notify admin for monitoring (optional)
                $admin_query = "SELECT user_id FROM users WHERE user_type = 'admin' LIMIT 1";
                $admin_result = mysqli_query($connection, $admin_query);
                if(mysqli_num_rows($admin_result) > 0) {
                    $admin = mysqli_fetch_assoc($admin_result);
                    $admin_notify = "INSERT INTO notifications 
                        (user_id, notification_type, title, message, related_id, is_read, created_at)
                        VALUES 
                        (?, 'system', 'New Appointment Request', ?, ?, 0, NOW())";
                    
                    $admin_msg = "New request: $child_name - $vaccine_name at $hospital_name";
                    
                    $stmt3 = mysqli_prepare($connection, $admin_notify);
                    mysqli_stmt_bind_param($stmt3, "isi", $admin['user_id'], $admin_msg, $request_id);
                    mysqli_stmt_execute($stmt3);
                }
                
                // Commit transaction
                mysqli_commit($connection);
                
                $_SESSION['msg'] = "Appointment request submitted successfully!<br>
                                    <strong>Child:</strong> $child_name<br>
                                    <strong>Vaccine:</strong> $vaccine_name (Dose $dose_number)<br>
                                    <strong>Hospital:</strong> $hospital_name<br>
                                    <strong>Date:</strong> " . date('d M Y', strtotime($preferred_date)) . "<br>
                                    <strong>Time:</strong> " . date('h:i A', strtotime($preferred_time)) . "<br><br>
                                    Hospital will review your request shortly. You will see an alert here when it is approved.";
                $_SESSION['msg_type'] = "success";
                
            } else {
                throw new Exception("Failed to insert request: " . mysqli_error($connection));
            }
            
        } catch (Exception $e) {
            mysqli_rollback($connection);
            $_SESSION['msg'] = "Error submitting request: " . $e->getMessage();
            $_SESSION['msg_type'] = "error";
        }
        
    } else {
        // Display errors
        $_SESSION['msg'] = "Please fix the following errors:<br>" . implode("<br>", $errors);
        $_SESSION['msg_type'] = "error";
    }
    
    // Redirect back to booking form
    header("location: book_appointment.php");
    exit();
    
} else {
    // Direct access without form submission
    header("location: book_appointment.php");
    exit();
}
?>