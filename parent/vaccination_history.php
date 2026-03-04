<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vaccination History</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', Arial, sans-serif;
            background: #f0f4ff;
            color: #1a1a2e;
            min-height: 100vh;
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
        .navbar-links { display: flex; align-items: center; gap: 6px; }
        .navbar-links a {
            color: #4b6cb7;
            text-decoration: none;
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 13.5px;
            font-weight: 500;
            transition: all 0.2s;
        }
        .navbar-links a:hover {
            background: #eff6ff;
            color: #1d4ed8;
        }
        .navbar-links a.active-link {
            background: #dbeafe;
            color: #1d4ed8;
            font-weight: 600;
        }
        .navbar-links a.logout {
            background: #fee2e2;
            color: #dc2626;
        }
        .navbar-links a.logout:hover { background: #fecaca; }

        /* ── LAYOUT ── */
        .container { max-width: 1200px; margin: 32px auto; padding: 0 24px; }

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

        /* ── PAGE HEADER ── */
        .page-header {
            background: linear-gradient(135deg, #1d4ed8 0%, #3b82f6 60%, #60a5fa 100%);
            border-radius: 18px;
            padding: 32px 36px;
            margin-bottom: 28px;
            color: white;
            box-shadow: 0 8px 32px rgba(59,130,246,0.3);
            position: relative;
            overflow: hidden;
        }
        .page-header::before {
            content: '';
            position: absolute;
            top: -40px; right: -40px;
            width: 200px; height: 200px;
            background: rgba(255,255,255,0.08);
            border-radius: 50%;
        }
        .page-header::after {
            content: '';
            position: absolute;
            bottom: -60px; right: 80px;
            width: 160px; height: 160px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
        }
        .page-header h2 {
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 6px;
            position: relative;
            z-index: 1;
        }
        .page-header p {
            font-size: 14px;
            opacity: 0.85;
            position: relative;
            z-index: 1;
        }

        /* ── CARD ── */
        .card {
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(59,130,246,0.07);
            border: 1px solid #e8eeff;
            overflow: hidden;
        }

        /* ── TABLE ── */
        table { width: 100%; border-collapse: collapse; }
        thead th {
            background: #f8faff;
            padding: 13px 20px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: #6b7280;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e8eeff;
        }
        tbody td {
            padding: 15px 20px;
            font-size: 14px;
            color: #374151;
            border-bottom: 1px solid #f4f6ff;
        }
        tbody tr:last-child td { border-bottom: none; }
        tbody tr:hover td { background: #f8faff; }

        /* ── BADGES ── */
        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-completed  { background: #dcfce7; color: #166534; }
        .badge-scheduled  { background: #dbeafe; color: #1d4ed8; }
        .badge-pending    { background: #fef9c3; color: #854d0e; }
        .badge-cancelled  { background: #f3f4f6; color: #6b7280; }

        /* ── EMPTY STATE ── */
        .empty-state {
            text-align: center;
            padding: 60px 40px;
            color: #9ca3af;
        }
        .empty-state span { font-size: 52px; display: block; margin-bottom: 16px; }
        .empty-state h4 { font-size: 18px; color: #374151; font-weight: 700; margin-bottom: 8px; }
        .empty-state p { font-size: 14px; margin-bottom: 24px; }
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            padding: 12px 28px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.22s;
            box-shadow: 0 4px 14px rgba(29,78,216,0.2);
            border: none;
            cursor: pointer;
            font-family: 'Inter', Arial, sans-serif;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(29,78,216,0.3);
            color: white;
        }

        /* ── RESPONSIVE ── */
        @media(max-width: 768px) {
            .navbar { padding: 0 16px; }
            .container { padding: 0 14px; }
            table { font-size: 13px; }
            thead th, tbody td { padding: 10px 12px; }
        }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <div class="navbar">
        <h2> Parent_Panel</h2>
        <div class="navbar-links">
            <a href="dashboard.php">Dashboard</a>
            <a href="my_children.php">My Children</a>
            <a href="book_appointment.php">Book</a>
            <a href="my_requests.php">My Requests</a>
            <a href="vaccination_history.php" class="active-link">History</a>
            <a href="myprofile.php">Profile</a>
            <a href="../logout.php" class="logout">Logout</a>
        </div>
    </div>

    <div class="container">

        <a href="dashboard.php" class="back-link">← Back to Dashboard</a>

        <!-- PAGE HEADER -->
        <div class="page-header">
            <h2>💉 Vaccination History</h2>
            <p>Your child's complete vaccination records</p>
        </div>

        <!-- CARD -->
        <div class="card">
            <div class="empty-state">
                <span>💉</span>
                <h4>No vaccination records found</h4>
                <p>Book an appointment to get started</p>
                <a href="book_appointment.php" class="btn-primary">📅 Book Appointment</a>
            </div>
        </div>

    </div>

</body>
</html>