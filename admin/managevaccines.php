<?php
session_start();

// Database connection
$db_host = "localhost";
$db_name = "child_vaccination_system";
$db_user = "root";
$db_pass = "";

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// ===== ADD VACCINE TO DATABASE =====
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_vaccine'])) {
    $vaccine_name = trim($_POST['vaccine_name'] ?? '');
    $vaccine_description = trim($_POST['vaccine_description'] ?? '');
    $scheduled_age = trim($_POST['scheduled_age'] ?? '');
    $manufacturer = trim($_POST['manufacturer'] ?? '');
    $dose_number = intval($_POST['dose_number'] ?? 1);
    $is_active = isset($_POST['is_active']) ? 1 : 1;
    
    if (!empty($vaccine_name) && !empty($vaccine_description)) {
        // Check if vaccines table exists, if not create it
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS vaccines (
                vaccine_id INT PRIMARY KEY AUTO_INCREMENT,
                vaccine_name VARCHAR(100) NOT NULL,
                description TEXT,
                scheduled_age VARCHAR(50),
                manufacturer VARCHAR(100),
                dose_number INT DEFAULT 1,
                is_active BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");
            
            // Insert vaccine
            $stmt = $pdo->prepare("INSERT INTO vaccines (vaccine_name, description, scheduled_age, manufacturer, dose_number, is_active) 
                                  VALUES (?, ?, ?, ?, ?, ?)");
            
            if ($stmt->execute([$vaccine_name, $vaccine_description, $scheduled_age, $manufacturer, $dose_number, $is_active])) {
                $message = "✅ Vaccine '{$vaccine_name}' added successfully!";
                $message_type = "success";
            } else {
                $message = "❌ Failed to add vaccine.";
                $message_type = "error";
            }
        } catch (PDOException $e) {
            $message = "❌ Database error: " . $e->getMessage();
            $message_type = "error";
        }
    } else {
        $message = "❌ Vaccine name and description are required!";
        $message_type = "error";
    }
}

// ===== GET ALL VACCINES FROM DATABASE =====
$vaccines = [];
try {
    $stmt = $pdo->query("SELECT * FROM vaccines WHERE is_active = 1 OR is_active IS NULL ORDER BY vaccine_name");
    $vaccines = $stmt->fetchAll();
} catch (PDOException $e) {
    // Table might not exist yet
    $vaccines = [];
}

// Default vaccines if database empty
if (empty($vaccines)) {
    $vaccines = [
        ['vaccine_name' => 'BCG', 'vaccine_description' => 'BCG vaccine tuberculosis (TB) se bachao ke liye di jati hai.', 'scheduled_age' => 'At Birth', 'manufacturer' => 'Serum Institute', 'dose_number' => 1],
        ['vaccine_name' => 'DPT', 'vaccine_description' => 'DPT diphtheria, pertussis aur tetanus se protection deta hai.', 'scheduled_age' => '6 weeks, 10 weeks, 14 weeks', 'manufacturer' => 'Serum Institute', 'dose_number' => 3],
        ['vaccine_name' => 'Hepatitis B', 'vaccine_description' => 'Ye vaccine liver infection se bachata hai.', 'scheduled_age' => 'At Birth, 6,10,14 weeks', 'manufacturer' => 'Biological E', 'dose_number' => 4],
        ['vaccine_name' => 'IPV', 'vaccine_description' => 'IPV polio se bachao karta hai.', 'scheduled_age' => '6,10,14 weeks', 'manufacturer' => 'Sanofi', 'dose_number' => 3],
        ['vaccine_name' => 'Measles', 'vaccine_description' => 'Measles vaccine khusra virus se bachata hai.', 'scheduled_age' => '9 months', 'manufacturer' => 'Serum Institute', 'dose_number' => 1],
        ['vaccine_name' => 'OPV', 'vaccine_description' => 'OPV oral polio vaccine hai.', 'scheduled_age' => 'At Birth, 6,10,14 weeks', 'manufacturer' => 'Bharat Biotech', 'dose_number' => 4],
        ['vaccine_name' => 'PCV', 'vaccine_description' => 'PCV pneumonia se bachata hai.', 'scheduled_age' => '6 weeks, 10 weeks, 14 weeks, Booster', 'manufacturer' => 'Pfizer', 'dose_number' => 4],
        ['vaccine_name' => 'Pentavalent', 'vaccine_description' => 'Ye vaccine 5 diseases se ek sath bachata hai.', 'scheduled_age' => '6,10,14 weeks', 'manufacturer' => 'Serum Institute', 'dose_number' => 3],
        ['vaccine_name' => 'Vitamin A', 'vaccine_description' => 'Vitamin A immunity strong karta hai.', 'scheduled_age' => '9 months, 16 months, 24 months', 'manufacturer' => 'Various', 'dose_number' => 3],
        ['vaccine_name' => 'Rotavirus', 'vaccine_description' => 'Rotavirus severe diarrhea se bachata hai.', 'scheduled_age' => '6,10,14 weeks', 'manufacturer' => 'Bharat Biotech', 'dose_number' => 3],
        ['vaccine_name' => 'MMR', 'vaccine_description' => 'MMR measles, mumps aur rubella se protection deta hai.', 'scheduled_age' => '9-12 months, 15 months', 'manufacturer' => 'Serum Institute', 'dose_number' => 2],
        ['vaccine_name' => 'Typhoid', 'vaccine_description' => 'Typhoid vaccine bukhar se bachata hai.', 'scheduled_age' => '2 years', 'manufacturer' => 'Bharat Biotech', 'dose_number' => 1]
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vaccination History</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: "Segoe UI", Arial, sans-serif;
            background: #f2f5ff;
        }

        .header {
            background: linear-gradient(135deg, #5a6dfc, #6f80ff);
            padding: 20px 30px;
            color: #fff;
            font-size: 24px;
            font-weight: 600;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .back-home {
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            background: rgba(255,255,255,0.2);
            border-radius: 30px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .back-home:hover {
            background: rgba(255,255,255,0.3);
            transform: translateX(-5px);
        }

        .container {
            width: 90%;
            margin: 35px auto;
        }

        .title-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .title {
            font-size: 28px;
            color: #333;
            font-weight: 600;
        }

        /* ===== ADD VACCINE BUTTON ===== */
        .add-vaccine-btn {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border: none;
            padding: 14px 28px;
            font-size: 16px;
            font-weight: 600;
            border-radius: 50px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
            border: 2px solid transparent;
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }

        .add-vaccine-btn i {
            font-size: 18px;
        }

        .add-vaccine-btn:hover {
            background: white;
            color: #28a745;
            border: 2px solid #28a745;
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(40, 167, 69, 0.4);
        }

        /* ===== MODAL FORM ===== */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(5px);
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            width: 90%;
            max-width: 600px;
            border-radius: 25px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.3);
            animation: slideIn 0.4s ease;
            max-height: 90vh;
            overflow-y: auto;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            background: linear-gradient(135deg, #5a6dfc, #6f80ff);
            color: white;
            padding: 25px 30px;
            border-radius: 25px 25px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h3 {
            font-size: 24px;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .close-btn {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            font-size: 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .close-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 35px;
        }

        .form-group {
            margin-bottom: 22px;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 15px;
        }

        .form-label i {
            color: #5a6dfc;
            margin-right: 8px;
            width: 20px;
        }

        .form-control {
            width: 100%;
            padding: 14px 18px;
            border: 2px solid #e1e5f0;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s;
            background: #fafcff;
        }

        .form-control:focus {
            border-color: #5a6dfc;
            outline: none;
            box-shadow: 0 0 0 4px rgba(90, 109, 252, 0.1);
            background: white;
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .btn-submit {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
            border: none;
            padding: 16px 30px;
            font-size: 17px;
            font-weight: 700;
            border-radius: 50px;
            width: 100%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s;
            border: 2px solid transparent;
            margin-top: 15px;
        }

        .btn-submit:hover {
            background: white;
            color: #28a745;
            border: 2px solid #28a745;
            transform: translateY(-3px);
        }

        /* ===== VACCINE CARDS ===== */
        .grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 22px;
        }

        .vaccine-card {
            background: #fff;
            border-radius: 16px;
            padding: 22px;
            box-shadow: 0 8px 18px rgba(0,0,0,0.08);
            transition: all 0.25s ease;
            border-left: 5px solid #5a6dfc;
            position: relative;
            cursor: pointer;
        }

        .vaccine-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.12);
        }

        .vaccine-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 10px;
        }

        .vaccine-name {
            font-size: 19px;
            font-weight: 700;
            color: #5a6dfc;
        }

        .vaccine-age {
            background: #eef2ff;
            padding: 5px 12px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            color: #5a6dfc;
        }

        .vaccine-manufacturer {
            font-size: 13px;
            color: #666;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .vaccine-desc {
            position: absolute;
            bottom: 110%;
            left: 50%;
            transform: translateX(-50%);
            width: 260px;
            background: #fff;
            padding: 18px;
            font-size: 14px;
            color: #555;
            line-height: 1.6;
            border-radius: 12px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            opacity: 0;
            visibility: hidden;
            transition: 0.3s;
            z-index: 10;
            border: 1px solid #e1e5f0;
        }

        .vaccine-desc::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            transform: translateX(-50%);
            border-width: 8px;
            border-style: solid;
            border-color: #fff transparent transparent transparent;
        }

        .vaccine-card:hover .vaccine-desc {
            opacity: 1;
            visibility: visible;
        }

        .dose-badge {
            background: #5a6dfc;
            color: white;
            padding: 4px 12px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
            margin-top: 10px;
        }

        /* ===== ACTION BOX ===== */
        .action-box {
            margin-top: 45px;
            text-align: center;
            background: #fff;
            padding: 35px;
            border-radius: 18px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }

        .action-box h3 {
            margin-bottom: 8px;
            color: #333;
            font-size: 24px;
            font-weight: 700;
        }

        .action-box p {
            color: #666;
            margin-bottom: 22px;
            font-size: 16px;
        }

        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #5a6dfc, #6f80ff);
            color: #fff;
            text-decoration: none;
            padding: 14px 34px;
            font-size: 16px;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s;
            border: 2px solid transparent;
        }

        .btn:hover {
            background: white;
            color: #5a6dfc;
            border: 2px solid #5a6dfc;
            transform: scale(1.05);
        }

        /* ===== ALERT MESSAGES ===== */
        .alert {
            padding: 18px 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.4s ease;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 6px solid #28a745;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border-left: 6px solid #dc3545;
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

        /* ===== RESPONSIVE ===== */
        @media (max-width: 900px) {
            .grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 600px) {
            .grid {
                grid-template-columns: 1fr;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .modal-body {
                padding: 25px;
            }
            
            .title-section {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .add-vaccine-btn {
                width: 100%;
                justify-content: center;
            }
        }
        
        .stats-badge {
            background: white;
            padding: 8px 18px;
            border-radius: 30px;
            color: #5a6dfc;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
    </style>
</head>
<body>

<div class="header">
    <div><i class="fas fa-history me-2"></i>Parent Dashboard - Vaccination History</div>
    <a href="dashboard.php" class="back-home">
        <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
    </a>
</div>

<div class="container">
    <!-- Title with Add Vaccine Button -->
    <div class="title-section">
        <div class="d-flex align-items-center">
            <h1 class="title"><i class="fas fa-syringe me-3" style="color: #5a6dfc;"></i>Vaccination History</h1>
            <span class="stats-badge ms-3">
                <i class="fas fa-flask me-1"></i>Total Vaccines: <?php echo count($vaccines); ?>
            </span>
        </div>
        <button class="add-vaccine-btn" onclick="openModal()">
            <i class="fas fa-plus-circle"></i> Add New Vaccine
        </button>
    </div>

    <!-- Alert Message -->
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'error'; ?>">
            <i class="fas <?php echo $message_type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> fa-lg"></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- Vaccines Grid -->
    <div class="grid">
        <?php foreach ($vaccines as $vaccine): ?>
        <div class="vaccine-card">
            <div class="vaccine-header">
                <span class="vaccine-name">
                    <?php echo htmlspecialchars(is_array($vaccine) ? ($vaccine['vaccine_name'] ?? $vaccine[0] ?? '') : ''); ?>
                </span>
                <span class="vaccine-age">
                    <i class="fas fa-calendar-alt me-1"></i>
                    <?php 
                    $age = is_array($vaccine) ? ($vaccine['scheduled_age'] ?? '') : '';
                    echo htmlspecialchars($age ?: 'Various');
                    ?>
                </span>
            </div>
            
            <?php if (is_array($vaccine) && !empty($vaccine['manufacturer'])): ?>
            <div class="vaccine-manufacturer">
                <i class="fas fa-industry"></i> <?php echo htmlspecialchars($vaccine['manufacturer']); ?>
            </div>
            <?php endif; ?>
            
            <?php if (is_array($vaccine) && !empty($vaccine['dose_number'])): ?>
            <span class="dose-badge">
                <i class="fas fa-syringe me-1"></i> <?php echo $vaccine['dose_number']; ?> Dose(s)
            </span>
            <?php endif; ?>
            
            <!-- Tooltip Description -->
            <div class="vaccine-desc">
                <strong style="color: #5a6dfc; display: block; margin-bottom: 8px;">
                    <i class="fas fa-info-circle me-1"></i>About this vaccine:
                </strong>
                <?php 
                $desc = is_array($vaccine) ? ($vaccine['vaccine_description'] ?? 'Information available.') : '';
                echo htmlspecialchars($desc);
                ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Book Appointment Section -->
    <div class="action-box">
        <h3><i class="fas fa-calendar-check me-2" style="color: #5a6dfc;"></i>Ready for the Next Dose?</h3>
        <p>Apne child ka next vaccination appointment abhi book karein. Safe aur secure vaccination!</p>
        <a href="book_appointment.php" class="btn">
            <i class="fas fa-calendar-plus me-2"></i>Book Your Appointment
        </a>
    </div>
</div>

<!-- ===== ADD VACCINE MODAL FORM ===== -->
<div id="vaccineModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-flask me-2"></i> Add New Vaccine</h3>
            <button class="close-btn" onclick="closeModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="modal-body">
            <form method="POST" action="" id="addVaccineForm">
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-syringe"></i> Vaccine Name *
                    </label>
                    <input type="text" class="form-control" name="vaccine_name" 
                           placeholder="e.g., COVID-19, Hepatitis A, Chickenpox" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-align-left"></i> Vaccine Description *
                    </label>
                    <textarea class="form-control" name="vaccine_description" 
                              placeholder="Write detailed description about this vaccine..." required></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-calendar"></i> Scheduled Age
                        </label>
                        <input type="text" class="form-control" name="scheduled_age" 
                               placeholder="e.g., At Birth, 6 weeks, 9 months">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-industry"></i> Manufacturer
                        </label>
                        <input type="text" class="form-control" name="manufacturer" 
                               placeholder="e.g., Serum Institute, Pfizer">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-syringe"></i> Number of Doses
                        </label>
                        <input type="number" class="form-control" name="dose_number" 
                               min="1" max="10" value="1">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-check-circle"></i> Status
                        </label>
                        <select class="form-control" name="is_active">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
                
                <button type="submit" name="add_vaccine" class="btn-submit">
                    <i class="fas fa-flask me-2"></i> Add Vaccine to System
                </button>
            </form>
        </div>
    </div>
</div>

<script>
// ===== MODAL FUNCTIONS =====
function openModal() {
    document.getElementById('vaccineModal').classList.add('show');
    document.body.style.overflow = 'hidden'; // Prevent background scrolling
}

function closeModal() {
    document.getElementById('vaccineModal').classList.remove('show');
    document.body.style.overflow = 'auto'; // Enable scrolling
}

// Close modal when clicking outside
window.onclick = function(event) {
    let modal = document.getElementById('vaccineModal');
    if (event.target == modal) {
        closeModal();
    }
}

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

// Form validation
document.getElementById('addVaccineForm')?.addEventListener('submit', function(e) {
    let vaccineName = document.querySelector('input[name="vaccine_name"]').value.trim();
    let vaccineDesc = document.querySelector('textarea[name="vaccine_description"]').value.trim();
    
    if (!vaccineName) {
        e.preventDefault();
        alert('❌ Please enter vaccine name');
    } else if (!vaccineDesc) {
        e.preventDefault();
        alert('❌ Please enter vaccine description');
    }
});

// Open modal if URL has #add
if (window.location.hash === '#add') {
    openModal();
}
</script>

</body>
</html>