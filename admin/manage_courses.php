<?php
session_name('ADMIN_SESSION');
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../public/admin.php");
    exit();
}

require_once __DIR__ . '/../config/database.php';
$admin = $_SESSION['user'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_course'])) {
        $course_name = trim($_POST['course_name']);
        $duration_type = $_POST['duration_type'];
        $description = trim($_POST['description']);

        if (!empty($course_name)) {
            try {
                $stmt = $conn->prepare("INSERT INTO courses (course_name, duration_type, description) VALUES (?, ?, ?)");
                $stmt->execute([$course_name, $duration_type, $description]);
                $_SESSION['success'] = "Course added successfully!";
            } catch (PDOException $e) {
                $_SESSION['error'] = "Error adding course: " . $e->getMessage();
            }
        } else {
            $_SESSION['error'] = "Course name cannot be empty!";
        }
    } elseif (isset($_POST['add_subjects'])) {
        $course_id = $_POST['course_id'];
        $year_or_semester = $_POST['year_or_semester'];
        $subjects = $_POST['subjects'];
        $sub_subjects = $_POST['sub_subjects'];

        try {
            $conn->beginTransaction();
            $stmt = $conn->prepare("INSERT INTO course_subjects (course_id, year_or_semester, subject_name, sub_subject) VALUES (?, ?, ?, ?)");

            for ($i = 0; $i < count($subjects); $i++) {
                $subject = trim($subjects[$i]);
                $sub_subject = trim($sub_subjects[$i]);

                if (!empty($subject)) {
                    $stmt->execute([$course_id, $year_or_semester, $subject, $sub_subject ?: null]);
                }
            }

            $conn->commit();
            $_SESSION['success'] = "Subjects added successfully!";
        } catch (PDOException $e) {
            $conn->rollBack();
            $_SESSION['error'] = "Error adding subjects: " . $e->getMessage();
        }
    }
}

// Handle deletions
if (isset($_GET['delete_course'])) {
    $delete_id = $_GET['delete_course'];
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE course_id = ?");
        $stmt->execute([$delete_id]);

        if ($stmt->fetchColumn() == 0) {
            $conn->exec("DELETE FROM courses WHERE id = $delete_id");
            $_SESSION['success'] = "Course deleted successfully!";
        } else {
            $_SESSION['error'] = "Cannot delete course - students are enrolled!";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error deleting course: " . $e->getMessage();
    }
}

// Fetch data
$courses = $conn->query("SELECT * FROM courses ORDER BY course_name")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>

<head>
     <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Courses</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="../public/assets/css/admin.css">
    <link rel="stylesheet" href="../admin/css/manage_std.css">
    <style>
  
        input,
        select {
            margin: 5px;
            padding: 5px;
        }

        .subject-pair {
            margin-bottom: 8px;
        }

        .message {
            padding: 10px;
            margin: 10px 0;
        }

        .success {
            background-color: #d4edda;
        }

        .error {
            background-color: #f8d7da;
        }
    </style>
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
            <h2>Manage Courses</h2>


            <?php if (isset($_SESSION['success'])): ?>
                <div class="message success"><?= $_SESSION['success'];
                unset($_SESSION['success']); ?></div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="message error"><?= $_SESSION['error'];
                unset($_SESSION['error']); ?></div>
            <?php endif; ?>

            <!-- Add Course Form -->
            <form method="POST">
                <h3>Add New Course</h3>
                <input type="text" name="course_name" placeholder="Course name" required>
                <select name="duration_type" required>
                    <option value="yearly">Yearly</option>
                    <option value="semester">Semester</option>
                </select>
                <input type="text" name="description" placeholder="Description">
                <button type="submit" name="add_course">Add Course</button>
            </form>

            <hr>

            <!-- Add Subjects Form -->
            <form method="POST">
                <h3>Add Subjects and Sub-subjects</h3>
                <label>Course:</label>
                <select name="course_id" required>
                    <?php foreach ($courses as $course): ?>
                        <option value="<?= $course['id'] ?>"><?= htmlspecialchars($course['course_name']) ?></option>
                    <?php endforeach; ?>
                </select><br>

                <label>Year or Semester:</label>
                <input type="text" name="year_or_semester" required><br>

                <div id="subject-container">
                    <div class="subject-pair">
                        <input type="text" name="subjects[]" placeholder="Subject name" required>
                        <input type="text" name="sub_subjects[]" placeholder="Sub-subject (optional)">
                    </div>
                </div>

                <button type="button" onclick="addSubjectRow()">+ Add More</button><br><br>
                <button type="submit" name="add_subjects">Submit Subjects</button>
            </form>

            <hr>

            <!-- List Courses -->
            <h3>Existing Courses</h3>
            <table border="1" cellpadding="8" cellspacing="0">
                <tr>
                    <th>Course Name</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Actions</th>
                </tr>
                <?php foreach ($courses as $course): ?>
                    <tr>
                        <td><?= htmlspecialchars($course['course_name']) ?></td>
                        <td><?= htmlspecialchars($course['duration_type']) ?></td>
                        <td><?= htmlspecialchars($course['description']) ?></td>
                        <td><a href="?delete_course=<?= $course['id'] ?>"
                                onclick="return confirm('Delete this course?')">Delete</a></td>
                    </tr>
                <?php endforeach; ?>
            </table>

            <script>
                function addSubjectRow() {
                    const container = document.getElementById('subject-container');
                    const div = document.createElement('div');
                    div.className = 'subject-pair';
                    div.innerHTML = `
                <input type="text" name="subjects[]" placeholder="Subject name" required>
                <input type="text" name="sub_subjects[]" placeholder="Sub-subject (optional)">
                `;
                    container.appendChild(div);
                }
            </script>
        </main>
        <footer>
            <p>&copy; <?= date("Y") ?> Patan Multiple Campus. All rights reserved.</p>
        </footer>

    </div>

</body>

</html>