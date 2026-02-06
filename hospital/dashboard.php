<?php
session_start();
if(!isset($_SESSION["logged_in"]) || $_SESSION["user_type"] != "hospital"){
    header("location:../login.php");
    exit();
}

include("../dbconnection.php");

$user_id = $_SESSION["user_id"];
$query_hospital = "SELECT * FROM hospitals WHERE user_id = '$user_id'";
$result_hospital = mysqli_query($connection, $query_hospital);
$hospital_data = mysqli_fetch_assoc($result_hospital);
$hospital_id = $hospital_data['hospital_id'];

// Check if hospital is verified
if($hospital_data['is_verified'] == 0) {
    echo "<!DOCTYPE html>
    <html>
    <head><title>Pending Verification</title>
    <style>
        body { font-family: Arial; display: flex; justify-content: center; align-items: center; height: 100vh; background: #f5f5f5; }
        .message { background: white; padding: 40px; border-radius: 10px; text-align: center; max-width: 500px; }
        h2 { color: #ffc107; margin-bottom: 20px; }
        a { display: inline-block; margin-top: 20px; padding: 12px 25px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; }
    </style>
    </head>
    <body>
    <div class='message'>
        <h2>⏳ Pending Verification</h2>
        <p>Your hospital account is pending admin verification. You will be able to access the system once your account is verified.</p>
        <a href='../logout.php'>Logout</a>
    </div>
    </body>
    </html>";
    exit();
}

// Get today's bookings
$query_today = "SELECT COUNT(*) as total FROM vaccination_bookings 
                WHERE hospital_id = '$hospital_id' 
                AND appointment_date = CURDATE() 
                AND booking_status = 'scheduled'";
$result_today = mysqli_query($connection, $query_today);
$today_count = mysqli_fetch_assoc($result_today)['total'];

// Get completed vaccinations this month
$query_month = "SELECT COUNT(*) as total FROM vaccination_records 
                WHERE hospital_id = '$hospital_id' 
                AND MONTH(vaccination_date) = MONTH(CURDATE())
                AND YEAR(vaccination_date) = YEAR(CURDATE())";
$result_month = mysqli_query($connection, $query_month);
$month_count = mysqli_fetch_assoc($result_month)['total'];

// Get vaccine inventory count
$query_inventory = "SELECT COUNT(*) as total FROM hospital_vaccine_inventory 
                    WHERE hospital_id = '$hospital_id' AND is_available = 1";
$result_inventory = mysqli_query($connection, $query_inventory);
$inventory_count = mysqli_fetch_assoc($result_inventory)['total'];

// Get upcoming bookings
$query_upcoming = "SELECT vb.*, c.full_name as child_name, v.vaccine_name, u.phone as parent_phone
                   FROM vaccination_bookings vb
                   JOIN children c ON vb.child_id = c.child_id
                   JOIN vaccines v ON vb.vaccine_id = v.vaccine_id
                   JOIN parents p ON c.parent_id = p.parent_id
                   JOIN users u ON p.user_id = u.user_id
                   WHERE vb.hospital_id = '$hospital_id' 
                   AND vb.booking_status = 'scheduled'
                   AND vb.appointment_date >= CURDATE()
                   ORDER BY vb.appointment_date ASC, vb.appointment_time ASC
                   LIMIT 10";
$result_upcoming = mysqli_query($connection, $query_upcoming);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hospital Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; }
        .navbar {
            background: #28a745;
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
        .stat-card h3 { color: #28a745; font-size: 36px; margin-bottom: 10px; }
        .stat-card p { color: #666; }
        .bookings-section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .bookings-section h3 { color: #333; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; color: #333; font-weight: bold; }
        .action-btn {
            padding: 6px 12px;
            background: #28a745;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-size: 13px;
        }
        .action-btn:hover { background: #218838; }
        .badge {
            padding: 5px 10px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: bold;
        }
        .badge-scheduled { background: #d4edda; color: #155724; }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>🏥 Hospital Dashboard</h2>
        <div>
            <a href="dashboard.php">Dashboard</a>
            <a href="bookings.php">Bookings</a>
            <a href="inventory.php">Vaccine Inventory</a>
            <a href="vaccination_records.php">Records</a>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="welcome">
            <h2>Welcome, <?php echo $hospital_data['hospital_name']; ?>! 🏥</h2>
            <p><?php echo $hospital_data['city']; ?>, <?php echo $hospital_data['state']; ?></p>
        </div>

        <div class="stats">
            <div class="stat-card">
                <h3><?php echo $today_count; ?></h3>
                <p>Today's Appointments</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $month_count; ?></h3>
                <p>Vaccinations This Month</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $inventory_count; ?></h3>
                <p>Active Vaccines in Stock</p>
            </div>
        </div>

        <div class="bookings-section">
            <h3>📅 Upcoming Appointments</h3>
            <?php if(mysqli_num_rows($result_upcoming) > 0): ?>
            <table>
                <tr>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Child Name</th>
                    <th>Vaccine</th>
                    <th>Dose</th>
                    <th>Contact</th>
                    <th>Action</th>
                </tr>
                <?php while($row = mysqli_fetch_assoc($result_upcoming)): ?>
                <tr>
                    <td><?php echo date('d M Y', strtotime($row['appointment_date'])); ?></td>
                    <td><?php echo date('h:i A', strtotime($row['appointment_time'])); ?></td>
                    <td><?php echo $row['child_name']; ?></td>
                    <td><?php echo $row['vaccine_name']; ?></td>
                    <td>Dose <?php echo $row['dose_number']; ?></td>
                    <td><?php echo $row['parent_phone'] ?? 'N/A'; ?></td>
                    <td>
                        <a href="complete_vaccination.php?id=<?php echo $row['booking_id']; ?>" class="action-btn">Mark Complete</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </table>
            <?php else: ?>
            <p>No upcoming appointments.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
