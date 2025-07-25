<?php
session_name('ADMIN_SESSION');
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../public/admin.php");
    exit();
}

require_once __DIR__ . '/../config/database.php';

// Fetch all students from the database
try {
    $stmt = $conn->prepare("SELECT id, name, email, phone, address, approved, course_id FROM users WHERE role = 'student'");
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get course names for each student
    foreach ($students as &$student) {
        if ($student['course_id']) {
            $courseStmt = $conn->prepare("SELECT course_name FROM courses WHERE id = ?");
            $courseStmt->execute([$student['course_id']]);
            $course = $courseStmt->fetch(PDO::FETCH_ASSOC);
            $student['course_name'] = $course ? $course['course_name'] : 'Not assigned';
        } else {
            $student['course_name'] = 'Not assigned';
        }
    }
    unset($student); // Break the reference
    
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Get messages from session
$success = $_SESSION['success'] ?? null;
$error = $_SESSION['error'] ?? $error ?? null;

// Clear messages
unset($_SESSION['success']);
unset($_SESSION['error']);

$admin = $_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Students - Patan Multiple Campus</title>
    <link href="./css/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/all.min.css">
    <!-- <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"> -->
    <!-- <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet"> -->
    <style>
        .student-table tr:hover {
            background-color: #f8fafc;
        }
        .action-btn {
            transition: all 0.2s ease;
        }
        .action-btn:hover {
            transform: translateY(-1px);
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
                        <li><a href="admin_dashboard.php" class="hover:text-blue-300"><i class="fas fa-tachometer-alt mr-2"></i>Dashboard</a></li>
                        <li><a href="manage_students.php" class="font-bold text-blue-300"><i class="fas fa-users mr-2"></i>Students</a></li>
                        <li><a href="manage_courses.php" class="hover:text-blue-300"><i class="fas fa-book mr-2"></i>Courses</a></li>
                        <li><a href="logout.php" class="hover:text-blue-300"><i class="fas fa-sign-out-alt mr-2"></i>Logout</a></li>
                    </ul>
                </nav>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-grow container mx-auto px-4 py-8">
            <div class="max-w-6xl mx-auto">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">Manage Students</h2>
                
                <?php if ($success): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                        <i class="fas fa-check-circle mr-2"></i> <?= htmlspecialchars($success) ?>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                        <i class="fas fa-exclamation-circle mr-2"></i> <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full student-table">
                            <thead class="bg-gray-100">
                                <tr>
                                    <th class="py-3 px-6 text-left">Name</th>
                                    <th class="py-3 px-6 text-left">Email</th>
                                    <th class="py-3 px-6 text-left">Phone</th>
                                    <th class="py-3 px-6 text-left">Course</th>
                                    <th class="py-3 px-6 text-left">Status</th>
                                    <th class="py-3 px-6 text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php if (empty($students)): ?>
                                    <tr>
                                        <td colspan="6" class="py-4 px-6 text-center text-gray-500">No students found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($students as $student): 
                                        $is_approved = (int) $student['approved'];
                                        ?>
                                        <tr>
                                            <td class="py-3 px-6"><?= htmlspecialchars($student['name']) ?></td>
                                            <td class="py-3 px-6"><?= htmlspecialchars($student['email']) ?></td>
                                            <td class="py-3 px-6"><?= htmlspecialchars($student['phone']) ?></td>
                                            <td class="py-3 px-6"><?= htmlspecialchars($student['course_name']) ?></td>
                                            <td class="py-3 px-6">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                                    <?= $is_approved ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                                                    <?= $is_approved ? 'Approved' : 'Pending' ?>
                                                </span>
                                            </td>
                                            <td class="py-3 px-6 text-center space-x-1">
                                                <!-- Approve Button -->
                                                <form class="inline" method="post" action="adstudent_process.php">
                                                    <input type="hidden" name="student_email" value="<?= $student['email'] ?>">
                                                    <input type="hidden" name="approved" value="1">
                                                    <button type="submit" class="action-btn bg-green-600 hover:bg-green-700 text-white px-3 py-1 rounded text-sm" 
                                                        <?= $is_approved ? 'disabled' : '' ?>>
                                                        <i class="fas fa-check mr-1"></i> Approve
                                                    </button>
                                                </form>

                                                <!-- Reject Button -->
                                                <form class="inline" method="post" action="adstudent_process.php">
                                                    <input type="hidden" name="student_email" value="<?= $student['email'] ?>">
                                                    <input type="hidden" name="approved" value="0">
                                                    <button type="submit" class="action-btn bg-red-600 hover:bg-red-700 text-white px-3 py-1 rounded text-sm" 
                                                        <?= !$is_approved ? 'disabled' : '' ?>>
                                                        <i class="fas fa-times mr-1"></i> Reject
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
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