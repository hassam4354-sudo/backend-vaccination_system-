<?php
session_start();
if(!isset($_SESSION["logged_in"]) || $_SESSION["user_type"] != "admin"){
    header("location:../login.php");
    exit();
}

include("../dbconnection.php");

// Get filter values
$filter_user_type = isset($_GET['user_type']) ? $_GET['user_type'] : 'all';
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 15;
$offset = ($page - 1) * $limit;

// Build query with filters
$query_users = "SELECT u.*, 
                CASE 
                    WHEN u.user_type = 'hospital' THEN h.hospital_name
                    WHEN u.user_type = 'parent' THEN p.full_name
                    WHEN u.user_type = 'admin' THEN a.full_name
                    ELSE NULL
                END as profile_name,
                CASE 
                    WHEN u.user_type = 'hospital' THEN h.contact_person
                    WHEN u.user_type = 'parent' THEN p.emergency_contact
                    ELSE NULL
                END as contact_info,
                h.is_verified as hospital_verified,
                h.hospital_name,
                p.full_name as parent_name,
                a.full_name as admin_name
                FROM users u
                LEFT JOIN hospitals h ON u.user_id = h.user_id AND u.user_type = 'hospital'
                LEFT JOIN parents p ON u.user_id = p.user_id AND u.user_type = 'parent'
                LEFT JOIN admins a ON u.user_id = a.user_id AND u.user_type = 'admin'
                WHERE 1=1";

if(!empty($search)) {
    $search = mysqli_real_escape_string($connection, $search);
    $query_users .= " AND (u.email LIKE '%$search%' OR u.phone LIKE '%$search%'";
    $query_users .= " OR (u.user_type = 'hospital' AND h.hospital_name LIKE '%$search%')";
    $query_users .= " OR (u.user_type = 'parent' AND p.full_name LIKE '%$search%')";
    $query_users .= " OR (u.user_type = 'admin' AND a.full_name LIKE '%$search%'))";
}

if($filter_user_type != 'all') {
    $filter_user_type = mysqli_real_escape_string($connection, $filter_user_type);
    $query_users .= " AND u.user_type = '$filter_user_type'";
}

if($filter_status == 'active') {
    $query_users .= " AND u.is_active = 1";
} elseif($filter_status == 'inactive') {
    $query_users .= " AND u.is_active = 0";
}

// Count total records for pagination - FIXED QUERY
$count_query = "SELECT COUNT(*) as total FROM users u
                LEFT JOIN hospitals h ON u.user_id = h.user_id AND u.user_type = 'hospital'
                LEFT JOIN parents p ON u.user_id = p.user_id AND u.user_type = 'parent'
                LEFT JOIN admins a ON u.user_id = a.user_id AND u.user_type = 'admin'
                WHERE 1=1";

if(!empty($search)) {
    $count_query .= " AND (u.email LIKE '%$search%' OR u.phone LIKE '%$search%'";
    $count_query .= " OR (u.user_type = 'hospital' AND h.hospital_name LIKE '%$search%')";
    $count_query .= " OR (u.user_type = 'parent' AND p.full_name LIKE '%$search%')";
    $count_query .= " OR (u.user_type = 'admin' AND a.full_name LIKE '%$search%'))";
}

if($filter_user_type != 'all') {
    $count_query .= " AND u.user_type = '$filter_user_type'";
}

if($filter_status == 'active') {
    $count_query .= " AND u.is_active = 1";
} elseif($filter_status == 'inactive') {
    $count_query .= " AND u.is_active = 0";
}

$count_result = mysqli_query($connection, $count_query);

// FIX: Check if query was successful
if($count_result && mysqli_num_rows($count_result) > 0) {
    $total_users = mysqli_fetch_assoc($count_result)['total'];
} else {
    $total_users = 0;
}

$total_pages = ($limit > 0) ? ceil($total_users / $limit) : 1;

// Add pagination to main query
$query_users .= " ORDER BY u.created_at DESC LIMIT $offset, $limit";
$result_users = mysqli_query($connection, $query_users);

// Get statistics - FIXED QUERY
$stats_query = "SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN user_type = 'admin' THEN 1 ELSE 0 END) as total_admins,
    SUM(CASE WHEN user_type = 'parent' THEN 1 ELSE 0 END) as total_parents,
    SUM(CASE WHEN user_type = 'hospital' THEN 1 ELSE 0 END) as total_hospitals,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users,
    SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_users
    FROM users";
$stats_result = mysqli_query($connection, $stats_query);

// FIX: Check if stats query was successful
if($stats_result && mysqli_num_rows($stats_result) > 0) {
    $stats = mysqli_fetch_assoc($stats_result);
} else {
    // Default stats if query fails
    $stats = [
        'total_users' => 0,
        'total_admins' => 0,
        'total_parents' => 0,
        'total_hospitals' => 0,
        'active_users' => 0,
        'inactive_users' => 0
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin Panel</title>
    
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
            grid-template-columns: repeat(4, 1fr);
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
        
        /* ===== TABLE CARD ===== */
        .table-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
            animation: fadeIn 0.5s ease 0.2s both;
        }
        
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--gray-200);
        }
        
        .table-header h3 {
            font-size: 18px;
            font-weight: 700;
            color: var(--gray-700);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .table-header h3 i {
            color: var(--primary);
        }
        
        .table-header .badge {
            background: var(--primary-soft);
            color: var(--primary);
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid var(--gray-200);
        }
        
        /* ===== TABLE ===== */
        .table-responsive {
            overflow-x: auto;
            border-radius: 14px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1200px;
        }
        
        thead th {
            background: linear-gradient(135deg, #f0f9ff, #e6f3ff);
            padding: 16px 15px;
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--gray-700);
            border-bottom: 3px solid var(--gray-300);
            text-align: left;
        }
        
        tbody td {
            padding: 18px 15px;
            border-bottom: 1px solid var(--gray-200);
            color: var(--gray-600);
            font-size: 14px;
            vertical-align: middle;
        }
        
        tbody tr {
            transition: var(--transition);
        }
        
        tbody tr:hover {
            background: var(--gray-50);
        }
        
        /* ===== USER AVATAR / ICON ===== */
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 600;
            color: white;
        }
        
        .avatar-admin {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        }
        
        .avatar-parent {
            background: linear-gradient(135deg, #10b981, #059669);
        }
        
        .avatar-hospital {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        }
        
        .user-details {
            display: flex;
            flex-direction: column;
        }
        
        .user-name {
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 3px;
        }
        
        .user-email {
            font-size: 12px;
            color: var(--gray-500);
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .user-email i {
            color: var(--primary);
            font-size: 11px;
        }
        
        /* ===== TYPE BADGES ===== */
        .type-badge {
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid transparent;
        }
        
        .type-admin {
            background: #ede9fe;
            color: #6d28d9;
            border-color: #c4b5fd;
        }
        
        .type-parent {
            background: var(--success-light);
            color: #059669;
            border-color: #a7f3d0;
        }
        
        .type-hospital {
            background: var(--primary-soft);
            color: var(--primary-dark);
            border-color: var(--blue-soft);
        }
        
        /* ===== STATUS BADGES ===== */
        .status-badge {
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid transparent;
        }
        
        .status-active {
            background: var(--success-light);
            color: #059669;
            border-color: #a7f3d0;
        }
        
        .status-inactive {
            background: var(--danger-light);
            color: #dc2626;
            border-color: #fecaca;
        }
        
        /* ===== VERIFIED BADGE ===== */
        .verified-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 10px;
            border-radius: 30px;
            font-size: 11px;
            font-weight: 600;
            background: var(--success-light);
            color: #059669;
            border: 1px solid #a7f3d0;
        }
        
        .verified-badge i {
            font-size: 10px;
        }
        
        /* ===== ACTION BUTTONS ===== */
        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .action-btn {
            padding: 8px 14px;
            border: none;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            border: 1px solid transparent;
        }
        
        .action-btn.view {
            background: var(--primary-soft);
            color: var(--primary);
            border: 1px solid var(--gray-200);
        }
        
        .action-btn.view:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 12px rgba(37, 99, 235, 0.2);
        }
        
        .action-btn.edit {
            background: var(--warning-light);
            color: #d97706;
            border: 1px solid #fde68a;
        }
        
        .action-btn.edit:hover {
            background: #f59e0b;
            color: white;
            transform: translateY(-2px);
        }
        
        .action-btn.toggle {
            background: var(--gray-100);
            color: var(--gray-600);
            border: 1px solid var(--gray-200);
        }
        
        .action-btn.toggle.active:hover {
            background: #dc2626;
            color: white;
            transform: translateY(-2px);
        }
        
        .action-btn.toggle.inactive:hover {
            background: #10b981;
            color: white;
            transform: translateY(-2px);
        }
        
        .action-btn.delete {
            background: var(--danger-light);
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        
        .action-btn.delete:hover {
            background: #dc2626;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 12px rgba(220, 38, 38, 0.2);
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
            text-decoration: none;
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
            text-decoration: none;
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
        
        /* ===== EMPTY STATE ===== */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-state i {
            font-size: 60px;
            color: var(--gray-300);
            margin-bottom: 20px;
        }
        
        .empty-state h4 {
            font-size: 20px;
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: var(--gray-500);
        }
        
        /* ===== ANIMATIONS ===== */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
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
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filter-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 1200px) {
            .filter-grid {
                grid-template-columns: 1fr;
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
            
            .table-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .action-btn {
                width: 100%;
                justify-content: center;
            }
            
            .pagination {
                flex-wrap: wrap;
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
                    <i class="fas fa-users-cog"></i>
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
                <a href="users.php" class="nav-item active">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
                    <span class="nav-badge"><?php echo $stats['total_users']; ?></span>
                </a>
                <a href="reports.php" class="nav-item">
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
                        <i class="fas fa-users-cog"></i>
                        User Management
                    </h1>
                    <p><i class="fas fa-info-circle"></i> Manage all users, view details, and control account status</p>
                </div>
                
                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon-wrapper">
                            <div class="stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <span class="stat-trend">
                                <i class="fas fa-arrow-up trend-up"></i> Total
                            </span>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['total_users']; ?></h3>
                            <p>Total Users</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon-wrapper">
                            <div class="stat-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <span class="stat-trend">
                                <i class="fas fa-arrow-up trend-up"></i> <?php echo $stats['active_users']; ?>
                            </span>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['active_users']; ?></h3>
                            <p>Active Users</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon-wrapper">
                            <div class="stat-icon">
                                <i class="fas fa-user-tie"></i>
                            </div>
                            <span class="stat-trend">
                                <i class="fas fa-arrow-up trend-up"></i> <?php echo $stats['total_admins']; ?>
                            </span>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['total_admins']; ?></h3>
                            <p>Admins</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon-wrapper">
                            <div class="stat-icon">
                                <i class="fas fa-user-friends"></i>
                            </div>
                            <span class="stat-trend">
                                <i class="fas fa-arrow-up trend-up"></i> <?php echo $stats['total_parents']; ?>
                            </span>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['total_parents']; ?></h3>
                            <p>Parents</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon-wrapper">
                            <div class="stat-icon">
                                <i class="fas fa-hospital"></i>
                            </div>
                            <span class="stat-trend">
                                <i class="fas fa-arrow-up trend-up"></i> <?php echo $stats['total_hospitals']; ?>
                            </span>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['total_hospitals']; ?></h3>
                            <p>Hospitals</p>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon-wrapper">
                            <div class="stat-icon">
                                <i class="fas fa-ban"></i>
                            </div>
                            <span class="stat-trend">
                                <i class="fas fa-arrow-down trend-down"></i> <?php echo $stats['inactive_users']; ?>
                            </span>
                        </div>
                        <div class="stat-content">
                            <h3><?php echo $stats['inactive_users']; ?></h3>
                            <p>Inactive Users</p>
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
                                   placeholder="Search by name, email, phone..."
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div class="filter-group">
                            <i class="fas fa-user-tag"></i>
                            <select name="user_type" class="filter-select">
                                <option value="all" <?php echo ($filter_user_type == 'all') ? 'selected' : ''; ?>>All User Types</option>
                                <option value="admin" <?php echo ($filter_user_type == 'admin') ? 'selected' : ''; ?>>Admins</option>
                                <option value="parent" <?php echo ($filter_user_type == 'parent') ? 'selected' : ''; ?>>Parents</option>
                                <option value="hospital" <?php echo ($filter_user_type == 'hospital') ? 'selected' : ''; ?>>Hospitals</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <i class="fas fa-toggle-on"></i>
                            <select name="status" class="filter-select">
                                <option value="all" <?php echo ($filter_status == 'all') ? 'selected' : ''; ?>>All Status</option>
                                <option value="active" <?php echo ($filter_status == 'active') ? 'selected' : ''; ?>>Active Only</option>
                                <option value="inactive" <?php echo ($filter_status == 'inactive') ? 'selected' : ''; ?>>Inactive Only</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <i class="fas fa-calendar"></i>
                            <select name="date_range" class="filter-select">
                                <option value="all">All Time</option>
                                <option value="today">Today</option>
                                <option value="week">This Week</option>
                                <option value="month">This Month</option>
                                <option value="year">This Year</option>
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
                    </div>
                </form>
                
                <!-- Users Table -->
                <div class="table-card">
                    <div class="table-header">
                        <h3>
                            <i class="fas fa-list-ul"></i>
                            Users List
                        </h3>
                        <span class="badge">
                            <i class="fas fa-users me-1"></i> Total: <?php echo $total_users; ?>
                        </span>
                    </div>
                    
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Contact</th>
                                    <th>User Type</th>
                                    <th>Status</th>
                                    <th>Joined</th>
                                    <th>Last Login</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($result_users && mysqli_num_rows($result_users) > 0): ?>
                                    <?php while($user = mysqli_fetch_assoc($result_users)): ?>
                                    <tr>
                                        <td>
                                            <div class="user-info">
                                                <div class="user-avatar <?php 
                                                    echo $user['user_type'] == 'admin' ? 'avatar-admin' : 
                                                        ($user['user_type'] == 'parent' ? 'avatar-parent' : 'avatar-hospital'); 
                                                ?>">
                                                    <i class="fas fa-<?php 
                                                        echo $user['user_type'] == 'admin' ? 'user-tie' : 
                                                            ($user['user_type'] == 'parent' ? 'user-friends' : 'hospital'); 
                                                    ?>"></i>
                                                </div>
                                                <div class="user-details">
                                                    <span class="user-name">
                                                        <?php 
                                                        if($user['user_type'] == 'admin') echo htmlspecialchars($user['admin_name'] ?? 'Admin');
                                                        elseif($user['user_type'] == 'parent') echo htmlspecialchars($user['parent_name'] ?? 'Parent');
                                                        elseif($user['user_type'] == 'hospital') echo htmlspecialchars($user['hospital_name'] ?? 'Hospital');
                                                        ?>
                                                    </span>
                                                    <span class="user-email">
                                                        <i class="fas fa-envelope"></i>
                                                        <?php echo htmlspecialchars($user['email']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if($user['phone']): ?>
                                            <div><i class="fas fa-phone me-1" style="color: var(--primary);"></i> <?php echo htmlspecialchars($user['phone']); ?></div>
                                            <?php endif; ?>
                                            <?php if($user['contact_info']): ?>
                                            <div class="text-muted small mt-1">
                                                <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($user['contact_info']); ?>
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="type-badge type-<?php echo $user['user_type']; ?>">
                                                <i class="fas fa-<?php 
                                                    echo $user['user_type'] == 'admin' ? 'user-tie' : 
                                                        ($user['user_type'] == 'parent' ? 'user-friends' : 'hospital'); 
                                                ?>"></i>
                                                <?php echo ucfirst($user['user_type']); ?>
                                            </span>
                                            <?php if($user['user_type'] == 'hospital' && $user['hospital_verified']): ?>
                                            <div class="verified-badge mt-1">
                                                <i class="fas fa-check-circle"></i> Verified
                                            </div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                                <i class="fas fa-circle"></i>
                                                <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span><?php echo date('d M Y', strtotime($user['created_at'])); ?></span>
                                            <div class="text-muted small">
                                                <i class="fas fa-clock me-1"></i> <?php echo date('h:i A', strtotime($user['created_at'])); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if($user['last_login']): ?>
                                            <span><?php echo date('d M Y', strtotime($user['last_login'])); ?></span>
                                            <div class="text-muted small">
                                                <i class="fas fa-clock me-1"></i> <?php echo date('h:i A', strtotime($user['last_login'])); ?>
                                            </div>
                                            <?php else: ?>
                                            <span class="text-muted">Never</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="action-buttons">
                                                <button class="action-btn view" title="View Details" onclick="viewUser(<?php echo $user['user_id']; ?>)">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <button class="action-btn edit" title="Edit User" onclick="editUser(<?php echo $user['user_id']; ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="action-btn toggle <?php echo $user['is_active'] ? 'active' : 'inactive'; ?>" 
                                                        title="<?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>"
                                                        onclick="toggleStatus(<?php echo $user['user_id']; ?>, <?php echo $user['is_active'] ? 0 : 1; ?>)">
                                                    <i class="fas fa-power-off"></i>
                                                </button>
                                                <?php if($user['user_id'] != 1): // Don't allow deleting main admin ?>
                                                <button class="action-btn delete" title="Delete User" onclick="deleteUser(<?php echo $user['user_id']; ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="empty-state">
                                            <i class="fas fa-users-slash"></i>
                                            <h4>No Users Found</h4>
                                            <p>No users match your current filters.</p>
                                            <button class="btn btn-primary" onclick="resetFilters()" style="margin-top: 15px;">
                                                <i class="fas fa-redo"></i> Reset Filters
                                            </button>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if($page > 1): ?>
                        <a href="?<?php 
                            $params = $_GET;
                            $params['page'] = $page - 1;
                            echo http_build_query($params);
                        ?>" class="pagination-btn">
                            <i class="fas fa-chevron-left"></i> Prev
                        </a>
                        <?php endif; ?>
                        
                        <div class="page-numbers">
                            <?php
                            $start = max(1, $page - 2);
                            $end = min($total_pages, $start + 4);
                            
                            for($i = $start; $i <= $end; $i++):
                            ?>
                            <a href="?<?php 
                                $params = $_GET;
                                $params['page'] = $i;
                                echo http_build_query($params);
                            ?>" class="page-number <?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                            <?php endfor; ?>
                        </div>
                        
                        <?php if($page < $total_pages): ?>
                        <a href="?<?php 
                            $params = $_GET;
                            $params['page'] = $page + 1;
                            echo http_build_query($params);
                        ?>" class="pagination-btn">
                            Next <i class="fas fa-chevron-right"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Toggle Sidebar
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }
        
        // Reset Filters
        function resetFilters() {
            window.location.href = 'users.php';
        }
        
        // View User
        function viewUser(userId) {
            window.location.href = 'user_details.php?id=' + userId;
        }
        
        // Edit User
        function editUser(userId) {
            window.location.href = 'edit_user.php?id=' + userId;
        }
        
        // Toggle Status
        function toggleStatus(userId, newStatus) {
            let action = newStatus ? 'activate' : 'deactivate';
            if(confirm(`Are you sure you want to ${action} this user?`)) {
                window.location.href = `toggle_user_status.php?id=${userId}&status=${newStatus}`;
            }
        }
        
        // Delete User
        function deleteUser(userId) {
            if(confirm('⚠️ Are you sure you want to delete this user? This action cannot be undone.')) {
                window.location.href = 'delete_user.php?id=' + userId;
            }
        }
        
        // Auto-submit on filter change
        document.querySelectorAll('.filter-select').forEach(select => {
            select.addEventListener('change', function() {
                document.getElementById('filterForm').submit();
            });
        });
    </script>
</body>
</html>
<?php mysqli_close($connection); ?>