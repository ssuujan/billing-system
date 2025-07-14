<!DOCTYPE html>
<html>

<head>
    <title>Patan Multipal Campus</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <!-- <link rel="stylesheet" href="assets/css/style.css"> -->
    <link rel="stylesheet" href="assets/css/admin.css"> 

    
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
              <div class="form-container">
        <!-- Action Buttons Column -->
        <div class="action-buttons">
            <a href="admin.php"><i class="fas fa-user-cog"></i> Admin</a>
            <a href="index.php"><i class="fas fa-user-plus"></i> Register</a>
            <a href="login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
        </div>
        
                <div class="login-form"> 
                    <p class="regtext">Use admin  account</p>


                    <form method="post" action="login_process.php" autocomplete="off">
                      
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" required><br>
                        <label for="password">Password:</label>
                        <input type="password" id="password" name="password" required><br>
                        <button type="submit">Login</button>

                    </form>
</form>
        </div>
    </div>
</div>

</body>

</html>