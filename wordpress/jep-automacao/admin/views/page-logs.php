<?php
/**
 * View: Pagina de Logs.
 *
 * @package JEP_Automacao
 */

defined( 'ABSPATH' ) || exit;

$logger  = jep_automacao()->logger();
$level   = sanitize_text_field( wp_unslash( $_GET['level'] ?? '' ) );
$paged   = max( 1, absint( $_GET['paged'] ?? 1 ) );
$limit   = 50;
$offset  = ( $paged - 1 ) * $limit;
$logs    = $logger->get_logs( $limit, $offset, $level );
$total   = $logger->count_logs( $level );
$pages   = ceil( $total / $limit );
$summary = $logger->get_summary();

$level_labels = array(
	''        => __( 'Todos', 'jep-automacao' ),
	'info'    => __( 'Info', 'jep-automacao' ),
	'success' => __( 'Sucesso', 'jep-automacao' ),
	'warning' => __( 'Aviso', 'jep-automacao' ),
	'error'   => __( 'Erro', 'jep-automacao' ),
);
?>
<div class="wrap jep-wrap">
	<h1 class="jep-title">
		<span class="dashicons dashicons-list-view"></span>
		<?php esc_html_e( 'Logs de Atividade', 'jep-automacao' ); ?>
	</h1>

	<!-- Resumo -->
	<div class="jep-cards" style="margin-bottom:20px;">
		<?php foreach ( array( 'info', 'success', 'warning', 'error' ) as $lvl ) : ?>
			<a class="jep-card jep-card--link <?php echo ( $level === $lvl ) ? 'jep-card--active' : ''; ?>"
			   href="<?php echo esc_url( add_query_arg( array( 'page' => 'jep-automacao-logs', 'level' => $lvl, 'paged' => 1 ), admin_url( 'admin.php' ) ) ); ?>">
				<strong><?php echo esc_html( $summary[ $lvl ] ); ?></strong>
				<span><?php echo esc_html( $level_labels[ $lvl ] ); ?></span>
			</a>
		<?php endforeach; ?>
	</div>

	<!-- Filtro por nivel -->
	<div class="tablenav top">
		<div class="alignleft actions">
			<?php foreach ( $level_labels as $lvl => $label ) : ?>
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'jep-automacao-logs', 'level' => $lvl, 'paged' => 1 ), admin_url( 'admin.php' ) ) ); ?>"
				   class="button <?php echo ( $level === $lvl ) ? 'button-primary' : 'button-secondary'; ?>" style="margin-right:4px;">
					<?php echo esc_html( $label ); ?>
				</a>
			<?php endforeach; ?>
		</div>

		<div class="alignright actions">
			<button class="button button-secondary" id="jep-clear-logs-30"><?php esc_html_e( 'Limpar +30 dias', 'jep-automacao' ); ?></button>
			<button class="button button-link-delete" id="jep-clear-logs-all" style="margin-left:8px;"><?php esc_html_e( 'Limpar Todos', 'jep-automacao' ); ?></button>
		</div>
		<br class="clear">
	</div>

	<!-- Tabela -->
	<?php if ( empty( $logs ) ) : ?>
		<p class="description"><?php esc_html_e( 'Nenhum log encontrado.', 'jep-automacao' ); ?></p>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped jep-log-table">
			<thead>
				<tr>
					<th width="150"><?php esc_html_e( 'Data', 'jep-automacao' ); ?></th>
					<th width="90"><?php esc_html_e( 'Nivel', 'jep-automacao' ); ?></th>
					<th width="180"><?php esc_html_e( 'Evento', 'jep-automacao' ); ?></th>
					<th width="60"><?php esc_html_e( 'Post', 'jep-automacao' ); ?></th>
					<th><?php esc_html_e( 'Mensagem', 'jep-automacao' ); ?></th>
					<th width="60"><?php esc_html_e( 'Contexto', 'jep-automacao' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $logs as $log ) : ?>
					<tr>
						<td><?php echo esc_html( wp_date( 'd/m/Y H:i:s', strtotime( $log->created_at ) ) ); ?></td>
						<td><span class="jep-badge jep-badge--<?php echo esc_attr( $log->level ); ?>"><?php echo esc_html( $log->level ); ?></span></td>
						<td><code><?php echo esc_html( $log->event ); ?></code></td>
						<td>
							<?php if ( $log->post_id ) : ?>
								<a href="<?php echo esc_url( get_edit_post_link( $log->post_id ) ); ?>" target="_blank">#<?php echo esc_html( $log->post_id ); ?></a>
							<?php else : ?>
								&mdash;
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $log->message ); ?></td>
						<td>
							<?php if ( $log->context ) : ?>
								<a href="#" class="jep-show-context" data-context="<?php echo esc_attr( $log->context ); ?>" title="<?php esc_attr_e( 'Ver contexto', 'jep-automacao' ); ?>">
									<span class="dashicons dashicons-info"></span>
								</a>
							<?php else : ?>
								&mdash;
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<!-- Paginacao -->
		<?php if ( $pages > 1 ) : ?>
			<div class="tablenav bottom">
				<div class="tablenav-pages">
					<?php
					echo wp_kses_post( paginate_links( array(
						'base'    => add_query_arg( 'paged', '%#%' ),
						'format'  => '',
						'current' => $paged,
						'total'   => $pages,
						'prev_text' => '&laquo;',
						'next_text' => '&raquo;',
					) ) );
					?>
				</div>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>

<!-- Modal de contexto -->
<div id="jep-context-modal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,.5); z-index:9999; align-items:center; justify-content:center;">
	<div style="background:#fff; padding:20px; border-radius:8px; max-width:600px; width:90%; max-height:80vh; overflow:auto; position:relative;">
		<button id="jep-close-modal" style="position:absolute; top:10px; right:10px; background:none; border:none; font-size:20px; cursor:pointer;">&times;</button>
		<h3><?php esc_html_e( 'Contexto do Log', 'jep-automacao' ); ?></h3>
		<pre id="jep-context-content" style="background:#f1f1f1; padding:10px; border-radius:4px; overflow:auto; white-space:pre-wrap;"></pre>
	</div>
</div>
