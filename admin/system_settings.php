<?php
session_start();
if(!isset($_SESSION["logged_in"]) || $_SESSION["user_type"] != "admin"){
    header("location:../login.php");
    exit();
}

include("../dbconnection.php");

$user_id = $_SESSION["user_id"];

// Get admin details
$query_admin = "SELECT a.full_name, u.email FROM admins a JOIN users u ON a.user_id = u.user_id WHERE a.user_id = '$user_id'";
$result_admin = mysqli_query($connection, $query_admin);
$admin_data = mysqli_fetch_assoc($result_admin);
$admin_name = $admin_data['full_name'];
$admin_email = $admin_data['email'];

$success_msg = '';
$error_msg = '';

// ===================== HANDLE FORM SUBMISSIONS =====================

// 1. Update Admin Profile
if(isset($_POST['update_profile'])) {
    $full_name = sanitize_input($_POST['full_name']);
    $email     = sanitize_input($_POST['email']);
    $phone     = sanitize_input($_POST['phone']);

    // Update users table
    $q1 = "UPDATE users SET email='$email', phone='$phone' WHERE user_id='$user_id'";
    // Update admins table
    $q2 = "UPDATE admins SET full_name='$full_name' WHERE user_id='$user_id'";

    if(mysqli_query($connection, $q1) && mysqli_query($connection, $q2)){
        log_audit($user_id, 'UPDATE', 'admins', $user_id, 'Admin profile updated');
        $success_msg = "Profile updated successfully!";
        $admin_name = $full_name;
    } else {
        $error_msg = "Failed to update profile: " . mysqli_error($connection);
    }
}

// 2. Change Password
if(isset($_POST['change_password'])) {
    $current_password  = $_POST['current_password'];
    $new_password      = $_POST['new_password'];
    $confirm_password  = $_POST['confirm_password'];

    // Get current hash
    $qpwd = "SELECT password_hash FROM users WHERE user_id='$user_id'";
    $rpwd = mysqli_query($connection, $qpwd);
    $udata = mysqli_fetch_assoc($rpwd);

    if(!password_verify($current_password, $udata['password_hash'])){
        $error_msg = "Current password is incorrect!";
    } elseif($new_password !== $confirm_password){
        $error_msg = "New passwords do not match!";
    } elseif(strlen($new_password) < 6){
        $error_msg = "Password must be at least 6 characters!";
    } else {
        $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $qupdate = "UPDATE users SET password_hash='$new_hash' WHERE user_id='$user_id'";
        if(mysqli_query($connection, $qupdate)){
            log_audit($user_id, 'UPDATE', 'users', $user_id, 'Admin changed password');
            $success_msg = "Password changed successfully!";
        } else {
            $error_msg = "Failed to change password!";
        }
    }
}

// 3. Update Site Settings (stored in a simple settings table if exists, else in config.php info)
// We'll store settings in a session-based display only (no settings table assumed)

// Fetch current admin full info
$q_info = "SELECT a.full_name, a.role, u.email, u.phone FROM admins a JOIN users u ON a.user_id = u.user_id WHERE a.user_id='$user_id'";
$r_info = mysqli_query($connection, $q_info);
$info = mysqli_fetch_assoc($r_info);

// Get system stats for info panel
$total_users  = mysqli_fetch_assoc(mysqli_query($connection, "SELECT COUNT(*) as c FROM users"))['c'];
$total_admins = mysqli_fetch_assoc(mysqli_query($connection, "SELECT COUNT(*) as c FROM admins"))['c'];
$total_logs   = mysqli_fetch_assoc(mysqli_query($connection, "SELECT COUNT(*) as c FROM audit_logs"))['c'];
$db_version   = mysqli_fetch_assoc(mysqli_query($connection, "SELECT VERSION() as v"))['v'];

// Get recent audit logs
$q_logs = "SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 10";
$r_logs = mysqli_query($connection, $q_logs);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Admin Panel</title>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Animate.css -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">

    <style>
        * {
            margin: 0; padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        :root {
            --primary: #1a6fc4;
            --secondary: #1155a0;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f8961e;
            --light: #f5f7fa;
            --dark: #212529;
            --gray: #6b7a99;
            --border-radius: 16px;
            --box-shadow: 0 10px 30px rgba(26,111,196,0.10);
            --transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }

        body {
            background: linear-gradient(135deg, #e8f1fb 0%, #dceeff 100%);
            min-height: 100vh;
            color: #333;
        }

        /* ===== LAYOUT ===== */
        .dashboard-layout {
            display: block;
            min-height: 100vh;
        }

        /* ===== TOP NAVBAR ===== */
        .admin-navbar {
            background: #ffffff;
            border-bottom: 2px solid #e8eeff;
            padding: 0 35px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 68px;
            box-shadow: 0 2px 16px rgba(26,111,196,0.08);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .admin-navbar .logo { display: flex; align-items: center; gap: 10px; }
        .admin-navbar .logo-icon {
            width: 40px; height: 40px;
            background: linear-gradient(135deg, #1a6fc4, #1155a0);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 20px;
        }
        .admin-navbar .logo h2 { font-size: 20px; font-weight: 700; color: #1155a0; letter-spacing: -0.3px; }
        .nav-links { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
        .nav-links a {
            color: #4b6cb7; text-decoration: none; padding: 8px 14px;
            border-radius: 8px; font-size: 13.5px; font-weight: 500;
            transition: all 0.2s; display: flex; align-items: center; gap: 6px;
        }
        .nav-links a:hover { background: #eff6ff; color: #1155a0; }
        .nav-links a.active { background: #dbeafe; color: #1155a0; font-weight: 600; }
        .nav-links a.logout { background: #fee2e2; color: #dc2626; }
        .nav-links a.logout:hover { background: #fecaca; }

        /* ===== MAIN CONTENT ===== */
        .main-content { padding: 30px; overflow-y: auto; }

        .page-header { margin-bottom: 30px; }

        .page-header h1 { color: var(--secondary); font-size: 2rem; margin-bottom: 8px; }
        .page-header p  { color: var(--gray); font-size: 1rem; }

        /* ===== ALERT MESSAGES ===== */
        .alert {
            padding: 14px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex; align-items: center; gap: 10px;
            animation: fadeInDown 0.4s ease;
        }

        .alert-success { background: #d1fae5; color: #065f46; border-left: 4px solid var(--success); }
        .alert-error   { background: #ffe4e6; color: #9f1239; border-left: 4px solid var(--danger); }

        /* ===== TABS ===== */
        .settings-tabs {
            display: flex; gap: 5px;
            background: rgba(26,111,196,0.08);
            border-radius: 14px;
            padding: 6px;
            margin-bottom: 25px;
            border: 1px solid rgba(26,111,196,0.12);
            flex-wrap: wrap;
        }

        .tab-btn {
            flex: 1;
            min-width: 120px;
            padding: 11px 20px;
            border: none; border-radius: 10px;
            background: transparent;
            color: var(--gray);
            cursor: pointer;
            transition: var(--transition);
            font-size: 13px;
            font-weight: 500;
            display: flex; align-items: center; gap: 8px;
            justify-content: center;
        }

        .tab-btn:hover  { background: rgba(26,111,196,0.1); color: var(--primary); }

        .tab-btn.active {
            background: var(--primary);
            color: white;
            box-shadow: 0 4px 12px rgba(26,111,196,0.3);
        }

        /* ===== CARDS ===== */
        .card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            margin-bottom: 25px;
            overflow: hidden;
        }

        .card-header {
            padding: 20px 25px;
            border-bottom: 1px solid #f1f5f9;
            display: flex; align-items: center; gap: 12px;
        }

        .card-header-icon {
            width: 40px; height: 40px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        .icon-blue   { background: rgba(26,111,196,0.1);  color: var(--primary); }
        .icon-green  { background: rgba(16,185,129,0.1); color: var(--success); }
        .icon-red    { background: rgba(239,68,68,0.1);  color: var(--danger);  }
        .icon-orange { background: rgba(248,150,30,0.1); color: var(--warning); }
        .icon-purple { background: rgba(26,111,196,0.1); color: var(--primary); }

        .card-header h3 { font-size: 16px; color: var(--dark); margin: 0; }
        .card-header p  { font-size: 12px; color: var(--gray); margin: 0; }

        .card-body { padding: 25px; }

        /* ===== FORM ===== */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group.full-width { grid-column: 1 / -1; }

        .form-group label {
            font-size: 13px; font-weight: 600;
            color: #374151;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            padding: 11px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            transition: var(--transition);
            outline: none;
            color: var(--dark);
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(26,111,196,0.1);
        }

        .form-group textarea { resize: vertical; min-height: 90px; }

        .btn {
            padding: 11px 24px;
            border: none; border-radius: 10px;
            cursor: pointer;
            font-size: 14px; font-weight: 600;
            transition: var(--transition);
            display: inline-flex; align-items: center; gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #3b8de0, var(--primary));
            color: white;
            box-shadow: 0 4px 15px rgba(26,111,196,0.3);
        }

        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(26,111,196,0.4); }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger), #dc2626);
            color: white;
            box-shadow: 0 4px 15px rgba(239,68,68,0.3);
        }

        .btn-danger:hover { transform: translateY(-2px); }

        .btn-warning {
            background: linear-gradient(135deg, var(--warning), #f3722c);
            color: white;
        }

        .btn-warning:hover { transform: translateY(-2px); }

        /* ===== INFO CARDS ===== */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .info-item {
            background: #f0f6ff;
            border-radius: 12px;
            padding: 18px;
            border-left: 4px solid var(--primary);
            transition: var(--transition);
        }

        .info-item:hover { transform: translateY(-3px); box-shadow: var(--box-shadow); }
        .info-item h4 { font-size: 11px; color: var(--gray); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
        .info-item p  { font-size: 20px; font-weight: 700; color: var(--dark); }
        .info-item span { font-size: 11px; color: var(--gray); }

        /* ===== TABLE ===== */
        .table-wrapper { overflow-x: auto; }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        thead th {
            background: #f8fafc;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: var(--gray);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        tbody tr { border-bottom: 1px solid #f1f5f9; transition: var(--transition); }
        tbody tr:hover { background: #f8fafc; }
        tbody td { padding: 12px 15px; color: var(--dark); }

        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-blue   { background: rgba(26,111,196,0.1);  color: var(--primary); }
        .badge-green  { background: rgba(16,185,129,0.1); color: var(--success); }
        .badge-red    { background: rgba(239,68,68,0.1);  color: var(--danger);  }
        .badge-orange { background: rgba(248,150,30,0.1); color: var(--warning); }

        /* ===== TOGGLE ===== */
        .toggle-row {
            display: flex; align-items: center; justify-content: space-between;
            padding: 16px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .toggle-row:last-child { border-bottom: none; }
        .toggle-info h4  { font-size: 14px; color: var(--dark); margin-bottom: 3px; }
        .toggle-info p   { font-size: 12px; color: var(--gray); }

        .switch { position: relative; display: inline-block; width: 50px; height: 26px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute; cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background: #e2e8f0; border-radius: 26px;
            transition: 0.4s;
        }
        .slider:before {
            content: "";
            position: absolute;
            height: 20px; width: 20px;
            left: 3px; bottom: 3px;
            background: white;
            border-radius: 50%;
            transition: 0.4s;
        }
        input:checked + .slider { background: var(--primary); }
        input:checked + .slider:before { transform: translateX(24px); }

        /* ===== TAB PANELS ===== */
        .tab-panel { display: none; animation: fadeIn 0.3s ease; }
        .tab-panel.active { display: block; }

        @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes fadeInDown { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        /* ===== DANGER ZONE ===== */
        .danger-zone {
            border: 2px dashed rgba(239,68,68,0.3);
            border-radius: 14px;
            padding: 20px;
            background: rgba(239,68,68,0.03);
        }

        .danger-zone h4 { color: var(--danger); margin-bottom: 5px; font-size: 14px; }
        .danger-zone p  { color: var(--gray); font-size: 12px; margin-bottom: 15px; }

        /* responsive */
        @media(max-width: 768px){
            .admin-navbar { padding: 0 16px; height: auto; flex-wrap: wrap; padding-top: 12px; padding-bottom: 12px; }
            .nav-links { gap: 4px; }
            .main-content { padding: 20px; }
        }
    </style>
</head>
<body>
<div class="dashboard-layout">

    <!-- ===== TOP NAVBAR ===== -->
    <nav class="admin-navbar">
        <div class="logo">
            <div class="logo-icon">🛡️</div>
            <h2>Admin Panel</h2>
        </div>
        <div class="nav-links">
            <a href="dashboard.php"> Dashboard</a>
            <a href="manage_children.php"> Children</a>
            <a href="manage_hospitals.php"> Hospitals</a>
            <a href="appointment_requests.php"> Requests</a>
            <a href="managevaccines.php"> Vaccines</a>
            <a href="bookingdetail.php"> Bookings</a>
            <a href="vaccination_reports.php"> Reports</a>
            <a href="system_settings.php" class="active"> Settings</a>
            <a href="../logout.php" class="logout"> Logout</a>
        </div>
    </nav>

    <!-- ===== MAIN CONTENT ===== -->
    <main class="main-content">

        <!-- Header -->
        <div class="page-header animate__animated animate__fadeInDown">
            <h1><i class="fas fa-cog"></i> System Settings</h1>
            <p>Manage your profile, security, and system preferences</p>
        </div>

        <!-- Alert Messages -->
        <?php if($success_msg): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_msg); ?>
            </div>
        <?php endif; ?>
        <?php if($error_msg): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_msg); ?>
            </div>
        <?php endif; ?>

        <!-- Tabs -->
        <div class="settings-tabs">
            <button class="tab-btn active" onclick="showTab('profile', this)">
                <i class="fas fa-user"></i> Profile
            </button>
            <button class="tab-btn" onclick="showTab('security', this)">
                <i class="fas fa-lock"></i> Security
            </button>
            <button class="tab-btn" onclick="showTab('system', this)">
                <i class="fas fa-server"></i> System Info
            </button>
            <button class="tab-btn" onclick="showTab('preferences', this)">
                <i class="fas fa-sliders-h"></i> Preferences
            </button>
            <button class="tab-btn" onclick="showTab('logs', this)">
                <i class="fas fa-history"></i> Activity Logs
            </button>
        </div>

        <!-- ============================= -->
        <!-- TAB 1: PROFILE               -->
        <!-- ============================= -->
        <div id="tab-profile" class="tab-panel active">
            <div class="card animate__animated animate__fadeInUp">
                <div class="card-header">
                    <div class="card-header-icon icon-blue"><i class="fas fa-user-edit"></i></div>
                    <div>
                        <h3>Admin Profile</h3>
                        <p>Update your personal information</p>
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="form-grid">
                            <div class="form-group">
                                <label><i class="fas fa-user"></i> Full Name</label>
                                <input type="text" name="full_name"
                                       value="<?php echo htmlspecialchars($info['full_name'] ?? ''); ?>"
                                       required placeholder="Enter full name">
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-envelope"></i> Email Address</label>
                                <input type="email" name="email"
                                       value="<?php echo htmlspecialchars($info['email'] ?? ''); ?>"
                                       required placeholder="admin@example.com">
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-phone"></i> Phone Number</label>
                                <input type="text" name="phone"
                                       value="<?php echo htmlspecialchars($info['phone'] ?? ''); ?>"
                                       placeholder="+92 300 0000000">
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-id-badge"></i> Role</label>
                                <input type="text"
                                       value="<?php echo htmlspecialchars($info['role'] ?? 'Admin'); ?>"
                                       disabled style="background:#f8fafc; color:#6c757d;">
                            </div>
                        </div>
                        <br>
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Changes
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- ============================= -->
        <!-- TAB 2: SECURITY              -->
        <!-- ============================= -->
        <div id="tab-security" class="tab-panel">
            <div class="card animate__animated animate__fadeInUp">
                <div class="card-header">
                    <div class="card-header-icon icon-red"><i class="fas fa-lock"></i></div>
                    <div>
                        <h3>Change Password</h3>
                        <p>Keep your account secure with a strong password</p>
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST" id="passwordForm">
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label><i class="fas fa-key"></i> Current Password</label>
                                <input type="password" name="current_password" required
                                       placeholder="Enter current password">
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-lock"></i> New Password</label>
                                <input type="password" name="new_password" id="new_password" required
                                       placeholder="Min. 6 characters">
                            </div>
                            <div class="form-group">
                                <label><i class="fas fa-lock"></i> Confirm New Password</label>
                                <input type="password" name="confirm_password" id="confirm_password" required
                                       placeholder="Repeat new password">
                            </div>
                        </div>
                        <br>
                        <!-- Password strength bar -->
                        <div style="margin-bottom: 15px;">
                            <label style="font-size:12px; color:var(--gray);">Password Strength</label>
                            <div style="height:6px; background:#e2e8f0; border-radius:10px; margin-top:5px;">
                                <div id="strength-bar" style="height:100%; width:0%; border-radius:10px; transition:all 0.3s;"></div>
                            </div>
                            <span id="strength-text" style="font-size:11px; color:var(--gray);"></span>
                        </div>
                        <button type="submit" name="change_password" class="btn btn-primary">
                            <i class="fas fa-shield-alt"></i> Update Password
                        </button>
                    </form>
                </div>
            </div>

            <!-- Session Info -->
            <div class="card">
                <div class="card-header">
                    <div class="card-header-icon icon-orange"><i class="fas fa-desktop"></i></div>
                    <div>
                        <h3>Session Information</h3>
                        <p>Current login session details</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <h4>User ID</h4>
                            <p>#<?php echo $user_id; ?></p>
                        </div>
                        <div class="info-item">
                            <h4>User Type</h4>
                            <p><?php echo ucfirst($_SESSION['user_type']); ?></p>
                        </div>
                        <div class="info-item">
                            <h4>IP Address</h4>
                            <p style="font-size:14px;"><?php echo $_SERVER['REMOTE_ADDR']; ?></p>
                        </div>
                        <div class="info-item">
                            <h4>Session Started</h4>
                            <p style="font-size:13px;"><?php echo date('d M Y, H:i'); ?></p>
                        </div>
                    </div>
                    <button class="btn btn-danger" onclick="if(confirm('Logout from all sessions?')) window.location.href='../logout.php'">
                        <i class="fas fa-sign-out-alt"></i> Logout All Sessions
                    </button>
                </div>
            </div>
        </div>

        <!-- ============================= -->
        <!-- TAB 3: SYSTEM INFO           -->
        <!-- ============================= -->
        <div id="tab-system" class="tab-panel">
            <div class="card animate__animated animate__fadeInUp">
                <div class="card-header">
                    <div class="card-header-icon icon-purple"><i class="fas fa-server"></i></div>
                    <div>
                        <h3>System Information</h3>
                        <p>Technical details about the running environment</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="info-grid">
                        <div class="info-item">
                            <h4>PHP Version</h4>
                            <p style="font-size:16px;"><?php echo PHP_VERSION; ?></p>
                        </div>
                        <div class="info-item">
                            <h4>MySQL Version</h4>
                            <p style="font-size:16px;"><?php echo $db_version; ?></p>
                        </div>
                        <div class="info-item">
                            <h4>Server Software</h4>
                            <p style="font-size:12px;"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Apache'; ?></p>
                        </div>
                        <div class="info-item">
                            <h4>Server Time</h4>
                            <p style="font-size:13px;"><?php echo date('d M Y H:i:s'); ?></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-header-icon icon-green"><i class="fas fa-database"></i></div>
                    <div>
                        <h3>Database Statistics</h3>
                        <p>Summary of system records</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="info-grid">
                        <?php
                        $tables = [
                            'users'                => ['label'=>'Total Users',       'icon'=>'fas fa-users'],
                            'parents'              => ['label'=>'Parents',            'icon'=>'fas fa-user-friends'],
                            'hospitals'            => ['label'=>'Hospitals',          'icon'=>'fas fa-hospital'],
                            'children'             => ['label'=>'Children',           'icon'=>'fas fa-baby'],
                            'vaccines'             => ['label'=>'Vaccines',           'icon'=>'fas fa-syringe'],
                            'appointment_requests' => ['label'=>'Requests',           'icon'=>'fas fa-calendar-check'],
                            'vaccination_bookings' => ['label'=>'Bookings',           'icon'=>'fas fa-calendar-alt'],
                            'vaccination_records'  => ['label'=>'Vaccination Records','icon'=>'fas fa-file-medical'],
                            'audit_logs'           => ['label'=>'Audit Logs',         'icon'=>'fas fa-history'],
                            'notifications'        => ['label'=>'Notifications',      'icon'=>'fas fa-bell'],
                        ];
                        foreach($tables as $table => $meta):
                            $cnt = @mysqli_fetch_assoc(mysqli_query($connection, "SELECT COUNT(*) as c FROM $table"))['c'] ?? 0;
                        ?>
                        <div class="info-item">
                            <h4><i class="<?php echo $meta['icon']; ?>"></i> <?php echo $meta['label']; ?></h4>
                            <p><?php echo number_format($cnt); ?></p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Danger Zone -->
            <div class="card">
                <div class="card-header">
                    <div class="card-header-icon icon-red"><i class="fas fa-exclamation-triangle"></i></div>
                    <div>
                        <h3>Danger Zone</h3>
                        <p>Irreversible actions — proceed with caution</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="danger-zone" style="margin-bottom: 15px;">
                        <h4><i class="fas fa-trash"></i> Clear Audit Logs</h4>
                        <p>Permanently delete all system activity logs. This cannot be undone.</p>
                        <button class="btn btn-warning" onclick="confirmClearLogs()">
                            <i class="fas fa-broom"></i> Clear All Logs
                        </button>
                    </div>
                    <div class="danger-zone">
                        <h4><i class="fas fa-bell-slash"></i> Clear All Notifications</h4>
                        <p>Permanently delete all system notifications for all users.</p>
                        <button class="btn btn-danger" onclick="confirmClearNotifications()">
                            <i class="fas fa-trash-alt"></i> Clear Notifications
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================= -->
        <!-- TAB 4: PREFERENCES           -->
        <!-- ============================= -->
        <div id="tab-preferences" class="tab-panel">
            <div class="card animate__animated animate__fadeInUp">
                <div class="card-header">
                    <div class="card-header-icon icon-blue"><i class="fas fa-sliders-h"></i></div>
                    <div>
                        <h3>System Preferences</h3>
                        <p>Configure system behaviour and notifications</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="toggle-row">
                        <div class="toggle-info">
                            <h4>Email Notifications</h4>
                            <p>Send email alerts for new bookings and requests</p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" checked id="pref_email">
                            <span class="slider"></span>
                        </label>
                    </div>
                    <div class="toggle-row">
                        <div class="toggle-info">
                            <h4>OTP Verification</h4>
                            <p>Require OTP email verification during registration</p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" checked id="pref_otp">
                            <span class="slider"></span>
                        </label>
                    </div>
                    <div class="toggle-row">
                        <div class="toggle-info">
                            <h4>Audit Logging</h4>
                            <p>Track all admin and user actions in audit logs</p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" checked id="pref_audit">
                            <span class="slider"></span>
                        </label>
                    </div>
                    <div class="toggle-row">
                        <div class="toggle-info">
                            <h4>Auto Hospital Verification</h4>
                            <p>Automatically approve hospital registrations</p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" id="pref_auto_verify">
                            <span class="slider"></span>
                        </label>
                    </div>
                    <div class="toggle-row">
                        <div class="toggle-info">
                            <h4>Maintenance Mode</h4>
                            <p>Temporarily disable public access to the system</p>
                        </div>
                        <label class="switch">
                            <input type="checkbox" id="pref_maintenance">
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Site Config -->
            <div class="card">
                <div class="card-header">
                    <div class="card-header-icon icon-green"><i class="fas fa-globe"></i></div>
                    <div>
                        <h3>Site Configuration</h3>
                        <p>Basic site information (shown across the system)</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Site Name</label>
                            <input type="text" value="Child Vaccination System" placeholder="Site name">
                        </div>
                        <div class="form-group">
                            <label>Base URL</label>
                            <input type="text" value="http://localhost/vaccination_booking_system/" placeholder="http://...">
                        </div>
                        <div class="form-group">
                            <label>Session Timeout (seconds)</label>
                            <input type="number" value="3600" min="300">
                        </div>
                        <div class="form-group">
                            <label>Upload Path</label>
                            <input type="text" value="uploads/" placeholder="uploads/">
                        </div>
                        <div class="form-group full-width">
                            <label>Site Description</label>
                            <textarea placeholder="Brief description...">A modern platform to manage and track child vaccination schedules.</textarea>
                        </div>
                    </div>
                    <br>
                    <button class="btn btn-primary" onclick="alert('Settings saved! (Update config.php manually to persist changes)')">
                        <i class="fas fa-save"></i> Save Preferences
                    </button>
                </div>
            </div>
        </div>

        <!-- ============================= -->
        <!-- TAB 5: ACTIVITY LOGS         -->
        <!-- ============================= -->
        <div id="tab-logs" class="tab-panel">
            <div class="card animate__animated animate__fadeInUp">
                <div class="card-header">
                    <div class="card-header-icon icon-purple"><i class="fas fa-history"></i></div>
                    <div>
                        <h3>Recent Activity Logs</h3>
                        <p>Last 10 system actions</p>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-wrapper">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>User ID</th>
                                    <th>Action</th>
                                    <th>Table</th>
                                    <th>Description</th>
                                    <th>IP Address</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php
                            $i = 1;
                            if($r_logs && mysqli_num_rows($r_logs) > 0):
                                while($log = mysqli_fetch_assoc($r_logs)):
                                    $action = strtolower($log['action_type']);
                                    $badge_class = match($action) {
                                        'login'           => 'badge-green',
                                        'logout'          => 'badge-orange',
                                        'delete'          => 'badge-red',
                                        'update','cancel' => 'badge-blue',
                                        default           => 'badge-blue',
                                    };
                            ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td>#<?php echo $log['user_id']; ?></td>
                                <td><span class="badge <?php echo $badge_class; ?>"><?php echo htmlspecialchars($log['action_type']); ?></span></td>
                                <td><?php echo htmlspecialchars($log['table_name']); ?></td>
                                <td><?php echo htmlspecialchars(substr($log['action_description'], 0, 60)) . (strlen($log['action_description']) > 60 ? '...' : ''); ?></td>
                                <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                <td><?php echo date('d M y, H:i', strtotime($log['created_at'])); ?></td>
                            </tr>
                            <?php endwhile; else: ?>
                            <tr><td colspan="7" style="text-align:center; color:var(--gray); padding:30px;">No activity logs found.</td></tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <br>
                    <a href="vaccination_reports.php" class="btn btn-primary">
                        <i class="fas fa-chart-bar"></i> View Full Reports
                    </a>
                </div>
            </div>
        </div>

    </main>
</div>

<!-- ===== SCRIPTS ===== -->
<script>
    // Tab switching
    function showTab(name, btn) {
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
        document.getElementById('tab-' + name).classList.add('active');
        btn.classList.add('active');
    }

    // Password strength
    document.getElementById('new_password')?.addEventListener('input', function(){
        const val = this.value;
        const bar = document.getElementById('strength-bar');
        const txt = document.getElementById('strength-text');
        let strength = 0;
        if(val.length >= 6)  strength++;
        if(val.length >= 10) strength++;
        if(/[A-Z]/.test(val)) strength++;
        if(/[0-9]/.test(val)) strength++;
        if(/[^A-Za-z0-9]/.test(val)) strength++;

        const levels = [
            {w:'0%',   c:'',        t:''},
            {w:'20%',  c:'#ef4444', t:'Very Weak'},
            {w:'40%',  c:'#f97316', t:'Weak'},
            {w:'60%',  c:'#eab308', t:'Fair'},
            {w:'80%',  c:'#22c55e', t:'Strong'},
            {w:'100%', c:'#16a34a', t:'Very Strong'},
        ];
        const lvl = levels[strength] || levels[0];
        bar.style.width = lvl.w;
        bar.style.background = lvl.c;
        txt.textContent = lvl.t;
        txt.style.color = lvl.c;
    });

    // Confirm clear logs
    function confirmClearLogs(){
        if(confirm('Are you sure you want to clear ALL audit logs? This cannot be undone.')){
            <?php
            // Clear logs action
            ?>
            fetch('?action=clear_logs').then(r => r.text()).then(() => {
                alert('Audit logs cleared!');
                location.reload();
            });
        }
    }

    // Confirm clear notifications
    function confirmClearNotifications(){
        if(confirm('Are you sure you want to clear ALL notifications?')){
            fetch('?action=clear_notifications').then(r => r.text()).then(() => {
                alert('Notifications cleared!');
                location.reload();
            });
        }
    }

    // Auto-dismiss alerts after 4 seconds
    setTimeout(() => {
        document.querySelectorAll('.alert').forEach(a => {
            a.style.transition = 'opacity 0.5s';
            a.style.opacity = '0';
            setTimeout(() => a.remove(), 500);
        });
    }, 4000);
</script>

<?php
// Handle AJAX clear actions
if(isset($_GET['action'])){
    if($_GET['action'] === 'clear_logs'){
        mysqli_query($connection, "DELETE FROM audit_logs");
        log_audit($user_id, 'DELETE', 'audit_logs', 0, 'Admin cleared all audit logs');
        echo "done";
        exit();
    }
    if($_GET['action'] === 'clear_notifications'){
        mysqli_query($connection, "DELETE FROM notifications");
        echo "done";
        exit();
    }
}
?>
</body>
</html>