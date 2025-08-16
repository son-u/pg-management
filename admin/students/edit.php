<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

// Set page title for header
$title = 'Edit Student';

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
$formData = [];

// Load existing student data
try {
    $supabase = supabase();
    
    $studentData = $supabase->select('students', '*', ['student_id' => $studentId]);
    
    if (empty($studentData)) {
        flash('error', 'Student not found.');
        header('Location: index.php');
        exit();
    }
    
    $student = $studentData[0];
    $formData = $student; // Initialize form with existing data
    
    // Update title with student name
    $title = 'Edit ' . $student['full_name'];
    
} catch (Exception $e) {
    $error = 'Error loading student: ' . $e->getMessage();
    error_log('Student edit load error: ' . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ✅ UPDATED: Collect and sanitize form data (removed unwanted fields)
    $formData = [
        'full_name' => trim($_POST['full_name'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'date_of_birth' => trim($_POST['date_of_birth'] ?? ''),
        'parent_phone' => trim($_POST['parent_phone'] ?? ''),
        'parent_name' => trim($_POST['parent_name'] ?? ''),
        'college_name' => trim($_POST['college_name'] ?? ''), // ✅ KEPT
        'course' => trim($_POST['course'] ?? ''), // ✅ KEPT
        'admission_date' => trim($_POST['admission_date'] ?? ''),
        'building_code' => trim($_POST['building_code'] ?? ''),
        'room_number' => trim($_POST['room_number'] ?? ''),
        'monthly_rent' => floatval($_POST['monthly_rent'] ?? 5000), // ✅ KEPT
        'address' => trim($_POST['address'] ?? ''), // ✅ UPDATED (single address field)
        'note' => trim($_POST['note'] ?? ''), // ✅ UPDATED (replaces medical_notes)
        'status' => $_POST['status'] ?? 'active'
    ];

    // Validation (same as before, just cleaned up)
    $validationErrors = [];
    
    if (empty($formData['full_name'])) {
        $validationErrors[] = 'Full Name is required';
    }
    
    if (empty($formData['building_code'])) {
        $validationErrors[] = 'Building selection is required';
    }
    
    if (!empty($formData['email']) && !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $validationErrors[] = 'Please enter a valid email address';
    }
    
    if (!empty($formData['phone']) && !preg_match('/^[0-9]{10}$/', $formData['phone'])) {
        $validationErrors[] = 'Phone number must be 10 digits';
    }
    
    if (!empty($formData['parent_phone']) && !preg_match('/^[0-9]{10}$/', $formData['parent_phone'])) {
        $validationErrors[] = 'Parent phone number must be 10 digits';
    }

    if (!empty($validationErrors)) {
        $error = implode('<br>', $validationErrors);
    } else {
        try {
            // Prepare update data (remove null values for optional fields)
            $updateData = [];
            foreach ($formData as $key => $value) {
                if ($value !== '' && $value !== null) {
                    $updateData[$key] = $value;
                } else {
                    // Set explicitly to null for empty optional fields
                    if (!in_array($key, ['full_name', 'building_code', 'status'])) {
                        $updateData[$key] = null;
                    }
                }
            }
            
            // Always include required fields and timestamps
            $updateData['updated_at'] = date('c');
            
            // Update student record
            $result = $supabase->update('students', $updateData, ['student_id' => $studentId]);
            
            if ($result) {
                flash('success', 'Student "' . htmlspecialchars($formData['full_name']) . '" has been updated successfully!');
                header('Location: view.php?id=' . urlencode($studentId));
                exit();
            } else {
                $error = 'Failed to update student. Please try again.';
            }
        } catch (Exception $e) {
            $error = 'Error updating student: ' . $e->getMessage();
            error_log('Student update error: ' . $e->getMessage());
        }
    }
}

// Helper function to format date for input
function formatDateForInput($date) {
    if (empty($date)) return '';
    try {
        return date('Y-m-d', strtotime($date));
    } catch (Exception $e) {
        return '';
    }
}

// ✅ ADDED: Fetch available buildings from database
$availableBuildings = [];
try {
    $supabase = supabase();
    $buildings = $supabase->select('buildings', 'building_code,building_name', [
        'status' => 'active'
    ]);
    $availableBuildings = $buildings ?: [];
} catch (Exception $e) {
    error_log('Error loading buildings: ' . $e->getMessage());
}
?>

<?php include '../../includes/header.php'; ?>

<!-- Edit Student Form -->
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
                <h1 class="text-2xl font-bold text-pg-text-primary">Edit Student</h1>
                <p class="text-pg-text-secondary mt-1">
                    Update student information for <?php echo htmlspecialchars($student['full_name'] ?? 'Student'); ?>
                </p>
            </div>
        </div>
        
        <div class="flex items-center space-x-3">
            <a href="view.php?id=<?php echo urlencode($studentId); ?>" class="btn-secondary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                </svg>
                <span>View Profile</span>
            </a>
        </div>
    </div>

    <!-- Error Messages -->
    <?php if ($error): ?>
        <div class="bg-status-danger bg-opacity-10 border border-status-danger text-status-danger px-4 py-3 rounded-lg">
            <div class="flex items-center">
                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                </svg>
                <div><?php echo $error; ?></div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Edit Student Form -->
    <div class="card">
        <form method="POST" class="space-y-6" data-validate>
            <!-- Basic Information Section -->
            <div>
                <h3 class="text-lg font-semibold text-pg-text-primary mb-4 pb-2 border-b border-pg-border">
                    Basic Information
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Student ID (Read-only) -->
                    <div>
                        <label for="student_id_display" class="block text-sm font-medium text-pg-text-primary mb-2">
                            Student ID
                        </label>
                        <input type="text" 
                               id="student_id_display" 
                               value="<?php echo htmlspecialchars($student['student_id'] ?? ''); ?>"
                               class="input-field w-full bg-gray-600 cursor-not-allowed"
                               readonly
                               title="Student ID cannot be changed">
                        <p class="text-xs text-pg-text-secondary mt-1">Student ID cannot be modified</p>
                    </div>

                    <!-- Full Name -->
                    <div>
                        <label for="full_name" class="block text-sm font-medium text-pg-text-primary mb-2">
                            Full Name <span class="text-status-danger">*</span>
                        </label>
                        <input type="text" 
                               id="full_name" 
                               name="full_name" 
                               value="<?php echo htmlspecialchars($formData['full_name'] ?? ''); ?>"
                               class="input-field w-full"
                               required>
                    </div>

                    <!-- Email -->
                    <div>
                        <label for="email" class="block text-sm font-medium text-pg-text-primary mb-2">
                            Email Address
                        </label>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               value="<?php echo htmlspecialchars($formData['email'] ?? ''); ?>"
                               class="input-field w-full"
                               placeholder="student@example.com">
                    </div>

                    <!-- Phone -->
                    <div>
                        <label for="phone" class="block text-sm font-medium text-pg-text-primary mb-2">
                            Phone Number
                        </label>
                        <input type="tel" 
                               id="phone" 
                               name="phone" 
                               value="<?php echo htmlspecialchars($formData['phone'] ?? ''); ?>"
                               class="input-field w-full"
                               placeholder="9876543210"
                               pattern="[0-9]{10}">
                        <p class="text-xs text-pg-text-secondary mt-1">10-digit mobile number</p>
                    </div>

                    <!-- Date of Birth -->
                    <div>
                        <label for="date_of_birth" class="block text-sm font-medium text-pg-text-primary mb-2">
                            Date of Birth
                        </label>
                        <input type="date" 
                               id="date_of_birth" 
                               name="date_of_birth" 
                               value="<?php echo formatDateForInput($formData['date_of_birth'] ?? ''); ?>"
                               class="input-field w-full"
                               max="<?php echo date('Y-m-d', strtotime('-15 years')); ?>">
                    </div>

                    <!-- Status -->
                    <div>
                        <label for="status" class="block text-sm font-medium text-pg-text-primary mb-2">
                            Status
                        </label>
                        <select id="status" name="status" class="select-field w-full">
                            <option value="active" <?php echo ($formData['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($formData['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- ✅ UPDATED: Academic Information Section (Simplified) -->
            <div>
                <h3 class="text-lg font-semibold text-pg-text-primary mb-4 pb-2 border-b border-pg-border">
                    Academic Information
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- College Name -->
                    <div>
                        <label for="college_name" class="block text-sm font-medium text-pg-text-primary mb-2">
                            College/Institution Name
                        </label>
                        <input type="text" 
                               id="college_name" 
                               name="college_name" 
                               value="<?php echo htmlspecialchars($formData['college_name'] ?? ''); ?>"
                               class="input-field w-full"
                               placeholder="e.g., Delhi University">
                    </div>

                    <!-- Course -->
                    <div>
                        <label for="course" class="block text-sm font-medium text-pg-text-primary mb-2">
                            Course/Program
                        </label>
                        <input type="text" 
                               id="course" 
                               name="course" 
                               value="<?php echo htmlspecialchars($formData['course'] ?? ''); ?>"
                               class="input-field w-full"
                               placeholder="e.g., B.Tech CSE, MBA, CA">
                    </div>

                    <!-- Admission Date -->
                    <div class="md:col-span-2">
                        <label for="admission_date" class="block text-sm font-medium text-pg-text-primary mb-2">
                            Admission Date
                        </label>
                        <input type="date" 
                               id="admission_date" 
                               name="admission_date" 
                               value="<?php echo formatDateForInput($formData['admission_date'] ?? ''); ?>"
                               class="input-field w-full md:w-1/2">
                    </div>
                </div>
            </div>

            <!-- ✅ UPDATED: Accommodation Information Section -->
            <div>
                <h3 class="text-lg font-semibold text-pg-text-primary mb-4 pb-2 border-b border-pg-border">
                    Accommodation Details
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Building (Dynamic from Database) -->
                    <div>
                        <label for="building_code" class="block text-sm font-medium text-pg-text-primary mb-2">
                            Building <span class="text-status-danger">*</span>
                        </label>
                        <select id="building_code" name="building_code" class="select-field w-full" onchange="loadRooms(this.value)" required>
                            <option value="">Select Building</option>
                            <?php foreach ($availableBuildings as $building): ?>
                                <option value="<?php echo htmlspecialchars($building['building_code']); ?>" 
                                        <?php echo ($formData['building_code'] ?? '') === $building['building_code'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($building['building_name']); ?> (<?php echo htmlspecialchars($building['building_code']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Room Number (Dynamic) -->
                    <div>
                        <label for="room_number" class="block text-sm font-medium text-pg-text-primary mb-2">
                            Room Number
                        </label>
                        <select id="room_number" name="room_number" class="select-field w-full">
                            <option value="">Select Building First</option>
                            <?php if (!empty($formData['room_number'])): ?>
                                <option value="<?php echo htmlspecialchars($formData['room_number']); ?>" selected>
                                    <?php echo htmlspecialchars($formData['room_number']); ?> (Current)
                                </option>
                            <?php endif; ?>
                        </select>
                        <p class="text-xs text-pg-text-secondary mt-1">Available rooms will load after selecting building</p>
                    </div>

                    <!-- Monthly Rent -->
                    <div>
                        <label for="monthly_rent" class="block text-sm font-medium text-pg-text-primary mb-2">
                            Monthly Rent (₹)
                        </label>
                        <input type="number" 
                               id="monthly_rent" 
                               name="monthly_rent" 
                               value="<?php echo htmlspecialchars($formData['monthly_rent'] ?? '5000'); ?>"
                               class="input-field w-full"
                               min="1000"
                               max="50000"
                               step="100"
                               placeholder="5000">
                    </div>
                </div>
            </div>

            <!-- ✅ UPDATED: Contact Information Section (Simplified) -->
            <div>
                <h3 class="text-lg font-semibold text-pg-text-primary mb-4 pb-2 border-b border-pg-border">
                    Contact & Additional Information
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Parent Name -->
                    <div>
                        <label for="parent_name" class="block text-sm font-medium text-pg-text-primary mb-2">
                            Parent/Guardian Name
                        </label>
                        <input type="text" 
                               id="parent_name" 
                               name="parent_name" 
                               value="<?php echo htmlspecialchars($formData['parent_name'] ?? ''); ?>"
                               class="input-field w-full"
                               placeholder="Parent or guardian's full name">
                    </div>

                    <!-- Parent Phone -->
                    <div>
                        <label for="parent_phone" class="block text-sm font-medium text-pg-text-primary mb-2">
                            Parent/Guardian Phone
                        </label>
                        <input type="tel" 
                               id="parent_phone" 
                               name="parent_phone" 
                               value="<?php echo htmlspecialchars($formData['parent_phone'] ?? ''); ?>"
                               class="input-field w-full"
                               placeholder="9876543210"
                               pattern="[0-9]{10}">
                    </div>

                    <!-- Address (Single Field) -->
                    <div class="md:col-span-2">
                        <label for="address" class="block text-sm font-medium text-pg-text-primary mb-2">
                            Home Address
                        </label>
                        <textarea id="address" 
                                  name="address" 
                                  rows="3"
                                  class="input-field w-full resize-none"
                                  placeholder="Complete home address"><?php echo htmlspecialchars($formData['address'] ?? $formData['permanent_address'] ?? ''); ?></textarea>
                    </div>
                </div>

                <!-- ✅ UPDATED: Note Field (replaces Medical Notes) -->
                <div class="mt-6">
                    <label for="note" class="block text-sm font-medium text-pg-text-primary mb-2">
                        Additional Notes (Optional)
                    </label>
                    <textarea id="note" 
                              name="note" 
                              rows="3"
                              class="input-field w-full resize-none"
                              placeholder="Any additional information, special requirements, or important notes"><?php echo htmlspecialchars($formData['note'] ?? $formData['medical_notes'] ?? ''); ?></textarea>
                    <p class="text-xs text-pg-text-secondary mt-1">Brief notes about the student for reference</p>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex items-center justify-between pt-6 border-t border-pg-border">
                <a href="view.php?id=<?php echo urlencode($studentId); ?>" class="btn-secondary">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                    <span>Cancel</span>
                </a>
                <div class="flex items-center space-x-3">
                    <button type="reset" class="btn-secondary" onclick="return confirm('Are you sure you want to reset all changes?');">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        <span>Reset</span>
                    </button>
                    <button type="submit" class="btn-primary">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                        <span>Update Student</span>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- ✅ ADDED: JavaScript for Dynamic Room Loading -->
<script>
async function loadRooms(buildingCode) {
    const roomSelect = document.getElementById('room_number');
    const currentRoom = '<?php echo htmlspecialchars($formData['room_number'] ?? ''); ?>';
    
    // Reset room dropdown
    roomSelect.innerHTML = '<option value="">Loading rooms...</option>';
    
    if (!buildingCode) {
        roomSelect.innerHTML = '<option value="">Select Building First</option>';
        return;
    }
    
    try {
        // You'll need to create this API endpoint (same as add.php)
        const response = await fetch(`../../api/get-rooms.php?building=${encodeURIComponent(buildingCode)}`);
        const data = await response.json();
        
        if (data.success && data.rooms) {
            roomSelect.innerHTML = '<option value="">Select Room (Optional)</option>';
            
            // Add current room first if it exists
            if (currentRoom) {
                const currentOption = document.createElement('option');
                currentOption.value = currentRoom;
                currentOption.textContent = `${currentRoom} (Current)`;
                currentOption.selected = true;
                roomSelect.appendChild(currentOption);
            }
            
            data.rooms.forEach(room => {
                // Skip if it's the current room (already added)
                if (room.room_number === currentRoom) return;
                
                const option = document.createElement('option');
                option.value = room.room_number;
                option.textContent = `${room.room_number} (${room.capacity} capacity, ${room.current_occupancy || 0} occupied)`;
                
                // Disable if room is full
                if (room.current_occupancy >= room.capacity) {
                    option.disabled = true;
                    option.textContent += ' - FULL';
                }
                
                roomSelect.appendChild(option);
            });
        } else {
            roomSelect.innerHTML = '<option value="">No rooms available</option>';
            
            // Add current room if it exists
            if (currentRoom) {
                const currentOption = document.createElement('option');
                currentOption.value = currentRoom;
                currentOption.textContent = `${currentRoom} (Current)`;
                currentOption.selected = true;
                roomSelect.appendChild(currentOption);
            }
        }
    } catch (error) {
        console.error('Error loading rooms:', error);
        roomSelect.innerHTML = '<option value="">Error loading rooms</option>';
        
        // Add current room if it exists
        if (currentRoom) {
            const currentOption = document.createElement('option');
            currentOption.value = currentRoom;
            currentOption.textContent = `${currentRoom} (Current)`;
            currentOption.selected = true;
            roomSelect.appendChild(currentOption);
        }
    }
}

// Load rooms on page load if building is selected
document.addEventListener('DOMContentLoaded', function() {
    const buildingSelect = document.getElementById('building_code');
    if (buildingSelect.value) {
        loadRooms(buildingSelect.value);
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
