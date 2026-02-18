<?php
/**
 * ANRP Cache Manager - Sistema Inteligente de Cache
 * 
 * Funcionalidades:
 * - Cache de conteúdo extraído (evita re-scraping)
 * - Cache de respostas de IA (economia de tokens)
 * - Cache de imagens processadas
 * - Limpeza automática de cache expirado
 * - Estatísticas de cache hit/miss
 */
class ANRP_Cache_Manager {
    
    private $cache_dir;
    private $default_ttl = 3600; // 1 hora
    private $stats_option = 'anrp_cache_stats';
    
    public function __construct() {
        $this->cache_dir = ANRP_UPLOAD_DIR . 'cache/';
        
        if (!file_exists($this->cache_dir)) {
            wp_mkdir_p($this->cache_dir);
            
            // Criar .htaccess para segurança
            $htaccess = $this->cache_dir . '.htaccess';
            if (!file_exists($htaccess)) {
                file_put_contents($htaccess, 'Deny from all');
            }
        }
    }
    
    /**
     * Armazena item no cache
     */
    public function set($key, $value, $ttl = null) {
        if ($ttl === null) {
            $ttl = $this->default_ttl;
        }
        
        $cache_file = $this->get_cache_file($key);
        $cache_data = [
            'value' => $value,
            'expires' => time() + $ttl,
            'created' => time()
        ];
        
        $result = file_put_contents($cache_file, serialize($cache_data));
        
        if ($result !== false) {
            $this->update_stats('set');
            return true;
        }
        
        return false;
    }
    
    /**
     * Recupera item do cache
     */
    public function get($key) {
        $cache_file = $this->get_cache_file($key);
        
        if (!file_exists($cache_file)) {
            $this->update_stats('miss');
            return null;
        }
        
        $cache_data = unserialize(file_get_contents($cache_file));
        
        // Verificar se expirou
        if ($cache_data['expires'] < time()) {
            unlink($cache_file);
            $this->update_stats('expired');
            return null;
        }
        
        $this->update_stats('hit');
        return $cache_data['value'];
    }
    
    /**
     * Remove item do cache
     */
    public function delete($key) {
        $cache_file = $this->get_cache_file($key);
        
        if (file_exists($cache_file)) {
            unlink($cache_file);
            return true;
        }
        
        return false;
    }
    
    /**
     * Limpa todo o cache
     */
    public function clear_all() {
        $files = glob($this->cache_dir . '*.cache');
        $count = 0;
        
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * Limpa cache expirado
     */
    public function clear_expired() {
        $files = glob($this->cache_dir . '*.cache');
        $count = 0;
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $cache_data = unserialize(file_get_contents($file));
                
                if (isset($cache_data['expires']) && $cache_data['expires'] < time()) {
                    unlink($file);
                    $count++;
                }
            }
        }
        
        return $count;
    }
    
    /**
     * Obtém estatísticas de cache
     */
    public function get_stats() {
        $stats = get_option($this->stats_option, [
            'hits' => 0,
            'misses' => 0,
            'sets' => 0,
            'expired' => 0,
            'hit_rate' => 0
        ]);
        
        // Calcular hit rate
        $total = $stats['hits'] + $stats['misses'];
        if ($total > 0) {
            $stats['hit_rate'] = ($stats['hits'] / $total) * 100;
        }
        
        // Tamanho do cache
        $stats['size'] = $this->get_cache_size();
        $stats['count'] = count(glob($this->cache_dir . '*.cache'));
        
        return $stats;
    }
    
    /**
     * Reseta estatísticas
     */
    public function reset_stats() {
        update_option($this->stats_option, [
            'hits' => 0,
            'misses' => 0,
            'sets' => 0,
            'expired' => 0,
            'hit_rate' => 0
        ]);
    }
    
    /**
     * Obtém tamanho do cache em MB
     */
    public function get_cache_size() {
        $files = glob($this->cache_dir . '*.cache');
        $size = 0;
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $size += filesize($file);
            }
        }
        
        return round($size / (1024 * 1024), 2); // MB
    }
    
    /**
     * Gera nome de arquivo de cache
     */
    private function get_cache_file($key) {
        $safe_key = md5($key);
        return $this->cache_dir . $safe_key . '.cache';
    }
    
    /**
     * Atualiza estatísticas
     */
    private function update_stats($type) {
        $stats = get_option($this->stats_option, [
            'hits' => 0,
            'misses' => 0,
            'sets' => 0,
            'expired' => 0
        ]);
        
        if (isset($stats[$type])) {
            $stats[$type]++;
            update_option($this->stats_option, $stats);
        } else if ($type === 'hit' || $type === 'miss' || $type === 'set' || $type === 'expired') {
            $stats[$type . 's'] = ($stats[$type . 's'] ?? 0) + 1;
            update_option($this->stats_option, $stats);
        }
    }
    
    /**
     * Verifica se chave existe no cache
     */
    public function has($key) {
        return $this->get($key) !== null;
    }
    
    /**
     * Cache com callback - se não existe, executa função e armazena
     */
    public function remember($key, $ttl, $callback) {
        $cached = $this->get($key);
        
        if ($cached !== null) {
            return $cached;
        }
        
        $value = $callback();
        $this->set($key, $value, $ttl);
        
        return $value;
    }
}

// Agendar limpeza de cache expirado
add_action('anrp_cache_cleanup_hook', function() {
    $cache = new ANRP_Cache_Manager();
    $deleted = $cache->clear_expired();
    error_log("ANRP Cache: Limpeza automática removeu {$deleted} itens expirados");
});

if (!wp_next_scheduled('anrp_cache_cleanup_hook')) {
    wp_schedule_event(time(), 'hourly', 'anrp_cache_cleanup_hook');
}
