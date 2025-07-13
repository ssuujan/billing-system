<?php
session_start();
if (isset($_SESSION['alert'])) {
    echo "<script>alert('" . addslashes($_SESSION['alert']) . "');</script>";
    unset($_SESSION['alert']);
}
?>
<!DOCTYPE html>
<html>

<head>
    <title>Patan Multipal Campus</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    
</head>

<body>   
      <script src="assets/javascript/valid.js"></script>   
 
    <div class="navbar">
    <nav>
        <div class="logo">
            <a href="index.php"><img src="assets/images/logo.png" alt="Patan Multipal Campus Logo"></a>
        </div>
        <div class="clzname">
            <h1>Patan Multiple Campus</h1>
        </div>
        <ul>
            <li><a href="index.html"><i class="fas fa-home"></i> Home</a></li>
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
            <p class="regtext">Fill the regitration form if you are new user</p>
              <div class="form-container">
        <!-- Action Buttons Column -->
        <div class="action-buttons">
            <a href="admin.php"><i class="fas fa-user-cog"></i> Admin</a>
            <a href="index.php"><i class="fas fa-user-plus"></i> Register</a>
            <a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
        </div>

          <form method="post" action="register.php" onsubmit="return validateForm()" autocomplete="off">
    <!-- Full Name -->
    <label for="name">Full Name:</label>
    <input type="text" id="name" name="name" autocomplete="name" required>
    
    <!-- Email -->
    <label for="email">Email:</label>
    <input type="email" id="email" name="email" autocomplete="email" required>
    
    <!-- Password -->
    <label for="password">Password:</label>
    <input type="password" id="password" name="password" autocomplete="new-password" required>
    
    <!-- Confirm Password -->
    <label for="confirm_password">Confirm Password:</label>
    <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" required>
    
    <!-- Phone -->
    <label for="phone">Phone Number:</label>
    <input type="tel" id="phone" name="phone" autocomplete="tel" required>
    
    <!-- Address -->
    <label for="address">Address:</label>
    <input type="text" id="address" name="address" autocomplete="street-address" required>
    
    <!-- Course Selection -->
    <label for="course">Select Course:</label>
    <select id="course" name="course" required>
        <option value="">-- Select a course --</option>
        <option value="BIT">BIT</option>
        <option value="BscIT">BscIT</option>
        <option value="BBS">BBS</option>
        <option value="BCA">BCA</option>
    </select>
    
    <button type="submit">Register</button>
</form>
        </div>
    </div>
</div>

</body>

</html>