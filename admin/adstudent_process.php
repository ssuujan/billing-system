<?php
session_start();

// Restrict access to only admins
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../public/admin.php");
    exit();
}

require_once __DIR__ . '/../config/database.php';

// Initialize variables
$error = null;

// Only process if it's a POST request with required parameters
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_email']) && isset($_POST['approved'])) {
    $student_email = trim($_POST['student_email']);
    $approved = (int)$_POST['approved']; // Convert to integer (0 or 1)

    // Validate inputs
    if (empty($student_email)) {
        $_SESSION['error'] = "Student email cannot be empty";
    } else {
        try {
            // Update the approval status
            $updateStmt = $conn->prepare("UPDATE users SET approved = :approved WHERE email = :email AND role = 'student'");
            $updateStmt->bindParam(':approved', $approved, PDO::PARAM_INT);
            $updateStmt->bindParam(':email', $student_email);
            
            if ($updateStmt->execute()) {
                $_SESSION['success'] = "Student status updated to: " . ($approved ? 'Approved' : 'Pending');
            } else {
                $_SESSION['error'] = "No changes were made to the student record.";
            }
        } catch (PDOException $e) {
            error_log("Database error: " . $e->getMessage());
            $_SESSION['error'] = "Database error. Please try again later.";
        }
    }
} else {
    $_SESSION['error'] = "Invalid request parameters.";
}

// Always redirect back to manage_students.php
header("Location: manage_students.php");
exit();
?>