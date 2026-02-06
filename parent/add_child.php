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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Child</title>
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
        .container {
            max-width: 800px;
            margin: 30px auto;
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h2 { color: #333; margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; color: #555; font-weight: bold; }
        input, select, textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        textarea { min-height: 80px; resize: vertical; }
        button {
            width: 100%;
            padding: 15px;
            background: #667eea;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
        }
        button:hover { background: #5568d3; }
        .back-btn {
            display: inline-block;
            margin-bottom: 20px;
            color: #667eea;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="navbar">
        <h2>👨‍👩‍👧 Parent Dashboard</h2>
        <div>
            <a href="dashboard.php">Dashboard</a>
            <a href="my_children.php">My Children</a>
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <a href="my_children.php" class="back-btn">← Back to My Children</a>
        <h2>➕ Add New Child</h2>
        
        <form action="create_child.php" method="post" enctype="multipart/form-data">
            <div class="form-group">
                <label>Child's Full Name:</label>
                <input type="text" name="full_name" required>
            </div>

            <div class="form-group">
                <label>Date of Birth:</label>
                <input type="date" name="date_of_birth" max="<?php echo date('Y-m-d'); ?>" required>
            </div>

            <div class="form-group">
                <label>Gender:</label>
                <select name="gender" required>
                    <option value="">Select Gender</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                    <option value="Other">Other</option>
                </select>
            </div>

            <div class="form-group">
                <label>Blood Group:</label>
                <select name="blood_group">
                    <option value="">Select Blood Group</option>
                    <option value="A+">A+</option>
                    <option value="A-">A-</option>
                    <option value="B+">B+</option>
                    <option value="B-">B-</option>
                    <option value="AB+">AB+</option>
                    <option value="AB-">AB-</option>
                    <option value="O+">O+</option>
                    <option value="O-">O-</option>
                </select>
            </div>

            <div class="form-group">
                <label>Birth Weight (kg):</label>
                <input type="number" name="birth_weight" step="0.01" placeholder="e.g., 3.2">
            </div>

            <div class="form-group">
                <label>Birth Height (cm):</label>
                <input type="number" name="birth_height" step="0.01" placeholder="e.g., 50">
            </div>

            <div class="form-group">
                <label>Medical Conditions (if any):</label>
                <textarea name="medical_conditions" placeholder="Enter any medical conditions or leave blank"></textarea>
            </div>

            <div class="form-group">
                <label>Allergies (if any):</label>
                <textarea name="allergies" placeholder="Enter any allergies or leave blank"></textarea>
            </div>

            <div class="form-group">
                <label>Child's Photo (optional):</label>
                <input type="file" name="photo" accept="image/*">
            </div>

            <button type="submit" name="add_child">Add Child</button>
        </form>
    </div>
</body>
</html>
