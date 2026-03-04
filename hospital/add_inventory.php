<?php
session_start();
if(!isset($_SESSION["logged_in"]) || $_SESSION["user_type"] != "hospital"){
    header("Location: ../login.php");
    exit();
}

include("../dbconnection.php");

$user_id = $_SESSION["user_id"];
$success_msg = "";
$error_msg = "";

// Get hospital data
$query_hospital = "SELECT * FROM hospitals WHERE user_id = '$user_id'";
$result_hospital = mysqli_query($connection, $query_hospital);
$hospital = mysqli_fetch_assoc($result_hospital);
$hospital_id = $hospital['hospital_id'] ?? 0;
$is_verified = $hospital['is_verified'] ?? 0;
$is_active = $hospital['is_active'] ?? 0;

// Get active vaccines for dropdown
$vaccines_query = "SELECT vaccine_id, vaccine_name, vaccine_code, manufacturer, description 
                   FROM vaccines WHERE is_active = 1 ORDER BY vaccine_name";
$vaccines_result = mysqli_query($connection, $vaccines_query);

// Handle form submission
if($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    $vaccine_id = intval($_POST['vaccine_id']);
    $quantity = intval($_POST['quantity']);
    $batch_number = mysqli_real_escape_string($connection, trim(strtoupper($_POST['batch_number'])));
    $expiry_date = mysqli_real_escape_string($connection, $_POST['expiry_date']);
    $last_restocked_date = mysqli_real_escape_string($connection, $_POST['last_restocked_date'] ?? date('Y-m-d'));
    
    $errors = [];
    
    // Validation
    if($vaccine_id <= 0) {
        $errors[] = "Please select a vaccine.";
    }
    
    if($quantity <= 0) {
        $errors[] = "Quantity must be greater than 0.";
    }
    
    if(empty($batch_number)) {
        $errors[] = "Batch number is required.";
    } elseif(!preg_match('/^[A-Za-z0-9\-]+$/', $batch_number)) {
        $errors[] = "Batch number me sirf letters, numbers, aur hyphens allowed hain.";
    }
    
    if(empty($expiry_date)) {
        $errors[] = "Expiry date is required.";
    } elseif($expiry_date < date('Y-m-d')) {
        $errors[] = "Expiry date future mein honi chahiye.";
    }
    
    // Check for duplicate batch number for this hospital
    if(empty($errors)) {
        $check_query = mysqli_query($connection,
            "SELECT inventory_id FROM hospital_vaccine_inventory 
             WHERE hospital_id = '$hospital_id' AND batch_number = '$batch_number'");
        
        if(mysqli_num_rows($check_query) > 0) {
            $errors[] = "Batch number already exists in your inventory.";
        }
    }
    
    // If no errors, insert into database
    if(empty($errors)) {
        
        $insert_query = "INSERT INTO hospital_vaccine_inventory 
            (hospital_id, vaccine_id, quantity_available, batch_number, expiry_date, last_restocked_date, is_available, created_at, updated_at) 
            VALUES 
            ('$hospital_id', '$vaccine_id', '$quantity', '$batch_number', '$expiry_date', '$last_restocked_date', '1', NOW(), NOW())";
        
        if(mysqli_query($connection, $insert_query)) {
            $inventory_id = mysqli_insert_id($connection);
            
            // Log to audit_logs
            $action_desc = "Added new vaccine stock: $batch_number (Qty: $quantity)";
            mysqli_query($connection,
                "INSERT INTO audit_logs (user_id, action_type, table_name, record_id, action_description, created_at)
                 VALUES ('$user_id', 'ADD', 'hospital_vaccine_inventory', '$inventory_id', '$action_desc', NOW())");
            
            // Success message with SweetAlert
            $_SESSION['msg'] = '✅ Vaccine stock added successfully!';
            $_SESSION['msg_type'] = 'success';
            header("Location: vaccine_inventory.php");
            exit();
        } else {
            $error_msg = "❌ Error adding inventory: " . mysqli_error($connection);
        }
    } else {
        $error_msg = implode("<br>", $errors);
    }
}

// Get vaccine details for JS
$vaccine_details = [];
mysqli_data_seek($vaccines_result, 0);
while($v = mysqli_fetch_assoc($vaccines_result)) {
    $vaccine_details[$v['vaccine_id']] = [
        'name' => $v['vaccine_name'],
        'code' => $v['vaccine_code'],
        'manufacturer' => $v['manufacturer'],
        'description' => $v['description']
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Inventory — VacciCare</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f0f4ff;
            color: #0a1628;
            min-height: 100vh;
        }

        /* Navbar */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 200;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(18px);
            border-bottom: 1px solid #e2e8f0;
            padding: 0 40px;
            height: 68px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 12px rgba(37,99,235,0.08);
        }

        .nav-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 800;
            font-size: 18px;
            color: #1a3a6e;
            text-decoration: none;
        }

        .nav-logo .logo-icon {
            width: 40px;
            height: 40px;
            background: #2563eb;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
            box-shadow: 0 4px 14px rgba(37,99,235,0.35);
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 8px 14px;
            border-radius: 8px;
            color: #334155;
            text-decoration: none;
            font-size: 13.5px;
            font-weight: 600;
            transition: all 0.2s;
        }

        .nav-link:hover {
            background: #eff6ff;
            color: #2563eb;
        }

        .nav-link.active {
            background: #eff6ff;
            color: #2563eb;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .nav-hospital-chip {
            display: flex;
            align-items: center;
            gap: 8px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 7px 14px;
            font-size: 13px;
            font-weight: 600;
            color: #0a1628;
        }

        .nav-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            display: inline-block;
        }

        .dot-green {
            background: #4ade80;
        }

        .dot-yellow {
            background: #facc15;
        }

        .dot-red {
            background: #f87171;
        }

        .nav-logout {
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 8px 16px;
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
            border-radius: 9px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s;
        }

        .nav-logout:hover {
            background: #dc2626;
            color: white;
        }

        .hamburger {
            display: none;
            flex-direction: column;
            gap: 5px;
            cursor: pointer;
            padding: 6px;
        }

        .hamburger span {
            width: 22px;
            height: 2px;
            background: #334155;
            border-radius: 2px;
        }

        .mobile-menu {
            display: none;
            position: fixed;
            top: 68px;
            left: 0;
            right: 0;
            background: white;
            border-bottom: 1px solid #e2e8f0;
            padding: 12px 20px;
            z-index: 199;
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }

        .mobile-menu.open {
            display: block;
        }

        .mobile-menu .nav-link {
            display: flex;
            padding: 10px 14px;
            margin-bottom: 4px;
        }

        .main {
            padding-top: 68px;
            min-height: 100vh;
        }

        .verify-banner {
            margin: 20px 32px 0;
            padding: 13px 20px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            font-weight: 500;
        }

        .verify-banner.pending {
            background: #fef9c3;
            border: 1px solid #fde68a;
            color: #92400e;
        }

        .verify-banner.verified {
            background: #dcfce7;
            border: 1px solid #bbf7d0;
            color: #166534;
        }

        .verify-banner.inactive {
            background: #fee2e2;
            border: 1px solid #fecaca;
            color: #991b1b;
        }

        .content {
            padding: 24px 32px 48px;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .page-header h1 {
            font-family: 'Playfair Display', serif;
            font-size: 28px;
            font-weight: 800;
            color: #0a1628;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-header h1 a {
            font-size: 14px;
            background: #f1f5f9;
            color: #334155;
            padding: 6px 14px;
            border-radius: 30px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
        }

        .page-header h1 a:hover {
            background: #2563eb;
            color: white;
        }

        .form-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 20px;
            padding: 36px;
            box-shadow: 0 8px 24px rgba(37,99,235,0.08);
            max-width: 700px;
            margin: 0 auto;
        }

        .form-section {
            margin-bottom: 32px;
            padding-bottom: 24px;
            border-bottom: 1px solid #f1f5f9;
        }

        .form-section:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: #1a3a6e;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-title span {
            font-size: 20px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 8px;
        }

        .form-label .required {
            color: #dc2626;
            margin-left: 2px;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1.5px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: #0a1628;
            background: #f8fafc;
            transition: all 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: #3b82f6;
            background: white;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
        }

        select.form-control {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            padding-right: 40px;
        }

        .input-hint {
            font-size: 11px;
            color: #94a3b8;
            margin-top: 6px;
        }

        .vaccine-info-box {
            background: linear-gradient(135deg, #eff6ff, #dbeafe);
            border: 1px solid #dbeafe;
            border-radius: 10px;
            padding: 16px 20px;
            margin-top: 16px;
            display: none;
        }

        .vaccine-info-box.show {
            display: block;
        }

        .vaccine-info-title {
            font-size: 14px;
            font-weight: 700;
            color: #1a3a6e;
            margin-bottom: 8px;
        }

        .vaccine-info-code {
            font-size: 13px;
            color: #334155;
            margin-bottom: 4px;
        }

        .vaccine-info-mfr {
            font-size: 12px;
            color: #64748b;
            margin-bottom: 4px;
        }

        .vaccine-info-desc {
            font-size: 12px;
            color: #4b5563;
            font-style: italic;
        }

        .form-actions {
            display: flex;
            gap: 16px;
            margin-top: 32px;
            align-items: center;
        }

        .btn-submit {
            flex: 1;
            padding: 14px 28px;
            background: linear-gradient(135deg, #1d4ed8, #3b82f6);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
            font-family: 'Plus Jakarta Sans', sans-serif;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 14px rgba(37,99,235,0.3);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(37,99,235,0.4);
        }

        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .btn-cancel {
            padding: 14px 28px;
            background: #f1f5f9;
            color: #334155;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-cancel:hover {
            background: #e2e8f0;
        }

        .info-box {
            background: #eff6ff;
            border: 1px solid #dbeafe;
            border-radius: 12px;
            padding: 16px 20px;
            margin: 24px 0 0;
            font-size: 13px;
            color: #1a3a6e;
        }

        .info-box strong {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .info-box ul {
            list-style: none;
            padding-left: 0;
        }

        .info-box li {
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Custom Alert */
        .custom-alert {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            max-width: 400px;
            padding: 16px 20px;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            display: flex;
            align-items: center;
            gap: 12px;
            transform: translateX(120%);
            transition: transform 0.3s ease;
        }

        .custom-alert.show {
            transform: translateX(0);
        }

        .custom-alert.success {
            background: #dcfce7;
            border-left: 4px solid #16a34a;
            color: #166534;
        }

        .custom-alert.error {
            background: #fee2e2;
            border-left: 4px solid #dc2626;
            color: #991b1b;
        }

        .custom-alert.warning {
            background: #fef9c3;
            border-left: 4px solid #ca8a04;
            color: #854d0e;
        }

        .custom-alert.info {
            background: #dbeafe;
            border-left: 4px solid #2563eb;
            color: #1e3a8a;
        }

        .custom-alert .close-btn {
            margin-left: auto;
            cursor: pointer;
            font-size: 18px;
            opacity: 0.7;
        }

        .custom-alert .close-btn:hover {
            opacity: 1;
        }

        @media (max-width: 860px) {
            .nav-links {
                display: none;
            }

            .hamburger {
                display: flex;
            }

            .nav-hospital-chip {
                display: none;
            }
        }

        @media (max-width: 640px) {
            .navbar {
                padding: 0 20px;
            }

            .content {
                padding: 16px 20px 32px;
            }

            .form-card {
                padding: 24px 20px;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn-submit,
            .btn-cancel {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <!-- Custom Alert Container -->
    <div id="customAlert" class="custom-alert" style="display: none;">
        <span id="alertIcon">✅</span>
        <span id="alertMessage"></span>
        <span class="close-btn" onclick="hideAlert()">✖️</span>
    </div>

    <!-- Navbar -->
    <nav class="navbar">
        <a href="dashboard.php" class="nav-logo">
          
            Hospital_Panel
        </a>
        <div class="nav-links">
            <a href="dashboard.php" class="nav-link">
                Dashboard
            </a>
            <a href="appointment_requests.php" class="nav-link">
                 Requests
            </a>
            <a href="todays_schedule.php" class="nav-link">
           Today
            </a>
            <a href="vaccination_bookings.php" class="nav-link">
               Bookings
            </a>
            <a href="vaccine_inventory.php" class="nav-link active">
                 Inventory
            </a>
            <a href="doctors.php" class="nav-link">
                Doctors
            </a>
            <a href="vaccination_records.php" class="nav-link">
                 Records
            </a>
            <a href="my_profile.php" class="nav-link">
              Profile
            </a>
        </div>
        <div class="nav-right">
            <div class="nav-hospital-chip">
                <span class="nav-dot <?php echo ($is_verified && $is_active) ? 'dot-green' : ($is_verified ? 'dot-red' : 'dot-yellow'); ?>"></span>
                <?php echo htmlspecialchars($hospital['hospital_name'] ?? 'Hospital'); ?>
            </div>
            <a href="../logout.php" class="nav-logout">
                <span>🚪</span> Logout
            </a>
            <div class="hamburger" onclick="toggleMenu()">
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
    </nav>

    <!-- Mobile Menu -->
    <div class="mobile-menu" id="mobileMenu">
        <a href="dashboard.php" class="nav-link">🏠 Dashboard</a>
        <a href="appointment_requests.php" class="nav-link">📋 Requests</a>
        <a href="todays_schedule.php" class="nav-link">📅 Today</a>
        <a href="vaccination_bookings.php" class="nav-link">💉 Bookings</a>
        <a href="vaccine_inventory.php" class="nav-link active">🧪 Inventory</a>
        <a href="doctors.php" class="nav-link">👨‍⚕️ Doctors</a>
        <a href="vaccination_records.php" class="nav-link">📁 Records</a>
        <a href="my_profile.php" class="nav-link">🏥 Profile</a>
        <a href="../logout.php" class="nav-logout" style="margin-top: 8px;">🚪 Logout</a>
    </div>

    <div class="main">
        <!-- Verification Banner -->
        <?php if (!$is_verified): ?>
            <div class="verify-banner pending">
                <span>⏳</span>
                <strong>Pending Verification:</strong> Admin verification ka wait kar raha hai.
            </div>
        <?php elseif (!$is_active): ?>
            <div class="verify-banner inactive">
                <span>🚫</span>
                <strong>Account Inactive:</strong> Admin se rabta karein.
            </div>
        <?php else: ?>
            <div class="verify-banner verified">
                <span>✅</span>
                <strong>Verified Hospital:</strong> Account verified aur active hai.
            </div>
        <?php endif; ?>

        <div class="content">
            <!-- Page Header -->
            <div class="page-header">
                <h1>
                    <span>➕</span> Add Vaccine Stock
                    <a href="vaccine_inventory.php">← Back to Inventory</a>
                </h1>
            </div>

            <!-- Form Card -->
            <div class="form-card">
                <form method="POST" action="" id="inventoryForm" onsubmit="return validateForm()">
                    <!-- Vaccine Selection -->
                    <div class="form-section">
                        <div class="section-title">
                            <span>💉</span> Vaccine Information
                        </div>

                        <div class="form-group full-width">
                            <label class="form-label">
                                Select Vaccine <span class="required">*</span>
                            </label>
                            <select name="vaccine_id" id="vaccine_id" class="form-control" required
                                onchange="showVaccineInfo()">
                                <option value="">-- Choose Vaccine --</option>
                                <?php
                                mysqli_data_seek($vaccines_result, 0);
                                while ($vaccine = mysqli_fetch_assoc($vaccines_result)):
                                    $selected = (isset($_POST['vaccine_id']) && $_POST['vaccine_id'] == $vaccine['vaccine_id']) ? 'selected' : '';
                                ?>
                                    <option value="<?php echo $vaccine['vaccine_id']; ?>" <?php echo $selected; ?>>
                                        <?php echo htmlspecialchars($vaccine['vaccine_name']); ?>
                                        (<?php echo $vaccine['vaccine_code']; ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <!-- Vaccine Info Display -->
                        <div class="vaccine-info-box" id="vaccineInfoBox">
                            <div class="vaccine-info-title" id="infoVaccineName"></div>
                            <div class="vaccine-info-code" id="infoVaccineCode"></div>
                            <div class="vaccine-info-mfr" id="infoManufacturer"></div>
                            <div class="vaccine-info-desc" id="infoDescription"></div>
                        </div>
                    </div>

                    <!-- Stock Details -->
                    <div class="form-section">
                        <div class="section-title">
                            <span>📦</span> Stock Details
                        </div>

                        <div class="form-grid">
                            <div class="form-group">
                                <label class="form-label">
                                    Batch Number <span class="required">*</span>
                                </label>
                                <input type="text" name="batch_number" id="batch_number" class="form-control"
                                    placeholder="e.g., BATCH2025-001" required
                                    value="<?php echo isset($_POST['batch_number']) ? htmlspecialchars($_POST['batch_number']) : ''; ?>">
                                <div class="input-hint">Unique batch identifier (auto-capitalized)</div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    Quantity (Doses) <span class="required">*</span>
                                </label>
                                <input type="number" name="quantity" id="quantity" class="form-control" min="1" required
                                    placeholder="e.g., 100"
                                    value="<?php echo isset($_POST['quantity']) ? $_POST['quantity'] : ''; ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    Expiry Date <span class="required">*</span>
                                </label>
                                <input type="date" name="expiry_date" id="expiry_date" class="form-control"
                                    min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required
                                    value="<?php echo isset($_POST['expiry_date']) ? $_POST['expiry_date'] : ''; ?>">
                                <div class="input-hint">Vaccine expiry date</div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    Last Restocked
                                </label>
                                <input type="date" name="last_restocked_date" class="form-control"
                                    value="<?php echo isset($_POST['last_restocked_date']) ? $_POST['last_restocked_date'] : date('Y-m-d'); ?>"
                                    max="<?php echo date('Y-m-d'); ?>">
                                <div class="input-hint">Default is today</div>
                            </div>
                        </div>
                    </div>

                    <!-- Info Box -->
                    <div class="info-box">
                        <strong>📌 Important Information:</strong>
                        <ul>
                            <li>✅ <strong>Batch Number</strong> - Unique identifier for this vaccine batch</li>
                            <li>✅ <strong>Quantity</strong> - Total number of doses in this batch</li>
                            <li>✅ <strong>Expiry Date</strong> - Ensure vaccine is not expired</li>
                            <li>✅ <strong>Auto Available</strong> - New stock automatically marked as available</li>
                            <li>✅ <strong>Audit Log</strong> - All additions are logged in audit trail</li>
                            <li>⚠️ Duplicate batch numbers are not allowed</li>
                        </ul>
                    </div>

                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="submit" name="add_inventory" value="1" class="btn-submit" id="submitBtn">
                            <span>💾</span> Save Inventory
                        </button>
                        <a href="vaccine_inventory.php" class="btn-cancel">
                            <span>✖️</span> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Vaccine data from PHP
        const vaccineData = <?php echo json_encode($vaccine_details); ?>;

        // Show error message if exists
        <?php if ($error_msg != ''): ?>
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            html: '<?php echo addslashes($error_msg); ?>',
            confirmButtonColor: '#2563eb',
            confirmButtonText: 'OK'
        });
        <?php endif; ?>

        // Toggle mobile menu
        function toggleMenu() {
            document.getElementById('mobileMenu').classList.toggle('open');
        }

        // Show vaccine info when selected
        function showVaccineInfo() {
            const vaccineId = document.getElementById('vaccine_id').value;
            const infoBox = document.getElementById('vaccineInfoBox');

            if (vaccineId && vaccineData[vaccineId]) {
                const v = vaccineData[vaccineId];
                document.getElementById('infoVaccineName').innerHTML = '💉 ' + v.name;
                document.getElementById('infoVaccineCode').innerHTML = '🔖 Code: ' + (v.code || 'N/A');
                document.getElementById('infoManufacturer').innerHTML = v.manufacturer ? '🏭 Manufacturer: ' + v.manufacturer : '';
                document.getElementById('infoDescription').innerHTML = v.description ? '📝 ' + v.description : '';
                infoBox.classList.add('show');
            } else {
                infoBox.classList.remove('show');
            }
        }

        // Auto-capitalize batch number
        document.getElementById('batch_number').addEventListener('input', function(e) {
            this.value = this.value.toUpperCase();
        });

        // Form validation with SweetAlert
        function validateForm() {
            const vaccineId = document.getElementById('vaccine_id').value;
            const quantity = document.getElementById('quantity').value;
            const batchNumber = document.getElementById('batch_number').value;
            const expiryDate = document.getElementById('expiry_date').value;
            const submitBtn = document.getElementById('submitBtn');

            // Validate vaccine selection
            if (!vaccineId) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Validation Error',
                    text: 'Please select a vaccine!',
                    confirmButtonColor: '#2563eb'
                });
                return false;
            }

            // Validate batch number
            if (!batchNumber) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Validation Error',
                    text: 'Batch number is required!',
                    confirmButtonColor: '#2563eb'
                });
                return false;
            }

            // Validate batch number format
            const batchRegex = /^[A-Za-z0-9\-]+$/;
            if (!batchRegex.test(batchNumber)) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Validation Error',
                    text: 'Batch number me sirf letters, numbers, aur hyphens allowed hain!',
                    confirmButtonColor: '#2563eb'
                });
                return false;
            }

            // Validate quantity
            if (!quantity || quantity < 1) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Validation Error',
                    text: 'Quantity must be at least 1!',
                    confirmButtonColor: '#2563eb'
                });
                return false;
            }

            // Validate expiry date
            if (!expiryDate) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Validation Error',
                    text: 'Expiry date is required!',
                    confirmButtonColor: '#2563eb'
                });
                return false;
            }

            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const expiry = new Date(expiryDate);

            if (expiry <= today) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Validation Error',
                    text: 'Expiry date future mein honi chahiye!',
                    confirmButtonColor: '#2563eb'
                });
                return false;
            }

            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span>⏳</span> Adding Stock...';

            // Show success message (will be shown after redirect)
            return true;
        }

        // Show vaccine info on page load if vaccine selected
        window.onload = function() {
            showVaccineInfo();
        }

        // Close mobile menu when clicking outside
        document.addEventListener('click', function(event) {
            const mobileMenu = document.getElementById('mobileMenu');
            const hamburger = document.querySelector('.hamburger');

            if (!hamburger.contains(event.target) && !mobileMenu.contains(event.target) && mobileMenu.classList.contains('open')) {
                mobileMenu.classList.remove('open');
            }
        });
    </script>
</body>

</html>