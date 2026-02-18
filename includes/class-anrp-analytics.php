<?php
/**
 * ANRP Analytics - Sistema de Métricas e Relatórios
 * 
 * Funcionalidades:
 * - Rastreamento de uso do plugin
 * - Métricas de performance
 * - Taxa de sucesso/falha
 * - Fontes mais usadas
 * - Modelos de IA mais usados
 * - Tempo médio de processamento
 * - Relatórios exportáveis
 */
class ANRP_Analytics {
    
    private $events_table;
    private $metrics_option = 'anrp_metrics';
    
    public function __construct() {
        global $wpdb;
        $this->events_table = $wpdb->prefix . 'anrp_analytics_events';
    }
    
    /**
     * Cria tabela de analytics na ativação
     */
    public static function create_tables() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'anrp_analytics_events';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            event_data text,
            url varchar(1000),
            model_used varchar(100),
            processing_time float,
            success tinyint(1),
            error_message text,
            user_id bigint(20),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY created_at (created_at),
            KEY success (success)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Registra evento
     */
    public function log_event($type, $data = []) {
        global $wpdb;
        
        $event = [
            'event_type' => $type,
            'event_data' => maybe_serialize($data),
            'url' => $data['url'] ?? null,
            'model_used' => $data['model'] ?? null,
            'processing_time' => $data['time'] ?? null,
            'success' => $data['success'] ?? 1,
            'error_message' => $data['error'] ?? null,
            'user_id' => get_current_user_id(),
            'created_at' => current_time('mysql')
        ];
        
        $wpdb->insert($this->events_table, $event);
    }
    
    /**
     * Obtém métricas gerais
     */
    public function get_general_metrics($period = '30days') {
        global $wpdb;
        
        $date_filter = $this->get_date_filter($period);
        
        // Total de processamentos
        $total = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$this->events_table} 
            WHERE event_type = 'article_processed' 
            AND created_at >= '{$date_filter}'
        ");
        
        // Sucessos
        $success = $wpdb->get_var("
            SELECT COUNT(*) 
            FROM {$this->events_table} 
            WHERE event_type = 'article_processed' 
            AND success = 1 
            AND created_at >= '{$date_filter}'
        ");
        
        // Taxa de sucesso
        $success_rate = $total > 0 ? ($success / $total) * 100 : 0;
        
        // Tempo médio
        $avg_time = $wpdb->get_var("
            SELECT AVG(processing_time) 
            FROM {$this->events_table} 
            WHERE event_type = 'article_processed' 
            AND success = 1 
            AND created_at >= '{$date_filter}'
        ");
        
        // Total de URLs únicas
        $unique_urls = $wpdb->get_var("
            SELECT COUNT(DISTINCT url) 
            FROM {$this->events_table} 
            WHERE event_type = 'article_processed' 
            AND created_at >= '{$date_filter}'
        ");
        
        return [
            'total_processed' => (int) $total,
            'successful' => (int) $success,
            'failed' => (int) ($total - $success),
            'success_rate' => round($success_rate, 2),
            'avg_processing_time' => round($avg_time, 2),
            'unique_sources' => (int) $unique_urls,
            'period' => $period
        ];
    }
    
    /**
     * Obtém modelos mais usados
     */
    public function get_top_models($limit = 10, $period = '30days') {
        global $wpdb;
        
        $date_filter = $this->get_date_filter($period);
        
        $results = $wpdb->get_results("
            SELECT 
                model_used,
                COUNT(*) as usage_count,
                AVG(processing_time) as avg_time,
                SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as success_count
            FROM {$this->events_table}
            WHERE event_type = 'article_processed'
            AND model_used IS NOT NULL
            AND created_at >= '{$date_filter}'
            GROUP BY model_used
            ORDER BY usage_count DESC
            LIMIT {$limit}
        ", ARRAY_A);
        
        foreach ($results as &$result) {
            $result['success_rate'] = round(($result['success_count'] / $result['usage_count']) * 100, 2);
            $result['avg_time'] = round($result['avg_time'], 2);
        }
        
        return $results;
    }
    
    /**
     * Obtém fontes mais processadas
     */
    public function get_top_sources($limit = 10, $period = '30days') {
        global $wpdb;
        
        $date_filter = $this->get_date_filter($period);
        
        $results = $wpdb->get_results("
            SELECT 
                url,
                COUNT(*) as process_count,
                MAX(created_at) as last_processed,
                SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as success_count
            FROM {$this->events_table}
            WHERE event_type = 'article_processed'
            AND url IS NOT NULL
            AND created_at >= '{$date_filter}'
            GROUP BY url
            ORDER BY process_count DESC
            LIMIT {$limit}
        ", ARRAY_A);
        
        foreach ($results as &$result) {
            // Extrair domínio
            $parsed = parse_url($result['url']);
            $result['domain'] = $parsed['host'] ?? 'N/A';
            $result['success_rate'] = round(($result['success_count'] / $result['process_count']) * 100, 2);
        }
        
        return $results;
    }
    
    /**
     * Obtém estatísticas por dia
     */
    public function get_daily_stats($days = 30) {
        global $wpdb;
        
        $results = $wpdb->get_results("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as total,
                SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as success,
                AVG(processing_time) as avg_time
            FROM {$this->events_table}
            WHERE event_type = 'article_processed'
            AND created_at >= DATE_SUB(NOW(), INTERVAL {$days} DAY)
            GROUP BY DATE(created_at)
            ORDER BY date ASC
        ", ARRAY_A);
        
        foreach ($results as &$result) {
            $result['failed'] = $result['total'] - $result['success'];
            $result['success_rate'] = $result['total'] > 0 ? 
                round(($result['success'] / $result['total']) * 100, 2) : 0;
            $result['avg_time'] = round($result['avg_time'], 2);
        }
        
        return $results;
    }
    
    /**
     * Obtém erros mais comuns
     */
    public function get_common_errors($limit = 10, $period = '30days') {
        global $wpdb;
        
        $date_filter = $this->get_date_filter($period);
        
        $results = $wpdb->get_results("
            SELECT 
                error_message,
                COUNT(*) as error_count,
                MAX(created_at) as last_occurrence
            FROM {$this->events_table}
            WHERE success = 0
            AND error_message IS NOT NULL
            AND created_at >= '{$date_filter}'
            GROUP BY error_message
            ORDER BY error_count DESC
            LIMIT {$limit}
        ", ARRAY_A);
        
        return $results;
    }
    
    /**
     * Exporta relatório em CSV
     */
    public function export_report($period = '30days') {
        $metrics = $this->get_general_metrics($period);
        $top_models = $this->get_top_models(10, $period);
        $top_sources = $this->get_top_sources(10, $period);
        $daily_stats = $this->get_daily_stats(30);
        
        $csv = "CRIA Releituras - Relatório de Analytics\n";
        $csv .= "Período: {$period}\n";
        $csv .= "Gerado em: " . date('Y-m-d H:i:s') . "\n\n";
        
        // Métricas gerais
        $csv .= "MÉTRICAS GERAIS\n";
        $csv .= "Total Processado,{$metrics['total_processed']}\n";
        $csv .= "Sucesso,{$metrics['successful']}\n";
        $csv .= "Falhas,{$metrics['failed']}\n";
        $csv .= "Taxa de Sucesso,{$metrics['success_rate']}%\n";
        $csv .= "Tempo Médio,{$metrics['avg_processing_time']}s\n";
        $csv .= "Fontes Únicas,{$metrics['unique_sources']}\n\n";
        
        // Modelos mais usados
        $csv .= "MODELOS MAIS USADOS\n";
        $csv .= "Modelo,Uso,Taxa Sucesso,Tempo Médio\n";
        foreach ($top_models as $model) {
            $csv .= "{$model['model_used']},{$model['usage_count']},{$model['success_rate']}%,{$model['avg_time']}s\n";
        }
        $csv .= "\n";
        
        // Estatísticas diárias
        $csv .= "ESTATÍSTICAS DIÁRIAS\n";
        $csv .= "Data,Total,Sucesso,Falhas,Taxa Sucesso,Tempo Médio\n";
        foreach ($daily_stats as $day) {
            $csv .= "{$day['date']},{$day['total']},{$day['success']},{$day['failed']},{$day['success_rate']}%,{$day['avg_time']}s\n";
        }
        
        return $csv;
    }
    
    /**
     * Obtém filtro de data SQL
     */
    private function get_date_filter($period) {
        $date = new DateTime();
        
        switch ($period) {
            case '7days':
                $date->modify('-7 days');
                break;
            case '30days':
                $date->modify('-30 days');
                break;
            case '90days':
                $date->modify('-90 days');
                break;
            case 'year':
                $date->modify('-1 year');
                break;
            case 'all':
                $date->modify('-10 years');
                break;
            default:
                $date->modify('-30 days');
        }
        
        return $date->format('Y-m-d H:i:s');
    }
    
    /**
     * Limpa dados antigos
     */
    public function cleanup_old_data($days = 90) {
        global $wpdb;
        
        $deleted = $wpdb->query("
            DELETE FROM {$this->events_table}
            WHERE created_at < DATE_SUB(NOW(), INTERVAL {$days} DAY)
        ");
        
        return $deleted;
    }
    
    /**
     * Obtém tamanho da tabela em MB
     */
    public function get_table_size() {
        global $wpdb;
        
        $size = $wpdb->get_var("
            SELECT 
                ROUND((data_length + index_length) / 1024 / 1024, 2) as size_mb
            FROM information_schema.TABLES
            WHERE table_schema = DATABASE()
            AND table_name = '{$this->events_table}'
        ");
        
        return $size ?? 0;
    }
}

// Hook para criar tabelas na ativação
register_activation_hook(__FILE__, function() {
    ANRP_Analytics::create_tables();
});
