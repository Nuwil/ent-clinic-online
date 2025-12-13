            </main>
        </div>
    </div>

    <script>
        // Sidebar Toggle Functionality
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const appWrapper = document.querySelector('.app-wrapper');

        function toggleSidebar() {
            sidebar.classList.toggle('collapsed');
            appWrapper.classList.toggle('sidebar-collapsed');
            // Persist collapsed state across reloads
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed') ? '1' : '0');
            if (sidebarToggle) sidebarToggle.setAttribute('aria-expanded', sidebar.classList.contains('collapsed') ? 'false' : 'true');
        }

        function toggleMobileMenu() {
            sidebar.classList.toggle('mobile-open');
        }

        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', toggleSidebar);
            // Reflect initial state
            sidebarToggle.setAttribute('aria-expanded', sidebar.classList.contains('collapsed') ? 'false' : 'true');
        }

        // Apply persisted sidebar state on load
        if (localStorage.getItem('sidebarCollapsed') === '1') {
            sidebar.classList.add('collapsed');
            appWrapper.classList.add('sidebar-collapsed');
            if (sidebarToggle) sidebarToggle.setAttribute('aria-expanded', 'false');
        }

        if (mobileMenuToggle) {
            mobileMenuToggle.addEventListener('click', toggleMobileMenu);
        }

        // Close mobile menu when clicking outside
        document.addEventListener('click', function(event) {
            if (window.innerWidth <= 768) {
                // guard mobileMenuToggle in case it's not present
                if (!sidebar.contains(event.target) && (!mobileMenuToggle || !mobileMenuToggle.contains(event.target))) {
                    sidebar.classList.remove('mobile-open');
                }
            }
        });

        // Sidebar user dropdown toggle
        const sidebarUserAvatar = document.getElementById('sidebarUserAvatar');
        const userDropdown = document.getElementById('userDropdown');
        if (sidebarUserAvatar && userDropdown) {
            sidebarUserAvatar.addEventListener('click', function(e) {
                e.stopPropagation();
                userDropdown.style.display = userDropdown.style.display === 'none' ? 'block' : 'none';
            });
            // close when clicking outside
            document.addEventListener('click', function(ev) {
                if (!sidebarUserAvatar.contains(ev.target) && !userDropdown.contains(ev.target)) {
                    userDropdown.style.display = 'none';
                }
            });
        }

        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768) {
                sidebar.classList.remove('mobile-open');
            }
        });

        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 300);
            });
        }, 5000);
    </script>
</body>
</html>
