<?php
/**
 * View: Pagina de Configuracoes.
 *
 * @package JEP_Automacao
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="wrap jep-wrap">
	<h1 class="jep-title">
		<span class="dashicons dashicons-admin-settings"></span>
		<?php esc_html_e( 'Configuracoes - JEP Automacao', 'jep-automacao' ); ?>
	</h1>

	<?php settings_errors( 'jep_automacao_settings' ); ?>

	<form method="post" action="options.php">
		<?php
		settings_fields( 'jep_automacao_settings' );
		do_settings_sections( 'jep-automacao-settings' );
		submit_button( __( 'Salvar Configuracoes', 'jep-automacao' ) );
		?>
	</form>

	<!-- Token secreto e endpoint REST -->
	<div class="jep-section jep-section--info" style="margin-top:30px;">
		<h2><?php esc_html_e( 'Como configurar no n8n', 'jep-automacao' ); ?></h2>
		<ol>
			<li><?php esc_html_e( 'Suba o n8n via Docker Compose (veja docs/setup.md no repositorio).', 'jep-automacao' ); ?></li>
			<li>
				<?php
				printf(
					esc_html__( 'No n8n, importe os 6 workflows da pasta %s.', 'jep-automacao' ),
					'<code>n8n/workflows/</code>'
				);
				?>
			</li>
			<li>
				<?php
				printf(
					esc_html__( 'Configure a credencial WordPress no n8n: URL %s + Application Password.', 'jep-automacao' ),
					'<code>' . esc_html( get_bloginfo( 'url' ) ) . '</code>'
				);
				?>
			</li>
			<li>
				<?php esc_html_e( 'Nos nodes HTTP do n8n que chamam este WordPress, adicione o header:', 'jep-automacao' ); ?>
				<br>
				<code>X-JEP-Token: <?php echo esc_html( get_option( 'jep_automacao_n8n_secret_token', '' ) ); ?></code>
			</li>
			<li>
				<?php esc_html_e( 'Base URL dos endpoints REST:', 'jep-automacao' ); ?>
				<br>
				<code><?php echo esc_html( rest_url( 'jep/v1' ) ); ?></code>
			</li>
		</ol>
	</div>
</div>
