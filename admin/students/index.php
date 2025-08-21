<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

$title = 'Students Management';
require_once '../../includes/auth_check.php';

// Get filter parameters
$search = trim($_GET['search'] ?? '');
$selectedBuilding = $_GET['building'] ?? 'all';
$status = $_GET['status'] ?? 'active';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Initialize variables
$students = [];
$totalStudents = 0;
$totalPages = 0;
$error = null;

// Get buildings data using the new Buildings class
try {
    $buildingCodes = Buildings::getCodes();
    $buildingNames = Buildings::getNames();
} catch (Exception $e) {
    error_log('Students index buildings error: ' . $e->getMessage());
    $buildingCodes = [];
    $buildingNames = [];
}

try {
    $supabase = supabase();

    // Base filters for API query
    $filters = [];
    if ($status !== 'all') {
        $filters['status'] = $status;
    }
    if ($selectedBuilding !== 'all') {
        $filters['building_code'] = $selectedBuilding;
    }

    // Fetch all students matching filters
    $allStudents = $supabase->select('students', '*', $filters);

    // ✅ UPDATED: Apply search filter (removed department reference)
    if ($search !== '') {
        $searchLower = strtolower($search);
        $filteredStudents = array_filter($allStudents, function ($student) use ($searchLower) {
            return strpos(strtolower($student['full_name']), $searchLower) !== false ||
                strpos(strtolower($student['student_id']), $searchLower) !== false ||
                strpos(strtolower($student['phone'] ?? ''), $searchLower) !== false ||
                strpos(strtolower($student['email'] ?? ''), $searchLower) !== false ||
                strpos(strtolower($student['college_name'] ?? ''), $searchLower) !== false ||
                strpos(strtolower($student['course'] ?? ''), $searchLower) !== false;
        });
    } else {
        $filteredStudents = $allStudents;
    }

    // Sort by created date (newest first)
    usort($filteredStudents, function ($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });

    $totalStudents = count($filteredStudents);
    $students = array_slice($filteredStudents, $offset, $perPage);
    $totalPages = ceil($totalStudents / $perPage);
} catch (Exception $e) {
    $error = 'Error loading students: ' . $e->getMessage();
    error_log('Students listing error: ' . $e->getMessage());
}

// Helper function to build query string for pagination
function buildQueryString($params, $page = null)
{
    if ($page !== null) {
        $params['page'] = $page;
    }
    return http_build_query(array_filter($params, function ($value) {
        return $value !== '' && $value !== 'all';
    }));
}

$queryParams = [
    'search' => $search,
    'building' => $selectedBuilding,
    'status' => $status
];
?>

<?php include '../../includes/header.php'; ?>

<!-- Main Content -->
<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-pg-text-primary">Students Management</h1>
            <p class="text-pg-text-secondary mt-1">
                <?php if ($selectedBuilding === 'all'): ?>
                    Manage students across all buildings
                <?php else: ?>
                    Manage students in <?php echo htmlspecialchars($buildingNames[$selectedBuilding] ?? $selectedBuilding); ?>
                <?php endif; ?>
            </p>
        </div>

        <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3">
            <div class="text-sm text-pg-text-secondary">
                Total: <span class="font-semibold text-pg-text-primary"><?php echo $totalStudents; ?></span> students
            </div>
            <a href="add.php" class="btn-primary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                <span>Add Student</span>
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

    <!-- Filters Section -->
    <div class="card">
        <form method="GET" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                <!-- Search Input -->
                <div class="sm:col-span-2">
                    <label for="search" class="block text-sm font-medium text-pg-text-primary mb-2">Search</label>
                    <input type="text"
                        id="search"
                        name="search"
                        value="<?php echo htmlspecialchars($search); ?>"
                        placeholder="Search by name, ID, phone, email, college, course..."
                        class="input-field w-full">
                </div>

                <!-- Building Filter -->
                <div>
                    <label for="building" class="block text-sm font-medium text-pg-text-primary mb-2">Building</label>
                    <select id="building" name="building" class="select-field w-full">
                        <option value="all" <?php echo $selectedBuilding === 'all' ? 'selected' : ''; ?>>All Buildings</option>
                        <?php if (!empty($buildingNames)): ?>
                            <?php foreach ($buildingNames as $code => $name): ?>
                                <option value="<?php echo htmlspecialchars($code); ?>" <?php echo $selectedBuilding === $code ? 'selected' : ''; ?>>
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
                        <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Status</option>
                    </select>
                </div>
            </div>

            <!-- Filter Actions -->
            <div class="flex flex-col sm:flex-row gap-2 btn-group-mobile">
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

    <!-- ✅ UPDATED: Students Table (Removed Department Column) -->
    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full">
                <thead>
                    <tr class="border-b border-pg-border">
                        <th class="table-header">Student ID</th>
                        <th class="table-header">Name</th>
                        <th class="table-header hidden sm:table-cell">Phone</th>
                        <th class="table-header hidden md:table-cell">Email</th>
                        <th class="table-header hidden lg:table-cell">College</th>
                        <th class="table-header">Building</th>
                        <th class="table-header hidden sm:table-cell">Room</th>
                        <th class="table-header hidden md:table-cell">Status</th>
                        <th class="table-header text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr>
                            <!-- ✅ UPDATED: colspan from 9 to 9 (still 9 columns total) -->
                            <td colspan="9" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center">
                                    <svg class="w-12 h-12 text-pg-text-secondary mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                                    </svg>
                                    <p class="text-pg-text-secondary">No students found</p>
                                    <p class="text-sm text-pg-text-secondary mt-1">Try adjusting your search filters or add a new student</p>
                                    <a href="add.php" class="btn-primary mt-4">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                        </svg>
                                        Add First Student
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($students as $student): ?>
                            <tr class="border-b border-pg-border hover:bg-pg-hover transition-colors duration-200">
                                <td class="px-6 py-4 font-mono text-sm">
                                    <?php echo htmlspecialchars($student['student_id']); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="flex items-center">
                                        <?php if (!empty($student['profile_photo_url'])): ?>
                                            <img class="w-8 h-8 rounded-full mr-3" src="<?php echo htmlspecialchars($student['profile_photo_url']); ?>" alt="Profile">
                                        <?php else: ?>
                                            <div class="w-8 h-8 bg-pg-accent rounded-full flex items-center justify-center mr-3">
                                                <span class="text-white text-sm font-semibold">
                                                    <?php echo strtoupper(substr($student['full_name'], 0, 1)); ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                        <div>
                                            <div class="font-medium"><?php echo htmlspecialchars($student['full_name']); ?></div>
                                            <!-- Optional: Show course as subtitle -->
                                            <?php if (!empty($student['course'])): ?>
                                                <div class="text-xs text-pg-text-secondary"><?php echo htmlspecialchars($student['course']); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td class="hidden sm:table-cell px-6 py-4"><?php echo htmlspecialchars($student['phone'] ?? '-'); ?></td>
                                <td class="hidden md:table-cell px-6 py-4"><?php echo htmlspecialchars($student['email'] ?? '-'); ?></td>
                                <!-- ✅ UPDATED: Changed from department to college_name -->
                                <td class="hidden lg:table-cell px-6 py-4">
                                    <div class="max-w-32 truncate" title="<?php echo htmlspecialchars($student['college_name'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($student['college_name'] ?? '-'); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-pg-accent bg-opacity-20 text-pg-accent">
                                        <?php echo htmlspecialchars($buildingNames[$student['building_code']] ?? $student['building_code']); ?>
                                    </span>
                                </td>
                                <td class="hidden sm:table-cell px-6 py-4"><?php echo htmlspecialchars($student['room_number'] ?? '-'); ?></td>
                                <td class="hidden md:table-cell px-6 py-4">
                                    <?php if ($student['status'] === 'active'): ?>
                                        <span class="status-badge status-active">Active</span>
                                    <?php else: ?>
                                        <span class="status-badge status-inactive">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <div class="flex items-center justify-center space-x-1">
                                        <a href="view.php?id=<?php echo urlencode($student['student_id']); ?>"
                                            class="text-blue-400 hover:text-blue-300 transition-colors duration-200 p-1"
                                            title="View Student">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                            </svg>
                                        </a>
                                        <a href="edit.php?id=<?php echo urlencode($student['student_id']); ?>"
                                            class="text-yellow-400 hover:text-yellow-300 transition-colors duration-200 p-1"
                                            title="Edit Student">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                        </a>
                                        <a href="delete.php?id=<?php echo urlencode($student['student_id']); ?>"
                                            class="text-red-400 hover:text-red-300 transition-colors duration-200 p-1"
                                            title="Delete Student"
                                            onclick="return confirm('Are you sure you want to delete this student? Click OK to proceed to the confirmation page.');">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
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
                Showing <?php echo min(($page - 1) * $perPage + 1, $totalStudents); ?> to
                <?php echo min($page * $perPage, $totalStudents); ?> of
                <?php echo $totalStudents; ?> students
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
