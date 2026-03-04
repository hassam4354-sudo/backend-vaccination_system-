<?php
session_start();
if(!isset($_SESSION["logged_in"]) || $_SESSION["user_type"] != "parent"){
    header("location:../login.php");
    exit();
}

include("../dbconnection.php");

$user_id = $_SESSION["user_id"];
$query_parent = "SELECT * FROM parents WHERE user_id = '$user_id'";
$result_parent = mysqli_query($connection, $query_parent);
$parent_data = mysqli_fetch_assoc($result_parent);
$parent_id = $parent_data['parent_id'] ?? "";

// Get all children
$query_children = "SELECT * FROM children WHERE parent_id = '$parent_id' ORDER BY date_of_birth DESC";
$result_children = mysqli_query($connection, $query_children);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Children</title>
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
        .navbar-links a.active {
            background: #eff6ff;
            color: #1d4ed8;
            font-weight: 600;
        }
        .navbar-links a.logout {
            background: #fee2e2;
            color: #dc2626;
        }
        .navbar-links a.logout:hover { background: #fecaca; }

        /* ── LAYOUT ── */
        .container { max-width: 1200px; margin: 32px auto; padding: 0 24px; }

        /* ── PAGE HEADER ── */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
        }
        .page-title {
            font-size: 24px;
            font-weight: 700;
            color: #1a1a2e;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .page-title span {
            font-size: 13px;
            font-weight: 500;
            color: #6b7280;
            background: #f0f4ff;
            border: 1px solid #e8eeff;
            padding: 4px 12px;
            border-radius: 20px;
        }
        .add-btn {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            padding: 11px 22px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            box-shadow: 0 4px 14px rgba(29,78,216,0.25);
            transition: all 0.2s;
        }
        .add-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(29,78,216,0.35);
        }

        /* ── CHILDREN GRID ── */
        .children-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 22px;
        }

        /* ── CHILD CARD ── */
        .child-card {
            background: white;
            border-radius: 16px;
            padding: 28px 24px;
            box-shadow: 0 2px 12px rgba(59,130,246,0.07);
            border: 1px solid #e8eeff;
            transition: all 0.25s;
            position: relative;
            overflow: hidden;
        }
        .child-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 4px;
            background: linear-gradient(90deg, #3b82f6, #1d4ed8);
            border-radius: 16px 16px 0 0;
        }
        .child-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 32px rgba(59,130,246,0.15);
            border-color: #bfdbfe;
        }

        /* Photo */
        .photo-wrap {
            display: flex;
            justify-content: center;
            margin-bottom: 16px;
        }
        .child-photo {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #3b82f6;
            box-shadow: 0 4px 14px rgba(59,130,246,0.25);
        }
        .child-photo-placeholder {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 38px;
            border: 3px solid #bfdbfe;
            box-shadow: 0 4px 14px rgba(59,130,246,0.12);
        }

        /* Name */
        .child-name {
            font-size: 18px;
            font-weight: 700;
            color: #1a1a2e;
            text-align: center;
            margin-bottom: 14px;
        }

        /* Info pills */
        .info-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            justify-content: center;
            margin-bottom: 6px;
        }
        .info-pill {
            background: #f0f4ff;
            border: 1px solid #e8eeff;
            border-radius: 20px;
            padding: 5px 12px;
            font-size: 12.5px;
            color: #374151;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .info-pill .pill-label {
            color: #9ca3af;
            font-weight: 500;
        }
        .info-pill .pill-value {
            color: #1d4ed8;
            font-weight: 600;
        }

        /* Divider */
        .card-divider {
            border: none;
            border-top: 1px solid #f1f5ff;
            margin: 16px 0;
        }

        /* Action buttons */
        .actions {
            display: flex;
            gap: 10px;
        }
        .btn {
            flex: 1;
            padding: 10px;
            text-align: center;
            border-radius: 9px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.2s;
        }
        .btn-view {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            box-shadow: 0 3px 10px rgba(29,78,216,0.2);
        }
        .btn-view:hover {
            box-shadow: 0 6px 16px rgba(29,78,216,0.3);
            transform: translateY(-1px);
        }
        .btn-edit {
            background: #fffbeb;
            color: #92400e;
            border: 1px solid #fde68a;
        }
        .btn-edit:hover {
            background: #fef3c7;
            transform: translateY(-1px);
        }

        /* ── NO CHILDREN STATE ── */
        .no-children {
            text-align: center;
            padding: 70px 30px;
            background: white;
            border-radius: 16px;
            border: 2px dashed #bfdbfe;
            box-shadow: 0 2px 12px rgba(59,130,246,0.05);
        }
        .no-children .empty-icon {
            font-size: 60px;
            margin-bottom: 18px;
            display: block;
        }
        .no-children h3 {
            font-size: 20px;
            font-weight: 700;
            color: #1a1a2e;
            margin-bottom: 8px;
        }
        .no-children p {
            color: #6b7280;
            font-size: 14px;
            margin-bottom: 24px;
        }
        .no-children .add-btn {
            display: inline-flex;
        }

        /* ── RESPONSIVE ── */
        @media(max-width: 700px) {
            .navbar { padding: 0 16px; }
            .navbar-brand h2 { display: none; }
            .container { padding: 0 14px; }
            .children-grid { grid-template-columns: 1fr; }
            .page-title span { display: none; }
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
            <a href="my_children.php" class="active"> My Children</a>
            <a href="book_appointment.php"> Book</a>
            <a href="vaccinationhistory.php"> History</a>
            <a href="myprofile.php"> Profile</a>
            <a href="../logout.php" class="logout"> Logout</a>
        </div>
    </div>

    <div class="container">

        <!-- PAGE HEADER -->
        <div class="page-header">
            <div class="page-title">
                 My Children
                <?php
                    $total = mysqli_num_rows($result_children);
                    echo "<span>$total registered</span>";
                ?>
            </div>
            <a href="add_child.php" class="add-btn">➕ Add New Child</a>
        </div>

        <!-- CHILDREN GRID -->
        <?php if($total > 0): ?>
        <div class="children-grid">
            <?php
            // Reset pointer
            mysqli_data_seek($result_children, 0);
            while($child = mysqli_fetch_assoc($result_children)):
                $age_days   = floor((time() - strtotime($child['date_of_birth'])) / 86400);
                $age_years  = floor($age_days / 365);
                $age_months = floor(($age_days % 365) / 30);

                if($age_years > 0) {
                    $age = $age_years . "y";
                    if($age_months > 0) $age .= " " . $age_months . "m";
                } else {
                    $age = $age_months . " month" . ($age_months != 1 ? "s" : "");
                }
            ?>
            <div class="child-card">

                <!-- Photo -->
                <div class="photo-wrap">
                    <?php if(!empty($child['photo_url'])): ?>
                        <img src="../<?php echo htmlspecialchars($child['photo_url']); ?>" alt="Child Photo" class="child-photo">
                    <?php else: ?>
                        <div class="child-photo-placeholder">👶</div>
                    <?php endif; ?>
                </div>

                <!-- Name -->
                <div class="child-name"><?php echo htmlspecialchars($child['full_name']); ?></div>

                <!-- Info Pills -->
                <div class="info-pills">
                    <div class="info-pill">
                        <span class="pill-label">Age</span>
                        <span class="pill-value"><?php echo $age; ?></span>
                    </div>
                    <div class="info-pill">
                        <span class="pill-label">Gender</span>
                        <span class="pill-value"><?php echo htmlspecialchars($child['gender']); ?></span>
                    </div>
                    <div class="info-pill">
                        <span class="pill-label">DOB</span>
                        <span class="pill-value"><?php echo date('d M Y', strtotime($child['date_of_birth'])); ?></span>
                    </div>
                    <?php if(!empty($child['blood_group'])): ?>
                    <div class="info-pill">
                        <span class="pill-label">Blood</span>
                        <span class="pill-value"><?php echo htmlspecialchars($child['blood_group']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>

                <hr class="card-divider">

                <!-- Actions -->
                <div class="actions">
                    <a href="child_details.php?id=<?php echo $child['child_id']; ?>" class="btn btn-view">👁️ View Details</a>
                    <a href="edit_child.php?id=<?php echo $child['child_id']; ?>" class="btn btn-edit">✏️ Edit</a>
                </div>

            </div>
            <?php endwhile; ?>
        </div>

        <?php else: ?>
        <!-- EMPTY STATE -->
        <div class="no-children">
            <span class="empty-icon">👶</span>
            <h3>No children added yet</h3>
            <p>Register your child to start managing their vaccination schedule.</p>
            <a href="add_child.php" class="add-btn">➕ Add Your First Child</a>
        </div>
        <?php endif; ?>

    </div>
</body>
</html>