<?php
// includes/class-anrp-notifications.php

class ANRP_Notifications {
    
    public function send_post_notification($post_id, $status) {
        $post = get_post($post_id);
        $title = $post->post_title;
        $url = get_permalink($post_id);
        
        // Notificação no painel admin
        $this->send_admin_notification($post_id, $title, $status);
        
        // Notificação push no navegador (se permitido)
        $this->send_browser_notification($title, $status, $url);
        
        // Log
        error_log("Notificação enviada para post {$post_id} - Status: {$status}");
    }
    
    private function send_admin_notification($post_id, $title, $status) {
        $message = sprintf(
            'Nova notícia publicada: "%s" (ID: %d, Status: %s)',
            $title,
            $post_id,
            $status
        );
        
        // Aqui você pode implementar notificações no painel WordPress
        // Por exemplo, criar um post no log do plugin
    }
    
    private function send_browser_notification($title, $status, $url) {
        // Esta função será chamada via AJAX no front-end
        // O JavaScript irá verificar as permissões e mostrar a notificação
        
        $notification_data = [
            'title' => 'Notícia ' . ($status === 'publish' ? 'Publicada' : 'Agendada'),
            'body' => $title,
            'icon' => get_site_icon_url(),
            'url' => $url,
            'timestamp' => time()
        ];
        
        // Armazenar notificação para ser buscada pelo JavaScript
        $notifications = get_option('anrp_pending_notifications', []);
        $notifications[] = $notification_data;
        update_option('anrp_pending_notifications', $notifications);
    }
    
    public function get_pending_notifications() {
        $notifications = get_option('anrp_pending_notifications', []);
        update_option('anrp_pending_notifications', []); // Limpar após buscar
        return $notifications;
    }
    
    public function request_permission() {
        // Esta função será chamada quando o usuário clicar para ativar notificações
        return true;
    }
}