# AI Chat Assistant Pro Plugin Documentation

## Overview
This WordPress plugin implements a floating chat widget that integrates with OpenAI's API to provide AI-powered assistance. It includes:
- Shortcode for embedding chat
- REST API endpoints for message handling
- Admin settings panel
- Custom CSS generation
- Rate limiting and caching mechanisms

## Key Components

### 1. Shortcode Integration
```php
add_shortcode('ai_chat', 'ai_chat_pro_shortcode');
```
Registers the `[ai_chat]` shortcode which:
- Enqueues necessary scripts/styles
- Outputs empty string (actual UI is rendered via JS)

### 2. Script/Style Enqueue
```php
add_action('wp_enqueue_scripts', 'ai_chat_pro_enqueue_scripts');
```
Key features:
- Conditional display based on `ai_chat_pro_should_show_chat()`
- Versioning with color hash: `1.9.92-{hash}`
- Inline CSS generation via `ai_chat_pro_generate_custom_css()`

### 3. REST API Endpoints
Two main endpoints:
```php
register_rest_route('ai-chat-pro/v1', '/message', ...) // POST
register_rest_route('ai-chat-pro/v1', '/check', ...)     // POST
```

#### /message Endpoint
Handles message transmission to OpenAI:
- Creates/OpenAI thread if needed
- Manages rate limiting (30 requests/hour by default)
- Handles errors from OpenAI API
- Returns run status and messages

#### /check Endpoint
Monitors message processing status:
- Checks if OpenAI run is completed
- Returns assistant's response when ready

### 4. Admin Settings
Accessible via "Chat IA Pro" menu:
- API configuration (OpenAI API key, assistant ID)
- Chat appearance settings (colors, icons, labels)
- Message limits (daily per IP)
- Auto-open configuration
- Excluded pages settings

### 5. OpenAI Integration
Uses OpenAI's Threads API:
```php
https://api.openai.com/v1/threads/{thread_id}/runs
```
Features:
- Message persistence through threads
- Error handling for rate limits
- Retry logic for concurrent message issues

### 6. Custom CSS Generation
Dynamically creates styles based on admin settings:
```css
:root {
    --ai-chat-pro-primary-color: #6a0dad;
    --ai-chat-pro-bubble-color: #6a0dad;
    ...
}
```

### 7. Caching & Performance
- Unique versioning for assets
- WP Rocket cache clearing on color changes
- Session tracking for auto-open feature

## JavaScript Functions

### 1. `toggleChatWidget([forceOpen])`
- **Description**: Opens or closes the chat widget.
- **Parameters**:
  - `forceOpen` (boolean): Optional parameter to force open/close.
- **Behavior**:
  - Toggles widget visibility
  - Updates aria attributes
  - Manages keyboard focus

### 2. `formatMessageText(text)`
- **Description**: Formats text messages with HTML elements.
- **Features**:
  - Converts markdown-style formatting to HTML
  - Handles links, bold text, italics, code blocks
  - Sanitizes input to prevent XSS

### 3. `appendMessage(text, sender, isThinking)`
- **Description**: Appends a message to the chat interface.
- **Parameters**:
  - `text` (string): Message content
  - `sender` (string): "user" or "assistant"
  - `isThinking` (boolean): Indicates if it's a "thinking" message
- **Behavior**:
  - Creates and appends message elements
  - Handles scroll behavior
  - Updates unread message count

### 4. `removeThinkingMessage()`
- **Description**: Removes the "thinking" message.
- **Behavior**:
  - Clears the temporary thinking indicator
  - Maintains chat history integrity

### 5. `loadChatHistory()`
- **Description**: Loads chat history from localStorage.
- **Features**:
  - Restores previous conversation
  - Handles edge cases with empty history

### 6. `saveMessageToHistory(text, sender)`
- **Description**: Saves messages to localStorage.
- **Features**:
  - Maintains conversation history
  - Limits history size to prevent overflow

### 7. `isThinkingMessage(text)`
- **Description**: Checks if a message is a "thinking" indicator.
- **Returns**: Boolean indicating if message is a thinking indicator

### 8. `ensureBubbleVisibility()`
- **Description**: Ensures the chat bubble is visible.
- **Features**:
  - Adjusts positioning for mobile
  - Handles keyboard visibility

### 9. `normalizeUrl(url)`
- **Description**: Normalizes URLs for tracking.
- **Features**:
  - Removes query parameters and hashes
  - Handles invalid URLs

### 10. `handleAutoOpenByPageViews()`
- **Description**: Manages auto-open feature based on page views.
- **Features**:
  - Tracks visited pages
  - Handles session timeouts
  - Shows auto-open message

### 11. `sendMessagePro()`
- **Description**: Handles sending messages to the server.
- **Features**:
  - Validates input
  - Manages message sending state
  - Handles rate limiting
  - Polls for responses

### 12. `pollForResponse(thread_id, run_id)`
- **Description**: Polls the server for response completion.
- **Features**:
  - Handles run status checks
  - Displays responses when ready
  - Manages error cases

## Configuration Options

| Setting Name                | Purpose |
|---------------------------|---------|
| `ai_chat_pro_api_key`     | OpenAI API key |
| `ai_chat_pro_assistant_id`| OpenAI assistant ID |
| `ai_chat_pro_primary_color` | Main UI color |
| `ai_chat_pro_message_limit` | Daily message limit per user |
| `ai_chat_pro_rate_limit_count` | API request rate limit |
| `ai_chat_pro_excluded_pages` | Pages where chat should not appear |

## Auto-Open Feature
- Triggers after visiting X pages
- Configurable via admin settings
- Tracks sessions with timeout
- Optionally shows custom message

## Error Handling
- Rate limit notifications
- API connection errors
- Message sending failures
- OpenAI run status monitoring

## File Structure
```
ai-chat-assistant.php        - Main plugin file
ai-chat-pro-styles.css       - Generated styles
ai-chat-pro-script.js        - Frontend logic
PDR.md                       - This document
```

This documentation provides a comprehensive overview of the plugin's architecture and functionality for future maintenance and development.
