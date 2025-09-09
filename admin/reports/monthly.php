<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

$title = 'Monthly Payment Report';
require_once '../../includes/auth_check.php';

// Initialize variables
$error = '';
$selectedMonthYear = $_GET['month_year'] ?? date('Y-m');

// Get buildings data using the new Buildings class
try {
    $buildingCodes = Buildings::getCodes();
    $buildingNames = Buildings::getNames();
} catch (Exception $e) {
    error_log('Monthly report buildings error: ' . $e->getMessage());
    $buildingCodes = [];
    $buildingNames = [];
}

// Function to get monthly rent for a student from their record
function getMonthlyRentForStudent($student) {
    return floatval($student['monthly_rent'] ?? 5000.00);
}

try {
    $supabase = supabase();
    
    if (!$supabase) {
        throw new Exception('Failed to connect to database');
    }

    // Get all payments for available months dropdown
    $allPayments = $supabase->select('payments', '*', []);
    
    if ($allPayments === false) {
        $allPayments = [];
    }

    // Get payments specifically for selected month
    $monthlyPayments = $supabase->select('payments', '*', ['month_year' => $selectedMonthYear]);
    
    if ($monthlyPayments === false) {
        $monthlyPayments = [];
    }

    // Get active students for pending calculation
    $allActiveStudents = $supabase->select('students', 'student_id,full_name,building_code,monthly_rent', ['status' => 'active']);
    
    if ($allActiveStudents === false) {
        $allActiveStudents = [];
    }

    // Create mapping of payments by student ID
    $paymentsByStudent = array_column($monthlyPayments, null, 'student_id');

    // Get available months for dropdown
    $availableMonths = array_unique(array_map(function ($p) {
        return $p['month_year'];
    }, $allPayments));
    
    // Add current month if not in payments
    $currentMonth = date('Y-m');
    if (!in_array($currentMonth, $availableMonths)) {
        $availableMonths[] = $currentMonth;
    }
    
    rsort($availableMonths);

    // Calculate simple metrics
    $metrics = [
        'total_payments' => count($monthlyPayments),
        'total_collected' => 0,
        'total_pending' => 0
    ];

    $statusBreakdown = ['paid' => 0, 'partial' => 0, 'no_record' => 0];
    $methodBreakdown = ['cash' => 0, 'upi' => 0, 'bank_transfer' => 0, 'cheque' => 0, 'not_paid' => 0];

    // Calculate collected amount from payments
    foreach ($monthlyPayments as $payment) {
        $paid = floatval($payment['amount_paid'] ?? 0);
        $metrics['total_collected'] += $paid;
        
        // Payment method breakdown
        $method = strtolower($payment['payment_method'] ?? 'cash');
        if (isset($methodBreakdown[$method])) {
            $methodBreakdown[$method]++;
        }
    }

    // Calculate pending amount (only for current month or selected month)
    foreach ($allActiveStudents as $student) {
        $studentId = $student['student_id'];
        $monthlyRent = getMonthlyRentForStudent($student);
        
        if (isset($paymentsByStudent[$studentId])) {
            $payment = $paymentsByStudent[$studentId];
            $due = floatval($payment['amount_due'] ?? $monthlyRent);
            $paid = floatval($payment['amount_paid'] ?? 0);
            $lateFee = floatval($payment['late_fee'] ?? 0);
            $balance = ($due + $lateFee) - $paid;
            
            if ($balance > 0.01) {
                $metrics['total_pending'] += $balance;
                $statusBreakdown['partial']++;
            } else {
                $statusBreakdown['paid']++;
            }
        } else {
            // Student has no payment record - completely pending
            $metrics['total_pending'] += $monthlyRent;
            $statusBreakdown['no_record']++;
            $methodBreakdown['not_paid']++;
        }
    }

    // Create detailed payment list for display
    $detailedPayments = [];
    foreach ($allActiveStudents as $student) {
        $studentId = $student['student_id'];
        $monthlyRent = getMonthlyRentForStudent($student);
        
        if (isset($paymentsByStudent[$studentId])) {
            $payment = $paymentsByStudent[$studentId];
            $payment['student_info'] = $student;
            $payment['expected_amount'] = $monthlyRent;
            $detailedPayments[] = $payment;
        } else {
            // Create virtual payment record for non-paying students
            $virtualPayment = [
                'payment_id' => 'NO_RECORD_' . $studentId . '_' . $selectedMonthYear,
                'student_id' => $studentId,
                'building_code' => $student['building_code'],
                'month_year' => $selectedMonthYear,
                'amount_due' => $monthlyRent,
                'amount_paid' => 0,
                'late_fee' => 0,
                'payment_status' => 'no_record',
                'payment_method' => null,
                'payment_date' => null,
                'student_info' => $student,
                'expected_amount' => $monthlyRent
            ];
            $detailedPayments[] = $virtualPayment;
        }
    }

    // Sort by payment status (paid first, then partial, then no record)
    usort($detailedPayments, function($a, $b) {
        $statusOrder = ['paid' => 1, 'partial' => 2, 'no_record' => 3];
        $aStatus = $a['payment_status'] ?? 'no_record';
        $bStatus = $b['payment_status'] ?? 'no_record';
        
        if ($statusOrder[$aStatus] !== $statusOrder[$bStatus]) {
            return $statusOrder[$aStatus] <=> $statusOrder[$bStatus];
        }
        
        return strcmp($a['student_info']['full_name'], $b['student_info']['full_name']);
    });

} catch (Exception $e) {
    $error = 'Error loading monthly report: ' . $e->getMessage();
    error_log('Monthly report error: ' . $e->getMessage());

    // Default values
    $monthlyPayments = [];
    $detailedPayments = [];
    $availableMonths = [date('Y-m')];
    $metrics = [
        'total_payments' => 0,
        'total_collected' => 0,
        'total_pending' => 0
    ];
    $statusBreakdown = ['paid' => 0, 'partial' => 0, 'no_record' => 0];
    $methodBreakdown = ['cash' => 0, 'upi' => 0, 'bank_transfer' => 0, 'cheque' => 0, 'not_paid' => 0];
}

// Helper functions
function formatCurrency($amount) {
    return '₹' . number_format(floatval($amount), 2);
}

function formatDate($date) {
    if (empty($date)) return '-';
    return date('M d, Y', strtotime($date));
}

function getStatusBadge($status) {
    $badges = [
        'paid' => 'status-badge status-active',
        'pending' => 'status-badge bg-yellow-500 bg-opacity-20 text-yellow-400',
        'partial' => 'status-badge bg-blue-500 bg-opacity-20 text-blue-400',
        'overdue' => 'status-badge status-inactive',
        'no_record' => 'status-badge bg-red-500 bg-opacity-20 text-red-400'
    ];
    return $badges[$status] ?? 'status-badge bg-gray-500 bg-opacity-20 text-gray-400';
}
?>

<?php include '../../includes/header.php'; ?>

<!-- Monthly Payment Report -->
<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex items-center justify-between">
        <div class="flex items-center space-x-4">
            <!-- Back Button -->
            <a href="overview.php" class="text-pg-text-secondary hover:text-pg-text-primary transition-colors duration-200">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
            </a>

            <div>
                <h1 class="text-2xl font-bold text-pg-text-primary">Monthly Payment Report</h1>
                <p class="text-pg-text-secondary mt-1">
                    Payment summary for <?php echo date('F Y', strtotime($selectedMonthYear . '-01')); ?>
                </p>
            </div>
        </div>

        <div class="flex items-center space-x-3">
            <a href="export.php?type=monthly&month=<?php echo urlencode($selectedMonthYear); ?>" class="btn-secondary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <span>Export</span>
            </a>
        </div>
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

    <!-- Month Selector -->
    <div class="card">
        <form method="GET" class="flex items-center space-x-4">
            <div class="flex-1 max-w-xs">
                <label for="month_year" class="block text-sm font-medium text-pg-text-primary mb-2">
                    Select Month
                </label>
                <select id="month_year" name="month_year" class="select-field w-full" onchange="this.form.submit()">
                    <?php foreach ($availableMonths as $month): ?>
                        <option value="<?php echo htmlspecialchars($month); ?>"
                            <?php echo $selectedMonthYear === $month ? 'selected' : ''; ?>>
                            <?php echo date('F Y', strtotime($month . '-01')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>

    <!-- Key Metrics -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Total Payments -->
        <div class="card text-center">
            <div class="text-3xl font-bold text-pg-accent mb-2">
                <?php echo number_format($metrics['total_payments']); ?>
            </div>
            <h3 class="text-lg font-semibold text-pg-text-primary mb-1">Total Payments</h3>
            <p class="text-sm text-pg-text-secondary">Payment records this month</p>
        </div>

        <!-- Amount Collected -->
        <div class="card text-center">
            <div class="text-3xl font-bold text-pg-accent mb-2">
                <?php echo formatCurrency($metrics['total_collected']); ?>
            </div>
            <h3 class="text-lg font-semibold text-pg-text-primary mb-1">Amount Collected</h3>
            <p class="text-sm text-pg-text-secondary">Total money received</p>
        </div>

        <!-- Pending Amount -->
        <div class="card text-center">
            <div class="text-3xl font-bold <?php echo $metrics['total_pending'] > 0 ? 'text-status-danger' : 'text-pg-accent'; ?> mb-2">
                <?php echo formatCurrency($metrics['total_pending']); ?>
            </div>
            <h3 class="text-lg font-semibold text-pg-text-primary mb-1">Pending Amount</h3>
            <p class="text-sm text-pg-text-secondary">Outstanding this month</p>
        </div>
    </div>

    <!-- Analytics Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Payment Status Breakdown -->
        <div class="card">
            <h3 class="text-lg font-semibold text-pg-text-primary mb-4 pb-2 border-b border-pg-border">
                Student Payment Status
            </h3>

            <div class="space-y-3">
                <div class="flex items-center justify-between p-3 bg-green-500 bg-opacity-10 border border-green-500 border-opacity-30 rounded-lg">
                    <div class="flex items-center">
                        <span class="status-badge status-active mr-3">Paid</span>
                        <span class="text-sm text-pg-text-secondary">Fully paid students</span>
                    </div>
                    <div class="font-semibold text-green-400">
                        <?php echo number_format($statusBreakdown['paid']); ?>
                    </div>
                </div>

                <div class="flex items-center justify-between p-3 bg-blue-500 bg-opacity-10 border border-blue-500 border-opacity-30 rounded-lg">
                    <div class="flex items-center">
                        <span class="status-badge bg-blue-500 bg-opacity-20 text-blue-400 mr-3">Partial</span>
                        <span class="text-sm text-pg-text-secondary">Partial payments</span>
                    </div>
                    <div class="font-semibold text-blue-400">
                        <?php echo number_format($statusBreakdown['partial']); ?>
                    </div>
                </div>

                <div class="flex items-center justify-between p-3 bg-red-500 bg-opacity-10 border border-red-500 border-opacity-30 rounded-lg">
                    <div class="flex items-center">
                        <span class="status-badge bg-red-500 bg-opacity-20 text-red-400 mr-3">No Record</span>
                        <span class="text-sm text-pg-text-secondary">Haven't paid yet</span>
                    </div>
                    <div class="font-semibold text-red-400">
                        <?php echo number_format($statusBreakdown['no_record']); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Method Analysis -->
        <div class="card">
            <h3 class="text-lg font-semibold text-pg-text-primary mb-4 pb-2 border-b border-pg-border">
                Payment Methods
            </h3>

            <div class="space-y-3">
                <?php foreach ($methodBreakdown as $method => $count): ?>
                    <?php if ($count > 0): ?>
                        <div class="flex items-center justify-between p-3 bg-pg-primary bg-opacity-50 rounded-lg">
                            <span class="text-pg-text-primary capitalize">
                                <?php 
                                $methodLabels = [
                                    'cash' => 'Cash',
                                    'upi' => 'UPI',
                                    'bank_transfer' => 'Bank Transfer',
                                    'cheque' => 'Cheque',
                                    'not_paid' => 'Not Paid'
                                ];
                                echo $methodLabels[$method] ?? ucfirst(str_replace('_', ' ', $method)); 
                                ?>
                            </span>
                            <div class="flex items-center space-x-2">
                                <span class="font-semibold text-pg-text-primary"><?php echo number_format($count); ?></span>
                                <span class="text-sm text-pg-text-secondary">
                                    (<?php echo count($detailedPayments) > 0 ? round(($count / count($detailedPayments)) * 100, 1) : 0; ?>%)
                                </span>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Detailed Student List -->
    <div class="card">
        <h3 class="text-lg font-semibold text-pg-text-primary mb-4 pb-2 border-b border-pg-border">
            All Students - <?php echo date('F Y', strtotime($selectedMonthYear . '-01')); ?>
            <span class="text-sm text-pg-text-secondary font-normal ml-2">
                (<?php echo number_format(count($detailedPayments)); ?> students)
            </span>
        </h3>

        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr class="border-b border-pg-border">
                        <th class="table-header">Student</th>
                        <th class="table-header">Building</th>
                        <th class="table-header text-right">Amount</th>
                        <th class="table-header">Status</th>
                        <th class="table-header">Method</th>
                        <th class="table-header">Date</th>
                        <th class="table-header text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($detailedPayments)): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <svg class="w-12 h-12 text-pg-text-secondary mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                                    </svg>
                                    <p class="text-pg-text-secondary">No students found for this month</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($detailedPayments as $record): ?>
                            <?php 
                            $student = $record['student_info'];
                            $isNoRecord = strpos($record['payment_id'], 'NO_RECORD_') === 0;
                            $amountPaid = floatval($record['amount_paid'] ?? 0);
                            $amountDue = floatval($record['amount_due'] ?? 0);
                            $lateFee = floatval($record['late_fee'] ?? 0);
                            $pending = ($amountDue + $lateFee) - $amountPaid;
                            ?>
                            <tr class="border-b border-pg-border hover:bg-pg-hover transition-colors duration-200">
                                <td class="px-6 py-4">
                                    <div>
                                        <div class="font-medium text-pg-text-primary">
                                            <?php echo htmlspecialchars($student['full_name']); ?>
                                        </div>
                                        <div class="text-sm text-pg-text-secondary">
                                            <?php echo htmlspecialchars($student['student_id']); ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <?php echo htmlspecialchars($buildingNames[$record['building_code']] ?? $record['building_code']); ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="font-semibold text-pg-accent">
                                        <?php echo formatCurrency($amountPaid); ?>
                                    </div>
                                    <?php if ($pending > 0): ?>
                                        <div class="text-xs text-red-400">
                                            Pending: <?php echo formatCurrency($pending); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="<?php echo getStatusBadge($record['payment_status']); ?>">
                                        <?php 
                                        $statusLabels = [
                                            'paid' => 'Paid',
                                            'partial' => 'Partial', 
                                            'pending' => 'Pending',
                                            'no_record' => 'No Record'
                                        ];
                                        echo $statusLabels[$record['payment_status']] ?? ucfirst($record['payment_status']); 
                                        ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if (!$isNoRecord && !empty($record['payment_method'])): ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-pg-accent bg-opacity-20 text-pg-accent">
                                            <?php echo ucfirst(str_replace('_', ' ', $record['payment_method'])); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-sm text-pg-text-secondary">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <?php echo $isNoRecord ? '-' : formatDate($record['payment_date']); ?>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <div class="flex items-center justify-center space-x-1">
                                        <?php if (!$isNoRecord): ?>
                                            <a href="../payments/view.php?id=<?php echo urlencode($record['payment_id']); ?>"
                                                class="text-blue-400 hover:text-blue-300 transition-colors duration-200 p-1"
                                                title="View Payment">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                                </svg>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($pending > 0.01 || $isNoRecord): ?>
                                            <a href="../payments/add.php?student_id=<?php echo urlencode($record['student_id']); ?>&amount=<?php echo urlencode($isNoRecord ? $record['expected_amount'] : $pending); ?>&month=<?php echo urlencode($record['month_year']); ?>"
                                                class="text-green-400 hover:text-green-300 transition-colors duration-200 p-1"
                                                title="Record Payment">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                                </svg>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
