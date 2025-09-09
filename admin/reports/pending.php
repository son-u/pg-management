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

// Get current month for consistency with dashboard
$currentMonth = date('Y-m');

// Get buildings data using the new Buildings class
try {
    $buildingCodes = Buildings::getCodes();
    $buildingNames = Buildings::getNames();
} catch (Exception $e) {
    error_log('Pending payments buildings error: ' . $e->getMessage());
    $buildingCodes = [];
    $buildingNames = [];
}

// Validate building selection
if ($selectedBuilding !== 'all' && !in_array($selectedBuilding, $buildingCodes)) {
    $selectedBuilding = 'all';
}

// Function to get monthly rent for a student from their record
function getMonthlyRentForStudent($student) {
    return floatval($student['monthly_rent'] ?? 5000.00);
}

// Updated function to calculate days overdue
function getDaysOverdue($paymentDate, $monthYear) {
    if (empty($paymentDate)) {
        // If no payment date, calculate days since month started
        $monthStart = date('Y-m-01', strtotime($monthYear . '-01'));
        $daysSinceMonthStart = floor((time() - strtotime($monthStart)) / (24 * 60 * 60));
        return max(0, $daysSinceMonthStart);
    }
    
    $paymentTime = strtotime($paymentDate);
    $currentTime = time();
    $daysDiff = floor(($currentTime - $paymentTime) / (24 * 60 * 60));
    
    return max(0, $daysDiff);
}

try {
    $supabase = supabase();
    
    if (!$supabase) {
        throw new Exception('Failed to connect to database');
    }

    // Get all active students with monthly_rent included
    $allActiveStudents = $supabase->select('students', 'student_id,full_name,building_code,phone,room_number,monthly_rent', ['status' => 'active']);
    
    if ($allActiveStudents === false || $allActiveStudents === null) {
        throw new Exception('Failed to fetch students data');
    }

    // Get all payments for current month with building filter if needed
    $currentMonthFilter = ['month_year' => $currentMonth];
    if ($selectedBuilding !== 'all') {
        $currentMonthFilter['building_code'] = $selectedBuilding;
    }
    
    $allCurrentPayments = $supabase->select('payments', '*', $currentMonthFilter);
    
    if ($allCurrentPayments === false) {
        $allCurrentPayments = []; // Handle case where no payments exist yet
    }

    // Create mapping of students who have made payments
    $paidStudentIds = array_column($allCurrentPayments, 'student_id');
    $paymentsById = array_column($allCurrentPayments, null, 'student_id');

    // Initialize variables
    $pendingPayments = [];
    $totalPendingAmount = 0;
    $totalPendingPayments = 0;

    // Debug logging
    error_log('Pending payments debug: Total active students: ' . count($allActiveStudents));
    error_log('Pending payments debug: Students with payments: ' . count($paidStudentIds));

    foreach ($allActiveStudents as $student) {
        $studentId = $student['student_id'];
        $studentBuilding = $student['building_code'];
        
        // Apply building filter
        if ($selectedBuilding !== 'all' && $studentBuilding !== $selectedBuilding) {
            continue;
        }
        
        // Check if student has payment for current month
        $hasPayment = in_array($studentId, $paidStudentIds);
        
        if (!$hasPayment) {
            // Student has no payment record - completely pending
            $monthlyRent = getMonthlyRentForStudent($student);
            
            $pendingPayment = [
                'payment_id' => 'PENDING_' . $studentId . '_' . $currentMonth,
                'student_id' => $studentId,
                'building_code' => $studentBuilding,
                'month_year' => $currentMonth,
                'amount_due' => $monthlyRent,
                'amount_paid' => 0,
                'late_fee' => 0,
                'pending_balance' => $monthlyRent,
                'payment_status' => 'pending',
                'payment_date' => null,
                'created_at' => date('Y-m-d H:i:s'),
                'student_info' => $student
            ];
            
            // Apply status filter
            if ($selectedStatus !== 'all') {
                if ($selectedStatus === 'partial') continue; // No partial for completely unpaid
                if ($selectedStatus === 'overdue') {
                    // Only include if it's been more than a certain number of days
                    $daysOverdue = getDaysOverdue(null, $currentMonth);
                    if ($daysOverdue < 7) continue; // Consider overdue after 7 days
                }
                // 'pending' status matches this case
            }
            
            $pendingPayments[] = $pendingPayment;
            $totalPendingAmount += $monthlyRent;
            $totalPendingPayments++;
            
        } else {
            // Student has payment record - check if there's outstanding balance
            $payment = $paymentsById[$studentId];
            $due = floatval($payment['amount_due'] ?? 0);
            $paid = floatval($payment['amount_paid'] ?? 0);
            $lateFee = floatval($payment['late_fee'] ?? 0);
            $balance = ($due + $lateFee) - $paid;
            
            if ($balance > 0.01) { // Use small threshold to avoid floating point issues
                $payment['pending_balance'] = round($balance, 2);
                $payment['student_info'] = $student;
                
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
                $dateA = $a['payment_date'] ?? $a['created_at'] ?? '2000-01-01';
                $dateB = $b['payment_date'] ?? $b['created_at'] ?? '2000-01-01';
                return strtotime($dateB) <=> strtotime($dateA);
            });
            break;
        case 'date_asc':
            usort($pendingPayments, function ($a, $b) {
                $dateA = $a['payment_date'] ?? $a['created_at'] ?? '2000-01-01';
                $dateB = $b['payment_date'] ?? $b['created_at'] ?? '2000-01-01';
                return strtotime($dateA) <=> strtotime($dateB);
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

    // Debug logging
    error_log('Pending payments debug: Final pending count: ' . count($pendingPayments));
    error_log('Pending payments debug: Total pending amount: ' . $totalPendingAmount);

} catch (Exception $e) {
    $error = 'Error loading pending payments: ' . $e->getMessage();
    error_log('Pending payments error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
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
                    Track and manage outstanding payment balances for <?php echo $currentMonth; ?>
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

    <!-- Debug Information (remove in production) -->
    <?php if (isset($_GET['debug'])): ?>
        <div class="bg-blue-500 bg-opacity-10 border border-blue-500 text-blue-600 px-4 py-3 rounded-lg">
            <h4 class="font-bold mb-2">Debug Information:</h4>
            <ul class="text-sm space-y-1">
                <li>Total Active Students: <?php echo count($allActiveStudents ?? []); ?></li>
                <li>Students with Current Month Payments: <?php echo count($paidStudentIds ?? []); ?></li>
                <li>Pending Payments Found: <?php echo count($pendingPayments); ?></li>
                <li>Total Pending Amount: ₹<?php echo number_format($totalPendingAmount); ?></li>
                <li>Selected Building: <?php echo $selectedBuilding; ?></li>
                <li>Current Month: <?php echo $currentMonth; ?></li>
                <li>Selected Status Filter: <?php echo $selectedStatus; ?></li>
            </ul>
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
                            <?php echo htmlspecialchars($buildingNames[$buildingCode] ?? $buildingCode); ?>
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
                    <?php foreach ($buildingCodes as $code): ?>
                        <option value="<?php echo htmlspecialchars($code); ?>" <?php echo $selectedBuilding === $code ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($buildingNames[$code] ?? $code); ?>
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
                            <th class="table-header text-right">Monthly Rent</th>
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
                            $isPendingRecord = strpos($payment['payment_id'], 'PENDING_') === 0;
                            ?>
                            <tr class="border-b border-pg-border hover:bg-pg-hover transition-colors duration-200">
                                <td class="px-6 py-4 font-mono text-sm">
                                    <?php if ($isPendingRecord): ?>
                                        <span class="text-status-warning font-medium">NO RECORD</span>
                                    <?php else: ?>
                                        <?php echo htmlspecialchars($payment['payment_id']); ?>
                                    <?php endif; ?>
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
                                        <?php echo htmlspecialchars($buildingNames[$payment['building_code']] ?? $payment['building_code']); ?>
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
                                    <?php if (isset($payment['late_fee']) && $payment['late_fee'] > 0): ?>
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
                                        <?php if (!$isPendingRecord): ?>
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
                                        <?php endif; ?>
                                        
                                        <!-- Record Payment Button -->
                                        <a href="../payments/add.php?student_id=<?php echo urlencode($payment['student_id']); ?>&amount=<?php echo urlencode($payment['pending_balance']); ?>"
                                            class="text-green-400 hover:text-green-300 transition-colors duration-200 p-1"
                                            title="Record Payment">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                            </svg>
                                        </a>
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
                <div class="text-sm text-pg-text-secondary">Add new payment</div>
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
