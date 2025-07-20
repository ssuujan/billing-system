<?php
session_name('ADMIN_SESSION');
session_start();
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../public/admin.php");
    exit();
}

require_once __DIR__ . '/../config/database.php';
$admin = $_SESSION['user'];

// Get all courses for dropdown
$courses = db_query("SELECT id, course_name FROM courses")->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $conn->beginTransaction();

        // Insert fee structure
        $stmt = $conn->prepare("INSERT INTO fee_structures 
            (course_id, fee_type, period, total_fee, payment_option, description) 
            VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['course_id'],
            $_POST['fee_type'],
            $_POST['period'],
            $_POST['total_fee'],
            $_POST['payment_option'],
            $_POST['description']
        ]);
        $feeStructureId = $conn->lastInsertId();

        // If installments, insert installment details
        if ($_POST['payment_option'] === 'installment' && isset($_POST['installments'])) {
            $installmentStmt = $conn->prepare("INSERT INTO fee_installments 
                (fee_structure_id, installment_number, amount, due_date) 
                VALUES (?, ?, ?, ?)");

            foreach ($_POST['installments'] as $i => $installment) {
                $installmentStmt->execute([
                    $feeStructureId,
                    $i + 1,
                    $installment['amount'],
                    $installment['due_date']
                ]);
            }
        }

        $conn->commit();
        $_SESSION['success'] = "Fee structure added successfully!";
    } catch (PDOException $e) {
        $conn->rollBack();
        $_SESSION['error'] = "Error adding fee structure: " . $e->getMessage();
    }
    header("Location: fee_structure.php");
    exit();
}

// Get all fee structures
$feeStructures = db_query("
    SELECT fs.*, c.course_name, 
           (SELECT COUNT(*) FROM fee_installments fi WHERE fi.fee_structure_id = fs.id) as installment_count
    FROM fee_structures fs
    JOIN courses c ON fs.course_id = c.id
    ORDER BY c.course_name, fs.fee_type, fs.period
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fee Structure - Patan Multiple Campus</title>
    <!-- <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet"> -->
    <link href="./css/tailwind.min.css" rel="stylesheet">
    <style>
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
                        <li><a href="admin_dashboard.php" class="hover:text-blue-300"><i class="fas fa-tachometer-alt mr-2"></i>Dashboard</a></li>
                        <li><a href="manage_students.php" class="font-bold text-blue-300"><i class="fas fa-users mr-2"></i>Students</a></li>
                        <li><a href="manage_courses.php" class="hover:text-blue-300"><i class="fas fa-book mr-2"></i>Courses</a></li>
                        <li><a href="logout.php" class="hover:text-blue-300"><i class="fas fa-sign-out-alt mr-2"></i>Logout</a></li>
                    </ul>
                </nav>
            </div>
        </header>
        <!-- Main Content -->
        <main class="flex-grow container mx-auto px-4 py-8">

<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow-md p-6">
            <h1 class="text-2xl font-bold mb-6">Fee Structure Management</h1>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?= $_SESSION['success'];
                    unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?= $_SESSION['error'];
                    unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <div class="mb-6">
                <button onclick="openModal()" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                    Add New Fee Structure
                </button>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead>
                        <tr class="bg-gray-200">
                            <th class="py-2 px-4">Course</th>
                            <th class="py-2 px-4">Type</th>
                            <th class="py-2 px-4">Period</th>
                            <th class="py-2 px-4">Total Fee</th>
                            <th class="py-2 px-4">Payment Option</th>
                            <th class="py-2 px-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($feeStructures as $fee): ?>
                            <tr class="border-b">
                                <td class="py-2 px-4"><?= htmlspecialchars($fee['course_name']) ?></td>
                                <td class="py-2 px-4"><?= ucfirst($fee['fee_type']) ?></td>
                                <td class="py-2 px-4">
                                    <?= $fee['fee_type'] === 'semester' ? 'Semester ' : 'Year ' ?>    <?= $fee['period'] ?>
                                </td>
                                <td class="py-2 px-4">NPR <?= number_format($fee['total_fee'], 2) ?></td>
                                <td class="py-2 px-4">
                                    <?= ucfirst($fee['payment_option']) ?>
                                    <?= $fee['payment_option'] === 'installment' ? "({$fee['installment_count']} installments)" : '' ?>
                                </td>
                                <td class="py-2 px-4">
                                    <a href="view_fee.php?id=<?= $fee['id'] ?>"
                                        class="text-blue-600 hover:text-blue-800 mr-2">View</a>
                                    <a href="edit_fee.php?id=<?= $fee['id'] ?>"
                                        class="text-yellow-600 hover:text-yellow-800 mr-2">Edit</a>
                                    <a href="delete_fee.php?id=<?= $fee['id'] ?>" class="text-red-600 hover:text-red-800"
                                        onclick="return confirm('Are you sure?')">Delete</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Fee Modal -->
    <div id="feeModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-lg p-6 w-full max-w-2xl">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold">Add Fee Structure</h2>
                <button onclick="closeModal()" class="text-gray-500 hover:text-gray-700">&times;</button>
            </div>

            <form id="feeForm" method="POST">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block mb-1">Course</label>
                        <select name="course_id" required class="w-full p-2 border rounded">
                            <option value="">Select Course</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?= $course['id'] ?>"><?= htmlspecialchars($course['course_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block mb-1">Fee Type</label>
                        <select name="fee_type" id="feeType" required onchange="updatePeriodOptions()"
                            class="w-full p-2 border rounded">
                            <option value="semester">Semester-wise</option>
                            <option value="yearly">Yearly</option>
                        </select>
                    </div>

                    <div>
                        <label class="block mb-1">Period</label>
                        <select name="period" id="period" required class="w-full p-2 border rounded">
                            <!-- Options will be populated by JavaScript -->
                        </select>
                    </div>

                    <div>
                        <label class="block mb-1">Total Fee (NPR)</label>
                        <input type="number" step="0.01" name="total_fee" required class="w-full p-2 border rounded">
                    </div>

                    <div>
                        <label class="block mb-1">Payment Option</label>
                        <select name="payment_option" id="paymentOption" required onchange="toggleInstallmentFields()"
                            class="w-full p-2 border rounded">
                            <option value="full">Full Payment</option>
                            <option value="installment">Installments</option>
                        </select>
                    </div>
                </div>

                <div id="installmentFields" class="hidden mb-4">
                    <h3 class="font-bold mb-2">Installment Plan</h3>
                    <div id="installmentContainer" class="space-y-3">
                        <!-- Installment fields will be added here -->
                    </div>
                    <button type="button" onclick="addInstallment()"
                        class="mt-2 bg-gray-200 px-3 py-1 rounded hover:bg-gray-300">
                        + Add Installment
                    </button>
                </div>

                <div class="mb-4">
                    <label class="block mb-1">Description (Optional)</label>
                    <textarea name="description" rows="3" class="w-full p-2 border rounded"></textarea>
                </div>

                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="closeModal()"
                        class="bg-gray-300 px-4 py-2 rounded hover:bg-gray-400">Cancel</button>
                    <button type="submit"
                        class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Save</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function openModal() {
            document.getElementById('feeModal').classList.remove('hidden');
            updatePeriodOptions();
        }

        function closeModal() {
            document.getElementById('feeModal').classList.add('hidden');
        }

        // Update period options based on fee type
        function updatePeriodOptions() {
            const feeType = document.getElementById('feeType').value;
            const periodSelect = document.getElementById('period');

            periodSelect.innerHTML = '';

            const maxPeriod = feeType === 'semester' ? 8 : 4;
            const periodLabel = feeType === 'semester' ? 'Semester ' : 'Year ';

            for (let i = 1; i <= maxPeriod; i++) {
                const option = document.createElement('option');
                option.value = i;
                option.textContent = periodLabel + i;
                periodSelect.appendChild(option);
            }
        }

        // Toggle installment fields
        function toggleInstallmentFields() {
            const paymentOption = document.getElementById('paymentOption').value;
            const installmentFields = document.getElementById('installmentFields');

            if (paymentOption === 'installment') {
                installmentFields.classList.remove('hidden');
                if (document.querySelectorAll('.installment-item').length === 0) {
                    addInstallment();
                }
            } else {
                installmentFields.classList.add('hidden');
            }
        }

     // Add installment field
function addInstallment() {
    const container = document.getElementById('installmentContainer');
    const totalFee = parseFloat(document.querySelector('input[name="total_fee"]').value) || 0;
    const installmentCount = document.querySelectorAll('.installment-item').length + 1;

    const div = document.createElement('div');
    div.className = 'installment-item bg-gray-50 p-3 rounded';
    div.innerHTML = `
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div>
                <label class="block mb-1">Amount (NPR)</label>
                <input type="number" step="0.01" name="installments[${installmentCount-1}][amount]" required class="w-full p-2 border rounded">
            </div>
            <div>
                <label class="block mb-1">Due Date</label>
                <input type="date" name="installments[${installmentCount-1}][due_date]" required class="w-full p-2 border rounded">
            </div>
            <div class="flex items-end">
                <button type="button" onclick="this.parentElement.parentElement.parentElement.remove()" class="text-red-600 hover:text-red-800">
                    Remove
                </button>
            </div>
        </div>
    `;

    container.appendChild(div);

    // Set amount for first installment
    if (installmentCount === 1) {
        div.querySelector('input[type="number"]').value = totalFee.toFixed(2);
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    updatePeriodOptions();
});
    </script>
        </main>
        <footer class="bg-gray-800 text-white py-4 mt-8">
            <div class="container mx-auto text-center">
                <p>&copy; <?= date('Y') ?> Patan Multiple Campus. All rights reserved.</p>
            </div>
</body>

</html>