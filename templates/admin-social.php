    <?php
    // templates/admin-social.php
    $linkedin_config = $this->social_share->get_config('linkedin');
    $instagram_config = $this->social_share->get_config('instagram');
    $twitter_config = $this->social_share->get_config('twitter');
    ?>
<div class="wrap anrp-social">
    <h1>Compartilhamento Social</h1>
    
    <div class="social-platforms">
        <!-- LinkedIn -->
        <div class="platform-card linkedin">
            <h2><span class="dashicons dashicons-linkedin"></span> LinkedIn</h2>
            <div class="platform-settings">
                <p>Crie um app no <a href="https://www.linkedin.com/developers/apps" target="_blank">LinkedIn Developers</a>.</p>
                <p><strong>Callback URL:</strong> <code><?php echo admin_url('admin-post.php?action=anrp_social_callback&platform=linkedin'); ?></code></p>
                
                <table class="form-table">
                     <tr>
                        <th scope="row"><label for="linkedin_client_id">Client ID:</label></th>
                        <td><input type="text" id="linkedin_client_id" class="regular-text" value="<?php echo esc_attr($linkedin_config['client_id'] ?? ''); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="linkedin_client_secret">Client Secret:</label></th>
                        <td><input type="password" id="linkedin_client_secret" class="regular-text" value="<?php echo esc_attr($linkedin_config['client_secret'] ?? ''); ?>"></td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><label>Automático:</label></th>
                        <td>
                            <input type="checkbox" id="linkedin-auto-share" <?php checked($linkedin_config['enabled'] ?? false); ?>> Ativar
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Status:</th>
                        <td>
                             <?php if (!empty($linkedin_config['access_token'])): ?>
                                <span class="status-badge status-good">Conectado <?php echo !empty($linkedin_config['username']) ? '(' . esc_html($linkedin_config['username']) . ')' : ''; ?></span>
                             <?php else: ?>
                                <span class="status-badge status-bad">Desconectado</span>
                             <?php endif; ?>
                        </td>
                    </tr>
                </table>
                <div class="actions">
                    <p class="description">Salve as credenciais abaixo antes de conectar.</p>
                    <button type="button" class="button button-primary action-save-social" data-platform="linkedin">Salvar Credenciais</button>
                    <button type="button" class="button button-secondary action-connect-social" data-platform="linkedin">Conectar Conta</button>
                </div>
            </div>
        </div>

        <!-- Instagram (Meta) -->
        <div class="platform-card instagram">
             <h2><span class="dashicons dashicons-instagram"></span> Instagram Business</h2>
             <div class="platform-settings">
                <p>Crie um app no <a href="https://developers.facebook.com/" target="_blank">Meta Developers</a> (Tipo: Empresa).</p>
                <p>Adicione o produto "Instagram Graph API".</p>
                <p><strong>Callback URL:</strong> <code><?php echo admin_url('admin-post.php?action=anrp_social_callback&platform=instagram'); ?></code></p>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="instagram_client_id">App ID:</label></th>
                         <td><input type="text" id="instagram_client_id" class="regular-text" value="<?php echo esc_attr($instagram_config['client_id'] ?? ''); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="instagram_client_secret">App Secret:</label></th>
                         <td><input type="password" id="instagram_client_secret" class="regular-text" value="<?php echo esc_attr($instagram_config['client_secret'] ?? ''); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label>Automático:</label></th>
                        <td>
                            <input type="checkbox" id="instagram-auto-share" <?php checked($instagram_config['enabled'] ?? false); ?>> Ativar
                        </td>
                    </tr>
                     <tr>
                        <th scope="row">Status:</th>
                        <td>
                             <?php if (!empty($instagram_config['access_token'])): ?>
                                <span class="status-badge status-good">Conectado (<?php echo esc_html($instagram_config['username'] ?? 'User'); ?>)</span>
                             <?php else: ?>
                                <span class="status-badge status-bad">Desconectado</span>
                             <?php endif; ?>
                        </td>
                    </tr>
                </table>
                 <div class="actions">
                    <p class="description">Salve as credenciais abaixo antes de conectar.</p>
                    <button type="button" class="button button-primary action-save-social" data-platform="instagram">Salvar Credenciais</button>
                    <button type="button" class="button button-secondary action-connect-social" data-platform="instagram">Conectar Conta</button>
                </div>
            </div>
        </div>
        
        <!-- Twitter -->
        <div class="platform-card twitter">
            <h2><span class="dashicons dashicons-twitter"></span> Twitter / X</h2>
            <div class="platform-settings">
                <p>Crie um app no <a href="https://developer.twitter.com/en/portal/dashboard" target="_blank">Twitter Developer Portal</a>.</p>
                <p>Configure User Authentication Settings (OAuth 2.0). Type: Web App.</p>
                <p><strong>Callback URL:</strong> <code><?php echo admin_url('admin-post.php?action=anrp_social_callback&platform=twitter'); ?></code></p>

                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="twitter_client_id">Client ID:</label></th>
                        <td><input type="text" id="twitter_client_id" class="regular-text" value="<?php echo esc_attr($twitter_config['client_id'] ?? ''); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="twitter_client_secret">Client Secret:</label></th>
                        <td><input type="password" id="twitter_client_secret" class="regular-text" value="<?php echo esc_attr($twitter_config['client_secret'] ?? ''); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label>Automático:</label></th>
                        <td>
                            <input type="checkbox" id="twitter-auto-share" <?php checked($twitter_config['enabled'] ?? false); ?>> Ativar
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Status:</th>
                        <td>
                             <?php if (!empty($twitter_config['access_token'])): ?>
                                <span class="status-badge status-good">Conectado</span>
                             <?php else: ?>
                                <span class="status-badge status-bad">Desconectado</span>
                             <?php endif; ?>
                        </td>
                    </tr>
                </table>
                 <div class="actions">
                    <p class="description">Salve as credenciais abaixo antes de conectar.</p>
                    <button type="button" class="button button-primary action-save-social" data-platform="twitter">Salvar Credenciais</button>
                    <button type="button" class="button button-secondary action-connect-social" data-platform="twitter">Conectar Conta</button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="card">
        <h2>Configurações Gerais</h2>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="social-auto-share">Compartilhamento Automático:</label></th>
                <td>
                    <input type="checkbox" id="social-auto-share" name="anrp_social_auto_share" 
                           value="1" <?php checked(get_option('anrp_social_auto_share', 0), 1); ?>>
                    <label for="social-auto-share">Compartilhar automaticamente todas as publicações</label>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><label for="social-image-template">Template de Imagem:</label></th>
                <td>
                    <select id="social-image-template">
                        <option value="default">Template Padrão</option>
                        <!-- Outros templates serão carregados via AJAX -->
                    </select>
                    <a href="<?php echo admin_url('admin.php?page=anrp-image-editor'); ?>" class="button button-small">
                        Gerenciar Templates
                    </a>
                </td>
            </tr>
            
            <tr>
                <th scope="row"><label>Compartilhamento Manual:</label></th>
                <td>
                    <p>Para compartilhar uma publicação manualmente, vá para a lista de posts e use as ações de compartilhamento.</p>
                </td>
            </tr>
        </table>
        
        <p class="submit">
            <button type="button" id="save-social-settings" class="button button-primary">
                Salvar Configurações
            </button>
        </p>
    </div>
</div>

<style>
.anrp-social .social-platforms {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.anrp-social .platform-card {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.anrp-social .platform-card h2 {
    margin-top: 0;
    color: #333;
    border-bottom: 2px solid;
    padding-bottom: 10px;
}

.anrp-social .platform-card.instagram h2 {
    border-color: #E1306C;
}

.anrp-social .platform-card.twitter h2 {
    border-color: #1DA1F2;
}

.anrp-social .connection-status {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 20px;
}

.anrp-social .status {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 3px;
    font-weight: bold;
    margin-right: 10px;
}

.anrp-social .status.connected {
    background: #d4edda;
    color: #155724;
}

.anrp-social .status.disconnected {
    background: #f8d7da;
    color: #721c24;
}

.anrp-social .platform-card input[type="text"],
.anrp-social .platform-card input[type="password"] {
    width: 100%;
    max-width: 100%;
    box-sizing: border-box;
    margin-bottom: 5px;
}
</style>