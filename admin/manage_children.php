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

// Build query with conditions
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

// Count total - FIXED VERSION
$count_sql = "SELECT COUNT(*) as total FROM children c LEFT JOIN parents p ON c.parent_id = p.parent_id";
if (!empty($where_clause)) {
    $count_sql .= " " . $where_clause;
}

$count_stmt = $pdo->prepare($count_sql);
$count_stmt->execute($params);
$total_children = $count_stmt->fetch()['total'];
$total_pages = ceil($total_children / $limit);

// Get children - FIXED LIMIT/OFFSET
$sql = "SELECT c.*, p.full_name as parent_name, u.email as parent_email, u.phone as parent_phone
        FROM children c
        LEFT JOIN parents p ON c.parent_id = p.parent_id
        LEFT JOIN users u ON p.user_id = u.user_id";

if (!empty($where_clause)) {
    $sql .= " " . $where_clause;
}

$sql .= " ORDER BY c.created_at DESC LIMIT :limit OFFSET :offset";

$stmt = $pdo->prepare($sql);

// Bind parameters
foreach ($params as $key => $value) {
    $stmt->bindValue(($key + 1), $value);
}

// Bind LIMIT and OFFSET as integers - THIS FIXES THE ERROR
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

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
        body { background: #f8f9fa; }
        .sidebar { background: #343a40; min-height: 100vh; }
        .sidebar a { color: #fff; text-decoration: none; padding: 10px 15px; display: block; }
        .sidebar a:hover { background: #495057; }
        .sidebar a.active { background: #007bff; }
        .page-header { background: #fff; padding: 20px; border-radius: 5px; margin-bottom: 20px; }
        .card { border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .table th { background: #f8f9fa; }
        .badge { font-size: 0.8em; }
        .profile-img { width: 40px; height: 40px; object-fit: cover; border-radius: 50%; }
        .table-actions .btn { padding: 0.25rem 0.5rem; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 sidebar p-0">
                <h4 class="text-white p-3 border-bottom">Admin Panel</h4>
                <nav class="nav flex-column">
                    <a href="dashboard.php"><i class="fas fa-tachometer-alt me-2"></i>Dashboard</a>
                    <a href="manage_children.php" class="active"><i class="fas fa-child me-2"></i>Manage Children</a>
                    <a href="manage_parents.php"><i class="fas fa-users me-2"></i>Manage Parents</a>
                    <a href="manage_hospitals.php"><i class="fas fa-hospital me-2"></i>Manage Hospitals</a>
                    <a href="appointment_requests.php"><i class="fas fa-calendar-check me-2"></i>Appointments</a>
                    <a href="../logout.php" class="mt-auto"><i class="fas fa-sign-out-alt me-2"></i>Logout</a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10">
                <div class="p-4">
                    <!-- Header -->
                    <div class="page-header">
                        <h1 class="h3"><i class="fas fa-child text-primary me-2"></i>Manage Children</h1>
                        <p class="text-muted">View and manage all children registered in the system</p>
                    </div>

                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>

                    <!-- Filters -->
                    <div class="card mb-4">
                        <div class="card-header bg-light">
                            <h6 class="mb-0"><i class="fas fa-filter me-2"></i>Filters</h6>
                        </div>
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-4">
                                    <input type="text" class="form-control" name="search" 
                                           placeholder="Search child or parent name..." 
                                           value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                                <div class="col-md-3">
                                    <select class="form-select" name="parent_id">
                                        <option value="0">All Parents</option>
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
                                        <option value="all" <?php echo $status == 'all' ? 'selected' : ''; ?>>All Status</option>
                                        <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>Active</option>
                                        <option value="inactive" <?php echo $status == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
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
                        <div class="card-header bg-light d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="fas fa-list me-2"></i>Children List</h6>
                            <span class="badge bg-primary">Total: <?php echo $total_children; ?></span>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($children)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-child fa-3x text-muted mb-3"></i>
                                    <p class="text-muted">No children found</p>
                                    <?php if ($search || $parent_id > 0 || $status !== 'all'): ?>
                                        <a href="manage_children.php" class="btn btn-primary">
                                            Clear Filters
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
                                                    <td>#<?php echo $child['child_id']; ?></td>
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <?php if (!empty($child['photo_url'])): ?>
                                                                <img src="../<?php echo htmlspecialchars($child['photo_url']); ?>" 
                                                                     class="profile-img me-2"
                                                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex'">
                                                            <?php endif; ?>
                                                            <div class="profile-img bg-light d-flex align-items-center justify-content-center me-2"
                                                                 style="display: <?php echo empty($child['photo_url']) ? 'flex' : 'none'; ?>">
                                                                <i class="fas fa-child text-muted"></i>
                                                            </div>
                                                            <div>
                                                                <strong><?php echo htmlspecialchars($child['full_name']); ?></strong>
                                                                <?php if ($child['blood_group']): ?>
                                                                    <div class="text-muted small">Blood: <?php echo $child['blood_group']; ?></div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <div>
                                                            <strong><?php echo htmlspecialchars($child['parent_name']); ?></strong>
                                                            <div class="text-muted small">
                                                                <?php echo htmlspecialchars($child['parent_email']); ?>
                                                                <?php if ($child['parent_phone']): ?>
                                                                    <br><?php echo htmlspecialchars($child['parent_phone']); ?>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <?php echo $dob->format('d M, Y'); ?>
                                                        <div class="text-muted small">
                                                            <?php echo $age->y . ' years'; ?>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-info">
                                                            <?php echo htmlspecialchars($child['gender']); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge <?php echo $child['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
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
                    <h5 class="modal-title">Child Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="childDetails">
                    <!-- Content will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- View Vaccinations Modal -->
    <div class="modal fade" id="viewVaccinationsModal">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Vaccination History</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="vaccinationDetails">
                    <!-- Content will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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
        if (confirm(newStatus ? 'Activate this child?' : 'Deactivate this child?')) {
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
        if (confirm('Are you sure you want to delete this child? This action cannot be undone.')) {
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