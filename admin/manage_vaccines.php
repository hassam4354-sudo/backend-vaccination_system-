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

$success_msg = '';
$error_msg = '';

// ===== ADD VACCINE =====
if(isset($_POST['add_vaccine'])) {
    $vaccine_name         = mysqli_real_escape_string($connection, trim($_POST['vaccine_name']));
    $vaccine_code         = strtoupper(mysqli_real_escape_string($connection, trim($_POST['vaccine_code'])));
    $description          = mysqli_real_escape_string($connection, trim($_POST['description']));
    $manufacturer         = mysqli_real_escape_string($connection, trim($_POST['manufacturer']));
    $scheduled_age        = mysqli_real_escape_string($connection, trim($_POST['scheduled_age']));
    $dosage_info          = mysqli_real_escape_string($connection, trim($_POST['dosage_info']));
    $storage_requirements = mysqli_real_escape_string($connection, trim($_POST['storage_requirements']));
    $side_effects         = mysqli_real_escape_string($connection, trim($_POST['side_effects']));

    $q = "INSERT INTO vaccines (vaccine_name, vaccine_code, description, manufacturer, scheduled_age, dosage_info, storage_requirements, side_effects, is_active, created_at, updated_at)
          VALUES ('$vaccine_name','$vaccine_code','$description','$manufacturer','$scheduled_age','$dosage_info','$storage_requirements','$side_effects', 1, NOW(), NOW())";
    if(mysqli_query($connection, $q)) {
        $success_msg = "Vaccine '<strong>$vaccine_name</strong>' successfully added!";
    } else {
        $error_msg = "Failed to add vaccine: " . mysqli_error($connection);
    }
}

// ===== EDIT VACCINE =====
if(isset($_POST['edit_vaccine'])) {
    $vaccine_id           = intval($_POST['vaccine_id']);
    $vaccine_name         = mysqli_real_escape_string($connection, trim($_POST['vaccine_name']));
    $vaccine_code         = strtoupper(mysqli_real_escape_string($connection, trim($_POST['vaccine_code'])));
    $description          = mysqli_real_escape_string($connection, trim($_POST['description']));
    $manufacturer         = mysqli_real_escape_string($connection, trim($_POST['manufacturer']));
    $scheduled_age        = mysqli_real_escape_string($connection, trim($_POST['scheduled_age']));
    $dosage_info          = mysqli_real_escape_string($connection, trim($_POST['dosage_info']));
    $storage_requirements = mysqli_real_escape_string($connection, trim($_POST['storage_requirements']));
    $side_effects         = mysqli_real_escape_string($connection, trim($_POST['side_effects']));

    $q = "UPDATE vaccines SET
            vaccine_name='$vaccine_name', vaccine_code='$vaccine_code',
            description='$description', manufacturer='$manufacturer',
            scheduled_age='$scheduled_age', dosage_info='$dosage_info',
            storage_requirements='$storage_requirements', side_effects='$side_effects',
            updated_at=NOW()
          WHERE vaccine_id='$vaccine_id'";
    if(mysqli_query($connection, $q)) {
        $success_msg = "Vaccine '<strong>$vaccine_name</strong>' updated successfully!";
    } else {
        $error_msg = "Failed to update: " . mysqli_error($connection);
    }
}

// ===== TOGGLE ACTIVE =====
if(isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $vid = intval($_GET['toggle']);
    mysqli_query($connection, "UPDATE vaccines SET is_active = NOT is_active, updated_at=NOW() WHERE vaccine_id='$vid'");
    header("location: manage_vaccines.php");
    exit();
}

// ===== DELETE VACCINE =====
if(isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $vid = intval($_GET['delete']);
    $used = mysqli_fetch_assoc(mysqli_query($connection, "SELECT COUNT(*) as c FROM appointment_requests WHERE vaccine_id='$vid'"))['c'];
    $used += mysqli_fetch_assoc(mysqli_query($connection, "SELECT COUNT(*) as c FROM vaccination_bookings WHERE vaccine_id='$vid'"))['c'];
    if($used > 0) {
        $error_msg = "Cannot delete: this vaccine is used in existing appointments or bookings.";
    } else {
        mysqli_query($connection, "DELETE FROM vaccines WHERE vaccine_id='$vid'");
        header("location: manage_vaccines.php");
        exit();
    }
}

// ===== FILTERS =====
$search = isset($_GET['search']) ? mysqli_real_escape_string($connection, trim($_GET['search'])) : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

$where = "WHERE 1=1";
if(!empty($search)) $where .= " AND (v.vaccine_name LIKE '%$search%' OR v.vaccine_code LIKE '%$search%' OR v.manufacturer LIKE '%$search%')";
if($filter == 'active')   $where .= " AND v.is_active = 1";
if($filter == 'inactive') $where .= " AND v.is_active = 0";

// Fetch vaccines with hospital inventory data
$result_vaccines = mysqli_query($connection,
    "SELECT v.*,
        COALESCE((SELECT SUM(hvi.quantity_available) FROM hospital_vaccine_inventory hvi WHERE hvi.vaccine_id = v.vaccine_id), 0) AS total_stock,
        COALESCE((SELECT COUNT(DISTINCT hvi.hospital_id) FROM hospital_vaccine_inventory hvi WHERE hvi.vaccine_id = v.vaccine_id AND hvi.is_available=1), 0) AS hospitals_count,
        COALESCE((SELECT COUNT(*) FROM vaccination_bookings vb WHERE vb.vaccine_id = v.vaccine_id), 0) AS total_bookings,
        COALESCE((SELECT COUNT(*) FROM vaccination_schedule vs WHERE vs.vaccine_id = v.vaccine_id), 0) AS total_doses
     FROM vaccines v
     $where
     ORDER BY v.vaccine_name ASC"
);

// ===== STATS =====
$r = mysqli_query($connection, "SELECT COUNT(*) as c FROM vaccines");
$total_vaccines  = $r ? mysqli_fetch_assoc($r)['c'] : 0;

$r = mysqli_query($connection, "SELECT COUNT(*) as c FROM vaccines WHERE is_active=1");
$active_vaccines = $r ? mysqli_fetch_assoc($r)['c'] : 0;

$r = mysqli_query($connection, "SELECT COUNT(DISTINCT vaccine_id) as c FROM hospital_vaccine_inventory WHERE is_available=1");
$in_stock = $r ? mysqli_fetch_assoc($r)['c'] : 0;

$r = mysqli_query($connection, "SELECT COUNT(*) as c FROM vaccination_bookings");
$total_bookings = $r ? mysqli_fetch_assoc($r)['c'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vaccine Inventory - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        * { margin:0; padding:0; box-sizing:border-box; font-family:'Inter',sans-serif; }

        :root {
            --primary:#2563eb; --primary-dark:#1d4ed8;
            --primary-soft:#dbeafe; --white:#ffffff; --white-off:#f8fafc;
            --gray-50:#f1f5f9; --gray-100:#e2e8f0;
            --gray-400:#64748b; --gray-500:#475569; --gray-600:#334155; --gray-700:#1e293b;
            --success:#10b981; --warning:#f59e0b; --danger:#ef4444;
            --shadow:0 4px 6px -1px rgba(0,0,0,0.05);
            --shadow-md:0 10px 15px -3px rgba(0,0,0,0.07);
            --radius:12px; --radius-lg:20px; --transition:all 0.2s ease;
        }
        body { background:linear-gradient(145deg,var(--white-off),var(--white)); min-height:100vh; color:var(--gray-600); }

        /* NAVBAR */
        .admin-navbar {
            background:#fff; border-bottom:2px solid #e8eeff;
            padding:0 35px; display:flex; justify-content:space-between;
            align-items:center; height:68px;
            box-shadow:0 2px 16px rgba(26,111,196,0.08);
            position:sticky; top:0; z-index:100;
        }
        .admin-navbar .logo { display:flex; align-items:center; gap:10px; }
        .admin-navbar .logo-icon {
            width:40px; height:40px;
            background:linear-gradient(135deg,#1a6fc4,#1155a0);
            border-radius:10px; display:flex; align-items:center;
            justify-content:center; font-size:20px; color:white;
        }
        .admin-navbar .logo h2 { font-size:20px; font-weight:700; color:#1155a0; }
        .nav-links { display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
        .nav-links a {
            color:#4b6cb7; text-decoration:none; padding:8px 14px;
            border-radius:8px; font-size:13.5px; font-weight:500;
            transition:all 0.2s; display:flex; align-items:center; gap:6px;
        }
        .nav-links a:hover { background:#eff6ff; color:#1155a0; }
        .nav-links a.active { background:#dbeafe; color:#1155a0; font-weight:600; }
        .nav-links a.logout { background:#fee2e2; color:#dc2626; }
        .nav-links a.logout:hover { background:#fecaca; }

        /* LAYOUT */
        .main-content { padding:30px; }
        .container { max-width:1400px; margin:0 auto; }

        /* PAGE HEADER */
        .page-header {
            background-image:url('https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?w=1800&q=85');
            background-size:cover; background-position:center;
            border-radius:18px; position:relative; overflow:hidden;
            padding:45px 50px 38px; margin-bottom:30px;
            box-shadow:0 8px 30px rgba(26,111,196,0.15);
            animation:fadeInDown 0.5s ease;
        }
        .page-header::before {
            content:''; position:absolute; inset:0;
            background:linear-gradient(135deg,rgba(26,111,196,0.85) 0%,rgba(17,85,160,0.78) 100%);
            z-index:1;
        }
        .page-header > * { position:relative; z-index:2; }
        .header-top { display:flex; justify-content:space-between; align-items:center; }
        .page-header h1 { color:white; font-size:26px; font-weight:700; margin-bottom:6px; display:flex; align-items:center; gap:12px; }
        .page-header p { color:rgba(255,255,255,0.8); font-size:14px; }
        .btn-add {
            background:white; color:var(--primary-dark);
            border:none; border-radius:var(--radius);
            padding:11px 22px; font-size:14px; font-weight:600;
            cursor:pointer; transition:var(--transition);
            display:flex; align-items:center; gap:8px; font-family:inherit;
        }
        .btn-add:hover { background:var(--primary-soft); transform:translateY(-1px); }

        /* ALERTS */
        .alert {
            padding:14px 20px; border-radius:var(--radius);
            margin-bottom:22px; font-size:14px;
            display:flex; align-items:center; gap:10px;
            animation:fadeInDown 0.4s ease;
        }
        .alert-success { background:#d1fae5; color:#065f46; border-left:4px solid var(--success); }
        .alert-error   { background:#fee2e2; color:#991b1b; border-left:4px solid var(--danger); }

        /* STATS */
        .stats-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:20px; margin-bottom:28px; }
        .stat-card {
            background:var(--white); border-radius:var(--radius-lg);
            padding:22px; box-shadow:var(--shadow); border:1px solid var(--gray-100);
            transition:var(--transition); display:flex; align-items:center; gap:18px;
            animation:scaleIn 0.5s ease both;
        }
        .stat-card:nth-child(1){animation-delay:.1s}
        .stat-card:nth-child(2){animation-delay:.15s}
        .stat-card:nth-child(3){animation-delay:.2s}
        .stat-card:nth-child(4){animation-delay:.25s}
        .stat-card:hover { transform:translateY(-3px); box-shadow:var(--shadow-md); }
        .stat-icon { width:52px; height:52px; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:22px; flex-shrink:0; }
        .si-blue   { background:var(--primary-soft); color:var(--primary); }
        .si-green  { background:#d1fae5; color:var(--success); }
        .si-yellow { background:#fef3c7; color:var(--warning); }
        .si-purple { background:#ede9fe; color:#7c3aed; }
        .stat-info h3 { font-size:28px; font-weight:700; color:var(--gray-700); line-height:1; }
        .stat-info p  { font-size:13px; color:var(--gray-400); margin-top:4px; }

        /* FILTER BAR */
        .filter-bar {
            background:var(--white); border-radius:var(--radius-lg);
            padding:18px 24px; margin-bottom:24px;
            box-shadow:var(--shadow); border:1px solid var(--gray-100);
            display:flex; align-items:center; gap:16px; flex-wrap:wrap;
        }
        .search-box { flex:1; min-width:220px; position:relative; }
        .search-box i { position:absolute; left:14px; top:50%; transform:translateY(-50%); color:var(--gray-400); font-size:14px; }
        .search-box input {
            width:100%; padding:10px 14px 10px 40px;
            border:1.5px solid var(--gray-100); border-radius:var(--radius);
            font-size:14px; font-family:inherit; color:var(--gray-600); transition:var(--transition);
        }
        .search-box input:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 3px var(--primary-soft); }
        .filter-tabs { display:flex; gap:6px; }
        .filter-tab {
            padding:8px 16px; border-radius:var(--radius);
            border:1.5px solid var(--gray-100); background:var(--white); color:var(--gray-500);
            font-size:13px; font-weight:500; cursor:pointer; text-decoration:none; transition:var(--transition);
        }
        .filter-tab:hover { border-color:var(--primary); color:var(--primary); }
        .filter-tab.active { background:var(--primary); border-color:var(--primary); color:white; }
        .btn { padding:10px 20px; border-radius:var(--radius); font-size:14px; font-weight:600; cursor:pointer; border:none; font-family:inherit; transition:var(--transition); display:inline-flex; align-items:center; gap:8px; }
        .btn-primary { background:linear-gradient(135deg,#3b8de0,var(--primary)); color:white; box-shadow:0 4px 15px rgba(37,99,235,0.25); }
        .btn-primary:hover { transform:translateY(-1px); }
        .btn-secondary { background:var(--gray-50); color:var(--gray-500); border:1px solid var(--gray-100); }
        .btn-secondary:hover { background:var(--gray-100); }

        /* TABLE */
        .table-card {
            background:var(--white); border-radius:var(--radius-lg);
            box-shadow:var(--shadow); border:1px solid var(--gray-100);
            overflow:hidden; animation:fadeIn 0.5s ease 0.2s both;
        }
        .table-card-header {
            padding:20px 24px; border-bottom:1px solid var(--gray-100);
            display:flex; align-items:center; justify-content:space-between;
        }
        .table-card-header h3 { font-size:16px; font-weight:600; color:var(--gray-700); display:flex; align-items:center; gap:10px; }
        .table-card-header h3 i { color:var(--primary); }
        .table-wrapper { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; font-size:14px; }
        thead th {
            background:var(--gray-50); padding:13px 18px;
            text-align:left; font-weight:600; color:var(--gray-500);
            font-size:11px; text-transform:uppercase; letter-spacing:0.5px;
            border-bottom:1px solid var(--gray-100);
        }
        tbody tr { border-bottom:1px solid var(--gray-50); transition:var(--transition); }
        tbody tr:hover { background:#f8faff; }
        tbody tr:last-child { border-bottom:none; }
        tbody td { padding:14px 18px; color:var(--gray-600); vertical-align:middle; }

        .vname { font-weight:600; color:var(--gray-700); }
        .vcode { display:inline-block; background:var(--primary-soft); color:var(--primary); font-size:11px; font-weight:600; padding:3px 9px; border-radius:20px; margin-left:8px; }
        .vtag  { display:inline-block; background:var(--gray-50); color:var(--gray-500); font-size:11px; padding:3px 9px; border-radius:20px; border:1px solid var(--gray-100); }
        .badge { padding:4px 12px; border-radius:20px; font-size:11px; font-weight:600; display:inline-flex; align-items:center; gap:5px; }
        .badge-active   { background:#d1fae5; color:#065f46; }
        .badge-inactive { background:#fee2e2; color:#991b1b; }
        .stock-pill { display:inline-flex; align-items:center; gap:6px; padding:5px 12px; border-radius:20px; font-size:12px; font-weight:600; }
        .stock-ok   { background:#d1fae5; color:#065f46; }
        .stock-none { background:#fee2e2; color:#991b1b; }
        .action-btns { display:flex; gap:7px; }
        .btn-icon {
            width:34px; height:34px; border-radius:8px;
            border:1px solid var(--gray-100); background:var(--white);
            color:var(--gray-400); display:flex; align-items:center;
            justify-content:center; cursor:pointer; transition:var(--transition);
            font-size:13px; text-decoration:none;
        }
        .btn-icon:hover { transform:translateY(-1px); box-shadow:var(--shadow); }
        .btn-icon.edit:hover   { border-color:var(--primary); color:var(--primary); background:var(--primary-soft); }
        .btn-icon.toggle:hover { border-color:var(--warning); color:var(--warning); background:#fef3c7; }
        .btn-icon.delete:hover { border-color:var(--danger);  color:var(--danger);  background:#fee2e2; }
        .btn-icon.view:hover   { border-color:#7c3aed; color:#7c3aed; background:#ede9fe; }
        .empty-state { text-align:center; padding:60px 20px; color:var(--gray-400); }
        .empty-state i { font-size:48px; margin-bottom:16px; color:var(--gray-200); display:block; }
        .empty-state h3 { font-size:18px; color:var(--gray-500); margin-bottom:8px; }

        /* MODAL */
        .modal-overlay {
            display:none; position:fixed; inset:0;
            background:rgba(0,0,0,0.45); z-index:1000;
            align-items:center; justify-content:center;
            backdrop-filter:blur(4px);
        }
        .modal-overlay.active { display:flex; }
        .modal {
            background:var(--white); border-radius:20px;
            width:100%; max-width:620px; max-height:92vh; overflow-y:auto;
            box-shadow:0 25px 60px rgba(0,0,0,0.2);
            animation:scaleIn 0.3s ease;
        }
        .modal-header {
            padding:22px 28px; border-bottom:1px solid var(--gray-100);
            display:flex; align-items:center; justify-content:space-between;
            position:sticky; top:0; background:white; z-index:2;
        }
        .modal-header h3 { font-size:18px; font-weight:700; color:var(--gray-700); display:flex; align-items:center; gap:10px; }
        .modal-header h3 i { color:var(--primary); }
        .close-btn { width:34px; height:34px; border-radius:8px; background:var(--gray-50); border:none; cursor:pointer; font-size:18px; color:var(--gray-400); transition:var(--transition); }
        .close-btn:hover { background:var(--gray-100); }
        .modal-body { padding:26px 28px; }
        .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:18px; }
        .form-group { display:flex; flex-direction:column; gap:7px; }
        .form-group.full { grid-column:1/-1; }
        .form-group label { font-size:13px; font-weight:600; color:var(--gray-600); }
        .form-group input,
        .form-group textarea {
            padding:10px 14px; border:1.5px solid var(--gray-100);
            border-radius:var(--radius); font-size:14px; font-family:inherit;
            color:var(--gray-700); transition:var(--transition);
        }
        .form-group input:focus,
        .form-group textarea:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 3px var(--primary-soft); }
        .form-group textarea { resize:vertical; min-height:75px; }
        .modal-footer { padding:18px 28px; border-top:1px solid var(--gray-100); display:flex; justify-content:flex-end; gap:12px; }
        .view-modal { max-width:540px; }
        .view-row { display:flex; gap:10px; padding:12px 0; border-bottom:1px solid var(--gray-50); }
        .view-row:last-child { border-bottom:none; }
        .view-label { font-size:12px; font-weight:600; color:var(--gray-400); text-transform:uppercase; letter-spacing:0.4px; min-width:130px; padding-top:2px; }
        .view-value { font-size:14px; color:var(--gray-700); flex:1; }

        /* ANIMATIONS */
        @keyframes fadeIn     { from{opacity:0} to{opacity:1} }
        @keyframes fadeInDown { from{opacity:0;transform:translateY(-10px)} to{opacity:1;transform:translateY(0)} }
        @keyframes scaleIn    { from{opacity:0;transform:scale(0.95)} to{opacity:1;transform:scale(1)} }

        /* RESPONSIVE */
        @media (max-width:1024px) { .stats-grid { grid-template-columns:repeat(2,1fr); } }
        @media (max-width:768px)  {
            .main-content { padding:20px; }
            .stats-grid { grid-template-columns:1fr; }
            .filter-bar { flex-direction:column; align-items:stretch; }
            .form-grid { grid-template-columns:1fr; }
            .nav-links a { padding:6px 10px; font-size:12.5px; }
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="admin-navbar">
    <div class="logo">
        <div class="logo-icon"><i class="fas fa-shield-alt"></i></div>
        <h2>Admin Panel</h2>
    </div>
    <div class="nav-links">
        <a href="dashboard.php"> Dashboard</a>
        <a href="manage_children.php"> Children</a>
        <a href="manage_hospitals.php"> Hospitals</a>
        <a href="appointment_requests.php"> Requests</a>
        <a href="manage_vaccines.php" class="active"> Vaccines</a>
        <a href="bookingdetail.php"> Bookings</a>
        <a href="vaccination_reports.php"> Reports</a>
        <a href="system_settings.php"> Settings</a>
        <a href="../logout.php" class="logout"> Logout</a>
    </div>
</nav>

<div class="main-content">
<div class="container">

    <!-- PAGE HEADER -->
    <div class="page-header">
        <div class="header-top">
            <div>
                <h1><i class="fas fa-syringe"></i> Vaccine Inventory</h1>
                <p>Manage vaccines, view hospital stock levels and track vaccination usage</p>
            </div>
            <button class="btn-add" onclick="openAddModal()">
                <i class="fas fa-plus"></i> Add New Vaccine
            </button>
        </div>
    </div>

    <!-- ALERTS -->
    <?php if($success_msg): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success_msg; ?></div>
    <?php endif; ?>
    <?php if($error_msg): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_msg); ?></div>
    <?php endif; ?>

    <!-- STATS -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon si-blue"><i class="fas fa-syringe"></i></div>
            <div class="stat-info"><h3><?php echo $total_vaccines; ?></h3><p>Total Vaccines</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon si-green"><i class="fas fa-check-circle"></i></div>
            <div class="stat-info"><h3><?php echo $active_vaccines; ?></h3><p>Active Vaccines</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon si-yellow"><i class="fas fa-hospital"></i></div>
            <div class="stat-info"><h3><?php echo $in_stock; ?></h3><p>Available in Hospitals</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon si-purple"><i class="fas fa-calendar-check"></i></div>
            <div class="stat-info"><h3><?php echo $total_bookings; ?></h3><p>Total Bookings</p></div>
        </div>
    </div>

    <!-- FILTER BAR -->
    <form method="GET" class="filter-bar">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" name="search" placeholder="Search by name, code or manufacturer..."
                   value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <div class="filter-tabs">
            <a href="?filter=all"      class="filter-tab <?php echo $filter=='all'     ?'active':''; ?>">All</a>
            <a href="?filter=active"   class="filter-tab <?php echo $filter=='active'  ?'active':''; ?>">Active</a>
            <a href="?filter=inactive" class="filter-tab <?php echo $filter=='inactive'?'active':''; ?>">Inactive</a>
        </div>
        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
    </form>

    <!-- TABLE -->
    <div class="table-card">
        <div class="table-card-header">
            <h3><i class="fas fa-list"></i> Vaccine List</h3>
            <span style="font-size:13px;color:var(--gray-400);"><?php echo mysqli_num_rows($result_vaccines); ?> vaccines found</span>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Vaccine Name</th>
                        <th>Manufacturer</th>
                        <th>Scheduled Age</th>
                        <th>Dosage</th>
                        <th>Schedule Doses</th>
                        <th>Hospital Stock</th>
                        <th>Bookings</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                if($result_vaccines && mysqli_num_rows($result_vaccines) > 0):
                    $i = 1;
                    while($v = mysqli_fetch_assoc($result_vaccines)):
                        $total_stock = intval($v['total_stock']);
                        $hosp_count  = intval($v['hospitals_count']);
                        $total_bk    = intval($v['total_bookings']);
                        $total_doses = intval($v['total_doses']);
                ?>
                <tr>
                    <td style="color:var(--gray-400);font-size:13px;"><?php echo $i++; ?></td>
                    <td>
                        <span class="vname"><?php echo htmlspecialchars($v['vaccine_name']); ?></span>
                        <?php if(!empty($v['vaccine_code'])): ?>
                            <span class="vcode"><?php echo htmlspecialchars($v['vaccine_code']); ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo !empty($v['manufacturer']) ? htmlspecialchars($v['manufacturer']) : '<span style="color:var(--gray-300)">—</span>'; ?></td>
                    <td>
                        <?php if(!empty($v['scheduled_age'])): ?>
                            <span class="vtag"><?php echo htmlspecialchars($v['scheduled_age']); ?></span>
                        <?php else: echo '<span style="color:var(--gray-300)">—</span>'; endif; ?>
                    </td>
                    <td style="font-size:13px;">
                        <?php echo !empty($v['dosage_info']) ? htmlspecialchars($v['dosage_info']) : '<span style="color:var(--gray-300)">—</span>'; ?>
                    </td>
                    <td style="text-align:center;">
                        <span style="font-weight:600;color:var(--gray-700);"><?php echo $total_doses; ?></span>
                        <span style="font-size:12px;color:var(--gray-400);"> dose<?php echo $total_doses!=1?'s':''; ?></span>
                    </td>
                    <td>
                        <?php if($total_stock > 0): ?>
                            <span class="stock-pill stock-ok">
                                <i class="fas fa-cubes"></i>
                                <?php echo number_format($total_stock); ?> units
                                <?php if($hosp_count > 0): ?>&nbsp;·&nbsp; <?php echo $hosp_count; ?> hosp.<?php endif; ?>
                            </span>
                        <?php else: ?>
                            <span class="stock-pill stock-none"><i class="fas fa-times-circle"></i> Not stocked</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center;font-weight:600;color:var(--gray-700);"><?php echo $total_bk; ?></td>
                    <td>
                        <span class="badge <?php echo $v['is_active'] ? 'badge-active':'badge-inactive'; ?>">
                            <i class="fas fa-circle" style="font-size:7px;"></i>
                            <?php echo $v['is_active'] ? 'Active':'Inactive'; ?>
                        </span>
                    </td>
                    <td>
                        <div class="action-btns">
                            <a href="#" class="btn-icon view" title="View Details"
                               onclick="openViewModal(<?php echo htmlspecialchars(json_encode($v), ENT_QUOTES); ?>); return false;">
                                <i class="fas fa-eye"></i>
                            </a>
                            <a href="#" class="btn-icon edit" title="Edit"
                               onclick="openEditModal(<?php echo htmlspecialchars(json_encode($v), ENT_QUOTES); ?>); return false;">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="?toggle=<?php echo $v['vaccine_id']; ?>&filter=<?php echo $filter; ?><?php echo !empty($search)?'&search='.urlencode($search):''; ?>"
                               class="btn-icon toggle"
                               title="<?php echo $v['is_active']?'Deactivate':'Activate'; ?>"
                               onclick="return confirm('<?php echo $v['is_active']?'Deactivate':'Activate'; ?> this vaccine?')">
                                <i class="fas fa-power-off"></i>
                            </a>
                            <a href="?delete=<?php echo $v['vaccine_id']; ?>" class="btn-icon delete" title="Delete"
                               onclick="return confirm('Delete this vaccine? This cannot be undone.')">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr>
                    <td colspan="10">
                        <div class="empty-state">
                            <i class="fas fa-syringe"></i>
                            <h3>No vaccines found</h3>
                            <p>Add your first vaccine using the button above.</p>
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</div>

<!-- ADD / EDIT MODAL -->
<div class="modal-overlay" id="vaccineModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modalTitle"><i class="fas fa-syringe"></i> Add New Vaccine</h3>
            <button class="close-btn" onclick="closeModal('vaccineModal')">×</button>
        </div>
        <form method="POST" id="vaccineForm">
            <input type="hidden" name="vaccine_id" id="f_id">
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Vaccine Name <span style="color:red">*</span></label>
                        <input type="text" name="vaccine_name" id="f_name" placeholder="e.g. BCG, OPV" required>
                    </div>
                    <div class="form-group">
                        <label>Vaccine Code</label>
                        <input type="text" name="vaccine_code" id="f_code" placeholder="e.g. BCG-01">
                    </div>
                    <div class="form-group">
                        <label>Manufacturer</label>
                        <input type="text" name="manufacturer" id="f_mfr" placeholder="e.g. Serum Institute">
                    </div>
                    <div class="form-group">
                        <label>Scheduled Age</label>
                        <input type="text" name="scheduled_age" id="f_age" placeholder="e.g. At Birth, 6 weeks">
                    </div>
                    <div class="form-group">
                        <label>Dosage Info</label>
                        <input type="text" name="dosage_info" id="f_dosage" placeholder="e.g. 0.5ml IM">
                    </div>
                    <div class="form-group">
                        <label>Storage Requirements</label>
                        <input type="text" name="storage_requirements" id="f_storage" placeholder="e.g. 2-8°C refrigerated">
                    </div>
                    <div class="form-group full">
                        <label>Description</label>
                        <textarea name="description" id="f_desc" placeholder="Brief description of the vaccine..."></textarea>
                    </div>
                    <div class="form-group full">
                        <label>Side Effects</label>
                        <textarea name="side_effects" id="f_side" placeholder="Known side effects (optional)..."></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('vaccineModal')">Cancel</button>
                <button type="submit" name="add_vaccine" id="submitBtn" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Vaccine
                </button>
            </div>
        </form>
    </div>
</div>

<!-- VIEW MODAL -->
<div class="modal-overlay" id="viewModal">
    <div class="modal view-modal">
        <div class="modal-header">
            <h3 id="viewTitle"><i class="fas fa-eye"></i> Vaccine Details</h3>
            <button class="close-btn" onclick="closeModal('viewModal')">×</button>
        </div>
        <div class="modal-body" id="viewBody"></div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('viewModal')">Close</button>
        </div>
    </div>
</div>

<script>
    function openAddModal() {
        document.getElementById('modalTitle').innerHTML = '<i class="fas fa-syringe"></i> Add New Vaccine';
        document.getElementById('vaccineForm').reset();
        document.getElementById('f_id').value = '';
        document.getElementById('submitBtn').name = 'add_vaccine';
        document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> Save Vaccine';
        document.getElementById('vaccineModal').classList.add('active');
    }

    function openEditModal(v) {
        document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Vaccine';
        document.getElementById('f_id').value      = v.vaccine_id            || '';
        document.getElementById('f_name').value    = v.vaccine_name          || '';
        document.getElementById('f_code').value    = v.vaccine_code          || '';
        document.getElementById('f_mfr').value     = v.manufacturer          || '';
        document.getElementById('f_age').value     = v.scheduled_age         || '';
        document.getElementById('f_dosage').value  = v.dosage_info           || '';
        document.getElementById('f_storage').value = v.storage_requirements  || '';
        document.getElementById('f_desc').value    = v.description           || '';
        document.getElementById('f_side').value    = v.side_effects          || '';
        document.getElementById('submitBtn').name  = 'edit_vaccine';
        document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i> Update Vaccine';
        document.getElementById('vaccineModal').classList.add('active');
    }

    function openViewModal(v) {
        document.getElementById('viewTitle').innerHTML = '<i class="fas fa-eye"></i> ' + (v.vaccine_name || 'Vaccine Details');
        const na = '<span style="color:#94a3b8">—</span>';
        const rows = [
            ['Vaccine Name',   v.vaccine_name  || na],
            ['Vaccine Code',   v.vaccine_code  || na],
            ['Manufacturer',   v.manufacturer  || na],
            ['Scheduled Age',  v.scheduled_age || na],
            ['Dosage Info',    v.dosage_info   || na],
            ['Storage',        v.storage_requirements || na],
            ['Description',    v.description   || na],
            ['Side Effects',   v.side_effects  || na],
            ['Status',         v.is_active == 1
                ? '<span class="badge badge-active"><i class="fas fa-circle" style="font-size:7px"></i> Active</span>'
                : '<span class="badge badge-inactive"><i class="fas fa-circle" style="font-size:7px"></i> Inactive</span>'],
            ['Added On',       v.created_at    || na],
        ];
        document.getElementById('viewBody').innerHTML = rows.map(r =>
            `<div class="view-row"><div class="view-label">${r[0]}</div><div class="view-value">${r[1]}</div></div>`
        ).join('');
        document.getElementById('viewModal').classList.add('active');
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('active');
    }

    document.querySelectorAll('.modal-overlay').forEach(m => {
        m.addEventListener('click', e => { if(e.target === m) m.classList.remove('active'); });
    });

    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(a => {
            a.style.transition = 'opacity 0.5s';
            a.style.opacity = '0';
            setTimeout(() => a.remove(), 500);
        });
    }, 4000);
</script>
</body>
</html>