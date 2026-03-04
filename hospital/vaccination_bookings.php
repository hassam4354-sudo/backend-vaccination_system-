<?php
session_start();
if(!isset($_SESSION["logged_in"]) || $_SESSION["user_type"] != "hospital"){
    header("location:../login.php");
    exit();
}

include("../dbconnection.php");

$user_id = $_SESSION["user_id"];
$today = date('Y-m-d');

// Get hospital data
$query_hospital = "SELECT * FROM hospitals WHERE user_id = '$user_id'";
$result_hospital = mysqli_query($connection, $query_hospital);
$hospital = mysqli_fetch_assoc($result_hospital);
$hospital_id = $hospital['hospital_id'] ?? 0;
$is_verified = $hospital['is_verified'] ?? 0;
$is_active = $hospital['is_active'] ?? 0;

// ── FILTERS ──
$filter_status = isset($_GET['status']) ? mysqli_real_escape_string($connection, $_GET['status']) : 'all';
$filter_date_from = isset($_GET['date_from']) ? mysqli_real_escape_string($connection, $_GET['date_from']) : '';
$filter_date_to = isset($_GET['date_to']) ? mysqli_real_escape_string($connection, $_GET['date_to']) : '';
$search = isset($_GET['search']) ? mysqli_real_escape_string($connection, $_GET['search']) : '';

$where = "vb.hospital_id = '$hospital_id'";
if($filter_status !== 'all') {
    $where .= " AND vb.booking_status = '$filter_status'";
}
if($filter_date_from && $filter_date_to) {
    $where .= " AND vb.appointment_date BETWEEN '$filter_date_from' AND '$filter_date_to'";
} elseif($filter_date_from) {
    $where .= " AND vb.appointment_date >= '$filter_date_from'";
} elseif($filter_date_to) {
    $where .= " AND vb.appointment_date <= '$filter_date_to'";
}
if($search) {
    $where .= " AND (c.full_name LIKE '%$search%' OR p.full_name LIKE '%$search%' OR v.vaccine_name LIKE '%$search%' OR vb.confirmation_code LIKE '%$search%')";
}

// ── COUNTS ──
$total_all = mysqli_fetch_assoc(mysqli_query($connection,
    "SELECT COUNT(*) as cnt FROM vaccination_bookings WHERE hospital_id = '$hospital_id'"))['cnt'];
    
$total_scheduled = mysqli_fetch_assoc(mysqli_query($connection,
    "SELECT COUNT(*) as cnt FROM vaccination_bookings WHERE hospital_id = '$hospital_id' AND booking_status = 'scheduled'"))['cnt'];
    
$total_completed = mysqli_fetch_assoc(mysqli_query($connection,
    "SELECT COUNT(*) as cnt FROM vaccination_bookings WHERE hospital_id = '$hospital_id' AND booking_status = 'completed'"))['cnt'];
    
$total_missed = mysqli_fetch_assoc(mysqli_query($connection,
    "SELECT COUNT(*) as cnt FROM vaccination_bookings WHERE hospital_id = '$hospital_id' AND booking_status = 'missed'"))['cnt'];
    
$total_cancelled = mysqli_fetch_assoc(mysqli_query($connection,
    "SELECT COUNT(*) as cnt FROM vaccination_bookings WHERE hospital_id = '$hospital_id' AND booking_status = 'cancelled'"))['cnt'];

// ── MAIN QUERY ──
$query = "SELECT vb.*, 
                 c.full_name as child_name, c.date_of_birth, c.gender,
                 v.vaccine_name, v.vaccine_code,
                 p.full_name as parent_name, p.emergency_contact
          FROM vaccination_bookings vb
          JOIN children c ON vb.child_id = c.child_id
          JOIN vaccines v ON vb.vaccine_id = v.vaccine_id
          JOIN parents p ON c.parent_id = p.parent_id
          WHERE $where
          ORDER BY vb.appointment_date DESC, vb.appointment_time ASC";

$result = mysqli_query($connection, $query);
$total_records = mysqli_num_rows($result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Bookings — VacciCare</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
    <style>
        /* Same CSS as appointment_requests.php */
        :root {
            --blue-900: #0a1628;
            --blue-700: #1a3a6e;
            --blue-600: #1e4db7;
            --blue-500: #2563eb;
            --blue-400: #3b82f6;
            --blue-100: #dbeafe;
            --blue-50:  #eff6ff;
            --gray-50:  #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-700: #334155;
            --white:    #ffffff;
            --bg:       #f0f4ff;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg);
            color: var(--blue-900);
            min-height: 100vh;
        }

        /* Navbar - same as before */
        .navbar {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 200;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(18px);
            border-bottom: 1px solid var(--gray-200);
            padding: 0 40px;
            height: 68px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 12px rgba(37,99,235,0.08);
        }
        .nav-logo {
            display: flex; align-items: center; gap: 12px;
            font-weight: 800; font-size: 18px; color: var(--blue-700);
            text-decoration: none;
        }
        .nav-logo .logo-icon {
            width: 40px; height: 40px; background: var(--blue-500);
            border-radius: 10px; display: flex; align-items: center;
            justify-content: center; font-size: 20px;
            box-shadow: 0 4px 14px rgba(37,99,235,0.35);
        }
        .nav-links { display: flex; align-items: center; gap: 4px; }
        .nav-link {
            display: flex; align-items: center; gap: 7px;
            padding: 8px 14px; border-radius: 8px; color: var(--gray-700);
            text-decoration: none; font-size: 13.5px; font-weight: 600;
            transition: all 0.2s;
        }
        .nav-link:hover { background: var(--blue-50); color: var(--blue-500); }
        .nav-link.active { background: var(--blue-50); color: var(--blue-500); }
        .nav-badge {
            background: #ef4444; color: white; font-size: 10px;
            font-weight: 700; padding: 1px 6px; border-radius: 20px;
        }
        .nav-right { display: flex; align-items: center; gap: 12px; }
        .nav-hospital-chip {
            display: flex; align-items: center; gap: 8px;
            background: var(--gray-50); border: 1px solid var(--gray-200);
            border-radius: 10px; padding: 7px 14px;
            font-size: 13px; font-weight: 600; color: var(--blue-900);
        }
        .nav-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; }
        .dot-green  { background: #4ade80; }
        .dot-yellow { background: #facc15; }
        .dot-red    { background: #f87171; }
        .nav-logout {
            display: flex; align-items: center; gap: 7px;
            padding: 8px 16px; background: #fee2e2; color: #dc2626;
            border: 1px solid #fecaca; border-radius: 9px;
            font-size: 13px; font-weight: 700; text-decoration: none;
            transition: all 0.2s;
        }
        .nav-logout:hover { background: #dc2626; color: white; }
        .hamburger {
            display: none; flex-direction: column; gap: 5px;
            cursor: pointer; padding: 6px;
        }
        .hamburger span { width: 22px; height: 2px; background: var(--gray-700); border-radius: 2px; }
        .mobile-menu {
            display: none; position: fixed; top: 68px; left: 0; right: 0;
            background: white; border-bottom: 1px solid var(--gray-200);
            padding: 12px 20px; z-index: 199;
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }
        .mobile-menu.open { display: block; }
        .mobile-menu .nav-link { display: flex; padding: 10px 14px; margin-bottom: 4px; }

        .main { padding-top: 68px; min-height: 100vh; }

        .verify-banner {
            margin: 20px 32px 0;
            padding: 13px 20px; border-radius: 12px;
            display: flex; align-items: center; gap: 12px;
            font-size: 14px; font-weight: 500;
        }
        .verify-banner.pending  { background: #fef9c3; border: 1px solid #fde68a; color: #92400e; }
        .verify-banner.verified { background: #dcfce7; border: 1px solid #bbf7d0; color: #166534; }
        .verify-banner.inactive { background: #fee2e2; border: 1px solid #fecaca; color: #991b1b; }

        .content { padding: 24px 32px 48px; }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            flex-wrap: wrap;
            gap: 16px;
        }
        .page-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 28px; font-weight: 800; color: var(--blue-900);
        }
        .page-header p {
            font-size: 13px; color: var(--gray-400); margin-top: 4px;
        }

        .alert {
            padding: 14px 20px; border-radius: 12px;
            font-size: 14px; font-weight: 600;
            margin-bottom: 24px;
            display: flex; align-items: center; gap: 10px;
            animation: slideDown 0.3s ease;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .alert-success { background: #dcfce7; border: 1px solid #bbf7d0; color: #166534; }
        .alert-error   { background: #fee2e2; border: 1px solid #fecaca; color: #991b1b; }

        .stats-strip {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        .strip-card {
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 14px;
            padding: 18px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }
        .strip-card:hover { 
            box-shadow: 0 6px 20px rgba(37,99,235,0.1); 
            transform: translateY(-2px); 
        }
        .strip-card.active-filter { 
            border-color: var(--blue-400); 
            box-shadow: 0 0 0 3px rgba(37,99,235,0.12); 
        }
        .strip-icon {
            width: 40px; height: 40px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; flex-shrink: 0;
        }
        .si-all   { background: var(--blue-50); }
        .si-scheduled { background: #fef9c3; }
        .si-completed { background: #dcfce7; }
        .si-missed { background: #fee2e2; }
        .si-cancelled { background: var(--gray-100); }
        .strip-num {
            font-family: 'Playfair Display', serif;
            font-size: 22px; font-weight: 800; color: var(--blue-900); line-height: 1;
        }
        .strip-label { font-size: 11px; color: var(--gray-500); margin-top: 2px; font-weight: 500; }

        .filters-bar {
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 14px;
            padding: 18px 22px;
            margin-bottom: 22px;
            display: flex;
            gap: 14px;
            align-items: center;
            flex-wrap: wrap;
        }
        .filters-bar input, .filters-bar select {
            padding: 9px 14px;
            border: 1.5px solid var(--gray-200);
            border-radius: 9px;
            font-size: 13.5px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--blue-900);
            background: var(--gray-50);
            transition: border-color 0.2s;
        }
        .filters-bar input:focus, .filters-bar select:focus {
            outline: none; border-color: var(--blue-400);
            box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
        }
        .search-wrap { position: relative; flex: 1; min-width: 200px; }
        .search-wrap input { width: 100%; padding-left: 38px; }
        .search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-size: 15px; }
        .filter-btn {
            padding: 9px 20px;
            background: var(--blue-500); color: white;
            border: none; border-radius: 9px;
            font-size: 13.5px; font-weight: 700;
            font-family: 'Plus Jakarta Sans', sans-serif;
            cursor: pointer; transition: all 0.2s;
        }
        .filter-btn:hover { background: var(--blue-600); }
        .reset-btn {
            padding: 9px 18px;
            background: var(--gray-100); color: var(--gray-700);
            border: 1px solid var(--gray-200); border-radius: 9px;
            font-size: 13.5px; font-weight: 600;
            font-family: 'Plus Jakarta Sans', sans-serif;
            cursor: pointer; text-decoration: none;
            transition: all 0.2s;
        }
        .reset-btn:hover { background: var(--gray-200); }

        .table-wrap {
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(37,99,235,0.06);
        }
        .table-header {
            padding: 18px 24px;
            border-bottom: 1px solid var(--gray-100);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .table-title {
            font-size: 15px; font-weight: 700; color: var(--blue-900);
        }
        .table-count {
            font-size: 12px; color: var(--gray-400);
            background: var(--gray-100); padding: 4px 12px;
            border-radius: 20px; font-weight: 600;
        }

        table { width: 100%; border-collapse: collapse; }
        thead tr { background: var(--gray-50); }
        thead th {
            padding: 12px 18px;
            text-align: left; font-size: 12px; font-weight: 700;
            color: var(--gray-500); text-transform: uppercase;
            letter-spacing: 0.5px; border-bottom: 1px solid var(--gray-200);
        }
        tbody tr {
            border-bottom: 1px solid #f4f6ff;
            transition: background 0.15s;
        }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: #f8faff; }
        td { padding: 14px 18px; font-size: 13.5px; vertical-align: middle; }

        .date-badge {
            background: var(--blue-50);
            color: var(--blue-700);
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        .time-badge {
            color: var(--gray-500);
            font-size: 11.5px;
            margin-top: 2px;
        }

        .child-cell { display: flex; align-items: center; gap: 12px; }
        .child-avatar {
            width: 38px; height: 38px;
            background: linear-gradient(135deg, var(--blue-100), #bfdbfe);
            border-radius: 50%; display: flex; align-items: center;
            justify-content: center; font-size: 18px; flex-shrink: 0;
        }
        .child-name { font-weight: 700; color: var(--blue-900); font-size: 13.5px; }
        .child-meta { font-size: 11.5px; color: var(--gray-400); margin-top: 2px; }

        .vaccine-name { font-weight: 600; color: var(--blue-700); }
        .dose-badge {
            display: inline-block; padding: 2px 8px;
            background: var(--blue-50); color: var(--blue-600);
            border-radius: 6px; font-size: 11px; font-weight: 700;
            margin-top: 3px;
        }

        .conf-code {
            font-family: monospace;
            font-size: 12px;
            font-weight: 700;
            background: var(--gray-100);
            padding: 3px 8px;
            border-radius: 6px;
            color: var(--gray-700);
        }

        .badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 5px 12px; border-radius: 20px;
            font-size: 12px; font-weight: 700; white-space: nowrap;
        }
        .badge::before { content: ''; width: 6px; height: 6px; border-radius: 50%; }
        .badge-scheduled  { background: #fef9c3; color: #92400e; }
        .badge-scheduled::before  { background: #f59e0b; }
        .badge-completed { background: #dcfce7; color: #166534; }
        .badge-completed::before { background: #22c55e; }
        .badge-missed { background: #fee2e2; color: #991b1b; }
        .badge-missed::before { background: #ef4444; }
        .badge-cancelled { background: var(--gray-100); color: var(--gray-500); }
        .badge-cancelled::before { background: var(--gray-400); }

        .action-btns { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .btn-view, .btn-record {
            padding: 6px 12px; border-radius: 6px;
            font-size: 12px; font-weight: 600;
            border: none; cursor: pointer; transition: all 0.2s;
            font-family: 'Plus Jakarta Sans', sans-serif;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-view {
            background: var(--blue-50); color: var(--blue-600);
            border: 1px solid var(--blue-100);
        }
        .btn-view:hover { background: var(--blue-500); color: white; }
        .btn-record {
            background: #ede9fe; color: #5b21b6;
            border: 1px solid #ddd6fe;
        }
        .btn-record:hover { background: #8b5cf6; color: white; }

        .empty-state {
            padding: 60px 24px; text-align: center; color: var(--gray-400);
        }
        .empty-state .e-icon { font-size: 56px; margin-bottom: 14px; }
        .empty-state h3 { font-size: 16px; font-weight: 700; margin-bottom: 6px; color: var(--gray-500); }
        .empty-state p { font-size: 13.5px; }

        .modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(10,22,40,0.5); z-index: 1000;
            align-items: center; justify-content: center;
            backdrop-filter: blur(4px);
        }
        .modal-overlay.open { display: flex; }
        .modal {
            background: white; border-radius: 20px;
            padding: 36px; width: 100%; max-width: 600px;
            box-shadow: 0 24px 60px rgba(0,0,0,0.2);
            animation: modalIn 0.25s ease;
            max-height: 90vh;
            overflow-y: auto;
        }
        @keyframes modalIn {
            from { opacity: 0; transform: scale(0.95) translateY(-10px); }
            to   { opacity: 1; transform: scale(1) translateY(0); }
        }
        .modal h2 {
            font-family: 'Playfair Display', serif;
            font-size: 24px; font-weight: 800; color: var(--blue-900);
            margin-bottom: 6px;
        }
        .modal p.modal-sub {
            font-size: 13.5px; color: var(--gray-500); 
            margin-bottom: 24px; padding-bottom: 12px;
            border-bottom: 1px solid var(--gray-100);
        }
        .modal-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 20px;
        }
        .modal-item .label {
            font-size: 11px; font-weight: 700; color: var(--gray-400);
            text-transform: uppercase; letter-spacing: 0.5px;
        }
        .modal-item .value {
            font-size: 15px; font-weight: 600; color: var(--blue-900);
            margin-top: 4px;
        }
        .modal-notes {
            grid-column: 1/-1;
            background: var(--blue-50);
            border: 1px solid var(--blue-100);
            border-radius: 10px;
            padding: 14px 18px;
            font-size: 13.5px;
            color: var(--blue-700);
        }
        .modal-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
            justify-content: flex-end;
        }
        .modal-btn {
            padding: 12px 24px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .modal-btn-primary {
            background: var(--blue-500);
            color: white;
        }
        .modal-btn-primary:hover {
            background: var(--blue-600);
        }
        .modal-btn-secondary {
            background: var(--gray-100);
            color: var(--gray-700);
            border: 1px solid var(--gray-200);
        }
        .modal-btn-secondary:hover {
            background: var(--gray-200);
        }

        @media(max-width: 1100px) {
            .stats-strip { grid-template-columns: repeat(3, 1fr); }
        }
        @media(max-width: 860px) {
            .nav-links { display: none; }
            .hamburger { display: flex; }
            .nav-hospital-chip { display: none; }
            .stats-strip { grid-template-columns: repeat(2, 1fr); }
            table { display: block; overflow-x: auto; }
        }
        @media(max-width: 640px) {
            .navbar { padding: 0 20px; }
            .content { padding: 16px 20px 32px; }
            .modal { padding: 24px 20px; margin: 16px; }
            .modal-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<nav class="navbar">
    <a href="dashboard.php" class="nav-logo">
      
        Hospital_Panel
    </a>
    <div class="nav-links">
        <a href="dashboard.php" class="nav-link"> Dashboard</a>
        <a href="appointment_requests.php" class="nav-link"> Requests</a>
        <a href="todays_schedule.php" class="nav-link"> Today</a>
        <a href="vaccination_bookings.php" class="nav-link active"> Bookings</a>
        <a href="vaccine_inventory.php" class="nav-link"> Inventory</a>
        <a href="doctors.php" class="nav-link">Doctors</a>
        <a href="vaccination_records.php" class="nav-link"> Records</a>
        <a href="my_profile.php" class="nav-link"> Profile</a>
    </div>
    <div class="nav-right">
        <div class="nav-hospital-chip">
            <span class="nav-dot <?php echo ($is_verified && $is_active) ? 'dot-green' : ($is_verified ? 'dot-red' : 'dot-yellow'); ?>"></span>
            <?php echo htmlspecialchars($hospital['hospital_name'] ?? 'Hospital'); ?>
        </div>
        <a href="../logout.php" class="nav-logout">🚪 Logout</a>
        <div class="hamburger" onclick="toggleMenu()">
            <span></span><span></span><span></span>
        </div>
    </div>
</nav>

<div class="mobile-menu" id="mobileMenu">
    <a href="dashboard.php" class="nav-link">🏠 Dashboard</a>
    <a href="appointment_requests.php" class="nav-link">📋 Requests</a>
    <a href="todays_schedule.php" class="nav-link">📅 Today</a>
    <a href="vaccination_bookings.php" class="nav-link active">💉 Bookings</a>
    <a href="vaccine_inventory.php" class="nav-link">🧪 Inventory</a>
    <a href="doctors.php" class="nav-link">👨‍⚕️ Doctors</a>
    <a href="vaccination_records.php" class="nav-link">📁 Records</a>
    <a href="my_profile.php" class="nav-link">🏥 Profile</a>
    <a href="../logout.php" class="nav-logout" style="margin-top:8px;">🚪 Logout</a>
</div>

<div class="main">
    <?php if(!$is_verified): ?>
    <div class="verify-banner pending">⏳ <strong>Pending Verification:</strong> Admin verification ka wait kar raha hai.</div>
    <?php elseif(!$is_active): ?>
    <div class="verify-banner inactive">🚫 <strong>Account Inactive:</strong> Admin se rabta karein.</div>
    <?php else: ?>
    <div class="verify-banner verified">✅ <strong>Verified Hospital:</strong> Account verified aur active hai.</div>
    <?php endif; ?>

    <div class="content">
        <div class="page-header">
            <div>
                <h1>💉 All Vaccination Bookings</h1>
                <p>Total <?php echo $total_all; ?> bookings · <?php echo $total_scheduled; ?> pending, <?php echo $total_completed; ?> completed</p>
            </div>
        </div>

        <?php if(isset($_GET['msg'])): ?>
        <div class="alert alert-success">✅ <?php echo htmlspecialchars($_GET['msg']); ?></div>
        <?php endif; ?>

        <div class="stats-strip">
            <a href="?status=all" class="strip-card <?php echo $filter_status=='all'?'active-filter':''; ?>">
                <div class="strip-icon si-all">📋</div>
                <div>
                    <div class="strip-num"><?php echo $total_all; ?></div>
                    <div class="strip-label">All</div>
                </div>
            </a>
            <a href="?status=scheduled" class="strip-card <?php echo $filter_status=='scheduled'?'active-filter':''; ?>">
                <div class="strip-icon si-scheduled">⏳</div>
                <div>
                    <div class="strip-num"><?php echo $total_scheduled; ?></div>
                    <div class="strip-label">Scheduled</div>
                </div>
            </a>
            <a href="?status=completed" class="strip-card <?php echo $filter_status=='completed'?'active-filter':''; ?>">
                <div class="strip-icon si-completed">✅</div>
                <div>
                    <div class="strip-num"><?php echo $total_completed; ?></div>
                    <div class="strip-label">Completed</div>
                </div>
            </a>
            <a href="?status=missed" class="strip-card <?php echo $filter_status=='missed'?'active-filter':''; ?>">
                <div class="strip-icon si-missed">❌</div>
                <div>
                    <div class="strip-num"><?php echo $total_missed; ?></div>
                    <div class="strip-label">Missed</div>
                </div>
            </a>
            <a href="?status=cancelled" class="strip-card <?php echo $filter_status=='cancelled'?'active-filter':''; ?>">
                <div class="strip-icon si-cancelled">🚫</div>
                <div>
                    <div class="strip-num"><?php echo $total_cancelled; ?></div>
                    <div class="strip-label">Cancelled</div>
                </div>
            </a>
        </div>

        <form method="GET" class="filters-bar">
            <div class="search-wrap">
                <span class="search-icon">🔍</span>
                <input type="text" name="search" placeholder="Search child, parent, vaccine, code..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <select name="status">
                <option value="all" <?php echo $filter_status=='all'?'selected':''; ?>>All Status</option>
                <option value="scheduled" <?php echo $filter_status=='scheduled'?'selected':''; ?>>Scheduled</option>
                <option value="completed" <?php echo $filter_status=='completed'?'selected':''; ?>>Completed</option>
                <option value="missed" <?php echo $filter_status=='missed'?'selected':''; ?>>Missed</option>
                <option value="cancelled" <?php echo $filter_status=='cancelled'?'selected':''; ?>>Cancelled</option>
            </select>
            <input type="date" name="date_from" value="<?php echo $filter_date_from; ?>" placeholder="From">
            <input type="date" name="date_to" value="<?php echo $filter_date_to; ?>" placeholder="To">
            <button type="submit" class="filter-btn">🔍 Filter</button>
            <a href="vaccination_bookings.php" class="reset-btn">↺ Reset</a>
        </form>

        <div class="table-wrap">
            <div class="table-header">
                <div class="table-title">📋 Booking Records</div>
                <div class="table-count"><?php echo $total_records; ?> records</div>
            </div>

            <?php if($total_records > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Date/Time</th>
                        <th>Child</th>
                        <th>Vaccine</th>
                        <th>Parent</th>
                        <th>Code</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php while($row = mysqli_fetch_assoc($result)): 
                    $age_days = floor((time() - strtotime($row['date_of_birth'])) / 86400);
                    $age_y = floor($age_days / 365);
                    $age_m = floor(($age_days % 365) / 30);
                    $age_str = $age_y > 0 ? "{$age_y}y {$age_m}m" : "{$age_m}m";
                ?>
                <tr>
                    <td>
                        <span class="date-badge"><?php echo date('d M Y', strtotime($row['appointment_date'])); ?></span>
                        <div class="time-badge">🕐 <?php echo date('h:i A', strtotime($row['appointment_time'])); ?></div>
                    </td>
                    <td>
                        <div class="child-cell">
                            <div class="child-avatar"><?php echo $row['gender']=='Female'?'👧':'👦'; ?></div>
                            <div>
                                <div class="child-name"><?php echo htmlspecialchars($row['child_name']); ?></div>
                                <div class="child-meta"><?php echo $age_str; ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="vaccine-name"><?php echo htmlspecialchars($row['vaccine_name']); ?></div>
                        <div class="dose-badge">Dose <?php echo $row['dose_number']; ?></div>
                    </td>
                    <td>
                        <div style="font-weight:600;"><?php echo htmlspecialchars($row['parent_name']); ?></div>
                        <?php if($row['emergency_contact']): ?>
                        <div style="font-size:11px; color:var(--gray-400);">📞 <?php echo $row['emergency_contact']; ?></div>
                        <?php endif; ?>
                    </td>
                    <td><span class="conf-code"><?php echo $row['confirmation_code']; ?></span></td>
                    <td><span class="badge badge-<?php echo $row['booking_status']; ?>"><?php echo ucfirst($row['booking_status']); ?></span></td>
                    <td>
                        <div class="action-btns">
                            <button class="btn-view" onclick="openViewModal(<?php echo htmlspecialchars(json_encode($row)); ?>)">👁 View</button>
                            <?php if($row['booking_status'] == 'completed'): 
                                $record_exists = mysqli_fetch_assoc(mysqli_query($connection,
                                    "SELECT record_id FROM vaccination_records WHERE booking_id = '{$row['booking_id']}'"));
                                if(!$record_exists):
                            ?>
                                <a href="add_vaccination_record.php?booking_id=<?php echo $row['booking_id']; ?>" class="btn-record">📝 Add Record</a>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <div class="e-icon">📭</div>
                <h3>No Bookings Found</h3>
                <p>No bookings match your current filters.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal-overlay" id="viewModal">
    <div class="modal">
        <h2>📋 Booking Details</h2>
        <p class="modal-sub" id="viewModalSub"></p>
        <div class="modal-grid" id="viewModalGrid"></div>
        <div class="modal-actions">
            <button type="button" class="modal-btn modal-btn-secondary" onclick="closeViewModal()">Close</button>
        </div>
    </div>
</div>

<script>
function toggleMenu() {
    document.getElementById('mobileMenu').classList.toggle('open');
}

function openViewModal(booking) {
    document.getElementById('viewModalSub').textContent = 'Booking #' + booking.booking_id + ' — ' + booking.booking_status.toUpperCase();
    
    const fields = [
        { label: 'Child Name', val: booking.child_name },
        { label: 'Gender', val: booking.gender },
        { label: 'Age', val: calculateAge(booking.date_of_birth) },
        { label: 'Parent Name', val: booking.parent_name },
        { label: 'Emergency Contact', val: booking.emergency_contact || '—' },
        { label: 'Vaccine', val: booking.vaccine_name },
        { label: 'Dose Number', val: 'Dose ' + booking.dose_number },
        { label: 'Date', val: booking.appointment_date },
        { label: 'Time', val: new Date('1970-01-01T' + booking.appointment_time).toLocaleTimeString('en-US', {hour: '2-digit', minute:'2-digit'}) },
        { label: 'Confirmation Code', val: booking.confirmation_code || '—' },
        { label: 'Status', val: booking.booking_status.toUpperCase() },
        { label: 'Created', val: booking.created_at },
    ];
    
    let html = '';
    fields.forEach(f => {
        html += `
            <div class="modal-item">
                <div class="label">${f.label}</div>
                <div class="value">${f.val}</div>
            </div>
        `;
    });
    
    document.getElementById('viewModalGrid').innerHTML = html;
    document.getElementById('viewModal').classList.add('open');
}

function calculateAge(dob) {
    let birthDate = new Date(dob);
    let today = new Date();
    let ageDays = Math.floor((today - birthDate) / (1000 * 60 * 60 * 24));
    let years = Math.floor(ageDays / 365);
    let months = Math.floor((ageDays % 365) / 30);
    return years > 0 ? years + 'y ' + months + 'm' : months + ' months';
}

function closeViewModal() {
    document.getElementById('viewModal').classList.remove('open');
}

document.getElementById('viewModal').addEventListener('click', function(e) {
    if(e.target === this) closeViewModal();
});
</script>

</body>
</html>