<?php
session_start();
if(!isset($_SESSION["logged_in"]) || $_SESSION["user_type"] != "admin"){
    header("location:../login.php");
    exit();
}

include("../dbconnection.php");

$hospital_id = $_GET['id'];

// Update hospital verification status
$query = "UPDATE hospitals SET is_verified = 1 WHERE hospital_id = '$hospital_id'";
$run = mysqli_query($connection, $query);

if($run) {
    // Get hospital user_id for notification
    $query_hospital = "SELECT u.user_id, h.hospital_name
                       FROM hospitals h
                       JOIN users u ON h.user_id = u.user_id
                       WHERE h.hospital_id = '$hospital_id'";
    $result = mysqli_query($connection, $query_hospital);
    $hospital_data = mysqli_fetch_assoc($result);
    
    // Send notification
    create_notification(
        $hospital_data['user_id'],
        'Hospital Verified',
        "Your hospital {$hospital_data['hospital_name']} has been verified by admin. You can now start managing appointments.",
        'system',
        $hospital_id
    );
    
    // Log the action
    log_audit($_SESSION["user_id"], 'VERIFY_HOSPITAL', 'hospitals', $hospital_id, "Verified hospital ID: $hospital_id");
    
    echo "<script>alert('Hospital verified successfully!')
    window.location.href = 'manage_hospitals.php'
    </script>";
} else {
    echo "<script>alert('Error: " . mysqli_error($connection) . "')
    window.location.href = 'manage_hospitals.php'
    </script>";
}
?>
