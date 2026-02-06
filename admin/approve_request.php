<?php
session_start();
if(!isset($_SESSION["logged_in"]) || $_SESSION["user_type"] != "admin"){
    header("location:../login.php");
    exit();
}

include("../dbconnection.php");

$request_id = $_GET['id'];
$user_id = $_SESSION["user_id"];

// Get admin_id
$query_admin = "SELECT admin_id FROM admins WHERE user_id = '$user_id'";
$result_admin = mysqli_query($connection, $query_admin);
$admin_data = mysqli_fetch_assoc($result_admin);
$admin_id = $admin_data['admin_id'];

// Call stored procedure to approve request
$query = "CALL sp_approve_appointment_request($request_id, $admin_id, 'Approved by admin')";
$run = mysqli_query($connection, $query);

if($run) {
    // Get parent user_id for notification
    $query_parent = "SELECT u.user_id, c.full_name as child_name
                     FROM appointment_requests ar
                     JOIN children c ON ar.child_id = c.child_id
                     JOIN parents p ON c.parent_id = p.parent_id
                     JOIN users u ON p.user_id = u.user_id
                     WHERE ar.request_id = '$request_id'";
    $result_parent = mysqli_query($connection, $query_parent);
    $parent_data = mysqli_fetch_assoc($result_parent);
    
    // Send notification to parent
    create_notification(
        $parent_data['user_id'],
        'Appointment Approved',
        "Your vaccination appointment request for {$parent_data['child_name']} has been approved!",
        'appointment_approved',
        $request_id
    );
    
    echo "<script>alert('Appointment request approved successfully!')
    window.location.href = 'appointment_requests.php'
    </script>";
} else {
    echo "<script>alert('Error: " . mysqli_error($connection) . "')
    window.location.href = 'appointment_requests.php'
    </script>";
}
?>
