<?php
include("dbconnection.php");
session_start();

$user_otp = sanitize_input($_POST["otp"]);

if($_SESSION["otp"] == $user_otp){
    // OTP is correct
    $user_type = $_SESSION["user_type"];
    $email = $_SESSION["email"];
    $password = $_SESSION["password"];

    // $password_hash = password_hash($_SESSION["password"], PASSWORD_DEFAULT);
    
    // Insert into users table
    $query_user = "INSERT INTO users(email, password_hash, user_type, phone)
                   VALUES ('$email', '$password', '$user_type', NULL)";
    
    $run = mysqli_query($connection, $query_user);
    
    if($run){
        $user_id = mysqli_insert_id($connection);
        
        // Create role-specific record
        if($user_type == 'parent') {
            header("location:parent/complete_profile.php?user_id=$user_id");
        } elseif($user_type == 'hospital') {
            header("location:hospital/complete_profile.php?user_id=$user_id");
        }
        
        // Remove OTP session
        unset($_SESSION["otp"]);
        
    } else {
        echo "<script>alert('Registration Failed! Try again.')
        window.location.href = 'signup.php'
        </script>";
    }
    
} else {
    echo "<script>alert('Invalid OTP! Please try again.')
    window.location.href = 'verify_otp.php'
    </script>";
}
?>
