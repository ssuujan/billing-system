<?php
// Start output buffering at the VERY FIRST LINE
ob_start();

session_start();
require_once __DIR__ . '/../config/database.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect all POST data with null coalescing for safety
    $post_data = [
        'name' => $_POST['name'] ?? '',
        'email' => $_POST['email'] ?? '',
        'password' => $_POST['password'] ?? '',
        'confirm_password' => $_POST['confirm_password'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'address' => $_POST['address'] ?? '',
    ];

    // Validate required fields
    $required_fields = ['name', 'email', 'password', 'confirm_password', 'phone', 'address'];
    foreach ($required_fields as $field) {
        if (empty($post_data[$field])) {
            $_SESSION['alert'] = ucfirst($field) . " is required!";
            header("Location: index.php");
            exit();
        }
    }

    // Validate passwords match
    if ($post_data['password'] !== $post_data['confirm_password']) {
        $_SESSION['alert'] = "Passwords do not match!";
        header("Location: index.php");
        exit();
    }

    try {
        // Check for existing email AND phone
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

        // Insert with plaintext password (for school project only)
        // Automatically assign 'student' role
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
            // Set success message in session
            $_SESSION['alert_success'] = "Registration successful! You can now login.";
            
            // Clear output buffer
            ob_end_clean();
            
            // Build absolute URL for redirect
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'];
            $path = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
            $redirect_url = "$protocol://$host$path/login.php";
            
            // Force redirect
            header("HTTP/1.1 303 See Other");
            header("Location: $redirect_url");
            exit();
        } else {
            throw new Exception("Registration failed - no database error but no rows inserted");
        }

    } catch(PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $_SESSION['alert'] = "Registration error. Please try again.";
        header("Location: index.php");
        exit();
    } catch(Exception $e) {
        error_log("System error: " . $e->getMessage());
        $_SESSION['alert'] = "System error occurred. Please contact support.";
        header("Location: index.php");
        exit();
    }
} else {
    // Not a POST request - redirect to index
    header("Location: index.php");
    exit();
}