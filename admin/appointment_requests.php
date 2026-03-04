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
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', Arial, sans-serif;
            background: #f0f4ff;
            color: #1a1a2e;
            min-height: 100vh;
        }

        /* ── NAVBAR ── */
        .admin-navbar {
            background: #ffffff;
            border-bottom: 2px solid #e8eeff;
            padding: 0 35px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 68px;
            box-shadow: 0 2px 16px rgba(59,130,246,0.08);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .admin-navbar .logo { display: flex; align-items: center; gap: 10px; }
        .admin-navbar .logo-icon {
            width: 40px; height: 40px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px;
        }
        .admin-navbar .logo h2 {
            font-size: 20px; font-weight: 700; color: #1d4ed8; letter-spacing: -0.3px;
        }
        .nav-links { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
        .nav-links a {
            color: #4b6cb7; text-decoration: none;
            padding: 8px 14px; border-radius: 8px;
            font-size: 13.5px; font-weight: 500; transition: all 0.2s;
            display: flex; align-items: center; gap: 6px;
        }
        .nav-links a:hover { background: #eff6ff; color: #1d4ed8; }
        .nav-links a.active {
            background: #dbeafe; color: #1d4ed8; font-weight: 600;
        }
        .nav-links a.logout { background: #fee2e2; color: #dc2626; }
        .nav-links a.logout:hover { background: #fecaca; }
        .nav-badge {
            background: #ef4444; color: white;
            padding: 2px 7px; border-radius: 10px; font-size: 11px; font-weight: 700;
        }

        /* ── LAYOUT ── */
        .container { max-width: 1400px; margin: 32px auto; padding: 0 24px; }

        /* ── PAGE HEADER ── */
        .dashboard-header {
            background: linear-gradient(135deg, #1d4ed8 0%, #3b82f6 60%, #60a5fa 100%);
            border-radius: 18px;
            padding: 32px 36px;
            margin-bottom: 28px;
            color: white;
            box-shadow: 0 8px 32px rgba(59,130,246,0.3);
            position: relative; overflow: hidden;
        }
        .dashboard-header::before {
            content: ''; position: absolute;
            top: -40px; right: -40px; width: 200px; height: 200px;
            background: rgba(255,255,255,0.08); border-radius: 50%;
        }
        .dashboard-header::after {
            content: ''; position: absolute;
            bottom: -60px; right: 80px; width: 160px; height: 160px;
            background: rgba(255,255,255,0.05); border-radius: 50%;
        }
        .dashboard-header h1 {
            font-size: 26px; font-weight: 700; margin-bottom: 6px;
            position: relative; z-index: 1;
            display: flex; align-items: center; gap: 12px;
        }
        .dashboard-header p {
            font-size: 14px; opacity: 0.85; position: relative; z-index: 1;
        }

        /* ── STATS ── */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 28px;
        }
        .stat-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 26px 28px;
            box-shadow: 0 2px 12px rgba(59,130,246,0.07);
            border: 1px solid #e8eeff;
            display: flex; align-items: center; gap: 18px;
            transition: transform 0.2s, box-shadow 0.2s;
            cursor: pointer;
        }
        .stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(59,130,246,0.13); }
        .stat-icon {
            width: 58px; height: 58px; border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 24px; flex-shrink: 0; background: #eff6ff;
        }
        .stat-icon.amber { background: #fffbeb; }
        .stat-icon.green { background: #f0fdf4; }
        .stat-icon.purple { background: #f5f3ff; }
        .stat-info h3 {
            font-size: 32px; font-weight: 700; color: #1d4ed8;
            line-height: 1; margin-bottom: 5px;
        }
        .stat-info p { font-size: 13px; color: #6b7280; font-weight: 500; }

        /* ── CONTENT SECTION ── */
        .content-section {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(59,130,246,0.07);
            border: 1px solid #e8eeff;
            padding: 28px;
            margin-bottom: 28px;
        }
        .section-header {
            display: flex; justify-content: space-between; align-items: flex-start;
            margin-bottom: 24px; padding-bottom: 18px;
            border-bottom: 1px solid #f1f5ff;
            flex-wrap: wrap; gap: 16px;
        }
        .section-header h3 {
            font-size: 17px; font-weight: 700; color: #1a1a2e;
        }

        /* ── SEARCH & FILTERS ── */
        .search-filter {
            display: flex; gap: 12px; flex-wrap: wrap; align-items: center;
        }
        .search-box { flex: 1; min-width: 200px; position: relative; }
        .search-box input {
            width: 100%;
            padding: 10px 14px 10px 38px;
            border: 1.5px solid #e8eeff;
            border-radius: 8px;
            font-size: 13.5px;
            font-family: 'Inter', Arial, sans-serif;
            background: #f8faff; color: #1a1a2e;
            transition: all 0.2s;
        }
        .search-box input:focus {
            outline: none; border-color: #3b82f6;
            background: white; box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
        }
        .search-box::before {
            content: '🔍'; position: absolute;
            left: 11px; top: 50%; transform: translateY(-50%);
            font-size: 13px; pointer-events: none;
        }
        .filter-select {
            padding: 10px 14px;
            border: 1.5px solid #e8eeff; border-radius: 8px;
            background: #f8faff; color: #1a1a2e;
            font-weight: 600; font-size: 13.5px;
            font-family: 'Inter', Arial, sans-serif;
            cursor: pointer; transition: all 0.2s; min-width: 160px;
        }
        .filter-select:focus { outline: none; border-color: #3b82f6; }

        /* ── BADGE FILTERS ── */
        .badge-filters {
            display: flex; gap: 8px; margin-bottom: 20px; flex-wrap: wrap;
        }
        .badge-filter {
            padding: 7px 16px; border-radius: 20px;
            background: #eff6ff; color: #4b6cb7;
            font-weight: 600; font-size: 13px;
            cursor: pointer; transition: all 0.2s;
            border: 1.5px solid #e8eeff;
        }
        .badge-filter:hover { background: #dbeafe; color: #1d4ed8; border-color: #bfdbfe; }
        .badge-filter.active {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white; border-color: transparent;
            box-shadow: 0 4px 12px rgba(29,78,216,0.2);
        }

        /* ── TABLE ── */
        .table-container {
            overflow-x: auto; border-radius: 10px;
            border: 1px solid #e8eeff; background: white; margin-bottom: 24px;
        }
        .requests-table { width: 100%; border-collapse: collapse; min-width: 1100px; }
        .requests-table thead th {
            background: #f8faff;
            padding: 13px 18px; text-align: left;
            font-size: 12px; font-weight: 600; color: #6b7280;
            text-transform: uppercase; letter-spacing: 0.5px;
            border-bottom: 1px solid #e8eeff;
        }
        .requests-table tbody tr {
            border-bottom: 1px solid #f4f6ff;
            transition: background 0.15s;
        }
        .requests-table tbody tr:last-child { border-bottom: none; }
        .requests-table tbody tr:hover td { background: #f8faff; }
        .requests-table td { padding: 16px 18px; font-size: 14px; color: #374151; }

        /* urgent / today row tints */
        .urgent-row td { background: #fff7ed !important; }
        .today-row  td { background: #f0fdf4 !important; }

        /* ── CELL COMPONENTS ── */
        .child-info { display: flex; align-items: center; gap: 12px; }
        .child-avatar {
            width: 44px; height: 44px; border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 20px; flex-shrink: 0;
        }
        .child-details h4 { font-size: 14px; font-weight: 700; color: #1a1a2e; margin-bottom: 3px; }
        .child-details p  { font-size: 12px; color: #6b7280; }

        .parent-info { display: flex; flex-direction: column; gap: 5px; }
        .parent-info span { font-size: 13.5px; color: #374151; }
        .parent-info small { font-size: 12px; color: #6b7280; }

        .vaccine-info, .hospital-info, .time-info {
            display: flex; align-items: center; gap: 10px;
        }
        .vaccine-icon  { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; background: #fff1f2; flex-shrink: 0; }
        .hospital-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; background: #f0fdf4; flex-shrink: 0; }
        .time-icon     { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; background: #f5f3ff; flex-shrink: 0; }

        /* ── STATUS BADGE ── */
        .status-badge {
            padding: 5px 14px; border-radius: 20px;
            font-size: 12px; font-weight: 700;
            display: inline-flex; align-items: center; gap: 5px;
            text-transform: uppercase; letter-spacing: 0.4px;
        }
        .status-pending { background: #fef9c3; color: #854d0e; }

        /* ── ACTION BUTTONS ── */
        .action-buttons { display: flex; gap: 8px; flex-wrap: wrap; }
        .btn {
            padding: 9px 16px; border: none; border-radius: 8px;
            font-weight: 600; font-size: 13px; cursor: pointer;
            transition: all 0.2s; display: flex; align-items: center;
            justify-content: center; gap: 6px;
            font-family: 'Inter', Arial, sans-serif;
        }
        .btn:hover { transform: translateY(-2px); }
        .btn:disabled { opacity: 0.4; cursor: not-allowed; transform: none; }
        .btn-approve {
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: white; box-shadow: 0 4px 10px rgba(34,197,94,0.25);
        }
        .btn-approve:hover { box-shadow: 0 8px 18px rgba(34,197,94,0.35); }
        .btn-reject {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white; box-shadow: 0 4px 10px rgba(239,68,68,0.25);
        }
        .btn-reject:hover { box-shadow: 0 8px 18px rgba(239,68,68,0.35); }

        /* ── EMPTY STATE ── */
        .empty-state {
            text-align: center; padding: 60px 40px; color: #9ca3af;
        }
        .empty-state span { font-size: 52px; display: block; margin-bottom: 14px; }
        .empty-state h4 { font-size: 18px; color: #374151; font-weight: 700; margin-bottom: 8px; }
        .empty-state p  { font-size: 14px; margin-bottom: 20px; }
        .btn-refresh {
            padding: 11px 24px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white; border: none; border-radius: 10px;
            font-weight: 600; font-size: 14px; cursor: pointer;
            display: inline-flex; align-items: center; gap: 8px;
            transition: all 0.22s; box-shadow: 0 4px 14px rgba(29,78,216,0.2);
            font-family: 'Inter', Arial, sans-serif;
        }
        .btn-refresh:hover { transform: translateY(-2px); box-shadow: 0 8px 22px rgba(29,78,216,0.3); }

        /* ── PAGINATION ── */
        .pagination {
            display: flex; justify-content: center; align-items: center;
            gap: 10px; margin-top: 24px;
        }
        .pagination-btn {
            padding: 9px 18px;
            background: #ffffff; border: 1.5px solid #e8eeff;
            border-radius: 8px; cursor: pointer; font-weight: 600;
            font-size: 13.5px; transition: all 0.2s;
            display: flex; align-items: center; gap: 6px;
            font-family: 'Inter', Arial, sans-serif; color: #4b6cb7;
        }
        .pagination-btn:hover:not(:disabled) {
            background: #eff6ff; border-color: #3b82f6; color: #1d4ed8;
        }
        .pagination-btn:disabled { opacity: 0.4; cursor: not-allowed; }
        .page-numbers { display: flex; gap: 6px; }
        .page-number {
            width: 38px; height: 38px; display: flex; align-items: center;
            justify-content: center; border-radius: 8px; cursor: pointer;
            font-weight: 600; font-size: 14px; transition: all 0.2s;
            background: #ffffff; border: 1.5px solid #e8eeff; color: #4b6cb7;
        }
        .page-number:hover:not(.active) { background: #eff6ff; border-color: #3b82f6; color: #1d4ed8; }
        .page-number.active {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white; border-color: transparent;
            box-shadow: 0 4px 12px rgba(29,78,216,0.25);
        }

        /* ── LOADING OVERLAY ── */
        .loading {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15,23,42,0.5); backdrop-filter: blur(6px);
            z-index: 9999; justify-content: center; align-items: center;
            flex-direction: column; gap: 16px;
        }
        .loading.active { display: flex; }
        .spinner {
            width: 52px; height: 52px;
            border: 4px solid rgba(255,255,255,0.15);
            border-top-color: #3b82f6;
            border-radius: 50%; animation: spin 0.9s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .loading-text { color: white; font-size: 16px; font-weight: 600; }

        /* ── TOAST ── */
        .toast-container {
            position: fixed; top: 24px; right: 24px; z-index: 9999;
            display: flex; flex-direction: column; gap: 12px; max-width: 360px;
        }
        .toast {
            background: white; padding: 16px 18px;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(59,130,246,0.15);
            border: 1px solid #e8eeff;
            display: flex; align-items: center; gap: 14px;
            transform: translateX(150%);
            transition: transform 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        .toast.show { transform: translateX(0); }
        .toast-icon { font-size: 22px; flex-shrink: 0; }
        .toast-success { border-left: 4px solid #22c55e; }
        .toast-error   { border-left: 4px solid #ef4444; }
        .toast-warning { border-left: 4px solid #f59e0b; }
        .toast-content { flex: 1; }
        .toast-content h4 { font-size: 14px; font-weight: 700; color: #1a1a2e; margin-bottom: 3px; }
        .toast-content p  { font-size: 13px; color: #6b7280; margin: 0; }
        .toast-close {
            background: none; border: none; color: #9ca3af;
            cursor: pointer; font-size: 18px; transition: color 0.2s;
        }
        .toast-close:hover { color: #ef4444; }

        /* ── RESPONSIVE ── */
        @media(max-width: 1200px) {
            .stats-container { grid-template-columns: repeat(2,1fr); }
            .admin-navbar { height: auto; padding: 12px 20px; flex-direction: column; gap: 12px; }
        }
        @media(max-width: 768px) {
            .stats-container { grid-template-columns: 1fr 1fr; }
            .search-filter { flex-direction: column; }
            .filter-select { min-width: auto; }
            .action-buttons { flex-direction: column; }
            .container { padding: 0 14px; }
        }
        @media(max-width: 480px) {
            .stats-container { grid-template-columns: 1fr; }
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeOut {
            from { opacity: 1; transform: translateX(0); }
            to   { opacity: 0; transform: translateX(-60px); }
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
    <nav class="admin-navbar">
        <div class="logo">
            <div class="logo-icon">🛡️</div>
            <h2>Admin Panel</h2>
        </div>
        <div class="nav-links">
            <a href="dashboard.php">Dashboard</a>
            <a href="manage_children.php"> Children</a>
            <a href="manage_hospitals.php"> Hospitals</a>
            <a href="appointment_requests.php"> Requests</a>
            <a href="managevaccines.php"> Vaccines</a>
            <a href="bookingdetail.php"> Bookings</a>
            <a href="vaccination_reports.php" class="active"> Reports</a>
            <a href="system_settings.php"> Settings</a>
            <a href="../logout.php" class="logout">Logout</a>
        </div>
    </nav>

    <div class="container">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <h1>📋 Pending Appointment Requests</h1>
            <p>Review and manage vaccination appointment requests from parents. You have <?php echo $total_requests; ?> pending request<?php echo $total_requests != 1 ? 's' : ''; ?> to process.</p>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon">⏳</div>
                <div class="stat-info">
                    <h3><?php echo $total_requests; ?></h3>
                    <p>Pending Requests</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon amber">👶</div>
                <div class="stat-info">
                    <h3 id="totalChildren"><?php echo $child_count; ?></h3>
                    <p>Children Waiting</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green">🏥</div>
                <div class="stat-info">
                    <h3 id="uniqueHospitals"><?php echo count($hospital_set); ?></h3>
                    <p>Hospitals Involved</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple">💉</div>
                <div class="stat-info">
                    <h3 id="uniqueVaccines"><?php echo count($vaccine_set); ?></h3>
                    <p>Vaccine Types</p>
                </div>
            </div>
        </div>
        
        <!-- Main Content Section -->
        <div class="content-section">
            <div class="section-header">
                <h3>📋 Request Details</h3>
                <div class="search-filter">
                    <div class="search-box">
                        <input type="text" 
                               id="searchInput" 
                               placeholder="Search by child, parent, vaccine, hospital..." 
                               onkeyup="searchRequests()">
                    </div>
                    <select class="filter-select" id="hospitalFilter" onchange="filterByHospital()">
                        <option value="all">All Hospitals</option>
                        <?php foreach(array_keys($hospital_set) as $hospital) { if (!empty($hospital)) echo "<option value='$hospital'>$hospital</option>"; } ?>
                    </select>
                    <select class="filter-select" id="vaccineFilter" onchange="filterByVaccine()">
                        <option value="all">All Vaccines</option>
                        <?php foreach(array_keys($vaccine_set) as $vaccine) { if (!empty($vaccine)) echo "<option value='$vaccine'>$vaccine</option>"; } ?>
                    </select>
                </div>
            </div>
            
            <!-- Badge Filters -->
            <div class="badge-filters">
                <div class="badge-filter active" onclick="filterAll()">All (<?php echo $total_requests; ?>)</div>
                <div class="badge-filter" onclick="filterUrgent()">⚡ Urgent</div>
                <div class="badge-filter" onclick="filterToday()">📅 Today</div>
                <div class="badge-filter" onclick="filterThisWeek()">🗓️ This Week</div>
                <div class="badge-filter" onclick="filterWithNotes()">📝 With Notes</div>
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
                                    <div style="margin-top:5px; font-size:11px; color:#dc2626; font-weight:700;">⚡ URGENT</div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            
                            <td>
                                <div class="child-info">
                                    <div class="child-avatar">👶</div>
                                    <div class="child-details">
                                        <h4><?php echo htmlspecialchars($row['child_name'] ?? 'N/A'); ?></h4>
                                        <p>🎂 <?php echo $age_months; ?> months old</p>
                                    </div>
                                </div>
                            </td>
                            
                            <td>
                                <div class="parent-info">
                                    <span>👤 <?php echo htmlspecialchars($row['parent_name'] ?? 'N/A'); ?></span>
                                    <?php if (isset($row['parent_phone']) && $row['parent_phone'] !== null): ?>
                                    <small>📞 <?php echo htmlspecialchars($row['parent_phone']); ?></small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            
                            <td>
                                <div class="vaccine-info">
                                    <div class="vaccine-icon">💉</div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($row['vaccine_name'] ?? 'N/A'); ?></strong>
                                    </div>
                                </div>
                            </td>
                            
                            <td>
                                <div style="text-align:center">
                                    <span style="display:inline-block;width:42px;height:42px;line-height:42px;background:linear-gradient(135deg,#3b82f6,#1d4ed8);border-radius:50%;font-weight:700;color:white;font-size:16px;box-shadow:0 4px 12px rgba(29,78,216,0.25);">
                                        <?php echo $row['dose_number'] ?? 'N/A'; ?>
                                    </span>
                                    <div style="font-size:11px;color:#6b7280;margin-top:6px;font-weight:600;">DOSE</div>
                                </div>
                            </td>
                            
                            <td>
                                <div class="hospital-info">
                                    <div class="hospital-icon">🏥</div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($row['hospital_name'] ?? 'N/A'); ?></strong>
                                        <p style="font-size:12px; color:#6b7280; margin-top:4px">
                                            📍 <?php echo htmlspecialchars($row['city'] ?? 'N/A'); ?>
                                        </p>
                                    </div>
                                </div>
                            </td>
                            
                            <td>
                                <div class="time-info">
                                    <div class="time-icon">🕐</div>
                                    <div>
                                        <strong><?php echo !empty($row['preferred_date']) ? date('d M Y', strtotime($row['preferred_date'])) : 'N/A'; ?></strong>
                                        <p style="font-size:12px; color:#6b7280; margin-top:4px">
                                            🕐 <?php echo !empty($row['preferred_time']) ? date('h:i A', strtotime($row['preferred_time'])) : 'N/A'; ?>
                                        </p>
                                    </div>
                                </div>
                            </td>
                            
                            <td>
                                <span class="status-badge status-pending">⏳ Pending</span>
                                <?php if (!empty($row['parent_notes'])): ?>
                                <div style="margin-top:8px">
                                    <small style="color:#3b82f6; cursor:pointer; font-weight:600;"
                                           onclick="showNotes('<?php echo htmlspecialchars(addslashes($row['parent_notes'])); ?>')">
                                        📝 View Notes
                                    </small>
                                </div>
                                <?php endif; ?>
                            </td>
                            
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-approve" onclick="approveRequest(<?php echo $row['request_id']; ?>, this)">
                                        ✓ Approve
                                    </button>
                                    <button class="btn btn-reject" onclick="rejectRequest(<?php echo $row['request_id']; ?>, this)">
                                        ✕ Reject
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
                <button class="pagination-btn" id="prevPage" onclick="changePage(-1)">← Previous</button>
                <div class="page-numbers" id="pageNumbers"></div>
                <button class="pagination-btn" id="nextPage" onclick="changePage(1)">Next →</button>
            </div>
            
            <?php else: ?>
            <div class="empty-state">
                <span style="font-size:56px;display:block;margin-bottom:16px;">✅</span>
                <h4>No Pending Requests</h4>
                <p>All appointment requests have been processed. Great job!</p>
                <button class="btn-refresh" onclick="location.reload()">🔄 Refresh</button>
            </div>
            <?php endif; ?>
        </div>
        
    <script>
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
            resetBtn.innerHTML = '🔄 Reset';
            resetBtn.style.cssText = `
                padding: 10px 18px;
                background: #f8faff;
                color: #4b6cb7;
                border: 1.5px solid #e8eeff;
                border-radius: 8px;
                cursor: pointer;
                font-weight: 600;
                font-size: 13.5px;
                font-family: 'Inter', Arial, sans-serif;
                transition: all 0.2s;
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
            modal.style.cssText = `position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(15,23,42,0.5);display:flex;justify-content:center;align-items:center;z-index:10000;backdrop-filter:blur(6px);`;
            modal.innerHTML = `
                <div style="background:white;padding:28px;border-radius:16px;max-width:480px;width:90%;position:relative;box-shadow:0 24px 60px rgba(59,130,246,0.15);border:1px solid #e8eeff;">
                    <button onclick="this.parentElement.parentElement.remove()" style="position:absolute;top:14px;right:16px;background:none;border:none;font-size:22px;color:#9ca3af;cursor:pointer;">×</button>
                    <h3 style="margin-bottom:16px;font-size:17px;font-weight:700;color:#1d4ed8;">📝 Parent Notes</h3>
                    <div style="background:#f8faff;padding:18px;border-radius:10px;border:1px solid #e8eeff;max-height:260px;overflow-y:auto;line-height:1.7;font-size:14px;color:#374151;">${notes}</div>
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
            const icon = type === 'success' ? '✅' : type === 'error' ? '❌' : 'ℹ️';
            toast.className = `toast toast-${type}`;
            toast.innerHTML = `
                <span class="toast-icon">${icon}</span>
                <div class="toast-content">
                    <h4>${title}</h4>
                    <p>${message}</p>
                </div>
                <button class="toast-close" onclick="this.parentElement.remove()">×</button>
            `;
            toastContainer.appendChild(toast);
            setTimeout(() => toast.classList.add('show'), 10);
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.classList.remove('show');
                    setTimeout(() => { if (toast.parentElement) toast.remove(); }, 500);
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