// assets/js/social.js

jQuery(document).ready(function($) {
    // Salvar credenciais de uma plataforma
    $('.action-save-social').on('click', function() {
        const platform = $(this).data('platform');
        const $btn = $(this);
        const originalText = $btn.text();
        
        console.log('Salvando credenciais para:', platform);
        
        let clientId, clientSecret, enabled;
        
        if (platform === 'linkedin') {
            clientId = $('#linkedin_client_id').val();
            clientSecret = $('#linkedin_client_secret').val();
            enabled = $('#linkedin-auto-share').is(':checked');
        } else if (platform === 'instagram') {
            clientId = $('#instagram_client_id').val();
            clientSecret = $('#instagram_client_secret').val();
            enabled = $('#instagram-auto-share').is(':checked');
        } else if (platform === 'twitter') {
            clientId = $('#twitter_client_id').val();
            clientSecret = $('#twitter_client_secret').val();
            enabled = $('#twitter-auto-share').is(':checked');
        }
        
        console.log('Client ID:', clientId);
        console.log('Client Secret:', clientSecret ? '***' : 'vazio');
        console.log('Enabled:', enabled);
        
        if (!clientId || !clientSecret) {
            alert('Por favor, preencha Client ID e Client Secret.');
            return;
        }
        
        $btn.prop('disabled', true).text('Salvando...');
        
        const data = {
            action: 'anrp_save_social_config',
            nonce: anrp_ajax.nonce,
            platform: platform,
            config: {
                client_id: clientId,
                client_secret: clientSecret,
                enabled: enabled
            }
        };
        
        console.log('Enviando dados:', data);
        
        $.post(anrp_ajax.ajax_url, data)
        .done(function(response) {
            console.log('Resposta recebida:', response);
            if (response.success) {
                alert('✓ Credenciais salvas com sucesso!\n\nAgora você pode clicar em "Conectar Conta".');
            } else {
                console.error('Erro na resposta:', response);
                alert('Erro ao salvar: ' + (response.data?.message || 'Erro desconhecido'));
            }
        }).fail(function(xhr, status, error) {
            console.error('Erro na requisição:', xhr, status, error);
            alert('Erro na requisição. Verifique o console.');
        }).always(function() {
            $btn.prop('disabled', false).text(originalText);
        });
    });
    
    // Conectar conta (OAuth)
    $('.action-connect-social').on('click', function() {
        const platform = $(this).data('platform');
        const $btn = $(this);
        
        console.log('Tentando conectar:', platform);
        
        // Verificar se credenciais foram salvas
        let clientId;
        if (platform === 'linkedin') {
            clientId = $('#linkedin_client_id').val();
        } else if (platform === 'instagram') {
            clientId = $('#instagram_client_id').val();
        } else if (platform === 'twitter') {
            clientId = $('#twitter_client_id').val();
        }
        
        console.log('Client ID encontrado:', clientId ? 'Sim' : 'Não');
        
        if (!clientId) {
            alert('⚠️ Salve as credenciais primeiro!\n\nClique em "Salvar Credenciais" antes de conectar.');
            return;
        }
        
        $btn.prop('disabled', true).text('Conectando...');
        
        // Buscar URL de autorização
        $.post(anrp_ajax.ajax_url, {
            action: 'anrp_get_oauth_url',
            nonce: anrp_ajax.nonce,
            platform: platform
        }).done(function(response) {
            console.log('Resposta OAuth URL:', response);
            
            if (response.success && response.data.auth_url) {
                console.log('URL de autorização gerada:', response.data.auth_url);
                
                // Abrir popup de autorização
                const width = 600;
                const height = 700;
                const left = (screen.width - width) / 2;
                const top = (screen.height - height) / 2;
                
                const popup = window.open(
                    response.data.auth_url,
                    'OAuth ' + platform.charAt(0).toUpperCase() + platform.slice(1),
                    `width=${width},height=${height},left=${left},top=${top},scrollbars=yes`
                );
                
                if (!popup) {
                    console.error('Popup foi bloqueado');
                    alert('⚠️ Popup bloqueado!\n\nPermita popups para este site e tente novamente.');
                    $btn.prop('disabled', false).text('Conectar Conta');
                    return;
                }
                
                console.log('Popup aberto com sucesso');
                
                // Monitorar fechamento do popup
                const checkPopup = setInterval(function() {
                    if (popup.closed) {
                        clearInterval(checkPopup);
                        console.log('Popup fechado');
                        $btn.prop('disabled', false).text('Conectar Conta');
                        // Recarregar página para atualizar status
                        setTimeout(function() {
                            location.reload();
                        }, 500);
                    }
                }, 500);
                
            } else {
                console.error('Erro ao gerar URL:', response);
                alert('Erro ao gerar URL de autorização: ' + (response.data?.message || 'Erro desconhecido'));
                $btn.prop('disabled', false).text('Conectar Conta');
            }
        }).fail(function(xhr, status, error) {
            console.error('Erro na requisição OAuth URL:', xhr, status, error);
            alert('Erro na requisição. Verifique o console.');
            $btn.prop('disabled', false).text('Conectar Conta');
        });
    });
    
    // Testar conexão
    $('.test-connection').on('click', function() {
        const platform = $(this).data('platform');
        const $btn = $(this);
        const originalText = $btn.text();
        
        $btn.prop('disabled', true).text('Testando...');
        
        $.post(anrp_ajax.ajax_url, {
            action: 'anrp_test_social_connection',
            nonce: anrp_ajax.nonce,
            platform: platform
        }).done(function(response) {
            if (response.success) {
                alert('✓ Conexão OK!\n\n' + (response.data?.message || 'Funcionando corretamente.'));
            } else {
                alert('✗ Erro na conexão:\n\n' + (response.data?.message || 'Erro desconhecido'));
            }
        }).fail(function() {
            alert('Erro na requisição.');
        }).always(function() {
            $btn.prop('disabled', false).text(originalText);
        });
    });
    
    // Salvar configurações gerais
    $('#save-social-settings').on('click', function() {
        const $btn = $(this);
        const originalText = $btn.text();
        
        $btn.prop('disabled', true).text('Salvando...');
        
        const settings = {
            auto_share: $('#social-auto-share').is(':checked'),
            image_template: $('#social-image-template').val()
        };
        
        $.post(anrp_ajax.ajax_url, {
            action: 'anrp_save_social_settings',
            nonce: anrp_ajax.nonce,
            settings: settings
        }).done(function(response) {
            if (response.success) {
                alert('✓ Configurações salvas!');
            } else {
                alert('Erro ao salvar.');
            }
        }).always(function() {
            $btn.prop('disabled', false).text(originalText);
        });
    });
    
    // Manual Share (se existir na lista de posts)
    $('.manual-share').on('click', function() {
        const postId = $(this).data('post-id');
        const platform = $(this).data('platform');
        const $btn = $(this);
        
        $btn.prop('disabled', true);
        
        $.post(anrp_ajax.ajax_url, {
            action: 'anrp_share_post',
            nonce: anrp_ajax.nonce,
            post_id: postId,
            platform: platform
        }).done(function(response) {
            if (response.success) {
                alert('✓ Publicado com sucesso!');
            } else {
                alert('Erro: ' + (response.data?.message || 'Erro desconhecido'));
            }
        }).always(function() {
            $btn.prop('disabled', false);
        });
    });
});