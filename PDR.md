Functions Documentation

1. __updateUnreadBadge()__

   - *Purpose:* Manages unread message badge visibility and content.
   - *Functionality:* Updates the badge count and style based on unread messages and chat state.

2. __toggleChatWidget(forceOpen=null)__

   - *Purpose:* Controls chat widget visibility.
   - *Functionality:* Toggles 'active' class, manages aria attributes, and adjusts UI for mobile keyboard visibility.

3. __formatMessageText(text)__

   - *Purpose:* Converts plain text to HTML with markdown-like formatting.
   - *Functionality:* Handles bold, italic, code blocks, links, and lists. Escapes HTML entities.

4. __appendMessage(text, sender, isThinking=false)__

   - *Purpose:* Adds messages to the chat interface.
   - *Functionality:* Creates message elements with appropriate styling for user/IA. Handles "thinking" indicators.

5. __removeThinkingMessage()__

   - *Purpose:* Removes the "thinking" message UI element.
   - *Functionality:* Clears the temporary thinking indicator after a response is received.

6. __loadChatHistory()__

   - *Purpose:* Initializes chat with stored history.
   - *Functionality:* Retrieves and displays messages from localStorage. Shows initial greeting if no history exists.

7. __saveMessageToHistory(text, sender)__

   - *Purpose:* Persists chat messages to localStorage.
   - *Functionality:* Maintains history limit and avoids duplicate "thinking" messages.

8. __isThinkingMessage(text)__

   - *Purpose:* Identifies "thinking" messages.
   - *Functionality:* Checks against configured thinking message strings.

9. __ensureBubbleVisibility()__

   - *Purpose:* Ensures chat bubble visibility on mobile.
   - *Functionality:* Adjusts positioning and styles for fixed viewport.

10. __handleViewportChange() / adjustChatForKeyboard()__

    - *Purpose:* Manages chat positioning when mobile keyboard is shown.
    - *Functionality:* Adjusts container heights and positions based on viewport changes.

11. __setSendingState(isSending)__

    - *Purpose:* Manages send button state during message processing.
    - *Functionality:* Disables input and shows "sending" indicator.

12. __sendMessagePro()__

    - *Purpose:* Handles user message sending and response handling.
    - *Functionality:* Validates input, manages history, and sends messages to WordPress REST API.

13. __pollForResponse(thread_id, run_id)__

    - *Purpose:* Monitors server for AI response completion.
    - *Functionality:* Polls status endpoint, handles timeouts, and updates UI with responses.

__Key Interactions:__

- Uses `localStorage` for chat history persistence

- Communicates with WordPress REST API via:

  - `aiChatPro.rest_url_message` (message sending)
  - `aiChatPro.rest_url_check` (response polling)

- Handles mobile keyboard positioning

- Implements message history limit and daily counter
