<?php
/**
 * Plugin Name: CRIA Releituras Enhanced
 * Plugin URI: https://cria.sa
 * Description: Sistema avançado de curadoria e reescrita de notícias com IA, anti-bloqueio, múltiplos formatos (HTML/RSS/JSON), gerenciamento de modelos IA, OAuth, processamento em lote, analytics e muito mais - Desenvolvido por CRIA S/A
 * Version: 4.0.0
 * Author: CRIA S/A - Desenvolvido para Joildo Santos
 * Author URI: https://cria.sa
 * License: GPL v2 or later
 * Text Domain: cria-releituras
 * Domain Path: /languages
 */

// Previne acesso direto
if (!defined('ABSPATH')) {
    exit;
}

// Define constantes do plugin
define('ANRP_VERSION', '4.0.0');
define('ANRP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ANRP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ANRP_UPLOAD_DIR', wp_upload_dir()['basedir'] . '/cria-releituras/');
define('ANRP_UPLOAD_URL', wp_upload_dir()['baseurl'] . '/cria-releituras/');

// Criar diretório de uploads
if (!file_exists(ANRP_UPLOAD_DIR)) {
    wp_mkdir_p(ANRP_UPLOAD_DIR);
}

// Carregar classes
$anrp_classes = [
    'ANRP_Core',
    'ANRP_Scraper',
    'ANRP_Free_Rewriter',
    'ANRP_Free_Image_Finder',
    'ANRP_Category_Tag_Identifier',
    'ANRP_History_Manager',
    'ANRP_Feed_Manager',
    'ANRP_Notifications',
    'ANRP_Social_Share',
    'ANRP_Image_Editor',
    'ANRP_Bookmarklet',
    'ANRP_AI_Models_Manager',
    'ANRP_Batch_Processor',
    'ANRP_Analytics',
    'ANRP_Cache_Manager'
];

foreach ($anrp_classes as $class) {
    $file = ANRP_PLUGIN_DIR . 'includes/class-' . strtolower(str_replace('_', '-', $class)) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
}

// Inicializa o plugin
function anrp_init() {
    return ANRP_Core::get_instance();
}
add_action('plugins_loaded', 'anrp_init');

// Ativação do plugin
register_activation_hook(__FILE__, 'anrp_activate_plugin');

function anrp_activate_plugin() {
    global $wpdb;
    
    // Cria tabelas
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    // Tabela de histórico
    $table_name = $wpdb->prefix . 'anrp_history';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        original_url varchar(1000) NOT NULL,
        new_title varchar(500) NOT NULL,
        published_url varchar(1000),
        published_date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
        status varchar(20) DEFAULT 'processed',
        post_id bigint(20),
        author_id bigint(20),
        source_type varchar(50),
        PRIMARY KEY (id),
        KEY original_url (original_url(191)),
        KEY published_date (published_date),
        KEY status (status)
    ) $charset_collate;";
    
    dbDelta($sql);
    
    // Tabela de feeds RSS
    $feeds_table = $wpdb->prefix . 'anrp_feeds';
    
    $sql_feeds = "CREATE TABLE IF NOT EXISTS $feeds_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        feed_url varchar(1000) NOT NULL,
        feed_name varchar(200),
        feed_category varchar(100),
        last_checked datetime,
        last_hash varchar(100),
        active tinyint(1) DEFAULT 1,
        auto_publish tinyint(1) DEFAULT 0,
        category_id bigint(20),
        author_id bigint(20),
        schedule_type varchar(20) DEFAULT 'immediate',
        schedule_time time,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY feed_url (feed_url(191)),
        KEY active (active)
    ) $charset_collate;";
    
    dbDelta($sql_feeds);
    
    // Tabela de configurações de social media
    $social_table = $wpdb->prefix . 'anrp_social_config';
    
    $sql_social = "CREATE TABLE IF NOT EXISTS $social_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        platform varchar(50) NOT NULL,
        access_token text,
        access_token_secret text,
        user_id varchar(100),
        username varchar(100),
        expires_at datetime,
        config text,
        active tinyint(1) DEFAULT 0,
        last_used datetime,
        PRIMARY KEY (id),
        KEY platform (platform),
        UNIQUE KEY platform_user (platform, user_id(50))
    ) $charset_collate;";
    
    dbDelta($sql_social);
    
    // Tabela de templates de imagem
    $templates_table = $wpdb->prefix . 'anrp_templates';
    
    $sql_templates = "CREATE TABLE IF NOT EXISTS $templates_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        template_name varchar(100) NOT NULL,
        template_type varchar(50) DEFAULT 'social',
        config text,
        preview_url varchar(500),
        is_default tinyint(1) DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    dbDelta($sql_templates);
    
    // Tabela de analytics (NOVO v4.0)
    ANRP_Analytics::create_tables();
    
    // Criar template padrão
    $default_template = [
        'logo' => [
            'position' => ['x' => 20, 'y' => 20],
            'size' => ['width' => 100, 'height' => 50],
            'opacity' => 100
        ],
        'title' => [
            'position' => ['x' => 50, 'y' => 450],
            'font_size' => 32,
            'font_color' => '#ffffff',
            'background_color' => 'rgba(0,0,0,0.7)',
            'padding' => 20,
            'max_width' => 700,
            'align' => 'center'
        ],
        'watermark' => [
            'text' => get_bloginfo('name'),
            'position' => 'bottom-right',
            'font_size' => 12,
            'color' => 'rgba(255,255,255,0.5)'
        ]
    ];
    
    $existing = $wpdb->get_var("SELECT COUNT(*) FROM $templates_table WHERE is_default = 1");
    if (!$existing) {
        $wpdb->insert($templates_table, [
            'template_name' => 'Template Padrão',
            'template_type' => 'social',
            'config' => serialize($default_template),
            'is_default' => 1
        ]);
    }
    
    // Agenda verificação de feeds
    if (!wp_next_scheduled('anrp_check_feeds_hook')) {
        wp_schedule_event(time(), 'hourly', 'anrp_check_feeds_hook');
    }
    
    // Agenda limpeza de histórico
    if (!wp_next_scheduled('anrp_cleanup_history_hook')) {
        wp_schedule_event(time(), 'daily', 'anrp_cleanup_history_hook');
    }
    
    // Opções padrão
    add_option('anrp_version', ANRP_VERSION);
    add_option('anrp_default_author', 0);
    add_option('anrp_default_tags', 'ig, territorio');
    add_option('anrp_auto_tags_count', 5);
    add_option('anrp_default_status', 'draft');
    add_option('anrp_notifications_enabled', 1);
    add_option('anrp_social_auto_share', 0);
    add_option('anrp_logo_url', '');
    add_option('anrp_bookmarklet_enabled', 1);
    add_option('anrp_textcortex_key', '');
    add_option('anrp_pexels_key', '');
    add_option('anrp_pixabay_key', '');
    add_option('anrp_unsplash_key', '');
    
    // Criar usuário Joildo Santos se não existir
    anrp_create_default_author();
    
    // IMPORTANTE: Flush rewrite rules para registrar o endpoint do bookmarklet
    flush_rewrite_rules();
}

function anrp_create_default_author() {
    $username = 'joildosantos';
    $user = get_user_by('login', $username);
    
    if (!$user) {
        $user_id = wp_create_user($username, wp_generate_password(), 'joildo@seusite.com');
        
        if (!is_wp_error($user_id)) {
            wp_update_user([
                'ID' => $user_id,
                'display_name' => 'Joildo Santos',
                'first_name' => 'Joildo',
                'last_name' => 'Santos',
                'role' => 'author'
            ]);
            
            // Atualizar opção padrão
            update_option('anrp_default_author', $user_id);
        }
    } else {
        update_option('anrp_default_author', $user->ID);
    }
}

// Desativação do plugin
register_deactivation_hook(__FILE__, 'anrp_deactivate_plugin');

function anrp_deactivate_plugin() {
    wp_clear_scheduled_hook('anrp_check_feeds_hook');
    wp_clear_scheduled_hook('anrp_cleanup_history_hook');
    
    // IMPORTANTE: Flush rewrite rules ao desativar
    flush_rewrite_rules();
}

// Carregar traduções
function anrp_load_textdomain() {
    load_plugin_textdomain('auto-news-rewriter-pro', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'anrp_load_textdomain');

// Adicionar link de configurações na lista de plugins
function anrp_plugin_action_links($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=anrp-settings') . '">' . __('Configurações', 'auto-news-rewriter-pro') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'anrp_plugin_action_links');

// Adicionar meta links
function anrp_plugin_meta_links($links, $file) {
    if (plugin_basename(__FILE__) === $file) {
        $links[] = '<a href="https://seusite.com/documentacao" target="_blank">' . __('Documentação', 'auto-news-rewriter-pro') . '</a>';
        $links[] = '<a href="https://seusite.com/suporte" target="_blank">' . __('Suporte', 'auto-news-rewriter-pro') . '</a>';
    }
    return $links;
}
add_filter('plugin_row_meta', 'anrp_plugin_meta_links', 10, 2);

// Função para regenerar rewrite rules quando necessário
function anrp_maybe_flush_rewrite_rules() {
    $version = get_option('anrp_version', '');
    if ($version !== ANRP_VERSION) {
        flush_rewrite_rules();
        update_option('anrp_version', ANRP_VERSION);
    }
}
add_action('admin_init', 'anrp_maybe_flush_rewrite_rules');