<?php
// Database configuration
$host = 'localhost';
$dbname = 'mybillingproject';
$username = 'root';
$password = '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create users table
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        address TEXT NOT NULL,
        course VARCHAR(50) NOT NULL DEFAULT 'none',
        role VARCHAR(20) NOT NULL DEFAULT 'student',
        approved TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
    )";
    $conn->exec($sql);
    
    // Create change_requests table
    $sql = "CREATE TABLE IF NOT EXISTS change_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_email VARCHAR(100) NOT NULL,
        field_name VARCHAR(50) NOT NULL,
        old_value TEXT,
        new_value TEXT,
        request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
        action_date TIMESTAMP NULL,
        processed_by VARCHAR(100) NULL,
        FOREIGN KEY (user_email) REFERENCES users(email) ON DELETE CASCADE
    )";
    $conn->exec($sql);
    
    // Create notifications table
    $sql = "CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_email VARCHAR(100) NOT NULL,
        message TEXT NOT NULL,
        is_read BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        related_request_id INT NULL,
        FOREIGN KEY (user_email) REFERENCES users(email) ON DELETE CASCADE,
        FOREIGN KEY (related_request_id) REFERENCES change_requests(id) ON DELETE SET NULL
    )";
    $conn->exec($sql);
    
    // Safe index creation function
    function createIndexIfNotExists($conn, $table, $index, $columns) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM information_schema.statistics 
                               WHERE table_name = ? AND index_name = ?");
        $stmt->execute([$table, $index]);
        if ($stmt->fetchColumn() == 0) {
            $conn->exec("CREATE INDEX $index ON $table($columns)");
        }
    }
    
    // Create indexes safely
    createIndexIfNotExists($conn, 'change_requests', 'idx_change_requests_status', 'status');
    createIndexIfNotExists($conn, 'change_requests', 'idx_change_requests_user', 'user_email');
    createIndexIfNotExists($conn, 'notifications', 'idx_notifications_user', 'user_email');
    createIndexIfNotExists($conn, 'notifications', 'idx_notifications_read', 'is_read');
    
    // Create default admin if needed
    // $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE role = 'admin'");
    // $stmt->execute();
    // if ($stmt->fetchColumn() == 0) {
    //     $hashedPassword = password_hash('admin123', PASSWORD_BCRYPT);
    //     $conn->exec("INSERT INTO users (name, email, password, phone, address, role, approved) VALUES (
    //         'Admin User',
    //         'admin@example.com',
    //         '$hashedPassword',
    //         '1234567890',
    //         'College Address',
    //         'admin',
    //         1
    //     )");
    // }
    
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>