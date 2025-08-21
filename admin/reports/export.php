<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

$title = 'Data Export';
require_once '../../includes/auth_check.php';

// Handle direct export requests
if (isset($_GET['action']) && $_GET['action'] === 'download') {
    handleDirectExport();
}

// Initialize variables
$error = '';
$success = '';

// Get buildings data using the new Buildings class
try {
    $buildingCodes = Buildings::getCodes();
    $buildingNames = Buildings::getNames();
} catch (Exception $e) {
    error_log('Export page buildings error: ' . $e->getMessage());
    $buildingCodes = [];
    $buildingNames = [];
}

try {
    $supabase = supabase();
    
    // Get summary statistics for export options
    $students = $supabase->select('students', '*', []);
    $payments = $supabase->select('payments', '*', []);
    
    $totalStudents = count($students);
    $activeStudents = count(array_filter($students, function($s) { 
        return ($s['status'] ?? 'active') === 'active'; 
    }));
    
    $totalPayments = count($payments);
    
    // Calculate pending and overdue counts
    $pendingCount = 0;
    $overdueCount = 0;
    $currentTime = time();
    
    foreach ($payments as $payment) {
        $due = floatval($payment['amount_due'] ?? 0);
        $paid = floatval($payment['amount_paid'] ?? 0);
        $lateFee = floatval($payment['late_fee'] ?? 0);
        $balance = ($due + $lateFee) - $paid;
        
        if ($balance > 0) {
            $pendingCount++;
            
            // Check if overdue
            $monthYear = $payment['month_year'] ?? '';
            if ($monthYear) {
                $dueDate = strtotime($monthYear . '-01 +1 month -1 day');
                if ($dueDate && $dueDate < $currentTime) {
                    $overdueCount++;
                }
            }
        }
    }

} catch (Exception $e) {
    $error = 'Error loading export data: ' . $e->getMessage();
    error_log('Export page error: ' . $e->getMessage());
    $totalStudents = $activeStudents = $totalPayments = $pendingCount = $overdueCount = 0;
}

// Export handler function
function handleDirectExport() {
    try {
        $supabase = supabase();
        $exportType = $_GET['type'] ?? '';
        $format = $_GET['format'] ?? 'csv';
        
        if (!$exportType) {
            throw new Exception('Export type is required');
        }
        
        switch ($exportType) {
            case 'students':
                exportStudents($supabase, $format);
                break;
                
            case 'active_students':
                exportActiveStudents($supabase, $format);
                break;
                
            case 'payments':
                exportPayments($supabase, $format);
                break;
                
            case 'pending':
                exportPendingPayments($supabase, $format);
                break;
                
            case 'overdue':
                exportOverduePayments($supabase, $format);
                break;
                
            case 'monthly':
                $month = $_GET['month'] ?? date('Y-m');
                exportMonthlyPayments($supabase, $format, $month);
                break;
                
            case 'building':
                $building = $_GET['building'] ?? '';
                exportBuildingData($supabase, $format, $building);
                break;
                
            case 'contact_list':
                exportContactList($supabase, $format);
                break;
                
            default:
                throw new Exception('Invalid export type');
        }
    } catch (Exception $e) {
        header('HTTP/1.1 500 Internal Server Error');
        echo 'Export failed: ' . htmlspecialchars($e->getMessage());
        exit();
    }
}

// Export functions
function exportStudents($supabase, $format) {
    $data = $supabase->select('students', '*', []);
    $columns = [
        'student_id' => 'Student ID',
        'full_name' => 'Full Name',
        'email' => 'Email',
        'phone' => 'Phone',
        'building_code' => 'Building Code',
        'room_number' => 'Room Number',
        'monthly_rent' => 'Monthly Rent',
        'security_deposit' => 'Security Deposit',
        'join_date' => 'Join Date',
        'status' => 'Status',
        'emergency_contact_name' => 'Emergency Contact',
        'emergency_contact_phone' => 'Emergency Phone'
    ];
    
    outputFile('all_students_' . date('Ymd'), $data, $columns, $format);
}

function exportActiveStudents($supabase, $format) {
    $allStudents = $supabase->select('students', '*', []);
    $data = array_filter($allStudents, function($s) { 
        return ($s['status'] ?? 'active') === 'active'; 
    });
    
    $columns = [
        'student_id' => 'Student ID',
        'full_name' => 'Full Name',
        'email' => 'Email',
        'phone' => 'Phone',
        'building_code' => 'Building Code',
        'room_number' => 'Room Number',
        'monthly_rent' => 'Monthly Rent'
    ];
    
    outputFile('active_students_' . date('Ymd'), $data, $columns, $format);
}

function exportPayments($supabase, $format) {
    $data = $supabase->select('payments', '*', []);
    $columns = [
        'payment_id' => 'Payment ID',
        'student_id' => 'Student ID',
        'building_code' => 'Building',
        'month_year' => 'Period',
        'amount_due' => 'Amount Due',
        'amount_paid' => 'Amount Paid',
        'late_fee' => 'Late Fee',
        'payment_date' => 'Payment Date',
        'payment_method' => 'Payment Method',
        'payment_status' => 'Status',
        'receipt_number' => 'Receipt Number',
        'notes' => 'Notes'
    ];
    
    outputFile('all_payments_' . date('Ymd'), $data, $columns, $format);
}

function exportPendingPayments($supabase, $format) {
    $allPayments = $supabase->select('payments', '*', []);
    $data = array_filter($allPayments, function($payment) {
        $due = floatval($payment['amount_due'] ?? 0);
        $paid = floatval($payment['amount_paid'] ?? 0);
        $lateFee = floatval($payment['late_fee'] ?? 0);
        $balance = ($due + $lateFee) - $paid;
        return $balance > 0;
    });
    
    // Add calculated balance
    $enhancedData = array_map(function($payment) {
        $due = floatval($payment['amount_due'] ?? 0);
        $paid = floatval($payment['amount_paid'] ?? 0);
        $lateFee = floatval($payment['late_fee'] ?? 0);
        $payment['pending_balance'] = $due + $lateFee - $paid;
        return $payment;
    }, $data);
    
    $columns = [
        'payment_id' => 'Payment ID',
        'student_id' => 'Student ID',
        'building_code' => 'Building',
        'month_year' => 'Period',
        'amount_due' => 'Amount Due',
        'amount_paid' => 'Amount Paid',
        'pending_balance' => 'Pending Balance',
        'late_fee' => 'Late Fee',
        'payment_date' => 'Payment Date',
        'payment_status' => 'Status'
    ];
    
    outputFile('pending_payments_' . date('Ymd'), $enhancedData, $columns, $format);
}

function exportOverduePayments($supabase, $format) {
    $allPayments = $supabase->select('payments', '*', []);
    $currentTime = time();
    
    $data = array_filter($allPayments, function($payment) use ($currentTime) {
        $due = floatval($payment['amount_due'] ?? 0);
        $paid = floatval($payment['amount_paid'] ?? 0);
        $lateFee = floatval($payment['late_fee'] ?? 0);
        $balance = ($due + $lateFee) - $paid;
        
        if ($balance <= 0) return false;
        
        $monthYear = $payment['month_year'] ?? '';
        if (!$monthYear) return false;
        
        $dueDate = strtotime($monthYear . '-01 +1 month -1 day');
        return $dueDate && $dueDate < $currentTime;
    });
    
    // Add calculated fields
    $enhancedData = array_map(function($payment) use ($currentTime) {
        $due = floatval($payment['amount_due'] ?? 0);
        $paid = floatval($payment['amount_paid'] ?? 0);
        $lateFee = floatval($payment['late_fee'] ?? 0);
        $payment['overdue_balance'] = $due + $lateFee - $paid;
        
        $dueDate = strtotime($payment['month_year'] . '-01 +1 month -1 day');
        $payment['days_overdue'] = floor(($currentTime - $dueDate) / (24 * 60 * 60));
        
        return $payment;
    }, $data);
    
    $columns = [
        'payment_id' => 'Payment ID',
        'student_id' => 'Student ID',
        'building_code' => 'Building',
        'month_year' => 'Period',
        'overdue_balance' => 'Overdue Amount',
        'days_overdue' => 'Days Overdue',
        'payment_date' => 'Payment Date',
        'payment_status' => 'Status'
    ];
    
    outputFile('overdue_payments_' . date('Ymd'), $enhancedData, $columns, $format);
}

function exportContactList($supabase, $format) {
    $students = $supabase->select('students', '*', []);
    $activeStudents = array_filter($students, function($s) { 
        return ($s['status'] ?? 'active') === 'active'; 
    });
    
    $columns = [
        'full_name' => 'Name',
        'student_id' => 'Student ID',
        'phone' => 'Phone',
        'email' => 'Email',
        'building_code' => 'Building',
        'room_number' => 'Room',
        'emergency_contact_name' => 'Emergency Contact',
        'emergency_contact_phone' => 'Emergency Phone'
    ];
    
    outputFile('student_contacts_' . date('Ymd'), $activeStudents, $columns, $format);
}

function exportMonthlyPayments($supabase, $format, $month) {
    $allPayments = $supabase->select('payments', '*', []);
    $data = array_filter($allPayments, function($payment) use ($month) {
        return ($payment['month_year'] ?? '') === $month;
    });

    // Add calculated balance for monthly report
    $enhancedData = array_map(function($payment) {
        $due = floatval($payment['amount_due'] ?? 0);
        $paid = floatval($payment['amount_paid'] ?? 0);
        $lateFee = floatval($payment['late_fee'] ?? 0);
        $payment['balance'] = ($due + $lateFee) - $paid;
        return $payment;
    }, $data);

    $columns = [
        'payment_id' => 'Payment ID',
        'student_id' => 'Student ID',
        'building_code' => 'Building',
        'month_year' => 'Period',
        'amount_due' => 'Amount Due',
        'amount_paid' => 'Amount Paid',
        'balance' => 'Balance',
        'late_fee' => 'Late Fee',
        'payment_date' => 'Payment Date',
        'payment_method' => 'Payment Method',
        'payment_status' => 'Status',
        'receipt_number' => 'Receipt Number',
        'notes' => 'Notes'
    ];

    outputFile('monthly_payments_' . str_replace('-', '_', $month) . '_' . date('Ymd'), $enhancedData, $columns, $format);
}

function exportBuildingData($supabase, $format, $building) {
    if (empty($building)) {
        throw new Exception('Building code is required for building export');
    }

    // Get buildings data using the new Buildings class for building name lookup
    try {
        $buildingNames = Buildings::getNames();
        $buildingName = $buildingNames[$building] ?? $building;
    } catch (Exception $e) {
        error_log('Export building data buildings error: ' . $e->getMessage());
        $buildingName = $building;
    }

    // Get students from the building
    $allStudents = $supabase->select('students', '*', []);
    $buildingStudents = array_filter($allStudents, function($student) use ($building) {
        return ($student['building_code'] ?? '') === $building;
    });

    // Get payments from the building
    $allPayments = $supabase->select('payments', '*', []);
    $buildingPayments = array_filter($allPayments, function($payment) use ($building) {
        return ($payment['building_code'] ?? '') === $building;
    });

    // Combine student and payment data
    $combinedData = [];
    
    // Add students data
    foreach ($buildingStudents as $student) {
        $combinedData[] = [
            'type' => 'Student',
            'id' => $student['student_id'],
            'name' => $student['full_name'] ?? '',
            'building_code' => $student['building_code'] ?? '',
            'room_number' => $student['room_number'] ?? '',
            'phone' => $student['phone'] ?? '',
            'email' => $student['email'] ?? '',
            'status' => $student['status'] ?? '',
            'monthly_rent' => $student['monthly_rent'] ?? '',
            'join_date' => $student['join_date'] ?? ''
        ];
    }

    // Add payment summary
    foreach ($buildingPayments as $payment) {
        $due = floatval($payment['amount_due'] ?? 0);
        $paid = floatval($payment['amount_paid'] ?? 0);
        $lateFee = floatval($payment['late_fee'] ?? 0);
        
        $combinedData[] = [
            'type' => 'Payment',
            'id' => $payment['payment_id'],
            'name' => $payment['student_id'],
            'building_code' => $payment['building_code'],
            'room_number' => '',
            'phone' => '',
            'email' => '',
            'status' => $payment['payment_status'] ?? '',
            'monthly_rent' => $payment['amount_due'] ?? '',
            'join_date' => $payment['payment_date'] ?? '',
            'amount_paid' => $payment['amount_paid'] ?? '',
            'balance' => ($due + $lateFee) - $paid,
            'payment_method' => $payment['payment_method'] ?? '',
            'month_year' => $payment['month_year'] ?? ''
        ];
    }

    $columns = [
        'type' => 'Record Type',
        'id' => 'ID',
        'name' => 'Name/Student ID',
        'building_code' => 'Building',
        'room_number' => 'Room',
        'phone' => 'Phone',
        'email' => 'Email',
        'status' => 'Status',
        'monthly_rent' => 'Rent/Amount Due',
        'join_date' => 'Join/Payment Date',
        'amount_paid' => 'Amount Paid',
        'balance' => 'Balance',
        'payment_method' => 'Payment Method',
        'month_year' => 'Period'
    ];

    outputFile('building_' . $building . '_data_' . date('Ymd'), $combinedData, $columns, $format);
}

function outputFile($filename, $data, $columns, $format) {
    if ($format === 'excel') {
        outputExcel($filename, $data, $columns);
    } else {
        outputCSV($filename, $data, $columns);
    }
}

function outputCSV($filename, $data, $columns) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    
    $output = fopen('php://output', 'w');
    
    // UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Write headers
    fputcsv($output, array_values($columns));
    
    // Write data
    foreach ($data as $row) {
        $line = [];
        foreach (array_keys($columns) as $key) {
            $value = $row[$key] ?? '';
            // Format monetary values
            if (in_array($key, ['monthly_rent', 'security_deposit', 'amount_due', 'amount_paid', 'late_fee', 'pending_balance', 'overdue_balance'])) {
                $value = is_numeric($value) ? number_format(floatval($value), 2) : $value;
            }
            $line[] = $value;
        }
        fputcsv($output, $line);
    }
    
    fclose($output);
    exit();
}

function outputExcel($filename, $data, $columns) {
    // For Excel, we'll use HTML table format that Excel can read
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    
    echo chr(0xEF).chr(0xBB).chr(0xBF); // UTF-8 BOM
    echo '<table border="1">';
    
    // Headers
    echo '<tr>';
    foreach ($columns as $header) {
        echo '<th>' . htmlspecialchars($header) . '</th>';
    }
    echo '</tr>';
    
    // Data
    foreach ($data as $row) {
        echo '<tr>';
        foreach (array_keys($columns) as $key) {
            $value = $row[$key] ?? '';
            // Format monetary values
            if (in_array($key, ['monthly_rent', 'security_deposit', 'amount_due', 'amount_paid', 'late_fee', 'pending_balance', 'overdue_balance'])) {
                $value = is_numeric($value) ? number_format(floatval($value), 2) : $value;
            }
            echo '<td>' . htmlspecialchars($value) . '</td>';
        }
        echo '</tr>';
    }
    
    echo '</table>';
    exit();
}
?>

<?php include '../../includes/header.php'; ?>

<!-- Data Export Interface -->
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
                <h1 class="text-2xl font-bold text-pg-text-primary">Data Export Center</h1>
                <p class="text-pg-text-secondary mt-1">
                    Export student and payment data in CSV or Excel format
                </p>
            </div>
        </div>
    </div>

    <!-- Error/Success Messages -->
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

    <!-- Export Statistics -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="card text-center">
            <div class="text-3xl font-bold text-pg-accent mb-2">
                <?php echo number_format($totalStudents); ?>
            </div>
            <h3 class="text-lg font-semibold text-pg-text-primary mb-1">Total Students</h3>
            <p class="text-sm text-pg-text-secondary">
                <?php echo number_format($activeStudents); ?> active
            </p>
        </div>

        <div class="card text-center">
            <div class="text-3xl font-bold text-pg-accent mb-2">
                <?php echo number_format($totalPayments); ?>
            </div>
            <h3 class="text-lg font-semibold text-pg-text-primary mb-1">Total Payments</h3>
            <p class="text-sm text-pg-text-secondary">All records</p>
        </div>

        <div class="card text-center">
            <div class="text-3xl font-bold text-yellow-400 mb-2">
                <?php echo number_format($pendingCount); ?>
            </div>
            <h3 class="text-lg font-semibold text-pg-text-primary mb-1">Pending Payments</h3>
            <p class="text-sm text-pg-text-secondary">Outstanding balances</p>
        </div>

        <div class="card text-center">
            <div class="text-3xl font-bold text-red-400 mb-2">
                <?php echo number_format($overdueCount); ?>
            </div>
            <h3 class="text-lg font-semibold text-pg-text-primary mb-1">Overdue Payments</h3>
            <p class="text-sm text-pg-text-secondary">Past due date</p>
        </div>
    </div>

    <!-- Export Options -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Student Data Exports -->
        <div class="card">
            <h3 class="text-lg font-semibold text-pg-text-primary mb-4 pb-2 border-b border-pg-border flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                </svg>
                Student Data Exports
            </h3>
            
            <div class="space-y-3">
                <!-- All Students -->
                <div class="flex items-center justify-between p-4 bg-pg-primary bg-opacity-50 rounded-lg">
                    <div>
                        <div class="font-medium text-pg-text-primary">All Students</div>
                        <div class="text-sm text-pg-text-secondary">Complete student database with all details</div>
                    </div>
                    <div class="flex space-x-2">
                        <a href="?action=download&type=students&format=csv" class="btn-secondary text-xs py-1 px-2">
                            CSV
                        </a>
                        <a href="?action=download&type=students&format=excel" class="btn-secondary text-xs py-1 px-2">
                            Excel
                        </a>
                    </div>
                </div>

                <!-- Active Students -->
                <div class="flex items-center justify-between p-4 bg-pg-primary bg-opacity-50 rounded-lg">
                    <div>
                        <div class="font-medium text-pg-text-primary">Active Students Only</div>
                        <div class="text-sm text-pg-text-secondary">Currently enrolled students</div>
                    </div>
                    <div class="flex space-x-2">
                        <a href="?action=download&type=active_students&format=csv" class="btn-secondary text-xs py-1 px-2">
                            CSV
                        </a>
                        <a href="?action=download&type=active_students&format=excel" class="btn-secondary text-xs py-1 px-2">
                            Excel
                        </a>
                    </div>
                </div>

                <!-- Contact List -->
                <div class="flex items-center justify-between p-4 bg-pg-primary bg-opacity-50 rounded-lg">
                    <div>
                        <div class="font-medium text-pg-text-primary">Contact List</div>
                        <div class="text-sm text-pg-text-secondary">Names, phones, emails for communication</div>
                    </div>
                    <div class="flex space-x-2">
                        <a href="?action=download&type=contact_list&format=csv" class="btn-secondary text-xs py-1 px-2">
                            CSV
                        </a>
                        <a href="?action=download&type=contact_list&format=excel" class="btn-secondary text-xs py-1 px-2">
                            Excel
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Payment Data Exports -->
        <div class="card">
            <h3 class="text-lg font-semibold text-pg-text-primary mb-4 pb-2 border-b border-pg-border flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
                Payment Data Exports
            </h3>
            
            <div class="space-y-3">
                <!-- All Payments -->
                <div class="flex items-center justify-between p-4 bg-pg-primary bg-opacity-50 rounded-lg">
                    <div>
                        <div class="font-medium text-pg-text-primary">All Payments</div>
                        <div class="text-sm text-pg-text-secondary">Complete payment transaction history</div>
                    </div>
                    <div class="flex space-x-2">
                        <a href="?action=download&type=payments&format=csv" class="btn-secondary text-xs py-1 px-2">
                            CSV
                        </a>
                        <a href="?action=download&type=payments&format=excel" class="btn-secondary text-xs py-1 px-2">
                            Excel
                        </a>
                    </div>
                </div>

                <!-- Pending Payments -->
                <div class="flex items-center justify-between p-4 bg-yellow-500 bg-opacity-10 border border-yellow-500 border-opacity-30 rounded-lg">
                    <div>
                        <div class="font-medium text-pg-text-primary">Pending Payments</div>
                        <div class="text-sm text-pg-text-secondary">Outstanding balances for follow-up</div>
                    </div>
                    <div class="flex space-x-2">
                        <a href="?action=download&type=pending&format=csv" class="btn-secondary text-xs py-1 px-2">
                            CSV
                        </a>
                        <a href="?action=download&type=pending&format=excel" class="btn-secondary text-xs py-1 px-2">
                            Excel
                        </a>
                    </div>
                </div>

                <!-- Overdue Payments -->
                <div class="flex items-center justify-between p-4 bg-red-500 bg-opacity-10 border border-red-500 border-opacity-30 rounded-lg">
                    <div>
                        <div class="font-medium text-pg-text-primary">Overdue Payments</div>
                        <div class="text-sm text-pg-text-secondary">Critical collection cases</div>
                    </div>
                    <div class="flex space-x-2">
                        <a href="?action=download&type=overdue&format=csv" class="btn-secondary text-xs py-1 px-2">
                            CSV
                        </a>
                        <a href="?action=download&type=overdue&format=excel" class="btn-secondary text-xs py-1 px-2">
                            Excel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Export Actions -->
    <div class="card">
        <h3 class="text-lg font-semibold text-pg-text-primary mb-4 pb-2 border-b border-pg-border">
            Quick Export Actions
        </h3>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
            <a href="?action=download&type=active_students&format=csv" 
               class="p-4 bg-green-500 bg-opacity-10 border border-green-500 border-opacity-30 rounded-lg hover:bg-green-500 hover:bg-opacity-20 transition-colors duration-200 text-center">
                <svg class="w-8 h-8 text-green-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                </svg>
                <div class="font-medium text-green-400">Active Students</div>
                <div class="text-sm text-pg-text-secondary">CSV Export</div>
            </a>
            
            <a href="?action=download&type=contact_list&format=excel" 
               class="p-4 bg-blue-500 bg-opacity-10 border border-blue-500 border-opacity-30 rounded-lg hover:bg-blue-500 hover:bg-opacity-20 transition-colors duration-200 text-center">
                <svg class="w-8 h-8 text-blue-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                </svg>
                <div class="font-medium text-blue-400">Contact List</div>
                <div class="text-sm text-pg-text-secondary">Excel Export</div>
            </a>
            
            <a href="?action=download&type=pending&format=csv" 
               class="p-4 bg-yellow-500 bg-opacity-10 border border-yellow-500 border-opacity-30 rounded-lg hover:bg-yellow-500 hover:bg-opacity-20 transition-colors duration-200 text-center">
                <svg class="w-8 h-8 text-yellow-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <div class="font-medium text-yellow-400">Pending Payments</div>
                <div class="text-sm text-pg-text-secondary">CSV Export</div>
            </a>
            
            <a href="?action=download&type=overdue&format=excel" 
               class="p-4 bg-red-500 bg-opacity-10 border border-red-500 border-opacity-30 rounded-lg hover:bg-red-500 hover:bg-opacity-20 transition-colors duration-200 text-center">
                <svg class="w-8 h-8 text-red-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                </svg>
                <div class="font-medium text-red-400">Overdue Payments</div>
                <div class="text-sm text-pg-text-secondary">Excel Export</div>
            </a>
        </div>
    </div>

    <!-- Export Information -->
    <div class="card">
        <h3 class="text-lg font-semibold text-pg-text-primary mb-4 pb-2 border-b border-pg-border">
            Export Information
        </h3>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <h4 class="font-semibold text-pg-text-primary mb-2">File Formats</h4>
                <ul class="space-y-2 text-sm text-pg-text-secondary">
                    <li class="flex items-center">
                        <svg class="w-4 h-4 text-pg-accent mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <strong>CSV:</strong> Compatible with Excel, Google Sheets, and database imports
                    </li>
                    <li class="flex items-center">
                        <svg class="w-4 h-4 text-pg-accent mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <strong>Excel:</strong> Native Microsoft Excel format with formatting
                    </li>
                </ul>
            </div>
            
            <div>
                <h4 class="font-semibold text-pg-text-primary mb-2">Data Included</h4>
                <ul class="space-y-2 text-sm text-pg-text-secondary">
                    <li class="flex items-center">
                        <svg class="w-4 h-4 text-pg-accent mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        UTF-8 encoding for international characters
                    </li>
                    <li class="flex items-center">
                        <svg class="w-4 h-4 text-pg-accent mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Proper formatting for currency and dates
                    </li>
                    <li class="flex items-center">
                        <svg class="w-4 h-4 text-pg-accent mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Header rows with descriptive column names
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
