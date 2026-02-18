<?php
// templates/admin-bookmarklet.php
?>
<div class="wrap anrp-bookmarklet">
    <h1>Bookmarklet do News Rewriter Pro</h1>
    
    <div class="card">
        <h2>Instruções de Uso</h2>
        <p>Arraste o botão abaixo para a barra de favoritos do seu navegador. Quando encontrar uma notícia que deseja republicar, clique no bookmarklet na barra de favoritos.</p>
        
        <div class="bookmarklet-button">
            <a href="<?php echo $this->bookmarklet->get_bookmarklet_code(); ?>" class="button button-primary button-hero">
                📰 Publicar no News Rewriter
            </a>
            <p class="description">Arraste este botão para a barra de favoritos</p>
        </div>
        
        <h3>Instruções por Navegador:</h3>
        <div class="browser-instructions">
            <div class="browser chrome">
                <h4>Google Chrome</h4>
                <ol>
                    <li>Arraste o botão acima para a barra de favoritos</li>
                    <li>Se a barra de favoritos não estiver visível, pressione Ctrl+Shift+B</li>
                    <li>Clique no bookmarklet quando estiver em uma página de notícia</li>
                </ol>
            </div>
            
            <div class="browser firefox">
                <h4>Mozilla Firefox</h4>
                <ol>
                    <li>Arraste o botão acima para a barra de marcadores</li>
                    <li>Para mostrar a barra de marcadores, pressione Ctrl+Shift+B</li>
                    <li>Clique no bookmarklet quando estiver em uma página de notícia</li>
                </ol>
            </div>
            
            <div class="browser safari">
                <h4>Safari</h4>
                <ol>
                    <li>Ative a barra de favoritos em Visualizar → Mostrar Barra de Favoritos</li>
                    <li>Arraste o botão acima para a barra de favoritos</li>
                    <li>Clique no bookmarklet quando estiver em uma página de notícia</li>
                </ol>
            </div>
        </div>
    </div>
    
    <div class="card">
        <h2>Código do Bookmarklet</h2>
        <p>Se não conseguir arrastar o botão, copie e cole o código abaixo em um novo favorito:</p>
        
        <textarea id="bookmarklet-code" rows="4" readonly><?php echo esc_textarea($this->bookmarklet->get_bookmarklet_code()); ?></textarea>
        <button id="copy-code" class="button button-secondary">Copiar Código</button>
    </div>
    
    <div class="card">
        <h2>Configurações</h2>
        <form method="post" action="options.php">
            <?php settings_fields('anrp_settings_group'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="bookmarklet-default-author">Autor Padrão:</label></th>
                    <td>
                        <?php
                        wp_dropdown_users([
                            'name' => 'anrp_default_author',
                            'selected' => get_option('anrp_default_author', 0),
                            'show_option_none' => 'Usar autor padrão do sistema',
                            'role__in' => ['author', 'editor', 'administrator']
                        ]);
                        ?>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="bookmarklet-default-status">Status Padrão:</label></th>
                    <td>
                        <select name="anrp_default_status" id="bookmarklet-default-status">
                            <option value="draft" <?php selected(get_option('anrp_default_status', 'draft'), 'draft'); ?>>Rascunho</option>
                            <option value="publish" <?php selected(get_option('anrp_default_status', 'draft'), 'publish'); ?>>Publicar</option>
                            <option value="pending" <?php selected(get_option('anrp_default_status', 'draft'), 'pending'); ?>>Revisão</option>
                        </select>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><label for="bookmarklet-auto-share">Compartilhamento Automático:</label></th>
                    <td>
                        <input type="checkbox" name="anrp_social_auto_share" id="bookmarklet-auto-share" 
                               value="1" <?php checked(get_option('anrp_social_auto_share', 0), 1); ?>>
                        <label for="bookmarklet-auto-share">Compartilhar automaticamente nas redes sociais</label>
                    </td>
                </tr>
            </table>
            
            <?php submit_button('Salvar Configurações do Bookmarklet'); ?>
        </form>
    </div>
    
    <div class="card">
        <h2>Estatísticas do Bookmarklet</h2>
        <div class="stats-grid">
            <div class="stat">
                <h3>Total de Envios</h3>
                <p class="stat-number"><?php echo $this->history_manager->get_count_by_source('bookmarklet'); ?></p>
            </div>
            
            <div class="stat">
                <h3>Publicados</h3>
                <p class="stat-number"><?php echo $this->history_manager->get_published_count_by_source('bookmarklet'); ?></p>
            </div>
            
            <div class="stat">
                <h3>Taxa de Sucesso</h3>
                <p class="stat-number">
                    <?php
                    $total = $this->history_manager->get_count_by_source('bookmarklet');
                    $published = $this->history_manager->get_published_count_by_source('bookmarklet');
                    echo $total > 0 ? round(($published / $total) * 100) . '%' : '0%';
                    ?>
                </p>
            </div>
        </div>
    </div>
</div>

<style>
.anrp-bookmarklet .bookmarklet-button {
    text-align: center;
    padding: 30px;
    background: #f8f9fa;
    border: 2px dashed #007cba;
    border-radius: 8px;
    margin: 20px 0;
}

.anrp-bookmarklet .bookmarklet-button a {
    font-size: 20px;
    padding: 20px 40px;
}

.anrp-bookmarklet .browser-instructions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 30px;
}

.anrp-bookmarklet .browser {
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 6px;
    padding: 20px;
}

.anrp-bookmarklet .browser h4 {
    margin-top: 0;
    color: #333;
    border-bottom: 2px solid;
    padding-bottom: 10px;
}

.anrp-bookmarklet .browser.chrome h4 {
    border-color: #4285f4;
}

.anrp-bookmarklet .browser.firefox h4 {
    border-color: #ff7139;
}

.anrp-bookmarklet .browser.safari h4 {
    border-color: #000000;
}

.anrp-bookmarklet .browser ol {
    margin-left: 20px;
}

.anrp-bookmarklet #bookmarklet-code {
    width: 100%;
    font-family: monospace;
    margin-bottom: 10px;
}

.anrp-bookmarklet .stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.anrp-bookmarklet .stat {
    text-align: center;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 6px;
    border: 1px solid #e9ecef;
}

.anrp-bookmarklet .stat h3 {
    margin: 0 0 10px 0;
    font-size: 14px;
    color: #6c757d;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.anrp-bookmarklet .stat .stat-number {
    font-size: 36px;
    font-weight: bold;
    color: #007cba;
    margin: 0;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Copiar código do bookmarklet
    $('#copy-code').on('click', function() {
        var code = $('#bookmarklet-code')[0];
        code.select();
        document.execCommand('copy');
        
        var originalText = $(this).text();
        $(this).text('Código copiado!');
        
        setTimeout(function() {
            $('#copy-code').text(originalText);
        }, 2000);
    });
});
</script>