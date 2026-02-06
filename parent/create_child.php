<?php
session_start();
if(!isset($_SESSION["logged_in"]) || $_SESSION["user_type"] != "parent"){
    header("location:../login.php");
    exit();
}

include("../dbconnection.php");

if(isset($_POST['add_child'])) {
    $user_id = $_SESSION["user_id"];
    
    // Get parent_id
    $query_parent = "SELECT parent_id FROM parents WHERE user_id = '$user_id'";
    $result = mysqli_query($connection, $query_parent);
    $parent_data = mysqli_fetch_assoc($result);
    $parent_id = $parent_data['parent_id'];
    
    // Get form data
    $full_name = sanitize_input($_POST['full_name']);
    $date_of_birth = sanitize_input($_POST['date_of_birth']);
    $gender = sanitize_input($_POST['gender']);
    $blood_group = sanitize_input($_POST['blood_group']);
    $birth_weight = $_POST['birth_weight'] ? sanitize_input($_POST['birth_weight']) : NULL;
    $birth_height = $_POST['birth_height'] ? sanitize_input($_POST['birth_height']) : NULL;
    $medical_conditions = sanitize_input($_POST['medical_conditions']);
    $allergies = sanitize_input($_POST['allergies']);
    
    // Handle photo upload
    $photo_url = NULL;
    if(isset($_FILES['photo']) && $_FILES['photo']['error'] == 0) {
        $upload_dir = "../uploads/children/";
        
        // Create directory if not exists
        if(!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $new_filename = "child_" . time() . "_" . rand(1000, 9999) . "." . $file_extension;
        $photo_path = $upload_dir . $new_filename;
        
        if(move_uploaded_file($_FILES['photo']['tmp_name'], $photo_path)) {
            $photo_url = "uploads/children/" . $new_filename;
        }
    }
    
    // Insert into children table
    $query = "INSERT INTO children (parent_id, full_name, date_of_birth, gender, blood_group, 
              birth_weight, birth_height, medical_conditions, allergies, photo_url)
              VALUES ('$parent_id', '$full_name', '$date_of_birth', '$gender', ";
    
    $query .= $blood_group ? "'$blood_group', " : "NULL, ";
    $query .= $birth_weight ? "'$birth_weight', " : "NULL, ";
    $query .= $birth_height ? "'$birth_height', " : "NULL, ";
    $query .= $medical_conditions ? "'$medical_conditions', " : "NULL, ";
    $query .= $allergies ? "'$allergies', " : "NULL, ";
    $query .= $photo_url ? "'$photo_url'" : "NULL";
    $query .= ")";
    
    $run = mysqli_query($connection, $query);
    
    if($run) {
        $child_id = mysqli_insert_id($connection);
        
        // Log the action
        log_audit($user_id, 'ADD_CHILD', 'children', $child_id, "Added child: $full_name");
        
        echo "<script>alert('Child added successfully!')
        window.location.href = 'my_children.php'
        </script>";
    } else {
        echo "<script>alert('Error: " . mysqli_error($connection) . "')
        window.location.href = 'add_child.php'
        </script>";
    }
}
?>
