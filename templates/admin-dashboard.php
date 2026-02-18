<?php
/**
 * Admin Dashboard Template - CRIA Releituras
 * Design System CRIA S/A
 */
global $wpdb;

// Estatísticas
$total_posts = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}anrp_history");
$posts_today = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}anrp_history WHERE DATE(published_date) = %s",
    current_time('Y-m-d')
));
$posts_week = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}anrp_history WHERE published_date >= %s",
    date('Y-m-d', strtotime('-7 days'))
));
$active_feeds = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}anrp_feeds WHERE active = 1");

// Últimos posts
$recent_posts = $wpdb->get_results(
    "SELECT * FROM {$wpdb->prefix}anrp_history ORDER BY published_date DESC LIMIT 5"
);

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

    <!-- Page Header -->
    <div class="anrp-page-header">
        <div>
            <h1><span class="dashicons dashicons-dashboard"></span> Dashboard</h1>
            <p class="anrp-page-subtitle">Visão geral do seu sistema de curadoria de notícias</p>
        </div>
        <div class="anrp-flex anrp-gap-md">
            <a href="<?php echo admin_url('admin.php?page=anrp-new-article'); ?>" class="anrp-btn anrp-btn-primary">
                <span class="dashicons dashicons-plus-alt"></span> Nova Matéria
            </a>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="anrp-stats-grid">
        <div class="anrp-stat-card">
            <div class="anrp-stat-icon">📰</div>
            <div class="anrp-stat-label">Total de Matérias</div>
            <div class="anrp-stat-value"><?php echo number_format($total_posts); ?></div>
        </div>
        
        <div class="anrp-stat-card stat-success">
            <div class="anrp-stat-icon">📅</div>
            <div class="anrp-stat-label">Publicadas Hoje</div>
            <div class="anrp-stat-value"><?php echo number_format($posts_today); ?></div>
        </div>
        
        <div class="anrp-stat-card stat-info">
            <div class="anrp-stat-icon">📊</div>
            <div class="anrp-stat-label">Últimos 7 Dias</div>
            <div class="anrp-stat-value"><?php echo number_format($posts_week); ?></div>
        </div>
        
        <div class="anrp-stat-card stat-warning">
            <div class="anrp-stat-icon">📡</div>
            <div class="anrp-stat-label">Feeds Ativos</div>
            <div class="anrp-stat-value"><?php echo number_format($active_feeds); ?></div>
        </div>
    </div>

    <!-- Main Grid -->
    <div class="anrp-dashboard-grid">
        <!-- Recent Posts -->
        <div class="anrp-card">
            <div class="anrp-card-header">
                <h3>📋 Últimas Publicações</h3>
                <a href="<?php echo admin_url('admin.php?page=anrp-history'); ?>" class="anrp-btn anrp-btn-ghost anrp-btn-sm">
                    Ver Todas
                </a>
            </div>
            <div class="anrp-card-body" style="padding:0;">
                <?php if ($recent_posts): ?>
                <table class="anrp-table">
                    <thead>
                        <tr>
                            <th>Título</th>
                            <th>Data</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_posts as $post): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html(mb_substr($post->new_title, 0, 50)); ?></strong>
                                <?php if (strlen($post->new_title) > 50) echo '...'; ?>
                            </td>
                            <td style="color:var(--slate-400);font-size:13px;">
                                <?php echo date_i18n('d/m/Y H:i', strtotime($post->published_date)); ?>
                            </td>
                            <td>
                                <?php if ($post->status === 'published' || $post->status === 'publish'): ?>
                                    <span class="anrp-badge anrp-badge-success">Publicado</span>
                                <?php else: ?>
                                    <span class="anrp-badge anrp-badge-warning">Rascunho</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($post->post_id): ?>
                                <a href="<?php echo get_edit_post_link($post->post_id); ?>" class="anrp-btn anrp-btn-ghost anrp-btn-sm" target="_blank">
                                    Editar
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="anrp-empty-state">
                    <span class="dashicons dashicons-media-text"></span>
                    <p>Nenhuma publicação ainda</p>
                    <a href="<?php echo admin_url('admin.php?page=anrp-new-article'); ?>" class="anrp-btn anrp-btn-primary anrp-mt-md">
                        Criar Primeira Matéria
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div>
            <div class="anrp-card">
                <div class="anrp-card-header">
                    <h3>⚡ Ações Rápidas</h3>
                </div>
                <div class="anrp-card-body">
                    <div style="display:flex;flex-direction:column;gap:12px;">
                        <a href="<?php echo admin_url('admin.php?page=anrp-new-article'); ?>" class="anrp-btn anrp-btn-primary" style="width:100%;justify-content:flex-start;">
                            <span class="dashicons dashicons-plus-alt"></span> Nova Matéria
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=anrp-image-editor'); ?>" class="anrp-btn anrp-btn-secondary" style="width:100%;justify-content:flex-start;">
                            <span class="dashicons dashicons-format-image"></span> Editor de Imagem
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=anrp-feeds'); ?>" class="anrp-btn anrp-btn-secondary" style="width:100%;justify-content:flex-start;">
                            <span class="dashicons dashicons-rss"></span> Gerenciar Feeds
                        </a>
                        <a href="<?php echo admin_url('admin.php?page=anrp-settings'); ?>" class="anrp-btn anrp-btn-ghost" style="width:100%;justify-content:flex-start;">
                            <span class="dashicons dashicons-admin-settings"></span> Configurações
                        </a>
                    </div>
                </div>
            </div>

            <!-- Tips -->
            <div class="anrp-card anrp-mt-lg" style="background:linear-gradient(135deg, var(--slate-800), var(--slate-900));border-color:var(--cria-lime);">
                <div class="anrp-card-body">
                    <div style="display:flex;align-items:flex-start;gap:16px;">
                        <div style="width:48px;height:48px;background:var(--cria-lime);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="#0A0A0A">
                                <path d="M12 2 L14 9 L21 10 L14 11 L12 18 L10 11 L3 10 L10 9 Z"/>
                            </svg>
                        </div>
                        <div>
                            <h4 style="font-size:16px;margin-bottom:8px;color:var(--cria-lime);">Dica CRIA</h4>
                            <p style="font-size:13px;color:var(--slate-300);margin:0;line-height:1.5;">
                                Use o Editor de Imagem para criar posts profissionais para Instagram com o visual do Espaço do Povo.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
