<?php
/**
 * View: Dashboard principal do plugin JEP Automacao v2.
 *
 * @package JEP_Automacao
 */

defined( 'ABSPATH' ) || exit;

$settings       = jep_automacao()->settings();
$logger         = jep_automacao()->logger();
$summary        = $logger->get_summary();
$recent         = $logger->get_logs( 10 );
$telegram_ok    = $settings->is_telegram_configured();
$instagram_ok   = $settings->is_instagram_enabled();
$nonce          = wp_create_nonce( 'jep_admin_nonce' );

$pipelines = array(
	'cold_content'  => array(
		'label' => __( 'Conteudo Frio', 'jep-automacao' ),
		'desc'  => __( 'Processa a proxima pauta do banco (seg/qua/sex 08h)', 'jep-automacao' ),
		'icon'  => 'dashicons-edit-page',
		'opt'   => 'cold',
	),
	'daily'         => array(
		'label' => __( 'Conteudo Diario', 'jep-automacao' ),
		'desc'  => __( 'Busca RSS + reescrita + envio ao Telegram (diario 06h)', 'jep-automacao' ),
		'icon'  => 'dashicons-rss',
		'opt'   => 'daily',
	),
	'topic_research' => array(
		'label' => __( 'Pesquisa de Pautas', 'jep-automacao' ),
		'desc'  => __( 'Gera 7 pautas por territorio (semanal segunda 07h)', 'jep-automacao' ),
		'icon'  => 'dashicons-search',
		'opt'   => 'research',
	),
	'rss_fetch'     => array(
		'label' => __( 'Buscar RSS Agora', 'jep-automacao' ),
		'desc'  => __( 'Forca a busca imediata de todos os feeds ativos', 'jep-automacao' ),
		'icon'  => 'dashicons-update',
		'opt'   => null,
	),
);
?>
<div class="wrap jep-wrap">
	<h1 class="jep-title">
		<span class="dashicons dashicons-superhero-alt"></span>
		<?php esc_html_e( 'JEP Automacao Editorial v2', 'jep-automacao' ); ?>
	</h1>

	<?php if ( ! $telegram_ok ) : ?>
		<div class="notice notice-warning">
			<p>
				<?php
				printf(
					/* translators: %s: link para configuracoes */
					esc_html__( 'Telegram ainda nao configurado. %s para inserir o token do bot e o Chat ID do editor.', 'jep-automacao' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=jep-automacao-settings' ) ) . '">' . esc_html__( 'Acesse as Configuracoes', 'jep-automacao' ) . '</a>'
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<!-- Status cards -->
	<div class="jep-cards">
		<div class="jep-card jep-card--<?php echo $telegram_ok ? 'success' : 'warning'; ?>">
			<span class="dashicons dashicons-<?php echo $telegram_ok ? 'yes-alt' : 'warning'; ?>"></span>
			<div>
				<strong><?php esc_html_e( 'Telegram', 'jep-automacao' ); ?></strong>
				<span><?php echo $telegram_ok ? esc_html__( 'Configurado', 'jep-automacao' ) : esc_html__( 'Nao configurado', 'jep-automacao' ); ?></span>
			</div>
		</div>

		<div class="jep-card jep-card--<?php echo $instagram_ok ? 'success' : 'info'; ?>">
			<span class="dashicons dashicons-camera" style="color:<?php echo $instagram_ok ? '#2ecc71' : '#aaa'; ?>"></span>
			<div>
				<strong><?php esc_html_e( 'Instagram', 'jep-automacao' ); ?></strong>
				<span><?php echo $instagram_ok ? esc_html__( 'Habilitado', 'jep-automacao' ) : esc_html__( 'Desativado', 'jep-automacao' ); ?></span>
			</div>
		</div>

		<div class="jep-card">
			<span class="dashicons dashicons-thumbs-up" style="color:#2ecc71"></span>
			<div>
				<strong><?php echo esc_html( $summary['success'] ); ?></strong>
				<span><?php esc_html_e( 'Sucessos', 'jep-automacao' ); ?></span>
			</div>
		</div>

		<div class="jep-card">
			<span class="dashicons dashicons-warning" style="color:#e67e22"></span>
			<div>
				<strong><?php echo esc_html( $summary['warning'] + $summary['error'] ); ?></strong>
				<span><?php esc_html_e( 'Alertas / Erros', 'jep-automacao' ); ?></span>
			</div>
		</div>
	</div>

	<!-- Executar pipelines manualmente -->
	<div class="jep-section">
		<h2><?php esc_html_e( 'Executar Pipelines Manualmente', 'jep-automacao' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Dispara o pipeline imediatamente, sem esperar o agendamento automatico.', 'jep-automacao' ); ?></p>

		<div class="jep-workflows">
			<?php foreach ( $pipelines as $pipeline_id => $pipeline ) : ?>
				<?php
				$enabled = is_null( $pipeline['opt'] ) || $settings->is_cron_enabled( $pipeline['opt'] );
				?>
				<div class="jep-workflow-card">
					<span class="dashicons <?php echo esc_attr( $pipeline['icon'] ); ?>"></span>
					<div class="jep-workflow-info">
						<strong><?php echo esc_html( $pipeline['label'] ); ?></strong>
						<small><?php echo esc_html( $pipeline['desc'] ); ?></small>
					</div>
					<button
						class="button button-primary jep-trigger-btn"
						data-pipeline="<?php echo esc_attr( $pipeline_id ); ?>"
						data-nonce="<?php echo esc_attr( $nonce ); ?>"
					>
						<?php esc_html_e( 'Executar Agora', 'jep-automacao' ); ?>
					</button>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

	<!-- Logs recentes -->
	<div class="jep-section">
		<h2>
			<?php esc_html_e( 'Atividade Recente', 'jep-automacao' ); ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=jep-automacao-logs' ) ); ?>" class="page-title-action">
				<?php esc_html_e( 'Ver todos', 'jep-automacao' ); ?>
			</a>
		</h2>

		<?php if ( empty( $recent ) ) : ?>
			<p class="description"><?php esc_html_e( 'Nenhuma atividade registrada ainda.', 'jep-automacao' ); ?></p>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped jep-log-table">
				<thead>
					<tr>
						<th width="150"><?php esc_html_e( 'Data', 'jep-automacao' ); ?></th>
						<th width="80"><?php esc_html_e( 'Nivel', 'jep-automacao' ); ?></th>
						<th width="150"><?php esc_html_e( 'Evento', 'jep-automacao' ); ?></th>
						<th><?php esc_html_e( 'Mensagem', 'jep-automacao' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $recent as $log ) : ?>
						<tr>
							<td><?php echo esc_html( wp_date( 'd/m/Y H:i', strtotime( $log->created_at ) ) ); ?></td>
							<td><span class="jep-badge jep-badge--<?php echo esc_attr( $log->level ); ?>"><?php echo esc_html( $log->level ); ?></span></td>
							<td><code><?php echo esc_html( $log->event ); ?></code></td>
							<td><?php echo esc_html( $log->message ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<!-- REST API / Webhook info -->
	<div class="jep-section jep-section--info">
		<h2><?php esc_html_e( 'REST API — Endpoints', 'jep-automacao' ); ?></h2>
		<p class="description">
			<?php
			printf(
				/* translators: %s: secret token */
				esc_html__( 'Token secreto REST: %s', 'jep-automacao' ),
				'<code>' . esc_html( $settings->get_rest_api_secret() ) . '</code>'
			);
			?>
		</p>
		<table class="jep-api-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Metodo', 'jep-automacao' ); ?></th>
					<th><?php esc_html_e( 'Endpoint', 'jep-automacao' ); ?></th>
					<th><?php esc_html_e( 'Descricao', 'jep-automacao' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td><code class="jep-method post">POST</code></td>
					<td><code><?php echo esc_html( rest_url( 'jep/v1/telegram-webhook' ) ); ?></code></td>
					<td><?php esc_html_e( 'Webhook do bot Telegram (callbacks de aprovacao)', 'jep-automacao' ); ?></td>
				</tr>
				<tr>
					<td><code class="jep-method get">GET</code></td>
					<td><code><?php echo esc_html( rest_url( 'jep/v1/status' ) ); ?></code></td>
					<td><?php esc_html_e( 'Status do plugin e resumo de logs', 'jep-automacao' ); ?></td>
				</tr>
				<tr>
					<td><code class="jep-method get">GET</code></td>
					<td><code><?php echo esc_html( rest_url( 'jep/v1/logs' ) ); ?></code></td>
					<td><?php esc_html_e( 'Consultar logs de atividade', 'jep-automacao' ); ?></td>
				</tr>
			</tbody>
		</table>
	</div>
</div>

<script>
jQuery( document ).ready( function( $ ) {
	$( '.jep-trigger-btn' ).on( 'click', function() {
		var $btn      = $( this );
		var pipeline  = $btn.data( 'pipeline' );
		var nonce     = $btn.data( 'nonce' );

		$btn.prop( 'disabled', true ).text( '<?php echo esc_js( __( 'Executando...', 'jep-automacao' ) ); ?>' );

		$.post( ajaxurl, { action: 'jep_run_pipeline', pipeline: pipeline, nonce: nonce }, function( res ) {
			if ( res.success ) {
				alert( res.data.message );
			} else {
				alert( res.data.message || '<?php echo esc_js( __( 'Erro ao executar.', 'jep-automacao' ) ); ?>' );
			}
		} ).always( function() {
			$btn.prop( 'disabled', false ).text( '<?php echo esc_js( __( 'Executar Agora', 'jep-automacao' ) ); ?>' );
		} );
	} );
} );
</script>
