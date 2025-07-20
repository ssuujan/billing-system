<?php
session_name('ADMIN_SESSION');
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../public/admin.php");
    exit();
}

require_once __DIR__ . '/../config/database.php';
$admin = $_SESSION['user'];

// Get all students with name, email, and student_id
$students = db_query("
    SELECT u.name, u.email, u.student_id, c.course_name
    FROM users u
    LEFT JOIN courses c ON u.course_id = c.id
    WHERE u.role = 'student' AND u.approved = 1
")->fetchAll();

// Handle viewing a specific student's payments using name and email
$selectedStudentName = $_GET['student_name'] ?? null;
$selectedStudentEmail = $_GET['student_email'] ?? null;
$student = null;
$studentPayments = [];
$paymentStatus = null;
$feeStructures = [];

if ($selectedStudentName && $selectedStudentEmail) {
    // Get student details by name and email (now including student_id)
    $student = db_query("
        SELECT u.*, c.course_name, c.id as course_id 
        FROM users u
        LEFT JOIN courses c ON u.course_id = c.id
        WHERE u.name = ? AND u.email = ?
    ", [$selectedStudentName, $selectedStudentEmail])->fetch();

    if ($student && $student['course_id']) {
        // Get fee structures for the student's course
        $feeStructures = db_query("
            SELECT fs.*
            FROM fee_structures fs
            WHERE fs.course_id = ?
            ORDER BY fs.fee_type, fs.period
        ", [$student['course_id']])->fetchAll();

        // Get all bills for the student (now showing student_id)
        $studentPayments = db_query("
            SELECT sb.*, fs.fee_type, fs.period, fs.payment_option, c.course_name, u.student_id
            FROM student_bills sb
            JOIN fee_structures fs ON sb.fee_structure_id = fs.id
            JOIN courses c ON fs.course_id = c.id
            JOIN users u ON sb.student_id = u.id
            WHERE u.name = ? AND u.email = ?
            ORDER BY sb.due_date ASC
        ", [$selectedStudentName, $selectedStudentEmail])->fetchAll();

        // Calculate payment status
        $totalBilled = db_query("
            SELECT SUM(amount) as total 
            FROM student_bills sb
            JOIN users u ON sb.student_id = u.id
            WHERE u.name = ? AND u.email = ?
        ", [$selectedStudentName, $selectedStudentEmail])->fetch()['total'] ?? 0;

        $totalPaid = db_query("
            SELECT SUM(amount) as total 
            FROM student_bills sb
            JOIN users u ON sb.student_id = u.id
            WHERE u.name = ? AND u.email = ? AND sb.status = 'paid'
        ", [$selectedStudentName, $selectedStudentEmail])->fetch()['total'] ?? 0;

        $paymentStatus = [
            'total_billed' => $totalBilled,
            'total_paid' => $totalPaid,
            'balance' => $totalBilled - $totalPaid,
            'percentage' => $totalBilled > 0 ? round(($totalPaid / $totalBilled) * 100) : 0,
            'fully_paid' => ($totalBilled - $totalPaid) <= 0
        ];
    }
}

// Handle bill generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_bill'])) {
    try {
        $conn->beginTransaction();
        
        // Get student by name and email
        $student = db_query("
            SELECT id FROM users 
            WHERE name = ? AND email = ?
        ", [$_POST['student_name'], $_POST['student_email']])->fetch();
        
        if (!$student) {
            throw new Exception("Student not found");
        }
        
        // Get fee structure details
        $feeStructure = db_query("
            SELECT * FROM fee_structures 
            WHERE id = ?
        ", [$_POST['fee_structure_id']])->fetch();
        
        if (!$feeStructure) {
            throw new Exception("Fee structure not found");
        }
        
        // Check if bill already exists
        $existingBill = db_query("
            SELECT id FROM student_bills 
            WHERE student_id = ? AND fee_structure_id = ?
            LIMIT 1
        ", [$student['id'], $_POST['fee_structure_id']])->fetch();
        
        if ($existingBill) {
            throw new Exception("A bill for this fee structure already exists for this student");
        }
        
        // Create bill record
        db_query("
            INSERT INTO student_bills 
            (student_id, fee_structure_id, amount, due_date, status) 
            VALUES (?, ?, ?, ?, 'unpaid')
        ", [
            $student['id'],
            $_POST['fee_structure_id'],
            $feeStructure['total_fee'],
            $_POST['due_date']
        ]);
        
        // If installments, create installment bills
        if ($feeStructure['payment_option'] === 'installment') {
            $installments = db_query("
                SELECT * FROM fee_installments 
                WHERE fee_structure_id = ?
                ORDER BY installment_number
            ", [$feeStructure['id']])->fetchAll();
            
            foreach ($installments as $installment) {
                db_query("
                    INSERT INTO student_bills 
                    (student_id, fee_structure_id, amount, due_date, status, is_installment) 
                    VALUES (?, ?, ?, ?, 'unpaid', 1)
                ", [
                    $student['id'],
                    $_POST['fee_structure_id'],
                    $installment['amount'],
                    $installment['due_date']
                ]);
            }
        }
        
        $conn->commit();
        $_SESSION['success'] = "Bill generated successfully!";
        header("Location: student_bills.php?student_name=" . urlencode($_POST['student_name']) . 
              "&student_email=" . urlencode($_POST['student_email']) . "&tab=payments");
        exit();
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error generating bill: " . $e->getMessage();
        header("Location: student_bills.php?student_name=" . urlencode($_POST['student_name']) . 
              "&student_email=" . urlencode($_POST['student_email']) . "&tab=generate-bill");
        exit();
    }
}

// Handle bill payment status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_paid'])) {
    try {
        db_query("
            UPDATE student_bills 
            SET status = 'paid', paid_at = NOW() 
            WHERE id = ?
        ", [$_POST['bill_id']]);
        
        $_SESSION['success'] = "Bill marked as paid successfully!";
        header("Location: student_bills.php?student_name=" . urlencode($_POST['student_name']) . 
              "&student_email=" . urlencode($_POST['student_email']) . "&tab=payments");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error updating bill status: " . $e->getMessage();
        header("Location: student_bills.php?student_name=" . urlencode($_POST['student_name']) . 
              "&student_email=" . urlencode($_POST['student_email']));
        exit();
    }
}

// Get unpaid bills count for the badge
$unpaidCount = db_query("
    SELECT COUNT(*) 
    FROM student_bills 
    WHERE status = 'unpaid'
")->fetchColumn();

// Get current tab from URL
$currentTab = $_GET['tab'] ?? 'payments';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Bills - Patan Multiple Campus</title>
    <link href="./css/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .payment-status-pending { background-color: #fef2f2; }
        .payment-status-paid { background-color: #f0fdf4; }
        .payment-status-pending_verification { background-color: #eff6ff; }
        .progress-bar {
            height: 1.5rem;
            background-color: #e5e7eb;
            border-radius: 0.375rem;
            overflow: hidden;
        }
        .progress-fill {
            height: 100%;
            background-color: #10b981;
            transition: width 0.3s ease;
        }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .proof-thumbnail {
            max-width: 100px;
            max-height: 100px;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.9);
        }
        .modal-content {
            margin: auto;
            display: block;
            max-width: 90%;
            max-height: 90%;
            margin-top: 50px;
        }
        .close {
            position: absolute;
            top: 15px;
            right: 35px;
            color: #f1f1f1;
            font-size: 40px;
            font-weight: bold;
            transition: 0.3s;
            cursor: pointer;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen flex flex-col">
        <!-- Header -->
        <header class="bg-blue-800 text-white shadow-lg">
            <div class="container mx-auto px-4 py-4 flex items-center justify-between">
                <div class="flex items-center space-x-4">
                    <img src="../public/assets/images/logo.png" alt="Logo" class="h-12">
                    <div>
                        <h1 class="text-xl font-bold">Patan Multiple Campus - Admin Panel</h1>
                        <p class="text-blue-200">Welcome, <?= htmlspecialchars($admin['name']) ?></p>
                    </div>
                </div>
                <nav>
                    <ul class="flex space-x-6">
                        <li><a href="admin_dashboard.php" class="hover:text-blue-300"><i class="fas fa-tachometer-alt mr-2"></i>Dashboard</a></li>
                        <li><a href="student_bills.php" class="font-bold text-blue-300"><i class="fas fa-money-bill-wave mr-2"></i>Student Bills</a></li>
                        <li><a href="manage_students.php" class="hover:text-blue-300"><i class="fas fa-users mr-2"></i>Students</a></li>
                        <li><a href="logout.php" class="hover:text-blue-300"><i class="fas fa-sign-out-alt mr-2"></i>Logout</a></li>
                    </ul>
                </nav>
            </div>
        </header>

        <!-- Main Content -->
        <main class="flex-grow container mx-auto px-4 py-8">
            <div class="max-w-7xl mx-auto">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">Student Bills Management</h2>
                
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
                        <?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                        <?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Student Selection Form -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                    <form method="GET" action="student_bills.php" class="flex items-end gap-4">
                        <div class="flex-grow">
                            <label class="block mb-1 font-medium">Select Student</label>
                            <select name="student_email" required class="w-full p-2 border rounded" onchange="updateStudentSelection(this)">
                                <option value="">-- Select a student --</option>
                                <?php foreach ($students as $s): ?>
                                    <option value="<?= htmlspecialchars($s['email']) ?>" 
                                        data-name="<?= htmlspecialchars($s['name']) ?>"
                                        <?= ($selectedStudentEmail == $s['email'] && $selectedStudentName == $s['name']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($s['name']) ?> (ID: <?= htmlspecialchars($s['student_id']) ?>)
                                        - <?= htmlspecialchars($s['course_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="student_name" id="student_name" value="<?= htmlspecialchars($selectedStudentName) ?>">
                        </div>
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                            View Bills
                        </button>
                    </form>
                </div>
                
                <?php if ($selectedStudentName && $selectedStudentEmail && $student): ?>
                    <!-- Student Payment Summary -->
                    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                        <div class="flex justify-between items-start mb-6">
                            <div>
                                <h3 class="text-xl font-semibold"><?= htmlspecialchars($student['name']) ?></h3>
                                <p class="text-gray-600">Student ID: <?= htmlspecialchars($student['student_id']) ?></p>
                                <p class="text-gray-600"><?= htmlspecialchars($student['email']) ?></p>
                                <p class="text-gray-600"><?= htmlspecialchars($student['course_name']) ?></p>
                            </div>
                            
                            <?php if ($paymentStatus): ?>
                                <div class="text-right">
                                    <div class="text-lg font-semibold mb-1">
                                        Payment Status: 
                                        <span class="<?= $paymentStatus['fully_paid'] ? 'text-green-600' : 'text-red-600' ?>">
                                            <?= $paymentStatus['fully_paid'] ? 'Fully Paid' : 'Pending' ?>
                                        </span>
                                    </div>
                                    <div class="text-sm text-gray-600">
                                        Paid: NPR <?= number_format($paymentStatus['total_paid'], 2) ?> 
                                        of NPR <?= number_format($paymentStatus['total_billed'], 2) ?>
                                    </div>
                                    <div class="progress-bar w-64 mt-2">
                                        <div class="progress-fill" style="width: <?= $paymentStatus['percentage'] ?>%"></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Tabs for Student View -->
                        <div class="mb-6">
                            <nav class="flex space-x-4 border-b">
                                <button type="button" onclick="switchTab('payments')" 
                                    class="tab-button <?= $currentTab === 'payments' ? 'active border-blue-500 text-blue-600' : 'border-transparent text-gray-500' ?> py-2 px-4 border-b-2 font-medium text-sm">
                                    Payment Records
                                </button>
                                <button type="button" onclick="switchTab('generate-bill')" 
                                    class="tab-button <?= $currentTab === 'generate-bill' ? 'active border-blue-500 text-blue-600' : 'border-transparent text-gray-500' ?> py-2 px-4 border-b-2 font-medium text-sm">
                                    Generate New Bill
                                </button>
                            </nav>
                        </div>
                        
                        <!-- Payments Tab -->
                        <div id="payments" class="tab-content <?= $currentTab === 'payments' ? 'active' : '' ?>">
                            <?php if (empty($studentPayments)): ?>
                                <p class="text-gray-600">No payment records found for this student.</p>
                            <?php else: ?>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full bg-white">
                                        <thead>
                                            <tr class="bg-gray-200">
                                                <th class="py-2 px-4 text-left">Description</th>
                                                <th class="py-2 px-4 text-left">Period</th>
                                                <th class="py-2 px-4 text-right">Amount (NPR)</th>
                                                <th class="py-2 px-4 text-left">Due Date</th>
                                                <th class="py-2 px-4 text-left">Status</th>
                                                <th class="py-2 px-4 text-left">Proof</th>
                                                <th class="py-2 px-4 text-left">Paid Date</th>
                                                <th class="py-2 px-4 text-left">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($studentPayments as $payment): ?>
                                                <?php
                                                $statusClass = '';
                                                if ($payment['status'] === 'paid') {
                                                    $statusClass = 'payment-status-paid';
                                                } elseif ($payment['status'] === 'pending_verification') {
                                                    $statusClass = 'payment-status-pending_verification';
                                                } else {
                                                    $statusClass = 'payment-status-pending';
                                                }
                                                ?>
                                                <tr class="border-b <?= $statusClass ?>">
                                                    <td class="py-2 px-4">
                                                        <?= htmlspecialchars($payment['course_name']) ?> - 
                                                        <?= $payment['fee_type'] === 'semester' ? 'Semester ' : 'Year ' ?>
                                                        <?= $payment['period'] ?>
                                                        <?= $payment['payment_option'] === 'installment' ? '(Installment)' : '' ?>
                                                    </td>
                                                    <td class="py-2 px-4">
                                                        <?= $payment['fee_type'] === 'semester' ? 'Semester ' : 'Year ' ?>
                                                        <?= $payment['period'] ?>
                                                    </td>
                                                    <td class="py-2 px-4 text-right"><?= number_format($payment['amount'], 2) ?></td>
                                                    <td class="py-2 px-4"><?= date('M d, Y', strtotime($payment['due_date'])) ?></td>
                                                    <td class="py-2 px-4">
                                                        <span class="<?= 
                                                            $payment['status'] === 'paid' ? 'text-green-600' : 
                                                            ($payment['status'] === 'pending_verification' ? 'text-blue-600' : 'text-red-600') 
                                                        ?>">
                                                            <?= ucfirst(str_replace('_', ' ', $payment['status'])) ?>
                                                        </span>
                                                    </td>
                                                    <td class="py-2 px-4">
                                                        <?php if ($payment['payment_proof']): ?>
                                                            <?php
                                                            $fileExt = pathinfo($payment['payment_proof'], PATHINFO_EXTENSION);
                                                            $isImage = in_array(strtolower($fileExt), ['jpg', 'jpeg', 'png', 'gif']);
                                                            ?>
                                                            <?php if ($isImage): ?>
                                                                <img src="../uploads/payment_proofs/<?= htmlspecialchars($payment['payment_proof']) ?>" 
                                                                     alt="Payment Proof" class="proof-thumbnail" 
                                                                     onclick="openModal(this.src)">
                                                            <?php else: ?>
                                                                <a href="../uploads/payment_proofs/<?= htmlspecialchars($payment['payment_proof']) ?>" 
                                                                   target="_blank" class="text-blue-600 hover:underline">
                                                                    View Proof (<?= strtoupper($fileExt) ?>)
                                                                </a>
                                                            <?php endif; ?>
                                                        <?php else: ?>
                                                            --
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="py-2 px-4">
                                                        <?= $payment['paid_at'] ? date('M d, Y', strtotime($payment['paid_at'])) : '--' ?>
                                                    </td>
                                                    <td class="py-2 px-4">
                                                        <?php if ($payment['status'] === 'pending_verification'): ?>
                                                            <form method="POST" action="student_bills.php" class="inline">
                                                                <input type="hidden" name="bill_id" value="<?= $payment['id'] ?>">
                                                                <input type="hidden" name="student_name" value="<?= htmlspecialchars($selectedStudentName) ?>">
                                                                <input type="hidden" name="student_email" value="<?= htmlspecialchars($selectedStudentEmail) ?>">
                                                                <input type="hidden" name="tab" value="payments">
                                                                <button type="submit" name="mark_paid" class="bg-green-600 text-white px-3 py-1 rounded hover:bg-green-700">
                                                                    <i class="fas fa-check mr-1"></i> Verify Payment
                                                                </button>
                                                            </form>
                                                            <button onclick="rejectPayment(<?= $payment['id'] ?>)" class="bg-red-600 text-white px-3 py-1 rounded hover:bg-red-700 ml-2">
                                                                <i class="fas fa-times mr-1"></i> Reject
                                                            </button>
                                                        <?php elseif ($payment['status'] === 'paid'): ?>
                                                            <a href="generate_receipt.php?bill_id=<?= $payment['id'] ?>" 
                                                               class="text-green-600 hover:text-green-800">
                                                                <i class="fas fa-download mr-1"></i> Receipt
                                                            </a>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Generate Bill Tab -->
                        <div id="generate-bill" class="tab-content <?= $currentTab === 'generate-bill' ? 'active' : '' ?>">
                            <?php if (empty($feeStructures)): ?>
                                <p class="text-gray-600">No fee structures available for this student's course.</p>
                            <?php else: ?>
                                <form method="POST" action="student_bills.php" class="space-y-4">
                                    <input type="hidden" name="student_name" value="<?= htmlspecialchars($selectedStudentName) ?>">
                                    <input type="hidden" name="student_email" value="<?= htmlspecialchars($selectedStudentEmail) ?>">
                                    <input type="hidden" name="tab" value="generate-bill">
                                    
                                    <div class="grid md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block mb-1">Fee Structure</label>
                                            <select name="fee_structure_id" required class="w-full p-2 border rounded">
                                                <option value="">-- Select Fee Structure --</option>
                                                <?php foreach ($feeStructures as $fee): ?>
                                                    <option value="<?= $fee['id'] ?>">
                                                        <?= ucfirst($fee['fee_type']) ?> - 
                                                        <?= $fee['fee_type'] === 'semester' ? 'Semester ' : 'Year ' ?><?= $fee['period'] ?>
                                                        (NPR <?= number_format($fee['total_fee'], 2) ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        
                                        <div>
                                            <label class="block mb-1">Due Date</label>
                                            <input type="date" name="due_date" required class="w-full p-2 border rounded" 
                                                   min="<?= date('Y-m-d') ?>">
                                        </div>
                                    </div>
                                    
                                    <button type="submit" name="generate_bill" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                                        <i class="fas fa-file-invoice mr-2"></i> Generate Bill
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Unpaid Bills Section -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-semibold">Pending Verification (<?= $unpaidCount ?>)</h3>
                    </div>
                    
                    <?php 
                    $unpaidBills = db_query("
                        SELECT sb.*, u.name as student_name, u.email, u.student_id,
                               fs.fee_type, fs.period, c.course_name
                        FROM student_bills sb
                        JOIN users u ON sb.student_id = u.id
                        JOIN fee_structures fs ON sb.fee_structure_id = fs.id
                        JOIN courses c ON fs.course_id = c.id
                        WHERE sb.status = 'pending_verification'
                        ORDER BY sb.due_date ASC
                        LIMIT 10
                    ")->fetchAll();
                    ?>
                    
                    <?php if (empty($unpaidBills)): ?>
                        <p class="text-gray-600">No bills pending verification.</p>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($unpaidBills as $bill): ?>
                                <div class="payment-status-pending_verification p-4 rounded-md border">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <h4 class="font-medium">
                                                <?= htmlspecialchars($bill['student_name']) ?> (ID: <?= htmlspecialchars($bill['student_id']) ?>)
                                            </h4>
                                            <p class="text-gray-600">
                                                <?= $bill['course_name'] ?> - 
                                                <?= $bill['fee_type'] === 'semester' ? 'Semester ' : 'Year ' ?><?= $bill['period'] ?>
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-blue-600 font-semibold">
                                                NPR <?= number_format($bill['amount'], 2) ?>
                                            </p>
                                            <p class="text-sm text-gray-500">
                                                Due: <?= date('M d, Y', strtotime($bill['due_date'])) ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <?php if ($bill['payment_proof']): ?>
                                            <?php
                                            $fileExt = pathinfo($bill['payment_proof'], PATHINFO_EXTENSION);
                                            $isImage = in_array(strtolower($fileExt), ['jpg', 'jpeg', 'png', 'gif']);
                                            ?>
                                            <div class="mb-2">
                                                <p class="font-medium">Payment Proof:</p>
                                                <?php if ($isImage): ?>
                                                    <img src="../uploads/payment_proofs/<?= htmlspecialchars($bill['payment_proof']) ?>" 
                                                         alt="Payment Proof" class="proof-thumbnail" 
                                                         onclick="openModal(this.src)">
                                                <?php else: ?>
                                                    <a href="../uploads/payment_proofs/<?= htmlspecialchars($bill['payment_proof']) ?>" 
                                                       target="_blank" class="text-blue-600 hover:underline">
                                                        View Proof (<?= strtoupper($fileExt) ?>)
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                        <div class="flex justify-end space-x-2">
                                            <form method="POST" action="student_bills.php" class="inline">
                                                <input type="hidden" name="bill_id" value="<?= $bill['id'] ?>">
                                                <input type="hidden" name="student_name" value="<?= htmlspecialchars($bill['student_name']) ?>">
                                                <input type="hidden" name="student_email" value="<?= htmlspecialchars($bill['email']) ?>">
                                                <input type="hidden" name="tab" value="payments">
                                                <button type="submit" name="mark_paid" class="bg-green-600 text-white px-3 py-1 rounded hover:bg-green-700">
                                                    <i class="fas fa-check mr-1"></i> Verify Payment
                                                </button>
                                            </form>
                                            <button onclick="rejectPayment(<?= $bill['id'] ?>)" class="bg-red-600 text-white px-3 py-1 rounded hover:bg-red-700">
                                                <i class="fas fa-times mr-1"></i> Reject
                                            </button>
                                            <a href="student_bills.php?student_name=<?= urlencode($bill['student_name']) ?>&student_email=<?= urlencode($bill['email']) ?>&tab=payments" class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700">
                                                <i class="fas fa-user mr-1"></i> View Student
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if ($unpaidCount > 10): ?>
                            <div class="mt-4 text-center">
                                <a href="unpaid_bills.php" class="text-blue-600 hover:text-blue-800">
                                    View all <?= $unpaidCount ?> pending bills →
                                </a>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="bg-gray-800 text-white py-4 mt-8">
            <div class="container mx-auto text-center">
                <p>&copy; <?= date('Y') ?> Patan Multiple Campus. All rights reserved.</p>
            </div>
        </footer>
    </div>

    <!-- Image Modal -->
    <div id="imageModal" class="modal">
        <span class="close" onclick="closeModal()">&times;</span>
        <img class="modal-content" id="modalImage">
    </div>

    <!-- Reject Payment Modal -->
    <div id="rejectModal" class="fixed z-50 inset-0 overflow-y-auto hidden">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>
            
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form method="POST" action="reject_payment.php" id="rejectForm">
                    <input type="hidden" name="bill_id" id="reject_bill_id">
                    <input type="hidden" name="student_name" value="<?= htmlspecialchars($selectedStudentName ?? '') ?>">
                    <input type="hidden" name="student_email" value="<?= htmlspecialchars($selectedStudentEmail ?? '') ?>">
                    <input type="hidden" name="tab" value="payments">
                    
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Reject Payment</h3>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="reject_reason">
                                Reason for rejection
                            </label>
                            <textarea name="reject_reason" id="reject_reason" required
                                      class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"
                                      rows="3"></textarea>
                        </div>
                    </div>
                    
                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" 
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Reject Payment
                        </button>
                        <button type="button" onclick="closeRejectModal()" 
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Update both student name and email when selection changes
        function updateStudentSelection(select) {
            const selectedOption = select.options[select.selectedIndex];
            document.getElementById('student_name').value = selectedOption.dataset.name;
        }

        // Tab switching with URL update
        function switchTab(tabId) {
            // Update URL
            const url = new URL(window.location);
            url.searchParams.set('tab', tabId);
            window.history.pushState({}, '', url);
            
            // Update UI
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.getElementById(tabId).classList.add('active');
            
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active', 'border-blue-500', 'text-blue-600');
                btn.classList.add('border-transparent', 'text-gray-500');
            });
            
            event.target.classList.add('active', 'border-blue-500', 'text-blue-600');
            event.target.classList.remove('border-transparent', 'text-gray-500');
        }

        // Image modal functions
        function openModal(src) {
            document.getElementById('modalImage').src = src;
            document.getElementById('imageModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('imageModal').style.display = 'none';
        }

        // Reject payment functions
        function rejectPayment(billId) {
            document.getElementById('reject_bill_id').value = billId;
            document.getElementById('rejectModal').classList.remove('hidden');
        }
        
        function closeRejectModal() {
            document.getElementById('rejectModal').classList.add('hidden');
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('imageModal');
            if (event.target == modal) {
                closeModal();
            }
            
            const rejectModal = document.getElementById('rejectModal');
            if (event.target.classList.contains('fixed') && event.target.classList.contains('inset-0')) {
                closeRejectModal();
            }
        }

        // Set initial tab from URL
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            if (tabParam) {
                switchTab(tabParam);
            }
        });

        // Confirm payment verification
        function confirmPayment(form) {
            if (confirm('Are you sure you want to verify this payment?')) {
                form.submit();
            }
            return false;
        }
    </script>
</body>
</html>