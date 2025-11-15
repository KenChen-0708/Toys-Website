function toggleChat() {
  const widget = document.getElementById("chat-widget");
  widget.style.display = widget.style.display === "flex" ? "none" : "flex";
}

function sendMessage() {
  let msg = document.getElementById("message").value;
  if (msg.trim() === "") return;

  let chatBody = document.getElementById("chat-body");
  chatBody.innerHTML += `<div class='msg user'>${msg}</div>`;

  fetch("chatbot.php", {
    method: "POST",
    headers: { "Content-Type": "application/x-www-form-urlencoded" },
    body: "message=" + encodeURIComponent(msg),
  })
    .then((res) => res.text())
    .then((reply) => {
      chatBody.innerHTML += `<div class='msg bot'>${reply}</div>`;
      chatBody.scrollTop = chatBody.scrollHeight;
    });

  document.getElementById("message").value = "";
}
