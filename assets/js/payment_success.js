// Print receipt function
function printReceipt() {
    window.print();
}

// Prevent browser back button after successful payment
// This prevents users from going back to payment/checkout pages
if (window.history && window.history.pushState) {
    // Replace current history entry
    window.history.replaceState(null, null, window.location.href);
    
    // Push a new state
    window.history.pushState(null, null, window.location.href);
    
    // Listen for back button
    window.addEventListener('popstate', function(event) {
        // Redirect to home page instead of going back
        window.location.href = '../../index.php';
    });
}

// Auto-redirect to home page after 60 seconds
let countdown = 60;
const countdownElement = document.createElement('div');
countdownElement.style.cssText = `
    position: fixed;
    top: 20px;
    right: 20px;
    background: rgba(0, 0, 0, 0.8);
    color: white;
    padding: 10px 15px;
    border-radius: 5px;
    font-size: 14px;
    z-index: 1000;
    cursor: pointer;
`;
countdownElement.textContent = `Redirecting to home in ${countdown}s`;
countdownElement.title = 'Click to cancel auto-redirect';
document.body.appendChild(countdownElement);

const countdownTimer = setInterval(() => {
    countdown--;
    countdownElement.textContent = `Redirecting to home in ${countdown}s`;
    
    if (countdown <= 0) {
        clearInterval(countdownTimer);
        countdownElement.textContent = 'Redirecting...';
        window.location.href = '../../index.php';
    }
}, 1000);

// Allow user to cancel auto-redirect by clicking the countdown
countdownElement.addEventListener('click', () => {
    clearInterval(countdownTimer);
    countdownElement.textContent = 'Auto-redirect cancelled';
    setTimeout(() => {
        countdownElement.remove();
    }, 2000);
});

// Clear buy_now session if it exists (cleanup)
fetch('../cart/cart_actions.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: 'action=clear_buy_now_session'
}).catch(() => {
    // Silently fail if cleanup doesn't work
});