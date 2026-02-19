<?php
/**
 * View: Telegram
 *
 * @package JEP_Automacao_Editorial
 */

defined( 'ABSPATH' ) || exit;

// Handle settings save.
if ( isset( $_POST['jep_telegram_save'] ) && check_admin_referer( 'jep_telegram_nonce' ) && current_user_can( 'manage_options' ) ) {
	update_option( 'jep_telegram_bot_token',      sanitize_text_field( wp_unslash( $_POST['bot_token'] ?? '' ) ) );
	update_option( 'jep_telegram_editor_chat_id', sanitize_text_field( wp_unslash( $_POST['editor_chat_id'] ?? '' ) ) );
	update_option( 'jep_telegram_channel_id',     sanitize_text_field( wp_unslash( $_POST['channel_id'] ?? '' ) ) );
	update_option( 'jep_telegram_webhook_secret', sanitize_text_field( wp_unslash( $_POST['webhook_secret'] ?? '' ) ) );
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Configuracoes Telegram salvas.', 'jep-automacao' ) . '</p></div>';
}

$bot_token      = get_option( 'jep_telegram_bot_token', '' );
$editor_chat_id = get_option( 'jep_telegram_editor_chat_id', '' );
$channel_id     = get_option( 'jep_telegram_channel_id', '' );
$webhook_secret = get_option( 'jep_telegram_webhook_secret', '' );

// Pending approvals.
global $wpdb;
$approvals_table = $wpdb->prefix . 'jep_approvals';
$pending = array();
if ( $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $approvals_table ) ) === $approvals_table ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$pending = $wpdb->get_results(
		"SELECT * FROM {$approvals_table} WHERE status = 'pending' ORDER BY created_at DESC LIMIT 20",
		ARRAY_A
	) ?: array();
}
?>
<div class="wrap jep-wrap">
	<h1 class="jep-title">
		<span class="dashicons dashicons-format-chat"></span>
		<?php esc_html_e( 'Telegram', 'jep-automacao' ); ?>
	</h1>

	<!-- Bot info -->
	<div class="jep-section">
		<h2><?php esc_html_e( 'Status do Bot', 'jep-automacao' ); ?></h2>
		<button class="button button-secondary" id="jep-telegram-get-me">
			<?php esc_html_e( 'Verificar conexao com o bot', 'jep-automacao' ); ?>
		</button>
		<span id="jep-telegram-me-result" style="margin-left:10px;"></span>
	</div>

	<!-- Settings form -->
	<div class="jep-section">
		<h2><?php esc_html_e( 'Configuracoes', 'jep-automacao' ); ?></h2>
		<form method="post">
			<?php wp_nonce_field( 'jep_telegram_nonce' ); ?>
			<input type="hidden" name="jep_telegram_save" value="1">
			<table class="form-table">
				<tr>
					<th><label for="tg_bot_token"><?php esc_html_e( 'Bot Token', 'jep-automacao' ); ?></label></th>
					<td>
						<input type="password" id="tg_bot_token" name="bot_token" class="regular-text"
							value="<?php echo esc_attr( $bot_token ); ?>"
							placeholder="123456789:ABCdef...">
						<p class="description"><?php esc_html_e( 'Obtido via @BotFather no Telegram.', 'jep-automacao' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="tg_editor_chat"><?php esc_html_e( 'Chat ID do Editor', 'jep-automacao' ); ?></label></th>
					<td>
						<input type="text" id="tg_editor_chat" name="editor_chat_id" class="regular-text"
							value="<?php echo esc_attr( $editor_chat_id ); ?>"
							placeholder="123456789">
						<p class="description"><?php esc_html_e( 'ID do chat ou grupo que recebe os cards de aprovacao A/B.', 'jep-automacao' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="tg_channel_id"><?php esc_html_e( 'ID do Canal', 'jep-automacao' ); ?></label></th>
					<td>
						<input type="text" id="tg_channel_id" name="channel_id" class="regular-text"
							value="<?php echo esc_attr( $channel_id ); ?>"
							placeholder="@seucanal ou -100123456789">
						<p class="description"><?php esc_html_e( 'Canal onde o conteudo aprovado sera publicado.', 'jep-automacao' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="tg_secret"><?php esc_html_e( 'Webhook Secret', 'jep-automacao' ); ?></label></th>
					<td>
						<input type="text" id="tg_secret" name="webhook_secret" class="regular-text"
							value="<?php echo esc_attr( $webhook_secret ); ?>">
						<p class="description"><?php esc_html_e( 'Token secreto para validar callbacks do Telegram (opcional).', 'jep-automacao' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Salvar configuracoes', 'jep-automacao' ) ); ?>
		</form>
	</div>

	<!-- Pending approvals -->
	<div class="jep-section">
		<h2><?php esc_html_e( 'Aprovacoes pendentes', 'jep-automacao' ); ?></h2>
		<?php if ( empty( $pending ) ) : ?>
			<p class="description"><?php esc_html_e( 'Nenhuma aprovacao pendente no momento.', 'jep-automacao' ); ?></p>
		<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th width="5%">#</th>
					<th width="20%"><?php esc_html_e( 'Tipo', 'jep-automacao' ); ?></th>
					<th><?php esc_html_e( 'Titulo A', 'jep-automacao' ); ?></th>
					<th><?php esc_html_e( 'Titulo B', 'jep-automacao' ); ?></th>
					<th width="15%"><?php esc_html_e( 'Criado em', 'jep-automacao' ); ?></th>
					<th width="10%"><?php esc_html_e( 'Status', 'jep-automacao' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $pending as $row ) : ?>
				<tr>
					<td><?php echo esc_html( $row['id'] ); ?></td>
					<td><code><?php echo esc_html( $row['source_type'] ); ?></code></td>
					<td><?php echo esc_html( $row['title_a'] ); ?></td>
					<td><?php echo esc_html( $row['title_b'] ); ?></td>
					<td><?php echo esc_html( wp_date( 'd/m/Y H:i', strtotime( $row['created_at'] ) ) ); ?></td>
					<td><span class="jep-badge jep-badge--warning"><?php echo esc_html( $row['status'] ); ?></span></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php endif; ?>
	</div>

	<!-- Webhook URL info -->
	<div class="jep-section jep-section--info">
		<h2><?php esc_html_e( 'URL do Webhook Telegram', 'jep-automacao' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Configure esta URL no Telegram via setWebhook para receber callbacks de aprovacao:', 'jep-automacao' ); ?></p>
		<code><?php echo esc_html( rest_url( 'jep/v1/telegram/webhook' ) ); ?></code>
	</div>
</div>
