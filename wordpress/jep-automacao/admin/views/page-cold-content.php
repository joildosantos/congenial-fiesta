<?php
/**
 * View: Pautas Frias
 *
 * @package JEP_Automacao_Editorial
 */

defined( 'ABSPATH' ) || exit;

$cold = new JEP_Cold_Content();

// Handle actions.
if ( isset( $_POST['jep_cc_action'] ) && check_admin_referer( 'jep_cc_nonce' ) && current_user_can( 'manage_options' ) ) {
	$action = sanitize_key( $_POST['jep_cc_action'] );

	if ( 'add' === $action ) {
		$cold->add( array(
			'title'      => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
			'summary'    => sanitize_textarea_field( wp_unslash( $_POST['summary'] ?? '' ) ),
			'territory'  => sanitize_text_field( wp_unslash( $_POST['territory'] ?? '' ) ),
			'source_url' => esc_url_raw( $_POST['source_url'] ?? '' ),
			'priority'   => absint( $_POST['priority'] ?? 10 ),
		) );
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Pauta adicionada.', 'jep-automacao' ) . '</p></div>';
	}

	if ( 'delete' === $action && ! empty( $_POST['item_id'] ) ) {
		$cold->delete( absint( $_POST['item_id'] ) );
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Pauta descartada.', 'jep-automacao' ) . '</p></div>';
	}
}

$status_filter = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : 'pending';
$paged         = max( 1, absint( $_GET['paged'] ?? 1 ) );
$per_page      = 20;
$territories   = $cold->get_territories();

$items = $cold->get_all( array(
	'status'   => 'all' === $status_filter ? '' : $status_filter,
	'page'     => $paged,
	'per_page' => $per_page,
) );

$counts = array(
	'pending'    => $cold->count( array( 'status' => 'pending' ) ),
	'processing' => $cold->count( array( 'status' => 'processing' ) ),
	'done'       => $cold->count( array( 'status' => 'done' ) ),
	'discarded'  => $cold->count( array( 'status' => 'discarded' ) ),
);
$total = array_sum( $counts );
?>
<div class="wrap jep-wrap">
	<h1 class="jep-title">
		<span class="dashicons dashicons-edit-page"></span>
		<?php esc_html_e( 'Pautas Frias', 'jep-automacao' ); ?>
		<a href="#jep-add-pauta" class="page-title-action"><?php esc_html_e( '+ Adicionar pauta', 'jep-automacao' ); ?></a>
	</h1>

	<!-- Status tabs -->
	<ul class="subsubsub">
		<?php
		$tab_items = array(
			'pending'    => array( __( 'Pendentes', 'jep-automacao' ), $counts['pending'] ),
			'processing' => array( __( 'Em processo', 'jep-automacao' ), $counts['processing'] ),
			'done'       => array( __( 'Concluidas', 'jep-automacao' ), $counts['done'] ),
			'discarded'  => array( __( 'Descartadas', 'jep-automacao' ), $counts['discarded'] ),
			'all'        => array( __( 'Todas', 'jep-automacao' ), $total ),
		);

		$links = array();
		foreach ( $tab_items as $tab_status => $tab_data ) {
			$class = $status_filter === $tab_status ? ' class="current"' : '';
			$url   = add_query_arg( array( 'page' => 'jep-automacao-cold-content', 'status' => $tab_status ), admin_url( 'admin.php' ) );
			$links[] = sprintf(
				'<li><a href="%s"%s>%s <span class="count">(%d)</span></a>',
				esc_url( $url ),
				$class,
				esc_html( $tab_data[0] ),
				(int) $tab_data[1]
			);
		}
		echo implode( ' | </li>', $links ) . '</li>'; // phpcs:ignore WordPress.Security.EscapeOutput
		?>
	</ul>

	<!-- Pipeline trigger -->
	<div style="margin:10px 0">
		<button class="button button-primary jep-run-pipeline" data-pipeline="cold_content">
			<span class="dashicons dashicons-controls-play"></span>
			<?php esc_html_e( 'Processar proxima pauta agora', 'jep-automacao' ); ?>
		</button>
		<span class="jep-pipeline-result"></span>
	</div>

	<!-- Items table -->
	<table class="wp-list-table widefat fixed striped">
		<thead>
			<tr>
				<th width="30%"><?php esc_html_e( 'Titulo', 'jep-automacao' ); ?></th>
				<th width="15%"><?php esc_html_e( 'Territorio', 'jep-automacao' ); ?></th>
				<th width="8%"><?php esc_html_e( 'Prioridade', 'jep-automacao' ); ?></th>
				<th width="10%"><?php esc_html_e( 'Status', 'jep-automacao' ); ?></th>
				<th width="15%"><?php esc_html_e( 'Criado em', 'jep-automacao' ); ?></th>
				<th><?php esc_html_e( 'Acoes', 'jep-automacao' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $items ) ) : ?>
			<tr>
				<td colspan="6"><?php esc_html_e( 'Nenhuma pauta encontrada.', 'jep-automacao' ); ?></td>
			</tr>
			<?php else : ?>
			<?php foreach ( $items as $item ) : ?>
			<tr>
				<td>
					<strong><?php echo esc_html( $item['title'] ); ?></strong>
					<?php if ( ! empty( $item['summary'] ) ) : ?>
						<p class="description" style="margin:4px 0 0"><?php echo esc_html( wp_trim_words( $item['summary'], 15 ) ); ?></p>
					<?php endif; ?>
					<?php if ( ! empty( $item['source_url'] ) ) : ?>
						<a href="<?php echo esc_url( $item['source_url'] ); ?>" target="_blank" class="description">
							<?php esc_html_e( 'Fonte', 'jep-automacao' ); ?> &rarr;
						</a>
					<?php endif; ?>
				</td>
				<td><?php echo esc_html( $item['territory'] ?: '—' ); ?></td>
				<td><?php echo esc_html( $item['priority'] ); ?></td>
				<td><span class="jep-badge jep-badge--<?php echo esc_attr( $item['status'] ); ?>"><?php echo esc_html( $item['status'] ); ?></span></td>
				<td><?php echo esc_html( wp_date( 'd/m/Y H:i', strtotime( $item['created_at'] ) ) ); ?></td>
				<td>
					<?php if ( 'pending' === $item['status'] ) : ?>
					<form method="post" style="display:inline">
						<?php wp_nonce_field( 'jep_cc_nonce' ); ?>
						<input type="hidden" name="jep_cc_action" value="delete">
						<input type="hidden" name="item_id" value="<?php echo esc_attr( $item['id'] ); ?>">
						<button type="submit" class="button button-small button-link-delete">
							<?php esc_html_e( 'Descartar', 'jep-automacao' ); ?>
						</button>
					</form>
					<?php endif; ?>
				</td>
			</tr>
			<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<!-- Add pauta form -->
	<div class="jep-section" id="jep-add-pauta">
		<h2><?php esc_html_e( 'Adicionar pauta manualmente', 'jep-automacao' ); ?></h2>
		<form method="post">
			<?php wp_nonce_field( 'jep_cc_nonce' ); ?>
			<input type="hidden" name="jep_cc_action" value="add">
			<table class="form-table">
				<tr>
					<th><label for="cc_title"><?php esc_html_e( 'Titulo', 'jep-automacao' ); ?></label></th>
					<td><input type="text" id="cc_title" name="title" class="large-text" required></td>
				</tr>
				<tr>
					<th><label for="cc_summary"><?php esc_html_e( 'Resumo / notas', 'jep-automacao' ); ?></label></th>
					<td><textarea id="cc_summary" name="summary" class="large-text" rows="4"></textarea></td>
				</tr>
				<tr>
					<th><label for="cc_territory"><?php esc_html_e( 'Territorio', 'jep-automacao' ); ?></label></th>
					<td>
						<input type="text" id="cc_territory" name="territory" class="regular-text" list="cc_territories_list">
						<datalist id="cc_territories_list">
							<?php foreach ( $territories as $t ) : ?>
								<option value="<?php echo esc_attr( $t ); ?>">
							<?php endforeach; ?>
						</datalist>
					</td>
				</tr>
				<tr>
					<th><label for="cc_source_url"><?php esc_html_e( 'URL da fonte', 'jep-automacao' ); ?></label></th>
					<td><input type="url" id="cc_source_url" name="source_url" class="regular-text"></td>
				</tr>
				<tr>
					<th><label for="cc_priority"><?php esc_html_e( 'Prioridade', 'jep-automacao' ); ?></label></th>
					<td><input type="number" id="cc_priority" name="priority" value="10" min="1" max="99" class="small-text">
					<p class="description"><?php esc_html_e( 'Menor = processada primeiro.', 'jep-automacao' ); ?></p></td>
				</tr>
			</table>
			<?php submit_button( __( 'Adicionar pauta', 'jep-automacao' ) ); ?>
		</form>
	</div>
</div>
