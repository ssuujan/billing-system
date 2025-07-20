<?php
session_name('STUDENT_SESSION');
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: ../public/login.php");
    exit();
}

require_once __DIR__ . '/../config/database.php';

// Get current user's data with approval check
try {
    $stmt = $conn->prepare("SELECT users.*, courses.course_name FROM users 
                        LEFT JOIN courses ON users.course_id = courses.id 
                        WHERE users.id = :user_id");
    $stmt->execute([':user_id' => $_SESSION['user']['id']]);
    $currentUser = $stmt->fetch();

    if ($currentUser && (!isset($currentUser['approved']) || $currentUser['approved'] != 1)) {
        $currentUser = [
            'id' => $currentUser['id'],
            'name' => $currentUser['name'],
            'email' => $currentUser['email'],
            'approved' => 0,
            'role' => $currentUser['role'] ?? 'student'
        ];
        $_SESSION['user'] = $currentUser;
    }

    if ($currentUser && !isset($currentUser['role'])) {
        $currentUser['role'] = 'student';
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    $_SESSION['error'] = "Database error occurred. Please try again later.";
    header("Location: dashboard.php");
    exit();
}

// Handle form submissions only if approved
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_values'])) {
    if (!isset($currentUser['approved']) || $currentUser['approved'] != 1) {
        $_SESSION['error'] = "Your account must be approved to make changes.";
        header("Location: dashboard.php");
        exit();
    }

    try {
        $stmt = $conn->prepare("
            UPDATE users 
            SET name = :name,
                email = :email,
                phone = :phone,
                address = :address,
                password = :password
            WHERE id = :user_id
        ");

        $success = $stmt->execute([
            ':name' => $_POST['name'],
            ':email' => $_POST['email'],
            ':phone' => $_POST['phone'],
            ':address' => $_POST['address'],
            ':password' => $_POST['password'],
            ':user_id' => $currentUser['id']
        ]);

        if ($success) {
            $_SESSION['user']['name'] = $_POST['name'];
            $_SESSION['success'] = "Profile updated successfully!";
            header("Location: dashboard.php");
            exit();
        }
    } catch (PDOException $e) {
        error_log("Update error: " . $e->getMessage());
        $_SESSION['error'] = "Failed to update information. Please try again.";
        header("Location: dashboard.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <style>
        .password-toggle { cursor: pointer; }
        .editable-row { display: none; }
        .edit-mode .view-row { display: none; }
        .edit-mode .editable-row { display: table-row; }
        .subject-item { border-left: 4px solid #3b82f6; }
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
                            <li><a href="bills_user.php" class="hover:text-blue-300" ><i class="fas fa-file-invoice-dollar mr-2"></i>Bills</a></li>
                        <?php endif; ?>
                        <li><a href="logout.php" class="hover:text-blue-300"><i class="fas fa-sign-out-alt mr-2"></i>Logout</a></li>
                    </ul>
                </nav>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-grow container mx-auto px-4 py-8">
            <div class="max-w-4xl mx-auto">
                <!-- Status Messages -->
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                        <i class="fas fa-check-circle mr-2"></i> <?= htmlspecialchars($_SESSION['success']) ?>
                        <?php unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                        <i class="fas fa-exclamation-circle mr-2"></i> <?= htmlspecialchars($_SESSION['error']) ?>
                        <?php unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <!-- Admission Status -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Admission Status</h2>
                    <?php if (isset($currentUser['approved']) && $currentUser['approved'] == 1): ?>
                        <div class="flex items-center text-green-600">
                            <i class="fas fa-check-circle mr-2"></i>
                            <p>Your admission has been approved!</p>
                        </div>
                    <?php else: ?>
                        <div class="flex items-center text-yellow-600">
                            <i class="fas fa-clock mr-2"></i>
                            <p>Pending admission approval from admin</p>
                        </div>
                        <p class="text-gray-600 mt-2">You will be able to access all features once your admission is approved.</p>
                    <?php endif; ?>
                </div>

                <!-- Profile Information -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Profile Information</h2>

                    <?php if (!$currentUser): ?>
                        <p class="text-gray-600">Your profile information could not be loaded.</p>
                    <?php else: ?>
                        <form method="POST" id="profile-form">
                            <table class="w-full" id="profile-table">
                                <thead class="bg-gray-100">
                                    <tr>
                                        <th class="py-2 px-4 text-left">Field</th>
                                        <th class="py-2 px-4 text-left">Info</th>
                                        <th class="py-2 px-4 text-left">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Name -->
                                    <tr class="view-row border-t border-gray-200">
                                        <td class="py-3 px-4 font-medium">Name</td>
                                        <td class="py-3 px-4"><?= htmlspecialchars($currentUser['name'] ?? 'Not set') ?></td>
                                        <td class="py-3 px-4">
                                            <?php if (isset($currentUser['approved']) && $currentUser['approved'] == 1): ?>
                                                <button type="button" class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-sm" onclick="toggleEditMode(true)">
                                                    <i class="fas fa-edit mr-1"></i> Edit
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr class="editable-row border-t border-gray-200">
                                        <td class="py-3 px-4 font-medium">Name</td>
                                        <td class="py-3 px-4">
                                            <input type="text" name="name" class="w-full px-3 py-2 border rounded" 
                                                value="<?= htmlspecialchars($currentUser['name'] ?? '') ?>" required>
                                        </td>
                                        <td class="py-3 px-4"></td>
                                    </tr>

                                    <!-- Email -->
                                    <tr class="view-row border-t border-gray-200">
                                        <td class="py-3 px-4 font-medium">Email</td>
                                        <td class="py-3 px-4"><?= htmlspecialchars($currentUser['email'] ?? 'Not set') ?></td>
                                        <td class="py-3 px-4"></td>
                                    </tr>
                                    <tr class="editable-row border-t border-gray-200">
                                        <td class="py-3 px-4 font-medium">Email</td>
                                        <td class="py-3 px-4">
                                            <input type="email" name="email" class="w-full px-3 py-2 border rounded" 
                                                value="<?= htmlspecialchars($currentUser['email'] ?? '') ?>" required>
                                        </td>
                                        <td class="py-3 px-4"></td>
                                    </tr>

                                    <!-- Phone -->
                                    <tr class="view-row border-t border-gray-200">
                                        <td class="py-3 px-4 font-medium">Phone</td>
                                        <td class="py-3 px-4"><?= htmlspecialchars($currentUser['phone'] ?? 'Not set') ?></td>
                                        <td class="py-3 px-4"></td>
                                    </tr>
                                    <tr class="editable-row border-t border-gray-200">
                                        <td class="py-3 px-4 font-medium">Phone</td>
                                        <td class="py-3 px-4">
                                            <input type="text" name="phone" class="w-full px-3 py-2 border rounded" 
                                                value="<?= htmlspecialchars($currentUser['phone'] ?? '') ?>">
                                        </td>
                                        <td class="py-3 px-4"></td>
                                    </tr>

                                    <!-- Course -->
                                    <tr class="border-t border-gray-200">
                                        <td class="py-3 px-4 font-medium">Course</td>
                                        <td class="py-3 px-4"><?= htmlspecialchars($currentUser['course_name'] ?? 'Not set') ?></td>
                                        <td class="py-3 px-4"></td>
                                    </tr>

                                    <!-- Address -->
                                    <tr class="view-row border-t border-gray-200">
                                        <td class="py-3 px-4 font-medium">Address</td>
                                        <td class="py-3 px-4"><?= htmlspecialchars($currentUser['address'] ?? 'Not set') ?></td>
                                        <td class="py-3 px-4"></td>
                                    </tr>
                                    <tr class="editable-row border-t border-gray-200">
                                        <td class="py-3 px-4 font-medium">Address</td>
                                        <td class="py-3 px-4">
                                            <input type="text" name="address" class="w-full px-3 py-2 border rounded" 
                                                value="<?= htmlspecialchars($currentUser['address'] ?? '') ?>">
                                        </td>
                                        <td class="py-3 px-4"></td>
                                    </tr>

                                    <!-- Password -->
                                    <tr class="view-row border-t border-gray-200">
                                        <td class="py-3 px-4 font-medium">Password</td>
                                        <td class="py-3 px-4">
                                            <span class="flex items-center">
                                                <span id="password-display">••••••••</span>
                                                <i class="fas fa-eye password-toggle ml-2" onclick="togglePasswordVisibility()"></i>
                                            </span>
                                        </td>
                                        <td class="py-3 px-4"></td>
                                    </tr>
                                    <tr class="editable-row border-t border-gray-200">
                                        <td class="py-3 px-4 font-medium">Password</td>
                                        <td class="py-3 px-4">
                                            <div class="flex items-center">
                                                <input type="password" name="password" id="password-input"
                                                    class="w-full px-3 py-2 border rounded" 
                                                    value="<?= htmlspecialchars($currentUser['password'] ?? '') ?>">
                                                <i class="fas fa-eye password-toggle ml-2" onclick="togglePasswordInputVisibility()"></i>
                                            </div>
                                        </td>
                                        <td class="py-3 px-4"></td>
                                    </tr>

                                    <!-- Role -->
                                    <tr class="border-t border-gray-200">
                                        <td class="py-3 px-4 font-medium">Role</td>
                                        <td class="py-3 px-4"><?= htmlspecialchars($currentUser['role'] ?? 'student') ?></td>
                                        <td class="py-3 px-4"></td>
                                    </tr>

                                    <!-- Approval Status -->
                                    <tr class="border-t border-gray-200">
                                        <td class="py-3 px-4 font-medium">Approval Status</td>
                                        <td class="py-3 px-4">
                                            <?php if (isset($currentUser['approved']) && $currentUser['approved'] == 1): ?>
                                                <span class="text-green-600">Approved</span>
                                            <?php else: ?>
                                                <span class="text-yellow-600">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3 px-4"></td>
                                    </tr>

                                    <!-- Form buttons -->
                                    <tr class="editable-row border-t border-gray-200">
                                        <td colspan="3" class="py-3 px-4 text-center space-x-4">
                                            <button type="submit" name="update_values" 
                                                class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded">
                                                <i class="fas fa-save mr-1"></i> Save Changes
                                            </button>
                                            <button type="button" onclick="toggleEditMode(false)" 
                                                class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded">
                                                <i class="fas fa-times mr-1"></i> Cancel
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </main>

        <footer class="bg-gray-800 text-white py-6">
            <div class="container mx-auto px-4 text-center">
                <p>&copy; <?= date('Y') ?> Patan Multiple Campus. All rights reserved.</p>
            </div>
        </footer>
    </div>

    <script>
        // Toggle between view and edit modes
        function toggleEditMode(enable) {
            const table = document.getElementById('profile-table');
            if (enable) {
                table.classList.add('edit-mode');
            } else {
                table.classList.remove('edit-mode');
            }
        }

        // Toggle password visibility in view mode
        let isPasswordVisible = false;
        function togglePasswordVisibility() {
            const display = document.getElementById('password-display');
            const toggleIcon = document.querySelector('.view-row .password-toggle');

            if (isPasswordVisible) {
                display.textContent = '••••••••';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            } else {
                display.textContent = '<?= htmlspecialchars($currentUser['password'] ?? '') ?>';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            }

            isPasswordVisible = !isPasswordVisible;
        }

        // Toggle password visibility in edit mode
        function togglePasswordInputVisibility() {
            const input = document.getElementById('password-input');
            const toggleIcon = document.querySelector('.editable-row .password-toggle');

            if (input.type === 'password') {
                input.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>