<?php
/**
 * View: Conteudo Diario
 *
 * @package JEP_Automacao_Editorial
 */

defined( 'ABSPATH' ) || exit;

$daily = new JEP_Daily_Content();
$config = $daily->get_config();

// Handle config save.
if ( isset( $_POST['jep_daily_save'] ) && check_admin_referer( 'jep_daily_nonce' ) && current_user_can( 'manage_options' ) ) {
	$daily->save_config( array(
		'territory'           => sanitize_text_field( wp_unslash( $_POST['territory'] ?? '' ) ),
		'digest_category_id'  => absint( $_POST['digest_category_id'] ?? 0 ),
		'digest_post_status'  => sanitize_key( $_POST['digest_post_status'] ?? 'draft' ),
		'digest_author_id'    => absint( $_POST['digest_author_id'] ?? get_current_user_id() ),
		'digest_title_format' => sanitize_text_field( wp_unslash( $_POST['digest_title_format'] ?? 'Resumo do dia: {date}' ) ),
	) );
	$config = $daily->get_config();
	echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Configuracoes salvas.', 'jep-automacao' ) . '</p></div>';
}

$categories = get_categories( array( 'hide_empty' => false ) );
$authors    = get_users( array( 'capability' => 'publish_posts' ) );
?>
<div class="wrap jep-wrap">
	<h1 class="jep-title">
		<span class="dashicons dashicons-calendar-alt"></span>
		<?php esc_html_e( 'Conteudo Diario', 'jep-automacao' ); ?>
	</h1>

	<!-- Manual trigger -->
	<div class="jep-section">
		<h2><?php esc_html_e( 'Executar pipeline agora', 'jep-automacao' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Busca RSS das ultimas 24h, reescreve os melhores itens com LLM, envia ao Telegram para aprovacao e cria post digest.', 'jep-automacao' ); ?></p>
		<button class="button button-primary jep-run-pipeline" data-pipeline="daily">
			<span class="dashicons dashicons-controls-play"></span>
			<?php esc_html_e( 'Executar pipeline diario agora', 'jep-automacao' ); ?>
		</button>
		<span class="jep-pipeline-result" style="margin-left:10px"></span>
	</div>

	<!-- Config form -->
	<div class="jep-section">
		<h2><?php esc_html_e( 'Configuracoes do pipeline', 'jep-automacao' ); ?></h2>
		<form method="post">
			<?php wp_nonce_field( 'jep_daily_nonce' ); ?>
			<input type="hidden" name="jep_daily_save" value="1">
			<table class="form-table">
				<tr>
					<th><label for="daily_territory"><?php esc_html_e( 'Territorio', 'jep-automacao' ); ?></label></th>
					<td>
						<input type="text" id="daily_territory" name="territory" class="regular-text"
							value="<?php echo esc_attr( $config['territory'] ); ?>"
							placeholder="BR">
						<p class="description"><?php esc_html_e( 'Filtra itens RSS por territorio. Deixe vazio para todos.', 'jep-automacao' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="daily_title_format"><?php esc_html_e( 'Formato do titulo do digest', 'jep-automacao' ); ?></label></th>
					<td>
						<input type="text" id="daily_title_format" name="digest_title_format" class="regular-text"
							value="<?php echo esc_attr( $config['digest_title_format'] ); ?>">
						<p class="description"><?php esc_html_e( 'Use {date} para inserir a data. Ex: Resumo do dia: {date}', 'jep-automacao' ); ?></p>
					</td>
				</tr>
				<tr>
					<th><label for="daily_cat"><?php esc_html_e( 'Categoria do post digest', 'jep-automacao' ); ?></label></th>
					<td>
						<select id="daily_cat" name="digest_category_id">
							<option value="0"><?php esc_html_e( '— Sem categoria —', 'jep-automacao' ); ?></option>
							<?php foreach ( $categories as $cat ) : ?>
								<option value="<?php echo esc_attr( $cat->term_id ); ?>" <?php selected( $config['digest_category_id'], $cat->term_id ); ?>>
									<?php echo esc_html( $cat->name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="daily_status"><?php esc_html_e( 'Status do post digest', 'jep-automacao' ); ?></label></th>
					<td>
						<select id="daily_status" name="digest_post_status">
							<option value="draft" <?php selected( $config['digest_post_status'], 'draft' ); ?>><?php esc_html_e( 'Rascunho', 'jep-automacao' ); ?></option>
							<option value="pending" <?php selected( $config['digest_post_status'], 'pending' ); ?>><?php esc_html_e( 'Pendente revisao', 'jep-automacao' ); ?></option>
							<option value="publish" <?php selected( $config['digest_post_status'], 'publish' ); ?>><?php esc_html_e( 'Publicado', 'jep-automacao' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th><label for="daily_author"><?php esc_html_e( 'Autor do post digest', 'jep-automacao' ); ?></label></th>
					<td>
						<select id="daily_author" name="digest_author_id">
							<?php foreach ( $authors as $user ) : ?>
								<option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( $config['digest_author_id'], $user->ID ); ?>>
									<?php echo esc_html( $user->display_name ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Salvar configuracoes', 'jep-automacao' ) ); ?>
		</form>
	</div>

	<!-- Agendamento info -->
	<div class="jep-section jep-section--info">
		<h2><?php esc_html_e( 'Agendamento automatico', 'jep-automacao' ); ?></h2>
		<p><?php esc_html_e( 'O pipeline diario e executado automaticamente todos os dias as 06h via WP-Cron (evento: jep_run_daily_content).', 'jep-automacao' ); ?></p>
		<?php
		$next_run = wp_next_scheduled( 'jep_run_daily_content' );
		if ( $next_run ) {
			echo '<p>' . sprintf(
				/* translators: %s: next scheduled time */
				esc_html__( 'Proxima execucao agendada: %s', 'jep-automacao' ),
				'<strong>' . esc_html( wp_date( 'd/m/Y H:i:s', $next_run ) ) . '</strong>'
			) . '</p>';
		} else {
			echo '<p class="description">' . esc_html__( 'Nenhum agendamento encontrado. O evento sera criado na proxima ativacao do plugin.', 'jep-automacao' ) . '</p>';
		}
		?>
	</div>
</div>
