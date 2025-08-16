<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

$title = 'Building-wise Report';
require_once '../../includes/auth_check.php';

// Initialize variables
$error = '';

// Fix: Create a variable copy of BUILDINGS constant
$buildingsArray = BUILDINGS;
$selectedBuilding = $_GET['building'] ?? reset($buildingsArray);

// Validate building selection
if (!in_array($selectedBuilding, BUILDINGS)) {
    $buildingsArray = BUILDINGS; // Reset the copy
    $selectedBuilding = reset($buildingsArray);
}

try {
    $supabase = supabase();

    // Get all students for the selected building
    $allStudents = $supabase->select('students', '*', []);
    $buildingStudents = array_filter($allStudents, function ($student) use ($selectedBuilding) {
        return ($student['building_code'] ?? '') === $selectedBuilding;
    });

    // Get all payments for the selected building
    $allPayments = $supabase->select('payments', '*', []);
    $buildingPayments = array_filter($allPayments, function ($payment) use ($selectedBuilding) {
        return ($payment['building_code'] ?? '') === $selectedBuilding;
    });

    // Calculate building metrics
    $metrics = [
        'total_students' => count($buildingStudents),
        'active_students' => count(array_filter($buildingStudents, function ($s) {
            return ($s['status'] ?? 'active') === 'active';
        })),
        'inactive_students' => count(array_filter($buildingStudents, function ($s) {
            return ($s['status'] ?? 'active') === 'inactive';
        })),
        'total_payments' => count($buildingPayments),
        'total_collected' => 0,
        'total_pending' => 0,
        'total_late_fees' => 0,
        'occupancy_rate' => 0,
        'avg_monthly_rent' => 0
    ];

    // Calculate financial metrics
    $statusBreakdown = ['paid' => 0, 'pending' => 0, 'partial' => 0, 'overdue' => 0];
    $monthlyBreakdown = [];

    foreach ($buildingPayments as $payment) {
        $due = floatval($payment['amount_due'] ?? 0);
        $paid = floatval($payment['amount_paid'] ?? 0);
        $lateFee = floatval($payment['late_fee'] ?? 0);
        $balance = ($due + $lateFee) - $paid;

        $metrics['total_collected'] += $paid;
        $metrics['total_late_fees'] += $lateFee;

        if ($balance > 0) {
            $metrics['total_pending'] += $balance;
        }

        // Status breakdown
        $status = strtolower($payment['payment_status'] ?? 'paid');
        if (isset($statusBreakdown[$status])) {
            $statusBreakdown[$status]++;
        }

        // Monthly breakdown
        $monthYear = $payment['month_year'] ?? 'unknown';
        if (!isset($monthlyBreakdown[$monthYear])) {
            $monthlyBreakdown[$monthYear] = [
                'payments' => 0,
                'collected' => 0,
                'pending' => 0
            ];
        }
        $monthlyBreakdown[$monthYear]['payments']++;
        $monthlyBreakdown[$monthYear]['collected'] += $paid;
        if ($balance > 0) {
            $monthlyBreakdown[$monthYear]['pending'] += $balance;
        }
    }

    // Calculate occupancy rate (assuming 50 rooms per building - adjust as needed)
    $totalRoomsCapacity = 50; // You can make this configurable per building
    $metrics['occupancy_rate'] = $totalRoomsCapacity > 0 ?
        round(($metrics['active_students'] / $totalRoomsCapacity) * 100, 1) : 0;

    // Calculate average monthly rent
    $rentSums = array_filter(array_map(function ($s) {
        return floatval($s['monthly_rent'] ?? 0);
    }, $buildingStudents));
    $metrics['avg_monthly_rent'] = count($rentSums) > 0 ?
        round(array_sum($rentSums) / count($rentSums), 2) : 0;

    // Sort monthly data by date (newest first)
    krsort($monthlyBreakdown);

    // Get recent payments (last 10)
    $recentPayments = array_slice(array_reverse($buildingPayments), 0, 10);
} catch (Exception $e) {
    $error = 'Error loading building report: ' . $e->getMessage();
    error_log('Building report error: ' . $e->getMessage());

    // Default values
    $buildingStudents = [];
    $buildingPayments = [];
    $recentPayments = [];
    $metrics = [
        'total_students' => 0,
        'active_students' => 0,
        'inactive_students' => 0,
        'total_payments' => 0,
        'total_collected' => 0,
        'total_pending' => 0,
        'total_late_fees' => 0,
        'occupancy_rate' => 0,
        'avg_monthly_rent' => 0
    ];
    $statusBreakdown = ['paid' => 0, 'pending' => 0, 'partial' => 0, 'overdue' => 0];
    $monthlyBreakdown = [];
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

<!-- Building-wise Report -->
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
                <h1 class="text-2xl font-bold text-pg-text-primary">Building Performance Report</h1>
                <p class="text-pg-text-secondary mt-1">
                    Detailed analysis for <?php echo BUILDING_NAMES[$selectedBuilding] ?? $selectedBuilding; ?>
                </p>
            </div>
        </div>

        <div class="flex items-center space-x-3">
            <a href="export.php?type=building&building=<?php echo urlencode($selectedBuilding); ?>" class="btn-secondary">
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

    <!-- Building Selector -->
    <div class="card">
        <form method="GET" class="flex items-center space-x-4">
            <div class="flex-1 max-w-xs">
                <label for="building" class="block text-sm font-medium text-pg-text-primary mb-2">
                    Select Building
                </label>
                <select id="building" name="building" class="select-field w-full" onchange="this.form.submit()">
                    <?php foreach (BUILDINGS as $buildingCode): ?>
                        <option value="<?php echo htmlspecialchars($buildingCode); ?>"
                            <?php echo $selectedBuilding === $buildingCode ? 'selected' : ''; ?>>
                            <?php echo BUILDING_NAMES[$buildingCode]; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>

    <!-- Key Performance Metrics -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Total Students -->
        <div class="card text-center">
            <div class="text-3xl font-bold text-pg-accent mb-2">
                <?php echo number_format($metrics['total_students']); ?>
            </div>
            <h3 class="text-lg font-semibold text-pg-text-primary mb-1">Total Students</h3>
            <p class="text-sm text-pg-text-secondary">
                <?php echo number_format($metrics['active_students']); ?> Active,
                <?php echo number_format($metrics['inactive_students']); ?> Inactive
            </p>
        </div>

        <!-- Occupancy Rate -->
        <div class="card text-center">
            <div class="text-3xl font-bold <?php echo $metrics['occupancy_rate'] >= 80 ? 'text-green-400' : ($metrics['occupancy_rate'] >= 60 ? 'text-yellow-400' : 'text-status-danger'); ?> mb-2">
                <?php echo $metrics['occupancy_rate']; ?>%
            </div>
            <h3 class="text-lg font-semibold text-pg-text-primary mb-1">Occupancy Rate</h3>
            <p class="text-sm text-pg-text-secondary">
                <?php echo number_format($metrics['active_students']); ?> / 50 rooms
            </p>
        </div>

        <!-- Total Revenue -->
        <div class="card text-center">
            <div class="text-3xl font-bold text-pg-accent mb-2">
                <?php echo formatCurrency($metrics['total_collected']); ?>
            </div>
            <h3 class="text-lg font-semibold text-pg-text-primary mb-1">Total Revenue</h3>
            <p class="text-sm text-pg-text-secondary">
                From <?php echo number_format($metrics['total_payments']); ?> payments
            </p>
        </div>

        <!-- Average Rent -->
        <div class="card text-center">
            <div class="text-3xl font-bold text-pg-accent mb-2">
                <?php echo formatCurrency($metrics['avg_monthly_rent']); ?>
            </div>
            <h3 class="text-lg font-semibold text-pg-text-primary mb-1">Avg Monthly Rent</h3>
            <p class="text-sm text-pg-text-secondary">Per student</p>
        </div>
    </div>

    <!-- Financial Summary -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Payment Status Analysis -->
        <div class="card">
            <h3 class="text-lg font-semibold text-pg-text-primary mb-4 pb-2 border-b border-pg-border">
                Payment Status Analysis
            </h3>

            <div class="space-y-3">
                <?php foreach ($statusBreakdown as $status => $count): ?>
                    <?php if ($count > 0): ?>
                        <div class="flex items-center justify-between p-3 bg-pg-primary bg-opacity-50 rounded-lg">
                            <div class="flex items-center">
                                <span class="<?php echo getStatusBadge($status); ?> mr-3">
                                    <?php echo ucfirst($status); ?>
                                </span>
                            </div>
                            <div class="text-right">
                                <div class="font-semibold text-pg-text-primary"><?php echo number_format($count); ?></div>
                                <div class="text-sm text-pg-text-secondary">
                                    <?php echo $metrics['total_payments'] > 0 ? round(($count / $metrics['total_payments']) * 100, 1) : 0; ?>%
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Financial Summary -->
        <div class="card">
            <h3 class="text-lg font-semibold text-pg-text-primary mb-4 pb-2 border-b border-pg-border">
                Financial Summary
            </h3>

            <div class="space-y-4">
                <div class="flex justify-between items-center p-3 bg-pg-primary bg-opacity-50 rounded-lg">
                    <span class="text-pg-text-primary">Total Collected</span>
                    <span class="font-semibold text-pg-accent"><?php echo formatCurrency($metrics['total_collected']); ?></span>
                </div>

                <?php if ($metrics['total_pending'] > 0): ?>
                    <div class="flex justify-between items-center p-3 bg-red-500 bg-opacity-20 rounded-lg">
                        <span class="text-pg-text-primary">Pending Amount</span>
                        <span class="font-semibold text-status-danger"><?php echo formatCurrency($metrics['total_pending']); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($metrics['total_late_fees'] > 0): ?>
                    <div class="flex justify-between items-center p-3 bg-yellow-500 bg-opacity-20 rounded-lg">
                        <span class="text-pg-text-primary">Late Fees Collected</span>
                        <span class="font-semibold text-yellow-400"><?php echo formatCurrency($metrics['total_late_fees']); ?></span>
                    </div>
                <?php endif; ?>

                <div class="flex justify-between items-center p-3 bg-pg-primary bg-opacity-50 rounded-lg">
                    <span class="text-pg-text-primary">Collection Efficiency</span>
                    <?php
                    $totalExpected = $metrics['total_collected'] + $metrics['total_pending'];
                    $efficiency = $totalExpected > 0 ? round(($metrics['total_collected'] / $totalExpected) * 100, 1) : 100;
                    ?>
                    <span class="font-semibold <?php echo $efficiency >= 90 ? 'text-green-400' : ($efficiency >= 70 ? 'text-yellow-400' : 'text-status-danger'); ?>">
                        <?php echo $efficiency; ?>%
                    </span>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly Performance Trends -->
    <?php if (!empty($monthlyBreakdown)): ?>
        <div class="card">
            <h3 class="text-lg font-semibold text-pg-text-primary mb-4 pb-2 border-b border-pg-border">
                Monthly Performance Trends
            </h3>

            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="border-b border-pg-border">
                            <th class="table-header text-left">Month</th>
                            <th class="table-header text-center">Payments</th>
                            <th class="table-header text-right">Collected</th>
                            <th class="table-header text-right">Pending</th>
                            <th class="table-header text-center">Efficiency</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($monthlyBreakdown, 0, 6) as $monthYear => $data): ?>
                            <tr class="border-b border-pg-border hover:bg-pg-hover transition-colors duration-200">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-pg-text-primary">
                                        <?php echo date('F Y', strtotime($monthYear . '-01')); ?>
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
                                    $monthTotal = $data['collected'] + $data['pending'];
                                    $monthEfficiency = $monthTotal > 0 ? round(($data['collected'] / $monthTotal) * 100, 1) : 100;
                                    ?>
                                    <span class="font-semibold <?php echo $monthEfficiency >= 90 ? 'text-green-400' : ($monthEfficiency >= 70 ? 'text-yellow-400' : 'text-status-danger'); ?>">
                                        <?php echo $monthEfficiency; ?>%
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- Students and Recent Payments -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Active Students List -->
        <div class="card">
            <h3 class="text-lg font-semibold text-pg-text-primary mb-4 pb-2 border-b border-pg-border">
                Active Students (<?php echo count(array_filter($buildingStudents, function ($s) {
                                        return ($s['status'] ?? 'active') === 'active';
                                    })); ?>)
            </h3>

            <div class="space-y-2 max-h-96 overflow-y-auto">
                <?php
                $activeStudentsList = array_filter($buildingStudents, function ($s) {
                    return ($s['status'] ?? 'active') === 'active';
                });
                ?>
                <?php if (empty($activeStudentsList)): ?>
                    <p class="text-pg-text-secondary text-center py-4">No active students in this building</p>
                <?php else: ?>
                    <?php foreach (array_slice($activeStudentsList, 0, 10) as $student): ?>
                        <div class="flex items-center justify-between p-3 bg-pg-primary bg-opacity-50 rounded-lg">
                            <div>
                                <div class="font-medium text-pg-text-primary">
                                    <?php echo htmlspecialchars($student['full_name']); ?>
                                </div>
                                <div class="text-sm text-pg-text-secondary">
                                    <?php echo htmlspecialchars($student['student_id']); ?>
                                    <?php if (!empty($student['room_number'])): ?>
                                        • Room <?php echo htmlspecialchars($student['room_number']); ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="text-right">
                                <?php if (!empty($student['monthly_rent'])): ?>
                                    <div class="font-semibold text-pg-accent">
                                        <?php echo formatCurrency($student['monthly_rent']); ?>
                                    </div>
                                    <div class="text-xs text-pg-text-secondary">Monthly</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php if (count($activeStudentsList) > 10): ?>
                        <div class="text-center pt-2">
                            <a href="../students/index.php?building=<?php echo urlencode($selectedBuilding); ?>"
                                class="text-pg-accent hover:text-pg-accent-light text-sm">
                                View all <?php echo count($activeStudentsList); ?> students →
                            </a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Payments -->
        <div class="card">
            <h3 class="text-lg font-semibold text-pg-text-primary mb-4 pb-2 border-b border-pg-border">
                Recent Payments
            </h3>

            <div class="space-y-2 max-h-96 overflow-y-auto">
                <?php if (empty($recentPayments)): ?>
                    <p class="text-pg-text-secondary text-center py-4">No recent payments for this building</p>
                <?php else: ?>
                    <?php foreach ($recentPayments as $payment): ?>
                        <div class="flex items-center justify-between p-3 bg-pg-primary bg-opacity-50 rounded-lg">
                            <div>
                                <div class="font-medium text-pg-text-primary">
                                    <?php echo htmlspecialchars($payment['student_id']); ?>
                                </div>
                                <div class="text-sm text-pg-text-secondary">
                                    <?php echo formatDate($payment['payment_date']); ?>
                                    • <?php echo htmlspecialchars($payment['month_year']); ?>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="font-semibold text-pg-accent">
                                    <?php echo formatCurrency($payment['amount_paid']); ?>
                                </div>
                                <span class="<?php echo getStatusBadge($payment['payment_status']); ?>">
                                    <?php echo ucfirst($payment['payment_status']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="text-center pt-2">
                        <a href="../payments/index.php?building=<?php echo urlencode($selectedBuilding); ?>"
                            class="text-pg-accent hover:text-pg-accent-light text-sm">
                            View all payments for this building →
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card">
        <h3 class="text-lg font-semibold text-pg-text-primary mb-4 pb-2 border-b border-pg-border">
            Quick Actions
        </h3>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
            <a href="../students/index.php?building=<?php echo urlencode($selectedBuilding); ?>"
                class="p-4 bg-pg-primary bg-opacity-50 rounded-lg hover:bg-pg-hover transition-colors duration-200 text-center">
                <svg class="w-8 h-8 text-pg-accent mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                </svg>
                <div class="font-medium text-pg-text-primary">View Students</div>
                <div class="text-sm text-pg-text-secondary">Manage building residents</div>
            </a>

            <a href="../payments/index.php?building=<?php echo urlencode($selectedBuilding); ?>"
                class="p-4 bg-pg-primary bg-opacity-50 rounded-lg hover:bg-pg-hover transition-colors duration-200 text-center">
                <svg class="w-8 h-8 text-pg-accent mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
                <div class="font-medium text-pg-text-primary">View Payments</div>
                <div class="text-sm text-pg-text-secondary">Check payment history</div>
            </a>

            <a href="pending.php?building=<?php echo urlencode($selectedBuilding); ?>"
                class="p-4 bg-pg-primary bg-opacity-50 rounded-lg hover:bg-pg-hover transition-colors duration-200 text-center">
                <svg class="w-8 h-8 text-pg-accent mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div class="font-medium text-pg-text-primary">Pending Payments</div>
                <div class="text-sm text-pg-text-secondary">Outstanding dues</div>
            </a>

            <a href="../payments/add.php"
                class="p-4 bg-pg-primary bg-opacity-50 rounded-lg hover:bg-pg-hover transition-colors duration-200 text-center">
                <svg class="w-8 h-8 text-pg-accent mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                <div class="font-medium text-pg-text-primary">Record Payment</div>
                <div class="text-sm text-pg-text-secondary">Add new payment</div>
            </a>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>