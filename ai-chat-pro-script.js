document.addEventListener("DOMContentLoaded", () => {
    const { __ } = wp.i18n; // Para traducciones en JS

    const chatBubble = document.getElementById("ai-chat-pro-bubble");
    const chatWidget = document.getElementById("ai-chat-pro-widget");
    const closeButton = document.getElementById("ai-chat-pro-close-btn");
    const chatMessagesContainer = document.getElementById("ai-chat-messages-pro");
    const chatInput = document.getElementById("ai-chat-input-pro");
    const sendButton = document.getElementById("ai-chat-send-button-pro");
    const widgetTitle = document.getElementById("ai-chat-pro-widget-title");
    const unreadBadge = chatBubble?.querySelector(".ai-chat-pro-unread-badge");

    if (!chatBubble || !chatWidget || !closeButton || !chatMessagesContainer || !chatInput || !sendButton || !widgetTitle) {
        console.warn("AI Chat Pro: Elementos del chat flotante no encontrados.");
        return;
    }

    // Configurar textos iniciales desde aiChatPro (localizado)
    chatBubble.innerHTML = aiChatPro.bubble_svg_icon + chatBubble.innerHTML; // Mantener el badge
    widgetTitle.textContent = aiChatPro.chat_title;
    chatInput.placeholder = aiChatPro.input_placeholder;
    sendButton.textContent = aiChatPro.send_button_text;
    chatBubble.setAttribute('aria-label', __('Abrir chat con ', 'ai-chat-pro') + aiChatPro.chat_title);
    closeButton.setAttribute('aria-label', __('Cerrar chat con ', 'ai-chat-pro') + aiChatPro.chat_title);

    let currentThinkingMessageDiv = null;
    let isChatOpen = aiChatPro.start_opened;
    let unreadCount = 0;

    function updateUnreadBadge() {
        if (unreadBadge) {
            if (unreadCount > 0 && !isChatOpen) {
                unreadBadge.textContent = unreadCount > 9 ? '9+' : unreadCount;
                unreadBadge.style.display = 'flex';
                chatBubble.classList.add('has-unread');
            } else {
                unreadBadge.style.display = 'none';
                chatBubble.classList.remove('has-unread');
                if (isChatOpen) unreadCount = 0; // Reset al abrir
            }
        }
    }
    
    function toggleChatWidget(forceOpen = null) {
        const open = forceOpen !== null ? forceOpen : !chatWidget.classList.contains("active");
        if (open) {
            chatWidget.classList.add("active");
            chatWidget.setAttribute('aria-hidden', 'false');
            isChatOpen = true;
            unreadCount = 0;
            
            // En móvil, ajustar inmediatamente para el teclado
            if (window.innerWidth <= 768) {
                setTimeout(() => {
                    adjustChatForKeyboard(true);
                    chatInput.focus();
                }, 100);
            } else {
                chatInput.focus();
            }
        } else {
            chatWidget.classList.remove("active");
            chatWidget.setAttribute('aria-hidden', 'true');
            isChatOpen = false;
            
            // Restaurar estado cuando se cierra
            if (window.innerWidth <= 768) {
                isKeyboardVisible = false;
                adjustChatForKeyboard(false);
            }
        }
        updateUnreadBadge();
    }

    chatBubble.addEventListener("click", () => toggleChatWidget());
    chatBubble.addEventListener("keydown", (e) => { 
        if(e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            toggleChatWidget();
        }
    });
    closeButton.addEventListener("click", () => toggleChatWidget(false));

    if (aiChatPro.start_opened) {
        isChatOpen = true;
        setTimeout(() => chatInput.focus(), 350); // Para transición
    }
    updateUnreadBadge();

    // Función para sanitizar y formatear texto con soporte para negritas, enlaces y saltos de línea
    function formatMessageText(text) {
        // Escapar HTML básico primero
        const div = document.createElement('div');
        div.textContent = text;
        let escapedText = div.innerHTML;
        
        // Convertir saltos de línea a <br>
        escapedText = escapedText.replace(/\n/g, '<br>');
        
        // Convertir **texto** a <strong>texto</strong>
        escapedText = escapedText.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        
        // Convertir *texto* a <em>texto</em> (cursiva)
        escapedText = escapedText.replace(/\*([^*\n]+)\*/g, '<em>$1</em>');
        
        // Convertir `código` a <code>código</code>
        escapedText = escapedText.replace(/`([^`\n]+)`/g, '<code>$1</code>');
        
        // Convertir ```bloque de código``` a <pre><code>bloque de código</code></pre>
        escapedText = escapedText.replace(/```([\s\S]*?)```/g, '<pre><code>$1</code></pre>');
        
        // Convertir URLs a enlaces clicables (protocolo requerido)
        escapedText = escapedText.replace(
            /(https?:\/\/[^\s<>"']+)/gi,
            '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>'
        );
        
        // Convertir [texto](url) a enlaces
        escapedText = escapedText.replace(
            /\[([^\]]+)\]\((https?:\/\/[^\s<>"']+)\)/gi,
            '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>'
        );
        
        // Convertir listas con - o * al inicio de línea
        escapedText = escapedText.replace(/^[\-\*]\s+(.+)$/gm, '<li>$1</li>');
        
        // Envolver listas consecutivas en <ul>
        escapedText = escapedText.replace(/(<li>.*<\/li>)/gs, function(match) {
            return '<ul>' + match + '</ul>';
        });
        
        // Limpiar <ul> anidados
        escapedText = escapedText.replace(/<\/ul>\s*<ul>/g, '');
        
        return escapedText;
    }

    function appendMessage(text, sender, isThinking = false) {
        const wrapper = document.createElement("div");
        wrapper.classList.add("message-wrapper", sender === aiChatPro.user_label ? "user-message" : "ia-message");
        
        const contentDiv = document.createElement("div");
        contentDiv.classList.add("message-content");

        if (isThinking) {
            wrapper.classList.add("thinking");
            contentDiv.textContent = text; // Solo el texto para "pensando"
        } else {
            if (sender === aiChatPro.ai_label) {
                const senderStrong = document.createElement("strong");
                senderStrong.textContent = sender + ":";
                contentDiv.appendChild(senderStrong);
                
                const messageText = document.createElement("span");
                messageText.innerHTML = " " + formatMessageText(text);
                contentDiv.appendChild(messageText);
            } else {
                // Para mensajes del usuario, mantener texto plano
                contentDiv.textContent = text;
            }
        }
        
        wrapper.appendChild(contentDiv);
        chatMessagesContainer.appendChild(wrapper);
        chatMessagesContainer.scrollTop = chatMessagesContainer.scrollHeight;

        if (isThinking) {
            currentThinkingMessageDiv = wrapper;
        } else if (sender === aiChatPro.ai_label && !isChatOpen) {
            unreadCount++;
            updateUnreadBadge();
        }
        return wrapper;
    }

    function removeThinkingMessage() {
        if (currentThinkingMessageDiv && currentThinkingMessageDiv.parentNode === chatMessagesContainer) {
            chatMessagesContainer.removeChild(currentThinkingMessageDiv);
            currentThinkingMessageDiv = null;
        }
    }

    function loadChatHistory() {
        let history = JSON.parse(localStorage.getItem("ai_chat_pro_history")) || [];
        const threadId = localStorage.getItem("ai_chat_pro_thread_id");

        if (history.length > 0 && threadId) {
            history.forEach(item => {
                // Si el último mensaje guardado fue "pensando", no lo mostramos al cargar
                if (item.text === aiChatPro.thinking || item.text === aiChatPro.thinking_saved_text) {
                    return; 
                }
                appendMessage(item.text, item.sender);
            });
        } else {
            appendMessage(aiChatPro.initial_greeting, aiChatPro.ai_label);
            saveMessageToHistory(aiChatPro.initial_greeting, aiChatPro.ai_label);
        }
    }
    
    function saveMessageToHistory(text, sender) {
        let history = JSON.parse(localStorage.getItem("ai_chat_pro_history")) || [];
        
        // Si el nuevo mensaje es "pensando", y el último también lo era, no añadir duplicado.
        if (isThinkingMessage(text) && history.length > 0 && isThinkingMessage(history[history.length-1].text) && history[history.length-1].sender === aiChatPro.ai_label) {
            return; 
        }
        history.push({ sender, text });
        while (history.length > aiChatPro.max_history_items) {
            history.shift();
        }
        localStorage.setItem("ai_chat_pro_history", JSON.stringify(history));
    }

    function isThinkingMessage(text) {
        return text === aiChatPro.thinking || text === aiChatPro.thinking_saved_text;
    }

    // Función para asegurar que la burbuja sea visible
    function ensureBubbleVisibility() {
        if (chatBubble && window.innerWidth <= 768) {
            // Forzar la visibilidad de la burbuja
            chatBubble.style.visibility = 'visible';
            chatBubble.style.opacity = '1';
            chatBubble.style.transform = 'none';
            chatBubble.style.position = 'fixed';
            chatBubble.style.bottom = '15px';
            chatBubble.style.right = '15px';
            chatBubble.style.zIndex = '10001';
        }
    }

    // Cargar historial
    loadChatHistory();
    
    // Detectar cambios en el viewport para ajustar el chat cuando aparece el teclado virtual
    let initialViewportHeight = window.innerHeight;
    let isKeyboardVisible = false;
    
    function handleViewportChange() {
        const currentHeight = window.innerHeight;
        const heightDifference = initialViewportHeight - currentHeight;
        
        // Si la altura se reduce significativamente, probablemente apareció el teclado
        if (heightDifference > 150 && !isKeyboardVisible) {
            isKeyboardVisible = true;
            adjustChatForKeyboard(true);
        } else if (heightDifference < 100 && isKeyboardVisible) {
            isKeyboardVisible = false;
            adjustChatForKeyboard(false);
        }
    }
    
    function adjustChatForKeyboard(keyboardVisible) {
        if (window.innerWidth <= 768) { // Solo en móvil
            if (keyboardVisible) {
                const availableHeight = window.innerHeight;
                chatWidget.style.height = `${availableHeight - 20}px`;
                chatWidget.style.maxHeight = `${availableHeight - 20}px`;
                chatWidget.style.top = '10px';
                chatWidget.style.bottom = 'auto';
                chatWidget.style.position = 'fixed';
                chatMessagesContainer.style.paddingBottom = '90px';
                chatMessagesContainer.style.maxHeight = `${availableHeight - 150}px`;
                
                // Asegurar que el input area esté visible
                const inputArea = document.getElementById('ai-chat-pro-input-area');
                if (inputArea) {
                    inputArea.style.position = 'absolute';
                    inputArea.style.bottom = '0';
                    inputArea.style.left = '0';
                    inputArea.style.right = '0';
                    inputArea.style.zIndex = '20';
                }
            } else {
                // Restaurar estilos originales
                chatWidget.style.height = '';
                chatWidget.style.maxHeight = '';
                chatWidget.style.top = '';
                chatWidget.style.bottom = '';
                chatWidget.style.position = '';
                chatMessagesContainer.style.paddingBottom = '';
                chatMessagesContainer.style.maxHeight = '';
                
                const inputArea = document.getElementById('ai-chat-pro-input-area');
                if (inputArea) {
                    inputArea.style.position = '';
                    inputArea.style.bottom = '';
                    inputArea.style.left = '';
                    inputArea.style.right = '';
                    inputArea.style.zIndex = '';
                }
            }
        }
    }
    
    // Usar Visual Viewport API si está disponible (mejor para iOS)
    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', () => {
            const currentHeight = window.visualViewport.height;
            const screenHeight = window.screen.height;
            const heightDifference = screenHeight - currentHeight;
            
            if (heightDifference > 150 && !isKeyboardVisible && isChatOpen) {
                isKeyboardVisible = true;
                adjustChatForKeyboard(true);
            } else if (heightDifference < 100 && isKeyboardVisible && isChatOpen) {
                isKeyboardVisible = false;
                adjustChatForKeyboard(false);
                // Asegurar que la burbuja sea visible después de ocultar el teclado
                ensureBubbleVisibility();
            }
        });
    } else {
        // Fallback para navegadores que no soportan Visual Viewport API
        window.addEventListener('resize', handleViewportChange);
    }
    
    window.addEventListener('orientationchange', () => {
        setTimeout(() => {
            initialViewportHeight = window.innerHeight;
            isKeyboardVisible = false;
            adjustChatForKeyboard(false);
        }, 500);
    });
    
    // Detectar cuando el input recibe focus (teclado aparece)
    chatInput.addEventListener('focus', () => {
        setTimeout(() => {
            if (window.innerWidth <= 768) {
                adjustChatForKeyboard(true);
                // Scroll para asegurar que el input sea visible
                setTimeout(() => {
                    chatInput.scrollIntoView({ behavior: 'smooth', block: 'end' });
                }, 100);
            }
        }, 300);
    });
    
    // Detectar cuando el input pierde focus (teclado se oculta)
    chatInput.addEventListener('blur', () => {
        setTimeout(() => {
            if (window.innerWidth <= 768 && !chatInput.matches(':focus')) {
                // Solo ajustar si el chat sigue abierto
                if (isChatOpen) {
                    isKeyboardVisible = false;
                    adjustChatForKeyboard(false);
                }
            }
        }, 300);
    });
    
    // Deshabilitar botón de enviar mientras se espera respuesta
    function setSendingState(isSending) {
        chatInput.disabled = isSending;
        sendButton.disabled = isSending;
        if (isSending) {
            sendButton.textContent = __('Enviando...', 'ai-chat-pro');
        } else {
            sendButton.textContent = aiChatPro.send_button_text;
        }
    }

    async function sendMessagePro() {
        const msg = chatInput.value.trim();
        if (!msg) return;

        setSendingState(true);
        appendMessage(msg, aiChatPro.user_label);
        saveMessageToHistory(msg, aiChatPro.user_label);
        chatInput.value = "";

        removeThinkingMessage(); // Quitar cualquier "pensando" viejo
        appendMessage(aiChatPro.thinking, aiChatPro.ai_label, true); // Nuevo "pensando"

        let thread_id = localStorage.getItem("ai_chat_pro_thread_id");
        let count = parseInt(localStorage.getItem("ai_chat_pro_message_count") || "0", 10);
        const today = new Date().toISOString().slice(0, 10);
        const lastMessageDate = localStorage.getItem("ai_chat_pro_last_message_date");

        if (lastMessageDate !== today) {
            count = 0; // Reset count for a new day
            localStorage.setItem("ai_chat_pro_message_count", "0");
            localStorage.setItem("ai_chat_pro_last_message_date", today);
        }

        if (count >= aiChatPro.message_limit_count) {
            removeThinkingMessage();
            appendMessage(aiChatPro.limit_exceeded, aiChatPro.ai_label);
            saveMessageToHistory(aiChatPro.limit_exceeded, aiChatPro.ai_label);
            setSendingState(false);
            return;
        }

        try {
            const res = await fetch(aiChatPro.rest_url_message, {
                method: "POST",
                headers: { 
                    "Content-Type": "application/json", 
                    "X-WP-Nonce": aiChatPro.nonce 
                },
                body: JSON.stringify({ message: msg, thread_id: thread_id })
            });

            if (!res.ok) {
                const errorData = await res.json().catch(() => ({ message: res.statusText }));
                throw new Error(errorData.message || `HTTP error ${res.status}`);
            }
            const data = await res.json();
            
            localStorage.setItem("ai_chat_pro_message_count", (count + 1).toString());
            localStorage.setItem("ai_chat_pro_last_message_date", today);
            if (data.thread_id) localStorage.setItem("ai_chat_pro_thread_id", data.thread_id);

            if (data.reply) { // Respuesta directa
                removeThinkingMessage();
                appendMessage(data.reply, aiChatPro.ai_label);
                saveMessageToHistory(data.reply, aiChatPro.ai_label);
                setSendingState(false);
            } else if (data.run_id && data.thread_id) {
                // Ahora sí guardamos el "pensando" porque el run inició
                saveMessageToHistory(aiChatPro.thinking_saved_text, aiChatPro.ai_label);
                pollForResponse(data.thread_id, data.run_id);
            } else {
                 throw new Error(data.message || __("Respuesta inesperada del servidor.", 'ai-chat-pro'));
            }

        } catch (error) {
            removeThinkingMessage();
            appendMessage(aiChatPro.error_prefix + error.message, aiChatPro.ai_label);
            saveMessageToHistory(aiChatPro.error_prefix + error.message, aiChatPro.ai_label);
            setSendingState(false);
        }
    }

    async function pollForResponse(thread_id, run_id) {
        let attempts = 0;
        const maxAttempts = 15; // ~30 segundos

        const checkStatus = async () => {
            if (attempts >= maxAttempts) {
                removeThinkingMessage();
                // Limpiar "pensando" del historial si estaba
                let history = JSON.parse(localStorage.getItem("ai_chat_pro_history")) || [];
                if (history.length > 0 && isThinkingMessage(history[history.length-1].text)) { 
                    history.pop(); 
                }
                
                const timeoutError = __("La IA tardó demasiado en responder.", 'ai-chat-pro');
                appendMessage(aiChatPro.error_prefix + timeoutError, aiChatPro.ai_label);
                history.push({ sender: aiChatPro.ai_label, text: aiChatPro.error_prefix + timeoutError });
                localStorage.setItem("ai_chat_pro_history", JSON.stringify(history));
                setSendingState(false);
                return;
            }
            attempts++;

            try {
                const runCheck = await fetch(aiChatPro.rest_url_check, {
                    method: "POST",
                    headers: { 
                        "Content-Type": "application/json", 
                        "X-WP-Nonce": aiChatPro.nonce 
                    },
                    body: JSON.stringify({ thread_id: thread_id, run_id: run_id })
                });

                if (!runCheck.ok) {
                    const errorData = await runCheck.json().catch(() => ({ message: runCheck.statusText }));
                    throw new Error(errorData.message || `HTTP error ${runCheck.status}`);
                }
                
                const result = await runCheck.json();
                let history = JSON.parse(localStorage.getItem("ai_chat_pro_history")) || [];
                // Siempre quitar el mensaje "pensando" del historial antes de añadir la respuesta final
                if (history.length > 0 && isThinkingMessage(history[history.length-1].text)) {
                    history.pop();
                }

                if (result.status === "completed") {
                    removeThinkingMessage();
                    appendMessage(result.reply, aiChatPro.ai_label);
                    history.push({ sender: aiChatPro.ai_label, text: result.reply });
                    setSendingState(false);
                } else if (result.status === "failed" || result.status === "cancelled" || result.status === "expired") {
                    removeThinkingMessage();
                    const failMessage = result.error_message || __(`El proceso de la IA falló (estado: ${result.status}).`, 'ai-chat-pro');
                    appendMessage(aiChatPro.error_prefix + failMessage, aiChatPro.ai_label);
                    history.push({ sender: aiChatPro.ai_label, text: aiChatPro.error_prefix + failMessage });
                    setSendingState(false);
                } else { // queued, in_progress
                    setTimeout(checkStatus, 2000);
                    return; // Importante para no ejecutar el save de historial de abajo prematuramente
                }
                localStorage.setItem("ai_chat_pro_history", JSON.stringify(history));

            } catch (error) {
                removeThinkingMessage();
                let history = JSON.parse(localStorage.getItem("ai_chat_pro_history")) || [];
                if (history.length > 0 && isThinkingMessage(history[history.length-1].text)) { 
                    history.pop(); 
                }
                
                appendMessage(aiChatPro.error_prefix + error.message, aiChatPro.ai_label);
                history.push({ sender: aiChatPro.ai_label, text: aiChatPro.error_prefix + error.message });
                localStorage.setItem("ai_chat_pro_history", JSON.stringify(history));
                setSendingState(false);
            }
        };
        checkStatus();
    }

    sendButton.addEventListener("click", sendMessagePro);
    chatInput.addEventListener("keypress", function(event) {
        if (event.key === "Enter" && !sendButton.disabled) {
            event.preventDefault();
            sendMessagePro();
        }
    });
});
