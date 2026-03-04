<?php
session_start();

// ── Auth Check ──
if(!isset($_SESSION["logged_in"]) || $_SESSION["user_type"] != "admin"){
    header("location:../login.php");
    exit();
}

include("../dbconnection.php");

$user_id  = intval($_SESSION["user_id"]);
$hospital_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if($hospital_id <= 0){
    header("location:manage_hospitals.php");
    exit();
}

// ── Get Admin Info ──
$q_admin = "SELECT admin_id, full_name FROM admins WHERE user_id = '$user_id'";
$r_admin  = mysqli_query($connection, $q_admin);
$admin    = mysqli_fetch_assoc($r_admin);
$admin_id = $admin['admin_id'] ?? 1;
$admin_name = $admin['full_name'] ?? 'Admin';

// ── Get Hospital Info ──
$q_hospital = "SELECT h.*, u.email, u.phone, u.is_active AS user_active
               FROM hospitals h
               JOIN users u ON h.user_id = u.user_id
               WHERE h.hospital_id = '$hospital_id'";
$r_hospital = mysqli_query($connection, $q_hospital);

if(!$r_hospital || mysqli_num_rows($r_hospital) == 0){
    $_SESSION['msg']  = "Hospital not found!";
    $_SESSION['msg_type'] = 'error';
    header("location:manage_hospitals.php");
    exit();
}

$hospital = mysqli_fetch_assoc($r_hospital);

// ── Already Verified Check ──
$already_verified = ($hospital['is_verified'] == 1);

// ── Handle POST ──
if($_SERVER['REQUEST_METHOD'] === 'POST'){

    $action = $_POST['action'] ?? '';
    $notes  = mysqli_real_escape_string($connection, trim($_POST['notes'] ?? ''));
    $send_notif = isset($_POST['send_notification']) ? 1 : 0;

    // ── APPROVE ──
    if($action === 'approve'){

        // 1) Verify + Activate hospital
        $upd = "UPDATE hospitals
                SET is_verified = 1,
                    is_active   = 1,
                    updated_at  = NOW()
                WHERE hospital_id = '$hospital_id'";
        $run = mysqli_query($connection, $upd);

        if(!$run){
            $error = "DB Error: " . mysqli_error($connection);
        } else {

            // 2) Activate user account too
            $upd_user = "UPDATE users SET is_active = 1
                         WHERE user_id = '{$hospital['user_id']}'";
            mysqli_query($connection, $upd_user);

            // 3) Audit log
            $desc = mysqli_real_escape_string($connection,
                "Verified & activated hospital: {$hospital['hospital_name']}. Notes: $notes");
            $ip   = $_SERVER['REMOTE_ADDR']    ?? '::1';
            $ua   = mysqli_real_escape_string($connection, $_SERVER['HTTP_USER_AGENT'] ?? '');
            $log  = "INSERT INTO audit_logs
                     (user_id, action_type, table_name, record_id, action_description, ip_address, user_agent, created_at)
                     VALUES ('$user_id','VERIFY_HOSPITAL','hospitals','$hospital_id','$desc','$ip','$ua',NOW())";
            mysqli_query($connection, $log);

            // 4) Notification to hospital user (optional)
            if($send_notif){
                $h_user_id = $hospital['user_id'];
                $h_name    = mysqli_real_escape_string($connection, $hospital['hospital_name']);
                $notif_msg = mysqli_real_escape_string($connection,
                    "Your hospital $h_name has been verified by admin. You can now start managing appointments.");
                $notif = "INSERT INTO notifications
                          (user_id, notification_type, title, message, related_id, created_at)
                          VALUES ('$h_user_id','system','Hospital Verified','$notif_msg','$hospital_id',NOW())";
                mysqli_query($connection, $notif);
            }

            $_SESSION['msg']      = "✅ Hospital '{$hospital['hospital_name']}' successfully verified & activated!";
            $_SESSION['msg_type'] = 'success';
            header("location:manage_hospitals.php");
            exit();
        }

    // ── REJECT ──
    } elseif($action === 'reject'){

        $reject_reason = mysqli_real_escape_string($connection, trim($_POST['reject_reason'] ?? 'No reason given'));

        // Keep is_verified=0, deactivate user
        $upd_user = "UPDATE users SET is_active = 0 WHERE user_id = '{$hospital['user_id']}'";
        mysqli_query($connection, $upd_user);

        // Audit log
        $desc = mysqli_real_escape_string($connection,
            "Rejected hospital: {$hospital['hospital_name']}. Reason: $reject_reason");
        $ip   = $_SERVER['REMOTE_ADDR']    ?? '::1';
        $ua   = mysqli_real_escape_string($connection, $_SERVER['HTTP_USER_AGENT'] ?? '');
        $log  = "INSERT INTO audit_logs
                 (user_id, action_type, table_name, record_id, action_description, ip_address, user_agent, created_at)
                 VALUES ('$user_id','REJECT_HOSPITAL','hospitals','$hospital_id','$desc','$ip','$ua',NOW())";
        mysqli_query($connection, $log);

        // Notification
        if($send_notif){
            $h_user_id = $hospital['user_id'];
            $h_name    = mysqli_real_escape_string($connection, $hospital['hospital_name']);
            $notif_msg = mysqli_real_escape_string($connection,
                "Your hospital $h_name verification has been rejected. Reason: $reject_reason. Please contact admin for more details.");
            $notif = "INSERT INTO notifications
                      (user_id, notification_type, title, message, related_id, created_at)
                      VALUES ('$h_user_id','system','Hospital Verification Rejected','$notif_msg','$hospital_id',NOW())";
            mysqli_query($connection, $notif);
        }

        $_SESSION['msg']      = "❌ Hospital '{$hospital['hospital_name']}' has been rejected.";
        $_SESSION['msg_type'] = 'warning';
        header("location:manage_hospitals.php");
        exit();
    }
}

// ── Get Total Bookings ──
$q_bk  = "SELECT COUNT(*) as cnt FROM vaccination_bookings WHERE hospital_id = '$hospital_id'";
$total_bookings = mysqli_fetch_assoc(mysqli_query($connection, $q_bk))['cnt'] ?? 0;

$q_rq  = "SELECT COUNT(*) as cnt FROM appointment_requests WHERE hospital_id = '$hospital_id'";
$total_requests = mysqli_fetch_assoc(mysqli_query($connection, $q_rq))['cnt'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Hospital — Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Inter',sans-serif; background:#f0f4ff; color:#1a1a2e; }

        /* ── NAVBAR ── */
        .navbar {
            background:#fff; border-bottom:2px solid #e8eeff;
            padding:0 32px; height:64px; display:flex;
            align-items:center; justify-content:space-between;
            position:sticky; top:0; z-index:100;
            box-shadow:0 2px 12px rgba(59,130,246,0.08);
        }
        .navbar-brand { display:flex; align-items:center; gap:10px; }
        .brand-icon {
            width:40px; height:40px;
            background:linear-gradient(135deg,#1d4ed8,#3b82f6);
            border-radius:10px; display:flex;
            align-items:center; justify-content:center;
            font-size:18px; color:#fff;
        }
        .navbar-brand h2 { font-size:19px; font-weight:700; color:#1d4ed8; }
        .nav-links { display:flex; gap:6px; align-items:center; }
        .nav-links a {
            color:#4b6cb7; text-decoration:none;
            padding:8px 14px; border-radius:8px;
            font-size:13px; font-weight:500; transition:all .2s;
        }
        .nav-links a:hover { background:#eff6ff; color:#1d4ed8; }
        .nav-links a.logout { background:#fee2e2; color:#dc2626; }

        /* ── LAYOUT ── */
        .page-wrap { max-width:900px; margin:30px auto; padding:0 20px; }

        /* ── BACK LINK ── */
        .back-link {
            display:inline-flex; align-items:center; gap:6px;
            color:#1d4ed8; text-decoration:none; font-size:14px;
            font-weight:600; margin-bottom:20px; padding:8px 14px;
            background:#fff; border:1px solid #e8eeff; border-radius:8px;
            transition:all .2s; box-shadow:0 1px 4px rgba(59,130,246,.07);
        }
        .back-link:hover { background:#eff6ff; transform:translateX(-3px); }

        /* ── BANNER ── */
        .page-banner {
            background:linear-gradient(135deg,#1d4ed8,#3b82f6,#60a5fa);
            border-radius:18px; padding:28px 36px; margin-bottom:24px;
            color:#fff; display:flex; align-items:center; justify-content:space-between;
            box-shadow:0 8px 32px rgba(59,130,246,.3); position:relative; overflow:hidden;
        }
        .page-banner::before {
            content:''; position:absolute; top:-40px; right:-40px;
            width:180px; height:180px; background:rgba(255,255,255,.08); border-radius:50%;
        }
        .banner-text { position:relative; z-index:1; }
        .banner-text h2 { font-size:21px; font-weight:700; margin-bottom:4px; }
        .banner-text p  { font-size:13px; opacity:.85; }
        .banner-icon { font-size:48px; opacity:.9; position:relative; z-index:1; }

        /* ── ALREADY VERIFIED ── */
        .verified-badge {
            background:#dcfce7; border:1px solid #bbf7d0;
            border-radius:12px; padding:16px 22px;
            color:#166534; font-size:14px; font-weight:600;
            margin-bottom:20px; display:flex; align-items:center; gap:10px;
        }

        /* ── ERROR ── */
        .error-box {
            background:#fee2e2; border:1px solid #fecaca;
            border-radius:12px; padding:14px 20px;
            color:#991b1b; font-size:14px; margin-bottom:16px;
        }

        /* ── GRID ── */
        .grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px; }

        /* ── CARD ── */
        .card {
            background:#fff; border-radius:16px;
            border:1px solid #e8eeff;
            box-shadow:0 2px 12px rgba(59,130,246,.07);
            overflow:hidden;
        }
        .card-header {
            padding:16px 22px; border-bottom:1px solid #f1f5ff;
            font-size:14px; font-weight:700; color:#1a1a2e;
            display:flex; align-items:center; gap:8px;
        }
        .card-body { padding:20px 22px; }

        /* ── INFO ROWS ── */
        .info-row {
            display:flex; align-items:flex-start;
            gap:10px; padding:9px 0;
            border-bottom:1px solid #f4f6ff;
            font-size:13.5px;
        }
        .info-row:last-child { border-bottom:none; }
        .info-label { color:#9ca3af; font-weight:600; min-width:130px; }
        .info-value { color:#1a1a2e; font-weight:500; flex:1; }

        /* ── STAT PILLS ── */
        .stat-pills { display:flex; gap:12px; margin-top:4px; }
        .stat-pill {
            background:#f0f4ff; border:1px solid #e8eeff;
            border-radius:10px; padding:12px 20px; text-align:center; flex:1;
        }
        .stat-pill .num { font-size:22px; font-weight:800; color:#1d4ed8; }
        .stat-pill .lbl { font-size:11.5px; color:#9ca3af; margin-top:2px; }

        /* ── BADGE ── */
        .badge {
            display:inline-block; padding:4px 12px; border-radius:20px;
            font-size:12px; font-weight:600;
        }
        .badge-verified  { background:#dcfce7; color:#166534; }
        .badge-pending   { background:#fef9c3; color:#92400e; }
        .badge-active    { background:#dcfce7; color:#166534; }
        .badge-inactive  { background:#fee2e2; color:#991b1b; }

        /* ── FORM CARD ── */
        .form-card { background:#fff; border-radius:16px; border:1px solid #e8eeff;
            box-shadow:0 2px 12px rgba(59,130,246,.07); padding:28px 30px; }

        .section-title {
            font-size:12px; font-weight:700; color:#6b7280;
            text-transform:uppercase; letter-spacing:.7px;
            margin:0 0 16px; padding-bottom:8px;
            border-bottom:1px solid #f1f5ff;
        }

        /* ── RADIO CARDS ── */
        .radio-group { display:flex; gap:14px; margin-bottom:20px; }
        .radio-card {
            flex:1; border:2px solid #e8eeff; border-radius:12px;
            padding:16px 18px; cursor:pointer; transition:all .2s;
            display:flex; align-items:center; gap:12px;
        }
        .radio-card:hover { border-color:#93c5fd; background:#f8faff; }
        .radio-card.approve-card.selected { border-color:#10b981; background:#f0fdf4; }
        .radio-card.reject-card.selected  { border-color:#ef4444; background:#fff5f5; }
        .radio-card input[type=radio] { display:none; }
        .radio-icon { font-size:22px; }
        .radio-text .rt { font-size:14px; font-weight:700; color:#1a1a2e; }
        .radio-text .rs { font-size:12px; color:#9ca3af; margin-top:2px; }

        /* ── FORM ELEMENTS ── */
        .field-label { display:block; font-size:13px; font-weight:600; color:#374151; margin-bottom:7px; }
        textarea, input[type=text] {
            width:100%; padding:11px 14px;
            border:1.5px solid #e8eeff; border-radius:10px;
            font-size:14px; font-family:'Inter',sans-serif;
            color:#1a1a2e; background:#fafbff;
            transition:all .2s; outline:none; resize:vertical;
        }
        textarea:focus, input:focus {
            border-color:#3b82f6; background:#fff;
            box-shadow:0 0 0 3px rgba(59,130,246,.1);
        }
        textarea { min-height:80px; }

        /* ── REJECT REASON (hidden by default) ── */
        #reject-reason-wrap { display:none; margin-top:14px; }

        /* ── CHECKBOX ── */
        .check-wrap {
            display:flex; align-items:center; gap:10px;
            margin-top:16px; padding:12px 16px;
            background:#f8faff; border:1px solid #e8eeff; border-radius:10px;
        }
        .check-wrap input[type=checkbox] { width:16px; height:16px; cursor:pointer; }
        .check-wrap label { font-size:13.5px; color:#374151; cursor:pointer; }

        /* ── BUTTONS ── */
        .btn-row { display:flex; gap:12px; margin-top:24px; }
        .btn-approve {
            flex:1; padding:14px; border:none; border-radius:10px;
            background:linear-gradient(135deg,#059669,#10b981);
            color:#fff; font-size:15px; font-weight:700;
            cursor:pointer; font-family:'Inter',sans-serif;
            box-shadow:0 4px 14px rgba(16,185,129,.3);
            transition:all .2s; display:flex; align-items:center;
            justify-content:center; gap:8px;
        }
        .btn-approve:hover { transform:translateY(-2px); box-shadow:0 8px 22px rgba(16,185,129,.4); }
        .btn-reject {
            flex:1; padding:14px; border:none; border-radius:10px;
            background:linear-gradient(135deg,#dc2626,#ef4444);
            color:#fff; font-size:15px; font-weight:700;
            cursor:pointer; font-family:'Inter',sans-serif;
            box-shadow:0 4px 14px rgba(239,68,68,.3);
            transition:all .2s; display:flex; align-items:center;
            justify-content:center; gap:8px;
        }
        .btn-reject:hover { transform:translateY(-2px); box-shadow:0 8px 22px rgba(239,68,68,.4); }
        .btn-back {
            padding:14px 22px; border:1.5px solid #e8eeff;
            border-radius:10px; background:#fff;
            color:#374151; font-size:14px; font-weight:600;
            cursor:pointer; font-family:'Inter',sans-serif; transition:all .2s;
        }
        .btn-back:hover { background:#f0f4ff; border-color:#bfdbfe; }

        @media(max-width:700px){
            .grid-2 { grid-template-columns:1fr; }
            .radio-group { flex-direction:column; }
            .btn-row { flex-direction:column; }
            .page-banner { flex-direction:column; gap:10px; }
            .banner-icon { display:none; }
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<div class="navbar">
    <div class="navbar-brand">
        <div class="brand-icon"><i class="fas fa-shield-alt"></i></div>
        <h2>VacciCare Admin</h2>
    </div>
    <div class="nav-links">
        <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
        <a href="manage_hospitals.php"><i class="fas fa-hospital"></i> Hospitals</a>
        <a href="../logout.php" class="logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</div>

<div class="page-wrap">

    <!-- BACK -->
    <a href="manage_hospitals.php" class="back-link">← Back to Hospitals</a>

    <!-- BANNER -->
    <div class="page-banner">
        <div class="banner-text">
            <h2>🏥 Hospital Verification</h2>
            <p>Review hospital details and approve or reject the registration</p>
        </div>
        <div class="banner-icon">🔍</div>
    </div>

    <?php if(isset($error)): ?>
    <div class="error-box"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
    <?php endif; ?>

    <?php if($already_verified): ?>
    <div class="verified-badge">
        <i class="fas fa-check-circle" style="font-size:20px;"></i>
        This hospital is already verified and active. No further action needed.
    </div>
    <?php endif; ?>

    <!-- TOP INFO GRID -->
    <div class="grid-2">

        <!-- Hospital Details -->
        <div class="card">
            <div class="card-header"><i class="fas fa-hospital"></i> Hospital Details</div>
            <div class="card-body">
                <div class="info-row">
                    <span class="info-label">Hospital Name</span>
                    <span class="info-value"><strong><?php echo htmlspecialchars($hospital['hospital_name']); ?></strong></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Reg. Number</span>
                    <span class="info-value"><?php echo htmlspecialchars($hospital['registration_number'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Contact Person</span>
                    <span class="info-value"><?php echo htmlspecialchars($hospital['contact_person'] ?? 'N/A'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Address</span>
                    <span class="info-value"><?php echo htmlspecialchars($hospital['address']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">City / State</span>
                    <span class="info-value"><?php echo htmlspecialchars($hospital['city'] . ', ' . $hospital['state']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Status</span>
                    <span class="info-value">
                        <?php if($hospital['is_verified']): ?>
                            <span class="badge badge-verified">✅ Verified</span>
                        <?php else: ?>
                            <span class="badge badge-pending">⏳ Pending</span>
                        <?php endif; ?>
                        &nbsp;
                        <?php if($hospital['is_active']): ?>
                            <span class="badge badge-active">Active</span>
                        <?php else: ?>
                            <span class="badge badge-inactive">Inactive</span>
                        <?php endif; ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Registered On</span>
                    <span class="info-value"><?php echo date('d M Y', strtotime($hospital['created_at'])); ?></span>
                </div>
            </div>
        </div>

        <!-- Contact & Stats -->
        <div style="display:flex; flex-direction:column; gap:16px;">
            <div class="card">
                <div class="card-header"><i class="fas fa-envelope"></i> Contact Info</div>
                <div class="card-body">
                    <div class="info-row">
                        <span class="info-label">Email</span>
                        <span class="info-value"><?php echo htmlspecialchars($hospital['email']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Phone</span>
                        <span class="info-value"><?php echo htmlspecialchars($hospital['phone'] ?? 'N/A'); ?></span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><i class="fas fa-chart-bar"></i> Activity</div>
                <div class="card-body" style="padding:16px 22px;">
                    <div class="stat-pills">
                        <div class="stat-pill">
                            <div class="num"><?php echo $total_bookings; ?></div>
                            <div class="lbl">Bookings</div>
                        </div>
                        <div class="stat-pill">
                            <div class="num"><?php echo $total_requests; ?></div>
                            <div class="lbl">Requests</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- end grid -->

    <!-- ── VERIFICATION FORM ── -->
    <?php if(!$already_verified): ?>
    <div class="form-card">
        <div class="section-title">⚡ Verification Action</div>

        <form method="POST" action="verify_hospital.php?id=<?php echo $hospital_id; ?>" id="verifyForm">

            <!-- Action Selection -->
            <div class="radio-group">
                <label class="radio-card approve-card" id="approveCard" onclick="selectAction('approve')">
                    <input type="radio" name="action" value="approve" id="approveRadio">
                    <span class="radio-icon">✅</span>
                    <div class="radio-text">
                        <div class="rt">Approve Hospital</div>
                        <div class="rs">Verify & activate this hospital account</div>
                    </div>
                </label>

                <label class="radio-card reject-card" id="rejectCard" onclick="selectAction('reject')">
                    <input type="radio" name="action" value="reject" id="rejectRadio">
                    <span class="radio-icon">❌</span>
                    <div class="radio-text">
                        <div class="rt">Reject Hospital</div>
                        <div class="rs">Decline this hospital registration</div>
                    </div>
                </label>
            </div>

            <!-- Reject Reason (shown only when reject selected) -->
            <div id="reject-reason-wrap">
                <label class="field-label">Rejection Reason <span style="color:#ef4444;">*</span></label>
                <input type="text" name="reject_reason" id="rejectReason"
                       placeholder="e.g. Invalid registration number, incomplete documents...">
            </div>

            <!-- Notes -->
            <label class="field-label" style="margin-top:16px;">Admin Notes (Optional)</label>
            <textarea name="notes" placeholder="Any additional notes for audit record..."></textarea>

            <!-- Notification Checkbox -->
            <div class="check-wrap">
                <input type="checkbox" name="send_notification" id="send_notification" value="1" checked>
                <label for="send_notification">
                    🔔 Send notification to hospital about this decision
                </label>
            </div>

            <!-- Buttons -->
            <div class="btn-row">
                <button type="button" class="btn-back" onclick="window.location.href='manage_hospitals.php'">
                    ← Cancel
                </button>
                <button type="submit" class="btn-approve" id="approveBtn" style="display:none;"
                        onclick="return confirm('Are you sure you want to VERIFY this hospital? This will activate their account.')">
                    ✅ Approve & Activate
                </button>
                <button type="submit" class="btn-reject" id="rejectBtn" style="display:none;"
                        onclick="return validateReject()">
                    ❌ Reject Hospital
                </button>
            </div>

        </form>
    </div>
    <?php else: ?>
    <!-- Already verified — just show back button -->
    <div class="form-card" style="text-align:center; padding:30px;">
        <p style="color:#6b7280; margin-bottom:20px;">This hospital is already verified. You can manage its status from the hospitals list.</p>
        <button class="btn-back" onclick="window.location.href='manage_hospitals.php'" style="padding:12px 28px;">
            ← Back to Hospitals
        </button>
    </div>
    <?php endif; ?>

</div><!-- end page-wrap -->

<script>
function selectAction(type) {
    // Reset cards
    document.getElementById('approveCard').classList.remove('selected');
    document.getElementById('rejectCard').classList.remove('selected');
    document.getElementById('approveBtn').style.display = 'none';
    document.getElementById('rejectBtn').style.display  = 'none';
    document.getElementById('reject-reason-wrap').style.display = 'none';
    document.getElementById('rejectReason').required = false;

    if(type === 'approve') {
        document.getElementById('approveCard').classList.add('selected');
        document.getElementById('approveRadio').checked = true;
        document.getElementById('approveBtn').style.display = 'flex';
    } else {
        document.getElementById('rejectCard').classList.add('selected');
        document.getElementById('rejectRadio').checked = true;
        document.getElementById('rejectBtn').style.display = 'flex';
        document.getElementById('reject-reason-wrap').style.display = 'block';
        document.getElementById('rejectReason').required = true;
    }
}

function validateReject() {
    const reason = document.getElementById('rejectReason').value.trim();
    if(!reason) {
        alert('Please enter a rejection reason!');
        return false;
    }
    return confirm('Are you sure you want to REJECT this hospital registration?');
}
</script>

</body>
</html>
<?php mysqli_close($connection); ?>