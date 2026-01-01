<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

$title = 'Payments Management';
require_once '../../includes/auth_check.php';

// Get filter parameters
$search = trim($_GET['search'] ?? '');
$selectedStudent = trim($_GET['student_id'] ?? '');
$selectedBuilding = $_GET['building'] ?? 'all';
$status = $_GET['status'] ?? 'all';
$selectedMonthYear = $_GET['month_year'] ?? '';
$paymentMethod = $_GET['payment_method'] ?? 'all';

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Initialize variables
$payments = [];
$totalPayments = 0;
$totalAmount = 0;
$totalPages = 0;
$error = '';

// Get buildings data using the new Buildings class
try {
    $buildingCodes = Buildings::getCodes();
    $buildingNames = Buildings::getNames();
} catch (Exception $e) {
    error_log('Payments index buildings error: ' . $e->getMessage());
    $buildingCodes = [];
    $buildingNames = [];
}

try {
    $supabase = supabase();

    // Build filters for API query
    $filters = [];
    if ($selectedStudent) {
        $filters['student_id'] = $selectedStudent;
    }
    if ($selectedBuilding !== 'all') {
        $filters['building_code'] = $selectedBuilding;
    }
    if ($status !== 'all') {
        $filters['payment_status'] = $status;
    }
    if ($selectedMonthYear) {
        $filters['month_year'] = $selectedMonthYear;
    }
    if ($paymentMethod !== 'all') {
        $filters['payment_method'] = $paymentMethod;
    }

    // Get all matching payments
    $allPayments = $supabase->select('payments', '*', $filters);

    // Apply search filter (client-side since Supabase REST doesn't support complex OR queries easily)
    if ($search !== '') {
        $searchLower = strtolower($search);
        $allPayments = array_filter($allPayments, function($payment) use ($searchLower) {
            return strpos(strtolower($payment['student_id'] ?? ''), $searchLower) !== false ||
                   strpos(strtolower($payment['payment_id'] ?? ''), $searchLower) !== false ||
                   strpos(strtolower($payment['receipt_number'] ?? ''), $searchLower) !== false ||
                   strpos(strtolower($payment['notes'] ?? ''), $searchLower) !== false;
        });
    }

    // Sort by payment date (newest first)
    usort($allPayments, function($a, $b) {
        return strtotime($b['payment_date']) - strtotime($a['payment_date']);
    });

    // ✅ NEW: Get student names for all payments
    if (!empty($allPayments)) {
        $studentIds = array_unique(array_map(function($payment) {
            return $payment['student_id'];
        }, $allPayments));
        
        $studentsData = $supabase->select('students', 'student_id,full_name', []);
        
        // Create student ID to name mapping
        $studentNameMap = [];
        foreach ($studentsData as $student) {
            $studentNameMap[$student['student_id']] = $student['full_name'];
        }
        
        // Add student names to payments
        foreach ($allPayments as &$payment) {
            $payment['student_name'] = $studentNameMap[$payment['student_id']] ?? 'Unknown Student';
        }
        unset($payment); // Break reference
    }

    $totalPayments = count($allPayments);
    $totalPages = ceil($totalPayments / $perPage);
    $payments = array_slice($allPayments, $offset, $perPage);

    // Calculate total amount for filtered results
    $totalAmount = array_sum(array_map(function($p) { 
        return floatval($p['amount_paid']); 
    }, $allPayments));

} catch (Exception $e) {
    $error = 'Error loading payments: ' . $e->getMessage();
    error_log('Payments listing error: ' . $e->getMessage());
}

// Get students list for dropdown
$students = [];
try {
    $students = $supabase->select('students', 'student_id,full_name', []);
    // Sort students by name
    usort($students, function($a, $b) {
        return strcmp($a['full_name'], $b['full_name']);
    });
} catch (Exception $e) {
    // Ignore errors, empty dropdown is acceptable
}

// Get unique month-year values from all payments for filter dropdown
$monthYears = [];
try {
    $allPaymentsForMonths = $supabase->select('payments', 'month_year', []);
    $monthYears = array_unique(array_column($allPaymentsForMonths, 'month_year'));
    rsort($monthYears); // Most recent first
} catch (Exception $e) {
    // Ignore errors
}

// Helper function to build query string for pagination
function buildQueryString($params, $page = null) {
    if ($page !== null) {
        $params['page'] = $page;
    }
    return http_build_query(array_filter($params, function($value) {
        return $value !== '' && $value !== 'all';
    }));
}

$queryParams = [
    'search' => $search,
    'student_id' => $selectedStudent,
    'building' => $selectedBuilding,
    'status' => $status,
    'month_year' => $selectedMonthYear,
    'payment_method' => $paymentMethod
];

// Helper function to format payment status badge
function getStatusBadge($status) {
    $badges = [
        'paid' => 'status-active',
        'pending' => 'status-badge bg-yellow-500 bg-opacity-20 text-yellow-400',
        'partial' => 'status-badge bg-blue-500 bg-opacity-20 text-blue-400',
        'overdue' => 'status-inactive'
    ];
    return $badges[$status] ?? 'status-badge bg-gray-500 bg-opacity-20 text-gray-400';
}
?>

<?php include '../../includes/header.php'; ?>

<!-- Payments Management -->
<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-pg-text-primary">Payments Management</h1>
            <p class="text-pg-text-secondary mt-1">
                Track and manage all payment transactions across buildings
            </p>
        </div>
        
        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
            <!-- Total Amount Display -->
            <div class="bg-pg-accent bg-opacity-20 text-pg-accent px-4 py-2 rounded-lg">
                <div class="text-sm font-medium">Total Collected</div>
                <div class="text-xl font-bold">₹<?php echo number_format($totalAmount, 2); ?></div>
            </div>
            
            <!-- Add Payment Button -->
            <a href="add.php" class="btn-primary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                <span>Record Payment</span>
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

    <!-- Advanced Filters -->
    <div class="card">
        <form method="GET" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                <!-- Search Input -->
                <div class="sm:col-span-2 md:col-span-2">
                    <label for="search" class="block text-sm font-medium text-pg-text-primary mb-2">Search</label>
                    <input type="text" 
                           id="search" 
                           name="search" 
                           value="<?php echo htmlspecialchars($search); ?>" 
                           placeholder="Search by Student ID, Payment ID, Receipt..."
                           class="input-field w-full">
                </div>

                <!-- Student Filter -->
                <div>
                    <label for="student_id" class="block text-sm font-medium text-pg-text-primary mb-2">Student</label>
                    <select id="student_id" name="student_id" class="select-field w-full">
                        <option value="">All Students</option>
                        <?php foreach ($students as $student): ?>
                            <option value="<?php echo htmlspecialchars($student['student_id']); ?>" 
                                    <?php echo $selectedStudent === $student['student_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($student['full_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Building Filter -->
                <div>
                    <label for="building" class="block text-sm font-medium text-pg-text-primary mb-2">Building</label>
                    <select id="building" name="building" class="select-field w-full">
                        <option value="all">All Buildings</option>
                        <?php if (!empty($buildingNames)): ?>
                            <?php foreach ($buildingNames as $code => $name): ?>
                                <option value="<?php echo htmlspecialchars($code); ?>" 
                                        <?php echo $selectedBuilding === $code ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($name); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="" disabled>No buildings available</option>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- Status Filter -->
                <div>
                    <label for="status" class="block text-sm font-medium text-pg-text-primary mb-2">Status</label>
                    <select id="status" name="status" class="select-field w-full">
                        <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="paid" <?php echo $status === 'paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="partial" <?php echo $status === 'partial' ? 'selected' : ''; ?>>Partial</option>
                        <option value="overdue" <?php echo $status === 'overdue' ? 'selected' : ''; ?>>Overdue</option>
                    </select>
                </div>

                <!-- Month-Year Filter -->
                <div>
                    <label for="month_year" class="block text-sm font-medium text-pg-text-primary mb-2">Period</label>
                    <select id="month_year" name="month_year" class="select-field w-full">
                        <option value="">All Periods</option>
                        <?php foreach ($monthYears as $monthYear): ?>
                            <option value="<?php echo htmlspecialchars($monthYear); ?>" 
                                    <?php echo $selectedMonthYear === $monthYear ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($monthYear); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Filter Actions -->
            <div class="flex flex-col sm:flex-row gap-2">
                <button type="submit" class="btn-primary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                    </svg>
                    <span>Apply Filters</span>
                </button>
                <a href="index.php" class="btn-secondary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    <span>Reset</span>
                </a>
            </div>
        </form>
    </div>

    
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr class="border-b border-pg-border">
                        <th class="table-header">Payment ID</th>
                        <th class="table-header">Student</th>
                        <th class="table-header">Amount</th>
                        <th class="table-header">Period</th>
                        <th class="table-header">Date</th>
                        <th class="table-header">Method</th>
                        <th class="table-header">Status</th>
                        <th class="table-header hidden md:table-cell">Receipt</th>
                        <th class="table-header text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr>
                            <td colspan="9" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <svg class="w-12 h-12 text-pg-text-secondary mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                    </svg>
                                    <p class="text-pg-text-secondary">No payments found</p>
                                    <p class="text-sm text-pg-text-secondary mt-1">Try adjusting your search filters or record a new payment</p>
                                    <a href="add.php" class="btn-primary mt-4">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                        </svg>
                                        <span>Record First Payment</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($payments as $payment): ?>
                            <tr class="border-b border-pg-border hover:bg-pg-hover transition-colors duration-200">
                                <td class="px-6 py-4 font-mono text-sm">
                                    <?php echo htmlspecialchars($payment['payment_id']); ?>
                                </td>
                                
                                <td class="px-6 py-4">
                                    <div>
                                        <div class="font-medium text-pg-text-primary">
                                            <?php echo htmlspecialchars($payment['student_name'] ?? 'Unknown Student'); ?>
                                        </div>
                                        <div class="text-sm text-pg-text-secondary">
                                            <?php echo htmlspecialchars($payment['student_id']); ?> • 
                                            <?php echo htmlspecialchars($buildingNames[$payment['building_code']] ?? $payment['building_code']); ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-semibold text-pg-text-primary">
                                        ₹<?php echo number_format($payment['amount_paid'], 2); ?>
                                    </div>
                                    <?php if ($payment['amount_due'] != $payment['amount_paid']): ?>
                                        <div class="text-xs text-pg-text-secondary">
                                            Due: ₹<?php echo number_format($payment['amount_due'], 2); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($payment['late_fee'] > 0): ?>
                                        <div class="text-xs text-status-danger">
                                            +₹<?php echo number_format($payment['late_fee'], 2); ?> late fee
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <?php echo htmlspecialchars($payment['month_year']); ?>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <?php echo date('M d, Y', strtotime($payment['payment_date'])); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-pg-accent bg-opacity-20 text-pg-accent">
                                        <?php echo ucfirst($payment['payment_method']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="status-badge <?php echo getStatusBadge($payment['payment_status']); ?>">
                                        <?php echo ucfirst($payment['payment_status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 hidden md:table-cell text-sm">
                                    <?php echo htmlspecialchars($payment['receipt_number'] ?? '-'); ?>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <div class="flex items-center justify-center space-x-1">
                                        <a href="view.php?id=<?php echo urlencode($payment['payment_id']); ?>" 
                                           class="text-blue-400 hover:text-blue-300 transition-colors duration-200 p-1" 
                                           title="View Payment">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                            </svg>
                                        </a>
                                        <a href="receipt.php?id=<?php echo urlencode($payment['payment_id']); ?>" 
                                           class="text-green-400 hover:text-green-300 transition-colors duration-200 p-1" 
                                           title="Print Receipt"
                                           target="_blank">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                                            </svg>
                                        </a>
                                        <a href="edit.php?id=<?php echo urlencode($payment['payment_id']); ?>" 
                                           class="text-yellow-400 hover:text-yellow-300 transition-colors duration-200 p-1" 
                                           title="Edit Payment">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
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

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="text-sm text-pg-text-secondary">
                Showing <?php echo min(($page - 1) * $perPage + 1, $totalPayments); ?> to 
                <?php echo min($page * $perPage, $totalPayments); ?> of 
                <?php echo $totalPayments; ?> payments
            </div>
            
            <nav class="flex items-center space-x-1">
                <!-- Previous Page -->
                <?php if ($page > 1): ?>
                    <a href="?<?php echo buildQueryString($queryParams, $page - 1); ?>" 
                       class="px-3 py-1 text-sm bg-pg-card border border-pg-border rounded text-pg-text-primary hover:bg-pg-hover transition-colors duration-200">
                        Previous
                    </a>
                <?php endif; ?>

                <!-- Page Numbers -->
                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                ?>
                
                <?php for ($p = $startPage; $p <= $endPage; $p++): ?>
                    <?php if ($p == $page): ?>
                        <span class="px-3 py-1 text-sm bg-pg-accent text-white rounded font-medium"><?php echo $p; ?></span>
                    <?php else: ?>
                        <a href="?<?php echo buildQueryString($queryParams, $p); ?>" 
                           class="px-3 py-1 text-sm bg-pg-card border border-pg-border rounded text-pg-text-primary hover:bg-pg-hover transition-colors duration-200">
                            <?php echo $p; ?>
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>

                <!-- Next Page -->
                <?php if ($page < $totalPages): ?>
                    <a href="?<?php echo buildQueryString($queryParams, $page + 1); ?>" 
                       class="px-3 py-1 text-sm bg-pg-card border border-pg-border rounded text-pg-text-primary hover:bg-pg-hover transition-colors duration-200">
                        Next
                    </a>
                <?php endif; ?>
            </nav>
        </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>
