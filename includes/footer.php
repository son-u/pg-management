            </main>
            </div>
            </div>

            <!-- Loading Overlay -->
            <div id="loadingOverlay"
                class="fixed inset-0 bg-pg-primary bg-opacity-75 items-center justify-center z-50 hidden">
                <div class="bg-pg-card rounded-lg p-6 shadow-xl">
                    <div class="flex items-center">
                        <svg class="animate-spin -ml-1 mr-3 h-8 w-8 text-pg-accent" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span class="text-lg font-medium text-pg-text-primary">Loading...</span>
                    </div>
                </div>
            </div>


            <!-- Essential Footer JavaScript -->
            <script>
                // Loading overlay functions
                function showLoading() {
                    const overlay = document.getElementById('loadingOverlay');
                    if (overlay) {
                        overlay.classList.remove('hidden');
                        overlay.classList.add('flex');
                    }
                }

                function hideLoading() {
                    const overlay = document.getElementById('loadingOverlay');
                    if (overlay) {
                        overlay.classList.add('hidden');
                        overlay.classList.remove('flex');
                    }
                }

                // Page loader cleanup
                document.addEventListener('DOMContentLoaded', function() {
                    setTimeout(() => {
                        const loaders = document.querySelectorAll('#page-loader, .page-loader');
                        loaders.forEach(loader => {
                            if (loader) {
                                loader.style.display = 'none';
                                loader.style.opacity = '0';
                                loader.style.visibility = 'hidden';
                            }
                        });
                    }, 500);
                });

                // Global error handler
                window.addEventListener('error', function(e) {
                    console.error('PG Management System Error:', e.error);
                    if (typeof showNotification === 'function') {
                        showNotification('An unexpected error occurred. Please refresh the page.', 'error');
                    }
                });

                // Before page unload cleanup
                window.addEventListener('beforeunload', function() {
                    hideLoading();
                });
            </script>


            </body>

            </html>