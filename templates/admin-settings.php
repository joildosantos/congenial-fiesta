<?php
/**
 * Admin Settings Template - CRIA Releituras
 * Design System CRIA S/A
 */
$authors = get_users(['role__in' => ['administrator', 'editor', 'author']]);
$logo_url = get_option('anrp_logo_url', '');
?>
<div class="wrap anrp-wrap">
    <!-- Brand Header -->
    <div class="anrp-brand-header">
        <div class="anrp-brand-logo">
            <svg viewBox="0 0 24 24" fill="#0A0A0A">
                <path d="M12 2 L14 9 L21 10 L14 11 L12 18 L10 11 L3 10 L10 9 Z"/>
            </svg>
        </div>
        <div class="anrp-brand-text">
            <div class="anrp-brand-name">CRIA <span>Releituras</span></div>
            <div class="anrp-brand-tagline">Curadoria inteligente de conteúdo • Desenvolvido por CRIA S/A</div>
        </div>
    </div>

    <div class="anrp-page-header">
        <div>
            <h1><span class="dashicons dashicons-admin-settings"></span> Configurações</h1>
            <p class="anrp-page-subtitle">Personalize o funcionamento do CRIA Releituras</p>
        </div>
    </div>

    <form method="post" action="options.php">
        <?php settings_fields('anrp_settings_group'); ?>
        
        <div class="anrp-dashboard-grid">
            <div>
                <!-- Configurações Gerais -->
                <div class="anrp-card">
                    <div class="anrp-card-header">
                        <h3>⚙️ Configurações Gerais</h3>
                    </div>
                    <div class="anrp-card-body">
                        <div class="anrp-form-group">
                            <label class="anrp-form-label">Autor Padrão</label>
                            <select name="anrp_default_author" class="anrp-form-select">
                                <option value="0">Selecione um autor...</option>
                                <?php foreach ($authors as $author): ?>
                                <option value="<?php echo $author->ID; ?>" <?php selected(get_option('anrp_default_author'), $author->ID); ?>>
                                    <?php echo esc_html($author->display_name); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="anrp-form-group">
                            <label class="anrp-form-label">Status Padrão</label>
                            <select name="anrp_default_status" class="anrp-form-select">
                                <option value="draft" <?php selected(get_option('anrp_default_status', 'draft'), 'draft'); ?>>Rascunho</option>
                                <option value="publish" <?php selected(get_option('anrp_default_status'), 'publish'); ?>>Publicado</option>
                                <option value="pending" <?php selected(get_option('anrp_default_status'), 'pending'); ?>>Pendente</option>
                            </select>
                        </div>
                        
                        <div class="anrp-form-group">
                            <label class="anrp-form-label">Tags Padrão</label>
                            <input type="text" name="anrp_default_tags" class="anrp-form-input" 
                                   value="<?php echo esc_attr(get_option('anrp_default_tags', 'ig, territorio')); ?>"
                                   placeholder="tag1, tag2, tag3">
                            <small style="color:var(--slate-400);font-size:12px;margin-top:4px;display:block;">Separe as tags por vírgula</small>
                        </div>
                        
                        <div class="anrp-form-group">
                            <label class="anrp-form-label">Quantidade de Tags Automáticas</label>
                            <input type="number" name="anrp_auto_tags_count" class="anrp-form-input" 
                                   value="<?php echo esc_attr(get_option('anrp_auto_tags_count', 5)); ?>"
                                   min="1" max="15" style="width:100px;">
                        </div>
                    </div>
                </div>

                <!-- API de Reescrita -->
                <div class="anrp-card anrp-mt-lg">
                    <div class="anrp-card-header">
                        <h3>🤖 API de Reescrita (IA)</h3>
                    </div>
                    <div class="anrp-card-body">
                        <div class="anrp-form-group">
                            <label class="anrp-form-label">OpenRouter API Key</label>
                            <input type="password" name="anrp_openrouter_key" class="anrp-form-input" 
                                   value="<?php echo esc_attr(get_option('anrp_openrouter_key', '')); ?>"
                                   placeholder="sk-or-v1-xxxxx">
                            <small style="color:var(--slate-400);font-size:12px;margin-top:4px;display:block;">
                                Obtenha em <a href="https://openrouter.ai" target="_blank" style="color:var(--cria-lime);">openrouter.ai</a> - Suporta modelos gratuitos como Gemini
                            </small>
                        </div>
                        
                        <div class="anrp-form-group">
                            <label class="anrp-form-label">Modelo OpenRouter</label>
                            <select name="anrp_openrouter_model" class="anrp-form-select">
                                <option value="google/gemini-2.0-flash-exp:free" <?php selected(get_option('anrp_openrouter_model'), 'google/gemini-2.0-flash-exp:free'); ?>>Gemini 2.0 Flash (Gratuito)</option>
                                <option value="google/gemini-flash-1.5" <?php selected(get_option('anrp_openrouter_model'), 'google/gemini-flash-1.5'); ?>>Gemini 1.5 Flash</option>
                                <option value="anthropic/claude-3-haiku" <?php selected(get_option('anrp_openrouter_model'), 'anthropic/claude-3-haiku'); ?>>Claude 3 Haiku</option>
                                <option value="openai/gpt-4o-mini" <?php selected(get_option('anrp_openrouter_model'), 'openai/gpt-4o-mini'); ?>>GPT-4o Mini</option>
                            </select>
                        </div>
                        
                        <hr style="border-color:var(--slate-700);margin:24px 0;">
                        
                        <div class="anrp-form-group">
                            <label class="anrp-form-label">Google Gemini API Key (Alternativo)</label>
                            <input type="password" name="anrp_gemini_key" class="anrp-form-input" 
                                   value="<?php echo esc_attr(get_option('anrp_gemini_key', '')); ?>"
                                   placeholder="AIzaSy...">
                            <small style="color:var(--slate-400);font-size:12px;margin-top:4px;display:block;">
                                Obtenha em <a href="https://aistudio.google.com/app/apikey" target="_blank" style="color:var(--cria-lime);">Google AI Studio</a>
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <!-- Logo -->
                <div class="anrp-card">
                    <div class="anrp-card-header">
                        <h3>🖼️ Logo do Site</h3>
                    </div>
                    <div class="anrp-card-body">
                        <div class="anrp-form-group">
                            <label class="anrp-form-label">URL do Logo</label>
                            <div style="display:flex;gap:8px;">
                                <input type="url" name="anrp_logo_url" id="anrp_logo_url" class="anrp-form-input" 
                                       value="<?php echo esc_attr($logo_url); ?>"
                                       placeholder="https://...">
                                <button type="button" id="upload-logo-btn" class="anrp-btn anrp-btn-secondary">
                                    📤
                                </button>
                            </div>
                        </div>
                        
                        <?php if ($logo_url): ?>
                        <div style="margin-top:16px;padding:16px;background:var(--slate-900);border-radius:8px;text-align:center;">
                            <img src="<?php echo esc_url($logo_url); ?>" alt="Logo" style="max-width:200px;max-height:100px;">
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Opções -->
                <div class="anrp-card anrp-mt-lg">
                    <div class="anrp-card-header">
                        <h3>🔧 Opções</h3>
                    </div>
                    <div class="anrp-card-body">
                        <label style="display:flex;align-items:center;gap:12px;cursor:pointer;padding:12px;background:var(--slate-900);border-radius:8px;margin-bottom:12px;">
                            <input type="checkbox" name="anrp_notifications_enabled" value="1" 
                                   <?php checked(get_option('anrp_notifications_enabled', 1), 1); ?>
                                   style="width:20px;height:20px;accent-color:var(--cria-lime);">
                            <div>
                                <strong style="color:var(--cria-white);">Notificações</strong>
                                <small style="display:block;color:var(--slate-400);">Receber alertas de novas publicações</small>
                            </div>
                        </label>
                        
                        <label style="display:flex;align-items:center;gap:12px;cursor:pointer;padding:12px;background:var(--slate-900);border-radius:8px;margin-bottom:12px;">
                            <input type="checkbox" name="anrp_bookmarklet_enabled" value="1" 
                                   <?php checked(get_option('anrp_bookmarklet_enabled', 1), 1); ?>
                                   style="width:20px;height:20px;accent-color:var(--cria-lime);">
                            <div>
                                <strong style="color:var(--cria-white);">Bookmarklet</strong>
                                <small style="display:block;color:var(--slate-400);">Habilitar captura de notícias via navegador</small>
                            </div>
                        </label>
                        
                        <label style="display:flex;align-items:center;gap:12px;cursor:pointer;padding:12px;background:var(--slate-900);border-radius:8px;">
                            <input type="checkbox" name="anrp_social_auto_share" value="1" 
                                   <?php checked(get_option('anrp_social_auto_share', 0), 1); ?>
                                   style="width:20px;height:20px;accent-color:var(--cria-lime);">
                            <div>
                                <strong style="color:var(--cria-white);">Auto-compartilhamento</strong>
                                <small style="display:block;color:var(--slate-400);">Gerar imagem social automaticamente</small>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Salvar -->
                <div class="anrp-card anrp-mt-lg" style="background:linear-gradient(135deg, var(--cria-lime), var(--cria-lime-dark));">
                    <div class="anrp-card-body">
                        <button type="submit" class="anrp-btn" style="width:100%;background:var(--cria-black);color:var(--cria-lime);font-weight:700;padding:16px;">
                            💾 Salvar Configurações
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <!-- Footer -->
    <div style="margin-top:48px;padding-top:24px;border-top:1px solid var(--slate-700);display:flex;justify-content:space-between;align-items:center;">
        <div style="display:flex;align-items:center;gap:12px;">
            <div style="width:32px;height:32px;background:var(--cria-lime);border-radius:50%;display:flex;align-items:center;justify-content:center;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="#0A0A0A">
                    <path d="M12 2 L14 9 L21 10 L14 11 L12 18 L10 11 L3 10 L10 9 Z"/>
                </svg>
            </div>
            <span style="color:var(--slate-400);font-size:13px;">CRIA Releituras v<?php echo ANRP_VERSION; ?> • Desenvolvido por <strong style="color:var(--cria-lime);">CRIA S/A</strong></span>
        </div>
        <span style="color:var(--slate-500);font-size:12px;">DESDE 2007</span>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#upload-logo-btn').on('click', function() {
        var frame = wp.media({
            title: 'Selecionar Logo',
            button: { text: 'Usar este logo' },
            multiple: false
        });
        
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            $('#anrp_logo_url').val(attachment.url);
        });
        
        frame.open();
    });
});
</script>
