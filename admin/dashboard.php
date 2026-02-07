<?php
session_start();
if(!isset($_SESSION["logged_in"]) || $_SESSION["user_type"] != "admin"){
    header("location:../login.php");
    exit();
}

include("../dbconnection.php");

$user_id = $_SESSION["user_id"];

// Get admin details
$query_admin = "SELECT full_name FROM admins WHERE user_id = '$user_id'";
$result_admin = mysqli_query($connection, $query_admin);
$admin_data = mysqli_fetch_assoc($result_admin);
$admin_name = $admin_data['full_name'];

// Get statistics
$query_children = "SELECT COUNT(*) as total FROM children WHERE is_active = 1";
$result_children = mysqli_query($connection, $query_children);
$children_count = mysqli_fetch_assoc($result_children)['total'];

$query_hospitals = "SELECT COUNT(*) as total FROM hospitals WHERE is_active = 1";
$result_hospitals = mysqli_query($connection, $query_hospitals);
$hospitals_count = mysqli_fetch_assoc($result_hospitals)['total'];

$query_pending = "SELECT COUNT(*) as total FROM appointment_requests WHERE request_status = 'pending'";
$result_pending = mysqli_query($connection, $query_pending);
$pending_count = mysqli_fetch_assoc($result_pending)['total'];

$query_today = "SELECT COUNT(*) as total FROM vaccination_bookings 
                WHERE appointment_date = CURDATE() AND booking_status = 'scheduled'";
$result_today = mysqli_query($connection, $query_today);
$today_count = mysqli_fetch_assoc($result_today)['total'];

// Get completed vaccinations
$query_completed = "SELECT COUNT(*) as total FROM vaccination_bookings WHERE booking_status = 'completed'";
$result_completed = mysqli_query($connection, $query_completed);
$completed_count = mysqli_fetch_assoc($result_completed)['total'];

// Get total parents
$query_parents = "SELECT COUNT(*) as total FROM parents";
$result_parents = mysqli_query($connection, $query_parents);
$parents_count = mysqli_fetch_assoc($result_parents)['total'];

// Get recent bookings
$query_recent_bookings = "SELECT 
    vb.booking_id, c.full_name as child_name, v.vaccine_name,
    h.hospital_name, vb.appointment_date, vb.appointment_time
    FROM vaccination_bookings vb
    JOIN children c ON vb.child_id = c.child_id
    JOIN vaccines v ON vb.vaccine_id = v.vaccine_id
    JOIN hospitals h ON vb.hospital_id = h.hospital_id
    WHERE vb.booking_status = 'scheduled'
    ORDER BY vb.appointment_date, vb.appointment_time
    LIMIT 5";
$result_bookings = mysqli_query($connection, $query_recent_bookings);

// Get recent activities
$query_recent = "SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 8";
$result_recent = mysqli_query($connection, $query_recent);

// Get monthly stats for chart
$query_monthly = "SELECT 
    MONTHNAME(created_at) as month,
    COUNT(*) as count
    FROM vaccination_bookings 
    WHERE YEAR(created_at) = YEAR(CURDATE())
    GROUP BY MONTH(created_at)
    ORDER BY MONTH(created_at)";
$result_monthly = mysqli_query($connection, $query_monthly);

$monthly_data = [];
$months = [];
$counts = [];

while($row = mysqli_fetch_assoc($result_monthly)) {
    $monthly_data[] = $row;
    $months[] = substr($row['month'], 0, 3);
    $counts[] = $row['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Vaccine Management System</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
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
            --primary: #4361ee;
            --secondary: #3a0ca3;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #f8961e;
            --info: #7209b7;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --border-radius: 16px;
            --box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }
        
        /* Dashboard Layout */
        .dashboard-layout {
            display: grid;
            grid-template-columns: 250px 1fr;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 30px 20px;
            border-right: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 5px 0 30px rgba(0, 0, 0, 0.1);
            position: relative;
            z-index: 100;
        }
        
        .sidebar-header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 20px;
            border-bottom: 2px solid rgba(67, 97, 238, 0.1);
        }
        
        .sidebar-header h2 {
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 24px;
            font-weight: 700;
        }
        
        .sidebar-header p {
            color: var(--gray);
            font-size: 12px;
            margin-top: 5px;
        }
        
        .admin-profile {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: rgba(67, 97, 238, 0.1);
            border-radius: 12px;
            margin-bottom: 30px;
        }
        
        .admin-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }
        
        .admin-info h4 {
            color: var(--dark);
            margin-bottom: 5px;
        }
        
        .admin-info p {
            color: var(--primary);
            font-size: 12px;
            font-weight: 600;
        }
        
        .nav-menu {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            border-radius: 12px;
            color: var(--dark);
            text-decoration: none;
            transition: var(--transition);
        }
        
        .nav-item:hover {
            background: rgba(67, 97, 238, 0.1);
            transform: translateX(5px);
        }
        
        .nav-item.active {
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            color: white;
            box-shadow: 0 5px 20px rgba(67, 97, 238, 0.3);
        }
        
        .nav-item i {
            width: 20px;
            text-align: center;
        }
        
        .logout-btn {
            margin-top: auto;
            background: rgba(247, 37, 133, 0.1);
            color: var(--danger);
            border: none;
            border-radius: 12px;
            padding: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
            cursor: pointer;
            transition: var(--transition);
            width: 100%;
            font-size: 16px;
        }
        
        .logout-btn:hover {
            background: var(--danger);
            color: white;
            transform: translateY(-3px);
        }
        
        /* Main Content */
        .main-content {
            padding: 30px;
            overflow-y: auto;
        }
        
        /* Header */
        .dashboard-header {
            margin-bottom: 30px;
        }
        
        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .welcome-message h1 {
            color: white;
            font-size: 2.5rem;
            margin-bottom: 10px;
        }
        
        .welcome-message p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 1.1rem;
        }
        
        .header-actions {
            display: flex;
            gap: 15px;
        }
        
        .action-icon {
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .action-icon:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-3px);
        }
        
        .stats-banner {
            background: rgba(255, 255, 255, 0.95);
            border-radius: var(--border-radius);
            padding: 25px 30px;
            box-shadow: var(--box-shadow);
            backdrop-filter: blur(10px);
            animation: slideInDown 0.6s ease-out;
        }
        
        .date-display {
            color: var(--gray);
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.6s ease-out;
        }
        
        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }
        
        .stat-card:nth-child(2)::before { background: linear-gradient(90deg, #f8961e, #f3722c); }
        .stat-card:nth-child(3)::before { background: linear-gradient(90deg, #f72585, #b5179e); }
        .stat-card:nth-child(4)::before { background: linear-gradient(90deg, #4cc9f0, #4895ef); }
        .stat-card:nth-child(5)::before { background: linear-gradient(90deg, #7209b7, #560bad); }
        .stat-card:nth-child(6)::before { background: linear-gradient(90deg, #10b981, #059669); }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            color: white;
            font-size: 24px;
        }
        
        .stat-card:nth-child(1) .stat-icon { background: linear-gradient(135deg, #4361ee, #3a0ca3); }
        .stat-card:nth-child(2) .stat-icon { background: linear-gradient(135deg, #f8961e, #f3722c); }
        .stat-card:nth-child(3) .stat-icon { background: linear-gradient(135deg, #f72585, #b5179e); }
        .stat-card:nth-child(4) .stat-icon { background: linear-gradient(135deg, #4cc9f0, #4895ef); }
        .stat-card:nth-child(5) .stat-icon { background: linear-gradient(135deg, #7209b7, #560bad); }
        .stat-card:nth-child(6) .stat-icon { background: linear-gradient(135deg, #10b981, #059669); }
        
        .stat-content h3 {
            font-size: 32px;
            color: var(--dark);
            margin-bottom: 5px;
        }
        
        .stat-content p {
            color: var(--gray);
            font-size: 14px;
        }
        
        .stat-trend {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            margin-top: 10px;
        }
        
        .trend-up { color: #10b981; }
        .trend-down { color: #ef4444; }
        
        /* Charts Section */
        .charts-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        @media (max-width: 1200px) {
            .charts-section {
                grid-template-columns: 1fr;
            }
        }
        
        .chart-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--box-shadow);
            animation: fadeIn 0.6s ease-out 0.3s both;
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        
        .chart-header h3 {
            color: var(--dark);
            font-size: 1.3rem;
        }
        
        .chart-container {
            height: 300px;
            position: relative;
        }
        
        /* Quick Actions */
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .quick-action-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            text-align: center;
            animation: fadeInUp 0.6s ease-out 0.4s both;
        }
        
        .quick-action-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }
        
        .action-icon-large {
            width: 70px;
            height: 70px;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 30px;
        }
        
        .action-1 .action-icon-large { background: linear-gradient(135deg, #4361ee, #3a0ca3); }
        .action-2 .action-icon-large { background: linear-gradient(135deg, #f72585, #b5179e); }
        .action-3 .action-icon-large { background: linear-gradient(135deg, #4cc9f0, #4895ef); }
        .action-4 .action-icon-large { background: linear-gradient(135deg, #10b981, #059669); }
        .action-5 .action-icon-large { background: linear-gradient(135deg, #f8961e, #f3722c); }
        
        .quick-action-card h4 {
            color: var(--dark);
            margin-bottom: 10px;
        }
        
        .quick-action-card p {
            color: var(--gray);
            font-size: 13px;
            margin-bottom: 20px;
        }
        
        .action-btn {
            display: inline-block;
            padding: 10px 20px;
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary);
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            transition: var(--transition);
        }
        
        .action-btn:hover {
            background: var(--primary);
            color: white;
        }
        
        /* Tables Section */
        .tables-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        @media (max-width: 1200px) {
            .tables-section {
                grid-template-columns: 1fr;
            }
        }
        
        .table-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--box-shadow);
            animation: fadeIn 0.6s ease-out 0.5s both;
        }
        
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }
        
        .table-header h3 {
            color: var(--dark);
            font-size: 1.3rem;
        }
        
        .view-all {
            color: var(--primary);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table thead {
            background: #f8fafc;
        }
        
        .data-table th {
            padding: 15px;
            text-align: left;
            color: #64748b;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .data-table tbody tr {
            border-bottom: 1px solid #f1f5f9;
            transition: var(--transition);
        }
        
        .data-table tbody tr:hover {
            background: #f8fafc;
        }
        
        .data-table td {
            padding: 15px;
            color: #334155;
            font-size: 14px;
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-pending { background: rgba(248, 150, 30, 0.1); color: #f8961e; }
        .status-scheduled { background: rgba(67, 97, 238, 0.1); color: #4361ee; }
        .status-completed { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes fadeInUp {
            from { 
                opacity: 0; 
                transform: translateY(30px); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0); 
            }
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
        
        /* Responsive */
        @media (max-width: 1024px) {
            .dashboard-layout {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                position: fixed;
                left: -300px;
                top: 0;
                bottom: 0;
                width: 250px;
                transition: var(--transition);
                z-index: 1000;
            }
            
            .sidebar.active {
                left: 0;
            }
            
            .menu-toggle {
                position: fixed;
                top: 20px;
                left: 20px;
                z-index: 1001;
                background: white;
                width: 50px;
                height: 50px;
                border-radius: 12px;
                display: flex;
                align-items: center;
                justify-content: center;
                color: var(--primary);
                cursor: pointer;
                box-shadow: var(--box-shadow);
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
            
            .charts-section,
            .tables-section {
                grid-template-columns: 1fr;
            }
            
            .header-top {
                flex-direction: column;
                gap: 20px;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <div class="menu-toggle" id="menuToggle">
        <i class="fas fa-bars"></i>
    </div>
    
    <div class="dashboard-layout">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2>Vaccine<span>Admin</span></h2>
                <p>Child Vaccination System</p>
            </div>
            
            <div class="admin-profile">
                <div class="admin-avatar">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="admin-info">
                    <h4><?php echo htmlspecialchars($admin_name); ?></h4>
                    <p>Administrator</p>
                </div>
            </div>
            
            <nav class="nav-menu">
                <a href="dashboard.php" class="nav-item active">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="manage_children.php" class="nav-item">
                    <i class="fas fa-baby"></i>
                    <span>Children</span>
                </a>
                <a href="manage_hospitals.php" class="nav-item">
                    <i class="fas fa-hospital"></i>
                    <span>Hospitals</span>
                </a>
                <a href="appointment_requests.php" class="nav-item">
                    <i class="fas fa-calendar-check"></i>
                    <span>Requests</span>
                </a>
                <a href="manage_vaccines.php" class="nav-item">
                    <i class="fas fa-syringe"></i>
                    <span>Vaccines</span>
                </a>
                <a href="booking_details.php" class="nav-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Bookings</span>
                </a>
                <a href="vaccination_reports.php" class="nav-item">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
                <a href="system_settings.php" class="nav-item">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </nav>
            
            <button class="logout-btn" onclick="window.location.href='../logout.php'">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </button>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <div class="dashboard-header">
                <div class="header-top">
                    <div class="welcome-message">
                        <h1>Welcome back, <?php echo htmlspecialchars(explode(' ', $admin_name)[0]); ?>! 👋</h1>
                        <p>Here's what's happening with your system today.</p>
                    </div>
                    <div class="header-actions">
                        <div class="action-icon" title="Notifications">
                            <i class="fas fa-bell"></i>
                        </div>
                        <div class="action-icon" title="Messages">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="action-icon" title="Settings" onclick="window.location.href='system_settings.php'">
                            <i class="fas fa-cog"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stats-banner">
                    <div class="date-display">
                        <i class="fas fa-calendar-alt"></i>
                        <?php echo date('l, F j, Y'); ?>
                    </div>
                    <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="width: 12px; height: 12px; background: #4361ee; border-radius: 50%;"></div>
                            <span style="color: #64748b; font-size: 14px;">Everything is running smoothly</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="width: 12px; height: 12px; background: #10b981; border-radius: 50%;"></div>
                            <span style="color: #64748b; font-size: 14px;">System uptime: 99.9%</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Stats Grid -->
            <div class="stats-grid">
                <!-- Children -->
                <div class="stat-card animate__animated animate__fadeInUp">
                    <div class="stat-icon">
                        <i class="fas fa-baby"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $children_count; ?></h3>
                        <p>Registered Children</p>
                        <div class="stat-trend trend-up">
                            <i class="fas fa-arrow-up"></i>
                            <span>+12% from last month</span>
                        </div>
                    </div>
                </div>
                
                <!-- Hospitals -->
                <div class="stat-card animate__animated animate__fadeInUp" style="animation-delay: 0.1s">
                    <div class="stat-icon">
                        <i class="fas fa-hospital"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $hospitals_count; ?></h3>
                        <p>Active Hospitals</p>
                        <div class="stat-trend trend-up">
                            <i class="fas fa-arrow-up"></i>
                            <span>+2 new this month</span>
                        </div>
                    </div>
                </div>
                
                <!-- Pending Requests -->
                <div class="stat-card animate__animated animate__fadeInUp" style="animation-delay: 0.2s">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $pending_count; ?></h3>
                        <p>Pending Requests</p>
                        <?php if($pending_count > 0): ?>
                        <div class="stat-trend trend-down" style="color: #f8961e;">
                            <i class="fas fa-exclamation-circle"></i>
                            <span>Needs attention</span>
                        </div>
                        <?php else: ?>
                        <div class="stat-trend trend-up">
                            <i class="fas fa-check-circle"></i>
                            <span>All caught up</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Today's Appointments -->
                <div class="stat-card animate__animated animate__fadeInUp" style="animation-delay: 0.3s">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $today_count; ?></h3>
                        <p>Today's Appointments</p>
                        <div class="stat-trend trend-up">
                            <i class="fas fa-calendar-check"></i>
                            <span>Scheduled for today</span>
                        </div>
                    </div>
                </div>
                
                <!-- Completed Vaccinations -->
                <div class="stat-card animate__animated animate__fadeInUp" style="animation-delay: 0.4s">
                    <div class="stat-icon">
                        <i class="fas fa-syringe"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $completed_count; ?></h3>
                        <p>Completed Vaccinations</p>
                        <div class="stat-trend trend-up">
                            <i class="fas fa-arrow-up"></i>
                            <span>+24% this month</span>
                        </div>
                    </div>
                </div>
                
                <!-- Total Parents -->
                <div class="stat-card animate__animated animate__fadeInUp" style="animation-delay: 0.5s">
                    <div class="stat-icon">
                        <i class="fas fa-user-friends"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?php echo $parents_count; ?></h3>
                        <p>Registered Parents</p>
                        <div class="stat-trend trend-up">
                            <i class="fas fa-arrow-up"></i>
                            <span>+8% from last month</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Charts Section -->
            <div class="charts-section">
                <!-- Monthly Bookings Chart -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="fas fa-chart-line"></i> Monthly Vaccination Bookings</h3>
                        <select style="padding: 8px 15px; border-radius: 8px; border: 2px solid #e2e8f0; background: white; color: #334155;">
                            <option>2024</option>
                            <option>2023</option>
                        </select>
                    </div>
                    <div class="chart-container">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>
                
                <!-- Status Distribution -->
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="fas fa-chart-pie"></i> Request Status</h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="quick-actions-grid">
                <div class="quick-action-card action-1">
                    <div class="action-icon-large">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h4>Approve Requests</h4>
                    <p>Review and approve pending vaccination appointment requests</p>
                    <a href="appointment_requests.php" class="action-btn">View Requests</a>
                </div>
                
                <div class="quick-action-card action-2">
                    <div class="action-icon-large">
                        <i class="fas fa-hospital"></i>
                    </div>
                    <h4>Manage Hospitals</h4>
                    <p>Add, edit or remove hospitals from the vaccination network</p>
                    <a href="manage_hospitals.php" class="action-btn">Manage</a>
                </div>
                
                <div class="quick-action-card action-3">
                    <div class="action-icon-large">
                        <i class="fas fa-syringe"></i>
                    </div>
                    <h4>Vaccine Inventory</h4>
                    <p>Track vaccine stock and manage inventory levels</p>
                    <a href="manage_vaccines.php" class="action-btn">Check Stock</a>
                </div>
                
                <div class="quick-action-card action-4">
                    <div class="action-icon-large">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <h4>Generate Reports</h4>
                    <p>Create vaccination reports and analytics for insights</p>
                    <a href="vaccination_reports.php" class="action-btn">View Reports</a>
                </div>
                
                <div class="quick-action-card action-5">
                    <div class="action-icon-large">
                        <i class="fas fa-bell"></i>
                    </div>
                    <h4>Send Notifications</h4>
                    <p>Send reminders and notifications to parents</p>
                    <a href="send_notifications.php" class="action-btn">Send</a>
                </div>
            </div>
            
            <!-- Tables Section -->
            <div class="tables-section">
                <!-- Recent Activities -->
                <div class="table-card">
                    <div class="table-header">
                        <h3><i class="fas fa-history"></i> Recent Activities</h3>
                        <a href="activity_logs.php" class="view-all">View All</a>
                    </div>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Action</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(mysqli_num_rows($result_recent) > 0): ?>
                                <?php while($row = mysqli_fetch_assoc($result_recent)): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600; color: #334155;">
                                            <?php echo date('h:i A', strtotime($row['created_at'])); ?>
                                        </div>
                                        <div style="font-size: 12px; color: #64748b;">
                                            <?php echo date('M d', strtotime($row['created_at'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                        $action_color = '#64748b';
                                        $action_icon = 'fas fa-circle';
                                        
                                        if($row['action_type'] == 'login') {
                                            $action_color = '#10b981';
                                            $action_icon = 'fas fa-sign-in-alt';
                                        } elseif($row['action_type'] == 'approve') {
                                            $action_color = '#4361ee';
                                            $action_icon = 'fas fa-check-circle';
                                        } elseif($row['action_type'] == 'reject') {
                                            $action_color = '#ef4444';
                                            $action_icon = 'fas fa-times-circle';
                                        } elseif($row['action_type'] == 'create') {
                                            $action_color = '#8b5cf6';
                                            $action_icon = 'fas fa-plus-circle';
                                        }
                                        ?>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <i class="<?php echo $action_icon; ?>" style="color: <?php echo $action_color; ?>;"></i>
                                            <span style="text-transform: capitalize; font-weight: 600; color: #334155;">
                                                <?php echo $row['action_type']; ?>
                                            </span>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="color: #334155; font-size: 13px;">
                                            <?php echo htmlspecialchars($row['action_description']); ?>
                                        </div>
                                        <div style="font-size: 11px; color: #64748b; margin-top: 3px;">
                                            IP: <?php echo $row['ip_address']; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                                <?php else: ?>
                                <tr>
                                    <td colspan="3" style="text-align: center; padding: 30px; color: #64748b;">
                                        <i class="fas fa-history" style="font-size: 24px; margin-bottom: 10px; display: block; color: #e2e8f0;"></i>
                                        No recent activities
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Upcoming Appointments -->
                <div class="table-card">
                    <div class="table-header">
                        <h3><i class="fas fa-calendar-alt"></i> Upcoming Appointments</h3>
                        <a href="booking_details.php" class="view-all">View All</a>
                    </div>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Child</th>
                                    <th>Vaccine</th>
                                    <th>Hospital</th>
                                    <th>Date & Time</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(mysqli_num_rows($result_bookings) > 0): ?>
                                <?php while($row = mysqli_fetch_assoc($result_bookings)): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight: 600; color: #334155;">
                                            <?php echo htmlspecialchars($row['child_name']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="color: #334155; font-size: 13px;">
                                            <?php echo htmlspecialchars($row['vaccine_name']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="color: #64748b; font-size: 12px;">
                                            <?php echo htmlspecialchars($row['hospital_name']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; color: #334155;">
                                            <?php echo date('M d', strtotime($row['appointment_date'])); ?>
                                        </div>
                                        <div style="font-size: 12px; color: #64748b;">
                                            <?php echo date('h:i A', strtotime($row['appointment_time'])); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge status-scheduled">
                                            <i class="fas fa-clock"></i> Scheduled
                                        </span>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                                <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 30px; color: #64748b;">
                                        <i class="fas fa-calendar" style="font-size: 24px; margin-bottom: 10px; display: block; color: #e2e8f0;"></i>
                                        No upcoming appointments
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        // Mobile menu toggle
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });
        
        // Initialize charts when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Monthly Bookings Chart
            const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
            const monthlyChart = new Chart(monthlyCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($months); ?>,
                    datasets: [{
                        label: 'Vaccination Bookings',
                        data: <?php echo json_encode($counts); ?>,
                        borderColor: '#4361ee',
                        backgroundColor: 'rgba(67, 97, 238, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#4361ee',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 6,
                        pointHoverRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: true,
                            position: 'top',
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            },
                            ticks: {
                                stepSize: 5
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
            
            // Status Distribution Chart
            const statusCtx = document.getElementById('statusChart').getContext('2d');
            const statusChart = new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Pending', 'Approved', 'Completed', 'Cancelled'],
                    datasets: [{
                        data: [<?php echo $pending_count; ?>, 
                               <?php echo $today_count; ?>, 
                               <?php echo $completed_count; ?>, 
                               <?php echo $pending_count > 0 ? 0 : 5; ?>],
                        backgroundColor: [
                            'rgba(248, 150, 30, 0.8)',
                            'rgba(67, 97, 238, 0.8)',
                            'rgba(16, 185, 129, 0.8)',
                            'rgba(239, 68, 68, 0.8)'
                        ],
                        borderColor: [
                            '#f8961e',
                            '#4361ee',
                            '#10b981',
                            '#ef4444'
                        ],
                        borderWidth: 2,
                        hoverOffset: 15
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                        }
                    },
                    cutout: '70%'
                }
            });
            
            // Animate stats cards on scroll
            const statsCards = document.querySelectorAll('.stat-card');
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.animationPlayState = 'running';
                    }
                });
            }, { threshold: 0.1 });
            
            statsCards.forEach(card => {
                observer.observe(card);
            });
            
            // Auto-refresh data every 30 seconds
            setInterval(() => {
                // In a real app, you would fetch new data here
                console.log('Auto-refreshing dashboard data...');
            }, 30000);
        });
        
        // Show greeting based on time of day
        const hour = new Date().getHours();
        const greeting = document.querySelector('.welcome-message h1');
        if (greeting) {
            if (hour < 12) {
                greeting.innerHTML = greeting.innerHTML.replace('Welcome back', 'Good morning');
            } else if (hour < 18) {
                greeting.innerHTML = greeting.innerHTML.replace('Welcome back', 'Good afternoon');
            } else {
                greeting.innerHTML = greeting.innerHTML.replace('Welcome back', 'Good evening');
            }
        }
        
        // Add animation to quick action cards
        const actionCards = document.querySelectorAll('.quick-action-card');
        actionCards.forEach((card, index) => {
            card.style.animationDelay = `${index * 0.1 + 0.4}s`;
        });
    </script>
</body>
</html>
<?php mysqli_close($connection); ?>