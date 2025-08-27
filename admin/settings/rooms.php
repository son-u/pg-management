<?php
require_once '../../config/config.php';
require_once '../../config/database.php';
$title = 'Room Management';
require_once '../../includes/auth_check.php';

$error = '';
$success = '';
$supabase = supabase();
$buildings = $supabase->select('buildings', '*', []);
$rooms = $supabase->select('rooms', '*', []);

// Create building names array
$buildingNames = [];
foreach ($buildings as $building) {
    $buildingNames[$building['building_code']] = $building['building_name'];
}

// ✅ FUNCTION TO CALCULATE EFFECTIVE STATUS BASED ON OCCUPANCY
function getEffectiveStatus($room) {
    $storedStatus = $room['status'] ?? 'available';
    $currentOccupancy = intval($room['current_occupancy']);
    $capacity = intval($room['capacity']);
    
    // Priority order: maintenance > reserved > occupancy-based status
    if ($storedStatus === 'maintenance') {
        return 'maintenance';
    }
    
    if ($storedStatus === 'reserved') {
        return 'reserved';
    }
    
    // Auto-calculate based on occupancy
    if ($currentOccupancy >= $capacity) {
        return 'occupied';
    } elseif ($currentOccupancy === 0) {
        return 'available';
    } else {
        return 'partially_occupied'; // New status for partially filled rooms
    }
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $action = $_POST['action'] ?? '';

        if ($action === 'add') {
            $building_code = trim($_POST['building_code'] ?? '');
            $room_number = trim($_POST['room_number'] ?? '');
            $capacity = intval($_POST['capacity'] ?? 2);
            $standard_rent = floatval($_POST['standard_rent'] ?? 0);
            $room_type = trim($_POST['room_type'] ?? 'shared');
            $facilities = trim($_POST['facilities'] ?? '');
            $status = trim($_POST['status'] ?? 'available');

            if (!$building_code || !$room_number || !$standard_rent) {
                throw new Exception('Building, room number, and standard rent are required.');
            }

            $insertData = [
                'building_code' => $building_code,
                'room_number' => $room_number,
                'capacity' => $capacity,
                'standard_rent' => $standard_rent,
                'room_type' => $room_type,
                'facilities' => $facilities,
                'status' => $status,
                'current_occupancy' => 0
            ];

            $result = $supabase->insert('rooms', $insertData);
            if ($result) {
                $success = 'Room added successfully!';
            } else {
                throw new Exception('Failed to add room');
            }
        } elseif ($action === 'edit') {
            $room_id = intval($_POST['room_id'] ?? 0);
            if (!$room_id) throw new Exception('Room ID is required for editing.');

            $updateData = [
                'building_code' => trim($_POST['building_code'] ?? ''),
                'room_number' => trim($_POST['room_number'] ?? ''),
                'capacity' => intval($_POST['capacity'] ?? 2),
                'standard_rent' => floatval($_POST['standard_rent'] ?? 0),
                'room_type' => trim($_POST['room_type'] ?? 'shared'),
                'facilities' => trim($_POST['facilities'] ?? ''),
                'status' => trim($_POST['status'] ?? 'available'),
                'updated_at' => date('c')
            ];

            $result = $supabase->update('rooms', $updateData, ['id' => $room_id]);
            if ($result) {
                $success = 'Room updated successfully!';
            } else {
                throw new Exception('Failed to update room');
            }
        } elseif ($action === 'delete') {
            $room_id = intval($_POST['room_id'] ?? 0);
            if (!$room_id) throw new Exception('Room ID is required for deletion.');

            $result = $supabase->delete('rooms', ['id' => $room_id]);
            if ($result) {
                $success = 'Room deleted successfully!';
            } else {
                throw new Exception('Failed to delete room');
            }
        }

        // Refresh rooms data
        $rooms = $supabase->select('rooms', '*', []);
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get room for editing
$editRoom = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $editRooms = $supabase->select('rooms', '*', ['id' => intval($_GET['edit'])]);
    $editRoom = !empty($editRooms) ? $editRooms[0] : null;
}

// Sort rooms by building and room number
usort($rooms, function ($a, $b) {
    $buildingCompare = strcmp($a['building_code'] ?? '', $b['building_code'] ?? '');
    if ($buildingCompare === 0) {
        return strcmp($a['room_number'] ?? '', $b['room_number'] ?? '');
    }
    return $buildingCompare;
});

// NOTE: getBuildingName() function is already defined in config.php - no need to redeclare it
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
                <h1 class="text-2xl font-bold text-pg-text-primary">Room Management</h1>
                <p class="text-pg-text-secondary mt-1">Manage room inventory and configuration</p>
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

    

    <!-- Add/Edit Room Form -->
    <div class="card">
        <h3 class="text-lg font-semibold text-pg-text-primary mb-4 pb-2 border-b border-pg-border">
            <?php echo $editRoom ? 'Edit Room' : 'Add New Room'; ?>
        </h3>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="action" value="<?php echo $editRoom ? 'edit' : 'add'; ?>">
            <?php if ($editRoom): ?>
                <input type="hidden" name="room_id" value="<?php echo htmlspecialchars($editRoom['id']); ?>">
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Building Selection -->
                <div>
                    <label for="building_code" class="block text-sm font-medium text-pg-text-primary mb-2">
                        Building <span class="text-status-danger">*</span>
                    </label>
                    <select name="building_code" id="building_code" required class="select-field w-full">
                        <option value="">Select Building</option>
                        <?php foreach ($buildings as $building): ?>
                            <option value="<?php echo htmlspecialchars($building['building_code']); ?>"
                                <?php echo ($editRoom && $editRoom['building_code'] === $building['building_code']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($building['building_name']); ?> (<?php echo htmlspecialchars($building['building_code']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Room Number -->
                <div>
                    <label for="room_number" class="block text-sm font-medium text-pg-text-primary mb-2">
                        Room Number <span class="text-status-danger">*</span>
                    </label>
                    <input type="text"
                        id="room_number"
                        name="room_number"
                        required
                        class="input-field w-full"
                        placeholder="e.g., 101, A-201"
                        value="<?php echo htmlspecialchars($editRoom['room_number'] ?? ''); ?>">
                </div>

                <!-- Capacity -->
                <div>
                    <label for="capacity" class="block text-sm font-medium text-pg-text-primary mb-2">
                        Capacity
                    </label>
                    <input type="number"
                        id="capacity"
                        name="capacity"
                        min="1"
                        max="10"
                        class="input-field w-full"
                        value="<?php echo htmlspecialchars($editRoom['capacity'] ?? '2'); ?>">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Standard Rent -->
                <div>
                    <label for="standard_rent" class="block text-sm font-medium text-pg-text-primary mb-2">
                        Standard Rent (₹) <span class="text-status-danger">*</span>
                    </label>
                    <input type="number"
                        step="0.01"
                        min="0"
                        id="standard_rent"
                        name="standard_rent"
                        required
                        class="input-field w-full"
                        placeholder="5000"
                        value="<?php echo htmlspecialchars($editRoom['standard_rent'] ?? ''); ?>">
                </div>

                <!-- Room Type -->
                <div>
                    <label for="room_type" class="block text-sm font-medium text-pg-text-primary mb-2">
                        Room Type
                    </label>
                    <select id="room_type" name="room_type" class="select-field w-full">
                        <option value="shared" <?php echo ($editRoom && $editRoom['room_type'] === 'shared') ? 'selected' : ''; ?>>Shared</option>
                        <option value="single" <?php echo ($editRoom && $editRoom['room_type'] === 'single') ? 'selected' : ''; ?>>Single</option>
                        <option value="double" <?php echo ($editRoom && $editRoom['room_type'] === 'double') ? 'selected' : ''; ?>>Double</option>
                        <option value="triple" <?php echo ($editRoom && $editRoom['room_type'] === 'triple') ? 'selected' : ''; ?>>Triple</option>
                    </select>
                </div>

                <!-- Manual Status Override -->
                <div>
                    <label for="status" class="block text-sm font-medium text-pg-text-primary mb-2">
                        Manual Status Override
                    </label>
                    <select id="status" name="status" class="select-field w-full">
                        <option value="available" <?php echo ($editRoom && $editRoom['status'] === 'available') ? 'selected' : ''; ?>>Auto (Based on Occupancy)</option>
                        <option value="maintenance" <?php echo ($editRoom && $editRoom['status'] === 'maintenance') ? 'selected' : ''; ?>>Maintenance</option>
                        <option value="reserved" <?php echo ($editRoom && $editRoom['status'] === 'reserved') ? 'selected' : ''; ?>>Reserved</option>
                    </select>
                    <p class="text-xs text-pg-text-secondary mt-1">Manual status overrides automatic occupancy-based status</p>
                </div>
            </div>

            <!-- Facilities -->
            <div>
                <label for="facilities" class="block text-sm font-medium text-pg-text-primary mb-2">
                    Facilities & Amenities
                </label>
                <textarea id="facilities"
                    name="facilities"
                    rows="3"
                    class="input-field w-full resize-none"
                    placeholder="e.g., AC, WiFi, Attached Bathroom, Balcony"><?php echo htmlspecialchars($editRoom['facilities'] ?? ''); ?></textarea>
            </div>

            <!-- Form Actions -->
            <div class="flex items-center justify-between pt-4 border-t border-pg-border">
                <?php if ($editRoom): ?>
                    <a href="rooms.php" class="btn-secondary">Cancel Edit</a>
                <?php else: ?>
                    <div></div>
                <?php endif; ?>

                <button type="submit" class="btn-primary">
                    <?php echo $editRoom ? 'Update Room' : 'Add Room'; ?>
                </button>
            </div>
        </form>
    </div>

    <!-- Room List -->
    <div class="card">
        <h3 class="text-lg font-semibold text-pg-text-primary mb-4 pb-2 border-b border-pg-border">
            Room Inventory (<?php echo count($rooms); ?> rooms)
        </h3>

        <?php if (empty($rooms)): ?>
            <div class="text-center py-8">
                <svg class="w-12 h-12 text-pg-text-secondary mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
                <h3 class="text-lg font-semibold text-pg-text-primary mb-2">No Rooms Found</h3>
                <p class="text-pg-text-secondary">Add your first room using the form above</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead>
                        <tr class="border-b border-pg-border">
                            <th class="table-header text-left">Building</th>
                            <th class="table-header text-left">Room</th>
                            <th class="table-header text-center">Capacity</th>
                            <th class="table-header text-center">Occupancy</th>
                            <th class="table-header text-left">Type</th>
                            <th class="table-header text-center">Status</th>
                            <th class="table-header text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rooms as $room): ?>
                            <?php 
                            // ✅ CALCULATE EFFECTIVE STATUS BASED ON OCCUPANCY
                            $effectiveStatus = getEffectiveStatus($room);
                            $currentOccupancy = intval($room['current_occupancy']);
                            $capacity = intval($room['capacity']);
                            ?>
                            <tr class="border-b border-pg-border hover:bg-pg-hover transition-colors duration-200">
                                <td class="px-6 py-4">
                                    <div class="font-medium text-pg-text-primary">
                                        <?php echo htmlspecialchars(getBuildingName($room['building_code'], $buildingNames)); ?>
                                    </div>
                                    <div class="text-sm text-pg-text-secondary">
                                        <?php echo htmlspecialchars($room['building_code']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="font-semibold text-pg-text-primary">
                                        <?php echo htmlspecialchars($room['room_number']); ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-pg-accent bg-opacity-20 text-pg-accent">
                                        <?php echo $capacity; ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="text-sm <?php echo $currentOccupancy >= $capacity ? 'text-status-danger font-semibold' : ($currentOccupancy > 0 ? 'text-yellow-600' : 'text-pg-text-secondary'); ?>">
                                        <?php echo $currentOccupancy; ?>/<?php echo $capacity; ?>
                                    </span>
                                    <?php if ($currentOccupancy >= $capacity): ?>
                                        <div class="text-xs text-status-danger font-medium">FULL</div>
                                    <?php elseif ($currentOccupancy > 0): ?>
                                        <div class="text-xs text-yellow-600">PARTIAL</div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="capitalize text-sm"><?php echo htmlspecialchars($room['room_type'] ?? 'shared'); ?></span>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <?php
                                    // ✅ UPDATED STATUS COLORS AND LOGIC
                                    $statusColors = [
                                        'available' => 'bg-green-500 bg-opacity-20 text-green-400',
                                        'occupied' => 'bg-blue-500 bg-opacity-20 text-blue-400', 
                                        'partially_occupied' => 'bg-yellow-500 bg-opacity-20 text-yellow-500',
                                        'maintenance' => 'bg-red-500 bg-opacity-20 text-red-400',
                                        'reserved' => 'bg-purple-500 bg-opacity-20 text-purple-400'
                                    ];
                                    $statusClass = $statusColors[$effectiveStatus] ?? 'bg-gray-500 bg-opacity-20 text-gray-400';
                                    
                                    // ✅ STATUS DISPLAY NAMES
                                    $statusNames = [
                                        'available' => 'Available',
                                        'occupied' => 'Occupied',
                                        'partially_occupied' => 'Partially Filled',
                                        'maintenance' => 'Maintenance',
                                        'reserved' => 'Reserved'
                                    ];
                                    $statusName = $statusNames[$effectiveStatus] ?? ucfirst($effectiveStatus);
                                    ?>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $statusClass; ?>">
                                        <?php echo $statusName; ?>
                                    </span>
                                    
                                    <?php if ($room['status'] !== 'available' && in_array($room['status'], ['maintenance', 'reserved'])): ?>
                                        <div class="text-xs text-pg-text-secondary mt-1">Manual Override</div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <div class="flex items-center justify-center space-x-2">
                                        <a href="rooms.php?edit=<?php echo intval($room['id']); ?>"
                                            class="text-blue-400 hover:text-blue-300 transition-colors duration-200"
                                            title="Edit Room">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                            </svg>
                                        </a>
                                        <form method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this room?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="room_id" value="<?php echo intval($room['id']); ?>">
                                            <button type="submit"
                                                class="text-red-400 hover:text-red-300 transition-colors duration-200"
                                                title="Delete Room">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                </svg>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
