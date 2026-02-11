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

$child_id = intval($_GET['child_id'] ?? 0);

// Get child name
$child_stmt = $pdo->prepare("SELECT full_name FROM children WHERE child_id = ?");
$child_stmt->execute([$child_id]);
$child = $child_stmt->fetch();

// Get vaccination records
$sql = "SELECT vr.*, v.vaccine_name, h.hospital_name
        FROM vaccination_records vr
        LEFT JOIN vaccines v ON vr.vaccine_id = v.vaccine_id
        LEFT JOIN hospitals h ON vr.hospital_id = h.hospital_id
        WHERE vr.child_id = ?
        ORDER BY vr.vaccination_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$child_id]);
$records = $stmt->fetchAll();
?>

<h4>Vaccination History for <?php echo htmlspecialchars($child['full_name']); ?></h4>

<?php if (empty($records)): ?>
    <div class="alert alert-info">No vaccination records found.</div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>Vaccine</th>
                    <th>Dose</th>
                    <th>Date</th>
                    <th>Hospital</th>
                    <th>Batch No.</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($records as $record): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($record['vaccine_name']); ?></td>
                        <td>#<?php echo $record['dose_number']; ?></td>
                        <td><?php echo date('d M, Y', strtotime($record['vaccination_date'])); ?></td>
                        <td><?php echo htmlspecialchars($record['hospital_name']); ?></td>
                        <td><?php echo htmlspecialchars($record['batch_number']); ?></td>
                        <td>
                            <span class="badge bg-<?php 
                                echo $record['vaccination_status'] == 'completed' ? 'success' : 'warning'; 
                            ?>">
                                <?php echo ucfirst($record['vaccination_status']); ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>