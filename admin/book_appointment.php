<?php
// book_appointment.php - SIMPLIFIED VERSION
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
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
    die("<div style='background: #f8d7da; padding: 20px; margin: 20px; border-radius: 10px; color: #721c24;'>
         <h3>❌ Database Connection Failed</h3>
         <p>Please check if MySQL is running and database exists.</p>
         <p>Error: " . $e->getMessage() . "</p>
         </div>");
}

$user_id = $_SESSION['user_id'];
$user_type = $_SESSION['user_type'] ?? '';
$parent_id = 0;

// Get parent_id for parent users
if ($user_type === 'parent') {
    $stmt = $pdo->prepare("SELECT parent_id FROM parents WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $parent = $stmt->fetch();
    $parent_id = $parent ? $parent['parent_id'] : 0;
}

// SIMPLE VACCINE LIST - Hardcoded (no database dependency)
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

// SIMPLE HOSPITAL LIST - Hardcoded
$simple_hospitals = [
    ['id' => 1, 'name' => 'City Care Hospital', 'city' => 'Mumbai'],
    ['id' => 2, 'name' => 'Children\'s Health Center', 'city' => 'Delhi'],
    ['id' => 3, 'name' => 'Vaccination Hub', 'city' => 'Bangalore'],
    ['id' => 4, 'name' => 'Mother & Child Hospital', 'city' => 'Pune'],
    ['id' => 5, 'name' => 'Pediatric Care Center', 'city' => 'Chennai']
];

// SIMPLE DOCTOR LIST - Hardcoded
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

// GET REGISTERED CHILDREN FROM DATABASE - YEH TOGGLE BAR MEIN DIKHEGA
$children = [];
try {
    if ($user_type === 'parent' && $parent_id > 0) {
        // PARENT: Sirf apne registered children dekhega
        $stmt = $pdo->prepare("SELECT * FROM children WHERE parent_id = ? AND (is_active = 1 OR is_active IS NULL) ORDER BY full_name");
        $stmt->execute([$parent_id]);
    } else {
        // ADMIN: Sab registered children dekhega
        $stmt = $pdo->prepare("SELECT c.*, p.full_name as parent_name FROM children c 
                               LEFT JOIN parents p ON c.parent_id = p.parent_id 
                               WHERE c.is_active = 1 OR c.is_active IS NULL 
                               ORDER BY c.full_name");
        $stmt->execute();
    }
    $children = $stmt->fetchAll();
} catch (PDOException $e) {
    $children = [];
}

// PROCESS BOOKING
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
            $error = "This time slot is already booked. Please select another time.";
        } else {
            $insert = $pdo->prepare("INSERT INTO appointments (child_id, vaccine_id, hospital_id, doctor_id, appointment_date, appointment_time, notes, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled')");
            if ($insert->execute([$child_id, $vaccine_id, $hospital_id, $doctor_id, $appointment_date, $appointment_time, $notes])) {
                $success = "✅ Appointment booked successfully!";
            } else {
                $error = "Failed to book appointment.";
            }
        }
    } else {
        $error = "Please fill all required fields.";
    }
}

// TIME SLOTS
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: 'Segoe UI', sans-serif;
            min-height: 100vh;
            padding: 20px;
        }
        .container {
            max-width: 1000px;
            margin: 0 auto;
        }
        .card {
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            border: none;
            overflow: hidden;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border: none;
        }
        .card-header h2 {
            margin: 0;
            font-weight: 700;
        }
        .card-body {
            padding: 40px;
            background: white;
        }
        .children-section {
            background: #f8f9ff;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            border: 2px solid #e8eafd;
        }
        .child-card {
            background: white;
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
            border: 2px solid transparent;
            transition: all 0.3s;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        .child-card:hover {
            border-color: #667eea;
            transform: translateX(5px);
        }
        .child-card.selected {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: #667eea;
        }
        .child-card.selected .child-name,
        .child-card.selected .child-info {
            color: white;
        }
        .child-avatar {
            width: 45px;
            height: 45px;
            background: #eef2ff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }
        .child-avatar i {
            font-size: 1.2rem;
            color: #667eea;
        }
        .child-card.selected .child-avatar {
            background: rgba(255,255,255,0.2);
        }
        .child-card.selected .child-avatar i {
            color: white;
        }
        .child-name {
            font-weight: 600;
            color: #333;
            margin-bottom: 5px;
        }
        .child-info {
            font-size: 0.85rem;
            color: #666;
        }
        .radio-indicator {
            width: 22px;
            height: 22px;
            border: 2px solid #667eea;
            border-radius: 50%;
            display: inline-block;
            position: relative;
        }
        .child-card.selected .radio-indicator {
            background: white;
            border-color: white;
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
            font-size: 12px;
        }
        .btn-book {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            font-size: 1.2rem;
            font-weight: 700;
            border-radius: 50px;
            width: 100%;
            transition: all 0.3s;
        }
        .btn-book:hover {
            transform: translateY(-3px);
            box-shadow: 0 20px 40px rgba(102, 126, 234, 0.4);
            color: white;
        }
        .empty-state {
            text-align: center;
            padding: 40px;
            background: #f8f9ff;
            border-radius: 15px;
        }
        .empty-state i {
            font-size: 4rem;
            color: #667eea;
            margin-bottom: 20px;
        }
        .alert {
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 25px;
            border: none;
        }
        .back-btn {
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 30px;
            background: rgba(255,255,255,0.2);
            display: inline-block;
            margin-bottom: 20px;
        }
        .back-btn:hover {
            background: rgba(255,255,255,0.3);
            color: white;
        }
        .form-control, .form-select {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 12px 15px;
        }
        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: none;
        }
        .badge-count {
            background: white;
            color: #667eea;
            padding: 5px 15px;
            border-radius: 30px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Back Button -->
        <?php if ($user_type === 'admin'): ?>
            <a href="manage_children.php" class="back-btn"><i class="fas fa-arrow-left me-2"></i>Back</a>
        <?php else: ?>
            <a href="parent_dashboard.php" class="back-btn"><i class="fas fa-arrow-left me-2"></i>Back</a>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-syringe me-2"></i>Book Vaccination Appointment</h2>
                <p class="mb-0 text-white-50">Schedule your child's vaccination</p>
            </div>
            <div class="card-body">
                <!-- Messages -->
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <!-- CHILDREN TOGGLE BAR - SIRF REGISTERED CHILDREN -->
                <div class="children-section">
                    <div class="d-flex align-items-center mb-3">
                        <i class="fas fa-child fa-2x me-2" style="color: #667eea;"></i>
                        <h5 class="mb-0 fw-bold">Select Your Child</h5>
                        <span class="badge-count ms-auto">
                            <i class="fas fa-users me-1"></i><?php echo count($children); ?> Registered
                        </span>
                    </div>

                    <?php if (empty($children)): ?>
                        <!-- NO CHILDREN REGISTERED -->
                        <div class="empty-state">
                            <i class="fas fa-child"></i>
                            <h4>No Registered Children Found</h4>
                            <p class="text-muted">Please register your child first to book an appointment.</p>
                            <?php if ($user_type === 'parent'): ?>
                                <a href="register_child.php" class="btn btn-primary">
                                    <i class="fas fa-plus-circle me-2"></i>Register Child
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <!-- REGISTERED CHILDREN LIST - TOGGLE BAR -->
                        <form method="POST" id="bookingForm">
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
                                            <span class="me-3"><i class="fas fa-venus-mars me-1"></i><?php echo $child['gender'] ?? 'N/A'; ?></span>
                                            <span><i class="fas fa-birthday-cake me-1"></i><?php echo $age->y; ?> yr <?php echo $age->m; ?> mo</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center">
                                    <span class="me-3 badge bg-light text-dark">
                                        ID: #<?php echo $child['child_id']; ?>
                                    </span>
                                    <span class="radio-indicator"></span>
                                </div>
                                <input type="radio" name="child_id" value="<?php echo $child['child_id']; ?>" 
                                       class="d-none" <?php echo $index === 0 ? 'checked' : ''; ?>>
                            </div>
                            <?php endforeach; ?>

                            <div class="row mt-4">
                                <!-- Select Vaccine -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">💉 Select Vaccine</label>
                                    <select class="form-select" name="vaccine_id" required>
                                        <option value="">Choose vaccine</option>
                                        <?php foreach ($simple_vaccines as $v): ?>
                                            <option value="<?php echo $v['id']; ?>">
                                                <?php echo $v['name']; ?> - <?php echo $v['age']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Select Hospital -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">🏥 Select Hospital</label>
                                    <select class="form-select" name="hospital_id" id="hospitalSelect" required>
                                        <option value="">Choose hospital</option>
                                        <?php foreach ($simple_hospitals as $h): ?>
                                            <option value="<?php echo $h['id']; ?>">
                                                <?php echo $h['name']; ?> - <?php echo $h['city']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Select Doctor -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">👨‍⚕️ Select Doctor</label>
                                    <select class="form-select" name="doctor_id" id="doctorSelect" required>
                                        <option value="">First select hospital</option>
                                        <?php foreach ($simple_doctors as $d): ?>
                                            <option value="<?php echo $d['id']; ?>" data-hospital="<?php echo $d['hospital_id']; ?>">
                                                Dr. <?php echo $d['name']; ?> (<?php echo $d['specialization']; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Date -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">📅 Appointment Date</label>
                                    <input type="date" class="form-control" name="appointment_date" 
                                           min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" 
                                           max="<?php echo date('Y-m-d', strtotime('+1 month')); ?>"
                                           value="<?php echo date('Y-m-d', strtotime('+3 days')); ?>" required>
                                </div>

                                <!-- Time -->
                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">⏰ Appointment Time</label>
                                    <select class="form-select" name="appointment_time" required>
                                        <option value="">Select time</option>
                                        <?php foreach ($time_slots as $value => $label): ?>
                                            <option value="<?php echo $value; ?>"><?php echo $label; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <!-- Notes -->
                                <div class="col-12 mb-3">
                                    <label class="form-label fw-bold">📝 Notes (Optional)</label>
                                    <textarea class="form-control" name="notes" rows="2" placeholder="Any specific concerns?"></textarea>
                                </div>
                            </div>

                            <button type="submit" name="book_appointment" class="btn-book">
                                <i class="fas fa-calendar-check me-2"></i>Book Appointment
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Select child function
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

    // Filter doctors by hospital
    document.getElementById('hospitalSelect')?.addEventListener('change', function() {
        let hospitalId = this.value;
        let doctorSelect = document.getElementById('doctorSelect');
        let options = doctorSelect.querySelectorAll('option');
        
        doctorSelect.value = '';
        
        options.forEach(option => {
            if (option.value === '') {
                option.textContent = hospitalId ? 'Select doctor' : 'First select hospital';
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

    // Auto hide alerts
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(alert => alert.remove());
    }, 5000);
    </script>
</body>
</html>