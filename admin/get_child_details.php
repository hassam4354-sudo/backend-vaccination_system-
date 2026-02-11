<?php
session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['user_type'] ?? '') !== 'admin') {
    exit('Access denied');
}

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

$child_id = intval($_GET['id'] ?? 0);

$sql = "SELECT c.*, p.full_name as parent_name, u.email as parent_email, u.phone as parent_phone,
               p.address as parent_address, p.city as parent_city, p.state as parent_state,
               p.emergency_contact
        FROM children c
        LEFT JOIN parents p ON c.parent_id = p.parent_id
        LEFT JOIN users u ON p.user_id = u.user_id
        WHERE c.child_id = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$child_id]);
$child = $stmt->fetch();

if (!$child) {
    echo '<div class="alert alert-danger">Child not found</div>';
    exit();
}

$dob = new DateTime($child['date_of_birth']);
$today = new DateTime();
$age = $today->diff($dob);
?>

<div class="row">
    <div class="col-md-4">
        <?php if (!empty($child['photo_url'])): ?>
            <img src="../<?php echo htmlspecialchars($child['photo_url']); ?>" 
                 class="img-fluid rounded mb-3">
        <?php else: ?>
            <div class="bg-light rounded d-flex align-items-center justify-content-center mb-3" 
                 style="height: 200px;">
                <i class="fas fa-child fa-4x text-muted"></i>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-body">
                <h6>Parent Information</h6>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($child['parent_name']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($child['parent_email']); ?></p>
                <p><strong>Phone:</strong> <?php echo htmlspecialchars($child['parent_phone'] ?: 'N/A'); ?></p>
                <p><strong>Emergency:</strong> <?php echo htmlspecialchars($child['emergency_contact'] ?: 'N/A'); ?></p>
                <p><strong>Address:</strong> <?php echo htmlspecialchars($child['parent_address'] ?: 'N/A'); ?></p>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <h4><?php echo htmlspecialchars($child['full_name']); ?></h4>
        
        <div class="row mb-3">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h6>Basic Information</h6>
                        <p><strong>Date of Birth:</strong> <?php echo $dob->format('d M, Y'); ?></p>
                        <p><strong>Age:</strong> <?php echo $age->y . ' years, ' . $age->m . ' months'; ?></p>
                        <p><strong>Gender:</strong> <?php echo htmlspecialchars($child['gender']); ?></p>
                        <p><strong>Blood Group:</strong> <?php echo htmlspecialchars($child['blood_group'] ?: 'N/A'); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h6>Birth Details</h6>
                        <p><strong>Birth Weight:</strong> <?php echo $child['birth_weight'] ? $child['birth_weight'] . ' kg' : 'N/A'; ?></p>
                        <p><strong>Birth Height:</strong> <?php echo $child['birth_height'] ? $child['birth_height'] . ' cm' : 'N/A'; ?></p>
                        <p><strong>Status:</strong> 
                            <span class="badge <?php echo $child['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
                                <?php echo $child['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h6>Medical Conditions</h6>
                        <p><?php echo nl2br(htmlspecialchars($child['medical_conditions'] ?: 'No conditions')); ?></p>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h6>Allergies</h6>
                        <p><?php echo nl2br(htmlspecialchars($child['allergies'] ?: 'No allergies')); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>