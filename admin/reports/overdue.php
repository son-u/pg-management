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

// Get buildings data using the new Buildings class
try {
    $buildingCodes = Buildings::getCodes();
    $buildingNames = Buildings::getNames();
} catch (Exception $e) {
    error_log('Overdue payments buildings error: ' . $e->getMessage());
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

// Function to get all months that should be considered overdue (only past completed months)
function getOverdueMonths() {
    $overdueMonths = [];
    $currentDate = new DateTime();
    $currentMonth = $currentDate->format('Y-m');
    
    // Only check previous months (completed months)
    // Since business started in September 2025, only check from September onwards
    $startDate = new DateTime('2025-09-01'); // Business start date
    $checkDate = clone $startDate;
    
    while ($checkDate->format('Y-m') < $currentMonth) {
        $overdueMonths[] = $checkDate->format('Y-m');
        $checkDate->modify('+1 month');
    }
    
    return $overdueMonths;
}

// Function to calculate days overdue for a specific month (from end of that month)
function calculateDaysOverdue($monthYear) {
    // Calculate days since the month ended
    $monthEndDate = new DateTime($monthYear . '-01');
    $monthEndDate->modify('last day of this month');
    
    $currentDate = new DateTime();
    
    if ($currentDate > $monthEndDate) {
        return $currentDate->diff($monthEndDate)->days;
    }
    
    return 0;
}

// Function to get urgency level based on days overdue (since month ended)
function getUrgencyLevel($daysOverdue) {
    if ($daysOverdue > 90) {        // 3+ months overdue
        return 'critical';
    } elseif ($daysOverdue > 60) {  // 2+ months overdue
        return 'high';
    } elseif ($daysOverdue > 30) {  // 1+ months overdue
        return 'medium';
    } else {                        // Less than 1 month overdue
        return 'low';
    }
}

try {
    $supabase = supabase();
    
    if (!$supabase) {
        throw new Exception('Failed to connect to database');
    }

    // Get all active students with monthly_rent included
    $allActiveStudents = $supabase->select('students', 'student_id,full_name,building_code,phone,room_number,monthly_rent,email,admission_date', ['status' => 'active']);
    
    if ($allActiveStudents === false || $allActiveStudents === null) {
        throw new Exception('Failed to fetch students data');
    }

    // Get months that should be considered overdue
    $overdueMonths = getOverdueMonths();
    
    // Initialize variables
    $overduePayments = [];
    $totalOverdueAmount = 0;
    $totalOverduePayments = 0;
    $urgencyBreakdown = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
    $buildingBreakdown = [];
    
    if (empty($overdueMonths)) {
        // No overdue months yet (still in first month of operation)
        error_log('Overdue payments debug: No overdue months yet. Current month: ' . date('Y-m'));
    } else {
        // Get payments for all overdue months
        $allOverduePayments = [];
        foreach ($overdueMonths as $month) {
            $monthPayments = $supabase->select('payments', '*', ['month_year' => $month]);
            if ($monthPayments !== false && !empty($monthPayments)) {
                $allOverduePayments = array_merge($allOverduePayments, $monthPayments);
            }
        }

        // Create mapping of payments by student and month
        $paymentsByStudentMonth = [];
        foreach ($allOverduePayments as $payment) {
            $key = $payment['student_id'] . '_' . $payment['month_year'];
            $paymentsByStudentMonth[$key] = $payment;
        }

        // Debug logging
        error_log('Overdue payments debug: Total active students: ' . count($allActiveStudents));
        error_log('Overdue payments debug: Overdue months to check: ' . implode(', ', $overdueMonths));

        foreach ($allActiveStudents as $student) {
            $studentId = $student['student_id'];
            $studentBuilding = $student['building_code'];
            
            // Apply building filter
            if ($selectedBuilding !== 'all' && $studentBuilding !== $selectedBuilding) {
                continue;
            }
            
            // Check if student was admitted before or during each overdue month
            $studentAdmissionDate = new DateTime($student['admission_date'] ?? '2025-09-01');
            
            // Check each overdue month for this student
            foreach ($overdueMonths as $monthYear) {
                $monthStartDate = new DateTime($monthYear . '-01');
                $monthEndDate = new DateTime($monthYear . '-01');
                $monthEndDate->modify('last day of this month');
                
                // Skip if student wasn't admitted yet during this month
                if ($studentAdmissionDate > $monthEndDate) {
                    continue;
                }
                
                $paymentKey = $studentId . '_' . $monthYear;
                $hasPayment = isset($paymentsByStudentMonth[$paymentKey]);
                
                $daysOverdue = calculateDaysOverdue($monthYear);
                
                if (!$hasPayment) {
                    // Student has no payment record for this completed month - overdue
                    $monthlyRent = getMonthlyRentForStudent($student);
                    $urgency = getUrgencyLevel($daysOverdue);
                    
                    $overduePayment = [
                        'payment_id' => 'OVERDUE_' . $studentId . '_' . $monthYear,
                        'student_id' => $studentId,
                        'building_code' => $studentBuilding,
                        'month_year' => $monthYear,
                        'amount_due' => $monthlyRent,
                        'amount_paid' => 0,
                        'late_fee' => 0,
                        'overdue_balance' => $monthlyRent,
                        'payment_status' => 'overdue',
                        'payment_date' => null,
                        'due_date' => $monthEndDate->format('Y-m-d'),
                        'days_overdue' => $daysOverdue,
                        'urgency' => $urgency,
                        'created_at' => date('Y-m-d H:i:s'),
                        'student_info' => $student
                    ];
                    
                    // Apply urgency filter
                    if ($urgencyFilter !== 'all' && $urgency !== $urgencyFilter) {
                        continue;
                    }
                    
                    $overduePayments[] = $overduePayment;
                    $totalOverdueAmount += $monthlyRent;
                    $totalOverduePayments++;
                    
                } else {
                    // Student has payment record - check if there's outstanding balance
                    $payment = $paymentsByStudentMonth[$paymentKey];
                    $due = floatval($payment['amount_due'] ?? 0);
                    $paid = floatval($payment['amount_paid'] ?? 0);
                    $lateFee = floatval($payment['late_fee'] ?? 0);
                    $balance = ($due + $lateFee) - $paid;
                    
                    if ($balance > 0.01) { // Has outstanding balance for completed month
                        $urgency = getUrgencyLevel($daysOverdue);
                        
                        $payment['overdue_balance'] = round($balance, 2);
                        $payment['days_overdue'] = $daysOverdue;
                        $payment['due_date'] = $monthEndDate->format('Y-m-d');
                        $payment['urgency'] = $urgency;
                        $payment['student_info'] = $student;
                        
                        // Apply urgency filter
                        if ($urgencyFilter !== 'all' && $urgency !== $urgencyFilter) {
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

        // Calculate urgency and building breakdown
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
    }

    // Debug logging
    error_log('Overdue payments debug: Final overdue count: ' . count($overduePayments));
    error_log('Overdue payments debug: Total overdue amount: ' . $totalOverdueAmount);

} catch (Exception $e) {
    $error = 'Error loading overdue payments: ' . $e->getMessage();
    error_log('Overdue payments error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    $overduePayments = [];
    $totalOverdueAmount = 0;
    $totalOverduePayments = 0;
    $urgencyBreakdown = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
    $buildingBreakdown = [];
}

// Helper functions
function formatCurrency($amount) {
    return '₹' . number_format(floatval($amount), 2);
}

function formatDate($date) {
    if (empty($date)) return '-';
    return date('M d, Y', strtotime($date));
}

function getUrgencyBadge($urgency) {
    $badges = [
        'critical' => 'bg-red-500 bg-opacity-20 text-red-300 border border-red-500',
        'high' => 'bg-orange-500 bg-opacity-20 text-orange-300 border border-orange-500',
        'medium' => 'bg-yellow-500 bg-opacity-20 text-yellow-300 border border-yellow-500',
        'low' => 'bg-blue-500 bg-opacity-20 text-blue-300 border border-blue-500'
    ];
    return $badges[$urgency] ?? 'bg-gray-500 bg-opacity-20 text-gray-400';
}

function getStatusBadge($status) {
    $badges = [
        'paid' => 'status-badge status-active',
        'pending' => 'status-badge bg-yellow-500 bg-opacity-20 text-yellow-400',
        'partial' => 'status-badge bg-blue-500 bg-opacity-20 text-blue-400',
        'overdue' => 'status-badge status-inactive'
    ];
    return $badges[$status] ?? 'status-badge bg-gray-500 bg-opacity-20 text-gray-400';
}

function getUrgencyColor($urgency) {
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
                    Payments from completed months • Critical follow-ups required
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

    <!-- Info Message for First Month -->
    <?php if (empty($overdueMonths)): ?>
        <div class="bg-blue-500 bg-opacity-10 border border-blue-500 text-blue-400 px-4 py-3 rounded-lg">
            <div class="flex items-center">
                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                </svg>
                <span><strong>No Overdue Months Yet:</strong> Currently in <?php echo date('F Y'); ?>. Overdue payments will appear here from October 2025 onwards for any unpaid September payments.</span>
            </div>
        </div>
    <?php endif; ?>

    <!-- Debug Information (remove in production) -->
    <?php if (isset($_GET['debug'])): ?>
        <div class="bg-purple-500 bg-opacity-10 border border-purple-500 text-purple-400 px-4 py-3 rounded-lg">
            <h4 class="font-bold mb-2">Debug Information:</h4>
            <ul class="text-sm space-y-1">
                <li>Total Active Students: <?php echo count($allActiveStudents ?? []); ?></li>
                <li>Overdue Months to Check: <?php echo empty($overdueMonths) ? 'None yet' : implode(', ', $overdueMonths); ?></li>
                <li>Overdue Payments Found: <?php echo count($overduePayments); ?></li>
                <li>Total Overdue Amount: ₹<?php echo number_format($totalOverdueAmount); ?></li>
                <li>Selected Building: <?php echo $selectedBuilding; ?></li>
                <li>Selected Urgency Filter: <?php echo $urgencyFilter; ?></li>
            </ul>
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
                    <strong>Critical Alert:</strong> <?php echo $urgencyBreakdown['critical']; ?> payments are severely overdue (90+ days since month ended).
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
            <p class="text-sm text-pg-text-secondary">From completed months</p>
        </div>

        <!-- Number of Overdue Payments -->
        <div class="card text-center">
            <div class="text-3xl font-bold text-red-400 mb-2">
                <?php echo number_format($totalOverduePayments); ?>
            </div>
            <h3 class="text-lg font-semibold text-pg-text-primary mb-1">Overdue Payments</h3>
            <p class="text-sm text-pg-text-secondary">Past month-end</p>
        </div>

        <!-- Critical Cases -->
        <div class="card text-center">
            <div class="text-3xl font-bold text-red-300 mb-2">
                <?php echo number_format($urgencyBreakdown['critical']); ?>
            </div>
            <h3 class="text-lg font-semibold text-pg-text-primary mb-1">Critical Cases</h3>
            <p class="text-sm text-pg-text-secondary">90+ days overdue</p>
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
            <p class="text-sm text-pg-text-secondary">Since month-end</p>
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
                        <div class="text-xs text-pg-text-secondary">90+ days</div>
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
                        <div class="text-xs text-pg-text-secondary">61-90 days</div>
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
                        <div class="text-xs text-pg-text-secondary">31-60 days</div>
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
                        <div class="text-xs text-pg-text-secondary">1-30 days</div>
                    </div>
                    <svg class="w-8 h-8 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    <!-- Building-wise Breakdown -->
    <?php if (!empty($buildingBreakdown)): ?>
        <div class="card">
            <h3 class="text-lg font-semibold text-pg-text-primary mb-4 pb-2 border-b border-pg-border">
                Building-wise Overdue Amounts
            </h3>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($buildingBreakdown as $buildingCode => $data): ?>
                    <div class="p-4 bg-red-500 bg-opacity-10 border border-red-500 border-opacity-30 rounded-lg">
                        <div class="font-semibold text-pg-text-primary mb-1">
                            <?php echo htmlspecialchars($buildingNames[$buildingCode] ?? $buildingCode); ?>
                        </div>
                        <div class="text-red-400 font-bold text-xl">
                            <?php echo formatCurrency($data['amount']); ?>
                        </div>
                        <div class="text-sm text-pg-text-secondary">
                            <?php echo number_format($data['count']); ?> overdue payments
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

            <!-- Urgency Filter -->
            <div>
                <label for="urgency" class="block text-sm font-medium text-pg-text-primary mb-2">
                    Urgency Level
                </label>
                <select id="urgency" name="urgency" class="select-field w-full">
                    <option value="all" <?php echo $urgencyFilter === 'all' ? 'selected' : ''; ?>>All Levels</option>
                    <option value="critical" <?php echo $urgencyFilter === 'critical' ? 'selected' : ''; ?>>Critical (90+ days)</option>
                    <option value="high" <?php echo $urgencyFilter === 'high' ? 'selected' : ''; ?>>High (61-90 days)</option>
                    <option value="medium" <?php echo $urgencyFilter === 'medium' ? 'selected' : ''; ?>>Medium (31-60 days)</option>
                    <option value="low" <?php echo $urgencyFilter === 'low' ? 'selected' : ''; ?>>Low (1-30 days)</option>
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
                    <?php if (empty($overdueMonths)): ?>
                        <svg class="w-16 h-16 text-blue-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <h3 class="text-lg font-semibold text-pg-text-primary mb-2">First Month of Operations</h3>
                        <p class="text-pg-text-secondary">Overdue payments will appear here from next month onwards</p>
                    <?php else: ?>
                        <svg class="w-16 h-16 text-green-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <h3 class="text-lg font-semibold text-pg-text-primary mb-2">Excellent Collection Performance!</h3>
                        <p class="text-pg-text-secondary">No overdue payments found with current filters</p>
                    <?php endif; ?>
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
                            <th class="table-header">Month End</th>
                            <th class="table-header text-center">Days Overdue</th>
                            <th class="table-header text-center">Urgency</th>
                            <th class="table-header">Status</th>
                            <th class="table-header text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($overduePayments as $payment): ?>
                            <?php 
                            $student = $payment['student_info'];
                            $isOverdueRecord = strpos($payment['payment_id'], 'OVERDUE_') === 0;
                            ?>
                            <tr class="border-b border-pg-border hover:bg-pg-hover transition-colors duration-200">
                                <td class="px-6 py-4 font-mono text-sm">
                                    <?php if ($isOverdueRecord): ?>
                                        <span class="text-red-400 font-medium">NO RECORD</span>
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
                                    <div class="font-bold text-red-400">
                                        <?php echo formatCurrency($payment['overdue_balance']); ?>
                                    </div>
                                    <?php if (isset($payment['late_fee']) && $payment['late_fee'] > 0): ?>
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
                                        <?php if (!$isOverdueRecord): ?>
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
                                        <?php endif; ?>
                                        
                                        <!-- Record Payment Button -->
                                        <a href="../payments/add.php?student_id=<?php echo urlencode($payment['student_id']); ?>&amount=<?php echo urlencode($payment['overdue_balance']); ?>&month=<?php echo urlencode($payment['month_year']); ?>"
                                            class="text-green-400 hover:text-green-300 transition-colors duration-200 p-1"
                                            title="Record Payment">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                            </svg>
                                        </a>

                                        <?php if ($student && !empty($student['email'])): ?>
                                            <a href="mailto:<?php echo urlencode($student['email']); ?>?subject=Payment Overdue - <?php echo urlencode($payment['month_year']); ?>&body=Dear <?php echo urlencode($student['full_name']); ?>,%0A%0AYour payment for <?php echo urlencode($payment['month_year']); ?> of <?php echo urlencode(formatCurrency($payment['overdue_balance'])); ?> is overdue by <?php echo $payment['days_overdue']; ?> days.%0A%0APlease clear the dues immediately to avoid further penalties."
                                                class="text-purple-400 hover:text-purple-300 transition-colors duration-200 p-1"
                                                title="Send Email Reminder">
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
            🚨 Collection Actions
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
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <div class="font-medium text-blue-400">Contact List</div>
                <div class="text-sm text-pg-text-secondary">Export for follow-up</div>
            </a>

            <a href="pending.php" class="p-4 bg-yellow-500 bg-opacity-10 border border-yellow-500 border-opacity-30 rounded-lg hover:bg-yellow-500 hover:bg-opacity-20 transition-colors duration-200 text-center">
                <svg class="w-8 h-8 text-yellow-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div class="font-medium text-yellow-400">Current Pending</div>
                <div class="text-sm text-pg-text-secondary">This month's payments</div>
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
