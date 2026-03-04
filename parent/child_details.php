<?php
session_start();
if(!isset($_SESSION["logged_in"]) || $_SESSION["user_type"] != "parent"){
    header("location:../login.php");
    exit();
}

include("../dbconnection.php");

$user_id = $_SESSION["user_id"];
$child_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if(!$child_id){
    header("location:my_children.php");
    exit();
}

// Get parent_id
$query_parent = "SELECT * FROM parents WHERE user_id = '$user_id'";
$result_parent = mysqli_query($connection, $query_parent);
$parent_data = mysqli_fetch_assoc($result_parent);
$parent_id = $parent_data['parent_id'];

// Get child details (make sure child belongs to this parent)
$query_child = "SELECT * FROM children WHERE child_id = '$child_id' AND parent_id = '$parent_id'";
$result_child = mysqli_query($connection, $query_child);

if(mysqli_num_rows($result_child) == 0){
    header("location:my_children.php");
    exit();
}

$child = mysqli_fetch_assoc($result_child);

// Calculate age
$age_days = floor((time() - strtotime($child['date_of_birth'])) / (60 * 60 * 24));
$age_years = floor($age_days / 365);
$age_months = floor(($age_days % 365) / 30);
$age_days_rem = $age_days % 30;

if($age_years > 0) {
    $age = $age_years . " year" . ($age_years > 1 ? "s" : "");
    if($age_months > 0) $age .= ", " . $age_months . " month" . ($age_months > 1 ? "s" : "");
} elseif($age_months > 0) {
    $age = $age_months . " month" . ($age_months > 1 ? "s" : "");
    if($age_days_rem > 0) $age .= ", " . $age_days_rem . " day" . ($age_days_rem > 1 ? "s" : "");
} else {
    $age = $age_days . " day" . ($age_days > 1 ? "s" : "");
}

// Get vaccination history for this child
$query_vaccinations = "SELECT vb.*, v.vaccine_name, v.vaccine_type, h.hospital_name, u.full_name as doctor_name
                       FROM vaccination_bookings vb
                       JOIN vaccines v ON vb.vaccine_id = v.vaccine_id
                       JOIN hospitals h ON vb.hospital_id = h.hospital_id
                       LEFT JOIN users u ON vb.administered_by = u.user_id
                       WHERE vb.child_id = '$child_id'
                       ORDER BY vb.appointment_date DESC";
$result_vaccinations = mysqli_query($connection, $query_vaccinations);

// Get pending/upcoming appointment requests
$query_requests = "SELECT ar.*, v.vaccine_name, h.hospital_name
                   FROM appointment_requests ar
                   JOIN vaccines v ON ar.vaccine_id = v.vaccine_id
                   JOIN hospitals h ON ar.hospital_id = h.hospital_id
                   WHERE ar.child_id = '$child_id'
                   ORDER BY ar.created_at DESC";
$result_requests = mysqli_query($connection, $query_requests);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Child Details - <?php echo htmlspecialchars($child['full_name']); ?></title>
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
        .navbar h2 {
            font-size: 20px;
            font-weight: 700;
            color: #1d4ed8;
            letter-spacing: -0.3px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .navbar div { display: flex; align-items: center; gap: 6px; }
        .navbar a {
            color: #4b6cb7;
            text-decoration: none;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 13.5px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .navbar a:hover {
            background: #eff6ff;
            color: #1d4ed8;
        }
        .navbar a.logout {
            background: #fee2e2;
            color: #dc2626;
        }
        .navbar a.logout:hover { background: #fecaca; }

        /* ── LAYOUT ── */
        .container { max-width: 1100px; margin: 32px auto; padding: 0 24px; }

        /* ── BACK LINK ── */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 20px;
            color: #3b82f6;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: color 0.2s;
        }
        .back-link:hover { color: #1d4ed8; text-decoration: underline; }

        /* ── PROFILE CARD ── */
        .profile-card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(59,130,246,0.07);
            border: 1px solid #e8eeff;
            padding: 35px;
            display: flex;
            gap: 35px;
            align-items: flex-start;
            margin-bottom: 28px;
        }
        .profile-photo {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #3b82f6;
            flex-shrink: 0;
        }
        .profile-photo-placeholder {
            width: 130px;
            height: 130px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 55px;
            flex-shrink: 0;
            border: 4px solid #3b82f6;
        }
        .profile-info h2 {
            font-size: 26px;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 6px;
        }
        .profile-info .age-badge {
            display: inline-block;
            background: #dbeafe;
            color: #1d4ed8;
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 18px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 14px;
            margin-top: 5px;
        }
        .info-item label {
            display: block;
            font-size: 12px;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
            margin-bottom: 4px;
        }
        .info-item span {
            font-size: 15px;
            color: #1a1a2e;
            font-weight: 600;
        }

        /* ── ACTION BAR ── */
        .action-bar {
            display: flex;
            gap: 12px;
            margin-bottom: 28px;
            flex-wrap: wrap;
        }
        .action-bar a {
            padding: 11px 22px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.22s;
            box-shadow: 0 2px 8px rgba(59,130,246,0.08);
        }
        .btn-book {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            border: 2px solid transparent;
        }
        .btn-book:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(29,78,216,0.25);
        }
        .btn-edit {
            background: #ffffff;
            color: #1d4ed8;
            border: 2px solid #e8eeff;
        }
        .btn-edit:hover {
            background: #eff6ff;
            border-color: #3b82f6;
            transform: translateY(-2px);
        }

        /* ── SECTIONS ── */
        .section {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(59,130,246,0.07);
            border: 1px solid #e8eeff;
            padding: 28px;
            margin-bottom: 24px;
        }
        .section h3 {
            font-size: 17px;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 18px;
            padding-bottom: 12px;
            border-bottom: 1px solid #f1f5ff;
        }

        /* ── INFO BOXES ── */
        .info-box {
            background: #f8faff;
            border-left: 4px solid #3b82f6;
            border-radius: 6px;
            padding: 14px 18px;
            color: #374151;
            font-size: 14px;
            line-height: 1.6;
        }
        .info-box.warning { border-left-color: #f59e0b; background: #fffbeb; }

        /* ── TABLE ── */
        table { width: 100%; border-collapse: collapse; }
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

        /* ── BADGES ── */
        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-completed  { background: #dcfce7; color: #166534; }
        .badge-scheduled  { background: #dbeafe; color: #1d4ed8; }
        .badge-pending    { background: #fef9c3; color: #854d0e; }
        .badge-approved   { background: #dcfce7; color: #166534; }
        .badge-rejected   { background: #fee2e2; color: #dc2626; }
        .badge-cancelled  { background: #f3f4f6; color: #6b7280; }

        /* ── EMPTY STATE ── */
        .empty-msg {
            text-align: center;
            padding: 40px 20px;
            color: #9ca3af;
            font-size: 15px;
        }
        .empty-msg span { font-size: 42px; display: block; margin-bottom: 12px; }

        /* ── RESPONSIVE ── */
        @media(max-width: 650px){
            .navbar { padding: 0 16px; }
            .profile-card { flex-direction: column; align-items: center; text-align: center; }
            .info-grid { grid-template-columns: 1fr 1fr; }
            .container { padding: 0 14px; }
        }
    </style>
</head>
<body>

<div class="navbar">
    <h2>👨‍👩‍👧 Parent Dashboard</h2>
    <div>
        <a href="dashboard.php">Dashboard</a>
        <a href="my_children.php">My Children</a>
        <a href="book_appointment.php">Book Appointment</a>
        <a href="myprofile.php">Profile</a>
        <a href="../logout.php" class="logout">Logout</a>
    </div>
</div>

<div class="container">
    <a href="my_children.php" class="back-link">← Back to My Children</a>

    <!-- Profile Card -->
    <div class="profile-card">
        <?php if(!empty($child['photo_url'])): ?>
            <img src="../<?php echo htmlspecialchars($child['photo_url']); ?>" alt="Child Photo" class="profile-photo">
        <?php else: ?>
            <div class="profile-photo-placeholder">👶</div>
        <?php endif; ?>

        <div class="profile-info">
            <h2><?php echo htmlspecialchars($child['full_name']); ?></h2>
            <span class="age-badge">Age: <?php echo $age; ?></span>

            <div class="info-grid">
                <div class="info-item">
                    <label>Date of Birth</label>
                    <span><?php echo date('d M Y', strtotime($child['date_of_birth'])); ?></span>
                </div>
                <div class="info-item">
                    <label>Gender</label>
                    <span><?php echo htmlspecialchars($child['gender']); ?></span>
                </div>
                <?php if(!empty($child['blood_group'])): ?>
                <div class="info-item">
                    <label>Blood Group</label>
                    <span><?php echo htmlspecialchars($child['blood_group']); ?></span>
                </div>
                <?php endif; ?>
                <?php if(!empty($child['birth_weight'])): ?>
                <div class="info-item">
                    <label>Birth Weight</label>
                    <span><?php echo htmlspecialchars($child['birth_weight']); ?> kg</span>
                </div>
                <?php endif; ?>
                <?php if(!empty($child['birth_height'])): ?>
                <div class="info-item">
                    <label>Birth Height</label>
                    <span><?php echo htmlspecialchars($child['birth_height']); ?> cm</span>
                </div>
                <?php endif; ?>
                <div class="info-item">
                    <label>Status</label>
                    <span><?php echo $child['is_active'] ? '✅ Active' : '❌ Inactive'; ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Action Buttons -->
    <div class="action-bar">
        <a href="book_appointment.php?child_id=<?php echo $child_id; ?>" class="btn-book">📅 Book Appointment</a>
        <a href="edit_child.php?id=<?php echo $child_id; ?>" class="btn-edit">✏️ Edit Info</a>
    </div>


    <!-- Medical Info -->
    <?php if(!empty($child['medical_conditions']) || !empty($child['allergies'])): ?>
    <div class="section">
        <h3>🏥 Medical Information</h3>
        <?php if(!empty($child['medical_conditions'])): ?>
            <p style="margin-bottom:10px;font-weight:bold;color:#555;">Medical Conditions:</p>
            <div class="info-box warning" style="margin-bottom:15px;"><?php echo nl2br(htmlspecialchars($child['medical_conditions'])); ?></div>
        <?php endif; ?>
        <?php if(!empty($child['allergies'])): ?>
            <p style="margin-bottom:10px;font-weight:bold;color:#555;">Allergies:</p>
            <div class="info-box warning"><?php echo nl2br(htmlspecialchars($child['allergies'])); ?></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Vaccination History -->
    <div class="section">
        <h3>💉 Vaccination History</h3>
        <?php if($result_vaccinations && mysqli_num_rows($result_vaccinations) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Vaccine</th>
                    <th>Type</th>
                    <th>Dose</th>
                    <th>Date</th>
                    <th>Hospital</th>
                    <th>Administered By</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php 
            $i = 1;
            while($vac = mysqli_fetch_assoc($result_vaccinations)): ?>
                <tr>
                    <td><?php echo $i++; ?></td>
                    <td><strong><?php echo htmlspecialchars($vac['vaccine_name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($vac['vaccine_type'] ?? '—'); ?></td>
                    <td>Dose <?php echo htmlspecialchars($vac['dose_number'] ?? '1'); ?></td>
                    <td><?php echo date('d M Y', strtotime($vac['appointment_date'])); ?></td>
                    <td><?php echo htmlspecialchars($vac['hospital_name']); ?></td>
                    <td><?php echo !empty($vac['doctor_name']) ? htmlspecialchars($vac['doctor_name']) : '—'; ?></td>
                    <td>
                        <?php
                        $status = $vac['booking_status'] ?? 'scheduled';
                        $badge_class = 'badge-scheduled';
                        if($status == 'completed') $badge_class = 'badge-completed';
                        elseif($status == 'cancelled') $badge_class = 'badge-cancelled';
                        ?>
                        <span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst($status); ?></span>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-msg">
            <span>💉</span>
            No vaccination records found for this child yet.
        </div>
        <?php endif; ?>
    </div>

    <!-- Appointment Requests -->
    <div class="section">
        <h3>📋 Appointment Requests</h3>
        <?php if($result_requests && mysqli_num_rows($result_requests) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Vaccine</th>
                    <th>Hospital</th>
                    <th>Preferred Date</th>
                    <th>Preferred Time</th>
                    <th>Dose</th>
                    <th>Status</th>
                    <th>Submitted On</th>
                </tr>
            </thead>
            <tbody>
            <?php 
            $j = 1;
            while($req = mysqli_fetch_assoc($result_requests)): ?>
                <tr>
                    <td><?php echo $j++; ?></td>
                    <td><strong><?php echo htmlspecialchars($req['vaccine_name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($req['hospital_name']); ?></td>
                    <td><?php echo !empty($req['preferred_date']) ? date('d M Y', strtotime($req['preferred_date'])) : '—'; ?></td>
                    <td><?php echo !empty($req['preferred_time']) ? date('h:i A', strtotime($req['preferred_time'])) : '—'; ?></td>
                    <td>Dose <?php echo htmlspecialchars($req['dose_number'] ?? '1'); ?></td>
                    <td>
                        <?php
                        $rs = $req['request_status'];
                        $rc = 'badge-pending';
                        if($rs == 'approved') $rc = 'badge-approved';
                        elseif($rs == 'rejected') $rc = 'badge-rejected';
                        elseif($rs == 'cancelled') $rc = 'badge-cancelled';
                        ?>
                        <span class="badge <?php echo $rc; ?>"><?php echo ucfirst($rs); ?></span>
                    </td>
                    <td><?php echo !empty($req['created_at']) ? date('d M Y', strtotime($req['created_at'])) : '—'; ?></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        <?php else: ?>
        <div class="empty-msg">
            <span>📋</span>
            No appointment requests submitted yet.
        </div>
        <?php endif; ?>
    </div>

</div>
</body>
</html>