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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            font-family: "Segoe UI", sans-serif;
            min-height: 100vh;
            padding: 20px;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        .card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            border: none;
            overflow: hidden;
        }
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
        }
        .back-btn {
            color: white;
            text-decoration: none;
            padding: 10px 25px;
            border-radius: 30px;
            background: rgba(255,255,255,0.2);
            display: inline-block;
            margin-bottom: 20px;
        }
        .back-btn:hover {
            background: rgba(255,255,255,0.3);
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="parent_dashboard.php" class="back-btn">
            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
        </a>
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-history me-2"></i>Vaccination History</h2>
                <p class="mb-0 text-white-50">Your child's vaccination records</p>
            </div>
            <div class="card-body">
                <div class="text-center py-5">
                    <i class="fas fa-syringe fa-4x text-muted mb-3"></i>
                    <h4>No vaccination records found</h4>
                    <p class="text-muted">Book an appointment to get started</p>
                    <a href="book_appointment.php" class="btn btn-primary mt-3">
                        <i class="fas fa-calendar-plus me-2"></i>Book Appointment
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>