<?php
include("../config.php");

$query = "SELECT * FROM bookings ORDER BY id DESC";
$result = mysqli_query($connection, $query);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin - Bookings</title>
    <style>
        body {
            margin: 0;
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(to right, #5f67d8, #7a5af8);
        }

        .container {
            padding: 30px;
        }

        .card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }

        h2 {
            color: #333;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        th {
            background: #f4f4f4;
            padding: 12px;
            text-align: left;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #eee;
        }

        tr:hover {
            background: #f9f9f9;
        }

        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .pending { background: #fff3cd; color: #856404; }
        .approved { background: #cce5ff; color: #004085; }
        .vaccinated { background: #d4edda; color: #155724; }
        .cancelled { background: #f8d7da; color: #721c24; }

        .btn {
            padding: 6px 10px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
        }

        .btn-vaccinated {
            background: #28a745;
            color: white;
        }

        .btn-delete {
            background: #dc3545;
            color: white;
        }

        .header {
            color: white;
            margin-bottom: 20px;
        }

    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>Bookings Management</h1>
        <p>Manage all vaccination bookings</p>
    </div>

    <div class="card">
        <h2>All Bookings</h2>

        <table>
            <tr>
                <th>ID</th>
                <th>Child</th>
                <th>Parent</th>
                <th>Vaccine</th>
                <th>Hospital</th>
                <th>Date</th>
                <th>Status</th>
                <th>Action</th>
            </tr>

            <?php while($row = mysqli_fetch_assoc($result)) { ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo $row['child_name']; ?></td>
                    <td><?php echo $row['parent_name']; ?></td>
                    <td><?php echo $row['vaccine_name']; ?></td>
                    <td><?php echo $row['hospital_name']; ?></td>
                    <td><?php echo $row['booking_date']; ?></td>
                    <td>
                        <?php
                        $status = strtolower($row['status']);
                        echo "<span class='badge $status'>{$row['status']}</span>";
                        ?>
                    </td>
                    <td>
                        <?php if($row['status'] != 'Vaccinated') { ?>
                            <a href="update_status.php?id=<?php echo $row['id']; ?>" 
                               class="btn btn-vaccinated">
                               Mark Vaccinated
                            </a>
                        <?php } ?>
                        
                        <a href="delete_booking.php?id=<?php echo $row['id']; ?>" 
                           class="btn btn-delete">
                           Delete
                        </a>
                    </td>
                </tr>
            <?php } ?>

        </table>
    </div>
</div>

</body>
</html>
