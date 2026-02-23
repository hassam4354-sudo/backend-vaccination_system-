<?php
session_start();
if (!isset($_SESSION["logged_in"]) || $_SESSION["user_type"] != "admin") {
    header("location: ../login.php");
    exit();
}

include("../dbconnection.php");

// Get admin details
$user_id = $_SESSION["user_id"];
$query_admin = "SELECT full_name FROM admins WHERE user_id = '$user_id'";
$result_admin = mysqli_query($connection, $query_admin);
$admin_data = mysqli_fetch_assoc($result_admin);
$admin_name = $admin_data['full_name'];

// Get filter values
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'overview';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$hospital_filter = isset($_GET['hospital_id']) ? intval($_GET['hospital_id']) : 0;
$vaccine_filter = isset($_GET['vaccine_id']) ? intval($_GET['vaccine_id']) : 0;

// Get all hospitals for filter
$hospitals = mysqli_query($connection, "SELECT hospital_id, hospital_name FROM hospitals ORDER BY hospital_name");

// Get all vaccines for filter
$vaccines = mysqli_query($connection, "SELECT vaccine_id, vaccine_name FROM vaccines WHERE is_active = 1 ORDER BY vaccine_name");

// ========== REPORT FUNCTIONS ==========

// 1. OVERVIEW REPORT
function getOverviewReport($conn, $from, $to) {
    $report = [];
    
    // Total bookings
    $q = "SELECT COUNT(*) as total FROM vaccination_bookings WHERE created_at BETWEEN '$from' AND '$to 23:59:59'";
    $r = mysqli_query($conn, $q);
    $report['total_bookings'] = mysqli_fetch_assoc($r)['total'];
    
    // Completed vaccinations
    $q = "SELECT COUNT(*) as total FROM vaccination_records WHERE vaccination_date BETWEEN '$from' AND '$to'";
    $r = mysqli_query($conn, $q);
    $report['completed_vaccinations'] = mysqli_fetch_assoc($r)['total'];
    
    // Pending requests
    $q = "SELECT COUNT(*) as total FROM appointment_requests WHERE request_status = 'pending' AND created_at BETWEEN '$from' AND '$to 23:59:59'";
    $r = mysqli_query($conn, $q);
    $report['pending_requests'] = mysqli_fetch_assoc($r)['total'];
    
    // New registrations (parents)
    $q = "SELECT COUNT(*) as total FROM parents WHERE created_at BETWEEN '$from' AND '$to 23:59:59'";
    $r = mysqli_query($conn, $q);
    $report['new_parents'] = mysqli_fetch_assoc($r)['total'];
    
    // New children registered
    $q = "SELECT COUNT(*) as total FROM children WHERE created_at BETWEEN '$from' AND '$to 23:59:59'";
    $r = mysqli_query($conn, $q);
    $report['new_children'] = mysqli_fetch_assoc($r)['total'];
    
    return $report;
}

// 2. VACCINE WISE REPORT
function getVaccineWiseReport($conn, $from, $to) {
    $query = "SELECT 
                v.vaccine_id,
                v.vaccine_name,
                v.vaccine_code,
                COUNT(DISTINCT vb.booking_id) as total_bookings,
                COUNT(DISTINCT vr.record_id) as administered_count,
                COUNT(DISTINCT CASE WHEN ar.request_status = 'pending' THEN ar.request_id END) as pending_requests,
                COUNT(DISTINCT CASE WHEN vb.booking_status = 'completed' THEN vb.booking_id END) as completed_bookings,
                COUNT(DISTINCT CASE WHEN vb.booking_status = 'cancelled' THEN vb.booking_id END) as cancelled_bookings
              FROM vaccines v
              LEFT JOIN appointment_requests ar ON v.vaccine_id = ar.vaccine_id AND ar.created_at BETWEEN '$from' AND '$to 23:59:59'
              LEFT JOIN vaccination_bookings vb ON v.vaccine_id = vb.vaccine_id AND vb.created_at BETWEEN '$from' AND '$to 23:59:59'
              LEFT JOIN vaccination_records vr ON v.vaccine_id = vr.vaccine_id AND vr.vaccination_date BETWEEN '$from' AND '$to'
              WHERE v.is_active = 1
              GROUP BY v.vaccine_id
              ORDER BY administered_count DESC";
    
    return mysqli_query($conn, $query);
}

// 3. HOSPITAL WISE REPORT
function getHospitalWiseReport($conn, $from, $to, $hospital_id = 0) {
    $where = "";
    if ($hospital_id > 0) {
        $where = " AND h.hospital_id = $hospital_id";
    }
    
    $query = "SELECT 
                h.hospital_id,
                h.hospital_name,
                h.city,
                COUNT(DISTINCT vb.booking_id) as total_bookings,
                COUNT(DISTINCT vr.record_id) as vaccinations_done,
                COUNT(DISTINCT CASE WHEN ar.request_status = 'pending' THEN ar.request_id END) as pending_requests,
                COUNT(DISTINCT CASE WHEN ar.request_status = 'approved' THEN ar.request_id END) as approved_requests,
                COUNT(DISTINCT u.user_id) as hospital_staff
              FROM hospitals h
              LEFT JOIN appointment_requests ar ON h.hospital_id = ar.hospital_id AND ar.created_at BETWEEN '$from' AND '$to 23:59:59'
              LEFT JOIN vaccination_bookings vb ON h.hospital_id = vb.hospital_id AND vb.created_at BETWEEN '$from' AND '$to 23:59:59'
              LEFT JOIN vaccination_records vr ON h.hospital_id = vr.hospital_id AND vr.vaccination_date BETWEEN '$from' AND '$to'
              LEFT JOIN users u ON h.user_id = u.user_id
              WHERE h.is_active = 1 $where
              GROUP BY h.hospital_id
              ORDER BY vaccinations_done DESC";
    
    return mysqli_query($conn, $query);
}

// 4. CHILDREN AGE GROUP REPORT
function getAgeGroupReport($conn) {
    $today = date('Y-m-d');
    
    $query = "SELECT 
                CASE 
                    WHEN TIMESTAMPDIFF(MONTH, date_of_birth, '$today') <= 1 THEN '0-1 Month'
                    WHEN TIMESTAMPDIFF(MONTH, date_of_birth, '$today') <= 3 THEN '1-3 Months'
                    WHEN TIMESTAMPDIFF(MONTH, date_of_birth, '$today') <= 6 THEN '3-6 Months'
                    WHEN TIMESTAMPDIFF(MONTH, date_of_birth, '$today') <= 12 THEN '6-12 Months'
                    WHEN TIMESTAMPDIFF(YEAR, date_of_birth, '$today') <= 2 THEN '1-2 Years'
                    WHEN TIMESTAMPDIFF(YEAR, date_of_birth, '$today') <= 5 THEN '2-5 Years'
                    ELSE '5+ Years'
                END as age_group,
                COUNT(*) as total_children,
                SUM(CASE WHEN gender = 'Male' THEN 1 ELSE 0 END) as boys,
                SUM(CASE WHEN gender = 'Female' THEN 1 ELSE 0 END) as girls
              FROM children 
              WHERE is_active = 1
              GROUP BY age_group
              ORDER BY FIELD(age_group, 
                  '0-1 Month', '1-3 Months', '3-6 Months', '6-12 Months', 
                  '1-2 Years', '2-5 Years', '5+ Years')";
    
    return mysqli_query($conn, $query);
}

// 5. DAILY TREND REPORT
function getDailyTrendReport($conn, $from, $to) {
    $query = "SELECT 
                DATE(vaccination_date) as date,
                COUNT(*) as vaccinations,
                COUNT(DISTINCT hospital_id) as hospitals_active,
                COUNT(DISTINCT vaccine_id) as vaccines_used
              FROM vaccination_records
              WHERE vaccination_date BETWEEN '$from' AND '$to'
              GROUP BY DATE(vaccination_date)
              ORDER BY date DESC";
    
    return mysqli_query($conn, $query);
}

// 6. PENDING REQUESTS SUMMARY
function getPendingSummary($conn) {
    $query = "SELECT 
                COUNT(*) as total_pending,
                COUNT(DISTINCT child_id) as unique_children,
                COUNT(DISTINCT hospital_id) as hospitals_involved,
                MIN(preferred_date) as earliest_date,
                MAX(preferred_date) as latest_date
              FROM appointment_requests
              WHERE request_status = 'pending'";
    
    return mysqli_fetch_assoc(mysqli_query($conn, $query));
}

// 7. COMPLETION RATE REPORT
function getCompletionRateReport($conn, $from, $to) {
    $query = "SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as total_requests,
                SUM(CASE WHEN request_status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN request_status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN request_status = 'pending' THEN 1 ELSE 0 END) as pending,
                ROUND(SUM(CASE WHEN request_status = 'approved' THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) as approval_rate
              FROM appointment_requests
              WHERE created_at BETWEEN '$from' AND '$to 23:59:59'
              GROUP BY DATE_FORMAT(created_at, '%Y-%m')
              ORDER BY month DESC";
    
    return mysqli_query($conn, $query);
}

// Fetch data based on report type
$overview = getOverviewReport($connection, $date_from, $date_to);
$vaccine_report = getVaccineWiseReport($connection, $date_from, $date_to);
$hospital_report = getHospitalWiseReport($connection, $date_from, $date_to, $hospital_filter);
$age_group_report = getAgeGroupReport($connection);
$daily_trend = getDailyTrendReport($connection, $date_from, $date_to);
$pending_summary = getPendingSummary($connection);
$completion_rates = getCompletionRateReport($connection, $date_from, $date_to);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vaccination Reports - Admin Panel</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    
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
        
        /* Admin Navbar - FIXED */
        .admin-navbar {
            background: white;
            border-radius: var(--border-radius);
            padding: 15px 30px;
            margin: 20px;
            box-shadow: var(--box-shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 5px solid var(--primary);
            animation: slideInDown 0.5s ease-out;
            position: sticky;
            top: 20px;
            z-index: 1000;
        }
        
        .admin-navbar .logo {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .admin-navbar .logo-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 22px;
        }
        
        .admin-navbar .logo h2 {
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 24px;
            font-weight: 700;
        }
        
        .nav-links {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .nav-links a {
            text-decoration: none;
            color: var(--dark);
            padding: 10px 20px;
            border-radius: 30px;
            font-weight: 500;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        
        .nav-links a i {
            color: var(--primary);
            transition: var(--transition);
        }
        
        .nav-links a:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-3px);
        }
        
        .nav-links a:hover i {
            color: white;
        }
        
        .nav-links a.active {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }
        
        .nav-links a.active i {
            color: white;
        }
        
        .nav-links a.logout {
            background: linear-gradient(135deg, var(--danger), #d0006f);
            color: white;
        }
        
        .nav-links a.logout i {
            color: white;
        }
        
        .nav-links a.logout:hover {
            background: linear-gradient(135deg, #d0006f, var(--danger));
        }
        
        /* Mobile menu toggle - hidden by default */
        .menu-toggle {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1001;
            background: white;
            width: 50px;
            height: 50px;
            border-radius: 12px;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            cursor: pointer;
            box-shadow: var(--box-shadow);
        }
        
        /* Container */
        .container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Page Header */
        .page-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius);
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--box-shadow);
            animation: fadeIn 0.6s ease-out;
        }
        
        .page-header h1 {
            color: var(--dark);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 2.2rem;
        }
        
        .page-header h1 i {
            color: var(--primary);
            background: rgba(67, 97, 238, 0.1);
            padding: 12px;
            border-radius: 10px;
        }
        
        .page-header p {
            color: var(--gray);
            font-size: 16px;
        }
        
        /* Stats Banner */
        .stats-banner {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px 30px;
            margin-bottom: 30px;
            box-shadow: var(--box-shadow);
        }
        
        .date-display {
            color: var(--gray);
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        /* Filter Section */
        .filter-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--box-shadow);
        }
        
        .filter-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .filter-title i {
            color: var(--primary);
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .filter-group {
            position: relative;
        }
        
        .filter-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
        }
        
        .filter-input, .filter-select {
            width: 100%;
            padding: 12px 20px 12px 45px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 14px;
            transition: var(--transition);
        }
        
        .filter-input:focus, .filter-select:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }
        
        .filter-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(67, 97, 238, 0.4);
        }
        
        .btn-secondary {
            background: #f3f4f6;
            color: var(--gray);
        }
        
        .btn-secondary:hover {
            background: #e5e7eb;
        }
        
        .btn-export {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }
        
        .btn-export:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(16, 185, 129, 0.4);
        }
        
        /* Report Cards */
        .report-section {
            margin-bottom: 40px;
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title i {
            background: rgba(255, 255, 255, 0.2);
            padding: 10px;
            border-radius: 10px;
        }
        
        .report-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .report-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        
        .report-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        
        .report-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }
        
        .report-card .card-icon {
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
        
        .report-card h3 {
            font-size: 2rem;
            color: var(--dark);
            margin-bottom: 5px;
        }
        
        .report-card p {
            color: var(--gray);
            font-size: 14px;
        }
        
        /* Charts Grid */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .chart-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--box-shadow);
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .chart-header h3 {
            color: var(--dark);
            font-size: 1.2rem;
            font-weight: 700;
        }
        
        .chart-container {
            height: 300px;
            position: relative;
        }
        
        /* Table Card */
        .table-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--box-shadow);
            margin-bottom: 30px;
        }
        
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .table-header h3 {
            color: var(--dark);
            font-size: 1.2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .badge-count {
            background: #f3f4f6;
            padding: 5px 15px;
            border-radius: 20px;
            color: var(--gray);
            font-size: 14px;
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
        
        .progress-bar {
            width: 100%;
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            border-radius: 4px;
        }
        
        /* Pending Summary */
        .pending-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            background: #fef3c7;
            border-radius: var(--border-radius);
            padding: 20px;
            margin-bottom: 30px;
            border-left: 4px solid #f59e0b;
        }
        
        .pending-item {
            text-align: center;
        }
        
        .pending-item h4 {
            color: #92400e;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        
        .pending-item .value {
            font-size: 1.8rem;
            font-weight: 700;
            color: #b45309;
        }
        
        /* Export Options */
        .export-options {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .export-btn {
            padding: 10px 20px;
            background: #f3f4f6;
            border: none;
            border-radius: 30px;
            color: var(--dark);
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .export-btn:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-3px);
        }
        
        /* Animations */
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
        
        /* Footer */
        .footer {
            text-align: center;
            padding: 20px;
            color: rgba(255, 255, 255, 0.7);
            margin-top: 30px;
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .admin-navbar {
                flex-direction: column;
                gap: 15px;
            }
            
            .nav-links {
                justify-content: center;
            }
        }
        
        @media (max-width: 768px) {
            .menu-toggle {
                display: flex;
            }
            
            .admin-navbar {
                position: fixed;
                top: 0;
                left: -100%;
                width: 80%;
                height: 100vh;
                margin: 0;
                border-radius: 0;
                flex-direction: column;
                justify-content: flex-start;
                padding-top: 80px;
                transition: left 0.3s ease;
                z-index: 999;
            }
            
            .admin-navbar.active {
                left: 0;
            }
            
            .nav-links {
                flex-direction: column;
                width: 100%;
            }
            
            .nav-links a {
                width: 100%;
                justify-content: center;
            }
            
            .container {
                padding: 0 10px;
                margin-top: 70px;
            }
            
            .page-header {
                padding: 20px;
            }
            
            .filter-grid {
                grid-template-columns: 1fr;
            }
            
            .charts-grid {
                grid-template-columns: 1fr;
            }
            
            .report-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 480px) {
            .report-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-actions {
                flex-direction: column;
            }
            
            .export-options {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <div class="menu-toggle" id="menuToggle">
        <i class="fas fa-bars"></i>
    </div>
    
    <!-- Admin Navbar - FIXED -->
    <nav class="admin-navbar" id="adminNavbar">
        <div class="logo">
            <div class="logo-icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h2>Vaccine<span style="color: var(--primary);">Admin</span></h2>
        </div>
        <div class="nav-links">
            <a href="dashboard.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="manage_children.php">
                <i class="fas fa-child"></i> Children
            </a>
            <a href="manage_hospitals.php">
                <i class="fas fa-hospital"></i> Hospitals
            </a>
            <a href="appointment_requests.php">
                <i class="fas fa-calendar-check"></i> Requests
            </a>
            <a href="managevaccines.php">
                <i class="fas fa-syringe"></i> Vaccines
            </a>
            <a href="bookingdetail.php">
                <i class="fas fa-calendar-alt"></i> Bookings
            </a>
            <a href="vaccination_reports.php" class="active">
                <i class="fas fa-chart-bar"></i> Reports
            </a>
            <a href="system_settings.php">
                <i class="fas fa-cog"></i> Settings
            </a>
            <a href="../logout.php" class="logout">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </nav>

    <div class="container">
        <!-- Page Header -->
        <div class="page-header animate__animated animate__fadeIn">
            <h1>
                <i class="fas fa-chart-bar"></i>
                Vaccination Reports
            </h1>
            <p>Detailed analytics and insights for your vaccination program</p>
        </div>
        
        <!-- Filter Section -->
        <div class="filter-section animate__animated animate__fadeIn">
            <div class="filter-title">
                <i class="fas fa-filter"></i> Filter Reports
            </div>
            
            <form method="GET" action="">
                <div class="filter-grid">
                    <div class="filter-group">
                        <i class="fas fa-calendar"></i>
                        <input type="date" class="filter-input" name="date_from" value="<?php echo $date_from; ?>">
                    </div>
                    
                    <div class="filter-group">
                        <i class="fas fa-calendar-check"></i>
                        <input type="date" class="filter-input" name="date_to" value="<?php echo $date_to; ?>">
                    </div>
                    
                    <div class="filter-group">
                        <i class="fas fa-hospital"></i>
                        <select class="filter-select" name="hospital_id">
                            <option value="0">All Hospitals</option>
                            <?php 
                            mysqli_data_seek($hospitals, 0);
                            while($h = mysqli_fetch_assoc($hospitals)): 
                            ?>
                            <option value="<?php echo $h['hospital_id']; ?>" <?php echo $hospital_filter == $h['hospital_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($h['hospital_name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <i class="fas fa-syringe"></i>
                        <select class="filter-select" name="vaccine_id">
                            <option value="0">All Vaccines</option>
                            <?php 
                            mysqli_data_seek($vaccines, 0);
                            while($v = mysqli_fetch_assoc($vaccines)): 
                            ?>
                            <option value="<?php echo $v['vaccine_id']; ?>" <?php echo $vaccine_filter == $v['vaccine_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($v['vaccine_name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i> Generate Report
                    </button>
                    <a href="vaccination_reports.php" class="btn btn-secondary">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Export Options -->
        <div class="export-options">
            <button class="export-btn" onclick="exportToPDF()">
                <i class="fas fa-file-pdf"></i> Export as PDF
            </button>
            <button class="export-btn" onclick="exportToExcel()">
                <i class="fas fa-file-excel"></i> Export as Excel
            </button>
            <button class="export-btn" onclick="printReport()">
                <i class="fas fa-print"></i> Print Report
            </button>
        </div>
        
        <!-- Pending Summary -->
        <div class="pending-summary animate__animated animate__fadeInUp">
            <div class="pending-item">
                <h4>Total Pending</h4>
                <div class="value"><?php echo $pending_summary['total_pending']; ?></div>
            </div>
            <div class="pending-item">
                <h4>Unique Children</h4>
                <div class="value"><?php echo $pending_summary['unique_children']; ?></div>
            </div>
            <div class="pending-item">
                <h4>Hospitals Involved</h4>
                <div class="value"><?php echo $pending_summary['hospitals_involved']; ?></div>
            </div>
            <div class="pending-item">
                <h4>Earliest Date</h4>
                <div class="value"><?php echo date('d M', strtotime($pending_summary['earliest_date'])); ?></div>
            </div>
        </div>
        
        <!-- Overview Cards -->
        <div class="report-grid">
            <div class="report-card animate__animated animate__fadeInUp">
                <div class="card-icon" style="background: linear-gradient(135deg, #4361ee, #3a0ca3);">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <h3><?php echo $overview['total_bookings']; ?></h3>
                <p>Total Bookings</p>
            </div>
            
            <div class="report-card animate__animated animate__fadeInUp" style="animation-delay: 0.1s">
                <div class="card-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3><?php echo $overview['completed_vaccinations']; ?></h3>
                <p>Completed Vaccinations</p>
            </div>
            
            <div class="report-card animate__animated animate__fadeInUp" style="animation-delay: 0.2s">
                <div class="card-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                    <i class="fas fa-clock"></i>
                </div>
                <h3><?php echo $overview['pending_requests']; ?></h3>
                <p>Pending Requests</p>
            </div>
            
            <div class="report-card animate__animated animate__fadeInUp" style="animation-delay: 0.3s">
                <div class="card-icon" style="background: linear-gradient(135deg, #8b5cf6, #6d28d9);">
                    <i class="fas fa-child"></i>
                </div>
                <h3><?php echo $overview['new_children']; ?></h3>
                <p>New Children</p>
            </div>
        </div>
        
        <!-- Charts Section -->
        <div class="charts-grid">
            <!-- Vaccine Distribution Chart -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3><i class="fas fa-chart-pie"></i> Vaccine Distribution</h3>
                </div>
                <div class="chart-container">
                    <canvas id="vaccineChart"></canvas>
                </div>
            </div>
            
            <!-- Daily Trend Chart -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3><i class="fas fa-chart-line"></i> Daily Vaccination Trend</h3>
                </div>
                <div class="chart-container">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Age Group Report -->
        <div class="table-card">
            <div class="table-header">
                <h3><i class="fas fa-users"></i> Children by Age Group</h3>
                <span class="badge-count">Age Distribution</span>
            </div>
            
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Age Group</th>
                            <th>Total Children</th>
                            <th>Boys</th>
                            <th>Girls</th>
                            <th>Distribution</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_children_age = 0;
                        $age_data = [];
                        while($age = mysqli_fetch_assoc($age_group_report)):
                            $total_children_age += $age['total_children'];
                            $age_data[] = $age;
                        endwhile;
                        
                        mysqli_data_seek($age_group_report, 0);
                        while($age = mysqli_fetch_assoc($age_group_report)):
                        ?>
                        <tr>
                            <td><strong><?php echo $age['age_group']; ?></strong></td>
                            <td><?php echo $age['total_children']; ?></td>
                            <td><?php echo $age['boys']; ?></td>
                            <td><?php echo $age['girls']; ?></td>
                            <td>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo ($age['total_children'] / $total_children_age) * 100; ?>%"></div>
                                </div>
                                <small><?php echo round(($age['total_children'] / $total_children_age) * 100, 1); ?>%</small>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Vaccine Wise Report -->
        <div class="table-card">
            <div class="table-header">
                <h3><i class="fas fa-syringe"></i> Vaccine Wise Report</h3>
                <span class="badge-count"><?php echo mysqli_num_rows($vaccine_report); ?> Vaccines</span>
            </div>
            
            <div class="table-responsive">
                <table class="data-table" id="vaccineTable">
                    <thead>
                        <tr>
                            <th>Vaccine</th>
                            <th>Code</th>
                            <th>Bookings</th>
                            <th>Administered</th>
                            <th>Pending</th>
                            <th>Completion Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        mysqli_data_seek($vaccine_report, 0);
                        while($v = mysqli_fetch_assoc($vaccine_report)): 
                            $completion_rate = $v['total_bookings'] > 0 ? round(($v['administered_count'] / $v['total_bookings']) * 100, 1) : 0;
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($v['vaccine_name']); ?></strong></td>
                            <td><?php echo $v['vaccine_code']; ?></td>
                            <td><?php echo $v['total_bookings']; ?></td>
                            <td><?php echo $v['administered_count']; ?></td>
                            <td><?php echo $v['pending_requests']; ?></td>
                            <td>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $completion_rate; ?>%"></div>
                                </div>
                                <small><?php echo $completion_rate; ?>%</small>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Hospital Wise Report -->
        <div class="table-card">
            <div class="table-header">
                <h3><i class="fas fa-hospital"></i> Hospital Performance Report</h3>
                <span class="badge-count"><?php echo mysqli_num_rows($hospital_report); ?> Hospitals</span>
            </div>
            
            <div class="table-responsive">
                <table class="data-table" id="hospitalTable">
                    <thead>
                        <tr>
                            <th>Hospital</th>
                            <th>Location</th>
                            <th>Total Bookings</th>
                            <th>Vaccinations Done</th>
                            <th>Pending</th>
                            <th>Approved</th>
                            <th>Performance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        mysqli_data_seek($hospital_report, 0);
                        while($h = mysqli_fetch_assoc($hospital_report)): 
                            $performance = $h['total_bookings'] > 0 ? round(($h['vaccinations_done'] / $h['total_bookings']) * 100, 1) : 0;
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($h['hospital_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($h['city']); ?></td>
                            <td><?php echo $h['total_bookings']; ?></td>
                            <td><?php echo $h['vaccinations_done']; ?></td>
                            <td><?php echo $h['pending_requests']; ?></td>
                            <td><?php echo $h['approved_requests']; ?></td>
                            <td>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $performance; ?>%"></div>
                                </div>
                                <small><?php echo $performance; ?>%</small>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Daily Trend Table -->
        <div class="table-card">
            <div class="table-header">
                <h3><i class="fas fa-calendar-alt"></i> Daily Vaccination Trend</h3>
                <span class="badge-count">Last <?php echo mysqli_num_rows($daily_trend); ?> Days</span>
            </div>
            
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Vaccinations</th>
                            <th>Active Hospitals</th>
                            <th>Vaccines Used</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        mysqli_data_seek($daily_trend, 0);
                        while($day = mysqli_fetch_assoc($daily_trend)): 
                        ?>
                        <tr>
                            <td><?php echo date('d M Y', strtotime($day['date'])); ?></td>
                            <td><strong><?php echo $day['vaccinations']; ?></strong></td>
                            <td><?php echo $day['hospitals_active']; ?></td>
                            <td><?php echo $day['vaccines_used']; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Completion Rate Report -->
        <div class="table-card">
            <div class="table-header">
                <h3><i class="fas fa-percentage"></i> Monthly Approval Rates</h3>
                <span class="badge-count">Trend Analysis</span>
            </div>
            
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Total Requests</th>
                            <th>Approved</th>
                            <th>Rejected</th>
                            <th>Pending</th>
                            <th>Approval Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        mysqli_data_seek($completion_rates, 0);
                        while($rate = mysqli_fetch_assoc($completion_rates)): 
                        ?>
                        <tr>
                            <td><strong><?php echo $rate['month']; ?></strong></td>
                            <td><?php echo $rate['total_requests']; ?></td>
                            <td><?php echo $rate['approved']; ?></td>
                            <td><?php echo $rate['rejected']; ?></td>
                            <td><?php echo $rate['pending']; ?></td>
                            <td>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $rate['approval_rate']; ?>%"></div>
                                </div>
                                <small><?php echo $rate['approval_rate']; ?>%</small>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p>© <?php echo date('Y'); ?> Child Vaccination System. All rights reserved.</p>
        </div>
    </div>
    
    <script>
        // Mobile menu toggle
        document.getElementById('menuToggle').addEventListener('click', function() {
            document.getElementById('adminNavbar').classList.toggle('active');
        });
        
        // Initialize DataTables
        $(document).ready(function() {
            $('#vaccineTable').DataTable({
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                order: [[2, 'desc']],
                language: {
                    search: "Search:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ vaccines"
                }
            });
            
            $('#hospitalTable').DataTable({
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                order: [[3, 'desc']],
                language: {
                    search: "Search:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ hospitals"
                }
            });
        });
        
        // Initialize Charts
        document.addEventListener('DOMContentLoaded', function() {
            // Vaccine Chart Data
            <?php
            mysqli_data_seek($vaccine_report, 0);
            $vaccine_names = [];
            $vaccine_counts = [];
            while($v = mysqli_fetch_assoc($vaccine_report)) {
                $vaccine_names[] = $v['vaccine_name'];
                $vaccine_counts[] = $v['administered_count'];
            }
            ?>
            
            const vaccineCtx = document.getElementById('vaccineChart').getContext('2d');
            new Chart(vaccineCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($vaccine_names); ?>,
                    datasets: [{
                        data: <?php echo json_encode($vaccine_counts); ?>,
                        backgroundColor: [
                            '#4361ee', '#3a0ca3', '#f72585', '#4cc9f0', 
                            '#f8961e', '#7209b7', '#10b981', '#f59e0b'
                        ],
                        borderWidth: 2,
                        borderColor: 'white'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                        }
                    }
                }
            });
            
            // Trend Chart Data
            <?php
            mysqli_data_seek($daily_trend, 0);
            $dates = [];
            $daily_counts = [];
            while($day = mysqli_fetch_assoc($daily_trend)) {
                $dates[] = date('d M', strtotime($day['date']));
                $daily_counts[] = $day['vaccinations'];
            }
            $dates = array_reverse($dates);
            $daily_counts = array_reverse($daily_counts);
            ?>
            
            const trendCtx = document.getElementById('trendChart').getContext('2d');
            new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($dates); ?>,
                    datasets: [{
                        label: 'Daily Vaccinations',
                        data: <?php echo json_encode($daily_counts); ?>,
                        borderColor: '#4361ee',
                        backgroundColor: 'rgba(67, 97, 238, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#4361ee',
                        pointBorderColor: 'white',
                        pointBorderWidth: 2,
                        pointRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
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
        });
        
        // Export functions
        function exportToPDF() {
            alert('PDF export functionality will be implemented here.');
            // You can use libraries like jsPDF or TCPDF
        }
        
        function exportToExcel() {
            alert('Excel export functionality will be implemented here.');
            // You can export HTML tables to Excel
        }
        
        function printReport() {
            window.print();
        }
        
        // Close mobile menu when clicking outside
        document.addEventListener('click', function(event) {
            const navbar = document.getElementById('adminNavbar');
            const toggle = document.getElementById('menuToggle');
            
            if (!navbar.contains(event.target) && !toggle.contains(event.target)) {
                navbar.classList.remove('active');
            }
        });
        
        // Add animation to cards
        const cards = document.querySelectorAll('.report-card');
        cards.forEach((card, index) => {
            card.style.animationDelay = `${index * 0.1}s`;
        });
    </script>
</body>
</html>
<?php mysqli_close($connection); ?>