<?php
session_name('ADMIN_SESSION');
session_start();
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $passwordInput = trim($_POST['password'] ?? '');

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

            // Check password (hashed or plain)
            if (password_verify($passwordInput, $user['password']) || $passwordInput === $user['password']) {
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'name' => $user['name'],
                    'email' => $user['email'],
                    'role' => $user['role']
                ];

                // ✅ Success alert and redirect
                echo "<script>
                    alert('Welcome, {$user['name']}! You are logged in successfully.');
                    window.location.href = '/project/admin/admin_dashboard.php';
                </script>";
                exit;
            } else {
                echo "<script>alert('Incorrect password'); window.history.back();</script>";
                exit;
            }
        } else {
            echo "<script>alert('Access denied. Admin only.'); window.history.back();</script>";
            exit;
        }
    } catch (PDOException $e) {
        echo "<script>alert('Database error: " . $e->getMessage() . "'); window.history.back();</script>";
        exit;
    }
} else {
    header("Location: admin.php");
    exit;
}
?>
