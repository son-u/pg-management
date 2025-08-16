<?php
$currentPage = basename($_SERVER['SCRIPT_NAME'], '.php');
$currentDir = basename(dirname($_SERVER['SCRIPT_NAME']));
?>

<div x-data="{ sidebarOpen: false }" @keydown.window.escape="sidebarOpen = false">
    <!-- Mobile Menu Button -->
    <button @click="sidebarOpen = true"
        class="lg:hidden fixed top-4 left-4 z-50 p-2 rounded-md bg-pg-accent text-white shadow-lg hover:bg-green-600 transition-colors duration-200">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
        </svg>
    </button>

    <!-- Desktop Sidebar -->
    <aside class="bg-pg-secondary w-64 min-h-screen shadow-lg flex-shrink-0 hidden lg:flex flex-col">
        <!-- Logo Section -->
        <div class="p-6 border-b border-pg-border">
            <div class="flex items-center">
                <div class="relative">
                    <img src="<?php echo asset('images/pg-logo.webp'); ?>"
                        alt="PG Management Logo"
                        class="h-10 w-10 object-cover rounded-full border-3 border-pg-accent bg-white p-0.5">
                </div>
                <div class="ml-3">
                    <h1 class="text-lg font-bold text-pg-text-primary">Pinki's PG</h1>
                    <p class="text-xs text-pg-text-secondary">A home far from home</p>
                </div>
            </div>
        </div>

        <!-- Desktop Navigation Menu -->
        <nav class="flex-1 p-4 space-y-2 overflow-y-auto">
            <!-- Dashboard -->
            <a href="<?php echo route('admin/dashboard.php'); ?>"
                class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors duration-200 <?php echo $currentPage === 'dashboard' ? 'bg-pg-accent text-white hover:text-white' : 'text-pg-text-primary hover:bg-pg-hover hover:text-pg-accent'; ?>">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2V7zm0 0a2 2 0 012-2h.01M3 7a2 2 0 012-2h.01m13 0a2 2 0 012 2H5a2 2 0 00-2-2v0z"></path>
                </svg>
                Dashboard
            </a>


            <!-- Buildings Section -->
            <div class="pt-4">
                <!-- Enhanced Buildings Header -->
                <div class="px-4 py-2 bg-pg-accent bg-opacity-10 rounded-lg mx-2 mb-2">
                    <p class="text-xs font-semibold text-pg-accent uppercase tracking-wider">All Buildings</p>
                    <p class="text-xs text-pg-text-secondary mt-1">Select building to filter data</p>
                </div>

                <!-- Individual Buildings -->
                <?php foreach (BUILDINGS as $code): ?>
                    <a href="<?php echo route('admin/dashboard.php') . '?building=' . urlencode($code); ?>"
                        class="flex items-center px-4 py-2 ml-2 text-sm font-medium rounded-lg transition-colors duration-200 <?php echo isset($_GET['building']) && $_GET['building'] === $code ? 'bg-pg-accent text-white' : 'text-pg-text-secondary hover:bg-pg-hover hover:text-pg-accent'; ?>">
                        <span class="w-2 h-2 bg-pg-accent rounded-full mr-3"></span>
                        <?php echo htmlspecialchars(BUILDING_NAMES[$code] ?? $code); ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Students Section -->
            <div class="pt-4">
                <p class="px-4 text-xs font-semibold text-pg-text-secondary uppercase tracking-wider">Students</p>

                <a href="<?php echo route('admin/students/index.php'); ?>"
                    class="mt-2 flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors duration-200 <?php echo $currentDir === 'students' && $currentPage === 'index' ? 'bg-pg-accent text-white' : 'text-pg-text-primary hover:bg-pg-hover hover:text-pg-accent'; ?>">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                    </svg>
                    All Students
                </a>

                <a href="<?php echo route('admin/students/add.php'); ?>"
                    class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors duration-200 <?php echo $currentDir === 'students' && $currentPage === 'add' ? 'bg-pg-accent text-white' : 'text-pg-text-primary hover:bg-pg-hover hover:text-pg-accent'; ?>">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"></path>
                    </svg>
                    Add Student
                </a>
            </div>

            <!-- Payments Section -->
            <div class="pt-4">
                <p class="px-4 text-xs font-semibold text-pg-text-secondary uppercase tracking-wider">Payments</p>

                <a href="<?php echo route('admin/payments/index.php'); ?>"
                    class="mt-2 flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors duration-200 <?php echo $currentDir === 'payments' && $currentPage === 'index' ? 'bg-pg-accent text-white' : 'text-pg-text-primary hover:bg-pg-hover hover:text-pg-accent'; ?>">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                    </svg>
                    All Payments
                </a>

                <a href="<?php echo route('admin/payments/add.php'); ?>"
                    class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors duration-200 <?php echo $currentDir === 'payments' && $currentPage === 'add' ? 'bg-pg-accent text-white' : 'text-pg-text-primary hover:bg-pg-hover hover:text-pg-accent'; ?>">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Record Payment
                </a>

                <a href="<?php echo route('admin/reports/overdue.php'); ?>"
                    class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors duration-200 <?php echo $currentDir === 'reports' && $currentPage === 'overdue' ? 'bg-red-500 text-white' : 'text-red-400 hover:bg-red-500 hover:bg-opacity-10 hover:text-red-300'; ?>">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                    </svg>
                    Overdue Payments
                </a>

                <a href="<?php echo route('admin/reports/pending.php'); ?>"
                    class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors duration-200 <?php echo $currentDir === 'reports' && $currentPage === 'pending' ? 'bg-yellow-500 text-white' : 'text-status-warning hover:bg-pg-hover hover:text-yellow-300'; ?>">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Pending Payments
                </a>
            </div>

            <!-- Reports Section -->
            <div class="pt-4">
                <p class="px-4 text-xs font-semibold text-pg-text-secondary uppercase tracking-wider">Reports</p>

                <a href="<?php echo route('admin/reports/overview.php'); ?>"
                    class="mt-2 flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors duration-200 <?php echo $currentDir === 'reports' && $currentPage === 'overview' ? 'bg-pg-accent text-white' : 'text-pg-text-primary hover:bg-pg-hover hover:text-pg-accent'; ?>">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    Overview
                </a>

                <a href="<?php echo route('admin/reports/monthly.php'); ?>"
                    class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors duration-200 <?php echo $currentDir === 'reports' && $currentPage === 'monthly' ? 'bg-pg-accent text-white' : 'text-pg-text-primary hover:bg-pg-hover hover:text-pg-accent'; ?>">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    Monthly Reports
                </a>

                <a href="<?php echo route('admin/reports/building_wise.php'); ?>"
                    class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors duration-200 <?php echo $currentDir === 'reports' && $currentPage === 'building_wise' ? 'bg-pg-accent text-white' : 'text-pg-text-primary hover:bg-pg-hover hover:text-pg-accent'; ?>">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-4m-5 0H3m2 0V9a2 2 0 012-2h4a2 2 0 012 2v12"></path>
                    </svg>
                    Building-wise
                </a>

                <a href="<?php echo route('admin/reports/export.php'); ?>"
                    class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors duration-200 <?php echo $currentDir === 'reports' && $currentPage === 'export' ? 'bg-pg-accent text-white' : 'text-pg-text-primary hover:bg-pg-hover hover:text-pg-accent'; ?>">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Export Data
                </a>
            </div>

            <!-- Settings Section -->
            <div class="pt-4">
                <p class="px-4 text-xs font-semibold text-pg-text-secondary uppercase tracking-wider">Settings</p>

                <a href="<?php echo route('admin/settings/rooms.php'); ?>"
                    class="mt-2 flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors duration-200 <?php echo $currentDir === 'settings' && $currentPage === 'rooms' ? 'bg-pg-accent text-white' : 'text-pg-text-primary hover:bg-pg-hover hover:text-pg-accent'; ?>">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                    </svg>
                    Room Management
                </a>

                <a href="<?php echo route('admin/settings/buildings.php'); ?>"
                    class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors duration-200 <?php echo $currentDir === 'settings' && $currentPage === 'buildings' ? 'bg-pg-accent text-white' : 'text-pg-text-primary hover:bg-pg-hover hover:text-pg-accent'; ?>">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-4m-5 0H3m2 0V9a2 2 0 012-2h4a2 2 0 012 2v12"></path>
                    </svg>
                    Building Settings
                </a>

                <a href="<?php echo route('admin/settings/profile.php'); ?>"
                    class="flex items-center px-4 py-3 text-sm font-medium rounded-lg transition-colors duration-200 <?php echo $currentDir === 'settings' && $currentPage === 'profile' ? 'bg-pg-accent text-white' : 'text-pg-text-primary hover:bg-pg-hover hover:text-pg-accent'; ?>">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    Admin Profile
                </a>
            </div>
        </nav>

        <!-- Footer Section with Developer Info -->
        <div class="p-4 border-t border-pg-border">
            <div class="flex items-center justify-between text-xs text-pg-text-secondary">
                <span>v1.0.0</span>
                <div class="flex items-center space-x-2">
                    <span>PG Manager</span>
                    <!-- Developer Info Tooltip -->
                    <div class="relative" x-data="{ showTooltip: false }">
                        <button @mouseenter="showTooltip = true"
                            @mouseleave="showTooltip = false"
                            class="flex items-center justify-center w-4 h-4 rounded-full bg-pg-accent bg-opacity-20 text-pg-accent hover:bg-opacity-30 transition-colors duration-200">
                            <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-3a1 1 0 00-.867.5 1 1 0 11-1.731-1A3 3 0 0113 8a3.001 3.001 0 01-2 2.83V11a1 1 0 11-2 0v-1a1 1 0 011-1 1 1 0 100-2zm0 8a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"></path>
                            </svg>
                        </button>
                        <!-- Tooltip -->
                        <div x-show="showTooltip"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 transform scale-95"
                            x-transition:enter-end="opacity-100 transform scale-100"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100 transform scale-100"
                            x-transition:leave-end="opacity-0 transform scale-95"
                            class="absolute bottom-full right-0 mb-2 px-3 py-2 text-xs text-white bg-gray-900 rounded-lg shadow-lg whitespace-nowrap z-50">
                            <div class="text-center">
                                <div class="font-medium">Developed by</div>
                                <div class="text-pg-accent">Sonu Sharma</div>
                            </div>
                            <!-- Tooltip arrow -->
                            <div class="absolute top-full right-4 w-0 h-0 border-l-4 border-r-4 border-t-4 border-transparent border-t-gray-900"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </aside>

    <!-- Mobile Sidebar Overlay -->
    <div x-show="sidebarOpen"
        x-transition:opacity
        @click="sidebarOpen = false"
        class="fixed inset-0 z-40 bg-black bg-opacity-50 lg:hidden">
    </div>

    <!-- Mobile Sidebar -->
    <aside x-show="sidebarOpen"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="-translate-x-full"
        x-transition:enter-end="translate-x-0"
        x-transition:leave="transition ease-in duration-300"
        x-transition:leave-start="translate-x-0"
        x-transition:leave-end="-translate-x-full"
        @click.away="sidebarOpen = false"
        class="fixed top-0 left-0 z-50 w-64 h-full bg-pg-secondary shadow-lg lg:hidden overflow-y-auto">

        <!-- Mobile Header with Close Button -->
        <div class="p-6 border-b border-pg-border flex items-center justify-between">
            <div class="flex items-center">
                <div class="relative">
                    <img src="<?php echo asset('images/pg-logo.webp'); ?>"
                        alt="PG Management Logo"
                        class="h-8 w-8 object-cover rounded-full border-2 border-pg-accent bg-white p-0.5">
                </div>
                <div class="ml-3">
                    <h1 class="text-lg font-bold text-pg-text-primary">PG Manager</h1>
                </div>
            </div>
            <button @click="sidebarOpen = false"
                class="text-pg-text-secondary hover:text-pg-text-primary p-1 rounded">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <!-- Mobile Navigation Menu -->
        <nav class="flex-1 p-4 space-y-1 overflow-y-auto">
            <!-- Dashboard -->
            <a href="<?php echo route('admin/dashboard.php'); ?>"
                @click="sidebarOpen = false"
                class="block px-3 py-2 rounded-md text-sm font-medium transition-colors duration-200 <?php echo $currentPage === 'dashboard' ? 'bg-pg-accent text-white' : 'text-pg-text-primary hover:bg-pg-hover hover:text-pg-accent'; ?>">
                Dashboard
            </a>

            <!-- Buildings Section -->
            <div class="pt-3">
                <p class="px-3 text-xs font-semibold text-pg-text-secondary uppercase tracking-wider">Buildings</p>

                <?php foreach (BUILDINGS as $code): ?>
                    <a href="<?php echo route('admin/dashboard.php') . '?building=' . urlencode($code); ?>"
                        @click="sidebarOpen = false"
                        class="block px-6 py-1.5 rounded-md text-sm transition-colors duration-200 <?php echo isset($_GET['building']) && $_GET['building'] === $code ? 'bg-pg-accent text-white' : 'text-pg-text-secondary hover:bg-pg-hover hover:text-pg-accent'; ?>">
                        <?php echo htmlspecialchars(BUILDING_NAMES[$code] ?? $code); ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Students Section -->
            <div class="pt-3">
                <p class="px-3 text-xs font-semibold text-pg-text-secondary uppercase tracking-wider">Students</p>

                <a href="<?php echo route('admin/students/index.php'); ?>"
                    @click="sidebarOpen = false"
                    class="block px-3 py-2 mt-1 rounded-md text-sm font-medium transition-colors duration-200 <?php echo $currentDir === 'students' && $currentPage === 'index' ? 'bg-pg-accent text-white' : 'text-pg-text-primary hover:bg-pg-hover hover:text-pg-accent'; ?>">
                    All Students
                </a>

                <a href="<?php echo route('admin/students/add.php'); ?>"
                    @click="sidebarOpen = false"
                    class="block px-3 py-2 rounded-md text-sm font-medium transition-colors duration-200 <?php echo $currentDir === 'students' && $currentPage === 'add' ? 'bg-pg-accent text-white' : 'text-pg-text-primary hover:bg-pg-hover hover:text-pg-accent'; ?>">
                    Add Student
                </a>
            </div>

            <!-- Payments Section -->
            <div class="pt-3">
                <p class="px-3 text-xs font-semibold text-pg-text-secondary uppercase tracking-wider">Payments</p>

                <a href="<?php echo route('admin/payments/index.php'); ?>"
                    @click="sidebarOpen = false"
                    class="block px-3 py-2 mt-1 rounded-md text-sm font-medium transition-colors duration-200 <?php echo $currentDir === 'payments' && $currentPage === 'index' ? 'bg-pg-accent text-white' : 'text-pg-text-primary hover:bg-pg-hover hover:text-pg-accent'; ?>">
                    All Payments
                </a>

                <a href="<?php echo route('admin/payments/add.php'); ?>"
                    @click="sidebarOpen = false"
                    class="block px-3 py-2 rounded-md text-sm font-medium transition-colors duration-200 <?php echo $currentDir === 'payments' && $currentPage === 'add' ? 'bg-pg-accent text-white' : 'text-pg-text-primary hover:bg-pg-hover hover:text-pg-accent'; ?>">
                    Record Payment
                </a>

                <a href="<?php echo route('admin/reports/overdue.php'); ?>"
                    @click="sidebarOpen = false"
                    class="block px-3 py-2 rounded-md text-sm font-medium transition-colors duration-200 <?php echo $currentDir === 'reports' && $currentPage === 'overdue' ? 'bg-red-500 text-white' : 'text-red-400 hover:bg-red-500 hover:bg-opacity-10 hover:text-red-300'; ?>">
                    Overdue Payments
                </a>

                <a href="<?php echo route('admin/reports/pending.php'); ?>"
                    @click="sidebarOpen = false"
                    class="block px-3 py-2 rounded-md text-sm font-medium transition-colors duration-200 <?php echo $currentDir === 'reports' && $currentPage === 'pending' ? 'bg-yellow-500 text-white' : 'text-status-warning hover:bg-pg-hover hover:text-yellow-300'; ?>">
                    Pending Payments
                </a>
            </div>

            <!-- Reports Section -->
            <div class="pt-3">
                <p class="px-3 text-xs font-semibold text-pg-text-secondary uppercase tracking-wider">Reports</p>

                <a href="<?php echo route('admin/reports/overview.php'); ?>"
                    @click="sidebarOpen = false"
                    class="block px-3 py-2 mt-1 rounded-md text-sm font-medium transition-colors duration-200 <?php echo $currentDir === 'reports' && $currentPage === 'overview' ? 'bg-pg-accent text-white' : 'text-pg-text-primary hover:bg-pg-hover hover:text-pg-accent'; ?>">
                    Overview
                </a>

                <a href="<?php echo route('admin/reports/monthly.php'); ?>"
                    @click="sidebarOpen = false"
                    class="block px-3 py-2 rounded-md text-sm font-medium transition-colors duration-200 <?php echo $currentDir === 'reports' && $currentPage === 'monthly' ? 'bg-pg-accent text-white' : 'text-pg-text-primary hover:bg-pg-hover hover:text-pg-accent'; ?>">
                    Monthly Reports
                </a>

                <a href="<?php echo route('admin/reports/building_wise.php'); ?>"
                    @click="sidebarOpen = false"
                    class="block px-3 py-2 rounded-md text-sm font-medium transition-colors duration-200 <?php echo $currentDir === 'reports' && $currentPage === 'building_wise' ? 'bg-pg-accent text-white' : 'text-pg-text-primary hover:bg-pg-hover hover:text-pg-accent'; ?>">
                    Building-wise
                </a>

                <a href="<?php echo route('admin/reports/export.php'); ?>"
                    @click="sidebarOpen = false"
                    class="block px-3 py-2 rounded-md text-sm font-medium transition-colors duration-200 <?php echo $currentDir === 'reports' && $currentPage === 'export' ? 'bg-pg-accent text-white' : 'text-pg-text-primary hover:bg-pg-hover hover:text-pg-accent'; ?>">
                    Export Data
                </a>
            </div>

            <!-- Settings Section -->
            <div class="pt-3 pb-4">
                <p class="px-3 text-xs font-semibold text-pg-text-secondary uppercase tracking-wider">Settings</p>

                <a href="<?php echo route('admin/settings/rooms.php'); ?>"
                    @click="sidebarOpen = false"
                    class="block px-3 py-2 mt-1 rounded-md text-sm font-medium transition-colors duration-200 <?php echo $currentDir === 'settings' && $currentPage === 'rooms' ? 'bg-pg-accent text-white' : 'text-pg-text-primary hover:bg-pg-hover hover:text-pg-accent'; ?>">
                    Room Management
                </a>

                <a href="<?php echo route('admin/settings/buildings.php'); ?>"
                    @click="sidebarOpen = false"
                    class="block px-3 py-2 rounded-md text-sm font-medium transition-colors duration-200 <?php echo $currentDir === 'settings' && $currentPage === 'buildings' ? 'bg-pg-accent text-white' : 'text-pg-text-primary hover:bg-pg-hover hover:text-pg-accent'; ?>">
                    Building Settings
                </a>

                <a href="<?php echo route('admin/settings/profile.php'); ?>"
                    @click="sidebarOpen = false"
                    class="block px-3 py-2 rounded-md text-sm font-medium transition-colors duration-200 <?php echo $currentDir === 'settings' && $currentPage === 'profile' ? 'bg-pg-accent text-white' : 'text-pg-text-primary hover:bg-pg-hover hover:text-pg-accent'; ?>">
                    Admin Profile
                </a>
            </div>
        </nav>
    </aside>
</div>