<?php
session_start();
if(!isset($_SESSION["logged_in"]) || $_SESSION["user_type"] != "hospital"){
    header("location:../login.php");
    exit();
}

include("../dbconnection.php");

$user_id = $_SESSION["user_id"];
$today = date('Y-m-d');
$current_time = date('H:i:s');

// Get hospital data
$query_hospital = "SELECT * FROM hospitals WHERE user_id = '$user_id'";
$result_hospital = mysqli_query($connection, $query_hospital);
$hospital = mysqli_fetch_assoc($result_hospital);
$hospital_id = $hospital['hospital_id'] ?? 0;
$is_verified = $hospital['is_verified'] ?? 0;
$is_active = $hospital['is_active'] ?? 0;

// ── MARK AS COMPLETED ──
if(isset($_GET['complete']) && isset($_GET['booking_id'])) {
    $booking_id = intval($_GET['booking_id']);
    
    // Check if booking belongs to this hospital
    $check = mysqli_query($connection,
        "SELECT booking_id FROM vaccination_bookings 
         WHERE booking_id = '$booking_id' AND hospital_id = '$hospital_id'");
    
    if(mysqli_num_rows($check) > 0) {
        // Update booking status to completed
        $update = mysqli_query($connection,
            "UPDATE vaccination_bookings 
             SET booking_status = 'completed', updated_at = NOW() 
             WHERE booking_id = '$booking_id'");
        
        if($update) {
            // Get booking details for notification
            $booking_data = mysqli_fetch_assoc(mysqli_query($connection,
                "SELECT vb.*, c.full_name as child_name, c.parent_id, 
                        v.vaccine_name 
                 FROM vaccination_bookings vb
                 JOIN children c ON vb.child_id = c.child_id
                 JOIN vaccines v ON vb.vaccine_id = v.vaccine_id
                 WHERE vb.booking_id = '$booking_id'"));
            
            // Get parent user_id
            $parent = mysqli_fetch_assoc(mysqli_query($connection,
                "SELECT user_id FROM parents WHERE parent_id = '{$booking_data['parent_id']}'"));
            
            if($parent) {
                // Create notification for parent
                $message = "Vaccination completed for {$booking_data['child_name']} - {$booking_data['vaccine_name']} (Dose {$booking_data['dose_number']})";
                mysqli_query($connection,
                    "INSERT INTO notifications 
                    (user_id, notification_type, title, message, related_id, is_read, created_at)
                    VALUES 
                    ('{$parent['user_id']}', 'vaccination_completed', 'Vaccination Completed',
                     '$message', '$booking_id', 0, NOW())");
            }
            
            $success_msg = "✅ Booking #$booking_id marked as completed!";
        } else {
            $error_msg = "❌ Error updating booking status.";
        }
    } else {
        $error_msg = "❌ Invalid booking ID.";
    }
}

// ── MARK AS MISSED ──
if(isset($_GET['missed']) && isset($_GET['booking_id'])) {
    $booking_id = intval($_GET['booking_id']);
    
    $check = mysqli_query($connection,
        "SELECT booking_id FROM vaccination_bookings 
         WHERE booking_id = '$booking_id' AND hospital_id = '$hospital_id'");
    
    if(mysqli_num_rows($check) > 0) {
        $update = mysqli_query($connection,
            "UPDATE vaccination_bookings 
             SET booking_status = 'missed', updated_at = NOW() 
             WHERE booking_id = '$booking_id'");
        
        if($update) {
            $success_msg = "⏰ Booking #$booking_id marked as missed!";
        } else {
            $error_msg = "❌ Error updating booking status.";
        }
    } else {
        $error_msg = "❌ Invalid booking ID.";
    }
}

// ── FILTERS ──
$filter_status = isset($_GET['status']) ? mysqli_real_escape_string($connection, $_GET['status']) : 'all';
$search = isset($_GET['search']) ? mysqli_real_escape_string($connection, $_GET['search']) : '';

$where = "vb.hospital_id = '$hospital_id'";
if($filter_status !== 'all') {
    $where .= " AND vb.booking_status = '$filter_status'";
}
if($search) {
    $where .= " AND (c.full_name LIKE '%$search%' OR p.full_name LIKE '%$search%' OR v.vaccine_name LIKE '%$search%')";
}

// ── COUNTS ──
$total_today = mysqli_fetch_assoc(mysqli_query($connection,
    "SELECT COUNT(*) as cnt FROM vaccination_bookings 
     WHERE hospital_id = '$hospital_id'"))['cnt'];

$completed_today = mysqli_fetch_assoc(mysqli_query($connection,
    "SELECT COUNT(*) as cnt FROM vaccination_bookings 
     WHERE hospital_id = '$hospital_id' 
     AND booking_status = 'completed'"))['cnt'];

$pending_today = mysqli_fetch_assoc(mysqli_query($connection,
    "SELECT COUNT(*) as cnt FROM vaccination_bookings 
     WHERE hospital_id = '$hospital_id' 
     AND booking_status = 'scheduled'"))['cnt'];

$missed_today = mysqli_fetch_assoc(mysqli_query($connection,
    "SELECT COUNT(*) as cnt FROM vaccination_bookings 
     WHERE hospital_id = '$hospital_id' 
     AND booking_status = 'missed'"))['cnt'];

// ── MAIN QUERY ──
$query = "SELECT vb.*, 
                 c.full_name as child_name, c.date_of_birth, c.gender, c.blood_group,
                 v.vaccine_name, v.vaccine_code,
                 p.full_name as parent_name, p.emergency_contact, p.user_id as parent_user_id,
                 ar.parent_notes
          FROM vaccination_bookings vb
          JOIN children c ON vb.child_id = c.child_id
          JOIN vaccines v ON vb.vaccine_id = v.vaccine_id
          JOIN parents p ON c.parent_id = p.parent_id
          LEFT JOIN appointment_requests ar ON vb.request_id = ar.request_id
          WHERE $where
          ORDER BY vb.appointment_date DESC, vb.appointment_time ASC";

$result = mysqli_query($connection, $query);
$total_records = mysqli_num_rows($result);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Bookings — VacciCare</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --blue-900: #0a1628;
            --blue-700: #1a3a6e;
            --blue-600: #1e4db7;
            --blue-500: #2563eb;
            --blue-400: #3b82f6;
            --blue-100: #dbeafe;
            --blue-50:  #eff6ff;
            --gray-50:  #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-700: #334155;
            --white:    #ffffff;
            --bg:       #f0f4ff;
            --green-100: #dcfce7;
            --green-600: #16a34a;
            --yellow-100: #fef9c3;
            --yellow-600: #ca8a04;
            --red-100: #fee2e2;
            --red-600: #dc2626;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: var(--bg);
            color: var(--blue-900);
            min-height: 100vh;
        }

        /* ── NAVBAR (same as appointment_requests.php) ── */
        .navbar {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 200;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(18px);
            border-bottom: 1px solid var(--gray-200);
            padding: 0 40px;
            height: 68px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 12px rgba(37,99,235,0.08);
        }
        .nav-logo {
            display: flex; align-items: center; gap: 12px;
            font-weight: 800; font-size: 18px; color: var(--blue-700);
            text-decoration: none;
        }
        .nav-logo .logo-icon {
            width: 40px; height: 40px; background: var(--blue-500);
            border-radius: 10px; display: flex; align-items: center;
            justify-content: center; font-size: 20px;
            box-shadow: 0 4px 14px rgba(37,99,235,0.35);
        }
        .nav-links { display: flex; align-items: center; gap: 4px; }
        .nav-link {
            display: flex; align-items: center; gap: 7px;
            padding: 8px 14px; border-radius: 8px; color: var(--gray-700);
            text-decoration: none; font-size: 13.5px; font-weight: 600;
            transition: all 0.2s; position: relative;
        }
        .nav-link:hover { background: var(--blue-50); color: var(--blue-500); }
        .nav-link.active { background: var(--blue-50); color: var(--blue-500); }
        .nav-badge {
            background: #ef4444; color: white; font-size: 10px;
            font-weight: 700; padding: 1px 6px; border-radius: 20px;
        }
        .nav-right { display: flex; align-items: center; gap: 12px; }
        .nav-hospital-chip {
            display: flex; align-items: center; gap: 8px;
            background: var(--gray-50); border: 1px solid var(--gray-200);
            border-radius: 10px; padding: 7px 14px;
            font-size: 13px; font-weight: 600; color: var(--blue-900);
        }
        .nav-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; }
        .dot-green  { background: #4ade80; }
        .dot-yellow { background: #facc15; }
        .dot-red    { background: #f87171; }
        .nav-logout {
            display: flex; align-items: center; gap: 7px;
            padding: 8px 16px; background: #fee2e2; color: #dc2626;
            border: 1px solid #fecaca; border-radius: 9px;
            font-size: 13px; font-weight: 700; text-decoration: none;
            transition: all 0.2s;
        }
        .nav-logout:hover { background: #dc2626; color: white; }
        .hamburger {
            display: none; flex-direction: column; gap: 5px;
            cursor: pointer; padding: 6px;
        }
        .hamburger span { width: 22px; height: 2px; background: var(--gray-700); border-radius: 2px; }
        .mobile-menu {
            display: none; position: fixed; top: 68px; left: 0; right: 0;
            background: white; border-bottom: 1px solid var(--gray-200);
            padding: 12px 20px; z-index: 199;
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }
        .mobile-menu.open { display: block; }
        .mobile-menu .nav-link { display: flex; padding: 10px 14px; margin-bottom: 4px; }

        /* ── MAIN ── */
        .main { padding-top: 68px; min-height: 100vh; }

        /* ── VERIFY BANNER ── */
        .verify-banner {
            margin: 20px 32px 0;
            padding: 13px 20px; border-radius: 12px;
            display: flex; align-items: center; gap: 12px;
            font-size: 14px; font-weight: 500;
        }
        .verify-banner.pending  { background: #fef9c3; border: 1px solid #fde68a; color: #92400e; }
        .verify-banner.verified { background: #dcfce7; border: 1px solid #bbf7d0; color: #166534; }
        .verify-banner.inactive { background: #fee2e2; border: 1px solid #fecaca; color: #991b1b; }

        /* ── CONTENT ── */
        .content { padding: 24px 32px 48px; }

        /* Page Header */
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
            font-size: 28px; font-weight: 800; color: var(--blue-900);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .page-header h1 span {
            font-size: 14px;
            background: var(--blue-100);
            color: var(--blue-600);
            padding: 4px 12px;
            border-radius: 30px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 600;
        }
        .page-header p {
            font-size: 13px; color: var(--gray-400); margin-top: 4px;
        }
        .date-display {
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 30px;
            padding: 8px 18px;
            font-size: 14px;
            font-weight: 600;
            color: var(--blue-700);
            box-shadow: 0 2px 6px rgba(0,0,0,0.02);
        }

        /* ── ALERT MESSAGES ── */
        .alert {
            padding: 14px 20px; border-radius: 12px;
            font-size: 14px; font-weight: 600;
            margin-bottom: 24px;
            display: flex; align-items: center; gap: 10px;
            animation: slideDown 0.3s ease;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .alert-success { background: #dcfce7; border: 1px solid #bbf7d0; color: #166534; }
        .alert-error   { background: #fee2e2; border: 1px solid #fecaca; color: #991b1b; }

        /* ── STATS STRIP ── */
        .stats-strip {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        .strip-card {
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 14px;
            padding: 18px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
        }
        .strip-card:hover { 
            box-shadow: 0 6px 20px rgba(37,99,235,0.1); 
            transform: translateY(-2px); 
        }
        .strip-card.active-filter { 
            border-color: var(--blue-400); 
            box-shadow: 0 0 0 3px rgba(37,99,235,0.12); 
        }
        .strip-icon {
            width: 46px; height: 46px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px; flex-shrink: 0;
        }
        .si-all   { background: var(--blue-50); }
        .si-scheduled { background: #fef9c3; }
        .si-completed { background: #dcfce7; }
        .si-missed { background: #fee2e2; }
        .strip-num {
            font-family: 'Playfair Display', serif;
            font-size: 26px; font-weight: 800; color: var(--blue-900); line-height: 1;
        }
        .strip-label { font-size: 12px; color: var(--gray-500); margin-top: 3px; font-weight: 500; }

        /* ── FILTERS ── */
        .filters-bar {
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 14px;
            padding: 18px 22px;
            margin-bottom: 22px;
            display: flex;
            gap: 14px;
            align-items: center;
            flex-wrap: wrap;
        }
        .filters-bar input, .filters-bar select {
            padding: 9px 14px;
            border: 1.5px solid var(--gray-200);
            border-radius: 9px;
            font-size: 13.5px;
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--blue-900);
            background: var(--gray-50);
            transition: border-color 0.2s;
        }
        .filters-bar input:focus, .filters-bar select:focus {
            outline: none; border-color: var(--blue-400);
            box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
        }
        .search-wrap { position: relative; flex: 1; min-width: 200px; }
        .search-wrap input { width: 100%; padding-left: 38px; }
        .search-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); font-size: 15px; }
        .filter-btn {
            padding: 9px 20px;
            background: var(--blue-500); color: white;
            border: none; border-radius: 9px;
            font-size: 13.5px; font-weight: 700;
            font-family: 'Plus Jakarta Sans', sans-serif;
            cursor: pointer; transition: all 0.2s;
        }
        .filter-btn:hover { background: var(--blue-600); }
        .reset-btn {
            padding: 9px 18px;
            background: var(--gray-100); color: var(--gray-700);
            border: 1px solid var(--gray-200); border-radius: 9px;
            font-size: 13.5px; font-weight: 600;
            font-family: 'Plus Jakarta Sans', sans-serif;
            cursor: pointer; text-decoration: none;
            transition: all 0.2s;
        }
        .reset-btn:hover { background: var(--gray-200); }

        /* ── TABLE ── */
        .table-wrap {
            background: white;
            border: 1px solid var(--gray-200);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(37,99,235,0.06);
        }
        .table-header {
            padding: 18px 24px;
            border-bottom: 1px solid var(--gray-100);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .table-title {
            font-size: 15px; font-weight: 700; color: var(--blue-900);
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .table-count {
            font-size: 12px; color: var(--gray-400);
            background: var(--gray-100); padding: 4px 12px;
            border-radius: 20px; font-weight: 600;
        }

        table { width: 100%; border-collapse: collapse; }
        thead tr { background: var(--gray-50); }
        thead th {
            padding: 12px 18px;
            text-align: left; font-size: 12px; font-weight: 700;
            color: var(--gray-500); text-transform: uppercase;
            letter-spacing: 0.5px; border-bottom: 1px solid var(--gray-200);
        }
        tbody tr {
            border-bottom: 1px solid #f4f6ff;
            transition: background 0.15s;
        }
        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: #f8faff; }
        td { padding: 14px 18px; font-size: 13.5px; vertical-align: middle; }

        /* Time cell */
        .time-badge {
            background: linear-gradient(135deg, var(--blue-100), #bfdbfe);
            color: var(--blue-700);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            display: inline-block;
            text-align: center;
            min-width: 70px;
        }

        /* Child cell */
        .child-cell { display: flex; align-items: center; gap: 12px; }
        .child-avatar {
            width: 38px; height: 38px;
            background: linear-gradient(135deg, var(--blue-100), #bfdbfe);
            border-radius: 50%; display: flex; align-items: center;
            justify-content: center; font-size: 18px; flex-shrink: 0;
        }
        .child-name { font-weight: 700; color: var(--blue-900); font-size: 13.5px; }
        .child-meta { font-size: 11.5px; color: var(--gray-400); margin-top: 2px; }

        /* Vaccine cell */
        .vaccine-name { font-weight: 600; color: var(--blue-700); }
        .dose-badge {
            display: inline-block; padding: 2px 8px;
            background: var(--blue-50); color: var(--blue-600);
            border-radius: 6px; font-size: 11px; font-weight: 700;
            margin-top: 3px;
        }

        /* Confirmation code */
        .conf-code {
            font-family: monospace;
            font-size: 12px;
            font-weight: 700;
            background: var(--gray-100);
            padding: 3px 8px;
            border-radius: 6px;
            color: var(--gray-700);
        }

        /* Status badges */
        .badge {
            display: inline-flex; align-items: center; gap: 5px;
            padding: 5px 12px; border-radius: 20px;
            font-size: 12px; font-weight: 700; white-space: nowrap;
        }
        .badge::before { content: ''; width: 6px; height: 6px; border-radius: 50%; }
        .badge-scheduled  { background: #fef9c3; color: #92400e; }
        .badge-scheduled::before  { background: #f59e0b; }
        .badge-completed { background: #dcfce7; color: #166534; }
        .badge-completed::before { background: #22c55e; }
        .badge-missed { background: #fee2e2; color: #991b1b; }
        .badge-missed::before { background: #ef4444; }
        .badge-cancelled { background: var(--gray-100); color: var(--gray-500); }
        .badge-cancelled::before { background: var(--gray-400); }

        /* Action buttons */
        .action-btns { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .btn-complete, .btn-missed, .btn-view, .btn-record {
            padding: 7px 14px; border-radius: 8px;
            font-size: 12.5px; font-weight: 700;
            border: none; cursor: pointer; transition: all 0.2s;
            font-family: 'Plus Jakarta Sans', sans-serif;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-complete {
            background: #dcfce7; color: #166534;
            border: 1px solid #bbf7d0;
        }
        .btn-complete:hover { background: #22c55e; color: white; }
        .btn-missed {
            background: #fee2e2; color: #991b1b;
            border: 1px solid #fecaca;
        }
        .btn-missed:hover { background: #ef4444; color: white; }
        .btn-view {
            background: var(--blue-50); color: var(--blue-600);
            border: 1px solid var(--blue-100);
        }
        .btn-view:hover { background: var(--blue-500); color: white; }
        .btn-record {
            background: #ede9fe; color: #5b21b6;
            border: 1px solid #ddd6fe;
        }
        .btn-record:hover { background: #8b5cf6; color: white; }

        /* Empty state */
        .empty-state {
            padding: 60px 24px; text-align: center; color: var(--gray-400);
        }
        .empty-state .e-icon { font-size: 56px; margin-bottom: 14px; }
        .empty-state h3 { font-size: 16px; font-weight: 700; margin-bottom: 6px; color: var(--gray-500); }
        .empty-state p { font-size: 13.5px; }

        /* ── MODAL ── */
        .modal-overlay {
            display: none; position: fixed; inset: 0;
            background: rgba(10,22,40,0.5); z-index: 1000;
            align-items: center; justify-content: center;
            backdrop-filter: blur(4px);
        }
        .modal-overlay.open { display: flex; }
        .modal {
            background: white; border-radius: 20px;
            padding: 36px; width: 100%; max-width: 600px;
            box-shadow: 0 24px 60px rgba(0,0,0,0.2);
            animation: modalIn 0.25s ease;
        }
        @keyframes modalIn {
            from { opacity: 0; transform: scale(0.95) translateY(-10px); }
            to   { opacity: 1; transform: scale(1) translateY(0); }
        }
        .modal h2 {
            font-family: 'Playfair Display', serif;
            font-size: 24px; font-weight: 800; color: var(--blue-900);
            margin-bottom: 6px;
        }
        .modal p.modal-sub {
            font-size: 13.5px; color: var(--gray-500); 
            margin-bottom: 24px; padding-bottom: 12px;
            border-bottom: 1px solid var(--gray-100);
        }
        .modal-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 20px;
        }
        .modal-item { }
        .modal-item .label {
            font-size: 11px; font-weight: 700; color: var(--gray-400);
            text-transform: uppercase; letter-spacing: 0.5px;
        }
        .modal-item .value {
            font-size: 15px; font-weight: 600; color: var(--blue-900);
            margin-top: 4px;
        }
        .modal-notes {
            grid-column: 1/-1;
            background: var(--blue-50);
            border: 1px solid var(--blue-100);
            border-radius: 10px;
            padding: 14px 18px;
            font-size: 13.5px;
            color: var(--blue-700);
        }
        .modal-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
            justify-content: flex-end;
        }
        .modal-btn {
            padding: 12px 24px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .modal-btn-primary {
            background: var(--blue-500);
            color: white;
        }
        .modal-btn-primary:hover {
            background: var(--blue-600);
        }
        .modal-btn-secondary {
            background: var(--gray-100);
            color: var(--gray-700);
            border: 1px solid var(--gray-200);
        }
        .modal-btn-secondary:hover {
            background: var(--gray-200);
        }

        /* Responsive */
        @media(max-width: 1100px) {
            .stats-strip { grid-template-columns: repeat(2, 1fr); }
        }
        @media(max-width: 860px) {
            .nav-links { display: none; }
            .hamburger { display: flex; }
            .nav-hospital-chip { display: none; }
            table { display: block; overflow-x: auto; }
        }
        @media(max-width: 640px) {
            .navbar { padding: 0 20px; }
            .content { padding: 16px 20px 32px; }
            .stats-strip { grid-template-columns: 1fr 1fr; }
            .modal { padding: 24px 20px; margin: 16px; }
            .modal-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- ── NAVBAR ── -->
<nav class="navbar">
    <a href="dashboard.php" class="nav-logo">
       
        Hospital_Panel
    </a>
    <div class="nav-links">
        <a href="dashboard.php" class="nav-link">Dashboard</a>
        <a href="appointment_requests.php" class="nav-link">
            Requests
            <?php 
            $pending_count = mysqli_fetch_assoc(mysqli_query($connection, 
                "SELECT COUNT(*) as cnt FROM appointment_requests WHERE hospital_id='$hospital_id' AND request_status='pending'"))['cnt'];
            if($pending_count > 0): ?>
            <span class="nav-badge"><?php echo $pending_count; ?></span>
            <?php endif; ?>
        </a>
        <a href="todays_schedule.php" class="nav-link active">
            All Bookings
            <?php if($pending_today > 0): ?>
            <span class="nav-badge"><?php echo $pending_today; ?></span>
            <?php endif; ?>
        </a>
        <a href="vaccination_bookings.php" class="nav-link">Bookings</a>
        <a href="vaccine_inventory.php" class="nav-link"> Inventory</a>
        <a href="doctors.php" class="nav-link"> Doctors</a>
        <a href="vaccination_records.php" class="nav-link"> Records</a>
        <a href="my_profile.php" class="nav-link"> Profile</a>
    </div>
    <div class="nav-right">
        <div class="nav-hospital-chip">
            <span class="nav-dot <?php echo ($is_verified && $is_active) ? 'dot-green' : ($is_verified ? 'dot-red' : 'dot-yellow'); ?>"></span>
            <?php echo htmlspecialchars($hospital['hospital_name'] ?? 'Hospital'); ?>
        </div>
        <a href="../logout.php" class="nav-logout">🚪 Logout</a>
        <div class="hamburger" onclick="toggleMenu()">
            <span></span><span></span><span></span>
        </div>
    </div>
</nav>

<!-- Mobile Menu -->
<div class="mobile-menu" id="mobileMenu">
    <a href="dashboard.php" class="nav-link">🏠 Dashboard</a>
    <a href="appointment_requests.php" class="nav-link">📋 Requests <?php if($pending_count > 0): ?><span class="nav-badge"><?php echo $pending_count; ?></span><?php endif; ?></a>
    <a href="todays_schedule.php" class="nav-link active">📅 All Bookings</a>
    <a href="vaccination_bookings.php" class="nav-link">💉 Bookings</a>
    <a href="vaccine_inventory.php" class="nav-link">🧪 Inventory</a>
    <a href="doctors.php" class="nav-link">👨‍⚕️ Doctors</a>
    <a href="vaccination_records.php" class="nav-link">📁 Records</a>
    <a href="my_profile.php" class="nav-link">🏥 Profile</a>
    <a href="../logout.php" class="nav-logout" style="margin-top:8px; display:inline-flex;">🚪 Logout</a>
</div>

<div class="main">

    <!-- Verify Banner -->
    <?php if(!$is_verified): ?>
    <div class="verify-banner pending">⏳ <strong>Pending Verification:</strong> Admin verification ka wait kar raha hai.</div>
    <?php elseif(!$is_active): ?>
    <div class="verify-banner inactive">🚫 <strong>Account Inactive:</strong> Admin se rabta karein.</div>
    <?php else: ?>
    <div class="verify-banner verified">✅ <strong>Verified Hospital:</strong> Account verified aur active hai.</div>
    <?php endif; ?>

    <div class="content">

        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1>
                    📅 All Bookings
                    <span><?php echo date('d M Y', strtotime($today)); ?></span>
                </h1>
                <p>🔔 Aaj ke tamam appointments ki list · Total: <?php echo $total_today; ?> bookings</p>
            </div>
            <div class="date-display">
                🕐 <?php echo date('h:i A'); ?>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if(isset($success_msg)): ?>
        <div class="alert alert-success">✅ <?php echo $success_msg; ?></div>
        <?php endif; ?>
        <?php if(isset($error_msg)): ?>
        <div class="alert alert-error">❌ <?php echo $error_msg; ?></div>
        <?php endif; ?>

        <!-- Stats Strip -->
        <div class="stats-strip">
            <a href="?status=all" class="strip-card <?php echo $filter_status=='all'?'active-filter':''; ?>">
                <div class="strip-icon si-all">📋</div>
                <div>
                    <div class="strip-num"><?php echo $total_today; ?></div>
                    <div class="strip-label">Total Today</div>
                </div>
            </a>
            <a href="?status=scheduled" class="strip-card <?php echo $filter_status=='scheduled'?'active-filter':''; ?>">
                <div class="strip-icon si-scheduled">⏳</div>
                <div>
                    <div class="strip-num"><?php echo $pending_today; ?></div>
                    <div class="strip-label">Pending</div>
                </div>
            </a>
            <a href="?status=completed" class="strip-card <?php echo $filter_status=='completed'?'active-filter':''; ?>">
                <div class="strip-icon si-completed">✅</div>
                <div>
                    <div class="strip-num"><?php echo $completed_today; ?></div>
                    <div class="strip-label">Completed</div>
                </div>
            </a>
            <a href="?status=missed" class="strip-card <?php echo $filter_status=='missed'?'active-filter':''; ?>">
                <div class="strip-icon si-missed">❌</div>
                <div>
                    <div class="strip-num"><?php echo $missed_today; ?></div>
                    <div class="strip-label">Missed</div>
                </div>
            </a>
        </div>

        <!-- Filters -->
        <form method="GET" class="filters-bar">
            <div class="search-wrap">
                <span class="search-icon">🔍</span>
                <input type="text" name="search" placeholder="Search child, parent, vaccine..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <select name="status">
                <option value="all"       <?php echo $filter_status=='all'?'selected':''; ?>>All Status</option>
                <option value="scheduled" <?php echo $filter_status=='scheduled'?'selected':''; ?>>Pending</option>
                <option value="completed" <?php echo $filter_status=='completed'?'selected':''; ?>>Completed</option>
                <option value="missed"    <?php echo $filter_status=='missed'?'selected':''; ?>>Missed</option>
                <option value="cancelled" <?php echo $filter_status=='cancelled'?'selected':''; ?>>Cancelled</option>
            </select>
            <button type="submit" class="filter-btn">🔍 Filter</button>
            <a href="todays_schedule.php" class="reset-btn">↺ Reset</a>
        </form>

        <!-- Table -->
        <div class="table-wrap">
            <div class="table-header">
                <div class="table-title">
                    📋 Today's Appointments
                    <span style="font-size:12px; color:var(--gray-400); font-weight:normal;">
                        (<?php echo date('l, d F Y', strtotime($today)); ?>)
                    </span>
                </div>
                <div class="table-count"><?php echo $total_records; ?> records</div>
            </div>

            <?php if($total_records > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Child Info</th>
                        <th>Vaccine</th>
                        <th>Parent</th>
                        <th>Confirmation</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php 
                $current_time_display = date('H:i');
                while($row = mysqli_fetch_assoc($result)):
                    $age_days = floor((time() - strtotime($row['date_of_birth'])) / 86400);
                    $age_y = floor($age_days / 365);
                    $age_m = floor(($age_days % 365) / 30);
                    $age_str = $age_y > 0 ? "{$age_y}y {$age_m}m" : "{$age_m}m";
                    
                    $is_past = ($row['appointment_time'] < $current_time_display && $row['booking_status'] == 'scheduled');
                ?>
                <tr style="<?php echo $is_past ? 'opacity:0.8; background:#fafafa;' : ''; ?>">
                    <td>
                        <div class="time-badge">
                            <?php echo date('h:i A', strtotime($row['appointment_time'])); ?>
                        </div>
                        <?php if($is_past): ?>
                        <div style="font-size:10px; color:#ef4444; margin-top:4px;">⏰ Past time</div>
                        <?php endif; ?>
                    </td>

                    <td>
                        <div class="child-cell">
                            <div class="child-avatar">
                                <?php echo $row['gender']=='Female' ? '👧' : '👦'; ?>
                            </div>
                            <div>
                                <div class="child-name"><?php echo htmlspecialchars($row['child_name']); ?></div>
                                <div class="child-meta">
                                    <?php echo $row['gender']; ?> · <?php echo $age_str; ?>
                                    <?php if($row['blood_group']): ?> · 🩸<?php echo $row['blood_group']; ?><?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </td>

                    <td>
                        <div class="vaccine-name"><?php echo htmlspecialchars($row['vaccine_name']); ?></div>
                        <div class="dose-badge">Dose <?php echo $row['dose_number']; ?></div>
                        <?php if($row['vaccine_code']): ?>
                        <div style="font-size:10px; color:var(--gray-400); margin-top:2px;"><?php echo $row['vaccine_code']; ?></div>
                        <?php endif; ?>
                    </td>

                    <td>
                        <div style="font-weight:600; color:var(--gray-700);"><?php echo htmlspecialchars($row['parent_name']); ?></div>
                        <?php if($row['emergency_contact']): ?>
                        <div style="font-size:11.5px; color:var(--gray-400);">📞 <?php echo $row['emergency_contact']; ?></div>
                        <?php endif; ?>
                    </td>

                    <td>
                        <span class="conf-code"><?php echo $row['confirmation_code']; ?></span>
                    </td>

                    <td>
                        <span class="badge badge-<?php echo $row['booking_status']; ?>">
                            <?php echo ucfirst($row['booking_status']); ?>
                        </span>
                    </td>

                    <td>
                        <div class="action-btns">
                            <!-- View Details Button -->
                            <button class="btn-view" onclick="openViewModal(<?php echo htmlspecialchars(json_encode($row)); ?>)">👁 View</button>
                            
                            <!-- Actions for scheduled bookings -->
                            <?php if($row['booking_status'] == 'scheduled'): ?>
                                <a href="?complete=1&booking_id=<?php echo $row['booking_id']; ?>" 
                                   class="btn-complete"
                                   onclick="return confirm('✅ Mark this vaccination as COMPLETED?')">✅ Complete</a>
                                <a href="?missed=1&booking_id=<?php echo $row['booking_id']; ?>" 
                                   class="btn-missed"
                                   onclick="return confirm('⏰ Mark this appointment as MISSED?')">⏰ Missed</a>
                            <?php endif; ?>
                            
                            <!-- If completed, link to add vaccination record -->
                            <?php if($row['booking_status'] == 'completed'): 
                                // Check if record already exists
                                $record_exists = mysqli_fetch_assoc(mysqli_query($connection,
                                    "SELECT record_id FROM vaccination_records WHERE booking_id = '{$row['booking_id']}'"));
                                if(!$record_exists):
                            ?>
                                <a href="add_vaccination_record.php?booking_id=<?php echo $row['booking_id']; ?>" 
                                   class="btn-record">📝 Add Record</a>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <div class="e-icon">📭</div>
                <h3>No Appointments Today</h3>
                <p>
                    <?php if($filter_status !== 'all' || $search): ?>
                    No appointments match your current filters. <a href="todays_schedule.php" style="color:var(--blue-500);">Clear filters</a>
                    <?php else: ?>
                    Abhi tak koi booking nahi hai. <br>
                    Jab koi request approve hogi to yahan dikhegi.
                    <?php endif; ?>
                </p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Time Legend -->
        <div style="display: flex; gap: 16px; margin-top: 16px; font-size: 12px; color: var(--gray-500); background: white; padding: 12px 20px; border-radius: 10px; border: 1px solid var(--gray-200);">
            <span><span style="display:inline-block; width:10px; height:10px; background: #f59e0b; border-radius:50%; margin-right:6px;"></span> Pending</span>
            <span><span style="display:inline-block; width:10px; height:10px; background: #22c55e; border-radius:50%; margin-right:6px;"></span> Completed</span>
            <span><span style="display:inline-block; width:10px; height:10px; background: #ef4444; border-radius:50%; margin-right:6px;"></span> Missed</span>
            <span><span style="display:inline-block; width:10px; height:10px; background: #6b7280; border-radius:50%; margin-right:6px;"></span> Cancelled</span>
            <span style="margin-left:auto;">🕐 Past time appointments are dimmed</span>
        </div>

    </div><!-- end content -->
</div><!-- end main -->

<!-- ══ VIEW MODAL ══ -->
<div class="modal-overlay" id="viewModal">
    <div class="modal">
        <h2>📋 Appointment Details</h2>
        <p class="modal-sub" id="viewModalSub">Complete information about this booking</p>
        <div class="modal-grid" id="viewModalGrid">
            <!-- Filled by JavaScript -->
        </div>
        <div class="modal-actions">
            <button type="button" class="modal-btn modal-btn-secondary" onclick="closeViewModal()">Close</button>
        </div>
    </div>
</div>

<script>
// ── Navbar toggle ──
function toggleMenu() {
    document.getElementById('mobileMenu').classList.toggle('open');
}

// ── View Modal ──
function openViewModal(booking) {
    document.getElementById('viewModalSub').textContent = 'Booking #' + booking.booking_id + ' — ' + booking.booking_status.toUpperCase();
    
    const fields = [
        { label: 'Child Name', val: booking.child_name },
        { label: 'Gender', val: booking.gender },
        { label: 'Age', val: calculateAge(booking.date_of_birth) },
        { label: 'Blood Group', val: booking.blood_group || '—' },
        { label: 'Parent Name', val: booking.parent_name },
        { label: 'Emergency Contact', val: booking.emergency_contact || '—' },
        { label: 'Vaccine', val: booking.vaccine_name + ' (' + (booking.vaccine_code || '') + ')' },
        { label: 'Dose Number', val: 'Dose ' + booking.dose_number },
        { label: 'Date', val: booking.appointment_date },
        { label: 'Time', val: booking.appointment_time ? new Date('1970-01-01T' + booking.appointment_time).toLocaleTimeString('en-US', {hour: '2-digit', minute:'2-digit'}) : '—' },
        { label: 'Confirmation Code', val: booking.confirmation_code || '—' },
        { label: 'Status', val: booking.booking_status.toUpperCase() },
    ];
    
    let html = '';
    fields.forEach(f => {
        html += `
            <div class="modal-item">
                <div class="label">${f.label}</div>
                <div class="value">${f.val}</div>
            </div>
        `;
    });
    
    if(booking.parent_notes) {
        html += `<div class="modal-notes"><strong>Parent Notes:</strong> ${booking.parent_notes}</div>`;
    }
    
    document.getElementById('viewModalGrid').innerHTML = html;
    document.getElementById('viewModal').classList.add('open');
}

function calculateAge(dob) {
    let birthDate = new Date(dob);
    let today = new Date();
    let ageDays = Math.floor((today - birthDate) / (1000 * 60 * 60 * 24));
    let years = Math.floor(ageDays / 365);
    let months = Math.floor((ageDays % 365) / 30);
    return years > 0 ? years + 'y ' + months + 'm' : months + ' months';
}

function closeViewModal() {
    document.getElementById('viewModal').classList.remove('open');
}

// Close modal on overlay click
document.getElementById('viewModal').addEventListener('click', function(e) {
    if(e.target === this) closeViewModal();
});

// Auto-hide alerts after 5 seconds
setTimeout(function() {
    document.querySelectorAll('.alert').forEach(el => {
        el.style.opacity = '0';
        setTimeout(() => el.style.display = 'none', 300);
    });
}, 5000);
</script>

</body>
</html>