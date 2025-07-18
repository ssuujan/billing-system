<?php
// Database configuration
$host = 'localhost';
$dbname = 'mybillingproject';
$username = 'root';
$password = '';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Create courses table
    $conn->exec("CREATE TABLE IF NOT EXISTS courses (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_name VARCHAR(100) NOT NULL UNIQUE,
        duration_type ENUM('4 years', '8 semesters') NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    // Create users table
    $conn->exec("CREATE TABLE IF NOT EXISTS users (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        phone VARCHAR(20) NOT NULL,
        address TEXT NOT NULL,
        course_id INT NULL COMMENT 'References courses table',
        role VARCHAR(20) NOT NULL DEFAULT 'student',
        approved TINYINT(1) NOT NULL DEFAULT 0 COMMENT '0=pending, 1=approved',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    // Create course_subjects table with price and sub_subjects
    $conn->exec("CREATE TABLE IF NOT EXISTS course_subjects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        year_or_semester VARCHAR(50) NOT NULL,
        subject_name VARCHAR(255) NOT NULL,
        price DECIMAL(10,2) DEFAULT NULL,
        sub_subjects TEXT DEFAULT NULL COMMENT 'Comma-separated sub-topics',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    // Ensure foreign key exists
    $fkExists = $conn->query("SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
                            WHERE CONSTRAINT_SCHEMA = '$dbname' 
                            AND TABLE_NAME = 'users' 
                            AND CONSTRAINT_NAME = 'fk_user_course'")->fetchColumn();

    if (!$fkExists) {
        $conn->exec("SET FOREIGN_KEY_CHECKS = 0");
        $conn->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS course_id INT NULL");
        $conn->exec("ALTER TABLE users ADD CONSTRAINT fk_user_course 
                    FOREIGN KEY (course_id) REFERENCES courses(id) 
                    ON DELETE SET NULL ON UPDATE CASCADE");
        $conn->exec("SET FOREIGN_KEY_CHECKS = 1");
    }

    // Create default admin
    $adminCount = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
    if ($adminCount == 0) {
        $hashedPassword = password_hash('admin123', PASSWORD_BCRYPT);
        $conn->exec("INSERT INTO users (name, email, password, phone, address, role, approved) VALUES (
            'Admin User',
            'admin@example.com',
            '$hashedPassword',
            '1234567890',
            'College Address',
            'admin',
            1
        )");
    }

    // Insert sample courses
    $coursesCount = $conn->query("SELECT COUNT(*) FROM courses")->fetchColumn();
    if ($coursesCount == 0) {
        $sampleCourses = [
            ['Computer Science', '4 years', 'Bachelor of Science in Computer Science'],
            ['Information Technology', '8 semesters', 'Bachelor of Information Technology']
        ];

        $stmt = $conn->prepare("INSERT INTO courses (course_name, duration_type, description) VALUES (?, ?, ?)");
        foreach ($sampleCourses as $course) {
            $stmt->execute($course);
        }
    }

} catch(PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Helper function
function db_query($sql, $params = []) {
    global $conn;
    try {
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch(PDOException $e) {
        error_log("Query error: " . $e->getMessage());
        throw $e;
    }
}

function table_exists($tableName) {
    global $conn;
    $stmt = $conn->prepare("SELECT COUNT(*) FROM information_schema.tables 
                           WHERE table_schema = ? AND table_name = ?");
    $stmt->execute([$GLOBALS['dbname'], $tableName]);
    return $stmt->fetchColumn() > 0;
}
?>
