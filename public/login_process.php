<?php
// filepath: c:\xampp\htdocs\project\public\login_process.php
session_name('STUDENT_SESSION');
session_start();
require_once __DIR__ . '/../config/database.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form values
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $password = trim($_POST['password'] ?? '');

    // Check empty fields
    if (empty($email) || empty($password)) {
        $_SESSION['login_error'] = 'Please enter both email and password.';
        header('Location: login.php');
        exit();
    }

    // Check if email exists
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // Email not registered
        $_SESSION['login_error'] = 'Email is not registered.';
        header('Location: login.php');
        exit();
    }

    // Check password (plain password match for now)
    if ($password !== $user['password']) {
        $_SESSION['login_error'] = 'Incorrect password.';
        header('Location: login.php');
        exit();
    }

    // If login successful
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => $user['id'],
        'email' => $user['email'],
        'name' => $user['name'],
        'role' => $user['role'] ?? 'student'
    ];

    $_SESSION['login_success'] = 'Welcome, ' . $user['name'] . '! You are logged in successfully.';
    header('Location: /project/student/dashboard.php');
    exit();
}

// If accessed directly
header('Location: login.php');
exit();
