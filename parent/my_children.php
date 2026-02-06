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
$parent_id = $parent_data['parent_id'];

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
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .add-btn {
            background: #28a745;
            color: white;
            padding: 12px 25px;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
        }
        .add-btn:hover { background: #218838; }
        .children-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 25px;
        }
        .child-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .child-card:hover { transform: translateY(-5px); }
        .child-photo {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            margin: 0 auto 15px;
            display: block;
            border: 3px solid #667eea;
        }
        .child-photo-placeholder {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: #e9ecef;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
        }
        .child-name { font-size: 20px; font-weight: bold; color: #333; text-align: center; margin-bottom: 10px; }
        .child-info { color: #666; font-size: 14px; margin: 8px 0; }
        .child-info strong { color: #333; }
        .actions {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }
        .btn {
            flex: 1;
            padding: 10px;
            text-align: center;
            border-radius: 5px;
            text-decoration: none;
            font-size: 14px;
        }
        .btn-view { background: #667eea; color: white; }
        .btn-edit { background: #ffc107; color: #333; }
        .btn:hover { opacity: 0.8; }
        .no-children {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 10px;
        }
        .no-children h3 { color: #666; margin-bottom: 20px; }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>👨‍👩‍👧 Parent Dashboard</h2>
        <div>
            <a href="dashboard.php">Dashboard</a>
            <a href="my_children.php">My Children</a>
            <a href="book_appointment.php">Book Appointment</a>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="header">
            <h2>👶 My Children</h2>
            <a href="add_child.php" class="add-btn">➕ Add New Child</a>
        </div>

        <?php if(mysqli_num_rows($result_children) > 0): ?>
        <div class="children-grid">
            <?php while($child = mysqli_fetch_assoc($result_children)): 
                $age_days = floor((time() - strtotime($child['date_of_birth'])) / (60 * 60 * 24));
                $age_years = floor($age_days / 365);
                $age_months = floor(($age_days % 365) / 30);
                
                if($age_years > 0) {
                    $age = $age_years . " year" . ($age_years > 1 ? "s" : "");
                    if($age_months > 0) $age .= " " . $age_months . " month" . ($age_months > 1 ? "s" : "");
                } else {
                    $age = $age_months . " month" . ($age_months > 1 ? "s" : "");
                }
            ?>
            <div class="child-card">
                <?php if($child['photo_url']): ?>
                    <img src="../<?php echo $child['photo_url']; ?>" alt="Child Photo" class="child-photo">
                <?php else: ?>
                    <div class="child-photo-placeholder">👶</div>
                <?php endif; ?>
                
                <div class="child-name"><?php echo $child['full_name']; ?></div>
                
                <div class="child-info">
                    <strong>Age:</strong> <?php echo $age; ?>
                </div>
                <div class="child-info">
                    <strong>Gender:</strong> <?php echo $child['gender']; ?>
                </div>
                <div class="child-info">
                    <strong>DOB:</strong> <?php echo date('d M Y', strtotime($child['date_of_birth'])); ?>
                </div>
                <?php if($child['blood_group']): ?>
                <div class="child-info">
                    <strong>Blood Group:</strong> <?php echo $child['blood_group']; ?>
                </div>
                <?php endif; ?>
                
                <div class="actions">
                    <a href="child_details.php?id=<?php echo $child['child_id']; ?>" class="btn btn-view">View Details</a>
                    <a href="edit_child.php?id=<?php echo $child['child_id']; ?>" class="btn btn-edit">Edit</a>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
        <?php else: ?>
        <div class="no-children">
            <h3>No children added yet</h3>
            <p>Click the "Add New Child" button to register your child</p>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
