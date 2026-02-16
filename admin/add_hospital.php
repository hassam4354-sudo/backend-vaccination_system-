<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['user_type'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

include("../dbconnection.php");

$message = '';
$message_type = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_hospital'])) {
    
    // Get form data
    $hospital_name = sanitize_input($_POST['hospital_name']);
    $registration_number = sanitize_input($_POST['registration_number']);
    $address = sanitize_input($_POST['address']);
    $city = sanitize_input($_POST['city']);
    $state = sanitize_input($_POST['state']);
    $postal_code = sanitize_input($_POST['postal_code']);
    $contact_person = sanitize_input($_POST['contact_person']);
    $email = sanitize_input($_POST['email']);
    $phone = sanitize_input($_POST['phone']);
    $latitude = !empty($_POST['latitude']) ? sanitize_input($_POST['latitude']) : 'NULL';
    $longitude = !empty($_POST['longitude']) ? sanitize_input($_POST['longitude']) : 'NULL';
    
    // Validate required fields
    if (empty($hospital_name) || empty($address) || empty($city) || empty($state) || empty($email) || empty($phone)) {
        $message = "❌ Please fill all required fields!";
        $message_type = "error";
    } else {
        // Check if email already exists in users table
        $check_email = mysqli_query($connection, "SELECT user_id FROM users WHERE email = '$email'");
        
        if (mysqli_num_rows($check_email) > 0) {
            $message = "❌ Email already exists! Please use a different email.";
            $message_type = "error";
        } else {
            // Start transaction
            mysqli_begin_transaction($connection);
            
            try {
                // Generate password (default: welcome123)
                $default_password = password_hash('welcome123', PASSWORD_DEFAULT);
                
                // Insert into users table
                $insert_user = "INSERT INTO users (email, password_hash, user_type, phone, is_active, created_at) 
                               VALUES ('$email', '$default_password', 'hospital', '$phone', 1, NOW())";
                
                if (!mysqli_query($connection, $insert_user)) {
                    throw new Exception("Failed to create user account: " . mysqli_error($connection));
                }
                
                $user_id = mysqli_insert_id($connection);
                
                // Insert into hospitals table
                $insert_hospital = "INSERT INTO hospitals (user_id, hospital_name, registration_number, address, city, state, postal_code, latitude, longitude, contact_person, is_verified, is_active, created_at) 
                                   VALUES ($user_id, '$hospital_name', '$registration_number', '$address', '$city', '$state', '$postal_code', $latitude, $longitude, '$contact_person', 0, 1, NOW())";
                
                if (!mysqli_query($connection, $insert_hospital)) {
                    throw new Exception("Failed to add hospital: " . mysqli_error($connection));
                }
                
                $hospital_id = mysqli_insert_id($connection);
                
                // Log the action
                $admin_id = $_SESSION['user_id'];
                log_audit($admin_id, 'ADD_HOSPITAL', 'hospitals', $hospital_id, "Added new hospital: $hospital_name");
                
                // Commit transaction
                mysqli_commit($connection);
                
                $message = "✅ Hospital added successfully! Default password: welcome123";
                $message_type = "success";
                
            } catch (Exception $e) {
                mysqli_rollback($connection);
                $message = "❌ Error: " . $e->getMessage();
                $message_type = "error";
            }
        }
    }
}

// Get states for dropdown (optional - you can hardcode or get from database)
$states = ['Sindh', 'Punjab', 'Khyber Pakhtunkhwa', 'Balochistan', 'Islamabad Capital Territory', 'Gilgit-Baltistan', 'Azad Kashmir'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Hospital - Admin</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 30px;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        /* Header */
        .page-header {
            background: white;
            border-radius: 16px;
            padding: 25px 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .page-header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 0;
        }
        
        .page-header p {
            color: #6b7280;
            margin-top: 5px;
            font-size: 0.9rem;
        }
        
        .back-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 12px 25px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
        }
        
        .back-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.4);
            color: white;
        }
        
        /* Form Card */
        .form-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .form-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f3f4f6;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-title i {
            color: #667eea;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group.full-width {
            grid-column: span 2;
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }
        
        .form-label i {
            color: #667eea;
            margin-right: 8px;
            width: 20px;
        }
        
        .form-label .required {
            color: #dc2626;
            margin-left: 3px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 0.95rem;
            transition: all 0.3s;
            background: #fafcff;
        }
        
        .form-control:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
            background: white;
        }
        
        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }
        
        .input-hint {
            font-size: 0.8rem;
            color: #6b7280;
            margin-top: 5px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.4s ease;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #dc2626;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 14px 30px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s;
            width: 100%;
            margin-top: 20px;
        }
        
        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(40, 167, 69, 0.4);
        }
        
        .btn-submit i {
            font-size: 1.1rem;
        }
        
        .info-box {
            background: #f0f9ff;
            border: 2px solid #bae6fd;
            border-radius: 10px;
            padding: 15px 20px;
            margin-top: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .info-box i {
            font-size: 2rem;
            color: #0284c7;
        }
        
        .info-box-content h4 {
            font-size: 1rem;
            font-weight: 700;
            color: #0369a1;
            margin-bottom: 5px;
        }
        
        .info-box-content p {
            font-size: 0.9rem;
            color: #075985;
        }
        
        @media (max-width: 768px) {
            body {
                padding: 15px;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-group.full-width {
                grid-column: span 1;
            }
            
            .page-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .back-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="page-header">
            <div>
                <h1><i class="fas fa-hospital-alt me-2"></i> Add New Hospital</h1>
                <p><i class="fas fa-plus-circle me-1" style="color: #667eea;"></i> Register a new hospital in the system</p>
            </div>
            <a href="manage_hospitals.php" class="back-btn">
                <i class="fas fa-arrow-left me-2"></i> Back to Hospitals
            </a>
        </div>
        
        <!-- Alert Message -->
        <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <i class="fas <?php echo $message_type == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> fa-lg"></i>
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <!-- Form Card -->
        <div class="form-card">
            <div class="form-title">
                <i class="fas fa-hospital"></i>
                Hospital Information
            </div>
            
            <form method="POST" action="">
                <div class="form-grid">
                    <!-- Hospital Name -->
                    <div class="form-group full-width">
                        <label class="form-label">
                            <i class="fas fa-hospital"></i> Hospital Name <span class="required">*</span>
                        </label>
                        <input type="text" class="form-control" name="hospital_name" 
                               placeholder="e.g., Aga Khan University Hospital" required>
                    </div>
                    
                    <!-- Registration Number -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-id-card"></i> Registration Number
                        </label>
                        <input type="text" class="form-control" name="registration_number" 
                               placeholder="e.g., REG-2025-001">
                    </div>
                    
                    <!-- Email -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-envelope"></i> Email <span class="required">*</span>
                        </label>
                        <input type="email" class="form-control" name="email" 
                               placeholder="hospital@example.com" required>
                    </div>
                    
                    <!-- Phone -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-phone"></i> Phone <span class="required">*</span>
                        </label>
                        <input type="text" class="form-control" name="phone" 
                               placeholder="e.g., 021-111222333" required>
                    </div>
                    
                    <!-- Contact Person -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-user"></i> Contact Person
                        </label>
                        <input type="text" class="form-control" name="contact_person" 
                               placeholder="e.g., Dr. John Smith">
                    </div>
                    
                    <!-- Address -->
                    <div class="form-group full-width">
                        <label class="form-label">
                            <i class="fas fa-map-marker-alt"></i> Address <span class="required">*</span>
                        </label>
                        <textarea class="form-control" name="address" 
                                  placeholder="Street address, building, area..." required></textarea>
                    </div>
                    
                    <!-- City -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-city"></i> City <span class="required">*</span>
                        </label>
                        <input type="text" class="form-control" name="city" 
                               placeholder="e.g., Karachi" required>
                    </div>
                    
                    <!-- State/Province -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-map"></i> State/Province <span class="required">*</span>
                        </label>
                        <select class="form-control" name="state" required>
                            <option value="">Select State</option>
                            <?php foreach ($states as $state): ?>
                                <option value="<?php echo $state; ?>"><?php echo $state; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Postal Code -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-mail-bulk"></i> Postal Code
                        </label>
                        <input type="text" class="form-control" name="postal_code" 
                               placeholder="e.g., 75550">
                    </div>
                    
                    <!-- Latitude & Longitude (Optional) -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-globe"></i> Latitude
                        </label>
                        <input type="text" class="form-control" name="latitude" 
                               placeholder="e.g., 24.8607">
                        <div class="input-hint">Optional - for map location</div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-globe"></i> Longitude
                        </label>
                        <input type="text" class="form-control" name="longitude" 
                               placeholder="e.g., 67.0011">
                        <div class="input-hint">Optional - for map location</div>
                    </div>
                </div>
                
                <!-- Info Box -->
                <div class="info-box">
                    <i class="fas fa-info-circle"></i>
                    <div class="info-box-content">
                        <h4>Default Login Credentials</h4>
                        <p>Email: Hospital email will be used as username<br>Password: <strong>welcome123</strong> (user can change after first login)</p>
                    </div>
                </div>
                
                <!-- Submit Button -->
                <button type="submit" name="add_hospital" class="btn-submit">
                    <i class="fas fa-plus-circle"></i> Add Hospital to System
                </button>
            </form>
        </div>
    </div>
    
    <script>
        // Auto-hide alert after 5 seconds
        setTimeout(function() {
            let alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.remove();
                }, 500);
            });
        }, 5000);
    </script>
</body>
</html>
<?php mysqli_close($connection); ?>