<?php
session_name('STUDENT_SESSION');    
session_start();

// Redirect to login if not logged in
if (!isset($_SESSION['user'])) {
    header("Location: ../public/login.php");
    exit();
}

require_once __DIR__ . '/../config/database.php';

try {
    // Fetch the full user data from the database
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :user_id");
    $stmt->execute([':user_id' => $_SESSION['user']['id']]);
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

    // ⚠️ If fetch failed, handle it gracefully
    if (!$currentUser) {
        $_SESSION['error'] = "User not found in database.";
        header("Location: ../public/login.php");
        exit();
    }

    // Default role if missing
    if (!isset($currentUser['role'])) {
        $currentUser['role'] = 'student';
    }

    // Update session with fresh user data
    $_SESSION['user'] = $currentUser;

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['error'] = "Database error occurred. Please try again later.";
    header("Location: dashboard.php");
    exit();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link rel="stylesheet" href="../public/assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container">
        <header class="header-text">
            <div class="logo">
                <img src="../public/assets/images/logo.png" alt="Patan Multiple Campus Logo">
            </div>
            <div class="header-text">
                <h1>Patan Multiple Campus</h1>
                <h1>Welcome, <?= htmlspecialchars(string: $currentUser['name']) ?>!</h1>
            </div>
        </header>

        <nav>
            <ul>
                <li><a href="dashboard.php">Profile</a></li>
                <li><a href="courses.php">Course</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </nav>

        <main>
        </main>

        <footer>
            <p>&copy; 2023 Patan Multiple Campus</p>
        </footer>
    </div>
</body>
</html>
