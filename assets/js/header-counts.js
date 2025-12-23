/**
 * Header Counts Management
 * Handles loading and updating cart and wishlist counts in the header
 * Uses vanilla JavaScript (no jQuery dependency)
 */

(function () {
    'use strict';

    // Wait for DOM to be ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    function init() {
        // Load counts on page load if user is logged in
        const wishlistCount = document.querySelector('.wishlist-count');
        const cartCount = document.querySelector('.cart-count');

        if (wishlistCount || cartCount) {
            loadHeaderCounts();
        }
    }

    /**
     * Load cart and wishlist counts from server
     */
    function loadHeaderCounts() {
        fetch('/pages/cart/get_cart_count.php', {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateCartCount(data.cart_count || 0);
                    updateWishlistCount(data.wishlist_count || 0);
                }
            })
            .catch(error => {
                // Silently fail - counts will remain at 0
                console.warn('Failed to load header counts:', error);
            });
    }

    /**
     * Update cart count in header
     * @param {number} count - The cart count to display
     */
    function updateCartCount(count) {
        const cartCountElements = document.querySelectorAll('.cart-count');

        cartCountElements.forEach(element => {
            const oldCount = parseInt(element.textContent) || 0;
            element.textContent = count;

            // Add pulse animation if count changed
            if (oldCount !== count) {
                element.classList.add('pulse');
                setTimeout(() => element.classList.remove('pulse'), 500);
            }
        });
    }

    /**
     * Update wishlist count in header
     * @param {number} count - The wishlist count to display
     */
    function updateWishlistCount(count) {
        const wishlistCountElements = document.querySelectorAll('.wishlist-count');

        wishlistCountElements.forEach(element => {
            const oldCount = parseInt(element.textContent) || 0;
            element.textContent = count;

            // Add pulse animation if count changed
            if (oldCount !== count) {
                element.classList.add('pulse');
                setTimeout(() => element.classList.remove('pulse'), 500);
            }
        });
    }

    // Export functions to window for use in other scripts
    window.updateCartCount = updateCartCount;
    window.updateWishlistCount = updateWishlistCount;
    window.loadHeaderCounts = loadHeaderCounts;
})();
