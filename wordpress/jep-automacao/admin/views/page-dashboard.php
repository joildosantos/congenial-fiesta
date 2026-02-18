<?php
/**
 * View: Dashboard principal do plugin.
 *
 * @package JEP_Automacao
 */

defined( 'ABSPATH' ) || exit;

$settings  = jep_automacao()->settings();
$logger    = jep_automacao()->logger();
$summary   = $logger->get_summary();
$recent    = $logger->get_logs( 10 );
$n8n_ok    = $settings->is_n8n_configured();

$workflows = array(
	'conteudo-frio'        => array(
		'label' => 'Conteudo Frio',
		'desc'  => 'Pautas evergreen (seg/qua/sex)',
		'icon'  => 'dashicons-edit-page',
	),
	'conteudo-diario'      => array(
		'label' => 'Conteudo Diario',
		'desc'  => 'Noticias via RSS (diario 6h)',
		'icon'  => 'dashicons-rss',
	),
	'auto-pesquisa-pautas' => array(
		'label' => 'Pesquisa de Pautas',
		'desc'  => 'Gera pautas automaticamente (semanal)',
		'icon'  => 'dashicons-search',
	),
	'resumo-semanal'       => array(
		'label' => 'Resumo Semanal',
		'desc'  => 'Relatorio via Telegram (domingo 20h)',
		'icon'  => 'dashicons-chart-bar',
	),
);
?>
<div class="wrap jep-wrap">
	<h1 class="jep-title">
		<span class="dashicons dashicons-rss"></span>
		<?php esc_html_e( 'JEP Automacao Editorial', 'jep-automacao' ); ?>
	</h1>

	<?php if ( ! $n8n_ok ) : ?>
		<div class="notice notice-warning">
			<p>
				<?php
				printf(
					/* translators: %s: link para configuracoes */
					esc_html__( 'O plugin ainda nao esta configurado. %s para inserir a URL do webhook n8n.', 'jep-automacao' ),
					'<a href="' . esc_url( admin_url( 'admin.php?page=jep-automacao-settings' ) ) . '">' . esc_html__( 'Acesse as Configuracoes', 'jep-automacao' ) . '</a>'
				);
				?>
			</p>
		</div>
	<?php endif; ?>

	<!-- Status cards -->
	<div class="jep-cards">
		<div class="jep-card jep-card--<?php echo $n8n_ok ? 'success' : 'warning'; ?>">
			<span class="dashicons dashicons-<?php echo $n8n_ok ? 'yes-alt' : 'warning'; ?>"></span>
			<div>
				<strong><?php esc_html_e( 'n8n', 'jep-automacao' ); ?></strong>
				<span><?php echo $n8n_ok ? esc_html__( 'Configurado', 'jep-automacao' ) : esc_html__( 'Nao configurado', 'jep-automacao' ); ?></span>
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

		<div class="jep-card">
			<span class="dashicons dashicons-admin-post" style="color:#3498db"></span>
			<div>
				<strong><?php echo esc_html( $summary['info'] ); ?></strong>
				<span><?php esc_html_e( 'Eventos Info', 'jep-automacao' ); ?></span>
			</div>
		</div>
	</div>

	<!-- Disparar workflows -->
	<div class="jep-section">
		<h2><?php esc_html_e( 'Disparar Workflows Manualmente', 'jep-automacao' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Aciona um workflow no n8n imediatamente, sem esperar o agendamento automatico.', 'jep-automacao' ); ?></p>

		<div class="jep-workflows">
			<?php foreach ( $workflows as $workflow_id => $workflow ) : ?>
				<div class="jep-workflow-card">
					<span class="dashicons <?php echo esc_attr( $workflow['icon'] ); ?>"></span>
					<div class="jep-workflow-info">
						<strong><?php echo esc_html( $workflow['label'] ); ?></strong>
						<small><?php echo esc_html( $workflow['desc'] ); ?></small>
					</div>
					<button
						class="button button-primary jep-trigger-btn"
						data-workflow="<?php echo esc_attr( $workflow_id ); ?>"
						<?php disabled( ! $n8n_ok ); ?>
					>
						<?php esc_html_e( 'Disparar', 'jep-automacao' ); ?>
					</button>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

	<!-- Testar webhook -->
	<div class="jep-section">
		<h2><?php esc_html_e( 'Testar Conexao com n8n', 'jep-automacao' ); ?></h2>
		<button
			class="button button-secondary"
			id="jep-test-webhook"
			<?php disabled( ! $n8n_ok ); ?>
		>
			<span class="dashicons dashicons-admin-plugins"></span>
			<?php esc_html_e( 'Testar Webhook', 'jep-automacao' ); ?>
		</button>
		<span id="jep-test-result" style="margin-left:10px;"></span>
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

	<!-- REST API info -->
	<div class="jep-section jep-section--info">
		<h2><?php esc_html_e( 'REST API - Endpoints para n8n', 'jep-automacao' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Use o Token Secreto no header X-JEP-Token para autenticar requisicoes do n8n.', 'jep-automacao' ); ?></p>
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
					<td><code class="jep-method get">GET</code></td>
					<td><code><?php echo esc_html( rest_url( 'jep/v1/status' ) ); ?></code></td>
					<td><?php esc_html_e( 'Status do plugin e resumo de logs', 'jep-automacao' ); ?></td>
				</tr>
				<tr>
					<td><code class="jep-method post">POST</code></td>
					<td><code><?php echo esc_html( rest_url( 'jep/v1/posts' ) ); ?></code></td>
					<td><?php esc_html_e( 'Criar e publicar um post via n8n', 'jep-automacao' ); ?></td>
				</tr>
				<tr>
					<td><code class="jep-method post">POST</code></td>
					<td><code><?php echo esc_html( rest_url( 'jep/v1/media/from-url' ) ); ?></code></td>
					<td><?php esc_html_e( 'Upload de imagem a partir de URL', 'jep-automacao' ); ?></td>
				</tr>
				<tr>
					<td><code class="jep-method get">GET</code></td>
					<td><code><?php echo esc_html( rest_url( 'jep/v1/logs' ) ); ?></code></td>
					<td><?php esc_html_e( 'Consultar logs de atividade', 'jep-automacao' ); ?></td>
				</tr>
				<tr>
					<td><code class="jep-method post">POST</code></td>
					<td><code><?php echo esc_html( rest_url( 'jep/v1/logs' ) ); ?></code></td>
					<td><?php esc_html_e( 'Registrar log externo vindo do n8n', 'jep-automacao' ); ?></td>
				</tr>
			</tbody>
		</table>
	</div>
</div>
