<?php
session_name('STUDENT_SESSION');
session_start();
require_once __DIR__ . '/../config/database.php';

if (!isset($_SESSION['user'])) {
    header("Location: ../public/login.php");
    exit();
}
try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$_SESSION['user']['email']]);
    $currentUser = $stmt->fetch();
    
    if (!$currentUser) {
        throw new Exception("User not found");
    }
} catch (Exception $e) {
    error_log("Error fetching user: " . $e->getMessage());
    $_SESSION['error'] = "Error loading profile";
    header("Location: dashboard.php");
    exit();
}



// Handle change requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_changes'])) {
    try {
        $fields = ['name', 'email', 'phone', 'course', 'address', 'password'];
        $changes_made = false;
        
        foreach ($fields as $field) {
            if (isset($_POST[$field]) && $_POST[$field] != $currentUser[$field]) {
                $stmt = $conn->prepare("
                    INSERT INTO change_requests 
                    (user_email, field_name, old_value, new_value) 
                    VALUES (?, ?, ?, ?)
                ");
                $stmt->execute([
                    $currentUser['email'],
                    $field,
                    $currentUser[$field],
                    $_POST[$field]
                ]);
                $changes_made = true;
            }
        }
        
        if ($changes_made) {
            $_SESSION['success'] = "Your change requests have been submitted for admin approval!";
        } else {
            $_SESSION['error'] = "No changes were made.";
        }
        
        header("Location: dashboard.php");
        exit();
        
    } catch (PDOException $e) {
        error_log("Request error: " . $e->getMessage());
        $_SESSION['error'] = "Failed to submit change request. Please try again.";
        header("Location: dashboard.php");
        exit();
    }
}

// Get unread notifications count
try {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_email = ? AND is_read = FALSE");
    $stmt->execute([$_SESSION['user']['email']]);
    $unread_notifications = $stmt->fetchColumn();
} catch(PDOException $e) {
    $unread_notifications = 0;
}

// Get pending change requests
try {
    $stmt = $conn->prepare("
        SELECT field_name, new_value, request_date 
        FROM change_requests 
        WHERE user_email = ? AND status = 'pending'
        ORDER BY request_date DESC
    ");
    $stmt->execute([$_SESSION['user']['email']]);
    $pending_requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $pending_requests = [];
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
    <style>
        .notification-badge {
            background: #dc3545;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.8em;
            margin-left: 5px;
        }
        .notification-dropdown {
            display: none;
            position: absolute;
            right: 20px;
            top: 60px;
            width: 300px;
            background: white;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }
        .pending-requests {
            background: #f8f9fa;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        .pending-requests h3 {
            margin-top: 0;
        }
    </style>
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
                <li>
                    <a href="#" onclick="toggleNotifications()">
                        <i class="fas fa-bell"></i> Notifications
                        <?php if ($unread_notifications > 0): ?>
                            <span class="notification-badge"><?= $unread_notifications ?></span>
                        <?php endif; ?>
                    </a>
                </li>
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
            
            <!-- Pending Change Requests -->
            <?php if (!empty($pending_requests)): ?>
                <div class="pending-requests">
                    <h3>Your Pending Change Requests</h3>
                    <ul>
                        <?php foreach ($pending_requests as $request): ?>
                            <li>
                                <strong><?= ucfirst($request['field_name']) ?>:</strong> 
                                Change to "<?= htmlspecialchars($request['new_value']) ?>"
                                <small>(requested <?= date('M j, g:i a', strtotime($request['request_date'])) ?>)</small>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <!-- Profile Information -->
            <div class="profile-info">
                <h2>Profile Information</h2>
                
                <?php if (!$currentUser): ?>
                    <p>Your profile information could not be loaded.</p>
                <?php else: ?>
                    <form method="POST" id="profile-form" autocomplete="off">
                        <input type="hidden" name="request_changes" value="1">
                        <table id="profile-table" autocomplete="off">
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
                            
                            <!-- Modified form buttons -->
                            <tr class="editable-row">
                                <td colspan="3" style="text-align: center;">
                                    <button type="submit" class="btn btn-save">
                                        <i class="fas fa-paper-plane"></i> Submit Change Request
                                    </button>
                                    <button type="button" onclick="toggleEditMode(false)" class="btn btn-cancel">
                                        <i class="fas fa-times"></i> Cancel
                                    </button>
                                </td>
                            </tr>
                        </table>
                    </form>
                <?php endif; ?>
            </div>
        </main>

        <!-- Notification Dropdown -->
        <div id="notification-dropdown" class="notification-dropdown">
            <div class="notification-header">
                <h3>Notifications</h3>
                <a href="mark_notifications_read.php" class="mark-read">Mark all as read</a>
            </div>
            <div class="notification-list">
                <?php
                try {
                    $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_email = ? ORDER BY created_at DESC LIMIT 5");
                    $stmt->execute([$_SESSION['user']['email']]);
                    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    if (empty($notifications)): ?>
                        <div class="notification-item">No notifications</div>
                    <?php else: 
                        foreach ($notifications as $notification): ?>
                            <div class="notification-item <?= $notification['is_read'] ? '' : 'unread' ?>">
                                <?= htmlspecialchars($notification['message']) ?>
                                <small><?= date('M j, g:i a', strtotime($notification['created_at'])) ?></small>
                            </div>
                        <?php endforeach;
                    endif;
                } catch(PDOException $e) {
                    echo '<div class="notification-item">Error loading notifications</div>';
                }
                ?>
            </div>
        </div>

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
    
    // Debugging
    console.log("Edit mode toggled:", enable);
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
          function toggleNotifications() {
            const dropdown = document.getElementById('notification-dropdown');
            dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('#notification-dropdown') && !e.target.closest('a[onclick="toggleNotifications()"]')) {
                document.getElementById('notification-dropdown').style.display = 'none';
            }
        });
        
        // Confirm before submitting changes
        document.getElementById('profile-form').addEventListener('submit', function(e) {
            if (!confirm('Are you sure you want to submit these changes for approval?')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>