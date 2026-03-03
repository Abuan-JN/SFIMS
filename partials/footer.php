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
        const toggle = document.getElementById('darkModeToggle');
        const html = document.documentElement;

        // Load saved preference
        const savedTheme = localStorage.getItem('sfims-theme') || 'dark';
        html.setAttribute('data-theme', savedTheme);
        
        if(toggle) {
            toggle.checked = (savedTheme === 'dark');
            toggle.addEventListener('change', () => {
                const newTheme = toggle.checked ? 'dark' : 'light';
                html.setAttribute('data-theme', newTheme);
                localStorage.setItem('sfims-theme', newTheme);
            });
        }


    </script>
</body>
</html>
