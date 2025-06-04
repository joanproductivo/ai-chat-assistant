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
    const chatHeader = document.getElementById("ai-chat-pro-header"); // Added
    const chatInputArea = document.getElementById("ai-chat-pro-input-area"); // Added

    if (!chatBubble || !chatWidget || !closeButton || !chatMessagesContainer || !chatInput || !sendButton || !widgetTitle || !chatHeader || !chatInputArea) { // Added checks
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
    let autoOpenTriggered = false;
    let currentConfig = aiChatPro.auto_open_config || {};

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
            updateUnreadBadge(); // Update badge immediately on open

            if (window.innerWidth <= 768) {
                // Delay focus slightly to allow CSS transitions and keyboard adjustments
                setTimeout(() => {
                    // adjustChatForKeyboard(true); // This will be triggered by focus event on chatInput
                    chatInput.focus(); 
                }, 150); // Slightly longer delay
            } else {
                setTimeout(() => chatInput.focus(), 50); // Minimal delay for desktop
            }
        } else {
            chatWidget.classList.remove("active");
            chatWidget.setAttribute('aria-hidden', 'true');
            isChatOpen = false;
            
            // Restaurar estado cuando se cierra
            if (window.innerWidth <= 768) {
                isKeyboardVisible = false;
                adjustChatForKeyboard(false);
                // Asegurar que la burbuja sea visible después de cerrar el chat
                setTimeout(() => {
                    ensureBubbleVisibility();
                }, 100);
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
        escapedText = escapedText.replace(/(<li>.*<\/li>)/gs, (match) => {
            // Ensure each <li> is properly part of the match before wrapping in <ul>
            // This regex is broad, so ensure it's what we want.
            // If <li> items can be separated by <br>, this might wrap them separately.
            // For simple, contiguous lists, it's okay.
            return '<ul>' + match.replace(/<\/li>\s*<br\s*\/?>\s*<li>/g, '</li><li>') + '</ul>';
        });
        
        // Limpiar <ul> anidados o mal formados por el reemplazo anterior
        escapedText = escapedText.replace(/<\/ul>\s*<ul>/g, ''); // Combines adjacent lists
        
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

    // Función mejorada para normalizar URLs
    function normalizeUrl(url) {
        try {
            const urlObj = new URL(url);
            
            // Si la normalización está habilitada, remover parámetros de query y hash
            if (currentConfig.normalize_urls) {
                return urlObj.origin + urlObj.pathname;
            }
            
            return url;
        } catch (e) {
            // Si la URL no es válida, devolver tal como está
            return url;
        }
    }

    // Función para obtener el timestamp del día actual
    function getCurrentDayTimestamp() {
        const now = new Date();
        const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        return Math.floor(today.getTime() / 1000);
    }

    // Función para detectar si es una recarga de página
    function isPageReload() {
        // Verificar si la página se cargó desde cache del navegador
        if (performance.navigation && performance.navigation.type === performance.navigation.TYPE_RELOAD) {
            return true;
        }
        
        // Verificar usando Performance API moderna
        if (performance.getEntriesByType) {
            const navEntries = performance.getEntriesByType('navigation');
            if (navEntries.length > 0 && navEntries[0].type === 'reload') {
                return true;
            }
        }
        
        // Verificar usando sessionStorage como fallback
        const sessionKey = 'ai_chat_pro_page_loaded';
        const currentUrl = window.location.href;
        const lastLoadedUrl = sessionStorage.getItem(sessionKey);
        
        if (lastLoadedUrl === currentUrl) {
            return true;
        }
        
        sessionStorage.setItem(sessionKey, currentUrl);
        return false;
    }

    // Función para actualizar configuración desde el servidor
    async function updateConfigFromServer() {
        try {
            const response = await fetch(aiChatPro.rest_url_config, {
                method: 'GET',
                headers: {
                    'X-WP-Nonce': aiChatPro.nonce
                }
            });
            
            if (response.ok) {
                const data = await response.json();
                currentConfig = data.auto_open_config || currentConfig;
                return true;
            }
        } catch (error) {
            console.warn('AI Chat Pro: No se pudo actualizar la configuración desde el servidor:', error);
            return false; // Ensure false is returned on error
        }
        return false; // Default return if response not ok or other issues
    }

    // Función mejorada para manejar el seguimiento de páginas visitadas y apertura automática
    async function handleAutoOpenByPageViews() {
        // Verificar si la función está habilitada
        if (!currentConfig.enabled || autoOpenTriggered || isChatOpen) {
            return;
        }

        // Intentar actualizar configuración desde el servidor (compatible con cache)
        await updateConfigFromServer();

        // Verificar nuevamente después de la actualización
        if (!currentConfig.enabled) {
            return;
        }

        const storageKey = 'ai_chat_pro_page_visits_v2'; // Versión 2 para nueva estructura
        const currentUrl = normalizeUrl(window.location.href);
        const currentTime = Math.floor(Date.now() / 1000);
        const currentDay = getCurrentDayTimestamp();
        
        // Verificar si es una recarga de página y si debe excluirse
        if (currentConfig.exclude_reloads && isPageReload()) {
            return;
        }
        
        // Obtener datos de visitas del localStorage
        let visitData = JSON.parse(localStorage.getItem(storageKey)) || {
            day: currentDay,
            count: 0,
            urls: [],
            sessions: [],
            lastVisit: currentTime
        };

        // Si es un nuevo día y el reset diario está habilitado, resetear el contador
        if (currentConfig.reset_daily && visitData.day !== currentDay) {
            visitData = {
                day: currentDay,
                count: 0,
                urls: [],
                sessions: [],
                lastVisit: currentTime
            };
        }

        // Verificar timeout de sesión
        const sessionTimeoutSeconds = (currentConfig.session_timeout || 30) * 60;
        const timeSinceLastVisit = currentTime - (visitData.lastVisit || 0);
        
        // Si ha pasado mucho tiempo, considerar como nueva sesión
        if (timeSinceLastVisit > sessionTimeoutSeconds) {
            visitData.sessions.push({
                timestamp: currentTime,
                urls: []
            });
            
            // Mantener solo las últimas 10 sesiones
            if (visitData.sessions.length > 10) {
                visitData.sessions = visitData.sessions.slice(-10);
            }
        }

        // Obtener la sesión actual
        let currentSession = visitData.sessions[visitData.sessions.length - 1];
        if (!currentSession) {
            currentSession = {
                timestamp: currentTime,
                urls: []
            };
            visitData.sessions.push(currentSession);
        }

        // Solo contar si es una URL nueva en esta sesión
        const urlAlreadyVisitedInSession = currentSession.urls.some(urlData => urlData.url === currentUrl);
        
        if (!urlAlreadyVisitedInSession) {
            // Añadir URL a la sesión actual
            currentSession.urls.push({
                url: currentUrl,
                timestamp: currentTime
            });
            
            // Añadir a la lista global si no existe
            if (!visitData.urls.some(urlData => urlData.url === currentUrl)) {
                visitData.urls.push({
                    url: currentUrl,
                    firstVisit: currentTime,
                    lastVisit: currentTime,
                    count: 1
                });
            } else {
                // Actualizar datos de la URL existente
                const existingUrl = visitData.urls.find(urlData => urlData.url === currentUrl);
                existingUrl.lastVisit = currentTime;
                existingUrl.count++;
            }
            
            visitData.count++;
            visitData.lastVisit = currentTime;
            
            // Mantener solo las últimas 100 URLs para no sobrecargar el localStorage
            if (visitData.urls.length > 100) {
                visitData.urls = visitData.urls.slice(-100);
            }
            
            // Mantener solo las últimas 50 URLs por sesión
            if (currentSession.urls.length > 50) {
                currentSession.urls = currentSession.urls.slice(-50);
            }
            
            localStorage.setItem(storageKey, JSON.stringify(visitData));

            // Verificar si se debe abrir el chat automáticamente
            if (visitData.count >= currentConfig.pages_required) {
                autoOpenTriggered = true;
                
                // Marcar que se activó la apertura automática para esta sesión
                localStorage.setItem('ai_chat_pro_auto_opened', currentDay.toString());
                
                // Esperar un poco antes de abrir para que la página se cargue completamente
                setTimeout(() => {
                    if (!isChatOpen) {
                        toggleChatWidget(true);
                        
                        // Añadir mensaje de apertura automática si está configurado
                        setTimeout(() => {
                            // Asegurarse de que el chat esté realmente abierto y no haya mensajes de error o límite excedido
                            if (isChatOpen && chatMessagesContainer.children.length > 0) {
                                const lastMessageElement = chatMessagesContainer.lastElementChild;
                                // Solo añadir el mensaje automático si el último mensaje es el saludo inicial o no hay errores.
                                // Esto evita añadir el mensaje si el usuario ya interactuó o si hay un error.
                                const isInitialGreeting = lastMessageElement && lastMessageElement.textContent.includes(aiChatPro.initial_greeting);
                                const noErrorOrLimit = !lastMessageElement || (!lastMessageElement.textContent.includes(aiChatPro.error_prefix) && !lastMessageElement.textContent.includes(aiChatPro.limit_exceeded));

                                if (currentConfig.message_enabled && currentConfig.message_text && (isInitialGreeting || chatMessagesContainer.children.length === 1) && noErrorOrLimit) {
                                    // Verificar si el mensaje automático ya fue enviado para evitar duplicados
                                    let history = JSON.parse(localStorage.getItem("ai_chat_pro_history")) || [];
                                    const autoMessageAlreadySent = history.some(item => item.text === currentConfig.message_text && item.sender === aiChatPro.ai_label);

                                    if (!autoMessageAlreadySent) {
                                        appendMessage(currentConfig.message_text, aiChatPro.ai_label);
                                        saveMessageToHistory(currentConfig.message_text, aiChatPro.ai_label);
                                    }
                                }
                            }
                        }, 500);
                    }
                }, 2000);
            }
        } else {
            // Actualizar timestamp de última visita aunque no se cuente
            visitData.lastVisit = currentTime;
            localStorage.setItem(storageKey, JSON.stringify(visitData));
        }
    }

    // Función para verificar si ya se activó la apertura automática hoy
    function checkAutoOpenAlreadyTriggered() {
        const currentDay = getCurrentDayTimestamp();
        const lastAutoOpened = localStorage.getItem('ai_chat_pro_auto_opened');
        
        if (lastAutoOpened && parseInt(lastAutoOpened) === currentDay) {
            autoOpenTriggered = true;
        }
    }

    // Cargar historial
    loadChatHistory();
    
    // Verificar si ya se activó la apertura automática hoy
    checkAutoOpenAlreadyTriggered();
    
    // Inicializar seguimiento de páginas visitadas
    handleAutoOpenByPageViews();

    // Función para abrir el chat si la URL tiene el hash #abrirchat
    function checkUrlHashForChatOpen() {
        if (window.location.hash === "#abrirchat") {
            // Esperar un poco para asegurar que todo esté cargado
            setTimeout(() => {
                if (!isChatOpen) {
                    toggleChatWidget(true);
                }
            }, 500);
        }
    }
    
    // Comprobar al cargar la página y cuando cambie el hash
    checkUrlHashForChatOpen(); 
    window.addEventListener('hashchange', checkUrlHashForChatOpen, false);
    
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
            // Asegurar que la burbuja sea visible después de ocultar el teclado
            setTimeout(() => {
                ensureBubbleVisibility();
            }, 100);
        }
    }
    
    function adjustChatForKeyboard(keyboardVisible) {
        if (window.innerWidth <= 768 && isChatOpen) { // Solo en móvil y si el chat está abierto
            const bubbleHeight = chatBubble ? chatBubble.offsetHeight : 50;
            const bubbleMargin = 15;
            const spaceForBubble = bubbleHeight + bubbleMargin + 10; // Espacio si el widget se posiciona encima de la burbuja

            const headerHeight = chatHeader ? chatHeader.offsetHeight : 50; // Altura del header del chat
            const inputAreaHeight = chatInputArea ? chatInputArea.offsetHeight : 70; // Altura del área de input
            const verticalMargins = 20; // Suma de márgenes superior e inferior para el widget

            if (keyboardVisible) {
                // Usar visualViewport si está disponible, sino window.innerHeight
                const viewportHeight = window.visualViewport ? window.visualViewport.height : window.innerHeight;
                
                // La altura total que el widget puede ocupar
                const widgetMaxHeight = viewportHeight - verticalMargins;
                chatWidget.style.height = `${widgetMaxHeight}px`;
                chatWidget.style.maxHeight = `${widgetMaxHeight}px`;
                chatWidget.style.top = `${verticalMargins / 2}px`; // Centrarlo un poco o ponerlo arriba
                chatWidget.style.bottom = 'auto'; // Anular el bottom original
                chatWidget.style.position = 'fixed'; // Asegurar que sea fixed

                // Altura disponible para el contenedor de mensajes
                const messagesContainerHeight = widgetMaxHeight - headerHeight - inputAreaHeight;
                chatMessagesContainer.style.height = `${messagesContainerHeight}px`;
                chatMessagesContainer.style.maxHeight = `${messagesContainerHeight}px`;
                
                // Asegurar que el input area esté fijo abajo dentro del widget
                chatInputArea.style.position = 'absolute';
                chatInputArea.style.bottom = '0';
                chatInputArea.style.left = '0';
                chatInputArea.style.right = '0';
                chatInputArea.style.width = '100%'; // Asegurar que ocupe todo el ancho
                chatInputArea.style.zIndex = '20'; // Por si acaso

                // Scroll al último mensaje
                chatMessagesContainer.scrollTop = chatMessagesContainer.scrollHeight;

            } else {
                // Restaurar estilos originales del widget
                chatWidget.style.height = ''; // CSS original tomará el control
                chatWidget.style.maxHeight = ''; // CSS original
                chatWidget.style.top = ''; // CSS original
                chatWidget.style.bottom = `${spaceForBubble}px`; // Dejar espacio para la burbuja
                // chatWidget.style.position = 'fixed'; // Ya debería ser fixed por CSS

                // Restaurar estilos del contenedor de mensajes
                chatMessagesContainer.style.height = ''; // CSS original
                chatMessagesContainer.style.maxHeight = ''; // CSS original
                
                // Restaurar estilos del área de input
                chatInputArea.style.position = '';
                chatInputArea.style.bottom = '';
                chatInputArea.style.left = '';
                chatInputArea.style.right = '';
                chatInputArea.style.width = '';
                chatInputArea.style.zIndex = '';
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
            // Asegurar que la burbuja sea visible después del cambio de orientación
            ensureBubbleVisibility();
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
                    // Asegurar que la burbuja sea visible después de ocultar el teclado
                    setTimeout(() => {
                        ensureBubbleVisibility();
                    }, 100);
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
