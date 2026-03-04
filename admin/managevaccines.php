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
    $description = trim($_POST['vaccine_description'] ?? '');
    $scheduled_age = trim($_POST['scheduled_age'] ?? '');
    $manufacturer = trim($_POST['manufacturer'] ?? '');
    $dose_number = intval($_POST['dose_number'] ?? 1);
    $is_active = isset($_POST['is_active']) ? 1 : 1;
    
    if (!empty($vaccine_name) && !empty($description)) {
        try {
            // Check if vaccines table exists with correct structure
            $check_table = $pdo->query("SHOW TABLES LIKE 'vaccines'");
            if ($check_table->rowCount() == 0) {
                // Create table with your database structure
                $pdo->exec("CREATE TABLE IF NOT EXISTS `vaccines` (
                    `vaccine_id` int(11) NOT NULL AUTO_INCREMENT,
                    `vaccine_name` varchar(100) NOT NULL,
                    `vaccine_code` varchar(20) DEFAULT NULL,
                    `description` text DEFAULT NULL,
                    `manufacturer` varchar(100) DEFAULT NULL,
                    `scheduled_age` varchar(50) DEFAULT NULL,
                    `dosage_info` varchar(100) DEFAULT NULL,
                    `storage_requirements` text DEFAULT NULL,
                    `side_effects` text DEFAULT NULL,
                    `is_active` tinyint(1) DEFAULT 1,
                    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                    `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
                    PRIMARY KEY (`vaccine_id`),
                    UNIQUE KEY `vaccine_code` (`vaccine_code`),
                    KEY `idx_vaccine_code` (`vaccine_code`),
                    KEY `idx_is_active` (`is_active`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            }
            
            // Generate vaccine code
            $words = explode(' ', $vaccine_name);
            $code = strtoupper(substr($words[0], 0, 3)) . '-' . rand(10, 99);
            
            // Insert vaccine using your table structure
            $stmt = $pdo->prepare("INSERT INTO vaccines 
                (vaccine_name, vaccine_code, description, manufacturer, scheduled_age, is_active, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
            
            if ($stmt->execute([$vaccine_name, $code, $description, $manufacturer, $scheduled_age, $is_active])) {
                $message = "✅ Vaccine '{$vaccine_name}' added successfully! Code: {$code}";
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
    $vaccines = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table might not exist yet
    $vaccines = [];
}

// If no vaccines in database, show default ones but don't add to database
if (empty($vaccines)) {
    $vaccines = [
        ['vaccine_id' => 0, 'vaccine_name' => 'BCG', 'description' => 'BCG vaccine tuberculosis (TB) se bachao ke liye di jati hai.', 'scheduled_age' => 'At Birth', 'manufacturer' => 'Serum Institute', 'dose_number' => 1],
        ['vaccine_id' => 0, 'vaccine_name' => 'DPT', 'description' => 'DPT diphtheria, pertussis aur tetanus se protection deta hai.', 'scheduled_age' => '6 weeks, 10 weeks, 14 weeks', 'manufacturer' => 'Serum Institute', 'dose_number' => 3],
        ['vaccine_id' => 0, 'vaccine_name' => 'Hepatitis B', 'description' => 'Ye vaccine liver infection se bachata hai.', 'scheduled_age' => 'At Birth, 6,10,14 weeks', 'manufacturer' => 'Biological E', 'dose_number' => 4],
        ['vaccine_id' => 0, 'vaccine_name' => 'IPV', 'description' => 'IPV polio se bachao karta hai.', 'scheduled_age' => '6,10,14 weeks', 'manufacturer' => 'Sanofi', 'dose_number' => 3],
        ['vaccine_id' => 0, 'vaccine_name' => 'Measles', 'description' => 'Measles vaccine khusra virus se bachata hai.', 'scheduled_age' => '9 months', 'manufacturer' => 'Serum Institute', 'dose_number' => 1],
        ['vaccine_id' => 0, 'vaccine_name' => 'OPV', 'description' => 'OPV oral polio vaccine hai.', 'scheduled_age' => 'At Birth, 6,10,14 weeks', 'manufacturer' => 'Bharat Biotech', 'dose_number' => 4],
        ['vaccine_id' => 0, 'vaccine_name' => 'PCV', 'description' => 'PCV pneumonia se bachata hai.', 'scheduled_age' => '6 weeks, 10 weeks, 14 weeks, Booster', 'manufacturer' => 'Pfizer', 'dose_number' => 4],
        ['vaccine_id' => 0, 'vaccine_name' => 'Pentavalent', 'description' => 'Ye vaccine 5 diseases se ek sath bachata hai.', 'scheduled_age' => '6,10,14 weeks', 'manufacturer' => 'Serum Institute', 'dose_number' => 3],
        ['vaccine_id' => 0, 'vaccine_name' => 'Vitamin A', 'description' => 'Vitamin A immunity strong karta hai.', 'scheduled_age' => '9 months, 16 months, 24 months', 'manufacturer' => 'Various', 'dose_number' => 3],
        ['vaccine_id' => 0, 'vaccine_name' => 'Rotavirus', 'description' => 'Rotavirus severe diarrhea se bachata hai.', 'scheduled_age' => '6,10,14 weeks', 'manufacturer' => 'Bharat Biotech', 'dose_number' => 3],
        ['vaccine_id' => 0, 'vaccine_name' => 'MMR', 'description' => 'MMR measles, mumps aur rubella se protection deta hai.', 'scheduled_age' => '9-12 months, 15 months', 'manufacturer' => 'Serum Institute', 'dose_number' => 2],
        ['vaccine_id' => 0, 'vaccine_name' => 'Typhoid', 'description' => 'Typhoid vaccine bukhar se bachata hai.', 'scheduled_age' => '2 years', 'manufacturer' => 'Bharat Biotech', 'dose_number' => 1]
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vaccination History - Parent Dashboard</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Inter',Arial,sans-serif; background:#f0f4ff; color:#1a1a2e; min-height:100vh; }

        /* NAVBAR */
        .header {
            background:#ffffff; border-bottom:2px solid #e8eeff;
            padding:0 35px; display:flex; justify-content:space-between;
            align-items:center; height:68px;
            box-shadow:0 2px 16px rgba(59,130,246,0.08);
            position:sticky; top:0; z-index:100;
            font-size:20px; font-weight:700; color:#1d4ed8; letter-spacing:-0.3px;
        }
        .back-home {
            color:#4b6cb7; text-decoration:none; padding:8px 16px;
            border-radius:8px; font-size:13.5px; font-weight:500;
            transition:all 0.2s; display:flex; align-items:center; gap:6px;
        }
        .back-home:hover { background:#eff6ff; color:#1d4ed8; }

        /* LAYOUT */
        .container { max-width:1400px; margin:32px auto; padding:0 24px; }

        /* PAGE BANNER */
        .page-banner {
            background:linear-gradient(135deg,#1d4ed8 0%,#3b82f6 60%,#60a5fa 100%);
            border-radius:18px; padding:32px 36px; margin-bottom:28px;
            color:white; box-shadow:0 8px 32px rgba(59,130,246,0.3);
            position:relative; overflow:hidden;
            display:flex; justify-content:space-between; align-items:center;
        }
        .page-banner::before { content:''; position:absolute; top:-40px; right:-40px; width:200px; height:200px; background:rgba(255,255,255,0.08); border-radius:50%; }
        .page-banner::after  { content:''; position:absolute; bottom:-60px; right:80px; width:160px; height:160px; background:rgba(255,255,255,0.05); border-radius:50%; }
        .page-banner-text { position:relative; z-index:1; }
        .page-banner-text h1 { font-size:26px; font-weight:700; margin-bottom:6px; }
        .page-banner-text p  { font-size:14px; opacity:0.85; }
        .page-banner-right { position:relative; z-index:1; display:flex; align-items:center; gap:12px; flex-wrap:wrap; }

        .stats-badge {
            background:rgba(255,255,255,0.2); padding:7px 16px; border-radius:20px;
            font-size:13px; font-weight:600; color:white;
            border:1px solid rgba(255,255,255,0.3);
        }

        /* ADD BUTTON */
        .add-vaccine-btn {
            background:#ffffff; color:#1d4ed8; border:none;
            padding:11px 20px; font-size:13.5px; font-weight:600;
            border-radius:10px; cursor:pointer;
            display:flex; align-items:center; gap:7px;
            transition:all 0.22s; font-family:'Inter',Arial,sans-serif;
            box-shadow:0 4px 14px rgba(0,0,0,0.1);
        }
        .add-vaccine-btn:hover { background:#eff6ff; transform:translateY(-2px); box-shadow:0 8px 20px rgba(0,0,0,0.15); }

        /* ALERTS */
        .alert { padding:14px 18px; border-radius:10px; margin-bottom:24px; display:flex; align-items:center; gap:10px; font-size:14px; font-weight:500; animation:slideDown 0.3s ease; }
        .alert-success { background:#dcfce7; color:#166534; border-left:4px solid #22c55e; }
        .alert-error   { background:#fee2e2; color:#dc2626; border-left:4px solid #ef4444; }
        @keyframes slideDown { from{opacity:0;transform:translateY(-12px)} to{opacity:1;transform:translateY(0)} }

        /* GRID */
        .grid { display:grid; grid-template-columns:repeat(3,1fr); gap:20px; margin-bottom:28px; }

        .vaccine-card {
            background:#ffffff; border-radius:16px; padding:22px 24px;
            box-shadow:0 2px 12px rgba(59,130,246,0.07);
            border:1px solid #e8eeff; border-left:4px solid #3b82f6;
            position:relative; cursor:pointer;
            transition:transform 0.2s,box-shadow 0.2s;
            animation:fadeIn 0.4s ease both;
        }
        .vaccine-card:hover { transform:translateY(-4px); box-shadow:0 10px 28px rgba(59,130,246,0.13); }

        .vaccine-header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:12px; gap:8px; }
        .vaccine-name { font-size:16px; font-weight:700; color:#1d4ed8; }
        .vaccine-code { font-family:monospace; background:#f1f5f9; padding:2px 7px; border-radius:4px; font-size:11px; color:#6b7280; margin-left:6px; }
        .vaccine-age { background:#dbeafe; padding:4px 12px; border-radius:20px; font-size:11.5px; font-weight:600; color:#1d4ed8; white-space:nowrap; flex-shrink:0; }
        .vaccine-manufacturer { font-size:12.5px; color:#6b7280; margin-top:6px; display:flex; align-items:center; gap:5px; padding-bottom:10px; border-bottom:1px solid #f1f5ff; }

        /* Tooltip */
        .vaccine-desc {
            position:absolute; bottom:110%; left:50%; transform:translateX(-50%);
            width:260px; background:#ffffff; padding:16px; font-size:13px;
            color:#374151; line-height:1.6; border-radius:12px;
            box-shadow:0 12px 32px rgba(59,130,246,0.15);
            opacity:0; visibility:hidden; transition:0.25s; z-index:20;
            border:1px solid #e8eeff;
        }
        .vaccine-desc::after { content:""; position:absolute; top:100%; left:50%; transform:translateX(-50%); border-width:8px; border-style:solid; border-color:white transparent transparent transparent; }
        .vaccine-desc strong { color:#1d4ed8; display:block; margin-bottom:6px; font-size:12px; }
        .vaccine-card:hover .vaccine-desc { opacity:1; visibility:visible; }

        .dose-badge { background:#dbeafe; color:#1d4ed8; padding:3px 12px; border-radius:20px; font-size:11px; font-weight:600; display:inline-block; margin-top:8px; }

        /* ACTION BOX */
        .action-box { margin-top:28px; text-align:center; background:#ffffff; padding:36px; border-radius:16px; box-shadow:0 2px 12px rgba(59,130,246,0.07); border:1px solid #e8eeff; }
        .action-box h3 { margin-bottom:8px; color:#1a1a2e; font-size:20px; font-weight:700; }
        .action-box p  { color:#6b7280; margin-bottom:20px; font-size:14px; }

        .btn {
            display:inline-flex; align-items:center; gap:8px;
            background:linear-gradient(135deg,#3b82f6,#1d4ed8);
            color:white; text-decoration:none; padding:12px 28px;
            font-size:14px; border-radius:10px; font-weight:600;
            transition:all 0.22s; box-shadow:0 4px 14px rgba(29,78,216,0.2);
            border:none; cursor:pointer; font-family:'Inter',Arial,sans-serif;
        }
        .btn:hover { transform:translateY(-2px); box-shadow:0 8px 22px rgba(29,78,216,0.3); color:white; }

        /* MODAL */
        .modal { display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.45); z-index:1000; justify-content:center; align-items:center; backdrop-filter:blur(6px); }
        .modal.show { display:flex; }
        .modal-content { background:white; width:90%; max-width:580px; border-radius:18px; box-shadow:0 24px 60px rgba(59,130,246,0.2); border:1px solid #e8eeff; animation:slideIn 0.35s ease; max-height:90vh; overflow-y:auto; }
        @keyframes slideIn { from{opacity:0;transform:translateY(-40px)} to{opacity:1;transform:translateY(0)} }

        .modal-header { background:linear-gradient(135deg,#1d4ed8,#3b82f6); color:white; padding:22px 28px; border-radius:18px 18px 0 0; display:flex; justify-content:space-between; align-items:center; }
        .modal-header h3 { font-size:18px; font-weight:700; margin:0; }
        .close-btn { background:rgba(255,255,255,0.2); border:none; color:white; width:36px; height:36px; border-radius:50%; font-size:20px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:all 0.2s; line-height:1; }
        .close-btn:hover { background:rgba(255,255,255,0.3); transform:rotate(90deg); }

        .modal-body { padding:28px; background:#f8faff; border-radius:0 0 18px 18px; }
        .form-group { margin-bottom:18px; }
        .form-label { display:block; font-weight:600; color:#374151; margin-bottom:6px; font-size:13.5px; }
        .form-control { width:100%; padding:10px 14px; border:1.5px solid #e8eeff; border-radius:8px; font-size:14px; font-family:'Inter',Arial,sans-serif; background:#ffffff; color:#1a1a2e; transition:all 0.2s; }
        .form-control:focus { border-color:#3b82f6; outline:none; box-shadow:0 0 0 3px rgba(59,130,246,0.1); }
        textarea.form-control { min-height:90px; resize:vertical; }
        .form-row { display:grid; grid-template-columns:1fr 1fr; gap:16px; }

        .btn-submit {
            background:linear-gradient(135deg,#3b82f6,#1d4ed8); color:white;
            border:none; padding:12px 24px; font-size:15px; font-weight:600;
            border-radius:10px; width:100%; cursor:pointer;
            display:flex; align-items:center; justify-content:center; gap:8px;
            transition:all 0.22s; margin-top:12px;
            box-shadow:0 4px 14px rgba(29,78,216,0.2);
            font-family:'Inter',Arial,sans-serif;
        }
        .btn-submit:hover { transform:translateY(-2px); box-shadow:0 8px 22px rgba(29,78,216,0.3); }

        @keyframes fadeIn { from{opacity:0;transform:translateY(10px)} to{opacity:1;transform:translateY(0)} }
        .vaccine-card:nth-child(1)  { animation-delay:0.05s }
        .vaccine-card:nth-child(2)  { animation-delay:0.10s }
        .vaccine-card:nth-child(3)  { animation-delay:0.15s }
        .vaccine-card:nth-child(4)  { animation-delay:0.20s }
        .vaccine-card:nth-child(5)  { animation-delay:0.25s }
        .vaccine-card:nth-child(6)  { animation-delay:0.30s }
        .vaccine-card:nth-child(7)  { animation-delay:0.35s }
        .vaccine-card:nth-child(8)  { animation-delay:0.40s }
        .vaccine-card:nth-child(9)  { animation-delay:0.45s }
        .vaccine-card:nth-child(10) { animation-delay:0.50s }
        .vaccine-card:nth-child(11) { animation-delay:0.55s }
        .vaccine-card:nth-child(12) { animation-delay:0.60s }

        @media(max-width:1100px) { .grid { grid-template-columns:repeat(2,1fr) } }
        @media(max-width:700px) {
            .grid { grid-template-columns:1fr }
            .form-row { grid-template-columns:1fr }
            .header { padding:0 16px; font-size:16px; height:auto; padding-top:12px; padding-bottom:12px; }
            .container { padding:0 14px }
            .page-banner { flex-direction:column; gap:16px }
            .page-banner-right { flex-wrap:wrap }
        }
        @media(max-width:480px) { .vaccine-desc { width:200px } }
         /* ── NAVBAR ── */
        .admin-navbar {
            background:#ffffff; border-bottom:2px solid #e8eeff;
            padding:0 35px; display:flex; justify-content:space-between;
            align-items:center; height:68px;
            box-shadow:0 2px 16px rgba(59,130,246,0.08);
            position:sticky; top:0; z-index:100;
        }
        .admin-navbar .logo { display:flex; align-items:center; gap:10px; }
        .admin-navbar .logo-icon {
            width:40px; height:40px;
            background:linear-gradient(135deg,#3b82f6,#1d4ed8);
            border-radius:10px; display:flex; align-items:center;
            justify-content:center; font-size:20px;
        }
        .admin-navbar .logo h2 { font-size:20px; font-weight:700; color:#1d4ed8; letter-spacing:-0.3px; }
        .nav-links { display:flex; align-items:center; gap:6px; flex-wrap:wrap; }
        .nav-links a {
            color:#4b6cb7; text-decoration:none; padding:8px 14px;
            border-radius:8px; font-size:13.5px; font-weight:500;
            transition:all 0.2s; display:flex; align-items:center; gap:6px;
        }
        .nav-links a:hover { background:#eff6ff; color:#1d4ed8; }
        .nav-links a.active { background:#dbeafe; color:#1d4ed8; font-weight:600; }
        .nav-links a.logout { background:#fee2e2; color:#dc2626; }
        .nav-links a.logout:hover { background:#fecaca; }
    </style>
</head>
<body>

  <!-- Admin Navbar -->
    <nav class="admin-navbar">
        <div class="logo">
            <div class="logo-icon">🛡️</div>
            <h2>Admin Panel</h2>
        </div>
        <div class="nav-links">
            <a href="dashboard.php">Dashboard</a>
            <a href="manage_children.php"> Children</a>
            <a href="manage_hospitals.php"> Hospitals</a>
            <a href="appointment_requests.php"> Requests</a>
            <a href="managevaccines.php"> Vaccines</a>
            <a href="bookingdetail.php">Bookings</a>
            <a href="vaccination_reports.php" class="active"> Reports</a>
            <a href="system_settings.php"> Settings</a>
            <a href="../logout.php" class="logout">Logout</a>
        </div>
    </nav>

<div class="container">

    <!-- Page Banner -->
    <div class="page-banner">
        <div class="page-banner-text">
            <h1>💉 Manage Vaccines</h1>
            <p>View, add and manage all vaccines in the system</p>
        </div>
        <div class="page-banner-right">
            <span class="stats-badge">🧪 Total: <?php echo count($vaccines); ?></span>
            <button class="add-vaccine-btn" onclick="openModal()">＋ Add New Vaccine</button>
        </div>
    </div>

    <!-- Alert Message -->
    <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'error'; ?>">
            <?php echo $message_type === 'success' ? '✅' : '❌'; ?>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <!-- Vaccines Grid -->
    <div class="grid">
        <?php foreach ($vaccines as $vaccine): ?>
        <div class="vaccine-card">
            <div class="vaccine-header">
                <span class="vaccine-name">
                    <?php echo htmlspecialchars($vaccine['vaccine_name'] ?? ''); ?>
                    <?php if (!empty($vaccine['vaccine_code'])): ?>
                        <span class="vaccine-code"><?php echo $vaccine['vaccine_code']; ?></span>
                    <?php endif; ?>
                </span>
                <span class="vaccine-age">
                    📅 <?php echo htmlspecialchars($vaccine['scheduled_age'] ?? 'Various'); ?>
                </span>
            </div>

            <?php if (!empty($vaccine['manufacturer'])): ?>
            <div class="vaccine-manufacturer">
                🏭 <?php echo htmlspecialchars($vaccine['manufacturer']); ?>
            </div>
            <?php endif; ?>

            <!-- Tooltip Description -->
            <div class="vaccine-desc">
                <strong>ℹ️ About this vaccine:</strong>
                <?php echo htmlspecialchars($vaccine['description'] ?? 'Information available.'); ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

   

<!-- ===== ADD VACCINE MODAL FORM ===== -->
<div id="vaccineModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>🧪 Add New Vaccine</h3>
            <button class="close-btn" onclick="closeModal()">×</button>
        </div>
        
        <div class="modal-body">
            <form method="POST" action="" id="addVaccineForm">
                <div class="form-group">
                    <label class="form-label">💉 Vaccine Name *</label>
                    <input type="text" class="form-control" name="vaccine_name" 
                           placeholder="e.g., COVID-19, Hepatitis A, Chickenpox" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">📝 Vaccine Description *</label>
                    <textarea class="form-control" name="vaccine_description" 
                              placeholder="Write detailed description about this vaccine..." required></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">📅 Scheduled Age</label>
                        <input type="text" class="form-control" name="scheduled_age" 
                               placeholder="e.g., At Birth, 6 weeks, 9 months">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">🏭 Manufacturer</label>
                        <input type="text" class="form-control" name="manufacturer" 
                               placeholder="e.g., Serum Institute, Pfizer">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">💊 Number of Doses</label>
                        <input type="number" class="form-control" name="dose_number" 
                               min="1" max="10" value="1">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">✅ Status</label>
                        <select class="form-control" name="is_active">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
                
                <button type="submit" name="add_vaccine" class="btn-submit">🧪 Add Vaccine to System</button>
            </form>
        </div>
    </div>
</div>

<script>
// ===== MODAL FUNCTIONS =====
function openModal() {
    document.getElementById('vaccineModal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeModal() {
    document.getElementById('vaccineModal').classList.remove('show');
    document.body.style.overflow = 'auto';
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
            if (alert.parentNode) {
                alert.remove();
            }
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