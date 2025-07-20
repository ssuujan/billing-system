<?php
ob_start();
session_start();
require_once __DIR__ . '/../config/database.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_data = [
        'name' => $_POST['name'] ?? '',
        'email' => $_POST['email'] ?? '',
        'password' => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'address' => $_POST['address'] ?? '',
    ];

    $required_fields = ['name', 'email', 'password', 'confirm_password', 'phone', 'address'];
    foreach ($required_fields as $field) {
        if (empty($post_data[$field])) {
            $_SESSION['alert'] = ucfirst($field) . " is required!";
            header("Location: index.php");
            exit();
        }
    }

    if ($post_data['password'] !== $post_data['confirm_password']) {
        $_SESSION['alert'] = "Passwords do not match!";
        header("Location: index.php");
        exit();
    }

    try {
        $check_email = $conn->prepare("SELECT email FROM users WHERE email = :email");
        $check_email->execute([':email' => $post_data['email']]);

        $check_phone = $conn->prepare("SELECT phone FROM users WHERE phone = :phone");
        $check_phone->execute([':phone' => $post_data['phone']]);

        if ($check_email->rowCount() > 0) {
            $_SESSION['alert'] = "Email already registered!";
            header("Location: index.php");
            exit();
        }

        if ($check_phone->rowCount() > 0) {
            $_SESSION['alert'] = "Phone number already registered!";
            header("Location: index.php");
            exit();
        }

        // No hashing - store password as-is (⚠️ insecure for production)
        $stmt = $conn->prepare("INSERT INTO users (name, email, password, phone, address, role) 
                               VALUES (:name, :email, :password, :phone, :address, 'student')");
        $success = $stmt->execute([
            ':name' => $post_data['name'],
            ':email' => $post_data['email'],
            ':password' => $post_data['password'],
            ':phone' => $post_data['phone'],
            ':address' => $post_data['address'],
        ]);

        if ($success) {
            $lastInsertId = $conn->lastInsertId();
            $student_id = 'STU' . str_pad($lastInsertId, 6, '0', STR_PAD_LEFT) . date('Y');

            $update = $conn->prepare("UPDATE users SET student_id = :student_id WHERE id = :id");
            $update->execute([
                ':student_id' => $student_id,
                ':id' => $lastInsertId
            ]);

            $_SESSION['alert_success'] = "Registration successful! You can now login.";

            ob_end_clean();

            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'];
            $path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
            $redirect_url = "$protocol://$host$path/login.php";

            header("HTTP/1.1 303 See Other");
            header("Location: $redirect_url");
            exit();
        } else {
            throw new Exception("Registration failed");
        }

    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $_SESSION['alert'] = "Registration error. Please try again.";
        header("Location: index.php");
        exit();
    } catch (Exception $e) {
        error_log("System error: " . $e->getMessage());
        $_SESSION['alert'] = "System error occurred. Please contact support.";
        header("Location: index.php");
        exit();
    }
} else {
    header("Location: index.php");
    exit();
}
?>