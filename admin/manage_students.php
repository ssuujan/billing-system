<?php
session_start();

// Restrict access to only admins
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../public/admin.php");
    exit();
}

require_once __DIR__ . '/../config/database.php';

// Fetch all students from the database
try {
    $stmt = $conn->prepare("SELECT name, email, phone, address, approved FROM users WHERE role = 'student'");
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    <title>Admin Dashboard - Patan Multiple Campus</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../public/assets/css/admin.css">
    <link rel="stylesheet" href="../admin/css/manage_std.css">

</head>

<body>
    <div class="container">
        <header class="header">
            <div class="logo">
                <img src="../public/assets/images/logo.png" alt="Patan Multiple Campus Logo">
            </div>
            <div class="header-text">
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
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <h2>Manage Students</h2>

            <table class="student-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Address</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">No students found</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($students as $student):
                            $is_approved = (int) $student['approved'];
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($student['name']) ?></td>
                                <td><?= htmlspecialchars($student['email']) ?></td>
                                <td><?= htmlspecialchars($student['phone']) ?></td>
                                <td><?= htmlspecialchars($student['address']) ?></td>
                                <td class="status-<?= $is_approved ? 'approved' : 'pending' ?>">
                                    <?= $is_approved ? 'Approved' : 'Pending' ?>
                                </td>
                                <td>
                                    <!-- Approve Button -->
                                    <form class="action-form" method="post" action="adstudent_process.php">
                                        <input type="hidden" name="student_email" value="<?= $student['email'] ?>">
                                        <input type="hidden" name="approved" value="1">
                                        <button type="submit" class="action-btn approve-btn" <?= $is_approved ? 'disabled' : '' ?>>
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                    </form>

                                    <!-- Reject Button -->
                                    <form class="action-form" method="post" action="adstudent_process.php">
                                        <input type="hidden" name="student_email" value="<?= $student['email'] ?>">
                                        <input type="hidden" name="approved" value="0">
                                        <button type="submit" class="action-btn reject-btn" <?= !$is_approved ? 'disabled' : '' ?>>
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </main>

        <?php
        // Process form submission at the bottom of the file
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_email']) && isset($_POST['approved'])) {
            $student_email = trim($_POST['student_email']);
            $approved = (int) $_POST['approved'];

            require_once __DIR__ . '/../config/database.php';

            try {
                $updateStmt = $conn->prepare("UPDATE users SET approved = :approved WHERE email = :email AND role = 'student'");
                $updateStmt->bindParam(':approved', $approved, PDO::PARAM_INT);
                $updateStmt->bindParam(':email', $student_email);

                if ($updateStmt->execute()) {
                    $_SESSION['success'] = "Student status updated to: " . ($approved ? 'Approved' : 'Pending');
                } else {
                    $_SESSION['error'] = "No changes were made to the student record.";
                }
            } catch (PDOException $e) {
                $_SESSION['error'] = "Database error: " . $e->getMessage();
            }

            // Redirect to same page to prevent form resubmission
            header("Location: manage_students.php");
            exit();
        }
        ?>

        <footer>
            <p>&copy; <?= date("Y") ?> Patan Multiple Campus. All rights reserved.</p>
        </footer>
    </div>
</body>

</html>