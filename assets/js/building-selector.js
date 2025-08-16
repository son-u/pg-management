// Building selector functionality - NO IMPORTS NEEDED
document.addEventListener('DOMContentLoaded', function() {
    initializeBuildingSelector();
});

function initializeBuildingSelector() {
    // Handle building filter persistence
    const buildingLinks = document.querySelectorAll('[href*="building="]');
    
    buildingLinks.forEach(link => {
        link.addEventListener('click', function() {
            const url = new URL(this.href);
            const building = url.searchParams.get('building');
            
            if (building) {
                // Store in session storage
                sessionStorage.setItem('selectedBuilding', building);
                
                // Add loading state
                this.classList.add('opacity-50');
                this.style.pointerEvents = 'none';
            }
        });
    });
    
    // Restore building selection
    const savedBuilding = sessionStorage.getItem('selectedBuilding');
    if (savedBuilding) {
        const currentSelector = document.querySelector('[data-building-selector]');
        if (currentSelector) {
            // Update UI to show selected building
            const selectedOption = currentSelector.querySelector(`[data-building="${savedBuilding}"]`);
            if (selectedOption) {
                selectedOption.classList.add('bg-pg-accent', 'text-white');
            }
        }
    }
}

// Update building filter
function updateBuildingFilter(building) {
    const url = new URL(window.location);
    
    if (building === 'all') {
        url.searchParams.delete('building');
    } else {
        url.searchParams.set('building', building);
    }
    
    window.location.href = url.toString();
}

// Building selector dropdown functionality
function toggleBuildingDropdown() {
    const dropdown = document.getElementById('building-dropdown');
    if (dropdown) {
        dropdown.classList.toggle('hidden');
    }
}
