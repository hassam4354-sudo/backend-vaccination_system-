<?php
session_start();
if(!isset($_SESSION["logged_in"]) || $_SESSION["user_type"] != "admin"){
    header("location:../login.php");
    exit();
}

include("../dbconnection.php");

$hospital_id = isset($_GET['id']) ? $_GET['id'] : '';

if(empty($hospital_id)) {
    header("location:manage_hospitals.php");
    exit();
}

// Get hospital details with verification documents
$query_hospital = "SELECT h.*, u.email, u.phone, u.is_active as user_active,
                   ud.documents_uploaded, ud.registration_certificate, ud.license_file,
                   ud.owner_id_proof, ud.other_documents,
                   (SELECT COUNT(*) FROM vaccination_bookings WHERE hospital_id = h.hospital_id) as total_bookings,
                   (SELECT COUNT(*) FROM doctors WHERE hospital_id = h.hospital_id AND is_active = 1) as doctor_count
                   FROM hospitals h
                   JOIN users u ON h.user_id = u.user_id
                   LEFT JOIN user_documents ud ON u.user_id = ud.user_id
                   WHERE h.hospital_id = '$hospital_id'";
$result_hospital = mysqli_query($connection, $query_hospital);
$hospital_data = mysqli_fetch_assoc($result_hospital);

if(!$hospital_data) {
    header("location:manage_hospitals.php");
    exit();
}

// Get admin details
$user_id = $_SESSION["user_id"];
$query_admin = "SELECT admin_id, full_name FROM admins WHERE user_id = '$user_id'";
$result_admin = mysqli_query($connection, $query_admin);
$admin_data = mysqli_fetch_assoc($result_admin);
$admin_id = $admin_data['admin_id'];
$admin_name = $admin_data['full_name'];

// Check if already verified
if($hospital_data['is_verified'] == 1) {
    echo "<script>alert('This hospital is already verified.')
    window.location.href = 'manage_hospitals.php'
    </script>";
    exit();
}

// Process form submission
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $verification_result = isset($_POST['verification_result']) ? mysqli_real_escape_string($connection, $_POST['verification_result']) : '';
    $verification_notes = isset($_POST['verification_notes']) ? mysqli_real_escape_string($connection, $_POST['verification_notes']) : '';
    $send_notification = isset($_POST['send_notification']) ? 1 : 0;
    
    if($verification_result === 'approve') {
        // Update hospital verification status
        $query = "UPDATE hospitals SET is_verified = 1, verified_at = NOW(), verified_by = '$admin_id' WHERE hospital_id = '$hospital_id'";
        $run = mysqli_query($connection, $query);
        
        if($run) {
            // Log the action
            $log_description = "Verified hospital: {$hospital_data['hospital_name']}. Notes: $verification_notes";
            $log_query = "INSERT INTO audit_logs (user_id, action_type, action_description, ip_address, created_at)
                         VALUES ('$user_id', 'verify_hospital', '$log_description', '{$_SERVER['REMOTE_ADDR']}', NOW())";
            mysqli_query($connection, $log_query);
            
            // Add to verification history
            $history_query = "INSERT INTO verification_history (hospital_id, verified_by, verification_notes, created_at)
                             VALUES ('$hospital_id', '$admin_id', '$verification_notes', NOW())";
            mysqli_query($connection, $history_query);
            
            if($send_notification) {
                // Send notification (simulated)
                $to = $hospital_data['email'];
                $subject = "Hospital Verification Approved - {$hospital_data['hospital_name']}";
                $message = "Dear Hospital Administrator,\n\n";
                $message .= "We are pleased to inform you that your hospital verification has been approved!\n\n";
                $message .= "Hospital: {$hospital_data['hospital_name']}\n";
                $message .= "Registration No: {$hospital_data['registration_number']}\n";
                $message .= "Verification Date: " . date('d M Y') . "\n\n";
                if(!empty($verification_notes)) {
                    $message .= "Admin Notes: $verification_notes\n\n";
                }
                $message .= "You can now:\n";
                $message .= "- Manage vaccination appointments\n";
                $message .= "- Add doctors to your hospital\n";
                $message .= "- View booking schedules\n";
                $message .= "- Generate vaccination reports\n\n";
                $message .= "Best regards,\nVaccination Management System";
                
                // In real implementation: mail($to, $subject, $message);
            }
            
            // Show success page
            echo generateSuccessPage($hospital_data, $admin_name, $verification_notes);
            exit();
        } else {
            $error = mysqli_error($connection);
        }
    } elseif($verification_result === 'reject') {
        $reject_reason = isset($_POST['reject_reason']) ? mysqli_real_escape_string($connection, $_POST['reject_reason']) : '';
        
        // Log rejection (but don't verify)
        $log_description = "Rejected hospital verification: {$hospital_data['hospital_name']}. Reason: $reject_reason. Notes: $verification_notes";
        $log_query = "INSERT INTO audit_logs (user_id, action_type, action_description, ip_address, created_at)
                     VALUES ('$user_id', 'reject_verification', '$log_description', '{$_SERVER['REMOTE_ADDR']}', NOW())";
        mysqli_query($connection, $log_query);
        
        if($send_notification) {
            // Send rejection notification
            $to = $hospital_data['email'];
            $subject = "Hospital Verification Update - {$hospital_data['hospital_name']}";
            $message = "Dear Hospital Administrator,\n\n";
            $message .= "Your hospital verification requires additional information.\n\n";
            $message .= "Reason: $reject_reason\n";
            if(!empty($verification_notes)) {
                $message .= "Additional Notes: $verification_notes\n\n";
            }
            $message .= "Please update your documents and submit again for verification.\n\n";
            $message .= "Best regards,\nVaccination Management System";
        }
        
        // Redirect with message
        echo "<script>alert('Verification rejected. Hospital owner has been notified.')
        window.location.href = 'manage_hospitals.php'
        </script>";
        exit();
    }
}

function generateSuccessPage($hospital_data, $admin_name, $notes) {
    return '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Hospital Verified - Admin Panel</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; font-family: "Segoe UI", sans-serif; }
            body {
                background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .success-container {
                background: white;
                border-radius: 20px;
                padding: 50px;
                max-width: 700px;
                width: 100%;
                text-align: center;
                box-shadow: 0 20px 60px rgba(0,0,0,0.2);
                animation: fadeIn 0.6s ease-out;
            }
            .success-icon {
                width: 100px;
                height: 100px;
                background: linear-gradient(135deg, #10b981, #059669);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 30px;
                color: white;
                font-size: 48px;
                animation: bounceIn 1s ease-out;
            }
            h1 { color: #1f2937; margin-bottom: 15px; font-size: 2.5rem; }
            p { color: #6b7280; font-size: 18px; line-height: 1.6; margin-bottom: 25px; }
            .verification-badge {
                display: inline-flex;
                align-items: center;
                gap: 10px;
                background: rgba(16, 185, 129, 0.1);
                color: #065f46;
                padding: 12px 25px;
                border-radius: 30px;
                font-weight: 600;
                margin: 20px 0;
            }
            .info-box {
                background: #f0fdf4;
                border: 2px solid #bbf7d0;
                padding: 25px;
                border-radius: 12px;
                margin: 25px 0;
                text-align: left;
            }
            .info-box h3 { color: #065f46; margin-bottom: 15px; }
            .next-steps {
                background: #f8fafc;
                border-radius: 12px;
                padding: 20px;
                margin: 25px 0;
                text-align: left;
            }
            .next-steps h4 { color: #1f2937; margin-bottom: 10px; }
            .next-steps ul { list-style: none; padding-left: 0; }
            .next-steps li { padding: 8px 0; color: #4b5563; display: flex; align-items: center; gap: 10px; }
            .actions { display: flex; gap: 15px; margin-top: 30px; }
            .btn {
                padding: 15px 30px;
                border: none;
                border-radius: 12px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s;
                flex: 1;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
            }
            .btn-primary {
                background: #1f2937;
                color: white;
            }
            .btn-secondary {
                background: #f3f4f6;
                color: #6b7280;
            }
            .btn:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
            .progress-bar {
                height: 4px;
                background: #e5e7eb;
                border-radius: 2px;
                margin-top: 30px;
                overflow: hidden;
            }
            .progress {
                height: 100%;
                background: #10b981;
                width: 0%;
                animation: progress 3s linear forwards;
                border-radius: 2px;
            }
            @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
            @keyframes bounceIn {
                0% { transform: scale(0.3); opacity: 0; }
                50% { transform: scale(1.05); }
                70% { transform: scale(0.9); }
                100% { transform: scale(1); opacity: 1; }
            }
            @keyframes progress {
                0% { width: 0%; }
                100% { width: 100%; }
            }
        </style>
    </head>
    <body>
        <div class="success-container">
            <div class="success-icon">
                <i class="fas fa-check-double"></i>
            </div>
            <h1>Hospital Verified!</h1>
            <p>The hospital has been successfully verified and activated in the system.</p>
            
            <div class="verification-badge">
                <i class="fas fa-shield-check"></i>
                VERIFIED HOSPITAL
            </div>
            
            <div class="info-box">
                <h3>Verification Details</h3>
                <p><strong>Hospital:</strong> '.htmlspecialchars($hospital_data['hospital_name']).'</p>
                <p><strong>Registration No:</strong> '.htmlspecialchars($hospital_data['registration_number']).'</p>
                <p><strong>Verified By:</strong> '.htmlspecialchars($admin_name).'</p>
                <p><strong>Date:</strong> '.date('d M Y, h:i A').'</p>
                '.($notes ? '<p><strong>Notes:</strong> '.htmlspecialchars($notes).'</p>' : '').'
            </div>
            
            <div class="next-steps">
                <h4><i class="fas fa-list-check"></i> What Happens Next:</h4>
                <ul>
                    <li><i class="fas fa-check-circle" style="color: #10b981;"></i> Hospital is now visible in search results</li>
                    <li><i class="fas fa-check-circle" style="color: #10b981;"></i> Can receive vaccination appointment requests</li>
                    <li><i class="fas fa-check-circle" style="color: #10b981;"></i> Hospital admin can manage bookings</li>
                    <li><i class="fas fa-check-circle" style="color: #10b981;"></i> Added to active hospital network</li>
                </ul>
            </div>
            
            <div class="actions">
                <a href="manage_hospitals.php" class="btn btn-primary">
                    <i class="fas fa-hospital"></i> Back to Hospitals
                </a>
                <a href="hospital_details.php?id='.$hospital_data['hospital_id'].'" class="btn btn-secondary">
                    <i class="fas fa-eye"></i> View Hospital Details
                </a>
            </div>
            
            <div class="progress-bar">
                <div class="progress"></div>
            </div>
            <p style="color: #9ca3af; font-size: 14px; margin-top: 10px;">
                Redirecting to hospitals page...
            </p>
        </div>
        
        <script>
            setTimeout(() => {
                window.location.href = "manage_hospitals.php";
            }, 3000);
        </script>
    </body>
    </html>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Hospital - Admin Panel</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        :root {
            --primary: #10b981;
            --primary-dark: #059669;
            --warning: #f59e0b;
            --danger: #ef4444;
            --light: #f8f9fa;
            --dark: #1f2937;
            --gray: #6b7280;
            --border-radius: 16px;
            --box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }
        
        body {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .verification-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        /* Navbar */
        .verification-navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius);
            padding: 20px 30px;
            margin-bottom: 30px;
            box-shadow: var(--box-shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            animation: slideInDown 0.5s ease-out;
        }
        
        .verification-navbar h1 {
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 1.8rem;
        }
        
        .verification-navbar h1 i {
            color: var(--primary);
        }
        
        .nav-actions {
            display: flex;
            gap: 15px;
        }
        
        .nav-btn {
            padding: 10px 20px;
            background: #f3f4f6;
            border: none;
            border-radius: 8px;
            color: var(--dark);
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .nav-btn:hover {
            background: var(--primary);
            color: white;
        }
        
        /* Main Content */
        .verification-content {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 30px;
        }
        
        @media (max-width: 1024px) {
            .verification-content {
                grid-template-columns: 1fr;
            }
        }
        
        /* Left Panel - Hospital Details */
        .hospital-details-panel {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            animation: fadeIn 0.6s ease-out;
        }
        
        .panel-header {
            background: linear-gradient(90deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 25px 30px;
        }
        
        .panel-header h2 {
            font-size: 1.8rem;
            margin-bottom: 10px;
        }
        
        .panel-header p {
            opacity: 0.9;
        }
        
        .hospital-info {
            padding: 30px;
        }
        
        .info-section {
            margin-bottom: 30px;
        }
        
        .info-section h3 {
            color: var(--dark);
            margin-bottom: 20px;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f3f4f6;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .info-card {
            background: #f8fafc;
            padding: 20px;
            border-radius: 12px;
            transition: var(--transition);
        }
        
        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .info-card h4 {
            color: var(--primary);
            margin-bottom: 15px;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-item {
            margin-bottom: 12px;
        }
        
        .info-label {
            color: var(--gray);
            font-size: 14px;
            display: block;
            margin-bottom: 5px;
        }
        
        .info-value {
            color: var(--dark);
            font-weight: 600;
            font-size: 15px;
        }
        
        /* Documents Section */
        .documents-section {
            margin-top: 30px;
        }
        
        .documents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .document-card {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: var(--transition);
            cursor: pointer;
        }
        
        .document-card:hover {
            border-color: var(--primary);
            transform: translateY(-5px);
        }
        
        .document-icon {
            width: 60px;
            height: 60px;
            background: rgba(16, 185, 129, 0.1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            color: var(--primary);
            font-size: 24px;
        }
        
        .document-card h4 {
            color: var(--dark);
            margin-bottom: 10px;
        }
        
        .document-status {
            font-size: 12px;
            padding: 4px 12px;
            border-radius: 20px;
            display: inline-block;
        }
        
        .status-uploaded {
            background: rgba(16, 185, 129, 0.1);
            color: var(--primary);
        }
        
        .status-pending {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }
        
        /* Right Panel - Verification Form */
        .verification-panel {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            padding: 30px;
            position: sticky;
            top: 20px;
            animation: slideInUp 0.6s ease-out 0.3s both;
        }
        
        .verification-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .verification-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 32px;
        }
        
        .verification-header h2 {
            color: var(--dark);
            margin-bottom: 10px;
        }
        
        .verification-header p {
            color: var(--gray);
            font-size: 15px;
        }
        
        /* Verification Form */
        .verification-form {
            margin-top: 30px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            color: var(--dark);
            font-weight: 600;
            margin-bottom: 12px;
            font-size: 1rem;
        }
        
        .verification-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 25px;
        }
        
        @media (max-width: 480px) {
            .verification-options {
                grid-template-columns: 1fr;
            }
        }
        
        .verification-option {
            padding: 20px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
        }
        
        .verification-option:hover {
            transform: translateY(-5px);
        }
        
        .verification-option.selected {
            border-color: var(--primary);
            background: rgba(16, 185, 129, 0.05);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }
        
        .option-approve {
            border-color: rgba(16, 185, 129, 0.3);
        }
        
        .option-reject {
            border-color: rgba(239, 68, 68, 0.3);
        }
        
        .verification-option i {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .option-approve i {
            color: var(--primary);
        }
        
        .option-reject i {
            color: var(--danger);
        }
        
        .verification-option h4 {
            color: var(--dark);
            margin-bottom: 8px;
            font-size: 1.1rem;
        }
        
        .verification-option p {
            color: var(--gray);
            font-size: 13px;
        }
        
        .reject-reason {
            display: none;
            margin-top: 20px;
            padding: 20px;
            background: #fef2f2;
            border-radius: 12px;
            border-left: 4px solid var(--danger);
        }
        
        .reject-reason select {
            width: 100%;
            padding: 12px;
            border: 2px solid #fecaca;
            border-radius: 8px;
            background: white;
            color: var(--dark);
            font-weight: 500;
            cursor: pointer;
        }
        
        textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 15px;
            resize: vertical;
            min-height: 100px;
            transition: var(--transition);
        }
        
        textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .checkbox-group input {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .checkbox-group label {
            color: var(--dark);
            cursor: pointer;
            margin-bottom: 0;
            font-weight: normal;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn {
            padding: 15px 25px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            flex: 1;
        }
        
        .btn-verify {
            background: linear-gradient(90deg, var(--primary), var(--primary-dark));
            color: white;
            box-shadow: 0 5px 20px rgba(16, 185, 129, 0.3);
        }
        
        .btn-verify:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(16, 185, 129, 0.4);
        }
        
        .btn-cancel {
            background: white;
            color: var(--gray);
            border: 2px solid #e9ecef;
        }
        
        .btn-cancel:hover {
            background: #f8f9fa;
            border-color: var(--gray);
        }
        
        /* Stats Overview */
        .stats-overview {
            background: #f8fafc;
            border-radius: var(--border-radius);
            padding: 25px;
            margin-top: 30px;
        }
        
        .stats-overview h3 {
            color: var(--dark);
            margin-bottom: 20px;
            font-size: 1.2rem;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .stat-item-small {
            text-align: center;
            padding: 15px;
            background: white;
            border-radius: 10px;
            border: 1px solid #e5e7eb;
        }
        
        .stat-number-small {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 5px;
        }
        
        .stat-label-small {
            font-size: 12px;
            color: var(--gray);
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideInDown {
            from { 
                opacity: 0; 
                transform: translateY(-30px); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0); 
            }
        }
        
        @keyframes slideInUp {
            from { 
                opacity: 0; 
                transform: translateY(30px); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0); 
            }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .verification-navbar {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
            
            .nav-actions {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .hospital-info {
                padding: 20px;
            }
            
            .verification-panel {
                padding: 20px;
                position: static;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
    
    <script>
        // Toggle verification option
        let selectedVerification = 'approve';
        
        function selectVerification(option) {
            selectedVerification = option;
            document.querySelectorAll('.verification-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');
            document.getElementById('verification_result').value = option;
            
            // Show/hide reject reason
            const rejectReason = document.getElementById('rejectReason');
            if(option === 'reject') {
                rejectReason.style.display = 'block';
            } else {
                rejectReason.style.display = 'none';
            }
        }
        
        // Validate form
        function validateForm() {
            if(!selectedVerification) {
                alert('Please select whether to approve or reject the verification.');
                return false;
            }
            
            if(selectedVerification === 'reject') {
                const rejectReason = document.getElementById('reject_reason').value;
                if(!rejectReason) {
                    alert('Please select a reason for rejection.');
                    return false;
                }
            }
            
            // Show loading state
            const verifyBtn = document.querySelector('.btn-verify');
            const originalHTML = verifyBtn.innerHTML;
            verifyBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            verifyBtn.disabled = true;
            
            return true;
        }
        
        // View document
        function viewDocument(documentType) {
            alert('Document viewing functionality would open in a new window or modal.\nDocument Type: ' + documentType);
            // In real implementation, you would open the document in a modal or new tab
        }
        
        // Initialize on load
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-select approve option
            selectVerification('approve');
            
            // Add animation to info cards
            const cards = document.querySelectorAll('.info-card');
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });
        });
    </script>
</head>
<body>
    <div class="verification-container">
        <!-- Navbar -->
        <nav class="verification-navbar">
            <h1>
                <i class="fas fa-shield-check"></i>
                Hospital Verification
            </h1>
            <div class="nav-actions">
                <a href="manage_hospitals.php" class="nav-btn">
                    <i class="fas fa-arrow-left"></i> Back to Hospitals
                </a>
                <a href="hospital_details.php?id=<?php echo $hospital_id; ?>" class="nav-btn">
                    <i class="fas fa-eye"></i> View Details
                </a>
            </div>
        </nav>
        
        <!-- Main Content -->
        <div class="verification-content">
            <!-- Left Panel - Hospital Details -->
            <div class="hospital-details-panel">
                <div class="panel-header">
                    <h2><?php echo htmlspecialchars($hospital_data['hospital_name']); ?></h2>
                    <p>Registration No: <?php echo htmlspecialchars($hospital_data['registration_number']); ?></p>
                </div>
                
                <div class="hospital-info">
                    <!-- Basic Information -->
                    <div class="info-section">
                        <h3><i class="fas fa-info-circle"></i> Basic Information</h3>
                        <div class="info-grid">
                            <div class="info-card">
                                <h4><i class="fas fa-hospital"></i> Hospital Details</h4>
                                <div class="info-item">
                                    <span class="info-label">Hospital Name</span>
                                    <span class="info-value"><?php echo htmlspecialchars($hospital_data['hospital_name']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Registration Number</span>
                                    <span class="info-value"><?php echo htmlspecialchars($hospital_data['registration_number']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Type</span>
                                    <span class="info-value"><?php echo $hospital_data['hospital_type'] ? htmlspecialchars($hospital_data['hospital_type']) : 'General Hospital'; ?></span>
                                </div>
                            </div>
                            
                            <div class="info-card">
                                <h4><i class="fas fa-map-marker-alt"></i> Location</h4>
                                <div class="info-item">
                                    <span class="info-label">Address</span>
                                    <span class="info-value"><?php echo htmlspecialchars($hospital_data['address']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">City</span>
                                    <span class="info-value"><?php echo htmlspecialchars($hospital_data['city']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">State</span>
                                    <span class="info-value"><?php echo htmlspecialchars($hospital_data['state']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Pincode</span>
                                    <span class="info-value"><?php echo htmlspecialchars($hospital_data['pincode']); ?></span>
                                </div>
                            </div>
                            
                            <div class="info-card">
                                <h4><i class="fas fa-user-tie"></i> Contact Person</h4>
                                <div class="info-item">
                                    <span class="info-label">Contact Person</span>
                                    <span class="info-value"><?php echo htmlspecialchars($hospital_data['contact_person']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Designation</span>
                                    <span class="info-value"><?php echo htmlspecialchars($hospital_data['designation']); ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Phone</span>
                                    <span class="info-value"><?php echo $hospital_data['phone'] ? htmlspecialchars($hospital_data['phone']) : 'N/A'; ?></span>
                                </div>
                                <div class="info-item">
                                    <span class="info-label">Email</span>
                                    <span class="info-value"><?php echo htmlspecialchars($hospital_data['email']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Documents Section -->
                    <div class="info-section documents-section">
                        <h3><i class="fas fa-file-contract"></i> Verification Documents</h3>
                        <div class="documents-grid">
                            <div class="document-card" onclick="viewDocument('registration_certificate')">
                                <div class="document-icon">
                                    <i class="fas fa-file-certificate"></i>
                                </div>
                                <h4>Registration Certificate</h4>
                                <span class="document-status <?php echo $hospital_data['registration_certificate'] ? 'status-uploaded' : 'status-pending'; ?>">
                                    <?php echo $hospital_data['registration_certificate'] ? 'Uploaded' : 'Pending'; ?>
                                </span>
                            </div>
                            
                            <div class="document-card" onclick="viewDocument('license_file')">
                                <div class="document-icon">
                                    <i class="fas fa-id-card"></i>
                                </div>
                                <h4>Hospital License</h4>
                                <span class="document-status <?php echo $hospital_data['license_file'] ? 'status-uploaded' : 'status-pending'; ?>">
                                    <?php echo $hospital_data['license_file'] ? 'Uploaded' : 'Pending'; ?>
                                </span>
                            </div>
                            
                            <div class="document-card" onclick="viewDocument('owner_id_proof')">
                                <div class="document-icon">
                                    <i class="fas fa-user-check"></i>
                                </div>
                                <h4>Owner ID Proof</h4>
                                <span class="document-status <?php echo $hospital_data['owner_id_proof'] ? 'status-uploaded' : 'status-pending'; ?>">
                                    <?php echo $hospital_data['owner_id_proof'] ? 'Uploaded' : 'Pending'; ?>
                                </span>
                            </div>
                            
                            <div class="document-card" onclick="viewDocument('other_documents')">
                                <div class="document-icon">
                                    <i class="fas fa-folder-open"></i>
                                </div>
                                <h4>Other Documents</h4>
                                <span class="document-status <?php echo $hospital_data['other_documents'] ? 'status-uploaded' : 'status-pending'; ?>">
                                    <?php echo $hospital_data['other_documents'] ? 'Uploaded' : 'Pending'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Panel - Verification Form -->
            <div class="verification-panel">
                <div class="verification-header">
                    <div class="verification-icon">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <h2>Verification Review</h2>
                    <p>Review the hospital details and documents before verification</p>
                </div>
                
                <!-- Stats Overview -->
                <div class="stats-overview">
                    <h3>Hospital Overview</h3>
                    <div class="stats-grid">
                        <div class="stat-item-small">
                            <div class="stat-number-small"><?php echo $hospital_data['total_bookings']; ?></div>
                            <div class="stat-label-small">Total Bookings</div>
                        </div>
                        <div class="stat-item-small">
                            <div class="stat-number-small"><?php echo $hospital_data['doctor_count']; ?></div>
                            <div class="stat-label-small">Doctors</div>
                        </div>
                        <div class="stat-item-small">
                            <div class="stat-number-small">
                                <?php echo $hospital_data['documents_uploaded'] ? '4/4' : '0/4'; ?>
                            </div>
                            <div class="stat-label-small">Documents</div>
                        </div>
                        <div class="stat-item-small">
                            <div class="stat-number-small">
                                <?php echo $hospital_data['is_active'] ? 'Active' : 'Inactive'; ?>
                            </div>
                            <div class="stat-label-small">Status</div>
                        </div>
                    </div>
                </div>
                
                <!-- Verification Form -->
                <form method="POST" class="verification-form" onsubmit="return validateForm()">
                    <?php if(isset($error)): ?>
                    <div style="background: #fee2e2; color: #991b1b; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <i class="fas fa-exclamation-circle"></i> Error: <?php echo htmlspecialchars($error); ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label>Verification Decision</label>
                        <input type="hidden" id="verification_result" name="verification_result" value="approve">
                        
                        <div class="verification-options">
                            <div class="verification-option option-approve selected" onclick="selectVerification('approve')">
                                <i class="fas fa-check-circle"></i>
                                <h4>Approve Verification</h4>
                                <p>Verify and activate this hospital</p>
                            </div>
                            
                            <div class="verification-option option-reject" onclick="selectVerification('reject')">
                                <i class="fas fa-times-circle"></i>
                                <h4>Reject & Request More Info</h4>
                                <p>Request additional documents</p>
                            </div>
                        </div>
                        
                        <!-- Reject Reason (Hidden by default) -->
                        <div id="rejectReason" class="reject-reason" style="display: none;">
                            <label style="color: var(--danger); margin-bottom: 10px; display: block;">Reason for Rejection</label>
                            <select id="reject_reason" name="reject_reason">
                                <option value="">Select a reason</option>
                                <option value="Incomplete Documents">Incomplete Documents</option>
                                <option value="Invalid Registration Certificate">Invalid Registration Certificate</option>
                                <option value="License Expired">License Expired</option>
                                <option value="ID Proof Not Clear">ID Proof Not Clear</option>
                                <option value="Other Issues">Other Issues</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Verification Notes (Optional)</label>
                        <textarea name="verification_notes" placeholder="Add any notes about the verification..."></textarea>
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" id="send_notification" name="send_notification" checked>
                        <label for="send_notification">Send notification email to hospital</label>
                    </div>
                    
                    <div class="action-buttons">
                        <button type="submit" class="btn btn-verify">
                            <i class="fas fa-shield-check"></i>
                            Complete Verification
                        </button>
                        <button type="button" class="btn btn-cancel" onclick="window.location.href='manage_hospitals.php'">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
<?php mysqli_close($connection); ?>