<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

$title = 'Edit Payment';
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
$error = '';
$formData = [];

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
    $formData = $payment; // Initialize form with existing data
    
    // Get students list for dropdown
    $students = $supabase->select('students', 'student_id,full_name,building_code', []);
    usort($students, function($a, $b) {
        return strcmp($a['full_name'], $b['full_name']);
    });
    
    // Update title with payment ID
    $title = 'Edit Payment ' . $payment['payment_id'];
    
} catch (Exception $e) {
    $error = 'Error loading payment data: ' . $e->getMessage();
    error_log('Payment edit load error: ' . $e->getMessage());
    $students = [];
}

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
        'created_by' => $payment['created_by'] ?? getCurrentAdmin()['name'] ?? 'admin'
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
            // Add timestamp for update
            $formData['updated_at'] = date('c');
            
            // Update payment record
            $result = $supabase->update('payments', $formData, ['payment_id' => $paymentId]);
            
            if ($result) {
                flash('success', 'Payment updated successfully!');
                header('Location: view.php?id=' . urlencode($paymentId));
                exit();
            } else {
                $error = 'Failed to update payment. Please try again.';
            }
        } catch (Exception $e) {
            $error = 'Error updating payment: ' . $e->getMessage();
            error_log('Payment update error: ' . $e->getMessage());
        }
    }
}

// Helper function to generate month-year options
function generateMonthYearOptions($startMonths = 12, $futureMonths = 6) {
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

// Helper function to format date for input
function formatDateForInput($date) {
    if (empty($date)) return '';
    try {
        return date('Y-m-d', strtotime($date));
    } catch (Exception $e) {
        return '';
    }
}
?>

<?php include '../../includes/header.php'; ?>

<!-- Edit Payment Form -->
<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex items-center justify-between">
        <div class="flex items-center space-x-4">
            <!-- Back Button -->
            <a href="view.php?id=<?php echo urlencode($paymentId); ?>" class="text-pg-text-secondary hover:text-pg-text-primary transition-colors duration-200">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
            </a>
            
            <div>
                <h1 class="text-2xl font-bold text-pg-text-primary">Edit Payment</h1>
                <p class="text-pg-text-secondary mt-1">
                    Update payment information for <?php echo htmlspecialchars($payment['payment_id'] ?? 'Payment'); ?>
                </p>
            </div>
        </div>
        
        <div class="flex items-center space-x-3">
            <a href="view.php?id=<?php echo urlencode($paymentId); ?>" class="btn-secondary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                </svg>
                <span>View Payment</span>
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

    <!-- Edit Payment Form -->
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
                               value="<?php echo htmlspecialchars($formData['payment_id'] ?? ''); ?>"
                               class="input-field w-full bg-gray-600 cursor-not-allowed"
                               readonly
                               title="Payment ID cannot be changed">
                        <p class="text-xs text-pg-text-secondary mt-1">Payment ID cannot be modified</p>
                    </div>

                    <!-- Payment Date -->
                    <div>
                        <label for="payment_date" class="block text-sm font-medium text-pg-text-primary mb-2">
                            Payment Date <span class="text-status-danger">*</span>
                        </label>
                        <input type="date" 
                               id="payment_date" 
                               name="payment_date" 
                               value="<?php echo formatDateForInput($formData['payment_date'] ?? ''); ?>"
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
                                        <?php echo $formData['student_id'] === $student['student_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($student['full_name'] . ' (' . $student['student_id'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Building -->
                    <div>
                        <label for="building_code" class="block text-sm font-medium text-pg-text-primary mb-2">
                            Building <span class="text-status-danger">*</span>
                        </label>
                        <select id="building_code" name="building_code" class="select-field w-full" required>
                            <option value="">Select Building</option>
                            <?php foreach (BUILDINGS as $code): ?>
                                <option value="<?php echo $code; ?>" 
                                        <?php echo $formData['building_code'] === $code ? 'selected' : ''; ?>>
                                    <?php echo BUILDING_NAMES[$code]; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
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
                            <option value="cash" <?php echo ($formData['payment_method'] ?? '') === 'cash' ? 'selected' : ''; ?>>Cash</option>
                            <option value="upi" <?php echo ($formData['payment_method'] ?? '') === 'upi' ? 'selected' : ''; ?>>UPI</option>
                            <option value="bank_transfer" <?php echo ($formData['payment_method'] ?? '') === 'bank_transfer' ? 'selected' : ''; ?>>Bank Transfer</option>
                            <option value="cheque" <?php echo ($formData['payment_method'] ?? '') === 'cheque' ? 'selected' : ''; ?>>Cheque</option>
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
                               value="<?php echo htmlspecialchars($formData['amount_due'] ?? ''); ?>"
                               class="input-field w-full"
                               step="0.01"
                               min="0"
                               required>
                    </div>

                    <!-- Amount Paid -->
                    <div>
                        <label for="amount_paid" class="block text-sm font-medium text-pg-text-primary mb-2">
                            Amount Paid (₹) <span class="text-status-danger">*</span>
                        </label>
                        <input type="number" 
                               id="amount_paid" 
                               name="amount_paid" 
                               value="<?php echo htmlspecialchars($formData['amount_paid'] ?? ''); ?>"
                               class="input-field w-full"
                               step="0.01"
                               min="0"
                               required>
                    </div>

                    <!-- Late Fee -->
                    <div>
                        <label for="late_fee" class="block text-sm font-medium text-pg-text-primary mb-2">
                            Late Fee (₹)
                        </label>
                        <input type="number" 
                               id="late_fee" 
                               name="late_fee" 
                               value="<?php echo htmlspecialchars($formData['late_fee'] ?? 0); ?>"
                               class="input-field w-full"
                               step="0.01"
                               min="0">
                    </div>

                    <!-- Payment Status -->
                    <div>
                        <label for="payment_status" class="block text-sm font-medium text-pg-text-primary mb-2">
                            Payment Status <span class="text-status-danger">*</span>
                        </label>
                        <select id="payment_status" name="payment_status" class="select-field w-full" required>
                            <option value="paid" <?php echo ($formData['payment_status'] ?? '') === 'paid' ? 'selected' : ''; ?>>Paid</option>
                            <option value="partial" <?php echo ($formData['payment_status'] ?? '') === 'partial' ? 'selected' : ''; ?>>Partial</option>
                            <option value="pending" <?php echo ($formData['payment_status'] ?? '') === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="overdue" <?php echo ($formData['payment_status'] ?? '') === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
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
                               value="<?php echo htmlspecialchars($formData['receipt_number'] ?? ''); ?>"
                               class="input-field w-full"
                               placeholder="e.g., RCP001, BANK123">
                    </div>

                    <!-- Balance Display -->
                    <div>
                        <label class="block text-sm font-medium text-pg-text-primary mb-2">Payment Balance</label>
                        <div id="balance_display" class="px-3 py-2 bg-pg-primary bg-opacity-50 rounded-lg text-pg-text-primary font-medium">
                            ₹0.00
                        </div>
                        <p class="text-xs text-pg-text-secondary mt-1">Remaining amount (Due + Late Fee - Paid)</p>
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
                                  placeholder="Additional notes about this payment..."><?php echo htmlspecialchars($formData['notes'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex items-center justify-between pt-6 border-t border-pg-border">
                <a href="view.php?id=<?php echo urlencode($paymentId); ?>" class="btn-secondary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                    <span>Cancel</span>
                </a>
                
                <div class="flex items-center space-x-3">
                    <button type="reset" class="btn-secondary" onclick="return confirm('Are you sure you want to reset all changes?');">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        <span>Reset</span>
                    </button>
                    
                    <button type="submit" class="btn-primary">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <span>Update Payment</span>
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

    // Auto-fill building when student is selected
    studentSelect.addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        
        if (selectedOption.value) {
            const building = selectedOption.getAttribute('data-building');
            if (building) {
                buildingSelect.value = building;
            }
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
});
</script>

<?php include '../../includes/footer.php'; ?>
