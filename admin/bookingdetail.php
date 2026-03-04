<?php
session_start();
include("../dbconnection.php");

// Check if admin is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['user_type'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';

// Sirf wahi bookings jo vaccine book ki hain ya lagwai hain - FULL QUERY
$query = "SELECT 
            vb.booking_id,
            vb.appointment_date as booking_date,
            vb.appointment_time,
            vb.booking_status,
            vb.confirmation_code,
            ar.request_status,
            c.full_name as child_name,
            p.full_name as parent_name,
            p.emergency_contact as parent_phone,
            v.vaccine_name,
            h.hospital_name,
            h.city as hospital_city,
            vr.vaccination_date,
            vr.vaccination_time,
            CASE 
                WHEN vb.booking_status = 'completed' THEN 'Vaccinated'
                WHEN vb.booking_status = 'cancelled' THEN 'Cancelled'
                WHEN vb.booking_status = 'scheduled' AND ar.request_status = 'approved' THEN 'Approved'
                WHEN vb.booking_status = 'scheduled' AND ar.request_status = 'pending' THEN 'Pending'
                ELSE vb.booking_status
            END as display_status
          FROM vaccination_bookings vb
          INNER JOIN appointment_requests ar ON vb.request_id = ar.request_id
          INNER JOIN children c ON vb.child_id = c.child_id
          INNER JOIN parents p ON c.parent_id = p.parent_id
          INNER JOIN vaccines v ON vb.vaccine_id = v.vaccine_id
          INNER JOIN hospitals h ON vb.hospital_id = h.hospital_id
          LEFT JOIN vaccination_records vr ON vb.booking_id = vr.booking_id
          WHERE 1=1";

// Apply status filter
if (!empty($status_filter) && $status_filter != 'all') {
    if ($status_filter == 'vaccinated') {
        $query .= " AND vb.booking_status = 'completed'";
    } elseif ($status_filter == 'pending') {
        $query .= " AND ar.request_status = 'pending' AND vb.booking_status = 'scheduled'";
    } elseif ($status_filter == 'approved') {
        $query .= " AND ar.request_status = 'approved' AND vb.booking_status = 'scheduled'";
    } elseif ($status_filter == 'cancelled') {
        $query .= " AND vb.booking_status = 'cancelled'";
    }
}

// Apply search filter
if (!empty($search)) {
    $search_term = "%$search%";
    $query .= " AND (c.full_name LIKE '%$search_term%' 
                    OR p.full_name LIKE '%$search_term%' 
                    OR v.vaccine_name LIKE '%$search_term%' 
                    OR h.hospital_name LIKE '%$search_term%'
                    OR vb.confirmation_code LIKE '%$search_term%')";
}

// Order by most recent
$query .= " ORDER BY vb.appointment_date DESC, vb.appointment_time DESC";

// Execute query
$result = mysqli_query($connection, $query);
if (!$result) {
    die("Query failed: " . mysqli_error($connection));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vaccination Bookings - Admin</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Google Fonts */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }
        
        :root {
            /* White & Blue Color Palette */
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
        
        body {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            min-height: 100vh;
            color: var(--gray-700);
            padding: 30px;
        }
        
        .container {
            max-width: 1600px;
            margin: 0 auto;
        }
        
        /* ===== PAGE HEADER ===== */
        .page-header {
            background: white;
            border-radius: 18px;
            padding: 25px 30px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
            border-left: 6px solid var(--primary);
            border-top: 1px solid var(--gray-200);
            border-right: 1px solid var(--gray-200);
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
            animation: fadeIn 0.5s ease;
        }
        
        .page-header h1 {
            font-size: 26px;
            font-weight: 700;
            color: var(--gray-700);
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0;
        }
        
        .page-header h1 i {
            color: white;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            padding: 12px;
            border-radius: 14px;
            font-size: 22px;
            box-shadow: 0 8px 15px rgba(37, 99, 235, 0.2);
        }
        
        .page-header p {
            color: var(--gray-500);
            font-size: 14px;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .page-header p i {
            color: var(--primary);
        }
        
        .booking-count {
            background: var(--primary-soft);
            padding: 10px 20px;
            border-radius: 40px;
            font-size: 14px;
            font-weight: 600;
            color: var(--primary);
            border: 1px solid var(--gray-200);
        }
        
        .booking-count i {
            margin-right: 8px;
        }
        
        /* ===== FILTER SECTION ===== */
        .filter-section {
            background: white;
            border-radius: 18px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
            animation: fadeIn 0.5s ease 0.1s both;
        }
        
        .filter-form {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .search-box {
            flex: 1;
            position: relative;
            min-width: 300px;
        }
        
        .search-box i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary);
            font-size: 16px;
        }
        
        .search-box input {
            width: 100%;
            padding: 14px 18px 14px 50px;
            border: 2px solid var(--gray-200);
            border-radius: 40px;
            font-size: 14px;
            transition: var(--transition);
            background: white;
            color: var(--gray-600);
        }
        
        .search-box input:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }
        
        .filter-select {
            padding: 14px 24px;
            border: 2px solid var(--gray-200);
            border-radius: 40px;
            font-size: 14px;
            min-width: 180px;
            cursor: pointer;
            background: white;
            color: var(--gray-600);
            font-weight: 500;
            transition: var(--transition);
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 20px center;
            background-size: 16px;
        }
        
        .filter-select:focus {
            border-color: var(--primary);
            outline: none;
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }
        
        .btn-filter {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 40px;
            padding: 14px 30px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 10px;
            border: 1px solid transparent;
            box-shadow: 0 8px 18px rgba(37, 99, 235, 0.25);
        }
        
        .btn-filter:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 25px rgba(37, 99, 235, 0.35);
        }
        
        .btn-reset {
            background: var(--gray-50);
            color: var(--gray-600);
            border: 1px solid var(--gray-200);
            border-radius: 40px;
            padding: 14px 30px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: var(--transition);
        }
        
        .btn-reset:hover {
            background: var(--gray-200);
            transform: translateY(-3px);
        }
        
        /* ===== TABLE CARD ===== */
        .table-card {
            background: white;
            border-radius: 18px;
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
        
        .booking-id {
            font-weight: 700;
            color: var(--primary);
        }
        
        .confirmation-code {
            font-family: monospace;
            background: var(--gray-100);
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 11px;
            display: inline-block;
            margin-top: 5px;
            color: var(--gray-600);
        }
        
        .child-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .child-avatar {
            width: 40px;
            height: 40px;
            background: var(--primary-soft);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
        }
        
        .parent-info {
            font-size: 12px;
            color: var(--gray-500);
            margin-top: 4px;
        }
        
        .parent-info i {
            color: var(--primary);
            width: 14px;
        }
        
        .vaccination-date {
            color: #10b981;
            font-weight: 600;
            font-size: 13px;
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
        
        .status-pending {
            background: var(--warning-light);
            color: #d97706;
            border-color: #fde68a;
        }
        
        .status-approved {
            background: var(--primary-soft);
            color: var(--primary-dark);
            border-color: var(--blue-soft);
        }
        
        .status-vaccinated {
            background: var(--success-light);
            color: #059669;
            border-color: #a7f3d0;
        }
        
        .status-cancelled {
            background: var(--danger-light);
            color: #dc2626;
            border-color: #fecaca;
        }
        
        /* ===== DELETE BUTTON ===== */
        .btn-delete {
            background: var(--danger-light);
            color: #dc2626;
            border: 1px solid #fecaca;
            border-radius: 8px;
            padding: 8px 16px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .btn-delete:hover {
            background: #dc2626;
            color: white;
            border-color: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 38, 38, 0.3);
        }
        
        /* ===== MODAL ===== */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
            z-index: 1000;
            backdrop-filter: blur(5px);
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 25px;
            width: 90%;
            max-width: 450px;
            box-shadow: 0 25px 50px rgba(220, 38, 38, 0.2);
            animation: slideIn 0.4s ease;
            overflow: hidden;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-header {
            background: linear-gradient(145deg, #dc2626, #b91c1c);
            color: white;
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h5 {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .modal-close {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            font-size: 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }
        
        .modal-close:hover {
            background: rgba(255,255,255,0.3);
            transform: rotate(90deg);
        }
        
        .modal-body {
            padding: 30px 25px;
            text-align: center;
        }
        
        .modal-footer {
            padding: 20px 25px;
            border-top: 2px solid var(--gray-200);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            background: #f9fdff;
        }
        
        .btn {
            padding: 10px 24px;
            border-radius: 40px;
            font-weight: 600;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-secondary {
            background: var(--gray-100);
            color: var(--gray-600);
            border: 1px solid var(--gray-200);
        }
        
        .btn-secondary:hover {
            background: var(--gray-200);
            transform: translateY(-2px);
        }
        
        .btn-danger {
            background: linear-gradient(145deg, #dc2626, #b91c1c);
            color: white;
            border: 1px solid transparent;
            box-shadow: 0 8px 18px rgba(220, 38, 38, 0.25);
        }
        
        .btn-danger:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 25px rgba(220, 38, 38, 0.35);
        }
        
        /* ===== ALERTS ===== */
        .alert {
            background: white;
            border-radius: 16px;
            padding: 16px 22px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.4s ease;
            border-left-width: 6px;
            border-left-style: solid;
            border-top: 1px solid;
            border-right: 1px solid;
            border-bottom: 1px solid;
        }
        
        .alert:not(.alert-danger) {
            background: linear-gradient(145deg, #e8f8f5, #d1f2eb);
            color: #1e6f5c;
            border-left-color: #10b981;
            border-color: #a7f3d0;
        }
        
        .alert-danger {
            background: linear-gradient(145deg, #fdeded, #f9e2e2);
            color: #c0392b;
            border-left-color: #ef4444;
            border-color: #fecaca;
        }
        
        .btn-close {
            background: none;
            border: none;
            color: inherit;
            font-size: 20px;
            cursor: pointer;
            margin-left: auto;
            padding: 0 8px;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
        @media (max-width: 768px) {
            body {
                padding: 15px;
            }
            
            .filter-form {
                flex-direction: column;
            }
            
            .search-box {
                min-width: 100%;
            }
            
            .filter-select {
                width: 100%;
            }
            
            .btn-filter, .btn-reset {
                width: 100%;
                justify-content: center;
            }
            
            .table-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .page-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .modal-footer {
                flex-direction: column;
            }
            
            .modal-footer .btn {
                width: 100%;
                justify-content: center;
            }
        }
          /* ── NAVBAR ── */
        .admin-navbar {
            background:#ffffff; border-bottom:2px solid #e8eeff;
            padding:0 35px; display:flex; justify-content:space-between;
            align-items:center; height:68px;
            box-shadow:0 2px 16px rgba(59,130,246,0.08);
            position:sticky; top:0; z-index:100;
        }
        .admin-navbar .logo { display:flex; align-items:center; gap:10px; }
        .admin-navbar .logo-icon {
            width:40px; height:40px;
            background:linear-gradient(135deg,#3b82f6,#1d4ed8);
            border-radius:10px; display:flex; align-items:center;
            justify-content:center; font-size:20px;
        }
        .admin-navbar .logo h2 { font-size:20px; font-weight:700; color:#1d4ed8; letter-spacing:-0.3px; }
        .nav-links { display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
        .nav-links a {
            color:#4b6cb7; text-decoration:none; padding:8px 14px;
            border-radius:8px; font-size:13.5px; font-weight:500;
            transition:all 0.2s; display:flex; align-items:center; gap:6px;
        }
        .nav-links a:hover { background:#eff6ff; color:#1d4ed8; }
        .nav-links a.active { background:#dbeafe; color:#1d4ed8; font-weight:600; }
        .nav-links a.logout { background:#fee2e2; color:#dc2626; }
        .nav-links a.logout:hover { background:#fecaca; }
    </style>
</head>
<body>
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
<br><br>
    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1>
                    <i class="fas fa-calendar-check"></i>
                    Vaccination Bookings
                </h1>
                <p><i class="fas fa-calendar-alt"></i> <?php echo date('l, F j, Y'); ?></p>
            </div>
            <div>
                <span class="booking-count">
                    <i class="fas fa-list-ul"></i> Total: <?php echo mysqli_num_rows($result); ?>
                </span>
            </div>
        </div>
        
        <!-- Flash Messages -->
        <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert <?php echo $_SESSION['flash_type'] == 'success' ? '' : 'alert-danger'; ?>">
            <i class="fas <?php echo $_SESSION['flash_type'] == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?>"></i>
            <?php 
                echo $_SESSION['flash_message'];
                unset($_SESSION['flash_message']);
                unset($_SESSION['flash_type']);
            ?>
            <button type="button" class="btn-close" onclick="this.parentElement.remove()">&times;</button>
        </div>
        <?php endif; ?>
        
        <!-- Filter Section -->
        <div class="filter-section">
            <form method="GET" action="" class="filter-form">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search by child, parent, vaccine, hospital..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <select class="filter-select" name="status">
                    <option value="all">All Status</option>
                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="vaccinated" <?php echo $status_filter == 'vaccinated' ? 'selected' : ''; ?>>Vaccinated</option>
                    <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
                <button type="submit" class="btn-filter">
                    <i class="fas fa-filter"></i> Apply
                </button>
                <a href="bookingdetail.php" class="btn-reset">
                    <i class="fas fa-redo"></i> Reset
                </a>
            </form>
        </div>
        
        <!-- Bookings Table -->
        <div class="table-card">
            <div class="table-header">
                <h3>
                    <i class="fas fa-list-ul"></i>
                    Bookings List
                </h3>
                <span class="booking-count">
                    <?php echo mysqli_num_rows($result); ?> Record<?php echo mysqli_num_rows($result) != 1 ? 's' : ''; ?>
                </span>
            </div>
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Child & Parent</th>
                            <th>Vaccine</th>
                            <th>Hospital</th>
                            <th>Booking Date</th>
                            <th>Vaccination Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && mysqli_num_rows($result) > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td>
                                    <span class="booking-id">#<?php echo $row['booking_id']; ?></span>
                                    <?php if ($row['confirmation_code']): ?>
                                    <div class="confirmation-code">
                                        <i class="fas fa-tag"></i> <?php echo $row['confirmation_code']; ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="child-info">
                                        <div class="child-avatar">
                                            <i class="fas fa-child"></i>
                                        </div>
                                        <div>
                                            <div style="font-weight: 600; color: var(--gray-700);"><?php echo htmlspecialchars($row['child_name']); ?></div>
                                            <div class="parent-info">
                                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($row['parent_name']); ?>
                                                <?php if ($row['parent_phone']): ?>
                                                <br><i class="fas fa-phone"></i> <?php echo htmlspecialchars($row['parent_phone']); ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: var(--gray-700);"><?php echo htmlspecialchars($row['vaccine_name']); ?></div>
                                </td>
                                <td>
                                    <div style="font-weight: 500; color: var(--gray-700);"><?php echo htmlspecialchars($row['hospital_name']); ?></div>
                                    <div class="parent-info">
                                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($row['hospital_city']); ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 600; color: var(--gray-700);"><?php echo date('d M Y', strtotime($row['booking_date'])); ?></div>
                                    <div class="parent-info">
                                        <i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($row['appointment_time'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($row['display_status'] == 'Vaccinated'): ?>
                                        <span class="vaccination-date">
                                            <i class="fas fa-check-circle"></i> 
                                            <?php echo date('d M Y', strtotime($row['vaccination_date'])); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="parent-info">
                                            <i class="fas fa-minus-circle"></i> Not vaccinated
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $status_class = '';
                                    $status_text = $row['display_status'];
                                    $status_icon = '';
                                    
                                    switch(strtolower($status_text)) {
                                        case 'pending':
                                            $status_class = 'status-pending';
                                            $status_icon = 'fa-clock';
                                            break;
                                        case 'approved':
                                            $status_class = 'status-approved';
                                            $status_icon = 'fa-check-circle';
                                            break;
                                        case 'vaccinated':
                                            $status_class = 'status-vaccinated';
                                            $status_icon = 'fa-check-double';
                                            break;
                                        case 'cancelled':
                                            $status_class = 'status-cancelled';
                                            $status_icon = 'fa-times-circle';
                                            break;
                                    }
                                    ?>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <i class="fas <?php echo $status_icon; ?>"></i>
                                        <?php echo $status_text; ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="btn-delete" onclick="showDeleteModal(<?php echo $row['booking_id']; ?>, '<?php echo htmlspecialchars(addslashes($row['child_name'])); ?>')">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="empty-state">
                                    <i class="fas fa-calendar-times"></i>
                                    <h4>No Bookings Found</h4>
                                    <p>No vaccination bookings found in the system.</p>
                                    <a href="bookingdetail.php" class="btn-filter" style="display: inline-block; margin-top: 15px; text-decoration: none;">
                                        <i class="fas fa-redo"></i> Reset Filters
                                    </a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Delete Modal -->
    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <div class="modal-header">
                <h5>
                    <i class="fas fa-trash"></i> Delete Booking
                </h5>
                <button type="button" class="modal-close" onclick="closeDeleteModal()">&times;</button>
            </div>
            <form action="delete_booking.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="booking_id" id="delete_booking_id">
                    
                    <div style="background: #fee2e2; width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 40px; color: #dc2626;"></i>
                    </div>
                    
                    <h5 style="font-weight: 700; margin-bottom: 15px; color: var(--gray-700);">Confirm Deletion</h5>
                    <p style="color: var(--gray-500); margin-bottom: 10px;">
                        Are you sure you want to delete the booking for <strong id="delete_child_name" style="color: var(--primary);"></strong>?
                    </p>
                    <p style="color: #dc2626; font-size: 13px;">
                        <i class="fas fa-exclamation-circle"></i> This action cannot be undone.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Delete Booking
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Show delete modal
        function showDeleteModal(bookingId, childName) {
            document.getElementById('delete_booking_id').value = bookingId;
            document.getElementById('delete_child_name').innerHTML = childName;
            document.getElementById('deleteModal').classList.add('active');
        }
        
        // Close delete modal
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            var modal = document.getElementById('deleteModal');
            if (event.target == modal) {
                modal.classList.remove('active');
            }
        }
        
        // Auto-submit filter on status change
        document.querySelector('.filter-select')?.addEventListener('change', function() {
            this.form.submit();
        });
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(function() {
                    if (alert.parentNode) {
                        alert.remove();
                    }
                }, 500);
            });
        }, 5000);
    </script>
</body>
</html>
<?php mysqli_close($connection); ?>