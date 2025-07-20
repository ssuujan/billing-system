<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

// Start session and verify admin
session_name('ADMIN_SESSION');
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
    header("Location: ../public/admin.php");
    exit();
}

$billId = $_GET['id'] ?? null;

if (!$billId) {
    die("Bill ID is required");
}

// Get bill details with all payment history for the student
$bill = db_query("
    SELECT sb.*, u.name as student_name, u.student_id as student_code, 
           u.email, u.phone, c.course_name, fs.fee_type, fs.period, 
           fs.payment_option, fs.total_fee as full_amount
    FROM student_bills sb
    JOIN users u ON sb.student_id = u.id
    JOIN fee_structures fs ON sb.fee_structure_id = fs.id
    JOIN courses c ON fs.course_id = c.id
    WHERE sb.id = ?
", [$billId])->fetch();

if (!$bill) {
    die("Bill not found");
}

// Get all bills for this student to show payment history
$allBills = db_query("
    SELECT sb.*, fs.fee_type, fs.period, fs.payment_option
    FROM student_bills sb
    JOIN fee_structures fs ON sb.fee_structure_id = fs.id
    WHERE sb.student_id = ?
    ORDER BY sb.due_date ASC
", [$bill['student_id']])->fetchAll();

// Calculate totals
$totalBilled = array_sum(array_column($allBills, 'amount'));
$totalPaid = array_sum(array_column(array_filter($allBills, function($b) { 
    return $b['status'] === 'paid'; 
}), 'amount'));

// Create PDF
$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'margin_left' => 10,
    'margin_right' => 10,
    'margin_top' => 20,
    'margin_bottom' => 20,
    'margin_header' => 5,
    'margin_footer' => 5
]);

// Add watermark for unpaid bills
if ($bill['status'] !== 'paid') {
    $mpdf->SetWatermarkText('UNPAID');
    $mpdf->showWatermarkText = true;
    $mpdf->watermarkTextAlpha = 0.1;
}

// Header with logo
$logoPath = file_exists(__DIR__ . '/../public/assets/images/logo.png') ? 
    'data:image/png;base64,' . base64_encode(file_get_contents(__DIR__ . '/../public/assets/images/logo.png')) : '';

$header = '
<div style="text-align: center; border-bottom: 1px solid #ddd; padding-bottom: 10px; margin-bottom: 20px;">
    ' . ($logoPath ? '<img src="' . $logoPath . '" style="height: 60px; margin-bottom: 10px;">' : '') . '
    <h1 style="color: #1a365d; margin: 5px 0;">Patan Multiple Campus</h1>
    <h2 style="color: #2b6cb0; margin: 5px 0;">' . ($bill['status'] === 'paid' ? 'PAYMENT RECEIPT' : 'FEE STATEMENT') . '</h2>
    <p style="color: #4a5568;">'.($bill['status'] === 'paid' ? 'Official Payment Confirmation' : 'Pending Payment Notice').'</p>
</div>';

// Student information section
$studentInfo = '
<div style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
    <table width="100%">
        <tr>
            <td width="50%" style="padding: 5px 0;">
                <strong>Receipt No:</strong> PMC-' . str_pad($bill['id'], 6, '0', STR_PAD_LEFT) . '<br>
                <strong>Date:</strong> ' . date('F j, Y') . '<br>
                <strong>Student ID:</strong> ' . htmlspecialchars($bill['student_code']) . '
            </td>
            <td width="50%" style="padding: 5px 0;">
                <strong>Student Name:</strong> ' . htmlspecialchars($bill['student_name']) . '<br>
                <strong>Course:</strong> ' . htmlspecialchars($bill['course_name']) . '<br>
                <strong>Contact:</strong> ' . htmlspecialchars($bill['email']) . ' / ' . htmlspecialchars($bill['phone'] ?? 'N/A') . '
            </td>
        </tr>
    </table>
</div>';

// Current bill details
$currentBill = '
<h3 style="color: #2b6cb0; border-bottom: 1px solid #eee; padding-bottom: 5px; margin: 20px 0 10px 0;">
    ' . ($bill['status'] === 'paid' ? 'Payment Details' : 'Outstanding Payment') . '
</h3>
<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
    <thead>
        <tr style="background: #2b6cb0; color: white;">
            <th style="padding: 8px; text-align: left;">Description</th>
            <th style="padding: 8px; text-align: right;">Amount (NPR)</th>
            <th style="padding: 8px; text-align: center;">Status</th>
            <th style="padding: 8px; text-align: left;">Due Date</th>
            ' . ($bill['status'] === 'paid' ? '<th style="padding: 8px; text-align: left;">Paid Date</th>' : '') . '
        </tr>
    </thead>
    <tbody>
        <tr style="border-bottom: 1px solid #eee;">
            <td style="padding: 8px;">' . 
                ($bill['fee_type'] === 'semester' ? 'Semester ' : 'Year ') . $bill['period'] . 
                ($bill['payment_option'] === 'installment' ? ' (Installment)' : '') . 
            '</td>
            <td style="padding: 8px; text-align: right;">' . number_format($bill['amount'], 2) . '</td>
            <td style="padding: 8px; text-align: center; color: ' . 
                ($bill['status'] === 'paid' ? '#38a169' : '#e53e3e') . '; font-weight: bold;">' . 
                strtoupper($bill['status']) . 
            '</td>
            <td style="padding: 8px;">' . date('M j, Y', strtotime($bill['due_date'])) . '</td>
            ' . ($bill['status'] === 'paid' ? 
                '<td style="padding: 8px;">' . date('M j, Y', strtotime($bill['paid_at'])) . '</td>' : '') . '
        </tr>
    </tbody>
</table>';

// Payment history section
$paymentHistory = '
<h3 style="color: #2b6cb0; border-bottom: 1px solid #eee; padding-bottom: 5px; margin: 20px 0 10px 0;">
    Payment History
</h3>
<table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
    <thead>
        <tr style="background: #2b6cb0; color: white;">
            <th style="padding: 8px; text-align: left;">Description</th>
            <th style="padding: 8px; text-align: right;">Amount (NPR)</th>
            <th style="padding: 8px; text-align: left;">Due Date</th>
            <th style="padding: 8px; text-align: center;">Status</th>
            <th style="padding: 8px; text-align: left;">Paid Date</th>
        </tr>
    </thead>
    <tbody>';

foreach ($allBills as $b) {
    $paymentHistory .= '
        <tr style="border-bottom: 1px solid #eee;">
            <td style="padding: 8px;">' . 
                ($b['fee_type'] === 'semester' ? 'Semester ' : 'Year ') . $b['period'] . 
                ($b['payment_option'] === 'installment' ? ' (Installment)' : '') . 
            '</td>
            <td style="padding: 8px; text-align: right;">' . number_format($b['amount'], 2) . '</td>
            <td style="padding: 8px;">' . date('M j, Y', strtotime($b['due_date'])) . '</td>
            <td style="padding: 8px; text-align: center; color: ' . 
                ($b['status'] === 'paid' ? '#38a169' : '#e53e3e') . '; font-weight: bold;">' . 
                strtoupper($b['status']) . 
            '</td>
            <td style="padding: 8px;">' . 
                ($b['paid_at'] ? date('M j, Y', strtotime($b['paid_at'])) : '---') . 
            '</td>
        </tr>';
}

$paymentHistory .= '
    </tbody>
    <tfoot style="border-top: 2px solid #2b6cb0;">
        <tr>
            <td style="padding: 8px; font-weight: bold;">TOTAL</td>
            <td style="padding: 8px; text-align: right; font-weight: bold;">' . number_format($totalBilled, 2) . '</td>
            <td colspan="2" style="padding: 8px; font-weight: bold;">PAID</td>
            <td style="padding: 8px; font-weight: bold;">' . number_format($totalPaid, 2) . '</td>
        </tr>
        <tr>
            <td colspan="4" style="padding: 8px; font-weight: bold;">BALANCE DUE</td>
            <td style="padding: 8px; font-weight: bold; color: #e53e3e;">' . number_format($totalBilled - $totalPaid, 2) . '</td>
        </tr>
    </tfoot>
</table>';

// Payment instructions for unpaid bills
$paymentInstructions = '';
if ($bill['status'] !== 'paid') {
    $paymentInstructions = '
    <div style="margin: 20px 0; padding: 15px; background: #fffaf0; border-left: 4px solid #dd6b20; border-radius: 0 5px 5px 0;">
        <h4 style="margin-top: 0; color: #dd6b20;">Payment Instructions</h4>
        <p>Please make payment before the due date to avoid late fees.</p>
        <p><strong>Payment Methods:</strong></p>
        <ul>
            <li>Bank Transfer (Account: 123456789, Patan Multiple Campus)</li>
            <li>Cash payment at Finance Office (Room 201)</li>
            <li>Online Payment via Campus Portal</li>
        </ul>
        <p>Include your Student ID as payment reference.</p>
    </div>';
}

// Footer
$footer = '
<div style="margin-top: 30px; border-top: 1px solid #ddd; padding-top: 15px;">
    <table width="100%">
        <tr>
            <td width="50%" style="text-align: center;">
                <div style="border-top: 1px dashed #000; width: 200px; margin: 0 auto; padding-top: 5px;">
                    Student Signature
                </div>
            </td>
            <td width="50%" style="text-align: center;">
                <div style="border-top: 1px dashed #000; width: 200px; margin: 0 auto; padding-top: 5px;">
                    Authorized Signature
                </div>
            </td>
        </tr>
    </table>
    <p style="text-align: center; font-size: 0.8em; color: #718096; margin-top: 20px;">
        Generated on ' . date('F j, Y \a\t H:i') . ' by ' . htmlspecialchars($_SESSION['user']['name']) . '<br>
        Patan Multiple Campus &copy; ' . date('Y') . ' - All rights reserved
    </p>
</div>';

// Combine all sections
$html = '
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #4a5568; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-danger { color: #e53e3e; }
        .text-success { color: #38a169; }
        .text-warning { color: #d69e2e; }
    </style>
</head>
<body>
    ' . $header . '
    ' . $studentInfo . '
    ' . $currentBill . '
    ' . $paymentHistory . '
    ' . $paymentInstructions . '
    ' . $footer . '
</body>
</html>';

$mpdf->WriteHTML($html);

// Output PDF
$filename = 'PMC_' . ($bill['status'] === 'paid' ? 'Receipt' : 'Bill') . '_' . $bill['student_code'] . '_' . date('Ymd') . '.pdf';
$mpdf->Output($filename, 'I');
exit();