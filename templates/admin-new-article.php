<?php
/**
 * Admin New Article Template - CRIA Releituras
 * Design System CRIA S/A
 */
$provided_url = '';
$bookmarklet_token = '';
if (!empty($_GET['url'])) {
    $provided_url = esc_url_raw(rawurldecode($_GET['url']));
}
if (!empty($_GET['token'])) {
    $bookmarklet_token = sanitize_text_field($_GET['token']);
}
$default_author = intval(get_option('anrp_default_author', 0));
$users = get_users(['role__in' => ['author', 'editor', 'administrator']]);
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
            <div class="anrp-brand-tagline">Curadoria inteligente de conteúdo</div>
        </div>
    </div>

    <div class="anrp-page-header">
        <div>
            <h1><span class="dashicons dashicons-plus-alt"></span> Nova Matéria</h1>
            <p class="anrp-page-subtitle">Transforme qualquer URL em conteúdo único para seu site</p>
        </div>
        <a href="<?php echo admin_url('admin.php?page=anrp-dashboard'); ?>" class="anrp-btn anrp-btn-ghost">
            <span class="dashicons dashicons-arrow-left-alt"></span> Voltar
        </a>
    </div>

    <div class="anrp-dashboard-grid">
        <div class="anrp-col-8">
            <div class="anrp-card">
                <div class="anrp-card-header">
                    <h2><span class="dashicons dashicons-edit"></span> Processar Matéria</h2>
                </div>
                <div class="anrp-card-body">
                    <form id="anrp-new-article-form">
                        <input type="hidden" id="bookmarklet_token" name="bookmarklet_token" value="<?php echo esc_attr($bookmarklet_token); ?>">
                        <input type="hidden" id="article_title" name="article_title" value="">
                        <input type="hidden" id="article_content" name="article_content" value="">

                        <div class="anrp-form-group">
                            <label class="anrp-form-label">URL da Matéria <span style="color:var(--cria-lime);">*</span></label>
                            <div class="anrp-input-with-icon">
                                <span class="anrp-input-icon dashicons dashicons-admin-links"></span>
                                <input type="url" id="article-url" name="article_url" class="anrp-form-input" 
                                       placeholder="https://site.com/noticia-para-reescrever" 
                                       value="<?php echo esc_attr($provided_url); ?>" required>
                            </div>
                            <p class="anrp-form-hint" style="color:var(--slate-400);font-size:12px;margin-top:4px;">Cole o link completo da matéria que deseja transformar</p>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
                            <div class="anrp-form-group">
                                <label class="anrp-form-label">Autor</label>
                                <select name="post_author" class="anrp-form-select">
                                    <option value="0">Selecione um autor</option>
                                    <?php foreach ($users as $u): ?>
                                    <option value="<?php echo esc_attr($u->ID); ?>" <?php selected($u->ID, $default_author); ?>>
                                        <?php echo esc_html($u->display_name ?: $u->user_login); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="anrp-form-group">
                                <label class="anrp-form-label">Categoria</label>
                                <?php wp_dropdown_categories(['name' => 'category_id', 'show_option_none' => 'Detectar automaticamente', 'option_none_value' => '0', 'class' => 'anrp-form-select', 'hide_empty' => false]); ?>
                            </div>
                        </div>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
                            <div class="anrp-form-group">
                                <label class="anrp-form-label">Método de Reescrita</label>
                                <?php 
                                $has_openrouter = !empty(get_option('anrp_openrouter_key'));
                                $has_gemini = !empty(get_option('anrp_gemini_key'));
                                $openrouter_model = get_option('anrp_openrouter_model', 'google/gemini-2.0-flash-exp:free');
                                ?>
                                <select id="rewriting_method" name="rewriting_method" class="anrp-form-select">
                                    <?php if ($has_openrouter): ?>
                                    <option value="openrouter" selected>🚀 OpenRouter (Recomendado)</option>
                                    <?php endif; ?>
                                    <?php if ($has_gemini): ?>
                                    <option value="gemini" <?php echo !$has_openrouter ? 'selected' : ''; ?>>🤖 Google Gemini</option>
                                    <?php endif; ?>
                                    <option value="auto" <?php echo (!$has_openrouter && !$has_gemini) ? 'selected' : ''; ?>>🔄 Automático (melhor disponível)</option>
                                    <option value="basic">📝 Básico (sem IA)</option>
                                </select>
                                <?php if (!$has_openrouter && !$has_gemini): ?>
                                <p class="anrp-form-hint" style="color:var(--anrp-warning);">
                                    ⚠️ Nenhuma API configurada. <a href="<?php echo admin_url('admin.php?page=anrp-settings'); ?>">Configurar APIs</a>
                                </p>
                                <?php endif; ?>
                            </div>
                            <div class="anrp-form-group">
                                <label class="anrp-form-label">Publicação</label>
                                <select id="publish_option" name="publish_option" class="anrp-form-select">
                                    <option value="draft">📋 Salvar como Rascunho</option>
                                    <option value="publish">🚀 Publicar Imediatamente</option>
                                    <option value="schedule">📅 Agendar Publicação</option>
                                </select>
                            </div>
                        </div>

                        <div class="anrp-form-group schedule-date-row" style="display:none;">
                            <label class="anrp-form-label">Data e Hora</label>
                            <input type="datetime-local" id="schedule_date" name="schedule_date" class="anrp-form-input" style="max-width:300px;">
                        </div>

                        <div class="anrp-form-group">
                            <label class="anrp-form-label">Tags Adicionais</label>
                            <input type="text" id="tags_input" name="tags_input" class="anrp-form-input" placeholder="Separe com vírgulas">
                            <p class="anrp-form-hint">Tags padrão serão adicionadas automaticamente</p>
                        </div>

                        <div class="anrp-form-group">
                            <label class="anrp-checkbox">
                                <input type="checkbox" id="share_social" name="share_social" value="1">
                                <span>🔗 Preparar para compartilhamento nas redes sociais</span>
                            </label>
                        </div>

                        <div class="anrp-mt-lg">
                            <button type="submit" class="anrp-btn anrp-btn-primary anrp-btn-lg" id="submit-article">
                                <span class="dashicons dashicons-admin-site-alt3"></span> Processar Notícia
                            </button>
                        </div>
                    </form>

                    <div id="processing-status" style="display:none;">
                        <div class="anrp-progress">
                            <div class="anrp-progress-bar">
                                <div class="anrp-progress-fill" style="width:0%;"></div>
                            </div>
                            <div class="anrp-progress-steps">
                                <div class="anrp-progress-step" data-step="scraping">
                                    <div class="step-icon">🔍</div>
                                    <div class="step-label">Extraindo</div>
                                </div>
                                <div class="anrp-progress-step" data-step="rewriting">
                                    <div class="step-icon">✍️</div>
                                    <div class="step-label">Reescrevendo</div>
                                </div>
                                <div class="anrp-progress-step" data-step="image">
                                    <div class="step-icon">🖼️</div>
                                    <div class="step-label">Imagem</div>
                                </div>
                                <div class="anrp-progress-step" data-step="publishing">
                                    <div class="step-icon">🚀</div>
                                    <div class="step-label">Publicando</div>
                                </div>
                            </div>
                        </div>
                        <div id="result-message"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="anrp-col-4">
            <div class="anrp-card">
                <div class="anrp-card-header">
                    <h2><span class="dashicons dashicons-info"></span> Como Funciona</h2>
                </div>
                <div class="anrp-card-body">
                    <div style="display:flex;flex-direction:column;gap:16px;">
                        <?php 
                        $steps = [
                            ['1', 'Cole a URL', 'Insira o link da notícia original'],
                            ['2', 'Processamento IA', 'O conteúdo é extraído e reescrito'],
                            ['3', 'Publicação', 'Post criado com imagem e tags'],
                            ['4', 'Compartilhe', 'Imagem pronta para Instagram']
                        ];
                        foreach ($steps as $s): ?>
                        <div style="display:flex;gap:12px;align-items:flex-start;">
                            <div style="width:32px;height:32px;background:linear-gradient(135deg,var(--anrp-primary),var(--anrp-secondary));border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;flex-shrink:0;"><?php echo $s[0]; ?></div>
                            <div>
                                <strong style="display:block;margin-bottom:2px;"><?php echo $s[1]; ?></strong>
                                <span style="font-size:13px;color:var(--anrp-gray-500);"><?php echo $s[2]; ?></span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="anrp-card anrp-mt-lg">
                <div class="anrp-card-header">
                    <h2><span class="dashicons dashicons-lightbulb"></span> Dicas</h2>
                </div>
                <div class="anrp-card-body">
                    <ul style="margin:0;padding-left:20px;font-size:13px;color:var(--anrp-gray-600);line-height:1.8;">
                        <li>Use URLs de páginas de notícias completas</li>
                        <li>Evite URLs de páginas de listagem</li>
                        <li>O Gemini produz os melhores resultados</li>
                        <li>Revise o conteúdo antes de publicar</li>
                        <li>Use o <a href="<?php echo admin_url('admin.php?page=anrp-bookmarklet'); ?>">Bookmarklet</a> para agilizar</li>
                    </ul>
                </div>
            </div>

            <div class="anrp-card anrp-mt-lg">
                <div class="anrp-card-header">
                    <h2><span class="dashicons dashicons-chart-bar"></span> Hoje</h2>
                </div>
                <div class="anrp-card-body">
                    <?php
                    global $wpdb;
                    $today_count = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}anrp_history WHERE DATE(published_date) = %s",
                        date('Y-m-d')
                    ));
                    ?>
                    <div style="text-align:center;">
                        <div style="font-size:48px;font-weight:800;color:var(--anrp-primary);"><?php echo intval($today_count); ?></div>
                        <div style="font-size:13px;color:var(--anrp-gray-500);">notícias processadas hoje</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Auto-submit se vier do bookmarklet com token válido
    var providedUrl = '<?php echo esc_js($provided_url); ?>';
    var token = '<?php echo esc_js($bookmarklet_token); ?>';
    if (providedUrl && token && token.length > 8) {
        setTimeout(function() { $('#anrp-new-article-form').submit(); }, 500);
    }
});
</script>
