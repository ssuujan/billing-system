<?php
session_name('STUDENT_SESSION');
session_start();

if (!isset($_SESSION['user'])) {
    header("Location: ../public/login.php");
    exit();
}

require_once __DIR__ . '/../config/database.php';

// Get current user
$student = db_query(
    "
    SELECT u.*, c.course_name 
    FROM users u
    LEFT JOIN courses c ON u.course_id = c.id
    WHERE u.id = ? AND u.role = 'student'
",
    [$_SESSION['user']['id']]
)->fetch();

if (!$student || $student['approved'] != 1) {
    header("Location: dashboard.php");
    exit();
}

// Handle mark as paid
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_as_paid'])) {
    try {
        $billId = $_POST['bill_id'];

        // Verify bill belongs to student
        $bill = db_query(
            "SELECT id FROM student_bills WHERE id = ? AND student_id = ?",
            [$billId, $student['id']]
        )->fetch();

        if (!$bill) {
            throw new Exception("Invalid bill ID");
        }

        // Update status to pending_verification
        db_query(
            "
            UPDATE student_bills 
            SET status = 'pending_verification',
                paid_at = NOW()
            WHERE id = ?
        ",
            [$billId]
        );

        $_SESSION['success'] = "Bill marked as paid successfully! Admin will verify your payment.";
        header("Location: bills_user.php");
        exit();

    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header("Location: bills_user.php");
        exit();
    }
}

// Get student's bills and payment status
$bills = db_query(
    "
    SELECT sb.*, fs.fee_type, fs.period, c.course_name, fs.payment_option
    FROM student_bills sb
    JOIN fee_structures fs ON sb.fee_structure_id = fs.id
    JOIN courses c ON fs.course_id = c.id
    WHERE sb.student_id = ?
    ORDER BY sb.due_date ASC
",
    [$student['id']]
)->fetchAll();

$totalBilled = db_query("SELECT SUM(amount) FROM student_bills WHERE student_id = ?", [$student['id']])->fetchColumn() ?? 0;
$totalPaid = db_query("SELECT SUM(amount) FROM student_bills WHERE student_id = ? AND status = 'paid'", [$student['id']])->fetchColumn() ?? 0;

$paymentStatus = [
    'total_billed' => $totalBilled,
    'total_paid' => $totalPaid,
    'balance' => $totalBilled - $totalPaid,
    'percentage' => $totalBilled > 0 ? round(($totalPaid / $totalBilled) * 100) : 0,
    'fully_paid' => ($totalBilled - $totalPaid) <= 0
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bills - Patan Multiple Campus</title>
    <link href="../student/css/tailwind.min.css?v=<?= time() ?>" rel="stylesheet">
    <link rel="stylesheet" href="../student/css/all.min.css?v=<?= time() ?>">
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
                        <h1 class="text-xl font-bold">Patan Multiple Campus - Student Portal</h1>
                        <p class="text-blue-200">Welcome, <?= htmlspecialchars($student['name']) ?> (ID:
                            <?= htmlspecialchars($student['student_id']) ?>)</p>
                    </div>
                </div>
                <nav>
                    <ul class="flex space-x-6">
                        <li><a href="dashboard.php" class="font-bold text-blue-300"><i
                                    class="fas fa-user mr-2"></i>Profile</a></li>
                        <li><a href="courses.php" class="hover:text-blue-300"><i
                                    class="fas fa-book mr-2"></i>Courses</a></li>
                        <li><a href="bills_user.php" class="hover:text-blue-300"><i
                                    class="fas fa-file-invoice-dollar mr-2"></i>My Bills</a></li>
                        <li><a href="logout.php" class="hover:text-blue-300"><i
                                    class="fas fa-sign-out-alt mr-2"></i>Logout</a></li>
                    </ul>
                </nav>
            </div>
        </header>
        <!-- Main Content -->
        <main class="flex-grow container mx-auto px-4 py-8">
            <div class="max-w-7xl mx-auto">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">My Bills and Payments</h2>
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
                        <?php echo htmlspecialchars($_SESSION['success']);
                        unset($_SESSION['success']); ?></div>
                <?php endif; ?>
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
                        <?php echo htmlspecialchars($_SESSION['error']);
                        unset($_SESSION['error']); ?></div>
                <?php endif; ?>
                <!-- Payment Summary -->
                <div class="bg-white rounded-lg shadow-md p-6 mb-8">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="text-xl font-semibold">Payment Summary</h3>
                            <p class="text-gray-600"><?= htmlspecialchars($student['course_name'] ?? 'Your Course') ?>
                            </p>
                        </div>
                        <div class="text-right">
                            <div class="text-lg font-semibold mb-1">Status: <span
                                    class="<?= $paymentStatus['fully_paid'] ? 'text-green-600' : 'text-red-600' ?>"><?= $paymentStatus['fully_paid'] ? 'Fully Paid' : 'Pending' ?></span>
                            </div>
                            <div class="text-sm text-gray-600">Paid: NPR
                                <?= number_format($paymentStatus['total_paid'], 2) ?> of NPR
                                <?= number_format($paymentStatus['total_billed'], 2) ?></div>
                            <div class="progress-bar w-64 mt-2">
                                <div class="progress-fill" style="width: <?= $paymentStatus['percentage'] ?>%;"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Bills Table -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-xl font-semibold mb-4">My Bills</h3>
                    <?php if (empty($bills)): ?>
                        <p class="text-gray-600">No bills found for your account.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto w-full">
                            <table class="min-w-full table-fixed bg-white border">
                                <thead>
                                    <tr class="bg-gray-200 text-left text-sm">
                                        <th class="w-1/4 py-2 px-4">Description</th>
                                        <th class="w-1/6 py-2 px-4">Period</th>
                                        <th class="w-1/6 py-2 px-4 text-right">Amount (NPR)</th>
                                        <th class="w-1/6 py-2 px-4">Due Date</th>
                                        <th class="w-1/6 py-2 px-4">Status</th>
                                        <th class="w-1/6 py-2 px-4 text-center">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bills as $bill): ?>
                                        <?php
                                        // Determine status class and text
                                        $statusClass = $bill['status'] === 'paid' ? 'payment-status-paid' :
                                            ($bill['status'] === 'pending_verification' ? 'payment-status-pending_verification' : 'payment-status-pending');
                                        $statusText = $bill['status'] === 'paid' ? 'Paid' :
                                            ($bill['status'] === 'pending_verification' ? 'Pending Verification' : 'Unpaid');
                                        $statusColor = $bill['status'] === 'paid' ? 'text-green-600' :
                                            ($bill['status'] === 'pending_verification' ? 'text-blue-600' : 'text-red-600');

                                        // Format period and due date
                                        $period = $bill['fee_type'] === 'semester' ? 'Semester ' . $bill['period'] : 'Year ' . $bill['period'];
                                        $dueDate = date('M d, Y', strtotime($bill['due_date']));
                                        ?>
                                        <tr class="border-b <?= $statusClass ?>" id="bill-row-<?= $bill['id'] ?>">
                                            <td class="py-2 px-4"><?= htmlspecialchars($bill['course_name']) ?> -
                                                <?= $period ?>        <?= $bill['payment_option'] === 'installment' ? ' (Installment)' : '' ?>
                                            </td>
                                            <td class="py-2 px-4"><?= $period ?></td>
                                            <td class="py-2 px-4 text-right"><?= number_format($bill['amount'], 2) ?></td>
                                            <td class="py-2 px-4"><?= $dueDate ?></td>
                                            <td class="py-2 px-4"><span class="<?= $statusColor ?>"><?= $statusText ?></span>
                                            </td>
                                            <td class="py-2 px-4 text-center">
                                                <?php if ($bill['status'] === 'unpaid'): ?>
                                                    <form method="POST" action="bills_user.php" class="inline" onsubmit="return markAsPaid(this, <?= $bill['id'] ?>)">
                                                        <input type="hidden" name="bill_id" value="<?= $bill['id'] ?>">
                                                        <button type="submit" name="mark_as_paid"
                                                            class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700">
                                                            <i class="fas fa-check mr-1"></i> Mark as Paid
                                                        </button>
                                                    </form>
                                                <?php elseif ($bill['status'] === 'pending_verification'): ?>
                                                    <span class="text-blue-600">Waiting for verification</span>
                                                <?php else: ?>
                                                    <a href="generate_receipt.php?bill_id=<?= $bill['id'] ?>"
                                                        class="text-green-600 hover:text-green-800"><i
                                                            class="fas fa-download mr-1"></i> Receipt</a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
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
    // Function to handle marking bill as paid
    async function markAsPaid(form, billId) {
        // Get the row element
        const row = document.getElementById(`bill-row-${billId}`);
        
        // Immediately update the UI
        row.classList.remove('payment-status-pending');
        row.classList.add('payment-status-pending_verification');
        
        const statusCell = row.querySelector('td:nth-child(5)');
        statusCell.innerHTML = '<span class="text-blue-600">Pending Verification</span>';
        
        const actionCell = row.querySelector('td:nth-child(6)');
        actionCell.innerHTML = '<span class="text-blue-600">Waiting for verification</span>';
        
        try {
            // Submit the form data via AJAX
            const formData = new FormData(form);
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData
            });
            
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            
            // Show success message from session if available
            const result = await response.text();
            if (result.includes('success')) {
                // Message already shown via session
            }
            
        } catch (error) {
            // Revert UI changes if there's an error
            row.classList.remove('payment-status-pending_verification');
            row.classList.add('payment-status-pending');
            
            statusCell.innerHTML = '<span class="text-red-600">Unpaid</span>';
            
            actionCell.innerHTML = `
                <form method="POST" action="bills_user.php" class="inline" onsubmit="return markAsPaid(this, ${billId})">
                    <input type="hidden" name="bill_id" value="${billId}">
                    <button type="submit" name="mark_as_paid"
                        class="bg-blue-600 text-white px-3 py-1 rounded hover:bg-blue-700">
                        <i class="fas fa-check mr-1"></i> Mark as Paid
                    </button>
                </form>
            `;
            
            alert('Error marking payment: ' + error.message);
        }
        
        return false; // Prevent default form submission
    }
    </script>
</body>

</html>