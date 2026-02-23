<?php
session_start();
if(!isset($_SESSION["logged_in"]) || $_SESSION["user_type"] != "parent"){
    header("location:../login.php");
    exit();
}

include("../dbconnection.php");

$user_id = $_SESSION["user_id"];

// Get parent info
$query_parent = "SELECT * FROM parents WHERE user_id = '$user_id'";
$result_parent = mysqli_query($connection, $query_parent);
$parent_data = mysqli_fetch_assoc($result_parent);
$parent_id = $parent_data['parent_id'];

// Get all appointment requests with details
$query_requests = "SELECT 
    ar.*,
    c.full_name as child_name,
    c.date_of_birth,
    v.vaccine_name,
    v.vaccine_code,
    h.hospital_name,
    h.city as hospital_city,
    a.full_name as admin_name,
    DATEDIFF(ar.preferred_date, CURDATE()) as days_remaining
FROM appointment_requests ar
JOIN children c ON ar.child_id = c.child_id
JOIN vaccines v ON ar.vaccine_id = v.vaccine_id
JOIN hospitals h ON ar.hospital_id = h.hospital_id
LEFT JOIN admins a ON ar.processed_by = a.admin_id
WHERE c.parent_id = '$parent_id'
ORDER BY 
    CASE 
        WHEN ar.request_status = 'pending' THEN 1
        WHEN ar.request_status = 'approved' THEN 2
        WHEN ar.request_status = 'rejected' THEN 3
        ELSE 4
    END,
    ar.preferred_date ASC";

$result_requests = mysqli_query($connection, $query_requests);

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total_requests,
    SUM(CASE WHEN request_status = 'pending' THEN 1 ELSE 0 END) as pending_count,
    SUM(CASE WHEN request_status = 'approved' THEN 1 ELSE 0 END) as approved_count,
    SUM(CASE WHEN request_status = 'rejected' THEN 1 ELSE 0 END) as rejected_count
FROM appointment_requests ar
JOIN children c ON ar.child_id = c.child_id
WHERE c.parent_id = '$parent_id'";

$stats_result = mysqli_query($connection, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointment Requests - Parent Dashboard</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 30px 20px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        /* ===== NAVBAR - ULTRA MODERN ===== */
        .navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border-radius: 30px;
            padding: 18px 35px;
            margin-bottom: 35px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.15), 0 0 0 1px rgba(255,255,255,0.5);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            overflow: hidden;
            animation: slideInDown 0.8s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        
        .navbar::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            animation: shimmer 3s infinite;
        }
        
        @keyframes shimmer {
            100% { left: 100%; }
        }
        
        .navbar h2 {
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 28px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .navbar h2 i {
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 32px;
        }
        
        .nav-links {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .nav-links a {
            text-decoration: none;
            color: #4a5568;
            padding: 12px 25px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(102,126,234,0.1);
            border: 1px solid rgba(102,126,234,0.2);
        }
        
        .nav-links a i {
            color: #667eea;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .nav-links a:hover {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 15px 30px rgba(102,126,234,0.4);
            border: 1px solid transparent;
        }
        
        .nav-links a:hover i {
            color: white;
        }
        
        .nav-links a.logout {
            background: linear-gradient(135deg, #f43f5e, #e11d48);
            color: white;
            border: none;
        }
        
        .nav-links a.logout i {
            color: white;
        }
        
        /* ===== PAGE HEADER ===== */
        .page-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 40px;
            padding: 40px 50px;
            margin-bottom: 40px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.2), 0 0 0 1px rgba(255,255,255,0.5);
            position: relative;
            overflow: hidden;
            animation: fadeIn 1s ease;
        }
        
        .page-header::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(102,126,234,0.15) 0%, transparent 60%);
            animation: rotate 20s linear infinite;
        }
        
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .page-header h1 {
            color: #1e293b;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 20px;
            font-size: 42px;
            font-weight: 800;
            position: relative;
            z-index: 2;
        }
        
        .page-header h1 i {
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 48px;
            padding: 15px;
            border-radius: 20px;
            background: rgba(255,255,255,0.5);
            backdrop-filter: blur(10px);
        }
        
        .page-header p {
            color: #64748b;
            font-size: 18px;
            max-width: 600px;
            line-height: 1.8;
            position: relative;
            z-index: 2;
        }
        
        /* ===== STATS CARDS ===== */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 30px;
            padding: 30px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.15), 0 0 0 1px rgba(255,255,255,0.5);
            transition: all 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            border: 1px solid rgba(255,255,255,0.3);
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.8s ease;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }
        
        .stat-card:hover {
            transform: translateY(-15px) scale(1.02);
            box-shadow: 0 30px 70px rgba(102,126,234,0.4);
        }
        
        .stat-icon {
            width: 70px;
            height: 70px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 25px;
            font-size: 32px;
            color: white;
            background: linear-gradient(135deg, #667eea, #764ba2);
            box-shadow: 0 15px 30px rgba(102,126,234,0.3);
        }
        
        .stat-card h3 {
            font-size: 42px;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 8px;
        }
        
        .stat-card p {
            color: #64748b;
            font-weight: 500;
            font-size: 16px;
        }
        
        /* ===== FILTER TABS ===== */
        .filter-tabs {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 50px;
            padding: 10px;
            margin-bottom: 40px;
            display: inline-flex;
            gap: 10px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            animation: fadeIn 0.8s ease;
        }
        
        .tab-btn {
            padding: 14px 35px;
            border: none;
            background: transparent;
            border-radius: 40px;
            font-weight: 600;
            font-size: 15px;
            color: #64748b;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .tab-btn i {
            font-size: 16px;
        }
        
        .tab-btn:hover {
            color: #667eea;
            background: rgba(102,126,234,0.1);
        }
        
        .tab-btn.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            box-shadow: 0 10px 25px rgba(102,126,234,0.4);
        }
        
        /* ===== REQUESTS GRID ===== */
        .requests-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }
        
        .request-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 30px;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1), 0 0 0 1px rgba(255,255,255,0.5);
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            border: 1px solid rgba(255,255,255,0.3);
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.8s ease;
        }
        
        .request-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 30px 60px rgba(102,126,234,0.3);
        }
        
        .request-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 6px;
        }
        
        .request-card.pending::before {
            background: linear-gradient(90deg, #f59e0b, #fbbf24);
        }
        
        .request-card.approved::before {
            background: linear-gradient(90deg, #10b981, #34d399);
        }
        
        .request-card.rejected::before {
            background: linear-gradient(90deg, #ef4444, #f87171);
        }
        
        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .child-avatar {
            width: 70px;
            height: 70px;
            border-radius: 20px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 32px;
            box-shadow: 0 15px 30px rgba(102,126,234,0.3);
        }
        
        .request-id {
            background: rgba(102,126,234,0.1);
            padding: 8px 18px;
            border-radius: 40px;
            font-weight: 700;
            font-size: 14px;
            color: #667eea;
            border: 1px solid rgba(102,126,234,0.3);
        }
        
        .child-name {
            font-size: 24px;
            font-weight: 800;
            color: #1e293b;
            margin-bottom: 10px;
        }
        
        .child-age {
            color: #64748b;
            font-weight: 500;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 15px;
        }
        
        .request-details {
            background: rgba(241, 245, 249, 0.7);
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            color: #64748b;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .detail-label i {
            color: #667eea;
            width: 20px;
        }
        
        .detail-value {
            font-weight: 700;
            color: #1e293b;
        }
        
        .status-badge {
            padding: 10px 25px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            width: fit-content;
        }
        
        .status-badge.pending {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: #b45309;
        }
        
        .status-badge.approved {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #065f46;
        }
        
        .status-badge.rejected {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #b91c1c;
        }
        
        .admin-notes {
            background: #f8fafc;
            border-radius: 15px;
            padding: 15px;
            margin: 20px 0;
            border-left: 4px solid #667eea;
            font-size: 14px;
            line-height: 1.8;
        }
        
        .days-remaining {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 18px;
            border-radius: 40px;
            font-weight: 700;
            font-size: 13px;
            background: rgba(102,126,234,0.1);
            color: #667eea;
        }
        
        .urgent {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #b91c1c;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .request-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }
        
        .btn {
            flex: 1;
            padding: 14px;
            border: none;
            border-radius: 15px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
        }
        
        .btn-view {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            box-shadow: 0 10px 20px rgba(102,126,234,0.3);
        }
        
        .btn-view:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 20px 30px rgba(102,126,234,0.5);
        }
        
        .btn-cancel {
            background: linear-gradient(135deg, #f43f5e, #e11d48);
            color: white;
            box-shadow: 0 10px 20px rgba(244,63,94,0.3);
        }
        
        .btn-cancel:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 20px 30px rgba(244,63,94,0.5);
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* ===== EMPTY STATE ===== */
        .empty-state {
            text-align: center;
            padding: 80px 40px;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(20px);
            border-radius: 50px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.2);
        }
        
        .empty-state i {
            font-size: 100px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 25px;
        }
        
        .empty-state h3 {
            font-size: 32px;
            color: #1e293b;
            margin-bottom: 15px;
            font-weight: 800;
        }
        
        .empty-state p {
            color: #64748b;
            font-size: 18px;
            margin-bottom: 30px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 16px 40px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 700;
            font-size: 16px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
            box-shadow: 0 10px 25px rgba(102,126,234,0.4);
        }
        
        .btn-primary:hover {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 20px 40px rgba(102,126,234,0.6);
        }
        
        /* ===== MODAL ===== */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(10px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            animation: fadeIn 0.3s ease;
        }
        
        .modal-overlay.active {
            display: flex;
        }
        
        .modal {
            background: white;
            border-radius: 40px;
            padding: 40px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 30px 70px rgba(0,0,0,0.3);
            animation: slideInUp 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        
        .modal-header h3 {
            font-size: 28px;
            font-weight: 800;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 30px;
            color: #64748b;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .modal-close:hover {
            color: #ef4444;
            transform: rotate(90deg);
        }
        
        .modal-body {
            margin-bottom: 30px;
            text-align: center;
        }
        
        .modal-icon {
            width: 80px;
            height: 80px;
            background: #fee2e2;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: #ef4444;
            font-size: 40px;
        }
        
        .modal-actions {
            display: flex;
            gap: 15px;
        }
        
        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
            flex: 1;
        }
        
        /* ===== ANIMATIONS ===== */
        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-100px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes slideInUp {
            from {
                opacity: 0;
                transform: translateY(100px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 1200px) {
            .navbar {
                flex-direction: column;
                gap: 20px;
            }
            
            .nav-links {
                justify-content: center;
            }
            
            .page-header h1 {
                font-size: 36px;
            }
        }
        
        @media (max-width: 768px) {
            .requests-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-container {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filter-tabs {
                flex-direction: column;
                width: 100%;
                border-radius: 30px;
            }
            
            .page-header h1 {
                font-size: 28px;
                flex-direction: column;
                align-items: flex-start;
            }
            
            .request-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
        }
        
        @media (max-width: 480px) {
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .navbar {
                padding: 15px;
            }
            
            .nav-links {
                flex-direction: column;
                width: 100%;
            }
            
            .nav-links a {
                width: 100%;
                justify-content: center;
            }
            
            .page-header {
                padding: 30px 25px;
            }
            
            .modal {
                padding: 30px 25px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- ===== NAVBAR ===== -->
        <nav class="navbar animate__animated animate__fadeInDown">
            <h2>
                <i class="fas fa-child"></i>
                Parent Dashboard
            </h2>
            <div class="nav-links">
                <a href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a href="my_children.php">
                    <i class="fas fa-users"></i> My Children
                </a>
                <a href="book_appointment.php">
                    <i class="fas fa-calendar-plus"></i> Book Appointment
                </a>
                <a href="my_requests.php" style="background: linear-gradient(135deg, #667eea, #764ba2); color: white;">
                    <i class="fas fa-clock"></i> My Requests
                </a>
                <a href="vaccination_history.php">
                    <i class="fas fa-history"></i> History
                </a>
                <a href="myprofile.php">
                    <i class="fas fa-user"></i> Profile
                </a>
                <a href="../logout.php" class="logout">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </nav>
        
        <!-- ===== PAGE HEADER ===== -->
        <div class="page-header animate__animated animate__fadeIn">
            <h1>
                <i class="fas fa-clock"></i>
                My Appointment Requests
            </h1>
            <p>Track the status of all your vaccination appointment requests</p>
        </div>
        
        <!-- ===== STATS CARDS ===== -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <h3><?php echo $stats['total_requests'] ?? 0; ?></h3>
                <p>Total Requests</p>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <h3><?php echo $stats['pending_count'] ?? 0; ?></h3>
                <p>Pending</p>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3><?php echo $stats['approved_count'] ?? 0; ?></h3>
                <p>Approved</p>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <h3><?php echo $stats['rejected_count'] ?? 0; ?></h3>
                <p>Rejected</p>
            </div>
        </div>
        
        <!-- ===== FILTER TABS ===== -->
        <div class="filter-tabs">
            <button class="tab-btn active" onclick="filterRequests('all')" id="tabAll">
                <i class="fas fa-list-ul"></i> All
            </button>
            <button class="tab-btn" onclick="filterRequests('pending')" id="tabPending">
                <i class="fas fa-clock"></i> Pending
            </button>
            <button class="tab-btn" onclick="filterRequests('approved')" id="tabApproved">
                <i class="fas fa-check-circle"></i> Approved
            </button>
            <button class="tab-btn" onclick="filterRequests('rejected')" id="tabRejected">
                <i class="fas fa-times-circle"></i> Rejected
            </button>
        </div>
        
        <!-- ===== REQUESTS GRID ===== -->
        <?php if(mysqli_num_rows($result_requests) > 0): ?>
        <div class="requests-grid" id="requestsGrid">
            <?php while($request = mysqli_fetch_assoc($result_requests)): 
                $days_remaining = $request['days_remaining'];
                $is_urgent = ($days_remaining <= 2 && $days_remaining >= 0 && $request['request_status'] == 'approved');
                
                // Calculate child age
                $age_days = floor((time() - strtotime($request['date_of_birth'])) / (60 * 60 * 24));
                $age_years = floor($age_days / 365);
                $age_months = floor(($age_days % 365) / 30);
                
                if($age_years > 0) {
                    $age_text = $age_years . " year" . ($age_years > 1 ? "s" : "");
                    if($age_months > 0) $age_text .= " " . $age_months . " month" . ($age_months > 1 ? "s" : "");
                } else {
                    $age_text = $age_months . " month" . ($age_months > 1 ? "s" : "");
                }
            ?>
            <div class="request-card <?php echo $request['request_status']; ?>" data-status="<?php echo $request['request_status']; ?>">
                <div class="request-header">
                    <div class="child-avatar">
                        <i class="fas fa-child"></i>
                    </div>
                    <span class="request-id">#<?php echo str_pad($request['request_id'], 5, '0', STR_PAD_LEFT); ?></span>
                </div>
                
                <h3 class="child-name"><?php echo htmlspecialchars($request['child_name']); ?></h3>
                <div class="child-age">
                    <i class="fas fa-birthday-cake"></i>
                    <?php echo $age_text; ?>
                </div>
                
                <div class="request-details">
                    <div class="detail-row">
                        <span class="detail-label">
                            <i class="fas fa-syringe"></i> Vaccine:
                        </span>
                        <span class="detail-value">
                            <?php echo htmlspecialchars($request['vaccine_name']); ?>
                            <?php if($request['vaccine_code']): ?>
                                <span style="font-family: monospace; background: #f1f5f9; padding: 3px 8px; border-radius: 5px; margin-left: 8px; font-size: 11px;">
                                    <?php echo $request['vaccine_code']; ?>
                                </span>
                            <?php endif; ?>
                        </span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">
                            <i class="fas fa-hospital"></i> Hospital:
                        </span>
                        <span class="detail-value">
                            <?php echo htmlspecialchars($request['hospital_name']); ?>
                            <span style="display: block; font-size: 12px; color: #64748b;">
                                <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($request['hospital_city']); ?>
                            </span>
                        </span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">
                            <i class="fas fa-calendar-alt"></i> Date:
                        </span>
                        <span class="detail-value">
                            <?php echo date('d M, Y', strtotime($request['preferred_date'])); ?>
                        </span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">
                            <i class="fas fa-clock"></i> Time:
                        </span>
                        <span class="detail-value">
                            <?php echo date('h:i A', strtotime($request['preferred_time'])); ?>
                        </span>
                    </div>
                    
                    <div class="detail-row">
                        <span class="detail-label">
                            <i class="fas fa-syringe"></i> Dose:
                        </span>
                        <span class="detail-value">
                            <span style="background: #667eea; color: white; padding: 4px 15px; border-radius: 30px; font-weight: 700;">
                                Dose #<?php echo $request['dose_number']; ?>
                            </span>
                        </span>
                    </div>
                </div>
                
                <?php if($request['request_status'] == 'approved'): ?>
                <div style="margin-bottom: 20px;">
                    <span class="days-remaining <?php echo $is_urgent ? 'urgent' : ''; ?>">
                        <i class="fas fa-hourglass-half"></i>
                        <?php 
                        if($days_remaining < 0) {
                            echo "Overdue by " . abs($days_remaining) . " days";
                        } elseif($days_remaining == 0) {
                            echo "Today!";
                        } else {
                            echo $days_remaining . " days remaining";
                        }
                        ?>
                    </span>
                </div>
                <?php endif; ?>
                
                <div class="status-badge <?php echo $request['request_status']; ?>">
                    <i class="fas 
                        <?php 
                        echo $request['request_status'] == 'pending' ? 'fa-clock' : 
                            ($request['request_status'] == 'approved' ? 'fa-check-circle' : 'fa-times-circle'); 
                        ?>">
                    </i>
                    <?php echo ucfirst($request['request_status']); ?>
                </div>
                
                <?php if($request['admin_notes'] && $request['request_status'] != 'pending'): ?>
                <div class="admin-notes">
                    <strong><i class="fas fa-user-shield"></i> Admin Notes:</strong>
                    <p style="margin-top: 8px;"><?php echo nl2br(htmlspecialchars($request['admin_notes'])); ?></p>
                    <?php if($request['admin_name']): ?>
                    <small style="color: #667eea;">- <?php echo $request['admin_name']; ?></small>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                
                <div class="request-actions">
                    <a href="request_details.php?id=<?php echo $request['request_id']; ?>" class="btn btn-view">
                        <i class="fas fa-eye"></i> View Details
                    </a>
                    
                    <?php if($request['request_status'] == 'pending'): ?>
                    <button class="btn btn-cancel" onclick="showCancelModal(<?php echo $request['request_id']; ?>, '<?php echo $request['child_name']; ?>')">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <?php else: ?>
                    <button class="btn btn-cancel" disabled>
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        
        <?php else: ?>
        <!-- ===== EMPTY STATE ===== -->
        <div class="empty-state animate__animated animate__fadeIn">
            <i class="fas fa-calendar-times"></i>
            <h3>No Appointment Requests Found</h3>
            <p>You haven't submitted any appointment requests yet.<br>Book your child's vaccination now!</p>
            <a href="book_appointment.php" class="btn-primary">
                <i class="fas fa-calendar-plus me-2"></i> Book Appointment
            </a>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- ===== CANCEL MODAL ===== -->
    <div class="modal-overlay" id="cancelModal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i> Cancel Request</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="modal-icon">
                    <i class="fas fa-question"></i>
                </div>
                <h4 style="font-size: 22px; margin-bottom: 15px;">Are you sure?</h4>
                <p style="color: #64748b; line-height: 1.8; margin-bottom: 10px;">
                    You are about to cancel the appointment request for <strong id="cancelChildName"></strong>.
                </p>
                <p style="color: #ef4444; font-size: 14px;">
                    <i class="fas fa-exclamation-circle"></i> This action cannot be undone.
                </p>
            </div>
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closeModal()">
                    <i class="fas fa-times"></i> No, Keep
                </button>
                <a href="#" id="cancelLink" class="btn btn-cancel" style="flex: 1;">
                    <i class="fas fa-check"></i> Yes, Cancel
                </a>
            </div>
        </div>
    </div>
    
    <script>
        // ===== FILTER REQUESTS =====
        function filterRequests(status) {
            const cards = document.querySelectorAll('.request-card');
            const tabs = document.querySelectorAll('.tab-btn');
            
            // Update active tab
            tabs.forEach(tab => tab.classList.remove('active'));
            document.getElementById('tab' + status.charAt(0).toUpperCase() + status.slice(1)).classList.add('active');
            
            // Filter cards
            cards.forEach(card => {
                if(status === 'all') {
                    card.style.display = 'block';
                } else {
                    if(card.dataset.status === status) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                }
            });
            
            // Show empty message if no cards visible
            const visibleCards = Array.from(cards).filter(card => card.style.display !== 'none');
            const emptyMessage = document.querySelector('.empty-state');
            
            if(visibleCards.length === 0 && document.querySelector('.requests-grid')) {
                if(!document.getElementById('noResults')) {
                    const noResults = document.createElement('div');
                    noResults.id = 'noResults';
                    noResults.className = 'empty-state';
                    noResults.innerHTML = `
                        <i class="fas fa-search"></i>
                        <h3>No ${status} requests found</h3>
                        <p>You don't have any ${status} appointment requests at the moment.</p>
                    `;
                    document.querySelector('.requests-grid').after(noResults);
                }
            } else if(document.getElementById('noResults')) {
                document.getElementById('noResults').remove();
            }
        }
        
        // ===== CANCEL MODAL =====
        function showCancelModal(requestId, childName) {
            document.getElementById('cancelChildName').innerHTML = childName;
            document.getElementById('cancelLink').href = 'cancel_request.php?id=' + requestId;
            document.getElementById('cancelModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('cancelModal').classList.remove('active');
        }
        
        // ===== CLOSE MODAL WHEN CLICKING OUTSIDE =====
        window.onclick = function(event) {
            const modal = document.getElementById('cancelModal');
            if(event.target === modal) {
                closeModal();
            }
        }
        
        // ===== AUTO REFRESH EVERY 30 SECONDS =====
        setTimeout(() => {
            location.reload();
        }, 30000);
        
        // ===== ADD ANIMATION DELAYS =====
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.request-card');
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });
            
            // Get status from URL
            const urlParams = new URLSearchParams(window.location.search);
            const status = urlParams.get('status');
            if(status && ['pending', 'approved', 'rejected'].includes(status)) {
                filterRequests(status);
            }
        });
    </script>
</body>
</html>
<?php mysqli_close($connection); ?>