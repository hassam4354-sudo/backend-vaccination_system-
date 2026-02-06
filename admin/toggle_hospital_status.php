<?php
session_start();
if(!isset($_SESSION["logged_in"]) || $_SESSION["user_type"] != "admin"){
    header("location:../login.php");
    exit();
}

include("../dbconnection.php");

$hospital_id = $_GET['id'];
$action = $_GET['action'];

$new_status = ($action == 'activate') ? 1 : 0;

// Update hospital status
$query = "UPDATE hospitals SET is_active = '$new_status' WHERE hospital_id = '$hospital_id'";
$run = mysqli_query($connection, $query);

if($run) {
    // Also update user status
    $query_user = "UPDATE users u 
                   JOIN hospitals h ON u.user_id = h.user_id 
                   SET u.is_active = '$new_status' 
                   WHERE h.hospital_id = '$hospital_id'";
    mysqli_query($connection, $query_user);
    
    // Log the action
    $action_text = $action == 'activate' ? 'Activated' : 'Deactivated';
    log_audit($_SESSION["user_id"], 'TOGGLE_HOSPITAL', 'hospitals', $hospital_id, "$action_text hospital ID: $hospital_id");
    
    echo "<script>alert('Hospital status updated successfully!')
    window.location.href = 'manage_hospitals.php'
    </script>";
} else {
    echo "<script>alert('Error: " . mysqli_error($connection) . "')
    window.location.href = 'manage_hospitals.php'
    </script>";
}
?>
