<?php
include("../dbconnection.php");

if(isset($_POST['save_hospital'])) {
    $user_id = sanitize_input($_POST['user_id']);
    $hospital_name = sanitize_input($_POST['hospital_name']);
    $registration_number = sanitize_input($_POST['registration_number']);
    $contact_person = sanitize_input($_POST['contact_person']);
    $address = sanitize_input($_POST['address']);
    $city = sanitize_input($_POST['city']);
    $state = sanitize_input($_POST['state']);
    $postal_code = sanitize_input($_POST['postal_code']);
    $latitude = $_POST['latitude'] ? sanitize_input($_POST['latitude']) : NULL;
    $longitude = $_POST['longitude'] ? sanitize_input($_POST['longitude']) : NULL;
    
    // Also update users table with phone
    $phone = sanitize_input($_POST['phone_number']);
    $update_user = "UPDATE users SET phone = '$phone' WHERE user_id = '$user_id'";
    mysqli_query($connection, $update_user);
    
    // Insert into hospitals table
    $query = "INSERT INTO hospitals 
              (user_id, hospital_name, registration_number, address, city, state, postal_code, 
               latitude, longitude, contact_person)
              VALUES ('$user_id', '$hospital_name', '$registration_number', '$address', '$city', '$state', '$postal_code', ";
    
    $query .= $latitude ? "'$latitude', " : "NULL, ";
    $query .= $longitude ? "'$longitude', " : "NULL, ";
    $query .= "'$contact_person')";
    
    $run = mysqli_query($connection, $query);
    
    if($run) {
        echo "<script>alert('Hospital profile created! Your account is pending admin verification.')
        window.location.href = '../login.php'
        </script>";
    } else {
        echo "<script>alert('Error: " . mysqli_error($connection) . "')
        window.location.href = 'complete_profile.php?user_id=$user_id'
        </script>";
    }
}
?>
