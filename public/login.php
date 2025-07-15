<?php

session_start();
// Display success message if it exists
if (isset($_SESSION['alert_success'])) {
    echo '<script>alert("' . htmlspecialchars($_SESSION['alert_success']) . '");</script>';
    unset($_SESSION['alert_success']);
}

// Display error message if it exists
if (isset($_SESSION['alert'])) {
    echo '<script>alert("' . htmlspecialchars($_SESSION['alert']) . '");</script>';
    unset($_SESSION['alert']);
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>Patan Multiple Campus</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
    </style>
</head>

<body>
    <?php if (isset($_SESSION['login_error'])): ?>
        <div class="alert alert-error">
            <?= htmlspecialchars($_SESSION['login_error']); ?>
            <?php unset($_SESSION['login_error']); ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['login_success'])): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($_SESSION['login_success']); ?>
            <?php unset($_SESSION['login_success']); ?>
        </div>
    <?php endif; ?>

    <div class="navbar">
        <nav>
            <div class="logo">
                <a href="index.html"><img src="assets/images/logo.png" alt="Patan Multipal Campus Logo"></a>
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

        <div class="container-inner">
            <div class="form-container">
                <!-- Action Buttons Column -->
                <div class="action-buttons">
                    <a href="admin.php"><i class="fas fa-user-cog"></i> Admin</a>
                    <a href="index.php"><i class="fas fa-user-plus"></i> Register</a>
                    <a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
                </div>



                <div class="login-form"> 
                    <p class="regtext">Login to your account</p>


                    <form method="post" action="login_process.php" autocomplete="off">
                      
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" required><br>
                        <label for="password">Password:</label>
                        <input type="password" id="password" name="password" required><br>
                        <button type="submit">Login</button>

                    </form>
            </div>





        </div>
    </div>

</body>

</html>