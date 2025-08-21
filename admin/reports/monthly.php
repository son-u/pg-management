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

try {
    $supabase = supabase();

    // Get all payments for analysis
    $allPayments = $supabase->select('payments', '*', []);

    // Filter payments for selected month
    $monthlyPayments = array_filter($allPayments, function ($payment) use ($selectedMonthYear) {
        return ($payment['month_year'] ?? '') === $selectedMonthYear;
    });

    // Get available months for dropdown
    $availableMonths = array_unique(array_map(function ($p) {
        return $p['month_year'];
    }, $allPayments));
    rsort($availableMonths);

    // Calculate monthly metrics
    $metrics = [
        'total_payments' => count($monthlyPayments),
        'total_collected' => 0,
        'total_due' => 0,
        'total_pending' => 0,
        'total_late_fees' => 0,
        'collection_rate' => 0
    ];

    $statusBreakdown = ['paid' => 0, 'pending' => 0, 'partial' => 0, 'overdue' => 0];
    $methodBreakdown = ['cash' => 0, 'upi' => 0, 'bank_transfer' => 0, 'cheque' => 0];
    $buildingBreakdown = [];

    foreach ($monthlyPayments as $payment) {
        $due = floatval($payment['amount_due'] ?? 0);
        $paid = floatval($payment['amount_paid'] ?? 0);
        $lateFee = floatval($payment['late_fee'] ?? 0);
        $balance = ($due + $lateFee) - $paid;

        $metrics['total_collected'] += $paid;
        $metrics['total_due'] += $due;
        $metrics['total_late_fees'] += $lateFee;

        if ($balance > 0) {
            $metrics['total_pending'] += $balance;
        }

        // Status breakdown
        $status = strtolower($payment['payment_status'] ?? 'paid');
        if (isset($statusBreakdown[$status])) {
            $statusBreakdown[$status]++;
        }

        // Payment method breakdown
        $method = strtolower($payment['payment_method'] ?? 'cash');
        if (isset($methodBreakdown[$method])) {
            $methodBreakdown[$method]++;
        }

        // Building breakdown
        $building = $payment['building_code'] ?? 'unknown';
        if (!isset($buildingBreakdown[$building])) {
            $buildingBreakdown[$building] = [
                'payments' => 0,
                'collected' => 0,
                'pending' => 0
            ];
        }
        $buildingBreakdown[$building]['payments']++;
        $buildingBreakdown[$building]['collected'] += $paid;
        if ($balance > 0) {
            $buildingBreakdown[$building]['pending'] += $balance;
        }
    }

    // Calculate collection rate
    $totalExpected = $metrics['total_due'] + $metrics['total_late_fees'];
    $metrics['collection_rate'] = $totalExpected > 0 ?
        round(($metrics['total_collected'] / $totalExpected) * 100, 1) : 100;

    // Get previous month for comparison
    $previousMonth = date('Y-m', strtotime($selectedMonthYear . '-01 -1 month'));
    $previousMonthPayments = array_filter($allPayments, function ($payment) use ($previousMonth) {
        return ($payment['month_year'] ?? '') === $previousMonth;
    });

    $previousMonthCollected = array_sum(array_map(function ($p) {
        return floatval($p['amount_paid'] ?? 0);
    }, $previousMonthPayments));

    $growth = $previousMonthCollected > 0 ?
        round((($metrics['total_collected'] - $previousMonthCollected) / $previousMonthCollected) * 100, 1) : 0;
} catch (Exception $e) {
    $error = 'Error loading monthly report: ' . $e->getMessage();
    error_log('Monthly report error: ' . $e->getMessage());

    // Default values
    $monthlyPayments = [];
    $availableMonths = [];
    $metrics = [
        'total_payments' => 0,
        'total_collected' => 0,
        'total_due' => 0,
        'total_pending' => 0,
        'total_late_fees' => 0,
        'collection_rate' => 0
    ];
    $statusBreakdown = ['paid' => 0, 'pending' => 0, 'partial' => 0, 'overdue' => 0];
    $methodBreakdown = ['cash' => 0, 'upi' => 0, 'bank_transfer' => 0, 'cheque' => 0];
    $buildingBreakdown = [];
    $growth = 0;
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
                    Detailed payment analysis for <?php echo date('F Y', strtotime($selectedMonthYear . '-01')); ?>
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
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Total Payments -->
        <div class="card text-center">
            <div class="text-3xl font-bold text-pg-accent mb-2">
                <?php echo number_format($metrics['total_payments']); ?>
            </div>
            <h3 class="text-lg font-semibold text-pg-text-primary mb-1">Total Payments</h3>
            <p class="text-sm text-pg-text-secondary">This month</p>
        </div>

        <!-- Amount Collected -->
        <div class="card text-center">
            <div class="text-3xl font-bold text-pg-accent mb-2">
                <?php echo formatCurrency($metrics['total_collected']); ?>
            </div>
            <h3 class="text-lg font-semibold text-pg-text-primary mb-1">Collected</h3>
            <?php if ($growth !== 0): ?>
                <p class="text-sm <?php echo $growth > 0 ? 'text-green-400' : 'text-red-400'; ?>">
                    <?php echo $growth > 0 ? '+' : ''; ?><?php echo $growth; ?>% vs last month
                </p>
            <?php else: ?>
                <p class="text-sm text-pg-text-secondary">No comparison data</p>
            <?php endif; ?>
        </div>

        <!-- Collection Rate -->
        <div class="card text-center">
            <div class="text-3xl font-bold text-pg-accent mb-2">
                <?php echo $metrics['collection_rate']; ?>%
            </div>
            <h3 class="text-lg font-semibold text-pg-text-primary mb-1">Collection Rate</h3>
            <p class="text-sm text-pg-text-secondary">
                <?php echo formatCurrency($metrics['total_due']); ?> expected
            </p>
        </div>

        <!-- Pending Amount -->
        <div class="card text-center">
            <div class="text-3xl font-bold <?php echo $metrics['total_pending'] > 0 ? 'text-status-danger' : 'text-pg-accent'; ?> mb-2">
                <?php echo formatCurrency($metrics['total_pending']); ?>
            </div>
            <h3 class="text-lg font-semibold text-pg-text-primary mb-1">Pending</h3>
            <p class="text-sm text-pg-text-secondary">Outstanding amount</p>
        </div>
    </div>

    <!-- Analytics Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Payment Status Breakdown -->
        <div class="card">
            <h3 class="text-lg font-semibold text-pg-text-primary mb-4 pb-2 border-b border-pg-border">
                Payment Status Breakdown
            </h3>

            <div class="space-y-3">
                <?php foreach ($statusBreakdown as $status => $count): ?>
                    <div class="flex items-center justify-between p-3 bg-pg-primary bg-opacity-50 rounded-lg">
                        <div class="flex items-center">
                            <span class="<?php echo getStatusBadge($status); ?> mr-3">
                                <?php echo ucfirst($status); ?>
                            </span>
                        </div>
                        <div class="font-semibold text-pg-text-primary">
                            <?php echo number_format($count); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
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
                                <?php echo str_replace('_', ' ', $method); ?>
                            </span>
                            <div class="flex items-center space-x-2">
                                <span class="font-semibold text-pg-text-primary"><?php echo number_format($count); ?></span>
                                <span class="text-sm text-pg-text-secondary">
                                    (<?php echo $metrics['total_payments'] > 0 ? round(($count / $metrics['total_payments']) * 100, 1) : 0; ?>%)
                                </span>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Building-wise Performance -->
    <?php if (!empty($buildingBreakdown)): ?>
        <div class="card">
            <h3 class="text-lg font-semibold text-pg-text-primary mb-4 pb-2 border-b border-pg-border">
                Building-wise Performance
            </h3>

            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="border-b border-pg-border">
                            <th class="table-header text-left">Building</th>
                            <th class="table-header text-center">Payments</th>
                            <th class="table-header text-right">Collected</th>
                            <th class="table-header text-right">Pending</th>
                            <th class="table-header text-center">Collection Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($buildingBreakdown as $buildingCode => $data): ?>
                            <tr class="border-b border-pg-border hover:bg-pg-hover transition-colors duration-200">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-pg-text-primary">
                                        <?php echo htmlspecialchars($buildingNames[$buildingCode] ?? $buildingCode); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-pg-accent bg-opacity-20 text-pg-accent">
                                        <?php echo number_format($data['payments']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right font-semibold text-pg-accent">
                                    <?php echo formatCurrency($data['collected']); ?>
                                </td>
                                <td class="px-6 py-4 text-right font-semibold <?php echo $data['pending'] > 0 ? 'text-status-danger' : 'text-pg-text-secondary'; ?>">
                                    <?php echo formatCurrency($data['pending']); ?>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <?php
                                    $buildingTotal = $data['collected'] + $data['pending'];
                                    $buildingRate = $buildingTotal > 0 ? round(($data['collected'] / $buildingTotal) * 100, 1) : 100;
                                    ?>
                                    <span class="font-semibold <?php echo $buildingRate >= 90 ? 'text-green-400' : ($buildingRate >= 70 ? 'text-yellow-400' : 'text-status-danger'); ?>">
                                        <?php echo $buildingRate; ?>%
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- Detailed Payment List -->
    <div class="card">
        <h3 class="text-lg font-semibold text-pg-text-primary mb-4 pb-2 border-b border-pg-border">
            All Payments - <?php echo date('F Y', strtotime($selectedMonthYear . '-01')); ?>
        </h3>

        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr class="border-b border-pg-border">
                        <th class="table-header">Payment ID</th>
                        <th class="table-header">Student</th>
                        <th class="table-header">Building</th>
                        <th class="table-header">Amount</th>
                        <th class="table-header">Date</th>
                        <th class="table-header">Status</th>
                        <th class="table-header">Method</th>
                        <th class="table-header text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($monthlyPayments)): ?>
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <svg class="w-12 h-12 text-pg-text-secondary mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    <p class="text-pg-text-secondary">No payments found for this month</p>
                                    <p class="text-sm text-pg-text-secondary mt-1">Try selecting a different month</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($monthlyPayments as $payment): ?>
                            <tr class="border-b border-pg-border hover:bg-pg-hover transition-colors duration-200">
                                <td class="px-6 py-4 font-mono text-sm">
                                    <?php echo htmlspecialchars($payment['payment_id']); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-medium text-pg-text-primary">
                                        <?php echo htmlspecialchars($payment['student_id']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <?php echo htmlspecialchars($buildingNames[$payment['building_code']] ?? $payment['building_code']); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-semibold text-pg-accent">
                                        <?php echo formatCurrency($payment['amount_paid']); ?>
                                    </div>
                                    <?php if ($payment['amount_due'] != $payment['amount_paid']): ?>
                                        <div class="text-xs text-pg-text-secondary">
                                            Due: <?php echo formatCurrency($payment['amount_due']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <?php echo formatDate($payment['payment_date']); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="<?php echo getStatusBadge($payment['payment_status']); ?>">
                                        <?php echo ucfirst($payment['payment_status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-pg-accent bg-opacity-20 text-pg-accent">
                                        <?php echo ucfirst(str_replace('_', ' ', $payment['payment_method'])); ?>
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
                                        <a href="../payments/receipt.php?id=<?php echo urlencode($payment['payment_id']); ?>"
                                            class="text-green-400 hover:text-green-300 transition-colors duration-200 p-1"
                                            title="Print Receipt"
                                            target="_blank">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                                            </svg>
                                        </a>
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