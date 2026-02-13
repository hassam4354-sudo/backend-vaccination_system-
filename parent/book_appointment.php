<?php
// ============================================
// COMPLETE BOOK APPOINTMENT SYSTEM
// Back button - Parent Dashboard par jayega
// ============================================

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) && !isset($_SESSION["logged_in"])) {
    header("Location: ../login.php");
    exit();
}

// Handle both session formats
if (isset($_SESSION["user_id"])) {
    $user_id = $_SESSION["user_id"];
    $user_type = $_SESSION['user_type'] ?? $_SESSION["user_type"] ?? '';
} else if (isset($_SESSION["user_id"])) {
    $user_id = $_SESSION["user_id"];
    $user_type = $_SESSION["user_type"] ?? '';
}

// Database connection
$db_host = "localhost";
$db_name = "child_vaccination_system";
$db_user = "root";
$db_pass = "";

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("<div style='background: #f8d7da; color: #721c24; padding: 20px; margin: 20px; border-radius: 10px;'>
         <h3>❌ Database Connection Failed</h3>
         <p>Error: " . $e->getMessage() . "</p>
         </div>");
}

// Get parent_id for parent users
$parent_id = 0;
if ($user_type === 'parent') {
    // Try both database connection styles
    if (isset($pdo)) {
        $stmt = $pdo->prepare("SELECT parent_id FROM parents WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $parent = $stmt->fetch();
        $parent_id = $parent ? $parent['parent_id'] : 0;
    }
}

// ========== VACCINES LIST ==========
$simple_vaccines = [
    ['id' => 1, 'name' => 'BCG', 'age' => 'At Birth'],
    ['id' => 2, 'name' => 'Hepatitis B', 'age' => 'At Birth, 6,10,14 weeks'],
    ['id' => 3, 'name' => 'OPV', 'age' => 'At Birth, 6,10,14 weeks'],
    ['id' => 4, 'name' => 'Pentavalent', 'age' => '6,10,14 weeks'],
    ['id' => 5, 'name' => 'Rotavirus', 'age' => '6,10,14 weeks'],
    ['id' => 6, 'name' => 'IPV', 'age' => '6,10,14 weeks'],
    ['id' => 7, 'name' => 'PCV', 'age' => '6,10,14 weeks'],
    ['id' => 8, 'name' => 'MMR', 'age' => '9-12 months'],
    ['id' => 9, 'name' => 'Measles', 'age' => '9 months'],
    ['id' => 10, 'name' => 'Vitamin A', 'age' => '9 months, 16 months'],
    ['id' => 11, 'name' => 'DPT', 'age' => '18 months, 5 years'],
    ['id' => 12, 'name' => 'Typhoid', 'age' => '2 years']
];

// ========== HOSPITALS LIST ==========
$simple_hospitals = [
    ['id' => 1, 'name' => 'City Care Hospital', 'city' => 'Mumbai'],
    ['id' => 2, 'name' => "Children's Health Center", 'city' => 'Delhi'],
    ['id' => 3, 'name' => 'Vaccination Hub', 'city' => 'Bangalore'],
    ['id' => 4, 'name' => 'Mother & Child Hospital', 'city' => 'Pune'],
    ['id' => 5, 'name' => 'Pediatric Care Center', 'city' => 'Chennai']
];

// ========== DOCTORS LIST ==========
$simple_doctors = [
    ['id' => 1, 'name' => 'Dr. Sharma', 'specialization' => 'Pediatrician', 'hospital_id' => 1],
    ['id' => 2, 'name' => 'Dr. Gupta', 'specialization' => 'Child Specialist', 'hospital_id' => 1],
    ['id' => 3, 'name' => 'Dr. Patel', 'specialization' => 'Vaccination Expert', 'hospital_id' => 2],
    ['id' => 4, 'name' => 'Dr. Singh', 'specialization' => 'General Physician', 'hospital_id' => 2],
    ['id' => 5, 'name' => 'Dr. Kumar', 'specialization' => 'Pediatrician', 'hospital_id' => 3],
    ['id' => 6, 'name' => 'Dr. Verma', 'specialization' => 'Neonatologist', 'hospital_id' => 3],
    ['id' => 7, 'name' => 'Dr. Reddy', 'specialization' => 'Pediatrician', 'hospital_id' => 4],
    ['id' => 8, 'name' => 'Dr. Joshi', 'specialization' => 'Family Medicine', 'hospital_id' => 5]
];

// ========== GET REGISTERED CHILDREN ==========
$children = [];
try {
    if ($user_type === 'parent' && $parent_id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM children WHERE parent_id = ? AND (is_active = 1 OR is_active IS NULL) ORDER BY full_name");
        $stmt->execute([$parent_id]);
        $children = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $children = [];
}

// ========== PROCESS BOOKING ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {
    $child_id = intval($_POST['child_id'] ?? 0);
    $vaccine_id = intval($_POST['vaccine_id'] ?? 0);
    $hospital_id = intval($_POST['hospital_id'] ?? 0);
    $doctor_id = intval($_POST['doctor_id'] ?? 0);
    $appointment_date = $_POST['appointment_date'] ?? '';
    $appointment_time = $_POST['appointment_time'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    if ($child_id && $vaccine_id && $hospital_id && $doctor_id && $appointment_date && $appointment_time) {
        // Create appointments table if not exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS appointments (
            appointment_id INT PRIMARY KEY AUTO_INCREMENT,
            child_id INT NOT NULL,
            vaccine_id INT NOT NULL,
            hospital_id INT NOT NULL,
            doctor_id INT NOT NULL,
            appointment_date DATE NOT NULL,
            appointment_time TIME NOT NULL,
            notes TEXT,
            status VARCHAR(20) DEFAULT 'scheduled',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Check if slot available
        $check = $pdo->prepare("SELECT appointment_id FROM appointments WHERE appointment_date = ? AND appointment_time = ? AND doctor_id = ? AND status != 'cancelled'");
        $check->execute([$appointment_date, $appointment_time, $doctor_id]);
        
        if ($check->rowCount() > 0) {
            $error = "❌ This time slot is already booked. Please select another time.";
        } else {
            $insert = $pdo->prepare("INSERT INTO appointments (child_id, vaccine_id, hospital_id, doctor_id, appointment_date, appointment_time, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled')");
            if ($insert->execute([$child_id, $vaccine_id, $hospital_id, $doctor_id, $appointment_date, $appointment_time, $notes])) {
                $success = "✅ Appointment booked successfully!";
            } else {
                $error = "❌ Failed to book appointment.";
            }
        }
    } else {
        $error = "❌ Please fill all required fields.";
    }
}

// ========== TIME SLOTS ==========
$time_slots = [
    '09:00:00' => '09:00 AM', '09:30:00' => '09:30 AM', '10:00:00' => '10:00 AM',
    '10:30:00' => '10:30 AM', '11:00:00' => '11:00 AM', '11:30:00' => '11:30 AM',
    '12:00:00' => '12:00 PM', '14:00:00' => '02:00 PM', '14:30:00' => '02:30 PM',
    '15:00:00' => '03:00 PM', '15:30:00' => '03:30 PM', '16:00:00' => '04:00 PM'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Vaccination Appointment</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ========== GLOBAL STYLES ========== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            padding: 30px 20px;
        }
        
        .container {
            max-width: 1100px;
            margin: 0 auto;
        }
        
        /* ========== BACK BUTTON - PARENT DASHBOARD ========== */
        .back-wrapper {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 20px 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            border-left: 8px solid #667eea;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
        }
        
        .back-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            text-decoration: none;
            padding: 14px 35px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 1.1rem;
            display: inline-flex;
            align-items: center;
            transition: all 0.3s;
            border: 2px solid transparent;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .back-btn i {
            margin-right: 12px;
            font-size: 1.1rem;
        }
        
        .back-btn:hover {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
            transform: translateX(-8px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        
        .page-indicator {
            background: #f0f2ff;
            padding: 10px 25px;
            border-radius: 50px;
            color: #667eea;
            font-weight: 600;
        }
        
        .page-indicator i {
            margin-right: 8px;
            color: #764ba2;
        }
        
        /* ========== MAIN CARD ========== */
        .appointment-card {
            background: white;
            border-radius: 30px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.25);
            overflow: hidden;
            animation: slideUp 0.6s ease;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .card-header {
            background: linear-gradient(145deg, #667eea, #764ba2);
            padding: 35px 40px;
            border-bottom: none;
            position: relative;
            overflow: hidden;
        }
        
        .card-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 70%);
            animation: rotate 25s linear infinite;
        }
        
        @keyframes rotate {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        
        .card-header h2 {
            color: white;
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
            text-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .card-header p {
            color: rgba(255,255,255,0.95);
            font-size: 1.1rem;
            margin-bottom: 0;
            position: relative;
            z-index: 1;
        }
        
        .card-body {
            padding: 45px;
            background: white;
        }
        
        /* ========== CHILDREN TOGGLE SECTION ========== */
        .children-section {
            background: linear-gradient(145deg, #f8faff, #f0f3ff);
            border-radius: 25px;
            padding: 30px;
            margin-bottom: 35px;
            border: 2px solid #e2e8ff;
            box-shadow: inset 0 2px 5px rgba(0,0,0,0.02);
        }
        
        .section-title {
            display: flex;
            align-items: center;
            margin-bottom: 25px;
            border-bottom: 2px dashed #a0b4ff;
            padding-bottom: 15px;
        }
        
        .section-title i {
            font-size: 2rem;
            color: #667eea;
            margin-right: 15px;
        }
        
        .section-title h3 {
            font-size: 1.5rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 0;
        }
        
        .registered-badge {
            background: white;
            color: #667eea;
            padding: 8px 20px;
            border-radius: 40px;
            font-weight: 700;
            margin-left: auto;
            border: 2px solid #667eea;
            box-shadow: 0 3px 10px rgba(102,126,234,0.2);
        }
        
        /* Child Cards - Toggle Style */
        .child-card {
            background: white;
            border-radius: 18px;
            padding: 18px 22px;
            margin-bottom: 18px;
            border: 2.5px solid transparent;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            position: relative;
            overflow: hidden;
        }
        
        .child-card::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 6px;
            background: #667eea;
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .child-card:hover {
            border-color: #667eea;
            transform: translateX(8px) scale(1.01);
            box-shadow: 0 10px 25px rgba(102,126,234,0.2);
        }
        
        .child-card:hover::before {
            opacity: 1;
        }
        
        .child-card.selected {
            background: linear-gradient(145deg, #667eea, #764ba2);
            border-color: #667eea;
            transform: scale(1.02);
            box-shadow: 0 15px 35px rgba(102,126,234,0.4);
        }
        
        .child-card.selected::before {
            background: white;
            opacity: 1;
            width: 8px;
        }
        
        .child-card.selected .child-name,
        .child-card.selected .child-info,
        .child-card.selected .child-info span,
        .child-card.selected .badge {
            color: white !important;
        }
        
        .child-avatar {
            width: 55px;
            height: 55px;
            background: linear-gradient(145deg, #edf2ff, #dfe7ff);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 18px;
            border: 3px solid white;
            box-shadow: 0 5px 12px rgba(0,0,0,0.08);
        }
        
        .child-avatar i {
            font-size: 1.6rem;
            color: #667eea;
        }
        
        .child-card.selected .child-avatar {
            background: rgba(255,255,255,0.25);
            border-color: rgba(255,255,255,0.5);
        }
        
        .child-card.selected .child-avatar i {
            color: white;
        }
        
        .child-name {
            font-size: 1.2rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 6px;
        }
        
        .child-info {
            display: flex;
            gap: 20px;
            color: #718096;
            font-size: 0.9rem;
        }
        
        .child-info span {
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .radio-indicator {
            width: 28px;
            height: 28px;
            border: 3px solid #667eea;
            border-radius: 50%;
            display: inline-block;
            position: relative;
            transition: all 0.2s;
            background: white;
        }
        
        .child-card.selected .radio-indicator {
            background: white;
            border-color: white;
            transform: scale(1.1);
        }
        
        .child-card.selected .radio-indicator::after {
            content: '\f00c';
            font-family: 'Font Awesome 6 Free';
            font-weight: 900;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: #764ba2;
            font-size: 14px;
        }
        
        .child-id-badge {
            background: rgba(102,126,234,0.15);
            color: #667eea;
            padding: 6px 16px;
            border-radius: 40px;
            font-weight: 600;
            font-size: 0.85rem;
            margin-right: 15px;
        }
        
        .child-card.selected .child-id-badge {
            background: rgba(255,255,255,0.25);
            color: white;
        }
        
        /* ========== FORM STYLES ========== */
        .form-label {
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 10px;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
        }
        
        .form-label i {
            margin-right: 10px;
            color: #667eea;
            font-size: 1.1rem;
        }
        
        .form-control, .form-select {
            border: 2.5px solid #e2e8f0;
            border-radius: 16px;
            padding: 14px 18px;
            font-size: 1rem;
            transition: all 0.3s;
            background: #fafcff;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102,126,234,0.15);
            background: white;
        }
        
        /* ========== BOOK BUTTON ========== */
        .btn-book {
            background: linear-gradient(145deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 18px 40px;
            font-size: 1.3rem;
            font-weight: 800;
            border-radius: 60px;
            width: 100%;
            transition: all 0.4s;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-top: 30px;
            position: relative;
            overflow: hidden;
            border: 2px solid transparent;
        }
        
        .btn-book:hover {
            transform: translateY(-5px) scale(1.02);
            box-shadow: 0 25px 45px rgba(102,126,234,0.5);
            color: white;
            border: 2px solid white;
        }
        
        .btn-book::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.6s;
        }
        
        .btn-book:hover::before {
            left: 100%;
        }
        
        /* ========== ALERTS ========== */
        .alert {
            border-radius: 18px;
            padding: 18px 25px;
            border: none;
            margin-bottom: 30px;
            font-weight: 600;
            display: flex;
            align-items: center;
            animation: slideDown 0.5s ease;
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
        
        .alert-success {
            background: linear-gradient(145deg, #d4edda, #c3e6cb);
            color: #155724;
            border-left: 8px solid #28a745;
        }
        
        .alert-danger {
            background: linear-gradient(145deg, #f8d7da, #f5c6cb);
            color: #721c24;
            border-left: 8px solid #dc3545;
        }
        
        /* ========== EMPTY STATE ========== */
        .empty-state {
            text-align: center;
            padding: 60px 30px;
            background: white;
            border-radius: 25px;
            border: 3px dashed #cbd5e0;
        }
        
        .empty-state i {
            font-size: 5rem;
            color: #a0b4ff;
            margin-bottom: 25px;
        }
        
        .empty-state h4 {
            color: #2d3748;
            font-weight: 700;
            margin-bottom: 15px;
            font-size: 1.8rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            padding: 14px 35px;
            border-radius: 50px;
            font-weight: 700;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(102,126,234,0.4);
        }
        
        /* ========== INFO SECTION ========== */
        .info-section {
            background: linear-gradient(145deg, #f8faff, #f0f3ff);
            border-radius: 20px;
            padding: 25px 30px;
            margin-top: 40px;
            border: 2px solid #e2e8ff;
        }
        
        .info-section h5 {
            color: #667eea;
            font-weight: 800;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
        }
        
        .info-section h5 i {
            font-size: 1.3rem;
            margin-right: 12px;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
            color: #4a5568;
        }
        
        .info-item i {
            color: #667eea;
            width: 28px;
            font-size: 1.1rem;
        }
        
        /* ========== RESPONSIVE ========== */
        @media (max-width: 768px) {
            .card-body { padding: 25px; }
            .card-header h2 { font-size: 1.6rem; }
            .child-card { flex-direction: column; text-align: center; }
            .child-avatar { margin-right: 0; margin-bottom: 15px; }
            .back-wrapper { flex-direction: column; gap: 15px; }
            .child-info { flex-direction: column; gap: 8px; }
        }
        
        @media (max-width: 576px) {
            body { padding: 15px; }
            .card-header { padding: 25px; }
            .children-section { padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- ===== BACK BUTTON - PARENT DASHBOARD ===== -->
        <div class="back-wrapper">
            <div class="d-flex align-items-center">
                <a href="dashboard.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <span class="ms-3 text-muted d-none d-md-inline">
                    <i class="fas fa-home me-1"></i> Return to Parent Dashboard
                </span>
            </div>
            <div class="page-indicator">
                <i class="fas fa-calendar-plus"></i> Book New Appointment
            </div>
        </div>

        <!-- ===== MAIN APPOINTMENT CARD ===== -->
        <div class="appointment-card">
            <div class="card-header">
                <h2><i class="fas fa-syringe me-3"></i>Book Vaccination Appointment</h2>
                <p><i class="fas fa-shield-alt me-2"></i>Secure your child's vaccination slot with our expert doctors</p>
            </div>
            
            <div class="card-body">
                <!-- Success/Error Messages -->
                <?php if (isset($success)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle fa-2x me-3"></i>
                        <div>
                            <strong>Success!</strong><br>
                            <?php echo $success; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle fa-2x me-3"></i>
                        <div>
                            <strong>Error!</strong><br>
                            <?php echo $error; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- CHILDREN TOGGLE SECTION - SIRF REGISTERED CHILDREN -->
                <div class="children-section">
                    <div class="section-title">
                        <i class="fas fa-child"></i>
                        <h3>Select Your Child</h3>
                        <span class="registered-badge">
                            <i class="fas fa-users me-2"></i><?php echo count($children); ?> Registered
                        </span>
                    </div>

                    <?php if (empty($children)): ?>
                        <!-- NO CHILDREN CASE -->
                        <div class="empty-state">
                            <i class="fas fa-child"></i>
                            <h4>No Registered Children Found!</h4>
                            <p class="text-muted fs-5 mb-4">Please register your child first to book an appointment.</p>
                            <a href="add_child.php" class="btn btn-primary btn-lg">
                                <i class="fas fa-plus-circle me-2"></i>Register Your Child Now
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- REGISTERED CHILDREN LIST - TOGGLE BAR -->
                        <form method="POST" action="" id="bookingForm">
                            <?php foreach ($children as $index => $child): 
                                $dob = new DateTime($child['date_of_birth']);
                                $today = new DateTime();
                                $age = $today->diff($dob);
                            ?>
                            <div class="child-card <?php echo $index === 0 ? 'selected' : ''; ?>" 
                                 onclick="selectChild(this, <?php echo $child['child_id']; ?>)">
                                <div class="d-flex align-items-center">
                                    <div class="child-avatar">
                                        <i class="fas <?php echo ($child['gender'] ?? 'Male') == 'Male' ? 'fa-child' : 'fa-female'; ?>"></i>
                                    </div>
                                    <div>
                                        <div class="child-name"><?php echo htmlspecialchars($child['full_name']); ?></div>
                                        <div class="child-info">
                                            <span>
                                                <i class="fas fa-venus-mars"></i>
                                                <?php echo $child['gender'] ?? 'N/A'; ?>
                                            </span>
                                            <span>
                                                <i class="fas fa-birthday-cake"></i>
                                                <?php echo $age->y; ?> years, <?php echo $age->m; ?> months
                                            </span>
                                            <span>
                                                <i class="fas fa-id-card"></i>
                                                ID: #<?php echo $child['child_id']; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center">
                                    <span class="child-id-badge me-3">
                                        <i class="fas fa-check-circle me-1"></i>Active
                                    </span>
                                    <span class="radio-indicator"></span>
                                </div>
                                <input type="radio" name="child_id" value="<?php echo $child['child_id']; ?>" 
                                       class="d-none" <?php echo $index === 0 ? 'checked' : ''; ?>>
                            </div>
                            <?php endforeach; ?>

                            <!-- APPOINTMENT DETAILS FORM -->
                            <div class="row mt-5">
                                <div class="col-md-6 mb-4">
                                    <label class="form-label">
                                        <i class="fas fa-syringe"></i> Select Vaccine *
                                    </label>
                                    <select class="form-select" name="vaccine_id" required>
                                        <option value="">-- Choose Vaccine --</option>
                                        <?php foreach ($simple_vaccines as $v): ?>
                                            <option value="<?php echo $v['id']; ?>">
                                                <?php echo $v['name']; ?> - <?php echo $v['age']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6 mb-4">
                                    <label class="form-label">
                                        <i class="fas fa-hospital"></i> Select Hospital *
                                    </label>
                                    <select class="form-select" name="hospital_id" id="hospitalSelect" required>
                                        <option value="">-- Choose Hospital --</option>
                                        <?php foreach ($simple_hospitals as $h): ?>
                                            <option value="<?php echo $h['id']; ?>">
                                                <?php echo $h['name']; ?> (<?php echo $h['city']; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6 mb-4">
                                    <label class="form-label">
                                        <i class="fas fa-user-md"></i> Select Doctor *
                                    </label>
                                    <select class="form-select" name="doctor_id" id="doctorSelect" required>
                                        <option value="">-- First Select Hospital --</option>
                                        <?php foreach ($simple_doctors as $d): ?>
                                            <option value="<?php echo $d['id']; ?>" data-hospital="<?php echo $d['hospital_id']; ?>">
                                                Dr. <?php echo $d['name']; ?> - <?php echo $d['specialization']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-6 mb-4">
                                    <label class="form-label">
                                        <i class="fas fa-calendar-alt"></i> Appointment Date *
                                    </label>
                                    <input type="date" class="form-control" name="appointment_date" 
                                           min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" 
                                           max="<?php echo date('Y-m-d', strtotime('+1 month')); ?>"
                                           value="<?php echo date('Y-m-d', strtotime('+3 days')); ?>" 
                                           required>
                                </div>

                                <div class="col-md-6 mb-4">
                                    <label class="form-label">
                                        <i class="fas fa-clock"></i> Appointment Time *
                                    </label>
                                    <select class="form-select" name="appointment_time" required>
                                        <option value="">-- Select Time --</option>
                                        <?php foreach ($time_slots as $value => $label): ?>
                                            <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-12 mb-4">
                                    <label class="form-label">
                                        <i class="fas fa-notes-medical"></i> Additional Notes (Optional)
                                    </label>
                                    <textarea class="form-control" name="notes" rows="3" 
                                              placeholder="Any specific concerns, allergies, or previous reactions?"></textarea>
                                </div>
                            </div>

                            <!-- BOOK BUTTON -->
                            <button type="submit" name="book_appointment" class="btn-book">
                                <i class="fas fa-calendar-check me-2"></i> Book Appointment
                                <span style="font-size: 0.9rem; display: block; text-transform: none; letter-spacing: normal; margin-top: 5px;">
                                    <i class="fas fa-shield-alt me-1"></i>Secure & Instant
                                </span>
                            </button>
                        </form>

                        <!-- IMPORTANT INFORMATION -->
                        <div class="info-section">
                            <h5><i class="fas fa-info-circle"></i> Important Guidelines</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-item">
                                        <i class="fas fa-check-circle"></i>
                                        <span>Arrive 10-15 minutes before appointment</span>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-id-card"></i>
                                        <span>Bring vaccination card and documents</span>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-thermometer"></i>
                                        <span>Don't come if child has fever</span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-item">
                                        <i class="fas fa-bell"></i>
                                        <span>SMS reminder 24 hours before</span>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-file-alt"></i>
                                        <span>Digital certificate provided</span>
                                    </div>
                                    <div class="info-item">
                                        <i class="fas fa-headset"></i>
                                        <span>Call for cancellations/changes</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
    // ===== SELECT CHILD FUNCTION =====
    function selectChild(element, childId) {
        document.querySelectorAll('.child-card').forEach(card => {
            card.classList.remove('selected');
            let radio = card.querySelector('input[type="radio"]');
            if (radio) radio.checked = false;
        });
        
        element.classList.add('selected');
        let radio = element.querySelector('input[type="radio"]');
        if (radio) radio.checked = true;
    }

    // ===== FILTER DOCTORS BY HOSPITAL =====
    document.getElementById('hospitalSelect')?.addEventListener('change', function() {
        let hospitalId = this.value;
        let doctorSelect = document.getElementById('doctorSelect');
        let options = doctorSelect.querySelectorAll('option');
        
        doctorSelect.value = '';
        
        options.forEach(option => {
            if (option.value === '') {
                option.textContent = hospitalId ? '-- Select Doctor --' : '-- First Select Hospital --';
                option.disabled = false;
            } else {
                let doctorHospital = option.getAttribute('data-hospital');
                if (hospitalId && doctorHospital === hospitalId) {
                    option.style.display = '';
                    option.disabled = false;
                } else {
                    option.style.display = 'none';
                    option.disabled = true;
                }
            }
        });
    });

    // ===== DISABLE SUNDAYS =====
    document.querySelector('input[name="appointment_date"]')?.addEventListener('change', function() {
        let selectedDate = new Date(this.value);
        let day = selectedDate.getDay();
        
        if (day === 0) {
            alert('❌ Appointments are not available on Sundays. Please select another date.');
            this.value = '<?php echo date('Y-m-d', strtotime('+1 day')); ?>';
        }
    });

    // ===== AUTO HIDE ALERTS =====
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        });
    }, 5000);

    // ===== FORM VALIDATION =====
    document.getElementById('bookingForm')?.addEventListener('submit', function(e) {
        let childSelected = document.querySelector('input[name="child_id"]:checked');
        let vaccine = document.querySelector('select[name="vaccine_id"]').value;
        let hospital = document.querySelector('select[name="hospital_id"]').value;
        let doctor = document.querySelector('select[name="doctor_id"]').value;
        let date = document.querySelector('input[name="appointment_date"]').value;
        let time = document.querySelector('select[name="appointment_time"]').value;
        
        if (!childSelected) {
            e.preventDefault();
            alert('❌ Please select a child');
        } else if (!vaccine) {
            e.preventDefault();
            alert('❌ Please select a vaccine');
        } else if (!hospital) {
            e.preventDefault();
            alert('❌ Please select a hospital');
        } else if (!doctor) {
            e.preventDefault();
            alert('❌ Please select a doctor');
        } else if (!date) {
            e.preventDefault();
            alert('❌ Please select appointment date');
        } else if (!time) {
            e.preventDefault();
            alert('❌ Please select appointment time');
        }
    });
    </script>
</body>
</html>