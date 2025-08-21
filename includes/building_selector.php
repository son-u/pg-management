<?php
// Get current building selection
$selectedBuilding = $_GET['building'] ?? $_SESSION['selected_building'] ?? 'all';
$currentRoute = $_SERVER['REQUEST_URI'];

// Get buildings data using the new Buildings class
try {
    $buildingCodes = Buildings::getCodes();
    $buildingNames = Buildings::getNames();
} catch (Exception $e) {
    error_log('Building selector error: ' . $e->getMessage());
    $buildingCodes = [];
    $buildingNames = [];
}
?>

<div class="relative" x-data="{ open: false }">
    <button @click="open = !open"
        class="flex items-center px-4 py-2 bg-pg-card border border-pg-border rounded-lg text-sm font-medium text-pg-text-primary hover:border-pg-accent transition-colors duration-200">
        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-4m-5 0H3m2 0V9a2 2 0 012-2h4a2 2 0 012 2v12"></path>
        </svg>

        <?php if ($selectedBuilding === 'all'): ?>
            All Buildings
        <?php else: ?>
            <?php echo htmlspecialchars($buildingNames[$selectedBuilding] ?? $selectedBuilding); ?>
        <?php endif; ?>

        <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
        </svg>
    </button>

    <div x-show="open"
        @click.away="open = false"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="transform opacity-0 scale-95"
        x-transition:enter-end="transform opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="transform opacity-100 scale-100"
        x-transition:leave-end="transform opacity-0 scale-95"
        class="absolute right-0 mt-2 w-48 bg-pg-card border border-pg-border rounded-lg shadow-lg z-20">

        <!-- All Buildings Option -->
        <a href="<?php echo updateUrlParameter($currentRoute, 'building', 'all'); ?>"
            class="block px-4 py-3 text-sm text-pg-text-primary hover:bg-pg-hover <?php echo $selectedBuilding === 'all' ? 'bg-pg-accent text-white' : ''; ?> first:rounded-t-lg">
            <div class="flex items-center">
                <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                </svg>
                All Buildings
            </div>
        </a>

        <div class="border-t border-pg-border"></div>

        <!-- Individual Buildings -->
        <?php if (!empty($buildingCodes)): ?>
            <?php
            $lastCode = end($buildingCodes);
            foreach ($buildingCodes as $code):
            ?>
                <a href="<?php echo updateUrlParameter($currentRoute, 'building', $code); ?>"
                    class="block px-4 py-3 text-sm text-pg-text-primary hover:bg-pg-hover <?php echo $selectedBuilding === $code ? 'bg-pg-accent text-white' : ''; ?> <?php echo $code === $lastCode ? 'last:rounded-b-lg' : ''; ?>">
                    <div class="flex items-center">
                        <span class="w-3 h-3 bg-pg-accent rounded-full mr-3 flex-shrink-0"></span>
                        <?php echo htmlspecialchars($buildingNames[$code] ?? $code); ?>
                        <span class="ml-auto text-xs text-pg-text-secondary"><?php echo htmlspecialchars($code); ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="px-4 py-3 text-sm text-pg-text-secondary">
                No buildings available
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// Helper function to update URL parameters
function updateUrlParameter($url, $param, $value)
{
    $parsedUrl = parse_url($url);
    $query = [];

    if (isset($parsedUrl['query'])) {
        parse_str($parsedUrl['query'], $query);
    }

    $query[$param] = $value;

    $newQuery = http_build_query($query);
    $newUrl = $parsedUrl['path'] . ($newQuery ? '?' . $newQuery : '');

    return $newUrl;
}
?>