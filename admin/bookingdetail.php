<?php
session_start();
include("../dbconnection.php");

// Check if admin is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['user_type'] != 'admin') {
    header("Location: ../login.php");
    exit();
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? sanitize_input($_GET['status']) : '';
$search = isset($_GET['search']) ? sanitize_input($_GET['search']) : '';

// Sirf wahi bookings jo vaccine book ki hain ya lagwai hain - FULL QUERY
$query = "SELECT 
            vb.booking_id,
            vb.appointment_date as booking_date,
            vb.appointment_time,
            vb.booking_status,
            vb.confirmation_code,
            ar.request_status,
            c.full_name as child_name,
            p.full_name as parent_name,
            p.emergency_contact as parent_phone,
            v.vaccine_name,
            h.hospital_name,
            h.city as hospital_city,
            vr.vaccination_date,
            vr.vaccination_time,
            CASE 
                WHEN vb.booking_status = 'completed' THEN 'Vaccinated'
                WHEN vb.booking_status = 'cancelled' THEN 'Cancelled'
                WHEN vb.booking_status = 'scheduled' AND ar.request_status = 'approved' THEN 'Approved'
                WHEN vb.booking_status = 'scheduled' AND ar.request_status = 'pending' THEN 'Pending'
                ELSE vb.booking_status
            END as display_status
          FROM vaccination_bookings vb
          INNER JOIN appointment_requests ar ON vb.request_id = ar.request_id
          INNER JOIN children c ON vb.child_id = c.child_id
          INNER JOIN parents p ON c.parent_id = p.parent_id
          INNER JOIN vaccines v ON vb.vaccine_id = v.vaccine_id
          INNER JOIN hospitals h ON vb.hospital_id = h.hospital_id
          LEFT JOIN vaccination_records vr ON vb.booking_id = vr.booking_id
          WHERE 1=1";

// Apply status filter
if (!empty($status_filter) && $status_filter != 'all') {
    if ($status_filter == 'vaccinated') {
        $query .= " AND vb.booking_status = 'completed'";
    } elseif ($status_filter == 'pending') {
        $query .= " AND ar.request_status = 'pending' AND vb.booking_status = 'scheduled'";
    } elseif ($status_filter == 'approved') {
        $query .= " AND ar.request_status = 'approved' AND vb.booking_status = 'scheduled'";
    } elseif ($status_filter == 'cancelled') {
        $query .= " AND vb.booking_status = 'cancelled'";
    }
}

// Apply search filter
if (!empty($search)) {
    $search_term = "%$search%";
    $query .= " AND (c.full_name LIKE '%$search_term%' 
                    OR p.full_name LIKE '%$search_term%' 
                    OR v.vaccine_name LIKE '%$search_term%' 
                    OR h.hospital_name LIKE '%$search_term%'
                    OR vb.confirmation_code LIKE '%$search_term%')";
}

// Order by most recent
$query .= " ORDER BY vb.appointment_date DESC, vb.appointment_time DESC";

// Execute query
$result = mysqli_query($connection, $query);
if (!$result) {
    die("Query failed: " . mysqli_error($connection));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vaccination Bookings - Admin</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 30px;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        /* Header */
        .page-header {
            background: white;
            border-radius: 16px;
            padding: 25px 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .page-header h1 {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 0;
        }
        
        .page-header p {
            color: #6b7280;
            margin-top: 5px;
            font-size: 0.9rem;
        }
        
        /* Filter Section */
        .filter-section {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .filter-form {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .search-box {
            flex: 1;
            position: relative;
            min-width: 300px;
        }
        
        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
        }
        
        .search-box input {
            width: 100%;
            padding: 12px 12px 12px 45px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 0.95rem;
            transition: all 0.3s;
        }
        
        .search-box input:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }
        
        .filter-select {
            padding: 12px 20px;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 0.95rem;
            min-width: 180px;
            cursor: pointer;
        }
        
        .filter-select:focus {
            border-color: #667eea;
            outline: none;
        }
        
        .btn-filter {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-filter:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.4);
        }
        
        .btn-reset {
            background: #f3f4f6;
            color: #4b5563;
            border: none;
            border-radius: 10px;
            padding: 12px 30px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }
        
        .btn-reset:hover {
            background: #e5e7eb;
        }
        
        /* Status Badges */
        .status-badge {
            padding: 6px 16px;
            border-radius: 30px;
            font-size: 0.8rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-transform: uppercase;
        }
        
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-approved {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .status-vaccinated {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-cancelled {
            background: #fee2e2;
            color: #991b1b;
        }
        
        /* Table Card */
        .table-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f3f4f6;
        }
        
        .table-header h3 {
            font-size: 1.3rem;
            font-weight: 700;
            color: #1f2937;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .table-header h3 i {
            color: #667eea;
        }
        
        .booking-count {
            background: #f3f4f6;
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 0.9rem;
            font-weight: 600;
            color: #4b5563;
        }
        
        /* Table */
        .table-responsive {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        thead th {
            background: #f9fafb;
            padding: 16px 12px;
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #4b5563;
            border-bottom: 2px solid #e5e7eb;
            text-align: left;
        }
        
        tbody td {
            padding: 20px 12px;
            border-bottom: 1px solid #f3f4f6;
            color: #1f2937;
            font-size: 0.95rem;
        }
        
        tbody tr:hover {
            background: #f9fafb;
        }
        
        .booking-id {
            font-weight: 700;
            color: #667eea;
        }
        
        .confirmation-code {
            font-family: monospace;
            background: #f3f4f6;
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            display: inline-block;
            margin-top: 5px;
        }
        
        .child-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .child-avatar {
            width: 40px;
            height: 40px;
            background: #f3e8ff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6b46c1;
        }
        
        .parent-info {
            font-size: 0.8rem;
            color: #6b7280;
            margin-top: 3px;
        }
        
        .vaccination-date {
            color: #10b981;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        /* Delete Button */
        .btn-delete {
            background: #fee2e2;
            color: #dc2626;
            border: none;
            border-radius: 8px;
            padding: 8px 16px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-delete:hover {
            background: #dc2626;
            color: white;
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 16px;
            width: 90%;
            max-width: 450px;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from {
                transform: translateY(-30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .modal-header {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            padding: 20px 25px;
            border-radius: 16px 16px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h5 {
            font-size: 1.2rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .modal-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
        }
        
        .modal-body {
            padding: 30px 25px;
            text-align: center;
        }
        
        .modal-footer {
            padding: 20px 25px;
            border-top: 1px solid #e5e7eb;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }
        
        .btn {
            padding: 10px 24px;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .btn-secondary {
            background: #f3f4f6;
            color: #4b5563;
        }
        
        .btn-secondary:hover {
            background: #e5e7eb;
        }
        
        .btn-danger {
            background: #dc2626;
            color: white;
        }
        
        .btn-danger:hover {
            background: #b91c1c;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220,38,38,0.3);
        }
        
        .alert {
            background: #d1fae5;
            color: #065f46;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-state i {
            font-size: 5rem;
            color: #e5e7eb;
            margin-bottom: 20px;
        }
        
        .empty-state h4 {
            font-size: 1.5rem;
            color: #1f2937;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: #6b7280;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            body {
                padding: 15px;
            }
            
            .filter-form {
                flex-direction: column;
            }
            
            .search-box {
                min-width: 100%;
            }
            
            .table-responsive {
                margin: 0 -15px;
            }
            
            table {
                min-width: 900px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Simple Header - Sirf Bookings -->
        <div class="page-header">
            <div>
                <h1><i class="fas fa-calendar-check me-2"></i> Vaccination Bookings</h1>
                <p><i class="fas fa-calendar-alt me-1" style="color: #667eea;"></i> <?php echo date('l, F j, Y'); ?></p>
            </div>
            <div>
                <span class="booking-count">
                    <i class="fas fa-list-ul me-1"></i> Total: <?php echo mysqli_num_rows($result); ?>
                </span>
            </div>
        </div>
        
        <!-- Flash Messages -->
        <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="alert <?php echo $_SESSION['flash_type'] == 'success' ? '' : 'alert-danger'; ?>">
            <i class="fas <?php echo $_SESSION['flash_type'] == 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> me-2"></i>
            <?php 
                echo $_SESSION['flash_message'];
                unset($_SESSION['flash_message']);
                unset($_SESSION['flash_type']);
            ?>
            <button type="button" class="btn-close ms-auto" onclick="this.parentElement.remove()" style="background: none; border: none; color: inherit; font-size: 1.2rem;">&times;</button>
        </div>
        <?php endif; ?>
        
        <!-- Filter Section - Sirf Search aur Status Filter -->
        <div class="filter-section">
            <form method="GET" action="" class="filter-form">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" name="search" placeholder="Search by child, parent, vaccine, hospital..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <select class="filter-select" name="status">
                    <option value="all">All Status</option>
                    <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="approved" <?php echo $status_filter == 'approved' ? 'selected' : ''; ?>>Approved</option>
                    <option value="vaccinated" <?php echo $status_filter == 'vaccinated' ? 'selected' : ''; ?>>Vaccinated</option>
                    <option value="cancelled" <?php echo $status_filter == 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
                <button type="submit" class="btn-filter">
                    <i class="fas fa-filter me-2"></i> Apply
                </button>
                <a href="bookingdetail.php" class="btn-reset">
                    <i class="fas fa-redo me-2"></i> Reset
                </a>
            </form>
        </div>
        
        <!-- Bookings Table - Sirf Bookings List -->
        <div class="table-card">
            <div class="table-header">
                <h3>
                    <i class="fas fa-list-ul"></i>
                    Bookings List
                </h3>
                <span class="booking-count">
                    <?php echo mysqli_num_rows($result); ?> Record<?php echo mysqli_num_rows($result) != 1 ? 's' : ''; ?>
                </span>
            </div>
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Child & Parent</th>
                            <th>Vaccine</th>
                            <th>Hospital</th>
                            <th>Booking Date</th>
                            <th>Vaccination Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && mysqli_num_rows($result) > 0): ?>
                            <?php while($row = mysqli_fetch_assoc($result)): ?>
                            <tr>
                                <td>
                                    <span class="booking-id">#<?php echo $row['booking_id']; ?></span>
                                    <?php if ($row['confirmation_code']): ?>
                                    <div class="confirmation-code">
                                        <i class="fas fa-tag"></i> <?php echo $row['confirmation_code']; ?>
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="child-info">
                                        <div class="child-avatar">
                                            <i class="fas fa-child"></i>
                                        </div>
                                        <div>
                                            <div style="font-weight: 700;"><?php echo htmlspecialchars($row['child_name']); ?></div>
                                            <div class="parent-info">
                                                <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($row['parent_name']); ?>
                                                <?php if ($row['parent_phone']): ?>
                                                <br><i class="fas fa-phone me-1"></i> <?php echo htmlspecialchars($row['parent_phone']); ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 600;"><?php echo htmlspecialchars($row['vaccine_name']); ?></div>
                                </td>
                                <td>
                                    <div style="font-weight: 500;"><?php echo htmlspecialchars($row['hospital_name']); ?></div>
                                    <div class="parent-info">
                                        <i class="fas fa-map-marker-alt me-1"></i> <?php echo htmlspecialchars($row['hospital_city']); ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 600;"><?php echo date('d M Y', strtotime($row['booking_date'])); ?></div>
                                    <div class="parent-info">
                                        <i class="fas fa-clock me-1"></i> <?php echo date('h:i A', strtotime($row['appointment_time'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($row['display_status'] == 'Vaccinated'): ?>
                                        <span class="vaccination-date">
                                            <i class="fas fa-check-circle me-1"></i> 
                                            <?php echo date('d M Y', strtotime($row['vaccination_date'])); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="parent-info">
                                            <i class="fas fa-minus-circle me-1"></i> Not vaccinated
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $status_class = '';
                                    $status_text = $row['display_status'];
                                    $status_icon = '';
                                    
                                    switch(strtolower($status_text)) {
                                        case 'pending':
                                            $status_class = 'status-pending';
                                            $status_icon = 'fa-clock';
                                            break;
                                        case 'approved':
                                            $status_class = 'status-approved';
                                            $status_icon = 'fa-check-circle';
                                            break;
                                        case 'vaccinated':
                                            $status_class = 'status-vaccinated';
                                            $status_icon = 'fa-check-double';
                                            break;
                                        case 'cancelled':
                                            $status_class = 'status-cancelled';
                                            $status_icon = 'fa-times-circle';
                                            break;
                                    }
                                    ?>
                                    <span class="status-badge <?php echo $status_class; ?>">
                                        <i class="fas <?php echo $status_icon; ?>"></i>
                                        <?php echo $status_text; ?>
                                    </span>
                                </td>
                                <td>
                                    <!-- Sirf Delete Button -->
                                    <button type="button" class="btn-delete" onclick="showDeleteModal(<?php echo $row['booking_id']; ?>, '<?php echo htmlspecialchars(addslashes($row['child_name'])); ?>')">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="empty-state">
                                    <i class="fas fa-calendar-times"></i>
                                    <h4>No Bookings Found</h4>
                                    <p>No vaccination bookings found in the system.</p>
                                    <a href="bookingdetail.php" class="btn-filter" style="display: inline-block; margin-top: 15px; text-decoration: none;">
                                        <i class="fas fa-redo me-2"></i> Reset Filters
                                    </a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Delete Modal - Sirf Delete Confirmation -->
    <div class="modal" id="deleteModal">
        <div class="modal-content">
            <div class="modal-header">
                <h5>
                    <i class="fas fa-trash me-2"></i> Delete Booking
                </h5>
                <button type="button" class="modal-close" onclick="closeDeleteModal()">&times;</button>
            </div>
            <form action="delete_booking.php" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="booking_id" id="delete_booking_id">
                    
                    <div style="background: #fee2e2; width: 80px; height: 80px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px;">
                        <i class="fas fa-exclamation-triangle" style="font-size: 40px; color: #dc2626;"></i>
                    </div>
                    
                    <h5 style="font-weight: 700; margin-bottom: 15px;">Confirm Deletion</h5>
                    <p style="color: #6b7280; margin-bottom: 10px;">
                        Are you sure you want to delete the booking for <strong id="delete_child_name"></strong>?
                    </p>
                    <p style="color: #dc2626; font-size: 0.9rem;">
                        <i class="fas fa-exclamation-circle me-1"></i> This action cannot be undone.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i> Delete Booking
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Show delete modal
        function showDeleteModal(bookingId, childName) {
            document.getElementById('delete_booking_id').value = bookingId;
            document.getElementById('delete_child_name').innerHTML = childName;
            document.getElementById('deleteModal').classList.add('active');
        }
        
        // Close delete modal
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            var modal = document.getElementById('deleteModal');
            if (event.target == modal) {
                modal.classList.remove('active');
            }
        }
        
        // Auto-submit filter on status change
        document.querySelector('.filter-select').addEventListener('change', function() {
            this.form.submit();
        });
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            var alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.remove();
                }, 500);
            });
        }, 5000);
    </script>
</body>
</html>
<?php mysqli_close($connection); ?>