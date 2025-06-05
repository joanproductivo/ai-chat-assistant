# AI Chat Assistant for WordPress

## Description
This WordPress plugin implements a floating chat widget that integrates with OpenAI's API to provide AI-powered assistance. It includes advanced features like rate limiting, caching, and admin configuration for seamless integration.

## Key Features
- Real-time AI chat interface with OpenAI API KEY and ASSISTANT ID
- REST API endpoints for message handling and status checks
- Customizable chat widget placement and appearance
- Multi-language support with translation files
- User session tracking and auto-open functionality
- Admin dashboard for monitoring and configuration

## Features Breakdown
### 1. OpenAI Integration
- Uses Threads API for message persistence
- Handles rate limiting (30 requests/hour by default)
- Retry logic for concurrent message issues

### 2. Admin Configuration
- API key and assistant ID setup
- Color theme customization
- Message limits (daily per IP)
- Auto-open settings (pages visited, session timeout)
- Excluded pages configuration

### 3. Performance
- Cache versioning with color hash
- WP Rocket cache integration
- Efficient resource loading

## Installation
1. Upload the plugin files to `/wp-content/plugins/ai-chat-assistant`
2. Activate through the WordPress admin panel
3. Configure settings under Settings > Chat IA Pro

## Usage
1. Add the chat widget to your site using the `[ai_chat]` shortcode
2. Configure in the admin settings:
   - API credentials
   - Chat appearance
   - Message limits
   - Auto-open settings

## File Structure
```
ai-chat-assistant.php        - Main plugin file
ai-chat-pro-styles.css       - Generated styles
ai-chat-pro-script.js        - Frontend logic
PDR.md                       - Technical documentation
README.md                    - This file
```
