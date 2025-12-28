<?php
/**
 * footer.php - Shared Layout Footer
 * This file closes the structural tags (container, main-content, body) opened in header.php.
 */
?>
        </div> <!-- End of .container -->
    </main> <!-- End of .main-content -->
    
    <!-- Global Scripts -->
    <script>
        // Simple logic for future dropdowns or mobile menu toggles
        document.addEventListener('DOMContentLoaded', function() {
            const profileBtn = document.querySelector('.profile-btn');
            if (profileBtn) {
                profileBtn.addEventListener('click', function() {
                    // Placeholder for profile menu logic
                    console.log('Profile menu clicked');
                });
            }
        });
    </script>
</body>
</html>