<?php
session_start();
if(!isset($_SESSION["logged_in"]) || $_SESSION["user_type"] != "parent"){
    header("location:../login.php");
    exit();
}

include("../dbconnection.php");

$user_id   = $_SESSION["user_id"];
$success_msg = "";
$error_msg   = "";

// ── ADD NEW VACCINE ──
if(isset($_POST['add_vaccine'])) {
    $vaccine_name  = sanitize_input($_POST['vaccine_name']);
    $vaccine_code  = sanitize_input($_POST['vaccine_code']);
    $description   = sanitize_input($_POST['description']);
    $manufacturer  = sanitize_input($_POST['manufacturer']);
    $scheduled_age = sanitize_input($_POST['scheduled_age']);
    $dosage_info   = sanitize_input($_POST['dosage_info']);
    $side_effects  = sanitize_input($_POST['side_effects']);

    if(empty($vaccine_name)) {
        $error_msg = "Vaccine name is required.";
    } else {
        // Check duplicate
        $check = mysqli_query($connection, "SELECT vaccine_id FROM vaccines WHERE vaccine_name = '$vaccine_name'");
        if(mysqli_num_rows($check) > 0) {
            $error_msg = "Vaccine '$vaccine_name' already exists.";
        } else {
            $q = "INSERT INTO vaccines (vaccine_name, vaccine_code, description, manufacturer, scheduled_age, dosage_info, side_effects, is_active)
                  VALUES ('$vaccine_name', '$vaccine_code', '$description', '$manufacturer', '$scheduled_age', '$dosage_info', '$side_effects', 1)";
            if(mysqli_query($connection, $q)) {
                log_audit($user_id, 'ADD_VACCINE', 'vaccines', mysqli_insert_id($connection), "Added vaccine: $vaccine_name");
                $success_msg = "Vaccine '$vaccine_name' added successfully!";
            } else {
                $error_msg = "Error: " . mysqli_error($connection);
            }
        }
    }
}

// ── FETCH VACCINES FROM DB ──
$q_vaccines = "SELECT * FROM vaccines WHERE is_active = 1 ORDER BY vaccine_name ASC";
$result_vaccines = mysqli_query($connection, $q_vaccines);
$vaccine_count = mysqli_num_rows($result_vaccines);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vaccination History</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', Arial, sans-serif;
            background: #f0f4ff;
            color: #1a1a2e;
        }

        /* ── NAVBAR ── */
        .navbar {
            background: #ffffff;
            border-bottom: 2px solid #e8eeff;
            padding: 0 35px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 68px;
            box-shadow: 0 2px 16px rgba(59,130,246,0.08);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .navbar-brand { display: flex; align-items: center; gap: 10px; }
        .navbar-brand .brand-icon {
            width: 40px; height: 40px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px;
        }
        .navbar-brand h2 { font-size: 20px; font-weight: 700; color: #1d4ed8; letter-spacing: -0.3px; }
        .navbar-links { display: flex; align-items: center; gap: 6px; }
        .navbar-links a {
            color: #4b6cb7; text-decoration: none;
            padding: 8px 14px; border-radius: 8px;
            font-size: 13.5px; font-weight: 500; transition: all 0.2s;
        }
        .navbar-links a:hover  { background: #eff6ff; color: #1d4ed8; }
        .navbar-links a.active { background: #eff6ff; color: #1d4ed8; font-weight: 600; }
        .navbar-links a.logout { background: #fee2e2; color: #dc2626; }
        .navbar-links a.logout:hover { background: #fecaca; }

        /* ── LAYOUT ── */
        .container { max-width: 1200px; margin: 32px auto; padding: 0 24px; }

        /* ── PAGE BANNER ── */
        .page-banner {
            background: linear-gradient(135deg, #1d4ed8 0%, #3b82f6 60%, #60a5fa 100%);
            border-radius: 18px;
            padding: 30px 36px;
            margin-bottom: 28px;
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 8px 32px rgba(59,130,246,0.3);
            position: relative;
            overflow: hidden;
        }
        .page-banner::before {
            content: '';
            position: absolute;
            top: -40px; right: -40px;
            width: 200px; height: 200px;
            background: rgba(255,255,255,0.08);
            border-radius: 50%;
        }
        .page-banner::after {
            content: '';
            position: absolute;
            bottom: -50px; right: 100px;
            width: 150px; height: 150px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
        }
        .banner-left { position: relative; z-index: 1; }
        .banner-left h2 { font-size: 22px; font-weight: 700; margin-bottom: 4px; }
        .banner-left p  { font-size: 13px; opacity: 0.85; margin: 0; }
        .banner-right { display: flex; align-items: center; gap: 12px; position: relative; z-index: 1; }
        .banner-badge {
            background: rgba(255,255,255,0.15);
            border: 2px solid rgba(255,255,255,0.3);
            color: white;
            padding: 8px 18px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
        }
        .btn-add-vaccine {
            background: white;
            color: #1d4ed8;
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 13.5px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .btn-add-vaccine:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(0,0,0,0.15);
        }

        /* ── ALERTS ── */
        .alert {
            padding: 13px 18px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-error   { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }

        /* ── SECTION LABEL ── */
        .section-label {
            font-size: 13px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 16px;
        }

        /* ── VACCINE GRID ── */
        .grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-bottom: 28px;
        }

        /* ── VACCINE CARD ── */
        .vaccine-card {
            background: #ffffff;
            border-radius: 14px;
            padding: 20px 22px;
            box-shadow: 0 2px 12px rgba(59,130,246,0.07);
            border: 1px solid #e8eeff;
            border-left: 5px solid #3b82f6;
            transition: all 0.25s;
            position: relative;
            cursor: default;
        }
        .vaccine-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 28px rgba(59,130,246,0.14);
            border-left-color: #1d4ed8;
            border-color: #bfdbfe;
        }
        .vaccine-name {
            font-size: 17px;
            font-weight: 700;
            color: #1d4ed8;
            margin-bottom: 8px;
        }
        .vaccine-meta {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .vaccine-meta-row {
            font-size: 12.5px;
            color: #6b7280;
            display: flex;
            align-items: flex-start;
            gap: 6px;
        }
        .meta-label {
            font-weight: 600;
            color: #374151;
            min-width: 80px;
            flex-shrink: 0;
        }
        .vaccine-code-badge {
            display: inline-block;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 11px;
            font-weight: 700;
            padding: 2px 10px;
            border-radius: 20px;
            border: 1px solid #bfdbfe;
            margin-bottom: 8px;
        }

        /* Tooltip */
        .vaccine-desc {
            position: absolute;
            bottom: 108%;
            left: 50%;
            transform: translateX(-50%);
            width: 240px;
            background: #1e3a8a;
            color: white;
            padding: 14px 16px;
            font-size: 13px;
            line-height: 1.6;
            border-radius: 12px;
            box-shadow: 0 10px 28px rgba(29,78,216,0.3);
            opacity: 0;
            visibility: hidden;
            transition: all 0.25s;
            z-index: 20;
            text-align: left;
        }
        .vaccine-desc::after {
            content: "";
            position: absolute;
            top: 100%; left: 50%;
            transform: translateX(-50%);
            border-width: 8px; border-style: solid;
            border-color: #1e3a8a transparent transparent transparent;
        }
        .vaccine-card:hover .vaccine-desc {
            opacity: 1;
            visibility: visible;
        }

        /* ── ACTION BOX ── */
        .action-box {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #e8eeff;
            box-shadow: 0 2px 12px rgba(59,130,246,0.07);
            padding: 36px 30px;
            text-align: center;
        }
        .action-box .action-icon { font-size: 48px; margin-bottom: 14px; display: block; }
        .action-box h3 { font-size: 20px; font-weight: 700; color: #1a1a2e; margin-bottom: 8px; }
        .action-box p  { color: #6b7280; font-size: 14px; margin-bottom: 22px; }
        .btn-book {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #1d4ed8, #3b82f6);
            color: white;
            text-decoration: none;
            padding: 13px 32px;
            font-size: 15px;
            border-radius: 10px;
            font-weight: 700;
            box-shadow: 0 4px 14px rgba(29,78,216,0.25);
            transition: all 0.2s;
        }
        .btn-book:hover { transform: translateY(-3px); box-shadow: 0 10px 24px rgba(29,78,216,0.35); }

        /* ── EMPTY STATE ── */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 16px;
            border: 2px dashed #bfdbfe;
            margin-bottom: 28px;
        }
        .empty-state span { font-size: 52px; display: block; margin-bottom: 14px; }
        .empty-state h4 { font-size: 18px; font-weight: 700; color: #1a1a2e; margin-bottom: 6px; }
        .empty-state p  { color: #6b7280; font-size: 14px; }

        /* ── MODAL OVERLAY ── */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 60, 0.5);
            z-index: 999;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .modal-overlay.open { display: flex; }

        .modal {
            background: white;
            border-radius: 18px;
            padding: 36px;
            width: 100%;
            max-width: 560px;
            box-shadow: 0 24px 60px rgba(29,78,216,0.2);
            position: relative;
            animation: modalIn 0.25s ease;
        }
        @keyframes modalIn {
            from { opacity: 0; transform: translateY(-20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid #e8eeff;
        }
        .modal-header h3 { font-size: 18px; font-weight: 700; color: #1a1a2e; }
        .modal-close {
            width: 34px; height: 34px;
            border-radius: 8px;
            background: #f1f5f9;
            border: none;
            cursor: pointer;
            font-size: 18px;
            display: flex; align-items: center; justify-content: center;
            color: #6b7280;
            transition: all 0.2s;
        }
        .modal-close:hover { background: #fee2e2; color: #dc2626; }

        .modal-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .modal-group { margin-bottom: 14px; }
        .modal-group.full { grid-column: 1 / -1; }
        .modal-label {
            display: block;
            font-size: 13px; font-weight: 600; color: #374151;
            margin-bottom: 6px;
        }
        .modal-label .req { color: #ef4444; }
        .modal-input,
        .modal-textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid #e8eeff;
            border-radius: 9px;
            font-size: 13.5px;
            font-family: 'Inter', Arial, sans-serif;
            color: #1a1a2e;
            background: #fafbff;
            outline: none;
            transition: all 0.2s;
        }
        .modal-input:focus, .modal-textarea:focus {
            border-color: #3b82f6;
            background: white;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
        }
        .modal-textarea { min-height: 75px; resize: vertical; }
        .modal-footer { margin-top: 20px; display: flex; gap: 12px; }
        .btn-modal-save {
            flex: 1; padding: 12px;
            background: linear-gradient(135deg, #1d4ed8, #3b82f6);
            color: white; border: none; border-radius: 10px;
            font-size: 14px; font-weight: 700; cursor: pointer;
            font-family: 'Inter', Arial, sans-serif;
            box-shadow: 0 4px 14px rgba(29,78,216,0.25);
            transition: all 0.2s;
        }
        .btn-modal-save:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(29,78,216,0.35); }
        .btn-modal-cancel {
            padding: 12px 22px;
            background: #f1f5f9; color: #555;
            border: 1px solid #e2e8f0; border-radius: 10px;
            font-size: 14px; font-weight: 600; cursor: pointer;
            font-family: 'Inter', Arial, sans-serif;
            transition: background 0.2s;
        }
        .btn-modal-cancel:hover { background: #e9ecef; }

        /* ── RESPONSIVE ── */
        @media(max-width: 900px) {
            .grid { grid-template-columns: repeat(2, 1fr); }
            .page-banner { flex-direction: column; align-items: flex-start; gap: 14px; }
            .banner-right { flex-wrap: wrap; }
        }
        @media(max-width: 600px) {
            .grid { grid-template-columns: 1fr; }
            .navbar { padding: 0 16px; }
            .navbar-brand h2 { display: none; }
            .container { padding: 0 14px; }
            .modal { padding: 22px; }
            .modal-row { grid-template-columns: 1fr; }
            .modal-group.full { grid-column: 1; }
        }
    </style>
</head>
<body>

<!-- ── NAVBAR ── -->
<div class="navbar">
    <div class="navbar-brand">
       
         <h2>Parent_Panel</h2>
    </div>
    <div class="navbar-links">
        <a href="dashboard.php"> Dashboard</a>
        <a href="my_children.php"> My Children</a>
        <a href="book_appointment.php">Book</a>
        <a href="vaccinationhistory.php" class="active"> History</a>
        <a href="myprofile.php"> Profile</a>
        <a href="../logout.php" class="logout"> Logout</a>
    </div>
</div>

<div class="container">

    <!-- ── PAGE BANNER ── -->
    <div class="page-banner">
        <div class="banner-left">
            <h2>💉 Vaccination History</h2>
            <p>Your child's complete vaccination record — hover on any card to learn more</p>
        </div>
        <div class="banner-right">
            <div class="banner-badge"><?php echo $vaccine_count; ?> Vaccines Listed</div>
            <button class="btn-add-vaccine" onclick="openModal()">➕ Add New Vaccine</button>
        </div>
    </div>

    <!-- ── ALERTS ── -->
    <?php if($success_msg): ?>
        <div class="alert alert-success">✅ <?php echo $success_msg; ?></div>
    <?php endif; ?>
    <?php if($error_msg): ?>
        <div class="alert alert-error">❌ <?php echo $error_msg; ?></div>
    <?php endif; ?>

    <!-- ── SECTION LABEL ── -->
    <div class="section-label">Available Vaccines</div>

    <!-- ── VACCINE GRID ── -->
    <?php if($vaccine_count > 0): ?>
    <div class="grid">
        <?php
        mysqli_data_seek($result_vaccines, 0);
        while($vac = mysqli_fetch_assoc($result_vaccines)):
        ?>
        <div class="vaccine-card">
            <!-- Code badge -->
            <?php if(!empty($vac['vaccine_code'])): ?>
            <span class="vaccine-code-badge"><?php echo htmlspecialchars($vac['vaccine_code']); ?></span>
            <?php endif; ?>

            <!-- Name -->
            <div class="vaccine-name"><?php echo htmlspecialchars($vac['vaccine_name']); ?></div>

            <!-- Meta info -->
            <div class="vaccine-meta">
                <?php if(!empty($vac['manufacturer'])): ?>
                <div class="vaccine-meta-row">
                    <span class="meta-label">Manufacturer:</span>
                    <span><?php echo htmlspecialchars($vac['manufacturer']); ?></span>
                </div>
                <?php endif; ?>
                <?php if(!empty($vac['scheduled_age'])): ?>
                <div class="vaccine-meta-row">
                    <span class="meta-label">Given at:</span>
                    <span><?php echo htmlspecialchars($vac['scheduled_age']); ?></span>
                </div>
                <?php endif; ?>
                <?php if(!empty($vac['dosage_info'])): ?>
                <div class="vaccine-meta-row">
                    <span class="meta-label">Dosage:</span>
                    <span><?php echo htmlspecialchars($vac['dosage_info']); ?></span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Tooltip on hover -->
            <?php if(!empty($vac['description']) || !empty($vac['side_effects'])): ?>
            <div class="vaccine-desc">
                <?php if(!empty($vac['description'])): ?>
                <strong><?php echo htmlspecialchars($vac['vaccine_name']); ?></strong><br>
                <?php echo htmlspecialchars($vac['description']); ?>
                <?php endif; ?>
                <?php if(!empty($vac['side_effects'])): ?>
                <br><br><strong>Side Effects:</strong><br>
                <?php echo htmlspecialchars($vac['side_effects']); ?>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endwhile; ?>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <span>💉</span>
        <h4>No vaccines found</h4>
        <p>Click "Add New Vaccine" to add your first vaccine.</p>
    </div>
    <?php endif; ?>

    <!-- ── ACTION BOX ── -->
    <div class="action-box">
        <span class="action-icon">📅</span>
        <h3>Ready for the Next Dose?</h3>
        <p>Apne child ka next vaccination appointment abhi book karein.</p>
        <a href="book_appointment.php" class="btn-book">📅 Book Your Appointment</a>
    </div>

</div>

<!-- ── ADD VACCINE MODAL ── -->
<div class="modal-overlay" id="vaccineModal">
    <div class="modal">
        <div class="modal-header">
            <h3>➕ Add New Vaccine</h3>
            <button class="modal-close" onclick="closeModal()">✕</button>
        </div>
        <form method="POST" action="vaccinationhistory.php">
            <div class="modal-row">
                <div class="modal-group">
                    <label class="modal-label">Vaccine Name <span class="req">*</span></label>
                    <input type="text" name="vaccine_name" class="modal-input" placeholder="e.g. BCG" required>
                </div>
                <div class="modal-group">
                    <label class="modal-label">Vaccine Code</label>
                    <input type="text" name="vaccine_code" class="modal-input" placeholder="e.g. BCG-01">
                </div>
                <div class="modal-group">
                    <label class="modal-label">Manufacturer</label>
                    <input type="text" name="manufacturer" class="modal-input" placeholder="e.g. Serum Institute">
                </div>
                <div class="modal-group">
                    <label class="modal-label">Scheduled Age</label>
                    <input type="text" name="scheduled_age" class="modal-input" placeholder="e.g. At Birth, 6 weeks">
                </div>
                <div class="modal-group">
                    <label class="modal-label">Dosage Info</label>
                    <input type="text" name="dosage_info" class="modal-input" placeholder="e.g. 0.5ml injection">
                </div>
                <div class="modal-group full">
                    <label class="modal-label">Description</label>
                    <textarea name="description" class="modal-textarea" placeholder="Brief description of the vaccine..."></textarea>
                </div>
                <div class="modal-group full">
                    <label class="modal-label">Side Effects</label>
                    <textarea name="side_effects" class="modal-textarea" placeholder="Possible side effects..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-modal-cancel" onclick="closeModal()">Cancel</button>
                <button type="submit" name="add_vaccine" class="btn-modal-save">💾 Save Vaccine</button>
            </div>
        </form>
    </div>
</div>

<script>
    function openModal() {
        document.getElementById('vaccineModal').classList.add('open');
        document.body.style.overflow = 'hidden';
    }
    function closeModal() {
        document.getElementById('vaccineModal').classList.remove('open');
        document.body.style.overflow = '';
    }
    // Close on outside click
    document.getElementById('vaccineModal').addEventListener('click', function(e) {
        if(e.target === this) closeModal();
    });
    // Close on Escape
    document.addEventListener('keydown', function(e) {
        if(e.key === 'Escape') closeModal();
    });

    // Auto-open modal if there was an error on add
    <?php if($error_msg && isset($_POST['add_vaccine'])): ?>
    openModal();
    <?php endif; ?>

    // Auto-hide alerts
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(a => {
            a.style.transition = 'opacity 0.5s';
            a.style.opacity = '0';
            setTimeout(() => a.remove(), 500);
        });
    }, 4000);
</script>
</body>
</html>