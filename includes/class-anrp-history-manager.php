<?php
// includes/class-anrp-history-manager.php

class ANRP_History_Manager {
    private $table_name;
    
    public function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'anrp_history';
    }
    
    public function add_record($original_url, $new_title, $published_url, $post_id, $status = 'processed', $author_id = 0, $source_type = '') {
        global $wpdb;
        $wpdb->insert(
            $this->table_name,
            [
                'original_url' => $original_url,
                'new_title' => $new_title,
                'published_url' => $published_url,
                'published_date' => current_time('mysql'),
                'status' => $status,
                'post_id' => $post_id,
                'author_id' => $author_id,
                'source_type' => $source_type
            ],
            ['%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s']
        );

        return $wpdb->insert_id;
    }
    
    public function get_history($filter = 'all', $page = 1, $per_page = 20) {
        global $wpdb;
        
        $offset = ($page - 1) * $per_page;
        $where = '';
        
        if ($filter !== 'all') {
            $where = "WHERE status = '" . esc_sql($filter) . "'";
        }
        
        $query = "SELECT * FROM {$this->table_name} {$where} ORDER BY published_date DESC LIMIT %d OFFSET %d";
        $query = $wpdb->prepare($query, $per_page, $offset);
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        // Process results to add author name and sync status
        foreach ($results as &$item) {
            // Sync status with real post status
            if (!empty($item['post_id'])) {
                $real_status = get_post_status($item['post_id']);
                if ($real_status && $real_status !== $item['status']) {
                    $item['status'] = $real_status;
                    // Update DB to keep in sync
                    $wpdb->update(
                        $this->table_name, 
                        ['status' => $real_status], 
                        ['id' => $item['id']],
                        ['%s'],
                        ['%d']
                    );
                } elseif (!$real_status) {
                    $item['status'] = 'deleted';
                }
            }
            
            // Get Author Name
            $item['author_name'] = 'N/A';
            if (!empty($item['author_id'])) {
                $user_info = get_userdata($item['author_id']);
                if ($user_info) {
                    $item['author_name'] = $user_info->display_name;
                }
            }
        }
        
        return $results;
    }
    
    public function get_total_count($filter = 'all') {
        global $wpdb;
        
        $where = '';
        if ($filter !== 'all') {
            $where = "WHERE status = '" . esc_sql($filter) . "'";
        }
        
        $query = "SELECT COUNT(*) FROM {$this->table_name} {$where}";
        return $wpdb->get_var($query);
    }
    
    public function get_published_count() {
        global $wpdb;
        
        $query = "SELECT COUNT(*) FROM {$this->table_name} WHERE status = 'publish'";
        return $wpdb->get_var($query);
    }

    public function get_count_by_source($source) {
        global $wpdb;

        $query = $wpdb->prepare("SELECT COUNT(*) FROM {$this->table_name} WHERE source_type = %s", $source);
        return (int) $wpdb->get_var($query);
    }

    public function get_published_count_by_source($source) {
        global $wpdb;

        $query = $wpdb->prepare("SELECT COUNT(*) FROM {$this->table_name} WHERE source_type = %s AND status = 'publish'", $source);
        return (int) $wpdb->get_var($query);
    }
    
    public function url_exists($url) {
        global $wpdb;
        
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table_name} WHERE original_url = %s",
            $url
        );
        
        return $wpdb->get_var($query) > 0;
    }
    
    public function delete_record($record_id) {
        global $wpdb;
        
        return $wpdb->delete(
            $this->table_name,
            ['id' => $record_id],
            ['%d']
        );
    }
}