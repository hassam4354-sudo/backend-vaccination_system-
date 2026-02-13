<?php
require_once 'db_connect.php';
checkAdminLogin();

$admin_details = getAdminDetails($conn);
$admin_id = $admin_details['admin_id'] ?? 1;
$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] == 'vaccinate') {
        
        $booking_id = sanitize($conn, $_POST['booking_id']);
        $batch_number = sanitize($conn, $_POST['batch_number']);
        $administered_by = sanitize($conn, $_POST['administered_by']);
        $side_effects = isset($_POST['side_effects']) ? sanitize($conn, $_POST['side_effects']) : null;
        $notes = isset($_POST['notes']) ? sanitize($conn, $_POST['notes']) : null;
        
        // Validate required fields
        if (empty($booking_id) || empty($batch_number) || empty($administered_by)) {
            redirect('bookingdetail.php', 'Please fill in all required fields', 'error');
        }
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Get booking details
            $get_booking = $conn->prepare("
                SELECT vb.child_id, vb.vaccine_id, vb.dose_number, vb.hospital_id, 
                       ar.request_id, vb.booking_id
                FROM vaccination_bookings vb
                INNER JOIN appointment_requests ar ON vb.request_id = ar.request_id
                WHERE vb.booking_id = ?
            ");
            $get_booking->bind_param("i", $booking_id);
            $get_booking->execute();
            $booking_result = $get_booking->get_result();
            
            if ($booking_result->num_rows == 0) {
                throw new Exception("Booking not found");
            }
            
            $booking = $booking_result->fetch_assoc();
            
            // Check if already vaccinated
            $check_vaccinated = $conn->prepare("
                SELECT record_id FROM vaccination_records WHERE booking_id = ?
            ");
            $check_vaccinated->bind_param("i", $booking_id);
            $check_vaccinated->execute();
            $check_result = $check_vaccinated->get_result();
            
            if ($check_result->num_rows > 0) {
                throw new Exception("This booking has already been marked as vaccinated");
            }
            
            // Update booking status
            $update_booking = $conn->prepare("
                UPDATE vaccination_bookings 
                SET booking_status = 'completed', updated_at = NOW()
                WHERE booking_id = ?
            ");
            $update_booking->bind_param("i", $booking_id);
            if (!$update_booking->execute()) {
                throw new Exception("Failed to update booking status");
            }
            
            // Create vaccination record
            $insert_record = $conn->prepare("
                INSERT INTO vaccination_records (
                    booking_id, child_id, vaccine_id, dose_number, hospital_id,
                    vaccination_date, vaccination_time, batch_number, administered_by,
                    side_effects, notes, vaccination_status, created_at
                ) VALUES (?, ?, ?, ?, ?, CURDATE(), CURTIME(), ?, ?, ?, ?, 'completed', NOW())
            ");
            $insert_record->bind_param(
                "iiiiisssss",
                $booking_id,
                $booking['child_id'],
                $booking['vaccine_id'],
                $booking['dose_number'],
                $booking['hospital_id'],
                $batch_number,
                $administered_by,
                $side_effects,
                $notes
            );
            
            if (!$insert_record->execute()) {
                throw new Exception("Failed to create vaccination record: " . $conn->error);
            }
            
            // Update inventory if batch exists
            $check_inventory = $conn->prepare("
                SELECT inventory_id FROM hospital_vaccine_inventory 
                WHERE hospital_id = ? AND vaccine_id = ? AND batch_number = ?
            ");
            $check_inventory->bind_param("iis", 
                $booking['hospital_id'], 
                $booking['vaccine_id'], 
                $batch_number
            );
            $check_inventory->execute();
            $inventory_result = $check_inventory->get_result();
            
            if ($inventory_result->num_rows > 0) {
                $update_inventory = $conn->prepare("
                    UPDATE hospital_vaccine_inventory 
                    SET quantity_available = quantity_available - 1,
                        updated_at = NOW()
                    WHERE hospital_id = ? AND vaccine_id = ? AND batch_number = ?
                ");
                $update_inventory->bind_param("iis", 
                    $booking['hospital_id'], 
                    $booking['vaccine_id'], 
                    $batch_number
                );
                $update_inventory->execute();
            }
            
            // Log the action
            $log_query = $conn->prepare("
                INSERT INTO audit_logs (user_id, action_type, table_name, record_id, action_description, ip_address, user_agent, created_at)
                VALUES (?, 'UPDATE', 'vaccination_bookings', ?, ?, ?, ?, NOW())
            ");
            $action_desc = "Marked booking #$booking_id as vaccinated. Batch: $batch_number";
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '::1';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $log_query->bind_param("iisss", $user_id, $booking_id, $action_desc, $ip_address, $user_agent);
            $log_query->execute();
            
            // Create notification for parent
            $get_parent = $conn->prepare("
                SELECT p.parent_id, u.user_id, p.full_name, c.full_name as child_name
                FROM vaccination_bookings vb
                INNER JOIN children c ON vb.child_id = c.child_id
                INNER JOIN parents p ON c.parent_id = p.parent_id
                INNER JOIN users u ON p.user_id = u.user_id
                WHERE vb.booking_id = ?
            ");
            $get_parent->bind_param("i", $booking_id);
            $get_parent->execute();
            $parent_result = $get_parent->get_result();
            
            if ($parent_result->num_rows > 0) {
                $parent = $parent_result->fetch_assoc();
                
                // Check if notifications table exists and insert
                $check_table = $conn->query("SHOW TABLES LIKE 'notifications'");
                if ($check_table->num_rows > 0) {
                    $notification = $conn->prepare("
                        INSERT INTO notifications (user_id, notification_type, title, message, related_id, created_at)
                        VALUES (?, 'vaccination_completed', ?, ?, ?, NOW())
                    ");
                    $title = "Vaccination Completed";
                    $message = "Your child {$parent['child_name']} has successfully received the vaccination.";
                    $notification->bind_param("issi", $parent['user_id'], $title, $message, $booking_id);
                    $notification->execute();
                }
            }
            
            // Commit transaction
            $conn->commit();
            
            redirect('bookingdetail.php', 'Vaccination record created successfully!', 'success');
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            redirect('bookingdetail.php', 'Error: ' . $e->getMessage(), 'error');
        }
        
    } elseif ($_POST['action'] == 'cancel') {
        // Cancel booking
        $booking_id = sanitize($conn, $_POST['booking_id']);
        $cancel_reason = sanitize($conn, $_POST['cancel_reason'] ?? 'Cancelled by admin');
        
        $conn->begin_transaction();
        
        try {
            $update = $conn->prepare("
                UPDATE vaccination_bookings 
                SET booking_status = 'cancelled', updated_at = NOW()
                WHERE booking_id = ?
            ");
            $update->bind_param("i", $booking_id);
            
            if (!$update->execute()) {
                throw new Exception("Failed to cancel booking");
            }
            
            // Log the action
            $log_query = $conn->prepare("
                INSERT INTO audit_logs (user_id, action_type, table_name, record_id, action_description, ip_address, user_agent, created_at)
                VALUES (?, 'CANCEL', 'vaccination_bookings', ?, ?, ?, ?, NOW())
            ");
            $action_desc = "Cancelled booking #$booking_id. Reason: $cancel_reason";
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? '::1';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $log_query->bind_param("iisss", $user_id, $booking_id, $action_desc, $ip_address, $user_agent);
            $log_query->execute();
            
            $conn->commit();
            
            redirect('bookingdetail.php', 'Booking cancelled successfully!', 'success');
            
        } catch (Exception $e) {
            $conn->rollback();
            redirect('bookingdetail.php', 'Error: ' . $e->getMessage(), 'error');
        }
    }
    
} else {
    redirect('bookingdetail.php', 'Invalid request', 'error');
}

$conn->close();
?>