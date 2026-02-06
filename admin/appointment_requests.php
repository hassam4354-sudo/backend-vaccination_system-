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
    <title>Appointment Requests</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; }
        .navbar {
            background: #dc3545;
            color: white;
            padding: 15px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .navbar h2 { font-size: 24px; }
        .navbar a {
            color: white;
            text-decoration: none;
            margin-left: 20px;
            padding: 8px 15px;
            background: rgba(255,255,255,0.2);
            border-radius: 5px;
        }
        .container { max-width: 1400px; margin: 30px auto; padding: 0 20px; }
        .section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .section h3 { color: #333; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; font-size: 14px; }
        th { background: #f8f9fa; color: #333; font-weight: bold; }
        .btn {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            text-decoration: none;
            display: inline-block;
        }
        .btn-approve { background: #28a745; color: white; }
        .btn-reject { background: #dc3545; color: white; margin-left: 5px; }
        .btn:hover { opacity: 0.8; }
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-pending { background: #fff3cd; color: #856404; }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>🔐 Admin Dashboard</h2>
        <div>
            <a href="dashboard.php">Dashboard</a>
            <a href="appointment_requests.php">Requests</a>
            <a href="manage_hospitals.php">Hospitals</a>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="section">
            <h3>📋 Pending Appointment Requests</h3>
            <?php if(mysqli_num_rows($result_requests) > 0): ?>
            <table>
                <tr>
                    <th>ID</th>
                    <th>Child Name</th>
                    <th>Age</th>
                    <th>Parent</th>
                    <th>Contact</th>
                    <th>Vaccine</th>
                    <th>Dose</th>
                    <th>Hospital</th>
                    <th>Preferred Date</th>
                    <th>Time</th>
                    <th>Notes</th>
                    <th>Action</th>
                </tr>
                <?php while($row = mysqli_fetch_assoc($result_requests)): 
                    $age_days = floor((time() - strtotime($row['date_of_birth'])) / (60 * 60 * 24));
                    $age_months = floor($age_days / 30);
                ?>
                <tr>
                    <td><?php echo $row['request_id']; ?></td>
                    <td><?php echo $row['child_name']; ?></td>
                    <td><?php echo $age_months; ?> months</td>
                    <td><?php echo $row['parent_name']; ?></td>
                    <td><?php echo $row['parent_phone']; ?></td>
                    <td><?php echo $row['vaccine_name']; ?></td>
                    <td>Dose <?php echo $row['dose_number']; ?></td>
                    <td><?php echo $row['hospital_name']; ?>, <?php echo $row['city']; ?></td>
                    <td><?php echo date('d M Y', strtotime($row['preferred_date'])); ?></td>
                    <td><?php echo date('h:i A', strtotime($row['preferred_time'])); ?></td>
                    <td><?php echo $row['parent_notes'] ? $row['parent_notes'] : '-'; ?></td>
                    <td>
                        <a href="approve_request.php?id=<?php echo $row['request_id']; ?>" 
                           class="btn btn-approve" 
                           onclick="return confirm('Approve this request?')">✓ Approve</a>
                        <a href="reject_request.php?id=<?php echo $row['request_id']; ?>" 
                           class="btn btn-reject" 
                           onclick="return confirm('Reject this request?')">✗ Reject</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </table>
            <?php else: ?>
            <p>No pending appointment requests.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
