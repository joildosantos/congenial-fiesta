<?php
/**
 * View: Qualidade / Fontes Sugeridas
 *
 * @package JEP_Automacao_Editorial
 */

defined( 'ABSPATH' ) || exit;

$discovery = new JEP_Source_Discovery();
$evaluator = new JEP_Prompt_Evaluator();

// Handle POST actions.
if ( isset( $_POST['jep_quality_action'] ) && check_admin_referer( 'jep_quality_nonce' ) && current_user_can( 'manage_options' ) ) {
	$action = sanitize_key( $_POST['jep_quality_action'] );

	if ( 'run_discovery' === $action ) {
		$discovery->run();
		echo '<div class="notice notice-success is-dismissible"><p>'
			. esc_html__( 'Descoberta de fontes executada. Verifique a tabela abaixo.', 'jep-automacao' )
			. '</p></div>';
	}

	if ( 'approve_source' === $action && ! empty( $_POST['source_id'] ) ) {
		$result = $discovery->approve_suggestion( absint( $_POST['source_id'] ) );
		if ( is_wp_error( $result ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html( $result->get_error_message() ) . '</p></div>';
		} else {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Fonte aprovada e adicionada aos feeds RSS.', 'jep-automacao' ) . '</p></div>';
		}
	}

	if ( 'reject_source' === $action && ! empty( $_POST['source_id'] ) ) {
		$discovery->reject_suggestion( absint( $_POST['source_id'] ) );
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Fonte rejeitada.', 'jep-automacao' ) . '</p></div>';
	}
}

$suggestions = $discovery->get_pending_suggestions();
$avg_scores  = $evaluator->get_average_scores( 30 );
?>
<div class="wrap jep-wrap">
	<h1 class="jep-title">
		<span class="dashicons dashicons-chart-line"></span>
		<?php esc_html_e( 'Qualidade / Fontes Sugeridas', 'jep-automacao' ); ?>
	</h1>

	<!-- Source Discovery -->
	<div class="jep-section">
		<h2><?php esc_html_e( 'Fontes RSS Sugeridas pela IA', 'jep-automacao' ); ?></h2>
		<p class="description"><?php esc_html_e( 'A IA analisa os dominios dos itens RSS processados e sugere novos veiculos jornalisticos relevantes. Execute para buscar novas sugestoes ou aguarde o cron diario das 03h.', 'jep-automacao' ); ?></p>

		<form method="post" style="margin-bottom:15px">
			<?php wp_nonce_field( 'jep_quality_nonce' ); ?>
			<input type="hidden" name="jep_quality_action" value="run_discovery">
			<button type="submit" class="button button-primary">
				<span class="dashicons dashicons-search"></span>
				<?php esc_html_e( 'Descobrir fontes agora', 'jep-automacao' ); ?>
			</button>
		</form>

		<?php if ( ! empty( $suggestions ) ) : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Fonte', 'jep-automacao' ); ?></th>
					<th width="12%"><?php esc_html_e( 'Tipo', 'jep-automacao' ); ?></th>
					<th width="10%"><?php esc_html_e( 'Regiao', 'jep-automacao' ); ?></th>
					<th><?php esc_html_e( 'Justificativa', 'jep-automacao' ); ?></th>
					<th width="15%"><?php esc_html_e( 'Feed URL', 'jep-automacao' ); ?></th>
					<th width="18%"><?php esc_html_e( 'Acoes', 'jep-automacao' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $suggestions as $src ) : ?>
				<tr>
					<td>
						<strong><?php echo esc_html( $src['name'] ); ?></strong><br>
						<a href="<?php echo esc_url( $src['url'] ); ?>" target="_blank" class="description">
							<?php echo esc_html( $src['url'] ); ?>
						</a>
					</td>
					<td><code><?php echo esc_html( $src['type'] ); ?></code></td>
					<td><?php echo esc_html( $src['region'] ?: '—' ); ?></td>
					<td><small><?php echo esc_html( wp_trim_words( $src['justification'], 20 ) ); ?></small></td>
					<td>
						<?php if ( ! empty( $src['feed_url'] ) ) : ?>
							<a href="<?php echo esc_url( $src['feed_url'] ); ?>" target="_blank" class="description">
								<small><?php echo esc_html( $src['feed_url'] ); ?></small>
							</a>
						<?php else : ?>
							<span class="description">—</span>
						<?php endif; ?>
					</td>
					<td>
						<form method="post" style="display:inline">
							<?php wp_nonce_field( 'jep_quality_nonce' ); ?>
							<input type="hidden" name="jep_quality_action" value="approve_source">
							<input type="hidden" name="source_id" value="<?php echo absint( $src['id'] ); ?>">
							<button type="submit" class="button button-small button-primary">
								<?php esc_html_e( 'Aprovar', 'jep-automacao' ); ?>
							</button>
						</form>
						<form method="post" style="display:inline;margin-left:4px">
							<?php wp_nonce_field( 'jep_quality_nonce' ); ?>
							<input type="hidden" name="jep_quality_action" value="reject_source">
							<input type="hidden" name="source_id" value="<?php echo absint( $src['id'] ); ?>">
							<button type="submit" class="button button-small">
								<?php esc_html_e( 'Rejeitar', 'jep-automacao' ); ?>
							</button>
						</form>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'Nenhuma sugestao de fonte pendente. Execute a descoberta acima ou aguarde o cron das 03h.', 'jep-automacao' ); ?></p>
		<?php endif; ?>
	</div>

	<!-- Prompt Evaluator -->
	<div class="jep-section">
		<h2><?php esc_html_e( 'Qualidade de Prompts — Ultimos 30 dias', 'jep-automacao' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Score medio por tipo de prompt. A avaliacao ocorre automaticamente a cada 5 chamadas LLM.', 'jep-automacao' ); ?></p>

		<?php if ( ! empty( $avg_scores ) ) : ?>
		<div class="jep-cards">
			<?php foreach ( $avg_scores as $type => $data ) : ?>
			<?php $score = $data['avg_score_total']; ?>
			<div class="jep-card jep-card--<?php echo $score >= 7 ? 'success' : ( $score >= 5 ? 'info' : 'warning' ); ?>">
				<span class="dashicons dashicons-admin-generic"></span>
				<div>
					<strong><?php echo esc_html( $type ); ?></strong>
					<span>
						<?php
						printf(
							/* translators: 1: score, 2: total */
							esc_html__( 'Score medio: %1$.1f/10 (%2$d amostras)', 'jep-automacao' ),
							(float) $score,
							(int) $data['total_evaluations']
						);
						?>
					</span>
					<small style="color:#888">
						<?php
						printf(
							'Clareza: %s | Tom: %s | SEO: %s',
							esc_html( $data['avg_clareza'] ),
							esc_html( $data['avg_tom'] ),
							esc_html( $data['avg_seo'] )
						);
						?>
					</small>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
		<?php else : ?>
			<p class="description"><?php esc_html_e( 'Nenhuma avaliacao disponivel ainda. Execute o pipeline de conteudo para gerar amostras.', 'jep-automacao' ); ?></p>
		<?php endif; ?>
	</div>
</div>
