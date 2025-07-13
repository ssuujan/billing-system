<?php
// filepath: c:\xampp\htdocs\project\public\login_process.php

session_start();
require_once __DIR__ . '/../config/database.php';

// Simple debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'] ?? '', filter: FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch();

    if ($user && $password === $user['password']) {
        session_regenerate_id(true);
        $_SESSION['user'] = [
            'id' => $user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'role' => $user['role'] ?? 'student'
        ];
        header('Location: /project/student/dashboard.php');
        exit();
    }
    
    $_SESSION['login_error'] = 'Invalid email or password.';
    header('Location: login.php');
    exit();
}

header('Location: login.php');
exit();