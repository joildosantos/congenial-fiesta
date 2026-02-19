<?php
/**
 * View: Configuracoes do plugin JEP Automacao v2.
 *
 * @package JEP_Automacao
 */

defined( 'ABSPATH' ) || exit;

$settings = jep_automacao()->settings();
$saved    = false;

// Save settings on POST.
if ( 'POST' === $_SERVER['REQUEST_METHOD']
	&& isset( $_POST['jep_settings_nonce'] )
	&& wp_verify_nonce( sanitize_key( $_POST['jep_settings_nonce'] ), 'jep_save_settings' )
	&& current_user_can( 'manage_options' )
) {
	$text_fields = array(
		'telegram_bot_token',
		'telegram_editor_chat_id',
		'telegram_channel_id',
		'instagram_account_id',
		'facebook_page_access_token',
		'image_badge_text',
		'ai_image_provider',
		'openai_api_key',
		'cron_daily_time',
		'cron_cold_time',
		'cron_research_time',
		'cron_discovery_time',
		'cron_summary_time',
	);
	foreach ( $text_fields as $key ) {
		if ( isset( $_POST[ 'jep_' . $key ] ) ) {
			$settings->set( $key, sanitize_text_field( wp_unslash( $_POST[ 'jep_' . $key ] ) ) );
		}
	}

	if ( isset( $_POST['jep_instagram_caption_template'] ) ) {
		$settings->set( 'instagram_caption_template', sanitize_textarea_field( wp_unslash( $_POST['jep_instagram_caption_template'] ) ) );
	}
	if ( isset( $_POST['jep_image_logo_url'] ) ) {
		$settings->set( 'image_logo_url', esc_url_raw( wp_unslash( $_POST['jep_image_logo_url'] ) ) );
	}
	if ( isset( $_POST['jep_image_accent_color'] ) ) {
		$settings->set( 'image_accent_color', sanitize_hex_color( wp_unslash( $_POST['jep_image_accent_color'] ) ) );
	}

	$toggles = array(
		'instagram_enabled',
		'instagram_caption_approval',
		'ai_images_enabled',
		'enable_cron_daily',
		'enable_cron_cold',
		'enable_cron_research',
		'enable_cron_discovery',
		'enable_cron_summary',
	);
	foreach ( $toggles as $key ) {
		$settings->set( $key, isset( $_POST[ 'jep_' . $key ] ) ? '1' : '0' );
	}

	$saved = true;
}
?>
<div class="wrap jep-wrap">
	<h1 class="jep-title">
		<span class="dashicons dashicons-admin-settings"></span>
		<?php esc_html_e( 'Configuracoes — JEP Automacao v2', 'jep-automacao' ); ?>
	</h1>

	<?php if ( $saved ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Configuracoes salvas.', 'jep-automacao' ); ?></p>
		</div>
	<?php endif; ?>

	<form method="post">
		<?php wp_nonce_field( 'jep_save_settings', 'jep_settings_nonce' ); ?>

		<!-- ===== TELEGRAM ===== -->
		<h2><?php esc_html_e( 'Telegram', 'jep-automacao' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="jep_telegram_bot_token"><?php esc_html_e( 'Bot Token', 'jep-automacao' ); ?></label></th>
				<td>
					<input type="text" id="jep_telegram_bot_token" name="jep_telegram_bot_token"
						value="<?php echo esc_attr( $settings->get_telegram_bot_token() ); ?>"
						class="regular-text" placeholder="123456:ABCdef...">
					<p class="description"><?php esc_html_e( 'Token obtido via @BotFather.', 'jep-automacao' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="jep_telegram_editor_chat_id"><?php esc_html_e( 'Chat ID do Editor', 'jep-automacao' ); ?></label></th>
				<td>
					<input type="text" id="jep_telegram_editor_chat_id" name="jep_telegram_editor_chat_id"
						value="<?php echo esc_attr( $settings->get_telegram_editor_chat_id() ); ?>"
						class="regular-text" placeholder="-100123456789">
					<p class="description"><?php esc_html_e( 'Chat/grupo do editor para receber aprovacoes.', 'jep-automacao' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><label for="jep_telegram_channel_id"><?php esc_html_e( 'Canal de Publicacao', 'jep-automacao' ); ?></label></th>
				<td>
					<input type="text" id="jep_telegram_channel_id" name="jep_telegram_channel_id"
						value="<?php echo esc_attr( $settings->get_telegram_channel_id() ); ?>"
						class="regular-text" placeholder="@seucanalaqui">
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'URL do Webhook', 'jep-automacao' ); ?></th>
				<td>
					<code><?php echo esc_html( rest_url( 'jep/v1/telegram-webhook' ) ); ?></code>
					<p class="description"><?php esc_html_e( 'Registre este URL como webhook do bot via API do Telegram.', 'jep-automacao' ); ?></p>
				</td>
			</tr>
		</table>

		<!-- ===== INSTAGRAM ===== -->
		<h2><?php esc_html_e( 'Instagram', 'jep-automacao' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( 'Habilitar Instagram', 'jep-automacao' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="jep_instagram_enabled" value="1"
							<?php checked( $settings->is_instagram_enabled() ); ?>>
						<?php esc_html_e( 'Publicar automaticamente no Instagram apos aprovacao', 'jep-automacao' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th><label for="jep_instagram_account_id"><?php esc_html_e( 'Instagram Account ID', 'jep-automacao' ); ?></label></th>
				<td>
					<input type="text" id="jep_instagram_account_id" name="jep_instagram_account_id"
						value="<?php echo esc_attr( $settings->get_instagram_account_id() ); ?>"
						class="regular-text">
				</td>
			</tr>
			<tr>
				<th><label for="jep_facebook_page_access_token"><?php esc_html_e( 'Facebook Page Access Token', 'jep-automacao' ); ?></label></th>
				<td>
					<input type="text" id="jep_facebook_page_access_token" name="jep_facebook_page_access_token"
						value="<?php echo esc_attr( $settings->get_facebook_page_access_token() ); ?>"
						class="large-text">
					<p class="description"><?php esc_html_e( 'Long-lived token com permissoes instagram_basic + instagram_content_publish.', 'jep-automacao' ); ?></p>
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Aprovar legenda', 'jep-automacao' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="jep_instagram_caption_approval" value="1"
							<?php checked( $settings->is_instagram_caption_approval_required() ); ?>>
						<?php esc_html_e( 'Exigir aprovacao da legenda via Telegram antes de publicar', 'jep-automacao' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th><label for="jep_instagram_caption_template"><?php esc_html_e( 'Template da Legenda', 'jep-automacao' ); ?></label></th>
				<td>
					<textarea id="jep_instagram_caption_template" name="jep_instagram_caption_template"
						rows="4" class="large-text"><?php echo esc_textarea( $settings->get_instagram_caption_template() ); ?></textarea>
					<p class="description"><?php esc_html_e( 'Variaveis: {titulo} {excerpt} {url} {categorias} {hashtags}', 'jep-automacao' ); ?></p>
				</td>
			</tr>
		</table>

		<!-- ===== IMAGENS ===== -->
		<h2><?php esc_html_e( 'Imagens', 'jep-automacao' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label for="jep_image_logo_url"><?php esc_html_e( 'URL do Logo', 'jep-automacao' ); ?></label></th>
				<td>
					<input type="url" id="jep_image_logo_url" name="jep_image_logo_url"
						value="<?php echo esc_attr( $settings->get_image_logo_url() ); ?>"
						class="large-text">
				</td>
			</tr>
			<tr>
				<th><label for="jep_image_accent_color"><?php esc_html_e( 'Cor de Destaque', 'jep-automacao' ); ?></label></th>
				<td>
					<input type="color" id="jep_image_accent_color" name="jep_image_accent_color"
						value="<?php echo esc_attr( $settings->get_image_accent_color() ); ?>">
				</td>
			</tr>
			<tr>
				<th><label for="jep_image_badge_text"><?php esc_html_e( 'Texto do Badge', 'jep-automacao' ); ?></label></th>
				<td>
					<input type="text" id="jep_image_badge_text" name="jep_image_badge_text"
						value="<?php echo esc_attr( $settings->get_image_badge_text() ); ?>"
						class="regular-text" maxlength="30">
				</td>
			</tr>
			<tr>
				<th><?php esc_html_e( 'Imagens com IA', 'jep-automacao' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="jep_ai_images_enabled" value="1"
							<?php checked( $settings->is_ai_images_enabled() ); ?>>
						<?php esc_html_e( 'Gerar imagens com IA quando nao houver foto disponivel', 'jep-automacao' ); ?>
					</label>
				</td>
			</tr>
			<tr>
				<th><label for="jep_ai_image_provider"><?php esc_html_e( 'Provider de Imagem IA', 'jep-automacao' ); ?></label></th>
				<td>
					<select id="jep_ai_image_provider" name="jep_ai_image_provider">
						<option value="pollinations" <?php selected( $settings->get_ai_image_provider(), 'pollinations' ); ?>>Pollinations.ai (gratuito)</option>
						<option value="openai" <?php selected( $settings->get_ai_image_provider(), 'openai' ); ?>>OpenAI DALL-E (pago)</option>
					</select>
				</td>
			</tr>
			<tr>
				<th><label for="jep_openai_api_key"><?php esc_html_e( 'OpenAI API Key', 'jep-automacao' ); ?></label></th>
				<td>
					<input type="text" id="jep_openai_api_key" name="jep_openai_api_key"
						value="<?php echo esc_attr( $settings->get_openai_api_key() ); ?>"
						class="large-text" placeholder="sk-...">
					<p class="description"><?php esc_html_e( 'Necessario apenas se o provider for OpenAI.', 'jep-automacao' ); ?></p>
				</td>
			</tr>
		</table>

		<!-- ===== AGENDAMENTO ===== -->
		<h2><?php esc_html_e( 'Agendamento de Crons', 'jep-automacao' ); ?></h2>
		<table class="form-table" role="presentation">
			<?php
			$crons = array(
				'daily'     => array( 'label' => __( 'Conteudo Diario', 'jep-automacao' ),     'desc' => __( 'HH:MM — diario', 'jep-automacao' ) ),
				'cold'      => array( 'label' => __( 'Conteudo Frio', 'jep-automacao' ),       'desc' => __( 'HH:MM — seg/qua/sex', 'jep-automacao' ) ),
				'research'  => array( 'label' => __( 'Pesquisa de Pautas', 'jep-automacao' ),  'desc' => __( 'HH:MM — toda segunda', 'jep-automacao' ) ),
				'discovery' => array( 'label' => __( 'Descoberta de Fontes', 'jep-automacao' ),'desc' => __( 'HH:MM — diario', 'jep-automacao' ) ),
				'summary'   => array( 'label' => __( 'Resumo Semanal', 'jep-automacao' ),      'desc' => __( 'HH:MM — todo domingo', 'jep-automacao' ) ),
			);
			foreach ( $crons as $key => $cron ) :
			?>
			<tr>
				<th><?php echo esc_html( $cron['label'] ); ?></th>
				<td>
					<label style="margin-right:15px">
						<input type="checkbox" name="jep_enable_cron_<?php echo esc_attr( $key ); ?>" value="1"
							<?php checked( $settings->is_cron_enabled( $key ) ); ?>>
						<?php esc_html_e( 'Ativo', 'jep-automacao' ); ?>
					</label>
					<input type="time" name="jep_cron_<?php echo esc_attr( $key ); ?>_time"
						value="<?php echo esc_attr( $settings->get_cron_time( $key ) ); ?>">
					<span class="description" style="margin-left:8px"><?php echo esc_html( $cron['desc'] ); ?></span>
				</td>
			</tr>
			<?php endforeach; ?>
		</table>

		<!-- ===== REST API ===== -->
		<h2><?php esc_html_e( 'REST API', 'jep-automacao' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><?php esc_html_e( 'Token Secreto', 'jep-automacao' ); ?></th>
				<td>
					<code><?php echo esc_html( $settings->get_rest_api_secret() ); ?></code>
					<p class="description"><?php esc_html_e( 'Envie no header X-JEP-Token para autenticar chamadas REST externas.', 'jep-automacao' ); ?></p>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Salvar Configuracoes', 'jep-automacao' ) ); ?>
	</form>
</div>
