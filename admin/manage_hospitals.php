<?php
session_start();
if(!isset($_SESSION["logged_in"]) || $_SESSION["user_type"] != "admin"){
    header("location:../login.php");
    exit();
}

include("../dbconnection.php");

// Get filter values
$filter_city = isset($_GET['city']) ? $_GET['city'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';

// Build query with filters
$query_hospitals = "SELECT h.*, u.email, u.phone, u.is_active as user_active,
                    (SELECT COUNT(*) FROM vaccination_bookings vb WHERE vb.hospital_id = h.hospital_id) as total_bookings,
                    (SELECT COUNT(*) FROM appointment_requests ar WHERE ar.hospital_id = h.hospital_id) as total_requests
                    FROM hospitals h
                    JOIN users u ON h.user_id = u.user_id
                    WHERE 1=1";

if(!empty($filter_city)) {
    $query_hospitals .= " AND h.city LIKE '%" . mysqli_real_escape_string($connection, $filter_city) . "%'";
}

if($filter_status == 'active') {
    $query_hospitals .= " AND h.is_active = 1";
} elseif($filter_status == 'inactive') {
    $query_hospitals .= " AND h.is_active = 0";
} elseif($filter_status == 'verified') {
    $query_hospitals .= " AND h.is_verified = 1";
} elseif($filter_status == 'pending') {
    $query_hospitals .= " AND h.is_verified = 0";
}

$query_hospitals .= " ORDER BY h.created_at DESC";
$result_hospitals = mysqli_query($connection, $query_hospitals);

// Get statistics
$query_stats = "SELECT 
    COUNT(*) as total_hospitals,
    SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) as verified_hospitals,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_hospitals,
    (SELECT COUNT(DISTINCT city) FROM hospitals) as cities_count
    FROM hospitals";
$result_stats = mysqli_query($connection, $query_stats);
$stats = mysqli_fetch_assoc($result_stats);

// Get unique cities for filter
$query_cities = "SELECT DISTINCT city FROM hospitals WHERE city IS NOT NULL ORDER BY city";
$result_cities = mysqli_query($connection, $query_cities);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Management - Admin Panel</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
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
            display: block;
            min-height: 100vh;
        }
        
        /* ===== TOP NAVBAR ===== */
        .admin-navbar {
            background: #ffffff;
            border-bottom: 2px solid #e8eeff;
            padding: 0 35px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 68px;
            box-shadow: 0 2px 16px rgba(26,111,196,0.08);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .admin-navbar .logo { display: flex; align-items: center; gap: 10px; }
        .admin-navbar .logo-icon {
            width: 40px; height: 40px;
            background: linear-gradient(135deg, #1a6fc4, #1155a0);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; color: white;
        }
        .admin-navbar .logo h2 { font-size: 20px; font-weight: 700; color: #1155a0; letter-spacing: -0.3px; }
        .nav-links { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
        .nav-links a {
            color: #4b6cb7; text-decoration: none; padding: 8px 14px;
            border-radius: 8px; font-size: 13.5px; font-weight: 500;
            transition: all 0.2s; display: flex; align-items: center; gap: 6px;
        }
        .nav-links a:hover { background: #eff6ff; color: #1155a0; }
        .nav-links a.active { background: #dbeafe; color: #1155a0; font-weight: 600; }
        .nav-links a.logout { background: #fee2e2; color: #dc2626; }
        .nav-links a.logout:hover { background: #fecaca; }

        /* ===== MAIN CONTENT ===== */
        .main-content {
            padding: 30px;
            background: #f8fafc;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1600px;
            margin: 0 auto;
        }
        
        /* ===== PAGE HEADER ===== */
        /* ===== PAGE HEADER ===== */
        .page-header {
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 8px 30px rgba(37,99,235,0.13);
            animation: fadeIn 0.5s ease;
            display: flex;
            align-items: stretch;
            overflow: hidden;
            min-height: 155px;
            border: none;
            padding: 0;
            position: relative;
        }

        .page-header-left {
            flex: 1;
            padding: 36px 42px;
            background: linear-gradient(135deg, #1a6fc4 0%, #0d47a1 100%);
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            overflow: hidden;
        }

        /* decorative circles */
        .page-header-left::before {
            content: '';
            position: absolute;
            top: -25px; right: 20px;
            width: 100px; height: 100px;
            border: 3px solid rgba(255,255,255,0.10);
            border-radius: 50%;
            box-shadow: 0 0 0 22px rgba(255,255,255,0.05), 0 0 0 44px rgba(255,255,255,0.03);
        }
        .page-header-left::after {
            content: '';
            position: absolute;
            bottom: -35px; left: -20px;
            width: 130px; height: 130px;
            background: rgba(255,255,255,0.06);
            transform: rotate(45deg);
        }

        .page-header h1 {
            color: #ffffff;
            font-size: 30px;
            font-weight: 800;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 14px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            position: relative;
            z-index: 1;
        }

        .page-header h1 i {
            color: white;
            background: rgba(255,255,255,0.18);
            padding: 11px 13px;
            border-radius: 13px;
            font-size: 22px;
            border: 1.5px solid rgba(255,255,255,0.30);
            backdrop-filter: blur(4px);
        }

        .page-header p {
            color: rgba(255,255,255,0.85);
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            position: relative;
            z-index: 1;
            margin: 0;
            max-width: 560px;
            line-height: 1.6;
        }

        .page-header p i {
            color: rgba(255,255,255,0.70);
        }

        .page-header-right {
            width: 260px;
            flex-shrink: 0;
            overflow: hidden;
            position: relative;
        }

        .page-header-right img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        @media (max-width: 768px) {
            .page-header { flex-direction: column; }
            .page-header-right { width: 100%; height: 160px; }
            .page-header-left { padding: 26px 24px; }
            .page-header h1 { font-size: 22px; }
        }
        
        /* ===== STATS GRID ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
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
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-light);
        }
        
        .stat-icon-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .stat-icon {
            width: 55px;
            height: 55px;
            background: var(--primary-soft);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 24px;
            transition: var(--transition);
        }
        
        .stat-card:hover .stat-icon {
            background: var(--primary);
            color: white;
            transform: scale(1.05) rotate(5deg);
        }
        
        .stat-trend {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 13px;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 30px;
            background: var(--gray-50);
            color: var(--gray-600);
        }
        
        .trend-up { color: #10b981; }
        .trend-down { color: #ef4444; }
        
        .stat-content h3 {
            font-size: 32px;
            font-weight: 700;
            color: var(--gray-700);
            margin-bottom: 5px;
        }
        
        .stat-content p {
            color: var(--gray-500);
            font-size: 14px;
            font-weight: 500;
        }
        
        /* ===== FILTERS SECTION ===== */
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
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 20px;
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
        
        .btn-search {
            background: var(--primary);
            color: white;
            border: none;
        }
        
        .btn-search:hover {
            background: var(--primary-dark);
            transform: translateY(-3px);
            box-shadow: 0 8px 18px rgba(37, 99, 235, 0.25);
        }
        
        .btn-add {
            background: #10b981;
            color: white;
            border: none;
        }
        
        .btn-add:hover {
            background: #059669;
            transform: translateY(-3px);
        }
        
        /* ===== VIEW TOGGLE ===== */
        .view-toggle {
            background: white;
            border-radius: 20px;
            padding: 20px 25px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
        }
        
        .view-toggle h3 {
            color: var(--gray-700);
            font-size: 18px;
            font-weight: 600;
        }
        
        .view-toggle p {
            color: var(--gray-500);
            font-size: 14px;
            margin-top: 5px;
        }
        
        .view-buttons {
            display: flex;
            gap: 12px;
        }
        
        .view-btn {
            padding: 10px 22px;
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
            border-radius: 30px;
            cursor: pointer;
            color: var(--gray-600);
            font-weight: 500;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: var(--transition);
        }
        
        .view-btn i {
            color: var(--primary);
            font-size: 16px;
        }
        
        .view-btn:hover {
            background: var(--primary-soft);
            transform: translateY(-2px);
        }
        
        .view-btn.active {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            box-shadow: 0 8px 18px rgba(37, 99, 235, 0.25);
        }
        
        .view-btn.active i {
            color: white;
        }
        
        /* ===== HOSPITALS GRID ===== */
        .hospitals-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .hospital-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
            transition: var(--transition);
        }
        
        .hospital-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
            border-color: var(--primary-light);
        }
        
        .hospital-header {
            padding: 25px;
            border-bottom: 1px solid var(--gray-200);
            position: relative;
            background: linear-gradient(to bottom, white, var(--gray-50));
        }
        
        .hospital-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .badge-success {
            background: var(--success-light);
            color: #059669;
            border: 1px solid #a7f3d0;
        }
        
        .badge-warning {
            background: var(--warning-light);
            color: #d97706;
            border: 1px solid #fde68a;
        }
        
        .hospital-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 30px;
            margin-bottom: 20px;
            box-shadow: 0 10px 20px rgba(37, 99, 235, 0.25);
            transition: var(--transition);
        }
        
        .hospital-card:hover .hospital-icon {
            transform: scale(1.05) rotate(5deg);
        }
        
        .hospital-header h3 {
            color: var(--gray-700);
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .hospital-header p {
            color: var(--gray-500);
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .hospital-header p i {
            color: var(--primary);
        }
        
        .hospital-details {
            padding: 20px 25px;
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .detail-item:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            color: var(--gray-500);
            font-weight: 500;
            font-size: 14px;
        }
        
        .detail-value {
            color: var(--gray-700);
            font-weight: 600;
            font-size: 14px;
        }
        
        .hospital-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin: 20px 0 10px;
            padding: 15px;
            background: var(--gray-50);
            border-radius: 16px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 22px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 4px;
        }
        
        .stat-label {
            font-size: 12px;
            color: var(--gray-500);
            font-weight: 500;
        }
        
        .hospital-actions {
            padding: 0 25px 25px;
            display: flex;
            gap: 12px;
        }
        
        .action-btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 40px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .action-btn.view {
            background: var(--primary-soft);
            color: var(--primary);
        }
        
        .action-btn.view:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 8px 18px rgba(37, 99, 235, 0.2);
        }
        
        .action-btn.verify {
            background: var(--success-light);
            color: #059669;
        }
        
        .action-btn.verify:hover {
            background: #10b981;
            color: white;
            transform: translateY(-3px);
        }
        
        .action-btn.activate {
            background: var(--primary-soft);
            color: var(--primary);
        }
        
        .action-btn.activate:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-3px);
        }
        
        .action-btn.deactivate {
            background: var(--danger-light);
            color: #dc2626;
        }
        
        .action-btn.deactivate:hover {
            background: #ef4444;
            color: white;
            transform: translateY(-3px);
        }
        
        /* ===== TABLE VIEW ===== */
        .table-view {
            display: none;
        }
        
        .table-view.active {
            display: block;
        }
        
        .table-container {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
            overflow-x: auto;
        }
        
        .hospital-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1200px;
        }
        
        .hospital-table th {
            padding: 18px 15px;
            text-align: left;
            color: var(--gray-600);
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--gray-200);
            background: var(--gray-50);
        }
        
        .hospital-table td {
            padding: 16px 15px;
            color: var(--gray-600);
            font-size: 14px;
            border-bottom: 1px solid var(--gray-200);
        }
        
        .hospital-table tr:hover {
            background: var(--gray-50);
        }
        
        /* ===== EMPTY STATE ===== */
        .empty-state {
            background: white;
            border-radius: 20px;
            padding: 80px 40px;
            text-align: center;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
        }
        
        .empty-state i {
            font-size: 70px;
            color: var(--gray-300);
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            color: var(--gray-700);
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 12px;
        }
        
        .empty-state p {
            color: var(--gray-500);
            font-size: 16px;
            margin-bottom: 25px;
        }
        
        /* ===== PAGINATION ===== */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
            margin-top: 30px;
        }
        
        .pagination-btn {
            padding: 12px 25px;
            background: white;
            border: 2px solid var(--gray-200);
            border-radius: 40px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--gray-600);
            transition: var(--transition);
        }
        
        .pagination-btn:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            transform: translateY(-3px);
            box-shadow: 0 8px 18px rgba(37, 99, 235, 0.2);
        }
        
        .page-numbers {
            display: flex;
            gap: 8px;
        }
        
        .page-number {
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 14px;
            cursor: pointer;
            font-weight: 600;
            font-size: 15px;
            border: 2px solid var(--gray-200);
            background: white;
            color: var(--gray-600);
            transition: var(--transition);
        }
        
        .page-number:hover {
            background: var(--primary-soft);
            border-color: var(--primary);
            transform: translateY(-2px);
        }
        
        .page-number.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            box-shadow: 0 8px 18px rgba(37, 99, 235, 0.25);
        }
        
        /* ===== ANIMATIONS ===== */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 1400px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .hospitals-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 1200px) {
            .filter-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 992px) {
            .nav-links { gap: 4px; }
            .nav-links a { padding: 6px 10px; font-size: 12.5px; }
            .main-content { padding: 20px; }
        }
        
        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-actions {
                flex-direction: column;
            }
            
            .filter-actions .btn {
                width: 100%;
                justify-content: center;
            }
            
            .view-toggle {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .hospital-actions {
                flex-direction: column;
            }
            
            .pagination {
                flex-wrap: wrap;
            }
        }
        
        /* ===== LOADING ===== */
        .loading {
            position: relative;
            pointer-events: none;
            opacity: 0.7;
        }
        
        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 30px;
            height: 30px;
            border: 3px solid var(--gray-200);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            transform: translate(-50%, -50%);
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <!-- Dashboard Layout -->
    <div class="dashboard-layout">
        <!-- Top Navbar -->
        <nav class="admin-navbar">
            <div class="logo">
                <div class="logo-icon"><i class="fas fa-hospital-alt"></i></div>
                <h2>Admin Panel</h2>
            </div>
            <div class="nav-links">
                <a href="dashboard.php"> Dashboard</a>
                <a href="manage_children.php"> Children</a>
                <a href="manage_hospitals.php" class="active"> Hospitals</a>
                <a href="appointment_requests.php">Requests</a>
                <a href="managevaccines.php">Vaccines</a>
                <a href="bookingdetail.php">Bookings</a>
                <a href="vaccination_reports.php"> Reports</a>
                <a href="system_settings.php"> Settings</a>
                <a href="../logout.php" class="logout"> Logout</a>
            </div>
        </nav>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="container">
                <!-- Page Header -->
                <!-- Page Header -->
                <div class="page-header">
                    <div class="page-header-left">
                        <h1>
                            <i class="fas fa-hospital-alt"></i>
                            Hospital Management
                        </h1>
                        <p><i class="fas fa-info-circle"></i> Manage all registered hospitals, verify credentials, and activate/deactivate as needed</p>
                    </div>
                    <div class="page-header-right">
                        <img src="https://images.unsplash.com/photo-1586773860418-d37222d8fce3?w=500&q=80" alt="Hospitals" />
                    </div>
                </div>
                
                <!-- Stats Cards -->
                <div class="stats-grid">
                    <!-- Total Hospitals -->
                    <div class="stat-card">
                        <div class="stat-icon-wrapper">
                            <div class="stat-icon">
                                <i class="fas fa-hotel"></i>
                            </div>
                            <span class="stat-trend">
                                <i class="fas fa-arrow-up trend-up"></i> +12%
                            </span>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['total_hospitals']; ?></h3>
                            <p>Total Hospitals</p>
                        </div>
                    </div>
                    
                    <!-- Verified Hospitals -->
                    <div class="stat-card">
                        <div class="stat-icon-wrapper">
                            <div class="stat-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <span class="stat-trend">
                                <i class="fas fa-arrow-up trend-up"></i> +8%
                            </span>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['verified_hospitals']; ?></h3>
                            <p>Verified Hospitals</p>
                        </div>
                    </div>
                    
                    <!-- Active Hospitals -->
                    <div class="stat-card">
                        <div class="stat-icon-wrapper">
                            <div class="stat-icon">
                                <i class="fas fa-toggle-on"></i>
                            </div>
                            <span class="stat-trend">
                                <i class="fas fa-arrow-up trend-up"></i> +5%
                            </span>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['active_hospitals']; ?></h3>
                            <p>Active Hospitals</p>
                        </div>
                    </div>
                    
                    <!-- Cities Covered -->
                    <div class="stat-card">
                        <div class="stat-icon-wrapper">
                            <div class="stat-icon">
                                <i class="fas fa-city"></i>
                            </div>
                            <span class="stat-trend">
                                <i class="fas fa-arrow-up trend-up"></i> +3
                            </span>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['cities_count']; ?></h3>
                            <p>Cities Covered</p>
                        </div>
                    </div>
                </div>
                
                <!-- Filters -->
                <form id="filterForm" method="GET" class="filters-section">
                    <div class="filter-grid">
                        <div class="filter-group">
                            <i class="fas fa-search"></i>
                            <input type="text" 
                                   name="search"
                                   class="filter-input"
                                   placeholder="Search by hospital name..."
                                   value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                        </div>
                        
                        <div class="filter-group">
                            <i class="fas fa-city"></i>
                            <select name="city" class="filter-select">
                                <option value="">All Cities</option>
                                <?php while($city = mysqli_fetch_assoc($result_cities)): ?>
                                <option value="<?php echo $city['city']; ?>" 
                                    <?php echo ($filter_city == $city['city']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($city['city']); ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <i class="fas fa-filter"></i>
                            <select name="status" class="filter-select">
                                <option value="all" <?php echo ($filter_status == 'all') ? 'selected' : ''; ?>>All Status</option>
                                <option value="active" <?php echo ($filter_status == 'active') ? 'selected' : ''; ?>>Active Only</option>
                                <option value="inactive" <?php echo ($filter_status == 'inactive') ? 'selected' : ''; ?>>Inactive Only</option>
                                <option value="verified" <?php echo ($filter_status == 'verified') ? 'selected' : ''; ?>>Verified Only</option>
                                <option value="pending" <?php echo ($filter_status == 'pending') ? 'selected' : ''; ?>>Pending Verification</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="filter-actions">
                        <button type="button" class="btn btn-reset" onclick="resetFilters()">
                            <i class="fas fa-redo"></i> Reset
                        </button>
                        <button type="submit" class="btn btn-search">
                            <i class="fas fa-search"></i> Search
                        </button>
                        <button type="button" class="btn btn-success" onclick="window.location.href='add_hospital.php'">
                            <i class="fas fa-plus"></i> Add Hospital
                        </button>
                    </div>
                </form>
                
                <!-- View Toggle -->
                <div class="view-toggle">
                    <div>
                        <h3><?php echo mysqli_num_rows($result_hospitals); ?> Hospitals Found</h3>
                        <?php if($filter_city): ?>
                        <p><i class="fas fa-filter"></i> Filtered by: <?php echo htmlspecialchars($filter_city); ?></p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="view-buttons">
                        <button class="view-btn active" onclick="toggleView('grid')">
                            <i class="fas fa-th-large"></i> Grid
                        </button>
                        <button class="view-btn" onclick="toggleView('table')">
                            <i class="fas fa-table"></i> Table
                        </button>
                    </div>
                </div>
                
                <?php if(mysqli_num_rows($result_hospitals) > 0): ?>
                
                <!-- Grid View -->
                <div id="gridView" class="hospitals-grid">
                    <?php 
                    mysqli_data_seek($result_hospitals, 0);
                    while($row = mysqli_fetch_assoc($result_hospitals)): 
                        $isVerified = $row['is_verified'];
                        $isActive = $row['is_active'];
                    ?>
                    <div class="hospital-card">
                        <div class="hospital-header">
                            <?php if($isVerified): ?>
                            <span class="hospital-badge badge-success">
                                <i class="fas fa-check-circle"></i> Verified
                            </span>
                            <?php else: ?>
                            <span class="hospital-badge badge-warning">
                                <i class="fas fa-clock"></i> Pending
                            </span>
                            <?php endif; ?>
                            
                            <div class="hospital-icon">
                                <i class="fas fa-hospital"></i>
                            </div>
                            
                            <h3><?php echo htmlspecialchars($row['hospital_name']); ?></h3>
                            <p>
                                <i class="fas fa-map-marker-alt"></i>
                                <?php echo htmlspecialchars($row['city']); ?>, <?php echo htmlspecialchars($row['state']); ?>
                            </p>
                        </div>
                        
                        <div class="hospital-details">
                            <div class="detail-item">
                                <span class="detail-label">Registration:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($row['registration_number']); ?></span>
                            </div>
                            
                            <div class="detail-item">
                                <span class="detail-label">Contact:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($row['contact_person']); ?></span>
                            </div>
                            
                            <div class="detail-item">
                                <span class="detail-label">Phone:</span>
                                <span class="detail-value"><?php echo $row['phone'] ? htmlspecialchars($row['phone']) : 'N/A'; ?></span>
                            </div>
                            
                            <div class="hospital-stats">
                                <div class="stat-item">
                                    <div class="stat-number"><?php echo $row['total_bookings']; ?></div>
                                    <div class="stat-label">Bookings</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number"><?php echo $row['total_requests']; ?></div>
                                    <div class="stat-label">Requests</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="hospital-actions">
                            <button class="action-btn view" onclick="window.location.href='hospital_details.php?id=<?php echo $row['hospital_id']; ?>'">
                                <i class="fas fa-eye"></i> View
                            </button>
                            
                            <?php if(!$isVerified): ?>
                            <button class="action-btn verify" 
                                    onclick="if(confirm('Verify this hospital?')) window.location.href='verify_hospital.php?id=<?php echo $row['hospital_id']; ?>'">
                                <i class="fas fa-check-circle"></i> Verify
                            </button>
                            <?php endif; ?>
                            
                            <?php if($isActive): ?>
                            <button class="action-btn deactivate" 
                                    onclick="if(confirm('Deactivate this hospital?')) window.location.href='toggle_hospital_status.php?id=<?php echo $row['hospital_id']; ?>&action=deactivate'">
                                <i class="fas fa-toggle-off"></i> Deactivate
                            </button>
                            <?php else: ?>
                            <button class="action-btn activate" 
                                    onclick="if(confirm('Activate this hospital?')) window.location.href='toggle_hospital_status.php?id=<?php echo $row['hospital_id']; ?>&action=activate'">
                                <i class="fas fa-toggle-on"></i> Activate
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
                
                <!-- Table View -->
                <div id="tableView" class="table-view">
                    <div class="table-container">
                        <table class="hospital-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Hospital Name</th>
                                    <th>Registration</th>
                                    <th>Location</th>
                                    <th>Contact Person</th>
                                    <th>Phone</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                mysqli_data_seek($result_hospitals, 0);
                                while($row = mysqli_fetch_assoc($result_hospitals)): 
                                ?>
                                <tr>
                                    <td>#<?php echo $row['hospital_id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($row['hospital_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($row['registration_number']); ?></td>
                                    <td><?php echo htmlspecialchars($row['city']); ?></td>
                                    <td><?php echo htmlspecialchars($row['contact_person']); ?></td>
                                    <td><?php echo $row['phone'] ? htmlspecialchars($row['phone']) : 'N/A'; ?></td>
                                    <td>
                                        <div style="display: flex; gap: 5px;">
                                            <?php if($row['is_verified']): ?>
                                            <span class="badge-success" style="padding: 4px 10px; border-radius: 30px; font-size: 11px;">Verified</span>
                                            <?php else: ?>
                                            <span class="badge-warning" style="padding: 4px 10px; border-radius: 30px; font-size: 11px;">Pending</span>
                                            <?php endif; ?>
                                            
                                            <?php if($row['is_active']): ?>
                                            <span class="badge-success" style="padding: 4px 10px; border-radius: 30px; font-size: 11px;">Active</span>
                                            <?php else: ?>
                                            <span style="background: #fee2e2; color: #dc2626; padding: 4px 10px; border-radius: 30px; font-size: 11px;">Inactive</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 5px;">
                                            <button class="action-btn view" style="padding: 6px 12px;" onclick="window.location.href='hospital_details.php?id=<?php echo $row['hospital_id']; ?>'">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if(!$row['is_verified']): ?>
                                            <button class="action-btn verify" style="padding: 6px 12px;" onclick="window.location.href='verify_hospital.php?id=<?php echo $row['hospital_id']; ?>'">
                                                <i class="fas fa-check-circle"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Pagination -->
                <div class="pagination">
                    <button class="pagination-btn">
                        <i class="fas fa-chevron-left"></i> Prev
                    </button>
                    <div class="page-numbers">
                        <span class="page-number active">1</span>
                        <span class="page-number">2</span>
                        <span class="page-number">3</span>
                        <span class="page-number">4</span>
                        <span class="page-number">5</span>
                    </div>
                    <button class="pagination-btn">
                        Next <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
                
                <?php else: ?>
                
                <!-- Empty State -->
                <div class="empty-state">
                    <i class="fas fa-hospital"></i>
                    <h3>No Hospitals Found</h3>
                    <p>No hospitals match your current filters.</p>
                    <button class="btn btn-primary" onclick="window.location.href='manage_hospitals.php'">
                        <i class="fas fa-redo"></i> Reset Filters
                    </button>
                </div>
                
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Toggle Sidebar
        // Toggle View
        function toggleView(viewType) {
            const gridView = document.getElementById('gridView');
            const tableView = document.getElementById('tableView');
            const gridBtn = document.querySelector('.view-btn:nth-child(1)');
            const tableBtn = document.querySelector('.view-btn:nth-child(2)');
            
            if(viewType === 'grid') {
                gridView.style.display = 'grid';
                tableView.style.display = 'none';
                gridBtn.classList.add('active');
                tableBtn.classList.remove('active');
            } else {
                gridView.style.display = 'none';
                tableView.style.display = 'block';
                gridBtn.classList.remove('active');
                tableBtn.classList.add('active');
            }
        }
        
        // Reset Filters
        function resetFilters() {
            window.location.href = 'manage_hospitals.php';
        }
        
        // Set default view
        document.addEventListener('DOMContentLoaded', function() {
            toggleView('grid');
        });
    </script>
</body>
</html>
<?php mysqli_close($connection); ?>