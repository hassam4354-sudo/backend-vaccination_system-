<?php
session_start();
if(!isset($_SESSION["logged_in"]) || $_SESSION["user_type"] != "parent"){
    header("location:../login.php");
    exit();
}

include("../dbconnection.php");

$user_id = $_SESSION["user_id"];

// Get parent info
$query_parent = "SELECT * FROM parents WHERE user_id = '$user_id'";
$result_parent = mysqli_query($connection, $query_parent);
$parent_data = mysqli_fetch_assoc($result_parent);
$parent_id = $parent_data['parent_id'];

// Get all appointment requests with details
$query_requests = "SELECT 
    ar.*,
    c.full_name as child_name,
    c.date_of_birth,
    v.vaccine_name,
    v.vaccine_code,
    h.hospital_name,
    h.city as hospital_city,
    a.full_name as admin_name,
    DATEDIFF(ar.preferred_date, CURDATE()) as days_remaining
FROM appointment_requests ar
JOIN children c ON ar.child_id = c.child_id
JOIN vaccines v ON ar.vaccine_id = v.vaccine_id
JOIN hospitals h ON ar.hospital_id = h.hospital_id
LEFT JOIN admins a ON ar.processed_by = a.admin_id
WHERE c.parent_id = '$parent_id'
ORDER BY 
    CASE 
        WHEN ar.request_status = 'pending' THEN 1
        WHEN ar.request_status = 'approved' THEN 2
        WHEN ar.request_status = 'rejected' THEN 3
        ELSE 4
    END,
    ar.preferred_date ASC";

$result_requests = mysqli_query($connection, $query_requests);

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total_requests,
    SUM(CASE WHEN request_status = 'pending' THEN 1 ELSE 0 END) as pending_count,
    SUM(CASE WHEN request_status = 'approved' THEN 1 ELSE 0 END) as approved_count,
    SUM(CASE WHEN request_status = 'rejected' THEN 1 ELSE 0 END) as rejected_count
FROM appointment_requests ar
JOIN children c ON ar.child_id = c.child_id
WHERE c.parent_id = '$parent_id'";

$stats_result = mysqli_query($connection, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Appointment Requests - Parent Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', Arial, sans-serif;
            background: #f0f4ff;
            color: #1a1a2e;
        }

        /* ── NAVBAR ── */
        .navbar {
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
        .navbar h2 {
            font-size: 20px;
            font-weight: 700;
            color: #1d4ed8;
            letter-spacing: -0.3px;
        }
        .nav-links { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
        .nav-links a {
            color: #4b6cb7;
            text-decoration: none;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 13.5px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .nav-links a:hover {
            background: #eff6ff;
            color: #1d4ed8;
        }
        .nav-links a.active-link {
            background: #dbeafe;
            color: #1d4ed8;
            font-weight: 600;
        }
        .nav-links a.logout {
            background: #fee2e2;
            color: #dc2626;
        }
        .nav-links a.logout:hover { background: #fecaca; }

        /* ── LAYOUT ── */
        .container { max-width: 1200px; margin: 32px auto; padding: 0 24px; }

        /* ── PAGE HEADER ── */
        .page-header {
            background: linear-gradient(135deg, #1d4ed8 0%, #3b82f6 60%, #60a5fa 100%);
            border-radius: 18px;
            padding: 32px 36px;
            margin-bottom: 28px;
            color: white;
            box-shadow: 0 8px 32px rgba(59,130,246,0.3);
            position: relative;
            overflow: hidden;
        }
        .page-header::before {
            content: '';
            position: absolute;
            top: -40px; right: -40px;
            width: 200px; height: 200px;
            background: rgba(255,255,255,0.08);
            border-radius: 50%;
        }
        .page-header::after {
            content: '';
            position: absolute;
            bottom: -60px; right: 80px;
            width: 160px; height: 160px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
        }
        .page-header h1 {
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 6px;
            position: relative;
            z-index: 1;
        }
        .page-header p {
            font-size: 14px;
            opacity: 0.85;
            position: relative;
            z-index: 1;
        }

        /* ── STATS GRID ── */
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
            display: flex;
            align-items: center;
            gap: 18px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(59,130,246,0.13);
        }
        .stat-icon {
            width: 58px;
            height: 58px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
            background: #eff6ff;
        }
        .stat-icon.green { background: #f0fdf4; }
        .stat-icon.amber { background: #fffbeb; }
        .stat-icon.red   { background: #fff1f2; }
        .stat-info h3 {
            font-size: 32px;
            font-weight: 700;
            color: #1d4ed8;
            line-height: 1;
            margin-bottom: 5px;
        }
        .stat-info p {
            font-size: 13px;
            color: #6b7280;
            font-weight: 500;
        }

        /* ── FILTER TABS ── */
        .filter-tabs {
            background: #ffffff;
            border-radius: 12px;
            padding: 8px;
            margin-bottom: 24px;
            display: inline-flex;
            gap: 6px;
            box-shadow: 0 2px 12px rgba(59,130,246,0.07);
            border: 1px solid #e8eeff;
        }
        .tab-btn {
            padding: 10px 24px;
            border: none;
            background: transparent;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13.5px;
            color: #6b7280;
            cursor: pointer;
            transition: all 0.2s;
            font-family: 'Inter', Arial, sans-serif;
        }
        .tab-btn:hover { color: #1d4ed8; background: #eff6ff; }
        .tab-btn.active {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            box-shadow: 0 4px 12px rgba(29,78,216,0.2);
        }

        /* ── REQUESTS GRID ── */
        .requests-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .request-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 2px 12px rgba(59,130,246,0.07);
            border: 1px solid #e8eeff;
            transition: transform 0.2s, box-shadow 0.2s;
            position: relative;
            overflow: hidden;
        }
        .request-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(59,130,246,0.13);
        }
        .request-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 5px;
        }
        .request-card.pending::before  { background: linear-gradient(90deg, #f59e0b, #fbbf24); }
        .request-card.approved::before { background: linear-gradient(90deg, #22c55e, #4ade80); }
        .request-card.rejected::before { background: linear-gradient(90deg, #ef4444, #f87171); }

        /* ── CARD HEADER ── */
        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 18px;
        }
        .child-avatar {
            width: 58px;
            height: 58px;
            border-radius: 14px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 26px;
        }
        .request-id {
            background: #eff6ff;
            padding: 6px 14px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 13px;
            color: #1d4ed8;
            border: 1px solid #dbeafe;
        }

        .child-name {
            font-size: 20px;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 6px;
        }
        .child-age {
            color: #6b7280;
            font-weight: 500;
            font-size: 13px;
            margin-bottom: 16px;
        }

        /* ── DETAIL ROWS ── */
        .request-details {
            background: #f8faff;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 18px;
            border: 1px solid #e8eeff;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #f1f5ff;
            font-size: 13.5px;
        }
        .detail-row:last-child { border-bottom: none; }
        .detail-label {
            color: #6b7280;
            font-weight: 500;
        }
        .detail-value {
            font-weight: 600;
            color: #1a1a2e;
            text-align: right;
        }

        /* ── BADGES ── */
        .status-badge {
            padding: 6px 16px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 14px;
        }
        .status-badge.pending  { background: #fef9c3; color: #854d0e; }
        .status-badge.approved { background: #dcfce7; color: #166534; }
        .status-badge.rejected { background: #fee2e2; color: #dc2626; }

        .dose-badge {
            background: #dbeafe;
            color: #1d4ed8;
            padding: 3px 12px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 12px;
        }

        .vaccine-code {
            font-family: monospace;
            background: #f1f5f9;
            padding: 2px 7px;
            border-radius: 5px;
            margin-left: 6px;
            font-size: 11px;
            color: #6b7280;
        }

        /* ── DAYS REMAINING ── */
        .days-remaining {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
            background: #eff6ff;
            color: #1d4ed8;
            margin-bottom: 14px;
        }
        .days-remaining.urgent {
            background: #fee2e2;
            color: #dc2626;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.04); }
        }

        /* ── ADMIN NOTES ── */
        .admin-notes {
            background: #f8faff;
            border-left: 4px solid #3b82f6;
            border-radius: 8px;
            padding: 14px 16px;
            margin: 14px 0;
            font-size: 13px;
            line-height: 1.7;
            color: #374151;
        }
        .admin-notes strong { color: #1d4ed8; }
        .admin-notes small  { color: #3b82f6; font-size: 12px; }

        /* ── ACTION BUTTONS ── */
        .request-actions {
            display: flex;
            gap: 10px;
            margin-top: 18px;
        }
        .btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 13.5px;
            cursor: pointer;
            transition: all 0.22s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            text-decoration: none;
            font-family: 'Inter', Arial, sans-serif;
        }
        .btn-view {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            box-shadow: 0 4px 12px rgba(29,78,216,0.2);
        }
        .btn-view:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(29,78,216,0.3);
        }
        .btn-cancel-req {
            background: #fff1f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        .btn-cancel-req:hover {
            background: #fee2e2;
            transform: translateY(-2px);
        }
        .btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
            transform: none !important;
        }

        /* ── EMPTY STATE ── */
        .empty-state {
            text-align: center;
            padding: 60px 40px;
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #e8eeff;
            box-shadow: 0 2px 12px rgba(59,130,246,0.07);
            color: #9ca3af;
        }
        .empty-state span { font-size: 52px; display: block; margin-bottom: 14px; }
        .empty-state h3 { font-size: 20px; color: #374151; margin-bottom: 8px; font-weight: 700; }
        .empty-state p { font-size: 14px; margin-bottom: 24px; }
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            padding: 12px 28px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.22s;
            box-shadow: 0 4px 14px rgba(29,78,216,0.2);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(29,78,216,0.3);
        }

        /* ── MODAL ── */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(15,23,42,0.45);
            backdrop-filter: blur(6px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        .modal-overlay.active { display: flex; }
        .modal {
            background: white;
            border-radius: 20px;
            padding: 36px;
            max-width: 460px;
            width: 90%;
            box-shadow: 0 24px 60px rgba(0,0,0,0.2);
            border: 1px solid #e8eeff;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 22px;
        }
        .modal-header h3 {
            font-size: 20px;
            font-weight: 700;
            color: #1a1a2e;
        }
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            color: #9ca3af;
            cursor: pointer;
            transition: color 0.2s;
        }
        .modal-close:hover { color: #dc2626; }
        .modal-body { text-align: center; margin-bottom: 24px; }
        .modal-icon {
            width: 72px;
            height: 72px;
            background: #fee2e2;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 18px;
            font-size: 34px;
        }
        .modal-body h4 { font-size: 18px; font-weight: 700; color: #1a1a2e; margin-bottom: 10px; }
        .modal-body p { color: #6b7280; font-size: 14px; line-height: 1.7; }
        .modal-body .warn-text { color: #dc2626; font-size: 13px; margin-top: 8px; }
        .modal-actions { display: flex; gap: 12px; }
        .btn-modal-keep {
            flex: 1;
            padding: 12px;
            background: #f8faff;
            color: #4b6cb7;
            border: 1.5px solid #e8eeff;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.2s;
            font-family: 'Inter', Arial, sans-serif;
        }
        .btn-modal-keep:hover { background: #eff6ff; }
        .btn-modal-cancel {
            flex: 1;
            padding: 12px;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all 0.22s;
            box-shadow: 0 4px 12px rgba(220,38,38,0.2);
        }
        .btn-modal-cancel:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(220,38,38,0.3); }

        /* ── RESPONSIVE ── */
        @media(max-width: 1024px) {
            .stats-container { grid-template-columns: repeat(2, 1fr); }
        }
        @media(max-width: 768px) {
            .requests-grid { grid-template-columns: 1fr; }
            .stats-container { grid-template-columns: repeat(2, 1fr); }
            .filter-tabs { flex-wrap: wrap; }
        }
        @media(max-width: 600px) {
            .navbar { padding: 0 16px; height: auto; padding: 12px 16px; flex-direction: column; gap: 12px; }
            .stats-container { grid-template-columns: 1fr 1fr; }
            .container { padding: 0 14px; }
        }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <div class="navbar">
        <h2> Parent_Panel</h2>
        <div class="nav-links">
            <a href="dashboard.php">Dashboard</a>
            <a href="my_children.php">My Children</a>
            <a href="book_appointment.php">Book</a>
            <a href="my_requests.php" class="active-link">My Requests</a>
            <a href="vaccination_history.php">History</a>
            <a href="myprofile.php">Profile</a>
            <a href="../logout.php" class="logout">Logout</a>
        </div>
    </div>

    <div class="container">

        <!-- PAGE HEADER -->
        <div class="page-header">
            <h1>📋 My Appointment Requests</h1>
            <p>Track the status of all your vaccination appointment requests</p>
        </div>

        <!-- STATS -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon">📅</div>
                <div class="stat-info">
                    <h3><?php echo $stats['total_requests'] ?? 0; ?></h3>
                    <p>Total Requests</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon amber">⏳</div>
                <div class="stat-info">
                    <h3><?php echo $stats['pending_count'] ?? 0; ?></h3>
                    <p>Pending</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green">✅</div>
                <div class="stat-info">
                    <h3><?php echo $stats['approved_count'] ?? 0; ?></h3>
                    <p>Approved</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon red">❌</div>
                <div class="stat-info">
                    <h3><?php echo $stats['rejected_count'] ?? 0; ?></h3>
                    <p>Rejected</p>
                </div>
            </div>
        </div>

        <!-- FILTER TABS -->
        <div class="filter-tabs">
            <button class="tab-btn active" onclick="filterRequests('all')" id="tabAll">All</button>
            <button class="tab-btn" onclick="filterRequests('pending')" id="tabPending">⏳ Pending</button>
            <button class="tab-btn" onclick="filterRequests('approved')" id="tabApproved">✅ Approved</button>
            <button class="tab-btn" onclick="filterRequests('rejected')" id="tabRejected">❌ Rejected</button>
        </div>

        <!-- REQUESTS GRID -->
        <?php if(mysqli_num_rows($result_requests) > 0): ?>
        <div class="requests-grid" id="requestsGrid">
            <?php while($request = mysqli_fetch_assoc($result_requests)): 
                $days_remaining = $request['days_remaining'];
                $is_urgent = ($days_remaining <= 2 && $days_remaining >= 0 && $request['request_status'] == 'approved');
                
                $age_days = floor((time() - strtotime($request['date_of_birth'])) / (60 * 60 * 24));
                $age_years = floor($age_days / 365);
                $age_months = floor(($age_days % 365) / 30);
                
                if($age_years > 0) {
                    $age_text = $age_years . " yr" . ($age_years > 1 ? "s" : "");
                    if($age_months > 0) $age_text .= " " . $age_months . " mo";
                } else {
                    $age_text = $age_months . " month" . ($age_months > 1 ? "s" : "");
                }
            ?>
            <div class="request-card <?php echo $request['request_status']; ?>" data-status="<?php echo $request['request_status']; ?>">

                <div class="request-header">
                    <div class="child-avatar">👶</div>
                    <span class="request-id">#<?php echo str_pad($request['request_id'], 5, '0', STR_PAD_LEFT); ?></span>
                </div>

                <div class="child-name"><?php echo htmlspecialchars($request['child_name']); ?></div>
                <div class="child-age">🎂 <?php echo $age_text; ?></div>

                <div class="request-details">
                    <div class="detail-row">
                        <span class="detail-label">💉 Vaccine</span>
                        <span class="detail-value">
                            <?php echo htmlspecialchars($request['vaccine_name']); ?>
                            <?php if($request['vaccine_code']): ?>
                                <span class="vaccine-code"><?php echo $request['vaccine_code']; ?></span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">🏥 Hospital</span>
                        <span class="detail-value">
                            <?php echo htmlspecialchars($request['hospital_name']); ?>
                            <span style="display:block;font-size:11px;color:#6b7280;">📍 <?php echo htmlspecialchars($request['hospital_city']); ?></span>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">📅 Date</span>
                        <span class="detail-value"><?php echo date('d M, Y', strtotime($request['preferred_date'])); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">🕐 Time</span>
                        <span class="detail-value"><?php echo date('h:i A', strtotime($request['preferred_time'])); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">💊 Dose</span>
                        <span class="detail-value">
                            <span class="dose-badge">Dose #<?php echo $request['dose_number']; ?></span>
                        </span>
                    </div>
                </div>

                <?php if($request['request_status'] == 'approved'): ?>
                <div class="days-remaining <?php echo $is_urgent ? 'urgent' : ''; ?>">
                    ⏳
                    <?php 
                    if($days_remaining < 0) echo "Overdue by " . abs($days_remaining) . " days";
                    elseif($days_remaining == 0) echo "Today!";
                    else echo $days_remaining . " days remaining";
                    ?>
                </div>
                <?php endif; ?>

                <div class="status-badge <?php echo $request['request_status']; ?>">
                    <?php
                    $icon = $request['request_status'] == 'pending' ? '⏳' : ($request['request_status'] == 'approved' ? '✅' : '❌');
                    echo $icon . ' ' . ucfirst($request['request_status']);
                    ?>
                </div>

                <?php if($request['admin_notes'] && $request['request_status'] != 'pending'): ?>
                <div class="admin-notes">
                    <strong>🛡️ Admin Notes:</strong>
                    <p style="margin-top:6px;"><?php echo nl2br(htmlspecialchars($request['admin_notes'])); ?></p>
                    <?php if($request['admin_name']): ?>
                    <small>— <?php echo htmlspecialchars($request['admin_name']); ?></small>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="request-actions">
                    <a href="request_details.php?id=<?php echo $request['request_id']; ?>" class="btn btn-view">
                        👁 View Details
                    </a>
                    <?php if($request['request_status'] == 'pending'): ?>
                    <button class="btn btn-cancel-req" onclick="showCancelModal(<?php echo $request['request_id']; ?>, '<?php echo htmlspecialchars($request['child_name'], ENT_QUOTES); ?>')">
                        ✕ Cancel
                    </button>
                    <?php else: ?>
                    <button class="btn btn-cancel-req" disabled>✕ Cancel</button>
                    <?php endif; ?>
                </div>

            </div>
            <?php endwhile; ?>
        </div>

        <?php else: ?>
        <div class="empty-state">
            <span>🗓️</span>
            <h3>No Appointment Requests Found</h3>
            <p>You haven't submitted any appointment requests yet. Book your child's vaccination now!</p>
            <a href="book_appointment.php" class="btn-primary">📅 Book Appointment</a>
        </div>
        <?php endif; ?>

    </div>

    <!-- CANCEL MODAL -->
    <div class="modal-overlay" id="cancelModal">
        <div class="modal">
            <div class="modal-header">
                <h3>⚠️ Cancel Request</h3>
                <button class="modal-close" onclick="closeModal()">×</button>
            </div>
            <div class="modal-body">
                <div class="modal-icon">❓</div>
                <h4>Are you sure?</h4>
                <p>You are about to cancel the appointment request for <strong id="cancelChildName"></strong>.</p>
                <p class="warn-text">⚠️ This action cannot be undone.</p>
            </div>
            <div class="modal-actions">
                <button class="btn-modal-keep" onclick="closeModal()">✕ No, Keep</button>
                <a href="#" id="cancelLink" class="btn-modal-cancel">✓ Yes, Cancel</a>
            </div>
        </div>
    </div>

    <script>
        function filterRequests(status) {
            const cards = document.querySelectorAll('.request-card');
            const tabs  = document.querySelectorAll('.tab-btn');

            tabs.forEach(t => t.classList.remove('active'));
            document.getElementById('tab' + status.charAt(0).toUpperCase() + status.slice(1)).classList.add('active');

            cards.forEach(card => {
                card.style.display = (status === 'all' || card.dataset.status === status) ? 'block' : 'none';
            });

            const visible = Array.from(cards).filter(c => c.style.display !== 'none');
            const existing = document.getElementById('noResults');
            if(visible.length === 0 && document.querySelector('.requests-grid')) {
                if(!existing) {
                    const el = document.createElement('div');
                    el.id = 'noResults';
                    el.className = 'empty-state';
                    el.innerHTML = `<span>🔍</span><h3>No ${status} requests</h3><p>You don't have any ${status} requests at the moment.</p>`;
                    document.querySelector('.requests-grid').after(el);
                }
            } else if(existing) { existing.remove(); }
        }

        function showCancelModal(requestId, childName) {
            document.getElementById('cancelChildName').textContent = childName;
            document.getElementById('cancelLink').href = 'cancel_request.php?id=' + requestId;
            document.getElementById('cancelModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('cancelModal').classList.remove('active');
        }

        window.onclick = function(e) {
            if(e.target === document.getElementById('cancelModal')) closeModal();
        }

        setTimeout(() => location.reload(), 30000);

        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const status = urlParams.get('status');
            if(status && ['pending','approved','rejected'].includes(status)) filterRequests(status);
        });
    </script>
</body>
</html>
<?php mysqli_close($connection); ?>