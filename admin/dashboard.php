<?php
$title = 'Dashboard';
require_once '../includes/header.php';
require_once '../config/database.php';

// Get buildings data using the new Buildings class
try {
    $buildingCodes = Buildings::getCodes();
    $buildingNames = Buildings::getNames();
} catch (Exception $e) {
    error_log('Dashboard buildings error: ' . $e->getMessage());
    $buildingCodes = [];
    $buildingNames = [];
}

// Get current month for data
$currentMonth = date('Y-m');
$previousMonth = date('Y-m', strtotime('-1 month'));
$selectedBuilding = $_GET['building'] ?? 'all';

// Initialize variables
$totalStudents = 0;
$totalRevenue = 0;
$pendingPayments = 0;
$buildingStats = [];
$recentStudents = [];
$recentPayments = [];
$monthlyGrowth = 0;
$systemStatus = 'operational';
$lastUpdated = date('M j, Y g:i A');
$totalCapacity = 0;
$totalOccupied = 0;

try {
    $supabase = supabase();

    // ✅ NEW: Get actual room capacity data from database
    $allRoomsData = $supabase->select('rooms', 'building_code,capacity,current_occupancy,status', []);
    
    // Calculate total capacity and occupancy from actual room data
    foreach ($allRoomsData as $room) {
        if ($selectedBuilding === 'all' || $room['building_code'] === $selectedBuilding) {
            $totalCapacity += intval($room['capacity']);
            $totalOccupied += intval($room['current_occupancy']);
        }
    }

    // Get active students count
    $studentsFilter = ['status' => 'active'];
    if ($selectedBuilding !== 'all') {
        $studentsFilter['building_code'] = $selectedBuilding;
    }

    $students = $supabase->select('students', 'id,student_id,full_name,building_code,created_at', $studentsFilter);
    $totalStudents = count($students);

    // ✅ FIXED: Get recent students with building filter
    $studentsFilterForRecent = ['status' => 'active'];
    if ($selectedBuilding !== 'all') {
        $studentsFilterForRecent['building_code'] = $selectedBuilding;
    }

    $allStudents = $supabase->select('students', 'student_id,full_name,building_code,created_at', $studentsFilterForRecent);
    usort($allStudents, function ($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    $recentStudents = array_slice($allStudents, 0, 5);

    // Get total revenue for current month
    $paymentsFilter = ['month_year' => $currentMonth];
    if ($selectedBuilding !== 'all') {
        $paymentsFilter['building_code'] = $selectedBuilding;
    }

    $payments = $supabase->select('payments', 'amount_paid,student_id,created_at', $paymentsFilter);
    $totalRevenue = array_sum(array_column($payments, 'amount_paid'));

    // ✅ FIXED: Get recent payments with building filter and student names
    $recentPayments = [];
    try {
        $paymentsFilterForRecent = [];
        if ($selectedBuilding !== 'all') {
            $paymentsFilterForRecent['building_code'] = $selectedBuilding;
        }
        
        $allPayments = $supabase->select('payments', 'payment_id,amount_paid,student_id,building_code,created_at', $paymentsFilterForRecent);
        usort($allPayments, function ($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        // Get top 5 recent payments
        $topPayments = array_slice($allPayments, 0, 5);
        
        // Get student names for these payments
        if (!empty($topPayments)) {
            $studentIds = array_unique(array_column($topPayments, 'student_id'));
            $studentNames = $supabase->select('students', 'student_id,full_name', []);
            
            // Create student ID to name mapping
            $studentNamesMap = [];
            foreach ($studentNames as $student) {
                $studentNamesMap[$student['student_id']] = $student['full_name'];
            }
            
            // Add student names to payments
            foreach ($topPayments as $payment) {
                $studentId = $payment['student_id'];
                $payment['student_name'] = $studentNamesMap[$studentId] ?? 'Unknown Student';
                $recentPayments[] = $payment;
            }
        }
    } catch (Exception $e) {
        error_log('Recent payments error: ' . $e->getMessage());
        $recentPayments = [];
    }

    // Calculate monthly growth
    $previousMonthPayments = $supabase->select('payments', 'amount_paid', ['month_year' => $previousMonth]);
    $previousRevenue = array_sum(array_column($previousMonthPayments, 'amount_paid'));

    if ($previousRevenue > 0) {
        $monthlyGrowth = round((($totalRevenue - $previousRevenue) / $previousRevenue) * 100, 1);
    }

    // Get pending payments (students without payments for current month)
    $allActiveStudents = $supabase->select('students', 'student_id,building_code,full_name', ['status' => 'active']);
    $allCurrentPayments = $supabase->select('payments', 'student_id', ['month_year' => $currentMonth]);
    $paidStudentIds = array_column($allCurrentPayments, 'student_id');

    $pendingStudents = array_filter($allActiveStudents, function ($student) use ($paidStudentIds, $selectedBuilding) {
        $hasPayment = in_array($student['student_id'], $paidStudentIds);
        $matchesBuilding = $selectedBuilding === 'all' || $student['building_code'] === $selectedBuilding;
        return !$hasPayment && $matchesBuilding;
    });
    $pendingPayments = count($pendingStudents);

    // ✅ UPDATED: Get accurate building-wise data with better performance calculation
    $buildingStats = [];
    if (!empty($buildingCodes)) {
        foreach ($buildingCodes as $code) {
            // Get students for this building
            $buildingStudents = $supabase->select('students', 'id', [
                'building_code' => $code,
                'status' => 'active'
            ]);

            // Get payments for this building
            $buildingPayments = $supabase->select('payments', 'amount_paid', [
                'building_code' => $code,
                'month_year' => $currentMonth
            ]);

            // Get actual room data for this building
            $buildingRooms = $supabase->select('rooms', 'capacity,current_occupancy,status', [
                'building_code' => $code
            ]);

            $studentCount = count($buildingStudents);
            $revenue = array_sum(array_column($buildingPayments, 'amount_paid'));
            $totalRooms = count($buildingRooms);
            
            // Calculate actual capacity and occupancy from room data
            $buildingCapacity = 0;
            $buildingOccupancy = 0;
            foreach ($buildingRooms as $room) {
                $buildingCapacity += intval($room['capacity']);
                $buildingOccupancy += intval($room['current_occupancy']);
            }
            
            $occupancyRate = $buildingCapacity > 0 ? round(($buildingOccupancy / $buildingCapacity) * 100, 1) : 0;

            // ✅ ENHANCED: Better performance calculation
            $performance = 'needs_attention'; // Default
            
            if ($revenue > 0 && $occupancyRate > 70) {
                $performance = 'excellent';
            } elseif ($revenue > 0 && $occupancyRate > 50) {
                $performance = 'good';
            } elseif ($revenue > 0 || $occupancyRate > 30) {
                $performance = 'fair';
            }
            // else remains 'needs_attention'

            $buildingStats[$code] = [
                'students' => $studentCount,
                'revenue' => $revenue,
                'occupancy' => min($occupancyRate, 100),
                'total_rooms' => $totalRooms,
                'capacity' => $buildingCapacity,
                'current_occupancy' => $buildingOccupancy,
                'performance' => $performance
            ];
        }
    }

} catch (Exception $e) {
    $error = 'Error loading dashboard data: ' . $e->getMessage();
    error_log('Dashboard error: ' . $e->getMessage());
    $systemStatus = 'error';
}

// ✅ UPDATED: Calculate accurate occupancy rate
$occupancyRate = $totalCapacity > 0 ? round(($totalOccupied / $totalCapacity) * 100, 1) : 0;
?>

<!-- Dashboard Content -->
<div class="space-y-4 sm:space-y-6 px-4 sm:px-6 lg:px-8 animate-fade-in">

    <!-- Mobile Header -->
    <div class="lg:hidden mb-4">
        <div class="flex items-center justify-between">
            <h1 class="text-2xl font-bold text-pg-text-primary">Dashboard</h1>
            <?php if ($selectedBuilding !== 'all'): ?>
                <span class="text-xs bg-pg-accent bg-opacity-20 text-pg-accent px-2 py-1 rounded">
                    <?php echo htmlspecialchars($buildingNames[$selectedBuilding] ?? $selectedBuilding); ?>
                </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Desktop Page Header -->
    <div class="hidden lg:flex flex-col lg:flex-row lg:items-center lg:justify-between">
        <div class="min-w-0 flex-1">
            <h1 class="text-3xl font-bold text-pg-text-primary">Dashboard Overview</h1>
            <div class="mt-2 flex flex-col sm:flex-row sm:flex-wrap sm:space-x-6">
                <div class="flex items-center text-sm text-pg-text-secondary">
                    <svg class="flex-shrink-0 mr-1.5 h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-4m-5 0H3m2 0V9a2 2 0 012-2h4a2 2 0 012 2v12"></path>
                    </svg>
                    <?php if ($selectedBuilding === 'all'): ?>
                        Multi-building performance summary • <?php echo $totalCapacity; ?> total bed capacity
                    <?php else: ?>
                        <?php echo htmlspecialchars($buildingNames[$selectedBuilding] ?? $selectedBuilding); ?> performance summary
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="mt-4 lg:mt-0 lg:ml-4">
            <div class="flex items-center">
                <div class="flex items-center text-sm text-pg-text-secondary">
                    <div class="w-2 h-2 bg-<?php echo $systemStatus === 'operational' ? 'status-success' : 'status-danger'; ?> rounded-full mr-2"></div>
                    <span>System <?php echo ucfirst($systemStatus); ?></span>
                </div>
                <div class="ml-4 text-right">
                    <p class="text-sm text-pg-text-secondary">Last updated</p>
                    <p class="text-base lg:text-lg font-semibold text-pg-text-primary"><?php echo $lastUpdated; ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- ✅ UPDATED: Show API Status Alert only on main dashboard (all buildings view) -->
<?php if ($selectedBuilding === 'all'): ?>
    <div class="bg-status-success bg-opacity-10 border border-status-success text-status-success px-3 sm:px-4 py-2 sm:py-3 rounded-lg text-sm animate-slide-up">
        <div class="flex items-center">
            <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
            </svg>
            <span class="font-medium">Real-time Data:</span>
            <span class="ml-1">Room occupancy auto-synced • <?php echo count($allRoomsData); ?> rooms monitored</span>
            <div class="ml-auto hidden sm:flex items-center">
                <span class="text-xs bg-status-success bg-opacity-20 px-2 py-1 rounded">Live Sync Active</span>
            </div>
        </div>
    </div>
<?php endif; ?>


    <!-- Key Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
        <!-- Total Revenue Card -->
        <div class="revenue-card animate-slide-up" style="animation-delay: 0.1s;">
            <div class="flex items-center justify-between">
                <div class="min-w-0 flex-1">
                    <p class="text-pg-text-secondary text-xs sm:text-sm font-medium truncate">Monthly Revenue</p>
                    <p class="text-xl sm:text-2xl font-bold text-pg-text-primary">₹<?php echo number_format($totalRevenue); ?></p>
                    <div class="flex items-center mt-1">
                        <?php if ($monthlyGrowth > 0): ?>
                            <svg class="w-3 h-3 mr-1 text-status-success" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M3.293 9.707a1 1 0 010-1.414l6-6a1 1 0 011.414 0l6 6a1 1 0 01-1.414 1.414L11 5.414V17a1 1 0 11-2 0V5.414L4.707 9.707a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-xs text-status-success">+<?php echo $monthlyGrowth; ?>%</span>
                        <?php elseif ($monthlyGrowth < 0): ?>
                            <svg class="w-3 h-3 mr-1 text-status-warning" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 10.293a1 1 0 010 1.414l-6 6a1 1 0 01-1.414 0l-6-6a1 1 0 111.414-1.414L9 14.586V3a1 1 0 112 0v11.586l4.293-4.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-xs text-status-warning"><?php echo $monthlyGrowth; ?>%</span>
                        <?php else: ?>
                            <svg class="w-3 h-3 mr-1 text-pg-text-secondary" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M3 10a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-xs text-pg-text-secondary">0%</span>
                        <?php endif; ?>
                        <span class="text-xs text-pg-text-secondary ml-1 hidden sm:inline">vs last month</span>
                    </div>
                </div>
                <div class="bg-pg-accent bg-opacity-20 p-2 sm:p-3 rounded-lg ml-2">
                    <svg class="w-5 h-5 sm:w-6 sm:h-6 text-pg-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                </div>
            </div>
        </div>

        <!-- Total Students Card -->
        <div class="card animate-slide-up" style="animation-delay: 0.2s;">
            <div class="flex items-center justify-between">
                <div class="min-w-0 flex-1">
                    <p class="text-pg-text-secondary text-xs sm:text-sm font-medium truncate">Active Students</p>
                    <p class="text-xl sm:text-2xl font-bold text-pg-text-primary"><?php echo $totalStudents; ?></p>
                    <p class="text-xs text-status-info mt-1">Currently enrolled</p>
                </div>
                <div class="bg-blue-500 bg-opacity-20 p-2 sm:p-3 rounded-lg ml-2">
                    <svg class="w-5 h-5 sm:w-6 sm:h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <circle cx="12" cy="8" r="3" stroke-width="2" />
                        <path d="M6 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </div>
            </div>
        </div>

        <!-- Occupancy Rate Card -->
        <div class="card animate-slide-up" style="animation-delay: 0.3s;">
            <div class="flex items-center justify-between">
                <div class="min-w-0 flex-1">
                    <p class="text-pg-text-secondary text-xs sm:text-sm font-medium truncate">Bed Occupancy Rate</p>
                    <p class="text-xl sm:text-2xl font-bold text-pg-text-primary"><?php echo $occupancyRate; ?>%</p>
                    <p class="text-xs text-pg-text-secondary mt-1"><?php echo $totalOccupied; ?>/<?php echo $totalCapacity; ?> beds occupied</p>
                </div>
                <div class="bg-purple-500 bg-opacity-20 p-2 sm:p-3 rounded-lg ml-2">
                    <svg class="w-5 h-5 sm:w-6 sm:h-6 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-4m-5 0H3m2 0V9a2 2 0 012-2h4a2 2 0 012 2v12"></path>
                    </svg>
                </div>
            </div>
            <!-- Progress Bar -->
            <div class="mt-4 w-full bg-pg-border rounded-full h-2">
                <div class="bg-purple-500 h-2 rounded-full transition-all duration-1000"
                    style="width: <?php echo min($occupancyRate, 100); ?>%"></div>
            </div>
        </div>

        <!-- Pending Payments Card -->
        <div class="card animate-slide-up" style="animation-delay: 0.4s;">
            <div class="flex items-center justify-between">
                <div class="min-w-0 flex-1">
                    <p class="text-pg-text-secondary text-xs sm:text-sm font-medium truncate">Pending Payments</p>
                    <p class="text-xl sm:text-2xl font-bold <?php echo $pendingPayments > 0 ? 'text-status-warning' : 'text-status-success'; ?>">
                        <?php echo $pendingPayments; ?>
                    </p>
                    <p class="text-xs text-pg-text-secondary mt-1">
                        <?php echo $pendingPayments > 0 ? 'Students pending' : 'All payments current'; ?>
                    </p>
                </div>
                <div class="bg-<?php echo $pendingPayments > 0 ? 'yellow' : 'green'; ?>-500 bg-opacity-20 p-2 sm:p-3 rounded-lg ml-2">
                    <?php if ($pendingPayments > 0): ?>
                        <svg class="w-5 h-5 sm:w-6 sm:h-6 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    <?php else: ?>
                        <svg class="w-5 h-5 sm:w-6 sm:h-6 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- ✅ UPDATED: Building-wise Performance with Enhanced Logic -->
    <?php if ($selectedBuilding === 'all' && !empty($buildingStats)): ?>
        <div class="card animate-slide-up" style="animation-delay: 0.5s;">
            <div class="flex items-center justify-between mb-4 sm:mb-6">
                <h3 class="text-base sm:text-lg font-semibold text-pg-text-primary">Building Performance</h3>
                <div class="text-xs text-pg-text-secondary">Real-time occupancy data</div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
                <?php foreach ($buildingStats as $code => $stats): ?>
                    <div class="building-card hover:shadow-lg transition-shadow duration-300">
                        <div class="flex items-center justify-between mb-3 sm:mb-4">
                            <div class="min-w-0 flex-1">
                                <h4 class="font-semibold text-pg-text-primary text-sm sm:text-base truncate">
                                    <?php echo htmlspecialchars($buildingNames[$code] ?? $code); ?>
                                </h4>
                                <p class="text-xs text-pg-text-secondary"><?php echo $stats['total_rooms']; ?> rooms • <?php echo $stats['capacity']; ?> bed capacity</p>
                            </div>
                            <div class="flex items-center ml-2">
                                <span class="text-xs bg-pg-accent bg-opacity-20 text-pg-accent px-2 py-1 rounded mr-2"><?php echo htmlspecialchars($code); ?></span>
                                <!-- ✅ UPDATED: Enhanced status indicator -->
                                <div class="w-2 h-2 bg-<?php 
                                    echo $stats['performance'] === 'excellent' ? 'green-500' : 
                                         ($stats['performance'] === 'good' ? 'status-success' : 
                                         ($stats['performance'] === 'fair' ? 'status-warning' : 'status-danger')); 
                                ?> rounded-full"></div>
                            </div>
                        </div>

                        <div class="space-y-2 sm:space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-xs sm:text-sm text-pg-text-secondary">Monthly Revenue</span>
                                <span class="font-semibold text-pg-text-primary text-sm">₹<?php echo number_format($stats['revenue']); ?></span>
                            </div>

                            <div class="flex justify-between items-center">
                                <span class="text-xs sm:text-sm text-pg-text-secondary">Active Students</span>
                                <span class="font-semibold text-pg-text-primary text-sm"><?php echo $stats['students']; ?></span>
                            </div>

                            <div class="flex justify-between items-center">
                                <span class="text-xs sm:text-sm text-pg-text-secondary">Bed Occupancy</span>
                                <span class="font-semibold text-<?php echo $stats['occupancy'] > 80 ? 'status-success' : ($stats['occupancy'] > 60 ? 'status-warning' : 'status-danger'); ?> text-sm">
                                    <?php echo $stats['occupancy']; ?>%
                                </span>
                            </div>

                            <!-- Progress Bar -->
                            <div class="space-y-2">
                                <div class="w-full bg-pg-border rounded-full h-2">
                                    <div class="bg-pg-accent h-2 rounded-full transition-all duration-1000"
                                        style="width: <?php echo min($stats['occupancy'], 100); ?>%"></div>
                                </div>
                                <!-- ✅ UPDATED: Enhanced performance labels -->
                                <div class="flex justify-between text-xs text-pg-text-secondary">
                                    <span>Performance: 
                                        <?php 
                                        $performanceLabels = [
                                            'excellent' => 'Excellent',
                                            'good' => 'Good', 
                                            'fair' => 'Fair',
                                            'needs_attention' => 'Needs Attention'
                                        ];
                                        echo $performanceLabels[$stats['performance']] ?? 'Unknown'; 
                                        ?>
                                    </span>
                                    <span><?php echo $stats['current_occupancy']; ?>/<?php echo $stats['capacity']; ?> beds</span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-3 sm:mt-4 pt-3 sm:pt-4 border-t border-pg-border">
                            <a href="<?php echo route('admin/reports/building_wise.php') . '?building=' . urlencode($code); ?>"
                                class="text-xs text-pg-accent hover:text-green-400 font-medium flex items-center justify-center sm:justify-start">
                                <span>View Detailed Report</span>
                                <svg class="w-3 h-3 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Recent Activity Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
        <!-- ✅ FIXED: Recent Students (Building-Filtered) -->
        <div class="card animate-slide-up" style="animation-delay: 0.6s;">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base sm:text-lg font-semibold text-pg-text-primary">
                    Recent Students
                    <?php if ($selectedBuilding !== 'all'): ?>
                        <span class="text-xs text-pg-text-secondary ml-1">
                            (<?php echo htmlspecialchars($buildingNames[$selectedBuilding] ?? $selectedBuilding); ?>)
                        </span>
                    <?php endif; ?>
                </h3>
                <a href="<?php echo route('admin/students/index.php'); ?>" class="text-xs text-pg-accent hover:text-green-400">View All</a>
            </div>

            <?php if (!empty($recentStudents)): ?>
                <div class="space-y-3">
                    <?php foreach ($recentStudents as $student): ?>
                        <div class="flex items-center justify-between p-3 bg-pg-secondary rounded-lg hover:bg-pg-hover transition-colors duration-200">
                            <div class="flex items-center min-w-0 flex-1">
                                <div class="bg-blue-500 bg-opacity-20 p-2 rounded-full mr-3 flex-shrink-0">
                                    <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-pg-text-primary truncate"><?php echo htmlspecialchars($student['full_name']); ?></p>
                                    <p class="text-xs text-pg-text-secondary"><?php echo htmlspecialchars($student['building_code']); ?> • <?php echo date('M j', strtotime($student['created_at'])); ?></p>
                                </div>
                            </div>
                            <span class="text-xs bg-status-success bg-opacity-20 text-status-success px-2 py-1 rounded ml-2 flex-shrink-0">Active</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8 text-pg-text-secondary">
                    <svg class="w-12 h-12 mx-auto mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                    </svg>
                    <p class="text-sm">No students found</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- ✅ FIXED: Recent Payments (Building-Filtered) -->
        <div class="card animate-slide-up" style="animation-delay: 0.7s;">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-base sm:text-lg font-semibold text-pg-text-primary">
                    Recent Payments
                    <?php if ($selectedBuilding !== 'all'): ?>
                        <span class="text-xs text-pg-text-secondary ml-1">
                            (<?php echo htmlspecialchars($buildingNames[$selectedBuilding] ?? $selectedBuilding); ?>)
                        </span>
                    <?php endif; ?>
                </h3>
                <a href="<?php echo route('admin/payments/index.php'); ?>" class="text-xs text-pg-accent hover:text-green-400">View All</a>
            </div>

            <?php if (!empty($recentPayments)): ?>
                <div class="space-y-3">
                    <?php foreach ($recentPayments as $payment): ?>
                        <div class="flex items-center justify-between p-3 bg-pg-secondary rounded-lg hover:bg-pg-hover transition-colors duration-200">
                            <div class="flex items-center min-w-0 flex-1">
                                <div class="bg-green-500 bg-opacity-20 p-2 rounded-full mr-3 flex-shrink-0">
                                    <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                    </svg>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-medium text-pg-text-primary">₹<?php echo number_format($payment['amount_paid']); ?></p>
                                    <!-- ✅ UPDATED: Show student name along with building and date -->
                                    <p class="text-xs text-pg-text-secondary">
                                        <?php echo htmlspecialchars($payment['student_name']); ?> • 
                                        <?php echo htmlspecialchars($payment['building_code']); ?> • 
                                        <?php echo date('M j', strtotime($payment['created_at'])); ?>
                                    </p>
                                </div>
                            </div>
                            <span class="text-xs bg-status-success bg-opacity-20 text-status-success px-2 py-1 rounded ml-2 flex-shrink-0">Paid</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8 text-pg-text-secondary">
                    <svg class="w-12 h-12 mx-auto mb-4 opacity-50" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                    <p class="text-sm">No recent payments</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 animate-slide-up" style="animation-delay: 0.8s;">
        <a href="<?php echo route('admin/students/add.php'); ?>"
            class="bg-pg-card border border-pg-border hover:border-pg-accent rounded-lg p-3 sm:p-4 transition-all duration-200 group hover:shadow-lg transform hover:scale-[1.02]">
            <div class="flex items-center">
                <div class="bg-blue-500 bg-opacity-20 p-2 rounded-lg group-hover:bg-opacity-30 transition-colors duration-200 flex-shrink-0">
                    <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                    </svg>
                </div>
                <div class="ml-3 min-w-0 flex-1">
                    <p class="text-sm font-medium text-pg-text-primary">Add Student</p>
                    <p class="text-xs text-pg-text-secondary">Register new student</p>
                </div>
            </div>
        </a>

        <a href="<?php echo route('admin/payments/add.php'); ?>"
            class="bg-pg-card border border-pg-border hover:border-pg-accent rounded-lg p-3 sm:p-4 transition-all duration-200 group hover:shadow-lg transform hover:scale-[1.02]">
            <div class="flex items-center">
                <div class="bg-green-500 bg-opacity-20 p-2 rounded-lg group-hover:bg-opacity-30 transition-colors duration-200 flex-shrink-0">
                    <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                </div>
                <div class="ml-3 min-w-0 flex-1">
                    <p class="text-sm font-medium text-pg-text-primary">Record Payment</p>
                    <p class="text-xs text-pg-text-secondary">Add new payment</p>
                </div>
            </div>
        </a>

        <a href="<?php echo route('admin/reports/pending.php'); ?>"
            class="bg-pg-card border border-pg-border hover:border-pg-accent rounded-lg p-3 sm:p-4 transition-all duration-200 group hover:shadow-lg transform hover:scale-[1.02]">
            <div class="flex items-center">
                <div class="bg-yellow-500 bg-opacity-20 p-2 rounded-lg group-hover:bg-opacity-30 transition-colors duration-200 flex-shrink-0">
                    <svg class="w-5 h-5 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="ml-3 min-w-0 flex-1">
                    <p class="text-sm font-medium text-pg-text-primary">Pending Payments</p>
                    <p class="text-xs text-pg-text-secondary">View outstanding balances</p>
                </div>
            </div>
        </a>

        <a href="<?php echo route('admin/reports/monthly.php'); ?>"
            class="bg-pg-card border border-pg-border hover:border-pg-accent rounded-lg p-3 sm:p-4 transition-all duration-200 group hover:shadow-lg transform hover:scale-[1.02]">
            <div class="flex items-center">
                <div class="bg-purple-500 bg-opacity-20 p-2 rounded-lg group-hover:bg-opacity-30 transition-colors duration-200 flex-shrink-0">
                    <svg class="w-5 h-5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                </div>
                <div class="ml-3 min-w-0 flex-1">
                    <p class="text-sm font-medium text-pg-text-primary">Monthly Report</p>
                    <p class="text-xs text-pg-text-secondary">View detailed reports</p>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- Mobile-specific CSS adjustments -->
<style>
    .animate-fade-in {
        animation: fadeIn 0.6s ease-out;
    }

    .animate-slide-up {
        animation: slideUp 0.6s ease-out forwards;
        animation-fill-mode: both;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @keyframes slideUp {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    @media (max-width: 1024px) {
        .space-y-4>*+* {
            margin-top: 1rem;
        }
        .space-y-6>*+* {
            margin-top: 1.5rem;
        }
        .card, .revenue-card, .building-card {
            position: relative;
            z-index: 1;
        }
        .grid {
            gap: 1rem;
        }
        @media (min-width: 640px) {
            .grid {
                gap: 1.5rem;
            }
        }
    }

    @media (max-width: 640px) {
        .text-3xl {
            font-size: 1.875rem;
            line-height: 2.25rem;
        }
        .text-2xl {
            font-size: 1.5rem;
            line-height: 2rem;
        }
    }

    * {
        box-sizing: border-box;
    }
    .min-w-0 {
        min-width: 0;
    }
    .truncate {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .flex-shrink-0 {
        flex-shrink: 0;
    }
</style>

<?php require_once '../includes/footer.php'; ?>
