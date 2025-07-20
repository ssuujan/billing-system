<?php
session_name('ADMIN_SESSION');
session_start();

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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        .dashboard-card {
            transition: all 0.3s ease;
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex flex-col">
        <!-- Header -->
        <header class="bg-blue-800 text-white shadow-lg">
            <div class="container mx-auto px-4 py-4 flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <img src="../public/assets/images/logo.png" alt="Logo" class="h-12">
                    <div>
                        <h1 class="text-xl font-bold">Patan Multiple Campus - Admin Panel</h1>
                        <p class="text-blue-200">Welcome, <?= htmlspecialchars($admin['name']) ?></p>
                    </div>
                </div>
                <nav>
                    <ul class="flex space-x-6">
                        <li><a href="admin_dashboard.php" class="font-bold text-blue-300"><i class="fas fa-tachometer-alt mr-2"></i>Dashboard</a></li>
                        <li><a href="manage_students.php" class="hover:text-blue-300"><i class="fas fa-users mr-2"></i>Students</a></li>
                        <li><a href="manage_courses.php" class="hover:text-blue-300"><i class="fas fa-book mr-2"></i>Courses</a></li>
                        <li><a href="logout.php" class="hover:text-blue-300"><i class="fas fa-sign-out-alt mr-2"></i>Logout</a></li>
                    </ul>
                </nav>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-grow container mx-auto px-4 py-8">
            <div class="max-w-6xl mx-auto">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">Admin Controls</h2>
                
                <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <a href="manage_students.php" class="dashboard-card bg-white rounded-lg shadow-md p-6 text-center hover:shadow-lg">
                        <div class="text-blue-600 mb-3">
                            <i class="fas fa-users fa-3x"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-800">Manage Students</h3>
                        <p class="text-gray-600 mt-2">View and manage student accounts</p>
                    </a>
                    
                    <a href="manage_courses.php" class="dashboard-card bg-white rounded-lg shadow-md p-6 text-center hover:shadow-lg">
                        <div class="text-blue-600 mb-3">
                            <i class="fas fa-book fa-3x"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-800">Manage Courses</h3>
                        <p class="text-gray-600 mt-2">Add and organize courses</p>
                    </a>
                    
                    <a href="fee_structure.php" class="dashboard-card bg-white rounded-lg shadow-md p-6 text-center hover:shadow-lg">
                        <div class="text-blue-600 mb-3">
                            <i class="fas fa-money-bill-wave fa-3x"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-800">Fee Structure</h3>
                        <p class="text-gray-600 mt-2">Set up course fees</p>
                    </a>
                    
                    <a href="student_bills.php" class="dashboard-card bg-white rounded-lg shadow-md p-6 text-center hover:shadow-lg">
                        <div class="text-blue-600 mb-3">
                            <i class="fas fa-file-invoice-dollar fa-3x"></i>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-800">Student Bills</h3>
                        <p class="text-gray-600 mt-2">Manage student payments</p>
                    </a>
                </div>
            </div>
        </main>

        <footer class="bg-gray-800 text-white py-6">
            <div class="container mx-auto px-4 text-center">
                <p>&copy; <?= date('Y') ?> Patan Multiple Campus. All rights reserved.</p>
            </div>
        </footer>
    </div>
</body>
</html>