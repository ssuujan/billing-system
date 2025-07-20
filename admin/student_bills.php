<?php
session_name('ADMIN_SESSION');
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../public/admin.php");
    exit();
}

require_once __DIR__ . '/../config/database.php';
$admin = $_SESSION['user'];

$students = db_query("
        SELECT u.id, u.name, u.email, c.course_name, c.id as course_id
        FROM users u
        LEFT JOIN courses c ON u.course_id = c.id
        WHERE u.role = 'student' AND u.approved = 1
    ")->fetchAll();

// Handle viewing a specific student's payments using both ID and email
$selectedStudentId = $_GET['student_id'] ?? null;
$selectedStudentEmail = $_GET['student_email'] ?? null;
$student = null;
$studentPayments = [];
$paymentStatus = null;
$feeStructures = [];

if ($selectedStudentId && $selectedStudentEmail) {
    // Get student details by both ID and email for precise identification
    $student = db_query("
            SELECT u.*, c.course_name, c.id as course_id 
            FROM users u
            LEFT JOIN courses c ON u.course_id = c.id
            WHERE u.id = ? AND u.email = ?
        ", [$selectedStudentId, $selectedStudentEmail])->fetch();

    if ($student) {
        // Get fee structures for the student's course
        $feeStructures = db_query("
                SELECT fs.*, 
                    (SELECT COUNT(*) FROM student_bills 
                        WHERE student_id = ? AND fee_structure_id = fs.id) as bill_count
                FROM fee_structures fs
                WHERE fs.course_id = ?
                ORDER BY fs.fee_type, fs.period
            ", [$student['id'], $student['course_id']])->fetchAll();

        // Get all bills for the student
        $studentPayments = db_query("
                SELECT sb.*, fs.fee_type, fs.period, fs.payment_option, c.course_name,
                    (SELECT SUM(amount) FROM student_bills WHERE student_id = ? AND status = 'paid') as total_paid,
                    (SELECT SUM(amount) FROM student_bills WHERE student_id = ?) as total_billed
                FROM student_bills sb
                JOIN fee_structures fs ON sb.fee_structure_id = fs.id
                JOIN courses c ON fs.course_id = c.id
                WHERE sb.student_id = ?
                ORDER BY sb.due_date ASC
            ", [$student['id'], $student['id'], $student['id']])->fetchAll();

        // Calculate payment status
        if (!empty($studentPayments)) {
            $totalBilled = $studentPayments[0]['total_billed'];
            $totalPaid = $studentPayments[0]['total_paid'];
            $paymentStatus = [
                'total_billed' => $totalBilled,
                'total_paid' => $totalPaid,
                'balance' => $totalBilled - $totalPaid,
                'percentage' => $totalBilled > 0 ? round(($totalPaid / $totalBilled) * 100) : 0,
                'fully_paid' => ($totalBilled - $totalPaid) <= 0
            ];
        }
    }
}

// Handle bill generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_bill'])) {
    try {
        $conn->beginTransaction();

        // Get student by email
        $student = db_query("SELECT id FROM users WHERE email = ?", [$_POST['student_email']])->fetch();
        if (!$student) {
            throw new Exception("Student not found");
        }

        // Get fee structure details
        $feeStructure = db_query("SELECT * FROM fee_structures WHERE id = ?", [$_POST['fee_structure_id']])->fetch();
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
        db_query("INSERT INTO student_bills 
                (student_id, fee_structure_id, amount, due_date, status) 
                VALUES (?, ?, ?, ?, 'unpaid')", [
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
                db_query("INSERT INTO student_bills 
                        (student_id, fee_structure_id, amount, due_date, status, is_installment) 
                        VALUES (?, ?, ?, ?, 'unpaid', 1)", [
                    $student['id'],
                    $_POST['fee_structure_id'],
                    $installment['amount'],
                    $installment['due_date']
                ]);
            }
        }

        $conn->commit();
        $_SESSION['success'] = "Bill generated successfully!";
        header("Location: student_bills.php?student_email=" . urlencode($_POST['student_email']) . "&tab=payments");
        exit();
    } catch (Exception $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error generating bill: " . $e->getMessage();
        header("Location: student_bills.php?student_email=" . urlencode($_POST['student_email']) . "&tab=generate-bill");
        exit();
    }
}

// Handle bill payment status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_paid'])) {
    try {
        db_query("UPDATE student_bills SET status = 'paid', paid_at = NOW() WHERE id = ?", [$_POST['bill_id']]);
        $_SESSION['success'] = "Bill marked as paid successfully!";
        header("Location: student_bills.php?student_email=" . urlencode($_POST['student_email']) . "&tab=payments");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error updating bill status: " . $e->getMessage();
        header("Location: student_bills.php?student_email=" . urlencode($_POST['student_email']));
        exit();
    }
}

// Get unpaid bills count for the badge
$unpaidCount = db_query("SELECT COUNT(*) FROM student_bills WHERE status = 'unpaid'")->fetchColumn();

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
    <style>
        .payment-status-pending {
            background-color: #fef2f2;
        }

        .payment-status-paid {
            background-color: #f0fdf4;
        }

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

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
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
                        <li><a href="admin_dashboard.php" class="hover:text-blue-300"><i
                                    class="fas fa-tachometer-alt mr-2"></i>Dashboard</a></li>
                        <li><a href="student_bills.php" class="font-bold text-blue-300"><i
                                    class="fas fa-money-bill-wave mr-2"></i>Student Bills</a></li>
                        <li><a href="manage_students.php" class="hover:text-blue-300"><i
                                    class="fas fa-users mr-2"></i>Students</a></li>
                        <li><a href="logout.php" class="hover:text-blue-300"><i
                                    class="fas fa-sign-out-alt mr-2"></i>Logout</a></li>
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
                        <?= htmlspecialchars($_SESSION['success']);
                        unset($_SESSION['success']); ?>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                        <?= htmlspecialchars($_SESSION['error']);
                        unset($_SESSION['error']); ?>
                    </div>
                <?php endif; ?>

                <!-- Student Selection Form -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                    <form method="GET" action="student_bills.php" class="flex items-end gap-4">
                        <div class="flex-grow">
                            <label class="block mb-1 font-medium">Select Student</label>
                            <select name="student_id" required class="w-full p-2 border rounded"
                                onchange="updateStudentSelection(this)">
                                <option value="">-- Select a student --</option>
                                <?php foreach ($students as $s): ?>
                                    <option value="<?= $s['id'] ?>" data-email="<?= htmlspecialchars($s['email']) ?>"
                                        <?= ($selectedStudentId == $s['id'] && $selectedStudentEmail == $s['email']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($s['email']) ?>)
                                        - <?= htmlspecialchars($s['course_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="hidden" name="student_email" id="student_email"
                                value="<?= htmlspecialchars($selectedStudentEmail) ?>">
                        </div>
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                            View Bills
                        </button>
                    </form>
                </div>

                <?php if ($selectedStudentId && $selectedStudentEmail && $student): ?>
                    <!-- Student Payment Summary -->
                    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                        <div class="flex justify-between items-start mb-6">
                            <div>
                                <h3 class="text-xl font-semibold"><?= htmlspecialchars($student['name']) ?></h3>
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
                                                <th class="py-2 px-4 text-left">Paid Date</th>
                                                <th class="py-2 px-4 text-left">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($studentPayments as $payment): ?>
                                                <tr
                                                    class="border-b <?= $payment['status'] === 'paid' ? 'payment-status-paid' : 'payment-status-pending' ?>">
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
                                                    <td class="py-2 px-4 text-right"><?= number_format($payment['amount'], 2) ?>
                                                    </td>
                                                    <td class="py-2 px-4"><?= date('M d, Y', strtotime($payment['due_date'])) ?>
                                                    </td>
                                                    <td class="py-2 px-4">
                                                        <span
                                                            class="<?= $payment['status'] === 'paid' ? 'text-green-600' : 'text-red-600' ?>">
                                                            <?= ucfirst($payment['status']) ?>
                                                        </span>
                                                    </td>
                                                    <td class="py-2 px-4">
                                                        <?= $payment['paid_at'] ? date('M d, Y', strtotime($payment['paid_at'])) : '--' ?>
                                                    </td>
                                                    <td class="py-2 px-4">
                                                        <?php if ($payment['status'] === 'unpaid'): ?>
                                                            <form method="POST" action="student_bills.php" class="inline">
                                                                <input type="hidden" name="bill_id" value="<?= $payment['id'] ?>">
                                                                <input type="hidden" name="student_email"
                                                                    value="<?= htmlspecialchars($selectedStudentEmail) ?>">
                                                                <input type="hidden" name="tab" value="payments">
                                                                <button type="submit" name="mark_paid"
                                                                    class="text-green-600 hover:text-green-800">
                                                                    <i class="fas fa-check mr-1"></i> Verify Payment
                                                                </button>
                                                            </form>
                                                        <?php endif; ?>
                                                        <a href="generate_bill_pdf.php?id=<?= $payment['id'] ?>" target="_blank"
                                                            class="text-green-600 hover:text-green-800 ml-2">
                                                            <i class="fas fa-download mr-1"></i> Receipt
                                                        </a>
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
                                    <input type="hidden" name="student_email"
                                        value="<?= htmlspecialchars($selectedStudentEmail) ?>">
                                    <input type="hidden" name="tab" value="generate-bill">

                                    <div class="grid md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block mb-1">Fee Structure</label>
                                            <select name="fee_structure_id" required class="w-full p-2 border rounded">
                                                <option value="">-- Select Fee Structure --</option>
                                                <?php foreach ($feeStructures as $fee): ?>
                                                    <option value="<?= $fee['id'] ?>">
                                                        <?= ucfirst($fee['fee_type']) ?> -
                                                        <?= $fee['fee_type'] === 'semester' ? 'Semester ' : 'Year ' ?>
                                                        <?= $fee['period'] ?>
                                                        (NPR <?= number_format($fee['total_fee'], 2) ?>)
                                                        <?= $fee['bill_count'] > 0 ? ' - Already billed' : '' ?>
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

                                    <button type="submit" name="generate_bill"
                                        class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
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
                        <h3 class="text-xl font-semibold">Pending Payments (<?= $unpaidCount ?>)</h3>
                    </div>

                    <?php
                    $unpaidBills = db_query("
                            SELECT sb.*, u.name as student_name, u.email, 
                                fs.fee_type, fs.period, c.course_name
                            FROM student_bills sb
                            JOIN users u ON sb.student_id = u.id
                            JOIN fee_structures fs ON sb.fee_structure_id = fs.id
                            JOIN courses c ON fs.course_id = c.id
                            WHERE sb.status = 'unpaid'
                            ORDER BY sb.due_date ASC
                            LIMIT 10
                        ")->fetchAll();
                    ?>

                    <?php if (empty($unpaidBills)): ?>
                        <p class="text-gray-600">No unpaid bills found.</p>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($unpaidBills as $bill): ?>
                                <div class="payment-status-pending p-4 rounded-md border">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <h4 class="font-medium">
                                                <?= htmlspecialchars($bill['student_name']) ?>
                                                (<?= htmlspecialchars($bill['email']) ?>)
                                            </h4>
                                            <p class="text-gray-600">
                                                <?= $bill['course_name'] ?> -
                                                <?= $bill['fee_type'] === 'semester' ? 'Semester ' : 'Year ' ?>
                                                <?= $bill['period'] ?>
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-red-600 font-semibold">
                                                NPR <?= number_format($bill['amount'], 2) ?>
                                            </p>
                                            <p class="text-sm text-gray-500">
                                                Due: <?= date('M d, Y', strtotime($bill['due_date'])) ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex justify-end mt-2 space-x-2">
                                        <form method="POST" action="student_bills.php" class="inline">
                                            <input type="hidden" name="bill_id" value="<?= $bill['id'] ?>">
                                            <input type="hidden" name="student_email"
                                                value="<?= htmlspecialchars($bill['email']) ?>">
                                            <input type="hidden" name="tab" value="payments">
                                            <button type="submit" name="mark_paid"
                                                class="bg-green-600 text-white px-3 py-1 rounded hover:bg-green-700">
                                                <i class="fas fa-check mr-1"></i> Verify Payment
                                            </button>
                                        </form>
                                        <a href="student_bills.php?student_id=<?= $bill['student_id'] ?>&student_email=<?= urlencode($bill['email']) ?>"
                                            class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700">
                                            <i class="fas fa-user mr-1"></i> View Student
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <?php if ($unpaidCount > 10): ?>
                            <div class="mt-4 text-center">
                                <a href="unpaid_bills.php" class="text-blue-600 hover:text-blue-800">
                                    View all <?= $unpaidCount ?> unpaid bills →
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

    <script>
        // Update both student ID and email when selection changes
        function updateStudentSelection(select) {
            const selectedOption = select.options[select.selectedIndex];
            document.getElementById('student_email').value = selectedOption.dataset.email;
        }
        // Tab switching with URL update
function switchTab(tabId) {
    // Get the tab content and button elements
    const tabContent = document.getElementById(tabId);
    const tabButton = event ? event.target : document.querySelector(`.tab-button[onclick="switchTab('${tabId}')"]`);
    
    // Only proceed if elements exist
    if (tabContent && tabButton) {
        // Update URL
        const url = new URL(window.location);
        url.searchParams.set('tab', tabId);
        window.history.pushState({}, '', url);

        // Update UI
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.classList.remove('active');
        });
        tabContent.classList.add('active');

        document.querySelectorAll('.tab-button').forEach(btn => {
            btn.classList.remove('active', 'border-blue-500', 'text-blue-600');
            btn.classList.add('border-transparent', 'text-gray-500');
        });

        tabButton.classList.add('active', 'border-blue-500', 'text-blue-600');
        tabButton.classList.remove('border-transparent', 'text-gray-500');
    }
}

// Set initial tab from URL
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');
    if (tabParam) {
        // Call switchTab without event parameter
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