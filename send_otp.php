<?php
session_start();
include("config.php");

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// Get data from form
$user_type = $_POST["user_type"];
$email = $_POST["email"];
$password = $_POST["password"];
$confirm_password = $_POST["confirm_password"];

// Validate password match
if($password !== $confirm_password) {
    echo "<script>alert('Passwords do not match!')
    window.location.href = 'signup.php'
    </script>";
    exit();
}

// Create sessions
$_SESSION["user_type"] = $user_type;
$_SESSION["email"] = $email;
$_SESSION["password"] = password_hash($password, PASSWORD_DEFAULT);

// Generate OTP
$otp = rand(100000, 999999);
$_SESSION["otp"] = $otp;

// Send OTP via Email
$mail = new PHPMailer(true);

try {
    // Server settings
    $mail->isSMTP();
    $mail->SMTPAuth   = true;
    $mail->Host       = 'smtp.gmail.com';
    $mail->Username   = $my_email;
    $mail->Password   = $app_password;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = 587;

    // Recipients
    $mail->setFrom($my_email, 'Vaccination System');
    $mail->addAddress($email);

    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Your OTP for Registration';
    $mail->Body    = "
    <h2>Child Vaccination System</h2>
    <p>Your OTP for registration is: <strong style='font-size:24px;color:#667eea;'>$otp</strong></p>
    <p>This OTP is valid for 10 minutes.</p>
    <p>If you didn't request this, please ignore this email.</p>
    ";

    $mail->send();
    
    echo "<script>alert('OTP sent to your email!')
    window.location.href = 'verify_otp.php'
    </script>";

} catch (Exception $e) {
    echo "<script>alert('Failed to send OTP. Error: {$mail->ErrorInfo}')
    window.location.href = 'signup.php'
    </script>";
}
?>
