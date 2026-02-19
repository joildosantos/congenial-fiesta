<?php
/**
 * Admin view: Logs
 *
 * @package JEP_Automacao
 */

defined( 'ABSPATH' ) || exit;

$logger = jep_automacao()->logger();

// Handle clear actions.
if ( isset( $_POST['jep_clear_logs_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['jep_clear_logs_nonce'] ) ), 'jep_clear_logs' ) ) {
	if ( isset( $_POST['clear_type'] ) ) {
		if ( 'old' === $_POST['clear_type'] ) {
			$deleted = $logger->prune( 30 );
			echo '<div class="notice notice-success is-dismissible"><p>' . sprintf( esc_html__( '%d registros mais antigos que 30 dias removidos.', 'jep-automacao' ), (int) $deleted ) . '</p></div>';
		} elseif ( 'all' === $_POST['clear_type'] ) {
			global $wpdb;
			$deleted = $wpdb->query( "DELETE FROM {$wpdb->prefix}jep_logs" );
			echo '<div class="notice notice-success is-dismissible"><p>' . sprintf( esc_html__( '%d registros removidos.', 'jep-automacao' ), (int) $deleted ) . '</p></div>';
		}
	}
}

$level        = isset( $_GET['level'] ) ? sanitize_text_field( wp_unslash( $_GET['level'] ) ) : '';
$per_page     = 50;
$current_page = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
$offset       = ( $current_page - 1 ) * $per_page;
$total        = $logger->count_logs( $level );
$logs         = $logger->get_logs( $per_page, $offset, $level );
$total_pages  = (int) ceil( $total / $per_page );

$level_labels = array(
	'info'    => array( 'label' => 'Info',    'class' => 'jep-badge-info' ),
	'success' => array( 'label' => 'Sucesso', 'class' => 'jep-badge-success' ),
	'warning' => array( 'label' => 'Aviso',   'class' => 'jep-badge-warning' ),
	'error'   => array( 'label' => 'Erro',    'class' => 'jep-badge-error' ),
);
?>
<div class="wrap jep-wrap">
	<h1><?php esc_html_e( 'Logs de Atividade', 'jep-automacao' ); ?></h1>

	<div class="jep-logs-toolbar">
		<form method="get">
			<input type="hidden" name="page" value="jep-automacao-logs">
			<select name="level" onchange="this.form.submit()">
				<option value=""><?php esc_html_e( 'Todos os níveis', 'jep-automacao' ); ?></option>
				<?php foreach ( $level_labels as $key => $meta ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $level, $key ); ?>>
						<?php echo esc_html( $meta['label'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</form>
		<form method="post" style="display:inline-block; margin-left: 16px;">
			<?php wp_nonce_field( 'jep_clear_logs', 'jep_clear_logs_nonce' ); ?>
			<button type="submit" name="clear_type" value="old" class="button">
				<?php esc_html_e( '🗑 Limpar +30 dias', 'jep-automacao' ); ?>
			</button>
			<button type="submit" name="clear_type" value="all" class="button button-link-delete"
				onclick="return confirm('Remover TODOS os logs? Esta ação não pode ser desfeita.')">
				<?php esc_html_e( '🗑 Limpar Tudo', 'jep-automacao' ); ?>
			</button>
		</form>
		<span class="jep-log-total"><?php echo esc_html( $total . ' registros' ); ?></span>
	</div>

	<?php if ( empty( $logs ) ) : ?>
		<div class="jep-empty-state">
			<span class="dashicons dashicons-list-view" style="font-size:48px; color:#ccc;"></span>
			<p><?php esc_html_e( 'Nenhum registro encontrado.', 'jep-automacao' ); ?></p>
		</div>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th style="width:80px"><?php esc_html_e( 'Nível', 'jep-automacao' ); ?></th>
					<th style="width:200px"><?php esc_html_e( 'Evento', 'jep-automacao' ); ?></th>
					<th><?php esc_html_e( 'Mensagem', 'jep-automacao' ); ?></th>
					<th style="width:70px"><?php esc_html_e( 'Post', 'jep-automacao' ); ?></th>
					<th style="width:160px"><?php esc_html_e( 'Data/Hora', 'jep-automacao' ); ?></th>
					<th style="width:70px"><?php esc_html_e( 'Ctx', 'jep-automacao' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $logs as $log ) :
					$meta = $level_labels[ $log->level ] ?? array( 'label' => $log->level, 'class' => '' );
				?>
				<tr>
					<td><span class="jep-badge <?php echo esc_attr( $meta['class'] ); ?>"><?php echo esc_html( $meta['label'] ); ?></span></td>
					<td><code><?php echo esc_html( $log->event ); ?></code></td>
					<td><?php echo esc_html( $log->message ); ?></td>
					<td>
						<?php if ( $log->post_id ) : ?>
							<a href="<?php echo esc_url( get_edit_post_link( $log->post_id ) ); ?>" target="_blank">#<?php echo (int) $log->post_id; ?></a>
						<?php else : ?>&mdash;<?php endif; ?>
					</td>
					<td><?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $log->created_at ) ) ); ?></td>
					<td>
						<?php if ( ! empty( $log->context ) ) : ?>
							<button class="button button-small jep-view-context" data-context="<?php echo esc_attr( $log->context ); ?>">Ver</button>
						<?php else : ?>&mdash;<?php endif; ?>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $total_pages > 1 ) :
			$base_url = add_query_arg( array( 'page' => 'jep-automacao-logs', 'level' => $level ), admin_url( 'admin.php' ) );
		?>
		<div class="tablenav bottom"><div class="tablenav-pages">
			<?php if ( $current_page > 1 ) : ?>
				<a class="button" href="<?php echo esc_url( add_query_arg( 'paged', $current_page - 1, $base_url ) ); ?>">&laquo; Anterior</a>
			<?php endif; ?>
			<span class="displaying-num">Página <?php echo (int) $current_page; ?> de <?php echo (int) $total_pages; ?></span>
			<?php if ( $current_page < $total_pages ) : ?>
				<a class="button" href="<?php echo esc_url( add_query_arg( 'paged', $current_page + 1, $base_url ) ); ?>">Próxima &raquo;</a>
			<?php endif; ?>
		</div></div>
		<?php endif; ?>
	<?php endif; ?>
</div>

<div id="jep-context-modal" class="jep-modal" style="display:none;">
	<div class="jep-modal-content">
		<button class="jep-modal-close">&times;</button>
		<h2>Contexto do Log</h2>
		<pre id="jep-context-content" style="background:#f1f1f1;padding:12px;overflow:auto;max-height:400px;font-size:12px;"></pre>
	</div>
</div>

<style>
.jep-logs-toolbar{display:flex;align-items:center;gap:12px;margin:12px 0;flex-wrap:wrap;}
.jep-log-total{margin-left:auto;color:#666;font-size:13px;}
</style>
<script>
jQuery(function($){
	$('.jep-view-context').on('click',function(){
		var raw=$(this).data('context'),pretty='';
		try{pretty=JSON.stringify(JSON.parse(raw),null,2);}catch(e){pretty=raw;}
		$('#jep-context-content').text(pretty);
		$('#jep-context-modal').fadeIn(200);
	});
	$(document).on('click','.jep-modal-close,.jep-modal',function(e){
		if($(e.target).is('.jep-modal-close,.jep-modal'))$('#jep-context-modal').fadeOut(200);
	});
});
</script>
