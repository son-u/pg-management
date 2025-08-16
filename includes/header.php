<?php
require_once __DIR__ . '/auth_check.php';

// Safely get current admin data to avoid PHP warnings
$currentAdmin = getCurrentAdmin();
$adminName = isset($currentAdmin['name']) && $currentAdmin['name'] !== null ? htmlspecialchars($currentAdmin['name']) : 'Admin User';
$adminEmail = isset($currentAdmin['email']) && $currentAdmin['email'] !== null ? htmlspecialchars($currentAdmin['email']) : 'admin@pgmanagement.com';
$adminRole = isset($currentAdmin['role']) && $currentAdmin['role'] !== null ? htmlspecialchars($currentAdmin['role']) : 'admin';

$currentPage = basename($_SERVER['SCRIPT_NAME'], '.php');
$currentDir = basename(dirname($_SERVER['SCRIPT_NAME']));
?>
<!DOCTYPE html>
<html lang="en" class="dark">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($title ?? 'Dashboard'); ?> - <?php echo htmlspecialchars(APP_NAME); ?></title>

    <!-- Tailwind CSS -->
    <link href="<?php echo asset('css/style.css'); ?>" rel="stylesheet">

    <!-- Alpine.js CDN (Fixed) -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.9/dist/cdn.min.js"></script>

    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.js"></script>

    <!-- Custom JavaScript (load after Chart.js) -->
    <script src="<?php echo asset('js/custom.js'); ?>"></script>
    <script src="<?php echo asset('js/building-selector.js'); ?>"></script>
    <script src="<?php echo asset('js/session-manager.js'); ?>"></script>

    <!-- Custom CSS (will create) -->
    <link href="<?php echo asset('css/custom.css'); ?>">
    </link>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?php echo asset('images/favicon.png'); ?>">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="<?php echo csrf_token(); ?>">

    <!-- Meta Tags -->
    <meta name="description" content="Multi-Building PG Management System">
    <meta name="author" content="PG Management">
    <meta name="robots" content="noindex, nofollow">
    <meta name="session-lifetime" content="<?php echo SESSION_LIFETIME; ?>">
</head>

<body class="bg-pg-primary text-pg-text-primary font-sans min-h-screen">

    <!-- Flash Messages (FIXED) -->
    <?php
    $successMessage = flash('success');
    if ($successMessage !== null && $successMessage !== ''):
    ?>
        <div class="fixed top-4 right-4 z-40 bg-status-success text-white px-6 py-3 rounded-lg shadow-lg animate-fade-in"
            x-data="{ show: true }"
            x-show="show"
            x-init="setTimeout(() => show = false, 5000)">
            <div class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
                <?php echo htmlspecialchars($successMessage); ?>
            </div>
        </div>
    <?php endif; ?>

    <?php
    $errorMessage = flash('error');
    if ($errorMessage !== null && $errorMessage !== ''):
    ?>
        <div class="fixed top-4 right-4 z-40 bg-status-danger text-white px-6 py-3 rounded-lg shadow-lg animate-fade-in"
            x-data="{ show: true }"
            x-show="show"
            x-init="setTimeout(() => show = false, 5000)">
            <div class="flex items-center">
                <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                </svg>
                <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="flex  bg-pg-primary">
        <!-- Sidebar -->
        <?php include __DIR__ . '/nav.php'; ?>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Top Navigation -->
            <header class="bg-pg-card border-b border-pg-border px-6 py-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <button @click="sidebarOpen = !sidebarOpen" class="text-pg-text-secondary hover:text-pg-text-primary lg:hidden">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                            </svg>
                        </button>
                        <h1 class="text-xl font-semibold text-pg-text-primary ml-4 lg:ml-0">
                            <?php echo htmlspecialchars($title ?? 'Dashboard'); ?>
                        </h1>
                    </div>

                    <div class="flex items-center space-x-4">
                        <!-- Building Selector -->
                        <?php include __DIR__ . '/building_selector.php'; ?>

                        <!-- Profile Dropdown (FIXED) -->
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open"
                                class="flex items-center text-sm text-pg-text-primary hover:text-pg-accent focus:outline-none"
                                :aria-expanded="open"
                                aria-haspopup="true">
                                <img class="h-8 w-8 rounded-full object-cover"
                                    src="https://ui-avatars.com/api/?name=<?php echo urlencode($adminName); ?>&background=3ecf8e&color=fff"
                                    alt="Profile">
                                <span class="ml-2 hidden md:block"><?php echo $adminName; ?></span>
                                <svg class="w-4 h-4 ml-1 transition-transform duration-200"
                                    :class="{ 'transform rotate-180': open }"
                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>

                            <!-- Dropdown Menu (FIXED) -->
                            <div x-show="open"
                                @click.away="open = false"
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="transform opacity-0 scale-95"
                                x-transition:enter-end="transform opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-75"
                                x-transition:leave-start="transform opacity-100 scale-100"
                                x-transition:leave-end="transform opacity-0 scale-95"
                                class="absolute right-0 mt-2 w-48 bg-pg-card border border-pg-border rounded-lg shadow-lg z-50 py-1"
                                role="menu"
                                style="display: none;">
                                <div class="px-4 py-2 border-b border-pg-border">
                                    <p class="text-sm font-medium text-pg-text-primary"><?php echo $adminName; ?></p>
                                    <p class="text-xs text-pg-text-secondary"><?php echo $adminEmail; ?></p>
                                </div>
                                <a href="<?php echo route('admin/settings/profile.php'); ?>"
                                    class="block px-4 py-2 text-sm text-pg-text-primary hover:bg-pg-hover transition-colors duration-200"
                                    role="menuitem">
                                    <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                    Profile Settings
                                </a>
                                <a href="<?php echo route('logout.php'); ?>"
                                    class="block px-4 py-2 text-sm text-status-danger hover:bg-pg-hover transition-colors duration-200"
                                    role="menuitem">
                                    <svg class="w-4 h-4 mr-2 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                    </svg>
                                    Logout
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content Container -->
            <main class="flex-1 overflow-y-auto p-6">