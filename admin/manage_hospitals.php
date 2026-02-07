<?php
session_start();
if(!isset($_SESSION["logged_in"]) || $_SESSION["user_type"] != "admin"){
    header("location:../login.php");
    exit();
}

include("../dbconnection.php");

// Get filter values
$filter_city = isset($_GET['city']) ? $_GET['city'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';

// Build query with filters
$query_hospitals = "SELECT h.*, u.email, u.phone, u.is_active as user_active,
                    (SELECT COUNT(*) FROM vaccination_bookings vb WHERE vb.hospital_id = h.hospital_id) as total_bookings,
                    (SELECT COUNT(*) FROM appointment_requests ar WHERE ar.hospital_id = h.hospital_id) as total_requests
                    FROM hospitals h
                    JOIN users u ON h.user_id = u.user_id
                    WHERE 1=1";

if(!empty($filter_city)) {
    $query_hospitals .= " AND h.city LIKE '%" . mysqli_real_escape_string($connection, $filter_city) . "%'";
}

if($filter_status == 'active') {
    $query_hospitals .= " AND h.is_active = 1";
} elseif($filter_status == 'inactive') {
    $query_hospitals .= " AND h.is_active = 0";
} elseif($filter_status == 'verified') {
    $query_hospitals .= " AND h.is_verified = 1";
} elseif($filter_status == 'pending') {
    $query_hospitals .= " AND h.is_verified = 0";
}

$query_hospitals .= " ORDER BY h.created_at DESC";
$result_hospitals = mysqli_query($connection, $query_hospitals);

// Get statistics
$query_stats = "SELECT 
    COUNT(*) as total_hospitals,
    SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) as verified_hospitals,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_hospitals,
    (SELECT COUNT(DISTINCT city) FROM hospitals) as cities_count
    FROM hospitals";
$result_stats = mysqli_query($connection, $query_stats);
$stats = mysqli_fetch_assoc($result_stats);

// Get unique cities for filter
$query_cities = "SELECT DISTINCT city FROM hospitals WHERE city IS NOT NULL ORDER BY city";
$result_cities = mysqli_query($connection, $query_cities);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Management - Admin Panel</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        :root {
            --primary: #3b82f6;
            --secondary: #1d4ed8;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #8b5cf6;
            --light: #f8f9fa;
            --dark: #1f2937;
            --gray: #6b7280;
            --border-radius: 16px;
            --box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }
        
        body {
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        /* Navbar */
        .admin-navbar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius);
            padding: 18px 30px;
            margin-bottom: 30px;
            box-shadow: var(--box-shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-left: 5px solid var(--primary);
            animation: slideInDown 0.5s ease-out;
        }
        
        .admin-navbar .logo {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .admin-navbar .logo h2 {
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 26px;
            font-weight: 700;
        }
        
        .admin-navbar .logo i {
            font-size: 28px;
            color: var(--primary);
        }
        
        .nav-links {
            display: flex;
            gap: 15px;
        }
        
        .nav-links a {
            text-decoration: none;
            color: var(--dark);
            padding: 10px 20px;
            border-radius: 30px;
            font-weight: 500;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .nav-links a:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-3px);
        }
        
        .nav-links a.active {
            background: var(--primary);
            color: white;
        }
        
        /* Container */
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        /* Page Header */
        .page-header {
            background: white;
            border-radius: var(--border-radius);
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--box-shadow);
            animation: fadeIn 0.6s ease-out;
        }
        
        .page-header h1 {
            color: var(--dark);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 2.2rem;
        }
        
        .page-header h1 i {
            color: var(--primary);
            background: rgba(59, 130, 246, 0.1);
            padding: 12px;
            border-radius: 10px;
        }
        
        .page-header p {
            color: var(--gray);
            font-size: 16px;
            margin-bottom: 20px;
        }
        
        /* Stats Cards */
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            border-top: 4px solid;
            animation: fadeInUp 0.6s ease-out;
        }
        
        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card:nth-child(1) { border-color: var(--primary); }
        .stat-card:nth-child(2) { border-color: var(--success); }
        .stat-card:nth-child(3) { border-color: var(--warning); }
        .stat-card:nth-child(4) { border-color: var(--info); }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            font-size: 24px;
            color: white;
        }
        
        .stat-card:nth-child(1) .stat-icon { background: var(--primary); }
        .stat-card:nth-child(2) .stat-icon { background: var(--success); }
        .stat-card:nth-child(3) .stat-icon { background: var(--warning); }
        .stat-card:nth-child(4) .stat-icon { background: var(--info); }
        
        .stat-card h3 {
            font-size: 32px;
            color: var(--dark);
            margin-bottom: 5px;
        }
        
        .stat-card p {
            color: var(--gray);
            font-size: 14px;
        }
        
        /* Search and Filters */
        .filters-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--box-shadow);
            animation: fadeIn 0.6s ease-out 0.3s both;
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .filter-group {
            position: relative;
        }
        
        .filter-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
        }
        
        .filter-input {
            width: 100%;
            padding: 12px 20px 12px 45px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 15px;
            transition: var(--transition);
        }
        
        .filter-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .filter-select {
            width: 100%;
            padding: 12px 20px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            background: white;
            color: var(--dark);
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .filter-select:focus {
            outline: none;
            border-color: var(--primary);
        }
        
        .filter-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
        }
        
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-search {
            background: var(--primary);
            color: white;
        }
        
        .btn-reset {
            background: #f3f4f6;
            color: var(--gray);
        }
        
        .btn-add {
            background: var(--success);
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        /* View Toggle */
        .view-toggle {
            background: white;
            border-radius: var(--border-radius);
            padding: 15px 25px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--box-shadow);
            animation: fadeIn 0.6s ease-out 0.4s both;
        }
        
        .view-buttons {
            display: flex;
            gap: 10px;
        }
        
        .view-btn {
            padding: 10px 20px;
            background: #f3f4f6;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            color: var(--gray);
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .view-btn.active {
            background: var(--primary);
            color: white;
        }
        
        /* Hospitals Grid View */
        .hospitals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .hospital-card {
            background: white;
            border-radius: var(--border-radius);
            overflow: hidden;
            box-shadow: var(--box-shadow);
            transition: var(--transition);
            animation: fadeInUp 0.6s ease-out;
        }
        
        .hospital-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        
        .hospital-header {
            padding: 25px;
            border-bottom: 1px solid #f3f4f6;
            position: relative;
        }
        
        .hospital-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .badge-verified {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }
        
        .badge-pending {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }
        
        .badge-active {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }
        
        .badge-inactive {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }
        
        .hospital-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 30px;
            margin-bottom: 20px;
        }
        
        .hospital-header h3 {
            color: var(--dark);
            font-size: 1.4rem;
            margin-bottom: 10px;
        }
        
        .hospital-header p {
            color: var(--gray);
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .hospital-details {
            padding: 0 25px 25px;
        }
        
        .detail-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .detail-item:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            color: var(--gray);
            font-weight: 500;
            font-size: 14px;
        }
        
        .detail-value {
            color: var(--dark);
            font-weight: 600;
            text-align: right;
        }
        
        .hospital-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin: 20px 0;
            padding: 20px;
            background: #f8fafc;
            border-radius: 12px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 12px;
            color: var(--gray);
        }
        
        .hospital-actions {
            padding: 0 25px 25px;
            display: flex;
            gap: 10px;
        }
        
        .action-btn {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 14px;
        }
        
        .btn-verify {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }
        
        .btn-verify:hover {
            background: #10b981;
            color: white;
        }
        
        .btn-activate {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }
        
        .btn-activate:hover {
            background: #3b82f6;
            color: white;
        }
        
        .btn-deactivate {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }
        
        .btn-deactivate:hover {
            background: #ef4444;
            color: white;
        }
        
        .btn-view {
            background: rgba(139, 92, 246, 0.1);
            color: #8b5cf6;
        }
        
        .btn-view:hover {
            background: #8b5cf6;
            color: white;
        }
        
        /* Table View (Hidden by default) */
        .table-view {
            display: none;
        }
        
        .table-container {
            background: white;
            border-radius: var(--border-radius);
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: var(--box-shadow);
            overflow-x: auto;
        }
        
        .hospital-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }
        
        .hospital-table thead {
            background: #f8fafc;
        }
        
        .hospital-table th {
            padding: 18px 15px;
            text-align: left;
            color: #64748b;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .hospital-table tbody tr {
            border-bottom: 1px solid #f1f5f9;
            transition: var(--transition);
        }
        
        .hospital-table tbody tr:hover {
            background: #f8fafc;
        }
        
        .hospital-table td {
            padding: 20px 15px;
            color: #334155;
            font-size: 14px;
        }
        
        /* Empty State */
        .empty-state {
            background: white;
            border-radius: var(--border-radius);
            padding: 80px 40px;
            text-align: center;
            box-shadow: var(--box-shadow);
            animation: fadeIn 0.6s ease-out;
        }
        
        .empty-state i {
            font-size: 60px;
            color: #e5e7eb;
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            color: var(--dark);
            margin-bottom: 10px;
            font-size: 1.5rem;
        }
        
        .empty-state p {
            color: var(--gray);
            margin-bottom: 25px;
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
            margin-top: 30px;
        }
        
        .pagination-btn {
            padding: 12px 20px;
            background: white;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .pagination-btn:hover {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .page-numbers {
            display: flex;
            gap: 8px;
        }
        
        .page-number {
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .page-number:hover {
            background: #f3f4f6;
        }
        
        .page-number.active {
            background: var(--primary);
            color: white;
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes fadeInUp {
            from { 
                opacity: 0; 
                transform: translateY(30px); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0); 
            }
        }
        
        @keyframes slideInDown {
            from { 
                opacity: 0; 
                transform: translateY(-30px); 
            }
            to { 
                opacity: 1; 
                transform: translateY(0); 
            }
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .admin-navbar {
                flex-direction: column;
                gap: 20px;
            }
            
            .nav-links {
                flex-wrap: wrap;
                justify-content: center;
            }
        }
        
        @media (max-width: 768px) {
            body {
                padding: 10px;
            }
            
            .page-header, .filters-section, .view-toggle {
                padding: 20px;
            }
            
            .hospitals-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-actions {
                flex-direction: column;
            }
            
            .hospital-actions {
                flex-direction: column;
            }
            
            .view-buttons {
                flex-wrap: wrap;
            }
        }
    </style>
    
    <script>
        // Toggle between grid and table view
        function toggleView(viewType) {
            const gridView = document.getElementById('gridView');
            const tableView = document.getElementById('tableView');
            const gridBtn = document.querySelector('.view-btn:nth-child(1)');
            const tableBtn = document.querySelector('.view-btn:nth-child(2)');
            
            if(viewType === 'grid') {
                gridView.style.display = 'grid';
                tableView.style.display = 'none';
                gridBtn.classList.add('active');
                tableBtn.classList.remove('active');
            } else {
                gridView.style.display = 'none';
                tableView.style.display = 'block';
                gridBtn.classList.remove('active');
                tableBtn.classList.add('active');
            }
        }
        
        // Search functionality
        function searchHospitals() {
            const searchInput = document.getElementById('searchInput').value.toLowerCase();
            const cityFilter = document.getElementById('cityFilter').value;
            const statusFilter = document.getElementById('statusFilter').value;
            
            // In a real app, you would submit the form or make AJAX request
            // For now, we'll just show a message
            if(searchInput || cityFilter !== '' || statusFilter !== 'all') {
                document.getElementById('filterForm').submit();
            }
        }
        
        // Reset filters
        function resetFilters() {
            document.getElementById('searchInput').value = '';
            document.getElementById('cityFilter').value = '';
            document.getElementById('statusFilter').value = 'all';
            document.getElementById('filterForm').submit();
        }
        
        // Confirmation for actions
        function confirmAction(action, hospitalId, hospitalName) {
            let message = '';
            
            switch(action) {
                case 'verify':
                    message = `Are you sure you want to verify "${hospitalName}"?`;
                    break;
                case 'activate':
                    message = `Are you sure you want to activate "${hospitalName}"?`;
                    break;
                case 'deactivate':
                    message = `Are you sure you want to deactivate "${hospitalName}"?`;
                    break;
            }
            
            return confirm(message);
        }
        
        // Add animation to cards on load
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.hospital-card');
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
            });
            
            // Set default view to grid
            toggleView('grid');
        });
    </script>
</head>
<body>
    <!-- Admin Navbar -->
    <nav class="admin-navbar animate__animated animate__fadeInDown">
        <div class="logo">
            <i class="fas fa-hospital-alt"></i>
            <h2>Vaccine<span>Admin</span> Pro</h2>
        </div>
        <div class="nav-links">
            <a href="dashboard.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="appointment_requests.php">
                <i class="fas fa-calendar-check"></i> Requests
            </a>
            <a href="manage_hospitals.php" class="active">
                <i class="fas fa-hospital"></i> Hospitals
            </a>
            <a href="manage_vaccines.php">
                <i class="fas fa-syringe"></i> Vaccines
            </a>
            <a href="../logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </nav>

    <div class="container">
        <!-- Page Header -->
        <div class="page-header">
            <h1>
                <i class="fas fa-hospital-alt"></i>
                Hospital Management
            </h1>
            <p>Manage all registered hospitals, verify credentials, and activate/deactivate as needed</p>
            
            <div class="filter-actions">
                <button class="btn btn-add" onclick="window.location.href='add_hospital.php'">
                    <i class="fas fa-plus"></i> Add New Hospital
                </button>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-container">
            <div class="stat-card animate__animated animate__fadeInUp">
                <div class="stat-icon">
                    <i class="fas fa-hotel"></i>
                </div>
                <h3><?php echo $stats['total_hospitals']; ?></h3>
                <p>Total Hospitals</p>
            </div>
            
            <div class="stat-card animate__animated animate__fadeInUp" style="animation-delay: 0.1s">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h3><?php echo $stats['verified_hospitals']; ?></h3>
                <p>Verified Hospitals</p>
            </div>
            
            <div class="stat-card animate__animated animate__fadeInUp" style="animation-delay: 0.2s">
                <div class="stat-icon">
                    <i class="fas fa-toggle-on"></i>
                </div>
                <h3><?php echo $stats['active_hospitals']; ?></h3>
                <p>Active Hospitals</p>
            </div>
            
            <div class="stat-card animate__animated animate__fadeInUp" style="animation-delay: 0.3s">
                <div class="stat-icon">
                    <i class="fas fa-city"></i>
                </div>
                <h3><?php echo $stats['cities_count']; ?></h3>
                <p>Cities Covered</p>
            </div>
        </div>
        
        <!-- Filters Section -->
        <form id="filterForm" method="GET" class="filters-section">
            <div class="filter-grid">
                <div class="filter-group">
                    <i class="fas fa-search"></i>
                    <input type="text" 
                           id="searchInput"
                           name="search"
                           class="filter-input"
                           placeholder="Search by hospital name or registration number..."
                           value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                </div>
                
                <div class="filter-group">
                    <i class="fas fa-city"></i>
                    <select id="cityFilter" name="city" class="filter-select">
                        <option value="">All Cities</option>
                        <?php while($city = mysqli_fetch_assoc($result_cities)): ?>
                        <option value="<?php echo $city['city']; ?>" 
                            <?php echo ($filter_city == $city['city']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($city['city']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <i class="fas fa-filter"></i>
                    <select id="statusFilter" name="status" class="filter-select">
                        <option value="all" <?php echo ($filter_status == 'all') ? 'selected' : ''; ?>>All Status</option>
                        <option value="active" <?php echo ($filter_status == 'active') ? 'selected' : ''; ?>>Active Only</option>
                        <option value="inactive" <?php echo ($filter_status == 'inactive') ? 'selected' : ''; ?>>Inactive Only</option>
                        <option value="verified" <?php echo ($filter_status == 'verified') ? 'selected' : ''; ?>>Verified Only</option>
                        <option value="pending" <?php echo ($filter_status == 'pending') ? 'selected' : ''; ?>>Pending Verification</option>
                    </select>
                </div>
            </div>
            
            <div class="filter-actions">
                <button type="button" class="btn btn-reset" onclick="resetFilters()">
                    <i class="fas fa-redo"></i> Reset Filters
                </button>
                <button type="button" class="btn btn-search" onclick="searchHospitals()">
                    <i class="fas fa-search"></i> Search Hospitals
                </button>
            </div>
        </form>
        
        <!-- View Toggle -->
        <div class="view-toggle">
            <div>
                <h3 style="color: var(--dark); font-size: 1.2rem;">
                    <?php echo mysqli_num_rows($result_hospitals); ?> Hospitals Found
                </h3>
                <p style="color: var(--gray); font-size: 14px; margin-top: 5px;">
                    <?php if($filter_city): ?>Filtered by: <?php echo htmlspecialchars($filter_city); ?><?php endif; ?>
                </p>
            </div>
            
            <div class="view-buttons">
                <button class="view-btn active" onclick="toggleView('grid')">
                    <i class="fas fa-th-large"></i> Grid View
                </button>
                <button class="view-btn" onclick="toggleView('table')">
                    <i class="fas fa-table"></i> Table View
                </button>
            </div>
        </div>
        
        <?php if(mysqli_num_rows($result_hospitals) > 0): ?>
        
        <!-- Grid View -->
        <div id="gridView" class="hospitals-grid">
            <?php 
            mysqli_data_seek($result_hospitals, 0); // Reset pointer
            while($row = mysqli_fetch_assoc($result_hospitals)): 
                $isVerified = $row['is_verified'];
                $isActive = $row['is_active'];
            ?>
            <div class="hospital-card animate__animated animate__fadeInUp">
                <div class="hospital-header">
                    <?php if($isVerified): ?>
                    <span class="hospital-badge badge-verified">
                        <i class="fas fa-check-circle"></i> Verified
                    </span>
                    <?php else: ?>
                    <span class="hospital-badge badge-pending">
                        <i class="fas fa-clock"></i> Pending
                    </span>
                    <?php endif; ?>
                    
                    <div class="hospital-icon">
                        <i class="fas fa-hospital"></i>
                    </div>
                    
                    <h3><?php echo htmlspecialchars($row['hospital_name']); ?></h3>
                    <p>
                        <i class="fas fa-map-marker-alt"></i>
                        <?php echo htmlspecialchars($row['city']); ?>, <?php echo htmlspecialchars($row['state']); ?>
                    </p>
                </div>
                
                <div class="hospital-details">
                    <div class="detail-item">
                        <span class="detail-label">Registration No:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($row['registration_number']); ?></span>
                    </div>
                    
                    <div class="detail-item">
                        <span class="detail-label">Contact Person:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($row['contact_person']); ?></span>
                    </div>
                    
                    <div class="detail-item">
                        <span class="detail-label">Phone:</span>
                        <span class="detail-value"><?php echo $row['phone'] ? htmlspecialchars($row['phone']) : 'N/A'; ?></span>
                    </div>
                    
                    <div class="detail-item">
                        <span class="detail-label">Email:</span>
                        <span class="detail-value"><?php echo htmlspecialchars($row['email']); ?></span>
                    </div>
                    
                    <div class="hospital-stats">
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $row['total_bookings']; ?></div>
                            <div class="stat-label">Total Bookings</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-number"><?php echo $row['total_requests']; ?></div>
                            <div class="stat-label">Pending Requests</div>
                        </div>
                    </div>
                </div>
                
                <div class="hospital-actions">
                    <button class="action-btn btn-view" onclick="window.location.href='hospital_details.php?id=<?php echo $row['hospital_id']; ?>'">
                        <i class="fas fa-eye"></i> View
                    </button>
                    
                    <?php if(!$isVerified): ?>
                    <button class="action-btn btn-verify" 
                            onclick="if(confirmAction('verify', <?php echo $row['hospital_id']; ?>, '<?php echo addslashes($row['hospital_name']); ?>')) window.location.href='verify_hospital.php?id=<?php echo $row['hospital_id']; ?>'">
                        <i class="fas fa-check-circle"></i> Verify
                    </button>
                    <?php endif; ?>
                    
                    <?php if($isActive): ?>
                    <button class="action-btn btn-deactivate" 
                            onclick="if(confirmAction('deactivate', <?php echo $row['hospital_id']; ?>, '<?php echo addslashes($row['hospital_name']); ?>')) window.location.href='toggle_hospital_status.php?id=<?php echo $row['hospital_id']; ?>&action=deactivate'">
                        <i class="fas fa-toggle-off"></i> Deactivate
                    </button>
                    <?php else: ?>
                    <button class="action-btn btn-activate" 
                            onclick="if(confirmAction('activate', <?php echo $row['hospital_id']; ?>, '<?php echo addslashes($row['hospital_name']); ?>')) window.location.href='toggle_hospital_status.php?id=<?php echo $row['hospital_id']; ?>&action=activate'">
                        <i class="fas fa-toggle-on"></i> Activate
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        
        <!-- Table View (Hidden by default) -->
        <div id="tableView" class="table-view">
            <div class="table-container">
                <table class="hospital-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Hospital Name</th>
                            <th>Registration No</th>
                            <th>Location</th>
                            <th>Contact Person</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        mysqli_data_seek($result_hospitals, 0); // Reset pointer again
                        while($row = mysqli_fetch_assoc($result_hospitals)): 
                            $isVerified = $row['is_verified'];
                            $isActive = $row['is_active'];
                        ?>
                        <tr>
                            <td>
                                <strong style="color: var(--primary);">HSP<?php echo str_pad($row['hospital_id'], 4, '0', STR_PAD_LEFT); ?></strong>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: var(--dark);">
                                    <?php echo htmlspecialchars($row['hospital_name']); ?>
                                </div>
                                <div style="font-size: 12px; color: var(--gray); margin-top: 3px;">
                                    <i class="fas fa-map-marker-alt"></i> 
                                    <?php echo htmlspecialchars($row['city']); ?>
                                </div>
                            </td>
                            <td>
                                <span style="font-family: monospace; background: #f3f4f6; padding: 3px 8px; border-radius: 4px;">
                                    <?php echo htmlspecialchars($row['registration_number']); ?>
                                </span>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($row['city']); ?>, <?php echo htmlspecialchars($row['state']); ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($row['contact_person']); ?>
                            </td>
                            <td>
                                <?php echo $row['phone'] ? htmlspecialchars($row['phone']) : 'N/A'; ?>
                            </td>
                            <td>
                                <a href="mailto:<?php echo htmlspecialchars($row['email']); ?>" style="color: var(--primary); text-decoration: none;">
                                    <?php echo htmlspecialchars($row['email']); ?>
                                </a>
                            </td>
                            <td>
                                <div style="display: flex; flex-direction: column; gap: 5px;">
                                    <?php if($isVerified): ?>
                                    <span class="hospital-badge badge-verified" style="width: fit-content;">
                                        <i class="fas fa-check-circle"></i> Verified
                                    </span>
                                    <?php else: ?>
                                    <span class="hospital-badge badge-pending" style="width: fit-content;">
                                        <i class="fas fa-clock"></i> Pending
                                    </span>
                                    <?php endif; ?>
                                    
                                    <?php if($isActive): ?>
                                    <span class="hospital-badge badge-active" style="width: fit-content;">
                                        <i class="fas fa-toggle-on"></i> Active
                                    </span>
                                    <?php else: ?>
                                    <span class="hospital-badge badge-inactive" style="width: fit-content;">
                                        <i class="fas fa-toggle-off"></i> Inactive
                                    </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                                    <button class="action-btn btn-view" style="padding: 8px 12px;" onclick="window.location.href='hospital_details.php?id=<?php echo $row['hospital_id']; ?>'">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    
                                    <?php if(!$isVerified): ?>
                                    <button class="action-btn btn-verify" style="padding: 8px 12px;"
                                            onclick="if(confirmAction('verify', <?php echo $row['hospital_id']; ?>, '<?php echo addslashes($row['hospital_name']); ?>')) window.location.href='verify_hospital.php?id=<?php echo $row['hospital_id']; ?>'">
                                        <i class="fas fa-check-circle"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if($isActive): ?>
                                    <button class="action-btn btn-deactivate" style="padding: 8px 12px;"
                                            onclick="if(confirmAction('deactivate', <?php echo $row['hospital_id']; ?>, '<?php echo addslashes($row['hospital_name']); ?>')) window.location.href='toggle_hospital_status.php?id=<?php echo $row['hospital_id']; ?>&action=deactivate'">
                                        <i class="fas fa-toggle-off"></i>
                                    </button>
                                    <?php else: ?>
                                    <button class="action-btn btn-activate" style="padding: 8px 12px;"
                                            onclick="if(confirmAction('activate', <?php echo $row['hospital_id']; ?>, '<?php echo addslashes($row['hospital_name']); ?>')) window.location.href='toggle_hospital_status.php?id=<?php echo $row['hospital_id']; ?>&action=activate'">
                                        <i class="fas fa-toggle-on"></i>
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Pagination -->
        <div class="pagination">
            <button class="pagination-btn">
                <i class="fas fa-chevron-left"></i> Previous
            </button>
            <div class="page-numbers">
                <span class="page-number active">1</span>
                <span class="page-number">2</span>
                <span class="page-number">3</span>
                <span class="page-number">...</span>
                <span class="page-number">5</span>
            </div>
            <button class="pagination-btn">
                Next <i class="fas fa-chevron-right"></i>
            </button>
        </div>
        
        <?php else: ?>
        <!-- Empty State -->
        <div class="empty-state animate__animated animate__fadeIn">
            <i class="fas fa-hospital"></i>
            <h3>No Hospitals Found</h3>
            <p>No hospitals match your current filters or no hospitals have been registered yet.</p>
            <button class="btn btn-add" onclick="window.location.href='add_hospital.php'" style="margin-top: 15px;">
                <i class="fas fa-plus"></i> Add First Hospital
            </button>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
<?php mysqli_close($connection); ?>