<?php
/* auth.php - User authentication */
include 'config.php';

if (isset($_POST['login'])) {
    $phone = $conn->real_escape_string($_POST['phone']);
    $password = $conn->real_escape_string($_POST['password']);
    
    $result = $conn->query("SELECT * FROM members WHERE phone='$phone'");
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            header("Location: dashboard.php");
        }
    }
}

// Registration and password reset similar
?>