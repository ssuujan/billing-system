
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../public/assets/css/dashboard.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <title>Dashboard</title>
    </head>
<body>
    <div class="container">
        <header class="header-text">
            <div class="logo">
                <img src="../public/assets/images/logo.png" alt="Patan Multiple Campus Logo">
            </div>
            <div class="header-text">
                <h1>Patan Multiple Campus</h1>
                <h1>Welcome, <?= htmlspecialchars($user['name']) ?>!</h1>
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


        </main>
         <footer>
            <p>&copy; 2023 Patan Multiple Campus</p>
        </footer>
    </div>
    </body>
    </html>
