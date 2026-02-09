<?php
session_start();
if(!isset($_SESSION["logged_in"]) || $_SESSION["user_type"] != "parent"){
    header("location:../login.php");
    exit();
}

include("../dbconnection.php");

// Get parent info
$user_id = $_SESSION["user_id"];
$query_parent = "SELECT * FROM parents WHERE user_id = '$user_id'";
$result_parent = mysqli_query($connection, $query_parent);
$parent_data = mysqli_fetch_assoc($result_parent);
$parent_id = $parent_data['parent_id'] ?? "";

// Get children count
$query_children = "SELECT COUNT(*) as total FROM children WHERE parent_id = '$parent_id' AND is_active = 1";
$result_children = mysqli_query($connection, $query_children);
$children_count = mysqli_fetch_assoc($result_children)['total'];

// Get upcoming vaccinations
$query_upcoming = "SELECT vb.*, c.full_name as child_name, h.hospital_name, v.vaccine_name, p.emergency_contact
                   FROM vaccination_bookings vb
                   JOIN children c ON vb.child_id = c.child_id
                   JOIN hospitals h ON vb.hospital_id = h.hospital_id
                   JOIN vaccines v ON vb.vaccine_id = v.vaccine_id
                   JOIN parents p ON c.parent_id = p.parent_id
                   WHERE c.parent_id = '$parent_id' 
                   AND vb.booking_status = 'scheduled'
                   AND vb.appointment_date >= CURDATE()
                   ORDER BY vb.appointment_date ASC
                   LIMIT 5";
$result_upcoming = mysqli_query($connection, $query_upcoming);

// Get pending requests
$query_pending = "SELECT COUNT(*) as total FROM appointment_requests ar
                  JOIN children c ON ar.child_id = c.child_id
                  WHERE c.parent_id = '$parent_id' AND ar.request_status = 'pending'";
$result_pending = mysqli_query($connection, $query_pending);
$pending_count = mysqli_fetch_assoc($result_pending)['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parent Dashboard</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; background: #f5f5f5; }
        .navbar {
            background: #667eea;
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
        .stat-card h3 { color: #667eea; font-size: 36px; margin-bottom: 10px; }
        .stat-card p { color: #666; }
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        .action-btn {
            background: #667eea;
            color: white;
            padding: 15px;
            text-align: center;
            border-radius: 10px;
            text-decoration: none;
            transition: transform 0.2s;
        }
        .action-btn:hover { transform: translateY(-2px); background: #5568d3; }
        .upcoming-section {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .upcoming-section h3 { color: #333; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; color: #333; font-weight: bold; }
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
        <h2>👨‍👩‍👧 Parent Dashboard</h2>
        <div>
            <a href="dashboard.php">Dashboard</a>
            <a href="my_children.php">My Children</a>
            <a href="book_appointment.php">Book Appointment</a>
            <a href="vaccinationhistory.php">Vaccination History</a>
            <a href="my_profile.php">Profile</a>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="welcome">
            <h2>Welcome, <?php echo $parent_data['full_name']; ?>! 👋</h2>
            <p>Manage your children's vaccination schedules and appointments</p>
        </div>

        <div class="stats">
            <div class="stat-card">
                <h3><?php echo $children_count; ?></h3>
                <p>Total Children</p>
            </div>
            <div class="stat-card">
                <h3><?php echo mysqli_num_rows($result_upcoming); ?></h3>
                <p>Upcoming Vaccinations</p>
            </div>
            <div class="stat-card">
                <h3><?php echo $pending_count; ?></h3>
                <p>Pending Requests</p>
            </div>
        </div>

        <div class="quick-actions">
            <a href="add_child.php" class="action-btn">➕ Add Child</a>
            <a href="book_appointment.php" class="action-btn">📅 Book Appointment</a>
            <a href="my_requests.php" class="action-btn">📋 My Requests</a>
            <a href="vaccination_history.php" class="action-btn">📊 Vaccination History</a>
        </div>

        <div class="upcoming-section">
            <h3>📅 Upcoming Vaccinations</h3>
            <?php if(mysqli_num_rows($result_upcoming) > 0): ?>
            <table>
                <tr>
                    <th>Child Name</th>
                    <th>Vaccine</th>
                    <th>Hospital</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Status</th>
                </tr>
                <?php while($row = mysqli_fetch_assoc($result_upcoming)): ?>
                <tr>
                    <td><?php echo $row['child_name']; ?></td>
                    <td><?php echo $row['vaccine_name']; ?></td>
                    <td><?php echo $row['hospital_name']; ?></td>
                    <td><?php echo date('d M Y', strtotime($row['appointment_date'])); ?></td>
                    <td><?php echo date('h:i A', strtotime($row['appointment_time'])); ?></td>
                    <td><span class="badge badge-scheduled">Scheduled</span></td>
                </tr>
                <?php endwhile; ?>
            </table>
            <?php else: ?>
            <p>No upcoming vaccinations scheduled.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
