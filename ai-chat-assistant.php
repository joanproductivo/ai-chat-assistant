<?php
/*
Plugin Name: AI Chat Assistant Pro
Description: Chat público flotante con un asistente de OpenAI.
Version: 1.5.7
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
    $excluded_pages = get_option('ai_chat_pro_excluded_pages', '');
    if (empty($excluded_pages)) {
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
    
    return true;
}

// Encolar scripts y estilos con mejor compatibilidad para WP Rocket
add_action('wp_enqueue_scripts', 'ai_chat_pro_enqueue_scripts');
function ai_chat_pro_enqueue_scripts() {
    // Verificar si el chat debe mostrarse en esta página
    if (!ai_chat_pro_should_show_chat()) {
        return;
    }
    
    $plugin_version = '1.5.3'; // Actualizada versión

    // Registrar y encolar CSS con prioridad alta para evitar problemas de cache
    wp_enqueue_style(
        'ai-chat-pro-styles',
        plugin_dir_url(__FILE__) . 'ai-chat-pro-styles.css',
        [],
        $plugin_version,
        'all'
    );

    // Registrar script externo en lugar de inline para mejor compatibilidad
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
        'ai_label'         => get_option('ai_chat_pro_ai_name', __('Ayudante', 'ai-chat-pro')), // <-- CAMBIO AQUÍ
        'close_chat_label' => __('Cerrar chat', 'ai-chat-pro'),
        'type_message_label' => __('Mensaje para Ayudante', 'ai-chat-pro'), // Podrías querer actualizar esto dinámicamente si 'ai_label' cambia mucho.
        'thinking_saved_text' => __('Está escribiendo... (refreshed)', 'ai-chat-pro'),
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
        'permission_callback' => '__return_true', // Podría requerir nonce check aquí también si no se maneja en JS
    ]);

    register_rest_route('ai-chat-pro/v1', '/check', [
        'methods' => 'POST',
        'callback' => 'ai_chat_pro_check_status',
        'permission_callback' => '__return_true', // Podría requerir nonce check aquí también
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
    // Considerar añadir un check_ajax_referer('wp_rest') aquí si no se valida en el JS
    // if (!check_ajax_referer('wp_rest', false, false)) {
    //     return new WP_REST_Response(['message' => __('Nonce inválido.', 'ai-chat-pro')], 403);
    // }

    if (!ai_chat_pro_check_rate_limit()) {
        return new WP_REST_Response(['message' => __('Has excedido el límite de solicitudes. Inténtalo más tarde.', 'ai-chat-pro')], 429);
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
    // Considerar añadir un check_ajax_referer('wp_rest') aquí si no se valida en el JS
    // if (!check_ajax_referer('wp_rest', false, false)) {
    //     return new WP_REST_Response(['message' => __('Nonce inválido.', 'ai-chat-pro')], 403);
    // }
    
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
    register_setting($setting_group, 'ai_chat_pro_message_limit', ['sanitize_callback' => 'absint', 'type' => 'integer', 'default' => 10]);
    register_setting($setting_group, 'ai_chat_pro_limit_exceeded', ['sanitize_callback' => 'sanitize_text_field', 'type' => 'string', 'default' => __('Has alcanzado el límite de mensajes de hoy. Vuelve mañana. Gracias.', 'ai-chat-pro')]);
    register_setting($setting_group, 'ai_chat_pro_start_opened', ['sanitize_callback' => 'rest_sanitize_boolean', 'type' => 'boolean', 'default' => false]);
    register_setting($setting_group, 'ai_chat_pro_rate_limit_count', ['sanitize_callback' => 'absint', 'type' => 'integer', 'default' => 30]);
    register_setting($setting_group, 'ai_chat_pro_rate_limit_duration', ['sanitize_callback' => 'absint', 'type' => 'integer', 'default' => HOUR_IN_SECONDS]);
    register_setting($setting_group, 'ai_chat_pro_excluded_pages', ['sanitize_callback' => 'sanitize_textarea_field', 'type' => 'string', 'default' => '']);


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
    
    add_settings_field('ai_chat_pro_start_opened', __('Abrir Chat al Cargar Página', 'ai-chat-pro'), 'ai_chat_pro_field_checkbox_cb', $page_slug, 'ai_chat_pro_customization_section', ['id' => 'ai_chat_pro_start_opened', 'desc' => __('Si se marca, el chat flotante aparecerá abierto.', 'ai-chat-pro')]);

    // Sección de Límites y Restricciones
    add_settings_section('ai_chat_pro_limits_section', __('Límites y Restricciones', 'ai-chat-pro'), null, $page_slug);
    add_settings_field('ai_chat_pro_message_limit', __('Límite de Mensajes por Día (por IP)', 'ai-chat-pro'), 'ai_chat_pro_field_number_cb', $page_slug, 'ai_chat_pro_limits_section', ['id' => 'ai_chat_pro_message_limit', 'desc' => __('Número de mensajes que un usuario (identificado por IP) puede enviar por día. Se usa por el JS para mostrar un aviso, no es un límite duro en servidor.', 'ai-chat-pro')]);
    add_settings_field('ai_chat_pro_limit_exceeded', __('Mensaje Límite Alcanzado', 'ai-chat-pro'), 'ai_chat_pro_field_text_cb', $page_slug, 'ai_chat_pro_limits_section', ['id' => 'ai_chat_pro_limit_exceeded', 'width' => '500px']);
    add_settings_field('ai_chat_pro_rate_limit_count', __('Límite de Solicitudes API (por IP)', 'ai-chat-pro'), 'ai_chat_pro_field_number_cb', $page_slug, 'ai_chat_pro_limits_section', ['id' => 'ai_chat_pro_rate_limit_count', 'desc' => __('Número máximo de solicitudes a la API de OpenAI por IP dentro de la duración especificada. Es un límite a nivel de servidor.', 'ai-chat-pro')]);
    add_settings_field('ai_chat_pro_rate_limit_duration', __('Duración del Límite de Solicitudes (segundos)', 'ai-chat-pro'), 'ai_chat_pro_field_number_cb', $page_slug, 'ai_chat_pro_limits_section', ['id' => 'ai_chat_pro_rate_limit_duration', 'desc' => __('Tiempo en segundos para el cual se aplica el límite de solicitudes. Por defecto: 3600 (1 hora).', 'ai-chat-pro')]);
    add_settings_field('ai_chat_pro_excluded_pages', __('Páginas Excluidas', 'ai-chat-pro'), 'ai_chat_pro_field_textarea_cb', $page_slug, 'ai_chat_pro_limits_section', ['id' => 'ai_chat_pro_excluded_pages', 'desc' => __('Lista de páginas donde NO mostrar el chat. Separa con comas. Puedes usar: IDs de página (ej: 123), slugs (ej: contacto), o rutas (ej: /tienda/checkout). Ejemplo: 123, contacto, /tienda/checkout', 'ai-chat-pro')]);

}

// Callback para campos de textarea
function ai_chat_pro_field_textarea_cb($args) {
    $option_name = $args['id'];
    $default_value = isset($args['default']) ? $args['default'] : '';
    $value = get_option($option_name, $default_value);
    
    echo "<textarea id='" . esc_attr($option_name) . "' name='" . esc_attr($option_name) . "' rows='3' style='width: 500px;'>" . esc_textarea($value) . "</textarea>";
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

// Hook para cargar archivos de traducción
add_action('plugins_loaded', 'ai_chat_pro_load_textdomain');
function ai_chat_pro_load_textdomain() {
    load_plugin_textdomain('ai-chat-pro', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
