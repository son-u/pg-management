<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

$title = 'Reports Overview';
require_once '../../includes/auth_check.php';

// Initialize variables
$error = '';
$metrics = [];

try {
    $supabase = supabase();

    // Get all students
    $students = $supabase->select('students', '*', []);
    
    // Get all payments
    $payments = $supabase->select('payments', '*', []);

    // Calculate key metrics
    $metrics = [
        'total_students' => count($students),
        'active_students' => count(array_filter($students, function($s) { 
            return ($s['status'] ?? 'active') === 'active'; 
        })),
        'total_buildings' => count(BUILDINGS),
        'total_payments' => count($payments),
        'total_collected' => array_sum(array_map(function($p) { 
            return floatval($p['amount_paid'] ?? 0); 
        }, $payments)),
        'pending_amount' => 0,
        'this_month_collection' => 0,
        'occupancy_rate' => 0
    ];

    // Calculate pending amounts and this month's collection
    $currentMonth = date('Y-m');
    foreach ($payments as $payment) {
        $due = floatval($payment['amount_due'] ?? 0);
        $paid = floatval($payment['amount_paid'] ?? 0);
        $lateFee = floatval($payment['late_fee'] ?? 0);
        
        $balance = ($due + $lateFee) - $paid;
        if ($balance > 0) {
            $metrics['pending_amount'] += $balance;
        }
        
        // This month's collection
        if (($payment['month_year'] ?? '') === $currentMonth) {
            $metrics['this_month_collection'] += $paid;
        }
    }

    // Calculate occupancy rate (assuming each active student occupies one room)
    // You can adjust this based on your room capacity data
    $totalCapacity = $metrics['total_buildings'] * 50; // Assuming 50 rooms per building
    $metrics['occupancy_rate'] = $totalCapacity > 0 ? 
        round(($metrics['active_students'] / $totalCapacity) * 100, 1) : 0;

    // Building-wise breakdown
    $buildingData = [];
    foreach (BUILDINGS as $buildingCode) {
        $buildingStudents = array_filter($students, function($s) use ($buildingCode) {
            return ($s['building_code'] ?? '') === $buildingCode && ($s['status'] ?? 'active') === 'active';
        });
        
        $buildingPayments = array_filter($payments, function($p) use ($buildingCode) {
            return ($p['building_code'] ?? '') === $buildingCode;
        });
        
        $buildingRevenue = array_sum(array_map(function($p) {
            return floatval($p['amount_paid'] ?? 0);
        }, $buildingPayments));

        $buildingData[$buildingCode] = [
            'name' => BUILDING_NAMES[$buildingCode],
            'students' => count($buildingStudents),
            'revenue' => $buildingRevenue,
            'payments' => count($buildingPayments)
        ];
    }

} catch (Exception $e) {
    $error = 'Error loading overview data: ' . $e->getMessage();
    error_log('Reports overview error: ' . $e->getMessage());
    
    // Default values
    $metrics = [
        'total_students' => 0,
        'active_students' => 0,
        'total_buildings' => 0,
        'total_payments' => 0,
        'total_collected' => 0,
        'pending_amount' => 0,
        'this_month_collection' => 0,
        'occupancy_rate' => 0
    ];
    $buildingData = [];
}
?>

<?php include '../../includes/header.php'; ?>

<!-- Reports Overview -->
<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-pg-text-primary">Reports Overview</h1>
            <p class="text-pg-text-secondary mt-1">
                Comprehensive summary of all PG operations and financial metrics
            </p>
        </div>
        
        <div class="flex items-center space-x-3">
            <a href="../dashboard.php" class="btn-secondary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2V7"></path>
                </svg>
                <span>Dashboard</span>
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

    <!-- Key Metrics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <!-- Total Students -->
        <div class="card text-center">
            <div class="text-3xl font-bold text-pg-accent mb-2">
                <?php echo number_format($metrics['total_students']); ?>
            </div>
            <h3 class="text-lg font-semibold text-pg-text-primary mb-1">Total Students</h3>
            <p class="text-sm text-pg-text-secondary">
                <?php echo number_format($metrics['active_students']); ?> Active
            </p>
        </div>

        <!-- Total Revenue -->
        <div class="card text-center">
            <div class="text-3xl font-bold text-pg-accent mb-2">
                ₹<?php echo number_format($metrics['total_collected'], 0); ?>
            </div>
            <h3 class="text-lg font-semibold text-pg-text-primary mb-1">Total Collected</h3>
            <p class="text-sm text-pg-text-secondary">
                From <?php echo number_format($metrics['total_payments']); ?> payments
            </p>
        </div>

        <!-- This Month Collection -->
        <div class="card text-center">
            <div class="text-3xl font-bold text-pg-accent mb-2">
                ₹<?php echo number_format($metrics['this_month_collection']); ?>
            </div>
            <h3 class="text-lg font-semibold text-pg-text-primary mb-1">This Month</h3>
            <p class="text-sm text-pg-text-secondary">
                <?php echo date('F Y'); ?> Collection
            </p>
        </div>

        <!-- Occupancy Rate -->
        <div class="card text-center">
            <div class="text-3xl font-bold text-pg-accent mb-2">
                <?php echo number_format($metrics['occupancy_rate'], 1); ?>%
            </div>
            <h3 class="text-lg font-semibold text-pg-text-primary mb-1">Occupancy Rate</h3>
            <p class="text-sm text-pg-text-secondary">
                Across <?php echo $metrics['total_buildings']; ?> buildings
            </p>
        </div>
    </div>

    <!-- Financial Summary -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Revenue Summary -->
        <div class="card">
            <h3 class="text-lg font-semibold text-pg-text-primary mb-4 pb-2 border-b border-pg-border flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
                Financial Summary
            </h3>
            
            <div class="space-y-4">
                <div class="flex justify-between items-center p-3 bg-pg-primary bg-opacity-50 rounded-lg">
                    <span class="text-pg-text-primary">Total Revenue</span>
                    <span class="font-semibold text-pg-accent">₹<?php echo number_format($metrics['total_collected']); ?></span>
                </div>
                
                <div class="flex justify-between items-center p-3 bg-pg-primary bg-opacity-50 rounded-lg">
                    <span class="text-pg-text-primary">This Month Collection</span>
                    <span class="font-semibold text-pg-accent">₹<?php echo number_format($metrics['this_month_collection']); ?></span>
                </div>
                
                <?php if ($metrics['pending_amount'] > 0): ?>
                    <div class="flex justify-between items-center p-3 bg-red-500 bg-opacity-20 rounded-lg">
                        <span class="text-pg-text-primary">Pending Amount</span>
                        <span class="font-semibold text-status-danger">₹<?php echo number_format($metrics['pending_amount']); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <h3 class="text-lg font-semibold text-pg-text-primary mb-4 pb-2 border-b border-pg-border flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                Detailed Reports
            </h3>
            
            <div class="grid grid-cols-1 gap-3">
                <a href="monthly.php" class="p-3 bg-pg-primary bg-opacity-50 rounded-lg hover:bg-pg-hover transition-colors duration-200 flex items-center justify-between">
                    <span class="text-pg-text-primary">Monthly Reports</span>
                    <svg class="w-4 h-4 text-pg-text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </a>
                
                <a href="building_wise.php" class="p-3 bg-pg-primary bg-opacity-50 rounded-lg hover:bg-pg-hover transition-colors duration-200 flex items-center justify-between">
                    <span class="text-pg-text-primary">Building-wise Reports</span>
                    <svg class="w-4 h-4 text-pg-text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </a>
                
                <a href="pending.php" class="p-3 bg-pg-primary bg-opacity-50 rounded-lg hover:bg-pg-hover transition-colors duration-200 flex items-center justify-between">
                    <span class="text-pg-text-primary">Pending Payments</span>
                    <?php if ($metrics['pending_amount'] > 0): ?>
                        <span class="bg-status-danger text-white px-2 py-1 rounded-full text-xs">
                            ₹<?php echo number_format($metrics['pending_amount']); ?>
                        </span>
                    <?php else: ?>
                        <svg class="w-4 h-4 text-pg-text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    <?php endif; ?>
                </a>
                
                <a href="overdue.php" class="p-3 bg-pg-primary bg-opacity-50 rounded-lg hover:bg-pg-hover transition-colors duration-200 flex items-center justify-between">
                    <span class="text-pg-text-primary">Overdue Payments</span>
                    <svg class="w-4 h-4 text-pg-text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </a>
                
                <a href="export.php" class="p-3 bg-pg-primary bg-opacity-50 rounded-lg hover:bg-pg-hover transition-colors duration-200 flex items-center justify-between">
                    <span class="text-pg-text-primary">Export Data</span>
                    <svg class="w-4 h-4 text-pg-text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </a>
            </div>
        </div>
    </div>

    <!-- Building-wise Summary -->
    <?php if (!empty($buildingData)): ?>
        <div class="card">
            <h3 class="text-lg font-semibold text-pg-text-primary mb-4 pb-2 border-b border-pg-border flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
                Building Performance Summary
            </h3>
            
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="border-b border-pg-border">
                            <th class="table-header text-left">Building</th>
                            <th class="table-header text-center">Students</th>
                            <th class="table-header text-center">Payments</th>
                            <th class="table-header text-right">Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($buildingData as $code => $data): ?>
                            <tr class="border-b border-pg-border hover:bg-pg-hover transition-colors duration-200">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-pg-text-primary"><?php echo htmlspecialchars($data['name']); ?></div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-pg-accent bg-opacity-20 text-pg-accent">
                                        <?php echo number_format($data['students']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <?php echo number_format($data['payments']); ?>
                                </td>
                                <td class="px-6 py-4 text-right font-semibold text-pg-accent">
                                    ₹<?php echo number_format($data['revenue']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="mt-4 text-center">
                <a href="building_wise.php" class="btn-secondary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    <span>View Detailed Building Reports</span>
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>
