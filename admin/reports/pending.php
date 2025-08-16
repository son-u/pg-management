<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

$title = 'Pending Payments';
require_once '../../includes/auth_check.php';

// Initialize variables
$error = '';
$selectedBuilding = $_GET['building'] ?? 'all';
$selectedStatus = $_GET['status'] ?? 'all';
$sortBy = $_GET['sort'] ?? 'balance_desc';

try {
    $supabase = supabase();

    // Get all payments
    $allPayments = $supabase->select('payments', '*', []);

    // Get all students for additional information
    $allStudents = $supabase->select('students', '*', []);
    $studentsById = array_column($allStudents, null, 'student_id');

    // Filter payments with outstanding balance
    $pendingPayments = [];
    $totalPendingAmount = 0;
    $totalPendingPayments = 0;

    foreach ($allPayments as $payment) {
        $due = floatval($payment['amount_due'] ?? 0);
        $paid = floatval($payment['amount_paid'] ?? 0);
        $lateFee = floatval($payment['late_fee'] ?? 0);
        $balance = ($due + $lateFee) - $paid;

        if ($balance > 0) {
            $payment['pending_balance'] = $balance;
            $payment['student_info'] = $studentsById[$payment['student_id']] ?? null;

            // Apply building filter
            if ($selectedBuilding !== 'all' && $payment['building_code'] !== $selectedBuilding) {
                continue;
            }

            // Apply status filter
            if ($selectedStatus !== 'all') {
                if ($selectedStatus === 'partial' && $payment['payment_status'] !== 'partial') continue;
                if ($selectedStatus === 'pending' && $payment['payment_status'] !== 'pending') continue;
                if ($selectedStatus === 'overdue' && $payment['payment_status'] !== 'overdue') continue;
            }

            $pendingPayments[] = $payment;
            $totalPendingAmount += $balance;
            $totalPendingPayments++;
        }
    }

    // Sort payments
    switch ($sortBy) {
        case 'balance_desc':
            usort($pendingPayments, function ($a, $b) {
                return $b['pending_balance'] <=> $a['pending_balance'];
            });
            break;
        case 'balance_asc':
            usort($pendingPayments, function ($a, $b) {
                return $a['pending_balance'] <=> $b['pending_balance'];
            });
            break;
        case 'date_desc':
            usort($pendingPayments, function ($a, $b) {
                return strtotime($b['payment_date'] ?? '2000-01-01') <=> strtotime($a['payment_date'] ?? '2000-01-01');
            });
            break;
        case 'date_asc':
            usort($pendingPayments, function ($a, $b) {
                return strtotime($a['payment_date'] ?? '2000-01-01') <=> strtotime($b['payment_date'] ?? '2000-01-01');
            });
            break;
        case 'student':
            usort($pendingPayments, function ($a, $b) {
                return strcmp($a['student_id'], $b['student_id']);
            });
            break;
    }

    // Calculate building-wise breakdown
    $buildingBreakdown = [];
    foreach ($pendingPayments as $payment) {
        $building = $payment['building_code'];
        if (!isset($buildingBreakdown[$building])) {
            $buildingBreakdown[$building] = [
                'count' => 0,
                'amount' => 0
            ];
        }
        $buildingBreakdown[$building]['count']++;
        $buildingBreakdown[$building]['amount'] += $payment['pending_balance'];
    }
} catch (Exception $e) {
    $error = 'Error loading pending payments: ' . $e->getMessage();
    error_log('Pending payments error: ' . $e->getMessage());
    $pendingPayments = [];
    $totalPendingAmount = 0;
    $totalPendingPayments = 0;
    $buildingBreakdown = [];
}

// Helper functions
function formatCurrency($amount)
{
    return '₹' . number_format(floatval($amount), 2);
}

function formatDate($date)
{
    if (empty($date)) return '-';
    return date('M d, Y', strtotime($date));
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

function getDaysOverdue($paymentDate, $monthYear)
{
    if (empty($paymentDate)) return 0;

    $paymentTime = strtotime($paymentDate);
    $currentTime = time();
    $daysDiff = floor(($currentTime - $paymentTime) / (24 * 60 * 60));

    return max(0, $daysDiff);
}
?>

<?php include '../../includes/header.php'; ?>

<!-- Pending Payments Report -->
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
                <h1 class="text-2xl font-bold text-pg-text-primary">Pending Payments</h1>
                <p class="text-pg-text-secondary mt-1">
                    Track and manage outstanding payment balances
                </p>
            </div>
        </div>

        <div class="flex items-center space-x-3">
            <a href="export.php?type=pending" class="btn-secondary">
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

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Total Pending Amount -->
        <div class="card text-center">
            <div class="text-3xl font-bold text-status-danger mb-2">
                <?php echo formatCurrency($totalPendingAmount); ?>
            </div>
            <h3 class="text-lg font-semibold text-pg-text-primary mb-1">Total Pending</h3>
            <p class="text-sm text-pg-text-secondary">Outstanding amount</p>
        </div>

        <!-- Number of Pending Payments -->
        <div class="card text-center">
            <div class="text-3xl font-bold text-status-danger mb-2">
                <?php echo number_format($totalPendingPayments); ?>
            </div>
            <h3 class="text-lg font-semibold text-pg-text-primary mb-1">Pending Payments</h3>
            <p class="text-sm text-pg-text-secondary">Require follow-up</p>
        </div>

        <!-- Average Pending Amount -->
        <div class="card text-center">
            <div class="text-3xl font-bold text-status-danger mb-2">
                <?php echo formatCurrency($totalPendingPayments > 0 ? $totalPendingAmount / $totalPendingPayments : 0); ?>
            </div>
            <h3 class="text-lg font-semibold text-pg-text-primary mb-1">Average Balance</h3>
            <p class="text-sm text-pg-text-secondary">Per pending payment</p>
        </div>
    </div>

    <!-- Building-wise Breakdown -->
    <?php if (!empty($buildingBreakdown)): ?>
        <div class="card">
            <h3 class="text-lg font-semibold text-pg-text-primary mb-4 pb-2 border-b border-pg-border">
                Building-wise Pending Amounts
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($buildingBreakdown as $buildingCode => $data): ?>
                    <div class="p-4 bg-red-500 bg-opacity-10 border border-red-500 border-opacity-30 rounded-lg">
                        <div class="font-semibold text-pg-text-primary mb-1">
                            <?php echo BUILDING_NAMES[$buildingCode] ?? $buildingCode; ?>
                        </div>
                        <div class="text-status-danger font-bold text-xl">
                            <?php echo formatCurrency($data['amount']); ?>
                        </div>
                        <div class="text-sm text-pg-text-secondary">
                            <?php echo number_format($data['count']); ?> pending payments
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Filters and Sorting -->
    <div class="card">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
            <!-- Building Filter -->
            <div>
                <label for="building" class="block text-sm font-medium text-pg-text-primary mb-2">
                    Building
                </label>
                <select id="building" name="building" class="select-field w-full">
                    <option value="all" <?php echo $selectedBuilding === 'all' ? 'selected' : ''; ?>>All Buildings</option>
                    <?php foreach (BUILDINGS as $code): ?>
                        <option value="<?php echo $code; ?>" <?php echo $selectedBuilding === $code ? 'selected' : ''; ?>>
                            <?php echo BUILDING_NAMES[$code]; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Status Filter -->
            <div>
                <label for="status" class="block text-sm font-medium text-pg-text-primary mb-2">
                    Status
                </label>
                <select id="status" name="status" class="select-field w-full">
                    <option value="all" <?php echo $selectedStatus === 'all' ? 'selected' : ''; ?>>All Status</option>
                    <option value="pending" <?php echo $selectedStatus === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="partial" <?php echo $selectedStatus === 'partial' ? 'selected' : ''; ?>>Partial</option>
                    <option value="overdue" <?php echo $selectedStatus === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                </select>
            </div>

            <!-- Sort By -->
            <div>
                <label for="sort" class="block text-sm font-medium text-pg-text-primary mb-2">
                    Sort By
                </label>
                <select id="sort" name="sort" class="select-field w-full">
                    <option value="balance_desc" <?php echo $sortBy === 'balance_desc' ? 'selected' : ''; ?>>Highest Amount</option>
                    <option value="balance_asc" <?php echo $sortBy === 'balance_asc' ? 'selected' : ''; ?>>Lowest Amount</option>
                    <option value="date_desc" <?php echo $sortBy === 'date_desc' ? 'selected' : ''; ?>>Newest First</option>
                    <option value="date_asc" <?php echo $sortBy === 'date_asc' ? 'selected' : ''; ?>>Oldest First</option>
                    <option value="student" <?php echo $sortBy === 'student' ? 'selected' : ''; ?>>Student ID</option>
                </select>
            </div>

            <!-- Filter Actions -->
            <div class="flex space-x-2">
                <button type="submit" class="btn-primary">Apply</button>
                <a href="pending.php" class="btn-secondary">Reset</a>
            </div>
        </form>
    </div>

    <!-- Pending Payments Table -->
    <div class="card">
        <h3 class="text-lg font-semibold text-pg-text-primary mb-4 pb-2 border-b border-pg-border">
            Outstanding Payments (<?php echo number_format(count($pendingPayments)); ?>)
        </h3>

        <?php if (empty($pendingPayments)): ?>
            <div class="text-center py-12">
                <div class="flex flex-col items-center">
                    <svg class="w-16 h-16 text-pg-text-secondary mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <h3 class="text-lg font-semibold text-pg-text-primary mb-2">All Payments Up to Date!</h3>
                    <p class="text-pg-text-secondary">No pending payments found with current filters</p>
                </div>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="border-b border-pg-border">
                            <th class="table-header">Payment ID</th>
                            <th class="table-header">Student</th>
                            <th class="table-header">Building</th>
                            <th class="table-header">Period</th>
                            <th class="table-header text-right">Due</th>
                            <th class="table-header text-right">Paid</th>
                            <th class="table-header text-right">Balance</th>
                            <th class="table-header">Status</th>
                            <th class="table-header">Days</th>
                            <th class="table-header text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingPayments as $payment): ?>
                            <?php
                            $student = $payment['student_info'];
                            $daysOverdue = getDaysOverdue($payment['payment_date'], $payment['month_year']);
                            ?>
                            <tr class="border-b border-pg-border hover:bg-pg-hover transition-colors duration-200">
                                <td class="px-6 py-4 font-mono text-sm">
                                    <?php echo htmlspecialchars($payment['payment_id']); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div>
                                        <div class="font-medium text-pg-text-primary">
                                            <?php echo htmlspecialchars($student['full_name'] ?? $payment['student_id']); ?>
                                        </div>
                                        <div class="text-sm text-pg-text-secondary">
                                            <?php echo htmlspecialchars($payment['student_id']); ?>
                                            <?php if ($student && !empty($student['phone'])): ?>
                                                • <?php echo htmlspecialchars($student['phone']); ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-medium text-pg-text-primary">
                                        <?php echo BUILDING_NAMES[$payment['building_code']] ?? $payment['building_code']; ?>
                                    </div>
                                    <?php if ($student && !empty($student['room_number'])): ?>
                                        <div class="text-sm text-pg-text-secondary">
                                            Room <?php echo htmlspecialchars($student['room_number']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <?php echo htmlspecialchars($payment['month_year']); ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="font-semibold text-pg-text-primary">
                                        <?php echo formatCurrency($payment['amount_due']); ?>
                                    </div>
                                    <?php if ($payment['late_fee'] > 0): ?>
                                        <div class="text-xs text-status-danger">
                                            +<?php echo formatCurrency($payment['late_fee']); ?> late fee
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-right font-semibold text-pg-accent">
                                    <?php echo formatCurrency($payment['amount_paid']); ?>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <div class="font-bold text-status-danger">
                                        <?php echo formatCurrency($payment['pending_balance']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="<?php echo getStatusBadge($payment['payment_status']); ?>">
                                        <?php echo ucfirst($payment['payment_status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-opacity-20 <?php echo $daysOverdue > 30 ? 'bg-red-500 text-red-400' : ($daysOverdue > 7 ? 'bg-yellow-500 text-yellow-400' : 'bg-gray-500 text-gray-400'); ?>">
                                        <?php echo $daysOverdue; ?> days
                                    </span>
                                </td>

                                <td class="px-6 py-4 text-center">
                                    <div class="flex items-center justify-center space-x-1">
                                        <a href="../payments/view.php?id=<?php echo urlencode($payment['payment_id']); ?>"
                                            class="text-blue-400 hover:text-blue-300 transition-colors duration-200 p-1"
                                            title="View Payment">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                            </svg>
                                        </a>
                                        <a href="../payments/edit.php?id=<?php echo urlencode($payment['payment_id']); ?>"
                                            class="text-yellow-400 hover:text-yellow-300 transition-colors duration-200 p-1"
                                            title="Edit Payment">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                        </a>
                                        <?php if ($student && !empty($student['phone'])): ?>
                                            <a href="tel:<?php echo htmlspecialchars($student['phone']); ?>"
                                                class="text-green-400 hover:text-green-300 transition-colors duration-200 p-1"
                                                title="Call Student">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                                </svg>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Quick Actions -->
    <div class="card">
        <h3 class="text-lg font-semibold text-pg-text-primary mb-4 pb-2 border-b border-pg-border">
            Follow-up Actions
        </h3>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
            <a href="overdue.php" class="p-4 bg-red-500 bg-opacity-10 border border-red-500 border-opacity-30 rounded-lg hover:bg-red-500 hover:bg-opacity-20 transition-colors duration-200 text-center">
                <svg class="w-8 h-8 text-status-danger mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div class="font-medium text-status-danger">Overdue Payments</div>
                <div class="text-sm text-pg-text-secondary">Critical follow-ups</div>
            </a>

            <a href="../payments/add.php" class="p-4 bg-pg-primary bg-opacity-50 rounded-lg hover:bg-pg-hover transition-colors duration-200 text-center">
                <svg class="w-8 h-8 text-pg-accent mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                <div class="font-medium text-pg-text-primary">Record Payment</div>
                <div class="text-sm text-pg-text-secondary">Update payment status</div>
            </a>

            <a href="export.php?type=pending_detailed" class="p-4 bg-pg-primary bg-opacity-50 rounded-lg hover:bg-pg-hover transition-colors duration-200 text-center">
                <svg class="w-8 h-8 text-pg-accent mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <div class="font-medium text-pg-text-primary">Export Report</div>
                <div class="text-sm text-pg-text-secondary">For follow-up calls</div>
            </a>

            <a href="monthly.php" class="p-4 bg-pg-primary bg-opacity-50 rounded-lg hover:bg-pg-hover transition-colors duration-200 text-center">
                <svg class="w-8 h-8 text-pg-accent mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                <div class="font-medium text-pg-text-primary">Monthly Analysis</div>
                <div class="text-sm text-pg-text-secondary">Payment trends</div>
            </a>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>