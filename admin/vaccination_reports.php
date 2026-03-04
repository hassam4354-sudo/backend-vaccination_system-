<?php
session_start();
if (!isset($_SESSION["logged_in"]) || $_SESSION["user_type"] != "admin") {
    header("location: ../login.php");
    exit();
}

include("../dbconnection.php");

// Get admin details
$user_id = $_SESSION["user_id"];
$query_admin = "SELECT full_name FROM admins WHERE user_id = '$user_id'";
$result_admin = mysqli_query($connection, $query_admin);
$admin_data = mysqli_fetch_assoc($result_admin);
$admin_name = $admin_data['full_name'];

// Get filter values
$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'overview';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : date('Y-m-d', strtotime('-30 days'));
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : date('Y-m-d');
$hospital_filter = isset($_GET['hospital_id']) ? intval($_GET['hospital_id']) : 0;
$vaccine_filter = isset($_GET['vaccine_id']) ? intval($_GET['vaccine_id']) : 0;

// Get all hospitals for filter
$hospitals = mysqli_query($connection, "SELECT hospital_id, hospital_name FROM hospitals ORDER BY hospital_name");

// Get all vaccines for filter
$vaccines = mysqli_query($connection, "SELECT vaccine_id, vaccine_name FROM vaccines WHERE is_active = 1 ORDER BY vaccine_name");

// ========== REPORT FUNCTIONS ==========

// 1. OVERVIEW REPORT
function getOverviewReport($conn, $from, $to) {
    $report = [];
    
    // Total bookings
    $q = "SELECT COUNT(*) as total FROM vaccination_bookings WHERE created_at BETWEEN '$from' AND '$to 23:59:59'";
    $r = mysqli_query($conn, $q);
    $report['total_bookings'] = mysqli_fetch_assoc($r)['total'];
    
    // Completed vaccinations
    $q = "SELECT COUNT(*) as total FROM vaccination_records WHERE vaccination_date BETWEEN '$from' AND '$to'";
    $r = mysqli_query($conn, $q);
    $report['completed_vaccinations'] = mysqli_fetch_assoc($r)['total'];
    
    // Pending requests
    $q = "SELECT COUNT(*) as total FROM appointment_requests WHERE request_status = 'pending' AND created_at BETWEEN '$from' AND '$to 23:59:59'";
    $r = mysqli_query($conn, $q);
    $report['pending_requests'] = mysqli_fetch_assoc($r)['total'];
    
    // New registrations (parents)
    $q = "SELECT COUNT(*) as total FROM parents WHERE created_at BETWEEN '$from' AND '$to 23:59:59'";
    $r = mysqli_query($conn, $q);
    $report['new_parents'] = mysqli_fetch_assoc($r)['total'];
    
    // New children registered
    $q = "SELECT COUNT(*) as total FROM children WHERE created_at BETWEEN '$from' AND '$to 23:59:59'";
    $r = mysqli_query($conn, $q);
    $report['new_children'] = mysqli_fetch_assoc($r)['total'];
    
    return $report;
}

// 2. VACCINE WISE REPORT
function getVaccineWiseReport($conn, $from, $to) {
    $query = "SELECT 
                v.vaccine_id,
                v.vaccine_name,
                v.vaccine_code,
                COUNT(DISTINCT vb.booking_id) as total_bookings,
                COUNT(DISTINCT vr.record_id) as administered_count,
                COUNT(DISTINCT CASE WHEN ar.request_status = 'pending' THEN ar.request_id END) as pending_requests,
                COUNT(DISTINCT CASE WHEN vb.booking_status = 'completed' THEN vb.booking_id END) as completed_bookings,
                COUNT(DISTINCT CASE WHEN vb.booking_status = 'cancelled' THEN vb.booking_id END) as cancelled_bookings
              FROM vaccines v
              LEFT JOIN appointment_requests ar ON v.vaccine_id = ar.vaccine_id AND ar.created_at BETWEEN '$from' AND '$to 23:59:59'
              LEFT JOIN vaccination_bookings vb ON v.vaccine_id = vb.vaccine_id AND vb.created_at BETWEEN '$from' AND '$to 23:59:59'
              LEFT JOIN vaccination_records vr ON v.vaccine_id = vr.vaccine_id AND vr.vaccination_date BETWEEN '$from' AND '$to'
              WHERE v.is_active = 1
              GROUP BY v.vaccine_id
              ORDER BY administered_count DESC";
    
    return mysqli_query($conn, $query);
}

// 3. HOSPITAL WISE REPORT
function getHospitalWiseReport($conn, $from, $to, $hospital_id = 0) {
    $where = "";
    if ($hospital_id > 0) {
        $where = " AND h.hospital_id = $hospital_id";
    }
    
    $query = "SELECT 
                h.hospital_id,
                h.hospital_name,
                h.city,
                COUNT(DISTINCT vb.booking_id) as total_bookings,
                COUNT(DISTINCT vr.record_id) as vaccinations_done,
                COUNT(DISTINCT CASE WHEN ar.request_status = 'pending' THEN ar.request_id END) as pending_requests,
                COUNT(DISTINCT CASE WHEN ar.request_status = 'approved' THEN ar.request_id END) as approved_requests,
                COUNT(DISTINCT u.user_id) as hospital_staff
              FROM hospitals h
              LEFT JOIN appointment_requests ar ON h.hospital_id = ar.hospital_id AND ar.created_at BETWEEN '$from' AND '$to 23:59:59'
              LEFT JOIN vaccination_bookings vb ON h.hospital_id = vb.hospital_id AND vb.created_at BETWEEN '$from' AND '$to 23:59:59'
              LEFT JOIN vaccination_records vr ON h.hospital_id = vr.hospital_id AND vr.vaccination_date BETWEEN '$from' AND '$to'
              LEFT JOIN users u ON h.user_id = u.user_id
              WHERE h.is_active = 1 $where
              GROUP BY h.hospital_id
              ORDER BY vaccinations_done DESC";
    
    return mysqli_query($conn, $query);
}

// 4. CHILDREN AGE GROUP REPORT
function getAgeGroupReport($conn) {
    $today = date('Y-m-d');
    
    $query = "SELECT 
                CASE 
                    WHEN TIMESTAMPDIFF(MONTH, date_of_birth, '$today') <= 1 THEN '0-1 Month'
                    WHEN TIMESTAMPDIFF(MONTH, date_of_birth, '$today') <= 3 THEN '1-3 Months'
                    WHEN TIMESTAMPDIFF(MONTH, date_of_birth, '$today') <= 6 THEN '3-6 Months'
                    WHEN TIMESTAMPDIFF(MONTH, date_of_birth, '$today') <= 12 THEN '6-12 Months'
                    WHEN TIMESTAMPDIFF(YEAR, date_of_birth, '$today') <= 2 THEN '1-2 Years'
                    WHEN TIMESTAMPDIFF(YEAR, date_of_birth, '$today') <= 5 THEN '2-5 Years'
                    ELSE '5+ Years'
                END as age_group,
                COUNT(*) as total_children,
                SUM(CASE WHEN gender = 'Male' THEN 1 ELSE 0 END) as boys,
                SUM(CASE WHEN gender = 'Female' THEN 1 ELSE 0 END) as girls
              FROM children 
              WHERE is_active = 1
              GROUP BY age_group
              ORDER BY FIELD(age_group, 
                  '0-1 Month', '1-3 Months', '3-6 Months', '6-12 Months', 
                  '1-2 Years', '2-5 Years', '5+ Years')";
    
    return mysqli_query($conn, $query);
}

// 5. DAILY TREND REPORT
function getDailyTrendReport($conn, $from, $to) {
    $query = "SELECT 
                DATE(vaccination_date) as date,
                COUNT(*) as vaccinations,
                COUNT(DISTINCT hospital_id) as hospitals_active,
                COUNT(DISTINCT vaccine_id) as vaccines_used
              FROM vaccination_records
              WHERE vaccination_date BETWEEN '$from' AND '$to'
              GROUP BY DATE(vaccination_date)
              ORDER BY date DESC";
    
    return mysqli_query($conn, $query);
}

// 6. PENDING REQUESTS SUMMARY
function getPendingSummary($conn) {
    $query = "SELECT 
                COUNT(*) as total_pending,
                COUNT(DISTINCT child_id) as unique_children,
                COUNT(DISTINCT hospital_id) as hospitals_involved,
                MIN(preferred_date) as earliest_date,
                MAX(preferred_date) as latest_date
              FROM appointment_requests
              WHERE request_status = 'pending'";
    
    return mysqli_fetch_assoc(mysqli_query($conn, $query));
}

// 7. COMPLETION RATE REPORT
function getCompletionRateReport($conn, $from, $to) {
    $query = "SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as total_requests,
                SUM(CASE WHEN request_status = 'approved' THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN request_status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                SUM(CASE WHEN request_status = 'pending' THEN 1 ELSE 0 END) as pending,
                ROUND(SUM(CASE WHEN request_status = 'approved' THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) as approval_rate
              FROM appointment_requests
              WHERE created_at BETWEEN '$from' AND '$to 23:59:59'
              GROUP BY DATE_FORMAT(created_at, '%Y-%m')
              ORDER BY month DESC";
    
    return mysqli_query($conn, $query);
}

// Fetch data based on report type
$overview = getOverviewReport($connection, $date_from, $date_to);
$vaccine_report = getVaccineWiseReport($connection, $date_from, $date_to);
$hospital_report = getHospitalWiseReport($connection, $date_from, $date_to, $hospital_filter);
$age_group_report = getAgeGroupReport($connection);
$daily_trend = getDailyTrendReport($connection, $date_from, $date_to);
$pending_summary = getPendingSummary($connection);
$completion_rates = getCompletionRateReport($connection, $date_from, $date_to);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vaccination Reports - Admin Panel</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Inter',Arial,sans-serif; background:#f0f4ff; color:#1a1a2e; min-height:100vh; }

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

        /* ── LAYOUT ── */
        .container { max-width:1400px; margin:32px auto; padding:0 24px; }

        /* ── PAGE HEADER BANNER ── */
        .page-header {
            background:linear-gradient(135deg,#1d4ed8 0%,#3b82f6 60%,#60a5fa 100%);
            border-radius:18px; padding:32px 36px; margin-bottom:28px;
            color:white; box-shadow:0 8px 32px rgba(59,130,246,0.3);
            position:relative; overflow:hidden;
        }
        .page-header::before { content:''; position:absolute; top:-40px; right:-40px; width:200px; height:200px; background:rgba(255,255,255,0.08); border-radius:50%; }
        .page-header::after  { content:''; position:absolute; bottom:-60px; right:80px; width:160px; height:160px; background:rgba(255,255,255,0.05); border-radius:50%; }
        .page-header h1 { font-size:26px; font-weight:700; margin-bottom:6px; position:relative; z-index:1; }
        .page-header p  { font-size:14px; opacity:0.85; position:relative; z-index:1; }

        /* ── FILTER SECTION ── */
        .filter-section {
            background:#ffffff; border-radius:16px; padding:24px 28px;
            margin-bottom:24px; box-shadow:0 2px 12px rgba(59,130,246,0.07);
            border:1px solid #e8eeff;
        }
        .filter-title { font-size:15px; font-weight:700; color:#1a1a2e; margin-bottom:18px; }
        .filter-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:16px; margin-bottom:16px; }
        .filter-group { position:relative; }
        .filter-input, .filter-select {
            width:100%; padding:10px 14px;
            border:1.5px solid #e8eeff; border-radius:8px;
            font-size:13.5px; font-family:'Inter',Arial,sans-serif;
            background:#f8faff; color:#1a1a2e; transition:all 0.2s;
        }
        .filter-input:focus, .filter-select:focus { border-color:#3b82f6; outline:none; box-shadow:0 0 0 3px rgba(59,130,246,0.1); background:white; }
        .filter-actions { display:flex; gap:12px; justify-content:flex-end; flex-wrap:wrap; }

        /* ── BUTTONS ── */
        .btn {
            padding:10px 20px; border:none; border-radius:8px;
            font-weight:600; cursor:pointer; transition:all 0.22s;
            display:inline-flex; align-items:center; gap:8px;
            font-size:13.5px; font-family:'Inter',Arial,sans-serif;
            text-decoration:none;
        }
        .btn-primary { background:linear-gradient(135deg,#3b82f6,#1d4ed8); color:white; box-shadow:0 4px 14px rgba(29,78,216,0.2); }
        .btn-primary:hover { transform:translateY(-2px); box-shadow:0 8px 22px rgba(29,78,216,0.3); color:white; }
        .btn-secondary { background:#f8faff; color:#4b6cb7; border:1.5px solid #e8eeff; }
        .btn-secondary:hover { background:#eff6ff; color:#1d4ed8; }

        /* ── EXPORT BUTTONS ── */
        .export-options { display:flex; gap:10px; margin-bottom:24px; flex-wrap:wrap; }
        .export-btn {
            padding:8px 18px; background:#ffffff; border:1.5px solid #e8eeff;
            border-radius:8px; color:#4b6cb7; font-weight:600; font-size:13px;
            cursor:pointer; transition:all 0.2s; display:flex; align-items:center; gap:7px;
            font-family:'Inter',Arial,sans-serif;
        }
        .export-btn:hover { background:#eff6ff; color:#1d4ed8; border-color:#bfdbfe; }

        /* ── PENDING SUMMARY ── */
        .pending-summary {
            display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
            gap:16px; background:#fffbeb; border-radius:14px;
            padding:20px 24px; margin-bottom:24px; border-left:4px solid #f59e0b;
            border:1px solid #fde68a;
        }
        .pending-item { text-align:center; }
        .pending-item h4 { color:#92400e; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.4px; margin-bottom:6px; }
        .pending-item .value { font-size:28px; font-weight:700; color:#b45309; }

        /* ── OVERVIEW CARDS ── */
        .report-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:20px; margin-bottom:24px; }
        .report-card {
            background:#ffffff; border-radius:16px; padding:24px;
            box-shadow:0 2px 12px rgba(59,130,246,0.07);
            border:1px solid #e8eeff; transition:transform 0.2s,box-shadow 0.2s;
            display:flex; align-items:center; gap:16px;
        }
        .report-card:hover { transform:translateY(-3px); box-shadow:0 8px 24px rgba(59,130,246,0.13); }
        .card-icon { width:56px; height:56px; border-radius:14px; display:flex; align-items:center; justify-content:center; font-size:22px; flex-shrink:0; }
        .card-info h3 { font-size:30px; font-weight:700; color:#1d4ed8; line-height:1; margin-bottom:4px; }
        .card-info p  { font-size:12.5px; color:#6b7280; font-weight:500; }

        /* ── CHARTS ── */
        .charts-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:24px; margin-bottom:24px; }
        .chart-card {
            background:#ffffff; border-radius:16px; padding:24px;
            box-shadow:0 2px 12px rgba(59,130,246,0.07); border:1px solid #e8eeff;
        }
        .chart-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; }
        .chart-header h3 { color:#1a1a2e; font-size:15px; font-weight:700; }
        .chart-container { height:280px; position:relative; }

        /* ── TABLE CARDS ── */
        .table-card {
            background:#ffffff; border-radius:16px; padding:24px;
            box-shadow:0 2px 12px rgba(59,130,246,0.07);
            border:1px solid #e8eeff; margin-bottom:24px;
        }
        .table-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; padding-bottom:14px; border-bottom:1px solid #f1f5ff; }
        .table-header h3 { color:#1a1a2e; font-size:15px; font-weight:700; display:flex; align-items:center; gap:8px; }
        .badge-count { background:#eff6ff; padding:4px 12px; border-radius:20px; color:#1d4ed8; font-size:12px; font-weight:600; }

        .data-table { width:100%; border-collapse:collapse; }
        .data-table thead th {
            background:#f8faff; padding:12px 16px; text-align:left;
            font-size:11.5px; font-weight:600; color:#6b7280;
            text-transform:uppercase; letter-spacing:0.5px;
            border-bottom:1px solid #e8eeff;
        }
        .data-table tbody tr { border-bottom:1px solid #f4f6ff; transition:background 0.15s; }
        .data-table tbody tr:last-child { border-bottom:none; }
        .data-table tbody tr:hover td { background:#f8faff; }
        .data-table td { padding:13px 16px; color:#374151; font-size:13.5px; }

        /* ── PROGRESS BAR ── */
        .progress-bar { width:100%; height:7px; background:#e8eeff; border-radius:4px; overflow:hidden; margin-bottom:3px; }
        .progress-fill { height:100%; background:linear-gradient(90deg,#3b82f6,#1d4ed8); border-radius:4px; }

        /* ── DATATABLES OVERRIDE ── */
        .dataTables_wrapper .dataTables_filter input { border:1.5px solid #e8eeff; border-radius:8px; padding:6px 12px; font-family:'Inter',Arial,sans-serif; }
        .dataTables_wrapper .dataTables_filter input:focus { border-color:#3b82f6; outline:none; }
        .dataTables_wrapper .dataTables_length select { border:1.5px solid #e8eeff; border-radius:8px; padding:4px 8px; font-family:'Inter',Arial,sans-serif; }
        table.dataTable thead th { background:#f8faff !important; color:#6b7280 !important; font-size:11.5px; }
        table.dataTable.no-footer { border-bottom:1px solid #e8eeff; }
        .dataTables_wrapper .dataTables_paginate .paginate_button.current {
            background:linear-gradient(135deg,#3b82f6,#1d4ed8) !important;
            color:white !important; border:none !important; border-radius:6px !important;
        }
        .dataTables_wrapper .dataTables_paginate .paginate_button:hover {
            background:#eff6ff !important; color:#1d4ed8 !important; border-color:#bfdbfe !important; border-radius:6px !important;
        }

        /* ── RESPONSIVE ── */
        @media(max-width:1200px) {
            .report-grid { grid-template-columns:repeat(2,1fr); }
            .charts-grid { grid-template-columns:1fr; }
            .admin-navbar { height:auto; padding:12px 20px; flex-direction:column; gap:10px; }
        }
        @media(max-width:768px) {
            .report-grid { grid-template-columns:repeat(2,1fr); }
            .filter-grid { grid-template-columns:1fr; }
            .container { padding:0 14px; }
        }
        @media(max-width:480px) {
            .report-grid { grid-template-columns:1fr; }
            .filter-actions { flex-direction:column; }
        }

        @media print {
            .admin-navbar, .filter-section, .export-options { display:none; }
            body { background:white; }
            .table-card, .chart-card { box-shadow:none; border:1px solid #ddd; }
        }
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

    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1>📊 Vaccination Reports</h1>
            <p>Detailed analytics and insights for your vaccination program</p>
        </div>
        
        <!-- Filter Section -->
        <div class="filter-section">
            <div class="filter-title">🔍 Filter Reports</div>
            
            <form method="GET" action="">
                <div class="filter-grid">
                    <div class="filter-group">
                            <input type="date" class="filter-input" name="date_from" value="<?php echo $date_from; ?>">
                    </div>
                    
                    <div class="filter-group">
                            <input type="date" class="filter-input" name="date_to" value="<?php echo $date_to; ?>">
                    </div>
                    
                    <div class="filter-group">
                            <select class="filter-select" name="hospital_id">
                            <option value="0">All Hospitals</option>
                            <?php 
                            mysqli_data_seek($hospitals, 0);
                            while($h = mysqli_fetch_assoc($hospitals)): 
                            ?>
                            <option value="<?php echo $h['hospital_id']; ?>" <?php echo $hospital_filter == $h['hospital_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($h['hospital_name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                            <select class="filter-select" name="vaccine_id">
                            <option value="0">All Vaccines</option>
                            <?php 
                            mysqli_data_seek($vaccines, 0);
                            while($v = mysqli_fetch_assoc($vaccines)): 
                            ?>
                            <option value="<?php echo $v['vaccine_id']; ?>" <?php echo $vaccine_filter == $v['vaccine_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($v['vaccine_name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">🔍 Generate Report</button>
                    <a href="vaccination_reports.php" class="btn btn-secondary">↺ Reset</a>
                </div>
            </form>
        </div>
        
        <!-- Export Options -->
        <div class="export-options">
            <button class="export-btn" onclick="exportToPDF()">📄 Export as PDF</button>
            <button class="export-btn" onclick="exportToExcel()">📊 Export as Excel</button>
            <button class="export-btn" onclick="printReport()">🖨️ Print Report</button>
        </div>
        
        <!-- Pending Summary -->
        <div class="pending-summary">
            <div class="pending-item">
                <h4>Total Pending</h4>
                <div class="value"><?php echo $pending_summary['total_pending']; ?></div>
            </div>
            <div class="pending-item">
                <h4>Unique Children</h4>
                <div class="value"><?php echo $pending_summary['unique_children']; ?></div>
            </div>
            <div class="pending-item">
                <h4>Hospitals Involved</h4>
                <div class="value"><?php echo $pending_summary['hospitals_involved']; ?></div>
            </div>
            <div class="pending-item">
                <h4>Earliest Date</h4>
                <div class="value"><?php echo date('d M', strtotime($pending_summary['earliest_date'])); ?></div>
            </div>
        </div>
        
        <!-- Overview Cards -->
        <div class="report-grid">
            <div class="report-card">
                <div class="card-icon" style="background: linear-gradient(135deg, #4361ee, #3a0ca3);">
                </div>
                <h3><?php echo $overview['total_bookings']; ?></h3>
                <p>Total Bookings</p>
            </div>
            
            <div class="report-card" style="animation-delay: 0.1s">
                <div class="card-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                </div>
                <h3><?php echo $overview['completed_vaccinations']; ?></h3>
                <p>Completed Vaccinations</p>
            </div>
            
            <div class="report-card" style="animation-delay: 0.2s">
                <div class="card-icon" style="background: linear-gradient(135deg, #f59e0b, #d97706);">
                </div>
                <h3><?php echo $overview['pending_requests']; ?></h3>
                <p>Pending Requests</p>
            </div>
            
            <div class="report-card" style="animation-delay: 0.3s">
                <div class="card-icon" style="background: linear-gradient(135deg, #8b5cf6, #6d28d9);">
                </div>
                <h3><?php echo $overview['new_children']; ?></h3>
                <p>New Children</p>
            </div>
        </div>
        
        <!-- Charts Section -->
        <div class="charts-grid">
            <!-- Vaccine Distribution Chart -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3>🍩 Vaccine Distribution</h3>
                </div>
                <div class="chart-container">
                    <canvas id="vaccineChart"></canvas>
                </div>
            </div>
            
            <!-- Daily Trend Chart -->
            <div class="chart-card">
                <div class="chart-header">
                    <h3>📈 Daily Vaccination Trend</h3>
                </div>
                <div class="chart-container">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Age Group Report -->
        <div class="table-card">
            <div class="table-header">
                <h3>👥 Children by Age Group</h3>
                <span class="badge-count">Age Distribution</span>
            </div>
            
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Age Group</th>
                            <th>Total Children</th>
                            <th>Boys</th>
                            <th>Girls</th>
                            <th>Distribution</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_children_age = 0;
                        $age_data = [];
                        while($age = mysqli_fetch_assoc($age_group_report)):
                            $total_children_age += $age['total_children'];
                            $age_data[] = $age;
                        endwhile;
                        
                        mysqli_data_seek($age_group_report, 0);
                        while($age = mysqli_fetch_assoc($age_group_report)):
                        ?>
                        <tr>
                            <td><strong><?php echo $age['age_group']; ?></strong></td>
                            <td><?php echo $age['total_children']; ?></td>
                            <td><?php echo $age['boys']; ?></td>
                            <td><?php echo $age['girls']; ?></td>
                            <td>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo ($age['total_children'] / $total_children_age) * 100; ?>%"></div>
                                </div>
                                <small><?php echo round(($age['total_children'] / $total_children_age) * 100, 1); ?>%</small>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Vaccine Wise Report -->
        <div class="table-card">
            <div class="table-header">
                <h3>💉 Vaccine Wise Report</h3>
                <span class="badge-count"><?php echo mysqli_num_rows($vaccine_report); ?> Vaccines</span>
            </div>
            
            <div class="table-responsive">
                <table class="data-table" id="vaccineTable">
                    <thead>
                        <tr>
                            <th>Vaccine</th>
                            <th>Code</th>
                            <th>Bookings</th>
                            <th>Administered</th>
                            <th>Pending</th>
                            <th>Completion Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        mysqli_data_seek($vaccine_report, 0);
                        while($v = mysqli_fetch_assoc($vaccine_report)): 
                            $completion_rate = $v['total_bookings'] > 0 ? round(($v['administered_count'] / $v['total_bookings']) * 100, 1) : 0;
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($v['vaccine_name']); ?></strong></td>
                            <td><?php echo $v['vaccine_code']; ?></td>
                            <td><?php echo $v['total_bookings']; ?></td>
                            <td><?php echo $v['administered_count']; ?></td>
                            <td><?php echo $v['pending_requests']; ?></td>
                            <td>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $completion_rate; ?>%"></div>
                                </div>
                                <small><?php echo $completion_rate; ?>%</small>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Hospital Wise Report -->
        <div class="table-card">
            <div class="table-header">
                <h3>🏥 Hospital Performance Report</h3>
                <span class="badge-count"><?php echo mysqli_num_rows($hospital_report); ?> Hospitals</span>
            </div>
            
            <div class="table-responsive">
                <table class="data-table" id="hospitalTable">
                    <thead>
                        <tr>
                            <th>Hospital</th>
                            <th>Location</th>
                            <th>Total Bookings</th>
                            <th>Vaccinations Done</th>
                            <th>Pending</th>
                            <th>Approved</th>
                            <th>Performance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        mysqli_data_seek($hospital_report, 0);
                        while($h = mysqli_fetch_assoc($hospital_report)): 
                            $performance = $h['total_bookings'] > 0 ? round(($h['vaccinations_done'] / $h['total_bookings']) * 100, 1) : 0;
                        ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($h['hospital_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($h['city']); ?></td>
                            <td><?php echo $h['total_bookings']; ?></td>
                            <td><?php echo $h['vaccinations_done']; ?></td>
                            <td><?php echo $h['pending_requests']; ?></td>
                            <td><?php echo $h['approved_requests']; ?></td>
                            <td>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $performance; ?>%"></div>
                                </div>
                                <small><?php echo $performance; ?>%</small>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Daily Trend Table -->
        <div class="table-card">
            <div class="table-header">
                <h3>📅 Daily Vaccination Trend</h3>
                <span class="badge-count">Last <?php echo mysqli_num_rows($daily_trend); ?> Days</span>
            </div>
            
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Vaccinations</th>
                            <th>Active Hospitals</th>
                            <th>Vaccines Used</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        mysqli_data_seek($daily_trend, 0);
                        while($day = mysqli_fetch_assoc($daily_trend)): 
                        ?>
                        <tr>
                            <td><?php echo date('d M Y', strtotime($day['date'])); ?></td>
                            <td><strong><?php echo $day['vaccinations']; ?></strong></td>
                            <td><?php echo $day['hospitals_active']; ?></td>
                            <td><?php echo $day['vaccines_used']; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Completion Rate Report -->
        <div class="table-card">
            <div class="table-header">
                <h3>📊 Monthly Approval Rates</h3>
                <span class="badge-count">Trend Analysis</span>
            </div>
            
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Total Requests</th>
                            <th>Approved</th>
                            <th>Rejected</th>
                            <th>Pending</th>
                            <th>Approval Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        mysqli_data_seek($completion_rates, 0);
                        while($rate = mysqli_fetch_assoc($completion_rates)): 
                        ?>
                        <tr>
                            <td><strong><?php echo $rate['month']; ?></strong></td>
                            <td><?php echo $rate['total_requests']; ?></td>
                            <td><?php echo $rate['approved']; ?></td>
                            <td><?php echo $rate['rejected']; ?></td>
                            <td><?php echo $rate['pending']; ?></td>
                            <td>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $rate['approval_rate']; ?>%"></div>
                                </div>
                                <small><?php echo $rate['approval_rate']; ?>%</small>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
    </div>
    
    <script>
        // Initialize DataTables
        $(document).ready(function() {
            $('#vaccineTable').DataTable({
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                order: [[2, 'desc']],
                language: {
                    search: "Search:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ vaccines"
                }
            });
            
            $('#hospitalTable').DataTable({
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                order: [[3, 'desc']],
                language: {
                    search: "Search:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ hospitals"
                }
            });
        });
        
        // Initialize Charts
        document.addEventListener('DOMContentLoaded', function() {
            // Vaccine Chart Data
            <?php
            mysqli_data_seek($vaccine_report, 0);
            $vaccine_names = [];
            $vaccine_counts = [];
            while($v = mysqli_fetch_assoc($vaccine_report)) {
                $vaccine_names[] = $v['vaccine_name'];
                $vaccine_counts[] = $v['administered_count'];
            }
            ?>
            
            const vaccineCtx = document.getElementById('vaccineChart').getContext('2d');
            new Chart(vaccineCtx, {
                type: 'doughnut',
                data: {
                    labels: <?php echo json_encode($vaccine_names); ?>,
                    datasets: [{
                        data: <?php echo json_encode($vaccine_counts); ?>,
                        backgroundColor: [
                            '#1d4ed8', '#3b82f6', '#60a5fa', '#93c5fd',
                            '#2563eb', '#1e40af', '#7c3aed', '#10b981'
                        ],
                        borderWidth: 2,
                        borderColor: 'white'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                        }
                    }
                }
            });
            
            // Trend Chart Data
            <?php
            mysqli_data_seek($daily_trend, 0);
            $dates = [];
            $daily_counts = [];
            while($day = mysqli_fetch_assoc($daily_trend)) {
                $dates[] = date('d M', strtotime($day['date']));
                $daily_counts[] = $day['vaccinations'];
            }
            $dates = array_reverse($dates);
            $daily_counts = array_reverse($daily_counts);
            ?>
            
            const trendCtx = document.getElementById('trendChart').getContext('2d');
            new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($dates); ?>,
                    datasets: [{
                        label: 'Daily Vaccinations',
                        data: <?php echo json_encode($daily_counts); ?>,
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#3b82f6',
                        pointBorderColor: 'white',
                        pointBorderWidth: 2,
                        pointRadius: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        });
        
        // Export functions
        function exportToPDF() {
            alert('PDF export functionality will be implemented here.');
            // You can use libraries like jsPDF or TCPDF
        }
        
        function exportToExcel() {
            alert('Excel export functionality will be implemented here.');
            // You can export HTML tables to Excel
        }
        
        function printReport() {
            window.print();
        }
        
        // Add animation to cards
        const cards = document.querySelectorAll('.report-card');
        cards.forEach((card, index) => {
            card.style.animationDelay = `${index * 0.1}s`;
        });
    </script>
</body>
</html>
<?php mysqli_close($connection); ?>