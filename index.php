<?php
require_once 'config/config.php';
require_once 'config/database.php';

// Redirect if already logged in
if (isset($_SESSION['admin_id'])) {
    redirect(route('admin/dashboard.php'));
}

$error = '';
$email = '';

// Handle login form submission
if ($_POST) {
    $email = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    // CSRF token verification
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        $error = 'Security token mismatch. Please try again.';
    } else if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        try {
            // Create Supabase client
            $supabase = supabase();

            // Get admin user via API
            $response = $supabase->select('admin_users', 'id,email,password_hash,full_name,role,status', [
                'email' => $email
            ]);

            if (!empty($response)) {
                $admin = $response[0];

                // Check if account is active
                if ($admin['status'] !== 'active') {
                    $error = 'Account is not active. Please contact administrator.';
                } else {
                    // Verify password
                    if (password_verify($password, $admin['password_hash'])) {
                        // Update last login
                        try {
                            $supabase->update(
                                'admin_users',
                                ['last_login' => date('c'), 'updated_at' => date('c')],
                                ['id' => $admin['id']]
                            );
                        } catch (Exception $updateError) {
                            // Don't fail login if update fails
                            error_log("Failed to update last_login: " . $updateError->getMessage());
                        }

                        // Set session data
                        $_SESSION['admin_id'] = $admin['id'];
                        $_SESSION['admin_email'] = $admin['email'];
                        $_SESSION['admin_name'] = $admin['full_name'];
                        $_SESSION['admin_role'] = $admin['role'];
                        $_SESSION['login_time'] = time();

                        // Initialize session tracking for new session management
                        $_SESSION['last_activity'] = time();
                        $_SESSION['created'] = time();

                        // Set success message
                        flash('success', 'Welcome back, ' . $admin['full_name'] . '!');

                        // Redirect to dashboard
                        header('Location: ' . route('admin/dashboard.php'));
                        exit();
                    } else {
                        $error = 'Invalid email or password.';
                        error_log("Password verification failed for user: $email");
                    }
                }
            } else {
                $error = 'Invalid email or password.';
                error_log("No user found with email: $email");
            }
        } catch (Exception $e) {
            $error = 'Login system temporarily unavailable. Please try again later.';
            error_log('Login error: ' . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo APP_NAME; ?></title>

    <!-- Tailwind CSS -->
    <link href="<?php echo asset('css/style.css'); ?>" rel="stylesheet">

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo asset('images/favicon.png'); ?>">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="<?php echo csrf_token(); ?>">

    <!-- Meta Tags -->
    <meta name="description" content="Admin login for PG Management System">
    <meta name="robots" content="noindex, nofollow">
</head>

<body class="bg-pg-primary text-pg-text-primary font-sans min-h-screen">
    <!-- Background -->
    <div class="absolute inset-0 bg-gradient-to-br from-pg-primary via-pg-secondary to-pg-primary"></div>

    <!-- Main Container -->
    <div class="relative min-h-screen flex items-center justify-center py-8 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-6">
            <!-- Logo and Title -->
            <div class="text-center">
                <div class="mx-auto mb-6 flex justify-center">
                    <div class="relative">
                        <div class="absolute inset-0 bg-pg-accent rounded-full"></div>
                        <img src="<?php echo asset('images/pg-logo.webp'); ?>"
                            alt="PG Management Logo"
                            class="relative h-16 w-16 sm:h-20 sm:w-20 object-cover rounded-full border-1 border-pg-accent bg-white">
                    </div>

                </div>
                <h1 class="text-2xl sm:text-3xl font-bold text-pg-text-primary">
                    Pinki's PG
                </h1>
                <p class="mt-2 text-sm text-pg-text-secondary">
                    A home far from home.
                </p>
            </div>

            <!-- Login Form Card -->
            <div class="bg-pg-card rounded-lg shadow-lg border border-pg-border">
                <div class="px-6 py-8 sm:px-8">

                    <!-- Error Message -->
                    <?php if ($error): ?>
                        <div class="mb-6 bg-status-danger bg-opacity-10 border border-status-danger text-status-danger px-4 py-3 rounded-lg text-sm">
                            <div class="flex items-center">
                                <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                </svg>
                                <span><?php echo htmlspecialchars($error); ?></span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Login Form -->
                    <form method="POST" class="space-y-6" x-data="{ loading: false }" @submit="loading = true">
                        <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

                        <!-- Email Field -->
                        <div>
                            <label for="email" class="block text-sm font-medium text-pg-text-primary mb-2">
                                Email Address
                            </label>
                            <div class="relative">
                                <input id="email"
                                    name="email"
                                    type="email"
                                    autocomplete="email"
                                    required
                                    value="<?php echo htmlspecialchars($email); ?>"
                                    class="input-field w-full pl-10 pr-4 py-3 text-sm focus:ring-2 focus:ring-pg-accent focus:border-transparent transition-colors duration-200"
                                    placeholder="Enter your email">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-pg-text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207"></path>
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- Password Field -->
                        <div>
                            <label for="password" class="block text-sm font-medium text-pg-text-primary mb-2">
                                Password
                            </label>
                            <div class="relative" x-data="{ showPassword: false }">
                                <input id="password"
                                    name="password"
                                    :type="showPassword ? 'text' : 'password'"
                                    autocomplete="current-password"
                                    required
                                    class="input-field w-full pl-10 pr-12 py-3 text-sm focus:ring-2 focus:ring-pg-accent focus:border-transparent transition-colors duration-200"
                                    placeholder="Enter your password">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-pg-text-secondary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                    </svg>
                                </div>
                                <button type="button"
                                    @click="showPassword = !showPassword"
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-pg-text-secondary hover:text-pg-text-primary transition-colors duration-200">
                                    <svg x-show="!showPassword" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                    <svg x-show="showPassword" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.878 9.878L3 3m6.878 6.878L21 21"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div>
                            <button type="submit"
                                class="w-full flex justify-center py-3 px-4 border border-transparent text-sm font-medium rounded-lg text-white bg-pg-accent hover:bg-green-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-pg-accent transition-colors duration-200 disabled:opacity-50 disabled:cursor-not-allowed"
                                :disabled="loading">
                                <span x-show="!loading" class="flex items-center">
                                    <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                                    </svg>
                                    Sign In
                                </span>
                                <span x-show="loading" class="flex items-center">
                                    <svg class="animate-spin h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Signing In...
                                </span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Footer -->
            <div class="text-center">
                <p class="text-xs text-pg-text-secondary">
                    &copy; <?php echo date('Y'); ?> - Developed by Sonu Sharma
                </p>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-focus email field
            const emailInput = document.getElementById('email');
            if (emailInput && !emailInput.value) {
                emailInput.focus();
            }

            // Form validation
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const email = document.getElementById('email').value.trim();
                    const password = document.getElementById('password').value.trim();

                    if (!email || !password) {
                        e.preventDefault();
                        alert('Please enter both email and password.');
                        return false;
                    }

                    if (!email.includes('@')) {
                        e.preventDefault();
                        alert('Please enter a valid email address.');
                        document.getElementById('email').focus();
                        return false;
                    }

                    return true;
                });
            }
        });
    </script>
</body>

</html>