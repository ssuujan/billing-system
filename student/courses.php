<?php
session_name('STUDENT_SESSION');
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: ../public/login.php");
    exit();
}

require_once __DIR__ . '/../config/database.php';

// Fetch user data
$stmt = $conn->prepare("SELECT users.*, courses.course_name FROM users 
                       LEFT JOIN courses ON users.course_id = courses.id 
                       WHERE users.id = ?");
$stmt->execute([$_SESSION['user']['id']]);
$currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle course selection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['select_course'])) {
    $course_id = $_POST['course_id'];
    
    $stmt = $conn->prepare("UPDATE users SET course_id = ? WHERE id = ?");
    $stmt->execute([$course_id, $currentUser['id']]);
    
    $_SESSION['success'] = "Course selected successfully!";
    header("Location: courses.php");
    exit();
}

// Fetch available courses
$courses = $conn->query("SELECT * FROM courses ORDER BY course_name")->fetchAll(PDO::FETCH_ASSOC);

// Fetch course structure if user has selected a course
$courseStructure = [];
if ($currentUser['course_id']) {
    $stmt = $conn->prepare("SELECT * FROM course_subjects WHERE course_id = ? ORDER BY year_or_semester, subject_name");
    $stmt->execute([$currentUser['course_id']]);
    $courseStructure = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
             
        <main>
            <h2>Course Selection</h2>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success"><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <?php endif; ?>
            
            <?php if ($currentUser['course_id']): ?>
                <div class="course-info">
                    <h3>Your Selected Course: <?= $currentUser['course_name'] ?></h3>
                    
                    <div class="course-structure">
                        <h4>Course Structure:</h4>
                        <?php
                        $currentSemester = null;
                        foreach ($courseStructure as $subject):
                            if ($subject['year_or_semester'] != $currentSemester):
                                $currentSemester = $subject['year_or_semester'];
                                echo "<h5>Year/Semester $currentSemester</h5><ul>";
                            endif;
                            echo "<li>{$subject['subject_name']}</li>";
                        endforeach;
                        if ($currentSemester) echo "</ul>";
                        ?>
                    </div>
                </div>
            <?php else: ?>
                <form method="POST">
                    <div class="form-group">
                        <label>Select Your Course:</label>
                        <select name="course_id" required>
                            <option value="">-- Choose a course --</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?= $course['id'] ?>">
                                    <?= $course['course_name'] ?> (<?= $course['duration_type'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="select_course" class="btn">Select Course</button>
                </form>
                
                <div class="available-courses">
                    <h3>Available Courses</h3>
                    <?php foreach ($courses as $course): ?>
                        <div class="course-card">
                            <h4><?= $course['course_name'] ?></h4>
                            <p>Duration: <?= $course['duration_type'] ?></p>
                            <p><?= $course['description'] ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
        </main>

        <footer>
            <p>&copy; 2023 Patan Multiple Campus</p>
        </footer>
    </div>
</body>
</html>
