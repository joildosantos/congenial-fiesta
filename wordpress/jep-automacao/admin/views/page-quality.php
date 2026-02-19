<?php
/**
 * View: Qualidade / Fontes
 *
 * @package JEP_Automacao_Editorial
 */

defined( 'ABSPATH' ) || exit;

$discovery = new JEP_Source_Discovery();
$evaluator = new JEP_Prompt_Evaluator();

// Handle actions.
if ( isset( $_POST['jep_quality_action'] ) && check_admin_referer( 'jep_quality_nonce' ) && current_user_can( 'manage_options' ) ) {
	$action = sanitize_key( $_POST['jep_quality_action'] );

	if ( 'run_discovery' === $action ) {
		$count = $discovery->run();
		echo '<div class="notice notice-success is-dismissible"><p>'
			. sprintf( esc_html__( '%d novas fontes descobertas.', 'jep-automacao' ), (int) $count )
			. '</p></div>';
	}

	if ( 'run_evaluation' === $action ) {
		$results = $evaluator->evaluate_recent();
		echo '<div class="notice notice-success is-dismissible"><p>'
			. sprintf( esc_html__( '%d prompts avaliados.', 'jep-automacao' ), count( $results ) )
			. '</p></div>';
	}
}

$sources = $discovery->get_sources();
$eval_summary = $evaluator->get_summary();
?>
<div class="wrap jep-wrap">
	<h1 class="jep-title">
		<span class="dashicons dashicons-chart-line"></span>
		<?php esc_html_e( 'Qualidade / Fontes', 'jep-automacao' ); ?>
	</h1>

	<!-- Source Discovery -->
	<div class="jep-section">
		<h2><?php esc_html_e( 'Descoberta de Fontes', 'jep-automacao' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Descobre automaticamente fontes confiaveis de informacao para alimentar o pipeline de conteudo.', 'jep-automacao' ); ?></p>

		<form method="post" style="margin-bottom:15px">
			<?php wp_nonce_field( 'jep_quality_nonce' ); ?>
			<input type="hidden" name="jep_quality_action" value="run_discovery">
			<button type="submit" class="button button-primary">
				<span class="dashicons dashicons-search"></span>
				<?php esc_html_e( 'Descobrir fontes agora', 'jep-automacao' ); ?>
			</button>
		</form>

		<?php if ( ! empty( $sources ) ) : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Fonte', 'jep-automacao' ); ?></th>
					<th width="15%"><?php esc_html_e( 'Tipo', 'jep-automacao' ); ?></th>
					<th width="15%"><?php esc_html_e( 'Territorio', 'jep-automacao' ); ?></th>
					<th width="10%"><?php esc_html_e( 'Score', 'jep-automacao' ); ?></th>
					<th width="12%"><?php esc_html_e( 'Status', 'jep-automacao' ); ?></th>
					<th width="15%"><?php esc_html_e( 'Descoberta em', 'jep-automacao' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $sources as $source ) : ?>
				<tr>
					<td>
						<strong><?php echo esc_html( $source['name'] ); ?></strong><br>
						<a href="<?php echo esc_url( $source['url'] ); ?>" target="_blank" class="description">
							<?php echo esc_html( $source['url'] ); ?>
						</a>
					</td>
					<td><code><?php echo esc_html( $source['type'] ); ?></code></td>
					<td><?php echo esc_html( $source['territory'] ?: '—' ); ?></td>
					<td>
						<strong style="color:<?php echo (float) $source['reliability_score'] >= 0.7 ? '#2ecc71' : '#e67e22'; ?>">
							<?php echo esc_html( number_format( (float) $source['reliability_score'], 2 ) ); ?>
						</strong>
					</td>
					<td>
						<span class="jep-badge jep-badge--<?php echo 'active' === $source['status'] ? 'success' : 'warning'; ?>">
							<?php echo esc_html( $source['status'] ); ?>
						</span>
					</td>
					<td><?php echo esc_html( wp_date( 'd/m/Y', strtotime( $source['created_at'] ) ) ); ?></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'Nenhuma fonte descoberta ainda.', 'jep-automacao' ); ?></p>
		<?php endif; ?>
	</div>

	<!-- Prompt Evaluator -->
	<div class="jep-section">
		<h2><?php esc_html_e( 'Avaliacao de Qualidade de Prompts', 'jep-automacao' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Avalia a qualidade das respostas dos LLMs para otimizar prompts ao longo do tempo.', 'jep-automacao' ); ?></p>

		<form method="post" style="margin-bottom:15px">
			<?php wp_nonce_field( 'jep_quality_nonce' ); ?>
			<input type="hidden" name="jep_quality_action" value="run_evaluation">
			<button type="submit" class="button button-secondary">
				<span class="dashicons dashicons-chart-bar"></span>
				<?php esc_html_e( 'Avaliar prompts recentes', 'jep-automacao' ); ?>
			</button>
		</form>

		<?php if ( ! empty( $eval_summary ) ) : ?>
		<div class="jep-cards">
			<?php foreach ( $eval_summary as $type => $data ) : ?>
			<div class="jep-card">
				<span class="dashicons dashicons-admin-generic"></span>
				<div>
					<strong><?php echo esc_html( $type ); ?></strong>
					<span>
						<?php
						printf(
							/* translators: 1: score, 2: total */
							esc_html__( 'Score medio: %1$s (%2$d amostras)', 'jep-automacao' ),
							esc_html( number_format( (float) $data['avg_score'], 2 ) ),
							(int) $data['total']
						);
						?>
					</span>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'Nenhuma avaliacao disponivel ainda. Execute o pipeline de conteudo primeiro.', 'jep-automacao' ); ?></p>
		<?php endif; ?>
	</div>
</div>
