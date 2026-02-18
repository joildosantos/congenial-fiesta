<?php
/**
 * Admin History Template - News Rewriter Pro v3.0
 */
?>
<div class="wrap anrp-wrap">
    <div class="anrp-page-header">
        <div>
            <h1><span class="dashicons dashicons-backup"></span> Histórico</h1>
            <p class="anrp-page-subtitle">Acompanhe todas as notícias processadas</p>
        </div>
        <div class="anrp-btn-group">
            <select id="anrp-history-filter" class="anrp-form-select" style="min-width:150px;">
                <option value="all">Todos os status</option>
                <option value="publish">Publicados</option>
                <option value="draft">Rascunhos</option>
                <option value="pending">Pendentes</option>
            </select>
        </div>
    </div>

    <div class="anrp-card">
        <div class="anrp-card-header">
            <h2><span class="dashicons dashicons-list-view"></span> Registros</h2>
            <span id="anrp-history-count" class="anrp-badge anrp-badge-neutral">0 registros</span>
        </div>
        <div class="anrp-card-body" style="padding:0;">
            <div class="anrp-table-wrapper">
                <table class="anrp-table" id="anrp-history-table">
                    <thead>
                        <tr>
                            <th style="width:35%;">Título</th>
                            <th style="width:25%;">URL Original</th>
                            <th style="width:12%;">Status</th>
                            <th style="width:15%;">Data</th>
                            <th style="width:13%;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="5">
                                <div class="anrp-loading">
                                    <div class="anrp-spinner"></div>
                                    <div class="anrp-loading-text">Carregando histórico...</div>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="anrp-card-footer">
            <div id="anrp-history-pagination" class="anrp-btn-group" style="justify-content:center;"></div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="anrp-stats-grid anrp-mt-lg">
        <?php
        global $wpdb;
        $table = $wpdb->prefix . 'anrp_history';
        
        $total = $wpdb->get_var("SELECT COUNT(*) FROM $table");
        $published = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'publish'");
        $drafts = $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'draft'");
        $today = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE DATE(published_date) = %s", date('Y-m-d')));
        ?>
        <div class="anrp-stat-card stat-primary">
            <div class="anrp-stat-icon">📊</div>
            <div class="anrp-stat-label">Total</div>
            <div class="anrp-stat-value"><?php echo number_format($total); ?></div>
        </div>
        <div class="anrp-stat-card stat-success">
            <div class="anrp-stat-icon">✅</div>
            <div class="anrp-stat-label">Publicados</div>
            <div class="anrp-stat-value"><?php echo number_format($published); ?></div>
        </div>
        <div class="anrp-stat-card stat-warning">
            <div class="anrp-stat-icon">📝</div>
            <div class="anrp-stat-label">Rascunhos</div>
            <div class="anrp-stat-value"><?php echo number_format($drafts); ?></div>
        </div>
        <div class="anrp-stat-card stat-info">
            <div class="anrp-stat-icon">📅</div>
            <div class="anrp-stat-label">Hoje</div>
            <div class="anrp-stat-value"><?php echo number_format($today); ?></div>
        </div>
    </div>
</div>
