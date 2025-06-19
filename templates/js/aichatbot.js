(function (Drupal, drupalSettings) {
  Drupal.behaviors.aichatbot = {
    attach: function (context) {
      const chatbotEl = once('aichatbot-init', '#aichatbot', context).shift();
      const launcherEl = once('aichatbot-launch', '.aichatbot-launch-link', context).shift();

      if (!chatbotEl) return;

      // Move to body so position: fixed works
      document.body.appendChild(chatbotEl);

      const messages = chatbotEl.querySelector('.aichatbot-messages');
      const input = chatbotEl.querySelector('.aichatbot-input');
      const send = chatbotEl.querySelector('.aichatbot-send');
      const reset = chatbotEl.querySelector('.aichatbot-reset');
      const closeBtn = chatbotEl.querySelector('.aichatbot-close');

      function appendMsg(role, text) {
        const div = document.createElement('div');
        div.className = 'aichatbot-msg ' + role;
        div.innerHTML = (role === 'user')
          ? `<strong>You:</strong><br>${text}`
          : `<strong>${drupalSettings.aichatbot.agentName}:</strong><br>${text}`;
        messages.appendChild(div);
        messages.scrollTop = messages.scrollHeight;
      }

      // Open chatbot
      if (launcherEl) {
        launcherEl.addEventListener('click', (e) => {
          e.preventDefault();
          chatbotEl.classList.remove('collapsed');
        });
      }

      // Close chatbot
      if (closeBtn) {
        closeBtn.addEventListener('click', () => {
          chatbotEl.classList.add('collapsed');
        });
      }

      // Load history
      fetch(drupalSettings.aichatbot.historyUrl)
        .then(resp => resp.json())
        .then(json => {
          json.history.forEach(item => {
            appendMsg('user', item.q);
            appendMsg('bot', item.a);
          });
        });

      function sendMessage() {
        const q = input.value.trim();
        if (!q) return;
        appendMsg('user', q);
        input.value = '';
        fetch(drupalSettings.aichatbot.chatUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ question: q }),
        })
          .then(resp => resp.json())
          .then(json => {
            appendMsg('bot', json.answer || json.error);
          });
      }

      send.addEventListener('click', sendMessage);

      input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
          event.preventDefault();
          sendMessage();
        }
      });

      reset.addEventListener('click', () => {
        fetch(drupalSettings.aichatbot.resetUrl, { method: 'POST' })
          .then(() => {
            messages.innerHTML = '';
            input.value = '';
          });
      });
    }
  };
})(Drupal, drupalSettings);
