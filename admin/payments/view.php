<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

$title = 'Payment Details';
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
    error_log('Payment view buildings error: ' . $e->getMessage());
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

    // Update title with payment ID
    $title = 'Payment ' . $payment['payment_id'] . ' - Details';
} catch (Exception $e) {
    $error = 'Error loading payment details: ' . $e->getMessage();
    error_log('Payment view error: ' . $e->getMessage());
}

// Helper functions
function formatDate($date)
{
    if (empty($date)) return '-';
    return date('M d, Y', strtotime($date));
}

function formatDateTime($datetime)
{
    if (empty($datetime)) return '-';
    return date('M d, Y g:i A', strtotime($datetime));
}

function formatCurrency($amount)
{
    return '₹' . number_format(floatval($amount), 2);
}

function getStatusBadge($status)
{
    $badges = [
        'paid' => 'status-badge status-active',
        'pending' => 'status-badge bg-yellow-500 bg-opacity-20 text-yellow-400',
        'partial' => 'status-badge bg-blue-500 bg-opacity-20 text-blue-400',
        'overdue' => 'status-badge status-inactive'
    ];
    return $badges[$status] ?? 'status-badge bg-gray-500 bg-opacity-20 text-gray-400';
}

function getPaymentMethodIcon($method)
{
    $icons = [
        'cash' => 'M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z',
        'upi' => 'M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z',
        'bank_transfer' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4',
        'cheque' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'
    ];
    return $icons[$method] ?? $icons['cash'];
}
?>

<?php include '../../includes/header.php'; ?>

<!-- Payment Details View -->
<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex items-center justify-between">
        <div class="flex items-center space-x-4">
            <!-- Back Button -->
            <a href="index.php" class="text-pg-text-secondary hover:text-pg-text-primary transition-colors duration-200">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
            </a>

            <div>
                <h1 class="text-2xl font-bold text-pg-text-primary">Payment Details</h1>
                <p class="text-pg-text-secondary mt-1">
                    <?php if ($payment): ?>
                        Payment ID: <?php echo htmlspecialchars($payment['payment_id']); ?>
                    <?php else: ?>
                        View payment transaction details
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <?php if ($payment): ?>
            <div class="flex items-center space-x-3">
                <a href="receipt.php?id=<?php echo urlencode($paymentId); ?>"
                    class="btn-secondary"
                    target="_blank">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                    </svg>
                    <span>Print Receipt</span>
                </a>
                <a href="edit.php?id=<?php echo urlencode($paymentId); ?>" class="btn-primary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                    </svg>
                    <span>Edit Payment</span>
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Error Message -->
    <?php if ($error): ?>
        <div class="bg-status-danger bg-opacity-10 border border-status-danger text-status-danger px-4 py-3 rounded-lg">
            <div class="flex items-center">
                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                </svg>
                <?php echo htmlspecialchars($error); ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($payment): ?>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Payment Summary Card -->
            <div class="lg:col-span-1">
                <div class="card text-center">
                    <!-- Payment Amount -->
                    <div class="mb-6">
                        <div class="text-3xl font-bold text-pg-accent mb-2">
                            <?php echo formatCurrency($payment['amount_paid']); ?>
                        </div>
                        <p class="text-pg-text-secondary">Amount Paid</p>

                        <?php if ($payment['amount_due'] != $payment['amount_paid']): ?>
                            <div class="mt-2 text-sm">
                                <span class="text-pg-text-secondary">Due: </span>
                                <span class="font-semibold"><?php echo formatCurrency($payment['amount_due']); ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if ($payment['late_fee'] > 0): ?>
                            <div class="mt-1 text-sm text-status-danger">
                                Late Fee: <?php echo formatCurrency($payment['late_fee']); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Payment Status -->
                    <div class="mb-4">
                        <span class="<?php echo getStatusBadge($payment['payment_status']); ?>">
                            <?php echo ucfirst($payment['payment_status']); ?>
                        </span>
                    </div>

                    <!-- Payment Method -->
                    <div class="bg-pg-primary bg-opacity-50 rounded-lg p-4">
                        <div class="flex items-center justify-center mb-2">
                            <svg class="w-6 h-6 text-pg-accent mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?php echo getPaymentMethodIcon($payment['payment_method']); ?>"></path>
                            </svg>
                            <span class="font-semibold text-pg-text-primary">
                                <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?>
                            </span>
                        </div>
                        <p class="text-sm text-pg-text-secondary">Payment Method</p>
                    </div>
                </div>
            </div>

            <!-- Detailed Information -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Student Information -->
                <div class="card">
                    <h3 class="text-lg font-semibold text-pg-text-primary mb-4 pb-2 border-b border-pg-border flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        Student Information
                    </h3>

                    <?php if ($student): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-pg-text-secondary mb-1">Full Name</label>
                                <p class="text-pg-text-primary"><?php echo htmlspecialchars($student['full_name']); ?></p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-pg-text-secondary mb-1">Student ID</label>
                                <p class="text-pg-text-primary font-mono"><?php echo htmlspecialchars($student['student_id']); ?></p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-pg-text-secondary mb-1">Building</label>
                                <p class="text-pg-text-primary"><?php echo htmlspecialchars($buildingNames[$student['building_code']] ?? $student['building_code']); ?></p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-pg-text-secondary mb-1">Room Number</label>
                                <p class="text-pg-text-primary"><?php echo htmlspecialchars($student['room_number'] ?? '-'); ?></p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-pg-text-secondary mb-1">Phone</label>
                                <p class="text-pg-text-primary">
                                    <?php if (!empty($student['phone'])): ?>
                                        <a href="tel:<?php echo htmlspecialchars($student['phone']); ?>"
                                            class="text-pg-accent hover:underline">
                                            <?php echo htmlspecialchars($student['phone']); ?>
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-pg-text-secondary mb-1">Email</label>
                                <p class="text-pg-text-primary">
                                    <?php if (!empty($student['email'])): ?>
                                        <a href="mailto:<?php echo htmlspecialchars($student['email']); ?>"
                                            class="text-pg-accent hover:underline">
                                            <?php echo htmlspecialchars($student['email']); ?>
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>

                        <div class="mt-4 pt-4 border-t border-pg-border">
                            <a href="../students/view.php?id=<?php echo urlencode($student['student_id']); ?>"
                                class="text-pg-accent hover:text-pg-accent-light transition-colors duration-200">
                                View Full Student Profile →
                            </a>
                        </div>
                    <?php else: ?>
                        <p class="text-pg-text-secondary">Student information not available.</p>
                    <?php endif; ?>
                </div>

                <!-- Payment Details -->
                <div class="card">
                    <h3 class="text-lg font-semibold text-pg-text-primary mb-4 pb-2 border-b border-pg-border flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                        Payment Details
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-pg-text-secondary mb-1">Payment ID</label>
                            <p class="text-pg-text-primary font-mono"><?php echo htmlspecialchars($payment['payment_id']); ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-pg-text-secondary mb-1">Payment Period</label>
                            <p class="text-pg-text-primary"><?php echo htmlspecialchars($payment['month_year']); ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-pg-text-secondary mb-1">Payment Date</label>
                            <p class="text-pg-text-primary"><?php echo formatDate($payment['payment_date']); ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-pg-text-secondary mb-1">Amount Due</label>
                            <p class="text-pg-text-primary font-semibold"><?php echo formatCurrency($payment['amount_due']); ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-pg-text-secondary mb-1">Amount Paid</label>
                            <p class="text-pg-accent font-semibold"><?php echo formatCurrency($payment['amount_paid']); ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-pg-text-secondary mb-1">Balance</label>
                            <?php
                            $balance = floatval($payment['amount_due']) + floatval($payment['late_fee']) - floatval($payment['amount_paid']);
                            $balanceClass = $balance > 0 ? 'text-status-danger' : ($balance < 0 ? 'text-blue-400' : 'text-pg-accent');
                            ?>
                            <p class="font-semibold <?php echo $balanceClass; ?>">
                                <?php echo formatCurrency($balance); ?>
                                <?php if ($balance > 0): ?>
                                    (Pending)
                                <?php elseif ($balance < 0): ?>
                                    (Overpaid)
                                <?php else: ?>
                                    (Settled)
                                <?php endif; ?>
                            </p>
                        </div>
                        <?php if ($payment['late_fee'] > 0): ?>
                            <div>
                                <label class="block text-sm font-medium text-pg-text-secondary mb-1">Late Fee</label>
                                <p class="text-status-danger font-semibold"><?php echo formatCurrency($payment['late_fee']); ?></p>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($payment['receipt_number'])): ?>
                            <div>
                                <label class="block text-sm font-medium text-pg-text-secondary mb-1">Receipt Number</label>
                                <p class="text-pg-text-primary font-mono"><?php echo htmlspecialchars($payment['receipt_number']); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Additional Information -->
                <?php if (!empty($payment['notes'])): ?>
                    <div class="card">
                        <h3 class="text-lg font-semibold text-pg-text-primary mb-4 pb-2 border-b border-pg-border flex items-center">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            Notes
                        </h3>
                        <div class="bg-pg-primary bg-opacity-50 rounded-lg p-4">
                            <p class="text-pg-text-primary whitespace-pre-wrap"><?php echo htmlspecialchars($payment['notes']); ?></p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- System Information -->
                <div class="card">
                    <h3 class="text-lg font-semibold text-pg-text-primary mb-4 pb-2 border-b border-pg-border flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        System Information
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-pg-text-secondary mb-1">Created By</label>
                            <p class="text-pg-text-primary"><?php echo htmlspecialchars($payment['created_by'] ?? 'System'); ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-pg-text-secondary mb-1">Created At</label>
                            <p class="text-pg-text-primary"><?php echo formatDateTime($payment['created_at']); ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-pg-text-secondary mb-1">Last Updated</label>
                            <p class="text-pg-text-primary"><?php echo formatDateTime($payment['updated_at']); ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-pg-text-secondary mb-1">Building</label>
                            <p class="text-pg-text-primary"><?php echo htmlspecialchars($buildingNames[$payment['building_code']] ?? $payment['building_code']); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="card">
                    <div class="flex flex-col sm:flex-row gap-3">
                        <a href="edit.php?id=<?php echo urlencode($paymentId); ?>" class="btn-primary">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                            <span>Edit Payment</span>
                        </a>

                        <a href="receipt.php?id=<?php echo urlencode($paymentId); ?>"
                            class="btn-secondary"
                            target="_blank">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                            </svg>
                            <span>Print Receipt</span>
                        </a>

                        <a href="add.php?student_id=<?php echo urlencode($payment['student_id']); ?>"
                            class="btn-secondary">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                            <span>Record New Payment</span>
                        </a>

                        <a href="delete.php?id=<?php echo urlencode($paymentId); ?>"
                            class="btn-secondary bg-status-danger hover:bg-red-700"
                            onclick="return confirm('Are you sure you want to delete this payment record? This action cannot be undone.');">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                            <span>Delete Payment</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>