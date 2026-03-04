<?php
session_start();
if(!isset($_SESSION["logged_in"]) || $_SESSION["user_type"] != "hospital"){
    header("location:../login.php");
    exit();
}

include("../dbconnection.php");

$user_id     = $_SESSION["user_id"];
$success_msg = "";
$error_msg   = "";

// Get hospital data
$query_hospital  = "SELECT * FROM hospitals WHERE user_id = '$user_id'";
$result_hospital = mysqli_query($connection, $query_hospital);
$hospital        = mysqli_fetch_assoc($result_hospital);
$hospital_id     = $hospital['hospital_id'] ?? 0;
$is_verified     = $hospital['is_verified'] ?? 0;
$is_active       = $hospital['is_active']   ?? 0;

// ── HANDLE APPROVE / REJECT ──
if(isset($_POST['action']) && isset($_POST['request_id'])) {
    $req_id = intval($_POST['request_id']);
    $action = $_POST['action'];
    $notes  = mysqli_real_escape_string($connection, $_POST['admin_notes'] ?? '');

    if($action === 'approve') {

        // Verify this request belongs to this hospital and is still pending
        $chk = mysqli_fetch_assoc(mysqli_query($connection,
            "SELECT request_id FROM appointment_requests
             WHERE request_id='$req_id' AND hospital_id='$hospital_id' AND request_status='pending'"));

        if($chk) {
            mysqli_begin_transaction($connection);
            try {
                $conf_code = 'VC' . str_pad($req_id, 6, '0', STR_PAD_LEFT);

                // 1. Update request status
                mysqli_query($connection,
                    "UPDATE appointment_requests
                     SET request_status='approved', admin_notes='$notes', processed_at=NOW()
                     WHERE request_id='$req_id'");

                // 2. Fetch full request data
                $req_data = mysqli_fetch_assoc(mysqli_query($connection,
                    "SELECT ar.*, c.full_name as child_name, c.parent_id,
                            v.vaccine_name, p.user_id as parent_user_id, p.full_name as parent_name
                     FROM appointment_requests ar
                     JOIN children c  ON ar.child_id  = c.child_id
                     JOIN vaccines v  ON ar.vaccine_id = v.vaccine_id
                     JOIN parents  p  ON c.parent_id   = p.parent_id
                     WHERE ar.request_id = '$req_id'"));

                if(!$req_data) throw new Exception("Request data not found.");

                // 3. Insert into vaccination_bookings (only if not already there)
                $already = mysqli_fetch_assoc(mysqli_query($connection,
                    "SELECT booking_id FROM vaccination_bookings WHERE request_id='$req_id'"));

                if(!$already) {
                    $ins = mysqli_query($connection,
                        "INSERT INTO vaccination_bookings
                         (request_id, child_id, hospital_id, vaccine_id, dose_number,
                          appointment_date, appointment_time, confirmation_code, booking_status,
                          created_at, updated_at)
                         VALUES
                         ('{$req_data['request_id']}', '{$req_data['child_id']}', '$hospital_id',
                          '{$req_data['vaccine_id']}', '{$req_data['dose_number']}',
                          '{$req_data['preferred_date']}', '{$req_data['preferred_time']}',
                          '$conf_code', 'scheduled', NOW(), NOW())");

                    if(!$ins) throw new Exception("Booking insert failed: " . mysqli_error($connection));
                }

                // 4. Notify parent — type: appointment_approved
                $p_user  = $req_data['parent_user_id'];
                $cname   = mysqli_real_escape_string($connection, $req_data['child_name']);
                $vname   = mysqli_real_escape_string($connection, $req_data['vaccine_name']);
                $pname   = mysqli_real_escape_string($connection, $req_data['parent_name']);
                $appt_dt = date('d M Y', strtotime($req_data['preferred_date']));
                $appt_tm = date('h:i A', strtotime($req_data['preferred_time']));
                $h_name  = mysqli_real_escape_string($connection, $hospital['hospital_name']);

                $notif_msg = "Your appointment request for $cname ($vname — Dose {$req_data['dose_number']}) "
                           . "has been APPROVED by $h_name. "
                           . "Date: $appt_dt at $appt_tm. Confirmation Code: $conf_code. "
                           . "Please visit the hospital on the scheduled date.";

                mysqli_query($connection,
                    "INSERT INTO notifications
                     (user_id, notification_type, title, message, related_id, is_read, created_at)
                     VALUES
                     ('$p_user', 'appointment_approved', 'Appointment Approved — Book Your Visit',
                      '$notif_msg', '$req_id', 0, NOW())");

                mysqli_commit($connection);
                $success_msg = "Request #$req_id approved! Booking created. Confirmation: <strong>$conf_code</strong>";

            } catch(Exception $e) {
                mysqli_rollback($connection);
                $error_msg = "Error approving request: " . $e->getMessage();
            }
        } else {
            $error_msg = "Request not found, already processed, or does not belong to your hospital.";
        }

    } elseif($action === 'reject') {

        $chk2 = mysqli_fetch_assoc(mysqli_query($connection,
            "SELECT request_id FROM appointment_requests
             WHERE request_id='$req_id' AND hospital_id='$hospital_id' AND request_status='pending'"));

        if($chk2) {
            mysqli_begin_transaction($connection);
            try {
                // 1. Update status
                mysqli_query($connection,
                    "UPDATE appointment_requests
                     SET request_status='rejected', admin_notes='$notes', processed_at=NOW()
                     WHERE request_id='$req_id' AND hospital_id='$hospital_id'");

                // 2. Fetch info for notification
                $req_data2 = mysqli_fetch_assoc(mysqli_query($connection,
                    "SELECT ar.*, c.full_name as child_name,
                            v.vaccine_name, p.user_id as parent_user_id
                     FROM appointment_requests ar
                     JOIN children c ON ar.child_id  = c.child_id
                     JOIN vaccines v ON ar.vaccine_id = v.vaccine_id
                     JOIN parents  p ON c.parent_id   = p.parent_id
                     WHERE ar.request_id = '$req_id'"));

                if($req_data2) {
                    $p_user2 = $req_data2['parent_user_id'];
                    $cname2  = mysqli_real_escape_string($connection, $req_data2['child_name']);
                    $vname2  = mysqli_real_escape_string($connection, $req_data2['vaccine_name']);
                    $reason  = $notes
                               ? "Reason: " . mysqli_real_escape_string($connection, $notes)
                               : "No reason provided.";
                    $h_name2 = mysqli_real_escape_string($connection, $hospital['hospital_name']);

                    $rej_msg = "Your appointment request for $cname2 ($vname2 — Dose {$req_data2['dose_number']}) "
                             . "has been rejected by $h_name2. $reason "
                             . "You may submit a new request or contact the hospital.";

                    mysqli_query($connection,
                        "INSERT INTO notifications
                         (user_id, notification_type, title, message, related_id, is_read, created_at)
                         VALUES
                         ('$p_user2', 'appointment_rejected', 'Appointment Request Rejected',
                          '$rej_msg', '$req_id', 0, NOW())");
                }

                mysqli_commit($connection);
                $success_msg = "Request #$req_id has been rejected.";

            } catch(Exception $e) {
                mysqli_rollback($connection);
                $error_msg = "Error rejecting request: " . $e->getMessage();
            }
        } else {
            $error_msg = "Request not found, already processed, or does not belong to your hospital.";
        }
    }
}

// ── FILTER ──
$filter_status = isset($_GET['status']) ? mysqli_real_escape_string($connection, $_GET['status']) : 'all';
$filter_date   = isset($_GET['date'])   ? mysqli_real_escape_string($connection, $_GET['date'])   : '';
$search        = isset($_GET['search']) ? mysqli_real_escape_string($connection, $_GET['search']) : '';

$where = "ar.hospital_id = '$hospital_id'";
if($filter_status !== 'all') $where .= " AND ar.request_status = '$filter_status'";
if($filter_date)             $where .= " AND ar.preferred_date = '$filter_date'";
if($search)                  $where .= " AND (c.full_name LIKE '%$search%' OR p.full_name LIKE '%$search%' OR v.vaccine_name LIKE '%$search%')";

// ── COUNTS ──
$cnt_all      = mysqli_fetch_assoc(mysqli_query($connection, "SELECT COUNT(*) as c FROM appointment_requests ar JOIN children c ON ar.child_id=c.child_id JOIN vaccines v ON ar.vaccine_id=v.vaccine_id JOIN parents p ON c.parent_id=p.parent_id WHERE ar.hospital_id='$hospital_id'"))['c'];
$cnt_pending  = mysqli_fetch_assoc(mysqli_query($connection, "SELECT COUNT(*) as c FROM appointment_requests WHERE hospital_id='$hospital_id' AND request_status='pending'"))['c'];
$cnt_approved = mysqli_fetch_assoc(mysqli_query($connection, "SELECT COUNT(*) as c FROM appointment_requests WHERE hospital_id='$hospital_id' AND request_status='approved'"))['c'];
$cnt_rejected = mysqli_fetch_assoc(mysqli_query($connection, "SELECT COUNT(*) as c FROM appointment_requests WHERE hospital_id='$hospital_id' AND request_status='rejected'"))['c'];

// ── MAIN QUERY ──
$q_requests = "SELECT ar.*, c.full_name as child_name, c.date_of_birth, c.gender, c.blood_group,
                      c.medical_conditions, c.allergies,
                      v.vaccine_name, v.vaccine_code,
                      p.full_name as parent_name, p.emergency_contact
               FROM appointment_requests ar
               JOIN children c  ON ar.child_id  = c.child_id
               JOIN vaccines v  ON ar.vaccine_id = v.vaccine_id
               JOIN parents  p  ON c.parent_id   = p.parent_id
               WHERE $where
               ORDER BY FIELD(ar.request_status,'pending','approved','rejected','cancelled'), ar.created_at DESC";
$result_requests = mysqli_query($connection, $q_requests);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Requests — VacciCare</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --blue-900:#0a1628; --blue-700:#1a3a6e; --blue-600:#1e4db7;
            --blue-500:#2563eb; --blue-400:#3b82f6; --blue-100:#dbeafe;
            --blue-50:#eff6ff;  --gray-50:#f8fafc;  --gray-100:#f1f5f9;
            --gray-200:#e2e8f0; --gray-400:#94a3b8; --gray-500:#64748b;
            --gray-700:#334155; --white:#ffffff;    --bg:#f0f4ff;
        }
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--blue-900);min-height:100vh;}

        /* NAVBAR */
        .navbar{position:fixed;top:0;left:0;right:0;z-index:200;background:rgba(255,255,255,.95);backdrop-filter:blur(18px);border-bottom:1px solid var(--gray-200);padding:0 40px;height:68px;display:flex;justify-content:space-between;align-items:center;box-shadow:0 2px 12px rgba(37,99,235,.08);}
        .nav-logo{display:flex;align-items:center;gap:12px;font-weight:800;font-size:18px;color:var(--blue-700);text-decoration:none;}
        .nav-logo .logo-icon{width:40px;height:40px;background:var(--blue-500);border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:20px;box-shadow:0 4px 14px rgba(37,99,235,.35);}
        .nav-links{display:flex;align-items:center;gap:4px;}
        .nav-link{display:flex;align-items:center;gap:7px;padding:8px 14px;border-radius:8px;color:var(--gray-700);text-decoration:none;font-size:13.5px;font-weight:600;transition:all .2s;}
        .nav-link:hover{background:var(--blue-50);color:var(--blue-500);}
        .nav-link.active{background:var(--blue-50);color:var(--blue-500);}
        .nav-badge{background:#ef4444;color:white;font-size:10px;font-weight:700;padding:1px 6px;border-radius:20px;}
        .nav-right{display:flex;align-items:center;gap:12px;}
        .nav-hospital-chip{display:flex;align-items:center;gap:8px;background:var(--gray-50);border:1px solid var(--gray-200);border-radius:10px;padding:7px 14px;font-size:13px;font-weight:600;color:var(--blue-900);}
        .nav-dot{width:7px;height:7px;border-radius:50%;display:inline-block;}
        .dot-green{background:#4ade80;} .dot-yellow{background:#facc15;} .dot-red{background:#f87171;}
        .nav-logout{display:flex;align-items:center;gap:7px;padding:8px 16px;background:#fee2e2;color:#dc2626;border:1px solid #fecaca;border-radius:9px;font-size:13px;font-weight:700;text-decoration:none;transition:all .2s;}
        .nav-logout:hover{background:#dc2626;color:white;}
        .hamburger{display:none;flex-direction:column;gap:5px;cursor:pointer;padding:6px;}
        .hamburger span{width:22px;height:2px;background:var(--gray-700);border-radius:2px;}
        .mobile-menu{display:none;position:fixed;top:68px;left:0;right:0;background:white;border-bottom:1px solid var(--gray-200);padding:12px 20px;z-index:199;box-shadow:0 8px 24px rgba(0,0,0,.1);}
        .mobile-menu.open{display:block;}
        .mobile-menu .nav-link{display:flex;padding:10px 14px;margin-bottom:4px;}

        /* MAIN */
        .main{padding-top:68px;min-height:100vh;}
        .verify-banner{margin:20px 32px 0;padding:13px 20px;border-radius:12px;display:flex;align-items:center;gap:12px;font-size:14px;font-weight:500;}
        .verify-banner.pending{background:#fef9c3;border:1px solid #fde68a;color:#92400e;}
        .verify-banner.verified{background:#dcfce7;border:1px solid #bbf7d0;color:#166534;}
        .verify-banner.inactive{background:#fee2e2;border:1px solid #fecaca;color:#991b1b;}
        .content{padding:24px 32px 48px;}

        /* PAGE HEADER */
        .page-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:28px;flex-wrap:wrap;gap:16px;}
        .page-header h1{font-family:'Playfair Display',serif;font-size:28px;font-weight:800;color:var(--blue-900);}
        .page-header p{font-size:13px;color:var(--gray-400);margin-top:4px;}

        /* ALERTS */
        .alert{padding:14px 20px;border-radius:12px;font-size:14px;font-weight:600;margin-bottom:24px;display:flex;align-items:flex-start;gap:10px;animation:slideDown .3s ease;}
        @keyframes slideDown{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
        .alert-success{background:#dcfce7;border:1px solid #bbf7d0;color:#166534;}
        .alert-error{background:#fee2e2;border:1px solid #fecaca;color:#991b1b;}

        /* STATS */
        .stats-strip{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px;}
        .strip-card{background:white;border:1px solid var(--gray-200);border-radius:14px;padding:18px 20px;display:flex;align-items:center;gap:14px;cursor:pointer;transition:all .2s;text-decoration:none;}
        .strip-card:hover{box-shadow:0 6px 20px rgba(37,99,235,.1);transform:translateY(-2px);}
        .strip-card.active-filter{border-color:var(--blue-400);box-shadow:0 0 0 3px rgba(37,99,235,.12);}
        .strip-icon{width:46px;height:46px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:22px;flex-shrink:0;}
        .si-all{background:var(--blue-50);} .si-pending{background:#fef9c3;} .si-approved{background:#dcfce7;} .si-rejected{background:#fee2e2;}
        .strip-num{font-family:'Playfair Display',serif;font-size:26px;font-weight:800;color:var(--blue-900);line-height:1;}
        .strip-label{font-size:12px;color:var(--gray-500);margin-top:3px;font-weight:500;}

        /* FILTERS */
        .filters-bar{background:white;border:1px solid var(--gray-200);border-radius:14px;padding:18px 22px;margin-bottom:22px;display:flex;gap:14px;align-items:center;flex-wrap:wrap;}
        .filters-bar input,.filters-bar select{padding:9px 14px;border:1.5px solid var(--gray-200);border-radius:9px;font-size:13.5px;font-family:'Plus Jakarta Sans',sans-serif;color:var(--blue-900);background:var(--gray-50);transition:border-color .2s;}
        .filters-bar input:focus,.filters-bar select:focus{outline:none;border-color:var(--blue-400);box-shadow:0 0 0 3px rgba(37,99,235,.1);}
        .search-wrap{position:relative;flex:1;min-width:200px;}
        .search-wrap input{width:100%;padding-left:38px;}
        .search-icon{position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:15px;}
        .filter-btn{padding:9px 20px;background:var(--blue-500);color:white;border:none;border-radius:9px;font-size:13.5px;font-weight:700;font-family:'Plus Jakarta Sans',sans-serif;cursor:pointer;transition:all .2s;}
        .filter-btn:hover{background:var(--blue-600);}
        .reset-btn{padding:9px 18px;background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);border-radius:9px;font-size:13.5px;font-weight:600;font-family:'Plus Jakarta Sans',sans-serif;cursor:pointer;text-decoration:none;transition:all .2s;}
        .reset-btn:hover{background:var(--gray-200);}

        /* TABLE */
        .table-wrap{background:white;border:1px solid var(--gray-200);border-radius:16px;overflow:hidden;box-shadow:0 2px 12px rgba(37,99,235,.06);}
        .table-header{padding:18px 24px;border-bottom:1px solid var(--gray-100);display:flex;align-items:center;justify-content:space-between;}
        .table-title{font-size:15px;font-weight:700;color:var(--blue-900);}
        .table-count{font-size:12px;color:var(--gray-400);background:var(--gray-100);padding:4px 12px;border-radius:20px;font-weight:600;}
        table{width:100%;border-collapse:collapse;}
        thead tr{background:var(--gray-50);}
        thead th{padding:12px 18px;text-align:left;font-size:12px;font-weight:700;color:var(--gray-500);text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid var(--gray-200);}
        tbody tr{border-bottom:1px solid #f4f6ff;transition:background .15s;}
        tbody tr:last-child{border-bottom:none;}
        tbody tr:hover{background:#f8faff;}
        td{padding:14px 18px;font-size:13.5px;vertical-align:middle;}

        .child-cell{display:flex;align-items:center;gap:12px;}
        .child-avatar{width:38px;height:38px;background:linear-gradient(135deg,var(--blue-100),#bfdbfe);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0;}
        .child-name{font-weight:700;color:var(--blue-900);font-size:13.5px;}
        .child-meta{font-size:11.5px;color:var(--gray-400);margin-top:2px;}
        .vaccine-name{font-weight:600;color:var(--blue-700);}
        .dose-badge{display:inline-block;padding:2px 8px;background:var(--blue-50);color:var(--blue-600);border-radius:6px;font-size:11px;font-weight:700;margin-top:3px;}
        .date-val{font-weight:600;color:var(--gray-700);}
        .time-val{font-size:11.5px;color:var(--gray-400);margin-top:2px;}

        .badge{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:20px;font-size:12px;font-weight:700;white-space:nowrap;}
        .badge::before{content:'';width:6px;height:6px;border-radius:50%;}
        .badge-pending{background:#fef9c3;color:#92400e;}
        .badge-pending::before{background:#f59e0b;}
        .badge-approved{background:#dcfce7;color:#166534;}
        .badge-approved::before{background:#22c55e;}
        .badge-rejected{background:#fee2e2;color:#991b1b;}
        .badge-rejected::before{background:#ef4444;}
        .badge-cancelled{background:var(--gray-100);color:var(--gray-500);}
        .badge-cancelled::before{background:var(--gray-400);}

        .action-btns{display:flex;gap:8px;align-items:center;}
        .btn-approve,.btn-reject,.btn-view{padding:7px 14px;border-radius:8px;font-size:12.5px;font-weight:700;border:none;cursor:pointer;transition:all .2s;font-family:'Plus Jakarta Sans',sans-serif;}
        .btn-approve{background:#dcfce7;color:#166534;border:1px solid #bbf7d0;}
        .btn-approve:hover{background:#22c55e;color:white;}
        .btn-reject{background:#fee2e2;color:#991b1b;border:1px solid #fecaca;}
        .btn-reject:hover{background:#ef4444;color:white;}
        .btn-view{background:var(--blue-50);color:var(--blue-600);border:1px solid var(--blue-100);}
        .btn-view:hover{background:var(--blue-500);color:white;}

        .empty-state{padding:60px 24px;text-align:center;color:var(--gray-400);}
        .empty-state .e-icon{font-size:56px;margin-bottom:14px;}
        .empty-state h3{font-size:16px;font-weight:700;margin-bottom:6px;color:var(--gray-500);}
        .empty-state p{font-size:13.5px;}

        /* MODAL */
        .modal-overlay{display:none;position:fixed;inset:0;background:rgba(10,22,40,.5);z-index:1000;align-items:center;justify-content:center;backdrop-filter:blur(4px);}
        .modal-overlay.open{display:flex;}
        .modal{background:white;border-radius:20px;padding:36px;width:100%;max-width:520px;box-shadow:0 24px 60px rgba(0,0,0,.2);animation:modalIn .25s ease;}
        @keyframes modalIn{from{opacity:0;transform:scale(.95) translateY(-10px)}to{opacity:1;transform:scale(1) translateY(0)}}
        .modal h2{font-family:'Playfair Display',serif;font-size:22px;font-weight:800;color:var(--blue-900);margin-bottom:6px;}
        .modal p.modal-sub{font-size:13.5px;color:var(--gray-500);margin-bottom:24px;}
        .modal-info-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px;}
        .mi-label{font-size:11px;font-weight:700;color:var(--gray-400);text-transform:uppercase;letter-spacing:.5px;}
        .mi-val{font-size:14px;font-weight:600;color:var(--blue-900);margin-top:3px;}
        .modal label{display:block;font-size:13px;font-weight:700;color:var(--gray-700);margin-bottom:7px;}
        .modal textarea{width:100%;padding:12px 14px;border:1.5px solid var(--gray-200);border-radius:10px;font-size:14px;font-family:'Plus Jakarta Sans',sans-serif;color:var(--blue-900);resize:vertical;min-height:90px;transition:border-color .2s;}
        .modal textarea:focus{outline:none;border-color:var(--blue-400);box-shadow:0 0 0 3px rgba(37,99,235,.1);}
        .modal-actions{display:flex;gap:12px;margin-top:20px;}
        .modal-btn{flex:1;padding:13px;border-radius:10px;font-size:15px;font-weight:700;font-family:'Plus Jakarta Sans',sans-serif;cursor:pointer;transition:all .2s;border:none;}
        .modal-btn-confirm-approve{background:var(--blue-500);color:white;box-shadow:0 4px 14px rgba(37,99,235,.3);}
        .modal-btn-confirm-approve:hover{background:var(--blue-600);}
        .modal-btn-confirm-reject{background:#ef4444;color:white;box-shadow:0 4px 14px rgba(239,68,68,.3);}
        .modal-btn-confirm-reject:hover{background:#dc2626;}
        .modal-btn-cancel{background:var(--gray-100);color:var(--gray-700);border:1px solid var(--gray-200);}
        .modal-btn-cancel:hover{background:var(--gray-200);}

        .view-modal-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
        .vmi{background:var(--gray-50);border-radius:10px;padding:12px 14px;}
        .vmi .vmi-label{font-size:11px;color:var(--gray-400);font-weight:700;text-transform:uppercase;letter-spacing:.4px;}
        .vmi .vmi-val{font-size:14px;font-weight:600;color:var(--blue-900);margin-top:4px;}
        .notes-box{background:var(--blue-50);border:1px solid var(--blue-100);border-radius:10px;padding:12px 16px;font-size:13.5px;color:var(--blue-700);margin-top:16px;grid-column:1/-1;}

        @media(max-width:1100px){.stats-strip{grid-template-columns:repeat(2,1fr);}}
        @media(max-width:860px){.nav-links{display:none;}.hamburger{display:flex;}.nav-hospital-chip{display:none;}table{display:block;overflow-x:auto;}}
        @media(max-width:640px){.navbar{padding:0 20px;}.content{padding:16px 20px 32px;}.stats-strip{grid-template-columns:1fr 1fr;}.modal{padding:24px 20px;margin:16px;}.modal-info-grid,.view-modal-grid{grid-template-columns:1fr;}}
    </style>
</head>
<body>

<nav class="navbar">
    <a href="dashboard.php" class="nav-logo">
       
        Hospital_Panel
    </a>
    <div class="nav-links">
        <a href="dashboard.php" class="nav-link">Dashboard</a>
        <a href="appointment_requests.php" class="nav-link active">
            Requests
            <?php if($cnt_pending > 0): ?>
            <span class="nav-badge"><?php echo $cnt_pending; ?></span>
            <?php endif; ?>
        </a>
        <a href="todays_schedule.php" class="nav-link">Today's Schedule</a>
        <a href="vaccination_bookings.php" class="nav-link">Bookings</a>
        <a href="vaccine_inventory.php" class="nav-link">Inventory</a>
        <a href="doctors.php" class="nav-link">Doctors</a>
        <a href="vaccination_records.php" class="nav-link">Records</a>
        <a href="my_profile.php" class="nav-link">Profile</a>
    </div>
    <div class="nav-right">
        <div class="nav-hospital-chip">
            <span class="nav-dot <?php echo ($is_verified && $is_active) ? 'dot-green' : ($is_verified ? 'dot-red' : 'dot-yellow'); ?>"></span>
            <?php echo htmlspecialchars($hospital['hospital_name'] ?? 'Hospital'); ?>
        </div>
        <a href="../logout.php" class="nav-logout">Logout</a>
        <div class="hamburger" onclick="toggleMenu()">
            <span></span><span></span><span></span>
        </div>
    </div>
</nav>

<div class="mobile-menu" id="mobileMenu">
    <a href="dashboard.php" class="nav-link">Dashboard</a>
    <a href="appointment_requests.php" class="nav-link active">Requests <?php if($cnt_pending>0): ?><span class="nav-badge"><?php echo $cnt_pending; ?></span><?php endif; ?></a>
    <a href="todays_schedule.php" class="nav-link">Today's Schedule</a>
    <a href="vaccination_bookings.php" class="nav-link">Bookings</a>
    <a href="vaccine_inventory.php" class="nav-link">Inventory</a>
    <a href="doctors.php" class="nav-link">Doctors</a>
    <a href="vaccination_records.php" class="nav-link">Records</a>
    <a href="my_profile.php" class="nav-link">Profile</a>
    <a href="../logout.php" class="nav-logout" style="margin-top:8px;display:inline-flex;">Logout</a>
</div>

<div class="main">

    <?php if(!$is_verified): ?>
    <div class="verify-banner pending"><strong>Pending Verification:</strong> Admin verification ka wait kar raha hai.</div>
    <?php elseif(!$is_active): ?>
    <div class="verify-banner inactive"><strong>Account Inactive:</strong> Admin se rabta karein.</div>
    <?php else: ?>
    <div class="verify-banner verified"><strong>Verified Hospital:</strong> Account verified aur active hai.</div>
    <?php endif; ?>

    <div class="content">
        <div class="page-header">
            <div>
                <h1>Appointment Requests</h1>
                <p><?php echo date('D, d M Y'); ?> &nbsp;·&nbsp; <?php echo $cnt_pending; ?> pending requests</p>
            </div>
        </div>

        <?php if($success_msg): ?>
        <div class="alert alert-success"><?php echo $success_msg; ?></div>
        <?php endif; ?>
        <?php if($error_msg): ?>
        <div class="alert alert-error"><?php echo $error_msg; ?></div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-strip">
            <a href="?status=all" class="strip-card <?php echo $filter_status=='all'?'active-filter':''; ?>">
                <div class="strip-icon si-all">All</div>
                <div><div class="strip-num"><?php echo $cnt_all; ?></div><div class="strip-label">Total Requests</div></div>
            </a>
            <a href="?status=pending" class="strip-card <?php echo $filter_status=='pending'?'active-filter':''; ?>">
                <div class="strip-icon si-pending">...</div>
                <div><div class="strip-num"><?php echo $cnt_pending; ?></div><div class="strip-label">Pending</div></div>
            </a>
            <a href="?status=approved" class="strip-card <?php echo $filter_status=='approved'?'active-filter':''; ?>">
                <div class="strip-icon si-approved">OK</div>
                <div><div class="strip-num"><?php echo $cnt_approved; ?></div><div class="strip-label">Approved</div></div>
            </a>
            <a href="?status=rejected" class="strip-card <?php echo $filter_status=='rejected'?'active-filter':''; ?>">
                <div class="strip-icon si-rejected">X</div>
                <div><div class="strip-num"><?php echo $cnt_rejected; ?></div><div class="strip-label">Rejected</div></div>
            </a>
        </div>

        <!-- Filters -->
        <form method="GET" class="filters-bar">
            <div class="search-wrap">
                <span class="search-icon">S</span>
                <input type="text" name="search" placeholder="Search child, parent, vaccine..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <select name="status">
                <option value="all"       <?php echo $filter_status=='all'?'selected':''; ?>>All Status</option>
                <option value="pending"   <?php echo $filter_status=='pending'?'selected':''; ?>>Pending</option>
                <option value="approved"  <?php echo $filter_status=='approved'?'selected':''; ?>>Approved</option>
                <option value="rejected"  <?php echo $filter_status=='rejected'?'selected':''; ?>>Rejected</option>
                <option value="cancelled" <?php echo $filter_status=='cancelled'?'selected':''; ?>>Cancelled</option>
            </select>
            <input type="date" name="date" value="<?php echo htmlspecialchars($filter_date); ?>" title="Filter by preferred date">
            <button type="submit" class="filter-btn">Filter</button>
            <a href="appointment_requests.php" class="reset-btn">Reset</a>
        </form>

        <!-- Table -->
        <div class="table-wrap">
            <div class="table-header">
                <div class="table-title">Appointment Requests</div>
                <div class="table-count"><?php echo mysqli_num_rows($result_requests); ?> records</div>
            </div>
            <?php if(mysqli_num_rows($result_requests) > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Child Info</th>
                        <th>Vaccine</th>
                        <th>Parent</th>
                        <th>Preferred Date/Time</th>
                        <th>Requested On</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php while($req = mysqli_fetch_assoc($result_requests)):
                    $dob      = $req['date_of_birth'];
                    $age_days = floor((time() - strtotime($dob)) / 86400);
                    $age_y    = floor($age_days / 365);
                    $age_m    = floor(($age_days % 365) / 30);
                    $age_str  = $age_y > 0 ? "{$age_y}y {$age_m}m" : "{$age_m}m";
                ?>
                <tr>
                    <td style="color:var(--gray-400);font-weight:600;font-size:12.5px;">#<?php echo $req['request_id']; ?></td>
                    <td>
                        <div class="child-cell">
                            <div class="child-avatar"><?php echo substr($req['child_name'],0,1); ?></div>
                            <div>
                                <div class="child-name"><?php echo htmlspecialchars($req['child_name']); ?></div>
                                <div class="child-meta"><?php echo $req['gender']; ?> · <?php echo $age_str; ?><?php if($req['blood_group']): ?> · <?php echo $req['blood_group']; ?><?php endif; ?></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="vaccine-name"><?php echo htmlspecialchars($req['vaccine_name']); ?></div>
                        <div class="dose-badge">Dose <?php echo $req['dose_number']; ?></div>
                    </td>
                    <td>
                        <div style="font-weight:600;color:var(--gray-700);"><?php echo htmlspecialchars($req['parent_name']); ?></div>
                        <?php if($req['emergency_contact']): ?>
                        <div style="font-size:11.5px;color:var(--gray-400);"><?php echo $req['emergency_contact']; ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="date-val"><?php echo date('d M Y', strtotime($req['preferred_date'])); ?></div>
                        <div class="time-val"><?php echo $req['preferred_time'] ? date('h:i A', strtotime($req['preferred_time'])) : '—'; ?></div>
                    </td>
                    <td>
                        <div style="font-size:12.5px;color:var(--gray-500);"><?php echo date('d M Y', strtotime($req['created_at'])); ?></div>
                        <div style="font-size:11.5px;color:var(--gray-400);"><?php echo date('h:i A', strtotime($req['created_at'])); ?></div>
                    </td>
                    <td>
                        <span class="badge badge-<?php echo $req['request_status']; ?>">
                            <?php echo ucfirst($req['request_status']); ?>
                        </span>
                    </td>
                    <td>
                        <div class="action-btns">
                            <button class="btn-view" onclick="openViewModal(<?php echo htmlspecialchars(json_encode($req)); ?>)">View</button>
                            <?php if($req['request_status'] === 'pending'): ?>
                            <button class="btn-approve" onclick="openActionModal(<?php echo $req['request_id']; ?>,'approve','<?php echo addslashes(htmlspecialchars($req['child_name'])); ?>','<?php echo addslashes(htmlspecialchars($req['vaccine_name'])); ?>')">Approve</button>
                            <button class="btn-reject"  onclick="openActionModal(<?php echo $req['request_id']; ?>,'reject','<?php echo addslashes(htmlspecialchars($req['child_name'])); ?>','<?php echo addslashes(htmlspecialchars($req['vaccine_name'])); ?>')">Reject</button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <div class="e-icon">[ ]</div>
                <h3>No Requests Found</h3>
                <p>
                    <?php if($filter_status !== 'all' || $search || $filter_date): ?>
                    No requests match your filters. <a href="appointment_requests.php" style="color:var(--blue-500);">Clear filters</a>
                    <?php else: ?>
                    Abhi tak koi appointment request nahi aayi.
                    <?php endif; ?>
                </p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ACTION MODAL -->
<div class="modal-overlay" id="actionModal">
    <div class="modal">
        <h2 id="modalTitle">Approve Request</h2>
        <p class="modal-sub" id="modalSub">Please confirm the action below.</p>
        <div class="modal-info-grid">
            <div class="modal-info-item">
                <div class="mi-label">Child</div>
                <div class="mi-val" id="modalChild">—</div>
            </div>
            <div class="modal-info-item">
                <div class="mi-label">Vaccine</div>
                <div class="mi-val" id="modalVaccine">—</div>
            </div>
        </div>
        <form method="POST" id="actionForm">
            <input type="hidden" name="request_id" id="modalReqId">
            <input type="hidden" name="action"     id="modalAction">
            <label for="admin_notes">Notes (optional)</label>
            <textarea name="admin_notes" id="admin_notes" placeholder="Add a note for this action..."></textarea>
            <div class="modal-actions">
                <button type="button" class="modal-btn modal-btn-cancel" onclick="closeActionModal()">Cancel</button>
                <button type="submit" class="modal-btn" id="modalConfirmBtn">Confirm</button>
            </div>
        </form>
    </div>
</div>

<!-- VIEW MODAL -->
<div class="modal-overlay" id="viewModal">
    <div class="modal" style="max-width:580px;">
        <h2>Request Details</h2>
        <p class="modal-sub" id="viewModalSub"></p>
        <div class="view-modal-grid" id="viewModalGrid"></div>
        <div class="modal-actions" style="margin-top:24px;">
            <button type="button" class="modal-btn modal-btn-cancel" style="max-width:160px;" onclick="closeViewModal()">Close</button>
        </div>
    </div>
</div>

<script>
function toggleMenu(){ document.getElementById('mobileMenu').classList.toggle('open'); }

function openActionModal(reqId,action,childName,vaccineName){
    document.getElementById('modalReqId').value   = reqId;
    document.getElementById('modalAction').value  = action;
    document.getElementById('modalChild').textContent   = childName;
    document.getElementById('modalVaccine').textContent = vaccineName;
    document.getElementById('admin_notes').value  = '';
    const btn = document.getElementById('modalConfirmBtn');
    if(action === 'approve'){
        document.getElementById('modalTitle').textContent = 'Approve Request';
        document.getElementById('modalSub').textContent   = 'Approving will create a booking and notify the parent.';
        btn.textContent = 'Approve Request';
        btn.className   = 'modal-btn modal-btn-confirm-approve';
    } else {
        document.getElementById('modalTitle').textContent = 'Reject Request';
        document.getElementById('modalSub').textContent   = 'Parent will be notified with your reason.';
        btn.textContent = 'Reject Request';
        btn.className   = 'modal-btn modal-btn-confirm-reject';
    }
    document.getElementById('actionModal').classList.add('open');
}
function closeActionModal(){ document.getElementById('actionModal').classList.remove('open'); }

function openViewModal(req){
    document.getElementById('viewModalSub').textContent = 'Request #'+req.request_id+' — '+req.request_status.toUpperCase();
    const fields = [
        {label:'Child Name',val:req.child_name},
        {label:'Gender',val:req.gender},
        {label:'Blood Group',val:req.blood_group||'—'},
        {label:'Parent Name',val:req.parent_name},
        {label:'Emergency Contact',val:req.emergency_contact||'—'},
        {label:'Vaccine',val:req.vaccine_name},
        {label:'Dose Number',val:'Dose '+req.dose_number},
        {label:'Preferred Date',val:req.preferred_date},
        {label:'Preferred Time',val:req.preferred_time||'—'},
        {label:'Status',val:req.request_status.toUpperCase()},
        {label:'Requested On',val:req.created_at},
    ];
    let html = '';
    fields.forEach(f => { html += `<div class="vmi"><div class="vmi-label">${f.label}</div><div class="vmi-val">${f.val}</div></div>`; });
    if(req.parent_notes) html += `<div class="notes-box" style="grid-column:1/-1"><strong>Parent Notes:</strong> ${req.parent_notes}</div>`;
    if(req.admin_notes)  html += `<div class="notes-box" style="grid-column:1/-1;background:#dcfce7;border-color:#bbf7d0;color:#166534"><strong>Hospital Notes:</strong> ${req.admin_notes}</div>`;
    if(req.medical_conditions && req.medical_conditions!=='nothing') html += `<div class="notes-box" style="grid-column:1/-1;background:#fef9c3;border-color:#fde68a;color:#92400e"><strong>Medical Conditions:</strong> ${req.medical_conditions}</div>`;
    if(req.allergies && req.allergies!=='nothing') html += `<div class="notes-box" style="grid-column:1/-1;background:#fee2e2;border-color:#fecaca;color:#991b1b"><strong>Allergies:</strong> ${req.allergies}</div>`;
    document.getElementById('viewModalGrid').innerHTML = html;
    document.getElementById('viewModal').classList.add('open');
}
function closeViewModal(){ document.getElementById('viewModal').classList.remove('open'); }

document.getElementById('actionModal').addEventListener('click',function(e){ if(e.target===this) closeActionModal(); });
document.getElementById('viewModal').addEventListener('click',function(e){ if(e.target===this) closeViewModal(); });
</script>
</body>
</html>