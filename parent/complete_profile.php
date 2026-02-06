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
    <title>Complete Profile - Parent</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
        }
        .container {
            max-width: 600px;
            margin: 50px auto;
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
        }
        h1 { text-align: center; color: #333; margin-bottom: 30px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; color: #555; font-weight: bold; }
        input, select { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; }
        button { width: 100%; padding: 12px; background: #667eea; color: white; border: none; border-radius: 5px; font-size: 16px; cursor: pointer; }
        button:hover { background: #5568d3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>👤 Complete Your Profile</h1>
        <form action="save_profile.php" method="post">
            <input type="hidden" name="user_id" value="<?php echo $user_id; ?>">
            
            <div class="form-group">
                <label>Full Name:</label>
                <input type="text" name="full_name" required>
            </div>
            
            <div class="form-group">
                <label>Emergency Contact Number:</label>
                <input type="tel" name="emergency_contact" pattern="[0-9]{11}" placeholder="03001234567" required>
            </div>
            
            <div class="form-group">
                <label>Address:</label>
                <input type="text" name="address" required>
            </div>
            
            <div class="form-group">
                <label>City:</label>
                <input type="text" name="city" required>
            </div>
            
            <div class="form-group">
                <label>State/Province:</label>
                <input type="text" name="state" required>
            </div>
            
            <div class="form-group">
                <label>Postal Code:</label>
                <input type="text" name="postal_code" required>
            </div>
            
            <button type="submit" name="save_profile">Complete Registration</button>
        </form>
    </div>
</body>
</html>
