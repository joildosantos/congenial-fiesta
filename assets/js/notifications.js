// assets/js/notifications.js

// Solicitar permissão para notificações
function anrpRequestNotificationPermission() {
    if (!('Notification' in window)) {
        console.log('Este navegador não suporta notificações.');
        return false;
    }
    
    if (Notification.permission === 'granted') {
        return true;
    }
    
    if (Notification.permission !== 'denied') {
        Notification.requestPermission().then(function(permission) {
            if (permission === 'granted') {
                anrpShowNotification('Notificações Ativadas', 'Você receberá notificações quando novas notícias forem publicadas.');
                return true;
            }
        });
    }
    
    return false;
}

// Mostrar notificação
function anrpShowNotification(title, body, icon = null, url = null) {
    if (Notification.permission !== 'granted') {
        return;
    }
    
    const options = {
        body: body,
        icon: icon || ((typeof anrp_ajax !== 'undefined' && anrp_ajax.plugin_url) ? anrp_ajax.plugin_url + 'assets/images/icon-128.png' : ''),
        badge: (typeof anrp_ajax !== 'undefined' && anrp_ajax.plugin_url) ? anrp_ajax.plugin_url + 'assets/images/badge-72.png' : '',
        tag: 'anrp-notification',
        renotify: true,
        vibrate: [200, 100, 200],
        data: {
            url: url,
            timestamp: Date.now()
        },
        actions: url ? [
            {
                action: 'open',
                title: 'Abrir'
            }
        ] : []
    };
    
    const notification = new Notification(title, options);
    
    notification.onclick = function(event) {
        event.preventDefault();
        if (url) {
            window.open(url, '_blank');
        }
        notification.close();
    };
    
    notification.onaction = function(event) {
        if (event.action === 'open' && url) {
            window.open(url, '_blank');
        }
    };
    
    // Fechar automaticamente após 10 segundos
    setTimeout(() => {
        notification.close();
    }, 10000);
    
    return notification;
}

// Verificar notificações pendentes
function anrpCheckPendingNotifications() {
    jQuery.ajax({
        url: (typeof anrp_ajax !== 'undefined' && anrp_ajax.ajax_url) ? anrp_ajax.ajax_url : '/wp-admin/admin-ajax.php',
        type: 'POST',
        data: {
            action: 'anrp_get_pending_notifications',
            nonce: (typeof anrp_ajax !== 'undefined' ? anrp_ajax.nonce : '')
        },
        success: function(response) {
            if (response.success && response.data.notifications) {
                response.data.notifications.forEach(function(notification) {
                    anrpShowNotification(
                        notification.title,
                        notification.body,
                        notification.icon,
                        notification.url
                    );
                });
            }
        }
    });
}

// Inicializar notificações quando a página carregar
jQuery(document).ready(function($) {
    // Verificar se o usuário está logado no painel admin
    if ($('body').hasClass('wp-admin')) {
        // Solicitar permissão na primeira visita
        if (!localStorage.getItem('anrp_notification_permission_asked')) {
            setTimeout(function() {
                anrpRequestNotificationPermission();
                localStorage.setItem('anrp_notification_permission_asked', 'true');
            }, 3000);
        }
        
        // Verificar notificações pendentes a cada 30 segundos
        setInterval(anrpCheckPendingNotifications, 30000);
        
        // Verificar imediatamente
        setTimeout(anrpCheckPendingNotifications, 5000);
    }
});

// Evento quando uma notícia é publicada via AJAX
jQuery(document).on('anrp_article_published', function(event, data) {
    if (data.post_status === 'publish') {
        anrpShowNotification(
            'Notícia Publicada!',
            data.post_title,
            data.post_image || null,
            data.post_url
        );
    }
});