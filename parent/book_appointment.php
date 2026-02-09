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

// Get children
$query_children = "SELECT * FROM children WHERE parent_id = '$parent_id' AND is_active = 1";
$result_children = mysqli_query($connection, $query_children);

// Get vaccines
$query_vaccines = "SELECT * FROM vaccines WHERE is_active = 1 ORDER BY vaccine_name";
$result_vaccines = mysqli_query($connection, $query_vaccines);

// Get hospitals
$query_hospitals = "SELECT * FROM hospitals WHERE is_active = 1 AND is_verified = 1 ORDER BY hospital_name";
$result_hospitals = mysqli_query($connection, $query_hospitals);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Appointment</title>
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
        select, input, textarea {
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
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #2196F3;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
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
            <a href="../logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <a href="dashboard.php" class="back-btn">← Back to Dashboard</a>
        <h2>📅 Book Vaccination Appointment</h2>
        
        <div class="info-box">
            ℹ️ Your appointment request will be sent to admin for approval. You will be notified once approved.
        </div>

        <form action="submit_appointment_request.php" method="post">
            <div class="form-group">
                <label>Select Child:</label>
                <select name="child_id" required>
                    <option value="">Choose Child</option>
                    <?php 
                    mysqli_data_seek($result_children, 0);
                    while($child = mysqli_fetch_assoc($result_children)): 
                    ?>
                    <option value="<?php echo $child['child_id']; ?>">
                        <?php echo $child['full_name']; ?> 
                        (<?php echo date('d M Y', strtotime($child['date_of_birth'])); ?>)
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Select Hospital:</label>
                <select name="hospital_id" required>
                    <option value="">Choose Hospital</option>
                    <?php while($hospital = mysqli_fetch_assoc($result_hospitals)): ?>
                    <option value="<?php echo $hospital['hospital_id']; ?>">
                        <?php echo $hospital['hospital_name']; ?> - <?php echo $hospital['city']; ?>
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Select Vaccine:</label>
                <select name="vaccine_id" required>
                    <option value="">Choose Vaccine</option>
                    <?php while($vaccine = mysqli_fetch_assoc($result_vaccines)): ?>
                    <option value="<?php echo $vaccine['vaccine_id']; ?>">
                        <?php echo $vaccine['vaccine_name']; ?> 
                        (<?php echo $vaccine['description']; ?>)
                    </option>
                    <?php endwhile; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Dose Number:</label>
                <select name="dose_number" required>
                    <option value="">Select Dose</option>
                    <option value="1">1st Dose</option>
                    <option value="2">2nd Dose</option>
                    <option value="3">3rd Dose</option>
                    <option value="4">4th Dose</option>
                    <option value="5">5th Dose</option>
                </select>
            </div>

            <div class="form-group">
                <label>Preferred Date:</label>
                <input type="date" name="preferred_date" min="<?php echo date('Y-m-d'); ?>" required>
            </div>

            <div class="form-group">
                <label>Preferred Time:</label>
                <input type="time" name="preferred_time" required>
            </div>

            <div class="form-group">
                <label>Additional Notes (Optional):</label>
                <textarea name="parent_notes" placeholder="Any special requirements or notes..."></textarea>
            </div>

            <button type="submit" name="submit_request">Submit Request</button>
        </form>
    </div>
</body>
</html>
