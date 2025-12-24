// Prevent browser back button after payment cancellation
if (window.history && window.history.pushState) {
    window.history.replaceState(null, null, window.location.href);
    window.history.pushState(null, null, window.location.href);
    
    window.addEventListener('popstate', function(event) {
        // Redirect to home page instead of going back to payment
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
`;
countdownElement.textContent = `Redirecting to home in ${countdown}s`;
document.body.appendChild(countdownElement);

const countdownTimer = setInterval(() => {
    countdown--;
    countdownElement.textContent = `Redirecting to home in ${countdown}s`;
    
    if (countdown <= 0) {
        clearInterval(countdownTimer);
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

countdownElement.title = 'Click to cancel auto-redirect';
countdownElement.style.cursor = 'pointer';