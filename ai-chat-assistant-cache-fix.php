<?php
/*
 * Solución para el problema de cache con WP Rocket
 * Añade este código a tu plugin principal o como funciones adicionales
 */

// Función mejorada para manejar el cache de WP Rocket
add_action('wp_enqueue_scripts', 'ai_chat_pro_enqueue_scripts_cache_compatible', 5);
function ai_chat_pro_enqueue_scripts_cache_compatible() {
    // Verificar si el chat debe mostrarse en esta página
    if (!ai_chat_pro_should_show_chat()) {
        return;
    }
    
    // Generar un hash único basado en los colores actuales
    $colors_hash = ai_chat_pro_get_colors_hash();
    $plugin_version = '1.5.3-' . $colors_hash; // Versión con hash de colores

    // Registrar y encolar CSS con versión única basada en colores
    wp_enqueue_style(
        'ai-chat-pro-styles',
        plugin_dir_url(__FILE__) . 'ai-chat-pro-styles.css',
        [],
        $plugin_version,
        'all'
    );

    // Añadir CSS personalizado con los colores configurados
    $custom_css = ai_chat_pro_generate_custom_css();
    wp_add_inline_style('ai-chat-pro-styles', $custom_css);

    // Registrar script con la misma versión
    wp_enqueue_script(
        'ai-chat-pro-js',
        plugin_dir_url(__FILE__) . 'ai-chat-pro-script.js',
        ['wp-i18n'],
        $plugin_version,
        true
    );

    // Resto del código de localización...
    wp_localize_script('ai-chat-pro-js', 'aiChatPro', array(
        'rest_url_message' => esc_url_raw(rest_url('ai-chat-pro/v1/message')),
        'rest_url_check'   => esc_url_raw(rest_url('ai-chat-pro/v1/check')),
        'nonce'            => wp_create_nonce('wp_rest'),
        'initial_greeting' => get_option('ai_chat_pro_initial_greeting', __('¡Hola! ¿En qué puedo ayudarte hoy?', 'ai-chat-pro')),
        'limit_exceeded'   => get_option('ai_chat_pro_limit_exceeded', __('Has alcanzado el límite de mensajes de hoy. Vuelve mañana. Gracias.', 'ai-chat-pro')),
        'thinking'         => __('Está escribiendo...', 'ai-chat-pro'),
        'error_prefix'     => __('Error: ', 'ai-chat-pro'),
        'send_button_text' => get_option('ai_chat_pro_send_button_text', __('Enviar', 'ai-chat-pro')),
        'input_placeholder'=> get_option('ai_chat_pro_input_placeholder', __('Escribe tu mensaje...', 'ai-chat-pro')),
        'chat_title'       => get_option('ai_chat_pro_chat_title', __('Ayudante', 'ai-chat-pro')),
        'bubble_svg_icon'  => '<svg viewBox="0 0 24 24"><path d="M21.99 4c0-1.1-.89-2-1.99-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h14l4 4-.01-18zM18 14H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z"/></svg>',
        'start_opened'     => (bool) get_option('ai_chat_pro_start_opened', false),
        'max_history_items'=> 50,
        'message_limit_count' => (int) get_option('ai_chat_pro_message_limit', 10),
        'user_label'       => __('Tú', 'ai-chat-pro'),
        'ai_label'         => get_option('ai_chat_pro_ai_name', __('Ayudante', 'ai-chat-pro')),
        'close_chat_label' => __('Cerrar chat', 'ai-chat-pro'),
        'type_message_label' => __('Mensaje para Ayudante', 'ai-chat-pro'),
        'thinking_saved_text' => __('Está escribiendo... (refreshed)', 'ai-chat-pro'),
        'auto_open_enabled' => (bool) get_option('ai_chat_pro_auto_open_enabled', false),
        'auto_open_pages' => (int) get_option('ai_chat_pro_auto_open_pages', 3),
    ));
}

// Función para generar hash único basado en los colores
function ai_chat_pro_get_colors_hash() {
    $colors = array(
        get_option('ai_chat_pro_primary_color', '#6a0dad'),
        get_option('ai_chat_pro_bubble_color', '#6a0dad'),
        get_option('ai_chat_pro_secondary_color', '#9370db'),
        get_option('ai_chat_pro_accent_color', '#4b0082'),
        get_option('ai_chat_pro_text_color', '#ffffff'),
        get_option('ai_chat_pro_bg_color', '#ffffff'),
        get_option('ai_chat_pro_messages_bg_color', '#f9f7fc'),
        get_option('ai_chat_pro_user_bubble_color', '#9370db'),
        get_option('ai_chat_pro_ai_bubble_color', '#e9e0f3'),
        get_option('ai_chat_pro_ai_text_color', '#333333')
    );
    
    return substr(md5(implode('', $colors)), 0, 8);
}

// Hook para limpiar cache automáticamente cuando se guardan los ajustes
add_action('update_option', 'ai_chat_pro_clear_cache_on_color_change', 10, 3);
function ai_chat_pro_clear_cache_on_color_change($option_name, $old_value, $new_value) {
    // Lista de opciones de color que deben limpiar el cache
    $color_options = array(
        'ai_chat_pro_primary_color',
        'ai_chat_pro_bubble_color',
        'ai_chat_pro_secondary_color',
        'ai_chat_pro_accent_color',
        'ai_chat_pro_text_color',
        'ai_chat_pro_bg_color',
        'ai_chat_pro_messages_bg_color',
        'ai_chat_pro_user_bubble_color',
        'ai_chat_pro_ai_bubble_color',
        'ai_chat_pro_ai_text_color'
    );
    
    if (in_array($option_name, $color_options) && $old_value !== $new_value) {
        ai_chat_pro_clear_wp_rocket_cache();
    }
}

// Función para limpiar el cache de WP Rocket
function ai_chat_pro_clear_wp_rocket_cache() {
    // Limpiar cache de WP Rocket si está disponible
    if (function_exists('rocket_clean_domain')) {
        rocket_clean_domain();
    }
    
    // Limpiar cache de WP Rocket (método alternativo)
    if (function_exists('rocket_clean_files')) {
        rocket_clean_files(array(
            get_home_url()
        ));
    }
    
    // Limpiar cache de otros plugins populares
    if (function_exists('wp_cache_clear_cache')) {
        wp_cache_clear_cache();
    }
    
    if (function_exists('w3tc_flush_all')) {
        w3tc_flush_all();
    }
    
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
}

// Añadir botón manual para limpiar cache en la página de ajustes
add_action('admin_init', 'ai_chat_pro_add_clear_cache_button');
function ai_chat_pro_add_clear_cache_button() {
    if (isset($_POST['ai_chat_pro_clear_cache']) && current_user_can('manage_options')) {
        check_admin_referer('ai_chat_pro_clear_cache_nonce');
        ai_chat_pro_clear_wp_rocket_cache();
        add_action('admin_notices', function() {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Cache limpiado correctamente. Los cambios de colores deberían ser visibles ahora.', 'ai-chat-pro') . '</p></div>';
        });
    }
}

// Añadir el botón en la página de ajustes
add_action('admin_footer', 'ai_chat_pro_add_cache_button_to_settings');
function ai_chat_pro_add_cache_button_to_settings() {
    $screen = get_current_screen();
    if ($screen && $screen->id === 'settings_page_ai-chat-pro-settings') {
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            var form = document.querySelector('form[action="options.php"]');
            if (form) {
                var clearCacheForm = document.createElement('form');
                clearCacheForm.method = 'post';
                clearCacheForm.style.marginTop = '20px';
                clearCacheForm.innerHTML = `
                    <h3><?php echo esc_js(__('Gestión de Cache', 'ai-chat-pro')); ?></h3>
                    <p><?php echo esc_js(__('Si has cambiado los colores y no se ven reflejados en el frontend, usa este botón para limpiar el cache.', 'ai-chat-pro')); ?></p>
                    <?php wp_nonce_field('ai_chat_pro_clear_cache_nonce'); ?>
                    <input type="hidden" name="ai_chat_pro_clear_cache" value="1">
                    <button type="submit" class="button button-secondary" style="background-color: #ff6b35; color: white; border-color: #ff6b35;">
                        <?php echo esc_js(__('🗑️ Limpiar Cache', 'ai-chat-pro')); ?>
                    </button>
                `;
                form.parentNode.insertBefore(clearCacheForm, form.nextSibling);
            }
        });
        </script>
        <?php
    }
}

// Función mejorada para generar CSS con mejor compatibilidad de cache
function ai_chat_pro_generate_custom_css_cache_safe() {
    // Obtener colores configurados o usar valores por defecto
    $primary_color = get_option('ai_chat_pro_primary_color', '#6a0dad');
    $bubble_color = get_option('ai_chat_pro_bubble_color', '#6a0dad');
    $secondary_color = get_option('ai_chat_pro_secondary_color', '#9370db');
    $accent_color = get_option('ai_chat_pro_accent_color', '#4b0082');
    $text_color = get_option('ai_chat_pro_text_color', '#ffffff');
    $bg_color = get_option('ai_chat_pro_bg_color', '#ffffff');
    $messages_bg_color = get_option('ai_chat_pro_messages_bg_color', '#f9f7fc');
    $user_bubble_color = get_option('ai_chat_pro_user_bubble_color', '#9370db');
    $ai_bubble_color = get_option('ai_chat_pro_ai_bubble_color', '#e9e0f3');
    $ai_text_color = get_option('ai_chat_pro_ai_text_color', '#333333');

    // Añadir timestamp para forzar actualización
    $timestamp = time();
    
    // Generar CSS personalizado con comentario de timestamp
    $custom_css = "
    /* Colores personalizados del chat - Actualizado: {$timestamp} */
    :root {
        --ai-chat-pro-primary-color: {$primary_color} !important;
        --ai-chat-pro-bubble-color: {$bubble_color} !important;
        --ai-chat-pro-secondary-color: {$secondary_color} !important;
        --ai-chat-pro-accent-color: {$accent_color} !important;
        --ai-chat-pro-text-color: {$text_color} !important;
        --ai-chat-pro-bg-color: {$bg_color} !important;
    }

    /* Burbuja flotante */
    #ai-chat-pro-bubble {
        background-color: {$bubble_color} !important;
    }
    #ai-chat-pro-bubble:hover {
        background-color: " . ai_chat_pro_darken_color($bubble_color, 15) . " !important;
    }

    /* Header del chat */
    #ai-chat-pro-header {
        background-color: {$primary_color} !important;
        color: {$text_color} !important;
    }

    /* Título del widget */
    #ai-chat-pro-widget-title {
        color: {$text_color} !important;
    }

    /* Widget del chat */
    #ai-chat-pro-widget {
        background-color: {$bg_color} !important;
    }

    /* Área de mensajes */
    #ai-chat-messages-pro {
        background-color: {$messages_bg_color} !important;
    }

    /* Mensajes del usuario */
    #ai-chat-messages-pro div.user-message .message-content {
        background-color: {$user_bubble_color} !important;
        color: white !important;
    }

    /* Mensajes de la IA */
    #ai-chat-messages-pro div.ia-message .message-content {
        background-color: {$ai_bubble_color} !important;
        color: {$ai_text_color} !important;
    }

    #ai-chat-messages-pro div.ia-message strong {
        color: {$accent_color} !important;
    }

    /* Enlaces en mensajes de IA */
    #ai-chat-messages-pro div.ia-message .message-content a {
        color: {$primary_color} !important;
    }

    #ai-chat-messages-pro div.ia-message .message-content a:hover {
        color: {$accent_color} !important;
    }

    #ai-chat-messages-pro div.ia-message .message-content a:visited {
        color: {$secondary_color} !important;
    }

    /* Elementos de formato en mensajes de IA */
    #ai-chat-messages-pro div.ia-message .message-content em {
        color: {$accent_color} !important;
    }

    #ai-chat-messages-pro div.ia-message .message-content span strong {
        color: {$accent_color} !important;
    }

    /* Input del chat */
    #ai-chat-input-pro:focus {
        border-color: {$primary_color} !important;
        box-shadow: 0 0 0 3px " . ai_chat_pro_hex_to_rgba($primary_color, 0.15) . " !important;
    }

    /* Botón de enviar */
    #ai-chat-send-button-pro {
        background-color: {$primary_color} !important;
    }
    #ai-chat-send-button-pro:hover {
        background-color: {$accent_color} !important;
    }

    /* Scrollbar */
    #ai-chat-messages-pro::-webkit-scrollbar-thumb {
        background: " . ai_chat_pro_lighten_color($secondary_color, 20) . " !important;
    }
    #ai-chat-messages-pro::-webkit-scrollbar-thumb:hover {
        background: {$secondary_color} !important;
    }
    ";

    return $custom_css;
}
