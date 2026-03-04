<?php
session_start();
if(!isset($_SESSION["logged_in"]) || $_SESSION["user_type"] != "hospital"){
    header("Location: ../login.php");
    exit();
}

include("../dbconnection.php");

$user_id = $_SESSION["user_id"];
$today   = date('Y-m-d');

// Get hospital data
$query_hospital  = "SELECT * FROM hospitals WHERE user_id = '$user_id'";
$result_hospital = mysqli_query($connection, $query_hospital);
$hospital        = mysqli_fetch_assoc($result_hospital);
$hospital_id     = $hospital['hospital_id'] ?? 0;
$is_verified     = $hospital['is_verified'] ?? 0;
$is_active       = $hospital['is_active']   ?? 0;

// ── HANDLE DELETE ──
if(isset($_GET['delete']) && isset($_GET['id'])) {
    $del_id = intval($_GET['id']);
    $chk = mysqli_query($connection,
        "SELECT record_id FROM vaccination_records WHERE record_id='$del_id' AND hospital_id='$hospital_id'");
    if(mysqli_num_rows($chk) > 0) {
        mysqli_query($connection,
            "DELETE FROM vaccination_records WHERE record_id='$del_id' AND hospital_id='$hospital_id'");
        $_SESSION['msg']      = "✅ Record delete ho gaya!";
        $_SESSION['msg_type'] = "success";
        header("Location: vaccination_records.php");
        exit();
    }
}

// ── FLASH MESSAGE ──
$flash_msg  = isset($_SESSION['msg'])      ? $_SESSION['msg']      : '';
$flash_type = isset($_SESSION['msg_type']) ? $_SESSION['msg_type'] : '';
unset($_SESSION['msg'], $_SESSION['msg_type']);

// ── FILTERS ──
$search         = isset($_GET['search'])         ? mysqli_real_escape_string($connection, $_GET['search'])         : '';
$filter_status  = isset($_GET['filter_status'])  ? mysqli_real_escape_string($connection, $_GET['filter_status'])  : 'all';
$filter_vaccine = isset($_GET['filter_vaccine']) ? intval($_GET['filter_vaccine'])                                  : 0;
$filter_date    = isset($_GET['filter_date'])    ? mysqli_real_escape_string($connection, $_GET['filter_date'])    : '';

$where = "vr.hospital_id = '$hospital_id'";
if($search)           $where .= " AND (c.full_name LIKE '%$search%' OR p.full_name LIKE '%$search%' OR v.vaccine_name LIKE '%$search%' OR vr.batch_number LIKE '%$search%')";
if($filter_status !== 'all') $where .= " AND vr.vaccination_status = '$filter_status'";
if($filter_vaccine > 0)      $where .= " AND vr.vaccine_id = '$filter_vaccine'";
if($filter_date)             $where .= " AND vr.vaccination_date = '$filter_date'";

// ── VACCINES FOR FILTER ──
$vaccines_result = mysqli_query($connection,
    "SELECT vaccine_id, vaccine_name FROM vaccines WHERE is_active=1 ORDER BY vaccine_name");

// ── COUNTS ──
$total_records = mysqli_fetch_assoc(mysqli_query($connection,
    "SELECT COUNT(*) as cnt FROM vaccination_records WHERE hospital_id='$hospital_id'"))['cnt'];
$completed_count = mysqli_fetch_assoc(mysqli_query($connection,
    "SELECT COUNT(*) as cnt FROM vaccination_records WHERE hospital_id='$hospital_id' AND vaccination_status='completed'"))['cnt'];
$partial_count = mysqli_fetch_assoc(mysqli_query($connection,
    "SELECT COUNT(*) as cnt FROM vaccination_records WHERE hospital_id='$hospital_id' AND vaccination_status='partial'"))['cnt'];
$adverse_count = mysqli_fetch_assoc(mysqli_query($connection,
    "SELECT COUNT(*) as cnt FROM vaccination_records WHERE hospital_id='$hospital_id' AND vaccination_status='adverse_reaction'"))['cnt'];
$today_count = mysqli_fetch_assoc(mysqli_query($connection,
    "SELECT COUNT(*) as cnt FROM vaccination_records WHERE hospital_id='$hospital_id' AND vaccination_date='$today'"))['cnt'];

// ── MAIN QUERY ──
$query = "SELECT vr.*,
                 c.full_name  as child_name, c.date_of_birth, c.gender, c.blood_group,
                 v.vaccine_name, v.vaccine_code,
                 p.full_name  as parent_name, p.emergency_contact,
                 vb.confirmation_code, vb.booking_id
          FROM vaccination_records vr
          JOIN children  c  ON vr.child_id   = c.child_id
          JOIN vaccines  v  ON vr.vaccine_id  = v.vaccine_id
          JOIN parents   p  ON c.parent_id    = p.parent_id
          LEFT JOIN vaccination_bookings vb ON vr.booking_id = vb.booking_id
          WHERE $where
          ORDER BY vr.vaccination_date DESC, vr.vaccination_time DESC";

$result       = mysqli_query($connection, $query);
$filtered_cnt = mysqli_num_rows($result);

// ── PENDING REQUESTS (navbar badge) ──
$pending_count = mysqli_fetch_assoc(mysqli_query($connection,
    "SELECT COUNT(*) as cnt FROM appointment_requests WHERE hospital_id='$hospital_id' AND request_status='pending'"))['cnt'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vaccination Records — VacciCare</title>
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
            --orange-100:#ffedd5;--orange-600:#ea580c;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Plus Jakarta Sans',sans-serif; background:var(--bg); color:var(--blue-900); min-height:100vh; }

        /* NAVBAR */
        .navbar { position:fixed; top:0; left:0; right:0; z-index:200; background:rgba(255,255,255,0.97); backdrop-filter:blur(18px); border-bottom:1px solid var(--gray-200); padding:0 40px; height:68px; display:flex; justify-content:space-between; align-items:center; box-shadow:0 2px 12px rgba(37,99,235,0.08); }
        .nav-logo { display:flex; align-items:center; gap:12px; font-weight:800; font-size:18px; color:var(--blue-700); text-decoration:none; }
        .nav-logo .logo-icon { width:40px; height:40px; background:var(--blue-500); border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:20px; box-shadow:0 4px 14px rgba(37,99,235,.35); }
        .nav-links { display:flex; align-items:center; gap:4px; }
        .nav-link { display:flex; align-items:center; gap:7px; padding:8px 14px; border-radius:8px; color:var(--gray-700); text-decoration:none; font-size:13.5px; font-weight:600; transition:all .2s; }
        .nav-link:hover,.nav-link.active { background:var(--blue-50); color:var(--blue-500); }
        .nav-badge { background:var(--red-600); color:#fff; font-size:10px; font-weight:700; padding:2px 6px; border-radius:20px; margin-left:4px; }
        .nav-right { display:flex; align-items:center; gap:12px; }
        .nav-logout { padding:8px 16px; background:var(--red-100); color:var(--red-600); border-radius:8px; text-decoration:none; font-size:13px; font-weight:700; transition:all .2s; }
        .nav-logout:hover { background:#fecaca; }
        .hamburger { display:none; background:none; border:none; cursor:pointer; font-size:22px; }
        .mobile-menu { display:none; }

        /* LAYOUT */
        .main { padding-top:88px; padding-bottom:40px; }
        .verify-banner { padding:10px 40px; font-size:13px; font-weight:600; }
        .verify-banner.verified  { background:#dcfce7; color:#166534; }
        .verify-banner.pending   { background:#fef9c3; color:#92400e; }
        .verify-banner.inactive  { background:#fee2e2; color:#991b1b; }
        .content { max-width:1300px; margin:0 auto; padding:24px 40px; }

        /* PAGE HEADER */
        .page-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
        .page-header h1 { font-size:22px; font-weight:800; color:var(--blue-900); }

        /* STATS */
        .stats-grid { display:grid; grid-template-columns:repeat(5,1fr); gap:14px; margin-bottom:24px; }
        .stat-card { background:#fff; border-radius:14px; padding:18px 20px; border:1px solid var(--gray-200); display:flex; align-items:center; gap:14px; }
        .stat-icon { width:44px; height:44px; border-radius:11px; display:flex; align-items:center; justify-content:center; font-size:20px; flex-shrink:0; }
        .si-total    { background:var(--blue-50); }
        .si-done     { background:var(--green-100); }
        .si-partial  { background:var(--yellow-100); }
        .si-adverse  { background:var(--orange-100); }
        .si-today    { background:#f3e8ff; }
        .stat-num   { font-size:24px; font-weight:800; color:var(--blue-900); line-height:1; }
        .stat-label { font-size:11px; color:var(--gray-500); font-weight:600; margin-top:3px; }

        /* FILTER BAR */
        .filter-bar { background:#fff; border-radius:12px; padding:16px 20px; border:1px solid var(--gray-200); margin-bottom:20px; display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
        .filter-bar input, .filter-bar select { padding:9px 13px; border:1.5px solid var(--gray-200); border-radius:8px; font-size:13px; font-family:inherit; color:var(--blue-900); background:var(--gray-50); outline:none; transition:all .2s; }
        .filter-bar input:focus,.filter-bar select:focus { border-color:var(--blue-400); background:#fff; }
        .filter-bar input[type="text"] { flex:1; min-width:180px; }
        .filter-btn { padding:9px 18px; background:var(--blue-500); color:#fff; border:none; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; font-family:inherit; transition:all .2s; }
        .filter-btn:hover { background:var(--blue-600); }
        .reset-btn { padding:9px 14px; background:var(--gray-100); color:var(--gray-700); border-radius:8px; text-decoration:none; font-size:13px; font-weight:600; transition:all .2s; }
        .reset-btn:hover { background:var(--gray-200); }

        /* TABLE */
        .table-wrap { background:#fff; border-radius:14px; border:1px solid var(--gray-200); overflow:hidden; }
        .table-header { padding:18px 24px; display:flex; align-items:center; justify-content:space-between; border-bottom:1px solid var(--gray-100); }
        .table-title { font-size:15px; font-weight:800; color:var(--blue-900); }
        .table-count { font-size:12px; color:var(--gray-500); background:var(--gray-100); padding:4px 10px; border-radius:20px; font-weight:600; }
        table { width:100%; border-collapse:collapse; }
        thead tr { background:var(--gray-50); }
        th { padding:12px 14px; text-align:left; font-size:11px; font-weight:700; color:var(--gray-500); text-transform:uppercase; letter-spacing:.5px; white-space:nowrap; }
        td { padding:13px 14px; border-bottom:1px solid var(--gray-100); font-size:13px; vertical-align:middle; }
        tr:last-child td { border-bottom:none; }
        tr:hover td { background:var(--blue-50); }

        .child-name   { font-weight:700; color:var(--blue-900); }
        .child-detail { font-size:11.5px; color:var(--gray-500); margin-top:2px; }
        .vaccine-name { font-weight:700; color:var(--blue-700); }
        .vaccine-code { font-size:11px; color:var(--gray-400); margin-top:2px; }
        .dose-badge   { display:inline-block; background:var(--blue-50); color:var(--blue-500); padding:2px 8px; border-radius:6px; font-size:11px; font-weight:700; margin-top:3px; }
        .batch-code   { font-family:monospace; background:var(--gray-100); padding:3px 8px; border-radius:6px; font-size:12px; color:var(--gray-700); }
        .conf-code    { font-family:monospace; background:var(--blue-50); color:var(--blue-600); padding:3px 8px; border-radius:6px; font-size:11px; font-weight:700; }

        /* STATUS BADGES */
        .badge { display:inline-flex; align-items:center; gap:4px; padding:4px 10px; border-radius:20px; font-size:11.5px; font-weight:700; }
        .badge-completed { background:var(--green-100); color:var(--green-600); }
        .badge-partial   { background:var(--yellow-100); color:var(--yellow-600); }
        .badge-adverse   { background:var(--orange-100); color:var(--orange-600); }

        /* ACTION BUTTONS */
        .action-btns { display:flex; gap:6px; flex-wrap:wrap; }
        .btn-view   { padding:6px 11px; background:var(--blue-50);  color:var(--blue-500);  border-radius:7px; font-size:12px; font-weight:700; border:1px solid var(--blue-100); cursor:pointer; transition:all .2s; }
        .btn-view:hover { background:var(--blue-100); }
        .btn-delete { padding:6px 11px; background:var(--red-100); color:var(--red-600); border-radius:7px; font-size:12px; font-weight:700; border:1px solid #fecaca; cursor:pointer; transition:all .2s; }
        .btn-delete:hover { background:#fecaca; }

        /* EMPTY STATE */
        .empty-state { text-align:center; padding:60px 30px; }
        .e-icon { font-size:52px; display:block; margin-bottom:14px; }
        .empty-state h3 { font-size:18px; font-weight:800; margin-bottom:8px; }
        .empty-state p  { color:var(--gray-500); font-size:14px; }

        /* VIEW MODAL */
        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:999; align-items:center; justify-content:center; padding:20px; }
        .modal-overlay.open { display:flex; }
        .modal { background:#fff; border-radius:18px; padding:32px; width:100%; max-width:620px; max-height:90vh; overflow-y:auto; box-shadow:0 20px 60px rgba(0,0,0,0.2); }
        .modal h2 { font-size:18px; font-weight:800; margin-bottom:4px; color:var(--blue-900); }
        .modal-sub { font-size:13px; color:var(--gray-500); margin-bottom:22px; }
        .modal-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .modal-item { background:var(--gray-50); border-radius:10px; padding:12px 14px; }
        .modal-item .label { font-size:10.5px; font-weight:700; color:var(--gray-400); text-transform:uppercase; letter-spacing:.5px; }
        .modal-item .value { font-size:14px; font-weight:700; color:var(--blue-900); margin-top:4px; }
        .modal-notes { background:var(--yellow-100); border:1px solid #fde68a; border-radius:10px; padding:12px 14px; margin-top:12px; font-size:13px; color:#78350f; grid-column:1/-1; }
        .modal-actions { display:flex; justify-content:flex-end; margin-top:22px; }
        .modal-btn { padding:10px 24px; border-radius:9px; font-size:13px; font-weight:700; cursor:pointer; border:none; font-family:inherit; background:var(--gray-100); color:var(--gray-700); transition:all .2s; }
        .modal-btn:hover { background:var(--gray-200); }

        /* DELETE MODAL */
        .del-modal { max-width:420px; }
        .del-modal h2 { color:var(--red-600); }
        .modal-btn-del { background:var(--red-600); color:#fff; }
        .modal-btn-del:hover { background:#b91c1c; }

        .alert { padding:12px 18px; border-radius:10px; margin-bottom:18px; font-size:13.5px; font-weight:600; }
        .alert-success { background:var(--green-100); border:1px solid #86efac; color:#166534; }
        .alert-error   { background:var(--red-100);   border:1px solid #fca5a5; color:#991b1b; }

        @media(max-width:900px){
            .stats-grid { grid-template-columns:repeat(2,1fr); }
            .navbar { padding:0 16px; }
            .nav-links { display:none; }
            .hamburger { display:block; }
            .mobile-menu.open { display:flex; flex-direction:column; position:fixed; top:68px; left:0; right:0; background:#fff; border-bottom:1px solid var(--gray-200); padding:12px; z-index:199; gap:4px; }
            .content { padding:16px; }
            .modal-grid { grid-template-columns:1fr; }
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
        <a href="dashboard.php"            class="nav-link"> Dashboard</a>
        <a href="appointment_requests.php" class="nav-link">
             Requests
            <?php if($pending_count > 0): ?>
            <span class="nav-badge"><?php echo $pending_count; ?></span>
            <?php endif; ?>
        </a>
        <a href="todays_schedule.php"      class="nav-link"> Bookings</a>
        <a href="vaccine_inventory.php"    class="nav-link"> Inventory</a>
        <a href="doctors.php"              class="nav-link"> Doctors</a>
        <a href="vaccination_records.php"  class="nav-link active"> Records</a>
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
    <a href="dashboard.php"            class="nav-link">📊 Dashboard</a>
    <a href="appointment_requests.php" class="nav-link">📋 Requests <?php if($pending_count>0):?><span class="nav-badge"><?php echo $pending_count;?></span><?php endif;?></a>
    <a href="todays_schedule.php"      class="nav-link">📅 Bookings</a>
    <a href="vaccine_inventory.php"    class="nav-link">💉 Inventory</a>
    <a href="doctors.php"              class="nav-link">👨‍⚕️ Doctors</a>
    <a href="vaccination_records.php"  class="nav-link active">📋 Records</a>
    <a href="../logout.php"            class="nav-logout" style="margin-top:8px;">🚪 Logout</a>
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
            <h1>📋 Vaccination Records</h1>
            <div style="font-size:13px; color:var(--gray-500);">Aaj: <?php echo date('d M Y'); ?></div>
        </div>

        <!-- STATS -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon si-total">📋</div>
                <div>
                    <div class="stat-num"><?php echo $total_records; ?></div>
                    <div class="stat-label">Total Records</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon si-done">✅</div>
                <div>
                    <div class="stat-num"><?php echo $completed_count; ?></div>
                    <div class="stat-label">Completed</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon si-partial">⚡</div>
                <div>
                    <div class="stat-num"><?php echo $partial_count; ?></div>
                    <div class="stat-label">Partial</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon si-adverse">⚠️</div>
                <div>
                    <div class="stat-num"><?php echo $adverse_count; ?></div>
                    <div class="stat-label">Adverse Reaction</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon si-today">📅</div>
                <div>
                    <div class="stat-num"><?php echo $today_count; ?></div>
                    <div class="stat-label">Aaj Ke Records</div>
                </div>
            </div>
        </div>

        <!-- FILTER BAR -->
        <form method="GET" class="filter-bar">
            <input type="text" name="search" placeholder="🔍 Child, parent, vaccine ya batch search karein..."
                   value="<?php echo htmlspecialchars($search); ?>">
            <select name="filter_vaccine">
                <option value="0">All Vaccines</option>
                <?php while($v = mysqli_fetch_assoc($vaccines_result)): ?>
                <option value="<?php echo $v['vaccine_id']; ?>" <?php echo $filter_vaccine == $v['vaccine_id'] ? 'selected':''; ?>>
                    <?php echo htmlspecialchars($v['vaccine_name']); ?>
                </option>
                <?php endwhile; ?>
            </select>
            <select name="filter_status">
                <option value="all"              <?php echo $filter_status==='all'              ?'selected':'';?>>All Status</option>
                <option value="completed"        <?php echo $filter_status==='completed'        ?'selected':'';?>>✅ Completed</option>
                <option value="partial"          <?php echo $filter_status==='partial'          ?'selected':'';?>>⚡ Partial</option>
                <option value="adverse_reaction" <?php echo $filter_status==='adverse_reaction' ?'selected':'';?>>⚠️ Adverse Reaction</option>
            </select>
            <input type="date" name="filter_date" value="<?php echo $filter_date; ?>" title="Date filter">
            <button type="submit" class="filter-btn">🔍 Filter</button>
            <a href="vaccination_records.php" class="reset-btn">↺ Reset</a>
        </form>

        <!-- TABLE -->
        <div class="table-wrap">
            <div class="table-header">
                <div class="table-title">📋 Records List</div>
                <div class="table-count"><?php echo $filtered_cnt; ?> records</div>
            </div>

            <?php if($filtered_cnt > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Child</th>
                        <th>Vaccine</th>
                        <th>Administered By</th>
                        <th>Batch</th>
                        <th>Date & Time</th>
                        <th>Status</th>
                        <th>Next Dose</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php $sr = 1; while($row = mysqli_fetch_assoc($result)):
                    $age_days = floor((time() - strtotime($row['date_of_birth'])) / 86400);
                    $age_yr   = floor($age_days / 365);
                    $age_mo   = floor(($age_days % 365) / 30);
                    $age_str  = $age_yr > 0 ? "{$age_yr}y {$age_mo}m" : "{$age_mo} months";
                ?>
                <tr>
                    <td style="color:var(--gray-400); font-weight:600;"><?php echo $sr++; ?></td>
                    <td>
                        <div class="child-name"><?php echo htmlspecialchars($row['child_name']); ?></div>
                        <div class="child-detail"><?php echo $row['gender']; ?> · <?php echo $age_str; ?></div>
                        <div class="child-detail">👤 <?php echo htmlspecialchars($row['parent_name']); ?></div>
                    </td>
                    <td>
                        <div class="vaccine-name"><?php echo htmlspecialchars($row['vaccine_name']); ?></div>
                        <div class="vaccine-code"><?php echo $row['vaccine_code']; ?></div>
                        <span class="dose-badge">Dose <?php echo $row['dose_number']; ?></span>
                    </td>
                    <td style="font-size:13px; color:var(--gray-700);">
                        <?php echo htmlspecialchars($row['administered_by'] ?? '—'); ?>
                    </td>
                    <td>
                        <span class="batch-code"><?php echo htmlspecialchars($row['batch_number'] ?? 'N/A'); ?></span>
                        <?php if($row['confirmation_code']): ?>
                        <div style="margin-top:4px;"><span class="conf-code"><?php echo $row['confirmation_code']; ?></span></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="font-weight:600; color:var(--gray-700);"><?php echo date('d M Y', strtotime($row['vaccination_date'])); ?></div>
                        <div style="font-size:11.5px; color:var(--gray-400);"><?php echo $row['vaccination_time'] ? date('h:i A', strtotime($row['vaccination_time'])) : '—'; ?></div>
                    </td>
                    <td>
                        <?php if($row['vaccination_status'] === 'completed'): ?>
                        <span class="badge badge-completed">✅ Completed</span>
                        <?php elseif($row['vaccination_status'] === 'partial'): ?>
                        <span class="badge badge-partial">⚡ Partial</span>
                        <?php else: ?>
                        <span class="badge badge-adverse">⚠️ Adverse</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:12.5px; color:var(--gray-600);">
                        <?php echo $row['next_dose_due_date'] ? date('d M Y', strtotime($row['next_dose_due_date'])) : '—'; ?>
                    </td>
                    <td>
                        <div class="action-btns">
                            <button class="btn-view" onclick='openViewModal(<?php echo json_encode($row); ?>)'>👁 View</button>
                            <button class="btn-delete" onclick="confirmDelete(<?php echo $row['record_id']; ?>, '<?php echo addslashes(htmlspecialchars($row['child_name'])); ?>')">🗑️</button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <span class="e-icon">📋</span>
                <h3>Koi Record Nahi Mila</h3>
                <p>
                    <?php if($search || $filter_status !== 'all' || $filter_vaccine > 0 || $filter_date): ?>
                    Filter clear karein ya doosra search karein.
                    <?php else: ?>
                    Abhi tak koi vaccination record nahi hai. Jab vaccination complete ho to record yahan dikhega.
                    <?php endif; ?>
                </p>
            </div>
            <?php endif; ?>
        </div>

        <!-- LEGEND -->
        <div style="margin-top:14px; background:#fff; border-radius:10px; padding:12px 20px; border:1px solid var(--gray-200); font-size:12px; color:var(--gray-500); display:flex; gap:20px; flex-wrap:wrap;">
            <span>✅ Completed — Vaccination successfully diya gaya</span>
            <span>⚡ Partial — Dose partially diya gaya</span>
            <span>⚠️ Adverse Reaction — Side effects aa gaye</span>
        </div>

    </div>
</div>

<!-- VIEW MODAL -->
<div class="modal-overlay" id="viewModal">
    <div class="modal">
        <h2>📋 Record Details</h2>
        <p class="modal-sub" id="viewModalSub">Vaccination record ki complete details</p>
        <div class="modal-grid" id="viewModalGrid"></div>
        <div class="modal-actions">
            <button class="modal-btn" onclick="closeViewModal()">Close</button>
        </div>
    </div>
</div>

<!-- DELETE MODAL -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal del-modal">
        <h2>🗑️ Record Delete Karein?</h2>
        <p id="deleteModalText" style="color:var(--gray-500); margin:12px 0 20px; font-size:13.5px;">Kya aap sure hain? Yeh action wapas nahi hoga.</p>
        <div class="modal-actions" style="gap:10px;">
            <button class="modal-btn" onclick="closeDeleteModal()">Cancel</button>
            <a href="#" id="confirmDeleteBtn" class="modal-btn modal-btn-del" style="text-decoration:none; text-align:center;">🗑️ Delete</a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// ── Flash SweetAlert ──
<?php if($flash_msg): ?>
Swal.fire({
    icon: '<?php echo $flash_type === "success" ? "success" : "error"; ?>',
    title: '<?php echo $flash_type === "success" ? "Done!" : "Error!"; ?>',
    text: '<?php echo addslashes($flash_msg); ?>',
    confirmButtonColor: '#2563eb',
    timer: 3000,
    timerProgressBar: true
});
<?php endif; ?>

// ── View Modal ──
function openViewModal(r) {
    document.getElementById('viewModalSub').textContent = 'Record #' + r.record_id + ' — ' + r.vaccination_status.toUpperCase();
    const fields = [
        {label:'Child Name',    val: r.child_name},
        {label:'Gender',        val: r.gender},
        {label:'Parent Name',   val: r.parent_name},
        {label:'Contact',       val: r.emergency_contact || '—'},
        {label:'Vaccine',       val: r.vaccine_name + ' (' + (r.vaccine_code||'') + ')'},
        {label:'Dose Number',   val: 'Dose ' + r.dose_number},
        {label:'Vaccination Date', val: r.vaccination_date},
        {label:'Vaccination Time', val: r.vaccination_time || '—'},
        {label:'Administered By',  val: r.administered_by || '—'},
        {label:'Batch Number',     val: r.batch_number || '—'},
        {label:'Status',           val: r.vaccination_status.toUpperCase()},
        {label:'Next Dose Due',    val: r.next_dose_due_date || '—'},
    ];
    let html = '';
    fields.forEach(f => {
        html += `<div class="modal-item"><div class="label">${f.label}</div><div class="value">${f.val}</div></div>`;
    });
    if(r.side_effects && r.side_effects !== 'null') html += `<div class="modal-notes" style="grid-column:1/-1"><strong>⚠️ Side Effects:</strong> ${r.side_effects}</div>`;
    if(r.notes && r.notes !== 'null')               html += `<div class="modal-notes" style="grid-column:1/-1"><strong>📝 Notes:</strong> ${r.notes}</div>`;
    document.getElementById('viewModalGrid').innerHTML = html;
    document.getElementById('viewModal').classList.add('open');
}
function closeViewModal() { document.getElementById('viewModal').classList.remove('open'); }

// ── Delete Modal ──
function confirmDelete(id, name) {
    document.getElementById('deleteModalText').textContent = '"' + name + '" ka record delete karna chahte hain? Yeh wapas nahi hoga.';
    document.getElementById('confirmDeleteBtn').href = '?delete=1&id=' + id;
    document.getElementById('deleteModal').classList.add('open');
}
function closeDeleteModal() { document.getElementById('deleteModal').classList.remove('open'); }

// ── Close on overlay click ──
['viewModal','deleteModal'].forEach(id => {
    document.getElementById(id).addEventListener('click', function(e){ if(e.target===this) this.classList.remove('open'); });
});

// ── Mobile menu ──
function toggleMenu() { document.getElementById('mobileMenu').classList.toggle('open'); }

// ── Auto hide alerts ──
setTimeout(function(){
    document.querySelectorAll('.alert').forEach(el => {
        el.style.opacity='0'; setTimeout(()=>el.style.display='none', 300);
    });
}, 5000);
</script>
</body>
</html>