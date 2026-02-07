<?php
session_start();
if(!isset($_SESSION["logged_in"]) || $_SESSION["user_type"] != "admin"){
    header("location:../login.php");
    exit();
}

include("../dbconnection.php");

$hospital_id = isset($_GET['id']) ? $_GET['id'] : '';
$action = isset($_GET['action']) ? $_GET['action'] : '';

if(empty($hospital_id) || empty($action)) {
    header("location:manage_hospitals.php");
    exit();
}

// Get hospital details
$query_hospital = "SELECT h.*, u.email, u.phone, u.is_active as user_active,
                   (SELECT COUNT(*) FROM vaccination_bookings WHERE hospital_id = h.hospital_id AND DATE(appointment_date) >= CURDATE()) as upcoming_bookings,
                   (SELECT COUNT(*) FROM appointment_requests WHERE hospital_id = h.hospital_id AND request_status = 'pending') as pending_requests
                   FROM hospitals h
                   JOIN users u ON h.user_id = u.user_id
                   WHERE h.hospital_id = '$hospital_id'";
$result_hospital = mysqli_query($connection, $query_hospital);
$hospital_data = mysqli_fetch_assoc($result_hospital);

if(!$hospital_data) {
    header("location:manage_hospitals.php");
    exit();
}

// Get admin details
$user_id = $_SESSION["user_id"];
$query_admin = "SELECT full_name FROM admins WHERE user_id = '$user_id'";
$result_admin = mysqli_query($connection, $query_admin);
$admin_data = mysqli_fetch_assoc($result_admin);
$admin_name = $admin_data['full_name'];

$new_status = ($action == 'activate') ? 1 : 0;
$current_status = $hospital_data['is_active'];
$hospital_name = $hospital_data['hospital_name'];
$status_text = ($action == 'activate') ? 'activate' : 'deactivate';
$status_text_cap = ($action == 'activate') ? 'Activate' : 'Deactivate';

// Process form submission
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirmation = isset($_POST['confirmation']) ? mysqli_real_escape_string($connection, $_POST['confirmation']) : '';
    $admin_notes = isset($_POST['admin_notes']) ? mysqli_real_escape_string($connection, $_POST['admin_notes']) : '';
    
    if($confirmation === 'yes') {
        // Update hospital status
        $query = "UPDATE hospitals SET is_active = '$new_status', updated_at = NOW() WHERE hospital_id = '$hospital_id'";
        $run = mysqli_query($connection, $query);
        
        if($run) {
            // Also update user status
            $query_user = "UPDATE users u 
                           JOIN hospitals h ON u.user_id = h.user_id 
                           SET u.is_active = '$new_status', u.updated_at = NOW()
                           WHERE h.hospital_id = '$hospital_id'";
            mysqli_query($connection, $query_user);
            
            // Log the action
            $log_description = "$status_text_cap hospital: $hospital_name. Notes: $admin_notes";
            $log_query = "INSERT INTO audit_logs (user_id, action_type, action_description, ip_address, created_at)
                         VALUES ('$user_id', '$status_text', '$log_description', '{$_SERVER['REMOTE_ADDR']}', NOW())";
            mysqli_query($connection, $log_query);
            
            // Update hospital status history
            $history_query = "INSERT INTO status_history (hospital_id, old_status, new_status, changed_by, notes, created_at)
                             VALUES ('$hospital_id', '$current_status', '$new_status', '$user_id', '$admin_notes', NOW())";
            mysqli_query($connection, $history_query);
            
            // Show success page
            echo generateSuccessPage($hospital_data, $action, $admin_name);
            exit();
        } else {
            $error = mysqli_error($connection);
        }
    } else {
        // User canceled
        header("location:manage_hospitals.php");
        exit();
    }
}

function generateSuccessPage($hospital_data, $action, $admin_name) {
    $status_text = ($action == 'activate') ? 'activated' : 'deactivated';
    $icon = ($action == 'activate') ? 'toggle-on' : 'toggle-off';
    $color = ($action == 'activate') ? '#10b981' : '#ef4444';
    $bg_color = ($action == 'activate') ? 'rgba(16, 185, 129, 0.1)' : 'rgba(239, 68, 68, 0.1)';
    
    return '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Status Updated - Admin Panel</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; font-family: "Segoe UI", sans-serif; }
            body {
                background: linear-gradient(135deg, '.($action == 'activate' ? '#10b981 0%, #059669 100%' : '#ef4444 0%, #dc2626 100%').');
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
                background: '.$bg_color.';
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 30px;
                color: '.$color.';
                font-size: 48px;
                animation: bounceIn 1s ease-out;
            }
            h1 { color: #1f2937; margin-bottom: 15px; font-size: 2.5rem; }
            p { color: #6b7280; font-size: 18px; line-height: 1.6; margin-bottom: 25px; }
            .info-box {
                background: #f8fafc;
                border: 2px solid '.$bg_color.';
                padding: 20px;
                border-radius: 12px;
                margin: 25px 0;
                text-align: left;
            }
            .info-box h3 { color: '.($action == 'activate' ? '#065f46' : '#991b1b').'; margin-bottom: 10px; }
            .status-badge {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                padding: 8px 16px;
                background: '.$bg_color.';
                color: '.$color.';
                border-radius: 20px;
                font-weight: 600;
                margin: 10px 0;
            }
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
                background: '.$color.';
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
                <i class="fas fa-'.$icon.'"></i>
            </div>
            <h1>Status Updated!</h1>
            <p>The hospital status has been successfully '.$status_text.'.</p>
            
            <div class="info-box">
                <h3>Hospital Details:</h3>
                <p><strong>Hospital:</strong> '.htmlspecialchars($hospital_data['hospital_name']).'</p>
                <p><strong>Registration No:</strong> '.htmlspecialchars($hospital_data['registration_number']).'</p>
                <p><strong>Location:</strong> '.htmlspecialchars($hospital_data['city']).', '.htmlspecialchars($hospital_data['state']).'</p>
                <div class="status-badge">
                    <i class="fas fa-'.$icon.'"></i>
                    '.($action == 'activate' ? 'Activated' : 'Deactivated').'
                </div>
                <p style="margin-top: 10px; font-size: 14px; color: #6b7280;">
                    <i class="fas fa-user-shield"></i> Updated by: '.htmlspecialchars($admin_name).'
                </p>
            </div>
            
            <div class="actions">
                <a href="manage_hospitals.php" class="btn btn-primary">
                    <i class="fas fa-hospital"></i> Back to Hospitals
                </a>
                <a href="hospital_details.php?id='.$hospital_data['hospital_id'].'" class="btn btn-secondary">
                    <i class="fas fa-eye"></i> View Details
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
    <title><?php echo $status_text_cap; ?> Hospital - Admin Panel</title>
    
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
            --activate-color: #10b981;
            --activate-dark: #059669;
            --deactivate-color: #ef4444;
            --deactivate-dark: #dc2626;
            --warning: #f59e0b;
            --light: #f8f9fa;
            --dark: #1f2937;
            --gray: #6b7280;
            --border-radius: 16px;
            --box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }
        
        body {
            background: linear-gradient(135deg, <?php echo $action == 'activate' ? '#10b981 0%, #059669 100%' : '#ef4444 0%, #dc2626 100%'; ?>);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .toggle-container {
            width: 100%;
            max-width: 900px;
            animation: fadeIn 0.6s ease-out;
        }
        
        /* Status Card */
        .status-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 40px;
            box-shadow: var(--box-shadow);
            text-align: center;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .status-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, <?php echo $action == 'activate' ? 'var(--activate-color), var(--activate-dark)' : 'var(--deactivate-color), var(--deactivate-dark)'; ?>);
        }
        
        .status-icon {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, <?php echo $action == 'activate' ? 'var(--activate-color), var(--activate-dark)' : 'var(--deactivate-color), var(--deactivate-dark)'; ?>);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            color: white;
            font-size: 48px;
            animation: bounceIn 1s ease-out;
            box-shadow: 0 10px 30px <?php echo $action == 'activate' ? 'rgba(16, 185, 129, 0.3)' : 'rgba(239, 68, 68, 0.3)'; ?>;
        }
        
        .status-card h1 {
            color: var(--dark);
            margin-bottom: 15px;
            font-size: 2.5rem;
        }
        
        .status-card p {
            color: var(--gray);
            font-size: 18px;
            line-height: 1.6;
            margin-bottom: 25px;
        }
        
        /* Impact Warning */
        .impact-warning {
            background: <?php echo $action == 'activate' ? 'rgba(16, 185, 129, 0.1)' : 'rgba(239, 68, 68, 0.1)'; ?>;
            border-radius: 12px;
            padding: 25px;
            margin: 25px 0;
            border-left: 4px solid <?php echo $action == 'activate' ? 'var(--activate-color)' : 'var(--deactivate-color)'; ?>;
            text-align: left;
        }
        
        .impact-warning h3 {
            color: <?php echo $action == 'activate' ? '#065f46' : '#991b1b'; ?>;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .impact-list {
            list-style: none;
        }
        
        .impact-list li {
            padding: 10px 0;
            border-bottom: 1px solid <?php echo $action == 'activate' ? 'rgba(16, 185, 129, 0.2)' : 'rgba(239, 68, 68, 0.2)'; ?>;
            display: flex;
            align-items: center;
            gap: 12px;
            color: <?php echo $action == 'activate' ? '#047857' : '#b91c1c'; ?>;
        }
        
        .impact-list li:last-child {
            border-bottom: none;
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
            color: <?php echo $action == 'activate' ? 'var(--activate-color)' : 'var(--deactivate-color)'; ?>;
            font-size: 24px;
        }
        
        .details-header h2 {
            color: var(--dark);
            font-size: 1.8rem;
        }
        
        .hospital-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .info-group {
            padding: 20px;
            border-radius: 12px;
            background: #f8fafc;
            transition: var(--transition);
        }
        
        .info-group:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .info-group h3 {
            color: <?php echo $action == 'activate' ? 'var(--activate-color)' : 'var(--deactivate-color)'; ?>;
            margin-bottom: 15px;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            color: var(--gray);
            font-weight: 500;
        }
        
        .info-value {
            color: var(--dark);
            font-weight: 600;
            text-align: right;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin: 25px 0;
        }
        
        .stat-item {
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            transition: var(--transition);
        }
        
        .stat-item:hover {
            border-color: <?php echo $action == 'activate' ? 'var(--activate-color)' : 'var(--deactivate-color)'; ?>;
            transform: translateY(-5px);
        }
        
        .stat-number {
            font-size: 28px;
            font-weight: 700;
            color: <?php echo $action == 'activate' ? 'var(--activate-color)' : 'var(--deactivate-color)'; ?>;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 12px;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Status Comparison */
        .status-comparison {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin: 30px 0;
        }
        
        .status-box {
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            transition: var(--transition);
        }
        
        .current-status {
            background: <?php echo $current_status == 1 ? 'rgba(16, 185, 129, 0.1)' : 'rgba(239, 68, 68, 0.1)'; ?>;
            border: 2px solid <?php echo $current_status == 1 ? 'var(--activate-color)' : 'var(--deactivate-color)'; ?>;
        }
        
        .new-status {
            background: <?php echo $new_status == 1 ? 'rgba(16, 185, 129, 0.1)' : 'rgba(239, 68, 68, 0.1)'; ?>;
            border: 2px dashed <?php echo $new_status == 1 ? 'var(--activate-color)' : 'var(--deactivate-color)'; ?>;
            animation: pulse 2s infinite;
        }
        
        .status-title {
            font-size: 14px;
            color: var(--gray);
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .status-value {
            font-size: 32px;
            font-weight: 700;
            color: <?php echo $current_status == 1 ? 'var(--activate-color)' : 'var(--deactivate-color)'; ?>;
            margin-bottom: 10px;
        }
        
        .new-status .status-value {
            color: <?php echo $new_status == 1 ? 'var(--activate-color)' : 'var(--deactivate-color)'; ?>;
        }
        
        /* Confirmation Form */
        .confirmation-form {
            background: #f8fafc;
            border-radius: var(--border-radius);
            padding: 30px;
            margin-top: 30px;
            border: 2px solid #e5e7eb;
        }
        
        .form-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .form-header i {
            color: <?php echo $action == 'activate' ? 'var(--activate-color)' : 'var(--deactivate-color)'; ?>;
            font-size: 24px;
        }
        
        .form-header h3 {
            color: var(--dark);
            font-size: 1.5rem;
        }
        
        .confirmation-options {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }
        
        @media (max-width: 768px) {
            .confirmation-options {
                grid-template-columns: 1fr;
            }
        }
        
        .confirmation-option {
            padding: 25px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            cursor: pointer;
            transition: var(--transition);
            text-align: center;
        }
        
        .confirmation-option:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .confirmation-option.selected {
            border-color: <?php echo $action == 'activate' ? 'var(--activate-color)' : 'var(--deactivate-color)'; ?>;
            background: <?php echo $action == 'activate' ? 'rgba(16, 185, 129, 0.05)' : 'rgba(239, 68, 68, 0.05)'; ?>;
            box-shadow: 0 0 0 3px <?php echo $action == 'activate' ? 'rgba(16, 185, 129, 0.1)' : 'rgba(239, 68, 68, 0.1)'; ?>;
        }
        
        .option-yes {
            border-color: <?php echo $action == 'activate' ? 'rgba(16, 185, 129, 0.3)' : 'rgba(239, 68, 68, 0.3)'; ?>;
        }
        
        .option-no {
            border-color: rgba(107, 114, 128, 0.3);
        }
        
        .confirmation-option i {
            font-size: 32px;
            margin-bottom: 15px;
        }
        
        .option-yes i {
            color: <?php echo $action == 'activate' ? 'var(--activate-color)' : 'var(--deactivate-color)'; ?>;
        }
        
        .option-no i {
            color: var(--gray);
        }
        
        .confirmation-option h4 {
            color: var(--dark);
            margin-bottom: 10px;
            font-size: 1.2rem;
        }
        
        .confirmation-option p {
            color: var(--gray);
            font-size: 14px;
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
            border-color: <?php echo $action == 'activate' ? 'var(--activate-color)' : 'var(--deactivate-color)'; ?>;
            box-shadow: 0 0 0 3px <?php echo $action == 'activate' ? 'rgba(16, 185, 129, 0.1)' : 'rgba(239, 68, 68, 0.1)'; ?>;
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
        
        .btn-confirm {
            background: linear-gradient(90deg, <?php echo $action == 'activate' ? 'var(--activate-color), var(--activate-dark)' : 'var(--deactivate-color), var(--deactivate-dark)'; ?>);
            color: white;
            box-shadow: 0 5px 20px <?php echo $action == 'activate' ? 'rgba(16, 185, 129, 0.3)' : 'rgba(239, 68, 68, 0.3)'; ?>;
        }
        
        .btn-confirm:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px <?php echo $action == 'activate' ? 'rgba(16, 185, 129, 0.4)' : 'rgba(239, 68, 68, 0.4)'; ?>;
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
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 <?php echo $new_status == 1 ? 'rgba(16, 185, 129, 0.4)' : 'rgba(239, 68, 68, 0.4)'; ?>; }
            70% { box-shadow: 0 0 0 10px <?php echo $new_status == 1 ? 'rgba(16, 185, 129, 0)' : 'rgba(239, 68, 68, 0)'; ?>; }
            100% { box-shadow: 0 0 0 0 <?php echo $new_status == 1 ? 'rgba(16, 185, 129, 0)' : 'rgba(239, 68, 68, 0)'; ?>; }
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .status-card, .details-card {
                padding: 25px;
            }
            
            .hospital-info {
                grid-template-columns: 1fr;
            }
            
            .status-comparison {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                min-width: 100%;
            }
        }
    </style>
    
    <script>
        // Select confirmation option
        let selectedOption = '';
        
        function selectOption(option) {
            selectedOption = option;
            document.querySelectorAll('.confirmation-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');
            document.getElementById('confirmation').value = option;
        }
        
        // Validate form submission
        function validateForm() {
            if(!selectedOption) {
                alert('Please select an option to proceed.');
                return false;
            }
            
            if(selectedOption === 'yes') {
                // Show loading state
                const confirmBtn = document.querySelector('.btn-confirm');
                const originalHTML = confirmBtn.innerHTML;
                confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                confirmBtn.disabled = true;
                
                // Animate status icon
                const statusIcon = document.querySelector('.status-icon i');
                statusIcon.classList.add('fa-spinner', 'fa-spin');
                
                return true;
            } else {
                // User selected 'no' - redirect back
                window.location.href = 'manage_hospitals.php';
                return false;
            }
        }
        
        // Add animation to elements on load
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-select 'yes' option
            selectOption('yes');
            
            // Add animation to info groups
            const groups = document.querySelectorAll('.info-group');
            groups.forEach((group, index) => {
                group.style.animationDelay = `${index * 0.1}s`;
            });
        });
    </script>
</head>
<body>
    <div class="toggle-container">
        <!-- Status Card -->
        <div class="status-card animate__animated animate__fadeIn">
            <div class="status-icon">
                <i class="fas fa-<?php echo $action == 'activate' ? 'toggle-on' : 'toggle-off'; ?>"></i>
            </div>
            <h1><?php echo $status_text_cap; ?> Hospital</h1>
            <p>You are about to <strong><?php echo $status_text; ?></strong> this hospital. Please review the details and confirm your action.</p>
            
            <div class="impact-warning">
                <h3>
                    <i class="fas fa-exclamation-triangle"></i>
                    Impact of <?php echo $status_text_cap; ?>ing
                </h3>
                <ul class="impact-list">
                    <?php if($action == 'activate'): ?>
                    <li><i class="fas fa-check-circle"></i> Hospital will appear in search results</li>
                    <li><i class="fas fa-check-circle"></i> Can receive new appointment requests</li>
                    <li><i class="fas fa-check-circle"></i> Hospital staff can login to system</li>
                    <li><i class="fas fa-check-circle"></i> Existing bookings will proceed as scheduled</li>
                    <?php else: ?>
                    <li><i class="fas fa-times-circle"></i> Hospital will be hidden from search results</li>
                    <li><i class="fas fa-times-circle"></i> Cannot receive new appointment requests</li>
                    <li><i class="fas fa-times-circle"></i> Hospital staff cannot login to system</li>
                    <li><i class="fas fa-times-circle"></i> Existing bookings may need to be rescheduled</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        
        <!-- Details Card -->
        <div class="details-card">
            <div class="details-header">
                <i class="fas fa-hospital-alt"></i>
                <h2>Hospital Information</h2>
            </div>
            
            <!-- Status Comparison -->
            <div class="status-comparison">
                <div class="status-box current-status">
                    <div class="status-title">Current Status</div>
                    <div class="status-value">
                        <?php echo $current_status == 1 ? 'ACTIVE' : 'INACTIVE'; ?>
                    </div>
                    <div style="font-size: 14px; color: <?php echo $current_status == 1 ? '#065f46' : '#991b1b'; ?>;">
                        <i class="fas fa-<?php echo $current_status == 1 ? 'toggle-on' : 'toggle-off'; ?>"></i>
                        <?php echo $current_status == 1 ? 'Currently Active' : 'Currently Inactive'; ?>
                    </div>
                </div>
                
                <div class="status-box new-status">
                    <div class="status-title">New Status</div>
                    <div class="status-value">
                        <?php echo $new_status == 1 ? 'ACTIVE' : 'INACTIVE'; ?>
                    </div>
                    <div style="font-size: 14px; color: <?php echo $new_status == 1 ? '#065f46' : '#991b1b'; ?>;">
                        <i class="fas fa-arrow-right"></i>
                        Will be <?php echo $new_status == 1 ? 'Activated' : 'Deactivated'; ?>
                    </div>
                </div>
            </div>
            
            <div class="hospital-info">
                <!-- Basic Information -->
                <div class="info-group">
                    <h3><i class="fas fa-info-circle"></i> Basic Information</h3>
                    <div class="info-item">
                        <span class="info-label">Hospital Name:</span>
                        <span class="info-value"><?php echo htmlspecialchars($hospital_data['hospital_name']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Registration No:</span>
                        <span class="info-value"><?php echo htmlspecialchars($hospital_data['registration_number']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Contact Person:</span>
                        <span class="info-value"><?php echo htmlspecialchars($hospital_data['contact_person']); ?></span>
                    </div>
                </div>
                
                <!-- Contact Information -->
                <div class="info-group">
                    <h3><i class="fas fa-address-book"></i> Contact Information</h3>
                    <div class="info-item">
                        <span class="info-label">Email:</span>
                        <span class="info-value"><?php echo htmlspecialchars($hospital_data['email']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Phone:</span>
                        <span class="info-value"><?php echo $hospital_data['phone'] ? htmlspecialchars($hospital_data['phone']) : 'N/A'; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Location:</span>
                        <span class="info-value"><?php echo htmlspecialchars($hospital_data['city']); ?>, <?php echo htmlspecialchars($hospital_data['state']); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $hospital_data['upcoming_bookings']; ?></div>
                    <div class="stat-label">Upcoming Bookings</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $hospital_data['pending_requests']; ?></div>
                    <div class="stat-label">Pending Requests</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">
                        <?php echo $hospital_data['is_verified'] ? '✓' : '⏳'; ?>
                    </div>
                    <div class="stat-label">Verification</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">
                        <?php echo $current_status == 1 ? 'ON' : 'OFF'; ?>
                    </div>
                    <div class="stat-label">Current Status</div>
                </div>
            </div>
            
            <!-- Confirmation Form -->
            <form method="POST" class="confirmation-form" onsubmit="return validateForm()">
                <div class="form-header">
                    <i class="fas fa-clipboard-check"></i>
                    <h3>Confirmation Required</h3>
                </div>
                
                <?php if(isset($error)): ?>
                <div style="background: #fee2e2; color: #991b1b; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <i class="fas fa-exclamation-circle"></i> Error: <?php echo htmlspecialchars($error); ?>
                </div>
                <?php endif; ?>
                
                <div class="confirmation-options">
                    <div class="confirmation-option option-yes selected" onclick="selectOption('yes')">
                        <i class="fas fa-check-circle"></i>
                        <h4>Yes, <?php echo $status_text; ?> this hospital</h4>
                        <p>I confirm that I want to <?php echo $status_text; ?> "<?php echo htmlspecialchars($hospital_name); ?>"</p>
                    </div>
                    
                    <div class="confirmation-option option-no" onclick="selectOption('no')">
                        <i class="fas fa-times-circle"></i>
                        <h4>No, cancel this action</h4>
                        <p>Keep the current status and return to hospitals list</p>
                    </div>
                </div>
                
                <input type="hidden" id="confirmation" name="confirmation" value="yes">
                
                <div class="form-group">
                    <label>Administrator Notes (Optional)</label>
                    <textarea name="admin_notes" placeholder="Add any notes about why you're changing the status..."></textarea>
                    <p style="color: var(--gray); font-size: 13px; margin-top: 8px;">
                        <i class="fas fa-info-circle"></i> These notes will be recorded in the audit log.
                    </p>
                </div>
                
                <div class="action-buttons">
                    <button type="submit" class="btn btn-confirm">
                        <i class="fas fa-<?php echo $action == 'activate' ? 'toggle-on' : 'toggle-off'; ?>"></i>
                        <?php echo $status_text_cap; ?> Hospital
                    </button>
                    <button type="button" class="btn btn-cancel" onclick="window.location.href='manage_hospitals.php'">
                        <i class="fas fa-arrow-left"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
<?php mysqli_close($connection); ?>