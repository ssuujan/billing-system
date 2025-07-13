<?php
// Database configuration
$host = 'localhost';
$dbname = 'mybillingproject';
$username = 'root'; // Default XAMPP username
$password = '';     // Default XAMPP password (empty)

try {
    // Create PDO connection
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    
    // Set error mode to exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create users table if it doesn't exist
   
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        address TEXT NOT NULL,
        course VARCHAR(50) NOT NULL,
        role VARCHAR(20) NOT NULL DEFAULT 'student',
        approved TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0=pending, 1=approved',

        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    
    )";
    
    $conn->exec($sql);
    
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>