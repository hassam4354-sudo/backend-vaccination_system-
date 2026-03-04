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
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', Arial, sans-serif;
            background: #f0f4ff;
            color: #1a1a2e;
        }

        /* ── NAVBAR ── */
        .navbar {
            background: #ffffff;
            border-bottom: 2px solid #e8eeff;
            padding: 0 35px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 68px;
            box-shadow: 0 2px 16px rgba(59,130,246,0.08);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .navbar-brand .brand-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        .navbar-brand h2 {
            font-size: 20px;
            font-weight: 700;
            color: #1d4ed8;
            letter-spacing: -0.3px;
        }
        .navbar-links { display: flex; align-items: center; gap: 6px; }
        .navbar-links a {
            color: #4b6cb7;
            text-decoration: none;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 13.5px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .navbar-links a:hover {
            background: #eff6ff;
            color: #1d4ed8;
        }
        .navbar-links a.logout {
            background: #fee2e2;
            color: #dc2626;
        }
        .navbar-links a.logout:hover {
            background: #fecaca;
        }

        /* ── LAYOUT ── */
        .container { max-width: 1200px; margin: 32px auto; padding: 0 24px; }

        /* ── WELCOME BANNER ── */
        .welcome-banner {
            background: linear-gradient(135deg, #1d4ed8 0%, #3b82f6 60%, #60a5fa 100%);
            border-radius: 18px;
            padding: 32px 36px;
            margin-bottom: 28px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 8px 32px rgba(59,130,246,0.3);
            overflow: hidden;
            position: relative;
        }
        .welcome-banner::before {
            content: '';
            position: absolute;
            top: -40px; right: -40px;
            width: 200px; height: 200px;
            background: rgba(255,255,255,0.08);
            border-radius: 50%;
        }
        .welcome-banner::after {
            content: '';
            position: absolute;
            bottom: -60px; right: 80px;
            width: 160px; height: 160px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
        }
        .welcome-text h2 {
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 6px;
        }
        .welcome-text p {
            font-size: 14px;
            opacity: 0.85;
        }
        .welcome-date {
            text-align: right;
            font-size: 13px;
            opacity: 0.8;
            position: relative;
            z-index: 1;
        }
        .welcome-date strong {
            display: block;
            font-size: 22px;
            font-weight: 700;
            opacity: 1;
        }

        /* ── STATS GRID ── */
        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 28px;
        }
        .stat-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 26px 28px;
            box-shadow: 0 2px 12px rgba(59,130,246,0.07);
            border: 1px solid #e8eeff;
            display: flex;
            align-items: center;
            gap: 18px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 24px rgba(59,130,246,0.13);
        }
        .stat-icon {
            width: 58px;
            height: 58px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            flex-shrink: 0;
        }
        .stat-icon.blue  { background: #eff6ff; }
        .stat-icon.green { background: #f0fdf4; }
        .stat-icon.amber { background: #fffbeb; }
        .stat-info h3 {
            font-size: 32px;
            font-weight: 700;
            color: #1d4ed8;
            line-height: 1;
            margin-bottom: 5px;
        }
        .stat-info p {
            font-size: 13px;
            color: #6b7280;
            font-weight: 500;
        }

        /* ── QUICK ACTIONS ── */
        .section-label {
            font-size: 13px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 14px;
        }
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }
        .action-btn {
            background: #ffffff;
            color: #1d4ed8;
            padding: 20px 16px;
            text-align: center;
            border-radius: 14px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            border: 2px solid #e8eeff;
            transition: all 0.22s;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            box-shadow: 0 2px 8px rgba(59,130,246,0.05);
        }
        .action-btn .action-icon {
            width: 46px;
            height: 46px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            transition: transform 0.2s;
        }
        .action-btn:hover {
            background: #1d4ed8;
            color: white;
            border-color: #1d4ed8;
            transform: translateY(-3px);
            box-shadow: 0 10px 28px rgba(29,78,216,0.25);
        }
        .action-btn:hover .action-icon {
            background: rgba(255,255,255,0.2);
            transform: scale(1.1);
        }

        /* ── UPCOMING TABLE ── */
        .upcoming-section {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(59,130,246,0.07);
            border: 1px solid #e8eeff;
            overflow: hidden;
        }
        .upcoming-header {
            padding: 22px 28px;
            border-bottom: 1px solid #f1f5ff;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .upcoming-header h3 {
            font-size: 17px;
            font-weight: 700;
            color: #1a1a2e;
        }
        .upcoming-header a {
            font-size: 13px;
            color: #3b82f6;
            text-decoration: none;
            font-weight: 500;
        }
        .upcoming-header a:hover { text-decoration: underline; }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        thead th {
            background: #f8faff;
            padding: 13px 20px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e8eeff;
        }
        tbody td {
            padding: 15px 20px;
            font-size: 14px;
            color: #374151;
            border-bottom: 1px solid #f4f6ff;
        }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover td { background: #f8faff; }

        .child-cell {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .child-avatar {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            flex-shrink: 0;
        }
        .child-cell strong {
            font-weight: 600;
            color: #1a1a2e;
        }

        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        .badge-scheduled {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #9ca3af;
        }
        .empty-state span { font-size: 48px; display: block; margin-bottom: 12px; }
        .empty-state p { font-size: 15px; }

        /* ── RESPONSIVE ── */
        @media(max-width: 900px) {
            .stats          { grid-template-columns: 1fr 1fr; }
            .quick-actions  { grid-template-columns: 1fr 1fr; }
        }
        @media(max-width: 600px) {
            .navbar         { padding: 0 16px; }
            .navbar-brand h2{ display: none; }
            .stats          { grid-template-columns: 1fr; }
            .quick-actions  { grid-template-columns: 1fr 1fr; }
            .welcome-date   { display: none; }
            .container      { padding: 0 14px; }
        }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <div class="navbar">
        <div class="navbar-brand">
       
            <h2>Parent_Panel</h2>
        </div>
        <div class="navbar-links">
            <a href="dashboard.php"> Dashboard</a>
            <a href="my_children.php"> My Children</a>
            <a href="book_appointment.php"> Book</a>
            <a href="vaccinationhistory.php"> History</a>
            <a href="myprofile.php"> Profile</a>
            <a href="../logout.php" class="logout"> Logout</a>
        </div>
    </div>

    <div class="container">

        <!-- WELCOME BANNER -->
        <div class="welcome-banner">
            <div class="welcome-text">
                <h2>Welcome back, <?php echo htmlspecialchars($parent_data['full_name']); ?>! </h2>
                <p>Manage your children's vaccination schedules and appointments with ease.</p>
            </div>
            <div class="welcome-date">
                <strong><?php echo date('d'); ?></strong>
                <?php echo date('M Y'); ?><br>
                <?php echo date('l'); ?>
            </div>
        </div>

        <!-- STATS -->
        <div class="stats">
            <div class="stat-card">
            
                <div class="stat-info">
                    <h3><?php echo $children_count; ?></h3>
                    <p>Total Children</p>
                </div>
            </div>
            <div class="stat-card">
             
                <div class="stat-info">
                    <h3><?php echo mysqli_num_rows($result_upcoming); ?></h3>
                    <p>Upcoming Vaccinations</p>
                </div>
            </div>
            <div class="stat-card">
              
                <div class="stat-info">
                    <h3><?php echo $pending_count; ?></h3>
                    <p>Pending Requests</p>
                </div>
            </div>
        </div>

        <!-- QUICK ACTIONS -->
        <div class="section-label">Quick Actions</div>
        <div class="quick-actions">
            <a href="add_child.php" class="action-btn">
                <div class="action-icon">➕</div>
                Add Child
            </a>
            <a href="book_appointment.php" class="action-btn">
                <div class="action-icon">📅</div>
                Book Appointment
            </a>
            <a href="my_requests.php" class="action-btn">
                <div class="action-icon">📋</div>
                My Requests
            </a>
            <a href="vaccination_history.php" class="action-btn">
                <div class="action-icon"></div>
                Vaccination History
            </a>
        </div>

        <!-- UPCOMING VACCINATIONS TABLE -->
        <div class="upcoming-section">
            <div class="upcoming-header">
                <h3>📅 Upcoming Vaccinations</h3>
                <a href="vaccination_history.php">View all →</a>
            </div>

            <?php if(mysqli_num_rows($result_upcoming) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Child Name</th>
                        <th>Vaccine</th>
                        <th>Hospital</th>
                        <th>Date</th>
                        <th>Time</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php while($row = mysqli_fetch_assoc($result_upcoming)): ?>
                <tr>
                    <td>
                        <div class="child-cell">
                            <div class="child-avatar"></div>
                            <strong><?php echo htmlspecialchars($row['child_name']); ?></strong>
                        </div>
                    </td>
                    <td><?php echo htmlspecialchars($row['vaccine_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['hospital_name']); ?></td>
                    <td><?php echo date('d M Y', strtotime($row['appointment_date'])); ?></td>
                    <td><?php echo date('h:i A', strtotime($row['appointment_time'])); ?></td>
                    <td><span class="badge badge-scheduled">Scheduled</span></td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <span>🗓️</span>
                <p>No upcoming vaccinations scheduled.</p>
            </div>
            <?php endif; ?>
        </div>

    </div>
</body>
</html>