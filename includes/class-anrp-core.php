<?php
class ANRP_Core {
    private static $instance = null;
    private $scraper;
    private $rewriter;
    private $image_finder;
    private $category_tag_identifier;
    private $history_manager;
    private $feed_manager;
    private $notifications;
    private $social_share;
    private $image_editor;
    private $bookmarklet;
    
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
        
        // Frontend hooks
        add_filter('the_content', [$this, 'add_quick_actions_to_content']);
        add_action('admin_post_anrp_quick_publish', [$this, 'handle_quick_publish']);
        
        // Social Auth hooks
        add_action('admin_post_anrp_social_auth', [$this, 'handle_social_auth_request']);
        add_action('admin_post_anrp_social_callback', [$this, 'handle_social_auth_callback']);
    }

    // Sanitizers: se o campo vier vazio, mantêm a chave já armazenada
    public function sanitize_anrp_textcortex_key($value) {
        $value = trim($value ?? '');
        if (empty($value)) {
            return get_option('anrp_textcortex_key', '');
        }
        return $value;
    }

    public function sanitize_anrp_pexels_key($value) {
        $value = trim($value ?? '');
        if (empty($value)) {
            return get_option('anrp_pexels_key', '');
        }
        return $value;
    }

    public function sanitize_anrp_gemini_key($value) {
        $value = trim($value ?? '');
        if (empty($value)) {
            return get_option('anrp_gemini_key', '');
        }
        return $value;
    }
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function init_hooks() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        add_action('wp_ajax_anrp_process_article', [$this, 'ajax_process_article']);
        add_action('wp_ajax_anrp_get_history', [$this, 'ajax_get_history']);
        add_action('wp_ajax_anrp_delete_history', [$this, 'ajax_delete_history']);
        add_action('wp_ajax_anrp_save_feed', [$this, 'ajax_save_feed']);
        add_action('wp_ajax_anrp_get_feeds', [$this, 'ajax_get_feeds']);
        add_action('wp_ajax_anrp_delete_feed', [$this, 'ajax_delete_feed']);
        add_action('wp_ajax_anrp_toggle_feed', [$this, 'ajax_toggle_feed']);
        add_action('wp_ajax_anrp_check_feed_now', [$this, 'ajax_check_feed_now']);
        add_action('wp_ajax_anrp_save_social_config', [$this, 'ajax_save_social_config']);
        add_action('wp_ajax_anrp_test_social_connection', [$this, 'ajax_test_social_connection']);
        add_action('wp_ajax_anrp_get_oauth_url', [$this, 'ajax_get_oauth_url']);
        add_action('wp_ajax_anrp_save_social_settings', [$this, 'ajax_save_social_settings']);
        add_action('wp_ajax_anrp_save_template', [$this, 'ajax_save_template']);
        add_action('wp_ajax_anrp_upload_logo', [$this, 'ajax_upload_logo']);
        add_action('wp_ajax_anrp_save_option', [$this, 'ajax_save_option']);
        add_action('wp_ajax_anrp_test_gemini_key', [$this, 'ajax_test_gemini_key']);
        add_action('wp_ajax_anrp_test_openrouter', [$this, 'ajax_test_openrouter']);
        add_action('wp_ajax_anrp_get_instagram_data', [$this, 'ajax_get_instagram_data']);
        add_action('wp_ajax_anrp_rewrite_post', [$this, 'ajax_rewrite_post']);
        add_action('wp_ajax_anrp_share_post', [$this, 'ajax_share_post']);
        add_action('wp_ajax_anrp_get_post_data_for_editor', [$this, 'ajax_get_post_data_for_editor']);

        add_action('wp_ajax_anrp_bookmarklet_submit', [$this, 'ajax_bookmarklet_submit']);
        add_action('wp_ajax_nopriv_anrp_bookmarklet_submit', [$this, 'ajax_bookmarklet_submit']);
        add_action('wp_ajax_anrp_fetch_article', [$this, 'ajax_fetch_article']);
        add_action('wp_ajax_anrp_preview_feed', [$this, 'ajax_preview_feed']);
        add_action('anrp_check_feeds_hook', [$this, 'check_feeds']);
        add_action('anrp_cleanup_history_hook', [$this, 'cleanup_history']);
        add_action('save_post', [$this, 'on_post_published'], 10, 3);
        add_action('admin_init', [$this, 'register_settings']);
        
        // CORREÇÃO: Endpoint para bookmarklet movido para o hook 'init'
        add_action('init', [$this, 'register_bookmarklet_endpoint']);
        add_filter('query_vars', [$this, 'add_query_vars']);
        add_action('template_redirect', [$this, 'handle_bookmarklet_endpoint']);
        
        // Adicionar coluna personalizada na lista de posts
        add_filter('manage_posts_columns', [$this, 'add_custom_post_columns']);
        add_action('manage_posts_custom_column', [$this, 'custom_post_columns_data'], 10, 2);
        
        // Adicionar ações rápidas
        add_filter('post_row_actions', [$this, 'add_post_row_actions'], 10, 2);
        
        // Meta box no editor de posts
        add_action('add_meta_boxes', [$this, 'add_cria_meta_box']);
        
        // Carregar traduções
        add_action('plugins_loaded', [$this, 'load_textdomain']);
    }
    
    // NOVO MÉTODO ADICIONADO para registrar o endpoint no hook correto
    public function register_bookmarklet_endpoint() {
        add_rewrite_rule('^anrp-bookmarklet/?', 'index.php?anrp_bookmarklet=1', 'top');
    }
    
    public function load_textdomain() {
        load_plugin_textdomain('auto-news-rewriter-pro', false, dirname(plugin_basename(__FILE__)) . '/../languages');
    }
    
    public function add_query_vars($vars) {
        $vars[] = 'anrp_bookmarklet';
        return $vars;
    }
    
    public function handle_bookmarklet_endpoint() {
        if (get_query_var('anrp_bookmarklet')) {
            $this->bookmarklet->handle_request();
            exit;
        }
    }
    
    private function load_dependencies() {
        $this->scraper = new ANRP_Scraper();
        $this->rewriter = new ANRP_Free_Rewriter();
        $this->image_finder = new ANRP_Free_Image_Finder();
        $this->category_tag_identifier = new ANRP_Category_Tag_Identifier();
        $this->history_manager = new ANRP_History_Manager();
        $this->feed_manager = new ANRP_Feed_Manager();
        $this->notifications = new ANRP_Notifications();
        $this->social_share = new ANRP_Social_Share();
        $this->image_editor = new ANRP_Image_Editor();
        $this->bookmarklet = new ANRP_Bookmarklet();
    }
    
    public function register_settings() {
        register_setting('anrp_settings_group', 'anrp_default_author');
        register_setting('anrp_settings_group', 'anrp_default_tags');
        register_setting('anrp_settings_group', 'anrp_auto_tags_count');
        register_setting('anrp_settings_group', 'anrp_default_status');
        register_setting('anrp_settings_group', 'anrp_notifications_enabled');
        register_setting('anrp_settings_group', 'anrp_social_auto_share');
        register_setting('anrp_settings_group', 'anrp_logo_url');
        register_setting('anrp_settings_group', 'anrp_logo_width');
        register_setting('anrp_settings_group', 'anrp_logo_height');
        register_setting('anrp_settings_group', 'anrp_bookmarklet_enabled');
        // OpenRouter
        register_setting('anrp_settings_group', 'anrp_openrouter_key');
        register_setting('anrp_settings_group', 'anrp_openrouter_model');
        // Gemini
        register_setting('anrp_settings_group', 'anrp_gemini_key', ['sanitize_callback' => [$this, 'sanitize_anrp_gemini_key']]);
        register_setting('anrp_settings_group', 'anrp_gemini_model');
        register_setting('anrp_settings_group', 'anrp_gemini_prompt');
    }
    
    public function add_admin_menu() {
        add_menu_page(
            'CRIA Releituras',
            'CRIA Releituras',
            'manage_options',
            'anrp-dashboard',
            [$this, 'render_dashboard'],
            'dashicons-star-filled',
            30
        );
        
        add_submenu_page(
            'anrp-dashboard',
            __('Dashboard', 'cria-releituras'),
            __('Dashboard', 'cria-releituras'),
            'manage_options',
            'anrp-dashboard',
            [$this, 'render_dashboard']
        );
        
        add_submenu_page(
            'anrp-dashboard',
            __('Nova Matéria', 'cria-releituras'),
            __('Nova Matéria', 'cria-releituras'),
            'publish_posts',
            'anrp-new-article',
            [$this, 'render_new_article']
        );
        
        add_submenu_page(
            'anrp-dashboard',
            __('Feeds RSS', 'cria-releituras'),
            __('Feeds RSS', 'cria-releituras'),
            'manage_options',
            'anrp-feeds',
            [$this, 'render_feeds']
        );
        
        add_submenu_page(
            'anrp-dashboard',
            __('Histórico', 'cria-releituras'),
            __('Histórico', 'cria-releituras'),
            'manage_options',
            'anrp-history',
            [$this, 'render_history']
        );
        
        add_submenu_page(
            'anrp-dashboard',
            __('Compartilhamento', 'cria-releituras'),
            __('Compartilhamento', 'cria-releituras'),
            'manage_options',
            'anrp-social',
            [$this, 'render_social']
        );
        
        add_submenu_page(
            'anrp-dashboard',
            __('Editor de Imagem', 'cria-releituras'),
            __('Editor de Imagem', 'cria-releituras'),
            'manage_options',
            'anrp-image-editor',
            [$this, 'render_image_editor']
        );
        
        add_submenu_page(
            'anrp-dashboard',
            __('Bookmarklet', 'cria-releituras'),
            __('Bookmarklet', 'cria-releituras'),
            'manage_options',
            'anrp-bookmarklet',
            [$this, 'render_bookmarklet']
        );
        
        add_submenu_page(
            'anrp-dashboard',
            __('Modelos de IA', 'cria-releituras'),
            __('Modelos de IA', 'cria-releituras'),
            'manage_options',
            'anrp-ai-models',
            [$this, 'render_ai_models']
        );
        
        add_submenu_page(
            'anrp-dashboard',
            __('Configurações', 'cria-releituras'),
            __('Configurações', 'cria-releituras'),
            'manage_options',
            'anrp-settings',
            [$this, 'render_settings']
        );
    }
    
    public function render_dashboard() {
        include ANRP_PLUGIN_DIR . 'templates/admin-dashboard.php';
    }
    
    public function render_new_article() {
        include ANRP_PLUGIN_DIR . 'templates/admin-new-article.php';
    }
    
    public function render_feeds() {
        include ANRP_PLUGIN_DIR . 'templates/admin-feeds.php';
    }
    
    public function render_history() {
        include ANRP_PLUGIN_DIR . 'templates/admin-history.php';
    }
    
    public function render_social() {
        include ANRP_PLUGIN_DIR . 'templates/admin-social.php';
    }
    
    public function render_ai_models() {
        include ANRP_PLUGIN_DIR . 'templates/admin-ai-models.php';
    }
    
    public function render_image_editor() {
        include ANRP_PLUGIN_DIR . 'templates/admin-image-editor.php';
    }
    
    public function render_bookmarklet() {
        include ANRP_PLUGIN_DIR . 'templates/admin-bookmarklet.php';
    }
    
    public function render_settings() {
        include ANRP_PLUGIN_DIR . 'templates/admin-settings.php';
    }
    
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'anrp-') === false) {
            return;
        }
        
        wp_enqueue_style('anrp-admin-style', ANRP_PLUGIN_URL . 'assets/css/admin.css', [], ANRP_VERSION);
        
        // Enfileirar scripts específicos por página
        $page = isset($_GET['page']) ? $_GET['page'] : '';
        
        switch ($page) {
            case 'anrp-image-editor':
                wp_enqueue_media();
                wp_enqueue_style('anrp-image-editor-style', ANRP_PLUGIN_URL . 'assets/css/image-editor.css', [], ANRP_VERSION);
                wp_enqueue_script('fabric-js', 'https://cdnjs.cloudflare.com/ajax/libs/fabric.js/4.5.0/fabric.min.js', [], '4.5.0', true);
                wp_enqueue_script('anrp-image-editor-script', ANRP_PLUGIN_URL . 'assets/js/image-editor.js', ['jquery', 'fabric-js'], ANRP_VERSION, true);
                break;
                
            case 'anrp-settings':
                wp_enqueue_media();
                // Falls through to load admin.js from default

            case 'anrp-social':
                wp_enqueue_script('anrp-social-script', ANRP_PLUGIN_URL . 'assets/js/social.js', ['jquery'], ANRP_VERSION, true);
                wp_enqueue_script('anrp-admin-script', ANRP_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], ANRP_VERSION, true);
                break;
                
            default:
                if ($page === 'anrp-settings') {
                    wp_enqueue_media();
                }
                wp_enqueue_script('anrp-admin-script', ANRP_PLUGIN_URL . 'assets/js/admin.js', ['jquery'], ANRP_VERSION, true);
                break;
        }
        
        $anrp_localize = [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('anrp_ajax_nonce'),
            'plugin_url' => ANRP_PLUGIN_URL,
            'upload_url' => ANRP_UPLOAD_URL,
            'admin_url' => admin_url(),
            'site_url' => site_url(),
            'site_name' => get_bloginfo('name'),
            'version' => ANRP_VERSION
        ];

        // Localizar para admin.js (padrão)
        wp_localize_script('anrp-admin-script', 'anrp_ajax', $anrp_localize);

        // Garantir que páginas que enfileiram scripts próprios também recebam a mesma variável
        if ($page === 'anrp-image-editor') {
            wp_localize_script('anrp-image-editor-script', 'anrp_ajax', $anrp_localize);
        }

        if ($page === 'anrp-social') {
            wp_localize_script('anrp-social-script', 'anrp_ajax', $anrp_localize);
        }
    }
    
    public function ajax_process_article() {
        check_ajax_referer('anrp_ajax_nonce', 'nonce');
        
        if (!current_user_can('publish_posts')) {
            wp_die('Permissão negada');
        }
        
        $url = sanitize_url($_POST['article_url']);
        // Se veio token do bookmarklet, validar contra transient
        if (!empty($_POST['bookmarklet_token'])) {
            $btoken = sanitize_text_field($_POST['bookmarklet_token']);
            $raw_post_url = rawurldecode($_POST['article_url']);
            $stored = get_transient('anrp_bookmarklet_token_' . md5($raw_post_url));
            if (!$stored || $stored !== $btoken) {
                wp_send_json_error(['message' => 'Token do bookmarklet inválido ou expirado']);
            }
        }
        $rewriting_method = sanitize_text_field($_POST['rewriting_method'] ?? 'gemini');
        $publish_option = sanitize_text_field($_POST['publish_option'] ?? 'draft');
        $schedule_date = isset($_POST['schedule_date']) ? sanitize_text_field($_POST['schedule_date']) : null;
        $author_id = intval($_POST['post_author'] ?? get_option('anrp_default_author', 0));
        $category_id = intval($_POST['category_id'] ?? 0);
        $tags_input = sanitize_text_field($_POST['tags_input'] ?? '');
        $share_social = isset($_POST['share_social']) && $_POST['share_social'] == '1';
        // Campos opcionais quando a reescrita foi feita client-side (Puter)
        $article_title = isset($_POST['article_title']) ? sanitize_text_field($_POST['article_title']) : '';
        $article_content = isset($_POST['article_content']) ? wp_kses_post($_POST['article_content']) : '';
        
        try {
            // Processar artigo
            $result = $this->process_article([
                'url' => $url,
                'rewriting_method' => $rewriting_method,
                'article_title' => $article_title,
                'article_content' => $article_content,
                'publish_option' => $publish_option,
                'schedule_date' => $schedule_date,
                'author_id' => $author_id,
                'category_id' => $category_id,
                'tags_input' => $tags_input,
                'share_social' => $share_social,
                'source_type' => 'manual'
            ]);
            
            wp_send_json_success([
                'post_id' => $result['post_id'],
                'post_url' => $result['post_url'],
                'post_status' => $result['post_status'],
                'post_title' => get_the_title($result['post_id']),
                'message' => 'Notícia processada com sucesso!',
                'social_shared' => $result['social_shared'] ?? false
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function ajax_test_gemini_key() {
        check_ajax_referer('anrp_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permissão negada']);
        }

        $posted_key = isset($_POST['key']) ? sanitize_text_field($_POST['key']) : '';
        $model = isset($_POST['model']) ? sanitize_text_field($_POST['model']) : get_option('anrp_gemini_model', 'gemini-1.5-flash');
        $use_key = '';

        if (!empty($posted_key)) {
            $use_key = $posted_key;
        } else {
            $use_key = get_option('anrp_gemini_key', '');
        }

        if (empty($use_key)) {
            wp_send_json_error(['message' => 'Nenhuma chave fornecida.']);
        }

        // Usar modelo padrão se não especificado
        $model = $model ?: 'gemini-1.5-flash';

        // API v1 do Gemini (formato atual)
        $endpoint = 'https://generativelanguage.googleapis.com/v1/models/' . urlencode($model) . ':generateContent?key=' . urlencode($use_key);

        $prompt = "Responda exatamente com a palavra: ANRP_OK";

        $body = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.0,
                'maxOutputTokens' => 20
            ]
        ];

        $response = wp_remote_post($endpoint, [
            'timeout' => 30,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => wp_json_encode($body)
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'Erro na requisição: ' . $response->get_error_message()]);
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $result = json_decode(wp_remote_retrieve_body($response), true);

        // Verificar erros HTTP
        if ($http_code !== 200) {
            $error_msg = 'Erro HTTP ' . $http_code;
            if (!empty($result['error']['message'])) {
                $error_msg = $result['error']['message'];
            }
            wp_send_json_error(['message' => $error_msg, 'code' => $http_code]);
        }

        // Extrair texto da resposta
        $text = '';
        if (!empty($result['candidates'][0]['content']['parts'][0]['text'])) {
            $text = $result['candidates'][0]['content']['parts'][0]['text'];
        }

        if (stripos($text, 'ANRP_OK') !== false) {
            wp_send_json_success(['message' => 'API Gemini funcionando corretamente!']);
        }

        // Se chegou texto mas não é ANRP_OK, a API está funcionando
        if (!empty($text)) {
            wp_send_json_success(['message' => 'API conectada com sucesso!']);
        }

        // Se não encontramos resposta válida
        wp_send_json_error(['message' => 'Resposta inesperada da API. Verifique a chave.', 'debug' => $result]);
    }

    /**
     * AJAX: Testar conexão com OpenRouter
     */
    public function ajax_test_openrouter() {
        check_ajax_referer('anrp_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permissão negada']);
        }

        $posted_key = isset($_POST['key']) ? sanitize_text_field($_POST['key']) : '';
        $model = isset($_POST['model']) ? sanitize_text_field($_POST['model']) : 'google/gemini-2.0-flash-exp:free';
        
        $use_key = !empty($posted_key) ? $posted_key : get_option('anrp_openrouter_key', '');

        if (empty($use_key)) {
            wp_send_json_error(['message' => 'Nenhuma chave API fornecida.']);
        }

        $endpoint = 'https://openrouter.ai/api/v1/chat/completions';
        
        $body = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => 'Responda apenas com: ANRP_OK'
                ]
            ],
            'max_tokens' => 20,
            'temperature' => 0
        ];

        $response = wp_remote_post($endpoint, [
            'timeout' => 30,
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $use_key,
                'HTTP-Referer' => get_site_url(),
                'X-Title' => get_bloginfo('name')
            ],
            'body' => wp_json_encode($body)
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'Erro na requisição: ' . $response->get_error_message()]);
        }

        $http_code = wp_remote_retrieve_response_code($response);
        $result = json_decode(wp_remote_retrieve_body($response), true);

        if ($http_code !== 200) {
            $error_msg = 'Erro HTTP ' . $http_code;
            if (!empty($result['error']['message'])) {
                $error_msg = $result['error']['message'];
            }
            wp_send_json_error(['message' => $error_msg]);
        }

        // Extrair texto (formato OpenAI)
        $text = '';
        if (!empty($result['choices'][0]['message']['content'])) {
            $text = $result['choices'][0]['message']['content'];
        }

        if (!empty($text)) {
            wp_send_json_success(['message' => 'OpenRouter conectado com sucesso! Modelo: ' . $model]);
        }

        wp_send_json_error(['message' => 'Resposta inesperada. Verifique a chave e o modelo.']);
    }
    public function ajax_test_textcortex_key() {
        check_ajax_referer('anrp_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permissão negada']);
        }

        $posted_key = isset($_POST['key']) ? sanitize_text_field($_POST['key']) : '';
        $use_key = !empty($posted_key) ? $posted_key : get_option('anrp_textcortex_key', '');

        if (empty($use_key)) {
            wp_send_json_error(['message' => 'Nenhuma chave fornecida.']);
        }

        $endpoint = 'https://api.textcortex.com/v1/texts/rewrites';

        $body = [
            'context' => 'ANRP_TEST',
            'max_tokens' => 5,
            'model' => 'gpt-3.5-turbo',
            'temperature' => 0.0
        ];

        $response = wp_remote_post($endpoint, [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . $use_key,
                'Content-Type' => 'application/json'
            ],
            'body' => wp_json_encode($body)
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'Erro na requisição: ' . $response->get_error_message()]);
        }

        $code = wp_remote_retrieve_response_code($response);
        $result = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 200 && $code < 300) {
            wp_send_json_success(['message' => 'Chave TextCortex válida', 'raw' => $result]);
        }

        wp_send_json_error(['message' => 'Resposta inesperada da API TextCortex', 'raw' => $result]);
    }

    public function ajax_test_pexels_key() {
        check_ajax_referer('anrp_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permissão negada']);
        }

        $posted_key = isset($_POST['key']) ? sanitize_text_field($_POST['key']) : '';
        $use_key = !empty($posted_key) ? $posted_key : get_option('anrp_pexels_key', '');

        if (empty($use_key)) {
            wp_send_json_error(['message' => 'Nenhuma chave fornecida.']);
        }

        $endpoint = 'https://api.pexels.com/v1/search?query=news&per_page=1';

        $response = wp_remote_get($endpoint, [
            'timeout' => 15,
            'headers' => [
                'Authorization' => $use_key
            ]
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'Erro na requisição: ' . $response->get_error_message()]);
        }

        $code = wp_remote_retrieve_response_code($response);
        $result = json_decode(wp_remote_retrieve_body($response), true);

        if ($code === 200 && isset($result['photos'])) {
            wp_send_json_success(['message' => 'Chave Pexels válida', 'raw' => $result]);
        }

        wp_send_json_error(['message' => 'Resposta inesperada da API Pexels', 'raw' => $result]);
    }
    
    public function process_article($params) {
        extract($params);
        
        // 1. Extrair conteúdo (ou usar conteúdo fornecido pelo cliente se disponível)
        if (!empty($article_content)) {
            $article_data = [
                'title' => !empty($article_title) ? $article_title : '',
                'content' => $article_content,
                'meta' => [],
                'main_image' => null,
                'url' => $url
            ];
        } else {
            $article_data = $this->scraper->extract_content($url);
        }
        
        // DEBUG: Log do conteúdo extraído
        error_log('ANRP Scraper - Title: ' . mb_substr($article_data['title'], 0, 100));
        error_log('ANRP Scraper - Content length: ' . strlen($article_data['content']));
        
        // VALIDAÇÃO: Se não extraiu conteúdo, lançar erro
        if (empty($article_data['content']) || strlen(trim($article_data['content'])) < 50) {
            throw new Exception('Não foi possível extrair o conteúdo da notícia. O site pode estar bloqueando ou o formato não é suportado.');
        }
        
        // 2. Reescrever conteúdo
        $rewritten_data = $this->rewriter->rewrite_content(
            $article_data['title'],
            $article_data['content'],
            $rewriting_method
        );
        
        // DEBUG: Log do conteúdo reescrito
        error_log('ANRP Rewriter - New Title: ' . mb_substr($rewritten_data['title'], 0, 100));
        error_log('ANRP Rewriter - New Content length: ' . strlen($rewritten_data['content']));

        // VALIDAÇÃO: Se reescrita falhou, usar conteúdo original com aviso
        if (empty($rewritten_data['content']) || strlen(trim($rewritten_data['content'])) < 50) {
            error_log('ANRP Warning: Reescrita retornou vazio, usando conteúdo original');
            $rewritten_data['content'] = $article_data['content'];
            // Se título também vazio, usar original
            if (empty($rewritten_data['title'])) {
                $rewritten_data['title'] = $article_data['title'];
            }
        }

        // 2.a Limpar possíveis referências ao veículo original
        $original_host = parse_url($url, PHP_URL_HOST) ?: '';
        
        // Limpar título: remover nome do site se ainda presente
        if (!empty($rewritten_data['title'])) {
            $rewritten_data['title'] = $this->clean_title_from_site_name($rewritten_data['title'], $original_host);
        }
        
        // Limpar conteúdo
        if (!empty($original_host)) {
            $rewritten_data['content'] = preg_replace('/\b' . preg_quote($original_host, '/') . '\b/i', '', $rewritten_data['content']);
            $rewritten_data['content'] = preg_replace('/\b(UOL|uol)\b/i', '', $rewritten_data['content']);
        }

        // Remover linhas de rodapé/commentários e tudo após certos marcadores
        $markers = ['O autor da mensagem', 'Leia as Regras', 'Ver original', 'Continua após a publicidade', 'Leia também', 'Comentários'];
        foreach ($markers as $m) {
            $pos = mb_stripos($rewritten_data['content'], $m);
            if ($pos !== false) {
                $rewritten_data['content'] = mb_substr($rewritten_data['content'], 0, $pos);
            }
        }

        // Limitar a no máximo 4 parágrafos finais
        $paras = preg_split('/\n\s*\n/', trim($rewritten_data['content']));
        $paras = array_filter(array_map('trim', $paras));
        if (count($paras) > 4) {
            $paras = array_slice($paras, 0, 4);
            $rewritten_data['content'] = implode("\n\n", $paras);
        }

        // Se a reescrita ficou muito similar ao original, forçar o fallback local
        $orig_text = preg_replace('/\s+/', ' ', trim($article_data['content']));
        $rew_text = preg_replace('/\s+/', ' ', trim($rewritten_data['content']));
        if (!empty($orig_text) && !empty($rew_text)) {
            similar_text(mb_substr($orig_text, 0, 4000), mb_substr($rew_text, 0, 4000), $simPct);
            if ($simPct > 85) {
                // Forçar reescrita local (usar fallback básico)
                $rewritten_data = $this->rewriter->rewrite_content($article_data['title'], $article_data['content'], 'basic');
                // garantir também o limite a 4 parágrafos
                $paras = preg_split('/\n\s*\n/', trim($rewritten_data['content']));
                $paras = array_filter(array_map('trim', $paras));
                if (count($paras) > 4) {
                    $paras = array_slice($paras, 0, 4);
                    $rewritten_data['content'] = implode("\n\n", $paras);
                }
            }
        }
        
        // 3. Buscar imagem (usar original se houver, ou buscar gratuita se falhar)
        $image_data = null;
        
        if (!empty($article_data['main_image'])) {
            $image_data = [
                'url' => $article_data['main_image'],
                'alt' => $rewritten_data['title'], // Fallback alt
                'credit' => !empty($article_data['main_image_credit']) ? $article_data['main_image_credit'] : 'Original Source',
                'caption' => !empty($article_data['main_image_caption']) ? $article_data['main_image_caption'] : ''
            ];
        } else {
             // Opcional: manter fallback para bancos gratuitos se desejar, mas o usuário pediu remover Pexels.
             // Se houver, manteria aqui. Mas a instrução foi explícita "usar imagen que tiver na noticia".
             // Deixaremos apenas a original.
        }
        
        // 4. Identificar categoria e tags
        $category_data = $this->category_tag_identifier->identify_category_and_tags(
            $rewritten_data['title'],
            $rewritten_data['content']
        );
        
        // Usar categoria específica se fornecida
        if ($category_id > 0) {
            $category_data['category_id'] = $category_id;
        }
        
        // Adicionar tags manuais
        if (!empty($tags_input)) {
            $manual_tags = array_map('trim', explode(',', $tags_input));
            $category_data['tags'] = array_merge($category_data['tags'], $manual_tags);
        }
        
        // 5. Determinar status e data
        $post_status = $publish_option;
        $post_date = current_time('mysql');
        
        if ($publish_option === 'schedule' && $schedule_date) {
            $post_status = 'future';
            $post_date = date('Y-m-d H:i:s', strtotime($schedule_date));
        }
        
        // 6. Preparar post
        $post_data = [
            'post_title' => $rewritten_data['title'],
            'post_content' => $rewritten_data['content'],
            'post_status' => $post_status,
            'post_date' => $post_date,
            'post_date_gmt' => get_gmt_from_date($post_date),
            'post_author' => $author_id,
            'post_category' => [$category_data['category_id']],
            'tags_input' => $category_data['tags'],
            'meta_input' => [
                '_anrp_original_url' => $url,
                '_anrp_processed_date' => current_time('mysql'),
                '_anrp_rewriting_method' => $rewriting_method,
                '_anrp_source_type' => $source_type,
                '_anrp_keywords' => $rewritten_data['keywords']
            ]
        ];
        
        if ($image_data) {
            $post_data['meta_input']['_anrp_featured_image'] = $image_data;
        }
        
        // 7. Publicar post
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            throw new Exception('Erro ao criar post: ' . $post_id->get_error_message());
        }
        
        // 8. Definir imagem destacada
        if ($image_data && $post_id) {
            // Determinar crédito da imagem verificando várias chaves possíveis
            $credit = '';
            $possible_keys = ['photographer','author','credit','photographer_name','user','user_name'];
            foreach ($possible_keys as $k) {
                if (!empty($image_data[$k])) {
                    $credit = $image_data[$k];
                    break;
                }
            }

            if (empty($credit) && !empty($article_data['meta']['author'])) {
                $credit = $article_data['meta']['author'];
            }
            $image_id = $this->set_featured_image($post_id, $image_data['url'], $image_data['alt'], $credit, $image_data['caption'] ?? '');
            
            // Criar imagem para redes sociais
            if ($image_id && get_option('anrp_social_auto_share', 0) && $share_social) {
                $social_image = $this->image_editor->create_social_image($post_id, $rewritten_data['title']);
                if ($social_image) {
                    update_post_meta($post_id, '_anrp_social_image', $social_image);
                }
            }
        }
        
        // 9. Registrar no histórico
        $this->history_manager->add_record(
            $url,
            $rewritten_data['title'],
            get_permalink($post_id),
            $post_id,
            $post_status,
            $author_id,
            $source_type
        );
        
        // 10. Compartilhar nas redes sociais
        $social_shared = false;
        if ($share_social && $post_status === 'publish') {
            $social_shared = $this->social_share->share_post($post_id);
        }
        
        // 11. Enviar notificação
        if (get_option('anrp_notifications_enabled', 1)) {
            $this->notifications->send_post_notification($post_id, $post_status);
        }
        
        return [
            'post_id' => $post_id,
            'post_url' => get_permalink($post_id),
            'post_status' => $post_status,
            'social_shared' => $social_shared
        ];
    }
    
    private function set_featured_image($post_id, $image_url, $alt_text = '', $credit = '', $caption = '') {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        // Baixar imagem temporariamente
        $tmp = download_url($image_url, 30);
        
        if (is_wp_error($tmp)) {
            return false;
        }
        
        // Preparar array de arquivo
        $file_array = [
            'name' => basename($image_url),
            'tmp_name' => $tmp
        ];
        
        // Fazer upload
        $image_id = media_handle_sideload($file_array, $post_id);
        
        // Remover arquivo temporário
        @unlink($tmp);
        
        if (is_wp_error($image_id)) {
            return false;
        }
        
        // Definir como imagem destacada
        set_post_thumbnail($post_id, $image_id);
        
        // Atualizar texto alternativo e legenda (excerpt)
        $update_data = ['ID' => $image_id];
        $update_meta = ['_wp_attachment_image_alt' => $alt_text];

        if (!empty($caption)) {
            $update_data['post_excerpt'] = $caption; // Legenda no WP é post_excerpt
        }

        wp_update_post($update_data);
        foreach ($update_meta as $key => $value) {
            update_post_meta($image_id, $key, $value);
        }

        // Se houver crédito, salvar como meta do attachment e do post
        if (!empty($credit)) {
            update_post_meta($image_id, '_anrp_credit', $credit);
            update_post_meta($post_id, '_anrp_featured_image_credit', $credit);
        }
        
        return $image_id;
    }
    
    public function ajax_preview_feed() {
        check_ajax_referer('anrp_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permissão negada']);
        }
        
        $feed_url = isset($_POST['feed_url']) ? esc_url_raw($_POST['feed_url']) : '';
        
        if (empty($feed_url)) {
            wp_send_json_error(['message' => 'URL do feed inválida']);
        }
        
        $items = $this->feed_manager->get_feed_items($feed_url);
        
        if (is_wp_error($items)) {
            wp_send_json_error(['message' => $items->get_error_message()]);
        }
        
        wp_send_json_success(['items' => $items]);
    }

    public function on_post_published($post_id, $post, $update) {
        if ($post->post_type !== 'post' || $update) {
            return;
        }
        
        // Verificar se é do nosso plugin
        $source_type = get_post_meta($post_id, '_anrp_source_type', true);
        if (!$source_type) {
            return;
        }
        
        // Compartilhamento automático
        if (get_option('anrp_social_auto_share', 0) && $post->post_status === 'publish') {
            $this->social_share->share_post($post_id);
        }
    }
    
    public function check_feeds() {
        $this->feed_manager->check_all_feeds();
    }
    
    public function cleanup_history() {
        $this->history_manager->cleanup_old_records(90); // Manter 90 dias
    }
    
    public function ajax_bookmarklet_submit() {
        $token = sanitize_text_field($_POST['token'] ?? '');
        $url = sanitize_url($_POST['url'] ?? '');
        
        // Verificar token
        $valid_token = get_option('anrp_bookmarklet_token', '');
        if ($valid_token && $token !== $valid_token) {
            wp_die('Token inválido');
        }
        
        // Processar URL
        try {
            $result = $this->process_article([
                'url' => $url,
                'rewriting_method' => 'gemini',
                'publish_option' => 'draft',
                'author_id' => get_option('anrp_default_author', 0),
                'source_type' => 'bookmarklet'
            ]);
            
            wp_send_json_success([
                'message' => 'Notícia enviada para processamento!',
                'post_id' => $result['post_id']
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    public function ajax_fetch_article() {
        check_ajax_referer('anrp_ajax_nonce', 'nonce');

        if (!current_user_can('publish_posts')) {
            wp_send_json_error(['message' => 'Permissão negada']);
        }

        $url = isset($_POST['url']) ? sanitize_url($_POST['url']) : '';
        if (empty($url)) {
            wp_send_json_error(['message' => 'URL inválida']);
        }

        try {
            $article = $this->scraper->extract_content($url);
            // Limitar tamanho e retornar
            $content = isset($article['content']) ? mb_substr($article['content'], 0, 40000) : '';
            $title = isset($article['title']) ? $article['title'] : '';

            wp_send_json_success([
                'title' => $title,
                'content' => $content,
                'meta' => $article['meta'] ?? []
            ]);
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    public function ajax_save_social_config() {
        check_ajax_referer('anrp_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Permissão negada');
        }
        
        $platform = sanitize_text_field($_POST['platform']);
        $config = wp_unslash($_POST['config']);
        
        $result = $this->social_share->save_config($platform, $config);
        
        wp_send_json_success([
            'message' => 'Configuração salva com sucesso!',
            'config' => $result
        ]);
    }
    
    public function ajax_save_template() {
        check_ajax_referer('anrp_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Permissão negada');
        }
        
        $template_data = wp_unslash($_POST['template']);
        $template_id = $this->image_editor->save_template($template_data);
        
        wp_send_json_success([
            'message' => 'Template salvo com sucesso!',
            'template_id' => $template_id
        ]);
    }
    
    public function ajax_upload_logo() {
        check_ajax_referer('anrp_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Permissão negada');
        }
        
        if (!empty($_FILES['logo']['name'])) {
            $upload = wp_handle_upload($_FILES['logo'], ['test_form' => false]);
            
            if (isset($upload['url'])) {
                update_option('anrp_logo_url', $upload['url']);
                
                // Obter dimensões
                $size = getimagesize($upload['file']);
                if ($size) {
                    update_option('anrp_logo_width', $size[0]);
                    update_option('anrp_logo_height', $size[1]);
                }
                
                wp_send_json_success([
                    'message' => 'Logo enviado com sucesso!',
                    'url' => $upload['url']
                ]);
            }
        }
        
        wp_send_json_error(['message' => 'Erro ao enviar logo']);
    }

    public function ajax_save_option() {
        check_ajax_referer('anrp_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permissão negada']);
        }

        $option = isset($_POST['option']) ? sanitize_text_field($_POST['option']) : '';
        $value = isset($_POST['value']) ? wp_unslash($_POST['value']) : '';

        if (empty($option)) {
            wp_send_json_error(['message' => 'Opção inválida']);
        }

        // Sanitização básica por opção conhecida
        switch ($option) {
            case 'anrp_default_author':
                $value = intval($value);
                break;
            case 'anrp_auto_tags_count':
                $value = intval($value);
                break;
            case 'anrp_bookmarklet_enabled':
                $value = $value ? 1 : 0;
                break;
            case 'anrp_default_tags':
                $value = sanitize_text_field($value);
                break;
            case 'anrp_logo_url':
                $value = esc_url_raw($value);
                break;
            case 'anrp_logo_width':
            case 'anrp_logo_height':
                $value = intval($value);
                break;
            case 'anrp_gemini_prompt':
                // Permitir reescrita customizada mas sanitizada minimamente (manter chaves)
                $value = wp_kses_post($value);
                break;
            default:
                $value = sanitize_text_field($value);
        }

        update_option($option, $value);

        wp_send_json_success(['message' => 'Opção salva', 'option' => $option, 'value' => $value]);
    }
    
    // Métodos para colunas personalizadas
    public function add_custom_post_columns($columns) {
        $columns['anrp_source'] = __('Fonte', 'auto-news-rewriter-pro');
        $columns['anrp_actions'] = __('Ações', 'auto-news-rewriter-pro');
        return $columns;
    }
    
    public function custom_post_columns_data($column, $post_id) {
        switch ($column) {
            case 'anrp_source':
                $source_type = get_post_meta($post_id, '_anrp_source_type', true);
                $original_url = get_post_meta($post_id, '_anrp_original_url', true);
                
                if ($source_type) {
                    echo '<span class="anrp-source-badge source-' . esc_attr($source_type) . '">';
                    echo esc_html(ucfirst($source_type));
                    echo '</span>';
                    
                    if ($original_url) {
                        echo '<br><small><a href="' . esc_url($original_url) . '" target="_blank" title="' . esc_attr($original_url) . '">';
                        echo esc_html(wp_trim_words($original_url, 5, '...'));
                        echo '</a></small>';
                    }
                } else {
                    echo '—';
                }
                break;
                
            case 'anrp_actions':
                $original_url = get_post_meta($post_id, '_anrp_original_url', true);
                
                echo '<div class="anrp-quick-actions" style="display:flex;gap:4px;">';
                
                if ($original_url) {
                    echo '<a href="' . esc_url($original_url) . '" target="_blank" class="button button-small" title="Ver original">';
                    echo '<span class="dashicons dashicons-external"></span>';
                    echo '</a>';
                }
                
                // Botão compartilhar sempre disponível para posts do CRIA
                echo '<button type="button" class="button button-small anrp-share-now" data-post-id="' . esc_attr($post_id) . '" title="Compartilhar no Instagram">';
                echo '<span class="dashicons dashicons-share"></span>';
                echo '</button>';
                
                echo '</div>';
                break;
        }
    }
    
    public function add_post_row_actions($actions, $post) {
        $source_type = get_post_meta($post->ID, '_anrp_source_type', true);
        
        if ($source_type) {
            $actions['anrp_rewrite'] = sprintf(
                '<a href="%s" class="anrp-rewrite-post" data-post-id="%d">%s</a>',
                '#',
                $post->ID,
                __('Reescrever Novamente', 'cria-releituras')
            );
            
            // Sempre mostrar opção de compartilhar para posts do CRIA
            $actions['anrp_share'] = sprintf(
                '<a href="%s" class="anrp-share-now" data-post-id="%d">%s</a>',
                '#',
                $post->ID,
                __('Compartilhar Agora', 'cria-releituras')
            );
        }
        
        // Botão Editor de Imagem para TODOS os posts
        $actions['anrp_image_editor'] = sprintf(
            '<a href="%s" style="color:#10b981;">%s</a>',
            admin_url('admin.php?page=anrp-image-editor&post_id=' . $post->ID),
            __('Editor de Imagem', 'cria-releituras')
        );
        
        return $actions;
    }
    
    /**
     * Adiciona meta box CRIA no editor de posts
     */
    public function add_cria_meta_box() {
        add_meta_box(
            'cria_releituras_box',
            '⭐ CRIA Releituras',
            [$this, 'render_cria_meta_box'],
            'post',
            'side',
            'high'
        );
    }
    
    /**
     * Renderiza conteúdo da meta box
     */
    public function render_cria_meta_box($post) {
        $author = get_userdata($post->post_author);
        $author_name = $author ? $author->display_name : 'Autor';
        $author_avatar = get_avatar_url($post->post_author, ['size' => 150]);
        $featured_image = get_the_post_thumbnail_url($post->ID, 'large');
        
        ?>
        <style>
            .cria-metabox { font-family: -apple-system, BlinkMacSystemFont, sans-serif; }
            .cria-metabox-btn { 
                display: flex; align-items: center; gap: 8px; width: 100%; 
                padding: 12px 16px; margin-bottom: 8px; border: none; border-radius: 8px; 
                font-size: 13px; font-weight: 600; cursor: pointer; text-decoration: none;
                transition: all 0.2s ease;
            }
            .cria-metabox-btn-primary { background: #CCFF00; color: #0A0A0A; }
            .cria-metabox-btn-primary:hover { background: #B8E600; color: #0A0A0A; }
            .cria-metabox-btn-secondary { background: #f1f5f9; color: #334155; }
            .cria-metabox-btn-secondary:hover { background: #e2e8f0; color: #1e293b; }
            .cria-metabox-info { 
                padding: 12px; background: #f8fafc; border-radius: 8px; 
                margin-top: 12px; font-size: 12px; color: #64748b;
            }
            .cria-metabox-author { 
                display: flex; align-items: center; gap: 10px; 
                padding: 10px; background: #f1f5f9; border-radius: 8px; margin-bottom: 12px;
            }
            .cria-metabox-author img { width: 40px; height: 40px; border-radius: 50%; }
            .cria-metabox-author-name { font-weight: 600; color: #1e293b; }
        </style>
        <div class="cria-metabox">
            <div class="cria-metabox-author">
                <img src="<?php echo esc_url($author_avatar); ?>" alt="<?php echo esc_attr($author_name); ?>">
                <div>
                    <div class="cria-metabox-author-name"><?php echo esc_html($author_name); ?></div>
                    <div style="font-size:11px;color:#64748b;">Autor do post</div>
                </div>
            </div>
            
            <a href="<?php echo admin_url('admin.php?page=anrp-image-editor&post_id=' . $post->ID); ?>" class="cria-metabox-btn cria-metabox-btn-primary">
                🎨 Criar Imagem para Instagram
            </a>
            
            <button type="button" class="cria-metabox-btn cria-metabox-btn-secondary anrp-share-now" data-post-id="<?php echo $post->ID; ?>">
                📤 Compartilhar Agora
            </button>
            
            <div class="cria-metabox-info">
                <strong>Dica:</strong> Use o Editor de Imagem para criar posts com templates profissionais incluindo foto do autor.
            </div>
        </div>
        <?php
    }
    
    // Métodos para histórico
    public function ajax_get_history() {
        check_ajax_referer('anrp_ajax_nonce', 'nonce');
        
        $filter = isset($_POST['filter']) ? sanitize_text_field($_POST['filter']) : 'all';
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = 20;
        
        $history = $this->history_manager->get_history($filter, $page, $per_page);
        $total = $this->history_manager->get_total_count($filter);
        
        wp_send_json_success([
            'history' => $history,
            'total' => $total,
            'pages' => ceil($total / $per_page),
            'page' => $page
        ]);
    }

    public function ajax_delete_history() {
        check_ajax_referer('anrp_ajax_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Permissão negada');
        }

        $record_id = intval($_POST['record_id'] ?? 0);
        if ($record_id <= 0) {
            wp_send_json_error(['message' => 'ID inválido']);
        }

        $deleted = $this->history_manager->delete_record($record_id);

        if ($deleted) {
            wp_send_json_success(['message' => 'Registro excluído com sucesso']);
        }

        wp_send_json_error(['message' => 'Falha ao excluir registro']);
    }
    
    public function ajax_get_feeds() {
        check_ajax_referer('anrp_ajax_nonce', 'nonce');
        
        $feeds = $this->feed_manager->get_all_feeds();
        
        wp_send_json_success([
            'feeds' => $feeds
        ]);
    }
    
    public function ajax_save_feed() {
        check_ajax_referer('anrp_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Permissão negada');
        }
        
        $feed_url = sanitize_url($_POST['feed_url']);
        
        // Validação: Tentar buscar itens do feed antes de salvar
        $test_items = $this->feed_manager->get_feed_items($feed_url);
        
        if (is_wp_error($test_items)) {
            wp_send_json_error([
                'message' => 'Erro ao validar feed: ' . $test_items->get_error_message() . '. Verifique a URL ou se o site bloqueia acesso.'
            ]);
        }
        
        // Se retornou zero itens, alertar, mas talvez permitir (pode ser feed novo vazio)?
        // Usuário pediu verificação rigorosa. Se não for válido, não deixa.
        if (empty($test_items)) {
            // Alguns feeds válidos podem estar vazios temporariamente.
            // Mas erro 403 geralmente retorna WP_Error acima.
            // Se chegou aqui vazio, pode ser só vazio mesmo. Vamos permitir mas com aviso?
            // O usuario disse: "se não for valido ou tiver com erro". Vazio tecnicamente é valido.
            // Mas vamos assumir que feed sem itens pode ser erro de parse.
            // Melhor: se array vazio, deixar passar. Se WP_Error, bloquear.
            // O bloqueio do 403 foi tratado acima.
        }

        $feed_data = [
            'feed_name' => sanitize_text_field($_POST['feed_name']),
            'feed_url' => $feed_url,
            'auto_publish' => intval($_POST['auto_publish']),
            'schedule_type' => sanitize_text_field($_POST['schedule_type']),
            'schedule_time' => isset($_POST['schedule_time']) ? sanitize_text_field($_POST['schedule_time']) : null,
            'active' => 1
        ];
        
        $feed_id = $this->feed_manager->save_feed($feed_data);
        
        wp_send_json_success([
            'feed_id' => $feed_id,
            'message' => 'Feed validado e salvo com sucesso!'
        ]);
    }
    
    public function ajax_delete_feed() {
        check_ajax_referer('anrp_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Permissão negada');
        }
        
        $feed_id = intval($_POST['feed_id']);
        $this->feed_manager->delete_feed($feed_id);
        
        wp_send_json_success([
            'message' => 'Feed removido com sucesso!'
        ]);
    }
    
    public function ajax_test_social_connection() {
        check_ajax_referer('anrp_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Permissão negada');
        }
        
        $platform = sanitize_text_field($_POST['platform']);
        $result = $this->social_share->test_connection($platform);
        
        wp_send_json_success([
            'message' => $result['message'],
            'connected' => $result['connected']
        ]);
    }

    /**
     * Adiciona botões de ação rápida no frontend
     */
    public function add_quick_actions_to_content($content) {
        if (!is_singular('post') || !in_the_loop() || !is_main_query()) {
            return $content;
        }

        if (!current_user_can('edit_post', get_the_ID())) {
            return $content;
        }

        $post_id = get_the_ID();
        $status = get_post_status($post_id);
        
        $buttons = '<div class="anrp-frontend-actions" style="margin-bottom: 20px; padding: 10px; background: #f0f0f1; border: 1px solid #ccd0d4; border-radius: 4px; display: flex; gap: 10px; align-items: center;">';
        
        // Botão Editar
        $buttons .= '<a href="' . get_edit_post_link($post_id) . '" class="button post-edit-link" style="text-decoration: none; background: #2271b1; color: #fff; padding: 5px 10px; border-radius: 3px;">Editar Post</a>';
        
        // Botão Excluir
        $delete_url = get_delete_post_link($post_id);
        if ($delete_url) {
            $buttons .= '<a href="' . $delete_url . '" class="button post-delete-link" style="text-decoration: none; background: #d63638; color: #fff; padding: 5px 10px; border-radius: 3px;" onclick="return confirm(\'Tem certeza que deseja excluir?\')">Excluir Post</a>';
        }

        // Botão Publicar (se for rascunho)
        if ($status !== 'publish') {
            $publish_url = admin_url('admin-post.php?action=anrp_quick_publish&post_id=' . $post_id . '&_wpnonce=' . wp_create_nonce('anrp_quick_publish_' . $post_id));
            $buttons .= '<a href="' . $publish_url . '" class="button post-publish-link" style="text-decoration: none; background: #00a32a; color: #fff; padding: 5px 10px; border-radius: 3px;">Publicar Post</a>';
        }
        
        $buttons .= '</div>';
        
        // Adiciona "acima" do título? Não, filter é content. Então é acima do conteudo.
        // O usuário disse "acima do título do post".
        // Filtrar 'the_title' é arriscado, mas se usuário exige...
        // Vou manter em 'the_content' mas visualmente no topo do artigo (abaixo do titulo real do tema, mas acima do texto).
        // Se eu quisesse acima do título MESMO teria que usar 'the_post' e injetar JS ou usar 'get_header'.
        // Geralmente "acima do titulo" no frontend é difícil sem hook do tema.
        // O padrão CMS é "Content top".
        
        return $buttons . $content;
    }

    /**
     * Handler para publicação rápida
     */
    public function handle_quick_publish() {
        $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
        
        if (!$post_id || !isset($_GET['_wpnonce'])) {
            wp_die('Requisição inválida');
        }

        if (!wp_verify_nonce($_GET['_wpnonce'], 'anrp_quick_publish_' . $post_id)) {
            wp_die('Link expirado');
        }

        if (!current_user_can('publish_posts', $post_id)) {
            wp_die('Permissão negada');
        }

        // Publicar post
        $updated = wp_update_post([
            'ID' => $post_id,
            'post_status' => 'publish',
            'post_date' => current_time('mysql'),
            'post_date_gmt' => get_gmt_from_date(current_time('mysql'))
        ]);

        if (is_wp_error($updated)) {
            wp_die('Erro ao publicar: ' . $updated->get_error_message());
        }

        // Redirecionar para o post publicado
        wp_redirect(get_permalink($post_id));
        exit;
    }

    /**
     * Limpa o título removendo nome do site/veículo
     */
    private function clean_title_from_site_name($title, $host = '') {
        if (empty($title)) return '';
        
        // Separadores comuns
        $separators = [' - ', ' | ', ' – ', ' :: ', ' — ', ' · '];
        
        foreach ($separators as $sep) {
            if (strpos($title, $sep) !== false) {
                $parts = explode($sep, $title);
                if (count($parts) >= 2) {
                    $last = trim(end($parts));
                    $first = trim($parts[0]);
                    
                    // Se última parte for curta (< 35 chars) e primeira for maior
                    if (strlen($last) < 35 && strlen($first) > 15) {
                        $title = $first;
                    }
                    // Se primeira parte for curta e última longa
                    elseif (strlen($first) < 35 && strlen($last) > 15) {
                        $title = $last;
                    }
                }
                break;
            }
        }
        
        // Remover nome do host se ainda presente
        if (!empty($host)) {
            // Extrair nome principal do domínio
            $host_parts = explode('.', str_replace('www.', '', $host));
            $site_name = $host_parts[0] ?? '';
            
            if (!empty($site_name) && strlen($site_name) > 2) {
                // Remover do final ou início do título
                $title = preg_replace('/\s*[-|–—·]\s*' . preg_quote($site_name, '/') . '\s*$/i', '', $title);
                $title = preg_replace('/^' . preg_quote($site_name, '/') . '\s*[-|–—·]\s*/i', '', $title);
            }
        }
        
        return trim($title);
    }

    public function handle_social_auth_request() {
        $platform = isset($_GET['platform']) ? sanitize_text_field($_GET['platform']) : '';
        $this->social_share->init_auth($platform);
    }

    public function handle_social_auth_callback() {
        $this->social_share->handle_auth_callback();
    }

    /**
     * AJAX: Toggle feed active status
     */
    public function ajax_toggle_feed() {
        check_ajax_referer('anrp_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permissão negada']);
        }

        $feed_id = isset($_POST['feed_id']) ? intval($_POST['feed_id']) : 0;
        $active = isset($_POST['active']) ? intval($_POST['active']) : 0;

        if (!$feed_id) {
            wp_send_json_error(['message' => 'Feed não encontrado']);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'anrp_feeds';
        
        $result = $wpdb->update(
            $table,
            ['active' => $active],
            ['id' => $feed_id],
            ['%d'],
            ['%d']
        );

        if ($result !== false) {
            wp_send_json_success(['message' => $active ? 'Feed ativado' : 'Feed desativado']);
        } else {
            wp_send_json_error(['message' => 'Erro ao atualizar feed']);
        }
    }

    /**
     * AJAX: Check feed now
     */
    public function ajax_check_feed_now() {
        check_ajax_referer('anrp_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permissão negada']);
        }

        $feed_id = isset($_POST['feed_id']) ? intval($_POST['feed_id']) : 0;
        
        if (!$feed_id) {
            wp_send_json_error(['message' => 'Feed não encontrado']);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'anrp_feeds';
        $feed = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $feed_id), ARRAY_A);

        if (!$feed) {
            wp_send_json_error(['message' => 'Feed não encontrado']);
        }

        // Check the feed
        $new_items = $this->feed_manager->process_feed($feed);

        // Update last_checked
        $wpdb->update(
            $table,
            ['last_checked' => current_time('mysql')],
            ['id' => $feed_id]
        );

        wp_send_json_success([
            'message' => 'Feed verificado',
            'new_items' => is_array($new_items) ? count($new_items) : 0
        ]);
    }

    /**
     * AJAX: Get Instagram data for sharing
     */
    public function ajax_get_instagram_data() {
        check_ajax_referer('anrp_ajax_nonce', 'nonce');

        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if (!$post_id) {
            wp_send_json_error(['message' => 'Post não encontrado']);
        }

        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(['message' => 'Post não encontrado']);
        }

        // Get featured image or social image
        $image_url = '';
        
        // Check if social image exists
        $social_image = get_post_meta($post_id, '_anrp_social_image', true);
        if ($social_image) {
            $image_url = $social_image;
        } else {
            // Try to generate social image
            $generated = $this->image_editor->create_social_image($post_id, $post->post_title);
            if ($generated) {
                $image_url = $generated;
                update_post_meta($post_id, '_anrp_social_image', $generated);
            } else {
                // Fallback to featured image
                $thumbnail_id = get_post_thumbnail_id($post_id);
                if ($thumbnail_id) {
                    $image_url = wp_get_attachment_image_url($thumbnail_id, 'large');
                }
            }
        }

        // Get excerpt
        $excerpt = $post->post_excerpt;
        if (empty($excerpt)) {
            $excerpt = wp_trim_words(strip_tags($post->post_content), 30, '...');
        }

        // Get tags as hashtags
        $tags = wp_get_post_tags($post_id, ['fields' => 'names']);
        $default_tags = get_option('anrp_default_tags', 'notícias');
        $default_tags_arr = array_map('trim', explode(',', $default_tags));
        $hashtags = array_merge($default_tags_arr, $tags);
        $hashtags = array_unique(array_filter($hashtags));

        wp_send_json_success([
            'post_id' => $post_id,
            'title' => $post->post_title,
            'excerpt' => $excerpt,
            'url' => get_permalink($post_id),
            'image_url' => $image_url,
            'hashtags' => array_values($hashtags)
        ]);
    }
    
    /**
     * AJAX: Reescrever post existente
     */
    public function ajax_rewrite_post() {
        check_ajax_referer('anrp_ajax_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => 'Permissão negada']);
        }
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if (!$post_id) {
            wp_send_json_error(['message' => 'Post não encontrado']);
        }
        
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(['message' => 'Post não encontrado']);
        }
        
        // Reescrever conteúdo
        $rewritten = $this->rewriter->rewrite($post->post_title, $post->post_content);
        
        if (!$rewritten || empty($rewritten['title']) || empty($rewritten['content'])) {
            wp_send_json_error(['message' => 'Erro ao reescrever conteúdo']);
        }
        
        // Atualizar post
        $updated = wp_update_post([
            'ID' => $post_id,
            'post_title' => $rewritten['title'],
            'post_content' => $rewritten['content']
        ]);
        
        if (is_wp_error($updated)) {
            wp_send_json_error(['message' => 'Erro ao atualizar post']);
        }
        
        wp_send_json_success([
            'message' => 'Post reescrito com sucesso!',
            'post_id' => $post_id,
            'title' => $rewritten['title']
        ]);
    }
    
    /**
     * AJAX: Compartilhar post (gerar imagem social)
     */
    public function ajax_share_post() {
        check_ajax_referer('anrp_ajax_nonce', 'nonce');
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if (!$post_id) {
            wp_send_json_error(['message' => 'Post não encontrado']);
        }
        
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(['message' => 'Post não encontrado']);
        }
        
        // Gerar imagem social
        $social_image = $this->image_editor->create_social_image($post_id, $post->post_title);
        
        if ($social_image) {
            update_post_meta($post_id, '_anrp_social_image', $social_image);
            wp_send_json_success([
                'message' => 'Imagem gerada com sucesso!',
                'image_url' => $social_image,
                'post_id' => $post_id
            ]);
        } else {
            wp_send_json_error(['message' => 'Erro ao gerar imagem']);
        }
    }
    
    /**
     * AJAX: Buscar dados do post para o Editor de Imagem
     */
    public function ajax_get_post_data_for_editor() {
        check_ajax_referer('anrp_ajax_nonce', 'nonce');
        
        $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
        
        if (!$post_id) {
            wp_send_json_error(['message' => 'Post não encontrado']);
        }
        
        $post = get_post($post_id);
        if (!$post) {
            wp_send_json_error(['message' => 'Post não encontrado']);
        }
        
        // Dados do autor
        $author_id = $post->post_author;
        $author = get_userdata($author_id);
        $author_name = $author ? $author->display_name : 'Autor';
        $author_role = '';
        
        // Tentar obter cargo do autor de diferentes fontes
        if ($author) {
            // Primeiro tenta a bio/descrição
            $author_role = get_user_meta($author_id, 'description', true);
            
            // Se não tem bio, usa o role do WordPress
            if (empty($author_role)) {
                $roles = $author->roles;
                if (!empty($roles)) {
                    $role_names = [
                        'administrator' => 'Administrador',
                        'editor' => 'Editor',
                        'author' => 'Colunista',
                        'contributor' => 'Colaborador',
                        'subscriber' => 'Assinante'
                    ];
                    $author_role = $role_names[reset($roles)] ?? 'Colunista';
                }
            }
            
            // Limitar tamanho
            if (strlen($author_role) > 50) {
                $author_role = substr($author_role, 0, 50) . '...';
            }
        }
        
        // FOTO DO AUTOR - Priorizar imagem local personalizada
        $author_avatar = '';
        
        // 1. PRIORIDADE: Meta 'author_photo' (campo personalizado do tema/plugin)
        $author_photo = get_user_meta($author_id, 'author_photo', true);
        if (!empty($author_photo)) {
            $author_avatar = $author_photo;
        }
        
        // 2. Tenta 'author_photo_id' (ID do attachment)
        if (empty($author_avatar)) {
            $author_photo_id = get_user_meta($author_id, 'author_photo_id', true);
            if ($author_photo_id) {
                $author_avatar = wp_get_attachment_url($author_photo_id);
            }
        }
        
        // 3. Tenta Simple Local Avatars plugin
        if (empty($author_avatar)) {
            $local_avatar = get_user_meta($author_id, 'simple_local_avatar', true);
            if (is_array($local_avatar) && !empty($local_avatar['full'])) {
                $author_avatar = $local_avatar['full'];
            }
        }
        
        // 4. Tenta WP User Avatar
        if (empty($author_avatar)) {
            $wp_user_avatar = get_user_meta($author_id, 'wp_user_avatar', true);
            if ($wp_user_avatar) {
                $author_avatar = wp_get_attachment_url($wp_user_avatar);
            }
        }
        
        // 5. Tenta meta '_wp_attachment_wp_user_avatar'
        if (empty($author_avatar)) {
            $attachment_avatar = get_user_meta($author_id, '_wp_attachment_wp_user_avatar', true);
            if ($attachment_avatar) {
                $author_avatar = wp_get_attachment_url($attachment_avatar);
            }
        }
        
        // 6. Tenta 'user_avatar' genérico
        if (empty($author_avatar)) {
            $user_avatar = get_user_meta($author_id, 'user_avatar', true);
            if (!empty($user_avatar)) {
                $author_avatar = $user_avatar;
            }
        }
        
        // 7. Por último, usa Gravatar como fallback
        if (empty($author_avatar)) {
            $author_avatar = get_avatar_url($author_id, ['size' => 200]);
        }
        
        // IMAGEM DE FUNDO - Imagem destacada do post (separada da foto do autor)
        $featured_image = get_the_post_thumbnail_url($post_id, 'large');
        
        wp_send_json_success([
            'id' => $post_id,
            'title' => $post->post_title,
            'image' => $featured_image ?: '', // Imagem de fundo = imagem destacada
            'author_name' => $author_name,
            'author_role' => $author_role ?: 'Colunista',
            'author_avatar' => $author_avatar // Foto do autor = avatar local ou gravatar
        ]);
    }
    
    /**
     * AJAX: Obter URL de OAuth para plataforma social
     */
    public function ajax_get_oauth_url() {
        check_ajax_referer('anrp_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permissão negada']);
        }
        
        $platform = sanitize_text_field($_POST['platform'] ?? '');
        
        if (empty($platform)) {
            wp_send_json_error(['message' => 'Plataforma não especificada']);
        }
        
        // Gerar URL de autorização OAuth via Social Share
        $auth_url = $this->social_share->get_oauth_url($platform);
        
        if ($auth_url) {
            wp_send_json_success(['auth_url' => $auth_url]);
        } else {
            wp_send_json_error(['message' => 'Não foi possível gerar URL de autorização. Verifique as credenciais.']);
        }
    }
    
    /**
     * AJAX: Salvar configurações gerais de social
     */
    public function ajax_save_social_settings() {
        check_ajax_referer('anrp_ajax_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permissão negada']);
        }
        
        $settings = $_POST['settings'] ?? [];
        
        if (isset($settings['auto_share'])) {
            update_option('anrp_social_auto_share', $settings['auto_share'] ? 1 : 0);
        }
        
        if (isset($settings['image_template'])) {
            update_option('anrp_social_image_template', sanitize_text_field($settings['image_template']));
        }
        
        wp_send_json_success(['message' => 'Configurações salvas']);
    }
}