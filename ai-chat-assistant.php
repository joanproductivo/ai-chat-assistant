<?php
/*
Plugin Name: AI Chat Assistant Pro
Description: Chat público flotante con un asistente de OpenAI.
Version: 1.8.5
Author: Joan Planas & IA
*/

defined('ABSPATH') or die('No script kiddies please!');

// Registrar el shortcode
add_action('init', function() {
    add_shortcode('ai_chat', 'ai_chat_pro_shortcode');
});

function ai_chat_pro_shortcode($atts) {
    wp_enqueue_script('ai-chat-pro-js');
    wp_enqueue_style('ai-chat-pro-styles');
    return '';
}

// Función para verificar si el chat debe mostrarse en la página actual
function ai_chat_pro_should_show_chat() {
    static $should_show = null; // Cache result for the request

    if ($should_show !== null) {
        return $should_show;
    }

    $excluded_pages = get_option('ai_chat_pro_excluded_pages', '');
    if (empty($excluded_pages)) {
        $should_show = true;
        return true;
    }
    
    $excluded_pages_array = array_map('trim', explode(',', $excluded_pages));
    $current_url = home_url($_SERVER['REQUEST_URI']);
    $current_path = parse_url($current_url, PHP_URL_PATH);
    
    // Verificar por ID de página/post
    $current_id = get_queried_object_id();
    
    foreach ($excluded_pages_array as $excluded_page) {
        if (empty($excluded_page)) continue;
        
        // Si es un número, comparar con el ID
        if (is_numeric($excluded_page) && $current_id == $excluded_page) {
            return false;
        }
        
        // Si contiene una barra, es una URL o path
        if (strpos($excluded_page, '/') !== false) {
            // Normalizar paths para comparación
            $excluded_path = '/' . trim($excluded_page, '/');
            $normalized_current_path = '/' . trim($current_path, '/');
            
            if ($excluded_path === $normalized_current_path || 
                strpos($normalized_current_path, $excluded_path) === 0) {
                return false;
            }
        } else {
            // Comparar con slug de página
            $current_post = get_queried_object();
            if ($current_post && isset($current_post->post_name) && 
                $current_post->post_name === $excluded_page) {
                return false;
            }
        }
    }
    
    $should_show = true;
    return true;
}

// Helper function to get all color settings
function ai_chat_pro_get_all_color_options() {
    static $colors = null;
    if ($colors === null) {
        $colors = [
            'primary_color'       => get_option('ai_chat_pro_primary_color', '#6a0dad'),
            'bubble_color'        => get_option('ai_chat_pro_bubble_color', '#6a0dad'),
            'secondary_color'     => get_option('ai_chat_pro_secondary_color', '#9370db'),
            'accent_color'        => get_option('ai_chat_pro_accent_color', '#4b0082'),
            'text_color'          => get_option('ai_chat_pro_text_color', '#ffffff'),
            'bg_color'            => get_option('ai_chat_pro_bg_color', '#ffffff'),
            'messages_bg_color'   => get_option('ai_chat_pro_messages_bg_color', '#f9f7fc'),
            'user_bubble_color'   => get_option('ai_chat_pro_user_bubble_color', '#9370db'),
            'ai_bubble_color'     => get_option('ai_chat_pro_ai_bubble_color', '#e9e0f3'),
            'ai_text_color'       => get_option('ai_chat_pro_ai_text_color', '#333333'),
        ];
    }
    return $colors;
}

// Encolar scripts y estilos con compatibilidad mejorada para WP Rocket y otros plugins de cache
add_action('wp_enqueue_scripts', 'ai_chat_pro_enqueue_scripts');
function ai_chat_pro_enqueue_scripts() {
    // Verificar si el chat debe mostrarse en esta página
    if (!ai_chat_pro_should_show_chat()) {
        return;
    }
    
    // Generar un hash único basado en los colores actuales para forzar actualización de cache
    $colors_hash = ai_chat_pro_get_colors_hash();
    $plugin_version = '1.8.3-' . $colors_hash; // Versión con hash de colores

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

    // Registrar script externo con la misma versión para consistencia
    wp_enqueue_script(
        'ai-chat-pro-js',
        plugin_dir_url(__FILE__) . 'ai-chat-pro-script.js',
        ['wp-i18n'],
        $plugin_version,
        true
    );

    // Cargar traducciones para JS
    wp_set_script_translations('ai-chat-pro-js', 'ai-chat-pro', plugin_dir_path(__FILE__) . 'languages');

    // Localizar variables para JS
    wp_localize_script('ai-chat-pro-js', 'aiChatPro', array(
        'rest_url_message' => esc_url_raw(rest_url('ai-chat-pro/v1/message')),
        'rest_url_check'   => esc_url_raw(rest_url('ai-chat-pro/v1/check')),
        'rest_url_config'  => esc_url_raw(rest_url('ai-chat-pro/v1/config')),
        'nonce'            => wp_create_nonce('wp_rest'),
        'initial_greeting' => get_option('ai_chat_pro_initial_greeting', __('¡Hola! ¿En qué puedo ayudarte hoy?', 'ai-chat-pro')),
        'limit_exceeded'   => get_option('ai_chat_pro_limit_exceeded', __('Has alcanzado el límite de mensajes de hoy. Vuelve mañana. Gracias.', 'ai-chat-pro')),
        'thinking'         => __('Está escribiendo...', 'ai-chat-pro'),
        'error_prefix'     => __('', 'ai-chat-pro'),
        'send_button_text' => get_option('ai_chat_pro_send_button_text', __('Enviar', 'ai-chat-pro')),
        'input_placeholder'=> get_option('ai_chat_pro_input_placeholder', __('Escribe tu mensaje...', 'ai-chat-pro')),
        'chat_title'       => get_option('ai_chat_pro_chat_title', __('Ayudante', 'ai-chat-pro')),
        'bubble_svg_icon'  => get_option('ai_chat_pro_bubble_icon_svg', '<svg viewBox="0 0 24 24"><path d="M21.99 4c0-1.1-.89-2-1.99-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h14l4 4-.01-18zM18 14H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z"/></svg>'),
        'start_opened'     => (bool) get_option('ai_chat_pro_start_opened', false),
        'max_history_items'=> 50,
        'message_limit_count' => (int) get_option('ai_chat_pro_message_limit', 10),
        'user_label'       => __('Tú', 'ai-chat-pro'),
        'ai_label'         => get_option('ai_chat_pro_ai_name', __('Ayudante', 'ai-chat-pro')), // <-- CAMBIO AQUÍ
        'close_chat_label' => __('Cerrar chat', 'ai-chat-pro'),
        'type_message_label' => __('Mensaje para Ayudante', 'ai-chat-pro'), // Podrías querer actualizar esto dinámicamente si 'ai_label' cambia mucho.
        'thinking_saved_text' => __('Está escribiendo... (refreshed)', 'ai-chat-pro'),
        'auto_open_config' => ai_chat_pro_get_auto_open_config(),
        'site_url'         => home_url(),
        'current_time'     => current_time('timestamp'),
        'timezone_offset'  => get_option('gmt_offset') * HOUR_IN_SECONDS,
    ));
}

// Añadir HTML del chat flotante al pie de página
add_action('wp_footer', 'ai_chat_pro_add_floating_chat_html');
function ai_chat_pro_add_floating_chat_html() {
    // Verificar si el chat debe mostrarse en esta página
    if (!ai_chat_pro_should_show_chat()) {
        return;
    }
    ?>
    <div id="ai-chat-pro-bubble" role="button" tabindex="0" aria-label="<?php esc_attr_e('Abrir chat de asistencia', 'ai-chat-pro'); ?>">
        <span class="ai-chat-pro-unread-badge" style="display:none;"></span>
    </div>

    <div id="ai-chat-pro-widget" class="<?php echo get_option('ai_chat_pro_start_opened', false) ? 'active' : ''; ?>" aria-hidden="<?php echo get_option('ai_chat_pro_start_opened', false) ? 'false' : 'true'; ?>">
        <div id="ai-chat-pro-header">
            <h3 id="ai-chat-pro-widget-title"></h3>
            <button id="ai-chat-pro-close-btn" aria-label="<?php esc_attr_e('Cerrar chat', 'ai-chat-pro'); ?>">×</button>
        </div>
        <div id="ai-chat-messages-pro" role="log" aria-live="polite">
            <!-- Los mensajes se añadirán aquí por JS -->
        </div>
        <div id="ai-chat-pro-input-area">
            <input type="text" id="ai-chat-input-pro" aria-label="<?php esc_attr_e('Escribe tu mensaje para la IA', 'ai-chat-pro'); ?>">
            <button id="ai-chat-send-button-pro"></button>
        </div>
    </div>
    <?php
}

// REST API Endpoints
add_action('rest_api_init', function () {
    register_rest_route('ai-chat-pro/v1', '/message', [
        'methods' => 'POST',
        'callback' => 'ai_chat_pro_handle_message',
        'permission_callback' => 'ai_chat_pro_rest_permission_check', 
    ]);

    register_rest_route('ai-chat-pro/v1', '/check', [
        'methods' => 'POST',
        'callback' => 'ai_chat_pro_check_status',
        'permission_callback' => 'ai_chat_pro_rest_permission_check', 
    ]);
});

// Rate limiting mejorado
function ai_chat_pro_check_rate_limit($ip = null) {
    if (!$ip) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }
    
    $transient_key = 'ai_chat_rate_limit_' . md5($ip);
    $requests = get_transient($transient_key);
    
    if ($requests === false) {
        $requests = 0;
    }
    
    $max_requests = (int) get_option('ai_chat_pro_rate_limit_count', 30); // Hacer configurable
    $rate_limit_duration = (int) get_option('ai_chat_pro_rate_limit_duration', HOUR_IN_SECONDS); // Hacer configurable

    if ($requests >= $max_requests) {
        return false;
    }
    
    set_transient($transient_key, $requests + 1, $rate_limit_duration);
    return true;
}

function ai_chat_pro_handle_message(WP_REST_Request $request) {
    // Nonce check is now handled by ai_chat_pro_rest_permission_check

    if (!ai_chat_pro_check_rate_limit()) {
        $limit_exceeded_message = get_option('ai_chat_pro_limit_exceeded', __('Has alcanzado el límite de mensajes de hoy. Vuelve mañana. Gracias.', 'ai-chat-pro'));
        // Fallback if the option is empty, though it has a default.
        if (empty($limit_exceeded_message)) {
            $limit_exceeded_message = __('Has excedido el límite de solicitudes. Inténtalo más tarde.', 'ai-chat-pro');
        }
        return new WP_REST_Response(['message' => $limit_exceeded_message], 429);
    }

    $params = $request->get_json_params();
    $message_content = isset($params['message']) ? sanitize_text_field($params['message']) : '';
    $thread_id = isset($params['thread_id']) ? sanitize_text_field($params['thread_id']) : null;

    $api_key = get_option('ai_chat_pro_api_key');
    $assistant_id = get_option('ai_chat_pro_assistant_id');

    if (empty($api_key)) {
        return new WP_REST_Response(['message' => __('Falta la clave API. Configúrala en Ajustes > Chat IA Pro.', 'ai-chat-pro')], 500);
    }
    if (empty($assistant_id)) {
        return new WP_REST_Response(['message' => __('Falta el ID del Asistente. Configúrala en Ajustes > Chat IA Pro.', 'ai-chat-pro')], 500);
    }
    if (empty($message_content)) {
        return new WP_REST_Response(['message' => __('El mensaje no puede estar vacío.', 'ai-chat-pro')], 400);
    }

    $headers = [
        'Authorization' => 'Bearer ' . $api_key,
        'Content-Type'  => 'application/json',
        'OpenAI-Beta'   => 'assistants=v2'
    ];

    // 1. Crear Thread si no existe
    if (!$thread_id) {
        $thread_resp = wp_remote_post("https://api.openai.com/v1/threads", [
            'method' => 'POST', 
            'headers' => $headers, 
            'body' => json_encode([]),
            'timeout' => 30
        ]);
        
        if (is_wp_error($thread_resp)) {
            error_log("AI Chat Pro - Error creando thread: " . $thread_resp->get_error_message());
            return new WP_REST_Response(['message' => __('Error al conectar con OpenAI (crear hilo).', 'ai-chat-pro')], 503);
        }
        
        $status_code = wp_remote_retrieve_response_code($thread_resp);
        $body = json_decode(wp_remote_retrieve_body($thread_resp), true);
        
        if ($status_code >= 400 || !isset($body['id'])) {
            $api_error = isset($body['error']['message']) ? $body['error']['message'] : wp_remote_retrieve_body($thread_resp);
            error_log("AI Chat Pro - Error API creando thread ($status_code): " . $api_error);
            return new WP_REST_Response(['message' => sprintf(__('Error de API OpenAI (crear hilo): %s', 'ai-chat-pro'), $api_error)], $status_code);
        }
        $thread_id = $body['id'];
    }

    // 2. Añadir Mensaje al Thread
    $message_payload = ['role' => 'user', 'content' => $message_content];
    $add_message_resp = wp_remote_post("https://api.openai.com/v1/threads/{$thread_id}/messages", [
        'method' => 'POST', 
        'headers' => $headers, 
        'body' => json_encode($message_payload),
        'timeout' => 30
    ]);
    
    if (is_wp_error($add_message_resp)) {
        error_log("AI Chat Pro - Error añadiendo mensaje: " . $add_message_resp->get_error_message());
        return new WP_REST_Response(['message' => __('Error al conectar con OpenAI (enviar mensaje).', 'ai-chat-pro')], 503);
    }
    
    $status_code_msg = wp_remote_retrieve_response_code($add_message_resp);
    $body_msg = json_decode(wp_remote_retrieve_body($add_message_resp), true);
    
    if ($status_code_msg >= 400) {
        $api_error = isset($body_msg['error']['message']) ? $body_msg['error']['message'] : wp_remote_retrieve_body($add_message_resp);
        error_log("AI Chat Pro - Error API añadiendo mensaje ($status_code_msg): " . $api_error);
        return new WP_REST_Response(['message' => sprintf(__('Error de API OpenAI (enviar mensaje): %s', 'ai-chat-pro'), $api_error)], $status_code_msg);
    }

    // 3. Crear un Run
    $run_payload = ['assistant_id' => $assistant_id];
    $run_resp = wp_remote_post("https://api.openai.com/v1/threads/{$thread_id}/runs", [
        'method' => 'POST', 
        'headers' => $headers, 
        'body' => json_encode($run_payload),
        'timeout' => 30
    ]);
    
    if (is_wp_error($run_resp)) {
        error_log("AI Chat Pro - Error creando run: " . $run_resp->get_error_message());
        return new WP_REST_Response(['message' => __('Error al conectar con OpenAI (iniciar IA).', 'ai-chat-pro')], 503);
    }
    
    $status_code_run = wp_remote_retrieve_response_code($run_resp);
    $run_body = json_decode(wp_remote_retrieve_body($run_resp), true);
    
    if ($status_code_run >= 400 || !isset($run_body['id'])) {
        $api_error = isset($run_body['error']['message']) ? $run_body['error']['message'] : wp_remote_retrieve_body($run_resp);
        error_log("AI Chat Pro - Error API creando run ($status_code_run): " . $api_error);
        return new WP_REST_Response(['message' => sprintf(__('Error de API OpenAI (iniciar IA): %s', 'ai-chat-pro'), $api_error)], $status_code_run);
    }

    return new WP_REST_Response(['run_id' => $run_body['id'], 'thread_id' => $thread_id, 'status' => $run_body['status']], 200);
}

function ai_chat_pro_check_status(WP_REST_Request $request) {
    // Nonce check is now handled by ai_chat_pro_rest_permission_check
    
    $params = $request->get_json_params();
    $thread_id = isset($params['thread_id']) ? sanitize_text_field($params['thread_id']) : null;
    $run_id = isset($params['run_id']) ? sanitize_text_field($params['run_id']) : null;

    $api_key = get_option('ai_chat_pro_api_key');
    if (empty($api_key)) {
        return new WP_REST_Response(['message' => __('Falta la clave API.', 'ai-chat-pro')], 500);
    }
    if (!$thread_id || !$run_id) {
        return new WP_REST_Response(['message' => __('Faltan parámetros para verificar estado.', 'ai-chat-pro')], 400);
    }

    $headers = ['Authorization' => 'Bearer ' . $api_key, 'Content-Type' => 'application/json', 'OpenAI-Beta' => 'assistants=v2'];

    $status_resp = wp_remote_get("https://api.openai.com/v1/threads/{$thread_id}/runs/{$run_id}", [
        'headers' => $headers,
        'timeout' => 30
    ]);
    
    if (is_wp_error($status_resp)) {
        error_log("AI Chat Pro - Error consultando estado run: " . $status_resp->get_error_message());
        return new WP_REST_Response(['message' => __('Error al consultar estado de la IA.', 'ai-chat-pro')], 503);
    }
    
    $status_code = wp_remote_retrieve_response_code($status_resp);
    $status_body = json_decode(wp_remote_retrieve_body($status_resp), true);
    
    if ($status_code >= 400 || !is_array($status_body) || !isset($status_body['status'])) {
        $api_error = isset($status_body['error']['message']) ? $status_body['error']['message'] : wp_remote_retrieve_body($status_resp);
        error_log("AI Chat Pro - Error API consultando estado run ($status_code): " . $api_error);
        return new WP_REST_Response(['message' => sprintf(__('Respuesta inválida de API al consultar estado: %s', 'ai-chat-pro'), $api_error)], $status_code);
    }
    
    $current_status = $status_body['status'];

    if ($current_status === 'completed') {
        $messages_resp = wp_remote_get("https://api.openai.com/v1/threads/{$thread_id}/messages?limit=5&order=desc", [
            'headers' => $headers,
            'timeout' => 30
        ]);
        
        if (is_wp_error($messages_resp)) {
            error_log("AI Chat Pro - Error obteniendo mensajes: " . $messages_resp->get_error_message());
            return new WP_REST_Response(['message' => __('Error al obtener respuesta de la IA.', 'ai-chat-pro')], 503);
        }
        
        $msg_status_code = wp_remote_retrieve_response_code($messages_resp);
        $messages_body = json_decode(wp_remote_retrieve_body($messages_resp), true);
        
        if ($msg_status_code >= 400 || !isset($messages_body['data'])) {
             $api_error = isset($messages_body['error']['message']) ? $messages_body['error']['message'] : wp_remote_retrieve_body($messages_resp);
             error_log("AI Chat Pro - Error API obteniendo mensajes ($msg_status_code): " . $api_error);
             return new WP_REST_Response(['message' => sprintf(__('Respuesta inválida de API al obtener mensajes: %s', 'ai-chat-pro'), $api_error)], $msg_status_code);
        }

        $assistant_reply = '';
        foreach ($messages_body['data'] as $message_item) {
            if ($message_item['role'] === 'assistant') {
                if (!empty($message_item['content'])) {
                    foreach ($message_item['content'] as $content_block) {
                        if ($content_block['type'] === 'text') {
                            $assistant_reply = $content_block['text']['value'];
                            break 2; 
                        }
                    }
                }
            }
        }
        
        if (empty($assistant_reply)) {
             return new WP_REST_Response([
                'status' => 'completed_no_reply',
                'reply' => __('La IA procesó tu solicitud pero no generó una respuesta visible esta vez.', 'ai-chat-pro')
            ], 200);
        }
        
        return new WP_REST_Response(['status' => 'completed', 'reply' => $assistant_reply], 200);

    } else if (in_array($current_status, ['failed', 'cancelled', 'expired'])) {
        $error_message = __('La IA falló al procesar la solicitud.', 'ai-chat-pro');
        if (isset($status_body['last_error']['message'])) {
            $error_message = $status_body['last_error']['message'];
        }
        error_log("AI Chat Pro - Run status: {$current_status}. Error: " . json_encode($status_body['last_error'] ?? null));
        return new WP_REST_Response(['status' => $current_status, 'error_message' => $error_message], 200);
    }

    return new WP_REST_Response(['status' => $current_status], 200);
}

// Sección de Ajustes en el Admin
add_action('admin_menu', function () {
    add_options_page(
        __('Ajustes del Chat IA Pro', 'ai-chat-pro'),
        __('Chat IA Pro', 'ai-chat-pro'),
        'manage_options',
        'ai-chat-pro-settings',
        'ai_chat_pro_settings_page_content'
    );
});

function ai_chat_pro_settings_page_content() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('Ajustes del Asistente de IA Pro', 'ai-chat-pro'); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('ai_chat_pro_settings_group');
            do_settings_sections('ai-chat-pro-settings');
            submit_button();
            ?>
        </form>
        <h2><?php echo esc_html__('Cómo usar', 'ai-chat-pro'); ?></h2>
        <p><?php echo esc_html__('El chat flotante se mostrará automáticamente en todas las páginas del frontend. Puedes personalizar su apariencia y comportamiento en los ajustes.', 'ai-chat-pro'); ?></p>
        <p><?php printf(
                esc_html__('Asegúrate de haber creado un "Assistant" en la plataforma de OpenAI (%1$s) y copia su ID en los ajustes.', 'ai-chat-pro'),
                '<a href="https://platform.openai.com/assistants" target="_blank">platform.openai.com/assistants</a>'
            ); ?>
        </p>
         <p><?php echo esc_html__('Si deseas usar el shortcode [ai_chat] para asegurar la carga en páginas específicas (aunque el flotante es global), puedes hacerlo.', 'ai-chat-pro'); ?></p>
    </div>
    <?php
}

add_action('admin_init', 'ai_chat_pro_register_all_settings');
function ai_chat_pro_register_all_settings() {
    $setting_group = 'ai_chat_pro_settings_group';
    $page_slug = 'ai-chat-pro-settings';

    // Registrar el grupo de ajustes
    register_setting($setting_group, 'ai_chat_pro_api_key', ['sanitize_callback' => 'sanitize_text_field', 'type' => 'string']);
    register_setting($setting_group, 'ai_chat_pro_assistant_id', ['sanitize_callback' => 'sanitize_text_field', 'type' => 'string']);
    register_setting($setting_group, 'ai_chat_pro_chat_title', ['sanitize_callback' => 'sanitize_text_field', 'type' => 'string', 'default' => __('Ayudante', 'ai-chat-pro')]);
    
    // --> NUEVO AJUSTE: Nombre de la IA
    register_setting($setting_group, 'ai_chat_pro_ai_name', ['sanitize_callback' => 'sanitize_text_field', 'type' => 'string', 'default' => __('Ayudante', 'ai-chat-pro')]);
    
    register_setting($setting_group, 'ai_chat_pro_initial_greeting', ['sanitize_callback' => 'sanitize_text_field', 'type' => 'string', 'default' => __('¡Hola! ¿En qué puedo ayudarte hoy?', 'ai-chat-pro')]);
    register_setting($setting_group, 'ai_chat_pro_input_placeholder', ['sanitize_callback' => 'sanitize_text_field', 'type' => 'string', 'default' => __('Escribe tu mensaje...', 'ai-chat-pro')]);
    register_setting($setting_group, 'ai_chat_pro_send_button_text', ['sanitize_callback' => 'sanitize_text_field', 'type' => 'string', 'default' => __('Enviar', 'ai-chat-pro')]);
    register_setting($setting_group, 'ai_chat_pro_bubble_icon_svg', ['sanitize_callback' => 'ai_chat_pro_sanitize_svg_field', 'type' => 'string', 'default' => '<svg viewBox="0 0 24 24"><path d="M21.99 4c0-1.1-.89-2-1.99-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h14l4 4-.01-18zM18 14H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z"/></svg>']);
    register_setting($setting_group, 'ai_chat_pro_message_limit', ['sanitize_callback' => 'absint', 'type' => 'integer', 'default' => 10]);
    register_setting($setting_group, 'ai_chat_pro_limit_exceeded', ['sanitize_callback' => 'sanitize_text_field', 'type' => 'string', 'default' => __('Has alcanzado el límite de mensajes de hoy. Vuelve mañana. Gracias.', 'ai-chat-pro')]);
    register_setting($setting_group, 'ai_chat_pro_start_opened', ['sanitize_callback' => 'rest_sanitize_boolean', 'type' => 'boolean', 'default' => false]);
    register_setting($setting_group, 'ai_chat_pro_auto_open_enabled', ['sanitize_callback' => 'rest_sanitize_boolean', 'type' => 'boolean', 'default' => false]);
    register_setting($setting_group, 'ai_chat_pro_auto_open_pages', ['sanitize_callback' => 'absint', 'type' => 'integer', 'default' => 3]);
    register_setting($setting_group, 'ai_chat_pro_auto_open_message_enabled', ['sanitize_callback' => 'rest_sanitize_boolean', 'type' => 'boolean', 'default' => true]);
    register_setting($setting_group, 'ai_chat_pro_auto_open_message_text', ['sanitize_callback' => 'sanitize_text_field', 'type' => 'string', 'default' => __('He visto que has visitado varias páginas. ¿Puedo ayudarte en algo?', 'ai-chat-pro')]);
    // Registering new auto_open settings
    register_setting($setting_group, 'ai_chat_pro_auto_open_reset_daily', ['sanitize_callback' => 'rest_sanitize_boolean', 'type' => 'boolean', 'default' => true]);
    register_setting($setting_group, 'ai_chat_pro_auto_open_exclude_reloads', ['sanitize_callback' => 'rest_sanitize_boolean', 'type' => 'boolean', 'default' => true]);
    register_setting($setting_group, 'ai_chat_pro_auto_open_normalize_urls', ['sanitize_callback' => 'rest_sanitize_boolean', 'type' => 'boolean', 'default' => true]);
    register_setting($setting_group, 'ai_chat_pro_auto_open_session_timeout', ['sanitize_callback' => 'absint', 'type' => 'integer', 'default' => 30]);

    register_setting($setting_group, 'ai_chat_pro_rate_limit_count', ['sanitize_callback' => 'absint', 'type' => 'integer', 'default' => 30]);
    register_setting($setting_group, 'ai_chat_pro_rate_limit_duration', ['sanitize_callback' => 'absint', 'type' => 'integer', 'default' => HOUR_IN_SECONDS]);
    register_setting($setting_group, 'ai_chat_pro_excluded_pages', ['sanitize_callback' => 'sanitize_textarea_field', 'type' => 'string', 'default' => '']);

    // Configuración de colores
    $color_options_defaults = ai_chat_pro_get_all_color_options(); // Get defaults for registration
    register_setting($setting_group, 'ai_chat_pro_primary_color', ['sanitize_callback' => 'sanitize_hex_color', 'type' => 'string', 'default' => $color_options_defaults['primary_color']]);
    register_setting($setting_group, 'ai_chat_pro_bubble_color', ['sanitize_callback' => 'sanitize_hex_color', 'type' => 'string', 'default' => $color_options_defaults['bubble_color']]);
    register_setting($setting_group, 'ai_chat_pro_secondary_color', ['sanitize_callback' => 'sanitize_hex_color', 'type' => 'string', 'default' => $color_options_defaults['secondary_color']]);
    register_setting($setting_group, 'ai_chat_pro_accent_color', ['sanitize_callback' => 'sanitize_hex_color', 'type' => 'string', 'default' => $color_options_defaults['accent_color']]);
    register_setting($setting_group, 'ai_chat_pro_text_color', ['sanitize_callback' => 'sanitize_hex_color', 'type' => 'string', 'default' => $color_options_defaults['text_color']]);
    register_setting($setting_group, 'ai_chat_pro_bg_color', ['sanitize_callback' => 'sanitize_hex_color', 'type' => 'string', 'default' => $color_options_defaults['bg_color']]);
    register_setting($setting_group, 'ai_chat_pro_messages_bg_color', ['sanitize_callback' => 'sanitize_hex_color', 'type' => 'string', 'default' => $color_options_defaults['messages_bg_color']]);
    register_setting($setting_group, 'ai_chat_pro_user_bubble_color', ['sanitize_callback' => 'sanitize_hex_color', 'type' => 'string', 'default' => $color_options_defaults['user_bubble_color']]);
    register_setting($setting_group, 'ai_chat_pro_ai_bubble_color', ['sanitize_callback' => 'sanitize_hex_color', 'type' => 'string', 'default' => $color_options_defaults['ai_bubble_color']]);
    register_setting($setting_group, 'ai_chat_pro_ai_text_color', ['sanitize_callback' => 'sanitize_hex_color', 'type' => 'string', 'default' => $color_options_defaults['ai_text_color']]);

    // Sección de API y Asistente
    add_settings_section('ai_chat_pro_api_section', __('Configuración API y Asistente', 'ai-chat-pro'), null, $page_slug);
    add_settings_field('ai_chat_pro_api_key', __('Clave de API OpenAI', 'ai-chat-pro'), 'ai_chat_pro_field_text_cb', $page_slug, 'ai_chat_pro_api_section', ['id' => 'ai_chat_pro_api_key', 'desc' => __('Introduce tu clave API de OpenAI (ej: sk-...).', 'ai-chat-pro')]);
    add_settings_field('ai_chat_pro_assistant_id', __('ID del Asistente OpenAI', 'ai-chat-pro'), 'ai_chat_pro_field_text_cb', $page_slug, 'ai_chat_pro_api_section', ['id' => 'ai_chat_pro_assistant_id', 'desc' => __('Introduce el ID de tu Asistente (ej: asst_...).', 'ai-chat-pro')]);

    // Sección de Personalización del Chat
    add_settings_section('ai_chat_pro_customization_section', __('Personalización del Chat', 'ai-chat-pro'), null, $page_slug);
    add_settings_field('ai_chat_pro_chat_title', __('Título del Chat', 'ai-chat-pro'), 'ai_chat_pro_field_text_cb', $page_slug, 'ai_chat_pro_customization_section', ['id' => 'ai_chat_pro_chat_title']);
    
    // --> NUEVO CAMPO: Nombre de la IA
    add_settings_field('ai_chat_pro_ai_name', __('Nombre del Asistente (IA)', 'ai-chat-pro'), 'ai_chat_pro_field_text_cb', $page_slug, 'ai_chat_pro_customization_section', ['id' => 'ai_chat_pro_ai_name', 'desc' => __('El nombre que se mostrará para los mensajes de la IA.', 'ai-chat-pro')]);

    add_settings_field('ai_chat_pro_initial_greeting', __('Mensaje de Saludo Inicial', 'ai-chat-pro'), 'ai_chat_pro_field_text_cb', $page_slug, 'ai_chat_pro_customization_section', ['id' => 'ai_chat_pro_initial_greeting', 'width' => '500px']);
    add_settings_field('ai_chat_pro_input_placeholder', __('Placeholder del Input', 'ai-chat-pro'), 'ai_chat_pro_field_text_cb', $page_slug, 'ai_chat_pro_customization_section', ['id' => 'ai_chat_pro_input_placeholder']);
    add_settings_field('ai_chat_pro_send_button_text', __('Texto Botón Enviar', 'ai-chat-pro'), 'ai_chat_pro_field_text_cb', $page_slug, 'ai_chat_pro_customization_section', ['id' => 'ai_chat_pro_send_button_text', 'width' => '150px']);
    add_settings_field('ai_chat_pro_bubble_icon_svg', __('SVG del Icono de la Burbuja', 'ai-chat-pro'), 'ai_chat_pro_field_textarea_cb', $page_slug, 'ai_chat_pro_customization_section', ['id' => 'ai_chat_pro_bubble_icon_svg', 'desc' => __('Pega aquí el código SVG completo para el icono de la burbuja. Asegúrate de que sea un SVG válido y que el `viewBox` y `path` sean correctos. El tamaño se ajustará por CSS.', 'ai-chat-pro'), 'rows' => 5, 'width' => '500px']);
    
    add_settings_field('ai_chat_pro_start_opened', __('Abrir Chat al Cargar Página', 'ai-chat-pro'), 'ai_chat_pro_field_checkbox_cb', $page_slug, 'ai_chat_pro_customization_section', ['id' => 'ai_chat_pro_start_opened', 'desc' => __('Si se marca, el chat flotante aparecerá abierto.', 'ai-chat-pro')]);
    add_settings_field('ai_chat_pro_auto_open_enabled', __('Apertura Automática por Páginas Visitadas', 'ai-chat-pro'), 'ai_chat_pro_field_checkbox_cb', $page_slug, 'ai_chat_pro_customization_section', ['id' => 'ai_chat_pro_auto_open_enabled', 'desc' => __('Si se marca, el chat se abrirá automáticamente después de visitar el número de páginas especificado.', 'ai-chat-pro')]);
    add_settings_field('ai_chat_pro_auto_open_pages', __('Número de Páginas para Apertura Automática', 'ai-chat-pro'), 'ai_chat_pro_field_number_cb', $page_slug, 'ai_chat_pro_customization_section', ['id' => 'ai_chat_pro_auto_open_pages', 'default' => 3, 'desc' => __('Número de páginas que el usuario debe visitar antes de que el chat se abra automáticamente. Solo funciona si la opción anterior está activada.', 'ai-chat-pro')]);
    add_settings_field('ai_chat_pro_auto_open_message_enabled', __('Habilitar Mensaje de Apertura Automática', 'ai-chat-pro'), 'ai_chat_pro_field_checkbox_cb', $page_slug, 'ai_chat_pro_customization_section', ['id' => 'ai_chat_pro_auto_open_message_enabled', 'default' => true, 'desc' => __('Si se marca, se mostrará un mensaje personalizado cuando el chat se abra automáticamente. Si se desmarca, el chat se abrirá sin mensaje inicial automático.', 'ai-chat-pro')]);
    add_settings_field('ai_chat_pro_auto_open_message_text', __('Texto del Mensaje de Apertura Automática', 'ai-chat-pro'), 'ai_chat_pro_field_text_cb', $page_slug, 'ai_chat_pro_customization_section', ['id' => 'ai_chat_pro_auto_open_message_text', 'default' => __('He visto que has visitado varias páginas. ¿Puedo ayudarte en algo?', 'ai-chat-pro'), 'desc' => __('Este mensaje se mostrará si la opción anterior está activada y el chat se abre automáticamente.', 'ai-chat-pro'), 'width' => '500px']);
    // Adding fields for new auto_open settings
    add_settings_field('ai_chat_pro_auto_open_reset_daily', __('Resetear Contador de Páginas Diariamente (Auto-Apertura)', 'ai-chat-pro'), 'ai_chat_pro_field_checkbox_cb', $page_slug, 'ai_chat_pro_customization_section', ['id' => 'ai_chat_pro_auto_open_reset_daily', 'default' => true, 'desc' => __('Si se marca, el contador de páginas visitadas para la apertura automática se reseteará cada día.', 'ai-chat-pro')]);
    add_settings_field('ai_chat_pro_auto_open_exclude_reloads', __('Excluir Recargas de Página (Auto-Apertura)', 'ai-chat-pro'), 'ai_chat_pro_field_checkbox_cb', $page_slug, 'ai_chat_pro_customization_section', ['id' => 'ai_chat_pro_auto_open_exclude_reloads', 'default' => true, 'desc' => __('Si se marca, las recargas de la misma página no contarán para la apertura automática.', 'ai-chat-pro')]);
    add_settings_field('ai_chat_pro_auto_open_normalize_urls', __('Normalizar URLs (Auto-Apertura)', 'ai-chat-pro'), 'ai_chat_pro_field_checkbox_cb', $page_slug, 'ai_chat_pro_customization_section', ['id' => 'ai_chat_pro_auto_open_normalize_urls', 'default' => true, 'desc' => __('Si se marca, las URLs se normalizarán (quitar parámetros query/hash) antes de contarlas para la apertura automática.', 'ai-chat-pro')]);
    add_settings_field('ai_chat_pro_auto_open_session_timeout', __('Timeout de Sesión para Auto-Apertura (minutos)', 'ai-chat-pro'), 'ai_chat_pro_field_number_cb', $page_slug, 'ai_chat_pro_customization_section', ['id' => 'ai_chat_pro_auto_open_session_timeout', 'default' => 30, 'desc' => __('Tiempo en minutos de inactividad tras el cual una nueva visita se considera parte de una nueva sesión para el contador de auto-apertura.', 'ai-chat-pro')]);
    
    // Sección de Límites y Restricciones
    add_settings_section('ai_chat_pro_limits_section', __('Límites y Restricciones', 'ai-chat-pro'), null, $page_slug);
    add_settings_field('ai_chat_pro_message_limit', __('Límite de Mensajes por Día (por IP)', 'ai-chat-pro'), 'ai_chat_pro_field_number_cb', $page_slug, 'ai_chat_pro_limits_section', ['id' => 'ai_chat_pro_message_limit', 'default' => 10, 'desc' => __('Número de mensajes que un usuario (identificado por IP) puede enviar por día. Se usa por el JS para mostrar un aviso, no es un límite duro en servidor.', 'ai-chat-pro')]);
    add_settings_field('ai_chat_pro_limit_exceeded', __('Mensaje Límite Alcanzado', 'ai-chat-pro'), 'ai_chat_pro_field_text_cb', $page_slug, 'ai_chat_pro_limits_section', ['id' => 'ai_chat_pro_limit_exceeded', 'width' => '500px']);
    add_settings_field('ai_chat_pro_rate_limit_count', __('Límite de Solicitudes API (por IP)', 'ai-chat-pro'), 'ai_chat_pro_field_number_cb', $page_slug, 'ai_chat_pro_limits_section', ['id' => 'ai_chat_pro_rate_limit_count', 'default' => 30, 'desc' => __('Número máximo de solicitudes a la API de OpenAI por IP dentro de la duración especificada. Es un límite a nivel de servidor.', 'ai-chat-pro')]);
    add_settings_field('ai_chat_pro_rate_limit_duration', __('Duración del Límite de Solicitudes (segundos)', 'ai-chat-pro'), 'ai_chat_pro_field_number_cb', $page_slug, 'ai_chat_pro_limits_section', ['id' => 'ai_chat_pro_rate_limit_duration', 'default' => 3600, 'desc' => __('Tiempo en segundos para el cual se aplica el límite de solicitudes. Por defecto: 3600 (1 hora).', 'ai-chat-pro')]);
    add_settings_field('ai_chat_pro_excluded_pages', __('Páginas Excluidas', 'ai-chat-pro'), 'ai_chat_pro_field_textarea_cb', $page_slug, 'ai_chat_pro_limits_section', ['id' => 'ai_chat_pro_excluded_pages', 'desc' => __('Lista de páginas donde NO mostrar el chat. Separa con comas. Puedes usar: IDs de página (ej: 123), slugs (ej: contacto), o rutas (ej: /tienda/checkout). Ejemplo: 123, contacto, /tienda/checkout', 'ai-chat-pro')]);

    // Sección de Colores del Chat
    add_settings_section('ai_chat_pro_colors_section', __('Colores del Chat', 'ai-chat-pro'), 'ai_chat_pro_colors_section_callback', $page_slug);
    add_settings_field('ai_chat_pro_primary_color', __('Color Principal (Header)', 'ai-chat-pro'), 'ai_chat_pro_field_color_cb', $page_slug, 'ai_chat_pro_colors_section', ['id' => 'ai_chat_pro_primary_color', 'default' => '#6a0dad', 'desc' => __('Color principal del header del chat.', 'ai-chat-pro')]);
    add_settings_field('ai_chat_pro_bubble_color', __('Color de la Burbuja Flotante', 'ai-chat-pro'), 'ai_chat_pro_field_color_cb', $page_slug, 'ai_chat_pro_colors_section', ['id' => 'ai_chat_pro_bubble_color', 'default' => '#6a0dad', 'desc' => __('Color específico para la burbuja flotante del chat.', 'ai-chat-pro')]);
    add_settings_field('ai_chat_pro_secondary_color', __('Color Secundario (Mensajes Usuario)', 'ai-chat-pro'), 'ai_chat_pro_field_color_cb', $page_slug, 'ai_chat_pro_colors_section', ['id' => 'ai_chat_pro_secondary_color', 'default' => '#9370db', 'desc' => __('Color de fondo de los mensajes del usuario.', 'ai-chat-pro')]);
    add_settings_field('ai_chat_pro_accent_color', __('Color de Acento (Hover y Enlaces)', 'ai-chat-pro'), 'ai_chat_pro_field_color_cb', $page_slug, 'ai_chat_pro_colors_section', ['id' => 'ai_chat_pro_accent_color', 'default' => '#4b0082', 'desc' => __('Color usado para efectos hover y enlaces.', 'ai-chat-pro')]);
    add_settings_field('ai_chat_pro_text_color', __('Color de Texto (Header)', 'ai-chat-pro'), 'ai_chat_pro_field_color_cb', $page_slug, 'ai_chat_pro_colors_section', ['id' => 'ai_chat_pro_text_color', 'default' => '#ffffff', 'desc' => __('Color del texto en el header del chat.', 'ai-chat-pro')]);
    add_settings_field('ai_chat_pro_bg_color', __('Color de Fondo (Widget)', 'ai-chat-pro'), 'ai_chat_pro_field_color_cb', $page_slug, 'ai_chat_pro_colors_section', ['id' => 'ai_chat_pro_bg_color', 'default' => '#ffffff', 'desc' => __('Color de fondo del widget del chat.', 'ai-chat-pro')]);
    add_settings_field('ai_chat_pro_messages_bg_color', __('Color de Fondo (Área de Mensajes)', 'ai-chat-pro'), 'ai_chat_pro_field_color_cb', $page_slug, 'ai_chat_pro_colors_section', ['id' => 'ai_chat_pro_messages_bg_color', 'default' => '#f9f7fc', 'desc' => __('Color de fondo del área donde aparecen los mensajes.', 'ai-chat-pro')]);
    add_settings_field('ai_chat_pro_user_bubble_color', __('Color Burbuja Usuario', 'ai-chat-pro'), 'ai_chat_pro_field_color_cb', $page_slug, 'ai_chat_pro_colors_section', ['id' => 'ai_chat_pro_user_bubble_color', 'default' => '#9370db', 'desc' => __('Color de fondo de las burbujas de mensajes del usuario.', 'ai-chat-pro')]);
    add_settings_field('ai_chat_pro_ai_bubble_color', __('Color Burbuja IA', 'ai-chat-pro'), 'ai_chat_pro_field_color_cb', $page_slug, 'ai_chat_pro_colors_section', ['id' => 'ai_chat_pro_ai_bubble_color', 'default' => '#e9e0f3', 'desc' => __('Color de fondo de las burbujas de mensajes de la IA.', 'ai-chat-pro')]);
    add_settings_field('ai_chat_pro_ai_text_color', __('Color Texto IA', 'ai-chat-pro'), 'ai_chat_pro_field_color_cb', $page_slug, 'ai_chat_pro_colors_section', ['id' => 'ai_chat_pro_ai_text_color', 'default' => '#333333', 'desc' => __('Color del texto en los mensajes de la IA.', 'ai-chat-pro')]);

}

// Custom SVG Sanitization
function ai_chat_pro_sanitize_svg_field($input) {
    $allowed_svg_tags = array(
        'svg' => array('viewbox' => true, 'xmlns' => true, 'width' => true, 'height' => true, 'fill' => true, 'class' => true, 'style' => true, 'aria-hidden' => true, 'role' => true, 'focusable' => true),
        'path' => array('d' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'class' => true, 'style' => true, 'transform' => true),
        'circle' => array('cx' => true, 'cy' => true, 'r' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'class' => true, 'style' => true),
        'rect' => array('x' => true, 'y' => true, 'width' => true, 'height' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'class' => true, 'style' => true, 'rx' => true, 'ry' => true),
        'line' => array('x1' => true, 'y1' => true, 'x2' => true, 'y2' => true, 'stroke' => true, 'stroke-width' => true, 'class' => true, 'style' => true),
        'polyline' => array('points' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'class' => true, 'style' => true),
        'polygon' => array('points' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'class' => true, 'style' => true),
        'text' => array('x' => true, 'y' => true, 'fill' => true, 'font-family' => true, 'font-size' => true, 'text-anchor' => true, 'class' => true, 'style' => true),
        'g' => array('fill' => true, 'stroke' => true, 'transform' => true, 'class' => true, 'style' => true),
        'defs' => array(),
        'symbol' => array('viewbox' => true, 'id' => true),
        'use' => array('xlink:href' => true, 'href' => true, 'fill' => true, 'stroke' => true, 'class' => true, 'style' => true),
        'style' => array('type' => true), // Allow style tags for CSS within SVG
    );
    // Allow style attribute for all tags
    foreach ($allowed_svg_tags as $tag => $attrs) {
        if (is_array($attrs)) { // Ensure $attrs is an array
            $allowed_svg_tags[$tag]['style'] = true;
        }
    }
    return wp_kses( $input, $allowed_svg_tags );
}

// Callback para campos de textarea
function ai_chat_pro_field_textarea_cb($args) {
    $option_name = $args['id'];
    $default_value = isset($args['default']) ? $args['default'] : '';
    $value = get_option($option_name, $default_value);
    $rows = $args['rows'] ?? '3';
    $width = $args['width'] ?? '500px';

    echo "<textarea id='" . esc_attr($option_name) . "' name='" . esc_attr($option_name) . "' rows='" . esc_attr($rows) . "' style='width: " . esc_attr($width) . ";'>" . esc_textarea($value) . "</textarea>";
    
    if ($option_name === 'ai_chat_pro_bubble_icon_svg') {
        $default_svg = '<svg viewBox="0 0 24 24"><path d="M21.99 4c0-1.1-.89-2-1.99-2H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h14l4 4-.01-18zM18 14H6v-2h12v2zm0-3H6V9h12v2zm0-3H6V6h12v2z"/></svg>';
        echo "<button type='button' onclick='document.getElementById(\"" . esc_js($option_name) . "\").value = " . json_encode($default_svg) . ";' style='margin-left: 5px; padding: 5px 10px; font-size: 12px; vertical-align: top;'>" . __('Reset a Por Defecto', 'ai-chat-pro') . "</button>";
    }

    if (!empty($args['desc'])) echo "<p class='description'>" . esc_html($args['desc']) . "</p>";
}

// Callbacks genéricos para campos de ajustes
function ai_chat_pro_field_text_cb($args) {
    $option_name = $args['id'];
    $default_value = isset($args['default']) ? $args['default'] : '';
    $value = get_option($option_name, $default_value);
    $width = $args['width'] ?? '400px';
    
    // Si el valor es el default de una cadena traducible y no hay valor guardado, mostrar el default traducido.
    if (empty(get_option($option_name)) && is_string($default_value) && $default_value === __($default_value, 'ai-chat-pro')) {
         $value = __($default_value, 'ai-chat-pro');
    }

    echo "<input type='text' id='" . esc_attr($option_name) . "' name='" . esc_attr($option_name) . "' value='" . esc_attr($value) . "' style='width: {$width};' />";
    if (!empty($args['desc'])) echo "<p class='description'>" . esc_html($args['desc']) . "</p>";
}

function ai_chat_pro_field_number_cb($args) {
    $option_name = $args['id'];
    $default_value = isset($args['default']) ? $args['default'] : 0;
    $value = get_option($option_name, $default_value);
    
    // Si no hay valor guardado y hay un default, usar el default
    if ($value === false || $value === '') {
        $value = $default_value;
    }
    
    echo "<input type='number' id='" . esc_attr($option_name) . "' name='" . esc_attr($option_name) . "' value='" . esc_attr($value) . "' min='0' style='width: 100px;' />";
    if (!empty($args['desc'])) echo "<p class='description'>" . esc_html($args['desc']) . "</p>";
}

function ai_chat_pro_field_checkbox_cb($args) {
    $option_name = $args['id'];
    $default_value = isset($args['default']) ? $args['default'] : false;
    $value = get_option($option_name, $default_value);
    echo "<input type='checkbox' id='" . esc_attr($option_name) . "' name='" . esc_attr($option_name) . "' value='1'" . checked(1, $value, false) . " />";
    if (!empty($args['desc'])) echo "<p class='description'>" . esc_html($args['desc']) . "</p>";
}

// Callback para campos de color
function ai_chat_pro_field_color_cb($args) {
    $option_name = $args['id'];
    $default_value = isset($args['default']) ? $args['default'] : '#000000';
    
    // Obtener el valor guardado, si no existe usar el valor por defecto
    $saved_value = get_option($option_name);
    if ($saved_value === false || $saved_value === '') {
        $value = $default_value;
    } else {
        $value = $saved_value;
    }
    
    echo "<input type='color' id='" . esc_attr($option_name) . "' name='" . esc_attr($option_name) . "' value='" . esc_attr($value) . "' style='width: 60px; height: 40px; border: none; cursor: pointer;' />";
    echo "<input type='text' id='" . esc_attr($option_name) . "_text' value='" . esc_attr($value) . "' style='width: 100px; margin-left: 10px;' readonly />";
    echo "<button type='button' onclick='resetColor(\"" . esc_js($option_name) . "\", \"" . esc_js($default_value) . "\")' style='margin-left: 5px; padding: 5px 10px; font-size: 12px;'>" . __('Reset', 'ai-chat-pro') . "</button>";
    if (!empty($args['desc'])) echo "<p class='description'>" . esc_html($args['desc']) . "</p>";
    
    // JavaScript para sincronizar el color picker con el campo de texto y función de reset
    echo "<script>
    document.addEventListener('DOMContentLoaded', function() {
        var colorInput = document.getElementById('" . esc_js($option_name) . "');
        var textInput = document.getElementById('" . esc_js($option_name) . "_text');
        if (colorInput && textInput) {
            colorInput.addEventListener('input', function() {
                textInput.value = this.value;
            });
        }
    });
    
    function resetColor(fieldName, defaultValue) {
        var colorInput = document.getElementById(fieldName);
        var textInput = document.getElementById(fieldName + '_text');
        if (colorInput && textInput) {
            colorInput.value = defaultValue;
            textInput.value = defaultValue;
        }
    }
    </script>";
}

// Callback para la sección de colores
function ai_chat_pro_colors_section_callback() {
    echo '<p>' . esc_html__('Personaliza los colores del chat. Los cambios se aplicarán inmediatamente en el frontend.', 'ai-chat-pro') . '</p>';
    echo '<p><strong>' . esc_html__('Vista previa:', 'ai-chat-pro') . '</strong> ' . esc_html__('Guarda los cambios para ver la vista previa actualizada en el frontend.', 'ai-chat-pro') . '</p>';
    echo '<p><button type="button" onclick="resetAllColors(aiChatProAdminColorDefaults)" style="background-color: #dc3545; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold;">' . __('Resetear Todos los Colores', 'ai-chat-pro') . '</button></p>';
    
    // Get default colors for the reset function
    $default_colors_for_js = [];
    $color_option_keys = [
        'ai_chat_pro_primary_color', 'ai_chat_pro_bubble_color', 'ai_chat_pro_secondary_color',
        'ai_chat_pro_accent_color', 'ai_chat_pro_text_color', 'ai_chat_pro_bg_color',
        'ai_chat_pro_messages_bg_color', 'ai_chat_pro_user_bubble_color', 'ai_chat_pro_ai_bubble_color',
        'ai_chat_pro_ai_text_color'
    ];
    // Fetch registered defaults for these settings
    global $wp_settings_fields;
    $registered_settings = $wp_settings_fields['ai-chat-pro-settings']['ai_chat_pro_colors_section'] ?? [];

    foreach ($color_option_keys as $key) {
        // Fallback to hardcoded defaults if not found in registered settings (should not happen ideally)
        $default_value = '#000000'; // A generic fallback
        if (isset($registered_settings[$key]['args']['default'])) {
            $default_value = $registered_settings[$key]['args']['default'];
        } else {
            // Fallback to the original hardcoded values if specific default isn't found
            $original_defaults = [
                'ai_chat_pro_primary_color' => '#6a0dad', 'ai_chat_pro_bubble_color' => '#6a0dad',
                'ai_chat_pro_secondary_color' => '#9370db', 'ai_chat_pro_accent_color' => '#4b0082',
                'ai_chat_pro_text_color' => '#ffffff', 'ai_chat_pro_bg_color' => '#ffffff',
                'ai_chat_pro_messages_bg_color' => '#f9f7fc', 'ai_chat_pro_user_bubble_color' => '#9370db',
                'ai_chat_pro_ai_bubble_color' => '#e9e0f3', 'ai_chat_pro_ai_text_color' => '#333333'
            ];
            if (isset($original_defaults[$key])) {
                $default_value = $original_defaults[$key];
            }
        }
        $default_colors_for_js[$key] = $default_value;
    }

    echo '<script>
    var aiChatProAdminColorDefaults = ' . json_encode($default_colors_for_js) . ';
    function resetAllColors(defaults) {
        if (confirm("' . esc_js(__('¿Estás seguro de que quieres resetear todos los colores a los valores por defecto?', 'ai-chat-pro')) . '")) {
            for (var fieldName in defaults) {
                var colorInput = document.getElementById(fieldName);
                var textInput = document.getElementById(fieldName + "_text");
                if (colorInput && textInput) {
                    colorInput.value = colorDefaults[fieldName];
                    textInput.value = colorDefaults[fieldName];
                }
            }
            
            alert("' . esc_js(__('Todos los colores han sido reseteados. Recuerda guardar los cambios.', 'ai-chat-pro')) . '");
        }
    }
    </script>';
}

// Función para generar CSS personalizado con los colores configurados
function ai_chat_pro_generate_custom_css() {
    $colors = ai_chat_pro_get_all_color_options();

    // Generar CSS personalizado
    $custom_css = "
    /* Colores personalizados del chat */
    :root {
        --ai-chat-pro-primary-color: {$colors['primary_color']} !important;
        --ai-chat-pro-bubble-color: {$colors['bubble_color']} !important;
        --ai-chat-pro-secondary-color: {$colors['secondary_color']} !important;
        --ai-chat-pro-accent-color: {$colors['accent_color']} !important;
        --ai-chat-pro-text-color: {$colors['text_color']} !important;
        --ai-chat-pro-bg-color: {$colors['bg_color']} !important;
    }

    /* Burbuja flotante */
    #ai-chat-pro-bubble {
        background-color: {$colors['bubble_color']} !important;
    }
    #ai-chat-pro-bubble:hover {
        background-color: " . ai_chat_pro_darken_color($colors['bubble_color'], 15) . " !important;
    }

    /* Header del chat */
    #ai-chat-pro-header {
        background-color: {$colors['primary_color']} !important;
        color: {$colors['text_color']} !important;
    }

    /* Título del widget */
    #ai-chat-pro-widget-title {
        color: {$colors['text_color']} !important;
    }

    /* Widget del chat */
    #ai-chat-pro-widget {
        background-color: {$colors['bg_color']} !important;
    }

    /* Área de mensajes */
    #ai-chat-messages-pro {
        background-color: {$colors['messages_bg_color']} !important;
    }

    /* Mensajes del usuario */
    #ai-chat-messages-pro div.user-message .message-content {
        background-color: {$colors['user_bubble_color']} !important;
        color: white !important; /* Assuming user bubble text is always white */
    }

    /* Mensajes de la IA */
    #ai-chat-messages-pro div.ia-message .message-content {
        background-color: {$colors['ai_bubble_color']} !important;
        color: {$colors['ai_text_color']} !important;
    }

    #ai-chat-messages-pro div.ia-message strong {
        color: {$colors['accent_color']} !important;
    }

    /* Enlaces en mensajes de IA */
    #ai-chat-messages-pro div.ia-message .message-content a {
        color: {$colors['primary_color']} !important;
    }

    #ai-chat-messages-pro div.ia-message .message-content a:hover {
        color: {$colors['accent_color']} !important;
    }

    #ai-chat-messages-pro div.ia-message .message-content a:visited {
        color: {$colors['secondary_color']} !important;
    }

    /* Elementos de formato en mensajes de IA */
    #ai-chat-messages-pro div.ia-message .message-content em {
        color: {$colors['accent_color']} !important;
    }

    #ai-chat-messages-pro div.ia-message .message-content span strong {
        color: {$colors['accent_color']} !important;
    }

    /* Input del chat */
    #ai-chat-input-pro:focus {
        border-color: {$colors['primary_color']} !important;
        box-shadow: 0 0 0 3px " . ai_chat_pro_hex_to_rgba($colors['primary_color'], 0.15) . " !important;
    }

    /* Botón de enviar */
    #ai-chat-send-button-pro {
        background-color: {$colors['primary_color']} !important;
    }
    #ai-chat-send-button-pro:hover {
        background-color: {$colors['accent_color']} !important;
    }

    /* Scrollbar */
    #ai-chat-messages-pro::-webkit-scrollbar-thumb {
        background: " . ai_chat_pro_lighten_color($colors['secondary_color'], 20) . " !important;
    }
    #ai-chat-messages-pro::-webkit-scrollbar-thumb:hover {
        background: {$colors['secondary_color']} !important;
    }
    ";

    return $custom_css;
}

// Función auxiliar para convertir hex a rgba
function ai_chat_pro_hex_to_rgba($hex, $alpha = 1) {
    $hex = str_replace('#', '', $hex);
    if (strlen($hex) == 3) {
        $hex = str_repeat(substr($hex, 0, 1), 2) . str_repeat(substr($hex, 1, 1), 2) . str_repeat(substr($hex, 2, 1), 2);
    }
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    return "rgba({$r}, {$g}, {$b}, {$alpha})";
}

// Función auxiliar para aclarar un color
function ai_chat_pro_lighten_color($hex, $percent) {
    $hex = str_replace('#', '', $hex);
    if (strlen($hex) == 3) {
        $hex = str_repeat(substr($hex, 0, 1), 2) . str_repeat(substr($hex, 1, 1), 2) . str_repeat(substr($hex, 2, 1), 2);
    }
    
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    
    $r = min(255, $r + ($percent / 100) * (255 - $r));
    $g = min(255, $g + ($percent / 100) * (255 - $g));
    $b = min(255, $b + ($percent / 100) * (255 - $b));
    
    return '#' . str_pad(dechex($r), 2, '0', STR_PAD_LEFT) . str_pad(dechex($g), 2, '0', STR_PAD_LEFT) . str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
}

// Función auxiliar para oscurecer un color
function ai_chat_pro_darken_color($hex, $percent) {
    $hex = str_replace('#', '', $hex);
    if (strlen($hex) == 3) {
        $hex = str_repeat(substr($hex, 0, 1), 2) . str_repeat(substr($hex, 1, 1), 2) . str_repeat(substr($hex, 2, 1), 2);
    }
    
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    
    $r = max(0, $r - ($percent / 100) * $r);
    $g = max(0, $g - ($percent / 100) * $g);
    $b = max(0, $b - ($percent / 100) * $b);
    
    return '#' . str_pad(dechex($r), 2, '0', STR_PAD_LEFT) . str_pad(dechex($g), 2, '0', STR_PAD_LEFT) . str_pad(dechex($b), 2, '0', STR_PAD_LEFT);
}

// Función para generar hash único basado en los colores
function ai_chat_pro_get_colors_hash() {
    $color_options = ai_chat_pro_get_all_color_options();
    // Use array_values to ensure consistent order for implode, though order from helper is already consistent.
    return substr(md5(implode('', array_values($color_options))), 0, 8);
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

// Función para limpiar el cache de WP Rocket y otros plugins
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

// Nueva función para obtener configuración de apertura automática
function ai_chat_pro_get_auto_open_config() {
    return array(
        'enabled' => (bool) get_option('ai_chat_pro_auto_open_enabled', false),
        'pages_required' => (int) get_option('ai_chat_pro_auto_open_pages', 3),
        'reset_daily' => (bool) get_option('ai_chat_pro_auto_open_reset_daily', true),
        'exclude_reloads' => (bool) get_option('ai_chat_pro_auto_open_exclude_reloads', true),
        'normalize_urls' => (bool) get_option('ai_chat_pro_auto_open_normalize_urls', true),
        'session_timeout' => (int) get_option('ai_chat_pro_auto_open_session_timeout', 30), // minutos
        'message_enabled' => (bool) get_option('ai_chat_pro_auto_open_message_enabled', true),
        'message_text' => get_option('ai_chat_pro_auto_open_message_text', __('He visto que has visitado varias páginas. ¿Puedo ayudarte en algo?', 'ai-chat-pro')),
    );
}

// Nueva REST API endpoint para obtener configuración actualizada
add_action('rest_api_init', function () {
    register_rest_route('ai-chat-pro/v1', '/config', [
        'methods' => 'GET',
        'callback' => 'ai_chat_pro_get_config',
        'permission_callback' => 'ai_chat_pro_rest_permission_check', // Use a dedicated permission callback
    ]);
});

// Permission callback function for REST API (checks nonce)
function ai_chat_pro_rest_permission_check(WP_REST_Request $request) {
    $nonce = null;
    // For POST requests, nonce might be in the body or header.
    // For GET requests, nonce might be in query arg or header.
    if ($request->get_method() === 'POST') {
        $params = $request->get_params();
        $nonce = $params['_wpnonce'] ?? null; 
        if (empty($nonce)) {
            $nonce = $request->get_header('X-WP-Nonce');
        }
    } else { // GET or other methods
        $nonce = $request->get_header('X-WP-Nonce');
        if (empty($nonce)) {
            $nonce = $request->get_param('_wpnonce'); // Fallback for query param
        }
    }

    if (!wp_verify_nonce($nonce, 'wp_rest')) {
        return new WP_Error('rest_forbidden', __('Nonce inválido.', 'ai-chat-pro'), ['status' => 403]);
    }
    return true;
}

function ai_chat_pro_get_config(WP_REST_Request $request) {
    return new WP_REST_Response([
        'auto_open_config' => ai_chat_pro_get_auto_open_config(),
        'current_time' => current_time('timestamp'),
        'timezone_offset' => get_option('gmt_offset') * HOUR_IN_SECONDS,
    ], 200);
}

// Hook para cargar archivos de traducción
add_action('plugins_loaded', 'ai_chat_pro_load_textdomain');
function ai_chat_pro_load_textdomain() {
    load_plugin_textdomain('ai-chat-pro', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
