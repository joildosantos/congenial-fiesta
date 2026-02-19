<?php
/**
 * View: Distribuicao (Instagram / Facebook)
 *
 * @package JEP_Automacao_Editorial
 */

defined( 'ABSPATH' ) || exit;

// Handle settings save.
if ( isset( $_POST['jep_dist_save'] ) && check_admin_referer( 'jep_dist_nonce' ) && current_user_can( 'manage_options' ) ) {
	update_option( 'jep_instagram_account_id',       sanitize_text_field( wp_unslash( $_POST['instagram_account_id'] ?? '' ) ) );
	update_option( 'jep_facebook_page_id',           sanitize_text_field( wp_unslash( $_POST['facebook_page_id'] ?? '' ) ) );
	update_option( 'jep_facebook_page_access_token', sanitize_text_field( wp_unslash( $_POST['facebook_page_access_token'] ?? '' ) ) );
	update_option( 'jep_instagram_auto_publish',     absint( $_POST['auto_publish'] ?? 0 ) );
	update_option( 'jep_instagram_hashtags_default', sanitize_textarea_field( wp_unslash( $_POST['default_hashtags'] ?? '' ) ) );
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Configuracoes de distribuicao salvas.', 'jep-automacao' ) . '</p></div>';
}

$instagram_id    = get_option( 'jep_instagram_account_id', '' );
$fb_page_id      = get_option( 'jep_facebook_page_id', '' );
$fb_token        = get_option( 'jep_facebook_page_access_token', '' );
$auto_publish    = get_option( 'jep_instagram_auto_publish', 0 );
$default_hashtags = get_option( 'jep_instagram_hashtags_default', '' );

// Recent distribution logs.
global $wpdb;
$logs_table = $wpdb->prefix . 'jep_logs';
// phpcs:ignore WordPress.DB.DirectDatabaseQuery
$recent_logs = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT * FROM {$logs_table} WHERE event = %s ORDER BY created_at DESC LIMIT 20",
		'instagram'
	),
	ARRAY_A
) ?: array();
?>
<div class="wrap jep-wrap">
	<h1 class="jep-title">
		<span class="dashicons dashicons-share"></span>
		<?php esc_html_e( 'Distribuicao', 'jep-automacao' ); ?>
	</h1>

	<!-- Settings -->
	<div class="jep-section">
		<h2><?php esc_html_e( 'Instagram / Facebook', 'jep-automacao' ); ?></h2>
		<form method="post">
			<?php wp_nonce_field( 'jep_dist_nonce' ); ?>
			<input type="hidden" name="jep_dist_save" value="1">
			<table class="form-table">
				<tr>
					<th><label for="dist_ig_id"><?php esc_html_e( 'Instagram Business Account ID', 'jep-automacao' ); ?></label></th>
					<td>
						<input type="text" id="dist_ig_id" name="instagram_account_id" class="regular-text"
							value="<?php echo esc_attr( $instagram_id ); ?>" placeholder="123456789">
					</td>
				</tr>
				<tr>
					<th><label for="dist_fb_page"><?php esc_html_e( 'Facebook Page ID', 'jep-automacao' ); ?></label></th>
					<td>
						<input type="text" id="dist_fb_page" name="facebook_page_id" class="regular-text"
							value="<?php echo esc_attr( $fb_page_id ); ?>" placeholder="123456789">
					</td>
				</tr>
				<tr>
					<th><label for="dist_fb_token"><?php esc_html_e( 'Page Access Token', 'jep-automacao' ); ?></label></th>
					<td>
						<input type="password" id="dist_fb_token" name="facebook_page_access_token" class="regular-text"
							value="<?php echo esc_attr( $fb_token ); ?>">
						<p class="description"><?php esc_html_e( 'Token de longa duracao da pagina Facebook (Graph API).', 'jep-automacao' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Auto-publicar no Instagram', 'jep-automacao' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="auto_publish" value="1" <?php checked( 1, (int) $auto_publish ); ?>>
							<?php esc_html_e( 'Publicar automaticamente apos aprovacao no Telegram', 'jep-automacao' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th><label for="dist_hashtags"><?php esc_html_e( 'Hashtags padrao', 'jep-automacao' ); ?></label></th>
					<td>
						<textarea id="dist_hashtags" name="default_hashtags" class="large-text" rows="3"
							placeholder="#JornalEspacoDoPovo #Noticias"><?php echo esc_textarea( $default_hashtags ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Adicionadas a todos os posts Instagram. Separadas por espaco.', 'jep-automacao' ); ?></p>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Salvar', 'jep-automacao' ) ); ?>
		</form>
	</div>

	<!-- Graph API info -->
	<div class="jep-section jep-section--info">
		<h2><?php esc_html_e( 'Como obter o Page Access Token', 'jep-automacao' ); ?></h2>
		<ol>
			<li><?php esc_html_e( 'Acesse developers.facebook.com e crie um App do tipo "Business".', 'jep-automacao' ); ?></li>
			<li><?php esc_html_e( 'Va em Graph API Explorer, selecione seu App e gere um User Token com permissoes: instagram_basic, instagram_content_publish, pages_manage_posts.', 'jep-automacao' ); ?></li>
			<li><?php esc_html_e( 'Troque o User Token de curta duracao por um Page Access Token de longa duracao usando o endpoint /oauth/access_token.', 'jep-automacao' ); ?></li>
		</ol>
	</div>

	<!-- Recent logs -->
	<?php if ( ! empty( $recent_logs ) ) : ?>
	<div class="jep-section">
		<h2><?php esc_html_e( 'Logs recentes de distribuicao', 'jep-automacao' ); ?></h2>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th width="150"><?php esc_html_e( 'Data', 'jep-automacao' ); ?></th>
					<th width="80"><?php esc_html_e( 'Nivel', 'jep-automacao' ); ?></th>
					<th><?php esc_html_e( 'Mensagem', 'jep-automacao' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $recent_logs as $log ) : ?>
				<tr>
					<td><?php echo esc_html( wp_date( 'd/m/Y H:i', strtotime( $log['created_at'] ) ) ); ?></td>
					<td><span class="jep-badge jep-badge--<?php echo esc_attr( $log['level'] ); ?>"><?php echo esc_html( $log['level'] ); ?></span></td>
					<td><?php echo esc_html( $log['message'] ); ?></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php endif; ?>
</div>
