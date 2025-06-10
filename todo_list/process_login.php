<?php
session_start();
require __DIR__ . '/db_connection.php'; // Pastikan path ini benar

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Query untuk mendapatkan hash password dari database
    $stmt = $conn->prepare("SELECT password FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        $hashed_password = $row['password'];

        // Verifikasi password
        if (password_verify($password, $hashed_password)) {
            $_SESSION['user'] = $username;
            header("Location: dashboard.php");
            exit();
        } else {
            header("Location: login.php?error=Invalid password");
            exit();
        }
    } else {
        header("Location: login.php?error=User not found");
        exit();
    }
} else {
    header("Location: login.php");
    exit();
}
?>
