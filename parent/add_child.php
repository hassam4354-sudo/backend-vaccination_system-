<?php
session_start();
if(!isset($_SESSION["logged_in"]) || $_SESSION["user_type"] != "parent"){
    header("location:../login.php");
    exit();
}

include("../dbconnection.php");

$user_id = $_SESSION["user_id"];
$query_parent = "SELECT * FROM parents WHERE user_id = '$user_id'";
$result_parent = mysqli_query($connection, $query_parent);
$parent_data = mysqli_fetch_assoc($result_parent);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Child</title>
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
        .navbar-links a.logout { background: #fee2e2; color: #dc2626; }
        .navbar-links a.logout:hover { background: #fecaca; }

        /* ── LAYOUT ── */
        .page-wrapper { max-width: 780px; margin: 32px auto; padding: 0 24px; }

        /* ── BACK LINK ── */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #1d4ed8;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 20px;
            padding: 8px 14px;
            background: white;
            border: 1px solid #e8eeff;
            border-radius: 8px;
            transition: all 0.2s;
            box-shadow: 0 1px 4px rgba(59,130,246,0.07);
        }
        .back-link:hover {
            background: #eff6ff;
            border-color: #bfdbfe;
            transform: translateX(-3px);
        }

        /* ── PAGE BANNER ── */
        .page-banner {
            background: linear-gradient(135deg, #1d4ed8 0%, #3b82f6 60%, #60a5fa 100%);
            border-radius: 18px;
            padding: 28px 36px;
            margin-bottom: 24px;
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
            width: 180px; height: 180px;
            background: rgba(255,255,255,0.08);
            border-radius: 50%;
        }
        .page-banner::after {
            content: '';
            position: absolute;
            bottom: -40px; right: 80px;
            width: 130px; height: 130px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
        }
        .banner-text { position: relative; z-index: 1; }
        .banner-text h2 { font-size: 21px; font-weight: 700; margin-bottom: 4px; }
        .banner-text p  { font-size: 13px; opacity: 0.85; margin: 0; }
        .banner-icon {
            font-size: 48px;
            position: relative;
            z-index: 1;
            opacity: 0.9;
        }

        /* ── MAIN CARD ── */
        .form-card {
            background: white;
            border-radius: 16px;
            border: 1px solid #e8eeff;
            box-shadow: 0 2px 12px rgba(59,130,246,0.07);
            padding: 36px;
        }

        /* ── SECTION TITLE ── */
        .section-title {
            font-size: 12px;
            font-weight: 700;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            margin: 28px 0 16px;
            padding-bottom: 8px;
            border-bottom: 1px solid #f1f5ff;
        }
        .section-title:first-child { margin-top: 0; }

        /* ── FORM GRID ── */
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; }
        .form-group { margin-bottom: 0; }
        .form-group.full { grid-column: 1 / -1; }

        /* ── LABELS ── */
        .field-label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 7px;
        }
        .field-label .req { color: #ef4444; margin-left: 2px; }

        /* ── INPUTS ── */
        input[type="text"],
        input[type="date"],
        input[type="number"],
        select,
        textarea {
            width: 100%;
            padding: 11px 14px;
            border: 1.5px solid #e8eeff;
            border-radius: 10px;
            font-size: 14px;
            font-family: 'Inter', Arial, sans-serif;
            color: #1a1a2e;
            background: #fafbff;
            transition: all 0.2s;
            outline: none;
            appearance: none;
        }
        input:focus, select:focus, textarea:focus {
            border-color: #3b82f6;
            background: white;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
        }
        textarea { min-height: 88px; resize: vertical; }

        /* Custom select arrow */
        select {
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            padding-right: 38px;
        }

        /* ── FILE INPUT ── */
        .file-upload-wrap {
            background: #f8faff;
            border: 2px dashed #bfdbfe;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            transition: all 0.2s;
            cursor: pointer;
        }
        .file-upload-wrap:hover { border-color: #3b82f6; background: #eff6ff; }
        .file-upload-wrap input[type="file"] {
            border: none;
            background: transparent;
            padding: 0;
            font-size: 13px;
            color: #4b6cb7;
            cursor: pointer;
        }
        .file-upload-hint {
            font-size: 12px;
            color: #9ca3af;
            margin-top: 6px;
        }

        /* ── SUBMIT BUTTON ── */
        .btn-submit {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #1d4ed8, #3b82f6);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 28px;
            font-family: 'Inter', Arial, sans-serif;
            box-shadow: 0 4px 14px rgba(29,78,216,0.25);
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(29,78,216,0.35);
        }

        /* ── RESPONSIVE ── */
        @media(max-width: 620px) {
            .navbar { padding: 0 16px; }
            .navbar-brand h2 { display: none; }
            .page-wrapper { padding: 0 14px; }
            .form-row { grid-template-columns: 1fr; }
            .form-group.full { grid-column: 1; }
            .form-card { padding: 22px; }
            .page-banner { flex-direction: column; align-items: flex-start; gap: 10px; }
            .banner-icon { display: none; }
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
        <a href="vaccinationhistory.php"> History</a>
        <a href="myprofile.php"> Profile</a>
        <a href="../logout.php" class="logout"> Logout</a>
    </div>
</div>

<div class="page-wrapper">

    <!-- ── BACK LINK ── -->
    <a href="my_children.php" class="back-link">← Back to My Children</a>

    <!-- ── PAGE BANNER ── -->
    <div class="page-banner">
        <div class="banner-text">
            <h2>➕ Add New Child</h2>
            <p>Fill in the details below to register your child</p>
        </div>
        <div class="banner-icon"></div>
    </div>

    <!-- ── FORM CARD ── -->
    <div class="form-card">
        <form action="create_child.php" method="post" enctype="multipart/form-data">

            <!-- Basic Info -->
            <div class="section-title">👤 Basic Information</div>
            <div class="form-row">
                <div class="form-group full">
                    <label class="field-label">Child's Full Name <span class="req">*</span></label>
                    <input type="text" name="full_name" placeholder="Enter full name" required>
                </div>

                <div class="form-group">
                    <label class="field-label">Date of Birth <span class="req">*</span></label>
                    <input type="date" name="date_of_birth" max="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="form-group">
                    <label class="field-label">Gender <span class="req">*</span></label>
                    <select name="gender" required>
                        <option value="">Select Gender</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="field-label">Blood Group</label>
                    <select name="blood_group">
                        <option value="">Select Blood Group</option>
                        <option value="A+">A+</option>
                        <option value="A-">A-</option>
                        <option value="B+">B+</option>
                        <option value="B-">B-</option>
                        <option value="AB+">AB+</option>
                        <option value="AB-">AB-</option>
                        <option value="O+">O+</option>
                        <option value="O-">O-</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="field-label">Birth Weight (kg)</label>
                    <input type="number" name="birth_weight" step="0.01" min="0" max="20" placeholder="e.g., 3.2">
                </div>

                <div class="form-group">
                    <label class="field-label">Birth Height (cm)</label>
                    <input type="number" name="birth_height" step="0.01" min="0" max="100" placeholder="e.g., 50">
                </div>
            </div>

            <!-- Medical Info -->
            <div class="section-title">🏥 Medical Information</div>
            <div class="form-row">
                <div class="form-group full">
                    <label class="field-label">Medical Conditions (if any)</label>
                    <textarea name="medical_conditions" placeholder="Enter any medical conditions or leave blank"></textarea>
                </div>
                <div class="form-group full">
                    <label class="field-label">Allergies (if any)</label>
                    <textarea name="allergies" placeholder="Enter any allergies or leave blank"></textarea>
                </div>
            </div>

            <!-- Photo -->
            <div class="section-title">📷 Profile Photo</div>
            <div class="file-upload-wrap">
                <input type="file" name="photo" accept="image/*">
                <div class="file-upload-hint">Optional — JPG, PNG, GIF or WEBP accepted</div>
            </div>

            <!-- Submit -->
            <button type="submit" name="add_child" class="btn-submit">
                 Add Child
            </button>

        </form>
    </div>
</div>

</body>
</html>