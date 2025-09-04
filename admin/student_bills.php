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

// Handle viewing a specific student's payments
$selectedStudentId = $_GET['student_id'] ?? null;
$selectedStudentEmail = $_GET['student_email'] ?? null;
$student = null;
$studentPayments = [];
$paymentStatus = null;
$feeStructures = [];

if ($selectedStudentId && $selectedStudentEmail) {
    $student = db_query("
        SELECT u.*, c.course_name, c.id as course_id 
        FROM users u
        LEFT JOIN courses c ON u.course_id = c.id
        WHERE u.id = ? AND u.email = ?
    ", [$selectedStudentId, $selectedStudentEmail])->fetch();

    if ($student) {
        $feeStructures = db_query("
            SELECT fs.*, 
                (SELECT COUNT(*) FROM student_bills 
                 WHERE student_id = ? AND fee_structure_id = fs.id) as bill_count
            FROM fee_structures fs
            WHERE fs.course_id = ?
            ORDER BY fs.fee_type, fs.period
        ", [$student['id'], $student['course_id']])->fetchAll();

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

        $student = db_query("SELECT id FROM users WHERE email = ?", [$_POST['student_email']])->fetch();
        if (!$student) {
            throw new Exception("Student not found");
        }

        $feeStructure = db_query("SELECT * FROM fee_structures WHERE id = ?", [$_POST['fee_structure_id']])->fetch();
        if (!$feeStructure) {
            throw new Exception("Fee structure not found");
        }

        $existingBill = db_query("
            SELECT id FROM student_bills 
            WHERE student_id = ? AND fee_structure_id = ?
            LIMIT 1
        ", [$student['id'], $_POST['fee_structure_id']])->fetch();

        if ($existingBill) {
            throw new Exception("A bill for this fee structure already exists for this student");
        }

        db_query("INSERT INTO student_bills 
                (student_id, fee_structure_id, amount, due_date, status) 
                VALUES (?, ?, ?, ?, 'unpaid')", [
            $student['id'],
            $_POST['fee_structure_id'],
            $feeStructure['total_fee'],
            $_POST['due_date']
        ]);

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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['mark_paid'])) {
            db_query("UPDATE student_bills SET status = 'paid', paid_at = NOW(), payment_verified = 1 WHERE id = ?", [$_POST['bill_id']]);
            $_SESSION['success'] = "Payment verified successfully!";
        } elseif (isset($_POST['mark_rejected'])) {
            db_query("UPDATE student_bills SET status = 'rejected', payment_verified = 0 WHERE id = ?", [$_POST['bill_id']]);
            $_SESSION['success'] = "Payment rejected successfully!";
        }

        header("Location: student_bills.php?student_email=" . urlencode($_POST['student_email']) . "&tab=payments");
        exit();
    } catch (PDOException $e) {
        $_SESSION['error'] = "Error updating bill status: " . $e->getMessage();
        header("Location: student_bills.php?student_email=" . urlencode($_POST['student_email']));
        exit();
    }
}

// Get unpaid bills count for the badge (includes pending verification)
$unpaidCount = db_query("
    SELECT COUNT(*) FROM student_bills 
    WHERE status IN ('unpaid', 'pending_verification')
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
    <link rel="stylesheet" href="css/all.min.css">
    <!-- <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"> -->
    <style>
        .payment-status-pending {
            background-color: #fef2f2;
        }

        .payment-status-paid {
            background-color: #f0fdf4;
        }

        .payment-status-pending_verification {
            background-color: #eff6ff;
        }

        .payment-status-rejected {
            background-color: #fff1f2;
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

        .proof-image {
            max-width: 100%;
            max-height: 60vh;
            object-fit: contain;
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
                                            <?php foreach ($studentPayments as $payment):
                                                $statusClass = '';
                                                $statusText = '';
                                                $statusColor = '';

                                                switch ($payment['status']) {
                                                    case 'paid':
                                                        $statusClass = 'payment-status-paid';
                                                        $statusText = 'Paid';
                                                        $statusColor = 'text-green-600';
                                                        break;
                                                    case 'pending_verification':
                                                        $statusClass = 'payment-status-pending_verification';
                                                        $statusText = 'Pending Verification';
                                                        $statusColor = 'text-blue-600';
                                                        break;
                                                    case 'rejected':
                                                        $statusClass = 'payment-status-rejected';
                                                        $statusText = 'Rejected';
                                                        $statusColor = 'text-red-600';
                                                        break;
                                                    default:
                                                        $statusClass = 'payment-status-pending';
                                                        $statusText = 'Unpaid';
                                                        $statusColor = 'text-red-600';
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
                                                    <td class="py-2 px-4 text-right"><?= number_format($payment['amount'], 2) ?>
                                                    </td>
                                                    <td class="py-2 px-4"><?= date('M d, Y', strtotime($payment['due_date'])) ?>
                                                    </td>
                                                    <td class="py-2 px-4">
                                                        <span class="<?= $statusColor ?> font-medium"><?= $statusText ?></span>
                                                    </td>
                                                    <td class="py-2 px-4">
                                                        <?= $payment['paid_at'] ? date('M d, Y', strtotime($payment['paid_at'])) : '--' ?>
                                                    </td>
                                                    <td class="py-2 px-4">
                                                        <?php if ($payment['status'] === 'unpaid' || $payment['status'] === 'pending_verification'): ?>
                                                            <button
                                                                onclick="showPaymentProof('<?= $payment['id'] ?>', '<?= htmlspecialchars($payment['payment_proof']) ?>', '<?= $payment['status'] ?>')"
                                                                class="text-blue-600 hover:text-blue-800 text-sm">
                                                                <i class="fas fa-eye mr-1"></i> Verify
                                                            </button>
                                                        <?php endif; ?>
                                                        <a href="generate_bill_pdf.php?id=<?= $payment['id'] ?>" target="_blank"
                                                            class="text-green-600 hover:text-green-800 ml-2 text-sm">
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
                            fs.fee_type, fs.period, c.course_name,
                            sb.payment_proof, sb.payment_verified
                        FROM student_bills sb
                        JOIN users u ON sb.student_id = u.id
                        JOIN fee_structures fs ON sb.fee_structure_id = fs.id
                        JOIN courses c ON fs.course_id = c.id
                        WHERE sb.status IN ('unpaid', 'pending_verification')
                        ORDER BY 
                            CASE 
                                WHEN sb.status = 'pending_verification' THEN 0 
                                ELSE 1 
                            END,
                            sb.due_date ASC
                        LIMIT 10
                    ")->fetchAll();
                    ?>

                    <?php if (empty($unpaidBills)): ?>
                        <p class="text-gray-600">No unpaid bills found.</p>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($unpaidBills as $bill):
                                $statusClass = $bill['status'] === 'pending_verification' ? 'payment-status-pending_verification' : 'payment-status-pending';
                                $statusText = $bill['status'] === 'pending_verification' ? 'Pending Verification' : 'Unpaid';
                                $statusColor = $bill['status'] === 'pending_verification' ? 'text-blue-600' : 'text-red-600';
                                ?>
                                <div class="<?= $statusClass ?> p-4 rounded-md border">
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
                                            <p class="text-sm <?= $statusColor ?>">
                                                <?= $statusText ?>
                                                <?php if ($bill['status'] === 'pending_verification' && $bill['payment_proof']): ?>
                                                    <span class="text-gray-500">(Proof submitted)</span>
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <p class="font-semibold">
                                                NPR <?= number_format($bill['amount'], 2) ?>
                                            </p>
                                            <p class="text-sm text-gray-500">
                                                Due: <?= date('M d, Y', strtotime($bill['due_date'])) ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="flex justify-end mt-2 space-x-2">
                                        <button
                                            onclick="showPaymentProof('<?= $bill['id'] ?>', '<?= htmlspecialchars($bill['payment_proof']) ?>', '<?= $bill['status'] ?>', '<?= $bill['email'] ?>')"
                                            class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700">
                                            <i class="fas fa-eye mr-1"></i> View Details
                                        </button>
                                        <a href="student_bills.php?student_id=<?= $bill['student_id'] ?>&student_email=<?= urlencode($bill['email']) ?>"
                                            class="bg-gray-600 text-white px-3 py-1 rounded hover:bg-gray-700">
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

    <!-- Payment Verification Modal -->
    <div id="paymentProofModal"
        class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg max-w-4xl w-full max-h-[90vh] overflow-auto">
            <div class="flex justify-between items-center border-b p-4">
                <h3 class="text-lg font-semibold">Payment Verification</h3>
                <button onclick="closePaymentProofModal()" class="text-gray-500 hover:text-gray-700">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-4">
                <div class="mb-4">
                    <h4 class="font-medium mb-2">Payment Proof:</h4>
                    <div id="proofImageContainer" class="flex justify-center">
                        <!-- Image will be inserted here by JavaScript -->
                    </div>
                </div>
                <form id="verificationForm" method="POST" action="student_bills.php">
                    <input type="hidden" name="bill_id" id="modalBillId">
                    <input type="hidden" name="student_email" id="modalStudentEmail">

                    <div class="flex justify-end space-x-3">
                        <button type="submit" name="mark_rejected" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
                            <i class="fas fa-times mr-1"></i> Reject
                        </button>
                        <button type="submit" name="mark_paid" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                            <i class="fas fa-check mr-1"></i> Accept
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Update student email input based on dropdown
        function updateStudentSelection(select) {
            const selectedOption = select.options[select.selectedIndex];
            document.getElementById('student_email').value = selectedOption.dataset.email;
        }

        // Safe tab switch with support for direct URL load
        function switchTab(tabId) {
            const tabContent = document.getElementById(tabId);
            const tabButton = event?.target || document.querySelector(`.tab-button[data-tab="${tabId}"]`);

            // Update URL
            const url = new URL(window.location);
            url.searchParams.set('tab', tabId);
            window.history.pushState({}, '', url);

            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(tab => tab.classList.remove('active'));

            // Show selected tab content
            if (tabContent) {
                tabContent.classList.add('active');
            }

            // Reset all tab buttons
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active', 'border-blue-500', 'text-blue-600');
                btn.classList.add('border-transparent', 'text-gray-500');
            });

            // Highlight current tab button
            if (tabButton) {
                tabButton.classList.add('active', 'border-blue-500', 'text-blue-600');
                tabButton.classList.remove('border-transparent', 'text-gray-500');
            }
        }

        // Show modal for payment proof
        function showPaymentProof(billId, proofPath, currentStatus, studentEmail = '') {
            const modal = document.getElementById('paymentProofModal');
            const billIdInput = document.getElementById('modalBillId');
            const studentEmailInput = document.getElementById('modalStudentEmail');
            const proofContainer = document.getElementById('proofImageContainer');

            // Set values
            billIdInput.value = billId;
            studentEmailInput.value = studentEmail || '<?= htmlspecialchars($selectedStudentEmail) ?>';

            // Clear previous proof
            proofContainer.innerHTML = '';

            // Show image if available
            if (proofPath) {
                const img = document.createElement('img');
                img.src = '../' + proofPath;
                img.alt = 'Payment Proof';
                img.className = 'proof-image';
                proofContainer.appendChild(img);
            } else {
                proofContainer.innerHTML = '<p class="text-red-500">No payment proof uploaded</p>';
            }

            // Show buttons only for pending status
            const acceptBtn = document.querySelector('#verificationForm [name="mark_paid"]');
            const rejectBtn = document.querySelector('#verificationForm [name="mark_rejected"]');

            if (currentStatus === 'pending_verification') {
                acceptBtn.style.display = 'inline-block';
                rejectBtn.style.display = 'inline-block';
            } else {
                acceptBtn.style.display = 'none';
                rejectBtn.style.display = 'none';
            }

            modal.classList.remove('hidden');
        }

        function closePaymentProofModal() {
            document.getElementById('paymentProofModal').classList.add('hidden');
        }

        // Close modal on outside click
        window.onclick = function(event) {
            const modal = document.getElementById('paymentProofModal');
            if (event.target === modal) {
                closePaymentProofModal();
            }
        };

        // On page load: switch to correct tab from URL
        document.addEventListener('DOMContentLoaded', function () {
            const urlParams = new URLSearchParams(window.location.search);
            const tabParam = urlParams.get('tab');
            if (tabParam) {
                switchTab(tabParam);
            }
        });
    </script>
</body>
</html>