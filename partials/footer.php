</main>
    </div>

    <!-- Help / Glossary Modal -->
    <div class="modal fade" id="glossaryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold"><i class="bi bi-info-circle text-accent me-2"></i>SFIMS Glossary & Help</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 h-100 border">
                                <h6 class="fw-bold text-accent"><i class="bi bi-box-seam me-2"></i>Consumables vs Fixed Assets</h6>
                                <p class="small text-muted mb-2"><strong>Consumables:</strong> Items that get used up (e.g., paper, ink). Stock is counted in bulk quantities.</p>
                                <p class="small text-muted mb-0"><strong>Fixed Assets:</strong> Permanent equipment (e.g., laptops, desks). Each unit is tracked individually with its own barcode and assigned location.</p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-3 h-100 border">
                                <h6 class="fw-bold text-accent"><i class="bi bi-arrow-down-up me-2"></i>Receive vs Disburse</h6>
                                <p class="small text-muted mb-2"><strong>Receive Stock:</strong> Logging new items coming into the supply room from a supplier.</p>
                                <p class="small text-muted mb-0"><strong>Disburse Stock:</strong> Issuing stock out of the supply room to a specific person, room, or department.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <footer class="footer mt-auto py-3 border-top" style="background-color: var(--header-dark) !important; border-bottom: none !important;">
        <div class="container text-center">
            <span class="small" style="color: var(--footer-text) !important; font-weight: 500;">
                &copy; <?php echo date('Y'); ?> SPMO-SFIMS - Pamantasan ng lungsod ng muntinlupa
            </span>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jsPDF and AutoTable for PDF Export -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
    <!-- Select2 JS for Searchable Dropdowns -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            // Initialize Bootstrap Tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('.sidebar-link[title], [data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl, {
                    trigger: 'hover',
                    placement: tooltipTriggerEl.classList.contains('sidebar-link') ? 'right' : 'top'
                });
            });

            // Apply select2 to any element with the select2 class
            $('.select2').select2({
                theme: 'bootstrap-5',
                width: '100%'
            });
            
            // Fix for auto-submitting forms when Select2 is used
            $('.select2').on('select2:select', function (e) {
                if (this.hasAttribute('onchange') && this.getAttribute('onchange').includes('this.form.submit()')) {
                    this.form.submit();
                }
            });
        });
    </script>

    <script>
        /**
         * LIVE CLOCK: Updates the header time and date display every second.
         */
        function updateClock() {
            const now = new Date();
            const timeEl = document.getElementById('header-time');
            const dateEl = document.getElementById('header-date');
            if (timeEl) {
                timeEl.textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            }
            if (dateEl) {
                dateEl.textContent = now.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
            }
        }
        updateClock();
        setInterval(updateClock, 1000);

        /**
         * NOTIFICATION BELL: Hides the red dot and notifies the server to mark as read.
         */
        function markAsRead() {
            const dot = document.getElementById('notif-red-dot');
            if (dot) dot.classList.add('d-none');

            // Call AJAX to update database - credentials required to send session cookie
            fetch('<?php echo BASE_URL; ?>ajax/mark_notif_read.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) console.warn('Notifications:', data.message);
                })
                .catch(err => console.warn('Notification bell error:', err));
        }
    </script>

    <script>
        /**
         * THEME TOGGLE LOGIC (JavaScript)
         * Handles the switching between Light and Dark mode.
         */
        
        // Selects the toggle switch input and the root <html> element
        const toggle = document.getElementById('darkModeToggle');
        const html = document.documentElement;

        // LOAD SAVED PREFERENCE: Retrieves the theme from browser storage or defaults to 'dark'
        const savedTheme = localStorage.getItem('sfims-theme') || 'dark';
        
        // Sets the data-theme attribute on the <html> tag to trigger the correct CSS variables
        html.setAttribute('data-theme', savedTheme);
        
        // Checks if the toggle switch exists on the current page
        if(toggle) {
            // Synchronizes the switch visual state (on/off) with the saved theme
            toggle.checked = (savedTheme === 'dark');
            
            // EVENT LISTENER: Listens for when the user clicks/toggles the switch
            toggle.addEventListener('change', () => {
                // Determines the new theme based on whether the checkbox is checked
                const newTheme = toggle.checked ? 'dark' : 'light';
                
                // Updates the <html> attribute immediately for a seamless transition
                html.setAttribute('data-theme', newTheme);
                html.setAttribute('data-bs-theme', newTheme);
                
                // PERSISTENCE: Saves the user's choice to local storage so it stays across page refreshes
                localStorage.setItem('sfims-theme', newTheme);
            });
        }

        /**
         * SIDEBAR TOGGLE LOGIC (JavaScript)
         * Handles the collapsing and expanding of the Mega-Sidebar.
         */
        const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
        const bodyEl = document.body;
        
        // LOAD SAVED PREFERENCE: Retrieves the sidebar state from browser storage
        const savedSidebarState = localStorage.getItem('sfims-sidebar-collapsed');
        
        // Initialize state on load
        if (savedSidebarState === 'true') {
            bodyEl.classList.add('sidebar-collapsed');
        }

        // EVENT LISTENER: Listens for clicks on the hamburger menu icon
        if (sidebarToggleBtn) {
            sidebarToggleBtn.addEventListener('click', () => {
                bodyEl.classList.toggle('sidebar-collapsed');
                const isCollapsed = bodyEl.classList.contains('sidebar-collapsed');
                localStorage.setItem('sfims-sidebar-collapsed', isCollapsed);
            });
        }

        /**
         * SIDEBAR SCROLL PERSISTENCE
         * Remembers how far down the user scrolled the sidebar, so it doesn't jump to the top on page reloads.
         */
        const sidebarEl = document.querySelector('.sidebar');
        if (sidebarEl) {
            // Restore scroll position on load
            const savedScroll = localStorage.getItem('sfims-sidebar-scroll');
            if (savedScroll) {
                sidebarEl.scrollTop = savedScroll;
            }
            // Save scroll position on scroll
            sidebarEl.addEventListener('scroll', () => {
                localStorage.setItem('sfims-sidebar-scroll', sidebarEl.scrollTop);
            });
        }

    </script>
</body>
</html>
