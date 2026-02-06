<?php
session_start();

if(isset($_SESSION["user_id"])) {
    include("dbconnection.php");
    log_audit($_SESSION["user_id"], 'LOGOUT', 'users', $_SESSION["user_id"], 'User logged out');
}

session_destroy();
header("location:login.php");
?>
