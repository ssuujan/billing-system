<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: ../public/login.php");
    exit();
}

require_once __DIR__ . '/../config/database.php';

// Get current user's data with approval check
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :user_id");
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
                course = :course,
                address = :address,
                password = :password
            WHERE id = :user_id
        ");
        
        $success = $stmt->execute([
            ':name' => $_POST['name'],
            ':email' => $_POST['email'],
            ':phone' => $_POST['phone'],
            ':course' => $_POST['course'],
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
    <link rel="stylesheet" href="../public/assets/css/dashboard.css">
    <link rel="stylesheet" href="../student/assets/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
   
</head>
<body>
    <div class="container">
        <header class="header">
            <div class="logo">
                <img src="../public/assets/images/logo.png" alt="Campus Logo">
            </div>
            <div class="header-text">
                <h1>Welcome, <?= htmlspecialchars($currentUser['name'] ?? 'Student') ?></h1>
                <p>Role: <?= htmlspecialchars($currentUser['role'] ?? 'Student') ?></p>
            </div>
        </header>

        <nav>
            <ul>
                <li><a href="dashboard.php" class="active"><i class="fas fa-user"></i> Profile</a></li>
                <?php if (isset($currentUser['approved']) && $currentUser['approved'] == 1): ?>
                    <li><a href="courses.php"><i class="fas fa-book"></i> Courses</a></li>
                <?php endif; ?>
                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
        </nav>
        
        <main>
            <!-- Status Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($_SESSION['success']) ?>
                    <?php unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert error">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($_SESSION['error']) ?>
                    <?php unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
            
            <!-- Admission Status -->
            <div class="status-container">
                <h2>Admission Status</h2>
                <?php if (isset($currentUser['approved']) && $currentUser['approved'] == 1): ?>
                    <p class="status-approved">
                        <i class="fas fa-check-circle"></i> Your admission has been approved!
                    </p>
                <?php else: ?>
                    <p class="status-pending">
                        <i class="fas fa-clock"></i> Pending for admission approval from admin
                    </p>
                    <p>You will be able to access all features once your admission is approved.</p>
                <?php endif; ?>
            </div>
            
            <!-- Profile Information -->
            <div class="profile-info">
                <h2>Profile Information</h2>
                
                <?php if (!$currentUser): ?>
                    <p>Your profile information could not be loaded.</p>
                <?php else: ?>
                    <form method="POST" id="profile-form" autocomplete="off">
                        <table id="profile-table" autocomplete="off">
                            <thead>
                                <tr>
                                    <th>Field</th>
                                    <th>Info</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Name -->
                                <tr class="view-row">
                                    <td><strong>Name</strong></td>
                                    <td><?= htmlspecialchars($currentUser['name'] ?? 'Not set') ?></td>
                                    <td>
                                        <?php if (isset($currentUser['approved']) && $currentUser['approved'] == 1): ?>
                                            <button type="button" class="btn btn-edit" onclick="toggleEditMode(true)">
                                                <i class="fas fa-edit"></i> Edit
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <tr class="editable-row">
                                    <td><strong>Name</strong></td>
                                    <td>
                                        <input type="text" name="name" autocomplete="off" value="<?= htmlspecialchars($currentUser['name'] ?? '') ?>" required>
                                    </td>
                                    <td></td>
                                </tr>
                                
                                <!-- Email -->
                                <tr class="view-row">
                                    <td><strong>Email</strong></td>
                                    <td><?= htmlspecialchars($currentUser['email'] ?? 'Not set') ?></td>
                                    <td></td>
                                </tr>
                                <tr class="editable-row">
                                    <td><strong>Email</strong></td>
                                    <td>
                                        <input type="email" name="email" autocomplete="off" value="<?= htmlspecialchars($currentUser['email'] ?? '') ?>" required>
                                    </td>
                                    <td></td>
                                </tr>
                                
                                <!-- Phone -->
                                <tr class="view-row">
                                    <td><strong>Phone</strong></td>
                                    <td><?= htmlspecialchars($currentUser['phone'] ?? 'Not set') ?></td>
                                    <td></td>
                                </tr>
                                <tr class="editable-row">
                                    <td><strong>Phone</strong></td>
                                    <td>
                                        <input type="text" name="phone"autocomplete="off" value="<?= htmlspecialchars($currentUser['phone'] ?? '') ?>">
                                    </td>
                                    <td></td>
                                </tr>
                                
                                <!-- Course -->
                                <tr class="view-row">
                                    <td><strong>Course</strong></td>
                                    <td><?= htmlspecialchars($currentUser['course'] ?? 'Not set') ?></td>
                                    <td></td>
                                </tr>
                                <tr class="editable-row">
                                    <td><strong>Course</strong></td>
                                    <td>
                                        <input type="text" name="course" value="<?= htmlspecialchars($currentUser['course'] ?? '') ?>">
                                    </td>
                                    <td></td>
                                </tr>
                                
                                <!-- Address -->
                                <tr class="view-row">
                                    <td><strong>Address</strong></td>
                                    <td><?= htmlspecialchars($currentUser['address'] ?? 'Not set') ?></td>
                                    <td></td>
                                </tr>
                                <tr class="editable-row">
                                    <td><strong>Address</strong></td>
                                    <td>
                                        <input type="text" name="address" autocomplete="off"value="<?= htmlspecialchars($currentUser['address'] ?? '') ?>">
                                    </td>
                                    <td></td>
                                </tr>
                                
                                <!-- Password -->
                                <tr class="view-row">
                                    <td><strong>Password</strong></td>
                                    <td>
                                        <span class="password-field">
                                            <span id="password-display">••••••••</span>
                                            <i class="fas fa-eye password-toggle" onclick="togglePasswordVisibility()"></i>
                                        </span>
                                    </td>
                                    <td></td>
                                </tr>
                                <tr class="editable-row">
                                    <td><strong>Password</strong></td>
                                    <td>
                                        <input type="password" name="password" value="<?= htmlspecialchars($currentUser['password'] ?? '') ?>" id="password-input">
                                        <i class="fas fa-eye password-toggle" onclick="togglePasswordInputVisibility()"></i>
                                    </td>
                                    <td></td>
                                </tr>
                                
                                <!-- Role (non-editable) -->
                                <tr>
                                    <td><strong>Role</strong></td>
                                    <td><?= htmlspecialchars($currentUser['role'] ?? 'student') ?></td>
                                    <td></td>
                                </tr>
                                
                                <!-- Approval Status (non-editable) -->
                                <tr>
                                    <td><strong>Approval Status</strong></td>
                                    <td>
                                        <?php if (isset($currentUser['approved']) && $currentUser['approved'] == 1): ?>
                                            <span class="status-approved">Approved</span>
                                        <?php else: ?>
                                            <span class="status-pending">Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td></td>
                                </tr>
                                
                                <!-- Form buttons (only shown in edit mode) -->
                                <tr class="editable-row">
                                    <td colspan="3" style="text-align: center;">
                                        <button type="submit" name="update_values" class="btn btn-save">
                                            <i class="fas fa-save"></i> Save Changes
                                        </button>
                                        <button type="button" onclick="toggleEditMode(false)" class="btn btn-cancel">
                                            <i class="fas fa-times"></i> Cancel
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </form>
                <?php endif; ?>
            </div>
        </main>

        <footer>
            <p>&copy; <?= date('Y') ?> Patan Multiple Campus. All rights reserved.</p>
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