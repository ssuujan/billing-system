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
    course_id INT NULL,
    student_id VARCHAR(50) NULL UNIQUE COMMENT 'Custom student ID number',
    role VARCHAR(20) NOT NULL DEFAULT 'student',
    approved TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE SET NULL
) ENGINE=InnoDB");

    // Create course_subjects table
    $conn->exec("CREATE TABLE IF NOT EXISTS course_subjects (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        year_or_semester INT NOT NULL,
        subject_name VARCHAR(255) NOT NULL,
        price DECIMAL(10,2) DEFAULT NULL,
        sub_subjects TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    // Create fee_structures and fee_installments tables
    $conn->exec("CREATE TABLE IF NOT EXISTS fee_structures (
        id INT AUTO_INCREMENT PRIMARY KEY,
        course_id INT NOT NULL,
        fee_type ENUM('semester', 'yearly') NOT NULL,
        period INT NOT NULL COMMENT 'Semester number or Year number',
        total_fee DECIMAL(10,2) NOT NULL,
        payment_option ENUM('full', 'installment') NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    $conn->exec("CREATE TABLE IF NOT EXISTS fee_installments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        fee_structure_id INT NOT NULL,
        installment_number INT NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        due_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (fee_structure_id) REFERENCES fee_structures(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

   // Create student_bills table
$conn->exec("CREATE TABLE IF NOT EXISTS student_bills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    fee_structure_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    due_date DATE NOT NULL,
    status ENUM('unpaid', 'pending_verification', 'paid') DEFAULT 'unpaid',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    paid_at TIMESTAMP NULL,
    payment_verified BOOLEAN DEFAULT FALSE,
    payment_proof VARCHAR(255) NULL,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (fee_structure_id) REFERENCES fee_structures(id) ON DELETE CASCADE,
    INDEX (status),
    INDEX (due_date)
) ENGINE=InnoDB");

// Create payment_history table
$conn->exec("CREATE TABLE IF NOT EXISTS payment_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bill_id INT NOT NULL,
    action ENUM('created', 'payment_submitted', 'verified', 'modified') NOT NULL,
    changed_by INT NOT NULL COMMENT 'User ID of who made the change',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bill_id) REFERENCES student_bills(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id),
    INDEX (action),
    INDEX (created_at)
) ENGINE=InnoDB");

    // Create admin user if it doesn't exist
    $stmt = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
    if ($stmt->fetchColumn() == 0) {
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

    // Insert sample courses if none exist
    $stmt = $conn->query("SELECT COUNT(*) FROM courses");
    if ($stmt->fetchColumn() == 0) {
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