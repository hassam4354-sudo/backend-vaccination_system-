<?php
session_start();
if (!isset($_SESSION["logged_in"]) || $_SESSION["user_type"] != "admin") {
    header("location: ../login.php");
    exit();
}

include("../dbconnection.php");

// First, let's check what columns actually exist
$check_parents = "SHOW COLUMNS FROM parents";
$result_check = mysqli_query($connection, $check_parents);
$parent_columns = [];
while ($col = mysqli_fetch_assoc($result_check)) {
    $parent_columns[] = strtolower($col['Field']);
}

// Check appointment_requests for parent_phone
$check_appointments = "SHOW COLUMNS FROM appointment_requests";
$result_check2 = mysqli_query($connection, $check_appointments);
$appointment_columns = [];
while ($col = mysqli_fetch_assoc($result_check2)) {
    $appointment_columns[] = strtolower($col['Field']);
}

// Determine phone column - check various possibilities
$phone_column_select = "";
if (in_array('parent_phone', $appointment_columns)) {
    // If parent_phone exists in appointment_requests table
    $phone_column_select = "ar.parent_phone";
} elseif (in_array('contact_number', $parent_columns)) {
    $phone_column_select = "p.contact_number as parent_phone";
} elseif (in_array('phone', $parent_columns)) {
    $phone_column_select = "p.phone as parent_phone";
} elseif (in_array('mobile', $parent_columns)) {
    $phone_column_select = "p.mobile as parent_phone";
} elseif (in_array('contact_phone', $parent_columns)) {
    $phone_column_select = "p.contact_phone as parent_phone";
} elseif (in_array('phone_number', $parent_columns)) {
    $phone_column_select = "p.phone_number as parent_phone";
} else {
    // If no phone column found, use NULL
    $phone_column_select = "NULL as parent_phone";
}

// Main query to fetch appointment requests
$query = "SELECT ar.*, 
          c.full_name as child_name,
          c.date_of_birth,
          p.full_name as parent_name,
          $phone_column_select,
          h.hospital_name,
          h.city,
          v.vaccine_name
          FROM appointment_requests ar
          LEFT JOIN children c ON ar.child_id = c.child_id
          LEFT JOIN parents p ON c.parent_id = p.parent_id
          LEFT JOIN hospitals h ON ar.hospital_id = h.hospital_id
          LEFT JOIN vaccines v ON ar.vaccine_id = v.vaccine_id
          WHERE ar.request_status = 'pending'
          ORDER BY ar.preferred_date ASC";

$result_requests = mysqli_query($connection, $query);

if (!$result_requests) {
    // If query fails, try a simpler version
    $query = "SELECT ar.*, 
              c.full_name as child_name,
              c.date_of_birth,
              p.full_name as parent_name,
              h.hospital_name,
              h.city,
              v.vaccine_name
              FROM appointment_requests ar
              LEFT JOIN children c ON ar.child_id = c.child_id
              LEFT JOIN parents p ON c.parent_id = p.parent_id
              LEFT JOIN hospitals h ON ar.hospital_id = h.hospital_id
              LEFT JOIN vaccines v ON ar.vaccine_id = v.vaccine_id
              WHERE ar.request_status = 'pending'
              ORDER BY ar.preferred_date ASC";
    
    $result_requests = mysqli_query($connection, $query);
    
    if (!$result_requests) {
        die("Database query failed: " . mysqli_error($connection));
    }
}

// Store results in array for multiple use
$requests_data = [];
$total_requests = 0;
$child_count = 0;
$hospital_set = [];
$vaccine_set = [];
$child_ids = [];

while ($row = mysqli_fetch_assoc($result_requests)) {
    $requests_data[] = $row;
    $total_requests++;
    
    // Count unique children
    if (!in_array($row['child_id'], $child_ids)) {
        $child_ids[] = $row['child_id'];
        $child_count++;
    }
    
    // Collect unique hospitals
    if (!empty($row['hospital_name']) && !isset($hospital_set[$row['hospital_name']])) {
        $hospital_set[$row['hospital_name']] = true;
    }
    
    // Collect unique vaccines
    if (!empty($row['vaccine_name']) && !isset($vaccine_set[$row['vaccine_name']])) {
        $vaccine_set[$row['vaccine_name']] = true;
    }
}

// Reset pointer for display loop
mysqli_data_seek($result_requests, 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Requests - Admin Dashboard</title>
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    
    <!-- AOS Animation Library -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        :root {
            --primary: #4361ee;
            --primary-light: #4895ef;
            --primary-dark: #3a0ca3;
            --secondary: #7209b7;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #f8961e;
            --info: #3f37c9;
            --light: #f8f9fa;
            --dark: #121826;
            --gray: #6c757d;
            --light-gray: #e9ecef;
            --dark-gray: #343a40;
            --border-radius: 16px;
            --border-radius-sm: 10px;
            --box-shadow: 0 20px 60px rgba(0, 0, 0, 0.08);
            --box-shadow-lg: 0 30px 80px rgba(0, 0, 0, 0.12);
            --transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            --glass-bg: rgba(255, 255, 255, 0.85);
            --glass-border: rgba(255, 255, 255, 0.2);
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 10% 20%, rgba(255, 255, 255, 0.05) 0%, transparent 20%),
                radial-gradient(circle at 90% 80%, rgba(255, 255, 255, 0.05) 0%, transparent 20%);
            z-index: -1;
        }
        
        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 10px;
        }
        
        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(to bottom, var(--primary), var(--secondary));
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(to bottom, var(--primary-light), var(--primary-dark));
        }
        
        /* Admin Navbar */
        .admin-navbar {
            background: var(--glass-bg);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border-radius: var(--border-radius);
            padding: 20px 30px;
            margin-bottom: 30px;
            box-shadow: var(--box-shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid var(--glass-border);
            position: relative;
            overflow: hidden;
            animation: slideInDown 0.6s ease-out;
        }
        
        .admin-navbar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }
        
        .admin-navbar .logo {
            display: flex;
            align-items: center;
            gap: 15px;
            z-index: 2;
        }
        
        .admin-navbar .logo-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            box-shadow: 0 10px 20px rgba(67, 97, 238, 0.3);
            transition: var(--transition);
        }
        
        .admin-navbar .logo:hover .logo-icon {
            transform: rotate(15deg) scale(1.1);
            box-shadow: 0 15px 30px rgba(67, 97, 238, 0.4);
        }
        
        .admin-navbar .logo h2 {
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 28px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        
        .nav-links {
            display: flex;
            gap: 10px;
            z-index: 2;
        }
        
        .nav-links a {
            text-decoration: none;
            color: var(--dark);
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 15px;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 10px;
            position: relative;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.5);
            backdrop-filter: blur(10px);
        }
        
        .nav-links a::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.7s;
        }
        
        .nav-links a:hover::before {
            left: 100%;
        }
        
        .nav-links a:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(67, 97, 238, 0.3);
        }
        
        .nav-links a.active {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            box-shadow: 0 10px 20px rgba(67, 97, 238, 0.4);
        }
        
        .nav-links a.logout {
            background: linear-gradient(135deg, var(--danger), #d0006f);
            color: white;
        }
        
        .nav-links a.logout:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(247, 37, 133, 0.4);
        }
        
        /* Main Container */
        .container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        /* Dashboard Header */
        .dashboard-header {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            padding: 30px 40px;
            margin-bottom: 30px;
            box-shadow: var(--box-shadow);
            border: 1px solid var(--glass-border);
            position: relative;
            overflow: hidden;
            animation: fadeIn 0.7s ease-out;
        }
        
        .dashboard-header::after {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(67, 97, 238, 0.1) 0%, transparent 70%);
            z-index: 0;
        }
        
        .dashboard-header h1 {
            color: var(--dark);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 20px;
            font-size: 32px;
            position: relative;
            z-index: 1;
        }
        
        .dashboard-header h1 i {
            color: var(--primary);
            background: rgba(67, 97, 238, 0.1);
            padding: 18px;
            border-radius: 14px;
            font-size: 24px;
            transition: var(--transition);
        }
        
        .dashboard-header h1:hover i {
            transform: rotate(15deg) scale(1.1);
            background: rgba(67, 97, 238, 0.2);
        }
        
        .dashboard-header p {
            color: var(--gray);
            font-size: 16px;
            max-width: 600px;
            line-height: 1.6;
            position: relative;
            z-index: 1;
        }
        
        /* Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            border: 1px solid var(--glass-border);
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }
        
        .stat-card:nth-child(1)::before { background: linear-gradient(90deg, #4361ee, #4895ef); }
        .stat-card:nth-child(2)::before { background: linear-gradient(90deg, #f8961e, #f9c74f); }
        .stat-card:nth-child(3)::before { background: linear-gradient(90deg, #4cc9f0, #3f37c9); }
        .stat-card:nth-child(4)::before { background: linear-gradient(90deg, #7209b7, #b5179e); }
        
        .stat-card:hover {
            transform: translateY(-15px);
            box-shadow: var(--box-shadow-lg);
        }
        
        .stat-icon {
            width: 70px;
            height: 70px;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 25px;
            font-size: 28px;
            color: white;
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.15);
            transition: var(--transition);
        }
        
        .stat-card:hover .stat-icon {
            transform: scale(1.1) rotate(10deg);
        }
        
        .stat-card:nth-child(1) .stat-icon { 
            background: linear-gradient(135deg, #4361ee, #4895ef);
        }
        .stat-card:nth-child(2) .stat-icon { 
            background: linear-gradient(135deg, #f8961e, #f9c74f);
        }
        .stat-card:nth-child(3) .stat-icon { 
            background: linear-gradient(135deg, #4cc9f0, #3f37c9);
        }
        .stat-card:nth-child(4) .stat-icon { 
            background: linear-gradient(135deg, #7209b7, #b5179e);
        }
        
        .stat-card h3 {
            font-size: 42px;
            color: var(--dark);
            margin-bottom: 10px;
            font-weight: 800;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .stat-card p {
            color: var(--gray);
            font-size: 15px;
            font-weight: 500;
        }
        
        .stat-trend {
            position: absolute;
            top: 30px;
            right: 30px;
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 14px;
            font-weight: 600;
            color: #10b981;
        }
        
        .stat-trend.down {
            color: var(--danger);
        }
        
        /* Main Content Section */
        .content-section {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius);
            padding: 40px;
            box-shadow: var(--box-shadow);
            border: 1px solid var(--glass-border);
            margin-bottom: 40px;
            position: relative;
            overflow: hidden;
        }
        
        .content-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(67, 97, 238, 0.05) 0%, transparent 70%);
            z-index: 0;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 25px;
            border-bottom: 2px solid rgba(0, 0, 0, 0.05);
            position: relative;
            z-index: 1;
        }
        
        .section-header h3 {
            color: var(--dark);
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 15px;
            font-weight: 700;
        }
        
        .section-header h3 i {
            color: var(--primary);
            background: rgba(67, 97, 238, 0.1);
            padding: 14px;
            border-radius: 12px;
            font-size: 20px;
        }
        
        /* Search and Filters */
        .search-filter {
            display: flex;
            gap: 20px;
            margin-bottom: 30px;
            position: relative;
            z-index: 1;
        }
        
        .search-box {
            flex: 1;
            position: relative;
        }
        
        .search-box i {
            position: absolute;
            left: 20px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
            font-size: 18px;
            transition: var(--transition);
        }
        
        .search-box input {
            width: 100%;
            padding: 18px 25px 18px 55px;
            border: 2px solid rgba(67, 97, 238, 0.1);
            border-radius: 14px;
            font-size: 16px;
            transition: var(--transition);
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            font-weight: 500;
        }
        
        .search-box input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(67, 97, 238, 0.1);
            background: white;
        }
        
        .search-box input:focus + i {
            color: var(--primary);
            transform: translateY(-50%) scale(1.1);
        }
        
        .filter-select {
            padding: 18px 25px;
            border: 2px solid rgba(67, 97, 238, 0.1);
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            color: var(--dark);
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            transition: var(--transition);
            min-width: 200px;
        }
        
        .filter-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(67, 97, 238, 0.1);
        }
        
        .filter-select:hover {
            border-color: var(--primary);
        }
        
        /* Badge Filter */
        .badge-filters {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            flex-wrap: wrap;
            position: relative;
            z-index: 1;
        }
        
        .badge-filter {
            padding: 10px 20px;
            border-radius: 30px;
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary);
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: var(--transition);
            border: 2px solid transparent;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .badge-filter:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
        }
        
        .badge-filter.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
        }
        
        .badge-filter i {
            font-size: 12px;
        }
        
        /* Requests Table */
        .table-container {
            overflow-x: auto;
            border-radius: var(--border-radius-sm);
            border: 2px solid rgba(0, 0, 0, 0.05);
            background: white;
            position: relative;
            z-index: 1;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
        }
        
        .table-container::-webkit-scrollbar {
            height: 8px;
        }
        
        .table-container::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.05);
            border-radius: 10px;
        }
        
        .table-container::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 10px;
        }
        
        .requests-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1300px;
        }
        
        .requests-table thead {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .requests-table th {
            padding: 22px 20px;
            text-align: left;
            color: white;
            font-weight: 700;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            position: relative;
        }
        
        .requests-table th:not(:last-child)::after {
            content: '';
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 1px;
            height: 30px;
            background: rgba(255, 255, 255, 0.2);
        }
        
        .requests-table tbody tr {
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            transition: var(--transition);
            position: relative;
        }
        
        .requests-table tbody tr:hover {
            background: rgba(67, 97, 238, 0.03);
            transform: translateX(5px);
        }
        
        .requests-table tbody tr::after {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 4px;
            height: 100%;
            background: transparent;
            transition: var(--transition);
        }
        
        .requests-table tbody tr:hover::after {
            background: var(--primary);
        }
        
        .requests-table td {
            padding: 25px 20px;
            color: var(--dark);
            font-size: 15px;
            font-weight: 500;
        }
        
        /* Child Info Cell */
        .child-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .child-avatar {
            width: 55px;
            height: 55px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 22px;
            box-shadow: 0 10px 20px rgba(67, 97, 238, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .child-avatar::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.7s;
        }
        
        .child-info:hover .child-avatar::before {
            left: 100%;
        }
        
        .child-details h4 {
            color: var(--dark);
            margin-bottom: 8px;
            font-weight: 700;
            font-size: 16px;
        }
        
        .child-details p {
            color: var(--gray);
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        /* Parent Info */
        .parent-info {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .parent-info span {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            color: var(--dark-gray);
        }
        
        .parent-info i {
            color: var(--primary);
            width: 18px;
            font-size: 16px;
        }
        
        /* Vaccine Info */
        .vaccine-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .vaccine-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: linear-gradient(135deg, rgba(240, 68, 56, 0.1), rgba(240, 68, 56, 0.2));
            color: #f04438;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            box-shadow: 0 8px 16px rgba(240, 68, 56, 0.15);
        }
        
        /* Hospital Info */
        .hospital-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .hospital-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(16, 185, 129, 0.2));
            color: #10b981;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            box-shadow: 0 8px 16px rgba(16, 185, 129, 0.15);
        }
        
        /* Time Info */
        .time-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .time-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.1), rgba(139, 92, 246, 0.2));
            color: #8b5cf6;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            box-shadow: 0 8px 16px rgba(139, 92, 246, 0.15);
        }
        
        /* Status Badge */
        .status-badge {
            padding: 10px 20px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .status-pending {
            background: linear-gradient(135deg, var(--warning), #f9c74f);
            color: white;
            animation: pulse 2s infinite;
            position: relative;
            overflow: hidden;
        }
        
        .status-pending::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            animation: shimmer 3s infinite;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 12px;
        }
        
        .btn {
            padding: 14px 24px;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            min-width: 120px;
            position: relative;
            overflow: hidden;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
            transition: left 0.7s;
        }
        
        .btn:hover::before {
            left: 100%;
        }
        
        .btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.2);
        }
        
        .btn-approve {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            box-shadow: 0 10px 20px rgba(16, 185, 129, 0.3);
        }
        
        .btn-approve:hover {
            box-shadow: 0 15px 30px rgba(16, 185, 129, 0.4);
        }
        
        .btn-reject {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            box-shadow: 0 10px 20px rgba(239, 68, 68, 0.3);
        }
        
        .btn-reject:hover {
            box-shadow: 0 15px 30px rgba(239, 68, 68, 0.4);
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 40px;
            color: var(--gray);
            position: relative;
            z-index: 1;
        }
        
        .empty-state i {
            font-size: 80px;
            margin-bottom: 30px;
            color: var(--light-gray);
            opacity: 0.5;
            animation: float 6s ease-in-out infinite;
        }
        
        .empty-state h4 {
            font-size: 28px;
            margin-bottom: 15px;
            color: var(--dark);
            font-weight: 700;
        }
        
        .empty-state p {
            font-size: 16px;
            max-width: 500px;
            margin: 0 auto 30px;
            line-height: 1.6;
        }
        
        .empty-state .btn-refresh {
            padding: 15px 30px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .empty-state .btn-refresh:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(67, 97, 238, 0.3);
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
            margin-top: 40px;
            position: relative;
            z-index: 1;
        }
        
        .pagination-btn {
            padding: 14px 24px;
            background: white;
            border: 2px solid var(--light-gray);
            border-radius: 12px;
            cursor: pointer;
            font-weight: 700;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 15px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .pagination-btn:hover:not(:disabled) {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(67, 97, 238, 0.3);
        }
        
        .pagination-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .page-numbers {
            display: flex;
            gap: 10px;
        }
        
        .page-number {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 700;
            transition: var(--transition);
            background: white;
            border: 2px solid var(--light-gray);
            font-size: 16px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .page-number:hover:not(.active) {
            background: rgba(67, 97, 238, 0.1);
            border-color: var(--primary);
            transform: translateY(-3px);
        }
        
        .page-number.active {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            border-color: var(--primary);
            box-shadow: 0 10px 20px rgba(67, 97, 238, 0.3);
            transform: scale(1.1);
        }
        
        /* Footer */
        .footer {
            text-align: center;
            padding: 25px;
            color: white;
            font-size: 14px;
            opacity: 0.8;
            margin-top: 20px;
        }
        
        .footer a {
            color: white;
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
        }
        
        .footer a:hover {
            color: var(--primary-light);
            text-decoration: underline;
        }
        
        /* Animations */
        @keyframes slideInDown {
            from { 
                opacity: 0; 
                transform: translateY(-50px); 
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
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(248, 150, 30, 0.4); }
            70% { box-shadow: 0 0 0 15px rgba(248, 150, 30, 0); }
            100% { box-shadow: 0 0 0 0 rgba(248, 150, 30, 0); }
        }
        
        @keyframes shimmer {
            0% { transform: translateX(-100%) rotate(45deg); }
            100% { transform: translateX(100%) rotate(45deg); }
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
        
        @keyframes ripple {
            0% { transform: scale(0); opacity: 1; }
            100% { transform: scale(4); opacity: 0; }
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .admin-navbar {
                flex-direction: column;
                gap: 20px;
            }
            
            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .dashboard-header {
                padding: 25px 30px;
            }
        }
        
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            
            .container {
                padding: 0 10px;
            }
            
            .dashboard-header, .content-section {
                padding: 25px 20px;
            }
            
            .search-filter {
                flex-direction: column;
            }
            
            .filter-select {
                min-width: auto;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 10px;
            }
            
            .btn {
                min-width: auto;
                width: 100%;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
            }
        }
        
        /* Loading Animation */
        .loading {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(10px);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            gap: 20px;
        }
        
        .loading.active {
            display: flex;
        }
        
        .spinner {
            width: 60px;
            height: 60px;
            border: 5px solid rgba(255, 255, 255, 0.1);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .loading-text {
            color: white;
            font-size: 18px;
            font-weight: 600;
        }
        
        /* Toast Notifications */
        .toast-container {
            position: fixed;
            top: 30px;
            right: 30px;
            z-index: 9999;
            display: flex;
            flex-direction: column;
            gap: 15px;
            max-width: 400px;
        }
        
        .toast {
            background: white;
            padding: 20px;
            border-radius: var(--border-radius-sm);
            box-shadow: var(--box-shadow);
            display: flex;
            align-items: center;
            gap: 15px;
            transform: translateX(150%);
            transition: transform 0.5s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        
        .toast.show {
            transform: translateX(0);
        }
        
        .toast i {
            font-size: 24px;
        }
        
        .toast-success i { color: #10b981; }
        .toast-error i { color: #ef4444; }
        .toast-warning i { color: #f8961e; }
        
        .toast-content {
            flex: 1;
        }
        
        .toast-content h4 {
            margin: 0 0 5px 0;
            color: var(--dark);
        }
        
        .toast-content p {
            margin: 0;
            color: var(--gray);
            font-size: 14px;
        }
        
        .toast-close {
            background: none;
            border: none;
            color: var(--gray);
            cursor: pointer;
            font-size: 18px;
            transition: var(--transition);
        }
        
        .toast-close:hover {
            color: var(--danger);
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading" id="loading">
        <div class="spinner"></div>
        <div class="loading-text">Processing Request...</div>
    </div>
    
    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>
    
    <!-- Admin Navbar -->
    <nav class="admin-navbar animate__animated animate__fadeInDown">
        <div class="logo">
            <div class="logo-icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h2>Vaccine<span style="color:#4361ee">Admin</span> Pro</h2>
        </div>
        <div class="nav-links">
            <a href="dashboard.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="appointment_requests.php" class="active">
                <i class="fas fa-calendar-check"></i> Requests
                <?php if ($total_requests > 0): ?>
                <span style="background: var(--danger); color: white; padding: 2px 8px; border-radius: 10px; font-size: 12px;"><?php echo $total_requests; ?></span>
                <?php endif; ?>
            </a>
            <a href="manage_hospitals.php">
                <i class="fas fa-hospital"></i> Hospitals
            </a>
            <a href="manage_vaccines.php">
                <i class="fas fa-syringe"></i> Vaccines
            </a>
            <a href="../logout.php" class="logout">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </nav>

    <div class="container">
        <!-- Dashboard Header -->
        <div class="dashboard-header" data-aos="fade-up">
            <h1>
                <i class="fas fa-calendar-alt"></i>
                Pending Appointment Requests
            </h1>
            <p>Review and manage vaccination appointment requests from parents. You have <?php echo $total_requests; ?> pending request<?php echo $total_requests != 1 ? 's' : ''; ?> to process.</p>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-container">
            <div class="stat-card" data-aos="fade-up" data-aos-delay="100">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <h3><?php echo $total_requests; ?></h3>
                <p>Pending Requests</p>
                <div class="stat-trend">
                    <i class="fas fa-arrow-up"></i>
                    <span>12%</span>
                </div>
            </div>
            
            <div class="stat-card" data-aos="fade-up" data-aos-delay="200">
                <div class="stat-icon">
                    <i class="fas fa-baby"></i>
                </div>
                <h3 id="totalChildren"><?php echo $child_count; ?></h3>
                <p>Children Waiting</p>
                <div class="stat-trend">
                    <i class="fas fa-arrow-up"></i>
                    <span>8%</span>
                </div>
            </div>
            
            <div class="stat-card" data-aos="fade-up" data-aos-delay="300">
                <div class="stat-icon">
                    <i class="fas fa-hospital-user"></i>
                </div>
                <h3 id="uniqueHospitals"><?php echo count($hospital_set); ?></h3>
                <p>Hospitals Involved</p>
                <div class="stat-trend">
                    <i class="fas fa-arrow-up"></i>
                    <span>5%</span>
                </div>
            </div>
            
            <div class="stat-card" data-aos="fade-up" data-aos-delay="400">
                <div class="stat-icon">
                    <i class="fas fa-syringe"></i>
                </div>
                <h3 id="uniqueVaccines"><?php echo count($vaccine_set); ?></h3>
                <p>Vaccine Types</p>
                <div class="stat-trend">
                    <i class="fas fa-arrow-up"></i>
                    <span>15%</span>
                </div>
            </div>
        </div>
        
        <!-- Main Content Section -->
        <div class="content-section" data-aos="fade-up" data-aos-delay="500">
            <div class="section-header">
                <h3>
                    <i class="fas fa-list-ul"></i>
                    Request Details
                </h3>
                
                <div class="search-filter">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" 
                               id="searchInput" 
                               placeholder="Search by child, parent, vaccine, hospital..." 
                               onkeyup="searchRequests()">
                    </div>
                    
                    <select class="filter-select" id="hospitalFilter" onchange="filterByHospital()">
                        <option value="all">All Hospitals</option>
                        <?php
                        // Get unique hospitals for filter
                        foreach(array_keys($hospital_set) as $hospital) {
                            if (!empty($hospital)) {
                                echo "<option value='$hospital'>$hospital</option>";
                            }
                        }
                        ?>
                    </select>
                    
                    <select class="filter-select" id="vaccineFilter" onchange="filterByVaccine()">
                        <option value="all">All Vaccines</option>
                        <?php
                        // Get unique vaccines for filter
                        foreach(array_keys($vaccine_set) as $vaccine) {
                            if (!empty($vaccine)) {
                                echo "<option value='$vaccine'>$vaccine</option>";
                            }
                        }
                        ?>
                    </select>
                </div>
            </div>
            
            <!-- Badge Filters -->
            <div class="badge-filters">
                <div class="badge-filter active" onclick="filterAll()">
                    <i class="fas fa-list"></i> All (<?php echo $total_requests; ?>)
                </div>
                <div class="badge-filter" onclick="filterUrgent()">
                    <i class="fas fa-exclamation-circle"></i> Urgent
                </div>
                <div class="badge-filter" onclick="filterToday()">
                    <i class="fas fa-calendar-day"></i> Today
                </div>
                <div class="badge-filter" onclick="filterThisWeek()">
                    <i class="fas fa-calendar-week"></i> This Week
                </div>
                <div class="badge-filter" onclick="filterWithNotes()">
                    <i class="fas fa-sticky-note"></i> With Notes
                </div>
            </div>
            
            <?php if ($total_requests > 0): ?>
            <div class="table-container">
                <table class="requests-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Child Details</th>
                            <th>Parent Info</th>
                            <th>Vaccine</th>
                            <th>Dose</th>
                            <th>Hospital</th>
                            <th>Appointment Time</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="requestsTableBody">
                        <?php 
                        $counter = 0;
                        while ($row = mysqli_fetch_assoc($result_requests)): 
                            // Calculate child's age
                            $age_days = 0;
                            if (!empty($row['date_of_birth'])) {
                                $age_days = floor((time() - strtotime($row['date_of_birth'])) / (60 * 60 * 24));
                            }
                            $age_months = floor($age_days / 30);
                            $counter++;
                            
                            // Check if appointment is urgent (within 2 days)
                            $isUrgent = false;
                            if (!empty($row['preferred_date'])) {
                                $appointmentDate = strtotime($row['preferred_date']);
                                $daysUntil = floor(($appointmentDate - time()) / (60 * 60 * 24));
                                $isUrgent = ($daysUntil <= 2 && $daysUntil >= 0);
                            }
                            
                            // Check if today
                            $isToday = false;
                            if (!empty($row['preferred_date'])) {
                                $isToday = date('Y-m-d', strtotime($row['preferred_date'])) == date('Y-m-d');
                            }
                            
                            $rowClass = $isUrgent ? 'urgent-row' : ($isToday ? 'today-row' : '');
                        ?>
                        <tr class="animate__animated <?php echo $rowClass; ?>" 
                            data-urgent="<?php echo $isUrgent ? 'true' : 'false'; ?>"
                            data-today="<?php echo $isToday ? 'true' : 'false'; ?>"
                            data-has-notes="<?php echo !empty($row['parent_notes']) ? 'true' : 'false'; ?>"
                            data-hospital="<?php echo htmlspecialchars($row['hospital_name'] ?? ''); ?>"
                            data-vaccine="<?php echo htmlspecialchars($row['vaccine_name'] ?? ''); ?>"
                            data-row-id="<?php echo $counter; ?>">
                            <td>
                                <div class="request-id">
                                    <strong style="color:#4361ee">#<?php echo $row['request_id']; ?></strong>
                                    <?php if ($isUrgent): ?>
                                    <div style="margin-top:5px; font-size:11px; color:#ef4444; font-weight:bold;">
                                        <i class="fas fa-exclamation-circle"></i> URGENT
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            
                            <td>
                                <div class="child-info">
                                    <div class="child-avatar">
                                        <i class="fas fa-baby"></i>
                                    </div>
                                    <div class="child-details">
                                        <h4><?php echo htmlspecialchars($row['child_name'] ?? 'N/A'); ?></h4>
                                        <p>
                                            <i class="fas fa-birthday-cake"></i>
                                            <?php echo $age_months; ?> months old
                                        </p>
                                    </div>
                                </div>
                            </td>
                            
                            <td>
                                <div class="parent-info">
                                    <span>
                                        <i class="fas fa-user"></i>
                                        <?php echo htmlspecialchars($row['parent_name'] ?? 'N/A'); ?>
                                    </span>
                                    <?php if (isset($row['parent_phone']) && $row['parent_phone'] !== null): ?>
                                    <span>
                                        <i class="fas fa-phone"></i>
                                        <?php echo htmlspecialchars($row['parent_phone']); ?>
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            
                            <td>
                                <div class="vaccine-info">
                                    <div class="vaccine-icon">
                                        <i class="fas fa-syringe"></i>
                                    </div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($row['vaccine_name'] ?? 'N/A'); ?></strong>
                                    </div>
                                </div>
                            </td>
                            
                            <td>
                                <div style="text-align:center">
                                    <span style="
                                        display:inline-block;
                                        width:45px;
                                        height:45px;
                                        line-height:45px;
                                        background:linear-gradient(135deg, #4361ee, #4895ef);
                                        border-radius:50%;
                                        font-weight:bold;
                                        color:white;
                                        font-size:18px;
                                        box-shadow:0 8px 16px rgba(67, 97, 238, 0.3);
                                    ">
                                        <?php echo $row['dose_number'] ?? 'N/A'; ?>
                                    </span>
                                    <div style="font-size:12px; color:#6c757d; margin-top:8px; font-weight:600">DOSE</div>
                                </div>
                            </td>
                            
                            <td>
                                <div class="hospital-info">
                                    <div class="hospital-icon">
                                        <i class="fas fa-hospital"></i>
                                    </div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($row['hospital_name'] ?? 'N/A'); ?></strong>
                                        <p style="font-size:13px; color:#6c757d; margin-top:5px">
                                            <i class="fas fa-map-marker-alt"></i>
                                            <?php echo htmlspecialchars($row['city'] ?? 'N/A'); ?>
                                        </p>
                                    </div>
                                </div>
                            </td>
                            
                            <td>
                                <div class="time-info">
                                    <div class="time-icon">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div>
                                        <strong>
                                            <?php 
                                            if (!empty($row['preferred_date'])) {
                                                echo date('d M Y', strtotime($row['preferred_date']));
                                            } else {
                                                echo 'N/A';
                                            }
                                            ?>
                                        </strong>
                                        <p style="font-size:13px; color:#6c757d; margin-top:5px">
                                            <i class="fas fa-clock"></i>
                                            <?php 
                                            if (!empty($row['preferred_time'])) {
                                                echo date('h:i A', strtotime($row['preferred_time']));
                                            } else {
                                                echo 'N/A';
                                            }
                                            ?>
                                        </p>
                                    </div>
                                </div>
                            </td>
                            
                            <td>
                                <span class="status-badge status-pending">
                                    <i class="fas fa-clock"></i> Pending
                                </span>
                                <?php if (!empty($row['parent_notes'])): ?>
                                <div style="margin-top:10px">
                                    <small style="color:#6c757d; cursor:pointer" 
                                           onclick="showNotes('<?php echo htmlspecialchars(addslashes($row['parent_notes'])); ?>')">
                                        <i class="fas fa-sticky-note"></i> View Notes
                                    </small>
                                </div>
                                <?php endif; ?>
                            </td>
                            
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-approve" 
                                            onclick="approveRequest(<?php echo $row['request_id']; ?>, this)">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                    <button class="btn btn-reject" 
                                            onclick="rejectRequest(<?php echo $row['request_id']; ?>, this)">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <div class="pagination">
                <button class="pagination-btn" id="prevPage" onclick="changePage(-1)">
                    <i class="fas fa-chevron-left"></i> Previous
                </button>
                <div class="page-numbers" id="pageNumbers">
                    <!-- Pages will be generated by JavaScript -->
                </div>
                <button class="pagination-btn" id="nextPage" onclick="changePage(1)">
                    Next <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            
            <?php else: ?>
            <div class="empty-state animate__animated animate__fadeIn">
                <i class="far fa-calendar-check"></i>
                <h4>No Pending Requests</h4>
                <p>All appointment requests have been processed. Great job!</p>
                <button class="btn-refresh" onclick="location.reload()">
                    <i class="fas fa-sync-alt"></i> Refresh
                </button>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> VaccineAdmin Pro. All rights reserved. | 
                <a href="#">Privacy Policy</a> | 
                <a href="#">Terms of Service</a>
            </p>
            <p style="margin-top:10px; font-size:12px;">
                <i class="fas fa-heart" style="color:#f72585;"></i> 
                Made with care for child healthcare
            </p>
        </div>
    </div>
    
    <!-- AOS Library -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    
    <script>
        // Initialize AOS animations
        AOS.init({
            duration: 800,
            once: true,
            offset: 100
        });
        
        // Pagination variables
        let currentPage = 1;
        const rowsPerPage = 10;
        let filteredRows = [];
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            initializeTable();
            initializeFilters();
            addRowAnimations();
            updateStats();
            
            // Show welcome toast
            setTimeout(() => {
                showToast('Welcome!', 'You have <?php echo $total_requests; ?> pending requests to review.', 'success');
            }, 1000);
        });
        
        // Initialize table and pagination
        function initializeTable() {
            const rows = document.querySelectorAll('#requestsTableBody tr');
            filteredRows = Array.from(rows);
            
            // Add data attributes for filtering
            rows.forEach((row, index) => {
                row.setAttribute('data-index', index);
            });
            
            updatePagination();
        }
        
        // Update pagination
        function updatePagination() {
            const totalPages = Math.ceil(filteredRows.length / rowsPerPage);
            const pageNumbers = document.getElementById('pageNumbers');
            
            // Clear existing page numbers
            pageNumbers.innerHTML = '';
            
            // Generate page numbers
            for (let i = 1; i <= totalPages; i++) {
                const pageNumber = document.createElement('span');
                pageNumber.className = `page-number ${i === currentPage ? 'active' : ''}`;
                pageNumber.textContent = i;
                pageNumber.onclick = () => goToPage(i);
                pageNumbers.appendChild(pageNumber);
            }
            
            // Update button states
            document.getElementById('prevPage').disabled = currentPage === 1;
            document.getElementById('nextPage').disabled = currentPage === totalPages;
            
            // Show current page rows
            showCurrentPage();
        }
        
        // Show current page rows
        function showCurrentPage() {
            const start = (currentPage - 1) * rowsPerPage;
            const end = start + rowsPerPage;
            
            // Hide all rows first
            document.querySelectorAll('#requestsTableBody tr').forEach(row => {
                row.style.display = 'none';
            });
            
            // Show only rows for current page
            filteredRows.slice(start, end).forEach(row => {
                row.style.display = '';
            });
        }
        
        // Change page
        function changePage(direction) {
            const totalPages = Math.ceil(filteredRows.length / rowsPerPage);
            const newPage = currentPage + direction;
            
            if (newPage >= 1 && newPage <= totalPages) {
                currentPage = newPage;
                updatePagination();
                addRowAnimations();
            }
        }
        
        // Go to specific page
        function goToPage(page) {
            currentPage = page;
            updatePagination();
            addRowAnimations();
        }
        
        // Search functionality
        function searchRequests() {
            const input = document.getElementById('searchInput').value.toLowerCase();
            const rows = document.querySelectorAll('#requestsTableBody tr');
            
            filteredRows = Array.from(rows).filter(row => {
                const text = row.textContent.toLowerCase();
                return text.includes(input);
            });
            
            currentPage = 1;
            updatePagination();
        }
        
        // Filter by hospital
        function filterByHospital() {
            const select = document.getElementById('hospitalFilter');
            const hospital = select.value.toLowerCase();
            
            if (hospital === 'all') {
                filteredRows = Array.from(document.querySelectorAll('#requestsTableBody tr'));
            } else {
                filteredRows = Array.from(document.querySelectorAll('#requestsTableBody tr')).filter(row => {
                    const hospitalName = row.getAttribute('data-hospital')?.toLowerCase() || '';
                    return hospitalName.includes(hospital);
                });
            }
            
            currentPage = 1;
            updatePagination();
            updateBadgeFilters('hospital');
        }
        
        // Filter by vaccine
        function filterByVaccine() {
            const select = document.getElementById('vaccineFilter');
            const vaccine = select.value.toLowerCase();
            
            if (vaccine === 'all') {
                filteredRows = Array.from(document.querySelectorAll('#requestsTableBody tr'));
            } else {
                filteredRows = Array.from(document.querySelectorAll('#requestsTableBody tr')).filter(row => {
                    const vaccineName = row.getAttribute('data-vaccine')?.toLowerCase() || '';
                    return vaccineName.includes(vaccine);
                });
            }
            
            currentPage = 1;
            updatePagination();
            updateBadgeFilters('vaccine');
        }
        
        // Badge filter functions
        function filterAll() {
            filteredRows = Array.from(document.querySelectorAll('#requestsTableBody tr'));
            currentPage = 1;
            updatePagination();
            updateBadgeFilters('all');
        }
        
        function filterUrgent() {
            filteredRows = Array.from(document.querySelectorAll('#requestsTableBody tr')).filter(row => {
                return row.getAttribute('data-urgent') === 'true';
            });
            currentPage = 1;
            updatePagination();
            updateBadgeFilters('urgent');
        }
        
        function filterToday() {
            filteredRows = Array.from(document.querySelectorAll('#requestsTableBody tr')).filter(row => {
                return row.getAttribute('data-today') === 'true';
            });
            currentPage = 1;
            updatePagination();
            updateBadgeFilters('today');
        }
        
        function filterThisWeek() {
            const today = new Date();
            const weekEnd = new Date(today);
            weekEnd.setDate(today.getDate() + 7);
            
            filteredRows = Array.from(document.querySelectorAll('#requestsTableBody tr')).filter(row => {
                const dateStr = row.querySelector('.time-info strong')?.textContent;
                if (dateStr && dateStr !== 'N/A') {
                    const rowDate = new Date(dateStr);
                    return rowDate >= today && rowDate <= weekEnd;
                }
                return false;
            });
            
            currentPage = 1;
            updatePagination();
            updateBadgeFilters('week');
        }
        
        function filterWithNotes() {
            filteredRows = Array.from(document.querySelectorAll('#requestsTableBody tr')).filter(row => {
                return row.getAttribute('data-has-notes') === 'true';
            });
            currentPage = 1;
            updatePagination();
            updateBadgeFilters('notes');
        }
        
        // Update badge filters active state
        function updateBadgeFilters(activeFilter) {
            document.querySelectorAll('.badge-filter').forEach(badge => {
                badge.classList.remove('active');
            });
            
            const badges = {
                'all': document.querySelector('.badge-filter:nth-child(1)'),
                'urgent': document.querySelector('.badge-filter:nth-child(2)'),
                'today': document.querySelector('.badge-filter:nth-child(3)'),
                'week': document.querySelector('.badge-filter:nth-child(4)'),
                'notes': document.querySelector('.badge-filter:nth-child(5)'),
                'hospital': null,
                'vaccine': null
            };
            
            if (badges[activeFilter]) {
                badges[activeFilter].classList.add('active');
            }
        }
        
        // Initialize filters
        function initializeFilters() {
            // Clear search on ESC
            document.getElementById('searchInput').addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    e.target.value = '';
                    searchRequests();
                }
            });
            
            // Reset filters button
            const resetBtn = document.createElement('button');
            resetBtn.innerHTML = '<i class="fas fa-redo"></i> Reset';
            resetBtn.style.cssText = `
                padding: 10px 20px;
                background: var(--gray);
                color: white;
                border: none;
                border-radius: 8px;
                cursor: pointer;
                font-weight: 600;
                transition: var(--transition);
                margin-left: 10px;
            `;
            resetBtn.onclick = () => {
                document.getElementById('searchInput').value = '';
                document.getElementById('hospitalFilter').value = 'all';
                document.getElementById('vaccineFilter').value = 'all';
                filterAll();
            };
            document.querySelector('.search-filter').appendChild(resetBtn);
        }
        
        // Add row animations
        function addRowAnimations() {
            const rows = document.querySelectorAll('#requestsTableBody tr');
            rows.forEach((row, index) => {
                if (row.style.display !== 'none') {
                    row.style.animation = `fadeInUp 0.6s ease-out ${index * 0.05}s both`;
                    row.classList.add('animate__fadeInUp');
                }
            });
        }
        
        // Update stats with animations
        function updateStats() {
            const stats = [
                { id: 'totalChildren', value: <?php echo $child_count; ?> },
                { id: 'uniqueHospitals', value: <?php echo count($hospital_set); ?> },
                { id: 'uniqueVaccines', value: <?php echo count($vaccine_set); ?> }
            ];
            
            stats.forEach(stat => {
                const element = document.getElementById(stat.id);
                if (element) {
                    animateCounter(element, stat.value);
                }
            });
        }
        
        // Animate counter
        function animateCounter(element, target) {
            let current = 0;
            const increment = target / 50;
            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                element.textContent = Math.floor(current);
            }, 30);
        }
        
        // Show notes modal
        function showNotes(notes) {
            const modal = document.createElement('div');
            modal.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.8);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 10000;
                backdrop-filter: blur(10px);
            `;
            
            modal.innerHTML = `
                <div style="
                    background: white;
                    padding: 30px;
                    border-radius: var(--border-radius);
                    max-width: 500px;
                    width: 90%;
                    position: relative;
                    box-shadow: var(--box-shadow-lg);
                ">
                    <button onclick="this.parentElement.parentElement.remove()" 
                            style="
                                position: absolute;
                                top: 15px;
                                right: 15px;
                                background: none;
                                border: none;
                                font-size: 24px;
                                color: var(--gray);
                                cursor: pointer;
                            ">
                        &times;
                    </button>
                    <h3 style="margin-bottom: 20px; color: var(--primary);">
                        <i class="fas fa-sticky-note"></i> Parent Notes
                    </h3>
                    <div style="
                        background: var(--light-gray);
                        padding: 20px;
                        border-radius: var(--border-radius-sm);
                        max-height: 300px;
                        overflow-y: auto;
                        line-height: 1.6;
                    ">
                        ${notes}
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
        }
        
        // Show loading overlay
        function showLoading() {
            document.getElementById('loading').classList.add('active');
        }
        
        // Hide loading overlay
        function hideLoading() {
            document.getElementById('loading').classList.remove('active');
        }
        
        // Show toast notification
        function showToast(title, message, type = 'info') {
            const toastContainer = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                <div class="toast-content">
                    <h4>${title}</h4>
                    <p>${message}</p>
                </div>
                <button class="toast-close" onclick="this.parentElement.remove()">
                    &times;
                </button>
            `;
            
            toastContainer.appendChild(toast);
            
            // Show with animation
            setTimeout(() => {
                toast.classList.add('show');
            }, 10);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.classList.remove('show');
                    setTimeout(() => {
                        if (toast.parentElement) {
                            toast.remove();
                        }
                    }, 500);
                }
            }, 5000);
        }
        
        // Approve request
        function approveRequest(requestId, button) {
            if (!confirm('Are you sure you want to approve this appointment request?')) {
                return;
            }
            
            showLoading();
            
            // Disable buttons
            const buttons = button.parentElement.querySelectorAll('button');
            buttons.forEach(btn => btn.disabled = true);
            
            // Simulate API call (replace with actual fetch)
            setTimeout(() => {
                hideLoading();
                showToast('Request Approved', `Appointment #${requestId} has been approved successfully.`, 'success');
                
                // Remove row with animation
                const row = button.closest('tr');
                row.style.animation = 'fadeOut 0.5s ease-out forwards';
                setTimeout(() => {
                    row.remove();
                    updateStatsAfterAction();
                }, 500);
            }, 1500);
        }
        
        // Reject request
        function rejectRequest(requestId, button) {
            if (!confirm('Are you sure you want to reject this appointment request?')) {
                return;
            }
            
            showLoading();
            
            // Disable buttons
            const buttons = button.parentElement.querySelectorAll('button');
            buttons.forEach(btn => btn.disabled = true);
            
            // Simulate API call (replace with actual fetch)
            setTimeout(() => {
                hideLoading();
                showToast('Request Rejected', `Appointment #${requestId} has been rejected.`, 'error');
                
                // Remove row with animation
                const row = button.closest('tr');
                row.style.animation = 'fadeOut 0.5s ease-out forwards';
                setTimeout(() => {
                    row.remove();
                    updateStatsAfterAction();
                }, 500);
            }, 1500);
        }
        
        // Update stats after action
        function updateStatsAfterAction() {
            const remainingRows = document.querySelectorAll('#requestsTableBody tr').length;
            const statsCard = document.querySelector('.stat-card:nth-child(1) h3');
            if (statsCard) {
                animateCounter(statsCard, remainingRows);
            }
            
            // Update badge count
            const requestBadge = document.querySelector('.nav-links a.active span');
            if (requestBadge) {
                requestBadge.textContent = remainingRows;
                if (remainingRows === 0) {
                    requestBadge.remove();
                }
            }
        }
        
        // Add fadeOut animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeOut {
                from { opacity: 1; transform: translateX(0); }
                to { opacity: 0; transform: translateX(-100px); }
            }
            
            .urgent-row {
                background: linear-gradient(90deg, rgba(255, 243, 235, 0.5), rgba(255, 243, 235, 0.2));
            }
            
            .today-row {
                background: linear-gradient(90deg, rgba(235, 251, 238, 0.5), rgba(235, 251, 238, 0.2));
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
<?php 
// Close database connections
if (isset($result_requests) && $result_requests) {
    mysqli_free_result($result_requests);
}
if (isset($result_check) && $result_check) {
    mysqli_free_result($result_check);
}
if (isset($result_check2) && $result_check2) {
    mysqli_free_result($result_check2);
}
mysqli_close($connection); 
?>