<?php
session_start();
if(!isset($_SESSION["logged_in"]) || $_SESSION["user_type"] != "parent"){
    header("location:../login.php");
    exit();
}

include("../dbconnection.php");

if(isset($_POST['submit_request'])) {
    $child_id = sanitize_input($_POST['child_id']);
    $hospital_id = sanitize_input($_POST['hospital_id']);
    $vaccine_id = sanitize_input($_POST['vaccine_id']);
    $dose_number = sanitize_input($_POST['dose_number']);
    $preferred_date = sanitize_input($_POST['preferred_date']);
    $preferred_time = sanitize_input($_POST['preferred_time']);
    $parent_notes = sanitize_input($_POST['parent_notes']);
    
    // Insert appointment request
    $query = "INSERT INTO appointment_requests 
              (child_id, hospital_id, vaccine_id, dose_number, preferred_date, preferred_time, parent_notes, request_status)
              VALUES ('$child_id', '$hospital_id', '$vaccine_id', '$dose_number', '$preferred_date', '$preferred_time', '$parent_notes', 'pending')";
    
    $run = mysqli_query($connection, $query);
    
    if($run) {
        $request_id = mysqli_insert_id($connection);
        
        // Get admin users to send notification
        $query_admin = "SELECT user_id FROM admins LIMIT 1";
        $result_admin = mysqli_query($connection, $query_admin);
        if(mysqli_num_rows($result_admin) > 0) {
            $admin = mysqli_fetch_assoc($result_admin);
            
            // Get child and vaccine names for notification
            $query_details = "SELECT c.full_name as child_name, v.vaccine_name 
                            FROM children c, vaccines v 
                            WHERE c.child_id = '$child_id' AND v.vaccine_id = '$vaccine_id'";
            $result_details = mysqli_query($connection, $query_details);
            $details = mysqli_fetch_assoc($result_details);
            
            create_notification(
                $admin['user_id'],
                'New Appointment Request',
                "New vaccination appointment request for {$details['child_name']} - {$details['vaccine_name']}",
                'system',
                $request_id
            );
        }
        
        // Log the action
        log_audit($_SESSION["user_id"], 'APPOINTMENT_REQUEST', 'appointment_requests', $request_id, "Submitted appointment request #$request_id");
        
        echo "<script>alert('Appointment request submitted successfully! You will be notified once admin approves.')
        window.location.href = 'my_requests.php'
        </script>";
    } else {
        echo "<script>alert('Error: " . mysqli_error($connection) . "')
        window.location.href = 'book_appointment.php'
        </script>";
    }
}
?>
