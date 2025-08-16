<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

$title = 'Overdue Payments';
require_once '../../includes/auth_check.php';

// Initialize variables
$error = '';
$selectedBuilding = $_GET['building'] ?? 'all';
$urgencyFilter = $_GET['urgency'] ?? 'all';
$sortBy = $_GET['sort'] ?? 'days_desc';

try {
    $supabase = supabase();

    // Get all payments
    $allPayments = $supabase->select('payments', '*', []);

    // Get all students for additional information
    $allStudents = $supabase->select('students', '*', []);
    $studentsById = array_column($allStudents, null, 'student_id');

    // Filter overdue payments (past due date with outstanding balance)
    $overduePayments = [];
    $totalOverdueAmount = 0;
    $totalOverduePayments = 0;
    $currentDate = new DateTime();

    foreach ($allPayments as $payment) {
        $due = floatval($payment['amount_due'] ?? 0);
        $paid = floatval($payment['amount_paid'] ?? 0);
        $lateFee = floatval($payment['late_fee'] ?? 0);
        $balance = ($due + $lateFee) - $paid;

        if ($balance > 0) {
            // Calculate due date (end of payment month)
            $monthYear = $payment['month_year'] ?? '';
            if ($monthYear) {
                $dueDate = new DateTime($monthYear . '-01');
                $dueDate->modify('last day of this month');

                // Check if payment is overdue
                if ($currentDate > $dueDate) {
                    $daysOverdue = $currentDate->diff($dueDate)->days;

                    $payment['overdue_balance'] = $balance;
                    $payment['days_overdue'] = $daysOverdue;
                    $payment['due_date'] = $dueDate->format('Y-m-d');
                    $payment['student_info'] = $studentsById[$payment['student_id']] ?? null;

                    // Determine urgency level
                    if ($daysOverdue > 60) {
                        $payment['urgency'] = 'critical';
                    } elseif ($daysOverdue > 30) {
                        $payment['urgency'] = 'high';
                    } elseif ($daysOverdue > 7) {
                        $payment['urgency'] = 'medium';
                    } else {
                        $payment['urgency'] = 'low';
                    }

                    // Apply building filter
                    if ($selectedBuilding !== 'all' && $payment['building_code'] !== $selectedBuilding) {
                        continue;
                    }

                    // Apply urgency filter
                    if ($urgencyFilter !== 'all' && $payment['urgency'] !== $urgencyFilter) {
                        continue;
                    }

                    $overduePayments[] = $payment;
                    $totalOverdueAmount += $balance;
                    $totalOverduePayments++;
                }
            }
        }
    }

    // Sort payments
    switch ($sortBy) {
        case 'days_desc':
            usort($overduePayments, function ($a, $b) {
                return $b['days_overdue'] <=> $a['days_overdue'];
            });
            break;
        case 'days_asc':
            usort($overduePayments, function ($a, $b) {
                return $a['days_overdue'] <=> $b['days_overdue'];
            });
            break;
        case 'amount_desc':
            usort($overduePayments, function ($a, $b) {
                return $b['overdue_balance'] <=> $a['overdue_balance'];
            });
            break;
        case 'amount_asc':
            usort($overduePayments, function ($a, $b) {
                return $a['overdue_balance'] <=> $b['overdue_balance'];
            });
            break;
        case 'urgency':
            $urgencyOrder = ['critical' => 4, 'high' => 3, 'medium' => 2, 'low' => 1];
            usort($overduePayments, function ($a, $b) use ($urgencyOrder) {
                return $urgencyOrder[$b['urgency']] <=> $urgencyOrder[$a['urgency']];
            });
            break;
    }

    // Calculate urgency breakdown
    $urgencyBreakdown = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
    $buildingBreakdown = [];

    foreach ($overduePayments as $payment) {
        $urgencyBreakdown[$payment['urgency']]++;

        $building = $payment['building_code'];
        if (!isset($buildingBreakdown[$building])) {
            $buildingBreakdown[$building] = [
                'count' => 0,
                'amount' => 0
            ];
        }
        $buildingBreakdown[$building]['count']++;
        $buildingBreakdown[$building]['amount'] += $payment['overdue_balance'];
    }
} catch (Exception $e) {
    $error = 'Error loading overdue payments: ' . $e->getMessage();
    error_log('Overdue payments error: ' . $e->getMessage());
    $overduePayments = [];
    $totalOverdueAmount = 0;
    $totalOverduePayments = 0;
    $urgencyBreakdown = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
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

function getUrgencyBadge($urgency)
{
    $badges = [
        'critical' => 'bg-red-500 bg-opacity-20 text-red-300 border border-red-500',
        'high' => 'bg-orange-500 bg-opacity-20 text-orange-300 border border-orange-500',
        'medium' => 'bg-yellow-500 bg-opacity-20 text-yellow-300 border border-yellow-500',
        'low' => 'bg-blue-500 bg-opacity-20 text-blue-300 border border-blue-500'
    ];
    return $badges[$urgency] ?? 'bg-gray-500 bg-opacity-20 text-gray-400';
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

function getUrgencyColor($urgency)
{
    $colors = [
        'critical' => 'text-red-400',
        'high' => 'text-orange-400',
        'medium' => 'text-yellow-400',
        'low' => 'text-blue-400'
    ];
    return $colors[$urgency] ?? 'text-gray-400';
}
?>

<?php include '../../includes/header.php'; ?>

<!-- Overdue Payments Report -->
<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex items-center justify-between">
        <div class="flex items-center space-x-4">
            <!-- Back Button -->
            <a href="pending.php" class="text-pg-text-secondary hover:text-pg-text-primary transition-colors duration-200">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
            </a>

            <div>
                <h1 class="text-2xl font-bold text-pg-text-primary">Overdue Payments</h1>
                <p class="text-pg-text-secondary mt-1">
                    Critical follow-ups required • Past due date payments
                </p>
            </div>
        </div>

        <div class="flex items-center space-x-3">
            <a href="export.php?type=overdue" class="btn-secondary">
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

    <!-- Critical Alert -->
    <?php if ($urgencyBreakdown['critical'] > 0): ?>
        <div class="bg-red-500 bg-opacity-10 border border-red-500 text-red-300 px-4 py-3 rounded-lg">
            <div class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                </svg>
                <div>
                    <strong>Critical Alert:</strong> <?php echo $urgencyBreakdown['critical']; ?> payments are severely overdue (60+ days).
                    Immediate action required!
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Total Overdue Amount -->
        <div class="card text-center border border-red-500 border-opacity-30">
            <div class="text-3xl font-bold text-red-400 mb-2">
                <?php echo formatCurrency($totalOverdueAmount); ?>
            </div>
            <h3 class="text-lg font-semibold text-pg-text-primary mb-1">Total Overdue</h3>
            <p class="text-sm text-pg-text-secondary">Urgent collection required</p>
        </div>

        <!-- Number of Overdue Payments -->
        <div class="card text-center">
            <div class="text-3xl font-bold text-red-400 mb-2">
                <?php echo number_format($totalOverduePayments); ?>
            </div>
            <h3 class="text-lg font-semibold text-pg-text-primary mb-1">Overdue Payments</h3>
            <p class="text-sm text-pg-text-secondary">Past due date</p>
        </div>

        <!-- Critical Cases -->
        <div class="card text-center">
            <div class="text-3xl font-bold text-red-300 mb-2">
                <?php echo number_format($urgencyBreakdown['critical']); ?>
            </div>
            <h3 class="text-lg font-semibold text-pg-text-primary mb-1">Critical Cases</h3>
            <p class="text-sm text-pg-text-secondary">60+ days overdue</p>
        </div>

        <!-- Average Days Overdue -->
        <div class="card text-center">
            <?php
            $totalDays = array_sum(array_map(function ($p) {
                return $p['days_overdue'];
            }, $overduePayments));
            $avgDays = $totalOverduePayments > 0 ? round($totalDays / $totalOverduePayments, 1) : 0;
            ?>
            <div class="text-3xl font-bold text-orange-400 mb-2">
                <?php echo $avgDays; ?>
            </div>
            <h3 class="text-lg font-semibold text-pg-text-primary mb-1">Avg Days Overdue</h3>
            <p class="text-sm text-pg-text-secondary">Collection delay</p>
        </div>
    </div>

    <!-- Urgency Breakdown -->
    <div class="card">
        <h3 class="text-lg font-semibold text-pg-text-primary mb-4 pb-2 border-b border-pg-border">
            Urgency Level Breakdown
        </h3>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <div class="p-4 bg-red-500 bg-opacity-10 border border-red-500 border-opacity-30 rounded-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-red-300 font-bold text-xl"><?php echo $urgencyBreakdown['critical']; ?></div>
                        <div class="text-sm text-pg-text-primary font-semibold">Critical</div>
                        <div class="text-xs text-pg-text-secondary">60+ days</div>
                    </div>
                    <svg class="w-8 h-8 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                </div>
            </div>

            <div class="p-4 bg-orange-500 bg-opacity-10 border border-orange-500 border-opacity-30 rounded-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-orange-300 font-bold text-xl"><?php echo $urgencyBreakdown['high']; ?></div>
                        <div class="text-sm text-pg-text-primary font-semibold">High</div>
                        <div class="text-xs text-pg-text-secondary">31-60 days</div>
                    </div>
                    <svg class="w-8 h-8 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>

            <div class="p-4 bg-yellow-500 bg-opacity-10 border border-yellow-500 border-opacity-30 rounded-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-yellow-300 font-bold text-xl"><?php echo $urgencyBreakdown['medium']; ?></div>
                        <div class="text-sm text-pg-text-primary font-semibold">Medium</div>
                        <div class="text-xs text-pg-text-secondary">8-30 days</div>
                    </div>
                    <svg class="w-8 h-8 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>

            <div class="p-4 bg-blue-500 bg-opacity-10 border border-blue-500 border-opacity-30 rounded-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-blue-300 font-bold text-xl"><?php echo $urgencyBreakdown['low']; ?></div>
                        <div class="text-sm text-pg-text-primary font-semibold">Low</div>
                        <div class="text-xs text-pg-text-secondary">1-7 days</div>
                    </div>
                    <svg class="w-8 h-8 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
        </div>
    </div>

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

            <!-- Urgency Filter -->
            <div>
                <label for="urgency" class="block text-sm font-medium text-pg-text-primary mb-2">
                    Urgency Level
                </label>
                <select id="urgency" name="urgency" class="select-field w-full">
                    <option value="all" <?php echo $urgencyFilter === 'all' ? 'selected' : ''; ?>>All Levels</option>
                    <option value="critical" <?php echo $urgencyFilter === 'critical' ? 'selected' : ''; ?>>Critical (60+ days)</option>
                    <option value="high" <?php echo $urgencyFilter === 'high' ? 'selected' : ''; ?>>High (31-60 days)</option>
                    <option value="medium" <?php echo $urgencyFilter === 'medium' ? 'selected' : ''; ?>>Medium (8-30 days)</option>
                    <option value="low" <?php echo $urgencyFilter === 'low' ? 'selected' : ''; ?>>Low (1-7 days)</option>
                </select>
            </div>

            <!-- Sort By -->
            <div>
                <label for="sort" class="block text-sm font-medium text-pg-text-primary mb-2">
                    Sort By
                </label>
                <select id="sort" name="sort" class="select-field w-full">
                    <option value="days_desc" <?php echo $sortBy === 'days_desc' ? 'selected' : ''; ?>>Most Overdue</option>
                    <option value="days_asc" <?php echo $sortBy === 'days_asc' ? 'selected' : ''; ?>>Least Overdue</option>
                    <option value="amount_desc" <?php echo $sortBy === 'amount_desc' ? 'selected' : ''; ?>>Highest Amount</option>
                    <option value="amount_asc" <?php echo $sortBy === 'amount_asc' ? 'selected' : ''; ?>>Lowest Amount</option>
                    <option value="urgency" <?php echo $sortBy === 'urgency' ? 'selected' : ''; ?>>Urgency Level</option>
                </select>
            </div>

            <!-- Filter Actions -->
            <div class="flex space-x-2">
                <button type="submit" class="btn-primary">Apply</button>
                <a href="overdue.php" class="btn-secondary">Reset</a>
            </div>
        </form>
    </div>

    <!-- Overdue Payments Table -->
    <div class="card">
        <h3 class="text-lg font-semibold text-pg-text-primary mb-4 pb-2 border-b border-pg-border">
            Overdue Payments (<?php echo number_format(count($overduePayments)); ?>)
        </h3>

        <?php if (empty($overduePayments)): ?>
            <div class="text-center py-12">
                <div class="flex flex-col items-center">
                    <svg class="w-16 h-16 text-green-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <h3 class="text-lg font-semibold text-pg-text-primary mb-2">Excellent Collection Performance!</h3>
                    <p class="text-pg-text-secondary">No overdue payments found with current filters</p>
                    <a href="pending.php" class="text-pg-accent hover:text-pg-accent-light text-sm mt-2">
                        Check pending payments →
                    </a>
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
                            <th class="table-header text-right">Balance</th>
                            <th class="table-header">Due Date</th>
                            <th class="table-header text-center">Days Overdue</th>
                            <th class="table-header text-center">Urgency</th>
                            <th class="table-header">Status</th>
                            <th class="table-header text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($overduePayments as $payment): ?>
                            <?php $student = $payment['student_info']; ?>
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
                                                • <a href="tel:<?php echo htmlspecialchars($student['phone']); ?>"
                                                    class="text-pg-accent hover:underline">
                                                    <?php echo htmlspecialchars($student['phone']); ?>
                                                </a>
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
                                    <div class="font-bold text-red-400">
                                        <?php echo formatCurrency($payment['overdue_balance']); ?>
                                    </div>
                                    <?php if ($payment['late_fee'] > 0): ?>
                                        <div class="text-xs text-pg-text-secondary">
                                            Incl. <?php echo formatCurrency($payment['late_fee']); ?> late fee
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <?php echo formatDate($payment['due_date']); ?>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-bold <?php echo getUrgencyColor($payment['urgency']); ?>">
                                        <?php echo $payment['days_overdue']; ?> days
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo getUrgencyBadge($payment['urgency']); ?>">
                                        <?php echo ucfirst($payment['urgency']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="<?php echo getStatusBadge($payment['payment_status']); ?>">
                                        <?php echo ucfirst($payment['payment_status']); ?>
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
                                            title="Update Payment">
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
                                        <?php if ($student && !empty($student['email'])): ?>
                                            <a href="mailto:<?php echo urlencode($student['email']); ?>?subject=Payment Reminder - <?php echo urlencode($payment['payment_id']); ?>"
                                                class="text-purple-400 hover:text-purple-300 transition-colors duration-200 p-1"
                                                title="Send Email">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
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

    <!-- Immediate Action Center -->
    <div class="card border border-red-500 border-opacity-30">
        <h3 class="text-lg font-semibold text-red-300 mb-4 pb-2 border-b border-red-500 border-opacity-30">
            🚨 Immediate Action Required
        </h3>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
            <a href="../payments/add.php" class="p-4 bg-green-500 bg-opacity-10 border border-green-500 border-opacity-30 rounded-lg hover:bg-green-500 hover:bg-opacity-20 transition-colors duration-200 text-center">
                <svg class="w-8 h-8 text-green-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                <div class="font-medium text-green-400">Record Payment</div>
                <div class="text-sm text-pg-text-secondary">Update payment status</div>
            </a>

            <a href="export.php?type=overdue_contact_list" class="p-4 bg-blue-500 bg-opacity-10 border border-blue-500 border-opacity-30 rounded-lg hover:bg-blue-500 hover:bg-opacity-20 transition-colors duration-200 text-center">
                <svg class="w-8 h-8 text-blue-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                </svg>
                <div class="font-medium text-blue-400">Contact List</div>
                <div class="text-sm text-pg-text-secondary">Export for calls</div>
            </a>

            <a href="pending.php" class="p-4 bg-yellow-500 bg-opacity-10 border border-yellow-500 border-opacity-30 rounded-lg hover:bg-yellow-500 hover:bg-opacity-20 transition-colors duration-200 text-center">
                <svg class="w-8 h-8 text-yellow-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div class="font-medium text-yellow-400">All Pending</div>
                <div class="text-sm text-pg-text-secondary">View all pending payments</div>
            </a>

            <a href="overview.php" class="p-4 bg-pg-primary bg-opacity-50 rounded-lg hover:bg-pg-hover transition-colors duration-200 text-center">
                <svg class="w-8 h-8 text-pg-accent mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                <div class="font-medium text-pg-text-primary">Dashboard</div>
                <div class="text-sm text-pg-text-secondary">Overview & analytics</div>
            </a>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>