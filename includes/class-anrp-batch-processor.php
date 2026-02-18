<?php
/**
 * ANRP Batch Processor - Processamento em Lote
 * 
 * Permite processar múltiplas URLs de uma vez
 * - Interface para upload de lista de URLs
 * - Processamento assíncrono em background
 * - Fila de processamento
 * - Relatório de progresso
 * - Pausar/retomar processamento
 */
class ANRP_Batch_Processor {
    
    private $queue_option = 'anrp_batch_queue';
    private $processing_option = 'anrp_batch_processing';
    private $results_option = 'anrp_batch_results';
    private $max_concurrent = 5;
    
    /**
     * Adiciona URLs à fila de processamento
     */
    public function add_to_queue($urls, $options = []) {
        $queue = $this->get_queue();
        
        foreach ($urls as $url) {
            $item = [
                'url' => $url,
                'status' => 'pending',
                'added' => time(),
                'options' => $options,
                'attempts' => 0,
                'last_error' => null
            ];
            
            $queue[] = $item;
        }
        
        update_option($this->queue_option, $queue);
        
        return count($urls);
    }
    
    /**
     * Processa próximo item da fila
     */
    public function process_next() {
        $queue = $this->get_queue();
        
        // Encontrar próximo item pendente
        $next_key = null;
        foreach ($queue as $key => $item) {
            if ($item['status'] === 'pending') {
                $next_key = $key;
                break;
            }
        }
        
        if ($next_key === null) {
            return ['success' => false, 'message' => 'Fila vazia'];
        }
        
        // Marcar como processando
        $queue[$next_key]['status'] = 'processing';
        $queue[$next_key]['started'] = time();
        update_option($this->queue_option, $queue);
        
        try {
            // Processar URL
            $result = $this->process_url(
                $queue[$next_key]['url'], 
                $queue[$next_key]['options']
            );
            
            // Atualizar status
            $queue[$next_key]['status'] = 'completed';
            $queue[$next_key]['completed'] = time();
            $queue[$next_key]['result'] = $result;
            $queue[$next_key]['post_id'] = $result['post_id'] ?? null;
            
            update_option($this->queue_option, $queue);
            
            // Salvar resultado
            $this->save_result($queue[$next_key]);
            
            return [
                'success' => true,
                'message' => 'Processado com sucesso',
                'result' => $result
            ];
            
        } catch (Exception $e) {
            $queue[$next_key]['attempts']++;
            
            if ($queue[$next_key]['attempts'] >= 3) {
                $queue[$next_key]['status'] = 'failed';
                $queue[$next_key]['last_error'] = $e->getMessage();
            } else {
                $queue[$next_key]['status'] = 'pending';
            }
            
            update_option($this->queue_option, $queue);
            
            return [
                'success' => false,
                'message' => 'Erro: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Processa múltiplos itens da fila
     */
    public function process_batch($count = 5) {
        $processed = 0;
        $results = [];
        
        for ($i = 0; $i < $count; $i++) {
            $result = $this->process_next();
            
            if (!$result['success']) {
                break;
            }
            
            $processed++;
            $results[] = $result;
        }
        
        return [
            'processed' => $processed,
            'results' => $results
        ];
    }
    
    /**
     * Processa URL individual
     */
    private function process_url($url, $options = []) {
        $core = ANRP_Core::get_instance();
        
        // Extrair conteúdo
        $scraper = new ANRP_Scraper();
        $content = $scraper->extract_content($url);
        
        // Reescrever
        $rewriter = new ANRP_Free_Rewriter();
        $rewritten = $rewriter->rewrite($content['title'], $content['content']);
        
        // Criar post
        $post_data = [
            'post_title' => $rewritten['title'] ?? $content['title'],
            'post_content' => $rewritten['content'] ?? $content['content'],
            'post_status' => $options['status'] ?? 'draft',
            'post_author' => $options['author_id'] ?? get_option('anrp_default_author'),
            'post_category' => $options['category_id'] ? [$options['category_id']] : []
        ];
        
        $post_id = wp_insert_post($post_data);
        
        if (is_wp_error($post_id)) {
            throw new Exception('Erro ao criar post: ' . $post_id->get_error_message());
        }
        
        // Adicionar imagem destacada se disponível
        if (!empty($content['main_image'])) {
            $image_id = $this->download_and_attach_image($content['main_image'], $post_id);
            if ($image_id) {
                set_post_thumbnail($post_id, $image_id);
            }
        }
        
        // Adicionar tags
        if (!empty($options['tags'])) {
            wp_set_post_tags($post_id, $options['tags'], false);
        } else {
            // Tags automáticas
            $tag_identifier = new ANRP_Category_Tag_Identifier();
            $auto_tags = $tag_identifier->identify_tags($rewritten['content'] ?? $content['content']);
            if (!empty($auto_tags)) {
                wp_set_post_tags($post_id, $auto_tags, false);
            }
        }
        
        // Adicionar ao histórico
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'anrp_history',
            [
                'original_url' => $url,
                'new_title' => $post_data['post_title'],
                'published_url' => get_permalink($post_id),
                'published_date' => current_time('mysql'),
                'status' => 'completed',
                'post_id' => $post_id,
                'author_id' => $post_data['post_author'],
                'source_type' => 'batch'
            ]
        );
        
        return [
            'post_id' => $post_id,
            'post_url' => get_permalink($post_id),
            'post_title' => $post_data['post_title']
        ];
    }
    
    /**
     * Download e anexa imagem ao post
     */
    private function download_and_attach_image($image_url, $post_id) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        $tmp = download_url($image_url);
        
        if (is_wp_error($tmp)) {
            return false;
        }
        
        $file_array = [
            'name' => basename($image_url),
            'tmp_name' => $tmp
        ];
        
        $id = media_handle_sideload($file_array, $post_id);
        
        if (is_wp_error($id)) {
            @unlink($tmp);
            return false;
        }
        
        return $id;
    }
    
    /**
     * Obtém fila de processamento
     */
    public function get_queue() {
        return get_option($this->queue_option, []);
    }
    
    /**
     * Obtém estatísticas da fila
     */
    public function get_queue_stats() {
        $queue = $this->get_queue();
        
        $stats = [
            'total' => count($queue),
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0
        ];
        
        foreach ($queue as $item) {
            if (isset($item['status']) && isset($stats[$item['status']])) {
                $stats[$item['status']]++;
            }
        }
        
        return $stats;
    }
    
    /**
     * Limpa fila
     */
    public function clear_queue($status = 'all') {
        if ($status === 'all') {
            delete_option($this->queue_option);
            return true;
        }
        
        $queue = $this->get_queue();
        $queue = array_filter($queue, function($item) use ($status) {
            return $item['status'] !== $status;
        });
        
        update_option($this->queue_option, array_values($queue));
        return true;
    }
    
    /**
     * Salva resultado do processamento
     */
    private function save_result($item) {
        $results = get_option($this->results_option, []);
        $results[] = $item;
        
        // Manter apenas últimos 100 resultados
        if (count($results) > 100) {
            $results = array_slice($results, -100);
        }
        
        update_option($this->results_option, $results);
    }
    
    /**
     * Obtém resultados salvos
     */
    public function get_results($limit = 20) {
        $results = get_option($this->results_option, []);
        return array_slice(array_reverse($results), 0, $limit);
    }
    
    /**
     * Verifica se está processando
     */
    public function is_processing() {
        return get_option($this->processing_option, false);
    }
    
    /**
     * Define estado de processamento
     */
    public function set_processing($status) {
        update_option($this->processing_option, $status);
    }
    
    /**
     * Processa fila em background (chamado por WP Cron)
     */
    public function process_background() {
        if ($this->is_processing()) {
            return; // Já está processando
        }
        
        $this->set_processing(true);
        
        try {
            $result = $this->process_batch($this->max_concurrent);
            error_log('ANRP Batch: Processados ' . $result['processed'] . ' itens');
        } catch (Exception $e) {
            error_log('ANRP Batch Error: ' . $e->getMessage());
        }
        
        $this->set_processing(false);
    }
}

// Agendar processamento em background
add_action('anrp_process_batch_hook', function() {
    $processor = new ANRP_Batch_Processor();
    $processor->process_background();
});

if (!wp_next_scheduled('anrp_process_batch_hook')) {
    wp_schedule_event(time(), 'every_5_minutes', 'anrp_process_batch_hook');
}

// Adicionar intervalo de 5 minutos
add_filter('cron_schedules', function($schedules) {
    $schedules['every_5_minutes'] = [
        'interval' => 300,
        'display' => __('A cada 5 minutos')
    ];
    return $schedules;
});
