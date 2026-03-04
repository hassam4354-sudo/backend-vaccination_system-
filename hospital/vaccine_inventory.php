<?php
session_start();
if(!isset($_SESSION["logged_in"]) || $_SESSION["user_type"] != "hospital"){
    header("Location: ../login.php");
    exit();
}

include("../dbconnection.php");

// Flash message from add/edit
$flash_msg = isset($_SESSION['msg']) ? $_SESSION['msg'] : '';
$flash_type = isset($_SESSION['msg_type']) ? $_SESSION['msg_type'] : '';
unset($_SESSION['msg'], $_SESSION['msg_type']);

$user_id = $_SESSION["user_id"];
$today = date('Y-m-d');

// Get hospital data
$query_hospital = "SELECT * FROM hospitals WHERE user_id = '$user_id'";
$result_hospital = mysqli_query($connection, $query_hospital);
$hospital = mysqli_fetch_assoc($result_hospital);
$hospital_id = $hospital['hospital_id'] ?? 0;
$is_verified = $hospital['is_verified'] ?? 0;
$is_active = $hospital['is_active'] ?? 0;

// ── HANDLE DELETE INVENTORY ──
if(isset($_GET['delete']) && isset($_GET['inventory_id'])) {
    $inventory_id = intval($_GET['inventory_id']);
    
    // Check if inventory belongs to this hospital
    $check = mysqli_query($connection,
        "SELECT inventory_id FROM hospital_vaccine_inventory 
         WHERE inventory_id = '$inventory_id' AND hospital_id = '$hospital_id'");
    
    if(mysqli_num_rows($check) > 0) {
        $delete = mysqli_query($connection,
            "DELETE FROM hospital_vaccine_inventory WHERE inventory_id = '$inventory_id'");
        
        if($delete) {
            $success_msg = "✅ Inventory record deleted successfully!";
        } else {
            $error_msg = "❌ Error deleting inventory record.";
        }
    } else {
        $error_msg = "❌ Invalid inventory ID.";
    }
}

// ── HANDLE UPDATE AVAILABILITY ──
if(isset($_POST['update_availability'])) {
    $inventory_id = intval($_POST['inventory_id']);
    $is_available = intval($_POST['is_available']);
    
    $update = mysqli_query($connection,
        "UPDATE hospital_vaccine_inventory 
         SET is_available = '$is_available', updated_at = NOW()
         WHERE inventory_id = '$inventory_id' AND hospital_id = '$hospital_id'");
    
    if($update) {
        $success_msg = "✅ Availability updated successfully!";
    } else {
        $error_msg = "❌ Error updating availability.";
    }
}

// ── FILTERS ──
$filter_vaccine = isset($_GET['vaccine_id']) ? intval($_GET['vaccine_id']) : 0;
$filter_status = isset($_GET['status']) ? mysqli_real_escape_string($connection, $_GET['status']) : 'all';
$search = isset($_GET['search']) ? mysqli_real_escape_string($connection, $_GET['search']) : '';

$where = "hvi.hospital_id = '$hospital_id'";
if($filter_vaccine > 0) {
    $where .= " AND hvi.vaccine_id = '$filter_vaccine'";
}
if($filter_status == 'available') {
    $where .= " AND hvi.is_available = 1 AND hvi.quantity_available > 0";
} elseif($filter_status == 'unavailable') {
    $where .= " AND (hvi.is_available = 0 OR hvi.quantity_available <= 0)";
} elseif($filter_status == 'expiring') {
    $expiry_threshold = date('Y-m-d', strtotime('+30 days'));
    $where .= " AND hvi.expiry_date <= '$expiry_threshold' AND hvi.expiry_date >= '$today'";
} elseif($filter_status == 'expired') {
    $where .= " AND hvi.expiry_date < '$today'";
}
if($search) {
    $where .= " AND (v.vaccine_name LIKE '%$search%' OR v.vaccine_code LIKE '%$search%' OR hvi.batch_number LIKE '%$search%')";
}

// ── GET ALL VACCINES FOR FILTER ──
$vaccines_query = "SELECT vaccine_id, vaccine_name, vaccine_code FROM vaccines WHERE is_active = 1 ORDER BY vaccine_name";
$vaccines_result = mysqli_query($connection, $vaccines_query);

// ── COUNTS ──
$total_items = mysqli_fetch_assoc(mysqli_query($connection,
    "SELECT COUNT(*) as cnt FROM hospital_vaccine_inventory WHERE hospital_id = '$hospital_id'"))['cnt'];
    
$total_available = mysqli_fetch_assoc(mysqli_query($connection,
    "SELECT COUNT(*) as cnt FROM hospital_vaccine_inventory 
     WHERE hospital_id = '$hospital_id' AND is_available = 1 AND quantity_available > 0"))['cnt'];
     
$total_unavailable = mysqli_fetch_assoc(mysqli_query($connection,
    "SELECT COUNT(*) as cnt FROM hospital_vaccine_inventory 
     WHERE hospital_id = '$hospital_id' AND (is_available = 0 OR quantity_available <= 0)"))['cnt'];
     
$total_expiring = mysqli_fetch_assoc(mysqli_query($connection,
    "SELECT COUNT(*) as cnt FROM hospital_vaccine_inventory 
     WHERE hospital_id = '$hospital_id' AND expiry_date <= DATE_ADD('$today', INTERVAL 30 DAY) 
     AND expiry_date >= '$today'"))['cnt'];
     
$total_expired = mysqli_fetch_assoc(mysqli_query($connection,
    "SELECT COUNT(*) as cnt FROM hospital_vaccine_inventory 
     WHERE hospital_id = '$hospital_id' AND expiry_date < '$today'"))['cnt'];

// ── MAIN QUERY ──
$query = "SELECT hvi.*, 
                 v.vaccine_name, v.vaccine_code, v.manufacturer,
                 DATEDIFF(hvi.expiry_date, '$today') as days_to_expiry
          FROM hospital_vaccine_inventory hvi
          JOIN vaccines v ON hvi.vaccine_id = v.vaccine_id
          WHERE $where
          ORDER BY 
            CASE 
                WHEN hvi.expiry_date < '$today' THEN 1
                WHEN hvi.expiry_date <= DATE_ADD('$today', INTERVAL 30 DAY) THEN 2
                ELSE 3
            END,
            hvi.expiry_date ASC,
            v.vaccine_name ASC";

$result = mysqli_query($connection, $query);
$total_records = mysqli_num_rows($result);

// ── TOTAL QUANTITY ──
$total_quantity = mysqli_fetch_assoc(mysqli_query($connection,
    "SELECT SUM(quantity_available) as total FROM hospital_vaccine_inventory 
     WHERE hospital_id = '$hospital_id'"))['total'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vaccine Inventory — VacciCare</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
    <style>
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
            --green-100: #dcfce7;
            --green-600: #16a34a;
            --yellow-100: #fef9c3;
            --yellow-600: #ca8a04;
            --red-100: #fee2e2;
            --red-600: #dc2626;
            --orange-100: #ffedd5;
            --orange-600: #ea580c;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg);
            color: var(--blue-900);
            min-height: 100vh;
        }

        /* Navbar */
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
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .page-header h1 span {
            font-size: 14px;
            background: var(--blue-100);
            color: var(--blue-600);
            padding: 4px 12px;
            border-radius: 30px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 600;
        }
        .page-header p {
            font-size: 13px; color: var(--gray-400); margin-top: 4px;
        }
        .add-btn {
            background: linear-gradient(135deg, #1d4ed8, #3b82f6);
            color: white;
            padding: 12px 24px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 14px rgba(37,99,235,0.3);
            transition: all 0.2s;
        }
        .add-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(37,99,235,0.4);
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 14px;
            padding: 18px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.2s;
        }
        .stat-card:hover { 
            box-shadow: 0 6px 20px rgba(37,99,235,0.1); 
            transform: translateY(-2px); 
        }
        .stat-icon {
            width: 40px; height: 40px; border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; flex-shrink: 0;
        }
        .si-total    { background: var(--blue-50); }
        .si-available { background: #dcfce7; }
        .si-unavailable { background: #fee2e2; }
        .si-expiring { background: #fef9c3; }
        .si-expired { background: #ffedd5; }
        .stat-num {
            font-family: 'Playfair Display', serif;
            font-size: 22px; font-weight: 800; color: var(--blue-900); line-height: 1;
        }
        .stat-label { font-size: 11px; color: var(--gray-500); margin-top: 2px; font-weight: 500; }

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
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .table-count {
            font-size: 12px; color: var(--gray-400);
            background: var(--gray-100); padding: 4px 12px;
            border-radius: 20px; font-weight: 600;
        }
        .total-quantity {
            background: var(--blue-50);
            color: var(--blue-700);
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 12px;
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

        .vaccine-name { font-weight: 700; color: var(--blue-700); }
        .vaccine-code {
            font-size: 11px;
            color: var(--gray-400);
            margin-top: 2px;
        }

        .batch-badge {
            font-family: monospace;
            font-size: 12px;
            font-weight: 600;
            background: var(--gray-100);
            padding: 4px 8px;
            border-radius: 6px;
            color: var(--gray-700);
        }

        .quantity {
            font-size: 18px;
            font-weight: 800;
            color: var(--blue-900);
        }
        .quantity-unit {
            font-size: 11px;
            color: var(--gray-400);
            margin-left: 2px;
        }

        .expiry-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .expiry-normal {
            background: var(--blue-50);
            color: var(--blue-700);
        }
        .expiry-warning {
            background: #fef9c3;
            color: #854d0e;
        }
        .expiry-danger {
            background: #fee2e2;
            color: #991b1b;
        }
        .expiry-expired {
            background: #ffedd5;
            color: #9a3412;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }
        .status-badge.available {
            background: #dcfce7;
            color: #166534;
        }
        .status-badge.unavailable {
            background: #fee2e2;
            color: #991b1b;
        }

        .action-btns {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .btn-edit, .btn-delete, .btn-toggle {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            font-family: 'Plus Jakarta Sans', sans-serif;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-edit {
            background: var(--blue-50);
            color: var(--blue-600);
            border: 1px solid var(--blue-100);
        }
        .btn-edit:hover { background: var(--blue-500); color: white; }
        .btn-delete {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        .btn-delete:hover { background: #dc2626; color: white; }
        .btn-toggle {
            background: var(--gray-100);
            color: var(--gray-700);
            border: 1px solid var(--gray-200);
        }
        .btn-toggle:hover { background: var(--gray-300); }

        .empty-state {
            padding: 60px 24px;
            text-align: center;
            color: var(--gray-400);
        }
        .empty-state .e-icon { font-size: 56px; margin-bottom: 14px; }
        .empty-state h3 { font-size: 16px; font-weight: 700; margin-bottom: 6px; color: var(--gray-500); }
        .empty-state p { font-size: 13.5px; }
        .empty-state .btn {
            display: inline-block;
            margin-top: 16px;
            padding: 10px 20px;
            background: var(--blue-500);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
        }

        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(10,22,40,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }
        .modal-overlay.open { display: flex; }
        .modal {
            background: white;
            border-radius: 20px;
            padding: 36px;
            width: 100%;
            max-width: 400px;
            box-shadow: 0 24px 60px rgba(0,0,0,0.2);
            animation: modalIn 0.25s ease;
        }
        @keyframes modalIn {
            from { opacity: 0; transform: scale(0.95) translateY(-10px); }
            to   { opacity: 1; transform: scale(1) translateY(0); }
        }
        .modal h3 {
            font-size: 20px;
            font-weight: 700;
            color: var(--blue-900);
            margin-bottom: 12px;
        }
        .modal p {
            color: var(--gray-500);
            margin-bottom: 24px;
        }
        .modal-actions {
            display: flex;
            gap: 12px;
        }
        .modal-btn {
            flex: 1;
            padding: 12px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .modal-btn-confirm {
            background: #dc2626;
            color: white;
        }
        .modal-btn-confirm:hover { background: #b91c1c; }
        .modal-btn-cancel {
            background: var(--gray-100);
            color: var(--gray-700);
        }
        .modal-btn-cancel:hover { background: var(--gray-200); }

        @media(max-width: 1100px) {
            .stats-grid { grid-template-columns: repeat(3, 1fr); }
        }
        @media(max-width: 860px) {
            .nav-links { display: none; }
            .hamburger { display: flex; }
            .nav-hospital-chip { display: none; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            table { display: block; overflow-x: auto; }
        }
        @media(max-width: 640px) {
            .navbar { padding: 0 20px; }
            .content { padding: 16px 20px 32px; }
            .page-header { flex-direction: column; align-items: flex-start; }
            .modal { padding: 24px 20px; margin: 16px; }
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
        <a href="appointment_requests.php" class="nav-link">Requests</a>
        <a href="todays_schedule.php" class="nav-link"> Today</a>
        <a href="vaccination_bookings.php" class="nav-link"> Bookings</a>
        <a href="vaccine_inventory.php" class="nav-link active"> Inventory</a>
        <a href="doctors.php" class="nav-link"> Doctors</a>
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
    <a href="vaccination_bookings.php" class="nav-link">💉 Bookings</a>
    <a href="vaccine_inventory.php" class="nav-link active">🧪 Inventory</a>
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
                <h1>
                    🧪 Vaccine Inventory
                    <span>Total Stock: <?php echo $total_quantity; ?> doses</span>
                </h1>
                <p><?php echo $total_items; ?> inventory items · <?php echo $total_available; ?> available</p>
            </div>
            <a href="add_inventory.php" class="add-btn">
                <span>+</span> Add New Stock
            </a>
        </div>

        <?php if(isset($success_msg)): ?>
        <div class="alert alert-success">✅ <?php echo $success_msg; ?></div>
        <?php endif; ?>
        <?php if(isset($error_msg)): ?>
        <div class="alert alert-error">❌ <?php echo $error_msg; ?></div>
        <?php endif; ?>
        <?php if($flash_msg): ?>
        <div class="alert alert-<?php echo $flash_type === 'success' ? 'success' : 'error'; ?>">
            <?php echo $flash_msg; ?>
        </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon si-total">📦</div>
                <div>
                    <div class="stat-num"><?php echo $total_items; ?></div>
                    <div class="stat-label">Total Items</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon si-available">✅</div>
                <div>
                    <div class="stat-num"><?php echo $total_available; ?></div>
                    <div class="stat-label">Available</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon si-unavailable">❌</div>
                <div>
                    <div class="stat-num"><?php echo $total_unavailable; ?></div>
                    <div class="stat-label">Unavailable</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon si-expiring">⚠️</div>
                <div>
                    <div class="stat-num"><?php echo $total_expiring; ?></div>
                    <div class="stat-label">Expiring Soon</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon si-expired">⌛</div>
                <div>
                    <div class="stat-num"><?php echo $total_expired; ?></div>
                    <div class="stat-label">Expired</div>
                </div>
            </div>
        </div>

        <form method="GET" class="filters-bar">
            <div class="search-wrap">
                <span class="search-icon">🔍</span>
                <input type="text" name="search" placeholder="Search vaccine, batch..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <select name="vaccine_id">
                <option value="0">All Vaccines</option>
                <?php 
                mysqli_data_seek($vaccines_result, 0);
                while($v = mysqli_fetch_assoc($vaccines_result)): 
                ?>
                <option value="<?php echo $v['vaccine_id']; ?>" <?php echo $filter_vaccine == $v['vaccine_id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($v['vaccine_name']); ?> (<?php echo $v['vaccine_code']; ?>)
                </option>
                <?php endwhile; ?>
            </select>
            <select name="status">
                <option value="all" <?php echo $filter_status=='all'?'selected':''; ?>>All Status</option>
                <option value="available" <?php echo $filter_status=='available'?'selected':''; ?>>Available</option>
                <option value="unavailable" <?php echo $filter_status=='unavailable'?'selected':''; ?>>Unavailable</option>
                <option value="expiring" <?php echo $filter_status=='expiring'?'selected':''; ?>>Expiring Soon (30 days)</option>
                <option value="expired" <?php echo $filter_status=='expired'?'selected':''; ?>>Expired</option>
            </select>
            <button type="submit" class="filter-btn">🔍 Filter</button>
            <a href="vaccine_inventory.php" class="reset-btn">↺ Reset</a>
        </form>

        <div class="table-wrap">
            <div class="table-header">
                <div class="table-title">
                    📋 Inventory List
                    <span class="total-quantity">Total Doses: <?php echo $total_quantity; ?></span>
                </div>
                <div class="table-count"><?php echo $total_records; ?> records</div>
            </div>

            <?php if($total_records > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Vaccine</th>
                        <th>Batch Number</th>
                        <th>Quantity</th>
                        <th>Expiry Date</th>
                        <th>Last Restocked</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php while($row = mysqli_fetch_assoc($result)): 
                    $expiry_class = 'expiry-normal';
                    $expiry_text = 'Valid';
                    
                    if($row['expiry_date'] < $today) {
                        $expiry_class = 'expiry-expired';
                        $expiry_text = 'Expired';
                    } elseif($row['days_to_expiry'] <= 7) {
                        $expiry_class = 'expiry-danger';
                        $expiry_text = 'Critical';
                    } elseif($row['days_to_expiry'] <= 30) {
                        $expiry_class = 'expiry-warning';
                        $expiry_text = 'Expiring Soon';
                    }
                ?>
                <tr>
                    <td>
                        <div class="vaccine-name"><?php echo htmlspecialchars($row['vaccine_name']); ?></div>
                        <div class="vaccine-code"><?php echo $row['vaccine_code']; ?></div>
                        <?php if($row['manufacturer']): ?>
                        <div style="font-size:10px; color:var(--gray-400);">🏭 <?php echo $row['manufacturer']; ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="batch-badge"><?php echo htmlspecialchars($row['batch_number'] ?? 'N/A'); ?></span>
                    </td>
                    <td>
                        <span class="quantity"><?php echo $row['quantity_available']; ?></span>
                        <span class="quantity-unit">doses</span>
                    </td>
                    <td>
                        <span class="expiry-badge <?php echo $expiry_class; ?>">
                            📅 <?php echo date('d M Y', strtotime($row['expiry_date'])); ?>
                            <br><small><?php echo $expiry_text; ?></small>
                        </span>
                    </td>
                    <td>
                        <?php echo $row['last_restocked_date'] ? date('d M Y', strtotime($row['last_restocked_date'])) : '—'; ?>
                    </td>
                    <td>
                        <span class="status-badge <?php echo $row['is_available'] && $row['quantity_available'] > 0 ? 'available' : 'unavailable'; ?>">
                            <?php if($row['is_available'] && $row['quantity_available'] > 0): ?>
                            ✅ Available
                            <?php else: ?>
                            ❌ Unavailable
                            <?php endif; ?>
                        </span>
                    </td>
                    <td>
                        <div class="action-btns">
                            <a href="edit_inventory.php?id=<?php echo $row['inventory_id']; ?>" class="btn-edit">✏️ Edit</a>
                            
                            <!-- Toggle Availability Form -->
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="inventory_id" value="<?php echo $row['inventory_id']; ?>">
                                <input type="hidden" name="is_available" value="<?php echo $row['is_available'] ? 0 : 1; ?>">
                                <button type="submit" name="update_availability" class="btn-toggle">
                                    <?php echo $row['is_available'] ? '🔴 Set Unavailable' : '🟢 Set Available'; ?>
                                </button>
                            </form>
                            
                            <button class="btn-delete" onclick="confirmDelete(<?php echo $row['inventory_id']; ?>)">🗑️ Delete</button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <div class="e-icon">📦</div>
                <h3>No Inventory Found</h3>
                <p>
                    <?php if($filter_vaccine > 0 || $filter_status != 'all' || $search): ?>
                    No items match your current filters.
                    <?php else: ?>
                    Aapne abhi tak koi vaccine stock add nahi kiya.
                    <?php endif; ?>
                </p>
                <a href="add_inventory.php" class="btn">➕ Add Your First Stock</a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Quick Stats Note -->
        <div style="margin-top: 16px; background: white; border-radius: 10px; padding: 12px 20px; border: 1px solid var(--gray-200); font-size: 12px; color: var(--gray-500);">
            <span style="display: inline-block; width: 10px; height: 10px; background: #22c55e; border-radius: 50%; margin-right: 6px;"></span> Available · 
            <span style="display: inline-block; width: 10px; height: 10px; background: #ef4444; border-radius: 50%; margin-right: 6px; margin-left: 12px;"></span> Unavailable · 
            <span style="display: inline-block; width: 10px; height: 10px; background: #f59e0b; border-radius: 50%; margin-right: 6px; margin-left: 12px;"></span> Expiring Soon · 
            <span style="display: inline-block; width: 10px; height: 10px; background: #ea580c; border-radius: 50%; margin-right: 6px; margin-left: 12px;"></span> Expired
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal">
        <h3>🗑️ Delete Inventory</h3>
        <p>Kya aap ye inventory record delete karna chahte hain? Yeh action wapas nahi kiya ja sakta.</p>
        <div class="modal-actions">
            <button class="modal-btn modal-btn-cancel" onclick="closeDeleteModal()">Cancel</button>
            <a href="#" id="confirmDeleteBtn" class="modal-btn modal-btn-confirm" style="text-decoration: none; text-align: center;">Delete</a>
        </div>
    </div>
</div>

<script>
function toggleMenu() {
    document.getElementById('mobileMenu').classList.toggle('open');
}

function confirmDelete(inventoryId) {
    document.getElementById('confirmDeleteBtn').href = '?delete=1&inventory_id=' + inventoryId;
    document.getElementById('deleteModal').classList.add('open');
}

function closeDeleteModal() {
    document.getElementById('deleteModal').classList.remove('open');
}

// Close modal on overlay click
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if(e.target === this) closeDeleteModal();
});

// Auto-hide alerts after 5 seconds
setTimeout(function() {
    document.querySelectorAll('.alert').forEach(el => {
        el.style.opacity = '0';
        setTimeout(() => el.style.display = 'none', 300);
    });
}, 5000);
</script>


    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        <?php if($flash_msg && $flash_type === 'success'): ?>
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: '<?php echo addslashes($flash_msg); ?>',
            confirmButtonColor: '#2563eb',
            timer: 3000,
            timerProgressBar: true
        });
        <?php elseif($flash_msg && $flash_type === 'error'): ?>
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            html: '<?php echo addslashes($flash_msg); ?>',
            confirmButtonColor: '#2563eb'
        });
        <?php endif; ?>
    </script>
</body>
</html>