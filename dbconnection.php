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

// ========== BOOKING LIST FUNCTIONS ==========

// Function to redirect with message
function redirect_with_message($url, $message = null, $type = 'success') {
    if ($message) {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $type;
    }
    header("Location: $url");
    exit();
}

// Function to check admin login
function check_admin_login() {
    if (!isset($_SESSION['logged_in']) || $_SESSION['user_type'] != 'admin') {
        header("Location: ../login.php");
        exit();
    }
}

// Function to get admin details
function get_admin_details($connection) {
    $user_id = $_SESSION['user_id'];
    $query = "SELECT admin_id, full_name FROM admins WHERE user_id = '$user_id'";
    $result = mysqli_query($connection, $query);
    return mysqli_fetch_assoc($result);
}

// Function to get ALL bookings (jinho ne book ki hai aur vaccinated hain)
function get_all_bookings($connection, $status_filter = '', $search = '') {
    
    $query = "SELECT 
                vb.booking_id,
                vb.appointment_date as booking_date,
                vb.appointment_time,
                vb.booking_status,
                vb.confirmation_code,
                ar.request_status,
                c.full_name as child_name,
                p.full_name as parent_name,
                p.emergency_contact as parent_phone,
                v.vaccine_name,
                h.hospital_name,
                h.city as hospital_city,
                vr.vaccination_date,
                vr.vaccination_time,
                CASE 
                    WHEN vb.booking_status = 'completed' THEN 'Vaccinated'
                    WHEN vb.booking_status = 'cancelled' THEN 'Cancelled'
                    WHEN vb.booking_status = 'scheduled' AND ar.request_status = 'approved' THEN 'Approved'
                    WHEN vb.booking_status = 'scheduled' AND ar.request_status = 'pending' THEN 'Pending'
                    WHEN vb.booking_status = 'missed' THEN 'Missed'
                    ELSE vb.booking_status
                END as display_status
              FROM vaccination_bookings vb
              INNER JOIN appointment_requests ar ON vb.request_id = ar.request_id
              INNER JOIN children c ON vb.child_id = c.child_id
              INNER JOIN parents p ON c.parent_id = p.parent_id
              INNER JOIN vaccines v ON vb.vaccine_id = v.vaccine_id
              INNER JOIN hospitals h ON vb.hospital_id = h.hospital_id
              LEFT JOIN vaccination_records vr ON vb.booking_id = vr.booking_id
              WHERE 1=1";
    
    // Apply status filter
    if (!empty($status_filter) && $status_filter != 'all') {
        if ($status_filter == 'vaccinated') {
            $query .= " AND vb.booking_status = 'completed'";
        } elseif ($status_filter == 'pending') {
            $query .= " AND ar.request_status = 'pending' AND vb.booking_status = 'scheduled'";
        } elseif ($status_filter == 'approved') {
            $query .= " AND ar.request_status = 'approved' AND vb.booking_status = 'scheduled'";
        } elseif ($status_filter == 'cancelled') {
            $query .= " AND vb.booking_status = 'cancelled'";
        }
    }
    
    // Apply search filter
    if (!empty($search)) {
        $search_term = "%$search%";
        $query .= " AND (c.full_name LIKE '%$search_term%' 
                        OR p.full_name LIKE '%$search_term%' 
                        OR v.vaccine_name LIKE '%$search_term%' 
                        OR h.hospital_name LIKE '%$search_term%'
                        OR vb.confirmation_code LIKE '%$search_term%')";
    }
    
    $query .= " ORDER BY vb.booking_id DESC";
    
    $result = mysqli_query($connection, $query);
    return $result;
}

// Function to get booking statistics
function get_booking_statistics($connection) {
    $query = "SELECT 
                COUNT(DISTINCT vb.booking_id) as total_bookings,
                SUM(CASE WHEN ar.request_status = 'pending' AND vb.booking_status = 'scheduled' THEN 1 ELSE 0 END) as pending_bookings,
                SUM(CASE WHEN ar.request_status = 'approved' AND vb.booking_status = 'scheduled' THEN 1 ELSE 0 END) as approved_bookings,
                SUM(CASE WHEN vb.booking_status = 'completed' THEN 1 ELSE 0 END) as completed_bookings,
                SUM(CASE WHEN vb.booking_status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings
              FROM vaccination_bookings vb
              INNER JOIN appointment_requests ar ON vb.request_id = ar.request_id";
    
    $result = mysqli_query($connection, $query);
    return mysqli_fetch_assoc($result);
}

// Function to get single booking details
function get_booking_details($connection, $booking_id) {
    $query = "SELECT 
                vb.*,
                c.full_name as child_name,
                c.date_of_birth,
                p.full_name as parent_name,
                p.emergency_contact as parent_phone,
                v.vaccine_name,
                h.hospital_name,
                h.city as hospital_city,
                vr.vaccination_date,
                vr.vaccination_time,
                vr.batch_number,
                vr.administered_by
              FROM vaccination_bookings vb
              LEFT JOIN children c ON vb.child_id = c.child_id
              LEFT JOIN parents p ON c.parent_id = p.parent_id
              LEFT JOIN vaccines v ON vb.vaccine_id = v.vaccine_id
              LEFT JOIN hospitals h ON vb.hospital_id = h.hospital_id
              LEFT JOIN vaccination_records vr ON vb.booking_id = vr.booking_id
              WHERE vb.booking_id = '$booking_id'";
    
    $result = mysqli_query($connection, $query);
    return mysqli_fetch_assoc($result);
}

// Function to delete booking
function delete_booking($connection, $booking_id, $user_id) {
    // Start transaction
    mysqli_begin_transaction($connection);
    
    try {
        // Get request_id
        $query = "SELECT request_id FROM vaccination_bookings WHERE booking_id = '$booking_id'";
        $result = mysqli_query($connection, $query);
        $booking = mysqli_fetch_assoc($result);
        $request_id = $booking['request_id'] ?? 0;
        
        // Delete vaccination records first
        mysqli_query($connection, "DELETE FROM vaccination_records WHERE booking_id = '$booking_id'");
        
        // Delete booking
        $delete = mysqli_query($connection, "DELETE FROM vaccination_bookings WHERE booking_id = '$booking_id'");
        
        if (!$delete) {
            throw new Exception("Failed to delete booking");
        }
        
        // Update request status
        if ($request_id) {
            mysqli_query($connection, "UPDATE appointment_requests SET request_status = 'cancelled' WHERE request_id = '$request_id'");
        }
        
        // Log audit
        log_audit($user_id, 'DELETE', 'vaccination_bookings', $booking_id, "Deleted booking #$booking_id");
        
        mysqli_commit($connection);
        return ['success' => true, 'message' => 'Booking deleted successfully'];
        
    } catch (Exception $e) {
        mysqli_rollback($connection);
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// booking
?>