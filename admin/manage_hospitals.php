<?php
session_start();
if(!isset($_SESSION["logged_in"]) || $_SESSION["user_type"] != "admin"){
    header("location:../login.php");
    exit();
}

include("../dbconnection.php");

// Get all hospitals
$query_hospitals = "SELECT h.*, u.email, u.phone, u.is_active as user_active
                    FROM hospitals h
                    JOIN users u ON h.user_id = u.user_id
                    ORDER BY h.created_at DESC";
$result_hospitals = mysqli_query($connection, $query_hospitals);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Hospitals</title>
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
        .btn-verify { background: #28a745; color: white; }
        .btn-deactivate { background: #dc3545; color: white; margin-left: 5px; }
        .btn-activate { background: #17a2b8; color: white; margin-left: 5px; }
        .btn:hover { opacity: 0.8; }
        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-verified { background: #d4edda; color: #155724; }
        .badge-pending { background: #fff3cd; color: #856404; }
        .badge-active { background: #d1ecf1; color: #0c5460; }
        .badge-inactive { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>🔐 Admin Dashboard</h2>
        <div>
            <a href="dashboard.php">Dashboard</a>
            <a href="appointment_requests.php">Requests</a>
            <a href="manage_hospitals.php">Hospitals</a>
            <a href="manage_vaccines.php">Vaccines</a>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="section">
            <h3>🏥 Manage Hospitals</h3>
            <?php if(mysqli_num_rows($result_hospitals) > 0): ?>
            <table>
                <tr>
                    <th>ID</th>
                    <th>Hospital Name</th>
                    <th>Registration No</th>
                    <th>Location</th>
                    <th>Contact Person</th>
                    <th>Phone</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
                <?php while($row = mysqli_fetch_assoc($result_hospitals)): ?>
                <tr>
                    <td><?php echo $row['hospital_id']; ?></td>
                    <td><?php echo $row['hospital_name']; ?></td>
                    <td><?php echo $row['registration_number']; ?></td>
                    <td><?php echo $row['city']; ?>, <?php echo $row['state']; ?></td>
                    <td><?php echo $row['contact_person']; ?></td>
                    <td><?php echo $row['phone'] ?? 'N/A'; ?></td>
                    <td><?php echo $row['email']; ?></td>
                    <td>
                        <?php if($row['is_verified']): ?>
                            <span class="badge badge-verified">✓ Verified</span>
                        <?php else: ?>
                            <span class="badge badge-pending">⏳ Pending</span>
                        <?php endif; ?>
                        <br>
                        <?php if($row['is_active']): ?>
                            <span class="badge badge-active">Active</span>
                        <?php else: ?>
                            <span class="badge badge-inactive">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if(!$row['is_verified']): ?>
                            <a href="verify_hospital.php?id=<?php echo $row['hospital_id']; ?>" 
                               class="btn btn-verify" 
                               onclick="return confirm('Verify this hospital?')">✓ Verify</a>
                        <?php endif; ?>
                        
                        <?php if($row['is_active']): ?>
                            <a href="toggle_hospital_status.php?id=<?php echo $row['hospital_id']; ?>&action=deactivate" 
                               class="btn btn-deactivate" 
                               onclick="return confirm('Deactivate this hospital?')">Deactivate</a>
                        <?php else: ?>
                            <a href="toggle_hospital_status.php?id=<?php echo $row['hospital_id']; ?>&action=activate" 
                               class="btn btn-activate" 
                               onclick="return confirm('Activate this hospital?')">Activate</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </table>
            <?php else: ?>
            <p>No hospitals registered yet.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
