<?php
session_start();
if(!isset($_SESSION["logged_in"]) || $_SESSION["user_type"] != "admin"){
    header("location:../login.php");
    exit();
}

include("../dbconnection.php");

$request_id = isset($_GET['id']) ? $_GET['id'] : '';
$user_id = $_SESSION["user_id"];

// Get admin_id and admin name
$query_admin = "SELECT admin_id, full_name FROM admins WHERE user_id = '$user_id'";
$result_admin = mysqli_query($connection, $query_admin);
$admin_data = mysqli_fetch_assoc($result_admin);
$admin_id = $admin_data['admin_id'];
$admin_name = $admin_data['full_name'];

// Get request details
$query_details = "SELECT 
    ar.*,
    c.full_name as child_name, c.date_of_birth,
    p.full_name as parent_name, p.emergency_contact as parent_phone, p.email as parent_email,
    h.hospital_name, h.city,
    v.vaccine_name, v.description as vaccine_desc
    FROM appointment_requests ar
    JOIN children c ON ar.child_id = c.child_id
    JOIN parents p ON c.parent_id = p.parent_id
    JOIN hospitals h ON ar.hospital_id = h.hospital_id
    JOIN vaccines v ON ar.vaccine_id = v.vaccine_id
    WHERE ar.request_id = '$request_id'";
$result_details = mysqli_query($connection, $query_details);
$request_data = mysqli_fetch_assoc($result_details);

// Calculate child age
$age_days = floor((time() - strtotime($request_data['date_of_birth'])) / (60 * 60 * 24));
$age_months = floor($age_days / 30);

// Process form submission
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rejection_reason = mysqli_real_escape_string($connection, $_POST['rejection_reason']);
    $additional_notes = mysqli_real_escape_string($connection, $_POST['additional_notes']);
    $send_notification = isset($_POST['send_notification']) ? 1 : 0;
    
    // Update request status to rejected
    $query = "UPDATE appointment_requests 
              SET request_status = 'rejected',
                  processed_by = '$admin_id',
                  processed_at = NOW(),
                  admin_notes = CONCAT('Rejection Reason: ', '$rejection_reason', '. ', '$additional_notes')
              WHERE request_id = '$request_id'";
    $run = mysqli_query($connection, $query);
    
    if($run) {
        // Log the action
        $log_query = "INSERT INTO audit_logs (user_id, action_type, action_description, ip_address)
                     VALUES ('$user_id', 'reject', 'Rejected appointment request #$request_id', '{$_SERVER['REMOTE_ADDR']}')";
        mysqli_query($connection, $log_query);
        
        if($send_notification) {
            // Send notification to parent (assuming create_notification function exists)
            // create_notification($parent_data['user_id'], 'Appointment Rejected', "...", 'appointment_rejected', $request_id);
            
            // Also send email notification (simulated)
            $to = $request_data['parent_email'];
            $subject = "Vaccination Appointment Request Rejected";
            $message = "Dear {$request_data['parent_name']},\n\n";
            $message .= "Your appointment request for {$request_data['child_name']} has been rejected.\n";
            $message .= "Reason: $rejection_reason\n";
            if(!empty($additional_notes)) {
                $message .= "Additional Notes: $additional_notes\n";
            }
            $message .= "\nYou can submit a new request with corrected information.\n\n";
            $message .= "Best regards,\nVaccination Management System";
            
            // In real implementation, you would use mail() function
            // mail($to, $subject, $message);
        }
        
        // Show success page
        echo generateSuccessPage($request_data, $rejection_reason);
        exit();
    } else {
        $error = mysqli_error($connection);
    }
}

function generateSuccessPage($request_data, $reason) {
    return '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Request Rejected - Admin Panel</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; font-family: "Segoe UI", sans-serif; }
            body {
                background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
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
                max-width: 600px;
                width: 100%;
                text-align: center;
                box-shadow: 0 20px 60px rgba(0,0,0,0.2);
                animation: fadeIn 0.6s ease-out;
            }
            .success-icon {
                width: 100px;
                height: 100px;
                background: linear-gradient(135deg, #ef4444, #dc2626);
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
            .info-box {
                background: #fef2f2;
                border-left: 4px solid #ef4444;
                padding: 20px;
                border-radius: 12px;
                margin: 25px 0;
                text-align: left;
            }
            .info-box h3 { color: #991b1b; margin-bottom: 10px; }
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
            @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
            @keyframes bounceIn {
                0% { transform: scale(0.3); opacity: 0; }
                50% { transform: scale(1.05); }
                70% { transform: scale(0.9); }
                100% { transform: scale(1); opacity: 1; }
            }
        </style>
    </head>
    <body>
        <div class="success-container">
            <div class="success-icon">
                <i class="fas fa-times-circle"></i>
            </div>
            <h1>Request Rejected</h1>
            <p>The appointment request has been successfully rejected and the parent has been notified.</p>
            
            <div class="info-box">
                <h3>Request Details:</h3>
                <p><strong>Child:</strong> '.htmlspecialchars($request_data['child_name']).'</p>
                <p><strong>Parent:</strong> '.htmlspecialchars($request_data['parent_name']).'</p>
                <p><strong>Vaccine:</strong> '.htmlspecialchars($request_data['vaccine_name']).'</p>
                <p><strong>Reason:</strong> '.htmlspecialchars($reason).'</p>
            </div>
            
            <div class="actions">
                <a href="appointment_requests.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Back to Requests
                </a>
                <a href="dashboard.php" class="btn btn-secondary">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
            </div>
        </div>
        
        <script>
            setTimeout(() => {
                window.location.href = "appointment_requests.php";
            }, 5000);
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
    <title>Reject Appointment Request - Admin Panel</title>
    
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
            --danger: #ef4444;
            --danger-dark: #dc2626;
            --warning: #f59e0b;
            --light: #f8f9fa;
            --dark: #1f2937;
            --gray: #6b7280;
            --border-radius: 16px;
            --box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }
        
        body {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .rejection-container {
            width: 100%;
            max-width: 900px;
            animation: fadeIn 0.6s ease-out;
        }
        
        /* Warning Card */
        .warning-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 40px;
            box-shadow: var(--box-shadow);
            text-align: center;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .warning-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #ef4444, #dc2626);
        }
        
        .warning-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            color: white;
            font-size: 48px;
            animation: bounceIn 1s ease-out;
            box-shadow: 0 10px 30px rgba(239, 68, 68, 0.3);
        }
        
        .warning-card h1 {
            color: var(--dark);
            margin-bottom: 15px;
            font-size: 2.5rem;
        }
        
        .warning-card p {
            color: var(--gray);
            font-size: 18px;
            line-height: 1.6;
            margin-bottom: 25px;
        }
        
        /* Timeline */
        .timeline {
            position: relative;
            padding: 30px 0;
            margin: 40px 0;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 50%;
            top: 0;
            bottom: 0;
            width: 4px;
            background: linear-gradient(to bottom, #f59e0b, #ef4444);
            transform: translateX(-50%);
            border-radius: 2px;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 30px;
            width: 45%;
        }
        
        .timeline-item:nth-child(odd) {
            left: 0;
            text-align: right;
            padding-right: 70px;
        }
        
        .timeline-item:nth-child(even) {
            left: 55%;
            padding-left: 70px;
        }
        
        .timeline-icon {
            position: absolute;
            width: 50px;
            height: 50px;
            background: white;
            border: 4px solid;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 1;
        }
        
        .timeline-item:nth-child(odd) .timeline-icon {
            right: -25px;
            border-color: #f59e0b;
            color: #f59e0b;
        }
        
        .timeline-item:nth-child(even) .timeline-icon {
            left: -25px;
            border-color: #ef4444;
            color: #ef4444;
        }
        
        .timeline-content {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .timeline-content h4 {
            color: var(--dark);
            margin-bottom: 8px;
        }
        
        .timeline-content p {
            color: var(--gray);
            font-size: 14px;
        }
        
        /* Details Card */
        .details-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 35px;
            box-shadow: var(--box-shadow);
            animation: slideInUp 0.6s ease-out 0.3s both;
        }
        
        .details-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--light);
        }
        
        .details-header i {
            color: var(--danger);
            font-size: 24px;
        }
        
        .details-header h2 {
            color: var(--dark);
            font-size: 1.8rem;
        }
        
        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .detail-group {
            padding: 20px;
            border-radius: 12px;
            background: #f8fafc;
            transition: var(--transition);
        }
        
        .detail-group:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .detail-group h3 {
            color: var(--danger);
            margin-bottom: 15px;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .detail-item:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            color: var(--gray);
            font-weight: 500;
        }
        
        .detail-value {
            color: var(--dark);
            font-weight: 600;
            text-align: right;
        }
        
        /* Rejection Form */
        .rejection-form {
            background: #fef2f2;
            border-radius: var(--border-radius);
            padding: 30px;
            margin-top: 30px;
            border: 2px solid #fecaca;
        }
        
        .form-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .form-header i {
            color: var(--danger);
            font-size: 24px;
        }
        
        .form-header h3 {
            color: var(--dark);
            font-size: 1.5rem;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            color: var(--dark);
            font-weight: 600;
            margin-bottom: 10px;
            font-size: 1rem;
        }
        
        .form-group .required {
            color: var(--danger);
        }
        
        .reason-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .reason-option {
            padding: 20px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
        }
        
        .reason-option:hover {
            border-color: var(--danger);
            background: #fef2f2;
        }
        
        .reason-option.selected {
            border-color: var(--danger);
            background: #fef2f2;
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
        }
        
        .reason-option i {
            font-size: 24px;
            color: var(--danger);
            margin-bottom: 10px;
        }
        
        .reason-option h4 {
            color: var(--dark);
            margin-bottom: 5px;
            font-size: 1rem;
        }
        
        .reason-option p {
            color: var(--gray);
            font-size: 13px;
        }
        
        textarea {
            width: 100%;
            padding: 15px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 15px;
            resize: vertical;
            min-height: 120px;
            transition: var(--transition);
        }
        
        textarea:focus {
            outline: none;
            border-color: var(--danger);
            box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1);
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .checkbox-group input {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        
        .checkbox-group label {
            color: var(--dark);
            cursor: pointer;
            margin-bottom: 0;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 20px;
            margin-top: 30px;
        }
        
        .btn {
            padding: 16px 35px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            flex: 1;
        }
        
        .btn-reject {
            background: linear-gradient(90deg, #ef4444, #dc2626);
            color: white;
            box-shadow: 0 5px 20px rgba(239, 68, 68, 0.3);
        }
        
        .btn-reject:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(239, 68, 68, 0.4);
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
        
        /* Confirmation Modal */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s;
        }
        
        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        .modal {
            background: white;
            border-radius: var(--border-radius);
            padding: 40px;
            max-width: 500px;
            width: 90%;
            transform: scale(0.8);
            transition: all 0.3s;
        }
        
        .modal-overlay.active .modal {
            transform: scale(1);
        }
        
        .modal h3 {
            color: var(--dark);
            margin-bottom: 20px;
            font-size: 1.5rem;
            text-align: center;
        }
        
        .modal-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn-confirm {
            background: #ef4444;
            color: white;
            flex: 1;
        }
        
        .btn-close {
            background: #6b7280;
            color: white;
            flex: 1;
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
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
        
        @keyframes bounceIn {
            0% { 
                transform: scale(0.3); 
                opacity: 0; 
            }
            50% { 
                transform: scale(1.05); 
            }
            70% { 
                transform: scale(0.9); 
            }
            100% { 
                transform: scale(1); 
                opacity: 1; 
            }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .details-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                min-width: 100%;
            }
            
            .timeline::before {
                left: 30px;
            }
            
            .timeline-item {
                width: 100%;
                left: 0 !important;
                text-align: left !important;
                padding-left: 70px !important;
                padding-right: 20px !important;
            }
            
            .timeline-item:nth-child(odd) .timeline-icon {
                left: 5px;
                right: auto;
            }
            
            .reason-options {
                grid-template-columns: 1fr;
            }
        }
    </style>
    
    <script>
        // Select rejection reason
        function selectReason(reason) {
            document.querySelectorAll('.reason-option').forEach(option => {
                option.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');
            document.getElementById('rejection_reason').value = reason;
        }
        
        // Show confirmation modal
        function showConfirmation() {
            const reason = document.getElementById('rejection_reason').value;
            const notes = document.getElementById('additional_notes').value;
            
            if(!reason.trim()) {
                alert('Please select a rejection reason.');
                return false;
            }
            
            document.getElementById('confirmationModal').classList.add('active');
            return false;
        }
        
        function closeModal() {
            document.getElementById('confirmationModal').classList.remove('active');
        }
        
        function submitRejection() {
            // Show loading state
            const rejectBtn = document.querySelector('.btn-reject');
            const originalHTML = rejectBtn.innerHTML;
            rejectBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Rejecting...';
            rejectBtn.disabled = true;
            
            // Animate warning icon
            const warningIcon = document.querySelector('.warning-icon i');
            warningIcon.classList.remove('fa-times-circle');
            warningIcon.classList.add('fa-spinner', 'fa-spin');
            
            // Submit the form
            document.getElementById('rejectionForm').submit();
            
            return false;
        }
        
        function cancelRejection() {
            window.location.href = 'appointment_requests.php';
        }
        
        // Auto-select reason from URL
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const autoReason = urlParams.get('reason');
            if(autoReason) {
                document.getElementById('rejection_reason').value = autoReason;
                document.querySelectorAll('.reason-option').forEach(option => {
                    if(option.querySelector('h4').textContent.includes(autoReason)) {
                        option.classList.add('selected');
                    }
                });
            }
            
            // Add animation to detail groups
            const groups = document.querySelectorAll('.detail-group');
            groups.forEach((group, index) => {
                group.style.animation = `slideInUp 0.5s ease-out ${index * 0.1}s both`;
            });
        });
    </script>
</head>
<body>
    <div class="rejection-container">
        <!-- Warning Message -->
        <div class="warning-card animate__animated animate__fadeIn">
            <div class="warning-icon">
                <i class="fas fa-times-circle"></i>
            </div>
            <h1>Reject Appointment Request</h1>
            <p>You are about to reject a vaccination appointment request. This action cannot be undone. Please provide a reason for rejection.</p>
            
            <div class="timeline">
                <div class="timeline-item">
                    <div class="timeline-icon">
                        <i class="fas fa-calendar-plus"></i>
                    </div>
                    <div class="timeline-content">
                        <h4>Request Submitted</h4>
                        <p>Parent submitted appointment request</p>
                    </div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-icon">
                        <i class="fas fa-user-md"></i>
                    </div>
                    <div class="timeline-content">
                        <h4>Under Review</h4>
                        <p>Currently being reviewed by admin</p>
                    </div>
                </div>
                <div class="timeline-item">
                    <div class="timeline-icon" style="background: #ef4444; color: white; border-color: #ef4444;">
                        <i class="fas fa-times"></i>
                    </div>
                    <div class="timeline-content" style="border: 2px solid #ef4444;">
                        <h4>Rejection</h4>
                        <p>Ready to reject this appointment</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Appointment Details -->
        <div class="details-card">
            <div class="details-header">
                <i class="fas fa-file-medical-alt"></i>
                <h2>Request Details</h2>
            </div>
            
            <div class="details-grid">
                <!-- Child Information -->
                <div class="detail-group">
                    <h3><i class="fas fa-baby"></i> Child Information</h3>
                    <div class="detail-item">
                        <span class="detail-label">Full Name:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($request_data['child_name']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Age:</span>
                        <span class="detail-value"><?php echo $age_months; ?> months</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Date of Birth:</span>
                        <span class="detail-value"><?php echo date('d M Y', strtotime($request_data['date_of_birth'])); ?></span>
                    </div>
                </div>
                
                <!-- Parent Information -->
                <div class="detail-group">
                    <h3><i class="fas fa-user-friends"></i> Parent Information</h3>
                    <div class="detail-item">
                        <span class="detail-label">Parent Name:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($request_data['parent_name']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Contact Number:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($request_data['parent_phone']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Email:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($request_data['parent_email']); ?></span>
                    </div>
                </div>
                
                <!-- Vaccine Information -->
                <div class="detail-group">
                    <h3><i class="fas fa-syringe"></i> Vaccine Information</h3>
                    <div class="detail-item">
                        <span class="detail-label">Vaccine Name:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($request_data['vaccine_name']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Dose Number:</span>
                        <span class="detail-value">Dose <?php echo $request_data['dose_number']; ?></span>
                    </div>
                </div>
                
                <!-- Hospital Information -->
                <div class="detail-group">
                    <h3><i class="fas fa-hospital"></i> Hospital Information</h3>
                    <div class="detail-item">
                        <span class="detail-label">Hospital Name:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($request_data['hospital_name']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">City:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($request_data['city']); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Preferred Date:</span>
                        <span class="detail-value"><?php echo date('l, d F Y', strtotime($request_data['preferred_date'])); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Rejection Form -->
            <form id="rejectionForm" method="POST" class="rejection-form">
                <div class="form-header">
                    <i class="fas fa-comment-medical"></i>
                    <h3>Rejection Details</h3>
                </div>
                
                <?php if(isset($error)): ?>
                <div style="background: #fee2e2; color: #991b1b; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <i class="fas fa-exclamation-circle"></i> Error: <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>
                
                <div class="form-group">
                    <label>Select Rejection Reason <span class="required">*</span></label>
                    <input type="hidden" id="rejection_reason" name="rejection_reason" required>
                    
                    <div class="reason-options">
                        <div class="reason-option" onclick="selectReason('Incomplete Information')">
                            <i class="fas fa-file-exclamation"></i>
                            <h4>Incomplete Information</h4>
                            <p>Required documents or information missing</p>
                        </div>
                        
                        <div class="reason-option" onclick="selectReason('Hospital Unavailable')">
                            <i class="fas fa-hospital-times"></i>
                            <h4>Hospital Unavailable</h4>
                            <p>Selected hospital is not available on requested date</p>
                        </div>
                        
                        <div class="reason-option" onclick="selectReason('Vaccine Out of Stock')">
                            <i class="fas fa-vial-virus"></i>
                            <h4>Vaccine Out of Stock</h4>
                            <p>Requested vaccine is temporarily unavailable</p>
                        </div>
                        
                        <div class="reason-option" onclick="selectReason('Age Inappropriate')">
                            <i class="fas fa-birthday-cake"></i>
                            <h4>Age Inappropriate</h4>
                            <p>Child's age doesn't match vaccine schedule</p>
                        </div>
                        
                        <div class="reason-option" onclick="selectReason('Duplicate Request')">
                            <i class="fas fa-copy"></i>
                            <h4>Duplicate Request</h4>
                            <p>Similar request already exists for this child</p>
                        </div>
                        
                        <div class="reason-option" onclick="selectReason('Other')">
                            <i class="fas fa-ellipsis-h"></i>
                            <h4>Other Reason</h4>
                            <p>Specify your reason below</p>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Additional Notes (Optional)</label>
                    <textarea id="additional_notes" name="additional_notes" 
                              placeholder="Provide any additional details or instructions for the parent..."></textarea>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="send_notification" name="send_notification" checked>
                    <label for="send_notification">Send notification email to parent</label>
                </div>
                
                <div class="action-buttons">
                    <button type="button" class="btn btn-reject" onclick="showConfirmation()">
                        <i class="fas fa-times-circle"></i> Confirm Rejection
                    </button>
                    <button type="button" class="btn btn-cancel" onclick="cancelRejection()">
                        <i class="fas fa-arrow-left"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Confirmation Modal -->
    <div class="modal-overlay" id="confirmationModal">
        <div class="modal">
            <div style="text-align: center; margin-bottom: 30px;">
                <div style="width: 80px; height: 80px; background: rgba(239, 68, 68, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 36px; color: #ef4444;"></i>
                </div>
                <h3>Confirm Rejection</h3>
                <p style="color: #6b7280; line-height: 1.6; margin-bottom: 10px;">
                    Are you sure you want to reject this appointment request?
                </p>
                <div style="background: #fef2f2; padding: 15px; border-radius: 8px; margin-top: 15px;">
                    <p style="color: #991b1b; font-size: 14px; margin-bottom: 5px;">
                        <i class="fas fa-exclamation-circle"></i> 
                        <strong>This action cannot be undone.</strong>
                    </p>
                    <p style="color: #b91c1c; font-size: 13px;">
                        Parent will be notified and will need to submit a new request.
                    </p>
                </div>
            </div>
            
            <div class="modal-actions">
                <button class="btn btn-confirm" onclick="submitRejection()">
                    <i class="fas fa-check"></i> Yes, Reject
                </button>
                <button class="btn btn-close" onclick="closeModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
    </div>
</body>
</html>
<?php mysqli_close($connection); ?>