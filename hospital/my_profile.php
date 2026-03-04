<?php
session_start();
if(!isset($_SESSION["logged_in"]) || $_SESSION["user_type"] != "hospital"){
    header("Location: ../login.php");
    exit();
}

include("../dbconnection.php");

$user_id = $_SESSION["user_id"];

// Get hospital + user data
$hospital = mysqli_fetch_assoc(mysqli_query($connection,
    "SELECT h.*, u.email, u.phone, u.is_active as user_active, u.created_at as reg_date, u.last_login
     FROM hospitals h
     JOIN users u ON h.user_id = u.user_id
     WHERE h.user_id = '$user_id'"));

if(!$hospital) {
    header("Location: dashboard.php");
    exit();
}

$hospital_id = $hospital['hospital_id'];
$is_verified = $hospital['is_verified'];
$is_active   = $hospital['is_active'];

// Stats for profile
$total_bookings   = mysqli_fetch_assoc(mysqli_query($connection,
    "SELECT COUNT(*) as cnt FROM vaccination_bookings WHERE hospital_id='$hospital_id'"))['cnt'];
$completed_vax    = mysqli_fetch_assoc(mysqli_query($connection,
    "SELECT COUNT(*) as cnt FROM vaccination_records WHERE hospital_id='$hospital_id'"))['cnt'];
$total_doctors    = mysqli_fetch_assoc(mysqli_query($connection,
    "SELECT COUNT(*) as cnt FROM doctors WHERE hospital_id='$hospital_id' AND is_active=1"))['cnt'];
$inventory_count  = mysqli_fetch_assoc(mysqli_query($connection,
    "SELECT COUNT(*) as cnt FROM hospital_vaccine_inventory WHERE hospital_id='$hospital_id' AND is_available=1"))['cnt'];
$pending_requests = mysqli_fetch_assoc(mysqli_query($connection,
    "SELECT COUNT(*) as cnt FROM appointment_requests WHERE hospital_id='$hospital_id' AND request_status='pending'"))['cnt'];

// Flash message
$flash_msg  = isset($_SESSION['msg'])      ? $_SESSION['msg']      : '';
$flash_type = isset($_SESSION['msg_type']) ? $_SESSION['msg_type'] : '';
unset($_SESSION['msg'], $_SESSION['msg_type']);

$error_msg = "";

// ── UPDATE HOSPITAL PROFILE ──
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $hospital_name       = mysqli_real_escape_string($connection, trim($_POST['hospital_name']));
    $registration_number = mysqli_real_escape_string($connection, trim($_POST['registration_number']));
    $contact_person      = mysqli_real_escape_string($connection, trim($_POST['contact_person']));
    $address             = mysqli_real_escape_string($connection, trim($_POST['address']));
    $city                = mysqli_real_escape_string($connection, trim($_POST['city']));
    $state               = mysqli_real_escape_string($connection, trim($_POST['state']));
    $postal_code         = mysqli_real_escape_string($connection, trim($_POST['postal_code']));
    $latitude            = !empty($_POST['latitude'])  ? mysqli_real_escape_string($connection, $_POST['latitude'])  : NULL;
    $longitude           = !empty($_POST['longitude']) ? mysqli_real_escape_string($connection, $_POST['longitude']) : NULL;
    $phone               = mysqli_real_escape_string($connection, trim($_POST['phone']));

    $errors = [];
    if(empty($hospital_name)) $errors[] = "Hospital naam zaroori hai.";
    if(empty($address))       $errors[] = "Address zaroori hai.";
    if(empty($city))          $errors[] = "City zaroori hai.";
    if(empty($state))         $errors[] = "State zaroori hai.";

    if(empty($errors)) {
        $lat_sql = $latitude  ? "'$latitude'"  : "NULL";
        $lng_sql = $longitude ? "'$longitude'" : "NULL";

        mysqli_query($connection,
            "UPDATE hospitals SET
                hospital_name='$hospital_name',
                registration_number='$registration_number',
                contact_person='$contact_person',
                address='$address',
                city='$city',
                state='$state',
                postal_code='$postal_code',
                latitude=$lat_sql,
                longitude=$lng_sql,
                updated_at=NOW()
             WHERE hospital_id='$hospital_id'");

        mysqli_query($connection,
            "UPDATE users SET phone='$phone', updated_at=NOW() WHERE user_id='$user_id'");

        $_SESSION['msg']      = "✅ Profile successfully update ho gayi!";
        $_SESSION['msg_type'] = "success";
        header("Location: my_profile.php");
        exit();
    } else {
        $error_msg = implode("<br>", $errors);
    }
}

// ── CHANGE PASSWORD ──
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_pass = $_POST['current_password'];
    $new_pass     = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    $user_row = mysqli_fetch_assoc(mysqli_query($connection,
        "SELECT password_hash FROM users WHERE user_id='$user_id'"));

    $errors = [];
    if(!password_verify($current_pass, $user_row['password_hash'])) {
        $errors[] = "Current password galat hai.";
    }
    if(strlen($new_pass) < 6) {
        $errors[] = "New password kam az kam 6 characters ka hona chahiye.";
    }
    if($new_pass !== $confirm_pass) {
        $errors[] = "New password aur confirm password match nahi karte.";
    }

    if(empty($errors)) {
        $hash = password_hash($new_pass, PASSWORD_DEFAULT);
        $hash = mysqli_real_escape_string($connection, $hash);
        mysqli_query($connection,
            "UPDATE users SET password_hash='$hash', updated_at=NOW() WHERE user_id='$user_id'");
        $_SESSION['msg']      = "✅ Password successfully change ho gaya!";
        $_SESSION['msg_type'] = "success";
        header("Location: my_profile.php");
        exit();
    } else {
        $error_msg = implode("<br>", $errors);
    }
}

// Refresh hospital data after update
$hospital = mysqli_fetch_assoc(mysqli_query($connection,
    "SELECT h.*, u.email, u.phone, u.is_active as user_active, u.created_at as reg_date, u.last_login
     FROM hospitals h
     JOIN users u ON h.user_id = u.user_id
     WHERE h.user_id = '$user_id'"));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile — VacciCare</title>
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
            --red-100:#fee2e2; --red-600:#dc2626;
            --yellow-100:#fef9c3; --yellow-600:#ca8a04;
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
        .content { max-width:1100px; margin:0 auto; padding:24px 40px; }

        /* PAGE HEADER */
        .page-header { margin-bottom:28px; }
        .page-header h1 { font-size:22px; font-weight:800; }
        .page-header p  { font-size:13px; color:var(--gray-500); margin-top:4px; }

        /* PROFILE TOP CARD */
        .profile-hero { background:linear-gradient(135deg,#1d4ed8,#3b82f6); border-radius:20px; padding:32px 36px; margin-bottom:28px; display:flex; align-items:center; gap:28px; flex-wrap:wrap; }
        .profile-avatar { width:80px; height:80px; background:rgba(255,255,255,0.2); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:36px; flex-shrink:0; border:3px solid rgba(255,255,255,0.4); }
        .profile-info { flex:1; }
        .profile-name  { font-size:24px; font-weight:800; color:#fff; }
        .profile-email { font-size:14px; color:rgba(255,255,255,0.8); margin-top:4px; }
        .profile-badges { display:flex; gap:8px; margin-top:12px; flex-wrap:wrap; }
        .pbadge { display:inline-flex; align-items:center; gap:6px; padding:5px 12px; border-radius:20px; font-size:12px; font-weight:700; }
        .pbadge-verified  { background:rgba(34,197,94,0.25);  color:#bbf7d0; border:1px solid rgba(34,197,94,0.3); }
        .pbadge-pending   { background:rgba(234,179,8,0.25);  color:#fde68a; border:1px solid rgba(234,179,8,0.3); }
        .pbadge-inactive  { background:rgba(239,68,68,0.25);  color:#fca5a5; border:1px solid rgba(239,68,68,0.3); }
        .pbadge-info      { background:rgba(255,255,255,0.15); color:rgba(255,255,255,0.9); border:1px solid rgba(255,255,255,0.2); }
        .profile-stats { display:grid; grid-template-columns:repeat(4,1fr); gap:1px; background:rgba(255,255,255,0.15); border-radius:12px; overflow:hidden; margin-top:20px; }
        .pstat { background:rgba(255,255,255,0.1); padding:14px 18px; text-align:center; }
        .pstat-num   { font-size:22px; font-weight:800; color:#fff; }
        .pstat-label { font-size:11px; color:rgba(255,255,255,0.7); font-weight:600; margin-top:2px; }

        /* TWO COLUMN LAYOUT */
        .two-col { display:grid; grid-template-columns:1fr 1fr; gap:24px; }
        .full-col { grid-column:1/-1; }

        /* FORM CARD */
        .form-card { background:#fff; border-radius:16px; border:1px solid var(--gray-200); box-shadow:0 2px 8px rgba(37,99,235,.05); overflow:hidden; }
        .card-header { padding:18px 24px; border-bottom:1px solid var(--gray-100); display:flex; align-items:center; gap:10px; }
        .card-header h2 { font-size:15px; font-weight:800; color:var(--blue-900); }
        .card-body { padding:24px; }
        .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:16px; }
        .form-group { margin-bottom:0; }
        .form-group.full { grid-column:1/-1; }
        .form-label { display:block; font-size:13px; font-weight:600; color:var(--gray-700); margin-bottom:6px; }
        .form-label span { color:var(--red-600); }
        .form-control { width:100%; padding:10px 14px; border:1.5px solid var(--gray-200); border-radius:9px; font-size:14px; font-family:inherit; color:var(--blue-900); background:var(--gray-50); outline:none; transition:all .2s; }
        .form-control:focus { border-color:var(--blue-400); background:#fff; box-shadow:0 0 0 3px rgba(59,130,246,.1); }
        .form-control[readonly] { background:var(--gray-100); color:var(--gray-500); cursor:not-allowed; }
        .input-hint { font-size:11.5px; color:var(--gray-400); margin-top:4px; }
        textarea.form-control { min-height:80px; resize:vertical; }

        /* READONLY INFO */
        .info-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        .info-item { background:var(--gray-50); border-radius:10px; padding:13px 16px; border:1px solid var(--gray-100); }
        .info-item .label { font-size:10.5px; font-weight:700; color:var(--gray-400); text-transform:uppercase; letter-spacing:.5px; }
        .info-item .value { font-size:14px; font-weight:700; color:var(--blue-900); margin-top:5px; }

        /* PASSWORD STRENGTH */
        .pass-strength { height:4px; border-radius:4px; margin-top:6px; transition:all .3s; background:var(--gray-200); }
        .pass-strength.weak   { background:#ef4444; width:33%; }
        .pass-strength.medium { background:#f59e0b; width:66%; }
        .pass-strength.strong { background:#22c55e; width:100%; }
        .pass-text { font-size:11px; margin-top:4px; font-weight:600; }
        .pass-text.weak   { color:#ef4444; }
        .pass-text.medium { color:#f59e0b; }
        .pass-text.strong { color:#22c55e; }

        /* SUBMIT BUTTONS */
        .btn-submit { padding:11px 24px; background:var(--blue-500); color:#fff; border:none; border-radius:9px; font-size:14px; font-weight:700; cursor:pointer; font-family:inherit; transition:all .2s; }
        .btn-submit:hover { background:var(--blue-600); transform:translateY(-1px); }
        .btn-danger { padding:11px 24px; background:var(--red-600); color:#fff; border:none; border-radius:9px; font-size:14px; font-weight:700; cursor:pointer; font-family:inherit; transition:all .2s; }
        .btn-danger:hover { background:#b91c1c; }

        /* ALERT */
        .alert { padding:13px 18px; border-radius:10px; margin-bottom:20px; font-size:13.5px; font-weight:600; }
        .alert-error { background:var(--red-100); border:1px solid #fca5a5; color:#991b1b; }

        /* TABS */
        .tabs { display:flex; gap:4px; background:var(--gray-100); border-radius:10px; padding:4px; margin-bottom:24px; }
        .tab-btn { flex:1; padding:9px; border:none; background:none; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; font-family:inherit; color:var(--gray-500); transition:all .2s; }
        .tab-btn.active { background:#fff; color:var(--blue-500); box-shadow:0 1px 4px rgba(0,0,0,0.1); }
        .tab-content { display:none; }
        .tab-content.active { display:block; }

        @media(max-width:768px){
            .navbar { padding:0 16px; }
            .nav-links { display:none; }
            .hamburger { display:block; }
            .mobile-menu.open { display:flex; flex-direction:column; position:fixed; top:68px; left:0; right:0; background:#fff; border-bottom:1px solid var(--gray-200); padding:12px; z-index:199; gap:4px; }
            .content { padding:16px; }
            .two-col { grid-template-columns:1fr; }
            .full-col { grid-column:1; }
            .form-grid { grid-template-columns:1fr; }
            .form-group.full { grid-column:1; }
            .profile-stats { grid-template-columns:repeat(2,1fr); }
            .info-grid { grid-template-columns:1fr; }
            .profile-hero { padding:20px; }
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
            <?php if($pending_requests > 0): ?>
            <span class="nav-badge"><?php echo $pending_requests; ?></span>
            <?php endif; ?>
        </a>
        <a href="todays_schedule.php"   class="nav-link"> Bookings</a>
        <a href="vaccine_inventory.php" class="nav-link"> Inventory</a>
        <a href="doctors.php"           class="nav-link"> Doctors</a>
        <a href="vaccination_records.php" class="nav-link">Records</a>
        <a href="my_profile.php"        class="nav-link active"> Profile</a>
    </div>
    <div class="nav-right">
        <div style="font-size:13px; font-weight:600; color:var(--blue-700);">
             <?php echo htmlspecialchars($hospital['hospital_name']); ?>
        </div>
        <a href="../logout.php" class="nav-logout"> Logout</a>
        <button class="hamburger" onclick="toggleMenu()">☰</button>
    </div>
</div>

<!-- MOBILE MENU -->
<div class="mobile-menu" id="mobileMenu">
    <a href="dashboard.php"            class="nav-link">📊 Dashboard</a>
    <a href="appointment_requests.php" class="nav-link">📋 Requests <?php if($pending_requests>0):?><span class="nav-badge"><?php echo $pending_requests;?></span><?php endif;?></a>
    <a href="todays_schedule.php"      class="nav-link">📅 Bookings</a>
    <a href="vaccine_inventory.php"    class="nav-link">💉 Inventory</a>
    <a href="doctors.php"              class="nav-link">👨‍⚕️ Doctors</a>
    <a href="vaccination_records.php"  class="nav-link">📋 Records</a>
    <a href="my_profile.php"           class="nav-link active">👤 Profile</a>
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

        <div class="page-header">
            <h1>My Profile</h1>
            <p>Hospital profile aur account settings manage karein</p>
        </div>

        <?php if($error_msg): ?>
        <div class="alert alert-error"><?php echo $error_msg; ?></div>
        <?php endif; ?>

        <!-- PROFILE HERO -->
        <div class="profile-hero">
            <div class="profile-avatar">🏥</div>
            <div class="profile-info">
                <div class="profile-name"><?php echo htmlspecialchars($hospital['hospital_name']); ?></div>
                <div class="profile-email">📧 <?php echo htmlspecialchars($hospital['email']); ?></div>
                <div class="profile-badges">
                    <?php if($is_verified): ?>
                    <span class="pbadge pbadge-verified">✅ Verified</span>
                    <?php else: ?>
                    <span class="pbadge pbadge-pending">⏳ Pending Verification</span>
                    <?php endif; ?>
                    <?php if($is_active): ?>
                    <span class="pbadge pbadge-verified">🟢 Active</span>
                    <?php else: ?>
                    <span class="pbadge pbadge-inactive">🔴 Inactive</span>
                    <?php endif; ?>
                    <span class="pbadge pbadge-info">📍 <?php echo htmlspecialchars($hospital['city']); ?>, <?php echo htmlspecialchars($hospital['state']); ?></span>
                    <?php if($hospital['registration_number']): ?>
                    <span class="pbadge pbadge-info">🏷️ Reg: <?php echo htmlspecialchars($hospital['registration_number']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="profile-stats" style="width:100%;">
                <div class="pstat">
                    <div class="pstat-num"><?php echo $total_bookings; ?></div>
                    <div class="pstat-label">Total Bookings</div>
                </div>
                <div class="pstat">
                    <div class="pstat-num"><?php echo $completed_vax; ?></div>
                    <div class="pstat-label">Vaccinations Done</div>
                </div>
                <div class="pstat">
                    <div class="pstat-num"><?php echo $total_doctors; ?></div>
                    <div class="pstat-label">Active Doctors</div>
                </div>
                <div class="pstat">
                    <div class="pstat-num"><?php echo $inventory_count; ?></div>
                    <div class="pstat-label">Inventory Items</div>
                </div>
            </div>
        </div>

        <!-- TABS -->
        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab('profile')">🏥 Hospital Info</button>
            <button class="tab-btn" onclick="switchTab('account')">📧 Account Info</button>
            <button class="tab-btn" onclick="switchTab('password')">🔒 Change Password</button>
        </div>

        <!-- TAB 1: HOSPITAL INFO -->
        <div class="tab-content active" id="tab-profile">
            <div class="form-card">
                <div class="card-header">
                    <span style="font-size:20px;">🏥</span>
                    <h2>Hospital Information Update Karein</h2>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">Hospital Name <span>*</span></label>
                                <input type="text" name="hospital_name" class="form-control"
                                       value="<?php echo htmlspecialchars($hospital['hospital_name']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Registration Number</label>
                                <input type="text" name="registration_number" class="form-control"
                                       value="<?php echo htmlspecialchars($hospital['registration_number'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Contact Person</label>
                                <input type="text" name="contact_person" class="form-control"
                                       value="<?php echo htmlspecialchars($hospital['contact_person'] ?? ''); ?>"
                                       placeholder="Contact person ka naam">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Phone Number</label>
                                <input type="text" name="phone" class="form-control"
                                       value="<?php echo htmlspecialchars($hospital['phone'] ?? ''); ?>"
                                       placeholder="03001234567">
                            </div>
                            <div class="form-group full">
                                <label class="form-label">Address <span>*</span></label>
                                <textarea name="address" class="form-control" required><?php echo htmlspecialchars($hospital['address']); ?></textarea>
                            </div>
                            <div class="form-group">
                                <label class="form-label">City <span>*</span></label>
                                <input type="text" name="city" class="form-control"
                                       value="<?php echo htmlspecialchars($hospital['city']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">State / Province <span>*</span></label>
                                <input type="text" name="state" class="form-control"
                                       value="<?php echo htmlspecialchars($hospital['state']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Postal Code</label>
                                <input type="text" name="postal_code" class="form-control"
                                       value="<?php echo htmlspecialchars($hospital['postal_code'] ?? ''); ?>">
                            </div>
                            <div class="form-group"></div>
                            <div class="form-group">
                                <label class="form-label">Latitude (optional)</label>
                                <input type="text" name="latitude" class="form-control"
                                       value="<?php echo $hospital['latitude'] ?? ''; ?>"
                                       placeholder="e.g. 24.8607">
                                <div class="input-hint">GPS coordinates for map</div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Longitude (optional)</label>
                                <input type="text" name="longitude" class="form-control"
                                       value="<?php echo $hospital['longitude'] ?? ''; ?>"
                                       placeholder="e.g. 67.0011">
                            </div>
                        </div>
                        <div style="margin-top:20px;">
                            <button type="submit" name="update_profile" value="1" class="btn-submit">💾 Profile Update Karein</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- TAB 2: ACCOUNT INFO -->
        <div class="tab-content" id="tab-account">
            <div class="form-card">
                <div class="card-header">
                    <span style="font-size:20px;">📧</span>
                    <h2>Account Information</h2>
                </div>
                <div class="card-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="label">Email Address</div>
                            <div class="value">📧 <?php echo htmlspecialchars($hospital['email']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="label">Account Status</div>
                            <div class="value"><?php echo $hospital['user_active'] ? '🟢 Active' : '🔴 Inactive'; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="label">Verification Status</div>
                            <div class="value"><?php echo $is_verified ? '✅ Verified' : '⏳ Pending'; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="label">Registered On</div>
                            <div class="value">📅 <?php echo date('d M Y', strtotime($hospital['reg_date'])); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="label">Last Login</div>
                            <div class="value">🕐 <?php echo $hospital['last_login'] ? date('d M Y, h:i A', strtotime($hospital['last_login'])) : 'N/A'; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="label">Hospital ID</div>
                            <div class="value">#<?php echo $hospital_id; ?></div>
                        </div>
                        <div class="info-item">
                            <div class="label">City</div>
                            <div class="value">📍 <?php echo htmlspecialchars($hospital['city']); ?>, <?php echo htmlspecialchars($hospital['state']); ?></div>
                        </div>
                        <div class="info-item">
                            <div class="label">Registration No.</div>
                            <div class="value">🏷️ <?php echo htmlspecialchars($hospital['registration_number'] ?? 'N/A'); ?></div>
                        </div>
                    </div>
                    <div style="margin-top:16px; padding:14px 16px; background:var(--yellow-100); border-radius:10px; font-size:13px; color:#78350f; border:1px solid #fde68a;">
                        ⚠️ <strong>Note:</strong> Email address change karne ke liye admin se contact karein. Email sirf admin change kar sakta hai.
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 3: CHANGE PASSWORD -->
        <div class="tab-content" id="tab-password">
            <div class="form-card">
                <div class="card-header">
                    <span style="font-size:20px;">🔒</span>
                    <h2>Password Change Karein</h2>
                </div>
                <div class="card-body">
                    <form method="POST" id="passForm">
                        <div class="form-grid">
                            <div class="form-group full">
                                <label class="form-label">Current Password <span>*</span></label>
                                <input type="password" name="current_password" class="form-control"
                                       placeholder="Apna current password likhein" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">New Password <span>*</span></label>
                                <input type="password" name="new_password" id="new_password" class="form-control"
                                       placeholder="Naya password (min 6 chars)" required minlength="6"
                                       oninput="checkStrength(this.value)">
                                <div class="pass-strength" id="passStrength"></div>
                                <div class="pass-text" id="passText"></div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Confirm New Password <span>*</span></label>
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control"
                                       placeholder="Password dobara likhein" required>
                                <div class="input-hint" id="matchHint"></div>
                            </div>
                        </div>
                        <div style="margin-top:20px;">
                            <button type="submit" name="change_password" value="1" class="btn-danger">🔒 Password Change Karein</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Flash SweetAlert
<?php if($flash_msg): ?>
Swal.fire({
    icon: '<?php echo $flash_type === "success" ? "success" : "warning"; ?>',
    title: '<?php echo $flash_type === "success" ? "Done!" : "Note!"; ?>',
    text: '<?php echo addslashes($flash_msg); ?>',
    confirmButtonColor: '#2563eb',
    timer: 3500,
    timerProgressBar: true
});
<?php endif; ?>

// Tabs
function switchTab(name) {
    document.querySelectorAll('.tab-btn').forEach((b,i) => {
        const tabs = ['profile','account','password'];
        b.classList.toggle('active', tabs[i] === name);
    });
    document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
}

// Password strength
function checkStrength(val) {
    const bar  = document.getElementById('passStrength');
    const text = document.getElementById('passText');
    if(!val) { bar.className='pass-strength'; text.textContent=''; return; }
    const strong = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&]).{8,}$/.test(val);
    const medium = /^(?=.*[a-zA-Z])(?=.*\d).{6,}$/.test(val);
    if(strong) { bar.className='pass-strength strong'; text.className='pass-text strong'; text.textContent='Strong ✅'; }
    else if(medium) { bar.className='pass-strength medium'; text.className='pass-text medium'; text.textContent='Medium ⚡'; }
    else { bar.className='pass-strength weak'; text.className='pass-text weak'; text.textContent='Weak ❌'; }
}

// Password match check
document.getElementById('confirm_password').addEventListener('input', function(){
    const hint = document.getElementById('matchHint');
    if(this.value === document.getElementById('new_password').value) {
        hint.textContent = '✅ Passwords match!'; hint.style.color = '#16a34a';
    } else {
        hint.textContent = '❌ Passwords match nahi karte!'; hint.style.color = '#dc2626';
    }
});

// Mobile menu
function toggleMenu() { document.getElementById('mobileMenu').classList.toggle('open'); }
</script>
</body>
</html>