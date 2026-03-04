<?php
session_start();
if(!isset($_SESSION["logged_in"]) || $_SESSION["user_type"] != "hospital"){
    header("location:../login.php");
    exit();
}

include("../dbconnection.php");

$user_id = $_SESSION["user_id"];

// Get hospital data
$query_hospital = "SELECT * FROM hospitals WHERE user_id = '$user_id'";
$result_hospital = mysqli_query($connection, $query_hospital);
$hospital = mysqli_fetch_assoc($result_hospital);
$hospital_id = $hospital['hospital_id'] ?? 0;

// ── STATS ──
// Total appointment requests for this hospital
$q_total = "SELECT COUNT(*) as cnt FROM appointment_requests WHERE hospital_id = '$hospital_id'";
$total_requests = mysqli_fetch_assoc(mysqli_query($connection, $q_total))['cnt'];

// Pending requests
$q_pending = "SELECT COUNT(*) as cnt FROM appointment_requests WHERE hospital_id = '$hospital_id' AND request_status = 'pending'";
$pending_requests = mysqli_fetch_assoc(mysqli_query($connection, $q_pending))['cnt'];

// Approved / completed
$q_approved = "SELECT COUNT(*) as cnt FROM appointment_requests WHERE hospital_id = '$hospital_id' AND request_status = 'approved'";
$approved_requests = mysqli_fetch_assoc(mysqli_query($connection, $q_approved))['cnt'];

// Today's bookings
$today = date('Y-m-d');
$q_today = "SELECT COUNT(*) as cnt FROM vaccination_bookings WHERE hospital_id = '$hospital_id' AND appointment_date = '$today'";
$today_bookings = mysqli_fetch_assoc(mysqli_query($connection, $q_today))['cnt'];

// Total completed vaccinations
$q_completed = "SELECT COUNT(*) as cnt FROM vaccination_bookings WHERE hospital_id = '$hospital_id' AND booking_status = 'completed'";
$completed_vaccinations = mysqli_fetch_assoc(mysqli_query($connection, $q_completed))['cnt'];

// Rejected requests
$q_rejected = "SELECT COUNT(*) as cnt FROM appointment_requests WHERE hospital_id = '$hospital_id' AND request_status = 'rejected'";
$rejected_requests = mysqli_fetch_assoc(mysqli_query($connection, $q_rejected))['cnt'];

// ── RECENT PENDING REQUESTS (latest 5) ──
$q_recent = "SELECT ar.*, c.full_name as child_name, c.date_of_birth, c.gender,
                    v.vaccine_name, p.full_name as parent_name
             FROM appointment_requests ar
             JOIN children c ON ar.child_id = c.child_id
             JOIN vaccines v ON ar.vaccine_id = v.vaccine_id
             JOIN parents p ON c.parent_id = p.parent_id
             WHERE ar.hospital_id = '$hospital_id' AND ar.request_status = 'pending'
             ORDER BY ar.created_at DESC LIMIT 5";
$result_recent = mysqli_query($connection, $q_recent);

// ── TODAY'S SCHEDULE ──
$q_schedule = "SELECT vb.*, c.full_name as child_name, v.vaccine_name, p.full_name as parent_name
               FROM vaccination_bookings vb
               JOIN children c ON vb.child_id = c.child_id
               JOIN vaccines v ON vb.vaccine_id = v.vaccine_id
               JOIN parents p ON c.parent_id = p.parent_id
               WHERE vb.hospital_id = '$hospital_id' AND vb.appointment_date = '$today'
               ORDER BY vb.appointment_time ASC";
$result_schedule = mysqli_query($connection, $q_schedule);

// ── UPCOMING 7 DAYS ──
$q_upcoming = "SELECT COUNT(*) as cnt FROM vaccination_bookings 
               WHERE hospital_id = '$hospital_id' 
               AND appointment_date BETWEEN '$today' AND DATE_ADD('$today', INTERVAL 7 DAY)
               AND booking_status = 'scheduled'";
$upcoming_7days = mysqli_fetch_assoc(mysqli_query($connection, $q_upcoming))['cnt'];

// Verification status
$is_verified = $hospital['is_verified'] ?? 0;
$is_active   = $hospital['is_active'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Dashboard — VacciCare</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@700;800&display=swap');

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f0f4ff;
            color: #0a1628;
            min-height: 100vh;
        }

        /* ══════════════════════════════
           NAVBAR
        ══════════════════════════════ */
        .navbar {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 200;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(18px);
            border-bottom: 1px solid #e2e8f0;
            padding: 0 40px;
            height: 68px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 12px rgba(37,99,235,0.08);
        }
        .nav-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 800;
            font-size: 18px;
            color: #1a3a6e;
            text-decoration: none;
        }
        .nav-logo .logo-icon {
            width: 40px; height: 40px;
            background: #2563eb;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px;
            box-shadow: 0 4px 14px rgba(37,99,235,0.35);
        }
        .nav-links {
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .nav-link {
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 8px 14px;
            border-radius: 8px;
            color: #334155;
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 600;
            transition: all 0.2s;
            position: relative;
        }
        .nav-link:hover { background: #eff6ff; color: #2563eb; }
        .nav-link.active {
            background: #eff6ff;
            color: #2563eb;
        }
        .nav-link .link-icon { font-size: 15px; }
        .nav-badge {
            background: #ef4444;
            color: white;
            font-size: 10px;
            font-weight: 700;
            padding: 1px 6px;
            border-radius: 20px;
            min-width: 18px;
            text-align: center;
        }
        .nav-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .nav-hospital-chip {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 7px 14px;
            font-size: 13px;
            font-weight: 600;
            color: #0a1628;
        }
        .nav-dot { width: 7px; height: 7px; border-radius: 50%; }
        .dot-green  { background: #4ade80; }
        .dot-yellow { background: #facc15; }
        .dot-red    { background: #f87171; }

        .nav-logout {
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 8px 16px;
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s;
        }
        .nav-logout:hover { background: #dc2626; color: white; }

        /* Mobile hamburger */
        .hamburger {
            display: none;
            flex-direction: column;
            gap: 5px;
            cursor: pointer;
            padding: 6px;
        }
        .hamburger span {
            width: 22px; height: 2px;
            background: #334155;
            border-radius: 2px;
            transition: all 0.3s;
        }
        .mobile-menu {
            display: none;
            position: fixed;
            top: 68px; left: 0; right: 0;
            background: white;
            border-bottom: 1px solid #e2e8f0;
            padding: 12px 20px;
            z-index: 199;
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }
        .mobile-menu.open { display: block; }
        .mobile-menu .nav-link {
            display: flex;
            padding: 10px 14px;
            margin-bottom: 4px;
        }

        /* ══════════════════════════════
           MAIN CONTENT
        ══════════════════════════════ */
        .main {
            margin-left: 0;
            padding-top: 68px;
            min-height: 100vh;
        }

        /* ── VERIFICATION BANNER ── */
        .verify-banner {
            margin: 24px 32px 0;
            padding: 14px 20px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            font-weight: 500;
        }
        .verify-banner.pending {
            background: #fef9c3;
            border: 1px solid #fde68a;
            color: #92400e;
        }
        .verify-banner.verified {
            background: #dcfce7;
            border: 1px solid #bbf7d0;
            color: #166534;
        }
        .verify-banner.inactive {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        /* ── CONTENT AREA ── */
        .content { padding: 24px 32px 40px; }

        /* ── STATS GRID ── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-bottom: 28px;
        }
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 22px 24px;
            border: 1px solid #e8eeff;
            box-shadow: 0 2px 10px rgba(59,130,246,0.06);
            display: flex;
            align-items: center;
            gap: 18px;
            transition: all 0.22s;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 28px rgba(59,130,246,0.13);
        }
        .stat-icon {
            width: 54px; height: 54px;
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            font-size: 26px;
            flex-shrink: 0;
        }
        .icon-blue   { background: linear-gradient(135deg, #dbeafe, #bfdbfe); }
        .icon-yellow { background: linear-gradient(135deg, #fef9c3, #fde68a); }
        .icon-green  { background: linear-gradient(135deg, #dcfce7, #bbf7d0); }
        .icon-red    { background: linear-gradient(135deg, #fee2e2, #fecaca); }
        .icon-purple { background: linear-gradient(135deg, #ede9fe, #ddd6fe); }
        .icon-teal   { background: linear-gradient(135deg, #ccfbf1, #99f6e4); }

        .stat-info { flex: 1; }
        .stat-value {
            font-size: 30px;
            font-weight: 800;
            color: #0a1628;
            line-height: 1;
            margin-bottom: 4px;
            font-family: 'Playfair Display', serif;
        }
        .stat-label {
            font-size: 12.5px;
            color: #6b7280;
            font-weight: 500;
        }
        .stat-sub {
            font-size: 11px;
            color: #9ca3af;
            margin-top: 3px;
        }

        /* ── BOTTOM GRID ── */
        .bottom-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 22px;
        }

        /* ── CARDS ── */
        .card {
            background: white;
            border-radius: 16px;
            border: 1px solid #e8eeff;
            box-shadow: 0 2px 10px rgba(59,130,246,0.06);
            overflow: hidden;
        }
        .card-header {
            padding: 18px 22px;
            border-bottom: 1px solid #f1f5ff;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .card-title {
            font-size: 15px;
            font-weight: 700;
            color: #0a1628;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .card-link {
            font-size: 12.5px;
            color: #3b82f6;
            text-decoration: none;
            font-weight: 600;
        }
        .card-link:hover { text-decoration: underline; }
        .card-body { padding: 0; }

        /* ── REQUEST ROW ── */
        .req-row {
            padding: 14px 22px;
            border-bottom: 1px solid #f4f6ff;
            display: flex;
            align-items: center;
            gap: 14px;
            transition: background 0.15s;
        }
        .req-row:last-child { border-bottom: none; }
        .req-row:hover { background: #f8faff; }
        .req-avatar {
            width: 40px; height: 40px;
            background: linear-gradient(135deg, #dbeafe, #bfdbfe);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }
        .req-info { flex: 1; min-width: 0; }
        .req-name {
            font-size: 14px;
            font-weight: 600;
            color: #1a1a2e;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .req-meta {
            font-size: 11.5px;
            color: #9ca3af;
            margin-top: 2px;
        }
        .req-right { text-align: right; flex-shrink: 0; }
        .req-date {
            font-size: 12px;
            font-weight: 600;
            color: #374151;
        }
        .req-time {
            font-size: 11px;
            color: #9ca3af;
            margin-top: 2px;
        }

        /* ── SCHEDULE ROW ── */
        .sched-row {
            padding: 13px 22px;
            border-bottom: 1px solid #f4f6ff;
            display: flex;
            align-items: center;
            gap: 14px;
            transition: background 0.15s;
        }
        .sched-row:last-child { border-bottom: none; }
        .sched-row:hover { background: #f8faff; }
        .sched-time {
            background: linear-gradient(135deg, #dbeafe, #bfdbfe);
            color: #1d4ed8;
            padding: 8px 12px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 700;
            text-align: center;
            min-width: 62px;
            flex-shrink: 0;
        }
        .sched-info { flex: 1; min-width: 0; }
        .sched-name {
            font-size: 13.5px;
            font-weight: 600;
            color: #1a1a2e;
        }
        .sched-meta {
            font-size: 11.5px;
            color: #9ca3af;
            margin-top: 2px;
        }

        /* ── BADGES ── */
        .badge {
            display: inline-block;
            padding: 4px 11px;
            border-radius: 20px;
            font-size: 11.5px;
            font-weight: 600;
        }
        .badge-pending   { background: #fef9c3; color: #92400e; }
        .badge-approved  { background: #dcfce7; color: #166534; }
        .badge-rejected  { background: #fee2e2; color: #991b1b; }
        .badge-completed { background: #dbeafe; color: #1e40af; }
        .badge-scheduled { background: #ede9fe; color: #5b21b6; }

        /* ── EMPTY STATE ── */
        .empty {
            padding: 36px 22px;
            text-align: center;
            color: #9ca3af;
        }
        .empty span { font-size: 40px; display: block; margin-bottom: 10px; }
        .empty p { font-size: 13.5px; }

        /* ── QUICK ACTIONS ── */
        .quick-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            padding: 18px 22px;
        }
        .qa-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 14px 16px;
            border-radius: 12px;
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 600;
            transition: all 0.2s;
            border: 1.5px solid transparent;
        }
        .qa-btn .qa-icon { font-size: 20px; }
        .qa-btn.blue   { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
        .qa-btn.green  { background: #f0fdf4; color: #166534; border-color: #bbf7d0; }
        .qa-btn.yellow { background: #fefce8; color: #92400e; border-color: #fde68a; }
        .qa-btn.purple { background: #faf5ff; color: #5b21b6; border-color: #ddd6fe; }
        .qa-btn:hover  { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(0,0,0,0.08); }

        /* ── RESPONSIVE ── */
        @media(max-width: 1100px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .nav-links { gap: 2px; }
            .nav-link { padding: 7px 10px; font-size: 12.5px; }
        }
        @media(max-width: 860px) {
            .nav-links { display: none; }
            .hamburger { display: flex; }
            .nav-hospital-chip { display: none; }
            .bottom-grid { grid-template-columns: 1fr; }
        }
        @media(max-width: 600px) {
            .navbar { padding: 0 20px; }
            .stats-grid { grid-template-columns: 1fr 1fr; }
            .content { padding: 16px 20px 32px; }
        }
        @media(max-width: 420px) {
            .stats-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- ══════════════════════════════
     NAVBAR
══════════════════════════════ -->
<nav class="navbar">

    <a href="dashboard.php" class="nav-logo">
        
        Hospital_Panel
    </a>

    <div class="nav-links">
        <a href="dashboard.php" class="nav-link active">
            Dashboard
        </a>
        <a href="appointment_requests.php" class="nav-link">
           Requests
            <?php if($pending_requests > 0): ?>
            <span class="nav-badge"><?php echo $pending_requests; ?></span>
            <?php endif; ?>
        </a>
        <a href="todays_schedule.php" class="nav-link">
             Today's Schedule
            <?php if($today_bookings > 0): ?>
            <span class="nav-badge"><?php echo $today_bookings; ?></span>
            <?php endif; ?>
        </a>
        <a href="vaccination_bookings.php" class="nav-link">
             Bookings
        </a>
        <a href="vaccine_inventory.php" class="nav-link">
           Inventory
        </a>
        <a href="doctors.php" class="nav-link">
       Doctors
        </a>
        <a href="vaccination_records.php" class="nav-link">
            Records
        </a>
        <a href="my_profile.php" class="nav-link">
     Profile
        </a>
    </div>

    <div class="nav-right">
        <div class="nav-hospital-chip">
            <span class="nav-dot
                <?php if($is_verified && $is_active): ?>dot-green
                <?php elseif($is_verified): ?>dot-red
                <?php else: ?>dot-yellow<?php endif; ?>">
            </span>
            <?php echo htmlspecialchars($hospital['hospital_name'] ?? 'Hospital'); ?>
        </div>
        <a href="../logout.php" class="nav-logout">🚪 Logout</a>
        <div class="hamburger" onclick="toggleMenu()">
            <span></span><span></span><span></span>
        </div>
    </div>

</nav>

<!-- Mobile Menu -->
<div class="mobile-menu" id="mobileMenu">
    <a href="dashboard.php" class="nav-link active"> Dashboard</a>
    <a href="appointment_requests.php" class="nav-link"> Requests <?php if($pending_requests > 0): ?><span class="nav-badge"><?php echo $pending_requests; ?></span><?php endif; ?></a>
    <a href="todays_schedule.php" class="nav-link"> Today's Schedule</a>
    <a href="vaccination_bookings.php" class="nav-link"> Bookings</a>
    <a href="vaccine_inventory.php" class="nav-link"> Inventory</a>
    <a href="doctors.php" class="nav-link"> Doctors</a>
    <a href="vaccination_records.php" class="nav-link"> Records</a>
    <a href="my_profile.php" class="nav-link"> Profile</a>
    <a href="../logout.php" class="nav-logout" style="margin-top:8px; display:inline-flex;"> Logout</a>
</div>

<!-- ══════════════════════════════
     MAIN CONTENT
══════════════════════════════ -->
<div class="main">

    <!-- VERIFICATION BANNER -->
    <?php if(!$is_verified): ?>
    <div class="verify-banner pending">
        ⏳ <strong>Pending Verification:</strong> Aapka hospital account admin verification ka wait kar raha hai. Verify hone ke baad puri functionality available ho gi.
    </div>
    <?php elseif(!$is_active): ?>
    <div class="verify-banner inactive">
        🚫 <strong>Account Inactive:</strong> Aapka hospital account currently inactive hai. Admin se rabta karein.
    </div>
    <?php else: ?>
    <div class="verify-banner verified">
        ✅ <strong>Verified Hospital:</strong> Aapka account verified aur active hai. Sab features available hain!
    </div>
    <?php endif; ?>

    <!-- CONTENT -->
    <div class="content">

        <!-- Page Header -->
        <div style="margin-bottom: 24px;">
            <h1 style="font-family:'Playfair Display',serif; font-size:26px; font-weight:800; color:#0a1628;">Dashboard</h1>
            <p style="font-size:13px; color:#9ca3af; margin-top:4px;">Welcome back, <?php echo htmlspecialchars($hospital['hospital_name'] ?? 'Hospital'); ?> &nbsp;·&nbsp; 📅 <?php echo date('D, d M Y'); ?></p>
        </div>

        <!-- ── STATS GRID ── -->
        <div class="stats-grid">

            <div class="stat-card">
                <div class="stat-icon icon-yellow">📋</div>
                <div class="stat-info">
                    <div class="stat-value"><?php echo $pending_requests; ?></div>
                    <div class="stat-label">Pending Requests</div>
                    <div class="stat-sub">Awaiting your action</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon icon-blue">📅</div>
                <div class="stat-info">
                    <div class="stat-value"><?php echo $today_bookings; ?></div>
                    <div class="stat-label">Today's Appointments</div>
                    <div class="stat-sub"><?php echo date('d M Y'); ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon icon-green">✅</div>
                <div class="stat-info">
                    <div class="stat-value"><?php echo $completed_vaccinations; ?></div>
                    <div class="stat-label">Completed Vaccinations</div>
                    <div class="stat-sub">Total till date</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon icon-blue">💉</div>
                <div class="stat-info">
                    <div class="stat-value"><?php echo $total_requests; ?></div>
                    <div class="stat-label">Total Requests</div>
                    <div class="stat-sub">All time</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon icon-purple">📆</div>
                <div class="stat-info">
                    <div class="stat-value"><?php echo $upcoming_7days; ?></div>
                    <div class="stat-label">Upcoming (7 Days)</div>
                    <div class="stat-sub">Scheduled bookings</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon icon-red">❌</div>
                <div class="stat-info">
                    <div class="stat-value"><?php echo $rejected_requests; ?></div>
                    <div class="stat-label">Rejected Requests</div>
                    <div class="stat-sub">Total rejected</div>
                </div>
            </div>

        </div>

        <!-- ── BOTTOM GRID ── -->
        <div class="bottom-grid">

            <!-- Pending Requests -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">⏳ Pending Requests</div>
                    <a href="appointment_requests.php" class="card-link">View All →</a>
                </div>
                <div class="card-body">
                    <?php if(mysqli_num_rows($result_recent) > 0): ?>
                        <?php while($req = mysqli_fetch_assoc($result_recent)):
                            $age_days = floor((time() - strtotime($req['date_of_birth'])) / 86400);
                            $age_m = floor($age_days / 30);
                            $age_y = floor($age_days / 365);
                            $age = $age_y > 0 ? "{$age_y}y {$age_m}m" : "{$age_m}m";
                        ?>
                        <div class="req-row">
                            <div class="req-avatar">
                                <?php echo $req['gender'] == 'Female' ? '👧' : '👦'; ?>
                            </div>
                            <div class="req-info">
                                <div class="req-name"><?php echo htmlspecialchars($req['child_name']); ?></div>
                                <div class="req-meta">
                                    💉 <?php echo htmlspecialchars($req['vaccine_name']); ?> · Dose <?php echo $req['dose_number']; ?>
                                    · <?php echo $age; ?>
                                </div>
                                <div class="req-meta">👤 <?php echo htmlspecialchars($req['parent_name']); ?></div>
                            </div>
                            <div class="req-right">
                                <div class="req-date"><?php echo date('d M', strtotime($req['preferred_date'])); ?></div>
                                <div class="req-time"><?php echo $req['preferred_time'] ? date('h:i A', strtotime($req['preferred_time'])) : '—'; ?></div>
                                <span class="badge badge-pending" style="margin-top:4px;">Pending</span>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="empty">
                            <span>✅</span>
                            <p>No pending requests!</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right column -->
            <div style="display:flex; flex-direction:column; gap:22px;">

                <!-- Today's Schedule -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">📅 Today's Schedule</div>
                        <a href="todays_schedule.php" class="card-link">Full Schedule →</a>
                    </div>
                    <div class="card-body">
                        <?php if(mysqli_num_rows($result_schedule) > 0): ?>
                            <?php while($sched = mysqli_fetch_assoc($result_schedule)): ?>
                            <div class="sched-row">
                                <div class="sched-time">
                                    <?php echo date('h:i', strtotime($sched['appointment_time'])); ?><br>
                                    <span style="font-size:10px; font-weight:500;"><?php echo date('A', strtotime($sched['appointment_time'])); ?></span>
                                </div>
                                <div class="sched-info">
                                    <div class="sched-name"><?php echo htmlspecialchars($sched['child_name']); ?></div>
                                    <div class="sched-meta">💉 <?php echo htmlspecialchars($sched['vaccine_name']); ?> · 👤 <?php echo htmlspecialchars($sched['parent_name']); ?></div>
                                </div>
                                <span class="badge badge-<?php echo $sched['booking_status']; ?>">
                                    <?php echo ucfirst($sched['booking_status']); ?>
                                </span>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="empty">
                                <span>🗓️</span>
                                <p>No appointments today</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">⚡ Quick Actions</div>
                    </div>
                    <div class="quick-actions">
                        <a href="appointment_requests.php" class="qa-btn blue">
                            <span class="qa-icon">📋</span> View Requests
                        </a>
                        <a href="todays_schedule.php" class="qa-btn green">
                            <span class="qa-icon">📅</span> Today's List
                        </a>
                        <a href="vaccine_inventory.php" class="qa-btn yellow">
                            <span class="qa-icon">🧪</span> Inventory
                        </a>
                        <a href="vaccination_records.php" class="qa-btn purple">
                            <span class="qa-icon">📁</span> Records
                        </a>
                    </div>
                </div>

            </div><!-- end right col -->

        </div><!-- end bottom-grid -->

    </div><!-- end content -->
</div><!-- end main -->

<script>
function toggleMenu() {
    document.getElementById('mobileMenu').classList.toggle('open');
}
</script>

</body>
</html>