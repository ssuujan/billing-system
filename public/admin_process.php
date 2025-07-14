<?php
session_start();
require_once __DIR__ . '/../config/database.php';

$email = $_POST['email'] ?? '';
$passwordInput = $_POST['password'] ?? '';

if (empty($email) || empty($passwordInput)) {
    echo "<script>alert('Email and password are required'); window.history.back();</script>";
    exit;
}

try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = :email AND role = 'admin'");
    $stmt->bindParam(':email', $email);
    $stmt->execute();

    if ($stmt->rowCount() === 1) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // In production, use password_verify() with hashed passwords
        if ($passwordInput === $user['password']) {
            // Set complete user data in session (matches dashboard expectation)
            $_SESSION['user'] = [
                'id' => $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role']
            ];
            
            // Redirect to dashboard with absolute path
            header("Location: http://" . $_SERVER['HTTP_HOST'] . "/project/admin/admin_dashboard.php");
            exit;
        } else {
            echo "<script>alert('Incorrect password'); window.history.back();</script>";
        }
    } else {
        echo "<script>alert('Access denied. Admin only.'); window.history.back();</script>";
    }
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
?>