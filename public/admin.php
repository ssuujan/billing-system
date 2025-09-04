<?php
session_name('ADMIN_SESSION');
session_start();

// If already logged in as admin → redirect to dashboard
if (isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin') {
    header("Location: /project/admin/admin_dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Patan Multiple Campus - Admin Login</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
     <link rel="stylesheet" href="css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        .action-buttons {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin-top: 82px;
            margin-bottom: 19px;
        }

        .password-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .password-wrapper input {
            width: 100%;
            padding-right: 35px;
        }

        .password-wrapper i {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #555;
        }
    </style>
</head>

<body>
    <div class="navbar">
        <nav>
            <div class="logo">
                <a href="index.php"><img src="assets/images/logo.png" alt="Patan Multiple Campus Logo"></a>
            </div>
            <div class="clzname">
                <h1>Patan Multiple Campus</h1>
            </div>
            <ul>
                <li><a href="index.php"><i class="fas fa-home"></i> Home</a></li>
                <li><a href="about.php"><i class="fas fa-info-circle"></i> About Us</a></li>
                <li><a href="courses.php"><i class="fas fa-book"></i> Courses</a></li>
                <li><a href="contact.php"><i class="fas fa-envelope"></i> Contact Us</a></li>
            </ul>
        </nav>
    </div>

    <div class="container-outer">
        <div class="image">
            <img src="assets/images/patan.jpg" alt="Patan Multiple Campus Banner">
        </div>
        <div>
            <div class="container-inner">
                <div class="form-container">
                    <!-- Action Buttons Column -->
                    <div class="action-buttons">
                        <a href="admin.php"><i class="fas fa-user-cog"></i> Admin</a>
                        <a href="index.php"><i class="fas fa-user-plus"></i> Register</a>
                        <a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
                    </div>

                    <div class="login-form">
                        <p class="regtext">Use your admin account</p>

                        <form method="post" action="admin_process.php" autocomplete="off">
                            <label for="email">Email:</label>
                            <input type="email" id="email" name="email" required>

                            <label for="password">Password:</label>
                            <div class="password-wrapper">
                                <input type="password" id="password" name="password" required>
                                <i id="togglePassword" class="fas fa-eye"></i>
                            </div>

                            <button type="submit">Login</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Password Show/Hide Script -->
    <script>
        const togglePassword = document.querySelector('#togglePassword');
        const passwordInput = document.querySelector('#password');

        togglePassword.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    </script>
</body>

</html>
