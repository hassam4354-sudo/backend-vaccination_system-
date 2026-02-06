<?php
include("../dbconnection.php");

if(isset($_POST['save_profile'])) {
    $user_id = sanitize_input($_POST['user_id']);
    $full_name = sanitize_input($_POST['full_name']);
    $emergency_contact = sanitize_input($_POST['emergency_contact']);
    $address = sanitize_input($_POST['address']);
    $city = sanitize_input($_POST['city']);
    $state = sanitize_input($_POST['state']);
    $postal_code = sanitize_input($_POST['postal_code']);
    
    // Insert into parents table
    $query = "INSERT INTO parents (user_id, full_name, emergency_contact, address, city, state, postal_code)
              VALUES ('$user_id', '$full_name', '$emergency_contact', '$address', '$city', '$state', '$postal_code')";
    
    $run = mysqli_query($connection, $query);
    
    if($run) {
        echo "<script>alert('Profile completed successfully! Please login.')
        window.location.href = '../login.php'
        </script>";
    } else {
        echo "<script>alert('Error: " . mysqli_error($connection) . "')
        window.location.href = 'complete_profile.php?user_id=$user_id'
        </script>";
    }
}
?>
