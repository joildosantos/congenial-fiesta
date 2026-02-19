<?php
/**
 * View: Provedores LLM
 *
 * @package JEP_Automacao_Editorial
 */

defined( 'ABSPATH' ) || exit;

$llm       = new JEP_LLM_Manager();
$providers = $llm->get_providers();
$usage     = $llm->get_usage_summary( 30 );

// Handle form actions.
if ( isset( $_POST['jep_llm_action'] ) && check_admin_referer( 'jep_llm_nonce' ) && current_user_can( 'manage_options' ) ) {
	$action = sanitize_key( $_POST['jep_llm_action'] );

	if ( 'add' === $action ) {
		$llm->add_provider( array(
			'name'          => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
			'provider_type' => sanitize_key( $_POST['provider_type'] ?? 'openrouter' ),
			'api_key'       => sanitize_text_field( wp_unslash( $_POST['api_key'] ?? '' ) ),
			'base_url'      => esc_url_raw( $_POST['base_url'] ?? '' ),
			'model'         => sanitize_text_field( wp_unslash( $_POST['model'] ?? '' ) ),
			'priority'      => absint( $_POST['priority'] ?? 10 ),
			'monthly_limit' => absint( $_POST['monthly_limit'] ?? 0 ),
			'is_active'     => 1,
		) );
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Provedor adicionado.', 'jep-automacao' ) . '</p></div>';
		$providers = $llm->get_providers();
	}

	if ( 'delete' === $action && ! empty( $_POST['provider_id'] ) ) {
		$llm->delete_provider( absint( $_POST['provider_id'] ) );
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Provedor removido.', 'jep-automacao' ) . '</p></div>';
		$providers = $llm->get_providers();
	}
}
?>
<div class="wrap jep-wrap">
	<h1 class="jep-title">
		<span class="dashicons dashicons-admin-network"></span>
		<?php esc_html_e( 'Provedores LLM', 'jep-automacao' ); ?>
	</h1>

	<!-- Usage summary -->
	<?php if ( ! empty( $usage ) ) : ?>
	<div class="jep-section">
		<h2><?php esc_html_e( 'Uso nos ultimos 30 dias', 'jep-automacao' ); ?></h2>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Provedor', 'jep-automacao' ); ?></th>
					<th><?php esc_html_e( 'Tipo', 'jep-automacao' ); ?></th>
					<th><?php esc_html_e( 'Chamadas', 'jep-automacao' ); ?></th>
					<th><?php esc_html_e( 'Tokens entrada', 'jep-automacao' ); ?></th>
					<th><?php esc_html_e( 'Tokens saida', 'jep-automacao' ); ?></th>
					<th><?php esc_html_e( 'Custo estimado (USD)', 'jep-automacao' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $usage as $row ) : ?>
				<tr>
					<td><?php echo esc_html( $row['provider_name'] ); ?></td>
					<td><code><?php echo esc_html( $row['provider_type'] ); ?></code></td>
					<td><?php echo esc_html( number_format_i18n( (int) $row['total_calls'] ) ); ?></td>
					<td><?php echo esc_html( number_format_i18n( (int) $row['total_input_tokens'] ) ); ?></td>
					<td><?php echo esc_html( number_format_i18n( (int) $row['total_output_tokens'] ) ); ?></td>
					<td>$<?php echo esc_html( number_format( (float) $row['total_cost_usd'], 4 ) ); ?></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php endif; ?>

	<!-- Provider list -->
	<div class="jep-section">
		<h2><?php esc_html_e( 'Provedores configurados', 'jep-automacao' ); ?></h2>
		<?php if ( empty( $providers ) ) : ?>
			<p class="description"><?php esc_html_e( 'Nenhum provedor cadastrado ainda.', 'jep-automacao' ); ?></p>
		<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Nome', 'jep-automacao' ); ?></th>
					<th><?php esc_html_e( 'Tipo', 'jep-automacao' ); ?></th>
					<th><?php esc_html_e( 'Modelo', 'jep-automacao' ); ?></th>
					<th><?php esc_html_e( 'Prioridade', 'jep-automacao' ); ?></th>
					<th><?php esc_html_e( 'Uso/Limite mensal', 'jep-automacao' ); ?></th>
					<th><?php esc_html_e( 'Ativo', 'jep-automacao' ); ?></th>
					<th><?php esc_html_e( 'Acoes', 'jep-automacao' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $providers as $p ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $p['name'] ); ?></strong></td>
					<td><code><?php echo esc_html( $p['provider_type'] ); ?></code></td>
					<td><code><?php echo esc_html( $p['model'] ); ?></code></td>
					<td><?php echo esc_html( $p['priority'] ); ?></td>
					<td>
						<?php echo esc_html( $p['used_this_month'] ); ?>
						<?php if ( (int) $p['monthly_limit'] > 0 ) : ?>
							/ <?php echo esc_html( $p['monthly_limit'] ); ?>
						<?php else : ?>
							/ &infin;
						<?php endif; ?>
					</td>
					<td>
						<?php if ( $p['is_active'] ) : ?>
							<span class="jep-badge jep-badge--success"><?php esc_html_e( 'Sim', 'jep-automacao' ); ?></span>
						<?php else : ?>
							<span class="jep-badge jep-badge--error"><?php esc_html_e( 'Nao', 'jep-automacao' ); ?></span>
						<?php endif; ?>
					</td>
					<td>
						<button class="button button-small jep-test-llm" data-id="<?php echo esc_attr( $p['id'] ); ?>">
							<?php esc_html_e( 'Testar', 'jep-automacao' ); ?>
						</button>
						<form method="post" style="display:inline">
							<?php wp_nonce_field( 'jep_llm_nonce' ); ?>
							<input type="hidden" name="jep_llm_action" value="delete">
							<input type="hidden" name="provider_id" value="<?php echo esc_attr( $p['id'] ); ?>">
							<button type="submit" class="button button-small button-link-delete"
								onclick="return confirm('<?php esc_attr_e( 'Remover este provedor?', 'jep-automacao' ); ?>')">
								<?php esc_html_e( 'Remover', 'jep-automacao' ); ?>
							</button>
						</form>
					</td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>
	</div>

	<!-- Add provider form -->
	<div class="jep-section">
		<h2><?php esc_html_e( 'Adicionar provedor', 'jep-automacao' ); ?></h2>
		<form method="post">
			<?php wp_nonce_field( 'jep_llm_nonce' ); ?>
			<input type="hidden" name="jep_llm_action" value="add">
			<table class="form-table">
				<tr>
					<th><label for="llm_name"><?php esc_html_e( 'Nome', 'jep-automacao' ); ?></label></th>
					<td><input type="text" id="llm_name" name="name" class="regular-text" required></td>
				</tr>
				<tr>
					<th><label for="llm_type"><?php esc_html_e( 'Tipo', 'jep-automacao' ); ?></label></th>
					<td>
						<select id="llm_type" name="provider_type">
							<option value="openrouter">OpenRouter</option>
							<option value="openai">OpenAI / Compativel</option>
							<option value="ollama">Ollama (local)</option>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="llm_api_key"><?php esc_html_e( 'API Key', 'jep-automacao' ); ?></label></th>
					<td><input type="password" id="llm_api_key" name="api_key" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="llm_base_url"><?php esc_html_e( 'Base URL (opcional)', 'jep-automacao' ); ?></label></th>
					<td><input type="url" id="llm_base_url" name="base_url" class="regular-text" placeholder="http://localhost:11434"></td>
				</tr>
				<tr>
					<th><label for="llm_model"><?php esc_html_e( 'Modelo', 'jep-automacao' ); ?></label></th>
					<td><input type="text" id="llm_model" name="model" class="regular-text" placeholder="meta-llama/llama-3.1-8b-instruct:free" required></td>
				</tr>
				<tr>
					<th><label for="llm_priority"><?php esc_html_e( 'Prioridade', 'jep-automacao' ); ?></label></th>
					<td><input type="number" id="llm_priority" name="priority" value="10" min="1" max="99" class="small-text">
					<p class="description"><?php esc_html_e( 'Menor numero = maior prioridade.', 'jep-automacao' ); ?></p></td>
				</tr>
				<tr>
					<th><label for="llm_limit"><?php esc_html_e( 'Limite mensal de chamadas', 'jep-automacao' ); ?></label></th>
					<td><input type="number" id="llm_limit" name="monthly_limit" value="0" min="0" class="small-text">
					<p class="description"><?php esc_html_e( '0 = sem limite.', 'jep-automacao' ); ?></p></td>
				</tr>
			</table>
			<?php submit_button( __( 'Adicionar provedor', 'jep-automacao' ) ); ?>
		</form>
	</div>
</div>
