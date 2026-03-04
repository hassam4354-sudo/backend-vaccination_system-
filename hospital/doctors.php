<?php
session_start();
if(!isset($_SESSION["logged_in"]) || $_SESSION["user_type"] != "hospital"){
    header("Location: ../login.php");
    exit();
}

include("../dbconnection.php");

$user_id = $_SESSION["user_id"];
$success_msg = "";
$error_msg = "";

// Get hospital data
$query_hospital = "SELECT * FROM hospitals WHERE user_id = '$user_id'";
$result_hospital = mysqli_query($connection, $query_hospital);
$hospital = mysqli_fetch_assoc($result_hospital);
$hospital_id = $hospital['hospital_id'] ?? 0;
$is_verified = $hospital['is_verified'] ?? 0;
$is_active   = $hospital['is_active']   ?? 0;

// ── ADD DOCTOR ──
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_doctor'])) {
    $full_name      = mysqli_real_escape_string($connection, trim($_POST['full_name']));
    $specialization = mysqli_real_escape_string($connection, trim($_POST['specialization']));

    $errors = [];
    if(empty($full_name))      $errors[] = "Doctor ka naam zaroori hai.";
    if(empty($specialization)) $errors[] = "Specialization zaroori hai.";

    // Duplicate check
    if(empty($errors)) {
        $dup = mysqli_query($connection,
            "SELECT doctor_id FROM doctors WHERE full_name='$full_name' AND hospital_id='$hospital_id'");
        if(mysqli_num_rows($dup) > 0) $errors[] = "Yeh doctor pehle se add hai.";
    }

    if(empty($errors)) {
        $ins = mysqli_query($connection,
            "INSERT INTO doctors (full_name, specialization, hospital_id, is_active, created_at)
             VALUES ('$full_name','$specialization','$hospital_id',1,NOW())");
        if($ins) {
            $_SESSION['msg']      = "✅ Doctor successfully add ho gaya!";
            $_SESSION['msg_type'] = "success";
            header("Location: doctors.php");
            exit();
        } else {
            $error_msg = "❌ Error: " . mysqli_error($connection);
        }
    } else {
        $error_msg = implode("<br>", $errors);
    }
}

// ── EDIT DOCTOR ──
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_doctor'])) {
    $edit_id        = intval($_POST['doctor_id']);
    $full_name      = mysqli_real_escape_string($connection, trim($_POST['full_name']));
    $specialization = mysqli_real_escape_string($connection, trim($_POST['specialization']));
    $is_active_val  = isset($_POST['is_active']) ? 1 : 0;

    $errors = [];
    if(empty($full_name))      $errors[] = "Doctor ka naam zaroori hai.";
    if(empty($specialization)) $errors[] = "Specialization zaroori hai.";

    if(empty($errors)) {
        $upd = mysqli_query($connection,
            "UPDATE doctors SET full_name='$full_name', specialization='$specialization',
             is_active='$is_active_val' WHERE doctor_id='$edit_id' AND hospital_id='$hospital_id'");
        if($upd) {
            $_SESSION['msg']      = "✅ Doctor info update ho gayi!";
            $_SESSION['msg_type'] = "success";
            header("Location: doctors.php");
            exit();
        } else {
            $error_msg = "❌ Update error: " . mysqli_error($connection);
        }
    } else {
        $error_msg = implode("<br>", $errors);
    }
}

// ── DELETE DOCTOR ──
if(isset($_GET['delete']) && isset($_GET['id'])) {
    $del_id = intval($_GET['id']);
    $chk = mysqli_query($connection,
        "SELECT doctor_id FROM doctors WHERE doctor_id='$del_id' AND hospital_id='$hospital_id'");
    if(mysqli_num_rows($chk) > 0) {
        $del = mysqli_query($connection,
            "DELETE FROM doctors WHERE doctor_id='$del_id' AND hospital_id='$hospital_id'");
        if($del) {
            $_SESSION['msg']      = "✅ Doctor delete ho gaya!";
            $_SESSION['msg_type'] = "error";
            header("Location: doctors.php");
            exit();
        }
    }
}

// ── TOGGLE ACTIVE ──
if(isset($_GET['toggle']) && isset($_GET['id'])) {
    $tog_id = intval($_GET['id']);
    $cur = mysqli_fetch_assoc(mysqli_query($connection,
        "SELECT is_active FROM doctors WHERE doctor_id='$tog_id' AND hospital_id='$hospital_id'"));
    if($cur) {
        $new_val = $cur['is_active'] ? 0 : 1;
        mysqli_query($connection,
            "UPDATE doctors SET is_active='$new_val' WHERE doctor_id='$tog_id' AND hospital_id='$hospital_id'");
        $_SESSION['msg']      = $new_val ? "✅ Doctor active kar diya!" : "⚠️ Doctor inactive kar diya!";
        $_SESSION['msg_type'] = $new_val ? "success" : "warning";
        header("Location: doctors.php");
        exit();
    }
}

// ── FLASH MESSAGE ──
$flash_msg  = isset($_SESSION['msg'])      ? $_SESSION['msg']      : '';
$flash_type = isset($_SESSION['msg_type']) ? $_SESSION['msg_type'] : '';
unset($_SESSION['msg'], $_SESSION['msg_type']);

// ── SEARCH / FILTER ──
$search        = isset($_GET['search'])        ? mysqli_real_escape_string($connection, $_GET['search'])        : '';
$filter_status = isset($_GET['filter_status']) ? mysqli_real_escape_string($connection, $_GET['filter_status']) : 'all';

$where = "hospital_id = '$hospital_id'";
if($search)                      $where .= " AND full_name LIKE '%$search%'";
if($filter_status === 'active')  $where .= " AND is_active = 1";
if($filter_status === 'inactive')$where .= " AND is_active = 0";

// ── COUNTS ──
$total_doctors   = mysqli_fetch_assoc(mysqli_query($connection,
    "SELECT COUNT(*) as cnt FROM doctors WHERE hospital_id='$hospital_id'"))['cnt'];
$active_doctors  = mysqli_fetch_assoc(mysqli_query($connection,
    "SELECT COUNT(*) as cnt FROM doctors WHERE hospital_id='$hospital_id' AND is_active=1"))['cnt'];
$inactive_doctors= mysqli_fetch_assoc(mysqli_query($connection,
    "SELECT COUNT(*) as cnt FROM doctors WHERE hospital_id='$hospital_id' AND is_active=0"))['cnt'];

// ── MAIN QUERY ──
$result  = mysqli_query($connection,
    "SELECT * FROM doctors WHERE $where ORDER BY is_active DESC, full_name ASC");
$total_records = mysqli_num_rows($result);

// ── FETCH SINGLE DOCTOR FOR EDIT ──
$edit_doctor = null;
if(isset($_GET['edit'])) {
    $edit_doctor = mysqli_fetch_assoc(mysqli_query($connection,
        "SELECT * FROM doctors WHERE doctor_id='" . intval($_GET['edit']) . "' AND hospital_id='$hospital_id'"));
}

// ── PENDING REQUESTS COUNT (for navbar badge) ──
$pending_count = mysqli_fetch_assoc(mysqli_query($connection,
    "SELECT COUNT(*) as cnt FROM appointment_requests WHERE hospital_id='$hospital_id' AND request_status='pending'"))['cnt'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctors — VacciCare</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        :root {
            --blue-900:#0a1628; --blue-700:#1a3a6e; --blue-600:#1e4db7;
            --blue-500:#2563eb; --blue-400:#3b82f6; --blue-100:#dbeafe; --blue-50:#eff6ff;
            --gray-50:#f8fafc; --gray-100:#f1f5f9; --gray-200:#e2e8f0;
            --gray-400:#94a3b8; --gray-500:#64748b; --gray-700:#334155;
            --white:#ffffff; --bg:#f0f4ff;
            --green-100:#dcfce7; --green-600:#16a34a;
            --red-100:#fee2e2;   --red-600:#dc2626;
            --yellow-100:#fef9c3;--yellow-600:#ca8a04;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Plus Jakarta Sans',sans-serif; background:var(--bg); color:var(--blue-900); min-height:100vh; }

        /* ── NAVBAR ── */
        .navbar {
            position:fixed; top:0; left:0; right:0; z-index:200;
            background:rgba(255,255,255,0.97); backdrop-filter:blur(18px);
            border-bottom:1px solid var(--gray-200);
            padding:0 40px; height:68px;
            display:flex; justify-content:space-between; align-items:center;
            box-shadow:0 2px 12px rgba(37,99,235,0.08);
        }
        .nav-logo { display:flex; align-items:center; gap:12px; font-weight:800; font-size:18px; color:var(--blue-700); text-decoration:none; }
        .nav-logo .logo-icon { width:40px; height:40px; background:var(--blue-500); border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:20px; box-shadow:0 4px 14px rgba(37,99,235,.35); }
        .nav-links { display:flex; align-items:center; gap:4px; }
        .nav-link { display:flex; align-items:center; gap:7px; padding:8px 14px; border-radius:8px; color:var(--gray-700); text-decoration:none; font-size:13.5px; font-weight:600; transition:all .2s; }
        .nav-link:hover,.nav-link.active { background:var(--blue-50); color:var(--blue-500); }
        .nav-right { display:flex; align-items:center; gap:12px; }
        .nav-badge { background:var(--red-600); color:#fff; font-size:10px; font-weight:700; padding:2px 6px; border-radius:20px; margin-left:4px; }
        .nav-logout { padding:8px 16px; background:var(--red-100); color:var(--red-600); border-radius:8px; text-decoration:none; font-size:13px; font-weight:700; transition:all .2s; }
        .nav-logout:hover { background:#fecaca; }
        .hamburger { display:none; background:none; border:none; cursor:pointer; font-size:22px; }
        .mobile-menu { display:none; }

        /* ── MAIN ── */
        .main { padding-top:88px; padding-bottom:40px; }
        .verify-banner { padding:10px 40px; font-size:13px; font-weight:600; }
        .verify-banner.verified  { background:#dcfce7; color:#166534; }
        .verify-banner.pending   { background:#fef9c3; color:#92400e; }
        .verify-banner.inactive  { background:#fee2e2; color:#991b1b; }
        .content { max-width:1200px; margin:0 auto; padding:24px 40px; }

        /* ── PAGE HEADER ── */
        .page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
        .page-header h1 { font-size:22px; font-weight:800; color:var(--blue-900); }
        .btn-add { display:flex; align-items:center; gap:8px; background:var(--blue-500); color:#fff; padding:10px 20px; border-radius:10px; font-size:14px; font-weight:700; border:none; cursor:pointer; transition:all .2s; text-decoration:none; }
        .btn-add:hover { background:var(--blue-600); transform:translateY(-1px); }

        /* ── STATS ── */
        .stats-grid { display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-bottom:24px; }
        .stat-card { background:#fff; border-radius:14px; padding:20px 24px; border:1px solid var(--gray-200); display:flex; align-items:center; gap:16px; }
        .stat-icon { width:48px; height:48px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:22px; }
        .si-total    { background:var(--blue-50); }
        .si-active   { background:var(--green-100); }
        .si-inactive { background:var(--red-100); }
        .stat-num  { font-size:26px; font-weight:800; color:var(--blue-900); }
        .stat-label{ font-size:12px; color:var(--gray-500); font-weight:600; margin-top:2px; }

        /* ── FILTER BAR ── */
        .filter-bar { background:#fff; border-radius:12px; padding:16px 20px; border:1px solid var(--gray-200); margin-bottom:20px; display:flex; gap:12px; flex-wrap:wrap; align-items:center; }
        .filter-bar input, .filter-bar select { padding:9px 14px; border:1.5px solid var(--gray-200); border-radius:8px; font-size:13px; font-family:inherit; color:var(--blue-900); background:var(--gray-50); outline:none; transition:all .2s; }
        .filter-bar input:focus, .filter-bar select:focus { border-color:var(--blue-400); background:#fff; }
        .filter-bar input { flex:1; min-width:200px; }
        .filter-btn { padding:9px 18px; background:var(--blue-500); color:#fff; border:none; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; font-family:inherit; transition:all .2s; }
        .filter-btn:hover { background:var(--blue-600); }
        .reset-btn { padding:9px 14px; background:var(--gray-100); color:var(--gray-700); border-radius:8px; text-decoration:none; font-size:13px; font-weight:600; transition:all .2s; }
        .reset-btn:hover { background:var(--gray-200); }

        /* ── TABLE ── */
        .table-wrap { background:#fff; border-radius:14px; border:1px solid var(--gray-200); overflow:hidden; }
        .table-header { padding:18px 24px; display:flex; align-items:center; justify-content:space-between; border-bottom:1px solid var(--gray-100); }
        .table-title { font-size:15px; font-weight:800; color:var(--blue-900); }
        .table-count { font-size:12px; color:var(--gray-500); background:var(--gray-100); padding:4px 10px; border-radius:20px; font-weight:600; }
        table { width:100%; border-collapse:collapse; }
        thead tr { background:var(--gray-50); }
        th { padding:12px 16px; text-align:left; font-size:11.5px; font-weight:700; color:var(--gray-500); text-transform:uppercase; letter-spacing:.5px; }
        td { padding:14px 16px; border-bottom:1px solid var(--gray-100); font-size:13.5px; }
        tr:last-child td { border-bottom:none; }
        tr:hover td { background:var(--blue-50); }

        .doctor-name { font-weight:700; color:var(--blue-900); font-size:14px; }
        .doctor-spec { font-size:12px; color:var(--gray-500); margin-top:2px; }
        .doctor-id   { font-size:11px; color:var(--gray-400); margin-top:2px; }

        .badge-active   { background:var(--green-100); color:var(--green-600); padding:4px 10px; border-radius:20px; font-size:11.5px; font-weight:700; }
        .badge-inactive { background:var(--red-100);   color:var(--red-600);   padding:4px 10px; border-radius:20px; font-size:11.5px; font-weight:700; }

        /* ── ACTION BUTTONS ── */
        .action-btns { display:flex; gap:6px; flex-wrap:wrap; }
        .btn-edit   { padding:6px 12px; background:var(--blue-50);  color:var(--blue-500);  border-radius:7px; font-size:12px; font-weight:700; border:1px solid var(--blue-100); cursor:pointer; text-decoration:none; transition:all .2s; }
        .btn-edit:hover { background:var(--blue-100); }
        .btn-toggle { padding:6px 12px; background:var(--yellow-100); color:var(--yellow-600); border-radius:7px; font-size:12px; font-weight:700; border:1px solid #fde68a; cursor:pointer; text-decoration:none; transition:all .2s; }
        .btn-toggle:hover { background:#fde68a; }
        .btn-delete { padding:6px 12px; background:var(--red-100); color:var(--red-600); border-radius:7px; font-size:12px; font-weight:700; border:1px solid #fecaca; cursor:pointer; transition:all .2s; }
        .btn-delete:hover { background:#fecaca; }

        /* ── EMPTY STATE ── */
        .empty-state { text-align:center; padding:60px 30px; }
        .e-icon { font-size:52px; display:block; margin-bottom:14px; }
        .empty-state h3 { font-size:18px; font-weight:800; margin-bottom:8px; }
        .empty-state p  { color:var(--gray-500); font-size:14px; margin-bottom:20px; }

        /* ── MODAL ── */
        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:999; align-items:center; justify-content:center; }
        .modal-overlay.open { display:flex; }
        .modal { background:#fff; border-radius:16px; padding:32px; width:100%; max-width:500px; box-shadow:0 20px 60px rgba(0,0,0,0.2); }
        .modal h2 { font-size:18px; font-weight:800; margin-bottom:6px; color:var(--blue-900); }
        .modal p  { font-size:13px; color:var(--gray-500); margin-bottom:20px; }
        .form-group { margin-bottom:16px; }
        .form-group label { display:block; font-size:13px; font-weight:600; color:var(--gray-700); margin-bottom:6px; }
        .form-group input, .form-group select { width:100%; padding:10px 14px; border:1.5px solid var(--gray-200); border-radius:9px; font-size:14px; font-family:inherit; color:var(--blue-900); outline:none; transition:all .2s; background:var(--gray-50); }
        .form-group input:focus, .form-group select:focus { border-color:var(--blue-400); background:#fff; }
        .toggle-wrap { display:flex; align-items:center; gap:10px; margin-top:4px; }
        .toggle-switch { position:relative; display:inline-block; width:44px; height:24px; }
        .toggle-switch input { opacity:0; width:0; height:0; }
        .toggle-slider { position:absolute; inset:0; background:#cbd5e1; border-radius:24px; cursor:pointer; transition:.3s; }
        .toggle-slider:before { content:''; position:absolute; height:18px; width:18px; left:3px; bottom:3px; background:#fff; border-radius:50%; transition:.3s; }
        input:checked + .toggle-slider { background:var(--blue-500); }
        input:checked + .toggle-slider:before { transform:translateX(20px); }
        .modal-actions { display:flex; gap:10px; margin-top:20px; justify-content:flex-end; }
        .modal-btn { padding:10px 22px; border-radius:9px; font-size:13px; font-weight:700; cursor:pointer; border:none; font-family:inherit; transition:all .2s; }
        .modal-btn-cancel  { background:var(--gray-100); color:var(--gray-700); }
        .modal-btn-cancel:hover { background:var(--gray-200); }
        .modal-btn-submit  { background:var(--blue-500); color:#fff; }
        .modal-btn-submit:hover { background:var(--blue-600); }
        .modal-btn-delete  { background:var(--red-600); color:#fff; }
        .modal-btn-delete:hover { background:#b91c1c; }

        .alert { padding:12px 18px; border-radius:10px; margin-bottom:18px; font-size:13.5px; font-weight:600; }
        .alert-success { background:var(--green-100); border:1px solid #86efac; color:#166534; }
        .alert-error   { background:var(--red-100);   border:1px solid #fca5a5; color:#991b1b; }

        @media(max-width:768px){
            .navbar { padding:0 16px; }
            .nav-links { display:none; }
            .hamburger { display:block; }
            .mobile-menu.open { display:flex; flex-direction:column; position:fixed; top:68px; left:0; right:0; background:#fff; border-bottom:1px solid var(--gray-200); padding:12px; z-index:199; gap:4px; }
            .content { padding:16px; }
            .stats-grid { grid-template-columns:1fr; }
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<div class="navbar">
    <a href="dashboard.php" class="nav-logo">
       
        Hospital Panel
    </a>
    <div class="nav-links">
        <a href="dashboard.php"          class="nav-link"> Dashboard</a>
        <a href="appointment_requests.php" class="nav-link">
             Requests
            <?php if($pending_count > 0): ?>
            <span class="nav-badge"><?php echo $pending_count; ?></span>
            <?php endif; ?>
        </a>
        <a href="todays_schedule.php"    class="nav-link"> Bookings</a>
        <a href="vaccine_inventory.php"  class="nav-link"> Inventory</a>
        <a href="doctors.php"            class="nav-link active"> Doctors</a>
          <a href="vaccination_records.php"  class="nav-link">Records</a>
            <a href="my_profile.php" class="nav-link">
     Profile
        </a>
    </div>
    <div class="nav-right">
        <div style="font-size:13px; font-weight:600; color:var(--blue-700);">
             <?php echo htmlspecialchars($hospital['hospital_name'] ?? 'Hospital'); ?>
        </div>
        <a href="../logout.php" class="nav-logout"> Logout</a>
        <button class="hamburger" onclick="toggleMenu()">☰</button>
    </div>
</div>

<!-- MOBILE MENU -->
<div class="mobile-menu" id="mobileMenu">
    <a href="dashboard.php"          class="nav-link">📊 Dashboard</a>
    <a href="appointment_requests.php" class="nav-link">📋 Requests <?php if($pending_count>0): ?><span class="nav-badge"><?php echo $pending_count;?></span><?php endif;?></a>
    <a href="todays_schedule.php"    class="nav-link">📅 Bookings</a>
    <a href="vaccine_inventory.php"  class="nav-link">💉 Inventory</a>
    <a href="doctors.php"            class="nav-link active">👨‍⚕️ Doctors</a>
    <a href="../logout.php"          class="nav-logout" style="margin-top:8px;">🚪 Logout</a>
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

        <!-- PAGE HEADER -->
        <div class="page-header">
            <h1> Doctors Management</h1>
            <button class="btn-add" onclick="openAddModal()">➕ Add New Doctor</button>
        </div>

        <!-- ALERTS -->
        <?php if($error_msg): ?>
        <div class="alert alert-error"><?php echo $error_msg; ?></div>
        <?php endif; ?>

        <!-- STATS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon si-total">👨‍⚕️</div>
                <div>
                    <div class="stat-num"><?php echo $total_doctors; ?></div>
                    <div class="stat-label">Total Doctors</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon si-active">✅</div>
                <div>
                    <div class="stat-num"><?php echo $active_doctors; ?></div>
                    <div class="stat-label">Active Doctors</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon si-inactive">❌</div>
                <div>
                    <div class="stat-num"><?php echo $inactive_doctors; ?></div>
                    <div class="stat-label">Inactive Doctors</div>
                </div>
            </div>
        </div>

        <!-- FILTER BAR -->
        <form method="GET" class="filter-bar">
            <input type="text" name="search" placeholder="🔍 Doctor naam search karein..." 
                   value="<?php echo htmlspecialchars($search); ?>">
            <select name="filter_status">
                <option value="all"      <?php echo $filter_status==='all'      ? 'selected':''; ?>>All</option>
                <option value="active"   <?php echo $filter_status==='active'   ? 'selected':''; ?>>Active</option>
                <option value="inactive" <?php echo $filter_status==='inactive' ? 'selected':''; ?>>Inactive</option>
            </select>
            <button type="submit" class="filter-btn">🔍 Filter</button>
            <a href="doctors.php" class="reset-btn">↺ Reset</a>
        </form>

        <!-- TABLE -->
        <div class="table-wrap">
            <div class="table-header">
                <div class="table-title"> Doctors List</div>
                <div class="table-count"><?php echo $total_records; ?> records</div>
            </div>

            <?php if($total_records > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Doctor</th>
                        <th>Specialization</th>
                        <th>Status</th>
                        <th>Added On</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php $sr = 1; while($row = mysqli_fetch_assoc($result)): ?>
                <tr>
                    <td style="color:var(--gray-400); font-weight:600;"><?php echo $sr++; ?></td>
                    <td>
                        <div class="doctor-name"><?php echo htmlspecialchars($row['full_name']); ?></div>
                        <div class="doctor-id">ID: #<?php echo $row['doctor_id']; ?></div>
                    </td>
                    <td>
                        <div class="doctor-spec">🩺 <?php echo htmlspecialchars($row['specialization'] ?? '—'); ?></div>
                    </td>
                    <td>
                        <?php if($row['is_active']): ?>
                        <span class="badge-active">✅ Active</span>
                        <?php else: ?>
                        <span class="badge-inactive">❌ Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td style="color:var(--gray-500); font-size:12.5px;">
                        <?php echo date('d M Y', strtotime($row['created_at'])); ?>
                    </td>
                    <td>
                        <div class="action-btns">
                            <button class="btn-edit" onclick='openEditModal(<?php echo json_encode($row); ?>)'>✏️ Edit</button>
                            <a href="?toggle=1&id=<?php echo $row['doctor_id']; ?>" class="btn-toggle"
                               onclick="return confirm('<?php echo $row['is_active'] ? 'Inactive' : 'Active'; ?> karna chahte hain?')">
                                <?php echo $row['is_active'] ? '🔴 Inactive' : '🟢 Active'; ?>
                            </a>
                            <button class="btn-delete" onclick="confirmDelete(<?php echo $row['doctor_id']; ?>, '<?php echo addslashes(htmlspecialchars($row['full_name'])); ?>')">🗑️ Delete</button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
               
                <h3>Try Again</h3>
                <p><?php echo ($search || $filter_status !== 'all') ? 'Filter clear karein.' : 'Pehla doctor add karein!'; ?></p>
                <?php if(!$search && $filter_status === 'all'): ?>
                <button class="btn-add" onclick="openAddModal()">➕ Add Doctor</button>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- ADD MODAL -->
<div class="modal-overlay" id="addModal">
    <div class="modal">
        <h2>➕ Add New Doctor</h2>
        <p>Doctor ki information fill karein</p>
        <form method="POST" id="addForm">
            <div class="form-group">
                <label>Doctor Ka Naam <span style="color:red">*</span></label>
                <input type="text" name="full_name" placeholder="e.g. Dr. Ahmed" required>
            </div>
            <div class="form-group">
                <label>Specialization <span style="color:red">*</span></label>
                <input type="text" name="specialization" placeholder="e.g. Pediatrician" required>
            </div>
            <div class="modal-actions">
                <button type="button" class="modal-btn modal-btn-cancel" onclick="closeAddModal()">Cancel</button>
                <button type="submit" name="add_doctor" value="1" class="modal-btn modal-btn-submit">💾 Save Doctor</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="editModal">
    <div class="modal">
        <h2>✏️ Edit Doctor</h2>
        <p>Doctor ki information update karein</p>
        <form method="POST" id="editForm">
            <input type="hidden" name="doctor_id" id="edit_doctor_id">
            <div class="form-group">
                <label>Doctor Ka Naam <span style="color:red">*</span></label>
                <input type="text" name="full_name" id="edit_full_name" placeholder="e.g. Dr. Ahmed" required>
            </div>
            <div class="form-group">
                <label>Specialization <span style="color:red">*</span></label>
                <input type="text" name="specialization" id="edit_specialization" placeholder="e.g. Pediatrician" required>
            </div>
            <div class="form-group">
                <label>Status</label>
                <div class="toggle-wrap">
                    <label class="toggle-switch">
                        <input type="checkbox" name="is_active" id="edit_is_active">
                        <span class="toggle-slider"></span>
                    </label>
                    <span id="edit_status_text">Active</span>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="modal-btn modal-btn-cancel" onclick="closeEditModal()">Cancel</button>
                <button type="submit" name="edit_doctor" value="1" class="modal-btn modal-btn-submit">💾 Update</button>
            </div>
        </form>
    </div>
</div>

<!-- DELETE MODAL -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal">
        <h2>🗑️ Doctor Delete Karein?</h2>
        <p id="deleteModalText">Kya aap sure hain? Yeh action wapas nahi hoga.</p>
        <div class="modal-actions">
            <button type="button" class="modal-btn modal-btn-cancel" onclick="closeDeleteModal()">Cancel</button>
            <a href="#" id="confirmDeleteBtn" class="modal-btn modal-btn-delete" style="text-decoration:none; text-align:center;">🗑️ Delete</a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// ── Flash message SweetAlert ──
<?php if($flash_msg): ?>
Swal.fire({
    icon: '<?php echo $flash_type === "success" ? "success" : ($flash_type === "warning" ? "warning" : "info"); ?>',
    title: '<?php echo $flash_type === "success" ? "Success!" : "Done!"; ?>',
    text: '<?php echo addslashes($flash_msg); ?>',
    confirmButtonColor: '#2563eb',
    timer: 3000,
    timerProgressBar: true
});
<?php endif; ?>

// ── Add Modal ──
function openAddModal() { document.getElementById('addModal').classList.add('open'); }
function closeAddModal(){ document.getElementById('addModal').classList.remove('open'); }

// ── Edit Modal ──
function openEditModal(doc) {
    document.getElementById('edit_doctor_id').value    = doc.doctor_id;
    document.getElementById('edit_full_name').value    = doc.full_name;
    document.getElementById('edit_specialization').value = doc.specialization || '';
    const chk = document.getElementById('edit_is_active');
    chk.checked = doc.is_active == 1;
    document.getElementById('edit_status_text').textContent = chk.checked ? 'Active' : 'Inactive';
    document.getElementById('editModal').classList.add('open');
}
function closeEditModal(){ document.getElementById('editModal').classList.remove('open'); }

document.getElementById('edit_is_active').addEventListener('change', function(){
    document.getElementById('edit_status_text').textContent = this.checked ? 'Active' : 'Inactive';
});

// ── Delete Modal ──
function confirmDelete(id, name) {
    document.getElementById('deleteModalText').textContent = '"' + name + '" ko delete karna chahte hain? Yeh action wapas nahi hoga.';
    document.getElementById('confirmDeleteBtn').href = '?delete=1&id=' + id;
    document.getElementById('deleteModal').classList.add('open');
}
function closeDeleteModal(){ document.getElementById('deleteModal').classList.remove('open'); }

// ── Close on overlay click ──
['addModal','editModal','deleteModal'].forEach(id => {
    document.getElementById(id).addEventListener('click', function(e){ if(e.target===this) this.classList.remove('open'); });
});

// ── Mobile menu ──
function toggleMenu(){ document.getElementById('mobileMenu').classList.toggle('open'); }
</script>
</body>
</html>