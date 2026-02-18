<?php
// includes/class-anrp-feed-manager.php

class ANRP_Feed_Manager {
    private $table_name;
    private $core;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'anrp_feeds';
    }
    
    public function save_feed($feed_data) {
        global $wpdb;
        // Evitar duplicatas pelo feed_url
        if (isset($feed_data['feed_url'])) {
            $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->table_name} WHERE feed_url = %s", $feed_data['feed_url']));
            if ($existing && empty($feed_data['id'])) {
                return $existing; // Retorna id existente para evitar duplicatas
            }
        }

        if (isset($feed_data['id'])) {
            $wpdb->update(
                $this->table_name,
                $feed_data,
                ['id' => $feed_data['id']]
            );
            return $feed_data['id'];
        } else {
            $wpdb->insert($this->table_name, $feed_data);
            return $wpdb->insert_id;
        }
    }
    
    public function get_all_feeds() {
        global $wpdb;
        
        $query = "SELECT * FROM {$this->table_name} ORDER BY created_at DESC";
        return $wpdb->get_results($query, ARRAY_A);
    }
    
    public function get_active_feeds() {
        global $wpdb;
        
        $query = "SELECT * FROM {$this->table_name} WHERE active = 1 ORDER BY last_checked ASC";
        return $wpdb->get_results($query, ARRAY_A);
    }
    
    public function get_active_feeds_count() {
        global $wpdb;
        
        $query = "SELECT COUNT(*) FROM {$this->table_name} WHERE active = 1";
        return $wpdb->get_var($query);
    }
    
    public function delete_feed($feed_id) {
        global $wpdb;
        
        return $wpdb->delete(
            $this->table_name,
            ['id' => $feed_id],
            ['%d']
        );
    }
    
    public function check_all_feeds() {
        $feeds = $this->get_active_feeds();
        
        foreach ($feeds as $feed) {
            $this->process_feed($feed);
            
            // Atualizar última verificação
            $this->update_last_checked($feed['id']);
        }
    }
    
    /**
     * Processa um feed individual - público para ser chamado externamente
     */
    public function process_feed($feed) {
        include_once(ABSPATH . WPINC . '/feed.php');
        
        $rss = fetch_feed($feed['feed_url']);
        
        if (is_wp_error($rss)) {
            error_log('Erro ao buscar feed: ' . $rss->get_error_message());
            return;
        }
        
        $max_items = $rss->get_item_quantity(10);
        $rss_items = $rss->get_items(0, $max_items);
        
        $history = new ANRP_History_Manager();
        
        foreach ($rss_items as $item) {
            $url = $item->get_permalink();
            
            // Verificar se já foi processado
            if (!$history->url_exists($url)) {
                $this->process_feed_item($item, $feed);
            }
        }
    }
    
    private function process_feed_item($item, $feed) {
        $url = $item->get_permalink();
        $title = $item->get_title();
        $description = $item->get_description();
        
        // Verificar se deve publicar automaticamente
        $auto_publish = $feed['auto_publish'] == 1;
        
        // Determinar data de publicação
        $publish_date = current_time('mysql');
        
        if ($feed['schedule_type'] == 'scheduled' && !empty($feed['schedule_time'])) {
            $today = date('Y-m-d');
            $publish_date = $today . ' ' . $feed['schedule_time'];
            
            // Se o horário já passou hoje, agenda para amanhã
            if (strtotime($publish_date) < current_time('timestamp')) {
                $publish_date = date('Y-m-d', strtotime('+1 day')) . ' ' . $feed['schedule_time'];
            }
        }
        
        // Processar item
        // Esta função seria chamada assincronamente em produção
        // Aqui podemos apenas registrar para processamento posterior
        $this->schedule_feed_processing($url, $auto_publish, $publish_date, $feed['id']);
    }
    
    private function schedule_feed_processing($url, $auto_publish, $publish_date, $feed_id) {
        // Usar WP Cron para processamento assíncrono
        wp_schedule_single_event(
            time() + 60, // 1 minuto depois
            'anrp_process_feed_item',
            [$url, $auto_publish, $publish_date, $feed_id]
        );
    }
    
    private function update_last_checked($feed_id) {
        global $wpdb;
        
        $wpdb->update(
            $this->table_name,
            ['last_checked' => current_time('mysql')],
            ['id' => $feed_id]
        );
    }
    
    public function add_url_to_monitoring($url) {
        // Cria um feed especial para monitorar esta URL
        $feed_data = [
            'feed_name' => 'Monitoramento: ' . substr($url, 0, 50),
            'feed_url' => $this->generate_single_url_feed($url),
            'auto_publish' => 0,
            'schedule_type' => 'immediate',
            'active' => 1
        ];
        
        return $this->save_feed($feed_data);
    }
    
    private function generate_single_url_feed($url) {
        // Para URLs individuais, podemos criar um feed RSS simples
        // Em produção, usar um serviço que converte páginas em feeds
        return $url; // Placeholder
    }

    public function get_feed_items($feed_url) {
        // Tentar obter conteúdo com headers completos de navegador (Chrome/Mac)
        $args = [
            'timeout' => 20,
            'redirection' => 5,
            'httpversion' => '1.1',
            'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'headers' => [
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8',
                'Accept-Language' => 'pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
                'Cache-Control' => 'no-cache',
                'Upgrade-Insecure-Requests' => '1',
                'Sec-Fetch-Dest' => 'document',
                'Sec-Fetch-Mode' => 'navigate',
                'Sec-Fetch-Site' => 'none',
                'Sec-Fetch-User' => '?1'
            ]
        ];
        
        $response = wp_remote_get($feed_url, $args);
        
        if (is_wp_error($response)) {
            return new WP_Error('feed_error', $response->get_error_message());
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        if ($response_code !== 200) {
            return new WP_Error('feed_error', 'Erro HTTP ' . $response_code);
        }
        
        $body = wp_remote_retrieve_body($response);
        
        if (empty($body)) {
            return new WP_Error('feed_error', 'Feed vazio');
        }
        
        // Usar SimplePie interno do WP para parsear string
        if (!class_exists('SimplePie')) {
            require_once(ABSPATH . WPINC . '/class-simplepie.php');
        }
        
        $feed = new SimplePie();
        $feed->set_raw_data($body);
        $feed->set_cache_location(get_temp_dir());
        $feed->set_cache_duration(12 * HOUR_IN_SECONDS); // Cache de arquivo temporário
        
        // Tentar inicializar
        $success = $feed->init();
        $feed->handle_content_type();
        
        if (!$success) {
            // Fallback: Tentar DOMDocument simples se SimplePie falhar com a string
            return $this->parse_feed_fallback($body);
        }
        
        $max_items = 20;
        $items_data = [];
        
        foreach ($feed->get_items(0, $max_items) as $item) {
            $items_data[] = [
                'title' => $item->get_title(),
                'link' => $item->get_permalink(),
                'date' => $item->get_date('Y-m-d H:i:s') ?: date('Y-m-d H:i:s'),
                'description' => wp_trim_words(strip_tags($item->get_description()), 20)
            ];
        }
        
        return $items_data;
    }
    
    private function parse_feed_fallback($xml_string) {
        $items_data = [];
        try {
            $xml = new SimpleXMLElement($xml_string);
            
            // RSS 2.0
            if (isset($xml->channel->item)) {
                foreach ($xml->channel->item as $item) {
                    if (count($items_data) >= 20) break;
                    $items_data[] = [
                        'title' => (string)$item->title,
                        'link' => (string)$item->link,
                        'date' => isset($item->pubDate) ? date('Y-m-d H:i:s', strtotime((string)$item->pubDate)) : date('Y-m-d H:i:s'),
                        'description' => wp_trim_words(strip_tags((string)$item->description), 20)
                    ];
                }
            } 
            // Atom
            elseif (isset($xml->entry)) {
                foreach ($xml->entry as $entry) {
                    if (count($items_data) >= 20) break;
                    $link = (string)$entry->link['href'];
                    $items_data[] = [
                        'title' => (string)$entry->title,
                        'link' => $link,
                        'date' => isset($entry->updated) ? date('Y-m-d H:i:s', strtotime((string)$entry->updated)) : date('Y-m-d H:i:s'),
                        'description' => wp_trim_words(strip_tags((string)$entry->summary ?? (string)$entry->content), 20)
                    ];
                }
            }
        } catch (Exception $e) {
            return new WP_Error('feed_error', 'Falha no parser fallback: ' . $e->getMessage());
        }
        
        return $items_data;
    }
}