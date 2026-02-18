<?php
// Se for chamado diretamente, abortar
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Opções para excluir
$options = [
    'anrp_version',
    'anrp_default_author',
    'anrp_default_tags',
    'anrp_auto_tags_count',
    'anrp_default_status',
    'anrp_notifications_enabled',
    'anrp_social_auto_share',
    'anrp_logo_url',
    'anrp_bookmarklet_enabled',
    'anrp_textcortex_key',
    'anrp_pexels_key',
    'anrp_pixabay_key',
    'anrp_unsplash_key',
    'anrp_logo_width',
    'anrp_logo_height',
    'anrp_pending_notifications'
];

// Excluir opções
foreach ($options as $option) {
    delete_option($option);
}

// Excluir tabelas do banco de dados
global $wpdb;

$tables = [
    $wpdb->prefix . 'anrp_history',
    $wpdb->prefix . 'anrp_feeds',
    $wpdb->prefix . 'anrp_social_config',
    $wpdb->prefix . 'anrp_templates'
];

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS $table");
}

// Excluir meta posts
$wpdb->query("DELETE FROM $wpdb->postmeta WHERE meta_key LIKE '_anrp_%'");

// Limpar agendamentos
wp_clear_scheduled_hook('anrp_check_feeds_hook');
wp_clear_scheduled_hook('anrp_cleanup_history_hook');

// Excluir diretório de uploads
$upload_dir = wp_upload_dir();
$anrp_dir = $upload_dir['basedir'] . '/anrp/';

if (file_exists($anrp_dir)) {
    // Função recursiva para excluir diretório
    function anrp_delete_directory($dir) {
        if (!file_exists($dir)) {
            return true;
        }
        
        if (!is_dir($dir)) {
            return unlink($dir);
        }
        
        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }
            
            if (!anrp_delete_directory($dir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }
        
        return rmdir($dir);
    }
    
    anrp_delete_directory($anrp_dir);
}