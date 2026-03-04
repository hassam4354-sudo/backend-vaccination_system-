<?php
session_start();
if(!isset($_SESSION["logged_in"]) || $_SESSION["user_type"] != "admin"){
    header("location:../login.php");
    exit();
}

include("../dbconnection.php");

// Get report parameters
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'vaccinations';
$date_range = isset($_GET['date_range']) ? $_GET['date_range'] : 'month';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

// Set date range based on selection
if(empty($start_date) || empty($end_date)) {
    switch($date_range) {
        case 'today':
            $start_date = date('Y-m-d');
            $end_date = date('Y-m-d');
            break;
        case 'week':
            $start_date = date('Y-m-d', strtotime('-7 days'));
            $end_date = date('Y-m-d');
            break;
        case 'month':
            $start_date = date('Y-m-d', strtotime('-30 days'));
            $end_date = date('Y-m-d');
            break;
        case 'quarter':
            $start_date = date('Y-m-d', strtotime('-3 months'));
            $end_date = date('Y-m-d');
            break;
        case 'year':
            $start_date = date('Y-m-d', strtotime('-1 year'));
            $end_date = date('Y-m-d');
            break;
        default:
            $start_date = date('Y-m-d', strtotime('-30 days'));
            $end_date = date('Y-m-d');
    }
}

// Escape dates for SQL
$start_date_esc = mysqli_real_escape_string($connection, $start_date);
$end_date_esc = mysqli_real_escape_string($connection, $end_date);

// Get summary statistics
$summary_query = "SELECT 
    (SELECT COUNT(*) FROM users) as total_users,
    (SELECT COUNT(*) FROM parents) as total_parents,
    (SELECT COUNT(*) FROM hospitals) as total_hospitals,
    (SELECT COUNT(*) FROM children) as total_children,
    (SELECT COUNT(*) FROM vaccines) as total_vaccines,
    (SELECT COUNT(*) FROM appointment_requests) as total_requests,
    (SELECT COUNT(*) FROM vaccination_bookings) as total_bookings,
    (SELECT COUNT(*) FROM vaccination_records) as total_vaccinated,
    (SELECT COUNT(*) FROM appointment_requests WHERE request_status = 'pending') as pending_requests,
    (SELECT COUNT(*) FROM hospitals WHERE is_verified = 0) as pending_hospitals";
$summary_result = mysqli_query($connection, $summary_query);
$summary = mysqli_fetch_assoc($summary_result);

// Get vaccination data for charts
if($report_type == 'vaccinations') {
    // Daily vaccinations for chart
    $daily_query = "SELECT 
        DATE(vr.vaccination_date) as date,
        COUNT(*) as count
        FROM vaccination_records vr
        WHERE vr.vaccination_date BETWEEN '$start_date_esc' AND '$end_date_esc'
        GROUP BY DATE(vr.vaccination_date)
        ORDER BY date ASC";
    $daily_result = mysqli_query($connection, $daily_query);
    
    // Vaccine-wise distribution
    $vaccine_query = "SELECT 
        v.vaccine_name,
        COUNT(*) as count
        FROM vaccination_records vr
        JOIN vaccines v ON vr.vaccine_id = v.vaccine_id
        WHERE vr.vaccination_date BETWEEN '$start_date_esc' AND '$end_date_esc'
        GROUP BY v.vaccine_id
        ORDER BY count DESC";
    $vaccine_result = mysqli_query($connection, $vaccine_query);
    
    // Hospital-wise vaccinations
    $hospital_query = "SELECT 
        h.hospital_name,
        COUNT(*) as count
        FROM vaccination_records vr
        JOIN hospitals h ON vr.hospital_id = h.hospital_id
        WHERE vr.vaccination_date BETWEEN '$start_date_esc' AND '$end_date_esc'
        GROUP BY h.hospital_id
        ORDER BY count DESC
        LIMIT 10";
    $hospital_result = mysqli_query($connection, $hospital_query);
    
} elseif($report_type == 'appointments') {
    // Appointments by status
    $appointment_status_query = "SELECT 
        request_status,
        COUNT(*) as count
        FROM appointment_requests
        WHERE created_at BETWEEN '$start_date_esc 00:00:00' AND '$end_date_esc 23:59:59'
        GROUP BY request_status";
    $appointment_status_result = mysqli_query($connection, $appointment_status_query);
    
    // Appointments by hospital
    $appointment_hospital_query = "SELECT 
        h.hospital_name,
        COUNT(*) as count
        FROM appointment_requests ar
        JOIN hospitals h ON ar.hospital_id = h.hospital_id
        WHERE ar.created_at BETWEEN '$start_date_esc 00:00:00' AND '$end_date_esc 23:59:59'
        GROUP BY h.hospital_id
        ORDER BY count DESC
        LIMIT 10";
    $appointment_hospital_result = mysqli_query($connection, $appointment_hospital_query);
    
} elseif($report_type == 'users') {
    // Users by type
    $user_type_query = "SELECT 
        user_type,
        COUNT(*) as count
        FROM users
        WHERE created_at BETWEEN '$start_date_esc 00:00:00' AND '$end_date_esc 23:59:59'
        GROUP BY user_type";
    $user_type_result = mysqli_query($connection, $user_type_query);
    
    // New users over time
    $new_users_query = "SELECT 
        DATE(created_at) as date,
        COUNT(*) as count
        FROM users
        WHERE created_at BETWEEN '$start_date_esc 00:00:00' AND '$end_date_esc 23:59:59'
        GROUP BY DATE(created_at)
        ORDER BY date ASC";
    $new_users_result = mysqli_query($connection, $new_users_query);
    
} elseif($report_type == 'hospitals') {
    // Hospitals by city
    $hospital_city_query = "SELECT 
        city,
        COUNT(*) as count
        FROM hospitals
        GROUP BY city
        ORDER BY count DESC";
    $hospital_city_result = mysqli_query($connection, $hospital_city_query);
    
    // Verified vs Unverified
    $hospital_verified_query = "SELECT 
        CASE WHEN is_verified = 1 THEN 'Verified' ELSE 'Pending' END as status,
        COUNT(*) as count
        FROM hospitals
        GROUP BY is_verified";
    $hospital_verified_result = mysqli_query($connection, $hospital_verified_query);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - Admin Panel</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <style>
        /* ===== GOOGLE FONTS ===== */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        /* ===== RESET & BASE ===== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }
        
        /* ===== CSS VARIABLES ===== */
        :root {
            /* Colors */
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-light: #60a5fa;
            --primary-soft: #dbeafe;
            --white: #ffffff;
            --white-off: #f8fafc;
            --gray-50: #f1f5f9;
            --gray-100: #e2e8f0;
            --gray-200: #cbd5e1;
            --gray-300: #94a3b8;
            --gray-400: #64748b;
            --gray-500: #475569;
            --gray-600: #334155;
            --gray-700: #1e293b;
            --blue-light: #eff6ff;
            --blue-soft: #bfdbfe;
            --success: #10b981;
            --success-light: #d1fae5;
            --warning: #f59e0b;
            --warning-light: #fef3c7;
            --danger: #ef4444;
            --danger-light: #fee2e2;
            
            /* Chart Colors */
            --chart-1: #2563eb;
            --chart-2: #10b981;
            --chart-3: #f59e0b;
            --chart-4: #8b5cf6;
            --chart-5: #ec4899;
            --chart-6: #06b6d4;
            --chart-7: #f97316;
            
            /* Shadows */
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-md: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
            --shadow-blue: 0 10px 25px -5px rgba(37, 99, 235, 0.2);
            
            /* Border Radius */
            --radius-sm: 6px;
            --radius: 10px;
            --radius-md: 14px;
            --radius-lg: 18px;
            --radius-xl: 22px;
            
            /* Transitions */
            --transition: all 0.2s ease;
        }
        
        /* ===== BODY STYLES ===== */
        body {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            min-height: 100vh;
            color: var(--gray-700);
        }
        
        /* ===== DASHBOARD LAYOUT ===== */
        .dashboard-layout {
            display: flex;
            min-height: 100vh;
        }
        
        /* ===== SIDEBAR STYLES ===== */
        .sidebar {
            width: 280px;
            background: white;
            border-right: 1px solid var(--gray-200);
            padding: 30px 25px;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.02);
            z-index: 50;
        }
        
        /* Logo */
        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 35px;
        }
        
        .logo-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 22px;
            font-weight: 700;
            box-shadow: 0 8px 15px rgba(37, 99, 235, 0.2);
        }
        
        .logo-text {
            font-size: 20px;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .logo-badge {
            background: var(--primary-soft);
            color: var(--primary);
            font-size: 10px;
            padding: 3px 10px;
            border-radius: 30px;
            font-weight: 600;
            margin-top: 4px;
            display: inline-block;
        }
        
        /* Admin Profile */
        .admin-profile {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 18px 15px;
            background: var(--gray-50);
            border-radius: 16px;
            margin-bottom: 30px;
            border: 1px solid var(--gray-200);
            transition: var(--transition);
        }
        
        .admin-profile:hover {
            border-color: var(--primary-light);
            background: white;
            box-shadow: var(--shadow);
        }
        
        .admin-avatar {
            width: 52px;
            height: 52px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 22px;
            font-weight: 600;
        }
        
        .admin-info h4 {
            color: var(--gray-700);
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 6px;
        }
        
        .admin-info p {
            color: var(--gray-500);
            font-size: 12px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .online-dot {
            width: 8px;
            height: 8px;
            background: #10b981;
            border-radius: 50%;
            display: inline-block;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        /* Navigation Menu */
        .nav-menu {
            display: flex;
            flex-direction: column;
            gap: 5px;
            margin-bottom: 30px;
        }
        
        .nav-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 14px 18px;
            border-radius: 14px;
            color: var(--gray-600);
            text-decoration: none;
            font-weight: 500;
            font-size: 15px;
            transition: var(--transition);
        }
        
        .nav-item i {
            width: 22px;
            color: var(--gray-400);
            font-size: 18px;
            transition: var(--transition);
        }
        
        .nav-item:hover {
            background: var(--gray-50);
            color: var(--primary);
            transform: translateX(5px);
        }
        
        .nav-item:hover i {
            color: var(--primary);
        }
        
        .nav-item.active {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            box-shadow: 0 10px 20px rgba(37, 99, 235, 0.25);
        }
        
        .nav-item.active i {
            color: white;
        }
        
        .nav-badge {
            background: var(--primary-soft);
            color: var(--primary);
            font-size: 11px;
            padding: 4px 10px;
            border-radius: 30px;
            margin-left: auto;
            font-weight: 600;
        }
        
        .nav-item.active .nav-badge {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }
        
        /* Logout Button */
        .logout-btn {
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: 14px;
            padding: 14px 18px;
            display: flex;
            align-items: center;
            gap: 15px;
            cursor: pointer;
            transition: var(--transition);
            width: 100%;
            font-size: 15px;
            font-weight: 500;
            color: var(--gray-600);
            text-decoration: none;
            margin-top: 20px;
        }
        
        .logout-btn:hover {
            background: #fee2e2;
            border-color: #fecaca;
            color: #dc2626;
        }
        
        .logout-btn:hover i {
            color: #dc2626;
        }
        
        /* ===== MAIN CONTENT ===== */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px;
            background: #f8fafc;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1600px;
            margin: 0 auto;
        }
        
        /* ===== PAGE HEADER ===== */
        .page-header {
            background: white;
            border-radius: 20px;
            padding: 30px 35px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
            border-left: 6px solid var(--primary);
            border-top: 1px solid var(--gray-200);
            border-right: 1px solid var(--gray-200);
            border-bottom: 1px solid var(--gray-200);
            animation: fadeIn 0.5s ease;
        }
        
        .page-header h1 {
            color: var(--gray-700);
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .page-header h1 i {
            color: white;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            padding: 12px;
            border-radius: 14px;
            font-size: 24px;
            box-shadow: 0 8px 15px rgba(37, 99, 235, 0.2);
        }
        
        .page-header p {
            color: var(--gray-500);
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .page-header p i {
            color: var(--primary);
        }
        
        /* ===== REPORT FILTERS ===== */
        .filters-section {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }
        
        .filter-group {
            position: relative;
        }
        
        .filter-group i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary);
            font-size: 16px;
        }
        
        .filter-input,
        .filter-select {
            width: 100%;
            padding: 14px 20px 14px 50px;
            border: 2px solid var(--gray-200);
            border-radius: 40px;
            font-size: 14px;
            transition: var(--transition);
            background: white;
            color: var(--gray-600);
            outline: none;
        }
        
        .filter-select {
            padding: 14px 20px 14px 50px;
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 20px center;
            background-size: 16px;
        }
        
        .filter-input:focus,
        .filter-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }
        
        .filter-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 20px;
        }
        
        /* ===== BUTTONS ===== */
        .btn {
            padding: 12px 28px;
            border: none;
            border-radius: 40px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: white;
            color: var(--gray-600);
            border: 1px solid var(--gray-200);
        }
        
        .btn i {
            font-size: 16px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            box-shadow: 0 8px 18px rgba(37, 99, 235, 0.25);
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 25px rgba(37, 99, 235, 0.35);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border: none;
        }
        
        .btn-success:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 18px rgba(16, 185, 129, 0.3);
        }
        
        .btn-reset {
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
        }
        
        .btn-reset:hover {
            background: var(--gray-200);
            transform: translateY(-3px);
        }
        
        .btn-export {
            background: var(--success);
            color: white;
            border: none;
        }
        
        .btn-export:hover {
            background: #059669;
            transform: translateY(-3px);
        }
        
        /* ===== STATS CARDS ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 18px;
            padding: 20px;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-light);
        }
        
        .stat-icon {
            width: 48px;
            height: 48px;
            background: var(--primary-soft);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 22px;
            margin-bottom: 15px;
            transition: var(--transition);
        }
        
        .stat-card:hover .stat-icon {
            background: var(--primary);
            color: white;
            transform: scale(1.05);
        }
        
        .stat-content h3 {
            font-size: 24px;
            font-weight: 700;
            color: var(--gray-700);
            margin-bottom: 5px;
        }
        
        .stat-content p {
            color: var(--gray-500);
            font-size: 13px;
            font-weight: 500;
        }
        
        /* ===== REPORT CARDS ===== */
        .report-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .report-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
            transition: var(--transition);
        }
        
        .report-card:hover {
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-light);
        }
        
        .report-card.full-width {
            grid-column: span 2;
        }
        
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--gray-200);
        }
        
        .report-header h3 {
            font-size: 18px;
            font-weight: 700;
            color: var(--gray-700);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .report-header h3 i {
            color: var(--primary);
        }
        
        .report-header .badge {
            background: var(--primary-soft);
            color: var(--primary);
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            border: 1px solid var(--gray-200);
        }
        
        /* ===== CHART CONTAINER ===== */
        .chart-container {
            height: 300px;
            position: relative;
            margin: 20px 0;
        }
        
        /* ===== DATA TABLE ===== */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th {
            text-align: left;
            padding: 12px 10px;
            color: var(--gray-500);
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--gray-200);
        }
        
        .data-table td {
            padding: 10px;
            color: var(--gray-600);
            font-size: 14px;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .data-table tr:hover td {
            background: var(--gray-50);
        }
        
        /* ===== SUMMARY CARDS ===== */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-top: 15px;
        }
        
        .summary-item {
            background: var(--gray-50);
            border-radius: 16px;
            padding: 15px;
            text-align: center;
        }
        
        .summary-item .value {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 5px;
        }
        
        .summary-item .label {
            color: var(--gray-500);
            font-size: 12px;
            font-weight: 500;
        }
        
        /* ===== ANIMATIONS ===== */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .page-header, .filters-section, .stat-card, .report-card {
            animation: fadeIn 0.5s ease;
        }
        
        /* ===== CUSTOM SCROLLBAR ===== */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: var(--gray-100);
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, var(--gray-300), var(--gray-400));
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 1400px) {
            .stats-grid {
                grid-template-columns: repeat(3, 1fr);
            }
            
            .filter-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 1200px) {
            .report-grid {
                grid-template-columns: 1fr;
            }
            
            .report-card.full-width {
                grid-column: span 1;
            }
        }
        
        @media (max-width: 992px) {
            .sidebar {
                width: 80px;
                padding: 20px 10px;
            }
            
            .sidebar .logo-text,
            .sidebar .logo-badge,
            .sidebar .admin-info,
            .sidebar .nav-item span,
            .sidebar .logout-btn span {
                display: none;
            }
            
            .sidebar .logo {
                justify-content: center;
            }
            
            .sidebar .admin-profile {
                justify-content: center;
                padding: 15px;
            }
            
            .sidebar .nav-item {
                justify-content: center;
                padding: 15px;
            }
            
            .sidebar .nav-item i {
                margin: 0;
                font-size: 20px;
            }
            
            .sidebar .logout-btn {
                justify-content: center;
                padding: 15px;
            }
            
            .main-content {
                margin-left: 80px;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-actions {
                flex-direction: column;
            }
            
            .filter-actions .btn {
                width: 100%;
                justify-content: center;
            }
            
            .summary-cards {
                grid-template-columns: 1fr;
            }
        }
        
        /* ===== MENU TOGGLE ===== */
        .menu-toggle {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1001;
            background: white;
            width: 48px;
            height: 48px;
            border-radius: 14px;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            cursor: pointer;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
            font-size: 20px;
        }
        
        @media (max-width: 992px) {
            .menu-toggle {
                display: flex;
            }
            
            .sidebar {
                left: -280px;
                transition: left 0.3s ease;
                width: 280px;
            }
            
            .sidebar.active {
                left: 0;
            }
            
            .sidebar .logo-text,
            .sidebar .logo-badge,
            .sidebar .admin-info,
            .sidebar .nav-item span,
            .sidebar .logout-btn span {
                display: block;
            }
            
            .sidebar .logo {
                justify-content: flex-start;
            }
            
            .sidebar .admin-profile {
                justify-content: flex-start;
            }
            
            .sidebar .nav-item {
                justify-content: flex-start;
            }
        }
    </style>
</head>
<body>
    <!-- Menu Toggle -->
    <div class="menu-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </div>
    
    <!-- Dashboard Layout -->
    <div class="dashboard-layout">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <!-- Logo -->
            <div class="logo">
                <div class="logo-icon">
                    <i class="fas fa-chart-pie"></i>
                </div>
                <div>
                    <div class="logo-text">VaccinePro</div>
                    <div class="logo-badge">ADMIN</div>
                </div>
            </div>
            
            <!-- Admin Profile -->
            <div class="admin-profile">
                <div class="admin-avatar">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="admin-info">
                    <h4>Admin User</h4>
                    <p><span class="online-dot"></span> Online</p>
                </div>
            </div>
            
            <!-- Navigation -->
            <div class="nav-menu">
                <a href="dashboard.php" class="nav-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="manage_hospitals.php" class="nav-item">
                    <i class="fas fa-hospital"></i>
                    <span>Hospitals</span>
                </a>
                <a href="managevaccines.php" class="nav-item">
                    <i class="fas fa-syringe"></i>
                    <span>Vaccines</span>
                </a>
                <a href="appointment_requests.php" class="nav-item">
                    <i class="fas fa-calendar-check"></i>
                    <span>Requests</span>
                </a>
                <a href="users.php" class="nav-item">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
                </a>
                <a href="reports.php" class="nav-item active">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </div>
            
            <!-- Logout -->
            <a href="../logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="container">
                <!-- Page Header -->
                <div class="page-header">
                    <h1>
                        <i class="fas fa-chart-pie"></i>
                        Reports & Analytics
                    </h1>
                    <p><i class="fas fa-info-circle"></i> View detailed reports and analytics for your vaccination system</p>
                </div>
                
                <!-- Report Filters -->
                <div class="filters-section">
                    <form method="GET" action="" id="reportForm">
                        <div class="filter-grid">
                            <div class="filter-group">
                                <i class="fas fa-chart-bar"></i>
                                <select name="report_type" class="filter-select">
                                    <option value="vaccinations" <?php echo $report_type == 'vaccinations' ? 'selected' : ''; ?>>Vaccination Reports</option>
                                    <option value="appointments" <?php echo $report_type == 'appointments' ? 'selected' : ''; ?>>Appointment Reports</option>
                                    <option value="users" <?php echo $report_type == 'users' ? 'selected' : ''; ?>>User Reports</option>
                                    <option value="hospitals" <?php echo $report_type == 'hospitals' ? 'selected' : ''; ?>>Hospital Reports</option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <i class="fas fa-calendar"></i>
                                <select name="date_range" class="filter-select" onchange="toggleCustomDates()">
                                    <option value="today" <?php echo $date_range == 'today' ? 'selected' : ''; ?>>Today</option>
                                    <option value="week" <?php echo $date_range == 'week' ? 'selected' : ''; ?>>Last 7 Days</option>
                                    <option value="month" <?php echo $date_range == 'month' ? 'selected' : ''; ?>>Last 30 Days</option>
                                    <option value="quarter" <?php echo $date_range == 'quarter' ? 'selected' : ''; ?>>Last 3 Months</option>
                                    <option value="year" <?php echo $date_range == 'year' ? 'selected' : ''; ?>>Last Year</option>
                                    <option value="custom" <?php echo $date_range == 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                                </select>
                            </div>
                            
                            <div class="filter-group" id="startDateGroup" style="<?php echo $date_range == 'custom' ? 'display:block' : 'display:none'; ?>">
                                <i class="fas fa-calendar-plus"></i>
                                <input type="date" name="start_date" class="filter-input" value="<?php echo $start_date; ?>">
                            </div>
                            
                            <div class="filter-group" id="endDateGroup" style="<?php echo $date_range == 'custom' ? 'display:block' : 'display:none'; ?>">
                                <i class="fas fa-calendar-check"></i>
                                <input type="date" name="end_date" class="filter-input" value="<?php echo $end_date; ?>">
                            </div>
                        </div>
                        
                        <div class="filter-actions">
                            <button type="button" class="btn btn-reset" onclick="resetFilters()">
                                <i class="fas fa-redo"></i> Reset
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-chart-line"></i> Generate Report
                            </button>
                            <button type="button" class="btn btn-export" onclick="exportReport()">
                                <i class="fas fa-download"></i> Export
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Summary Statistics -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $summary['total_users']; ?></h3>
                            <p>Total Users</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-child"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $summary['total_children']; ?></h3>
                            <p>Total Children</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-syringe"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $summary['total_vaccinated']; ?></h3>
                            <p>Vaccinations Done</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $summary['pending_requests']; ?></h3>
                            <p>Pending Requests</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon">
                            <i class="fas fa-hospital"></i>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $summary['pending_hospitals']; ?></h3>
                            <p>Unverified Hospitals</p>
                        </div>
                    </div>
                </div>
                
                <!-- Report Content -->
                <?php if($report_type == 'vaccinations'): ?>
                <div class="report-grid">
                    <!-- Daily Vaccinations Chart -->
                    <div class="report-card full-width">
                        <div class="report-header">
                            <h3><i class="fas fa-chart-line"></i> Daily Vaccinations</h3>
                            <span class="badge"><?php echo date('d M Y', strtotime($start_date)); ?> - <?php echo date('d M Y', strtotime($end_date)); ?></span>
                        </div>
                        <div class="chart-container">
                            <canvas id="dailyChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Vaccine Distribution -->
                    <div class="report-card">
                        <div class="report-header">
                            <h3><i class="fas fa-pie-chart"></i> By Vaccine</h3>
                            <span class="badge">Distribution</span>
                        </div>
                        <div class="chart-container">
                            <canvas id="vaccineChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Top Hospitals -->
                    <div class="report-card">
                        <div class="report-header">
                            <h3><i class="fas fa-trophy"></i> Top Hospitals</h3>
                            <span class="badge">Most Vaccinations</span>
                        </div>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Hospital</th>
                                    <th>Vaccinations</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if($hospital_result && mysqli_num_rows($hospital_result) > 0):
                                    while($row = mysqli_fetch_assoc($hospital_result)):
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['hospital_name']); ?></td>
                                    <td><strong><?php echo $row['count']; ?></strong></td>
                                </tr>
                                <?php 
                                    endwhile;
                                else:
                                ?>
                                <tr>
                                    <td colspan="2" class="text-center">No data available</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <script>
                // Daily Chart
                const dailyCtx = document.getElementById('dailyChart').getContext('2d');
                new Chart(dailyCtx, {
                    type: 'line',
                    data: {
                        labels: [<?php 
                            $dates = [];
                            $counts = [];
                            if($daily_result && mysqli_num_rows($daily_result) > 0) {
                                mysqli_data_seek($daily_result, 0);
                                while($row = mysqli_fetch_assoc($daily_result)) {
                                    $dates[] = "'" . date('d M', strtotime($row['date'])) . "'";
                                    $counts[] = $row['count'];
                                }
                            }
                            echo implode(',', $dates);
                        ?>],
                        datasets: [{
                            label: 'Vaccinations',
                            data: [<?php echo implode(',', $counts); ?>],
                            borderColor: '#2563eb',
                            backgroundColor: 'rgba(37, 99, 235, 0.1)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
                
                // Vaccine Chart
                const vaccineCtx = document.getElementById('vaccineChart').getContext('2d');
                new Chart(vaccineCtx, {
                    type: 'doughnut',
                    data: {
                        labels: [<?php 
                            $vaccines = [];
                            $v_counts = [];
                            if($vaccine_result && mysqli_num_rows($vaccine_result) > 0) {
                                mysqli_data_seek($vaccine_result, 0);
                                while($row = mysqli_fetch_assoc($vaccine_result)) {
                                    $vaccines[] = "'" . addslashes($row['vaccine_name']) . "'";
                                    $v_counts[] = $row['count'];
                                }
                            }
                            echo implode(',', $vaccines);
                        ?>],
                        datasets: [{
                            data: [<?php echo implode(',', $v_counts); ?>],
                            backgroundColor: [
                                '#2563eb', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899',
                                '#06b6d4', '#f97316', '#6b7280', '#ef4444', '#14b8a6'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom'
                            }
                        }
                    }
                });
                </script>
                
                <?php elseif($report_type == 'appointments'): ?>
                <div class="report-grid">
                    <!-- Appointment Status -->
                    <div class="report-card">
                        <div class="report-header">
                            <h3><i class="fas fa-chart-pie"></i> Appointment Status</h3>
                            <span class="badge">Distribution</span>
                        </div>
                        <div class="chart-container">
                            <canvas id="statusChart"></canvas>
                        </div>
                        <div class="summary-cards">
                            <?php 
                            $total_appointments = 0;
                            if($appointment_status_result && mysqli_num_rows($appointment_status_result) > 0) {
                                mysqli_data_seek($appointment_status_result, 0);
                                while($row = mysqli_fetch_assoc($appointment_status_result)) {
                                    $total_appointments += $row['count'];
                                }
                            }
                            ?>
                            <div class="summary-item">
                                <div class="value"><?php echo $total_appointments; ?></div>
                                <div class="label">Total Appointments</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Appointments by Hospital -->
                    <div class="report-card">
                        <div class="report-header">
                            <h3><i class="fas fa-hospital"></i> By Hospital</h3>
                            <span class="badge">Top 10</span>
                        </div>
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Hospital</th>
                                    <th>Appointments</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if($appointment_hospital_result && mysqli_num_rows($appointment_hospital_result) > 0):
                                    while($row = mysqli_fetch_assoc($appointment_hospital_result)):
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['hospital_name']); ?></td>
                                    <td><strong><?php echo $row['count']; ?></strong></td>
                                </tr>
                                <?php 
                                    endwhile;
                                else:
                                ?>
                                <tr>
                                    <td colspan="2" class="text-center">No data available</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <script>
                // Status Chart
                const statusCtx = document.getElementById('statusChart').getContext('2d');
                new Chart(statusCtx, {
                    type: 'pie',
                    data: {
                        labels: [<?php 
                            $statuses = [];
                            $s_counts = [];
                            if($appointment_status_result && mysqli_num_rows($appointment_status_result) > 0) {
                                mysqli_data_seek($appointment_status_result, 0);
                                while($row = mysqli_fetch_assoc($appointment_status_result)) {
                                    $statuses[] = "'" . ucfirst($row['request_status']) . "'";
                                    $s_counts[] = $row['count'];
                                }
                            }
                            echo implode(',', $statuses);
                        ?>],
                        datasets: [{
                            data: [<?php echo implode(',', $s_counts); ?>],
                            backgroundColor: ['#10b981', '#f59e0b', '#ef4444', '#6b7280']
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });
                </script>
                
                <?php elseif($report_type == 'users'): ?>
                <div class="report-grid">
                    <!-- Users by Type -->
                    <div class="report-card">
                        <div class="report-header">
                            <h3><i class="fas fa-chart-pie"></i> Users by Type</h3>
                            <span class="badge">Distribution</span>
                        </div>
                        <div class="chart-container">
                            <canvas id="userTypeChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- New Users Over Time -->
                    <div class="report-card">
                        <div class="report-header">
                            <h3><i class="fas fa-chart-line"></i> New Users</h3>
                            <span class="badge"><?php echo date('d M Y', strtotime($start_date)); ?> - <?php echo date('d M Y', strtotime($end_date)); ?></span>
                        </div>
                        <div class="chart-container">
                            <canvas id="newUsersChart"></canvas>
                        </div>
                    </div>
                </div>
                
                <script>
                // User Type Chart
                const userTypeCtx = document.getElementById('userTypeChart').getContext('2d');
                new Chart(userTypeCtx, {
                    type: 'doughnut',
                    data: {
                        labels: [<?php 
                            $types = [];
                            $t_counts = [];
                            if($user_type_result && mysqli_num_rows($user_type_result) > 0) {
                                mysqli_data_seek($user_type_result, 0);
                                while($row = mysqli_fetch_assoc($user_type_result)) {
                                    $types[] = "'" . ucfirst($row['user_type']) . "'";
                                    $t_counts[] = $row['count'];
                                }
                            }
                            echo implode(',', $types);
                        ?>],
                        datasets: [{
                            data: [<?php echo implode(',', $t_counts); ?>],
                            backgroundColor: ['#2563eb', '#10b981', '#f59e0b']
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });
                
                // New Users Chart
                const newUsersCtx = document.getElementById('newUsersChart').getContext('2d');
                new Chart(newUsersCtx, {
                    type: 'bar',
                    data: {
                        labels: [<?php 
                            $dates = [];
                            $n_counts = [];
                            if($new_users_result && mysqli_num_rows($new_users_result) > 0) {
                                mysqli_data_seek($new_users_result, 0);
                                while($row = mysqli_fetch_assoc($new_users_result)) {
                                    $dates[] = "'" . date('d M', strtotime($row['date'])) . "'";
                                    $n_counts[] = $row['count'];
                                }
                            }
                            echo implode(',', $dates);
                        ?>],
                        datasets: [{
                            label: 'New Users',
                            data: [<?php echo implode(',', $n_counts); ?>],
                            backgroundColor: '#2563eb'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
                </script>
                
                <?php elseif($report_type == 'hospitals'): ?>
                <div class="report-grid">
                    <!-- Hospitals by City -->
                    <div class="report-card">
                        <div class="report-header">
                            <h3><i class="fas fa-city"></i> Hospitals by City</h3>
                            <span class="badge">Distribution</span>
                        </div>
                        <div class="chart-container">
                            <canvas id="cityChart"></canvas>
                        </div>
                    </div>
                    
                    <!-- Verified vs Unverified -->
                    <div class="report-card">
                        <div class="report-header">
                            <h3><i class="fas fa-check-circle"></i> Verification Status</h3>
                            <span class="badge">Overview</span>
                        </div>
                        <div class="chart-container">
                            <canvas id="verifiedChart"></canvas>
                        </div>
                        <div class="summary-cards">
                            <?php 
                            $verified_count = 0;
                            $pending_count = 0;
                            if($hospital_verified_result && mysqli_num_rows($hospital_verified_result) > 0) {
                                mysqli_data_seek($hospital_verified_result, 0);
                                while($row = mysqli_fetch_assoc($hospital_verified_result)) {
                                    if($row['status'] == 'Verified') {
                                        $verified_count = $row['count'];
                                    } else {
                                        $pending_count = $row['count'];
                                    }
                                }
                            }
                            ?>
                            <div class="summary-item">
                                <div class="value"><?php echo $verified_count; ?></div>
                                <div class="label">Verified</div>
                            </div>
                            <div class="summary-item">
                                <div class="value"><?php echo $pending_count; ?></div>
                                <div class="label">Pending</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <script>
                // City Chart
                const cityCtx = document.getElementById('cityChart').getContext('2d');
                new Chart(cityCtx, {
                    type: 'bar',
                    data: {
                        labels: [<?php 
                            $cities = [];
                            $c_counts = [];
                            if($hospital_city_result && mysqli_num_rows($hospital_city_result) > 0) {
                                mysqli_data_seek($hospital_city_result, 0);
                                while($row = mysqli_fetch_assoc($hospital_city_result)) {
                                    $cities[] = "'" . addslashes($row['city']) . "'";
                                    $c_counts[] = $row['count'];
                                }
                            }
                            echo implode(',', $cities);
                        ?>],
                        datasets: [{
                            label: 'Hospitals',
                            data: [<?php echo implode(',', $c_counts); ?>],
                            backgroundColor: '#2563eb'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
                
                // Verified Chart
                const verifiedCtx = document.getElementById('verifiedChart').getContext('2d');
                new Chart(verifiedCtx, {
                    type: 'pie',
                    data: {
                        labels: ['Verified', 'Pending'],
                        datasets: [{
                            data: [<?php echo $verified_count; ?>, <?php echo $pending_count; ?>],
                            backgroundColor: ['#10b981', '#f59e0b']
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });
                </script>
                
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Toggle Sidebar
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }
        
        // Toggle Custom Date Inputs
        function toggleCustomDates() {
            const dateRange = document.querySelector('select[name="date_range"]').value;
            const startGroup = document.getElementById('startDateGroup');
            const endGroup = document.getElementById('endDateGroup');
            
            if(dateRange === 'custom') {
                startGroup.style.display = 'block';
                endGroup.style.display = 'block';
            } else {
                startGroup.style.display = 'none';
                endGroup.style.display = 'none';
            }
        }
        
        // Reset Filters
        function resetFilters() {
            window.location.href = 'reports.php';
        }
        
        // Export Report
        function exportReport() {
            const reportType = document.querySelector('select[name="report_type"]').value;
            const startDate = document.querySelector('input[name="start_date"]')?.value || '<?php echo $start_date; ?>';
            const endDate = document.querySelector('input[name="end_date"]')?.value || '<?php echo $end_date; ?>';
            
            window.location.href = `export_report.php?type=${reportType}&start_date=${startDate}&end_date=${endDate}`;
        }
    </script>
</body>
</html>
<?php mysqli_close($connection); ?>