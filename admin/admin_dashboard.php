<?php
session_start();

// Restrict access to only admins
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../public/admin.php");
    exit();
}

$admin = $_SESSION['user'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Patan Multiple Campus</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../public/assets/css/admin.css">

</head>
<body>
    <div class="container">
        <header>
            <div class="logo">
                <img src="../public/assets/images/logo.png" alt="Patan Multiple Campus Logo">
            </div>
            <div>
                <h1>Patan Multiple Campus - Admin Panel</h1>
                <h2>Welcome, <?= htmlspecialchars($admin['name']) ?>!</h2>
            </div>
        </header>

        <nav>
            <ul>
                <li><a href="admin_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                <li><a href="manage_students.php"><i class="fas fa-users"></i> Manage Students</a></li>
                <li><a href="manage_courses.php"><i class="fas fa-book"></i> Manage Courses</a></li>
                <li><a href="fee_structure.php"><i class="fas fa-money-bill"></i> Fee Structure</a></li>
                <li><a href="student_billing.php"><i class="fas fa-file-invoice"></i> Student Bills</a></li>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </nav>

        <main>
            <h2>Admin Controls</h2>
            <div class="dashboard-links">
                <a href="manage_students.php"><i class="fas fa-users fa-2x"></i><br>Manage Students</a>
                <a href="manage_courses.php"><i class="fas fa-book fa-2x"></i><br>Manage Courses</a>
                <a href="fee_structure.php"><i class="fas fa-file-invoice-dollar fa-2x"></i><br>Fee Structure</a>
                <a href="student_billing.php"><i class="fas fa-receipt fa-2x"></i><br>Student Bills</a>
            </div>
        </main>

        <footer>
            <p>&copy; <?= date("Y") ?> Patan Multiple Campus. All rights reserved.</p>
        </footer>
    </div>
</body>
</html>
