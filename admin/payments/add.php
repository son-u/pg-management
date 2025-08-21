<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

$title = 'Record New Payment';
require_once '../../includes/auth_check.php';

// Initialize variables
$error = '';
$formData = [];

// Get pre-selected student from URL (if coming from student profile)
$preSelectedStudent = $_GET['student_id'] ?? '';

try {
    $supabase = supabase();

    // Get buildings data using the new Buildings class
    $buildingCodes = Buildings::getCodes();
    $buildingNames = Buildings::getNames();

    // Get students list
    $students = $supabase->select('students', 'student_id,full_name,building_code,monthly_rent', []);
    usort($students, function ($a, $b) {
        return strcmp($a['full_name'], $b['full_name']);
    });

    // Generate next payment ID - FIXED VERSION
    $allPayments = $supabase->select('payments', 'payment_id', []);
    $nextPaymentNumber = 1;

    if (!empty($allPayments)) {
        $maxNumber = 0;
        foreach ($allPayments as $payment) {
            if (preg_match('/PAY(\d+)/', $payment['payment_id'], $matches)) {
                $maxNumber = max($maxNumber, intval($matches[1]));
            }
        }
        $nextPaymentNumber = $maxNumber + 1;
    }

    $generatedPaymentId = 'PAY' . str_pad($nextPaymentNumber, 6, '0', STR_PAD_LEFT);
} catch (Exception $e) {
    $error = 'Error loading initial data: ' . $e->getMessage();
    $students = [];
    $buildingCodes = [];
    $buildingNames = [];
    $generatedPaymentId = 'PAY000001';
}

// Initialize form data
$formData = [
    'payment_id' => $generatedPaymentId,
    'student_id' => $preSelectedStudent,
    'building_code' => '',
    'month_year' => date('Y-m'),
    'amount_due' => '',
    'amount_paid' => '',
    'payment_date' => date('Y-m-d'),
    'payment_method' => 'cash',
    'receipt_number' => '',
    'payment_status' => 'paid',
    'late_fee' => 0,
    'notes' => ''
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $formData = [
        'payment_id' => trim($_POST['payment_id'] ?? ''),
        'student_id' => trim($_POST['student_id'] ?? ''),
        'building_code' => trim($_POST['building_code'] ?? ''),
        'month_year' => trim($_POST['month_year'] ?? ''),
        'amount_due' => floatval($_POST['amount_due'] ?? 0),
        'amount_paid' => floatval($_POST['amount_paid'] ?? 0),
        'payment_date' => trim($_POST['payment_date'] ?? ''),
        'payment_method' => trim($_POST['payment_method'] ?? ''),
        'receipt_number' => trim($_POST['receipt_number'] ?? ''),
        'payment_status' => trim($_POST['payment_status'] ?? ''),
        'late_fee' => floatval($_POST['late_fee'] ?? 0),
        'notes' => trim($_POST['notes'] ?? ''),
        'created_by' => getCurrentAdmin()['name'] ?? 'admin'
    ];

    // Validation
    $validationErrors = [];

    if (empty($formData['student_id'])) {
        $validationErrors[] = 'Student selection is required';
    }

    if (empty($formData['building_code'])) {
        $validationErrors[] = 'Building is required';
    }

    if (empty($formData['month_year'])) {
        $validationErrors[] = 'Payment period is required';
    }

    if ($formData['amount_due'] <= 0) {
        $validationErrors[] = 'Amount due must be greater than zero';
    }

    if ($formData['amount_paid'] <= 0) {
        $validationErrors[] = 'Amount paid must be greater than zero';
    }

    if (empty($formData['payment_date']) || !strtotime($formData['payment_date'])) {
        $validationErrors[] = 'Valid payment date is required';
    }

    if (!empty($validationErrors)) {
        $error = implode('<br>', $validationErrors);
    } else {
        try {
            // Check for duplicate payment for same student and month
            $existingPayment = $supabase->select('payments', 'payment_id', [
                'student_id' => $formData['student_id'],
                'month_year' => $formData['month_year']
            ]);

            if (!empty($existingPayment)) {
                $error = 'Payment for this student and period already exists.';
            } else {
                // Add timestamps
                $formData['created_at'] = date('c');
                $formData['updated_at'] = date('c');

                // Insert payment record
                $result = $supabase->insert('payments', $formData);

                if ($result) {
                    flash('success', 'Payment recorded successfully for ' . $formData['student_id'] . '!');
                    header('Location: view.php?id=' . urlencode($formData['payment_id']));
                    exit();
                } else {
                    $error = 'Failed to record payment. Please try again.';
                }
            }
        } catch (Exception $e) {
            $error = 'Error recording payment: ' . $e->getMessage();
            error_log('Payment recording error: ' . $e->getMessage());
        }
    }
}

// Helper function to generate month-year options
function generateMonthYearOptions($startMonths = 12, $futureMonths = 6)
{
    $options = [];
    $currentDate = new DateTime();

    // Add future months
    for ($i = $futureMonths; $i >= 0; $i--) {
        $date = clone $currentDate;
        $date->add(new DateInterval("P{$i}M"));
        $value = $date->format('Y-m');
        $label = $date->format('F Y');
        $options[] = ['value' => $value, 'label' => $label];
    }

    // Add past months
    for ($i = 1; $i <= $startMonths; $i++) {
        $date = clone $currentDate;
        $date->sub(new DateInterval("P{$i}M"));
        $value = $date->format('Y-m');
        $label = $date->format('F Y');
        $options[] = ['value' => $value, 'label' => $label];
    }

    return $options;
}
?>

<?php include '../../includes/header.php'; ?>

<!-- Add Payment Form -->
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
                <h1 class="text-2xl font-bold text-pg-text-primary">Record New Payment</h1>
                <p class="text-pg-text-secondary mt-1">Add a new payment transaction to the system</p>
            </div>
        </div>

        <div class="flex items-center space-x-3">
            <a href="index.php" class="btn-secondary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
                <span>View All Payments</span>
            </a>
        </div>
    </div>

    <!-- Error Messages -->
    <?php if ($error): ?>
        <div class="bg-status-danger bg-opacity-10 border border-status-danger text-status-danger px-4 py-3 rounded-lg">
            <div class="flex items-center">
                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                </svg>
                <div><?php echo $error; ?></div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Payment Form -->
    <div class="card">
        <form method="POST" class="space-y-6" data-validate>
            <!-- Payment Information Section -->
            <div>
                <h3 class="text-lg font-semibold text-pg-text-primary mb-4 pb-2 border-b border-pg-border">
                    Payment Information
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Payment ID (Read-only) -->
                    <div>
                        <label for="payment_id" class="block text-sm font-medium text-pg-text-primary mb-2">
                            Payment ID
                        </label>
                        <input type="text"
                            id="payment_id"
                            name="payment_id"
                            value="<?php echo htmlspecialchars($formData['payment_id']); ?>"
                            class="input-field w-full bg-gray-600 cursor-not-allowed"
                            readonly
                            title="Auto-generated payment ID">
                        <p class="text-xs text-pg-text-secondary mt-1">Auto-generated unique payment identifier</p>
                    </div>

                    <!-- Payment Date -->
                    <div>
                        <label for="payment_date" class="block text-sm font-medium text-pg-text-primary mb-2">
                            Payment Date <span class="text-status-danger">*</span>
                        </label>
                        <input type="date"
                            id="payment_date"
                            name="payment_date"
                            value="<?php echo htmlspecialchars($formData['payment_date']); ?>"
                            class="input-field w-full"
                            required>
                    </div>
                </div>
            </div>

            <!-- Student & Building Section -->
            <div>
                <h3 class="text-lg font-semibold text-pg-text-primary mb-4 pb-2 border-b border-pg-border">
                    Student & Building Details
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Student Selection -->
                    <div>
                        <label for="student_id" class="block text-sm font-medium text-pg-text-primary mb-2">
                            Student <span class="text-status-danger">*</span>
                        </label>
                        <select id="student_id" name="student_id" class="select-field w-full" required>
                            <option value="">Select Student</option>
                            <?php foreach ($students as $student): ?>
                                <option value="<?php echo htmlspecialchars($student['student_id']); ?>"
                                    data-building="<?php echo htmlspecialchars($student['building_code']); ?>"
                                    data-rent="<?php echo htmlspecialchars($student['monthly_rent'] ?? ''); ?>"
                                    <?php echo $formData['student_id'] === $student['student_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($student['full_name'] . ' (' . $student['student_id'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Building (Auto-filled) -->
                    <div>
                        <label for="building_code" class="block text-sm font-medium text-pg-text-primary mb-2">
                            Building <span class="text-status-danger">*</span>
                        </label>
                        <select id="building_code" name="building_code" class="select-field w-full" required>
                            <option value="">Select Building</option>
                            <?php if (!empty($buildingNames)): ?>
                                <?php foreach ($buildingNames as $code => $name): ?>
                                    <option value="<?php echo htmlspecialchars($code); ?>"
                                        <?php echo $formData['building_code'] === $code ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($name); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled>No buildings available</option>
                            <?php endif; ?>
                        </select>
                        <p class="text-xs text-pg-text-secondary mt-1">Auto-filled when student is selected</p>
                    </div>
                </div>
            </div>

            <!-- Payment Details Section -->
            <div>
                <h3 class="text-lg font-semibold text-pg-text-primary mb-4 pb-2 border-b border-pg-border">
                    Payment Details
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Payment Period -->
                    <div>
                        <label for="month_year" class="block text-sm font-medium text-pg-text-primary mb-2">
                            Payment Period <span class="text-status-danger">*</span>
                        </label>
                        <select id="month_year" name="month_year" class="select-field w-full" required>
                            <option value="">Select Payment Period</option>
                            <?php foreach (generateMonthYearOptions() as $option): ?>
                                <option value="<?php echo htmlspecialchars($option['value']); ?>"
                                    <?php echo $formData['month_year'] === $option['value'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($option['label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Payment Method -->
                    <div>
                        <label for="payment_method" class="block text-sm font-medium text-pg-text-primary mb-2">
                            Payment Method <span class="text-status-danger">*</span>
                        </label>
                        <select id="payment_method" name="payment_method" class="select-field w-full" required>
                            <option value="cash" <?php echo $formData['payment_method'] === 'cash' ? 'selected' : ''; ?>>Cash</option>
                            <option value="upi" <?php echo $formData['payment_method'] === 'upi' ? 'selected' : ''; ?>>UPI</option>
                            <option value="bank_transfer" <?php echo $formData['payment_method'] === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                            <option value="cheque" <?php echo $formData['payment_method'] === 'cheque' ? 'selected' : ''; ?>>Cheque</option>
                        </select>
                    </div>

                    <!-- Amount Due -->
                    <div>
                        <label for="amount_due" class="block text-sm font-medium text-pg-text-primary mb-2">
                            Amount Due (₹) <span class="text-status-danger">*</span>
                        </label>
                        <input type="number"
                            id="amount_due"
                            name="amount_due"
                            value="<?php echo htmlspecialchars($formData['amount_due']); ?>"
                            class="input-field w-full"
                            step="0.01"
                            min="0"
                            placeholder="0.00"
                            required>
                        <p class="text-xs text-pg-text-secondary mt-1">Total amount that was due for this period</p>
                    </div>

                    <!-- Amount Paid -->
                    <div>
                        <label for="amount_paid" class="block text-sm font-medium text-pg-text-primary mb-2">
                            Amount Paid (₹) <span class="text-status-danger">*</span>
                        </label>
                        <input type="number"
                            id="amount_paid"
                            name="amount_paid"
                            value="<?php echo htmlspecialchars($formData['amount_paid']); ?>"
                            class="input-field w-full"
                            step="0.01"
                            min="0"
                            placeholder="0.00"
                            required>
                        <p class="text-xs text-pg-text-secondary mt-1">Actual amount received from student</p>
                    </div>

                    <!-- Late Fee -->
                    <div>
                        <label for="late_fee" class="block text-sm font-medium text-pg-text-primary mb-2">
                            Late Fee (₹)
                        </label>
                        <input type="number"
                            id="late_fee"
                            name="late_fee"
                            value="<?php echo htmlspecialchars($formData['late_fee']); ?>"
                            class="input-field w-full"
                            step="0.01"
                            min="0"
                            placeholder="0.00">
                        <p class="text-xs text-pg-text-secondary mt-1">Additional charges for late payment</p>
                    </div>

                    <!-- Payment Status -->
                    <div>
                        <label for="payment_status" class="block text-sm font-medium text-pg-text-primary mb-2">
                            Payment Status <span class="text-status-danger">*</span>
                        </label>
                        <select id="payment_status" name="payment_status" class="select-field w-full" required>
                            <option value="paid" <?php echo $formData['payment_status'] === 'paid' ? 'selected' : ''; ?>>Paid</option>
                            <option value="partial" <?php echo $formData['payment_status'] === 'partial' ? 'selected' : ''; ?>>Partial</option>
                            <option value="pending" <?php echo $formData['payment_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="overdue" <?php echo $formData['payment_status'] === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Additional Information Section -->
            <div>
                <h3 class="text-lg font-semibold text-pg-text-primary mb-4 pb-2 border-b border-pg-border">
                    Additional Information
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Receipt Number -->
                    <div>
                        <label for="receipt_number" class="block text-sm font-medium text-pg-text-primary mb-2">
                            Receipt Number
                        </label>
                        <input type="text"
                            id="receipt_number"
                            name="receipt_number"
                            value="<?php echo htmlspecialchars($formData['receipt_number']); ?>"
                            class="input-field w-full"
                            placeholder="e.g., RCP001, BANK123">
                        <p class="text-xs text-pg-text-secondary mt-1">Official receipt or transaction reference</p>
                    </div>

                    <!-- Payment Balance Display -->
                    <div>
                        <label class="block text-sm font-medium text-pg-text-primary mb-2">Payment Balance</label>
                        <div id="balance_display" class="px-3 py-2 bg-pg-primary bg-opacity-50 rounded-lg text-pg-text-primary font-medium">
                            ₹0.00
                        </div>
                        <p class="text-xs text-pg-text-secondary mt-1">Remaining amount (Due - Paid)</p>
                    </div>

                    <!-- Notes -->
                    <div class="md:col-span-2">
                        <label for="notes" class="block text-sm font-medium text-pg-text-primary mb-2">
                            Notes
                        </label>
                        <textarea id="notes"
                            name="notes"
                            rows="3"
                            class="input-field w-full resize-none"
                            placeholder="Additional notes about this payment..."><?php echo htmlspecialchars($formData['notes']); ?></textarea>
                        <p class="text-xs text-pg-text-secondary mt-1">Any additional information about this payment</p>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex items-center justify-between pt-6 border-t border-pg-border">
                <a href="index.php" class="btn-secondary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                    <span>Cancel</span>
                </a>

                <div class="flex items-center space-x-3">
                    <button type="reset" class="btn-secondary" onclick="return confirm('Are you sure you want to clear all form data?');">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        <span>Clear Form</span>
                    </button>

                    <button type="submit" class="btn-primary">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <span>Record Payment</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Enhanced JavaScript for Form Interactions -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const studentSelect = document.getElementById('student_id');
        const buildingSelect = document.getElementById('building_code');
        const amountDueInput = document.getElementById('amount_due');
        const amountPaidInput = document.getElementById('amount_paid');
        const lateFeeInput = document.getElementById('late_fee');
        const paymentStatusSelect = document.getElementById('payment_status');
        const balanceDisplay = document.getElementById('balance_display');

        // Auto-fill building and rent when student is selected
        studentSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];

            if (selectedOption.value) {
                // Auto-fill building
                const building = selectedOption.getAttribute('data-building');
                if (building) {
                    buildingSelect.value = building;
                }

                // Auto-fill amount due with monthly rent
                const rent = selectedOption.getAttribute('data-rent');
                if (rent && rent !== '') {
                    amountDueInput.value = parseFloat(rent).toFixed(2);
                    amountPaidInput.value = parseFloat(rent).toFixed(2);
                    updateBalance();
                    updatePaymentStatus();
                }
            } else {
                // Clear fields when no student selected
                buildingSelect.value = '';
                amountDueInput.value = '';
                amountPaidInput.value = '';
            }
        });

        // Update balance calculation
        function updateBalance() {
            const due = parseFloat(amountDueInput.value) || 0;
            const paid = parseFloat(amountPaidInput.value) || 0;
            const lateFee = parseFloat(lateFeeInput.value) || 0;
            const balance = due + lateFee - paid;

            balanceDisplay.textContent = '₹' + balance.toFixed(2);

            // Color coding for balance
            if (balance > 0) {
                balanceDisplay.className = 'px-3 py-2 bg-red-500 bg-opacity-20 text-red-400 rounded-lg font-medium';
            } else if (balance < 0) {
                balanceDisplay.className = 'px-3 py-2 bg-blue-500 bg-opacity-20 text-blue-400 rounded-lg font-medium';
            } else {
                balanceDisplay.className = 'px-3 py-2 bg-green-500 bg-opacity-20 text-green-400 rounded-lg font-medium';
            }
        }

        // Auto-update payment status based on amounts
        function updatePaymentStatus() {
            const due = parseFloat(amountDueInput.value) || 0;
            const paid = parseFloat(amountPaidInput.value) || 0;

            if (paid >= due && due > 0) {
                paymentStatusSelect.value = 'paid';
            } else if (paid > 0 && paid < due) {
                paymentStatusSelect.value = 'partial';
            } else if (paid === 0) {
                paymentStatusSelect.value = 'pending';
            }
        }

        // Add event listeners for amount fields
        [amountDueInput, amountPaidInput, lateFeeInput].forEach(input => {
            input.addEventListener('input', updateBalance);
        });

        [amountDueInput, amountPaidInput].forEach(input => {
            input.addEventListener('input', updatePaymentStatus);
        });

        // Initial calculation
        updateBalance();
        updatePaymentStatus();

        // Form validation before submit
        document.querySelector('form').addEventListener('submit', function(e) {
            const due = parseFloat(amountDueInput.value) || 0;
            const paid = parseFloat(amountPaidInput.value) || 0;

            if (due <= 0) {
                alert('Amount due must be greater than zero.');
                e.preventDefault();
                return false;
            }

            if (paid <= 0) {
                alert('Amount paid must be greater than zero.');
                e.preventDefault();
                return false;
            }

            // Confirm if overpayment
            if (paid > due) {
                if (!confirm('Amount paid exceeds amount due. This will create an overpayment. Continue?')) {
                    e.preventDefault();
                    return false;
                }
            }

            return true;
        });
    });
</script>

<?php include '../../includes/footer.php'; ?>