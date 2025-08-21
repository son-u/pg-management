<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

$title = 'Building Management';
require_once '../../includes/auth_check.php';

$error = '';
$success = '';

try {
    $supabase = supabase();

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'update') {
            $building_id = intval($_POST['building_id'] ?? 0);
            if (!$building_id) throw new Exception('Building ID is required.');

            $updateData = [
                'building_name' => trim($_POST['building_name'] ?? ''),
                'building_address' => trim($_POST['building_address'] ?? ''),
                'contact_person' => trim($_POST['contact_person'] ?? ''),
                'contact_phone' => trim($_POST['contact_phone'] ?? ''),
                'status' => trim($_POST['status'] ?? 'active'),
                'updated_at' => date('c')
            ];

            $supabase->update('buildings', $updateData, ['id' => $building_id]);

            // Redirect to prevent form resubmission
            header('Location: buildings.php?success=' . urlencode('Building updated successfully!'));
            exit();
        }
    }

    // Check for success message from redirect
    if (isset($_GET['success'])) {
        $success = $_GET['success'];
    }

    // Fetch buildings
    $buildingsRaw = $supabase->select('buildings', '*', []);
    $buildings = [];
    $seenCodes = [];

    if (is_array($buildingsRaw)) {
        foreach ($buildingsRaw as $building) {
            $code = $building['building_code'] ?? '';

            // Skip duplicates
            if (in_array($code, $seenCodes)) {
                continue;
            }

            $seenCodes[] = $code;
            $buildings[] = $building;
        }

        // Sort buildings by building_code
        usort($buildings, function ($a, $b) {
            return strcmp($a['building_code'] ?? '', $b['building_code'] ?? '');
        });
    }

    // Fetch rooms for statistics
    $allRooms = $supabase->select('rooms', '*', []);
    $allRooms = is_array($allRooms) ? $allRooms : [];

    // Calculate statistics for each building
    foreach ($buildings as &$building) {
        $buildingCode = $building['building_code'] ?? '';

        $buildingRooms = array_filter($allRooms, function ($room) use ($buildingCode) {
            return ($room['building_code'] ?? '') === $buildingCode;
        });

        $building['actual_total_rooms'] = count($buildingRooms);
        $building['actual_occupied_rooms'] = count(array_filter($buildingRooms, function ($room) {
            return ($room['status'] ?? '') === 'occupied';
        }));
        $building['actual_total_capacity'] = array_sum(array_map(function ($room) {
            return intval($room['capacity'] ?? 0);
        }, $buildingRooms));
        $building['actual_current_occupancy'] = array_sum(array_map(function ($room) {
            return intval($room['current_occupancy'] ?? 0);
        }, $buildingRooms));
    }
    unset($building);

} catch (Exception $e) {
    $error = 'Error: ' . $e->getMessage();
    error_log('Buildings page error: ' . $e->getMessage());
    $buildings = [];
}
?>

<?php include '../../includes/header.php'; ?>

<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex items-center justify-between">
        <div class="flex items-center space-x-4">
            <a href="../dashboard.php" class="text-pg-text-secondary hover:text-pg-text-primary transition-colors duration-200">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
            </a>

            <div>
                <h1 class="text-2xl font-bold text-pg-text-primary">Building Management</h1>
                <p class="text-pg-text-secondary mt-1">Configure building information and settings</p>
            </div>
        </div>
    </div>

    <!-- Messages -->
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

    <?php if ($success): ?>
        <div class="bg-status-success bg-opacity-10 border border-status-success text-status-success px-4 py-3 rounded-lg">
            <div class="flex items-center">
                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
                <?php echo htmlspecialchars($success); ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Buildings List -->
    <?php if (empty($buildings)): ?>
        <div class="card text-center py-8">
            <svg class="w-12 h-12 text-pg-text-secondary mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
            </svg>
            <h3 class="text-lg font-semibold text-pg-text-primary mb-2">No Buildings Found</h3>
            <p class="text-pg-text-secondary">Check your database connection or add buildings to your database</p>
        </div>
    <?php else: ?>
        <?php foreach ($buildings as $building): ?>
            <div class="card">
                <div class="flex items-center justify-between mb-4 pb-2 border-b border-pg-border">
                    <h3 class="text-lg font-semibold text-pg-text-primary">
                        <?php echo htmlspecialchars($building['building_name']); ?>
                        <span class="text-sm font-normal text-pg-text-secondary ml-2">
                            (<?php echo htmlspecialchars($building['building_code']); ?>)
                        </span>
                    </h3>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-opacity-20 <?php echo ($building['status'] ?? 'active') === 'active' ? 'bg-green-500 text-green-400' : 'bg-red-500 text-red-400'; ?>">
                        <?php echo htmlspecialchars(ucfirst($building['status'] ?? 'active')); ?>
                    </span>
                </div>

                <form method="POST" class="space-y-6">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="building_id" value="<?php echo intval($building['id'] ?? 0); ?>">

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Building Information -->
                        <div class="space-y-4">
                            <h4 class="font-semibold text-pg-text-primary">Building Information</h4>

                            <div>
                                <label class="block text-sm font-medium text-pg-text-primary mb-2">
                                    Building Name <span class="text-status-danger">*</span>
                                </label>
                                <input type="text"
                                    name="building_name"
                                    class="input-field w-full"
                                    required
                                    value="<?php echo htmlspecialchars($building['building_name']); ?>">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-pg-text-primary mb-2">
                                    Address
                                </label>
                                <textarea name="building_address"
                                    rows="3"
                                    class="input-field w-full resize-none"
                                    placeholder="Building address"><?php echo htmlspecialchars($building['building_address']); ?></textarea>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-pg-text-primary mb-2">
                                    Status
                                </label>
                                <select name="status" class="select-field w-full">
                                    <option value="active" <?php echo ($building['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="maintenance" <?php echo ($building['status'] ?? '') === 'maintenance' ? 'selected' : ''; ?>>Under Maintenance</option>
                                    <option value="inactive" <?php echo ($building['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                        </div>

                        <!-- Contact Information -->
                        <div class="space-y-4">
                            <h4 class="font-semibold text-pg-text-primary">Contact Information</h4>

                            <div>
                                <label class="block text-sm font-medium text-pg-text-primary mb-2">
                                    Contact Person
                                </label>
                                <input type="text"
                                    name="contact_person"
                                    class="input-field w-full"
                                    placeholder="Building Manager Name"
                                    value="<?php echo htmlspecialchars($building['contact_person']); ?>">
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-pg-text-primary mb-2">
                                    Contact Phone
                                </label>
                                <input type="tel"
                                    name="contact_phone"
                                    class="input-field w-full"
                                    placeholder="+91 9876543210"
                                    value="<?php echo htmlspecialchars($building['contact_phone']); ?>">
                            </div>

                            <!-- Statistics -->
                            <div class="pt-2">
                                <h5 class="font-medium text-pg-text-primary mb-2">Current Statistics</h5>
                                <div class="grid grid-cols-2 gap-4 text-sm">
                                    <div class="p-3 bg-pg-primary bg-opacity-50 rounded-lg">
                                        <div class="text-pg-text-secondary">Total Rooms</div>
                                        <div class="font-semibold text-pg-accent"><?php echo intval($building['actual_total_rooms'] ?? 0); ?></div>
                                    </div>
                                    <div class="p-3 bg-pg-primary bg-opacity-50 rounded-lg">
                                        <div class="text-pg-text-secondary">Occupied Rooms</div>
                                        <div class="font-semibold text-pg-accent"><?php echo intval($building['actual_occupied_rooms'] ?? 0); ?></div>
                                    </div>
                                    <div class="p-3 bg-pg-primary bg-opacity-50 rounded-lg">
                                        <div class="text-pg-text-secondary">Total Capacity</div>
                                        <div class="font-semibold text-pg-accent"><?php echo intval($building['actual_total_capacity'] ?? 0); ?></div>
                                    </div>
                                    <div class="p-3 bg-pg-primary bg-opacity-50 rounded-lg">
                                        <div class="text-pg-text-secondary">Current Occupancy</div>
                                        <div class="font-semibold text-pg-accent"><?php echo intval($building['actual_current_occupancy'] ?? 0); ?></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Last Updated -->
                            <?php if (!empty($building['updated_at'])): ?>
                                <div class="text-xs text-pg-text-secondary">
                                    Last updated: <?php echo date('M d, Y H:i', strtotime($building['updated_at'])); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="flex justify-end pt-4 border-t border-pg-border">
                        <button type="submit" class="btn-primary">
                            Update Building
                        </button>
                    </div>
                </form>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>
