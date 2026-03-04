<?php
session_start();
if(!isset($_SESSION["logged_in"]) || $_SESSION["user_type"] != "admin"){
    header("location:../login.php");
    exit();
}

include("../dbconnection.php");

$user_id = $_SESSION["user_id"];

$query_admin = "SELECT full_name FROM admins WHERE user_id = '$user_id'";
$result_admin = mysqli_query($connection, $query_admin);
$admin_data = mysqli_fetch_assoc($result_admin);
$admin_name = $admin_data['full_name'];

$query_children = "SELECT COUNT(*) as total FROM children WHERE is_active = 1";
$result_children = mysqli_query($connection, $query_children);
$children_count = mysqli_fetch_assoc($result_children)['total'];

$query_hospitals = "SELECT COUNT(*) as total FROM hospitals WHERE is_active = 1";
$result_hospitals = mysqli_query($connection, $query_hospitals);
$hospitals_count = mysqli_fetch_assoc($result_hospitals)['total'];

$query_pending = "SELECT COUNT(*) as total FROM appointment_requests WHERE request_status = 'pending'";
$result_pending = mysqli_query($connection, $query_pending);
$pending_count = mysqli_fetch_assoc($result_pending)['total'];

$query_today = "SELECT COUNT(*) as total FROM vaccination_bookings WHERE appointment_date = CURDATE() AND booking_status = 'scheduled'";
$result_today = mysqli_query($connection, $query_today);
$today_count = mysqli_fetch_assoc($result_today)['total'];

$query_completed = "SELECT COUNT(*) as total FROM vaccination_bookings WHERE booking_status = 'completed'";
$result_completed = mysqli_query($connection, $query_completed);
$completed_count = mysqli_fetch_assoc($result_completed)['total'];

$query_parents = "SELECT COUNT(*) as total FROM parents";
$result_parents = mysqli_query($connection, $query_parents);
$parents_count = mysqli_fetch_assoc($result_parents)['total'];

$query_recent_bookings = "SELECT vb.booking_id, c.full_name as child_name, v.vaccine_name, h.hospital_name, vb.appointment_date, vb.appointment_time
    FROM vaccination_bookings vb
    JOIN children c ON vb.child_id = c.child_id
    JOIN vaccines v ON vb.vaccine_id = v.vaccine_id
    JOIN hospitals h ON vb.hospital_id = h.hospital_id
    WHERE vb.booking_status = 'scheduled'
    ORDER BY vb.appointment_date, vb.appointment_time LIMIT 5";
$result_bookings = mysqli_query($connection, $query_recent_bookings);

$query_recent = "SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 8";
$result_recent = mysqli_query($connection, $query_recent);

$query_monthly = "SELECT MONTHNAME(created_at) as month, COUNT(*) as count FROM vaccination_bookings WHERE YEAR(created_at) = YEAR(CURDATE()) GROUP BY MONTH(created_at) ORDER BY MONTH(created_at)";
$result_monthly = mysqli_query($connection, $query_monthly);

$months = []; $counts = [];
while($row = mysqli_fetch_assoc($result_monthly)) {
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
   <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        :root {
            --primary: #2563eb; --primary-dark: #1d4ed8; --primary-light: #60a5fa;
            --primary-soft: #dbeafe; --white: #ffffff; --white-off: #f8fafc;
            --gray-50: #f1f5f9; --gray-100: #e2e8f0; --gray-200: #cbd5e1;
            --gray-300: #94a3b8; --gray-400: #64748b; --gray-500: #475569;
            --gray-600: #334155; --gray-700: #1e293b;
            --shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            --shadow-md: 0 10px 15px -3px rgba(0,0,0,0.05);
            --shadow-blue: 0 10px 25px -5px rgba(37,99,235,0.1);
            --radius-sm: 8px; --radius: 12px; --radius-md: 16px;
            --radius-lg: 20px; --radius-xl: 24px;
            --transition: all 0.2s ease;
        }
        body { background: linear-gradient(145deg, var(--white-off), var(--white)); min-height: 100vh; color: var(--gray-600); }
        .dashboard-layout { display: block; min-height: 100vh; }

        /* NAVBAR */
        .admin-navbar {
            background: #fff; border-bottom: 2px solid #e8eeff; padding: 0 35px;
            display: flex; justify-content: space-between; align-items: center;
            height: 68px; box-shadow: 0 2px 16px rgba(26,111,196,0.08);
            position: sticky; top: 0; z-index: 100;
        }
        .admin-navbar .logo { display: flex; align-items: center; gap: 10px; }
        .admin-navbar .logo-icon { width: 40px; height: 40px; background: linear-gradient(135deg, #1a6fc4, #1155a0); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; color: white; }
        .admin-navbar .logo h2 { font-size: 20px; font-weight: 700; color: #1155a0; }
        .nav-links { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
        .nav-links a { color: #4b6cb7; text-decoration: none; padding: 8px 14px; border-radius: 8px; font-size: 13.5px; font-weight: 500; transition: all 0.2s; display: flex; align-items: center; gap: 6px; }
        .nav-links a:hover { background: #eff6ff; color: #1155a0; }
        .nav-links a.active { background: #dbeafe; color: #1155a0; font-weight: 600; }
        .nav-links a.logout { background: #fee2e2; color: #dc2626; }
        .nav-links a.logout:hover { background: #fecaca; }

        /* MAIN */
        .main-content { padding: 30px; background: var(--white-off); }

        /* DASHBOARD HEADER */
        .dashboard-header {
            margin-bottom: 30px;
            background-image: url('https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?w=1800&q=85');
            background-size: cover; background-position: center;
            border-radius: 18px; position: relative; overflow: hidden;
            padding: 55px 55px 42px;
            box-shadow: 0 8px 30px rgba(26,111,196,0.15); min-height: 220px;
        }
        .dashboard-header::before {
            content: ''; position: absolute; inset: 0;
            background: linear-gradient(135deg, rgba(26,111,196,0.82) 0%, rgba(17,85,160,0.75) 100%);
            z-index: 1;
        }
        .dashboard-header > * { position: relative; z-index: 2; }
        .header-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .welcome-message h1 { color: #fff; font-size: 26px; font-weight: 600; margin-bottom: 5px; }
        .welcome-message p { color: rgba(255,255,255,0.8); font-size: 14px; display: flex; align-items: center; gap: 6px; }
        .header-actions { display: flex; gap: 10px; }
        .action-icon { width: 42px; height: 42px; background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); border-radius: var(--radius); display: flex; align-items: center; justify-content: center; color: white; cursor: pointer; transition: var(--transition); }
        .action-icon:hover { background: rgba(255,255,255,0.35); transform: translateY(-2px); }
        .stats-banner { background: rgba(255,255,255,0.15); border-radius: var(--radius-lg); padding: 18px 22px; border: 1px solid rgba(255,255,255,0.25); }
        .date-display { color: rgba(255,255,255,0.85); font-size: 13px; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; }

        /* STATS GRID */
        /* ===== STATS GRID ===== */
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 22px; margin-bottom: 30px; }

        .stat-card {
            background: var(--white); border-radius: 18px; padding: 0;
            box-shadow: 0 4px 20px rgba(37,99,235,0.08);
            transition: var(--transition); border: 1px solid var(--gray-100);
            overflow: hidden; position: relative;
        }
        .stat-card:hover { transform: translateY(-6px); box-shadow: 0 16px 40px rgba(37,99,235,0.16); }

        .stat-card:nth-child(1) .stat-top { background: linear-gradient(135deg, #2563eb, #1d4ed8); }
        .stat-card:nth-child(2) .stat-top { background: linear-gradient(135deg, #0891b2, #0e7490); }
        .stat-card:nth-child(3) .stat-top { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .stat-card:nth-child(4) .stat-top { background: linear-gradient(135deg, #8b5cf6, #7c3aed); }
        .stat-card:nth-child(5) .stat-top { background: linear-gradient(135deg, #10b981, #059669); }
        .stat-card:nth-child(6) .stat-top { background: linear-gradient(135deg, #ef4444, #dc2626); }

        .stat-top {
            padding: 22px 24px 18px;
            display: flex; justify-content: space-between; align-items: flex-start;
        }
        .stat-top-left h3 { font-size: 36px; font-weight: 800; color: white; margin-bottom: 4px; line-height: 1; }
        .stat-top-left p { font-size: 13px; color: rgba(255,255,255,0.82); font-weight: 500; }

        .stat-icon {
            width: 52px; height: 52px;
            background: rgba(255,255,255,0.22);
            border-radius: 14px; display: flex; align-items: center; justify-content: center;
            color: white; font-size: 22px; transition: var(--transition);
            border: 1.5px solid rgba(255,255,255,0.3); flex-shrink: 0;
        }
        .stat-card:hover .stat-icon { transform: rotate(8deg) scale(1.1); background: rgba(255,255,255,0.32); }

        .stat-bottom {
            padding: 12px 24px 16px; background: white;
            display: flex; align-items: center; justify-content: space-between;
        }

        .stat-trend {
            display: inline-flex; align-items: center; gap: 5px;
            font-size: 12px; font-weight: 600; padding: 5px 12px; border-radius: 30px;
            background: var(--white-off);
        }
        .trend-up { color: #10b981; }
        .trend-down { color: #f59e0b; }

        .stat-mini-bar { height: 4px; width: 65px; background: var(--gray-100); border-radius: 10px; overflow: hidden; }
        .stat-mini-bar-fill { height: 100%; border-radius: 10px; }
        .stat-card:nth-child(1) .stat-mini-bar-fill { background: #2563eb; width: 72%; }
        .stat-card:nth-child(2) .stat-mini-bar-fill { background: #0891b2; width: 60%; }
        .stat-card:nth-child(3) .stat-mini-bar-fill { background: #f59e0b; width: 45%; }
        .stat-card:nth-child(4) .stat-mini-bar-fill { background: #8b5cf6; width: 80%; }
        .stat-card:nth-child(5) .stat-mini-bar-fill { background: #10b981; width: 90%; }
        .stat-card:nth-child(6) .stat-mini-bar-fill { background: #ef4444; width: 55%; }

        .stat-content { display: none; }

        /* CHARTS */
        .charts-section { display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 30px; }
        .chart-card { background: var(--white); border-radius: var(--radius-lg); padding: 22px; box-shadow: var(--shadow); border: 1px solid var(--gray-100); }
        .chart-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; }
        .chart-header h3 { color: var(--gray-700); font-size: 16px; font-weight: 600; }
        .chart-container { height: 280px; position: relative; }

        /* ===== QUICK ACTIONS WITH BACKGROUND IMAGES ===== */
        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 18px;
            margin-bottom: 30px;
        }

        .quick-action-card {
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-md);
            border: none;
            text-align: center;
            transition: all 0.35s ease;
            position: relative;
            min-height: 230px;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
        }

        /* Each card ki alag background image */
        .quick-action-card.action-1 {
            background: url('https://images.unsplash.com/photo-1631815588090-d1bcbe9b4b38?w=500&q=80') center/cover no-repeat;
        }
        .quick-action-card.action-2 {
            background: url('https://images.unsplash.com/photo-1586773860418-d37222d8fce3?w=500&q=80') center/cover no-repeat;
        }
        .quick-action-card.action-3 {
            background: url('https://images.unsplash.com/photo-1584308666744-24d5c474f2ae?w=500&q=80') center/cover no-repeat;
        }
        .quick-action-card.action-4 {
            background: url('https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=500&q=80') center/cover no-repeat;
        }
        .quick-action-card.action-5 {
            background: url('https://images.unsplash.com/photo-1611532736597-de2d4265fba3?w=500&q=80') center/cover no-repeat;
        }

        /* Dark gradient overlay — neeche se upar */
        .quick-action-card::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(to bottom, rgba(0,0,0,0.05) 0%, rgba(10,25,70,0.78) 100%);
            transition: all 0.35s ease;
            z-index: 1;
        }
        .quick-action-card:hover::before {
            background: linear-gradient(to bottom, rgba(37,99,235,0.2) 0%, rgba(10,25,70,0.90) 100%);
        }
        .quick-action-card:hover {
            transform: translateY(-7px);
            box-shadow: 0 22px 45px rgba(37, 99, 235, 0.28);
        }

        /* Content z-index upar */
        .qa-content {
            position: relative;
            z-index: 2;
            padding: 22px 18px;
        }

        .action-icon-large {
            width: 54px;
            height: 54px;
            background: rgba(255,255,255,0.18);
            backdrop-filter: blur(8px);
            border: 1.5px solid rgba(255,255,255,0.45);
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 14px;
            color: white;
            font-size: 22px;
            transition: var(--transition);
        }
        .quick-action-card:hover .action-icon-large {
            background: rgba(255,255,255,0.28);
            transform: scale(1.1) rotate(3deg);
        }

        .quick-action-card h4 {
            color: #ffffff;
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 7px;
            text-shadow: 0 1px 6px rgba(0,0,0,0.5);
        }

        .quick-action-card p {
            color: rgba(255,255,255,0.80);
            font-size: 12px;
            margin-bottom: 16px;
            line-height: 1.55;
        }

        .action-btn {
            display: inline-block;
            padding: 8px 22px;
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(6px);
            color: #ffffff;
            border-radius: 30px;
            text-decoration: none;
            font-weight: 600;
            font-size: 12px;
            border: 1.5px solid rgba(255,255,255,0.55);
            transition: var(--transition);
            letter-spacing: 0.3px;
        }
        .action-btn:hover {
            background: rgba(255,255,255,0.30);
            border-color: white;
        }

        /* TABLES */
        .tables-section { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        .table-card { background: var(--white); border-radius: var(--radius-lg); padding: 22px; box-shadow: var(--shadow); border: 1px solid var(--gray-100); }
        .table-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 18px; }
        .table-header h3 { color: var(--gray-700); font-size: 16px; font-weight: 600; }
        .view-all { color: var(--primary); text-decoration: none; font-size: 13px; font-weight: 500; display: flex; align-items: center; gap: 4px; transition: var(--transition); }
        .view-all:hover { color: var(--primary-dark); gap: 6px; }
        .table-container { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table thead { background: var(--white-off); }
        .data-table th { padding: 14px; text-align: left; color: var(--gray-500); font-weight: 600; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 1px solid var(--gray-100); }
        .data-table tbody tr { border-bottom: 1px solid var(--gray-100); transition: var(--transition); }
        .data-table tbody tr:hover { background: var(--white-off); }
        .data-table td { padding: 14px; color: var(--gray-600); font-size: 13px; }
        .status-badge { padding: 5px 12px; border-radius: 30px; font-size: 11px; font-weight: 600; display: inline-block; min-width: 80px; text-align: center; }
        .status-scheduled { background: var(--primary-soft); color: var(--primary-dark); }

        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: var(--gray-50); }
        ::-webkit-scrollbar-thumb { background: var(--gray-200); border-radius: 8px; }

        @media (max-width: 1200px) { .charts-section, .tables-section { grid-template-columns: 1fr; } }
        @media (max-width: 1100px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 768px) {
            .main-content { padding: 20px; }
            .stats-grid { grid-template-columns: 1fr; }
            .stat-top-left h3 { font-size: 28px; }
            .header-top { flex-direction: column; gap: 15px; align-items: flex-start; }
            .quick-actions-grid { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>
    <div class="dashboard-layout">
        <nav class="admin-navbar">
            <div class="logo">
                <div class="logo-icon"><i class="fas fa-shield-alt"></i></div>
                <h2>Admin Panel</h2>
            </div>
            <div class="nav-links">
                <a href="dashboard.php" class="active"> Dashboard</a>
                <a href="manage_children.php"> Children</a>
                <a href="manage_hospitals.php"> Hospitals</a>
                <a href="appointment_requests.php"> Requests</a>
                <a href="managevaccines.php"> Vaccines</a>
                <a href="bookingdetail.php"> Bookings</a>
                <a href="vaccination_reports.php"> Reports</a>
                <a href="system_settings.php"> Settings</a>
                <a href="../logout.php" class="logout"> Logout</a>
            </div>
        </nav>
        
        <main class="main-content">
            <!-- Header -->
            <div class="dashboard-header">
                <div class="header-top">
                    <div class="welcome-message">
                        <h1>Welcome back, <?php echo htmlspecialchars(explode(' ', $admin_name)[0]); ?>!</h1>
                        <p>Here's what's happening with your system today.</p>
                    </div>
                    <div class="header-actions">
                        <div class="action-icon" title="Notifications"><i class="fas fa-bell"></i></div>
                        <div class="action-icon" title="Messages"><i class="fas fa-envelope"></i></div>
                        <div class="action-icon" onclick="window.location.href='system_settings.php'"><i class="fas fa-cog"></i></div>
                    </div>
                </div>
                <div class="stats-banner">
                    <div class="date-display"><i class="fas fa-calendar-alt"></i><?php echo date('l, F j, Y'); ?></div>
                    <div style="display:flex;align-items:center;gap:15px;flex-wrap:wrap;">
                        <div style="display:flex;align-items:center;gap:8px;"><div style="width:12px;height:12px;background:#93c5fd;border-radius:50%;"></div><span style="color:rgba(255,255,255,0.85);font-size:14px;">Everything is running smoothly</span></div>
                        <div style="display:flex;align-items:center;gap:8px;"><div style="width:12px;height:12px;background:#6ee7b7;border-radius:50%;"></div><span style="color:rgba(255,255,255,0.85);font-size:14px;">System uptime: 99.9%</span></div>
                    </div>
                </div>
            </div>
            
            <!-- Stats Grid -->
            <div class="stats-grid">

                <div class="stat-card">
                    <div class="stat-top">
                        <div class="stat-top-left">
                            <h3><?php echo $children_count; ?></h3>
                            <p>Registered Children</p>
                        </div>
                        <div class="stat-icon"><i class="fas fa-baby"></i></div>
                    </div>
                    <div class="stat-bottom">
                        <div class="stat-trend trend-up"><i class="fas fa-arrow-up"></i> +12% last month</div>
                        <div class="stat-mini-bar"><div class="stat-mini-bar-fill"></div></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-top">
                        <div class="stat-top-left">
                            <h3><?php echo $hospitals_count; ?></h3>
                            <p>Active Hospitals</p>
                        </div>
                        <div class="stat-icon"><i class="fas fa-hospital"></i></div>
                    </div>
                    <div class="stat-bottom">
                        <div class="stat-trend trend-up"><i class="fas fa-arrow-up"></i> +2 this month</div>
                        <div class="stat-mini-bar"><div class="stat-mini-bar-fill"></div></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-top">
                        <div class="stat-top-left">
                            <h3><?php echo $pending_count; ?></h3>
                            <p>Pending Requests</p>
                        </div>
                        <div class="stat-icon"><i class="fas fa-clock"></i></div>
                    </div>
                    <div class="stat-bottom">
                        <?php if($pending_count > 0): ?>
                        <div class="stat-trend trend-down"><i class="fas fa-exclamation-circle"></i> Needs attention</div>
                        <?php else: ?>
                        <div class="stat-trend trend-up"><i class="fas fa-check-circle"></i> All caught up</div>
                        <?php endif; ?>
                        <div class="stat-mini-bar"><div class="stat-mini-bar-fill"></div></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-top">
                        <div class="stat-top-left">
                            <h3><?php echo $today_count; ?></h3>
                            <p>Today's Appointments</p>
                        </div>
                        <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
                    </div>
                    <div class="stat-bottom">
                        <div class="stat-trend trend-up"><i class="fas fa-calendar-check"></i> Scheduled today</div>
                        <div class="stat-mini-bar"><div class="stat-mini-bar-fill"></div></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-top">
                        <div class="stat-top-left">
                            <h3><?php echo $completed_count; ?></h3>
                            <p>Completed Vaccinations</p>
                        </div>
                        <div class="stat-icon"><i class="fas fa-syringe"></i></div>
                    </div>
                    <div class="stat-bottom">
                        <div class="stat-trend trend-up"><i class="fas fa-arrow-up"></i> +24% this month</div>
                        <div class="stat-mini-bar"><div class="stat-mini-bar-fill"></div></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-top">
                        <div class="stat-top-left">
                            <h3><?php echo $parents_count; ?></h3>
                            <p>Registered Parents</p>
                        </div>
                        <div class="stat-icon"><i class="fas fa-user-friends"></i></div>
                    </div>
                    <div class="stat-bottom">
                        <div class="stat-trend trend-up"><i class="fas fa-arrow-up"></i> +8% last month</div>
                        <div class="stat-mini-bar"><div class="stat-mini-bar-fill"></div></div>
                    </div>
                </div>

            </div>
            
            <!-- Charts -->
            <div class="charts-section">
                <div class="chart-card">
                    <div class="chart-header">
                        <h3><i class="fas fa-chart-line"></i> Monthly Vaccination Bookings</h3>
                        <select style="padding:8px 15px;border-radius:8px;border:2px solid #e2e8f0;background:white;color:#334155;"><option>2024</option><option>2023</option></select>
                    </div>
                    <div class="chart-container"><canvas id="monthlyChart"></canvas></div>
                </div>
                <div class="chart-card">
                    <div class="chart-header"><h3><i class="fas fa-chart-pie"></i> Request Status</h3></div>
                    <div class="chart-container"><canvas id="statusChart"></canvas></div>
                </div>
            </div>
            
            <!-- ===== QUICK ACTIONS ===== -->
            <div class="quick-actions-grid">

                <div class="quick-action-card action-1">
                    <div class="qa-content">
                        <div class="action-icon-large"><i class="fas fa-calendar-check"></i></div>
                        <h4>Approve Requests</h4>
                        <p>Review and approve pending vaccination appointment requests</p>
                        <a href="appointment_requests.php" class="action-btn">View Requests</a>
                    </div>
                </div>

                <div class="quick-action-card action-2">
                    <div class="qa-content">
                        <div class="action-icon-large"><i class="fas fa-hospital"></i></div>
                        <h4>Manage Hospitals</h4>
                        <p>Add, edit or remove hospitals from the vaccination network</p>
                        <a href="manage_hospitals.php" class="action-btn">Manage</a>
                    </div>
                </div>

                <div class="quick-action-card action-3">
                    <div class="qa-content">
                        <div class="action-icon-large"><i class="fas fa-syringe"></i></div>
                        <h4>Vaccine Inventory</h4>
                        <p>Track vaccine stock and manage inventory levels</p>
                        <a href="manage_vaccines.php" class="action-btn">Check Stock</a>
                    </div>
                </div>

                <div class="quick-action-card action-4">
                    <div class="qa-content">
                        <div class="action-icon-large"><i class="fas fa-chart-bar"></i></div>
                        <h4>Generate Reports</h4>
                        <p>Create vaccination reports and analytics for insights</p>
                        <a href="vaccination_reports.php" class="action-btn">View Reports</a>
                    </div>
                </div>

                <div class="quick-action-card action-5">
                    <div class="qa-content">
                        <div class="action-icon-large"><i class="fas fa-bell"></i></div>
                        <h4>Send Notifications</h4>
                        <p>Send reminders and notifications to parents</p>
                        <a href="send_notifications.php" class="action-btn">Send</a>
                    </div>
                </div>

            </div>
            
            <!-- Tables -->
            <div class="tables-section">
                <div class="table-card">
                    <div class="table-header">
                        <h3><i class="fas fa-history"></i> Recent Activities</h3>
                        <a href="activity_logs.php" class="view-all">View All <i class="fas fa-arrow-right"></i></a>
                    </div>
                    <div class="table-container">
                        <table class="data-table">
                            <thead><tr><th>Time</th><th>Action</th><th>Description</th></tr></thead>
                            <tbody>
                                <?php if(mysqli_num_rows($result_recent) > 0): ?>
                                <?php while($row = mysqli_fetch_assoc($result_recent)): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight:600;color:#334155;"><?php echo date('h:i A', strtotime($row['created_at'])); ?></div>
                                        <div style="font-size:12px;color:#64748b;"><?php echo date('M d', strtotime($row['created_at'])); ?></div>
                                    </td>
                                    <td>
                                        <?php
                                        $ac='#64748b';$ai='fas fa-circle';
                                        if($row['action_type']=='login'){$ac='#10b981';$ai='fas fa-sign-in-alt';}
                                        elseif($row['action_type']=='approve'){$ac='#4361ee';$ai='fas fa-check-circle';}
                                        elseif($row['action_type']=='reject'){$ac='#ef4444';$ai='fas fa-times-circle';}
                                        elseif($row['action_type']=='create'){$ac='#8b5cf6';$ai='fas fa-plus-circle';}
                                        ?>
                                        <div style="display:flex;align-items:center;gap:8px;"><i class="<?php echo $ai; ?>" style="color:<?php echo $ac; ?>;"></i><span style="text-transform:capitalize;font-weight:600;color:#334155;"><?php echo $row['action_type']; ?></span></div>
                                    </td>
                                    <td>
                                        <div style="color:#334155;font-size:13px;"><?php echo htmlspecialchars($row['action_description']); ?></div>
                                        <div style="font-size:11px;color:#64748b;margin-top:3px;">IP: <?php echo $row['ip_address']; ?></div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                                <?php else: ?>
                                <tr><td colspan="3" style="text-align:center;padding:30px;color:#64748b;"><i class="fas fa-history" style="font-size:24px;margin-bottom:10px;display:block;color:#e2e8f0;"></i>No recent activities</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="table-card">
                    <div class="table-header">
                        <h3><i class="fas fa-calendar-alt"></i> Upcoming Appointments</h3>
                        <a href="booking_details.php" class="view-all">View All <i class="fas fa-arrow-right"></i></a>
                    </div>
                    <div class="table-container">
                        <table class="data-table">
                            <thead><tr><th>Child</th><th>Vaccine</th><th>Hospital</th><th>Date & Time</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php if(mysqli_num_rows($result_bookings) > 0): ?>
                                <?php while($row = mysqli_fetch_assoc($result_bookings)): ?>
                                <tr>
                                    <td><div style="font-weight:600;color:#334155;"><?php echo htmlspecialchars($row['child_name']); ?></div></td>
                                    <td><div style="color:#334155;font-size:13px;"><?php echo htmlspecialchars($row['vaccine_name']); ?></div></td>
                                    <td><div style="color:#64748b;font-size:12px;"><?php echo htmlspecialchars($row['hospital_name']); ?></div></td>
                                    <td>
                                        <div style="font-weight:600;color:#334155;"><?php echo date('M d', strtotime($row['appointment_date'])); ?></div>
                                        <div style="font-size:12px;color:#64748b;"><?php echo date('h:i A', strtotime($row['appointment_time'])); ?></div>
                                    </td>
                                    <td><span class="status-badge status-scheduled"><i class="fas fa-clock"></i> Scheduled</span></td>
                                </tr>
                                <?php endwhile; ?>
                                <?php else: ?>
                                <tr><td colspan="5" style="text-align:center;padding:30px;color:#64748b;"><i class="fas fa-calendar" style="font-size:24px;margin-bottom:10px;display:block;color:#e2e8f0;"></i>No upcoming appointments</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            new Chart(document.getElementById('monthlyChart').getContext('2d'), {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($months); ?>,
                    datasets: [{ label: 'Vaccination Bookings', data: <?php echo json_encode($counts); ?>, borderColor: '#4361ee', backgroundColor: 'rgba(67,97,238,0.1)', borderWidth: 3, fill: true, tension: 0.4, pointBackgroundColor: '#4361ee', pointBorderColor: '#fff', pointBorderWidth: 2, pointRadius: 6, pointHoverRadius: 8 }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: true, position: 'top' } }, scales: { y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } }, x: { grid: { display: false } } } }
            });
            new Chart(document.getElementById('statusChart').getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: ['Pending', 'Approved', 'Completed', 'Cancelled'],
                    datasets: [{ data: [<?php echo $pending_count; ?>, <?php echo $today_count; ?>, <?php echo $completed_count; ?>, <?php echo $pending_count > 0 ? 0 : 5; ?>], backgroundColor: ['rgba(248,150,30,0.8)','rgba(67,97,238,0.8)','rgba(16,185,129,0.8)','rgba(239,68,68,0.8)'], borderColor: ['#f8961e','#4361ee','#10b981','#ef4444'], borderWidth: 2, hoverOffset: 15 }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' } }, cutout: '70%' }
            });
        });
        const hour = new Date().getHours();
        const greeting = document.querySelector('.welcome-message h1');
        if(greeting){ if(hour<12) greeting.innerHTML=greeting.innerHTML.replace('Welcome back','Good morning'); else if(hour<18) greeting.innerHTML=greeting.innerHTML.replace('Welcome back','Good afternoon'); else greeting.innerHTML=greeting.innerHTML.replace('Welcome back','Good evening'); }
    </script>
</body>
</html>
<?php mysqli_close($connection); ?>