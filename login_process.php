<?php
session_start();
include("dbconnection.php");

$email = sanitize_input($_POST["email"]);
$password = $_POST["password"];

$query_select = "SELECT * FROM users WHERE email = '$email'";
$row = mysqli_query($connection, $query_select);

if(mysqli_num_rows($row) > 0){
    // User exists
    $data = mysqli_fetch_assoc($row);
    
    if(password_verify($password, $data["password_hash"])){
        // Password verified
        
        // Check if account is active
        if($data["is_active"] == 0) {
            echo "<script>alert('Your account is deactivated. Contact admin.')
            window.location.href = 'login.php'
            </script>";
            exit();
        }
        
        // Create sessions
        $_SESSION["user_id"] = $data["user_id"];
        $_SESSION["email"] = $data["email"];
        $_SESSION["user_type"] = $data["user_type"];
        $_SESSION["logged_in"] = true;
        $_SESSION["last_activity"] = time();
        
        // Log the login
        log_audit($data["user_id"], 'LOGIN', 'users', $data["user_id"], 'User logged in');
        
        // Redirect based on user type
        if($data["user_type"] == "admin"){
            header("location:admin/dashboard.php");
        } elseif($data["user_type"] == "parent"){
            header("location:parent/dashboard.php");
        } elseif($data["user_type"] == "hospital"){
            header("location:hospital/dashboard.php");
        }
        
    } else {
        // Invalid password
        echo "<script>alert('Invalid Password!')
        window.location.href = 'login.php'
        </script>";
    }
    
} else {
    // User not found
    echo "<script>alert('User Not Found! Please SignUp first.')
    window.location.href = 'signup.php'
    </script>";
}
?>
