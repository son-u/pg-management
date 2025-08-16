<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

$title = 'Admin Profile';
require_once '../../includes/auth_check.php';

$error = '';
$success = '';



// Function to safely format dates
function safe_date($date, $format = 'M d, Y')
{
    if (empty($date)) {
        return 'Not set';
    }

    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return 'Invalid date';
    }

    return date($format, $timestamp);
}

try {
    $supabase = supabase();

    // For demo purposes, we'll use admin ID = 1
    // In a real implementation, get this from session: $_SESSION['admin_id']
    $admin_id = 1;

    // Fetch current admin data from admin_users table
    $adminUsers = $supabase->select('admin_users', '*', ['id' => $admin_id]);

    if (empty($adminUsers)) {
        throw new Exception('Admin user not found. Please contact system administrator.');
    }

    $adminData = $adminUsers[0];

    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_profile') {
            // Validate input
            $full_name = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');

            if (empty($full_name)) {
                throw new Exception('Full name is required.');
            }

            if (empty($email)) {
                throw new Exception('Email address is required.');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Please enter a valid email address.');
            }

            // Check if email is already taken by another admin
            $existingUsers = $supabase->select('admin_users', 'id', ['email' => $email]);
            if (!empty($existingUsers) && $existingUsers[0]['id'] != $admin_id) {
                throw new Exception('This email address is already in use by another admin.');
            }

            // Update admin profile
            $updateData = [
                'full_name' => $full_name,
                'email' => $email,
                'updated_at' => date('c')
            ];

            $supabase->update('admin_users', $updateData, ['id' => $admin_id]);

            // Refresh admin data after update
            $adminUsers = $supabase->select('admin_users', '*', ['id' => $admin_id]);
            $adminData = $adminUsers[0];

            $success = 'Profile updated successfully!';
        } elseif ($action === 'change_password') {
            // Validate password fields
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            if (empty($current_password)) {
                throw new Exception('Current password is required.');
            }

            if (empty($new_password)) {
                throw new Exception('New password is required.');
            }

            if (empty($confirm_password)) {
                throw new Exception('Please confirm your new password.');
            }

            if (strlen($new_password) < 6) {
                throw new Exception('Password must be at least 6 characters long.');
            }

            if ($new_password !== $confirm_password) {
                throw new Exception('New password and confirmation do not match.');
            }

            // Verify current password (if password_hash exists)
            if (!empty($adminData['password_hash'])) {
                if (!password_verify($current_password, $adminData['password_hash'])) {
                    throw new Exception('Current password is incorrect.');
                }
            }

            // Hash the new password
            $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);

            // Update password in database
            $supabase->update('admin_users', [
                'password_hash' => $new_password_hash,
                'updated_at' => date('c')
            ], ['id' => $admin_id]);

            $success = 'Password changed successfully!';
        }
    }
} catch (Exception $e) {
    $error = $e->getMessage();
    error_log('Admin profile error: ' . $e->getMessage());

    // Fallback admin data if database fetch fails
    $adminData = [
        'id' => $admin_id ?? 1,
        'full_name' => 'Admin User',
        'email' => 'admin@example.com',
        'role' => 'admin',
        'status' => 'active',
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
        'last_login' => null
    ];
}
?>

<?php include '../../includes/header.php'; ?>

<!-- Admin Profile Interface -->
<div class="space-y-6 max-w-4xl mx-auto">
    <!-- Page Header -->
    <div class="flex items-center justify-between">
        <div class="flex items-center space-x-4">
            <a href="../dashboard.php" class="text-pg-text-secondary hover:text-pg-text-primary transition-colors duration-200">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
            </a>

            <div>
                <h1 class="text-2xl font-bold text-pg-text-primary">Admin Profile</h1>
                <p class="text-pg-text-secondary mt-1">Manage your account settings and information</p>
            </div>
        </div>

        <div class="flex items-center space-x-3">
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-pg-accent bg-opacity-20 text-pg-accent">
                <?php echo safe_html(ucfirst($adminData['role'] ?? 'admin')); ?>
            </span>
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-opacity-20 <?php echo ($adminData['status'] ?? 'active') === 'active' ? 'bg-green-500 text-green-400' : 'bg-red-500 text-red-400'; ?>">
                <?php echo safe_html(ucfirst($adminData['status'] ?? 'active')); ?>
            </span>

        </div>
    </div>

    <!-- Messages -->
    <?php if ($error): ?>
        <div class="bg-status-danger bg-opacity-10 border border-status-danger text-status-danger px-4 py-3 rounded-lg">
            <div class="flex items-center">
                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                </svg>
                <?php echo safe_html($error); ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="bg-status-success bg-opacity-10 border border-status-success text-status-success px-4 py-3 rounded-lg">
            <div class="flex items-center">
                <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
                <?php echo safe_html($success); ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Profile Information -->
        <div class="card">
            <h3 class="text-lg font-semibold text-pg-text-primary mb-4 pb-2 border-b border-pg-border flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
                Profile Information
            </h3>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="update_profile">

                <div>
                    <label for="admin_id" class="block text-sm font-medium text-pg-text-primary mb-2">
                        Admin ID
                    </label>
                    <input type="text"
                        id="admin_id"
                        class="input-field w-full bg-gray-600 cursor-not-allowed opacity-75"
                        value="<?php echo safe_html($adminData['id'] ?? 'N/A'); ?>"
                        disabled>
                    <p class="text-xs text-pg-text-secondary mt-1">System-generated ID</p>
                </div>

                <div>
                    <label for="full_name" class="block text-sm font-medium text-pg-text-primary mb-2">
                        Full Name <span class="text-status-danger">*</span>
                    </label>
                    <input type="text"
                        id="full_name"
                        name="full_name"
                        required
                        class="input-field w-full"
                        placeholder="Enter your full name"
                        value="<?php echo safe_html($adminData['full_name'] ?? ''); ?>">
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-pg-text-primary mb-2">
                        Email Address <span class="text-status-danger">*</span>
                    </label>
                    <input type="email"
                        id="email"
                        name="email"
                        required
                        class="input-field w-full"
                        placeholder="admin@example.com"
                        value="<?php echo safe_html($adminData['email'] ?? ''); ?>">
                </div>

                <div class="pt-4">
                    <button type="submit" class="btn-primary w-full">
                        <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
                        </svg>
                        Update Profile
                    </button>
                </div>
            </form>
        </div>

        <!-- Change Password -->
        <div class="card">
            <h3 class="text-lg font-semibold text-pg-text-primary mb-4 pb-2 border-b border-pg-border flex items-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                </svg>
                Change Password
            </h3>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="change_password">

                <div>
                    <label for="current_password" class="block text-sm font-medium text-pg-text-primary mb-2">
                        Current Password <span class="text-status-danger">*</span>
                    </label>
                    <input type="password"
                        id="current_password"
                        name="current_password"
                        required
                        class="input-field w-full"
                        placeholder="Enter current password">
                </div>

                <div>
                    <label for="new_password" class="block text-sm font-medium text-pg-text-primary mb-2">
                        New Password <span class="text-status-danger">*</span>
                    </label>
                    <input type="password"
                        id="new_password"
                        name="new_password"
                        required
                        minlength="6"
                        class="input-field w-full"
                        placeholder="Enter new password">
                    <p class="text-xs text-pg-text-secondary mt-1">Minimum 6 characters</p>
                </div>

                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-pg-text-primary mb-2">
                        Confirm New Password <span class="text-status-danger">*</span>
                    </label>
                    <input type="password"
                        id="confirm_password"
                        name="confirm_password"
                        required
                        minlength="6"
                        class="input-field w-full"
                        placeholder="Confirm new password">
                </div>

                <div class="pt-4">
                    <button type="submit" class="btn-primary w-full">
                        <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                        Change Password
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Account Information -->
    <div class="card">
        <h3 class="text-lg font-semibold text-pg-text-primary mb-4 pb-2 border-b border-pg-border flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            Account Information
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="text-center p-4 bg-pg-primary bg-opacity-50 rounded-lg">
                <div class="text-2xl font-bold text-pg-accent mb-1">
                    <?php echo safe_html(ucfirst($adminData['role'] ?? 'admin')); ?>
                </div>
                <div class="text-sm text-pg-text-secondary">Account Role</div>
            </div>

            <div class="text-center p-4 bg-pg-primary bg-opacity-50 rounded-lg">
                <div class="text-2xl font-bold text-pg-accent mb-1">
                    <?php echo safe_date($adminData['created_at'] ?? null); ?>
                </div>
                <div class="text-sm text-pg-text-secondary">Account Created</div>
            </div>

            <div class="text-center p-4 bg-pg-primary bg-opacity-50 rounded-lg">
                <div class="text-2xl font-bold text-pg-accent mb-1">
                    <?php echo safe_date($adminData['last_login'] ?? null, 'M d, H:i'); ?>
                </div>
                <div class="text-sm text-pg-text-secondary">Last Login</div>
            </div>

            <div class="text-center p-4 bg-pg-primary bg-opacity-50 rounded-lg">
                <div class="text-2xl font-bold <?php echo ($adminData['status'] ?? 'active') === 'active' ? 'text-green-400' : 'text-red-400'; ?> mb-1">
                    <?php echo safe_html(ucfirst($adminData['status'] ?? 'active')); ?>
                </div>
                <div class="text-sm text-pg-text-secondary">Account Status</div>
            </div>
        </div>

        <!-- Additional Info -->
        <div class="mt-6 pt-4 border-t border-pg-border">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div class="flex justify-between">
                    <span class="text-pg-text-secondary">User ID:</span>
                    <span class="text-pg-text-primary font-mono"><?php echo safe_html($adminData['user_id'] ?? 'N/A'); ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-pg-text-secondary">Last Updated:</span>
                    <span class="text-pg-text-primary"><?php echo safe_date($adminData['updated_at'] ?? null, 'M d, Y H:i'); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Password Confirmation JavaScript -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');

        function validatePasswords() {
            if (newPassword.value && confirmPassword.value) {
                if (newPassword.value === confirmPassword.value) {
                    confirmPassword.setCustomValidity('');
                    confirmPassword.classList.remove('border-red-500');
                    confirmPassword.classList.add('border-green-500');
                } else {
                    confirmPassword.setCustomValidity('Passwords do not match');
                    confirmPassword.classList.remove('border-green-500');
                    confirmPassword.classList.add('border-red-500');
                }
            } else {
                confirmPassword.setCustomValidity('');
                confirmPassword.classList.remove('border-red-500', 'border-green-500');
            }
        }

        newPassword.addEventListener('input', validatePasswords);
        confirmPassword.addEventListener('input', validatePasswords);
    });
</script>

<?php include '../../includes/footer.php'; ?>