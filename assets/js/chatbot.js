// Toggle chat widget visibility
function toggleChat() {
  const chatWidget = document.getElementById('chat-widget');
  const currentDisplay = chatWidget.style.display;
  
  if (currentDisplay === 'none' || currentDisplay === '') {
    chatWidget.style.display = 'flex';
    
    // Show welcome message only on first open
    const chatBody = document.getElementById('chat-body');
    if (!chatBody.hasAttribute('data-initialized')) {
      showWelcomeMessage();
      chatBody.setAttribute('data-initialized', 'true');
    }
  } else {
    chatWidget.style.display = 'none';
  }
}

// Display welcome message
function showWelcomeMessage() {
  const welcomeMessages = [
    "Hello! 👋 Welcome to ToyWorld!",
    "I'm your ToyWorld Assistant, what can I help you with today? 😊"
  ];
  
  const chatBody = document.getElementById('chat-body');
  
  // Add each welcome message with a slight delay for natural feel
  welcomeMessages.forEach((msg, index) => {
    setTimeout(() => {
      const messageDiv = document.createElement('div');
      messageDiv.className = 'msg bot';
      
      if (msg === '') {
        // Empty line for spacing
        messageDiv.style.height = '5px';
        messageDiv.style.background = 'transparent';
        messageDiv.style.boxShadow = 'none';
      } else {
        messageDiv.innerHTML = msg;
      }
      
      chatBody.appendChild(messageDiv);
      chatBody.scrollTop = chatBody.scrollHeight;
    }, index * 200); // 200ms delay between each message
  });
}

// Send message to chatbot
function sendMessage() {
  const input = document.getElementById('message');
  const message = input.value.trim();
  
  if (message === '') return;
  
  // Display user message
  appendMessage(message, 'user');
  input.value = '';
  
  // Send message to server and get bot reply
  fetch('../chatbot.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: 'message=' + encodeURIComponent(message)
  })
  .then(response => response.text())
  .then(reply => {
    // Display bot reply
    setTimeout(() => {
      appendMessage(reply, 'bot');
    }, 500);
  })
  .catch(error => {
    console.error('Error:', error);
    appendMessage('Sorry, something went wrong. Please try again.', 'bot');
  });
}

// Append message to chat body
function appendMessage(text, sender) {
  const chatBody = document.getElementById('chat-body');
  const messageDiv = document.createElement('div');
  messageDiv.className = 'msg ' + sender;
  
  // Use innerHTML for bot messages to allow clickable links
  if (sender === 'bot') {
    messageDiv.innerHTML = text;
  } else {
    messageDiv.textContent = text;
  }
  
  chatBody.appendChild(messageDiv);
  
  // Auto-scroll to bottom
  chatBody.scrollTop = chatBody.scrollHeight;
}

// Allow Enter key to send message
document.addEventListener('DOMContentLoaded', function() {
  const input = document.getElementById('message');
  if (input) {
    input.addEventListener('keypress', function(e) {
      if (e.key === 'Enter') {
        sendMessage();
      }
    });
  }
});