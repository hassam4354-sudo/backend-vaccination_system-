<?php
session_start();
if(!isset($_SESSION["logged_in"]) || $_SESSION["user_type"] != "admin"){
    header("location:../login.php");
    exit();
}

include("../dbconnection.php");
$user_id = $_SESSION["user_id"];

$query_admin = "SELECT full_name FROM admins WHERE user_id = '$user_id'";
$result_admin = mysqli_query($connection, $query_admin);
$admin_data = mysqli_fetch_assoc($result_admin);
$admin_name = $admin_data['full_name'];

// Get parents for dropdown
$query_parents = "SELECT p.parent_id, p.full_name, u.email, u.phone FROM parents p JOIN users u ON p.user_id = u.user_id ORDER BY p.full_name";
$result_parents = mysqli_query($connection, $query_parents);

// Handle send notification
$success_msg = '';
$error_msg = '';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $title       = mysqli_real_escape_string($connection, $_POST['title'] ?? '');
    $message     = mysqli_real_escape_string($connection, $_POST['message'] ?? '');
    $type        = mysqli_real_escape_string($connection, $_POST['type'] ?? 'general');
    $recipient   = mysqli_real_escape_string($connection, $_POST['recipient'] ?? 'all');
    $parent_id   = intval($_POST['parent_id'] ?? 0);
    $sent_at     = date('Y-m-d H:i:s');

    if(!empty($title) && !empty($message)){
        if($recipient === 'all'){
            // Send to all parents
            $q_all = "SELECT parent_id FROM parents";
            $r_all = mysqli_query($connection, $q_all);
            $inserted = 0;
            while($p = mysqli_fetch_assoc($r_all)){
                $ins = "INSERT INTO notifications (parent_id, title, message, type, is_read, created_at)
                        VALUES ('{$p['parent_id']}', '$title', '$message', '$type', 0, '$sent_at')";
                if(mysqli_query($connection, $ins)) $inserted++;
            }
            $success_msg = "Notification sent to $inserted parents successfully!";
        } else {
            $ins = "INSERT INTO notifications (parent_id, title, message, type, is_read, created_at)
                    VALUES ('$parent_id', '$title', '$message', '$type', 0, '$sent_at')";
            if(mysqli_query($connection, $ins)){
                $success_msg = "Notification sent successfully!";
            } else {
                $error_msg = "Failed to send notification. Please try again.";
            }
        }
    } else {
        $error_msg = "Title and message are required.";
    }
}

// Get recent notifications
$query_notifs = "SELECT n.*, p.full_name as parent_name, u.email
                 FROM notifications n
                 LEFT JOIN parents p ON n.parent_id = p.parent_id
                 LEFT JOIN users u ON p.user_id = u.user_id
                 ORDER BY n.created_at DESC LIMIT 20";
$result_notifs = mysqli_query($connection, $query_notifs);

// Stats
$total_notifs  = mysqli_fetch_assoc(mysqli_query($connection, "SELECT COUNT(*) as c FROM notifications"))['c'] ?? 0;
$unread_notifs = mysqli_fetch_assoc(mysqli_query($connection, "SELECT COUNT(*) as c FROM notifications WHERE is_read = 0"))['c'] ?? 0;
$today_notifs  = mysqli_fetch_assoc(mysqli_query($connection, "SELECT COUNT(*) as c FROM notifications WHERE DATE(created_at) = CURDATE()"))['c'] ?? 0;
$total_parents = mysqli_fetch_assoc(mysqli_query($connection, "SELECT COUNT(*) as c FROM parents"))['c'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Send Notifications - Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        *{margin:0;padding:0;box-sizing:border-box;font-family:'Inter',sans-serif;}
        :root{
            --primary:#2563eb;--primary-dark:#1d4ed8;--primary-light:#60a5fa;
            --primary-soft:#dbeafe;--white:#ffffff;--white-off:#f8fafc;
            --gray-100:#e2e8f0;--gray-200:#cbd5e1;--gray-300:#94a3b8;
            --gray-400:#64748b;--gray-500:#475569;--gray-600:#334155;--gray-700:#1e293b;
            --success:#10b981;--success-light:#d1fae5;
            --warning:#f59e0b;--warning-light:#fef3c7;
            --danger:#ef4444;--danger-light:#fee2e2;
            --shadow:0 4px 6px -1px rgba(0,0,0,0.07);
            --shadow-md:0 10px 20px -3px rgba(0,0,0,0.08);
            --shadow-lg:0 20px 35px -5px rgba(0,0,0,0.1);
            --radius:12px;--radius-lg:20px;--transition:all 0.2s ease;
        }
        body{background:linear-gradient(145deg,#f8fafc,#f1f5f9);min-height:100vh;color:var(--gray-600);}

        /* NAVBAR */
        .admin-navbar{
            background:#fff;border-bottom:2px solid #e8eeff;
            padding:0 35px;display:flex;justify-content:space-between;
            align-items:center;height:68px;
            box-shadow:0 2px 16px rgba(26,111,196,0.08);
            position:sticky;top:0;z-index:100;
        }
        .logo{display:flex;align-items:center;gap:10px;}
        .logo-icon{width:40px;height:40px;background:linear-gradient(135deg,#1a6fc4,#1155a0);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;color:white;}
        .logo h2{font-size:20px;font-weight:700;color:#1155a0;}
        .nav-links{display:flex;align-items:center;gap:6px;flex-wrap:wrap;}
        .nav-links a{color:#4b6cb7;text-decoration:none;padding:8px 14px;border-radius:8px;font-size:13.5px;font-weight:500;transition:all 0.2s;display:flex;align-items:center;gap:6px;}
        .nav-links a:hover{background:#eff6ff;color:#1155a0;}
        .nav-links a.active{background:#dbeafe;color:#1155a0;font-weight:600;}
        .nav-links a.logout{background:#fee2e2;color:#dc2626;}
        .nav-links a.logout:hover{background:#fecaca;}

        /* MAIN */
        .main-content{padding:30px;min-height:calc(100vh - 68px);}
        .container{max-width:1400px;margin:0 auto;}

        /* PAGE HEADER */
        .page-header{
            border-radius:20px;margin-bottom:28px;
            box-shadow:var(--shadow-md);
            display:flex;align-items:stretch;
            overflow:hidden;min-height:160px;border:none;padding:0;
        }
        .page-header-text{
            flex:1;padding:36px 42px;
            display:flex;flex-direction:column;justify-content:center;
            background:linear-gradient(135deg,#1a6fc4,#0d47a1);
            position:relative;overflow:hidden;
        }
        .page-header-text::after{
            content:'';position:absolute;top:-20px;right:30px;
            width:80px;height:80px;
            border:3px solid rgba(255,255,255,0.12);border-radius:50%;
            box-shadow:0 0 0 20px rgba(255,255,255,0.06),0 0 0 40px rgba(255,255,255,0.03);
        }
        .page-header-text::before{
            content:'';position:absolute;bottom:-30px;left:-20px;
            width:120px;height:120px;background:rgba(255,255,255,0.07);
            transform:rotate(45deg);
        }
        .page-header h1{color:#fff;font-size:30px;font-weight:800;margin-bottom:10px;text-transform:uppercase;letter-spacing:0.3px;}
        .page-header h1 i{background:rgba(255,255,255,0.2);padding:10px;border-radius:12px;margin-right:10px;}
        .page-header p{color:rgba(255,255,255,0.85);font-size:14.5px;margin:0;}
        .page-header-img{display:none;}

        /* STATS */
        .stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:20px;margin-bottom:28px;}
        .stat-card{
            background:white;border-radius:var(--radius-lg);padding:22px;
            box-shadow:var(--shadow);border:1px solid var(--gray-100);
            transition:var(--transition);position:relative;overflow:hidden;
        }
        .stat-card::before{content:'';position:absolute;top:0;left:0;width:100%;height:4px;background:linear-gradient(90deg,var(--primary),var(--primary-light));}
        .stat-card:hover{transform:translateY(-4px);box-shadow:var(--shadow-md);}
        .stat-icon-wrap{display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;}
        .stat-icon{width:50px;height:50px;background:var(--primary-soft);border-radius:14px;display:flex;align-items:center;justify-content:center;color:var(--primary);font-size:22px;transition:var(--transition);}
        .stat-card:hover .stat-icon{background:var(--primary);color:white;}
        .stat-card h3{font-size:30px;font-weight:700;color:var(--gray-700);margin-bottom:4px;}
        .stat-card p{color:var(--gray-400);font-size:13px;font-weight:500;}

        /* LAYOUT: form + table */
        .content-grid{display:grid;grid-template-columns:1fr 1.6fr;gap:24px;align-items:start;}

        /* FORM CARD */
        .form-card{background:white;border-radius:var(--radius-lg);box-shadow:var(--shadow);overflow:hidden;}
        .card-header{
            background:linear-gradient(135deg,var(--primary),var(--primary-dark));
            padding:20px 26px;display:flex;align-items:center;gap:12px;
        }
        .card-header i{color:rgba(255,255,255,0.8);font-size:18px;}
        .card-header h3{color:white;font-size:16px;font-weight:700;}
        .card-body{padding:26px;}

        .form-group{margin-bottom:20px;}
        .form-label{display:block;font-size:13px;font-weight:600;color:var(--gray-600);margin-bottom:8px;}
        .form-label span{color:var(--danger);}
        .form-control{
            width:100%;padding:12px 16px;
            border:2px solid var(--gray-100);border-radius:10px;
            font-size:14px;color:var(--gray-600);
            background:white;outline:none;
            transition:var(--transition);
        }
        .form-control:focus{border-color:var(--primary);box-shadow:0 0 0 4px rgba(37,99,235,0.08);}
        textarea.form-control{resize:vertical;min-height:110px;}
        select.form-control{cursor:pointer;appearance:none;
            background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
            background-repeat:no-repeat;background-position:right 14px center;background-size:16px;
        }

        /* Type badges */
        .type-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px;margin-top:6px;}
        .type-option{position:relative;}
        .type-option input[type="radio"]{position:absolute;opacity:0;width:0;height:0;}
        .type-label{
            display:flex;align-items:center;gap:10px;padding:12px 14px;
            border:2px solid var(--gray-100);border-radius:10px;cursor:pointer;
            transition:var(--transition);font-size:13px;font-weight:500;color:var(--gray-500);
        }
        .type-label i{font-size:16px;}
        .type-option input:checked + .type-label{border-color:var(--primary);background:var(--primary-soft);color:var(--primary);}
        .type-label:hover{border-color:var(--primary-light);background:#f0f7ff;}

        /* Recipient toggle */
        .recipient-toggle{display:flex;gap:0;border:2px solid var(--gray-100);border-radius:10px;overflow:hidden;margin-bottom:16px;}
        .recipient-btn{
            flex:1;padding:10px;text-align:center;cursor:pointer;
            font-size:13px;font-weight:600;transition:var(--transition);
            color:var(--gray-500);background:white;border:none;
        }
        .recipient-btn.active{background:var(--primary);color:white;}

        .parent-select-wrap{display:none;}
        .parent-select-wrap.show{display:block;}

        /* Send button */
        .btn-send{
            width:100%;padding:14px;
            background:linear-gradient(135deg,var(--primary),var(--primary-dark));
            color:white;border:none;border-radius:10px;
            font-size:15px;font-weight:700;cursor:pointer;
            transition:var(--transition);display:flex;align-items:center;justify-content:center;gap:10px;
            box-shadow:0 8px 20px rgba(37,99,235,0.25);
        }
        .btn-send:hover{transform:translateY(-2px);box-shadow:0 12px 28px rgba(37,99,235,0.35);}
        .btn-send:active{transform:translateY(0);}

        /* NOTIFICATIONS TABLE */
        .table-card{background:white;border-radius:var(--radius-lg);box-shadow:var(--shadow);overflow:hidden;}
        .table-header-row{
            background:linear-gradient(135deg,#f8fafc,#f0f6ff);
            padding:18px 24px;display:flex;justify-content:space-between;align-items:center;
            border-bottom:2px solid var(--gray-100);
        }
        .table-header-row h3{color:var(--gray-700);font-size:15px;font-weight:700;display:flex;align-items:center;gap:8px;}
        .table-header-row h3 i{color:var(--primary);}
        .badge-count{background:var(--primary);color:white;font-size:11px;font-weight:700;padding:3px 10px;border-radius:20px;}

        .notif-table{width:100%;border-collapse:collapse;}
        .notif-table thead th{
            padding:13px 16px;text-align:left;
            background:#f8fafc;color:var(--gray-400);
            font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;
            border-bottom:1px solid var(--gray-100);
        }
        .notif-table tbody tr{border-bottom:1px solid var(--gray-100);transition:var(--transition);}
        .notif-table tbody tr:hover{background:#f8fafc;}
        .notif-table td{padding:14px 16px;font-size:13px;color:var(--gray-600);vertical-align:middle;}

        .notif-title{font-weight:600;color:var(--gray-700);margin-bottom:3px;}
        .notif-msg{font-size:12px;color:var(--gray-400);max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}

        .type-badge{padding:4px 12px;border-radius:20px;font-size:11px;font-weight:600;display:inline-block;}
        .type-general{background:#dbeafe;color:#1d4ed8;}
        .type-reminder{background:#fef3c7;color:#92400e;}
        .type-alert{background:#fee2e2;color:#b91c1c;}
        .type-appointment{background:#d1fae5;color:#065f46;}

        .read-badge{padding:4px 12px;border-radius:20px;font-size:11px;font-weight:600;display:inline-block;}
        .badge-read{background:#f1f5f9;color:#94a3b8;}
        .badge-unread{background:#dbeafe;color:#1d4ed8;}

        /* ALERTS */
        .alert{padding:14px 20px;border-radius:12px;margin-bottom:20px;font-weight:500;font-size:14px;display:flex;align-items:center;gap:10px;animation:slideDown 0.3s ease;}
        .alert-success{background:var(--success-light);color:#065f46;border-left:4px solid var(--success);}
        .alert-danger{background:var(--danger-light);color:#b91c1c;border-left:4px solid var(--danger);}
        @keyframes slideDown{from{opacity:0;transform:translateY(-8px);}to{opacity:1;transform:translateY(0);}}

        /* EMPTY STATE */
        .empty-state{padding:50px 20px;text-align:center;color:var(--gray-400);}
        .empty-state i{font-size:40px;margin-bottom:12px;display:block;color:var(--gray-200);}
        .empty-state p{font-size:14px;}

        /* RESPONSIVE */
        @media(max-width:1100px){
            .content-grid{grid-template-columns:1fr;}
            .stats-grid{grid-template-columns:repeat(2,1fr);}
        }
        @media(max-width:768px){
            .main-content{padding:20px;}
            .stats-grid{grid-template-columns:1fr 1fr;}
            .page-header-img{display:none;}
        }

        ::-webkit-scrollbar{width:8px;height:8px;}
        ::-webkit-scrollbar-track{background:#f1f5f9;}
        ::-webkit-scrollbar-thumb{background:#cbd5e1;border-radius:8px;}
    </style>
</head>
<body>
<div class="dashboard-layout">

    <!-- NAVBAR -->
    <nav class="admin-navbar">
        <div class="logo">
            <div class="logo-icon"><i class="fas fa-shield-alt"></i></div>
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
            <a href="system_settings.php"> Settings</a>
            <a href="send_notifications.php" class="active"> Notifications</a>
            <a href="../logout.php" class="logout"> Logout</a>
        </div>
    </nav>

    <!-- MAIN -->
    <main class="main-content">
        <div class="container">

            <!-- PAGE HEADER -->
            <div class="page-header">
                <div class="page-header-text">
                    <h1><i class="fas fa-bell"></i> Send Notifications</h1>
                    <p>Send alerts, reminders, and updates to parents in the vaccination system</p>
                </div>
                <div class="page-header-img">
                    <img src="https://images.unsplash.com/photo-1611532736597-de2d4265fba3?w=500&q=80" alt="Notifications">
                </div>
            </div>

            <!-- ALERTS -->
            <?php if($success_msg): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success_msg; ?></div>
            <?php endif; ?>
            <?php if($error_msg): ?>
            <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo $error_msg; ?></div>
            <?php endif; ?>

            <!-- STATS -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon-wrap">
                        <div class="stat-icon"><i class="fas fa-paper-plane"></i></div>
                    </div>
                    <h3><?php echo $total_notifs; ?></h3>
                    <p>Total Sent</p>
                </div>
                <div class="stat-card">
                    <div class="stat-icon-wrap">
                        <div class="stat-icon" style="background:#fef3c7;color:#d97706;"><i class="fas fa-envelope-open"></i></div>
                    </div>
                    <h3><?php echo $unread_notifs; ?></h3>
                    <p>Unread</p>
                </div>
                <div class="stat-card">
                    <div class="stat-icon-wrap">
                        <div class="stat-icon" style="background:#d1fae5;color:#059669;"><i class="fas fa-calendar-day"></i></div>
                    </div>
                    <h3><?php echo $today_notifs; ?></h3>
                    <p>Sent Today</p>
                </div>
                <div class="stat-card">
                    <div class="stat-icon-wrap">
                        <div class="stat-icon" style="background:#ede9fe;color:#7c3aed;"><i class="fas fa-users"></i></div>
                    </div>
                    <h3><?php echo $total_parents; ?></h3>
                    <p>Total Parents</p>
                </div>
            </div>

            <!-- CONTENT GRID -->
            <div class="content-grid">

                <!-- SEND FORM -->
                <div class="form-card">
                    <div class="card-header">
                        <i class="fas fa-paper-plane"></i>
                        <h3>Compose Notification</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="notifForm">

                            <!-- Recipient Toggle -->
                            <div class="form-group">
                                <label class="form-label">Send To <span>*</span></label>
                                <div class="recipient-toggle">
                                    <button type="button" class="recipient-btn active" onclick="setRecipient('all', this)">
                                        <i class="fas fa-users"></i> All Parents
                                    </button>
                                    <button type="button" class="recipient-btn" onclick="setRecipient('single', this)">
                                        <i class="fas fa-user"></i> Specific Parent
                                    </button>
                                </div>
                                <input type="hidden" name="recipient" id="recipientInput" value="all">
                            </div>

                            <!-- Parent Select (shown when specific) -->
                            <div class="form-group parent-select-wrap" id="parentSelectWrap">
                                <label class="form-label">Select Parent <span>*</span></label>
                                <div style="position:relative;">
                                    <i class="fas fa-user" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--primary);font-size:14px;"></i>
                                    <select name="parent_id" class="form-control" style="padding-left:40px;">
                                        <option value="">-- Select Parent --</option>
                                        <?php
                                        // Reset result pointer
                                        mysqli_data_seek($result_parents, 0);
                                        while($p = mysqli_fetch_assoc($result_parents)):
                                        ?>
                                        <option value="<?php echo $p['parent_id']; ?>">
                                            <?php echo htmlspecialchars($p['full_name']); ?> — <?php echo htmlspecialchars($p['email']); ?>
                                        </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>
                            </div>

                            <!-- Notification Type -->
                            <div class="form-group">
                                <label class="form-label">Notification Type <span>*</span></label>
                                <div class="type-grid">
                                    <label class="type-option">
                                        <input type="radio" name="type" value="general" checked>
                                        <span class="type-label"><i class="fas fa-info-circle" style="color:#2563eb;"></i> General</span>
                                    </label>
                                    <label class="type-option">
                                        <input type="radio" name="type" value="reminder">
                                        <span class="type-label"><i class="fas fa-clock" style="color:#d97706;"></i> Reminder</span>
                                    </label>
                                    <label class="type-option">
                                        <input type="radio" name="type" value="appointment">
                                        <span class="type-label"><i class="fas fa-calendar-check" style="color:#059669;"></i> Appointment</span>
                                    </label>
                                    <label class="type-option">
                                        <input type="radio" name="type" value="alert">
                                        <span class="type-label"><i class="fas fa-exclamation-triangle" style="color:#dc2626;"></i> Alert</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Title -->
                            <div class="form-group">
                                <label class="form-label">Title <span>*</span></label>
                                <div style="position:relative;">
                                    <i class="fas fa-heading" style="position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--primary);font-size:14px;"></i>
                                    <input type="text" name="title" class="form-control" style="padding-left:40px;"
                                           placeholder="e.g. Vaccination Reminder" required
                                           value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>">
                                </div>
                            </div>

                            <!-- Message -->
                            <div class="form-group">
                                <label class="form-label">Message <span>*</span></label>
                                <textarea name="message" class="form-control" placeholder="Write your message here..." required><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                                <div style="text-align:right;font-size:11px;color:var(--gray-400);margin-top:5px;">
                                    <span id="charCount">0</span> characters
                                </div>
                            </div>

                            <!-- Quick Templates -->
                            <div class="form-group">
                                <label class="form-label">Quick Templates</label>
                                <div style="display:flex;flex-wrap:wrap;gap:8px;">
                                    <button type="button" class="template-btn" onclick="useTemplate('reminder')">📅 Vaccine Due</button>
                                    <button type="button" class="template-btn" onclick="useTemplate('appointment')">🏥 Appointment Booked</button>
                                    <button type="button" class="template-btn" onclick="useTemplate('followup')">🔔 Follow Up</button>
                                    <button type="button" class="template-btn" onclick="useTemplate('welcome')">👋 Welcome</button>
                                </div>
                            </div>

                            <button type="submit" class="btn-send">
                                <i class="fas fa-paper-plane"></i> Send Notification
                            </button>
                        </form>
                    </div>
                </div>

                <!-- NOTIFICATIONS LOG -->
                <div class="table-card">
                    <div class="table-header-row">
                        <h3><i class="fas fa-history"></i> Sent Notifications</h3>
                        <span class="badge-count"><?php echo $total_notifs; ?> Total</span>
                    </div>
                    <div style="overflow-x:auto;">
                        <table class="notif-table">
                            <thead>
                                <tr>
                                    <th>Notification</th>
                                    <th>Recipient</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if($result_notifs && mysqli_num_rows($result_notifs) > 0): ?>
                                <?php while($n = mysqli_fetch_assoc($result_notifs)): ?>
                                <tr>
                                    <td>
                                        <div class="notif-title"><?php echo htmlspecialchars($n['title']); ?></div>
                                        <div class="notif-msg"><?php echo htmlspecialchars($n['message']); ?></div>
                                    </td>
                                    <td>
                                        <div style="font-weight:600;color:var(--gray-700);font-size:13px;">
                                            <?php echo htmlspecialchars($n['parent_name'] ?? 'Unknown'); ?>
                                        </div>
                                        <div style="font-size:11px;color:var(--gray-400);">
                                            <?php echo htmlspecialchars($n['email'] ?? ''); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $type_class = 'type-' . ($n['type'] ?? 'general');
                                        $type_label = ucfirst($n['type'] ?? 'General');
                                        ?>
                                        <span class="type-badge <?php echo $type_class; ?>"><?php echo $type_label; ?></span>
                                    </td>
                                    <td>
                                        <?php if($n['is_read']): ?>
                                            <span class="read-badge badge-read"><i class="fas fa-check"></i> Read</span>
                                        <?php else: ?>
                                            <span class="read-badge badge-unread"><i class="fas fa-circle"></i> Unread</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="font-weight:600;color:var(--gray-600);font-size:13px;">
                                            <?php echo date('M d, Y', strtotime($n['created_at'])); ?>
                                        </div>
                                        <div style="font-size:11px;color:var(--gray-400);">
                                            <?php echo date('h:i A', strtotime($n['created_at'])); ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                                <?php else: ?>
                                <tr>
                                    <td colspan="5">
                                        <div class="empty-state">
                                            <i class="fas fa-bell-slash"></i>
                                            <p>No notifications sent yet.</p>
                                        </div>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div><!-- /content-grid -->
        </div><!-- /container -->
    </main>
</div>

<style>
.template-btn{
    padding:7px 14px;border:1.5px solid var(--gray-100);border-radius:8px;
    background:var(--white-off);color:var(--gray-600);font-size:12px;font-weight:500;
    cursor:pointer;transition:var(--transition);
}
.template-btn:hover{border-color:var(--primary);background:var(--primary-soft);color:var(--primary);}
</style>

<script>
    // Recipient toggle
    function setRecipient(val, btn){
        document.getElementById('recipientInput').value = val;
        document.querySelectorAll('.recipient-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        const wrap = document.getElementById('parentSelectWrap');
        wrap.classList.toggle('show', val === 'single');
    }

    // Char counter
    const msgField = document.querySelector('textarea[name="message"]');
    const counter  = document.getElementById('charCount');
    if(msgField){
        msgField.addEventListener('input', () => { counter.textContent = msgField.value.length; });
    }

    // Quick templates
    const templates = {
        reminder: {
            title: 'Vaccination Reminder',
            message: 'Dear Parent, this is a reminder that your child\'s vaccination is due soon. Please schedule an appointment at your nearest registered hospital at your earliest convenience. Thank you!'
        },
        appointment: {
            title: 'Appointment Confirmed',
            message: 'Dear Parent, your vaccination appointment has been successfully booked. Please arrive 10 minutes before your scheduled time and bring your child\'s vaccination card. Thank you!'
        },
        followup: {
            title: 'Follow-up Required',
            message: 'Dear Parent, your child\'s follow-up vaccination is due. Please visit your nearest hospital or contact us to schedule your appointment. Timely vaccination ensures your child\'s health.'
        },
        welcome: {
            title: 'Welcome to VacciTrack!',
            message: 'Dear Parent, welcome to the Child Vaccination Management System. You can now track your child\'s vaccination schedule, book appointments, and receive timely reminders. Thank you for joining us!'
        }
    };

    function useTemplate(key){
        const t = templates[key];
        if(!t) return;
        document.querySelector('input[name="title"]').value = t.title;
        msgField.value = t.message;
        counter.textContent = t.message.length;
    }

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
<?php mysqli_close($connection); ?>