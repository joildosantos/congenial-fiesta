<?php
/**
 * View: Feeds RSS
 *
 * @package JEP_Automacao_Editorial
 */

defined( 'ABSPATH' ) || exit;

$rss = new JEP_RSS_Manager();

// Handle actions.
if ( isset( $_POST['jep_rss_action'] ) && check_admin_referer( 'jep_rss_nonce' ) && current_user_can( 'manage_options' ) ) {
	$action = sanitize_key( $_POST['jep_rss_action'] );

	if ( 'add' === $action ) {
		$id = $rss->add_feed( array(
			'url'       => esc_url_raw( $_POST['url'] ?? '' ),
			'name'      => sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ),
			'territory' => sanitize_text_field( wp_unslash( $_POST['territory'] ?? '' ) ),
			'category'  => sanitize_text_field( wp_unslash( $_POST['category'] ?? '' ) ),
			'priority'  => absint( $_POST['priority'] ?? 10 ),
		) );
		if ( $id ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Feed adicionado.', 'jep-automacao' ) . '</p></div>';
		}
	}

	if ( 'delete' === $action && ! empty( $_POST['feed_id'] ) ) {
		$rss->delete_feed( absint( $_POST['feed_id'] ) );
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Feed removido.', 'jep-automacao' ) . '</p></div>';
	}

	if ( 'toggle' === $action && ! empty( $_POST['feed_id'] ) ) {
		$active = absint( $_POST['active'] ?? 0 );
		$rss->toggle_feed( absint( $_POST['feed_id'] ), (bool) $active );
	}
}

$feeds       = $rss->get_feeds();
$recent_items = $rss->get_recent_items( 24, '', 10 );
?>
<div class="wrap jep-wrap">
	<h1 class="jep-title">
		<span class="dashicons dashicons-rss"></span>
		<?php esc_html_e( 'Feeds RSS', 'jep-automacao' ); ?>
	</h1>

	<!-- Actions -->
	<div style="margin-bottom:15px">
		<button class="button button-primary" id="jep-rss-fetch-now">
			<span class="dashicons dashicons-update"></span>
			<?php esc_html_e( 'Captar feeds agora', 'jep-automacao' ); ?>
		</button>
		<span class="jep-pipeline-result" style="margin-left:10px"></span>
	</div>

	<!-- Feeds list -->
	<div class="jep-section">
		<h2><?php esc_html_e( 'Feeds cadastrados', 'jep-automacao' ); ?></h2>
		<?php if ( empty( $feeds ) ) : ?>
			<p class="description"><?php esc_html_e( 'Nenhum feed cadastrado ainda.', 'jep-automacao' ); ?></p>
		<?php else : ?>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Nome', 'jep-automacao' ); ?></th>
					<th><?php esc_html_e( 'URL', 'jep-automacao' ); ?></th>
					<th width="12%"><?php esc_html_e( 'Territorio', 'jep-automacao' ); ?></th>
					<th width="10%"><?php esc_html_e( 'Prioridade', 'jep-automacao' ); ?></th>
					<th width="10%"><?php esc_html_e( 'Ativo', 'jep-automacao' ); ?></th>
					<th width="15%"><?php esc_html_e( 'Ultima captacao', 'jep-automacao' ); ?></th>
					<th width="10%"><?php esc_html_e( 'Acoes', 'jep-automacao' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $feeds as $feed ) : ?>
				<tr>
					<td><strong><?php echo esc_html( $feed['name'] ?: $feed['url'] ); ?></strong></td>
					<td><a href="<?php echo esc_url( $feed['url'] ); ?>" target="_blank" class="description"><?php echo esc_html( $feed['url'] ); ?></a></td>
					<td><?php echo esc_html( $feed['territory'] ?: '—' ); ?></td>
					<td><?php echo esc_html( $feed['priority'] ); ?></td>
					<td>
						<?php if ( $feed['is_active'] ) : ?>
							<span class="jep-badge jep-badge--success"><?php esc_html_e( 'Sim', 'jep-automacao' ); ?></span>
						<?php else : ?>
							<span class="jep-badge jep-badge--error"><?php esc_html_e( 'Nao', 'jep-automacao' ); ?></span>
						<?php endif; ?>
					</td>
					<td>
						<?php echo $feed['last_fetch_at'] ? esc_html( wp_date( 'd/m/Y H:i', strtotime( $feed['last_fetch_at'] ) ) ) : '—'; ?>
						<?php if ( ! empty( $feed['last_error'] ) ) : ?>
							<br><span class="description" style="color:#e74c3c"><?php echo esc_html( wp_trim_words( $feed['last_error'], 8 ) ); ?></span>
						<?php endif; ?>
					</td>
					<td>
						<form method="post" style="display:inline">
							<?php wp_nonce_field( 'jep_rss_nonce' ); ?>
							<input type="hidden" name="jep_rss_action" value="toggle">
							<input type="hidden" name="feed_id" value="<?php echo esc_attr( $feed['id'] ); ?>">
							<input type="hidden" name="active" value="<?php echo $feed['is_active'] ? '0' : '1'; ?>">
							<button type="submit" class="button button-small">
								<?php echo $feed['is_active'] ? esc_html__( 'Desativar', 'jep-automacao' ) : esc_html__( 'Ativar', 'jep-automacao' ); ?>
							</button>
						</form>
						<form method="post" style="display:inline">
							<?php wp_nonce_field( 'jep_rss_nonce' ); ?>
							<input type="hidden" name="jep_rss_action" value="delete">
							<input type="hidden" name="feed_id" value="<?php echo esc_attr( $feed['id'] ); ?>">
							<button type="submit" class="button button-small button-link-delete"
								onclick="return confirm('<?php esc_attr_e( 'Remover este feed?', 'jep-automacao' ); ?>')">
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

	<!-- Add feed form -->
	<div class="jep-section">
		<h2><?php esc_html_e( 'Adicionar feed', 'jep-automacao' ); ?></h2>
		<form method="post">
			<?php wp_nonce_field( 'jep_rss_nonce' ); ?>
			<input type="hidden" name="jep_rss_action" value="add">
			<table class="form-table">
				<tr>
					<th><label for="rss_url"><?php esc_html_e( 'URL do feed', 'jep-automacao' ); ?></label></th>
					<td><input type="url" id="rss_url" name="url" class="large-text" required placeholder="https://example.com/feed/"></td>
				</tr>
				<tr>
					<th><label for="rss_name"><?php esc_html_e( 'Nome', 'jep-automacao' ); ?></label></th>
					<td><input type="text" id="rss_name" name="name" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="rss_territory"><?php esc_html_e( 'Territorio', 'jep-automacao' ); ?></label></th>
					<td><input type="text" id="rss_territory" name="territory" class="regular-text" placeholder="SP-Capital"></td>
				</tr>
				<tr>
					<th><label for="rss_category"><?php esc_html_e( 'Categoria', 'jep-automacao' ); ?></label></th>
					<td><input type="text" id="rss_category" name="category" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="rss_priority"><?php esc_html_e( 'Prioridade', 'jep-automacao' ); ?></label></th>
					<td><input type="number" id="rss_priority" name="priority" value="10" min="1" max="99" class="small-text"></td>
				</tr>
			</table>
			<?php submit_button( __( 'Adicionar feed', 'jep-automacao' ) ); ?>
		</form>
	</div>

	<!-- Recent items -->
	<?php if ( ! empty( $recent_items ) ) : ?>
	<div class="jep-section">
		<h2><?php esc_html_e( 'Itens captados nas ultimas 24h', 'jep-automacao' ); ?></h2>
		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Titulo', 'jep-automacao' ); ?></th>
					<th width="15%"><?php esc_html_e( 'Territorio', 'jep-automacao' ); ?></th>
					<th width="15%"><?php esc_html_e( 'Publicado em', 'jep-automacao' ); ?></th>
					<th width="10%"><?php esc_html_e( 'Status', 'jep-automacao' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $recent_items as $item ) : ?>
				<tr>
					<td>
						<?php if ( ! empty( $item['url'] ) ) : ?>
							<a href="<?php echo esc_url( $item['url'] ); ?>" target="_blank">
								<?php echo esc_html( $item['title'] ); ?>
							</a>
						<?php else : ?>
							<?php echo esc_html( $item['title'] ); ?>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( $item['territory'] ?: '—' ); ?></td>
					<td><?php echo esc_html( wp_date( 'd/m/Y H:i', strtotime( $item['pub_date'] ) ) ); ?></td>
					<td><span class="jep-badge jep-badge--<?php echo 'used' === $item['status'] ? 'success' : 'warning'; ?>"><?php echo esc_html( $item['status'] ); ?></span></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
	<?php endif; ?>
</div>
