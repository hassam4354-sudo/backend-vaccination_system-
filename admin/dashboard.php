<?php
session_start();
if(!isset($_SESSION["logged_in"]) || $_SESSION["user_type"] != "admin"){
    header("location:../login.php");
    exit();
}

include("../dbconnection.php");

$user_id = $_SESSION["user_id"];

// Get statistics
$query_children = "SELECT COUNT(*) as total FROM children WHERE is_active = 1";
$result_children = mysqli_query($connection, $query_children);
$children_count = mysqli_fetch_assoc($result_children)['total'];

$query_hospitals = "SELECT COUNT(*) as total FROM hospitals WHERE is_active = 1";
$result_hospitals = mysqli_query($connection, $query_hospitals);
$hospitals_count = mysqli_fetch_assoc($result_hospitals)['total'];

$query_pending = "SELECT COUNT(*) as total FROM appointment_requests WHERE request_status = 'pending'";
$result_pending = mysqli_query($connection, $query_pending);
$pending_count = mysqli_fetch_assoc($result_pending)['total'];

$query_today = "SELECT COUNT(*) as total FROM vaccination_bookings 
                WHERE appointment_date = CURDATE() AND booking_status = 'scheduled'";
$result_today = mysqli_query($connection, $query_today);
$today_count = mysqli_fetch_assoc($result_today)['total'];

// Get recent activities
$query_recent = "SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 10";
$result_recent = mysqli_query($connection, $query_recent);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
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
        .navbar a:hover { background: rgba(255,255,255,0.3); }
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        .welcome { background: white; padding: 20px; border-radius: 10px; margin-bottom: 30px; }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .stat-card h3 { color: #dc3545; font-size: 36px; margin-bottom: 10px; }
        .stat-card p { color: #666; }
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        .action-btn {
            background: #dc3545;
            color: white;
            padding: 15px;
            text-align: center;
            border-radius: 10px;
            text-decoration: none;
            transition: transform 0.2s;
        }
        .action-btn:hover { transform: translateY(-2px); background: #c82333; }
        .activities-section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .activities-section h3 { color: #333; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; color: #333; font-weight: bold; }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>🔐 Admin Dashboard</h2>
        <div>
            <a href="dashboard.php">Dashboard</a>
            <a href="manage_children.php">Children</a>
            <a href="manage_hospitals.php">Hospitals</a>
            <a href="appointment_requests.php">Requests</a>
            <a href="manage_vaccines.php">Vaccines</a>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="welcome">
            <h2>Welcome to Admin Panel! 👨‍💼</h2>
            <p>Manage the Child Vaccination System</p>
        </div>

        <div class="stats">
            <div class="stat-card">
                <h3><?php echo $children_count; ?></h3>
                <p>Registered Children</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $hospitals_count; ?></h3>
                <p>Active Hospitals</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $pending_count; ?></h3>
                <p>Pending Requests</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $today_count; ?></h3>
                <p>Today's Appointments</p>
            </div>
        </div>

        <div class="quick-actions">
            <a href="appointment_requests.php" class="action-btn">📋 View Requests</a>
            <a href="manage_hospitals.php" class="action-btn">🏥 Manage Hospitals</a>
            <a href="manage_vaccines.php" class="action-btn">💉 Manage Vaccines</a>
            <a href="booking_details.php" class="action-btn">📅 All Bookings</a>
            <a href="vaccination_reports.php" class="action-btn">📊 Reports</a>
        </div>

        <div class="activities-section">
            <h3>🔔 Recent Activities</h3>
            <?php if(mysqli_num_rows($result_recent) > 0): ?>
            <table>
                <tr>
                    <th>Time</th>
                    <th>Action</th>
                    <th>Description</th>
                    <th>IP Address</th>
                </tr>
                <?php while($row = mysqli_fetch_assoc($result_recent)): ?>
                <tr>
                    <td><?php echo date('d M Y H:i', strtotime($row['created_at'])); ?></td>
                    <td><?php echo $row['action_type']; ?></td>
                    <td><?php echo $row['action_description']; ?></td>
                    <td><?php echo $row['ip_address']; ?></td>
                </tr>
                <?php endwhile; ?>
            </table>
            <?php else: ?>
            <p>No recent activities.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
