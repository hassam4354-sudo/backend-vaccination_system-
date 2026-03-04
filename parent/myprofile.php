<?php
session_start();
if(!isset($_SESSION["logged_in"]) || $_SESSION["user_type"] != "parent"){
    header("location:../login.php");
    exit();
}

include("../dbconnection.php");

$user_id = $_SESSION["user_id"];

$query = "SELECT p.*, u.email, u.phone, u.created_at as joined_on
          FROM parents p
          JOIN users u ON p.user_id = u.user_id
          WHERE p.user_id = '$user_id'";
$result = mysqli_query($connection, $query);
$profile = mysqli_fetch_assoc($result);

$parent_id = $profile['parent_id'];
$q_children = "SELECT COUNT(*) as total FROM children WHERE parent_id = '$parent_id' AND is_active = 1";
$children_count = mysqli_fetch_assoc(mysqli_query($connection, $q_children))['total'];

$q_vac = "SELECT COUNT(*) as total FROM vaccination_bookings vb
           JOIN children c ON vb.child_id = c.child_id
           WHERE c.parent_id = '$parent_id' AND vb.booking_status = 'completed'";
$vac_count = mysqli_fetch_assoc(mysqli_query($connection, $q_vac))['total'];

$q_pending = "SELECT COUNT(*) as total FROM appointment_requests ar
              JOIN children c ON ar.child_id = c.child_id
              WHERE c.parent_id = '$parent_id' AND ar.request_status = 'pending'";
$pending_count = mysqli_fetch_assoc(mysqli_query($connection, $q_pending))['total'];

$success_msg = "";
$error_msg = "";

if(isset($_POST['update_profile'])) {
    $full_name         = sanitize_input($_POST['full_name']);
    $emergency_contact = sanitize_input($_POST['emergency_contact']);
    $address           = sanitize_input($_POST['address']);
    $city              = sanitize_input($_POST['city']);
    $state             = sanitize_input($_POST['state']);
    $postal_code       = sanitize_input($_POST['postal_code']);
    $phone             = sanitize_input($_POST['phone']);

    $q_update = "UPDATE parents SET full_name='$full_name', emergency_contact='$emergency_contact',
                 address='$address', city='$city', state='$state', postal_code='$postal_code'
                 WHERE user_id='$user_id'";
    $run1 = mysqli_query($connection, $q_update);
    $q_update_user = "UPDATE users SET phone='$phone' WHERE user_id='$user_id'";
    $run2 = mysqli_query($connection, $q_update_user);

    if($run1 && $run2) {
        log_audit($user_id, 'UPDATE_PROFILE', 'parents', $parent_id, "Updated profile");
        $result2 = mysqli_query($connection, $query);
        $profile  = mysqli_fetch_assoc($result2);
        $success_msg = "Profile updated successfully!";
    } else {
        $error_msg = "Error: " . mysqli_error($connection);
    }
}

if(isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password     = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    $q_pass = "SELECT password FROM users WHERE user_id = '$user_id'";
    $r_pass  = mysqli_fetch_assoc(mysqli_query($connection, $q_pass));

    if(!password_verify($current_password, $r_pass['password'])) {
        $error_msg = "Current password is incorrect.";
    } elseif($new_password !== $confirm_password) {
        $error_msg = "New passwords do not match.";
    } elseif(strlen($new_password) < 6) {
        $error_msg = "New password must be at least 6 characters.";
    } else {
        $hashed = password_hash($new_password, PASSWORD_DEFAULT);
        $q_upd  = "UPDATE users SET password='$hashed' WHERE user_id='$user_id'";
        if(mysqli_query($connection, $q_upd)) {
            log_audit($user_id, 'CHANGE_PASSWORD', 'users', $user_id, "Password changed");
            $success_msg = "Password changed successfully!";
        } else {
            $error_msg = "Error changing password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile</title>
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
        .container { max-width: 1000px; margin: 32px auto; padding: 0 24px; }

        /* ── PROFILE HERO ── */
        .profile-hero {
            background: linear-gradient(135deg, #1d4ed8 0%, #3b82f6 60%, #60a5fa 100%);
            border-radius: 18px;
            padding: 30px 36px;
            color: white;
            display: flex;
            align-items: center;
            gap: 28px;
            margin-bottom: 24px;
            box-shadow: 0 8px 32px rgba(59,130,246,0.3);
            position: relative;
            overflow: hidden;
        }
        .profile-hero::before {
            content: '';
            position: absolute;
            top: -40px; right: -40px;
            width: 200px; height: 200px;
            background: rgba(255,255,255,0.08);
            border-radius: 50%;
        }
        .profile-hero::after {
            content: '';
            position: absolute;
            bottom: -50px; right: 120px;
            width: 150px; height: 150px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
        }
        .avatar {
            width: 90px; height: 90px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex; align-items: center; justify-content: center;
            font-size: 44px; flex-shrink: 0;
            border: 3px solid rgba(255,255,255,0.4);
            position: relative; z-index: 1;
        }
        .hero-info { position: relative; z-index: 1; }
        .hero-info h2 { font-size: 24px; font-weight: 700; margin-bottom: 5px; }
        .hero-info p  { font-size: 13.5px; opacity: 0.88; margin-bottom: 3px; }
        .joined-badge {
            display: inline-block;
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.3);
            padding: 4px 14px; border-radius: 20px;
            font-size: 12px; margin-top: 8px;
        }

        /* ── STATS ── */
        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: white;
            border-radius: 14px;
            padding: 20px 22px;
            box-shadow: 0 2px 10px rgba(59,130,246,0.07);
            border: 1px solid #e8eeff;
            display: flex;
            align-items: center;
            gap: 16px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 22px rgba(59,130,246,0.13);
        }
        .stat-icon {
            width: 50px; height: 50px;
            border-radius: 12px;
            background: #eff6ff;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; flex-shrink: 0;
        }
        .stat-num  { font-size: 30px; font-weight: 700; color: #1d4ed8; line-height: 1; margin-bottom: 3px; }
        .stat-label{ font-size: 13px; color: #6b7280; font-weight: 500; }

        /* ── TABS ── */
        .tabs {
            display: flex;
            background: white;
            border-radius: 12px 12px 0 0;
            border: 1px solid #e8eeff;
            border-bottom: none;
            overflow: hidden;
        }
        .tab-btn {
            flex: 1;
            padding: 14px 20px;
            border: none;
            background: white;
            cursor: pointer;
            font-size: 13.5px;
            font-weight: 600;
            color: #6b7280;
            border-bottom: 3px solid transparent;
            transition: all 0.2s;
            font-family: 'Inter', Arial, sans-serif;
        }
        .tab-btn:hover  { color: #1d4ed8; background: #f8faff; }
        .tab-btn.active { color: #1d4ed8; border-bottom-color: #1d4ed8; background: #f8faff; }

        /* ── CARD ── */
        .card {
            background: white;
            border-radius: 0 0 14px 14px;
            border: 1px solid #e8eeff;
            border-top: none;
            box-shadow: 0 4px 16px rgba(59,130,246,0.07);
            padding: 32px;
            margin-bottom: 24px;
        }

        .tab-panel { display: none; }
        .tab-panel.active { display: block; }

        /* ── ALERTS ── */
        .alert {
            padding: 13px 18px;
            border-radius: 10px;
            margin-bottom: 22px;
            font-size: 14px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid #10b981; }
        .alert-error   { background: #fee2e2; color: #991b1b; border-left: 4px solid #ef4444; }

        /* ── SECTION TITLE ── */
        .section-title {
            font-size: 12px;
            font-weight: 700;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            margin: 22px 0 14px;
            padding-bottom: 8px;
            border-bottom: 1px solid #f1f5ff;
        }
        .section-title:first-child { margin-top: 0; }

        /* ── INFO GRID ── */
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
        .info-item {
            background: #f8faff;
            border: 1px solid #e8eeff;
            border-radius: 10px;
            padding: 14px 18px;
        }
        .info-item label {
            display: block;
            font-size: 11px; color: #9ca3af;
            text-transform: uppercase; letter-spacing: 0.5px;
            margin-bottom: 5px; font-weight: 600;
        }
        .info-item span { font-size: 15px; color: #1a1a2e; font-weight: 600; display: block; }
        .info-item.full { grid-column: 1 / -1; }

        /* ── FORM ── */
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-group { margin-bottom: 0; }
        .form-group.full { grid-column: 1 / -1; }

        .field-label {
            display: block;
            margin-bottom: 6px;
            color: #374151; font-weight: 600; font-size: 13px;
        }
        .field-label .req { color: #ef4444; margin-left: 2px; }

        input[type="text"],
        input[type="email"],
        input[type="tel"],
        input[type="password"] {
            width: 100%;
            padding: 11px 14px;
            border: 1.5px solid #e8eeff;
            border-radius: 10px;
            font-size: 14px; color: #1a1a2e;
            background: #fafbff;
            transition: all 0.2s;
            font-family: 'Inter', Arial, sans-serif;
            outline: none;
        }
        input:focus { border-color: #3b82f6; background: white; box-shadow: 0 0 0 3px rgba(59,130,246,0.1); }
        input[readonly] { background: #f1f5f9; color: #9ca3af; cursor: not-allowed; border-color: #e2e8f0; }

        .btn-save {
            width: 100%;
            padding: 13px;
            background: linear-gradient(135deg, #1d4ed8, #3b82f6);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 15px; font-weight: 700;
            cursor: pointer;
            margin-top: 20px;
            font-family: 'Inter', Arial, sans-serif;
            box-shadow: 0 4px 14px rgba(29,78,216,0.25);
            transition: all 0.2s;
        }
        .btn-save:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(29,78,216,0.35); }

        /* ── PASSWORD STRENGTH ── */
        .strength-bar { height: 5px; border-radius: 3px; background: #e8eeff; margin-top: 8px; overflow: hidden; }
        .strength-fill { height: 100%; width: 0; border-radius: 3px; transition: width 0.3s, background 0.3s; }
        .strength-text { font-size: 12px; margin-top: 5px; color: #9ca3af; }

        /* ── RESPONSIVE ── */
        @media(max-width: 650px) {
            .navbar { padding: 0 16px; }
            .navbar-brand h2 { display: none; }
            .container { padding: 0 14px; }
            .profile-hero { flex-direction: column; text-align: center; }
            .stats { grid-template-columns: 1fr; }
            .form-row, .info-grid { grid-template-columns: 1fr; }
            .form-group.full, .info-item.full { grid-column: 1; }
            .card { padding: 20px; }
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
        <a href="book_appointment.php"> Book</a>
        <a href="vaccinationhistory.php"> History</a>
        <a href="myprofile.php" class="active"> Profile</a>
        <a href="../logout.php" class="logout"> Logout</a>
    </div>
</div>

<div class="container">

    <!-- ── PROFILE HERO ── -->
    <div class="profile-hero">
        <div class="avatar">👤</div>
        <div class="hero-info">
            <h2><?php echo htmlspecialchars($profile['full_name']); ?></h2>
            <p>📧 <?php echo htmlspecialchars($profile['email']); ?></p>
            <?php if(!empty($profile['phone'])): ?>
            <p>📞 <?php echo htmlspecialchars($profile['phone']); ?></p>
            <?php endif; ?>
            <?php if(!empty($profile['city'])): ?>
            <p>📍 <?php echo htmlspecialchars($profile['city']); ?><?php echo !empty($profile['state']) ? ', ' . htmlspecialchars($profile['state']) : ''; ?></p>
            <?php endif; ?>
            <span class="joined-badge">📅 Member since <?php echo date('M Y', strtotime($profile['joined_on'])); ?></span>
        </div>
    </div>

    <!-- ── STATS ── -->
    <div class="stats">
        <div class="stat-card">
            <div class="stat-icon"></div>
            <div>
                <div class="stat-num"><?php echo $children_count; ?></div>
                <div class="stat-label">Registered Children</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"></div>
            <div>
                <div class="stat-num"><?php echo $vac_count; ?></div>
                <div class="stat-label">Vaccinations Done</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"></div>
            <div>
                <div class="stat-num"><?php echo $pending_count; ?></div>
                <div class="stat-label">Pending Requests</div>
            </div>
        </div>
    </div>

    <!-- ── TABS ── -->
    <div class="tabs">
        <button class="tab-btn active" onclick="showTab('overview', this)">👁️ Overview</button>
        <button class="tab-btn" onclick="showTab('edit', this)">✏️ Edit Profile</button>
        <button class="tab-btn" onclick="showTab('password', this)">🔒 Change Password</button>
    </div>

    <div class="card">

        <?php if($success_msg): ?>
            <div class="alert alert-success">✅ <?php echo $success_msg; ?></div>
        <?php endif; ?>
        <?php if($error_msg): ?>
            <div class="alert alert-error">❌ <?php echo $error_msg; ?></div>
        <?php endif; ?>

        <!-- ── OVERVIEW ── -->
        <div id="tab-overview" class="tab-panel active">
            <div class="section-title">👤 Personal Information</div>
            <div class="info-grid">
                <div class="info-item">
                    <label>Full Name</label>
                    <span><?php echo htmlspecialchars($profile['full_name']); ?></span>
                </div>
                <div class="info-item">
                    <label>Email</label>
                    <span><?php echo htmlspecialchars($profile['email']); ?></span>
                </div>
                <div class="info-item">
                    <label>Phone</label>
                    <span><?php echo !empty($profile['phone']) ? htmlspecialchars($profile['phone']) : '—'; ?></span>
                </div>
                <div class="info-item">
                    <label>Emergency Contact</label>
                    <span><?php echo !empty($profile['emergency_contact']) ? htmlspecialchars($profile['emergency_contact']) : '—'; ?></span>
                </div>
            </div>

            <div class="section-title" style="margin-top:22px;">📍 Address Information</div>
            <div class="info-grid">
                <div class="info-item full">
                    <label>Street Address</label>
                    <span><?php echo !empty($profile['address']) ? htmlspecialchars($profile['address']) : '—'; ?></span>
                </div>
                <div class="info-item">
                    <label>City</label>
                    <span><?php echo !empty($profile['city']) ? htmlspecialchars($profile['city']) : '—'; ?></span>
                </div>
                <div class="info-item">
                    <label>State / Province</label>
                    <span><?php echo !empty($profile['state']) ? htmlspecialchars($profile['state']) : '—'; ?></span>
                </div>
                <div class="info-item">
                    <label>Postal Code</label>
                    <span><?php echo !empty($profile['postal_code']) ? htmlspecialchars($profile['postal_code']) : '—'; ?></span>
                </div>
            </div>
        </div>

        <!-- ── EDIT PROFILE ── -->
        <div id="tab-edit" class="tab-panel">
            <form action="myprofile.php" method="post">
                <div class="section-title">👤 Personal Information</div>
                <div class="form-row">
                    <div class="form-group full">
                        <label class="field-label">Full Name <span class="req">*</span></label>
                        <input type="text" name="full_name" value="<?php echo htmlspecialchars($profile['full_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="field-label">Email (cannot change)</label>
                        <input type="email" value="<?php echo htmlspecialchars($profile['email']); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label class="field-label">Phone Number</label>
                        <input type="tel" name="phone" placeholder="03001234567" value="<?php echo htmlspecialchars($profile['phone'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label class="field-label">Emergency Contact <span class="req">*</span></label>
                        <input type="tel" name="emergency_contact" placeholder="03001234567" value="<?php echo htmlspecialchars($profile['emergency_contact'] ?? ''); ?>" required>
                    </div>
                </div>

                <div class="section-title">📍 Address Information</div>
                <div class="form-row">
                    <div class="form-group full">
                        <label class="field-label">Street Address <span class="req">*</span></label>
                        <input type="text" name="address" value="<?php echo htmlspecialchars($profile['address'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="field-label">City <span class="req">*</span></label>
                        <input type="text" name="city" value="<?php echo htmlspecialchars($profile['city'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="field-label">State / Province <span class="req">*</span></label>
                        <input type="text" name="state" value="<?php echo htmlspecialchars($profile['state'] ?? ''); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="field-label">Postal Code <span class="req">*</span></label>
                        <input type="text" name="postal_code" value="<?php echo htmlspecialchars($profile['postal_code'] ?? ''); ?>" required>
                    </div>
                </div>
                <button type="submit" name="update_profile" class="btn-save">💾 Save Changes</button>
            </form>
        </div>

        <!-- ── CHANGE PASSWORD ── -->
        <div id="tab-password" class="tab-panel">
            <form action="myprofile.php" method="post">
                <div class="section-title">🔒 Change Password</div>
                <div style="display:flex; flex-direction:column; gap:16px;">
                    <div>
                        <label class="field-label">Current Password <span class="req">*</span></label>
                        <input type="password" name="current_password" placeholder="Enter current password" required>
                    </div>
                    <div>
                        <label class="field-label">New Password <span class="req">*</span></label>
                        <input type="password" name="new_password" id="new_password"
                               placeholder="Minimum 6 characters" required oninput="checkStrength(this.value)">
                        <div class="strength-bar"><div class="strength-fill" id="strength-fill"></div></div>
                        <div class="strength-text" id="strength-text">Enter a new password</div>
                    </div>
                    <div>
                        <label class="field-label">Confirm New Password <span class="req">*</span></label>
                        <input type="password" name="confirm_password" placeholder="Re-enter new password" required>
                    </div>
                </div>
                <button type="submit" name="change_password" class="btn-save">🔑 Change Password</button>
            </form>
        </div>

    </div>
</div>

<script>
    function showTab(tabName, btn) {
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('tab-' + tabName).classList.add('active');
        btn.classList.add('active');
    }

    <?php if(isset($_POST['update_profile']) && $error_msg): ?>
    document.querySelectorAll('.tab-btn')[1].click();
    <?php endif; ?>
    <?php if(isset($_POST['change_password']) && $error_msg): ?>
    document.querySelectorAll('.tab-btn')[2].click();
    <?php endif; ?>

    function checkStrength(val) {
        const fill = document.getElementById('strength-fill');
        const text = document.getElementById('strength-text');
        let score = 0;
        if(val.length >= 6)  score++;
        if(val.length >= 10) score++;
        if(/[A-Z]/.test(val)) score++;
        if(/[0-9]/.test(val)) score++;
        if(/[^A-Za-z0-9]/.test(val)) score++;
        const levels = [
            { w: '0%',   color: '#e8eeff', label: 'Enter a new password' },
            { w: '25%',  color: '#ef4444', label: '🔴 Weak' },
            { w: '50%',  color: '#f59e0b', label: '🟡 Fair' },
            { w: '75%',  color: '#3b82f6', label: '🔵 Good' },
            { w: '90%',  color: '#10b981', label: '🟢 Strong' },
            { w: '100%', color: '#065f46', label: '✅ Very Strong' },
        ];
        const lvl = levels[Math.min(score, 5)];
        fill.style.width = lvl.w;
        fill.style.background = lvl.color;
        text.textContent = lvl.label;
        text.style.color = lvl.color;
    }
</script>
</body>
</html>