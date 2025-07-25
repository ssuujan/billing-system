<?php
session_name('STUDENT_SESSION');
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: ../public/login.php");
    exit();
}

require_once __DIR__ . '/../config/database.php';

// Fetch user data with course info
$userQuery = db_query("
    SELECT u.*, c.course_name, c.duration_type 
    FROM users u
    LEFT JOIN courses c ON u.course_id = c.id
    WHERE u.id = ?
", [$_SESSION['user']['id']]);

$currentUser = $userQuery ? $userQuery->fetch(PDO::FETCH_ASSOC) : null;

if (!$currentUser) {
    $_SESSION['error'] = "User data could not be loaded. Please try again.";
    header("Location: ../public/login.php");
    exit();
}

// Handle course selection/drop
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['select_course'])) {
            $course_id = $_POST['course_id'] ?? null;
            if ($course_id) {
                db_query("UPDATE users SET course_id = ? WHERE id = ?", [$course_id, $currentUser['id']]);
                $_SESSION['success'] = "Course selected successfully!";
            }
        } 
        elseif (isset($_POST['drop_course'])) {
            db_query("UPDATE users SET course_id = NULL WHERE id = ?", [$currentUser['id']]);
            $_SESSION['success'] = "Course dropped successfully!";
        }
        header("Location: courses.php");
        exit();
    } catch(PDOException $e) {
        error_log("Course update error: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred. Please try again.";
        header("Location: courses.php");
        exit();
    }
}

// Fetch available courses
$courses = db_query("SELECT * FROM courses ORDER BY course_name")->fetchAll(PDO::FETCH_ASSOC) ?? [];

// Fetch course structure if enrolled
$courseStructure = [];
if (!empty($currentUser['course_id'])) {
    $structureQuery = db_query("
        SELECT * FROM course_subjects 
        WHERE course_id = ? 
        ORDER BY year_or_semester, subject_name
    ", [$currentUser['course_id']]);
    
    $courseStructure = $structureQuery ? $structureQuery->fetchAll(PDO::FETCH_ASSOC) : [];
}

// Fetch fee structures for the enrolled course
$feeStructures = [];
if (!empty($currentUser['course_id'])) {
    $feeQuery = db_query("
        SELECT fs.*, 
               (SELECT COUNT(*) FROM fee_installments fi WHERE fi.fee_structure_id = fs.id) as installment_count
        FROM fee_structures fs
        WHERE fs.course_id = ?
        ORDER BY fs.fee_type, fs.period
    ", [$currentUser['course_id']]);
    
    $feeStructures = $feeQuery ? $feeQuery->fetchAll(PDO::FETCH_ASSOC) : [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Course Management | Patan Multiple Campus</title>
     <link href="../student/css/tailwind.min.css?v=<?= time() ?>" rel="stylesheet">
     <link rel="stylesheet" href="../student/css/all.min.css?v=<?= time() ?>">
    <!-- <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"> -->
    <!-- <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet"> -->
    <style>
        .course-card {
            transition: all 0.3s ease;
        }
        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .subject-item {
            border-left: 4px solid #3b82f6;
        }
        .fee-item {
            border-left: 4px solid #10b981;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
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
                        <h1 class="text-xl font-bold">Patan Multiple Campus</h1>
                        <p class="text-blue-200">Welcome, <?= htmlspecialchars($currentUser['name'] ?? 'Student') ?></p>
                    </div>
                </div>
  <nav>
                    <ul class="flex space-x-6">
                        <li><a href="dashboard.php" class="font-bold text-blue-300"><i class="fas fa-user mr-2"></i>Profile</a></li>
                        <?php if (isset($currentUser['approved']) && $currentUser['approved'] == 1): ?>
                            <li><a href="courses.php" class="hover:text-blue-300"><i class="fas fa-book mr-2"></i>Courses</a></li>
                            <li><a href="bills_user.php" class="hover:text-blue-300"><i class="fas fa-file-invoice-dollar mr-2"></i>Bills</a></li>
                            <?php endif; ?>
                        <li><a href="logout.php" class="hover:text-blue-300"><i class="fas fa-sign-out-alt mr-2"></i>Logout</a></li>
                    </ul>
                    </nav>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-grow container mx-auto px-4 py-8">
            <div class="max-w-6xl mx-auto">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">Course Management</h2>
                
                <!-- Alert Messages -->
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                        <p><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                        <p><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></p>
                    </div>
                <?php endif; ?>
                
                <!-- Course Information -->
                <?php if (!empty($currentUser['course_id'])): ?>
                    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-xl font-semibold text-gray-800">
                                Your Current Course: <span class="text-blue-600"><?= htmlspecialchars($currentUser['course_name'] ?? 'Not specified') ?></span>
                            </h3>
                            <form method="POST">
                                <button type="submit" name="drop_course" 
                                    class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md transition"
                                    onclick="return confirm('Are you sure you want to drop this course?')">
                                    <i class="fas fa-times mr-2"></i>Drop Course
                                </button>
                            </form>
                        </div>
                        
                        <p class="text-gray-600 mb-4">
                            Duration: <?= htmlspecialchars($currentUser['duration_type'] ?? 'Not specified') ?>
                        </p>
                        
                        <!-- Fee Structure Section -->
                        <?php if (!empty($feeStructures)): ?>
                            <div class="mt-8">
                                <h4 class="text-lg font-semibold text-gray-800 mb-4">Course Fee Structure:</h4>
                                
                                <div class="mb-4">
                                    <nav class="flex space-x-4 border-b">
                                        <button onclick="switchTab('all-fees')" class="tab-button active py-2 px-4 border-b-2 font-medium text-sm border-blue-500 text-blue-600">
                                            All Fees
                                        </button>
                                        <button onclick="switchTab('semester-fees')" class="tab-button py-2 px-4 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                            Semester-wise
                                        </button>
                                        <button onclick="switchTab('yearly-fees')" class="tab-button py-2 px-4 border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                                            Yearly
                                        </button>
                                    </nav>
                                </div>
                                
                                <div id="all-fees" class="tab-content active">
                                    <div class="space-y-4">
                                        <?php foreach ($feeStructures as $fee): ?>
                                            <div class="fee-item bg-white p-4 rounded-md shadow-sm">
                                                <div class="flex justify-between items-start">
                                                    <div>
                                                        <h5 class="font-medium text-gray-800">
                                                            <?= $fee['fee_type'] === 'semester' ? 'Semester ' : 'Year ' ?><?= $fee['period'] ?> Fee
                                                        </h5>
                                                        <p class="text-green-600 font-semibold">
                                                            NPR <?= number_format($fee['total_fee'], 2) ?>
                                                        </p>
                                                        <p class="text-sm text-gray-600 mt-1">
                                                            Payment Option: <?= ucfirst($fee['payment_option']) ?>
                                                            <?= $fee['payment_option'] === 'installment' ? "({$fee['installment_count']} installments)" : '' ?>
                                                        </p>
                                                    </div>
                                                </div>
                                                
                                                <?php if ($fee['payment_option'] === 'installment'): ?>
                                                    <?php 
                                                    $installments = db_query("
                                                        SELECT * FROM fee_installments 
                                                        WHERE fee_structure_id = ? 
                                                        ORDER BY installment_number
                                                    ", [$fee['id']])->fetchAll(PDO::FETCH_ASSOC);
                                                    ?>
                                                    <?php if (!empty($installments)): ?>
                                                        <div class="mt-3">
                                                            <h6 class="text-sm font-medium text-gray-700 mb-1">Installment Plan:</h6>
                                                            <ul class="space-y-2">
                                                                <?php foreach ($installments as $installment): ?>
                                                                    <li class="flex justify-between text-sm">
                                                                        <span>Installment <?= $installment['installment_number'] ?>:</span>
                                                                        <span class="font-medium">NPR <?= number_format($installment['amount'], 2) ?></span>
                                                                        <span class="text-gray-500">Due: <?= date('M d, Y', strtotime($installment['due_date'])) ?></span>
                                                                    </li>
                                                                <?php endforeach; ?>
                                                            </ul>
                                                        </div>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <!-- Filtered tabs would show the same data but filtered -->
                                <div id="semester-fees" class="tab-content">
                                    <div class="space-y-4">
                                        <?php foreach ($feeStructures as $fee): ?>
                                            <?php if ($fee['fee_type'] === 'semester'): ?>
                                                <!-- Same fee item structure as above -->
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <div id="yearly-fees" class="tab-content">
                                    <div class="space-y-4">
                                        <?php foreach ($feeStructures as $fee): ?>
                                            <?php if ($fee['fee_type'] === 'yearly'): ?>
                                                <!-- Same fee item structure as above -->
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-600">No fee structure available for this course.</p>
                        <?php endif; ?>
                        
                        <!-- Course Structure Section -->
                        <h4 class="text-lg font-semibold text-gray-800 mt-8 mb-4">Course Structure:</h4>
                        
                        <div class="space-y-6">
                            <?php
                            $currentLevel = null;
                            if (!empty($courseStructure)) {
                                foreach ($courseStructure as $subject):
                                    if ($subject['year_or_semester'] != $currentLevel):
                                        $currentLevel = $subject['year_or_semester'];
                                        $levelLabel = str_contains($currentUser['duration_type'] ?? '', 'year') ? 
                                            "Year $currentLevel" : "Semester $currentLevel";
                                        ?>
                                        <div>
                                            <h5 class="font-medium text-gray-700 bg-gray-100 p-2 rounded-md">
                                                <?= htmlspecialchars($levelLabel) ?>
                                            </h5>
                                            <div class="ml-4 mt-2">
                                    <?php endif; ?>
                                    
                                    <div class="subject-item bg-white p-4 rounded-md shadow-sm mb-3">
                                        <div class="flex justify-between items-center">
                                            <div>
                                                <h6 class="font-medium text-gray-800"><?= htmlspecialchars($subject['subject_name'] ?? 'Untitled Subject') ?></h6>
                                                <?php if (!empty($subject['price'])): ?>
                                                    <span class="text-sm text-green-600">NRs. <?= number_format($subject['price'], 2) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <?php if (!empty($subject['sub_subjects'])): ?>
                                            <div class="mt-2">
                                                <p class="text-sm text-gray-600">Topics:</p>
                                                <ul class="list-disc list-inside text-sm text-gray-600 ml-4">
                                                    <?php 
                                                    $subtopics = explode(',', $subject['sub_subjects']);
                                                    foreach ($subtopics as $topic): ?>
                                                        <li><?= htmlspecialchars(trim($topic)) ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php 
                                    $nextSubject = next($courseStructure);
                                    if (($nextSubject === false) || ($nextSubject['year_or_semester'] != $currentLevel)): ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach;
                            } else {
                                echo "<p class='text-gray-600'>No course structure details available.</p>";
                            }
                            ?>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Course Selection Form -->
                    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                        <h3 class="text-xl font-semibold text-gray-800 mb-4">Select Your Course</h3>
                        
                        <form method="POST" class="space-y-4">
                            <div class="form-group">
                                <label class="block text-gray-700 mb-2">Choose a course:</label>
                                <select name="course_id" required
                                    class="w-full px-4 py-2 border rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="">-- Select Course --</option>
                                    <?php if (!empty($courses)): ?>
                                        <?php foreach ($courses as $course): ?>
                                            <option value="<?= htmlspecialchars($course['id']) ?>">
                                                <?= htmlspecialchars($course['course_name']) ?> 
                                                (<?= htmlspecialchars($course['duration_type']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="" disabled>No courses available</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            
                            <button type="submit" name="select_course"
                                class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-2 rounded-md transition">
                                <i class="fas fa-check mr-2"></i>Select Course
                            </button>
                        </form>
                    </div>
                    
                    <!-- Available Courses -->
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h3 class="text-xl font-semibold text-gray-800 mb-4">Available Courses</h3>
                        
                        <?php if (!empty($courses)): ?>
                            <div class="grid md:grid-cols-2 gap-6">
                                <?php foreach ($courses as $course): ?>
                                    <div class="course-card bg-white border border-gray-200 rounded-lg overflow-hidden shadow-sm hover:shadow-md">
                                        <div class="p-6">
                                            <h4 class="text-lg font-semibold text-gray-800 mb-2">
                                                <?= htmlspecialchars($course['course_name']) ?>
                                            </h4>
                                            <span class="inline-block bg-blue-100 text-blue-800 text-xs px-2 py-1 rounded-full mb-3">
                                                <?= htmlspecialchars($course['duration_type']) ?>
                                            </span>
                                            <p class="text-gray-600 mb-4"><?= htmlspecialchars($course['description'] ?? 'No description available') ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="text-gray-600">No courses are currently available for enrollment.</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>

        <!-- Footer -->
        <footer class="bg-gray-800 text-white py-6">
            <div class="container mx-auto px-4 text-center">
                <p>&copy; <?= date('Y') ?> Patan Multiple Campus. All rights reserved.</p>
            </div>
        </footer>
    </div>

    <script>
        // Confirm before dropping course
        document.querySelector('button[name="drop_course"]')?.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to drop this course? This action cannot be undone.')) {
                e.preventDefault();
            }
        });

        // Tab switching for fee structure
        function switchTab(tabId) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabId).classList.add('active');
            
            // Update active tab button
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active', 'border-blue-500', 'text-blue-600');
                btn.classList.add('border-transparent', 'text-gray-500');
            });
            
            event.target.classList.add('active', 'border-blue-500', 'text-blue-600');
            event.target.classList.remove('border-transparent', 'text-gray-500');
        }
    </script>
</body>
</html>