<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

// Set page title for header
$title = 'Add New Student';

// Authentication check
require_once '../../includes/auth_check.php';

// Initialize variables
$error = '';
$success = '';
$formData = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and sanitize form data (UPDATED - removed unwanted fields)
    $formData = [
        'student_id' => trim($_POST['student_id'] ?? ''),
        'full_name' => trim($_POST['full_name'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'date_of_birth' => trim($_POST['date_of_birth'] ?? ''),
        'parent_phone' => trim($_POST['parent_phone'] ?? ''),
        'parent_name' => trim($_POST['parent_name'] ?? ''),
        'college_name' => trim($_POST['college_name'] ?? ''), // ✅ ADDED
        'course' => trim($_POST['course'] ?? ''),
        'building_code' => trim($_POST['building_code'] ?? ''),
        'room_number' => trim($_POST['room_number'] ?? ''),
        'monthly_rent' => floatval($_POST['monthly_rent'] ?? 5000), // ✅ ADDED
        'address' => trim($_POST['address'] ?? ''),
        'note' => trim($_POST['note'] ?? ''), 
        'status' => $_POST['status'] ?? 'active'
    ];

    // Validation (UPDATED - removed unwanted validations)
    $validationErrors = [];
    
    if (empty($formData['student_id'])) {
        $validationErrors[] = 'Student ID is required';
    }
    
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
            $supabase = supabase();
            
            // Check if student ID already exists
            $existingStudent = $supabase->select('students', 'student_id', [
                'student_id' => $formData['student_id']
            ]);
            
            if (!empty($existingStudent)) {
                $error = 'Student ID "' . htmlspecialchars($formData['student_id']) . '" already exists. Please choose a different ID.';
            } else {
                // Prepare student data for insertion (UPDATED - removed unwanted fields)
                $studentData = [
                    'student_id' => $formData['student_id'],
                    'full_name' => $formData['full_name'],
                    'email' => $formData['email'] ?: null,
                    'phone' => $formData['phone'] ?: null,
                    'date_of_birth' => $formData['date_of_birth'] ?: null,
                    'parent_phone' => $formData['parent_phone'] ?: null,
                    'parent_name' => $formData['parent_name'] ?: null,
                    'college_name' => $formData['college_name'] ?: null, // ✅ ADDED
                    'course' => $formData['course'] ?: null,
                    'building_code' => $formData['building_code'],
                    'room_number' => $formData['room_number'] ?: null,
                    'monthly_rent' => $formData['monthly_rent'],
                    'address' => $formData['address'] ?: null,
                    'note' => $formData['note'] ?: null, // ✅ ADDED (replaces medical_notes)
                    'status' => $formData['status'],
                    'admission_date' => date('Y-m-d'), // ✅ ADDED
                    'created_at' => date('c'),
                    'updated_at' => date('c')
                ];

                // Insert student record
                $result = $supabase->insert('students', $studentData);
                
                if ($result) {
                    flash('success', 'Student "' . htmlspecialchars($formData['full_name']) . '" has been added successfully!');
                    header('Location: index.php');
                    exit();
                } else {
                    $error = 'Failed to add student. Please try again.';
                }
            }
        } catch (Exception $e) {
            $error = 'Error adding student: ' . $e->getMessage();
            error_log('Add student error: ' . $e->getMessage());
        }
    }
}

// Generate auto student ID suggestion
$suggestedStudentId = '';
try {
    $currentYear = date('Y');
    $supabase = supabase();
    $recentStudents = $supabase->select('students', 'student_id', []);
    
    // Find the highest number for current year
    $maxNumber = 0;
    foreach ($recentStudents as $student) {
        if (preg_match('/^PG-' . $currentYear . '-(\d+)$/', $student['student_id'], $matches)) {
            $maxNumber = max($maxNumber, intval($matches[1]));
        }
    }
    
    $nextNumber = $maxNumber + 1;
    $suggestedStudentId = 'PG-' . $currentYear . '-' . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
} catch (Exception $e) {
    // If auto-generation fails, provide a basic suggestion
    $suggestedStudentId = 'PG-' . date('Y') . '-001';
}

// ✅ UPDATED: Fetch available buildings using Buildings class
$availableBuildings = [];
try {
    $availableBuildings = Buildings::getAll();
} catch (Exception $e) {
    error_log('Add student buildings error: ' . $e->getMessage());
    $availableBuildings = [];
}
?>

<?php include '../../includes/header.php'; ?>

<!-- Add Student Form -->
<div class="space-y-6">
    <!-- Page Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-pg-text-primary">Add New Student</h1>
            <p class="text-pg-text-secondary mt-1">Register a new student in the PG management system</p>
        </div>
        <div class="flex items-center space-x-3">
            <a href="index.php" class="btn-secondary">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Back to Students
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

    <!-- Add Student Form -->
    <div class="card">
        <form method="POST" class="space-y-6" data-validate>
            <!-- Basic Information Section -->
            <div>
                <h3 class="text-lg font-semibold text-pg-text-primary mb-4 pb-2 border-b border-pg-border">
                    Basic Information
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Student ID -->
                    <div>
                        <label for="student_id" class="block text-sm font-medium text-pg-text-primary mb-2">
                            Student ID <span class="text-status-danger">*</span>
                        </label>
                        <div class="relative">
                            <input type="text" 
                                   id="student_id" 
                                   name="student_id" 
                                   value="<?php echo htmlspecialchars($formData['student_id'] ?? $suggestedStudentId); ?>"
                                   class="input-field w-full"
                                   placeholder="e.g., PG-2025-001"
                                   required>
                            <p class="text-xs text-pg-text-secondary mt-1">Auto-generated unique identifier</p>
                        </div>
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
                               placeholder="Enter student's full name"
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
                               value="<?php echo htmlspecialchars($formData['date_of_birth'] ?? ''); ?>"
                               class="input-field w-full"
                               max="<?php echo date('Y-m-d', strtotime('-15 years')); ?>">
                    </div>

                    <!-- Status -->
                    <div>
                        <label for="status" class="block text-sm font-medium text-pg-text-primary mb-2">
                            Status
                        </label>
                        <select id="status" name="status" class="select-field w-full">
                            <option value="active" <?php echo ($formData['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
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
                               placeholder="e.g., Siliguri Institute of Technology">
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
                </div>
            </div>

            <!-- ✅ UPDATED: Accommodation Information Section -->
            <div>
                <h3 class="text-lg font-semibold text-pg-text-primary mb-4 pb-2 border-b border-pg-border">
                    Accommodation Details
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- Building (Dynamic from Database using Buildings class) -->
                    <div>
                        <label for="building_code" class="block text-sm font-medium text-pg-text-primary mb-2">
                            Building <span class="text-status-danger">*</span>
                        </label>
                        <select id="building_code" name="building_code" class="select-field w-full" onchange="loadRooms(this.value)" required>
                            <option value="">Select Building</option>
                            <?php if (!empty($availableBuildings)): ?>
                                <?php foreach ($availableBuildings as $building): ?>
                                    <option value="<?php echo htmlspecialchars($building['building_code']); ?>" 
                                            <?php echo ($formData['building_code'] ?? '') === $building['building_code'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($building['building_name']); ?> (<?php echo htmlspecialchars($building['building_code']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled>No buildings available</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <!-- Room Number (Dynamic) -->
                    <div>
                        <label for="room_number" class="block text-sm font-medium text-pg-text-primary mb-2">
                            Room Number
                        </label>
                        <select id="room_number" name="room_number" class="select-field w-full">
                            <option value="">Select Building First</option>
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
                               value="<?php echo htmlspecialchars($formData['monthly_rent'] ?? '7000'); ?>"
                               class="input-field w-full"
                               min="1000"
                               max="50000"
                               step="100"
                               placeholder="7000">
                    </div>
                </div>
            </div>

            <!-- ✅ UPDATED: Contact Information Section -->
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

                    <!-- Address -->
                    <div class="md:col-span-2">
                        <label for="address" class="block text-sm font-medium text-pg-text-primary mb-2">
                            Home Address
                        </label>
                        <textarea id="address" 
                                  name="address" 
                                  rows="3"
                                  class="input-field w-full resize-none"
                                  placeholder="Complete home address"><?php echo htmlspecialchars($formData['address'] ?? ''); ?></textarea>
                    </div>
                </div>

                <!-- ✅ UPDATED: Short Note (replaces Medical Notes) -->
                <div class="mt-6">
                    <label for="note" class="block text-sm font-medium text-pg-text-primary mb-2">
                        Additional Notes (Optional)
                    </label>
                    <textarea id="note" 
                              name="note" 
                              rows="3"
                              class="input-field w-full resize-none"
                              placeholder="Any additional information, special requirements, or important notes"><?php echo htmlspecialchars($formData['note'] ?? ''); ?></textarea>
                    <p class="text-xs text-pg-text-secondary mt-1">Brief notes about the student for reference</p>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex items-center justify-between pt-6 border-t border-pg-border">
                <a href="index.php" class="btn-secondary">
                    Cancel
                </a>
                <div class="flex items-center space-x-3">
                    <button type="reset" class="btn-secondary">
                        Clear Form
                    </button>
                    <button type="submit" class="btn-primary">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Add Student
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
    
    // Reset room dropdown
    roomSelect.innerHTML = '<option value="">Loading rooms...</option>';
    
    if (!buildingCode) {
        roomSelect.innerHTML = '<option value="">Select Building First</option>';
        return;
    }
    
    try {
        // You'll need to create this API endpoint
        const response = await fetch(`../../api/get-rooms.php?building=${encodeURIComponent(buildingCode)}`);
        const data = await response.json();
        
        if (data.success && data.rooms) {
            roomSelect.innerHTML = '<option value="">Select Room (Optional)</option>';
            
            data.rooms.forEach(room => {
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
        }
    } catch (error) {
        console.error('Error loading rooms:', error);
        roomSelect.innerHTML = '<option value="">Error loading rooms</option>';
    }
}

// Load rooms if building is pre-selected (on form reload after error)
document.addEventListener('DOMContentLoaded', function() {
    const buildingSelect = document.getElementById('building_code');
    if (buildingSelect.value) {
        loadRooms(buildingSelect.value);
    }
});
</script>

<?php include '../../includes/footer.php'; ?>
