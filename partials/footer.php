</main>
    </div>

    <footer class="footer mt-auto py-3 border-top" style="background-color: var(--header-dark) !important; border-bottom: none !important;">
        <div class="container text-center">
            <span class="small" style="color: var(--footer-text) !important; font-weight: 500;">
                &copy; <?php echo date('Y'); ?> SFIMS - Pamantasan ng lungsod ng muntinlupa
            </span>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

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
                
                // PERSISTENCE: Saves the user's choice to local storage so it stays across page refreshes
                localStorage.setItem('sfims-theme', newTheme);
            });
        }

    </script>
</body>
</html>
