<?php
/**
 * Admin Feeds Template - News Rewriter Pro v3.0
 */
$users = get_users(['role__in' => ['author', 'editor', 'administrator']]);
$default_author = intval(get_option('anrp_default_author', 0));
?>
<div class="wrap anrp-wrap">
    <div class="anrp-page-header">
        <div>
            <h1><span class="dashicons dashicons-rss"></span> Feeds RSS</h1>
            <p class="anrp-page-subtitle">Monitore fontes de notícias automaticamente</p>
        </div>
        <a href="<?php echo admin_url('admin.php?page=anrp-dashboard'); ?>" class="anrp-btn anrp-btn-ghost">
            <span class="dashicons dashicons-arrow-left-alt"></span> Voltar
        </a>
    </div>

    <div class="anrp-dashboard-grid">
        <!-- Lista de Feeds -->
        <div class="anrp-col-8">
            <div class="anrp-card">
                <div class="anrp-card-header">
                    <h2><span class="dashicons dashicons-list-view"></span> Feeds Cadastrados</h2>
                    <span id="anrp-feeds-count" class="anrp-badge anrp-badge-neutral">0 feeds</span>
                </div>
                <div class="anrp-card-body" style="padding:0;">
                    <div id="anrp-feeds-list" style="padding:var(--anrp-space-lg);">
                        <div class="anrp-loading">
                            <div class="anrp-spinner"></div>
                            <div class="anrp-loading-text">Carregando feeds...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Adicionar Feed -->
        <div class="anrp-col-4">
            <div class="anrp-card">
                <div class="anrp-card-header">
                    <h2><span class="dashicons dashicons-plus-alt"></span> Adicionar Feed</h2>
                </div>
                <div class="anrp-card-body">
                    <form id="anrp-add-feed-form">
                        <div class="anrp-form-group">
                            <label class="anrp-form-label">URL do Feed <span class="required">*</span></label>
                            <input type="url" name="feed_url" id="feed-url-input" class="anrp-form-input" placeholder="https://site.com/feed" required>
                            <p class="anrp-form-hint">URL do feed RSS ou Atom. Exemplo: https://g1.globo.com/rss/g1/</p>
                        </div>

                        <div class="anrp-form-group">
                            <label class="anrp-form-label">Nome do Feed</label>
                            <input type="text" name="feed_name" class="anrp-form-input" placeholder="Ex: Portal de Notícias">
                        </div>

                        <div class="anrp-form-group">
                            <label class="anrp-form-label">Categoria Padrão</label>
                            <?php wp_dropdown_categories(['name' => 'category_id', 'show_option_none' => 'Detectar automaticamente', 'option_none_value' => '0', 'class' => 'anrp-form-select', 'hide_empty' => false]); ?>
                        </div>

                        <div class="anrp-form-group">
                            <label class="anrp-form-label">Autor Padrão</label>
                            <select name="author_id" class="anrp-form-select">
                                <option value="0">Usar autor padrão do sistema</option>
                                <?php foreach ($users as $u): ?>
                                <option value="<?php echo esc_attr($u->ID); ?>" <?php selected($u->ID, $default_author); ?>>
                                    <?php echo esc_html($u->display_name ?: $u->user_login); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="anrp-form-group">
                            <label class="anrp-form-label">Tipo de Agendamento</label>
                            <select name="schedule_type" id="schedule_type" class="anrp-form-select">
                                <option value="immediate">⚡ Processar imediatamente</option>
                                <option value="scheduled">📅 Horário específico</option>
                            </select>
                        </div>

                        <div class="anrp-form-group schedule-time-row" style="display:none;">
                            <label class="anrp-form-label">Horário</label>
                            <input type="time" name="schedule_time" class="anrp-form-input" value="08:00">
                        </div>

                        <div class="anrp-form-group">
                            <label class="anrp-checkbox">
                                <input type="checkbox" name="auto_publish" value="1">
                                <span>🚀 Publicar automaticamente</span>
                            </label>
                            <p class="anrp-form-hint">Se desativado, os posts serão salvos como rascunho</p>
                        </div>

                        <div class="anrp-mt-lg">
                            <button type="submit" class="anrp-btn anrp-btn-primary" style="width:100%;">
                                <span class="dashicons dashicons-plus-alt"></span> Adicionar Feed
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Info Card -->
            <div class="anrp-card anrp-mt-lg">
                <div class="anrp-card-header">
                    <h2><span class="dashicons dashicons-info"></span> Sobre Feeds RSS</h2>
                </div>
                <div class="anrp-card-body">
                    <p style="font-size:13px;color:var(--anrp-gray-600);line-height:1.7;">
                        Os feeds RSS são verificados <strong>a cada hora</strong> automaticamente. 
                        Novos itens encontrados são processados de acordo com suas configurações.
                    </p>
                    <div style="margin-top:16px;padding:12px;background:var(--anrp-info-bg);border-radius:var(--anrp-radius-md);">
                        <strong style="color:var(--anrp-info);display:block;margin-bottom:4px;">💡 Dica</strong>
                        <span style="font-size:13px;color:var(--anrp-gray-700);">
                            Para encontrar o feed de um site, procure por um ícone RSS ou adicione <code>/feed</code> ou <code>/rss</code> à URL.
                        </span>
                    </div>
                </div>
            </div>

            <!-- Feeds Populares -->
            <div class="anrp-card anrp-mt-lg">
                <div class="anrp-card-header">
                    <h2><span class="dashicons dashicons-star-filled"></span> Feeds Sugeridos</h2>
                </div>
                <div class="anrp-card-body">
                    <div style="display:flex;flex-direction:column;gap:8px;">
                        <?php
                        $suggested = [
                            ['G1', 'https://g1.globo.com/rss/g1/'],
                            ['UOL Notícias', 'https://noticias.uol.com.br/ultimas/index.xml'],
                            ['Folha de SP', 'https://feeds.folha.uol.com.br/emcimadahora/rss091.xml'],
                            ['Terra', 'https://www.terra.com.br/rss/controller.htm?path=/home'],
                        ];
                        foreach ($suggested as $feed): ?>
                        <button type="button" class="anrp-btn anrp-btn-ghost anrp-btn-sm use-suggested-feed" 
                                data-url="<?php echo esc_attr($feed[1]); ?>" 
                                data-name="<?php echo esc_attr($feed[0]); ?>"
                                style="justify-content:flex-start;text-align:left;">
                            📡 <?php echo esc_html($feed[0]); ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Toggle schedule time field
    $('#schedule_type').on('change', function() {
        if ($(this).val() === 'scheduled') {
            $('.schedule-time-row').slideDown();
        } else {
            $('.schedule-time-row').slideUp();
        }
    });

    // Use suggested feed
    $('.use-suggested-feed').on('click', function() {
        $('input[name="feed_url"]').val($(this).data('url'));
        $('input[name="feed_name"]').val($(this).data('name'));
        $('input[name="feed_url"]').focus();
        ANRP.Toast.info('Feed preenchido! Clique em "Adicionar Feed" para salvar.');
    });
});
</script>
