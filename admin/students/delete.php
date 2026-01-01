<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

// Set page title for header
$title = 'Delete Student';

// Authentication check
require_once '../../includes/auth_check.php';

// Get student ID from URL
$studentId = $_GET['id'] ?? '';

if (empty($studentId)) {
    flash('error', 'Student ID is required.');
    header('Location: index.php');
    exit();
}

// Initialize variables
$student = null;
$error = '';
$relatedRecords = [];

// Get buildings data using the new Buildings class
try {
    $buildingNames = Buildings::getNames();
} catch (Exception $e) {
    error_log('Student delete buildings error: ' . $e->getMessage());
    $buildingNames = [];
}

// Load student data and check for related records
try {
    $supabase = supabase();

    $studentData = $supabase->select('students', '*', ['student_id' => $studentId]);

    if (empty($studentData)) {
        flash('error', 'Student not found.');
        header('Location: index.php');
        exit();
    }

    $student = $studentData[0];

   
    // Check for payments using correct column names
    $payments = $supabase->select('payments', 'id, payment_id, amount_paid, amount_due, payment_date, payment_type, month_year, payment_status', ['student_id' => $studentId]);
    if (!empty($payments)) {
        $relatedRecords['payments'] = $payments;
    }



} catch (Exception $e) {
    $error = 'Error loading student: ' . $e->getMessage();
    error_log('Student delete load error: ' . $e->getMessage());
}

// Handle deletion confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $confirmDelete = $_POST['confirm_delete'] ?? '';

    if ($confirmDelete === 'yes') {
        try {
            
            $supabase = supabase();
            
            // Begin transaction-like operations
            $deletionSuccess = true;
            $deletedRecords = [];

            // Delete related records first
            if (!empty($relatedRecords['payments'])) {
                $paymentResult = $supabase->delete('payments', ['student_id' => $studentId]);
                if ($paymentResult) {
                    $deletedRecords[] = count($relatedRecords['payments']) . ' payment record(s)';
                } else {
                    throw new Exception('Failed to delete payment records');
                }
            }

            // Add deletion for other related tables as needed
            // if (!empty($relatedRecords['attendance'])) {
            //     $attendanceResult = $supabase->delete('attendance', ['student_id' => $studentId]);
            //     if ($attendanceResult) {
            //         $deletedRecords[] = count($relatedRecords['attendance']) . ' attendance record(s)';
            //     } else {
            //         throw new Exception('Failed to delete attendance records');
            //     }
            // }

            // Finally, delete the student
            $result = $supabase->delete('students', ['student_id' => $studentId]);

            if ($result) {
                $message = 'Student "' . htmlspecialchars($student['full_name']) . '" has been deleted successfully.';
                if (!empty($deletedRecords)) {
                    $message .= ' Also deleted: ' . implode(', ', $deletedRecords) . '.';
                }
                flash('success', $message);
                header('Location: index.php');
                exit();
            } else {
                $error = 'Failed to delete student. Please try again.';
            }
        } catch (Exception $e) {
            $error = 'Error deleting student: ' . $e->getMessage();
            error_log('Student deletion error: ' . $e->getMessage());
        }
    } else {
        // User didn't confirm, redirect back
        header('Location: view.php?id=' . urlencode($studentId));
        exit();
    }
}
?>

<?php include '../../includes/header.php'; ?>

<!-- Delete Student Confirmation -->
<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex items-center justify-between">
        <div class="flex items-center space-x-4">
            <!-- Back Button -->
            <a href="view.php?id=<?php echo urlencode($studentId); ?>" class="text-pg-text-secondary hover:text-pg-text-primary transition-colors duration-200">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
            </a>

            <div>
                <h1 class="text-2xl font-bold text-status-danger">Delete Student</h1>
                <p class="text-pg-text-secondary mt-1">Permanently remove student from the system</p>
            </div>
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

    <?php if ($student): ?>
        
        <?php if (!empty($relatedRecords)): ?>
            <div class="bg-yellow-500 bg-opacity-10 border border-yellow-500 text-yellow-600 px-4 py-3 rounded-lg">
                <div class="flex items-start">
                    <svg class="w-5 h-5 mr-2 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                    </svg>
                    <div>
                        <h4 class="font-semibold mb-2">Related Records Found</h4>
                        <p class="text-sm mb-2">This student has related records that will also be deleted:</p>
                        <ul class="text-sm space-y-1">
                            <?php if (!empty($relatedRecords['payments'])): ?>
                                <?php 
                                $totalPaid = array_sum(array_column($relatedRecords['payments'], 'amount_paid'));
                                $totalDue = array_sum(array_column($relatedRecords['payments'], 'amount_due'));
                                ?>
                                <li>• <strong><?php echo count($relatedRecords['payments']); ?> payment record(s)</strong></li>
                                <li class="ml-4">- Total Paid: ₹<?php echo number_format($totalPaid, 2); ?></li>
                                <li class="ml-4">- Total Due: ₹<?php echo number_format($totalDue, 2); ?></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        
        <?php if (!empty($relatedRecords['payments']) && count($relatedRecords['payments']) <= 10): ?>
            <div class="card">
                <h4 class="font-semibold text-pg-text-primary mb-3">Payment Records to be Deleted</h4>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="border-b border-pg-border">
                                <th class="text-left py-2">Payment ID</th>
                                <th class="text-left py-2">Month/Year</th>
                                <th class="text-right py-2">Amount Paid</th>
                                <th class="text-right py-2">Amount Due</th>
                                <th class="text-center py-2">Status</th>
                                <th class="text-left py-2">Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($relatedRecords['payments'] as $payment): ?>
                                <tr class="border-b border-pg-border">
                                    <td class="py-2 font-mono text-xs"><?php echo htmlspecialchars($payment['payment_id']); ?></td>
                                    <td class="py-2"><?php echo htmlspecialchars($payment['month_year']); ?></td>
                                    <td class="py-2 text-right">₹<?php echo number_format($payment['amount_paid'], 2); ?></td>
                                    <td class="py-2 text-right">₹<?php echo number_format($payment['amount_due'], 2); ?></td>
                                    <td class="py-2 text-center">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium 
                                            <?php echo $payment['payment_status'] === 'paid' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                                            <?php echo ucfirst($payment['payment_status']); ?>
                                        </span>
                                    </td>
                                    <td class="py-2 capitalize"><?php echo htmlspecialchars($payment['payment_type']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php elseif (!empty($relatedRecords['payments']) && count($relatedRecords['payments']) > 10): ?>
            <div class="card">
                <h4 class="font-semibold text-pg-text-primary mb-3">Payment Records Summary</h4>
                <p class="text-pg-text-secondary">Too many payment records to display (<?php echo count($relatedRecords['payments']); ?> total). All will be permanently deleted.</p>
            </div>
        <?php endif; ?>

        <!-- Confirmation Card -->
        <div class="card border-status-danger">
            <div class="text-center mb-6">
                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-status-danger bg-opacity-10 mb-4">
                    <svg class="h-8 w-8 text-status-danger" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-bold text-status-danger mb-2">Confirm Student Deletion</h3>
                <p class="text-pg-text-secondary">This action cannot be undone. All associated data will be permanently removed.</p>
            </div>

            <!-- Student Information -->
            <div class="bg-pg-primary bg-opacity-50 rounded-lg p-4 mb-6">
                <div class="flex items-center space-x-4">
                    <!-- Profile Photo -->
                    <?php if (!empty($student['profile_photo_url'])): ?>
                        <img class="w-16 h-16 rounded-full object-cover"
                            src="<?php echo htmlspecialchars($student['profile_photo_url']); ?>"
                            alt="Profile Photo">
                    <?php else: ?>
                        <div class="w-16 h-16 bg-pg-accent rounded-full flex items-center justify-center">
                            <span class="text-white text-xl font-bold">
                                <?php echo strtoupper(substr($student['full_name'], 0, 1)); ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <!-- Student Details -->
                    <div class="flex-1">
                        <h4 class="text-lg font-semibold text-pg-text-primary">
                            <?php echo htmlspecialchars($student['full_name']); ?>
                        </h4>
                        <p class="text-pg-text-secondary font-mono">
                            <?php echo htmlspecialchars($student['student_id']); ?>
                        </p>
                        <div class="flex items-center space-x-4 mt-2 text-sm text-pg-text-secondary">
                            <span>
                                <svg class="w-4 h-4 inline mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                                <?php echo htmlspecialchars($buildingNames[$student['building_code']] ?? $student['building_code']); ?>
                            </span>
                            <?php if (!empty($student['room_number'])): ?>
                                <span>Room <?php echo htmlspecialchars($student['room_number']); ?></span>
                            <?php endif; ?>
                            <?php if (!empty($student['college_name'])): ?>
                                <span><?php echo htmlspecialchars($student['college_name']); ?></span>
                            <?php elseif (!empty($student['course'])): ?>
                                <span><?php echo htmlspecialchars($student['course']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Warning Information -->
            <div class="bg-status-danger bg-opacity-5 border border-status-danger rounded-lg p-4 mb-6">
                <h5 class="font-semibold text-status-danger mb-2 flex items-center">
                    <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                    </svg>
                    What will be deleted:
                </h5>
                <ul class="text-sm text-pg-text-secondary space-y-1 ml-6">
                    <li>• Student profile and personal information</li>
                    <li>• Academic records and accommodation details</li>
                    <li>• Contact information and parent details</li>
                    <?php if (!empty($relatedRecords['payments'])): ?>
                        <li>• <strong class="text-status-danger"><?php echo count($relatedRecords['payments']); ?> payment record(s)</strong> with all transaction history</li>
                    <?php endif; ?>
                    <li>• Profile photo and uploaded documents</li>
                    <li>• Additional notes and system records</li>
                </ul>
            </div>

            <!-- Confirmation Form -->
            <form method="POST" class="space-y-6">
                <!-- Confirmation Checkbox -->
                <div class="flex items-start space-x-3">
                    <input type="checkbox"
                        id="confirm_deletion"
                        name="confirm_deletion"
                        class="mt-1 h-4 w-4 text-status-danger focus:ring-status-danger border-gray-300 rounded"
                        required>
                    <label for="confirm_deletion" class="text-sm text-pg-text-primary">
                        I understand that this action is permanent and cannot be undone. I want to delete
                        <strong><?php echo htmlspecialchars($student['full_name']); ?></strong>
                        <?php if (!empty($relatedRecords)): ?>
                            and all related records
                        <?php endif; ?>
                        from the system.
                    </label>
                </div>

                <!-- Type Confirmation -->
                <div>
                    <label for="type_delete" class="block text-sm font-medium text-pg-text-primary mb-2">
                        Type <strong class="text-status-danger">DELETE</strong> to confirm:
                    </label>
                    <input type="text"
                        id="type_delete"
                        name="type_delete"
                        class="input-field w-full md:w-1/2"
                        placeholder="Type DELETE here"
                        required>
                </div>

                <!-- Hidden confirmation field -->
                <input type="hidden" name="confirm_delete" value="yes">

                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row gap-4 pt-6 border-t border-pg-border">
                    <a href="view.php?id=<?php echo urlencode($studentId); ?>" class="btn-secondary flex-1 sm:flex-none">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        <span>Cancel</span>
                    </a>

                    <button type="submit"
                        id="delete_button"
                        class="btn-primary bg-status-danger hover:bg-red-700 flex-1 sm:flex-none"
                        disabled>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                        <span>Delete Student Permanently</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- JavaScript for Enhanced UX -->
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const confirmCheckbox = document.getElementById('confirm_deletion');
                const typeDeleteInput = document.getElementById('type_delete');
                const deleteButton = document.getElementById('delete_button');

                function updateDeleteButton() {
                    const isChecked = confirmCheckbox.checked;
                    const isTypedCorrectly = typeDeleteInput.value.toUpperCase() === 'DELETE';

                    if (isChecked && isTypedCorrectly) {
                        deleteButton.disabled = false;
                        deleteButton.classList.remove('opacity-50', 'cursor-not-allowed');
                    } else {
                        deleteButton.disabled = true;
                        deleteButton.classList.add('opacity-50', 'cursor-not-allowed');
                    }
                }

                confirmCheckbox.addEventListener('change', updateDeleteButton);
                typeDeleteInput.addEventListener('input', updateDeleteButton);

                // Initial state
                updateDeleteButton();

                // Final confirmation before submission
                deleteButton.closest('form').addEventListener('submit', function(e) {
                    if (!confirm('Are you absolutely sure you want to delete this student and all related records? This action cannot be undone.')) {
                        e.preventDefault();
                    }
                });
            });
        </script>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>
