<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_name('ADMIN_SESSION');
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../public/admin.php");
    exit();
}

require_once __DIR__ . '/../config/database.php';
$admin = $_SESSION['user'];

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
            $stmt = $conn->prepare("INSERT INTO course_subjects (course_id, year_or_semester, subject_name, sub_subjects) VALUES (?, ?, ?, ?)");

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

if (isset($_GET['delete_subject'])) {
    $subject_id = $_GET['delete_subject'];
    try {
        $stmt = $conn->prepare("SELECT course_id FROM course_subjects WHERE id = ?");
        $stmt->execute([$subject_id]);
        $subject = $stmt->fetch();

        if ($subject) {
            $course_id = $subject['course_id'];

            $stmt = $conn->prepare("DELETE FROM course_subjects WHERE id = ?");
            $stmt->execute([$subject_id]);

            $_SESSION['success'] = "Subject deleted successfully!";
            $_SESSION['open_course_id'] = $course_id;
        } else {
            $_SESSION['error'] = "Subject not found.";
        }
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error deleting subject: " . $e->getMessage();
    }
}

$courses = $conn->query("SELECT * FROM courses ORDER BY course_name")->fetchAll(PDO::FETCH_ASSOC);
$subjectsMap = [];
foreach ($courses as $course) {
    $stmt = $conn->prepare("SELECT * FROM course_subjects WHERE course_id = ?");
    $stmt->execute([$course['id']]);
    $subjectsMap[$course['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Manage Courses</title>
    <link href="./css/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/all.min.css">
    <style>
        .course-card { transition: all 0.3s ease; }
        .course-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .subject-item { border-left: 4px solid #3b82f6; }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex flex-col">
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
                        <li><a href="manage_students.php" class="hover:text-blue-300"><i class="fas fa-users mr-2"></i>Students</a></li>
                        <li><a href="manage_courses.php" class="font-bold text-blue-300"><i class="fas fa-book mr-2"></i>Courses</a></li>
                        <li><a href="logout.php" class="hover:text-blue-300"><i class="fas fa-sign-out-alt mr-2"></i>Logout</a></li>
                    </ul>
                </nav>
            </div>
        </header>

        <main class="flex-grow container mx-auto px-4 py-8">
            <div class="max-w-6xl mx-auto">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">Manage Courses</h2>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                        <p><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                        <p><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></p>
                    </div>
                <?php endif; ?>

                <div class="tabs mb-6">
                    <button class="tab-btn bg-blue-600 text-white px-4 py-2 rounded-l" data-tab="add-course">Add Course</button>
                    <button class="tab-btn bg-blue-600 text-white px-4 py-2" data-tab="add-subjects">Add Subjects</button>
                    <button class="tab-btn bg-blue-600 text-white px-4 py-2 rounded-r" data-tab="view-courses">View Courses</button>
                </div>

                <div id="add-course" class="tab-content active">
                    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                        <h3 class="text-xl font-semibold text-gray-800 mb-4">Add New Course</h3>
                        <form method="POST" class="space-y-4">
                            <div>
                                <label class="block text-gray-700 mb-2">Course Name:</label>
                                <input type="text" name="course_name" class="w-full px-4 py-2 border rounded-md" required>
                            </div>
                            <div>
                                <label class="block text-gray-700 mb-2">Duration Type:</label>
                                <select name="duration_type" class="w-full px-4 py-2 border rounded-md" required>
                                    <option value="">Select Type</option>
                                    <option value="4 years">4 Years</option>
                                    <option value="8 semesters">8 Semesters</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-gray-700 mb-2">Description:</label>
                                <textarea name="description" class="w-full px-4 py-2 border rounded-md"></textarea>
                            </div>
                            <button type="submit" name="add_course" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md">
                                <i class="fas fa-plus mr-2"></i>Add Course
                            </button>
                        </form>
                    </div>
                </div>

                <div id="add-subjects" class="tab-content">
                    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                        <h3 class="text-xl font-semibold text-gray-800 mb-4">Add Subjects</h3>
                        <form method="POST" class="space-y-4">
                            <div>
                                <label class="block text-gray-700 mb-2">Course:</label>
                                <select name="course_id" class="w-full px-4 py-2 border rounded-md" required>
                                    <?php foreach ($courses as $course): ?>
                                        <option value="<?= $course['id'] ?>"><?= htmlspecialchars($course['course_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-gray-700 mb-2">Year/Semester:</label>
                                <select name="year_or_semester" class="w-full px-4 py-2 border rounded-md" required>
                                    <?php for ($i = 1; $i <= 8; $i++): ?>
                                        <option value="<?= $i ?>"><?= $i ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div id="subject-container" class="space-y-4">
                                <div class="subject-pair flex space-x-4">
                                    <div class="flex-1">
                                        <label class="block text-gray-700 mb-2">Subject Name:</label>
                                        <input type="text" name="subjects[]" class="w-full px-4 py-2 border rounded-md" required>
                                    </div>
                                    <div class="flex-1">
                                        <label class="block text-gray-700 mb-2">Sub-subjects:</label>
                                        <input type="text" name="sub_subjects[]" class="w-full px-4 py-2 border rounded-md">
                                    </div>
                                </div>
                            </div>
                            <button type="button" onclick="addSubjectRow()" class="bg-gray-200 hover:bg-gray-300 text-gray-800 px-4 py-2 rounded-md">
                                <i class="fas fa-plus mr-2"></i>Add More Subjects
                            </button>
                            <div class="pt-4">
                                <button type="submit" name="add_subjects" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md">
                                    <i class="fas fa-save mr-2"></i>Save Subjects
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div id="view-courses" class="tab-content">
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-xl font-semibold text-gray-800 mb-4">Existing Courses</h3>
                        <?php if (empty($courses)): ?>
                            <p class="text-gray-600">No courses found.</p>
                        <?php else: ?>
                            <table class="min-w-full bg-white">
                                <thead class="bg-gray-100">
                                    <tr>
                                        <th class="py-3 px-4 text-left">Course Name</th>
                                        <th class="py-3 px-4 text-left">Type</th>
                                        <th class="py-3 px-4 text-left">Description</th>
                                        <th class="py-3 px-4 text-left">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($courses as $course): ?>
                                        <tr class="border-t">
                                            <td class="py-3 px-4"><?= htmlspecialchars($course['course_name']) ?></td>
                                            <td class="py-3 px-4"><?= htmlspecialchars($course['duration_type']) ?></td>
                                            <td class="py-3 px-4"><?= htmlspecialchars($course['description']) ?></td>
                                            <td class="py-3 px-4 space-x-2">
                                                <a href="?delete_course=<?= $course['id'] ?>" class="text-red-600" onclick="return confirm('Delete this course?')">
                                                    <i class="fas fa-trash mr-1"></i>Delete
                                                </a>
                                                <a href="javascript:void(0)" class="text-blue-600 view-subjects" data-course-id="<?= $course['id'] ?>">
                                                    <i class="fas fa-eye mr-1"></i>View Subjects
                                                </a>
                                            </td>
                                        </tr>
                                        <tr id="subjects-<?= $course['id'] ?>" class="hidden bg-gray-50">
                                            <td colspan="4" class="py-4 px-4">
                                                <?php if (!empty($subjectsMap[$course['id']])): ?>
                                                    <?php 
                                                    $grouped = [];
                                                    foreach ($subjectsMap[$course['id']] as $subj) {
                                                        $grouped[$subj['year_or_semester']][] = $subj;
                                                    }
                                                    foreach ($grouped as $group => $subs): ?>
                                                        <div class="mb-4">
                                                            <h4 class="font-medium text-gray-700 mb-2"><?= str_contains($course['duration_type'], 'year') ? "Year $group" : "Semester $group" ?>:</h4>
                                                            <ul class="space-y-2 ml-4">
                                                                <?php foreach ($subs as $s): ?>
                                                                    <li class="subject-item bg-white p-3 rounded-md shadow-sm flex justify-between items-start">
                                                                        <div>
                                                                            <div class="font-medium"><?= htmlspecialchars($s['subject_name']) ?></div>
                                                                            <?php if ($s['sub_subjects']): ?>
                                                                                <div class="text-sm text-gray-600 mt-1">
                                                                                    <span class="font-medium">Topics:</span> <?= htmlspecialchars($s['sub_subjects']) ?>
                                                                                </div>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                        <a href="?delete_subject=<?= $s['id'] ?>" class="text-red-600 hover:text-red-800 text-sm ml-4" onclick="return confirm('Delete this subject?')">
                                                                            <i class="fas fa-trash-alt mr-1"></i>Delete
                                                                        </a>
                                                                    </li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php else: ?>
                                                    <p class="text-gray-600 italic">No subjects added for this course.</p>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>

        <footer class="bg-gray-800 text-white py-6 text-center">
            <p>&copy; <?= date('Y') ?> Patan Multiple Campus. All rights reserved.</p>
        </footer>
    </div>

    <script>
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.replace('bg-blue-700', 'bg-blue-600'));
                btn.classList.replace('bg-blue-600', 'bg-blue-700');

                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                document.getElementById(btn.dataset.tab).classList.add('active');
            });
        });

        function addSubjectRow() {
            const container = document.getElementById('subject-container');
            const div = document.createElement('div');
            div.className = 'subject-pair flex space-x-4';
            div.innerHTML = `
                <div class="flex-1"><input type="text" name="subjects[]" class="w-full px-4 py-2 border rounded-md" required></div>
                <div class="flex-1"><input type="text" name="sub_subjects[]" class="w-full px-4 py-2 border rounded-md"></div>`;
            container.appendChild(div);
        }

        document.querySelectorAll('.view-subjects').forEach(link => {
            link.addEventListener('click', function () {
                const row = document.getElementById(`subjects-${this.dataset.courseId}`);
                const icon = this.querySelector('i');
                row.classList.toggle('hidden');
                icon.classList.toggle('fa-eye');
                icon.classList.toggle('fa-eye-slash');
            });
        });
    </script>

    <?php if (isset($_SESSION['open_course_id'])): ?>
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const courseId = <?= json_encode($_SESSION['open_course_id']) ?>;
            const row = document.getElementById(`subjects-${courseId}`);
            const trigger = document.querySelector(`.view-subjects[data-course-id='${courseId}']`);
            if (row && trigger) trigger.click();
        });
    </script>
    <?php unset($_SESSION['open_course_id']); endif; ?>
</body>
</html>
