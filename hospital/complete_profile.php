<?php
session_start();
$user_id = $_GET['user_id'] ?? null;

if(!$user_id) {
    header("location:../login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Hospital Profile</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
        }
        .container {
            max-width: 700px;
            margin: 30px auto;
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        h1 { text-align: center; color: #333; margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; color: #555; font-weight: bold; }
        input, select, textarea { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; }
        textarea { min-height: 80px; resize: vertical; }
        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        button { width: 100%; padding: 15px; background: #667eea; color: white; border: none; border-radius: 5px; font-size: 16px; cursor: pointer; }
        button:hover { background: #5568d3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🏥 Complete Hospital Profile</h1>
        <form action="save_hospital_profile.php" method="post">
            <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
            
            <div class="form-group">
                <label>Hospital Name:</label>
                <input type="text" name="hospital_name" required>
            </div>
            
            <div class="form-group">
                <label>Registration Number:</label>
                <input type="text" name="registration_number" required>
            </div>
            
            <div class="form-group">
                <label>Contact Person Name:</label>
                <input type="text" name="contact_person" required>
            </div>
            
            <div class="form-group">
                <label>Phone Number:</label>
                <input type="tel" name="phone_number" pattern="[0-9]{11}" placeholder="03001234567" required>
            </div>
            
            <div class="form-group">
                <label>Complete Address:</label>
                <textarea name="address" required></textarea>
            </div>
            
            <div class="row">
                <div class="form-group">
                    <label>City:</label>
                    <input type="text" name="city" required>
                </div>
                
                <div class="form-group">
                    <label>State/Province:</label>
                    <input type="text" name="state" required>
                </div>
            </div>
            
            <div class="row">
                <div class="form-group">
                    <label>Postal Code:</label>
                    <input type="text" name="postal_code" required>
                </div>
            </div>
            
            <div class="row">
                <div class="form-group">
                    <label>Latitude (optional):</label>
                    <input type="text" name="latitude" placeholder="24.8607">
                </div>
                
                <div class="form-group">
                    <label>Longitude (optional):</label>
                    <input type="text" name="longitude" placeholder="67.0011">
                </div>
            </div>
            
            <button type="submit" name="save_hospital">Complete Registration</button>
        </form>
    </div>
</body>
</html>
