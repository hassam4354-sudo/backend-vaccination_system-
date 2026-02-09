<?php
session_start();

// Only allow admin users
if(!isset($_SESSION["logged_in"]) || $_SESSION["user_type"] != "admin"){
    header("location:../login.php");
    exit();
}

include("../dbconnection.php");

$hospital_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if($hospital_id <= 0) {
    die("Invalid hospital ID.");
}

// Fetch hospital data
$query_hospital = "SELECT h.*, u.email, u.phone, u.is_active AS user_active,
                   (SELECT COUNT(*) FROM vaccination_bookings WHERE hospital_id = h.hospital_id) AS total_bookings
                   FROM hospitals h
                   JOIN users u ON h.user_id = u.user_id
                   WHERE h.hospital_id = $hospital_id";

$result_hospital = mysqli_query($connection, $query_hospital);

if(!$result_hospital){
    die("Hospital fetch failed: " . mysqli_error($connection) . "<br>Query: $query_hospital");
}

$hospital_data = mysqli_fetch_assoc($result_hospital);

if(!$hospital_data) {
    die("Hospital not found.");
}

// Debug: Show hospital data before update
echo "<pre>Hospital data before update:\n";
print_r($hospital_data);
echo "</pre>";

// Get admin details
$user_id = intval($_SESSION["user_id"]);
$query_admin = "SELECT admin_id, full_name FROM admins WHERE user_id = $user_id";
$result_admin = mysqli_query($connection, $query_admin);
$admin_data = mysqli_fetch_assoc($result_admin);
$admin_name = $admin_data['full_name'] ?? 'Admin';

// Check if already verified
if($hospital_data['is_verified'] == 1) {
    die("This hospital is already verified.");
}

// Process form submission
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $verification_result = $_POST['verification_result'] ?? '';
    $verification_notes = mysqli_real_escape_string($connection, $_POST['verification_notes'] ?? '');
    $send_notification = isset($_POST['send_notification']) ? 1 : 0;

    if($verification_result === 'approve') {

        // Force hospital_id as integer for safety
        $hospital_id_int = intval($hospital_id);

        // UPDATE hospital
        $query_update = "UPDATE hospitals 
                         SET is_verified = 1, updated_at = NOW() 
                         WHERE hospital_id = $hospital_id_int";

        $run = mysqli_query($connection, $query_update);

        // Debug: Show query and errors
        echo "<pre>Update query: $query_update\n";
        if(!$run){
            echo "Update failed: " . mysqli_error($connection);
        } else {
            echo "Update executed successfully.\n";
        }

        // Debug: Show affected rows
        $affected = mysqli_affected_rows($connection);
        echo "Rows affected by update: $affected\n";

        // Debug: Check hospital data after update
        $check = mysqli_query($connection, "SELECT * FROM hospitals WHERE hospital_id = $hospital_id_int");
        $updated_hospital = mysqli_fetch_assoc($check);
        echo "Hospital data after update:\n";
        print_r($updated_hospital);
        echo "</pre>";

        // If update affected 0 rows, warn
        if($affected === 0){
            die("Warning: No rows were updated. Possible reasons: hospital_id mismatch, same value already set, or DB issue.");
        }

        // Insert audit log
        $log_description = "Verified hospital: {$hospital_data['hospital_name']}. Notes: $verification_notes";
        $log_query = "INSERT INTO audit_logs 
                      (user_id, action_type, table_name, record_id, action_description, ip_address, user_agent, created_at)
                      VALUES 
                      ($user_id, 'VERIFY_HOSPITAL', 'hospitals', $hospital_id_int, '$log_description',
                       '{$_SERVER['REMOTE_ADDR']}', '{$_SERVER['HTTP_USER_AGENT']}', NOW())";
        mysqli_query($connection, $log_query);

        echo "<h2>Hospital Verified Successfully</h2>";
        exit();

    } elseif($verification_result === 'reject') {
        $reject_reason = mysqli_real_escape_string($connection, $_POST['reject_reason'] ?? '');
        die("Reject flow debug not implemented here yet.");
    }
}

// Display verification form
?>
<!DOCTYPE html>
<html>
<head>
    <title>Verify Hospital</title>
</head>
<body>
    <h1>Verify Hospital</h1>
    <h2>Hospital Details</h2>
    <p><strong>Name:</strong> <?php echo htmlspecialchars($hospital_data['hospital_name']); ?></p>
    <p><strong>Registration Number:</strong> <?php echo htmlspecialchars($hospital_data['registration_number']); ?></p>
    <p><strong>Address:</strong> <?php echo htmlspecialchars($hospital_data['address']); ?></p>
    <p><strong>City:</strong> <?php echo htmlspecialchars($hospital_data['city']); ?></p>
    <p><strong>Contact Person:</strong> <?php echo htmlspecialchars($hospital_data['contact_person']); ?></p>
    <p><strong>Email:</strong> <?php echo htmlspecialchars($hospital_data['email']); ?></p>
    <p><strong>Phone:</strong> <?php echo htmlspecialchars($hospital_data['phone']); ?></p>
    <p><strong>Total Bookings:</strong> <?php echo $hospital_data['total_bookings']; ?></p>
    
    <h2>Verification Action</h2>
    <form method="POST" action="">
        <div>
            <label>Verification Result:</label><br>
            <input type="radio" name="verification_result" value="approve" id="approve" required>
            <label for="approve">Approve</label><br>
            <input type="radio" name="verification_result" value="reject" id="reject">
            <label for="reject">Reject</label>
        </div>
        
        <div>
            <label for="verification_notes">Verification Notes:</label><br>
            <textarea name="verification_notes" id="verification_notes" rows="4" cols="50"></textarea>
        </div>
        
        <div>
            <input type="checkbox" name="send_notification" id="send_notification" value="1">
            <label for="send_notification">Send notification to hospital</label>
        </div>
        
        <button type="submit">Submit Verification</button>
    </form>
</body>
</html>
<?php
mysqli_close($connection);
?>