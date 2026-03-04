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

// Count total
$count_sql = "SELECT COUNT(*) as total FROM children c LEFT JOIN parents p ON c.parent_id = p.parent_id";
if (!empty($where_clause)) {
    $count_sql .= " " . $where_clause;
}

$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_children = $count_stmt->fetch()['total'];
$total_pages = ceil($total_children / $limit);

// Get children
$sql = "SELECT c.*, p.full_name as parent_name, u.email as parent_email, u.phone as parent_phone
        FROM children c
        LEFT JOIN parents p ON c.parent_id = p.parent_id
        LEFT JOIN users u ON p.user_id = u.user_id";

if (!empty($where_clause)) {
    $sql .= " " . $where_clause;
}

$sql .= " ORDER BY c.created_at DESC LIMIT ? OFFSET ?";

$stmt = $pdo->prepare($sql);

// Bind parameters
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
    <title>Manage Children - Admin Panel</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* Google Fonts */
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }
        
        :root {
            /* White & Blue Color Palette */
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-light: #60a5fa;
            --primary-soft: #dbeafe;
            --white: #ffffff;
            --white-off: #f8fafc;
            --gray-50: #f1f5f9;
            --gray-100: #e2e8f0;
            --gray-200: #cbd5e1;
            --gray-300: #94a3b8;
            --gray-400: #64748b;
            --gray-500: #475569;
            --gray-600: #334155;
            --gray-700: #1e293b;
            --blue-light: #eff6ff;
            --blue-soft: #bfdbfe;
            --success: #10b981;
            --success-light: #d1fae5;
            --warning: #f59e0b;
            --warning-light: #fef3c7;
            --danger: #ef4444;
            --danger-light: #fee2e2;
            
            /* Shadows */
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --shadow-md: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1);
            --shadow-lg: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
            --shadow-blue: 0 10px 25px -5px rgba(37, 99, 235, 0.2);
            
            /* Border Radius */
            --radius-sm: 6px;
            --radius: 10px;
            --radius-md: 14px;
            --radius-lg: 18px;
            --radius-xl: 22px;
            
            /* Transitions */
            --transition: all 0.2s ease;
        }
        
        body {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            min-height: 100vh;
            color: var(--gray-700);
        }
        
        /* Dashboard Layout */
        .dashboard-layout {
            display: block;
            min-height: 100vh;
        }
        
        /* ===== TOP NAVBAR ===== */
        .admin-navbar {
            background: #ffffff;
            border-bottom: 2px solid #e8eeff;
            padding: 0 35px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 68px;
            box-shadow: 0 2px 16px rgba(26,111,196,0.08);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .admin-navbar .logo { display: flex; align-items: center; gap: 10px; }
        .admin-navbar .logo-icon {
            width: 40px; height: 40px;
            background: linear-gradient(135deg, #1a6fc4, #1155a0);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px; color: white;
        }
        .admin-navbar .logo h2 { font-size: 20px; font-weight: 700; color: #1155a0; letter-spacing: -0.3px; }
        .nav-links { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
        .nav-links a {
            color: #4b6cb7; text-decoration: none; padding: 8px 14px;
            border-radius: 8px; font-size: 13.5px; font-weight: 500;
            transition: all 0.2s; display: flex; align-items: center; gap: 6px;
        }
        .nav-links a:hover { background: #eff6ff; color: #1155a0; }
        .nav-links a.active { background: #dbeafe; color: #1155a0; font-weight: 600; }
        .nav-links a.logout { background: #fee2e2; color: #dc2626; }
        .nav-links a.logout:hover { background: #fecaca; }

        /* ===== MAIN CONTENT ===== */
        .main-content {
            padding: 30px;
            background: #f8fafc;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1600px;
            margin: 0 auto;
        }
        
        /* ===== PAGE HEADER ===== */
        .page-header {
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-md);
            animation: fadeIn 0.5s ease;
            display: flex;
            align-items: stretch;
            overflow: hidden;
            min-height: 200px;
            border: none;
            padding: 0;
        }

        .page-header-text {
            flex: 1;
            padding: 40px 45px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: linear-gradient(135deg, #1a6fc4, #0d47a1);
            position: relative;
            overflow: hidden;
        }

        .page-header-text::before {
            content: '';
            position: absolute;
            bottom: -30px;
            left: -20px;
            width: 120px;
            height: 120px;
            background: rgba(255,255,255,0.08);
            transform: rotate(45deg);
        }

        .page-header-text::after {
            content: '';
            position: absolute;
            top: -20px;
            right: 30px;
            width: 80px;
            height: 80px;
            border: 3px solid rgba(255,255,255,0.15);
            border-radius: 50%;
            box-shadow: 0 0 0 15px rgba(255,255,255,0.07), 0 0 0 30px rgba(255,255,255,0.04);
        }

        .page-header h1 {
            color: #ffffff;
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 14px;
            line-height: 1.2;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            flex-direction: column;
            gap: 0;
        }

        .page-header h1 i {
            display: none;
        }

        .page-header h1 .header-icon {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            font-size: 32px;
            font-weight: 800;
        }

        .page-header h1 .header-icon i {
            display: inline;
            color: white;
            background: rgba(255,255,255,0.2);
            padding: 10px;
            border-radius: 12px;
            font-size: 22px;
        }

        .page-header p {
            color: rgba(255,255,255,0.88);
            font-size: 14.5px;
            line-height: 1.7;
            margin: 0 0 22px 0;
            max-width: 480px;
        }

        .page-header p i {
            display: none;
        }

        .page-header-img {
            width: 45%;
            flex-shrink: 0;
            overflow: hidden;
            position: relative;
        }

        .page-header-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
            }
            .page-header-text {
                padding: 30px 25px;
            }
            .page-header-img {
                width: 100%;
                height: 200px;
            }
        }
        
        /* ===== STATS GRID ===== */
        /* ===== STATS GRID ===== */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 22px;
            margin-bottom: 30px;
        }

        .stat-card {
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(37,99,235,0.10);
            transition: all 0.3s ease;
            border: none;
            position: relative;
            padding: 0;
        }
        .stat-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 18px 40px rgba(37,99,235,0.18);
        }

        /* Per card gradient */
        .stat-card:nth-child(1) .stat-top-bg { background: linear-gradient(135deg, #2563eb, #1d4ed8); }
        .stat-card:nth-child(2) .stat-top-bg { background: linear-gradient(135deg, #10b981, #059669); }
        .stat-card:nth-child(3) .stat-top-bg { background: linear-gradient(135deg, #0891b2, #0e7490); }
        .stat-card:nth-child(4) .stat-top-bg { background: linear-gradient(135deg, #f472b6, #ec4899); }

        .stat-top-bg {
            padding: 22px 22px 18px;
            position: relative;
            overflow: hidden;
        }
        /* decorative circle */
        .stat-top-bg::after {
            content: '';
            position: absolute;
            top: -18px; right: -18px;
            width: 90px; height: 90px;
            background: rgba(255,255,255,0.10);
            border-radius: 50%;
        }
        .stat-top-bg::before {
            content: '';
            position: absolute;
            bottom: -30px; right: 30px;
            width: 60px; height: 60px;
            background: rgba(255,255,255,0.07);
            border-radius: 50%;
        }

        .stat-icon-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 14px;
        }
        .stat-icon {
            width: 52px; height: 52px;
            background: rgba(255,255,255,0.22);
            border-radius: 14px;
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 22px;
            border: 1.5px solid rgba(255,255,255,0.35);
            transition: all 0.3s ease;
        }
        .stat-card:hover .stat-icon {
            background: rgba(255,255,255,0.35);
            transform: rotate(8deg) scale(1.1);
        }
        .stat-badge {
            background: rgba(255,255,255,0.20);
            color: white;
            font-size: 11px; font-weight: 700;
            padding: 4px 10px; border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.3);
            letter-spacing: 0.3px;
        }
        .stat-number {
            font-size: 38px; font-weight: 800;
            color: white; line-height: 1;
            margin-bottom: 4px;
        }
        .stat-label-top {
            font-size: 13px; color: rgba(255,255,255,0.82);
            font-weight: 500;
        }

        .stat-bottom {
            background: white;
            padding: 13px 22px 15px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .stat-trend {
            display: inline-flex; align-items: center; gap: 5px;
            font-size: 12px; font-weight: 600;
            color: var(--gray-500);
        }
        .trend-up { color: #10b981; }
        .trend-down { color: #ef4444; }
        .stat-progress {
            height: 4px; width: 60px;
            background: var(--gray-100); border-radius: 10px; overflow: hidden;
        }
        .stat-progress-fill { height: 100%; border-radius: 10px; }
        .stat-card:nth-child(1) .stat-progress-fill { background: #2563eb; width: 75%; }
        .stat-card:nth-child(2) .stat-progress-fill { background: #10b981; width: 88%; }
        .stat-card:nth-child(3) .stat-progress-fill { background: #0891b2; width: 55%; }
        .stat-card:nth-child(4) .stat-progress-fill { background: #ec4899; width: 48%; }

        /* hide old unused classes */
        .stat-content { display: none; }
        .stat-icon-wrapper { display: none; }
        
        /* ===== FILTERS SECTION ===== */
        .filters-section {
            background: white;
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
            border: 1px solid var(--gray-200);
        }
        
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .filter-group {
            position: relative;
        }
        
        .filter-group i {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--primary);
            font-size: 16px;
        }
        
        .filter-input,
        .filter-select {
            width: 100%;
            padding: 14px 20px 14px 50px;
            border: 2px solid var(--gray-200);
            border-radius: 40px;
            font-size: 14px;
            transition: var(--transition);
            background: white;
            color: var(--gray-600);
            outline: none;
        }
        
        .filter-select {
            padding: 14px 20px 14px 50px;
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 20px center;
            background-size: 16px;
        }
        
        .filter-input:focus,
        .filter-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
        }
        
        .filter-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 20px;
        }
        
        /* ===== BUTTONS ===== */
        .btn {
            padding: 12px 28px;
            border: none;
            border-radius: 40px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: white;
            color: var(--gray-600);
            border: 1px solid var(--gray-200);
        }
        
        .btn i {
            font-size: 16px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            box-shadow: 0 8px 18px rgba(37, 99, 235, 0.25);
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 25px rgba(37, 99, 235, 0.35);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border: none;
        }
        
        .btn-success:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 18px rgba(16, 185, 129, 0.3);
        }
        
        .btn-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            border: none;
        }
        
        .btn-warning:hover {
            transform: translateY(-3px);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            border: none;
        }
        
        .btn-danger:hover {
            transform: translateY(-3px);
        }
        
        .btn-reset {
            background: var(--gray-50);
            border: 1px solid var(--gray-200);
        }
        
        .btn-reset:hover {
            background: var(--gray-200);
            transform: translateY(-3px);
        }
        
        .btn-search {
            background: var(--primary);
            color: white;
            border: none;
        }
        
        .btn-search:hover {
            background: var(--primary-dark);
            transform: translateY(-3px);
        }
        
        /* ===== CARDS ===== */
        .card { 
            border-radius: 18px; 
            box-shadow: var(--shadow); 
            border: 1px solid var(--gray-200);
            margin-bottom: 25px;
            background: white;
            transition: var(--transition);
            overflow: hidden;
            animation: fadeIn 0.5s ease;
        }
        
        .card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-3px);
            border-color: var(--primary-light);
        }
        
        .card-header { 
            background: linear-gradient(to right, #f9fdff, #f0f9ff); 
            border-bottom: 2px solid var(--gray-200); 
            padding: 18px 25px;
            border-radius: 18px 18px 0 0 !important;
        }
        
        .card-header h6 {
            color: var(--gray-700);
            font-weight: 700;
            font-size: 1rem;
            margin-bottom: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .card-header h6 i {
            color: var(--primary);
        }
        
        .card-body {
            padding: 25px;
            background: white;
        }
        
        .card-footer {
            background: linear-gradient(to right, #f9fdff, #f0f9ff);
            border-top: 2px solid var(--gray-200);
            padding: 18px 25px;
            border-radius: 0 0 18px 18px;
        }
        
        /* ===== TABLES ===== */
        .table-responsive {
            border-radius: 16px;
            overflow-x: auto;
        }
        
        .table { 
            margin-bottom: 0; 
            width: 100%;
        }
        
        .table thead th { 
            background: linear-gradient(135deg, #f0f9ff, #e6f3ff); 
            color: var(--gray-700); 
            font-weight: 600; 
            border-bottom: 3px solid var(--gray-300); 
            padding: 16px 18px;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
        }
        
        .table tbody td {
            padding: 16px 18px;
            vertical-align: middle;
            border-bottom: 1px solid var(--gray-200);
            color: var(--gray-600);
            background: white;
            font-size: 0.95rem;
        }
        
        .table tbody tr {
            transition: all 0.2s ease;
        }
        
        .table tbody tr:hover {
            background: linear-gradient(135deg, #f8fcff, #f0f9ff);
        }
        
        /* ===== BADGES ===== */
        .badge { 
            font-size: 0.75rem; 
            padding: 6px 16px;
            border-radius: 30px;
            font-weight: 600;
            letter-spacing: 0.3px;
            display: inline-block;
            text-align: center;
            min-width: 85px;
        }
        
        .badge-success {
            background: linear-gradient(135deg, #10b981, #059669) !important;
            color: white;
        }
        
        .badge-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626) !important;
            color: white;
        }
        
        .badge-info {
            background: linear-gradient(135deg, #60a5fa, #3b82f6) !important;
            color: white;
        }
        
        .badge-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark)) !important;
            color: white;
        }
        
        .badge-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706) !important;
            color: white;
        }
        
        /* ===== PROFILE IMAGES ===== */
        .profile-img { 
            width: 45px; 
            height: 45px; 
            object-fit: cover; 
            border-radius: 12px; 
            border: 2px solid white;
            box-shadow: 0 4px 10px rgba(37, 99, 235, 0.2);
        }
        
        .bg-light.profile-img {
            background: linear-gradient(135deg, #dbeafe, #bfdbfe) !important;
            color: var(--primary);
            border: 2px solid white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1rem;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .user-details h6 {
            margin: 0;
            font-weight: 600;
            color: var(--gray-700);
            font-size: 0.95rem;
        }
        
        .user-details small {
            color: var(--gray-500);
            font-size: 0.8rem;
        }
        
        /* ===== BUTTONS & ACTIONS ===== */
        .table-actions {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        
        .table-actions .btn { 
            padding: 0.4rem 0.8rem; 
            border-radius: 10px;
            transition: all 0.2s ease;
            font-size: 0.8rem;
            font-weight: 500;
            border-width: 1px;
        }
        
        .btn-outline-primary {
            color: var(--primary);
            border-color: var(--gray-200);
            background: white;
        }
        
        .btn-outline-primary:hover {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-outline-info {
            color: var(--primary-dark);
            border-color: var(--gray-200);
            background: white;
        }
        
        .btn-outline-info:hover {
            background: linear-gradient(135deg, #60a5fa, #3b82f6);
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-outline-warning {
            color: #d97706;
            border-color: var(--gray-200);
            background: white;
        }
        
        .btn-outline-warning:hover {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            transform: translateY(-2px);
        }
        
        .btn-outline-danger {
            color: #dc2626;
            border-color: var(--gray-200);
            background: white;
        }
        
        .btn-outline-danger:hover {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            transform: translateY(-2px);
        }
        
        /* ===== PAGINATION ===== */
        .pagination {
            margin-bottom: 0;
            gap: 5px;
        }
        
        .page-link {
            color: var(--primary);
            border: 2px solid var(--gray-200);
            margin: 0 3px;
            border-radius: 12px;
            padding: 8px 16px;
            transition: all 0.2s ease;
            font-weight: 500;
            background: white;
            font-size: 0.9rem;
            text-decoration: none;
        }
        
        .page-link:hover {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border-color: var(--primary);
            transform: translateY(-2px);
        }
        
        .page-item.active .page-link {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border-color: var(--primary);
            box-shadow: var(--shadow-blue);
            font-weight: 600;
        }
        
        /* ===== MODAL STYLES ===== */
        .modal-content {
            border-radius: 25px;
            border: none;
            box-shadow: 0 25px 50px rgba(37, 99, 235, 0.2);
            overflow: hidden;
        }
        
        .modal-header {
            background: linear-gradient(145deg, var(--primary), var(--primary-dark));
            color: white;
            border-radius: 25px 25px 0 0;
            padding: 22px 28px;
            border-bottom: none;
        }
        
        .modal-header .modal-title {
            font-weight: 600;
            color: white;
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .modal-header .btn-close {
            background-color: white;
            opacity: 0.9;
            border-radius: 50%;
            padding: 8px;
            transition: all 0.2s ease;
        }
        
        .modal-header .btn-close:hover {
            opacity: 1;
            transform: rotate(90deg);
        }
        
        .modal-body {
            padding: 28px;
            background: #f9fdff;
        }
        
        .modal-footer {
            border-top: 2px solid var(--gray-200);
            padding: 22px 28px;
            background: white;
            border-radius: 0 0 25px 25px;
        }
        
        /* ===== ALERTS ===== */
        .alert {
            border-radius: 16px;
            padding: 16px 22px;
            font-weight: 500;
            margin-bottom: 20px;
            border-left-width: 6px;
            border-left-style: solid;
            border-top: 1px solid;
            border-right: 1px solid;
            border-bottom: 1px solid;
        }
        
        .alert-success {
            background: linear-gradient(145deg, #e8f8f5, #d1f2eb);
            color: #1e6f5c;
            border-left-color: #10b981;
            border-color: #a7f3d0;
        }
        
        .alert-danger {
            background: linear-gradient(145deg, #fdeded, #f9e2e2);
            color: #c0392b;
            border-left-color: #ef4444;
            border-color: #fecaca;
        }
        
        .alert-info {
            background: linear-gradient(145deg, #e6f3ff, #d9ecff);
            color: var(--primary-dark);
            border-left-color: var(--primary);
            border-color: #bfdbfe;
        }
        
        /* ===== ANIMATIONS ===== */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes scaleIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        
        .page-header, .card, .alert {
            animation: fadeIn 0.5s ease;
        }
        
        /* ===== CUSTOM SCROLLBAR ===== */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: var(--gray-100);
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, var(--gray-300), var(--gray-400));
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
        }
        
        /* ===== RESPONSIVE ===== */
        @media (max-width: 1400px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filter-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 1200px) {
            .filter-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 992px) {
            .nav-links { gap: 4px; }
            .nav-links a { padding: 6px 10px; font-size: 12.5px; }
            .main-content { padding: 20px; }
        }
        
        @media (max-width: 768px) {
            .main-content {
                padding: 20px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .filter-actions {
                flex-direction: column;
            }
            
            .filter-actions .btn {
                width: 100%;
                justify-content: center;
            }
            
            .table-actions .btn-group {
                flex-direction: column;
            }
            
            .table-actions .btn {
                width: 100%;
                margin: 2px 0;
            }
            
            .modal-body {
                padding: 20px;
            }
        }
        
        /* ===== LOADING ===== */
        .loading {
            position: relative;
            pointer-events: none;
            opacity: 0.7;
        }
        
        .loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 30px;
            height: 30px;
            border: 3px solid var(--gray-200);
            border-top-color: var(--primary);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
            transform: translate(-50%, -50%);
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* ===== PRINT STYLES ===== */
        @media print {
            .sidebar, .btn, .table-actions, .pagination, .modal, .badge, .menu-toggle {
                display: none !important;
            }
            
            .main-content {
                margin-left: 0;
                padding: 0;
            }
            
            .card {
                break-inside: avoid;
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
    </style>
</head>
<body>
    <!-- Menu Toggle -->
    <!-- Dashboard Layout -->
    <div class="dashboard-layout">
        <!-- Top Navbar -->
        <nav class="admin-navbar">
            <div class="logo">
                <div class="logo-icon"><i class="fas fa-shield-alt"></i></div>
                <h2>Admin Panel</h2>
            </div>
            <div class="nav-links">
                <a href="dashboard.php"> Dashboard</a>
                <a href="manage_children.php" class="active"> Children</a>
                <a href="manage_hospitals.php"> Hospitals</a>
                <a href="appointment_requests.php"> Requests</a>
                <a href="managevaccines.php"> Vaccines</a>
                <a href="bookingdetail.php"> Bookings</a>
                <a href="vaccination_reports.php"> Reports</a>
                <a href="system_settings.php"> Settings</a>
                <a href="../logout.php" class="logout">Logout</a>
            </div>
        </nav>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="container">
                <!-- Page Header -->
                <div class="page-header">
                    <div class="page-header-text">
                        <h1>
                            <span class="header-icon"><i class="fas fa-child"></i> Manage</span>
                            Children
                        </h1>
                        <p>View and manage all children registered in the vaccination system. Monitor records, track health status, and ensure complete vaccination coverage.</p>
                    </div>
                    <div class="page-header-img">
                        <img src="../uploads/download__10_.png" alt="Children" 
                             onerror="this.src='https://images.unsplash.com/photo-1576765608535-5f04d1e3f289?w=600&h=300&fit=crop'" />
                    </div>
                </div>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Statistics Cards -->
                <div class="stats-grid">
                    <?php
                    $active_count = $pdo->query("SELECT COUNT(*) FROM children WHERE is_active = 1")->fetchColumn();
                    $male_count = $pdo->query("SELECT COUNT(*) FROM children WHERE gender = 'Male'")->fetchColumn();
                    $female_count = $pdo->query("SELECT COUNT(*) FROM children WHERE gender = 'Female'")->fetchColumn();
                    ?>

                    <!-- Card 1: Total -->
                    <div class="stat-card">
                        <div class="stat-top-bg">
                            <div class="stat-icon-row">
                                <div class="stat-icon"><i class="fas fa-child"></i></div>
                                <span class="stat-badge">ALL</span>
                            </div>
                            <div class="stat-number"><?php echo $total_children; ?></div>
                            <div class="stat-label-top">Total Children</div>
                        </div>
                        <div class="stat-bottom">
                            <span class="stat-trend trend-up"><i class="fas fa-arrow-up"></i> All Registered</span>
                            <div class="stat-progress"><div class="stat-progress-fill"></div></div>
                        </div>
                    </div>

                    <!-- Card 2: Active -->
                    <div class="stat-card">
                        <div class="stat-top-bg">
                            <div class="stat-icon-row">
                                <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                                <span class="stat-badge">ACTIVE</span>
                            </div>
                            <div class="stat-number"><?php echo $active_count; ?></div>
                            <div class="stat-label-top">Active Children</div>
                        </div>
                        <div class="stat-bottom">
                            <span class="stat-trend trend-up"><i class="fas fa-check"></i> Currently Active</span>
                            <div class="stat-progress"><div class="stat-progress-fill"></div></div>
                        </div>
                    </div>

                    <!-- Card 3: Male -->
                    <div class="stat-card">
                        <div class="stat-top-bg">
                            <div class="stat-icon-row">
                                <div class="stat-icon"><i class="fas fa-mars"></i></div>
                                <span class="stat-badge">BOYS</span>
                            </div>
                            <div class="stat-number"><?php echo $male_count; ?></div>
                            <div class="stat-label-top">Male Children</div>
                        </div>
                        <div class="stat-bottom">
                            <span class="stat-trend" style="color:#0891b2;"><i class="fas fa-male"></i> Male Count</span>
                            <div class="stat-progress"><div class="stat-progress-fill"></div></div>
                        </div>
                    </div>

                    <!-- Card 4: Female -->
                    <div class="stat-card">
                        <div class="stat-top-bg">
                            <div class="stat-icon-row">
                                <div class="stat-icon"><i class="fas fa-venus"></i></div>
                                <span class="stat-badge">GIRLS</span>
                            </div>
                            <div class="stat-number"><?php echo $female_count; ?></div>
                            <div class="stat-label-top">Female Children</div>
                        </div>
                        <div class="stat-bottom">
                            <span class="stat-trend" style="color:#ec4899;"><i class="fas fa-female"></i> Female Count</span>
                            <div class="stat-progress"><div class="stat-progress-fill"></div></div>
                        </div>
                    </div>

                </div>

                <!-- Filters Section -->
                <div class="filters-section">
                    <form method="GET" class="filter-grid">
                        <div class="filter-group">
                            <i class="fas fa-search"></i>
                            <input type="text" 
                                   name="search" 
                                   class="filter-input" 
                                   placeholder="Search child or parent name..."
                                   value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        
                        <div class="filter-group">
                            <i class="fas fa-user"></i>
                            <select name="parent_id" class="filter-select">
                                <option value="0">👥 All Parents</option>
                                <?php foreach ($parents as $parent): ?>
                                    <option value="<?php echo $parent['parent_id']; ?>" 
                                        <?php echo $parent_id == $parent['parent_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($parent['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <i class="fas fa-filter"></i>
                            <select name="status" class="filter-select">
                                <option value="all" <?php echo $status == 'all' ? 'selected' : ''; ?>>📊 All Status</option>
                                <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>✅ Active</option>
                                <option value="inactive" <?php echo $status == 'inactive' ? 'selected' : ''; ?>>❌ Inactive</option>
                            </select>
                        </div>
                        
                        <div class="filter-actions" style="grid-column: span 3;">
                            <button type="submit" class="btn btn-search">
                                <i class="fas fa-search"></i> Search
                            </button>
                            <a href="manage_children.php" class="btn btn-reset">
                                <i class="fas fa-redo"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Children Table Card -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <i class="fas fa-list me-2"></i>Children List
                        </h6>
                        <span class="badge badge-primary">Total: <?php echo $total_children; ?></span>
                    </div>
                    
                    <div class="card-body p-0">
                        <?php if (empty($children)): ?>
                            <div class="empty-state">
                                <i class="fas fa-child"></i>
                                <h3>No Children Found</h3>
                                <p>No children match your current filters.</p>
                                <?php if ($search || $parent_id > 0 || $status !== 'all'): ?>
                                    <a href="manage_children.php" class="btn btn-primary">
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
                                                <td class="fw-bold" style="color: var(--primary);">#<?php echo $child['child_id']; ?></td>
                                                <td>
                                                    <div class="user-info">
                                                        <?php if (!empty($child['photo_url'])): ?>
                                                            <img src="../<?php echo htmlspecialchars($child['photo_url']); ?>" 
                                                                 class="profile-img me-3"
                                                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
                                                        <?php endif; ?>
                                                        <div class="profile-img bg-light d-flex align-items-center justify-content-center me-3"
                                                             style="display: <?php echo empty($child['photo_url']) ? 'flex' : 'none'; ?>">
                                                            <i class="fas fa-child"></i>
                                                        </div>
                                                        <div class="user-details">
                                                            <h6><?php echo htmlspecialchars($child['full_name']); ?></h6>
                                                            <?php if ($child['blood_group']): ?>
                                                                <small><i class="fas fa-tint" style="color: #ef4444;"></i> Blood: <?php echo $child['blood_group']; ?></small>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong style="color: var(--gray-700);"><?php echo htmlspecialchars($child['parent_name']); ?></strong>
                                                        <div class="text-muted small">
                                                            <i class="fas fa-envelope me-1" style="color: var(--primary);"></i>
                                                            <?php echo htmlspecialchars($child['parent_email']); ?>
                                                            <?php if ($child['parent_phone']): ?>
                                                                <br><i class="fas fa-phone me-1" style="color: #10b981;"></i>
                                                                <?php echo htmlspecialchars($child['parent_phone']); ?>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span style="color: var(--gray-700);"><?php echo $dob->format('d M, Y'); ?></span>
                                                    <div class="text-muted small">
                                                        <i class="fas fa-birthday-cake me-1" style="color: #f59e0b;"></i>
                                                        <?php echo $age->y . ' years, ' . $age->m . ' months'; ?>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge badge-info">
                                                        <i class="fas <?php echo $child['gender'] == 'Male' ? 'fa-male' : 'fa-female'; ?> me-1"></i>
                                                        <?php echo htmlspecialchars($child['gender']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo $child['is_active'] ? 'badge-success' : 'badge-danger'; ?>">
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
<?php
// No need to close PDO connection explicitly, it closes automatically
?>