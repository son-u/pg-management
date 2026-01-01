<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

// Set page title for header
$title = 'Student Profile';

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

// Get buildings data using the new Buildings class
try {
    $buildingNames = Buildings::getNames();
} catch (Exception $e) {
    error_log('Student view buildings error: ' . $e->getMessage());
    $buildingNames = [];
}

try {
    $supabase = supabase();

    // Fetch student details
    $studentData = $supabase->select('students', '*', ['student_id' => $studentId]);

    if (empty($studentData)) {
        flash('error', 'Student not found.');
        header('Location: index.php');
        exit();
    }

    $student = $studentData[0]; // Get first (and should be only) result

    // Update title with student name
    $title = $student['full_name'] . ' - Student Profile';
} catch (Exception $e) {
    $error = 'Error loading student: ' . $e->getMessage();
    error_log('Student view error: ' . $e->getMessage());
}

// Helper function to format date
function formatDate($date)
{
    if (empty($date)) return '-';
    return date('M d, Y', strtotime($date));
}

// Helper function to calculate age
function calculateAge($dateOfBirth)
{
    if (empty($dateOfBirth)) return '-';
    $today = new DateTime();
    $birthDate = new DateTime($dateOfBirth);
    return $birthDate->diff($today)->y . ' years';
}
?>

<?php include '../../includes/header.php'; ?>

<!-- Student Profile -->
<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div class="flex items-center space-x-4">
            <!-- Back Button -->
            <a href="index.php" class="text-pg-text-secondary hover:text-pg-text-primary transition-colors duration-200">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
            </a>

            <div>
                <h1 class="text-2xl font-bold text-pg-text-primary">Student Profile</h1>
                <p class="text-pg-text-secondary mt-1">View and manage student information</p>
            </div>
        </div>

        <div class="flex items-center space-x-3">
            <a href="edit.php?id=<?php echo urlencode($studentId); ?>" class="btn-primary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
                <span>Edit Student</span>
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

    <?php if ($student): ?>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Profile Card -->
            <div class="lg:col-span-1">
                <div class="card text-center">
                    <!-- Profile Photo -->
                    <div class="flex justify-center mb-4">
                        <?php if (!empty($student['profile_photo_url'])): ?>
                            <img class="w-32 h-32 rounded-full object-cover border-4 border-pg-accent"
                                src="<?php echo htmlspecialchars($student['profile_photo_url']); ?>"
                                alt="Profile Photo">
                        <?php else: ?>
                            <div class="w-32 h-32 bg-pg-accent rounded-full flex items-center justify-center border-4 border-pg-accent">
                                <span class="text-white text-4xl font-bold">
                                    <?php echo strtoupper(substr($student['full_name'], 0, 1)); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Basic Info -->
                    <h2 class="text-xl font-bold text-pg-text-primary mb-2">
                        <?php echo htmlspecialchars($student['full_name']); ?>
                    </h2>

                    <p class="text-pg-text-secondary mb-4">
                        <?php echo htmlspecialchars($student['student_id']); ?>
                    </p>

                    <!-- Status Badge -->
                    <div class="mb-4">
                        <?php if ($student['status'] === 'active'): ?>
                            <span class="status-badge status-active">Active</span>
                        <?php else: ?>
                            <span class="status-badge status-inactive">Inactive</span>
                        <?php endif; ?>
                    </div>

                    <!-- Quick Stats -->
                    <div class="grid grid-cols-2 gap-4 text-center">
                        <div class="bg-pg-primary bg-opacity-50 rounded-lg p-3">
                            <div class="text-lg font-bold text-pg-accent">
                                <?php echo htmlspecialchars($buildingNames[$student['building_code']] ?? $student['building_code']); ?>
                            </div>
                            <div class="text-xs text-pg-text-secondary">Building</div>
                        </div>
                        <div class="bg-pg-primary bg-opacity-50 rounded-lg p-3">
                            <div class="text-lg font-bold text-pg-accent">
                                <?php echo htmlspecialchars($student['room_number'] ?? 'N/A'); ?>
                            </div>
                            <div class="text-xs text-pg-text-secondary">Room</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Information -->
            <div class="lg:col-span-2 space-y-6">
                <!-- Personal Information -->
                <div class="card">
                    <h3 class="text-lg font-semibold text-pg-text-primary mb-4 pb-2 border-b border-pg-border flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        Personal Information
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-pg-text-secondary mb-1">Full Name</label>
                            <p class="text-pg-text-primary"><?php echo htmlspecialchars($student['full_name']); ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-pg-text-secondary mb-1">Student ID</label>
                            <p class="text-pg-text-primary font-mono"><?php echo htmlspecialchars($student['student_id']); ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-pg-text-secondary mb-1">Date of Birth</label>
                            <p class="text-pg-text-primary">
                                <?php echo formatDate($student['date_of_birth']); ?>
                                <?php if (!empty($student['date_of_birth'])): ?>
                                    <span class="text-sm text-pg-text-secondary ml-2">(<?php echo calculateAge($student['date_of_birth']); ?>)</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-pg-text-secondary mb-1">Status</label>
                            <p class="text-pg-text-primary"><?php echo ucfirst($student['status']); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Contact Information -->
                <div class="card">
                    <h3 class="text-lg font-semibold text-pg-text-primary mb-4 pb-2 border-b border-pg-border flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                        </svg>
                        Contact Information
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-pg-text-secondary mb-1">Phone</label>
                            <p class="text-pg-text-primary">
                                <?php if (!empty($student['phone'])): ?>
                                    <a href="tel:<?php echo htmlspecialchars($student['phone']); ?>"
                                        class="text-pg-accent hover:underline">
                                        <?php echo htmlspecialchars($student['phone']); ?>
                                    </a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-pg-text-secondary mb-1">Email</label>
                            <p class="text-pg-text-primary">
                                <?php if (!empty($student['email'])): ?>
                                    <a href="mailto:<?php echo htmlspecialchars($student['email']); ?>"
                                        class="text-pg-accent hover:underline">
                                        <?php echo htmlspecialchars($student['email']); ?>
                                    </a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-pg-text-secondary mb-1">Parent/Guardian Name</label>
                            <p class="text-pg-text-primary"><?php echo htmlspecialchars($student['parent_name'] ?? '-'); ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-pg-text-secondary mb-1">Parent Phone</label>
                            <p class="text-pg-text-primary">
                                <?php if (!empty($student['parent_phone'])): ?>
                                    <a href="tel:<?php echo htmlspecialchars($student['parent_phone']); ?>"
                                        class="text-pg-accent hover:underline">
                                        <?php echo htmlspecialchars($student['parent_phone']); ?>
                                    </a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>

                
                <div class="card">
                    <h3 class="text-lg font-semibold text-pg-text-primary mb-4 pb-2 border-b border-pg-border flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                        Academic Information
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-pg-text-secondary mb-1">College/Institution</label>
                            <p class="text-pg-text-primary"><?php echo htmlspecialchars($student['college_name'] ?? '-'); ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-pg-text-secondary mb-1">Course/Program</label>
                            <p class="text-pg-text-primary"><?php echo htmlspecialchars($student['course'] ?? '-'); ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-pg-text-secondary mb-1">Admission Date</label>
                            <p class="text-pg-text-primary"><?php echo formatDate($student['admission_date']); ?></p>
                        </div>
                    </div>
                </div>

              
                <div class="card">
                    <h3 class="text-lg font-semibold text-pg-text-primary mb-4 pb-2 border-b border-pg-border flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                        Accommodation Details
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-pg-text-secondary mb-1">Building</label>
                            <p class="text-pg-text-primary"><?php echo htmlspecialchars($buildingNames[$student['building_code']] ?? $student['building_code']); ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-pg-text-secondary mb-1">Room Number</label>
                            <p class="text-pg-text-primary"><?php echo htmlspecialchars($student['room_number'] ?? '-'); ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-pg-text-secondary mb-1">Monthly Rent</label>
                            <p class="text-pg-text-primary">
                                <?php if (!empty($student['monthly_rent'])): ?>
                                    ₹<?php echo number_format($student['monthly_rent'], 2); ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </p>
                        </div>
                    </div>
                </div>

                
                <div class="card">
                    <h3 class="text-lg font-semibold text-pg-text-primary mb-4 pb-2 border-b border-pg-border flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        Address & Additional Information
                    </h3>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-pg-text-secondary mb-1">Home Address</label>
                            <p class="text-pg-text-primary whitespace-pre-wrap">
                                <?php echo htmlspecialchars($student['address'] ?? '-'); ?>
                            </p>
                        </div>

                        <?php if (!empty($student['note'])): ?>
                            <div>
                                <label class="block text-sm font-medium text-pg-text-secondary mb-1">Additional Notes</label>
                                <p class="text-pg-text-primary whitespace-pre-wrap">
                                    <?php echo htmlspecialchars($student['note']); ?>
                                </p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- System Information -->
                <div class="card">
                    <h3 class="text-lg font-semibold text-pg-text-primary mb-4 pb-2 border-b border-pg-border flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        System Information
                    </h3>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-pg-text-secondary mb-1">Created</label>
                            <p class="text-pg-text-primary"><?php echo formatDate($student['created_at']); ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-pg-text-secondary mb-1">Last Updated</label>
                            <p class="text-pg-text-primary"><?php echo formatDate($student['updated_at']); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="card">
                    <div class="flex flex-col sm:flex-row gap-3">
                        <a href="edit.php?id=<?php echo urlencode($studentId); ?>" class="btn-primary">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                            <span>Edit Student</span>
                        </a>

                        <a href="../payments/add.php?student_id=<?php echo urlencode($studentId); ?>" class="btn-secondary">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                            </svg>
                            <span>Record Payment</span>
                        </a>

                        <a href="delete.php?id=<?php echo urlencode($studentId); ?>"
                            class="btn-secondary bg-status-danger hover:bg-red-700"
                            onclick="return confirm('Are you sure you want to delete this student? Click OK to proceed to the confirmation page.');">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                            <span>Delete Student</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>
