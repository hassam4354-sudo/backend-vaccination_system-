<?php
include("config.php");

// Create connection
$connection = mysqli_connect($host, $dbusername, $dbpassword, $databasename);

// Check connection
if(!$connection){
    die("Database Connection Failed: " . mysqli_connect_error());
}

// Set charset to UTF-8
mysqli_set_charset($connection, "utf8mb4");

// Function to sanitize input
function sanitize_input($data) {
    global $connection;
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return mysqli_real_escape_string($connection, $data);
}

// Function to log audit
function log_audit($user_id, $action_type, $table_name, $record_id, $description) {
    global $connection;
    
    $ip_address = $_SERVER['REMOTE_ADDR'];
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    
    $query = "INSERT INTO audit_logs (user_id, action_type, table_name, record_id, action_description, ip_address, user_agent)
              VALUES ('$user_id', '$action_type', '$table_name', '$record_id', '$description', '$ip_address', '$user_agent')";
    
    mysqli_query($connection, $query);
}

// Function to create notification
function create_notification($user_id, $title, $message, $type = 'system', $related_id = NULL) {
    global $connection;
    
    $query = "INSERT INTO notifications (user_id, title, message, notification_type, related_id)
              VALUES ('$user_id', '$title', '$message', '$type', ";
    $query .= $related_id ? "'$related_id'" : "NULL";
    $query .= ")";
    
    return mysqli_query($connection, $query);
}

// Function to redirect with message (for booking page)
function redirect_with_message($url, $message = null, $type = 'success') {
    if ($message) {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
    }
    header("Location: $url");
    exit();
}

// Function to check admin login (for booking page)
function check_admin_login() {
    global $connection;
    if (!isset($_SESSION['logged_in']) || $_SESSION['user_type'] != 'admin') {
        header("Location: ../login.php");
        exit();
    }
}

// Function to get admin details (for booking page)
function get_admin_details($connection) {
    $user_id = $_SESSION['user_id'];
    $query = "SELECT admin_id, full_name FROM admins WHERE user_id = '$user_id'";
    $result = mysqli_query($connection, $query);
    return mysqli_fetch_assoc($result);
}

// Function to get booking statistics (for booking page)
function get_booking_statistics($connection) {
    $query = "SELECT 
                COUNT(DISTINCT vb.booking_id) as total_bookings,
                SUM(CASE WHEN ar.request_status = 'pending' AND vb.booking_status = 'scheduled' THEN 1 ELSE 0 END) as pending_bookings,
                SUM(CASE WHEN ar.request_status = 'approved' AND vb.booking_status = 'scheduled' THEN 1 ELSE 0 END) as approved_bookings,
                SUM(CASE WHEN vb.booking_status = 'completed' THEN 1 ELSE 0 END) as completed_bookings,
                SUM(CASE WHEN vb.booking_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
                SUM(CASE WHEN vb.booking_status = 'missed' THEN 1 ELSE 0 END) as missed_bookings
              FROM vaccination_bookings vb
              INNER JOIN appointment_requests ar ON vb.request_id = ar.request_id";
    
    $result = mysqli_query($connection, $query);
    return mysqli_fetch_assoc($result);
}

// booking
?>