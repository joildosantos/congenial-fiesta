<?php
// includes/class-anrp-bookmarklet.php

class ANRP_Bookmarklet {
    
    public function handle_request() {
        if (!get_option('anrp_bookmarklet_enabled', 1)) {
            wp_die('Bookmarklet desativado');
        }
        
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $this->show_form();
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->process_submission();
        }
    }
    
    private function show_form() {
        $url = isset($_GET['url']) ? esc_url($_GET['url']) : '';
        $token = $this->generate_token();
        
        ?>
        <!DOCTYPE html>
        <html lang="pt-BR">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Enviar para News Rewriter</title>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    margin: 0;
                    padding: 20px;
                }
                
                .container {
                    background: white;
                    border-radius: 10px;
                    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
                    width: 100%;
                    max-width: 500px;
                    overflow: hidden;
                }
                
                .header {
                    background: #4a5568;
                    color: white;
                    padding: 20px;
                    text-align: center;
                }
                
                .header h1 {
                    margin: 0;
                    font-size: 24px;
                }
                
                .header .logo {
                    font-size: 40px;
                    margin-bottom: 10px;
                }
                
                .content {
                    padding: 30px;
                }
                
                .form-group {
                    margin-bottom: 20px;
                }
                
                .form-group label {
                    display: block;
                    margin-bottom: 5px;
                    font-weight: 600;
                    color: #4a5568;
                }
                
                .form-group input[type="url"] {
                    width: 100%;
                    padding: 12px;
                    border: 2px solid #e2e8f0;
                    border-radius: 6px;
                    font-size: 16px;
                    transition: border-color 0.3s;
                }
                
                .form-group input[type="url"]:focus {
                    outline: none;
                    border-color: #667eea;
                }
                
                .submit-btn {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    border: none;
                    padding: 15px 30px;
                    font-size: 16px;
                    font-weight: 600;
                    border-radius: 6px;
                    cursor: pointer;
                    width: 100%;
                    transition: transform 0.2s;
                }
                
                .submit-btn:hover {
                    transform: translateY(-2px);
                }
                
                .submit-btn:disabled {
                    opacity: 0.6;
                    cursor: not-allowed;
                }
                
                .loading {
                    display: none;
                    text-align: center;
                    margin-top: 20px;
                }
                
                .loading-spinner {
                    border: 4px solid #f3f3f3;
                    border-top: 4px solid #667eea;
                    border-radius: 50%;
                    width: 40px;
                    height: 40px;
                    animation: spin 1s linear infinite;
                    margin: 0 auto 10px;
                }
                
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
                
                .message {
                    display: none;
                    padding: 15px;
                    border-radius: 6px;
                    margin-top: 20px;
                    text-align: center;
                }
                
                .message.success {
                    background: #c6f6d5;
                    color: #22543d;
                    border: 1px solid #9ae6b4;
                }
                
                .message.error {
                    background: #fed7d7;
                    color: #742a2a;
                    border: 1px solid #fc8181;
                }
                
                .options {
                    margin-top: 20px;
                    background: #f7fafc;
                    padding: 15px;
                    border-radius: 6px;
                    border: 1px solid #e2e8f0;
                }
                
                .option {
                    display: flex;
                    align-items: center;
                    margin-bottom: 10px;
                }
                
                .option:last-child {
                    margin-bottom: 0;
                }
                
                .option input[type="checkbox"] {
                    margin-right: 10px;
                }
                
                .option label {
                    color: #4a5568;
                }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <div class="logo">📰</div>
                    <h1>News Rewriter Pro</h1>
                    <p>Envie a notícia para processamento</p>
                </div>
                
                <div class="content">
                    <form id="bookmarklet-form">
                        <div class="form-group">
                            <label for="url">URL da Notícia:</label>
                            <input type="url" id="url" name="url" 
                                   value="<?php echo $url; ?>" 
                                   placeholder="https://exemplo.com/noticia" 
                                   required>
                        </div>
                        
                        <div class="options">
                            <div class="option">
                                <input type="checkbox" id="auto-publish" name="auto_publish" value="1">
                                <label for="auto-publish">Publicar automaticamente</label>
                            </div>
                            <div class="option">
                                <input type="checkbox" id="share-social" name="share_social" value="1" checked>
                                <label for="share-social">Compartilhar nas redes sociais</label>
                            </div>
                        </div>
                        
                        <input type="hidden" id="token" name="token" value="<?php echo $token; ?>">
                        
                        <button type="submit" class="submit-btn" id="submit-btn">
                            Enviar para Processamento
                        </button>
                    </form>
                    
                    <div class="loading" id="loading">
                        <div class="loading-spinner"></div>
                        <p>Processando notícia...</p>
                    </div>
                    
                    <div class="message" id="message"></div>
                </div>
            </div>
            
            <script>
                document.getElementById('bookmarklet-form').addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const form = e.target;
                    const submitBtn = document.getElementById('submit-btn');
                    const loading = document.getElementById('loading');
                    const message = document.getElementById('message');
                    
                    // Mostrar loading
                    submitBtn.disabled = true;
                    loading.style.display = 'block';
                    message.style.display = 'none';
                    
                    // Enviar via AJAX
                    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams(new FormData(form))
                    })
                    .then(response => response.json())
                    .then(data => {
                        loading.style.display = 'none';
                        
                        if (data.success) {
                            message.className = 'message success';
                            message.innerHTML = `
                                <h3>✅ Sucesso!</h3>
                                <p>${data.data.message}</p>
                                <p>ID do post: ${data.data.post_id}</p>
                                <p>Esta janela pode ser fechada.</p>
                            `;
                            
                            // Fechar automaticamente após 3 segundos
                            setTimeout(() => {
                                window.close();
                            }, 3000);
                        } else {
                            message.className = 'message error';
                            message.innerHTML = `
                                <h3>❌ Erro!</h3>
                                <p>${data.data.message}</p>
                                <p>Tente novamente ou entre em contato com o administrador.</p>
                            `;
                            submitBtn.disabled = false;
                        }
                        
                        message.style.display = 'block';
                    })
                    .catch(error => {
                        loading.style.display = 'none';
                        message.className = 'message error';
                        message.innerHTML = `
                            <h3>❌ Erro de Conexão!</h3>
                            <p>Verifique sua conexão e tente novamente.</p>
                        `;
                        message.style.display = 'block';
                        submitBtn.disabled = false;
                    });
                });
                
                // Focar no campo URL
                document.getElementById('url').focus();

                // Se a URL já foi fornecida pelo bookmarklet, redirecionar automaticamente
                (function(){
                    var provided = '<?php echo esc_js($url); ?>';
                    var token = '<?php echo esc_js($token); ?>';
                    if (provided && provided.length > 0) {
                        var dest = '<?php echo admin_url('admin.php?page=anrp-new-article'); ?>';
                        // manter os parâmetros originais: url e token
                        var u = encodeURIComponent(provided);
                        // usar replace para não poluir o histórico
                        window.location.replace(dest + '&url=' + u + '&token=' + encodeURIComponent(token));
                    }
                })();
            </script>
        </body>
        </html>
        <?php
    }
    
    private function process_submission() {
        $token = sanitize_text_field($_POST['token'] ?? '');
        $posted_url = $_POST['url'] ?? '';

        // Normalizar URL (decodificar URI encoding antes de comparar)
        $raw_post_url = rawurldecode($posted_url);
        $url = sanitize_url($raw_post_url);

        // Validar token usando a URL normalizada
        $stored_token = get_transient('anrp_bookmarklet_token_' . md5($raw_post_url));
        if (!$stored_token || $stored_token !== $token) {
            wp_send_json_error(['message' => 'Token inválido ou expirado']);
        }
        
        // Processar a URL
        $core = ANRP_Core::get_instance();
        
        try {
            $result = $core->process_article([
                'url' => $url,
                'rewriting_method' => 'textcortex',
                'publish_option' => isset($_POST['auto_publish']) ? 'publish' : 'draft',
                'author_id' => get_option('anrp_default_author', 0),
                'source_type' => 'bookmarklet',
                'share_social' => isset($_POST['share_social'])
            ]);
            
            wp_send_json_success([
                'message' => 'Notícia processada com sucesso!',
                'post_id' => $result['post_id'],
                'post_url' => $result['post_url']
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }
    
    private function generate_token() {
        $url = isset($_GET['url']) ? rawurldecode($_GET['url']) : '';
        $token = wp_generate_password(32, false);

        // Armazenar token por 5 minutos usando URL normalizada
        set_transient('anrp_bookmarklet_token_' . md5($url), $token, 300);

        return $token;
    }
    
    public function get_bookmarklet_code() {
        $site_url = site_url();
        $token = wp_create_nonce('anrp_bookmarklet');
        
        // Abrir diretamente a página de "Nova Notícia" para criar um post com a URL enviada
        $admin_page = admin_url('admin.php?page=anrp-new-article');

        $code = "javascript:(function(){";
        $code .= "var url=encodeURIComponent(window.location.href);";
        $code .= "var popup=window.open('{$admin_page}&url='+url+'&_wpnonce={$token}','anrpBookmarklet','width=600,height=700,resizable=yes,scrollbars=yes');";
        $code .= "if(window.focus) popup.focus();";
        $code .= "})()";
        
        return $code;
    }
}