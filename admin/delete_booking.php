<?php
require_once 'db_connect.php';
checkAdminLogin();

$admin_details = getAdminDetails($conn);
$admin_id = $admin_details['admin_id'] ?? 1;
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['booking_id'])) {
    
    $booking_id = sanitize($conn, $_POST['booking_id']);
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Get booking details for logging
        $get_details = $conn->prepare("
            SELECT vb.*, c.full_name as child_name, ar.request_id
            FROM vaccination_bookings vb
            LEFT JOIN children c ON vb.child_id = c.child_id
            LEFT JOIN appointment_requests ar ON vb.request_id = ar.request_id
            WHERE vb.booking_id = ?
        ");
        $get_details->bind_param("i", $booking_id);
        $get_details->execute();
        $details_result = $get_details->get_result();
        
        if ($details_result->num_rows == 0) {
            throw new Exception("Booking not found");
        }
        
        $booking = $details_result->fetch_assoc();
        $request_id = $booking['request_id'];
        $child_name = $booking['child_name'];
        
        // Check for vaccination records
        $check_records = $conn->prepare("
            SELECT record_id FROM vaccination_records WHERE booking_id = ?
        ");
        $check_records->bind_param("i", $booking_id);
        $check_records->execute();
        $records_result = $check_records->get_result();
        
        // Delete vaccination records first (foreign key constraint)
        $delete_records = $conn->prepare("
            DELETE FROM vaccination_records WHERE booking_id = ?
        ");
        $delete_records->bind_param("i", $booking_id);
        $delete_records->execute();
        
        // Delete the booking
        $delete_booking = $conn->prepare("
            DELETE FROM vaccination_bookings WHERE booking_id = ?
        ");
        $delete_booking->bind_param("i", $booking_id);
        
        if (!$delete_booking->execute()) {
            throw new Exception("Failed to delete booking");
        }
        
        // Update appointment request status if no other bookings exist
        if ($request_id) {
            $check_other = $conn->prepare("
                SELECT booking_id FROM vaccination_bookings 
                WHERE request_id = ? AND booking_id != ?
            ");
            $check_other->bind_param("ii", $request_id, $booking_id);
            $check_other->execute();
            $other_result = $check_other->get_result();
            
            if ($other_result->num_rows == 0) {
                // No other bookings, update request status
                $update_request = $conn->prepare("
                    UPDATE appointment_requests 
                    SET request_status = 'cancelled', 
                        admin_notes = 'Booking deleted by admin',
                        processed_by = ?,
                        processed_at = NOW()
                    WHERE request_id = ?
                ");
                $update_request->bind_param("ii", $admin_id, $request_id);
                $update_request->execute();
            }
        }
        
        // Log the deletion
        $log_query = $conn->prepare("
            INSERT INTO audit_logs (user_id, action_type, table_name, record_id, action_description, ip_address, user_agent, created_at)
            VALUES (?, 'DELETE', 'vaccination_bookings', ?, ?, ?, ?, NOW())
        ");
        $action_desc = "Deleted booking #$booking_id for child: $child_name";
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '::1';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $log_query->bind_param("iisss", $user_id, $booking_id, $action_desc, $ip_address, $user_agent);
        
        if (!$log_query->execute()) {
            throw new Exception("Failed to log deletion");
        }
        
        // Commit transaction
        $conn->commit();
        
        redirect('bookingdetail.php', 'Booking deleted successfully!', 'success');
        
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        redirect('bookingdetail.php', 'Error: ' . $e->getMessage(), 'error');
    }
    
} else {
    redirect('bookingdetail.php', 'Invalid request', 'error');
}

$conn->close();
?>