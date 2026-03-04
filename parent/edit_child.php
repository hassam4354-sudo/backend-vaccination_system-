<?php
session_start();
if(!isset($_SESSION["logged_in"]) || $_SESSION["user_type"] != "parent"){
    header("location:../login.php");
    exit();
}

include("../dbconnection.php");

$user_id = $_SESSION["user_id"];
$child_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if(!$child_id){
    header("location:my_children.php");
    exit();
}

// Get parent_id
$query_parent = "SELECT * FROM parents WHERE user_id = '$user_id'";
$result_parent = mysqli_query($connection, $query_parent);
$parent_data = mysqli_fetch_assoc($result_parent);
$parent_id = $parent_data['parent_id'];

// Get child details (make sure child belongs to this parent)
$query_child = "SELECT * FROM children WHERE child_id = '$child_id' AND parent_id = '$parent_id'";
$result_child = mysqli_query($connection, $query_child);

if(mysqli_num_rows($result_child) == 0){
    header("location:my_children.php");
    exit();
}

$child = mysqli_fetch_assoc($result_child);

// Handle form submission
$success_msg = "";
$error_msg = "";

if(isset($_POST['update_child'])) {
    $full_name        = sanitize_input($_POST['full_name']);
    $date_of_birth    = sanitize_input($_POST['date_of_birth']);
    $gender           = sanitize_input($_POST['gender']);
    $blood_group      = sanitize_input($_POST['blood_group']);
    $birth_weight     = $_POST['birth_weight'] !== '' ? sanitize_input($_POST['birth_weight']) : NULL;
    $birth_height     = $_POST['birth_height'] !== '' ? sanitize_input($_POST['birth_height']) : NULL;
    $medical_conditions = sanitize_input($_POST['medical_conditions']);
    $allergies        = sanitize_input($_POST['allergies']);

    // Handle photo upload
    $photo_url = $child['photo_url']; // keep existing by default

    if(isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $file_type = $_FILES['photo']['type'];

        if(in_array($file_type, $allowed_types)) {
            $upload_dir = "../uploads/children/";
            if(!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }

            $file_extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
            $new_filename = "child_" . time() . "_" . rand(1000, 9999) . "." . $file_extension;
            $photo_path = $upload_dir . $new_filename;

            if(move_uploaded_file($_FILES['photo']['tmp_name'], $photo_path)) {
                $photo_url = "uploads/children/" . $new_filename;
            }
        } else {
            $error_msg = "Invalid image format. Please upload JPG, PNG, GIF or WEBP.";
        }
    }

    // Remove photo if checkbox checked
    if(isset($_POST['remove_photo'])) {
        $photo_url = NULL;
    }

    if(empty($error_msg)) {
        $blood_group_val      = $blood_group      ? "'$blood_group'"      : "NULL";
        $birth_weight_val     = $birth_weight     ? "'$birth_weight'"     : "NULL";
        $birth_height_val     = $birth_height     ? "'$birth_height'"     : "NULL";
        $medical_conditions_val = $medical_conditions ? "'$medical_conditions'" : "NULL";
        $allergies_val        = $allergies        ? "'$allergies'"        : "NULL";
        $photo_url_val        = $photo_url        ? "'$photo_url'"        : "NULL";

        $query_update = "UPDATE children SET
            full_name          = '$full_name',
            date_of_birth      = '$date_of_birth',
            gender             = '$gender',
            blood_group        = $blood_group_val,
            birth_weight       = $birth_weight_val,
            birth_height       = $birth_height_val,
            medical_conditions = $medical_conditions_val,
            allergies          = $allergies_val,
            photo_url          = $photo_url_val
            WHERE child_id = '$child_id' AND parent_id = '$parent_id'";

        $run = mysqli_query($connection, $query_update);

        if($run) {
            log_audit($user_id, 'EDIT_CHILD', 'children', $child_id, "Updated child: $full_name");

            // Refresh child data
            $result_child2 = mysqli_query($connection, "SELECT * FROM children WHERE child_id = '$child_id'");
            $child = mysqli_fetch_assoc($result_child2);
            $success_msg = "Child information updated successfully!";
        } else {
            $error_msg = "Error updating: " . mysqli_error($connection);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Child - <?php echo htmlspecialchars($child['full_name']); ?></title>
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
        .navbar h2 {
            font-size: 20px;
            font-weight: 700;
            color: #1d4ed8;
            letter-spacing: -0.3px;
        }
        .navbar div { display: flex; align-items: center; gap: 6px; }
        .navbar a {
            color: #4b6cb7;
            text-decoration: none;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 13.5px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .navbar a:hover {
            background: #eff6ff;
            color: #1d4ed8;
        }
        .navbar a.logout {
            background: #fee2e2;
            color: #dc2626;
        }
        .navbar a.logout:hover { background: #fecaca; }

        /* ── LAYOUT ── */
        .container {
            max-width: 800px;
            margin: 32px auto;
            padding: 0 24px;
        }

        /* ── BACK LINK ── */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 20px;
            color: #3b82f6;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: color 0.2s;
        }
        .back-link:hover { color: #1d4ed8; text-decoration: underline; }

        /* ── CARD ── */
        .card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(59,130,246,0.07);
            border: 1px solid #e8eeff;
            padding: 40px;
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 32px;
            padding-bottom: 20px;
            border-bottom: 1px solid #f1f5ff;
        }

        .current-photo {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #3b82f6;
            flex-shrink: 0;
        }
        .current-photo-placeholder {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            border: 3px solid #3b82f6;
            flex-shrink: 0;
        }
        .card-header-info h2 {
            font-size: 22px;
            font-weight: 700;
            color: #1a1a2e;
        }
        .card-header-info p {
            color: #6b7280;
            font-size: 14px;
            margin-top: 4px;
        }

        /* ── ALERTS ── */
        .alert {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-size: 14px;
            font-weight: 600;
        }
        .alert-success { background: #dcfce7; color: #166534; border-left: 4px solid #22c55e; }
        .alert-error   { background: #fee2e2; color: #dc2626; border-left: 4px solid #ef4444; }

        /* ── SECTION TITLES ── */
        .section-title {
            font-size: 13px;
            font-weight: 700;
            color: #1d4ed8;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin: 28px 0 15px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e8eeff;
        }

        /* ── FORM ── */
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .form-group { margin-bottom: 18px; }
        .form-group.full { grid-column: 1 / -1; }

        label {
            display: block;
            margin-bottom: 6px;
            color: #374151;
            font-weight: 600;
            font-size: 13.5px;
        }
        label span.req { color: #ef4444; margin-left: 2px; }

        input[type="text"],
        input[type="date"],
        input[type="number"],
        input[type="tel"],
        select,
        textarea {
            width: 100%;
            padding: 11px 14px;
            border: 1.5px solid #e8eeff;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Inter', Arial, sans-serif;
            color: #1a1a2e;
            transition: border 0.2s, box-shadow 0.2s;
            background: #f8faff;
        }
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #3b82f6;
            background: white;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
        }
        textarea { min-height: 90px; resize: vertical; }

        /* ── PHOTO SECTION ── */
        .photo-section {
            background: #f8faff;
            border: 2px dashed #bfdbfe;
            border-radius: 10px;
            padding: 22px;
            text-align: center;
        }
        .photo-section input[type="file"] {
            background: white;
            border: 1.5px solid #e8eeff;
            border-radius: 8px;
            cursor: pointer;
            padding: 8px;
        }
        .photo-section p {
            font-size: 12px;
            color: #9ca3af;
            margin-top: 8px;
        }

        .remove-photo-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 12px;
            justify-content: center;
            font-size: 14px;
            color: #dc2626;
        }
        .remove-photo-row input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: #dc2626;
        }

        /* ── BUTTONS ── */
        .btn-row {
            display: flex;
            gap: 14px;
            margin-top: 32px;
        }
        .btn-save {
            flex: 1;
            padding: 14px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 700;
            font-family: 'Inter', Arial, sans-serif;
            cursor: pointer;
            transition: all 0.22s;
            box-shadow: 0 4px 14px rgba(29,78,216,0.2);
        }
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(29,78,216,0.3);
        }

        .btn-cancel {
            flex: 1;
            padding: 14px;
            background: #ffffff;
            color: #4b6cb7;
            border: 2px solid #e8eeff;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            text-align: center;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        .btn-cancel:hover {
            background: #eff6ff;
            border-color: #3b82f6;
            color: #1d4ed8;
        }

        @media(max-width: 600px){
            .navbar { padding: 0 16px; }
            .form-row { grid-template-columns: 1fr; }
            .card { padding: 24px; }
            .container { padding: 0 14px; }
        }
    </style>
</head>
<body>

<div class="navbar">
    <h2>👨‍👩‍👧 Parent Dashboard</h2>
    <div>
        <a href="dashboard.php">Dashboard</a>
        <a href="my_children.php">My Children</a>
        <a href="book_appointment.php">Book Appointment</a>
        <a href="myprofile.php">Profile</a>
        <a href="../logout.php" class="logout">Logout</a>
    </div>
</div>

<div class="container">
    <a href="child_details.php?id=<?php echo $child_id; ?>" class="back-link">← Back to Child Details</a>

    <div class="card">

        <!-- Header -->
        <div class="card-header">
            <?php if(!empty($child['photo_url'])): ?>
                <img src="../<?php echo htmlspecialchars($child['photo_url']); ?>" class="current-photo" alt="Photo">
            <?php else: ?>
                <div class="current-photo-placeholder">👶</div>
            <?php endif; ?>
            <div class="card-header-info">
                <h2>✏️ Edit Child Info</h2>
                <p>Updating: <strong><?php echo htmlspecialchars($child['full_name']); ?></strong></p>
            </div>
        </div>

        <!-- Alerts -->
        <?php if($success_msg): ?>
            <div class="alert alert-success">✅ <?php echo $success_msg; ?></div>
        <?php endif; ?>
        <?php if($error_msg): ?>
            <div class="alert alert-error">❌ <?php echo $error_msg; ?></div>
        <?php endif; ?>

        <!-- Form -->
        <form action="edit_child.php?id=<?php echo $child_id; ?>" method="post" enctype="multipart/form-data">

            <!-- Basic Info -->
            <div class="section-title">👤 Basic Information</div>
            <div class="form-row">
                <div class="form-group full">
                    <label>Child's Full Name <span class="req">*</span></label>
                    <input type="text" name="full_name" value="<?php echo htmlspecialchars($child['full_name']); ?>" required>
                </div>

                <div class="form-group">
                    <label>Date of Birth <span class="req">*</span></label>
                    <input type="date" name="date_of_birth"
                           value="<?php echo $child['date_of_birth']; ?>"
                           max="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="form-group">
                    <label>Gender <span class="req">*</span></label>
                    <select name="gender" required>
                        <option value="">Select Gender</option>
                        <option value="Male"   <?php echo $child['gender'] == 'Male'   ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo $child['gender'] == 'Female' ? 'selected' : ''; ?>>Female</option>
                        <option value="Other"  <?php echo $child['gender'] == 'Other'  ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Blood Group</label>
                    <select name="blood_group">
                        <option value="">Select Blood Group</option>
                        <?php foreach(['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $bg): ?>
                        <option value="<?php echo $bg; ?>" <?php echo $child['blood_group'] == $bg ? 'selected' : ''; ?>><?php echo $bg; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Birth Weight (kg)</label>
                    <input type="number" name="birth_weight" step="0.01" min="0" max="20"
                           placeholder="e.g., 3.2"
                           value="<?php echo htmlspecialchars($child['birth_weight'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>Birth Height (cm)</label>
                    <input type="number" name="birth_height" step="0.01" min="0" max="100"
                           placeholder="e.g., 50"
                           value="<?php echo htmlspecialchars($child['birth_height'] ?? ''); ?>">
                </div>
            </div>

            <!-- Medical Info -->
            <div class="section-title">🏥 Medical Information</div>
            <div class="form-group">
                <label>Medical Conditions (if any)</label>
                <textarea name="medical_conditions" placeholder="Enter any medical conditions or leave blank"><?php echo htmlspecialchars($child['medical_conditions'] ?? ''); ?></textarea>
            </div>
            <div class="form-group">
                <label>Allergies (if any)</label>
                <textarea name="allergies" placeholder="Enter any allergies or leave blank"><?php echo htmlspecialchars($child['allergies'] ?? ''); ?></textarea>
            </div>

            <!-- Photo -->
            <div class="section-title">📷 Profile Photo</div>
            <div class="photo-section">
                <?php if(!empty($child['photo_url'])): ?>
                    <img src="../<?php echo htmlspecialchars($child['photo_url']); ?>"
                         style="width:80px;height:80px;border-radius:50%;object-fit:cover;margin-bottom:12px;border:3px solid #3b82f6;" alt="Current Photo">
                    <br>
                <?php endif; ?>
                <input type="file" name="photo" accept="image/*">
                <p>Upload a new photo to replace the current one (JPG, PNG, GIF, WEBP)</p>

                <?php if(!empty($child['photo_url'])): ?>
                <div class="remove-photo-row" style="justify-content:center;">
                    <input type="checkbox" name="remove_photo" id="remove_photo">
                    <label for="remove_photo" style="font-weight:normal;margin:0;color:#dc3545;cursor:pointer;">Remove current photo</label>
                </div>
                <?php endif; ?>
            </div>

            <!-- Buttons -->
            <div class="btn-row">
                <a href="child_details.php?id=<?php echo $child_id; ?>" class="btn-cancel">Cancel</a>
                <button type="submit" name="update_child" class="btn-save">💾 Save Changes</button>
            </div>

        </form>
    </div>
</div>

</body>
</html>