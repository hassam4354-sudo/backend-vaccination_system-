<?php
// manage_children.php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'admin') {
    header("Location: ../login.php");
    exit();
}

// Direct database connection
$db_host = "localhost";
$db_name = "child_vaccination_system";
$db_user = "root";
$db_pass = "";

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Process actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $child_id = isset($_POST['child_id']) ? intval($_POST['child_id']) : 0;
    
    if ($child_id > 0) {
        switch ($action) {
            case 'toggle_status':
                $new_status = isset($_POST['status']) ? intval($_POST['status']) : 0;
                $stmt = $pdo->prepare("UPDATE children SET is_active = ? WHERE child_id = ?");
                $stmt->execute([$new_status, $child_id]);
                $_SESSION['success'] = "Child status updated successfully!";
                break;
                
            case 'delete':
                $stmt = $pdo->prepare("DELETE FROM children WHERE child_id = ?");
                $stmt->execute([$child_id]);
                $_SESSION['success'] = "Child deleted successfully!";
                break;
        }
        header("Location: manage_children.php");
        exit();
    }
}

// Search and filter
$search = $_GET['search'] ?? '';
$parent_id = $_GET['parent_id'] ?? 0;
$status = $_GET['status'] ?? 'all';

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query with conditions - USING ONLY POSITIONAL PARAMETERS
$conditions = [];
$params = [];

if (!empty($search)) {
    $conditions[] = "(c.full_name LIKE ? OR p.full_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($parent_id > 0) {
    $conditions[] = "c.parent_id = ?";
    $params[] = $parent_id;
}

if ($status !== 'all') {
    $conditions[] = "c.is_active = ?";
    $params[] = ($status == 'active' ? 1 : 0);
}

$where_clause = empty($conditions) ? "" : "WHERE " . implode(" AND ", $conditions);

// Count total - FIXED: Use positional parameters only
$count_sql = "SELECT COUNT(*) as total FROM children c LEFT JOIN parents p ON c.parent_id = p.parent_id";
if (!empty($where_clause)) {
    $count_sql .= " " . $where_clause;
}

$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params); // Execute with params array
$total_children = $count_stmt->fetch()['total'];
$total_pages = ceil($total_children / $limit);

// Get children - FIXED: Use positional parameters for WHERE and append LIMIT/OFFSET with bindValue
$sql = "SELECT c.*, p.full_name as parent_name, u.email as parent_email, u.phone as parent_phone
        FROM children c
        LEFT JOIN parents p ON c.parent_id = p.parent_id
        LEFT JOIN users u ON p.user_id = u.user_id";

if (!empty($where_clause)) {
    $sql .= " " . $where_clause;
}

$sql .= " ORDER BY c.created_at DESC LIMIT ? OFFSET ?";

$stmt = $pdo->prepare($sql);

// Bind parameters - FIXED: Use positional parameters for all
$param_index = 1;
foreach ($params as $value) {
    $stmt->bindValue($param_index++, $value);
}

// Bind LIMIT and OFFSET as integers
$stmt->bindValue($param_index++, $limit, PDO::PARAM_INT);
$stmt->bindValue($param_index++, $offset, PDO::PARAM_INT);

$stmt->execute();
$children = $stmt->fetchAll();

// Get parents for dropdown
$parents = $pdo->query("SELECT parent_id, full_name FROM parents ORDER BY full_name")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Children - Admin</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { 
            background: linear-gradient(135deg, #f0f9ff, #e6f3ff); 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        /* Sidebar/Navbar - Light Blue Theme */
        .sidebar { 
            background: linear-gradient(165deg, #ffffff, #f0fbff); 
            min-height: 100vh; 
            box-shadow: 5px 0 20px rgba(52, 152, 219, 0.15); 
            border-right: 1px solid #d4e6f1;
        }
        
        .sidebar h4 { 
            background: linear-gradient(135deg, #3498db, #2980b9);
            margin-bottom: 25px;
            padding: 25px 20px !important;
            font-weight: 700;
            letter-spacing: 1.5px;
            border-bottom: none;
            color: white !important;
            font-size: 1.3rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
        }
        
        .sidebar a { 
            color: #2c3e50; 
            text-decoration: none; 
            padding: 14px 25px; 
            display: block; 
            margin: 8px 15px;
            border-radius: 12px;
            transition: all 0.3s;
            font-weight: 500;
            font-size: 1rem;
            border: 1px solid transparent;
        }
        
        .sidebar a i {
            color: #3498db;
            width: 25px;
            text-align: center;
            margin-right: 10px;
            transition: all 0.3s;
        }
        
        .sidebar a:hover { 
            background: linear-gradient(135deg, #e1f0fa, #d4e6f1); 
            color: #1a5276;
            border: 1px solid #a9cce3;
            transform: translateX(8px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.2);
        }
        
        .sidebar a:hover i {
            color: #1a5276;
            transform: scale(1.1);
        }
        
        .sidebar a.active { 
            background: linear-gradient(135deg, #3498db, #2980b9); 
            color: white !important;
            box-shadow: 0 8px 20px rgba(52, 152, 219, 0.4);
            border: none;
        }
        
        .sidebar a.active i {
            color: white !important;
        }
        
        .page-header { 
            background: linear-gradient(120deg, #ffffff, #f0fbff); 
            padding: 25px; 
            border-radius: 15px; 
            margin-bottom: 25px; 
            box-shadow: 0 5px 20px rgba(52, 152, 219, 0.12);
            border-left: 6px solid #3498db;
            border-top: 1px solid #d4e6f1;
            border-right: 1px solid #d4e6f1;
            border-bottom: 1px solid #d4e6f1;
        }
        
        .page-header h1 {
            color: #1a5276;
            font-weight: 700;
        }
        
        .page-header p {
            color: #5d6d7e;
            font-size: 1.1rem;
        }
        
        .card { 
            border-radius: 20px; 
            box-shadow: 0 10px 25px rgba(52, 152, 219, 0.08); 
            border: 1px solid #e8f4fd;
            margin-bottom: 25px;
            background: white;
            backdrop-filter: blur(10px);
        }
        
        .card-header { 
            background: linear-gradient(to right, #f8fcff, #f0fbff); 
            border-bottom: 2px solid #c5e0fa; 
            padding: 18px 25px;
            border-radius: 20px 20px 0 0 !important;
        }
        
        .card-header h6 {
            color: #1f618d;
            font-weight: 700;
            font-size: 1rem;
            margin-bottom: 0;
        }
        
        .table { 
            margin-bottom: 0; 
        }
        
        .table thead th { 
            background: linear-gradient(135deg, #e1f0fa, #d4e9ff); 
            color: #154360; 
            font-weight: 700; 
            border-bottom: 3px solid #7fb3d5; 
            padding: 16px;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }
        
        .table tbody td {
            padding: 18px 16px;
            vertical-align: middle;
            border-bottom: 1px solid #e3f0fa;
            color: #2c3e50;
            background: white;
        }
        
        .table tbody tr:hover {
            background: linear-gradient(135deg, #f4fcff, #e8f6ff);
            transition: all 0.2s;
        }
        
        .badge { 
            font-size: 0.75rem; 
            padding: 6px 14px;
            border-radius: 30px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        .badge.bg-success {
            background: linear-gradient(135deg, #2ecc71, #27ae60) !important;
            box-shadow: 0 3px 10px rgba(46, 204, 113, 0.3);
        }
        
        .badge.bg-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b) !important;
            box-shadow: 0 3px 10px rgba(231, 76, 60, 0.3);
        }
        
        .badge.bg-info {
            background: linear-gradient(135deg, #5dade2, #3498db) !important;
            box-shadow: 0 3px 10px rgba(52, 152, 219, 0.3);
        }
        
        .badge.bg-primary {
            background: linear-gradient(135deg, #3498db, #2980b9) !important;
        }
        
        .profile-img { 
            width: 48px; 
            height: 48px; 
            object-fit: cover; 
            border-radius: 50%; 
            border: 3px solid white;
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.25);
        }
        
        .bg-light.profile-img {
            background: linear-gradient(135deg, #d4e6f1, #c5e0fa) !important;
            color: #1a5276;
            border: 3px solid white;
        }
        
        .table-actions .btn { 
            padding: 0.4rem 0.7rem; 
            margin: 0 3px;
            border-radius: 10px;
            transition: all 0.2s;
            font-size: 0.85rem;
        }
        
        .btn-outline-primary {
            color: #2980b9;
            border-color: #a9cce3;
            background: white;
        }
        
        .btn-outline-primary:hover {
            background: linear-gradient(135deg, #3498db, #2980b9);
            border-color: #2980b9;
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 8px 15px rgba(52, 152, 219, 0.3);
        }
        
        .btn-outline-info {
            color: #1f618d;
            border-color: #a9cce3;
            background: white;
        }
        
        .btn-outline-info:hover {
            background: linear-gradient(135deg, #5dade2, #3498db);
            border-color: #3498db;
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 8px 15px rgba(93, 173, 226, 0.3);
        }
        
        .btn-outline-warning {
            color: #e67e22;
            border-color: #fad7a0;
            background: white;
        }
        
        .btn-outline-warning:hover {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            border-color: #e67e22;
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 8px 15px rgba(243, 156, 18, 0.3);
        }
        
        .btn-outline-danger {
            color: #c0392b;
            border-color: #f5b7b1;
            background: white;
        }
        
        .btn-outline-danger:hover {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            border-color: #c0392b;
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 8px 15px rgba(231, 76, 60, 0.3);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3498db, #2980b9);
            border: none;
            padding: 10px 25px;
            border-radius: 30px;
            font-weight: 600;
            transition: all 0.3s;
            color: white;
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #2980b9, #1f618d);
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(52, 152, 219, 0.4);
            color: white;
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #bdc3c7, #95a5a6);
            border: none;
            padding: 10px 25px;
            border-radius: 30px;
            font-weight: 600;
            transition: all 0.3s;
            color: white;
        }
        
        .btn-secondary:hover {
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(149, 165, 166, 0.4);
            color: white;
        }
        
        .form-control, .form-select {
            border-radius: 30px;
            border: 2px solid #e3f0fa;
            padding: 12px 25px;
            transition: all 0.3s;
            background: white;
            color: #2c3e50;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.15);
            border-width: 2px;
        }
        
        /* Statistics Cards */
        .bg-primary.text-white .card-body {
            background: linear-gradient(145deg, #3498db, #2980b9);
            border-radius: 20px;
            box-shadow: 0 15px 30px rgba(52, 152, 219, 0.3);
        }
        
        .bg-success.text-white .card-body {
            background: linear-gradient(145deg, #2ecc71, #27ae60);
            border-radius: 20px;
            box-shadow: 0 15px 30px rgba(46, 204, 113, 0.3);
        }
        
        .bg-info.text-white .card-body {
            background: linear-gradient(145deg, #5dade2, #3498db);
            border-radius: 20px;
            box-shadow: 0 15px 30px rgba(93, 173, 226, 0.3);
        }
        
        .bg-warning.text-white .card-body {
            background: linear-gradient(145deg, #f39c12, #e67e22);
            border-radius: 20px;
            box-shadow: 0 15px 30px rgba(243, 156, 18, 0.3);
        }
        
        .card.bg-primary, .card.bg-success, .card.bg-info, .card.bg-warning {
            border: none;
            border-radius: 20px;
            transition: all 0.3s;
        }
        
        .card.bg-primary:hover, .card.bg-success:hover, .card.bg-info:hover, .card.bg-warning:hover {
            transform: translateY(-5px);
        }
        
        .fa-2x {
            filter: drop-shadow(0 5px 10px rgba(0,0,0,0.1));
        }
        
        /* Pagination */
        .page-link {
            color: #2980b9;
            border: 2px solid #e3f0fa;
            margin: 0 5px;
            border-radius: 12px;
            padding: 10px 18px;
            transition: all 0.2s;
            font-weight: 500;
            background: white;
        }
        
        .page-link:hover {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            border-color: #3498db;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }
        
        .page-item.active .page-link {
            background: linear-gradient(135deg, #3498db, #2980b9);
            border-color: #3498db;
            color: white;
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.4);
        }
        
        /* Modal Styling */
        .modal-content {
            border-radius: 25px;
            border: none;
            box-shadow: 0 20px 50px rgba(52, 152, 219, 0.25);
            overflow: hidden;
        }
        
        .modal-header {
            background: linear-gradient(145deg, #3498db, #2980b9);
            color: white;
            border-radius: 25px 25px 0 0;
            padding: 22px 30px;
            border-bottom: none;
        }
        
        .modal-header .modal-title {
            font-weight: 700;
            letter-spacing: 0.5px;
            color: white;
            font-size: 1.3rem;
        }
        
        .modal-header .btn-close {
            background-color: white;
            opacity: 0.9;
            border-radius: 50%;
            padding: 8px;
        }
        
        .modal-body {
            padding: 30px;
            background: #f8fcff;
        }
        
        .modal-footer {
            border-top: 2px solid #e3f0fa;
            padding: 22px 30px;
            background: white;
            border-radius: 0 0 25px 25px;
        }
        
        /* Alert Styling */
        .alert-success {
            background: linear-gradient(145deg, #d4edda, #c3e6cb);
            color: #155724;
            border-left: 6px solid #28a745;
            border-radius: 15px;
            border-top: 1px solid #c3e6cb;
            border-right: 1px solid #c3e6cb;
            border-bottom: 1px solid #c3e6cb;
            padding: 18px 25px;
        }
        
        /* Text colors */
        .text-muted {
            color: #5d6d7e !important;
        }
        
        .text-primary {
            color: #3498db !important;
        }
        
        /* Footer */
        .card-footer {
            background: linear-gradient(to right, #f8fcff, #f0fbff);
            border-top: 2px solid #d4e6f1;
            padding: 22px;
            border-radius: 0 0 20px 20px;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .sidebar {
                min-height: auto;
                background: white;
            }
            
            .page-header {
                padding: 20px;
            }
            
            .table-actions .btn-group {
                display: flex;
                flex-direction: column;
                gap: 5px;
            }
            
            .table-actions .btn {
                margin: 2px 0;
                border-radius: 10px !important;
            }
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }
        
        ::-webkit-scrollbar-track {
            background: #e8f4fd;
            border-radius: 5px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #a9cce3, #7fb3d5);
            border-radius: 5px;
            border: 2px solid #e8f4fd;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, #3498db, #2980b9);
        }
        
        .text-white-50 {
            color: rgba(255,255,255,0.85) !important;
        }
        
        .bg-light {
            background-color: #f0fbff !important;
        }
        
        .pagination {
            margin-bottom: 0;
            gap: 5px;
        }
        
        .table-actions .btn-group {
            gap: 8px;
        }
        
        /* Navbar/Sidebar additional styling */
        .sidebar .nav {
            padding: 0 5px;
        }
        
        .sidebar .nav a i {
            font-size: 1.1rem;
        }
        
        .sidebar h4 i {
            margin-right: 12px;
            color: white;
            filter: drop-shadow(0 2px 5px rgba(0,0,0,0.2));
        }
        
        /* Main content area */
        .col-md-10 {
            background: linear-gradient(135deg, #f0f9ff, #e6f3ff);
            min-height: 100vh;
        }
        
        /* Container fluid */
        .container-fluid {
            padding: 0;
            background: #f0f9ff;
        }
        
        /* Row styling */
        .row {
            margin: 0;
        }
        
        /* Dashboard cards hover effect */
        .card:not(.bg-primary):not(.bg-success):not(.bg-info):not(.bg-warning):hover {
            box-shadow: 0 15px 35px rgba(52, 152, 219, 0.15);
            transform: translateY(-2px);
            transition: all 0.3s;
        }
        
        /* Filter card specific */
        .card .form-control, .card .form-select {
            background: white;
            border-color: #d4e6f1;
        }
        
        /* Table card */
        .card:last-child {
            margin-bottom: 0;
        }
        
        /* Active status badge */
        .badge.bg-success {
            padding: 6px 16px;
        }
        
        /* Inactive status badge */
        .badge.bg-danger {
            padding: 6px 16px;
        }
        
        /* Gender badge */
        .badge.bg-info {
            padding: 6px 16px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar - Light Blue & White Theme -->
            <div class="col-md-2 sidebar p-0">
                <h4 class="p-3 border-bottom">
                    <i class="fas fa-shield-alt me-2"></i>Admin Panel
                </h4>
                <nav class="nav flex-column">
                    <a href="dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                    <a href="manage_children.php" class="active">
                        <i class="fas fa-child me-2"></i>Manage Children
                    </a>
                    <a href="manage_parents.php">
                        <i class="fas fa-users me-2"></i>Manage Parents
                    </a>
                    <a href="manage_hospitals.php">
                        <i class="fas fa-hospital me-2"></i>Manage Hospitals
                    </a>
                    <a href="appointment_requests.php">
                        <i class="fas fa-calendar-check me-2"></i>Appointments
                    </a>
                    <a href="../logout.php" class="mt-4">
                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                    </a>
                </nav>
                
                <!-- Admin Profile Mini Section -->
                <div class="mt-auto p-3 position-absolute bottom-0 w-100">
                    <div class="d-flex align-items-center p-3 rounded" style="background: linear-gradient(135deg, #e1f0fa, #d4e6f1);">
                        <div class="bg-white rounded-circle p-2 me-2">
                            <i class="fas fa-user-shield text-primary"></i>
                        </div>
                        <div>
                            <small class="text-dark fw-bold">Administrator</small>
                            <small class="text-muted d-block" style="font-size: 11px;">Super Admin</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10">
                <div class="p-4">
                    <!-- Header -->
                    <div class="page-header">
                        <h1 class="h3">
                            <i class="fas fa-child text-primary me-2"></i>Manage Children
                        </h1>
                        <p class="text-muted">View and manage all children registered in the system</p>
                    </div>

                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="fas fa-check-circle me-2"></i>
                            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Filters -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-filter me-2" style="color: #3498db;"></i>Filters
                            </h6>
                        </div>
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-4">
                                    <input type="text" class="form-control" name="search" 
                                           placeholder="🔍 Search child or parent name..." 
                                           value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="col-md-3">
                                    <select class="form-select" name="parent_id">
                                        <option value="0">👥 All Parents</option>
                                        <?php foreach ($parents as $parent): ?>
                                            <option value="<?php echo $parent['parent_id']; ?>" 
                                                <?php echo $parent_id == $parent['parent_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($parent['full_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <select class="form-select" name="status">
                                        <option value="all" <?php echo $status == 'all' ? 'selected' : ''; ?>>📊 All Status</option>
                                        <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>✅ Active</option>
                                        <option value="inactive" <?php echo $status == 'inactive' ? 'selected' : ''; ?>>❌ Inactive</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-1"></i> Search
                                    </button>
                                    <a href="manage_children.php" class="btn btn-secondary">
                                        <i class="fas fa-redo me-1"></i> Reset
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="text-white-50">Total Children</h6>
                                            <h3><?php echo $total_children; ?></h3>
                                        </div>
                                        <div>
                                            <i class="fas fa-child fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <?php
                            $active_count = $pdo->query("SELECT COUNT(*) FROM children WHERE is_active = 1")->fetchColumn();
                            ?>
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="text-white-50">Active</h6>
                                            <h3><?php echo $active_count; ?></h3>
                                        </div>
                                        <div>
                                            <i class="fas fa-check-circle fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <?php
                            $male_count = $pdo->query("SELECT COUNT(*) FROM children WHERE gender = 'Male'")->fetchColumn();
                            ?>
                            <div class="card bg-info text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="text-white-50">Boys</h6>
                                            <h3><?php echo $male_count; ?></h3>
                                        </div>
                                        <div>
                                            <i class="fas fa-male fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <?php
                            $female_count = $pdo->query("SELECT COUNT(*) FROM children WHERE gender = 'Female'")->fetchColumn();
                            ?>
                            <div class="card bg-warning text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h6 class="text-white-50">Girls</h6>
                                            <h3><?php echo $female_count; ?></h3>
                                        </div>
                                        <div>
                                            <i class="fas fa-female fa-2x"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Children Table -->
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">
                                <i class="fas fa-list me-2" style="color: #3498db;"></i>Children List
                            </h6>
                            <span class="badge bg-primary">Total: <?php echo $total_children; ?></span>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($children)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-child fa-4x text-muted mb-3" style="color: #a9cce3 !important;"></i>
                                    <p class="text-muted fs-5">No children found</p>
                                    <?php if ($search || $parent_id > 0 || $status !== 'all'): ?>
                                        <a href="manage_children.php" class="btn btn-primary mt-3">
                                            <i class="fas fa-undo me-2"></i>Clear Filters
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Child Name</th>
                                                <th>Parent</th>
                                                <th>Date of Birth</th>
                                                <th>Gender</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($children as $child): 
                                                $dob = new DateTime($child['date_of_birth']);
                                                $today = new DateTime();
                                                $age = $today->diff($dob);
                                            ?>
                                                <tr>
                                                    <td class="fw-bold" style="color: #1a5276;">#<?php echo $child['child_id']; ?></td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <?php if (!empty($child['photo_url'])): ?>
                                                                <img src="../<?php echo htmlspecialchars($child['photo_url']); ?>" 
                                                                     class="profile-img me-3"
                                                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
                                                            <?php endif; ?>
                                                            <div class="profile-img bg-light d-flex align-items-center justify-content-center me-3"
                                                                 style="display: <?php echo empty($child['photo_url']) ? 'flex' : 'none'; ?>">
                                                                <i class="fas fa-child" style="color: #3498db;"></i>
                                                            </div>
                                                            <div>
                                                                <strong style="color: #1a5276;"><?php echo htmlspecialchars($child['full_name']); ?></strong>
                                                                <?php if ($child['blood_group']): ?>
                                                                    <div class="text-muted small">
                                                                        <i class="fas fa-tint me-1" style="color: #e74c3c;"></i>
                                                                        Blood: <?php echo $child['blood_group']; ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div>
                                                            <strong style="color: #2874a6;"><?php echo htmlspecialchars($child['parent_name']); ?></strong>
                                                            <div class="text-muted small">
                                                                <i class="fas fa-envelope me-1" style="color: #3498db;"></i>
                                                                <?php echo htmlspecialchars($child['parent_email']); ?>
                                                                <?php if ($child['parent_phone']): ?>
                                                                    <br><i class="fas fa-phone me-1" style="color: #2ecc71;"></i>
                                                                    <?php echo htmlspecialchars($child['parent_phone']); ?>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span style="color: #2c3e50;"><?php echo $dob->format('d M, Y'); ?></span>
                                                        <div class="text-muted small">
                                                            <i class="fas fa-birthday-cake me-1" style="color: #e67e22;"></i>
                                                            <?php echo $age->y . ' years, ' . $age->m . ' months'; ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info">
                                                            <i class="fas <?php echo $child['gender'] == 'Male' ? 'fa-male' : 'fa-female'; ?> me-1"></i>
                                                            <?php echo htmlspecialchars($child['gender']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge <?php echo $child['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
                                                            <i class="fas <?php echo $child['is_active'] ? 'fa-check-circle' : 'fa-times-circle'; ?> me-1"></i>
                                                            <?php echo $child['is_active'] ? 'Active' : 'Inactive'; ?>
                                                        </span>
                                                    </td>
                                                    <td class="table-actions">
                                                        <div class="btn-group btn-group-sm">
                                                            <button type="button" class="btn btn-outline-primary" 
                                                                    title="View Details"
                                                                    onclick="viewChild(<?php echo $child['child_id']; ?>)">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-outline-info" 
                                                                    title="Vaccination History"
                                                                    onclick="viewVaccinations(<?php echo $child['child_id']; ?>)">
                                                                <i class="fas fa-syringe"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-outline-warning" 
                                                                    title="<?php echo $child['is_active'] ? 'Deactivate' : 'Activate'; ?>"
                                                                    onclick="toggleStatus(<?php echo $child['child_id']; ?>, <?php echo $child['is_active'] ? 0 : 1; ?>)">
                                                                <i class="fas fa-power-off"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-outline-danger" 
                                                                    title="Delete"
                                                                    onclick="deleteChild(<?php echo $child['child_id']; ?>)">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($total_pages > 1): ?>
                            <div class="card-footer">
                                <nav aria-label="Page navigation">
                                    <ul class="pagination justify-content-center mb-0">
                                        <?php if ($page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?<?php 
                                                    $query = $_GET;
                                                    $query['page'] = $page - 1;
                                                    echo http_build_query($query);
                                                ?>">
                                                    <i class="fas fa-chevron-left"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                        
                                        <?php 
                                        // Show page numbers
                                        $start_page = max(1, $page - 2);
                                        $end_page = min($total_pages, $start_page + 4);
                                        
                                        for ($i = $start_page; $i <= $end_page; $i++): ?>
                                            <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                                <a class="page-link" href="?<?php 
                                                    $query = $_GET;
                                                    $query['page'] = $i;
                                                    echo http_build_query($query);
                                                ?>">
                                                    <?php echo $i; ?>
                                                </a>
                                            </li>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?<?php 
                                                    $query = $_GET;
                                                    $query['page'] = $page + 1;
                                                    echo http_build_query($query);
                                                ?>">
                                                    <i class="fas fa-chevron-right"></i>
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </nav>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- View Child Modal -->
    <div class="modal fade" id="viewChildModal">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-child me-2"></i>Child Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="childDetails">
                    <!-- Content will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Vaccinations Modal -->
    <div class="modal fade" id="viewVaccinationsModal">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-syringe me-2"></i>Vaccination History
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="vaccinationDetails">
                    <!-- Content will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function viewChild(childId) {
        // Simple AJAX without fetch for compatibility
        const xhr = new XMLHttpRequest();
        xhr.open('GET', 'get_child_details.php?id=' + childId, true);
        xhr.onload = function() {
            if (xhr.status === 200) {
                document.getElementById('childDetails').innerHTML = xhr.responseText;
                new bootstrap.Modal(document.getElementById('viewChildModal')).show();
            } else {
                alert('Error loading child details');
            }
        };
        xhr.send();
    }
    
    function viewVaccinations(childId) {
        const xhr = new XMLHttpRequest();
        xhr.open('GET', 'get_vaccination_history.php?child_id=' + childId, true);
        xhr.onload = function() {
            if (xhr.status === 200) {
                document.getElementById('vaccinationDetails').innerHTML = xhr.responseText;
                new bootstrap.Modal(document.getElementById('viewVaccinationsModal')).show();
            } else {
                alert('Error loading vaccination history');
            }
        };
        xhr.send();
    }
    
    function toggleStatus(childId, newStatus) {
        if (confirm(newStatus ? '✅ Activate this child?' : '❌ Deactivate this child?')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'manage_children.php';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'toggle_status';
            
            const childInput = document.createElement('input');
            childInput.type = 'hidden';
            childInput.name = 'child_id';
            childInput.value = childId;
            
            const statusInput = document.createElement('input');
            statusInput.type = 'hidden';
            statusInput.name = 'status';
            statusInput.value = newStatus;
            
            form.appendChild(actionInput);
            form.appendChild(childInput);
            form.appendChild(statusInput);
            
            document.body.appendChild(form);
            form.submit();
        }
    }
    
    function deleteChild(childId) {
        if (confirm('⚠️ Are you sure you want to delete this child? This action cannot be undone.')) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'manage_children.php';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'delete';
            
            const childInput = document.createElement('input');
            childInput.type = 'hidden';
            childInput.name = 'child_id';
            childInput.value = childId;
            
            form.appendChild(actionInput);
            form.appendChild(childInput);
            
            document.body.appendChild(form);
            form.submit();
        }
    }
    
    // Auto-hide success message after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
    </script>
</body>
</html>