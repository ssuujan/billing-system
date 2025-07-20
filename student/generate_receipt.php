<?php
session_name('STUDENT_SESSION');
session_start();

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'student') {
    header("Location: ../public/login.php");
    exit();
}

require_once __DIR__ . '../../vendor/autoload.php';
require_once __DIR__ . '../../config/database.php';

$billId = $_GET['bill_id'] ?? null;
$student = $_SESSION['user'];

if (!$billId) {
    die("Bill ID is required");
}

// Get bill details
$bill = db_query("
    SELECT sb.*, fs.fee_type, fs.period, fs.payment_option, c.course_name
    FROM student_bills sb
    JOIN fee_structures fs ON sb.fee_structure_id = fs.id
    JOIN courses c ON fs.course_id = c.id
    WHERE sb.id = ? AND sb.student_id = ?
", [$billId, $student['id']])->fetch();

if (!$bill) {
    die("Bill not found or you don't have permission to view it");
}

// Create PDF
$mpdf = new \Mpdf\Mpdf([
    'mode' => 'utf-8',
    'format' => 'A4',
    'margin_left' => 15,
    'margin_right' => 15,
    'margin_top' => 15,
    'margin_bottom' => 20,
    'margin_header' => 5,
    'margin_footer' => 5
]);

// Header with logo
$logoPath = file_exists(__DIR__ . '/../../public/assets/images/logo.png') ? 
    'data:image/png;base64,' . base64_encode(file_get_contents(__DIR__ . '/../../public/assets/images/logo.png')) : '';

$header = '
<div style="text-align: center; border-bottom: 1px solid #ddd; padding-bottom: 10px; margin-bottom: 20px;">
    ' . ($logoPath ? '<img src="' . $logoPath . '" style="height: 60px; margin-bottom: 10px;">' : '') . '
    <h1 style="color: #1a365d; margin: 5px 0;">Patan Multiple Campus</h1>
    <h2 style="color: ' . ($bill['status'] === 'paid' ? '#38a169' : '#e53e3e') . '; margin: 5px 0;">
        ' . ($bill['status'] === 'paid' ? 'PAYMENT RECEIPT' : 'FEE STATEMENT') . '
    </h2>
    <p style="color: #4a5568; font-size: 0.9em;">
        ' . ($bill['status'] === 'paid' ? 'Official Payment Confirmation' : 'Payment Due Notice') . '
    </p>
</div>';

// Student information
$studentInfo = '
<div style="margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
    <table width="100%">
        <tr>
            <td width="50%" style="padding: 5px 0; vertical-align: top;">
                <strong>Receipt No:</strong> PMC-' . str_pad($bill['id'], 6, '0', STR_PAD_LEFT) . '<br>
                <strong>Date:</strong> ' . date('F j, Y') . '<br>
                <strong>Student ID:</strong> ' . htmlspecialchars($student['student_id']) . '
            </td>
            <td width="50%" style="padding: 5px 0; vertical-align: top;">
                <strong>Name:</strong> ' . htmlspecialchars($student['name']) . '<br>
                <strong>Course:</strong> ' . htmlspecialchars($bill['course_name']) . '<br>
                <strong>Contact:</strong> ' . htmlspecialchars($student['email']) . '
            </td>
        </tr>
    </table>
</div>';

// Current bill details with proper status display
$statusText = strtoupper($bill['status'] === 'paid' ? 'PAID' : 'UNPAID');
$statusColor = $bill['status'] === 'paid' ? '#38a169' : '#e53e3e';

$currentBill = '
<h3 style="color: #2b6cb0; border-bottom: 1px solid #eee; padding-bottom: 5px; margin: 20px 0 10px 0;">
    ' . ($bill['status'] === 'paid' ? 'Payment Details' : 'Payment Due') . '
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
            <td style="padding: 8px; text-align: center; font-weight: bold; color: ' . $statusColor . ';">' . 
                $statusText . 
            '</td>
            <td style="padding: 8px;">' . date('M j, Y', strtotime($bill['due_date'])) . '</td>
            ' . ($bill['status'] === 'paid' ? 
                '<td style="padding: 8px;">' . date('M j, Y', strtotime($bill['paid_at'])) . '</td>' : '') . '
        </tr>
    </tbody>
</table>';

// Add prominent notice for unpaid bills
if ($bill['status'] !== 'paid') {
    $currentBill .= '
    <div style="padding: 15px; background: #fff5f5; border: 1px solid #fed7d7; border-radius: 5px; margin-bottom: 20px;">
        <h4 style="color: #e53e3e; margin-top: 0;">PAYMENT DUE NOTICE</h4>
        <p style="margin-bottom: 5px;">Your payment of <strong>NPR ' . number_format($bill['amount'], 2) . '</strong> is due by:</p>
        <p style="font-size: 1.2em; font-weight: bold; color: #e53e3e; margin: 10px 0;">' . date('F j, Y', strtotime($bill['due_date'])) . '</p>
        <p>Please make payment to avoid late fees or registration holds.</p>
    </div>';
}

// Payment methods section
$qrCodePath = file_exists(__DIR__ . '/../../public/assets/images/payment-qr.png') ? 
    'data:image/png;base64,' . base64_encode(file_get_contents(__DIR__ . '/../../public/assets/images/payment-qr.png')) : '';

$paymentMethods = '
<div style="margin: 25px 0; page-break-inside: avoid;">
    <h3 style="color: #2b6cb0; border-bottom: 1px solid #eee; padding-bottom: 5px; margin-bottom: 15px;">
        Payment Options
    </h3>
    
    <div style="display: flex; flex-wrap: wrap; gap: 20px; margin-bottom: 20px;">
        <!-- QR Code Section -->
        <div style="flex: 1; min-width: 200px;">
            <h4 style="margin-top: 0; color: #4a5568;">Scan to Pay</h4>
            <div style="border: 1px solid #ddd; padding: 10px; display: inline-block; background: white; text-align: center;">
                ' . ($qrCodePath ? 
                    '<img src="' . $qrCodePath . '" style="width: 150px; height: 150px;">' : 
                    '<div style="width: 150px; height: 150px; background: #f0f0f0; display: flex; align-items: center; justify-content: center;">
                        QR Code
                    </div>') . '
                <p style="margin: 5px 0 0 0; font-size: 0.8em;">
                    Scan with mobile banking app
                </p>
            </div>
        </div>
        
        <!-- Bank Details -->
        <div style="flex: 2; min-width: 250px;">
            <h4 style="margin-top: 0; color: #4a5568;">Bank Transfer</h4>
            <div style="background: #f8f9fa; padding: 15px; border-radius: 5px;">
                <table style="width: 100%;">
                    <tr>
                        <td style="padding: 5px 0; width: 120px;"><strong>Bank:</strong></td>
                        <td style="padding: 5px 0;">Global IME Bank</td>
                    </tr>
                    <tr>
                        <td style="padding: 5px 0;"><strong>Account:</strong></td>
                        <td style="padding: 5px 0;">Patan Multiple Campus</td>
                    </tr>
                    <tr>
                        <td style="padding: 5px 0;"><strong>A/C No:</strong></td>
                        <td style="padding: 5px 0;">1234567890123456</td>
                    </tr>
                    <tr>
                        <td style="padding: 5px 0;"><strong>Reference:</strong></td>
                        <td style="padding: 5px 0;">STD-' . htmlspecialchars($student['student_id']) . '</td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>';

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
                    Campus Stamp
                </div>
            </td>
        </tr>
    </table>
    <p style="text-align: center; font-size: 0.8em; color: #718096; margin-top: 20px;">
        Patan Multiple Campus &copy; ' . date('Y') . ' - All rights reserved<br>
        Generated on ' . date('F j, Y \a\t H:i') . '
    </p>
</div>';

// Combine all sections
$html = '
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #4a5568; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #2b6cb0; color: white; text-align: left; padding: 8px; }
        td { padding: 8px; border-bottom: 1px solid #eee; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
    </style>
</head>
<body>
    ' . $header . '
    ' . $studentInfo . '
    ' . $currentBill . '
    ' . $paymentMethods . '
    ' . $footer . '
</body>
</html>';

$mpdf->WriteHTML($html);

// Output PDF
$filename = 'PMC_' . ($bill['status'] === 'paid' ? 'Receipt' : 'Bill') . '_' . $student['student_id'] . '.pdf';
$mpdf->Output($filename, 'I');
exit();