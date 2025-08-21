<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

$title = 'Payment Receipt';
require_once '../../includes/auth_check.php';

// Get payment ID from URL
$paymentId = $_GET['id'] ?? '';

if (empty($paymentId)) {
    flash('error', 'Payment ID is required.');
    header('Location: index.php');
    exit();
}

// Initialize variables
$payment = null;
$student = null;
$error = '';

// Get buildings data using the new Buildings class
try {
    $buildingNames = Buildings::getNames();
} catch (Exception $e) {
    error_log('Receipt buildings error: ' . $e->getMessage());
    $buildingNames = [];
}

try {
    $supabase = supabase();

    // Fetch payment details
    $paymentData = $supabase->select('payments', '*', ['payment_id' => $paymentId]);

    if (empty($paymentData)) {
        flash('error', 'Payment not found.');
        header('Location: index.php');
        exit();
    }

    $payment = $paymentData[0];

    // Fetch student details
    if (!empty($payment['student_id'])) {
        $studentData = $supabase->select('students', '*', ['student_id' => $payment['student_id']]);
        $student = $studentData[0] ?? null;
    }
} catch (Exception $e) {
    $error = 'Error loading payment receipt: ' . $e->getMessage();
    error_log('Receipt generation error: ' . $e->getMessage());
}

// Helper functions
function formatDate($date)
{
    if (empty($date)) return '-';
    return date('M d, Y', strtotime($date));
}

function formatCurrency($amount)
{
    return '₹' . number_format(floatval($amount), 2);
}

function getPaymentMethodDisplay($method)
{
    $methods = [
        'cash' => 'Cash Payment',
        'upi' => 'UPI Payment',
        'bank_transfer' => 'Bank Transfer',
        'cheque' => 'Cheque Payment'
    ];
    return $methods[$method] ?? ucfirst(str_replace('_', ' ', $method));
}

// Calculate totals
$totalDue = floatval($payment['amount_due'] ?? 0);
$lateFee = floatval($payment['late_fee'] ?? 0);
$totalAmount = $totalDue + $lateFee;
$amountPaid = floatval($payment['amount_paid'] ?? 0);
$balance = $totalAmount - $amountPaid;
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Receipt - <?php echo htmlspecialchars($paymentId); ?> | <?php echo APP_NAME; ?></title>

    <!-- Print-optimized styles -->
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', 'Helvetica', sans-serif;
            line-height: 1.6;
            color: #333;
            background: #f5f5f5;
            padding: 20px;
        }

        .receipt-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .receipt-header {
            background: linear-gradient(135deg, #059669, #10b981);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
        }

        .company-logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 15px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: bold;
            color: #059669;
        }

        .receipt-title {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 5px;
        }

        .receipt-subtitle {
            font-size: 16px;
            opacity: 0.9;
        }

        .receipt-number {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.2);
            padding: 8px 12px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: bold;
        }

        .receipt-body {
            padding: 40px;
        }

        .info-section {
            margin-bottom: 30px;
        }

        .section-title {
            color: #059669;
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 2px solid #059669;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        .info-item {
            margin-bottom: 12px;
        }

        .info-label {
            color: #666;
            font-size: 14px;
            margin-bottom: 4px;
            font-weight: 600;
        }

        .info-value {
            color: #333;
            font-size: 16px;
            font-weight: 500;
        }

        .amount-summary {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            border-left: 4px solid #059669;
        }

        .amount-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            padding: 8px 0;
        }

        .amount-row.total {
            border-top: 2px solid #059669;
            padding-top: 15px;
            margin-top: 15px;
            font-size: 18px;
            font-weight: bold;
            color: #059669;
        }

        .amount-row.balance {
            background: #059669;
            color: white;
            padding: 12px;
            border-radius: 4px;
            margin: 10px -12px -12px;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-paid {
            background: #d1fae5;
            color: #059669;
        }

        .status-pending {
            background: #fef3c7;
            color: #d97706;
        }

        .status-partial {
            background: #dbeafe;
            color: #2563eb;
        }

        .status-overdue {
            background: #fee2e2;
            color: #dc2626;
        }

        .payment-method {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .payment-method-icon {
            width: 20px;
            height: 20px;
            background: #059669;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 10px;
        }

        .receipt-footer {
            background: #f8f9fa;
            padding: 30px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
        }

        .footer-text {
            color: #666;
            font-size: 14px;
            margin-bottom: 8px;
        }

        .company-info {
            color: #059669;
            font-weight: bold;
        }

        .print-info {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #e5e7eb;
            color: #999;
            font-size: 12px;
        }

        /* Print styles */
        @media print {
            body {
                background: white;
                padding: 0;
            }

            .receipt-container {
                box-shadow: none;
                border-radius: 0;
            }

            .print-info {
                display: none;
            }

            @page {
                margin: 0.5in;
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .receipt-header {
                padding: 20px;
            }

            .receipt-body {
                padding: 20px;
            }

            .receipt-number {
                position: static;
                margin-top: 15px;
                display: inline-block;
            }
        }
    </style>
</head>

<body>
    <?php if ($error): ?>
        <div style="max-width: 600px; margin: 50px auto; padding: 20px; background: #fee2e2; color: #dc2626; border-radius: 8px; text-align: center;">
            <h2>Error Loading Receipt</h2>
            <p><?php echo htmlspecialchars($error); ?></p>
            <a href="index.php" style="color: #dc2626; text-decoration: underline;">← Back to Payments</a>
        </div>
    <?php elseif ($payment): ?>
        <div class="receipt-container">
            <!-- Receipt Header -->
            <div class="receipt-header">
                <div class="receipt-number">
                    #<?php echo htmlspecialchars($payment['receipt_number'] ?? $payment['payment_id']); ?>
                </div>

                <div class="company-logo">
                    PG
                </div>

                <h1 class="receipt-title">Payment Receipt</h1>
                <p class="receipt-subtitle"><?php echo APP_NAME; ?></p>
            </div>

            <!-- Receipt Body -->
            <div class="receipt-body">
                <!-- Payment Information -->
                <div class="info-section">
                    <h2 class="section-title">Payment Information</h2>
                    <div class="info-grid">
                        <div>
                            <div class="info-item">
                                <div class="info-label">Payment ID</div>
                                <div class="info-value"><?php echo htmlspecialchars($payment['payment_id']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Payment Date</div>
                                <div class="info-value"><?php echo formatDate($payment['payment_date']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Payment Period</div>
                                <div class="info-value"><?php echo htmlspecialchars($payment['month_year']); ?></div>
                            </div>
                        </div>
                        <div>
                            <div class="info-item">
                                <div class="info-label">Payment Method</div>
                                <div class="info-value">
                                    <div class="payment-method">
                                        <div class="payment-method-icon">₹</div>
                                        <?php echo getPaymentMethodDisplay($payment['payment_method']); ?>
                                    </div>
                                </div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Payment Status</div>
                                <div class="info-value">
                                    <span class="status-badge status-<?php echo $payment['payment_status']; ?>">
                                        <?php echo ucfirst($payment['payment_status']); ?>
                                    </span>
                                </div>
                            </div>
                            <?php if (!empty($payment['receipt_number'])): ?>
                                <div class="info-item">
                                    <div class="info-label">Reference Number</div>
                                    <div class="info-value"><?php echo htmlspecialchars($payment['receipt_number']); ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Student Information -->
                <div class="info-section">
                    <h2 class="section-title">Student Details</h2>
                    <?php if ($student): ?>
                        <div class="info-grid">
                            <div>
                                <div class="info-item">
                                    <div class="info-label">Student Name</div>
                                    <div class="info-value"><?php echo htmlspecialchars($student['full_name']); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Student ID</div>
                                    <div class="info-value"><?php echo htmlspecialchars($student['student_id']); ?></div>
                                </div>
                                <?php if (!empty($student['phone'])): ?>
                                    <div class="info-item">
                                        <div class="info-label">Phone Number</div>
                                        <div class="info-value"><?php echo htmlspecialchars($student['phone']); ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <div class="info-item">
                                    <div class="info-label">Building</div>
                                    <div class="info-value"><?php echo htmlspecialchars($buildingNames[$student['building_code']] ?? $student['building_code']); ?></div>
                                </div>
                                <div class="info-item">
                                    <div class="info-label">Room Number</div>
                                    <div class="info-value"><?php echo htmlspecialchars($student['room_number'] ?? '-'); ?></div>
                                </div>
                                <?php if (!empty($student['email'])): ?>
                                    <div class="info-item">
                                        <div class="info-label">Email</div>
                                        <div class="info-value"><?php echo htmlspecialchars($student['email']); ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <p style="color: #666; font-style: italic;">Student information not available.</p>
                    <?php endif; ?>
                </div>

                <!-- Payment Summary -->
                <div class="info-section">
                    <h2 class="section-title">Payment Summary</h2>
                    <div class="amount-summary">
                        <div class="amount-row">
                            <span>Amount Due</span>
                            <span><?php echo formatCurrency($totalDue); ?></span>
                        </div>

                        <?php if ($lateFee > 0): ?>
                            <div class="amount-row">
                                <span>Late Fee</span>
                                <span><?php echo formatCurrency($lateFee); ?></span>
                            </div>
                        <?php endif; ?>

                        <div class="amount-row total">
                            <span>Total Amount</span>
                            <span><?php echo formatCurrency($totalAmount); ?></span>
                        </div>

                        <div class="amount-row">
                            <span>Amount Paid</span>
                            <span><?php echo formatCurrency($amountPaid); ?></span>
                        </div>

                        <div class="amount-row balance">
                            <span>Balance</span>
                            <span>
                                <?php echo formatCurrency($balance); ?>
                                <?php if ($balance > 0): ?>
                                    (Pending)
                                <?php elseif ($balance < 0): ?>
                                    (Overpaid)
                                <?php else: ?>
                                    (Settled)
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Notes Section -->
                <?php if (!empty($payment['notes'])): ?>
                    <div class="info-section">
                        <h2 class="section-title">Additional Notes</h2>
                        <div style="background: #f8f9fa; padding: 15px; border-radius: 4px; border-left: 4px solid #059669;">
                            <p style="color: #333; line-height: 1.6;">
                                <?php echo nl2br(htmlspecialchars($payment['notes'])); ?>
                            </p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Receipt Footer -->
            <div class="receipt-footer">
                <p class="footer-text">Thank you for your payment!</p>
                <p class="company-info"><?php echo APP_NAME; ?></p>
                <p class="footer-text">This is a computer-generated receipt.</p>

                <div class="print-info">
                    <p>Receipt generated on <?php echo date('M d, Y g:i A'); ?> |
                        Printed by: <?php echo htmlspecialchars(getCurrentAdmin()['name'] ?? 'Admin'); ?></p>
                </div>
            </div>
        </div>

        <!-- Print JavaScript -->
        <script>
            // Auto-print when page loads (optional)
            // window.onload = function() {
            //     window.print();
            // };

            // Print function for manual printing
            function printReceipt() {
                window.print();
            }

            // Add print button (visible only on screen, not in print)
            document.addEventListener('DOMContentLoaded', function() {
                const printButton = document.createElement('div');
                printButton.innerHTML = `
                    <div style="text-align: center; padding: 20px; background: white;">
                        <button onclick="printReceipt()" style="background: #059669; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 16px; margin-right: 10px;">
                            🖨️ Print Receipt
                        </button>
                        <button onclick="window.close()" style="background: #6b7280; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; font-size: 16px;">
                            ❌ Close
                        </button>
                    </div>
                `;
                document.body.appendChild(printButton);

                // Hide print buttons when printing
                const style = document.createElement('style');
                style.textContent = '@media print { .print-buttons { display: none !important; } }';
                document.head.appendChild(style);
                printButton.className = 'print-buttons';
            });
        </script>
    <?php endif; ?>
</body>

</html>
