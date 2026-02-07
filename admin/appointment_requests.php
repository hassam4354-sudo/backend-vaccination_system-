<?php
session_start();
if(!isset($_SESSION["logged_in"]) || $_SESSION["user_type"] != "admin"){
    header("location:../login.php");
    exit();
}

include("../dbconnection.php");

// Get all pending appointment requests
$query_requests = "SELECT ar.*, 
                   c.full_name as child_name, c.date_of_birth,
                   p.full_name as parent_name, p.emergency_contact as parent_phone,
                   h.hospital_name, h.city,
                   v.vaccine_name
                   FROM appointment_requests ar
                   JOIN children c ON ar.child_id = c.child_id
                   JOIN parents p ON c.parent_id = p.parent_id
                   JOIN hospitals h ON ar.hospital_id = h.hospital_id
                   JOIN vaccines v ON ar.vaccine_id = v.vaccine_id
                   WHERE ar.request_status = 'pending'
                   ORDER BY ar.created_at DESC";
$result_requests = mysqli_query($connection, $query_requests);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Requests - Admin Panel</title>
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Animate.css for animations -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        :root {
            --primary: #4361ee;
            --secondary: #3a0ca3;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #f8961e;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --light-gray: #e9ecef;
            --border-radius: 12px;
            --box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        /* Admin Navbar */
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
        
        .nav-links a.logout {
            background: var(--danger);
            color: white;
        }
        
        /* Main Container */
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        /* Dashboard Header */
        .dashboard-header {
            background: white;
            border-radius: var(--border-radius);
            padding: 25px 30px;
            margin-bottom: 30px;
            box-shadow: var(--box-shadow);
            animation: fadeIn 0.6s ease-out;
        }
        
        .dashboard-header h1 {
            color: var(--dark);
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .dashboard-header h1 i {
            color: var(--primary);
            background: rgba(67, 97, 238, 0.1);
            padding: 12px;
            border-radius: 10px;
        }
        
        .dashboard-header p {
            color: var(--gray);
            font-size: 16px;
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
        .stat-card:nth-child(2) { border-color: var(--warning); }
        .stat-card:nth-child(3) { border-color: var(--success); }
        .stat-card:nth-child(4) { border-color: #8b5cf6; }
        
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
        .stat-card:nth-child(2) .stat-icon { background: var(--warning); }
        .stat-card:nth-child(3) .stat-icon { background: var(--success); }
        .stat-card:nth-child(4) .stat-icon { background: #8b5cf6; }
        
        .stat-card h3 {
            font-size: 32px;
            color: var(--dark);
            margin-bottom: 5px;
        }
        
        .stat-card p {
            color: var(--gray);
            font-size: 14px;
        }
        
        /* Main Content Section */
        .content-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 30px;
            box-shadow: var(--box-shadow);
            animation: fadeIn 0.7s ease-out;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--light-gray);
        }
        
        .section-header h3 {
            color: var(--dark);
            font-size: 22px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .section-header h3 i {
            color: var(--primary);
        }
        
        /* Search and Filters */
        .search-filter {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
        }
        
        .search-box {
            flex: 1;
            position: relative;
        }
        
        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
        }
        
        .search-box input {
            width: 100%;
            padding: 12px 20px 12px 45px;
            border: 2px solid var(--light-gray);
            border-radius: 30px;
            font-size: 15px;
            transition: var(--transition);
        }
        
        .search-box input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }
        
        .filter-select {
            padding: 12px 20px;
            border: 2px solid var(--light-gray);
            border-radius: 30px;
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
        
        /* Requests Table */
        .table-container {
            overflow-x: auto;
            border-radius: 10px;
            border: 1px solid var(--light-gray);
        }
        
        .requests-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1200px;
        }
        
        .requests-table thead {
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }
        
        .requests-table th {
            padding: 18px 15px;
            text-align: left;
            color: white;
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .requests-table tbody tr {
            border-bottom: 1px solid var(--light-gray);
            transition: var(--transition);
        }
        
        .requests-table tbody tr:hover {
            background: rgba(67, 97, 238, 0.03);
            transform: scale(1.002);
        }
        
        .requests-table td {
            padding: 20px 15px;
            color: var(--dark);
            font-size: 15px;
        }
        
        /* Child Info Cell */
        .child-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .child-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
        }
        
        .child-details h4 {
            color: var(--dark);
            margin-bottom: 5px;
        }
        
        .child-details p {
            color: var(--gray);
            font-size: 13px;
        }
        
        /* Parent Info */
        .parent-info {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        
        .parent-info span {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        
        .parent-info i {
            color: var(--primary);
            width: 16px;
        }
        
        /* Vaccine Info */
        .vaccine-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .vaccine-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: rgba(240, 68, 56, 0.1);
            color: #f04438;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        
        /* Hospital Info */
        .hospital-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .hospital-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        
        /* Time Info */
        .time-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .time-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: rgba(139, 92, 246, 0.1);
            color: #8b5cf6;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        
        /* Status Badge */
        .status-badge {
            padding: 6px 15px;
            border-radius: 30px;
            font-size: 13px;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-pending {
            background: rgba(248, 150, 30, 0.1);
            color: var(--warning);
            animation: pulse 2s infinite;
        }
        
        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 10px 18px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-width: 100px;
        }
        
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .btn-approve {
            background: linear-gradient(90deg, #10b981, #059669);
            color: white;
        }
        
        .btn-reject {
            background: linear-gradient(90deg, #ef4444, #dc2626);
            color: white;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--gray);
        }
        
        .empty-state i {
            font-size: 60px;
            margin-bottom: 20px;
            color: var(--light-gray);
        }
        
        .empty-state h4 {
            font-size: 22px;
            margin-bottom: 10px;
            color: var(--dark);
        }
        
        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 30px;
        }
        
        .pagination-btn {
            padding: 10px 18px;
            background: white;
            border: 2px solid var(--light-gray);
            border-radius: 8px;
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
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
        }
        
        .page-number:hover {
            background: var(--light-gray);
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
                transform: translateY(20px); 
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
        
        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(248, 150, 30, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(248, 150, 30, 0); }
            100% { box-shadow: 0 0 0 0 rgba(248, 150, 30, 0); }
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
            
            .dashboard-header, .content-section {
                padding: 20px;
            }
            
            .search-filter {
                flex-direction: column;
            }
            
            .action-buttons {
                flex-direction: column;
                gap: 8px;
            }
            
            .btn {
                min-width: auto;
                width: 100%;
            }
        }
    </style>
    
    <script>
        // Search functionality
        function searchRequests() {
            const input = document.getElementById('searchInput').value.toLowerCase();
            const rows = document.querySelectorAll('.requests-table tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(input) ? '' : 'none';
            });
        }
        
        // Filter by hospital
        function filterByHospital() {
            const select = document.getElementById('hospitalFilter');
            const hospital = select.value;
            const rows = document.querySelectorAll('.requests-table tbody tr');
            
            rows.forEach(row => {
                const hospitalCell = row.cells[7] ? row.cells[7].textContent : '';
                if (hospital === 'all' || hospitalCell.includes(hospital)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
        // Add row hover effect
        document.addEventListener('DOMContentLoaded', function() {
            const rows = document.querySelectorAll('.requests-table tbody tr');
            rows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    this.style.transition = 'all 0.3s ease';
                });
            });
            
            // Add animation to table rows
            setTimeout(() => {
                rows.forEach((row, index) => {
                    row.style.animation = `fadeInUp 0.5s ease-out ${index * 0.05}s both`;
                });
            }, 300);
        });
        
        // Confirmation for approve/reject
        function confirmAction(action, id) {
            const message = action === 'approve' 
                ? 'Are you sure you want to approve this appointment request?'
                : 'Are you sure you want to reject this appointment request?';
                
            if (confirm(message)) {
                return true;
            }
            return false;
        }
    </script>
</head>
<body>
    <!-- Admin Navbar -->
    <nav class="admin-navbar animate__animated animate__fadeInDown">
        <div class="logo">
            <i class="fas fa-shield-alt"></i>
            <h2>Vaccine<span style="color:#4361ee">Admin</span> Pro</h2>
        </div>
        <div class="nav-links">
            <a href="dashboard.php" class="active">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a href="appointment_requests.php">
                <i class="fas fa-calendar-check"></i> Requests
            </a>
            <a href="manage_hospitals.php">
                <i class="fas fa-hospital"></i> Hospitals
            </a>
            <a href="manage_vaccines.php">
                <i class="fas fa-syringe"></i> Vaccines
            </a>
            <a href="../logout.php" class="logout">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </nav>

    <div class="container">
        <!-- Dashboard Header -->
        <div class="dashboard-header">
            <h1>
                <i class="fas fa-calendar-alt"></i>
                Pending Appointment Requests
            </h1>
            <p>Review and manage vaccination appointment requests from parents</p>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-container">
            <div class="stat-card animate__animated animate__fadeInUp">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <h3>
                    <?php echo mysqli_num_rows($result_requests); ?>
                </h3>
                <p>Pending Requests</p>
            </div>
            
            <div class="stat-card animate__animated animate__fadeInUp" style="animation-delay: 0.1s">
                <div class="stat-icon">
                    <i class="fas fa-baby"></i>
                </div>
                <h3 id="totalChildren">0</h3>
                <p>Children Waiting</p>
            </div>
            
            <div class="stat-card animate__animated animate__fadeInUp" style="animation-delay: 0.2s">
                <div class="stat-icon">
                    <i class="fas fa-hospital-user"></i>
                </div>
                <h3 id="uniqueHospitals">0</h3>
                <p>Hospitals Involved</p>
            </div>
            
            <div class="stat-card animate__animated animate__fadeInUp" style="animation-delay: 0.3s">
                <div class="stat-icon">
                    <i class="fas fa-syringe"></i>
                </div>
                <h3 id="uniqueVaccines">0</h3>
                <p>Vaccine Types</p>
            </div>
        </div>
        
        <!-- Main Content Section -->
        <div class="content-section">
            <div class="section-header">
                <h3>
                    <i class="fas fa-list-ul"></i>
                    Request Details
                </h3>
                
                <div class="search-filter">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" 
                               id="searchInput" 
                               placeholder="Search by child, parent, vaccine..." 
                               onkeyup="searchRequests()">
                    </div>
                    
                    <select class="filter-select" id="hospitalFilter" onchange="filterByHospital()">
                        <option value="all">All Hospitals</option>
                        <?php
                        // Reset pointer to get hospital list
                        mysqli_data_seek($result_requests, 0);
                        $hospitals = [];
                        while($row = mysqli_fetch_assoc($result_requests)) {
                            $hospitals[$row['hospital_name']] = true;
                        }
                        mysqli_data_seek($result_requests, 0); // Reset again for main loop
                        
                        foreach(array_keys($hospitals) as $hospital) {
                            echo "<option value='$hospital'>$hospital</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>
            
            <?php if(mysqli_num_rows($result_requests) > 0): ?>
            <div class="table-container">
                <table class="requests-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Child Details</th>
                            <th>Parent Info</th>
                            <th>Vaccine</th>
                            <th>Dose</th>
                            <th>Hospital</th>
                            <th>Appointment Time</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $childCount = 0;
                        $hospitalSet = [];
                        $vaccineSet = [];
                        
                        while($row = mysqli_fetch_assoc($result_requests)): 
                            $age_days = floor((time() - strtotime($row['date_of_birth'])) / (60 * 60 * 24));
                            $age_months = floor($age_days / 30);
                            $childCount++;
                            $hospitalSet[$row['hospital_name']] = true;
                            $vaccineSet[$row['vaccine_name']] = true;
                        ?>
                        <tr class="animate__animated">
                            <td>
                                <strong style="color:#4361ee"><?php echo $row['request_id']; ?></strong>
                            </td>
                            
                            <td>
                                <div class="child-info">
                                    <div class="child-avatar">
                                        <i class="fas fa-baby"></i>
                                    </div>
                                    <div class="child-details">
                                        <h4><?php echo htmlspecialchars($row['child_name']); ?></h4>
                                        <p><?php echo $age_months; ?> months old</p>
                                    </div>
                                </div>
                            </td>
                            
                            <td>
                                <div class="parent-info">
                                    <span>
                                        <i class="fas fa-user"></i>
                                        <?php echo htmlspecialchars($row['parent_name']); ?>
                                    </span>
                                    <span>
                                        <i class="fas fa-phone"></i>
                                        <?php echo htmlspecialchars($row['parent_phone']); ?>
                                    </span>
                                </div>
                            </td>
                            
                            <td>
                                <div class="vaccine-info">
                                    <div class="vaccine-icon">
                                        <i class="fas fa-syringe"></i>
                                    </div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($row['vaccine_name']); ?></strong>
                                    </div>
                                </div>
                            </td>
                            
                            <td>
                                <div style="text-align:center">
                                    <span style="
                                        display:inline-block;
                                        width:35px;
                                        height:35px;
                                        line-height:35px;
                                        background:#e9ecef;
                                        border-radius:50%;
                                        font-weight:bold;
                                        color:#4361ee;
                                    ">
                                        <?php echo $row['dose_number']; ?>
                                    </span>
                                    <div style="font-size:12px; color:#6c757d; margin-top:5px">Dose</div>
                                </div>
                            </td>
                            
                            <td>
                                <div class="hospital-info">
                                    <div class="hospital-icon">
                                        <i class="fas fa-hospital"></i>
                                    </div>
                                    <div>
                                        <strong><?php echo htmlspecialchars($row['hospital_name']); ?></strong>
                                        <p style="font-size:13px; color:#6c757d; margin-top:2px">
                                            <?php echo htmlspecialchars($row['city']); ?>
                                        </p>
                                    </div>
                                </div>
                            </td>
                            
                            <td>
                                <div class="time-info">
                                    <div class="time-icon">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div>
                                        <strong>
                                            <?php echo date('d M Y', strtotime($row['preferred_date'])); ?>
                                        </strong>
                                        <p style="font-size:13px; color:#6c757d; margin-top:2px">
                                            <?php echo date('h:i A', strtotime($row['preferred_time'])); ?>
                                        </p>
                                    </div>
                                </div>
                            </td>
                            
                            <td>
                                <span class="status-badge status-pending">
                                    <i class="fas fa-clock"></i> Pending
                                </span>
                                <?php if(!empty($row['parent_notes'])): ?>
                                <div style="margin-top:8px">
                                    <small style="color:#6c757d; cursor:help" title="<?php echo htmlspecialchars($row['parent_notes']); ?>">
                                        <i class="fas fa-sticky-note"></i> Has notes
                                    </small>
                                </div>
                                <?php endif; ?>
                            </td>
                            
                            <td>
                                <div class="action-buttons">
                                    <a href="approve_request.php?id=<?php echo $row['request_id']; ?>" 
                                       class="btn btn-approve"
                                       onclick="return confirmAction('approve', <?php echo $row['request_id']; ?>)">
                                        <i class="fas fa-check"></i> Approve
                                    </a>
                                    <a href="reject_request.php?id=<?php echo $row['request_id']; ?>" 
                                       class="btn btn-reject"
                                       onclick="return confirmAction('reject', <?php echo $row['request_id']; ?>)">
                                        <i class="fas fa-times"></i> Reject
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
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
                    <span class="page-number">10</span>
                </div>
                <button class="pagination-btn">
                    Next <i class="fas fa-chevron-right"></i>
                </button>
            </div>
            
            <?php else: ?>
            <div class="empty-state animate__animated animate__fadeIn">
                <i class="far fa-calendar-check"></i>
                <h4>No Pending Requests</h4>
                <p>All appointment requests have been processed</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Update stats after page loads
        document.addEventListener('DOMContentLoaded', function() {
            // These would come from PHP in real implementation
            document.getElementById('totalChildren').textContent = '<?php echo $childCount; ?>';
            document.getElementById('uniqueHospitals').textContent = '<?php echo count($hospitalSet); ?>';
            document.getElementById('uniqueVaccines').textContent = '<?php echo count($vaccineSet); ?>';
            
            // Add animation to table rows
            const rows = document.querySelectorAll('.requests-table tbody tr');
            rows.forEach((row, index) => {
                row.style.animationDelay = `${index * 0.05}s`;
                row.classList.add('animate__fadeInUp');
            });
        });
    </script>
</body>
</html>
<?php mysqli_close($connection); ?>