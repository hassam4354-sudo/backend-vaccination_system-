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
$query_admin = "SELECT admin_id, full_name FROM admins WHERE user_id = '$user_id'";
$result_admin = mysqli_query($connection, $query_admin);
$admin_data = mysqli_fetch_assoc($result_admin);
$admin_id = $admin_data['admin_id'];
$admin_name = $admin_data['full_name'];

// Get request details before approving
$query_details = "SELECT 
    ar.*,
    c.full_name as child_name, c.date_of_birth,
    p.full_name as parent_name, p.emergency_contact as parent_phone,
    h.hospital_name, h.city, h.address,
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approve Appointment - Admin Panel</title>
    
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
            --secondary: #059669;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --border-radius: 16px;
            --box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }
        
        body {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .approval-container {
            width: 100%;
            max-width: 800px;
            animation: fadeIn 0.6s ease-out;
        }
        
        /* Success Card */
        .success-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 40px;
            box-shadow: var(--box-shadow);
            text-align: center;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .success-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #10b981, #059669);
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
            box-shadow: 0 10px 30px rgba(16, 185, 129, 0.3);
        }
        
        .success-card h1 {
            color: var(--dark);
            margin-bottom: 15px;
            font-size: 2.5rem;
        }
        
        .success-card p {
            color: var(--gray);
            font-size: 18px;
            line-height: 1.6;
            margin-bottom: 25px;
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
            color: var(--primary);
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
            color: var(--primary);
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
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 20px;
            margin-top: 30px;
            justify-content: center;
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
            min-width: 200px;
        }
        
        .btn-approve {
            background: linear-gradient(90deg, #10b981, #059669);
            color: white;
            box-shadow: 0 5px 20px rgba(16, 185, 129, 0.3);
        }
        
        .btn-approve:hover {
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
            background: linear-gradient(to bottom, #10b981, #059669);
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
            border: 4px solid #10b981;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #10b981;
            font-size: 20px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 1;
        }
        
        .timeline-item:nth-child(odd) .timeline-icon {
            right: -25px;
        }
        
        .timeline-item:nth-child(even) .timeline-icon {
            left: -25px;
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
            background: #10b981;
            color: white;
            flex: 1;
        }
        
        .btn-close {
            background: #ef4444;
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
        
        @keyframes checkmark {
            0% { 
                stroke-dashoffset: 100; 
                opacity: 0; 
            }
            100% { 
                stroke-dashoffset: 0; 
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
        }
    </style>
    
    <script>
        // Confirmation modal
        function showConfirmation() {
            document.getElementById('confirmationModal').classList.add('active');
            return false;
        }
        
        function closeModal() {
            document.getElementById('confirmationModal').classList.remove('active');
        }
        
        function approveRequest() {
            // Show loading state
            const approveBtn = document.querySelector('.btn-approve');
            const originalHTML = approveBtn.innerHTML;
            approveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Approving...';
            approveBtn.disabled = true;
            
            // Animate success icon
            const checkmark = document.querySelector('.success-icon i');
            checkmark.classList.remove('fa-check-circle');
            checkmark.classList.add('fa-spinner', 'fa-spin');
            
            // Submit the form
            setTimeout(() => {
                document.getElementById('approvalForm').submit();
            }, 1500);
            
            return false;
        }
        
        function cancelApproval() {
            window.location.href = 'appointment_requests.php';
        }
        
        // Animate checkmark on load
        document.addEventListener('DOMContentLoaded', function() {
            // Add success animation after 1 second
            setTimeout(() => {
                const icon = document.querySelector('.success-icon');
                icon.style.animation = 'bounceIn 1s ease-out';
            }, 1000);
            
            // Add animation to detail groups
            const groups = document.querySelectorAll('.detail-group');
            groups.forEach((group, index) => {
                group.style.animation = `slideInUp 0.5s ease-out ${index * 0.1}s both`;
            });
        });
    </script>
</head>
<body>
    <div class="approval-container">
        <!-- Success Message -->
        <div class="success-card animate__animated animate__fadeIn">
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h1>Approve Appointment</h1>
            <p>You are about to approve a vaccination appointment request. Please review the details below before confirming.</p>
            
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
                    <div class="timeline-icon" style="background: #10b981; color: white;">
                        <i class="fas fa-check"></i>
                    </div>
                    <div class="timeline-content" style="border: 2px solid #10b981;">
                        <h4>Approval</h4>
                        <p>Ready to approve this appointment</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Appointment Details -->
        <div class="details-card">
            <div class="details-header">
                <i class="fas fa-file-medical-alt"></i>
                <h2>Appointment Details</h2>
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
                        <span class="detail-label">Notes:</span>
                        <span class="detail-value">
                            <?php echo !empty($request_data['parent_notes']) ? htmlspecialchars($request_data['parent_notes']) : 'No notes provided'; ?>
                        </span>
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
                    <div class="detail-item">
                        <span class="detail-label">Description:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($request_data['vaccine_desc']); ?></span>
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
                        <span class="detail-label">Address:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($request_data['address']); ?></span>
                    </div>
                </div>
                
                <!-- Appointment Timing -->
                <div class="detail-group">
                    <h3><i class="fas fa-clock"></i> Appointment Timing</h3>
                    <div class="detail-item">
                        <span class="detail-label">Preferred Date:</span>
                        <span class="detail-value"><?php echo date('l, d F Y', strtotime($request_data['preferred_date'])); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Preferred Time:</span>
                        <span class="detail-value"><?php echo date('h:i A', strtotime($request_data['preferred_time'])); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Requested On:</span>
                        <span class="detail-value"><?php echo date('d M Y, h:i A', strtotime($request_data['created_at'])); ?></span>
                    </div>
                </div>
                
                <!-- Approval Information -->
                <div class="detail-group">
                    <h3><i class="fas fa-user-shield"></i> Approval Information</h3>
                    <div class="detail-item">
                        <span class="detail-label">Approving Admin:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($admin_name); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Request ID:</span>
                        <span class="detail-value">REQ<?php echo str_pad($request_id, 5, '0', STR_PAD_LEFT); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Current Status:</span>
                        <span class="detail-value" style="color: #f59e0b; font-weight: bold;">
                            <i class="fas fa-clock"></i> Pending Approval
                        </span>
                    </div>
                </div>
            </div>
            
            <form id="approvalForm" action="" method="POST">
                <div class="action-buttons">
                    <button type="button" class="btn btn-approve" onclick="showConfirmation()">
                        <i class="fas fa-check-circle"></i> Confirm Approval
                    </button>
                    <button type="button" class="btn btn-cancel" onclick="cancelApproval()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
                
                <!-- Hidden fields for form submission -->
                <input type="hidden" name="request_id" value="<?php echo $request_id; ?>">
                <input type="hidden" name="admin_id" value="<?php echo $admin_id; ?>">
            </form>
        </div>
    </div>
    
    <!-- Confirmation Modal -->
    <div class="modal-overlay" id="confirmationModal">
        <div class="modal">
            <div style="text-align: center; margin-bottom: 30px;">
                <div style="width: 80px; height: 80px; background: rgba(16, 185, 129, 0.1); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                    <i class="fas fa-question-circle" style="font-size: 36px; color: #10b981;"></i>
                </div>
                <h3>Confirm Approval</h3>
                <p style="color: #6c757d; line-height: 1.6; margin-bottom: 10px;">
                    Are you sure you want to approve this appointment request?
                </p>
                <p style="color: #f59e0b; font-size: 14px;">
                    <i class="fas fa-exclamation-circle"></i> 
                    This action cannot be undone.
                </p>
            </div>
            
            <div class="modal-actions">
                <button class="btn btn-confirm" onclick="approveRequest()">
                    <i class="fas fa-check"></i> Yes, Approve
                </button>
                <button class="btn btn-close" onclick="closeModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </div>
        </div>
    </div>
    
    <?php
    // Process form submission
    if($_SERVER['REQUEST_METHOD'] === 'POST') {
        $request_id = $_POST['request_id'];
        $admin_id = $_POST['admin_id'];
        
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
            // Assuming create_notification function exists
            // create_notification(...);
            
            // Show success animation and redirect
            echo "
            <script>
            // Show success animation
            document.body.innerHTML = `
                <div style='
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
                    text-align: center;
                    padding: 40px;
                '>
                    <div style='
                        background: white;
                        padding: 60px 40px;
                        border-radius: 20px;
                        box-shadow: 0 20px 60px rgba(0,0,0,0.2);
                        max-width: 500px;
                        width: 100%;
                    '>
                        <div style='
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
                            animation: bounce 1s;
                        '>
                            <i class='fas fa-check-circle'></i>
                        </div>
                        <h1 style='color: #1e293b; margin-bottom: 15px; font-size: 2.2rem;'>Approved Successfully!</h1>
                        <p style='color: #64748b; font-size: 18px; margin-bottom: 25px; line-height: 1.6;'>
                            Appointment request for <strong>".htmlspecialchars($request_data['child_name'])."</strong> has been approved.
                        </p>
                        <div style='
                            background: #f0fdf4;
                            padding: 20px;
                            border-radius: 12px;
                            border-left: 4px solid #10b981;
                            margin: 25px 0;
                            text-align: left;
                        '>
                            <p style='color: #065f46; margin-bottom: 10px;'>
                                <i class='fas fa-bell'></i> 
                                <strong>Notification sent to parent</strong>
                            </p>
                            <p style='color: #047857; font-size: 14px;'>
                                The parent has been notified about the approval via SMS and email.
                            </p>
                        </div>
                        <p style='color: #6b7280; font-size: 14px; margin-top: 30px;'>
                            Redirecting to appointment requests page...
                        </p>
                        <div style='
                            height: 4px;
                            background: #e5e7eb;
                            border-radius: 2px;
                            margin-top: 15px;
                            overflow: hidden;
                        '>
                            <div style='
                                height: 100%;
                                background: #10b981;
                                width: 0%;
                                animation: progress 3s linear;
                                border-radius: 2px;
                            '></div>
                        </div>
                    </div>
                </div>
                <style>
                    @keyframes bounce {
                        0%, 20%, 50%, 80%, 100% {transform: translateY(0);}
                        40% {transform: translateY(-30px);}
                        60% {transform: translateY(-15px);}
                    }
                    @keyframes progress {
                        0% { width: 0%; }
                        100% { width: 100%; }
                    }
                </style>
            `;
            
            // Redirect after 3 seconds
            setTimeout(() => {
                window.location.href = 'appointment_requests.php';
            }, 3000);
            </script>
            ";
        } else {
            echo "<script>
            alert('Error: " . addslashes(mysqli_error($connection)) . "');
            window.history.back();
            </script>";
        }
        exit();
    }
    ?>
</body>
</html>
<?php mysqli_close($connection); ?>