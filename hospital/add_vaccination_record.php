<?php
session_start();
if(!isset($_SESSION["logged_in"]) || $_SESSION["user_type"] != "hospital"){
    header("Location: ../login.php");
    exit();
}

include("../dbconnection.php");

$user_id = $_SESSION["user_id"];

// Get hospital data
$query_hospital  = "SELECT * FROM hospitals WHERE user_id = '$user_id'";
$result_hospital = mysqli_query($connection, $query_hospital);
$hospital        = mysqli_fetch_assoc($result_hospital);
$hospital_id     = $hospital['hospital_id'] ?? 0;
$is_verified     = $hospital['is_verified'] ?? 0;
$is_active       = $hospital['is_active']   ?? 0;

// Get booking_id from URL
$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;

if($booking_id == 0) {
    header("Location: todays_schedule.php");
    exit();
}

// Fetch booking details
$booking = mysqli_fetch_assoc(mysqli_query($connection,
    "SELECT vb.*, 
            c.full_name as child_name, c.date_of_birth, c.gender, c.blood_group, c.allergies, c.medical_conditions,
            v.vaccine_name, v.vaccine_code,
            p.full_name as parent_name, p.emergency_contact,
            ar.parent_notes
     FROM vaccination_bookings vb
     JOIN children c  ON vb.child_id  = c.child_id
     JOIN vaccines v  ON vb.vaccine_id = v.vaccine_id
     JOIN parents  p  ON c.parent_id   = p.parent_id
     LEFT JOIN appointment_requests ar ON vb.request_id = ar.request_id
     WHERE vb.booking_id = '$booking_id' AND vb.hospital_id = '$hospital_id'"));

if(!$booking) {
    header("Location: todays_schedule.php");
    exit();
}

// Check if record already exists
$existing = mysqli_fetch_assoc(mysqli_query($connection,
    "SELECT record_id FROM vaccination_records WHERE booking_id = '$booking_id'"));
if($existing) {
    $_SESSION['msg']      = "⚠️ Is booking ka record pehle se exist karta hai!";
    $_SESSION['msg_type'] = "warning";
    header("Location: vaccination_records.php");
    exit();
}

// Get doctors for this hospital
$doctors_result = mysqli_query($connection,
    "SELECT doctor_id, full_name, specialization FROM doctors 
     WHERE hospital_id = '$hospital_id' AND is_active = 1 ORDER BY full_name");

// Get inventory batches for this vaccine
$inventory_result = mysqli_query($connection,
    "SELECT batch_number, quantity_available, expiry_date 
     FROM hospital_vaccine_inventory 
     WHERE hospital_id = '$hospital_id' AND vaccine_id = '{$booking['vaccine_id']}' 
     AND is_available = 1 AND quantity_available > 0
     ORDER BY expiry_date ASC");

$error_msg = "";

// ── HANDLE FORM SUBMIT ──
if($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_record'])) {

    $vaccination_date   = mysqli_real_escape_string($connection, $_POST['vaccination_date']);
    $vaccination_time   = mysqli_real_escape_string($connection, $_POST['vaccination_time']);
    $administered_by    = mysqli_real_escape_string($connection, trim($_POST['administered_by']));
    $batch_number       = mysqli_real_escape_string($connection, trim($_POST['batch_number']));
    $vaccination_status = mysqli_real_escape_string($connection, $_POST['vaccination_status']);
    $next_dose_due_date = !empty($_POST['next_dose_due_date']) ? mysqli_real_escape_string($connection, $_POST['next_dose_due_date']) : NULL;
    $side_effects       = mysqli_real_escape_string($connection, trim($_POST['side_effects'] ?? ''));
    $notes              = mysqli_real_escape_string($connection, trim($_POST['notes'] ?? ''));

    $errors = [];

    if(empty($vaccination_date))   $errors[] = "Vaccination date zaroori hai.";
    if(empty($administered_by))    $errors[] = "Administered by zaroori hai.";
    if(empty($batch_number))       $errors[] = "Batch number zaroori hai.";
    if(empty($vaccination_status)) $errors[] = "Status select karein.";

    if(empty($errors)) {
        mysqli_begin_transaction($connection);
        try {
            // Insert record
            $next_dose_sql = $next_dose_due_date ? "'$next_dose_due_date'" : "NULL";

            $ins = mysqli_query($connection,
                "INSERT INTO vaccination_records 
                 (booking_id, child_id, vaccine_id, dose_number, hospital_id,
                  vaccination_date, vaccination_time, batch_number, administered_by,
                  next_dose_due_date, vaccination_status, side_effects, notes, created_at, updated_at)
                 VALUES
                 ('$booking_id', '{$booking['child_id']}', '{$booking['vaccine_id']}', '{$booking['dose_number']}', '$hospital_id',
                  '$vaccination_date', '$vaccination_time', '$batch_number', '$administered_by',
                  $next_dose_sql, '$vaccination_status', '$side_effects', '$notes', NOW(), NOW())");

            if(!$ins) throw new Exception("Record insert failed: " . mysqli_error($connection));

            // Update booking status to completed
            mysqli_query($connection,
                "UPDATE vaccination_bookings SET booking_status='completed', updated_at=NOW() 
                 WHERE booking_id='$booking_id'");

            // Decrease inventory quantity
            if(!empty($batch_number)) {
                mysqli_query($connection,
                    "UPDATE hospital_vaccine_inventory 
                     SET quantity_available = quantity_available - 1, updated_at = NOW()
                     WHERE hospital_id='$hospital_id' AND vaccine_id='{$booking['vaccine_id']}' 
                     AND batch_number='$batch_number' AND quantity_available > 0");
            }

            // Notify parent
            $parent_user_id = mysqli_fetch_assoc(mysqli_query($connection,
                "SELECT user_id FROM parents WHERE parent_id = (
                    SELECT parent_id FROM children WHERE child_id = '{$booking['child_id']}'
                 )"))['user_id'] ?? 0;

            if($parent_user_id) {
                $cname  = mysqli_real_escape_string($connection, $booking['child_name']);
                $vname  = mysqli_real_escape_string($connection, $booking['vaccine_name']);
                $notif_msg = "Vaccination completed for $cname — $vname (Dose {$booking['dose_number']}). Date: $vaccination_date.";
                mysqli_query($connection,
                    "INSERT INTO notifications (user_id, notification_type, title, message, related_id, is_read, created_at)
                     VALUES ('$parent_user_id', 'vaccination_completed', 'Vaccination Completed ✅', '$notif_msg', '$booking_id', 0, NOW())");
            }

            mysqli_commit($connection);

            $_SESSION['msg']      = "✅ Vaccination record successfully add ho gaya! Booking completed mark ho gayi.";
            $_SESSION['msg_type'] = "success";
            header("Location: vaccination_records.php");
            exit();

        } catch(Exception $e) {
            mysqli_rollback($connection);
            $error_msg = "❌ Error: " . $e->getMessage();
        }
    } else {
        $error_msg = implode("<br>", $errors);
    }
}

// Pending count for navbar
$pending_count = mysqli_fetch_assoc(mysqli_query($connection,
    "SELECT COUNT(*) as cnt FROM appointment_requests WHERE hospital_id='$hospital_id' AND request_status='pending'"))['cnt'];

// Child age
$age_days = floor((time() - strtotime($booking['date_of_birth'])) / 86400);
$age_yr   = floor($age_days / 365);
$age_mo   = floor(($age_days % 365) / 30);
$age_str  = $age_yr > 0 ? "{$age_yr}y {$age_mo}m" : "{$age_mo} months";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Vaccination Record — VacciCare</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        :root {
            --blue-900:#0a1628; --blue-700:#1a3a6e; --blue-600:#1e4db7;
            --blue-500:#2563eb; --blue-400:#3b82f6; --blue-100:#dbeafe; --blue-50:#eff6ff;
            --gray-50:#f8fafc; --gray-100:#f1f5f9; --gray-200:#e2e8f0;
            --gray-400:#94a3b8; --gray-500:#64748b; --gray-700:#334155;
            --white:#ffffff; --bg:#f0f4ff;
            --green-100:#dcfce7; --green-600:#16a34a;
            --red-100:#fee2e2; --red-600:#dc2626;
            --yellow-100:#fef9c3; --yellow-600:#ca8a04;
            --orange-100:#ffedd5; --orange-600:#ea580c;
        }
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Plus Jakarta Sans',sans-serif; background:var(--bg); color:var(--blue-900); min-height:100vh; }

        /* NAVBAR */
        .navbar { position:fixed; top:0; left:0; right:0; z-index:200; background:rgba(255,255,255,0.97); backdrop-filter:blur(18px); border-bottom:1px solid var(--gray-200); padding:0 40px; height:68px; display:flex; justify-content:space-between; align-items:center; box-shadow:0 2px 12px rgba(37,99,235,0.08); }
        .nav-logo { display:flex; align-items:center; gap:12px; font-weight:800; font-size:18px; color:var(--blue-700); text-decoration:none; }
        .nav-logo .logo-icon { width:40px; height:40px; background:var(--blue-500); border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:20px; box-shadow:0 4px 14px rgba(37,99,235,.35); }
        .nav-links { display:flex; align-items:center; gap:4px; }
        .nav-link { display:flex; align-items:center; gap:7px; padding:8px 14px; border-radius:8px; color:var(--gray-700); text-decoration:none; font-size:13.5px; font-weight:600; transition:all .2s; }
        .nav-link:hover, .nav-link.active { background:var(--blue-50); color:var(--blue-500); }
        .nav-badge { background:var(--red-600); color:#fff; font-size:10px; font-weight:700; padding:2px 6px; border-radius:20px; margin-left:4px; }
        .nav-right { display:flex; align-items:center; gap:12px; }
        .nav-logout { padding:8px 16px; background:var(--red-100); color:var(--red-600); border-radius:8px; text-decoration:none; font-size:13px; font-weight:700; transition:all .2s; }
        .nav-logout:hover { background:#fecaca; }
        .hamburger { display:none; background:none; border:none; cursor:pointer; font-size:22px; }
        .mobile-menu { display:none; }

        /* LAYOUT */
        .main { padding-top:88px; padding-bottom:40px; }
        .verify-banner { padding:10px 40px; font-size:13px; font-weight:600; }
        .verify-banner.verified  { background:#dcfce7; color:#166534; }
        .verify-banner.pending   { background:#fef9c3; color:#92400e; }
        .verify-banner.inactive  { background:#fee2e2; color:#991b1b; }
        .content { max-width:900px; margin:0 auto; padding:24px 40px; }

        /* BACK LINK */
        .back-link { display:inline-flex; align-items:center; gap:6px; color:var(--blue-500); text-decoration:none; font-size:14px; font-weight:600; margin-bottom:20px; padding:8px 14px; background:#fff; border:1px solid var(--gray-200); border-radius:8px; transition:all .2s; }
        .back-link:hover { background:var(--blue-50); transform:translateX(-3px); }

        /* PAGE HEADER */
        .page-header { margin-bottom:24px; }
        .page-header h1 { font-size:22px; font-weight:800; color:var(--blue-900); }
        .page-header p  { font-size:13px; color:var(--gray-500); margin-top:4px; }

        /* BOOKING INFO CARD */
        .booking-card { background:linear-gradient(135deg,#1d4ed8,#3b82f6); border-radius:16px; padding:24px 28px; margin-bottom:24px; color:#fff; display:grid; grid-template-columns:repeat(auto-fit, minmax(160px,1fr)); gap:16px; }
        .booking-item-label { font-size:10.5px; font-weight:700; opacity:.75; text-transform:uppercase; letter-spacing:.5px; }
        .booking-item-val   { font-size:15px; font-weight:800; margin-top:4px; }
        .booking-item-sub   { font-size:11.5px; opacity:.8; margin-top:2px; }
        .conf-badge { display:inline-block; background:rgba(255,255,255,0.2); padding:3px 10px; border-radius:6px; font-family:monospace; font-size:13px; font-weight:800; letter-spacing:1px; }

        /* ALERT */
        .alert { padding:13px 18px; border-radius:10px; margin-bottom:18px; font-size:14px; font-weight:600; }
        .alert-error   { background:var(--red-100);    border:1px solid #fca5a5; color:#991b1b; }
        .alert-warning { background:var(--yellow-100); border:1px solid #fde68a; color:#92400e; }

        /* MEDICAL WARNING */
        .medical-warn { background:var(--yellow-100); border:1px solid #fde68a; border-radius:12px; padding:14px 18px; margin-bottom:20px; font-size:13px; color:#78350f; }
        .medical-warn strong { display:block; margin-bottom:6px; font-size:14px; }

        /* FORM CARD */
        .form-card { background:#fff; border-radius:16px; border:1px solid var(--gray-200); box-shadow:0 2px 12px rgba(37,99,235,.06); padding:32px; }
        .section-title { font-size:11.5px; font-weight:800; color:var(--gray-500); text-transform:uppercase; letter-spacing:.8px; margin:28px 0 16px; padding-bottom:8px; border-bottom:1px solid var(--gray-100); }
        .section-title:first-child { margin-top:0; }
        .form-grid { display:grid; grid-template-columns:1fr 1fr; gap:18px; }
        .form-group { margin-bottom:0; }
        .form-group.full { grid-column:1/-1; }
        .form-label { display:block; font-size:13px; font-weight:600; color:var(--gray-700); margin-bottom:7px; }
        .form-label span { color:var(--red-600); }
        .form-control { width:100%; padding:11px 14px; border:1.5px solid var(--gray-200); border-radius:10px; font-size:14px; font-family:inherit; color:var(--blue-900); background:var(--gray-50); outline:none; transition:all .2s; }
        .form-control:focus { border-color:var(--blue-400); background:#fff; box-shadow:0 0 0 3px rgba(59,130,246,.1); }
        textarea.form-control { min-height:85px; resize:vertical; }
        select.form-control { appearance:none; background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E"); background-repeat:no-repeat; background-position:right 14px center; padding-right:38px; }
        .input-hint { font-size:11.5px; color:var(--gray-400); margin-top:5px; }

        /* STATUS RADIO */
        .status-group { display:flex; gap:12px; flex-wrap:wrap; margin-top:4px; }
        .status-opt { cursor:pointer; }
        .status-opt input { display:none; }
        .status-label { display:flex; align-items:center; gap:8px; padding:10px 16px; border:2px solid var(--gray-200); border-radius:10px; font-size:13px; font-weight:600; transition:all .2s; cursor:pointer; }
        .status-opt input:checked + .status-label { border-color:var(--blue-500); background:var(--blue-50); color:var(--blue-700); }
        .status-opt.completed input:checked + .status-label { border-color:var(--green-600); background:var(--green-100); color:var(--green-600); }
        .status-opt.partial   input:checked + .status-label { border-color:var(--yellow-600); background:var(--yellow-100); color:var(--yellow-600); }
        .status-opt.adverse   input:checked + .status-label { border-color:var(--orange-600); background:var(--orange-100); color:var(--orange-600); }

        /* FORM ACTIONS */
        .form-actions { display:flex; gap:12px; margin-top:28px; }
        .btn-submit { flex:1; padding:14px; background:linear-gradient(135deg,#1d4ed8,#3b82f6); color:#fff; border:none; border-radius:10px; font-size:15px; font-weight:700; cursor:pointer; font-family:inherit; transition:all .2s; box-shadow:0 4px 14px rgba(29,78,216,.25); }
        .btn-submit:hover { transform:translateY(-2px); box-shadow:0 8px 22px rgba(29,78,216,.35); }
        .btn-cancel { padding:14px 24px; background:var(--gray-100); color:var(--gray-700); border:none; border-radius:10px; font-size:14px; font-weight:700; cursor:pointer; font-family:inherit; text-decoration:none; display:flex; align-items:center; transition:all .2s; }
        .btn-cancel:hover { background:var(--gray-200); }

        @media(max-width:768px){
            .navbar { padding:0 16px; }
            .nav-links { display:none; }
            .hamburger { display:block; }
            .mobile-menu.open { display:flex; flex-direction:column; position:fixed; top:68px; left:0; right:0; background:#fff; border-bottom:1px solid var(--gray-200); padding:12px; z-index:199; gap:4px; }
            .content { padding:16px; }
            .form-grid { grid-template-columns:1fr; }
            .form-group.full { grid-column:1; }
            .booking-card { grid-template-columns:1fr 1fr; }
            .form-actions { flex-direction:column; }
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<div class="navbar">
    <a href="dashboard.php" class="nav-logo">
       
        Hospital Panel
    </a>
    <div class="nav-links">
        <a href="dashboard.php"            class="nav-link"> Dashboard</a>
        <a href="appointment_requests.php" class="nav-link">
             Requests
            <?php if($pending_count > 0): ?>
            <span class="nav-badge"><?php echo $pending_count; ?></span>
            <?php endif; ?>
        </a>
        <a href="todays_schedule.php"      class="nav-link active"> Bookings</a>
        <a href="vaccine_inventory.php"    class="nav-link"> Inventory</a>
        <a href="doctors.php"              class="nav-link"> Doctors</a>
        <a href="vaccination_records.php"  class="nav-link">Records</a>
    </div>
    <div class="nav-right">
        <div style="font-size:13px; font-weight:600; color:var(--blue-700);">
             <?php echo htmlspecialchars($hospital['hospital_name'] ?? 'Hospital'); ?>
        </div>
        <a href="../logout.php" class="nav-logout"> Logout</a>
        <button class="hamburger" onclick="toggleMenu()">☰</button>
    </div>
</div>

<!-- MOBILE MENU -->
<div class="mobile-menu" id="mobileMenu">
    <a href="dashboard.php"            class="nav-link"> Dashboard</a>
    <a href="appointment_requests.php" class="nav-link"> Requests <?php if($pending_count>0):?><span class="nav-badge"><?php echo $pending_count;?></span><?php endif;?></a>
    <a href="todays_schedule.php"      class="nav-link active"> Bookings</a>
    <a href="vaccine_inventory.php"    class="nav-link"> Inventory</a>
    <a href="doctors.php"              class="nav-link"> Doctors</a>
    <a href="vaccination_records.php"  class="nav-link"> Records</a>
    <a href="../logout.php"            class="nav-logout" style="margin-top:8px;"> Logout</a>
</div>

<div class="main">
    <?php if(!$is_verified): ?>
    <div class="verify-banner pending">⏳ <strong>Pending Verification:</strong> Admin verification ka wait kar raha hai.</div>
    <?php elseif(!$is_active): ?>
    <div class="verify-banner inactive">🚫 <strong>Account Inactive:</strong> Admin se rabta karein.</div>
    <?php else: ?>
    <div class="verify-banner verified">✅ <strong>Verified Hospital:</strong> Account verified aur active hai.</div>
    <?php endif; ?>

    <div class="content">

        <a href="todays_schedule.php" class="back-link">← Back to Bookings</a>

        <div class="page-header">
            <h1>📝 Add Vaccination Record</h1>
            <p>Booking #<?php echo $booking_id; ?> ka vaccination record fill karein</p>
        </div>

        <!-- BOOKING INFO -->
        <div class="booking-card">
            <div>
                <div class="booking-item-label">Child</div>
                <div class="booking-item-val"><?php echo htmlspecialchars($booking['child_name']); ?></div>
                <div class="booking-item-sub"><?php echo $booking['gender']; ?> · <?php echo $age_str; ?></div>
            </div>
            <div>
                <div class="booking-item-label">Vaccine</div>
                <div class="booking-item-val"><?php echo htmlspecialchars($booking['vaccine_name']); ?></div>
                <div class="booking-item-sub">Dose <?php echo $booking['dose_number']; ?> · <?php echo $booking['vaccine_code']; ?></div>
            </div>
            <div>
                <div class="booking-item-label">Parent</div>
                <div class="booking-item-val"><?php echo htmlspecialchars($booking['parent_name']); ?></div>
                <div class="booking-item-sub">📞 <?php echo $booking['emergency_contact'] ?? '—'; ?></div>
            </div>
            <div>
                <div class="booking-item-label">Confirmation</div>
                <div class="booking-item-val"><span class="conf-badge"><?php echo $booking['confirmation_code'] ?? '—'; ?></span></div>
                <div class="booking-item-sub">Appointment: <?php echo date('d M Y', strtotime($booking['appointment_date'])); ?></div>
            </div>
        </div>

        <!-- MEDICAL WARNING -->
        <?php if(($booking['allergies'] && $booking['allergies'] !== 'nothing') || ($booking['medical_conditions'] && $booking['medical_conditions'] !== 'nothing')): ?>
        <div class="medical-warn">
            <strong>⚠️ Medical Alert — Pehle Check Karein:</strong>
            <?php if($booking['medical_conditions'] && $booking['medical_conditions'] !== 'nothing'): ?>
            <div>🩺 Medical Conditions: <?php echo htmlspecialchars($booking['medical_conditions']); ?></div>
            <?php endif; ?>
            <?php if($booking['allergies'] && $booking['allergies'] !== 'nothing'): ?>
            <div>🚫 Allergies: <?php echo htmlspecialchars($booking['allergies']); ?></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- ERROR -->
        <?php if($error_msg): ?>
        <div class="alert alert-error"><?php echo $error_msg; ?></div>
        <?php endif; ?>

        <!-- FORM -->
        <div class="form-card">
            <form method="POST" id="recordForm">

                <!-- Basic Info -->
                <div class="section-title">💉 Vaccination Details</div>
                <div class="form-grid">

                    <div class="form-group">
                        <label class="form-label">Vaccination Date <span>*</span></label>
                        <input type="date" name="vaccination_date" class="form-control"
                               value="<?php echo isset($_POST['vaccination_date']) ? $_POST['vaccination_date'] : date('Y-m-d'); ?>"
                               max="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Vaccination Time</label>
                        <input type="time" name="vaccination_time" class="form-control"
                               value="<?php echo isset($_POST['vaccination_time']) ? $_POST['vaccination_time'] : date('H:i'); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Administered By <span>*</span></label>
                        <select name="administered_by" class="form-control" id="administered_by" required>
                            <option value="">-- Doctor Select Karein --</option>
                            <?php while($doc = mysqli_fetch_assoc($doctors_result)): ?>
                            <option value="<?php echo htmlspecialchars($doc['full_name']); ?>"
                                <?php echo (isset($_POST['administered_by']) && $_POST['administered_by'] == $doc['full_name']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($doc['full_name']); ?> — <?php echo htmlspecialchars($doc['specialization']); ?>
                            </option>
                            <?php endwhile; ?>
                            <option value="other">✏️ Manually Enter</option>
                        </select>
                        <input type="text" name="administered_by_manual" id="administered_by_manual"
                               class="form-control" placeholder="Doctor ka naam likhein..."
                               style="display:none; margin-top:8px;"
                               value="<?php echo isset($_POST['administered_by_manual']) ? htmlspecialchars($_POST['administered_by_manual']) : ''; ?>">
                        <div class="input-hint">Jo doctor ne vaccine diya</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Batch Number <span>*</span></label>
                        <select name="batch_number" class="form-control" required>
                            <option value="">-- Batch Select Karein --</option>
                            <?php 
                            $has_inventory = false;
                            while($inv = mysqli_fetch_assoc($inventory_result)): 
                                $has_inventory = true;
                            ?>
                            <option value="<?php echo htmlspecialchars($inv['batch_number']); ?>"
                                <?php echo (isset($_POST['batch_number']) && $_POST['batch_number'] == $inv['batch_number']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($inv['batch_number']); ?> 
                                (Qty: <?php echo $inv['quantity_available']; ?> | Exp: <?php echo date('d M Y', strtotime($inv['expiry_date'])); ?>)
                            </option>
                            <?php endwhile; ?>
                            <?php if(!$has_inventory): ?>
                            <option value="MANUAL" selected>📝 Manual Entry</option>
                            <?php endif; ?>
                        </select>
                        <input type="text" name="batch_number_manual" id="batch_number_manual"
                               class="form-control" placeholder="Batch number likhein..."
                               style="<?php echo !$has_inventory ? '' : 'display:none;'; ?> margin-top:8px;"
                               value="<?php echo isset($_POST['batch_number_manual']) ? htmlspecialchars($_POST['batch_number_manual']) : ''; ?>">
                        <div class="input-hint">Inventory se batch select karein</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Next Dose Due Date</label>
                        <input type="date" name="next_dose_due_date" class="form-control"
                               min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>"
                               value="<?php echo isset($_POST['next_dose_due_date']) ? $_POST['next_dose_due_date'] : ''; ?>">
                        <div class="input-hint">Agar next dose scheduled ho</div>
                    </div>

                </div>

                <!-- Status -->
                <div class="section-title">📊 Vaccination Status</div>
                <div class="status-group">
                    <label class="status-opt completed">
                        <input type="radio" name="vaccination_status" value="completed"
                               <?php echo (!isset($_POST['vaccination_status']) || $_POST['vaccination_status'] === 'completed') ? 'checked' : ''; ?>>
                        <span class="status-label">✅ Completed</span>
                    </label>
                    <label class="status-opt partial">
                        <input type="radio" name="vaccination_status" value="partial"
                               <?php echo (isset($_POST['vaccination_status']) && $_POST['vaccination_status'] === 'partial') ? 'checked' : ''; ?>>
                        <span class="status-label">⚡ Partial</span>
                    </label>
                    <label class="status-opt adverse">
                        <input type="radio" name="vaccination_status" value="adverse_reaction"
                               <?php echo (isset($_POST['vaccination_status']) && $_POST['vaccination_status'] === 'adverse_reaction') ? 'checked' : ''; ?>>
                        <span class="status-label">⚠️ Adverse Reaction</span>
                    </label>
                </div>

                <!-- Notes -->
                <div class="section-title">📝 Additional Notes</div>
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Side Effects (if any)</label>
                        <textarea name="side_effects" class="form-control"
                                  placeholder="Koi side effects observe hue? Jaise fever, redness, swelling..."><?php echo isset($_POST['side_effects']) ? htmlspecialchars($_POST['side_effects']) : ''; ?></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Notes</label>
                        <textarea name="notes" class="form-control"
                                  placeholder="Koi additional notes ya observations..."><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
                    </div>
                </div>

                <!-- Actions -->
                <div class="form-actions">
                    <button type="submit" name="save_record" value="1" class="btn-submit">
                        💾 Save Vaccination Record
                    </button>
                    <a href="todays_schedule.php" class="btn-cancel">✖️ Cancel</a>
                </div>

            </form>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function toggleMenu() { document.getElementById('mobileMenu').classList.toggle('open'); }

// Doctor select — show manual input if "other" selected
document.getElementById('administered_by').addEventListener('change', function() {
    const manual = document.getElementById('administered_by_manual');
    if(this.value === 'other') {
        manual.style.display = 'block';
        manual.required = true;
    } else {
        manual.style.display = 'none';
        manual.required = false;
    }
});

// Batch select — show manual input if "MANUAL" selected
const batchSelect = document.querySelector('select[name="batch_number"]');
if(batchSelect) {
    batchSelect.addEventListener('change', function() {
        const manual = document.getElementById('batch_number_manual');
        if(this.value === 'MANUAL') {
            manual.style.display = 'block';
            manual.required = true;
        } else {
            manual.style.display = 'none';
            manual.required = false;
        }
    });
}

// Before submit — if "other" doctor, use manual value
document.getElementById('recordForm').addEventListener('submit', function(e) {
    const docSelect = document.getElementById('administered_by');
    const docManual = document.getElementById('administered_by_manual');
    if(docSelect.value === 'other') {
        if(!docManual.value.trim()) {
            e.preventDefault();
            Swal.fire({ icon:'warning', title:'Zaroori!', text:'Doctor ka naam likhein!', confirmButtonColor:'#2563eb' });
            return;
        }
        docSelect.value = docManual.value.trim();
    }

    const batchSel = document.querySelector('select[name="batch_number"]');
    const batchMan = document.getElementById('batch_number_manual');
    if(batchSel && batchSel.value === 'MANUAL') {
        if(!batchMan.value.trim()) {
            e.preventDefault();
            Swal.fire({ icon:'warning', title:'Zaroori!', text:'Batch number likhein!', confirmButtonColor:'#2563eb' });
            return;
        }
        batchSel.value = batchMan.value.trim();
    }
});
</script>
</body>
</html>