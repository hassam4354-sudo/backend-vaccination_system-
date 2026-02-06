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
?>
