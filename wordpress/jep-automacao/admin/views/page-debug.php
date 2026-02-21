<?php
/**
 * Pagina de diagnostico do plugin JEP Automacao.
 * Acesse: wp-admin/admin.php?page=jep-automacao-debug
 *
 * @package JEP_Automacao
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( 'Acesso negado.' );
}

global $wpdb;

$prefix = $wpdb->prefix;
$plugin = jep_automacao();

// ---- Tables ----
$tables = array(
	'jep_llm_providers',
	'jep_llm_usage',
	'jep_rss_feeds',
	'jep_rss_queue',
	'jep_cold_content',
	'jep_logs',
);

$table_status = array();
foreach ( $tables as $t ) {
	$full = $prefix . $t;
	// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	$exists = $wpdb->get_var( "SHOW TABLES LIKE '{$full}'" ) === $full;
	$count  = $exists ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$full}`" ) : null; // phpcs:ignore
	$cols   = $exists
		? $wpdb->get_col( "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$full}' ORDER BY ORDINAL_POSITION" ) // phpcs:ignore
		: array();
	$table_status[ $t ] = array(
		'exists' => $exists,
		'count'  => $count,
		'cols'   => $cols,
	);
}

// ---- LLM Providers ----
$llm_table     = $prefix . 'jep_llm_providers';
$llm_providers = array();
if ( $table_status['jep_llm_providers']['exists'] ) {
	$llm_providers = $wpdb->get_results( "SELECT id, name, provider_type, model, is_active, monthly_limit, used_this_month FROM `{$llm_table}` ORDER BY priority", ARRAY_A ); // phpcs:ignore
}

// ---- Settings ----
$settings       = $plugin ? $plugin->settings() : null;
$llm_enabled    = $settings ? $settings->get( 'enable_llm', false ) : '?';
$tg_token_raw   = $settings ? $settings->get_telegram_bot_token() : '';
$tg_token_mask  = $tg_token_raw ? ( substr( $tg_token_raw, 0, 6 ) . '...' . substr( $tg_token_raw, -4 ) ) : '(vazio)';
$tg_chat        = $settings ? $settings->get( 'telegram_editor_chat_id', '' ) : '?';

// ---- DB Version ----
$db_version       = get_option( 'jep_automacao_db_version', '—' );
$db_version_const = defined( 'JEP_Installer' ) ? '' : ( class_exists( 'JEP_Installer' ) ? JEP_Installer::DB_VERSION : '—' );

// ---- Crons ----
$cron_hooks = array(
	'jep_daily_content',
	'jep_cold_content',
	'jep_topic_research',
	'jep_source_discovery',
	'jep_weekly_summary',
);

$cron_status = array();
foreach ( $cron_hooks as $hook ) {
	$next = wp_next_scheduled( $hook );
	$cron_status[ $hook ] = $next ? wp_date( 'd/m/Y H:i:s', $next ) : 'NAO AGENDADO';
}

// ---- Recent Logs ----
$recent_logs = JEP_Logger::get_logs( 20, 0 );
$log_counts  = JEP_Logger::get_summary();

// ---- PHP Info ----
$php_version    = PHP_VERSION;
$max_exec       = ini_get( 'max_execution_time' );
$memory_limit   = ini_get( 'memory_limit' );
$curl_ok        = function_exists( 'curl_init' );
$wp_debug       = defined( 'WP_DEBUG' ) && WP_DEBUG;
$wp_debug_log   = defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG;

// ---- Last AJAX error (if available in options) ----
?>
<div class="wrap">
	<h1>🔧 JEP Automacao — Diagnostico</h1>
	<p><em>Pagina de diagnostico para identificar problemas de configuracao e execucao.</em></p>

	<hr>

	<h2>1. Versoes e Ambiente</h2>
	<table class="widefat striped" style="max-width:700px">
		<tbody>
			<tr><td><strong>PHP</strong></td><td><?php echo esc_html( $php_version ); ?></td></tr>
			<tr><td><strong>WordPress</strong></td><td><?php echo esc_html( get_bloginfo( 'version' ) ); ?></td></tr>
			<tr><td><strong>DB Version (salva)</strong></td><td><?php echo esc_html( $db_version ); ?></td></tr>
			<tr><td><strong>DB Version (codigo)</strong></td><td><?php echo esc_html( class_exists( 'JEP_Installer' ) ? JEP_Installer::DB_VERSION : '?' ); ?></td></tr>
			<tr><td><strong>max_execution_time</strong></td><td><?php echo esc_html( $max_exec ); ?>s</td></tr>
			<tr><td><strong>memory_limit</strong></td><td><?php echo esc_html( $memory_limit ); ?></td></tr>
			<tr><td><strong>cURL</strong></td><td><?php echo $curl_ok ? '✅ OK' : '❌ AUSENTE'; ?></td></tr>
			<tr><td><strong>WP_DEBUG</strong></td><td><?php echo $wp_debug ? '✅ Ativo' : '⚠ Inativo'; ?></td></tr>
			<tr><td><strong>WP_DEBUG_LOG</strong></td><td><?php echo $wp_debug_log ? '✅ Ativo' : '⚠ Inativo'; ?></td></tr>
		</tbody>
	</table>

	<hr>

	<h2>2. Tabelas do Banco de Dados</h2>
	<table class="widefat striped">
		<thead>
			<tr>
				<th>Tabela</th>
				<th>Existe?</th>
				<th>Registros</th>
				<th>Colunas (relevantes)</th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( $table_status as $name => $info ) : ?>
			<tr>
				<td><code><?php echo esc_html( $prefix . $name ); ?></code></td>
				<td><?php echo $info['exists'] ? '✅' : '❌ AUSENTE'; ?></td>
				<td><?php echo null !== $info['count'] ? esc_html( $info['count'] ) : '—'; ?></td>
				<td style="font-size:11px"><?php echo esc_html( implode( ', ', $info['cols'] ) ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<?php if ( ! $table_status['jep_llm_providers']['exists'] || ! $table_status['jep_logs']['exists'] ) : ?>
		<div class="notice notice-error">
			<p><strong>Tabelas ausentes!</strong> Execute: <code>wp option delete jep_automacao_db_version</code> e reative o plugin para recriar as tabelas.</p>
		</div>
	<?php endif; ?>

	<hr>

	<h2>3. Provedores LLM</h2>
	<?php if ( empty( $llm_providers ) ) : ?>
		<div class="notice notice-warning inline"><p>⚠ Nenhum provedor cadastrado. Adicione um em <a href="<?php echo esc_url( admin_url( 'admin.php?page=jep-automacao-llm' ) ); ?>">Provedores LLM</a>.</p></div>
	<?php else : ?>
		<table class="widefat striped">
			<thead><tr><th>ID</th><th>Nome</th><th>Tipo</th><th>Modelo</th><th>Ativo</th><th>Limite/Mes</th><th>Usado</th></tr></thead>
			<tbody>
			<?php foreach ( $llm_providers as $p ) : ?>
				<tr>
					<td><?php echo esc_html( $p['id'] ); ?></td>
					<td><?php echo esc_html( $p['name'] ); ?></td>
					<td><?php echo esc_html( $p['provider_type'] ); ?></td>
					<td><?php echo esc_html( $p['model'] ); ?></td>
					<td><?php echo $p['is_active'] ? '✅' : '❌'; ?></td>
					<td><?php echo esc_html( $p['monthly_limit'] ); ?></td>
					<td><?php echo esc_html( $p['used_this_month'] ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<hr>

	<h2>4. Configuracoes (mascaradas)</h2>
	<table class="widefat striped" style="max-width:700px">
		<tbody>
			<tr><td><strong>Telegram Bot Token</strong></td><td><code><?php echo esc_html( $tg_token_mask ); ?></code></td></tr>
			<tr><td><strong>Telegram Editor Chat ID</strong></td><td><code><?php echo esc_html( $tg_chat ?: '(vazio)' ); ?></code></td></tr>
			<tr><td><strong>LLM Habilitado</strong></td><td><?php echo $llm_enabled ? '✅' : '❌'; ?></td></tr>
		</tbody>
	</table>

	<hr>

	<h2>5. Crons Agendados</h2>
	<table class="widefat striped" style="max-width:700px">
		<thead><tr><th>Hook</th><th>Proximo Disparo</th></tr></thead>
		<tbody>
		<?php foreach ( $cron_status as $hook => $next ) : ?>
			<tr>
				<td><code><?php echo esc_html( $hook ); ?></code></td>
				<td><?php echo 'NAO AGENDADO' === $next
					? '<span style="color:red">❌ NAO AGENDADO</span>'
					: esc_html( $next ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
	<?php $unscheduled = array_filter( $cron_status, fn( $v ) => 'NAO AGENDADO' === $v ); ?>
	<?php if ( ! empty( $unscheduled ) ) : ?>
		<p>
			<form method="post" style="display:inline">
				<?php wp_nonce_field( 'jep_reschedule_crons', '_nonce_reschedule' ); ?>
				<button type="submit" name="jep_reschedule" class="button">Reagendar Crons</button>
			</form>
		</p>
		<?php
		if ( isset( $_POST['jep_reschedule'] ) && check_admin_referer( 'jep_reschedule_crons', '_nonce_reschedule' ) ) {
			JEP_Scheduler::register_schedules();
			echo '<div class="notice notice-success inline"><p>Crons reagendados.</p></div>';
		}
		?>
	<?php endif; ?>

	<hr>

	<h2>6. Logs de Atividade</h2>
	<p>
		Info: <strong><?php echo esc_html( $log_counts['info'] ); ?></strong> &nbsp;|&nbsp;
		Sucesso: <strong><?php echo esc_html( $log_counts['success'] ); ?></strong> &nbsp;|&nbsp;
		Aviso: <strong><?php echo esc_html( $log_counts['warning'] ); ?></strong> &nbsp;|&nbsp;
		Erro: <strong style="color:red"><?php echo esc_html( $log_counts['error'] ); ?></strong>
	</p>

	<?php if ( empty( $recent_logs ) ) : ?>
		<div class="notice notice-warning inline">
			<p>⚠ <strong>Nenhum log encontrado.</strong> Verifique se a tabela <code><?php echo esc_html( $prefix . 'jep_logs' ); ?></code> existe e se o plugin foi executado ao menos uma vez.</p>
			<p>Clique abaixo para gerar um log de teste:</p>
			<form method="post">
				<?php wp_nonce_field( 'jep_test_log', '_nonce_testlog' ); ?>
				<button type="submit" name="jep_write_test_log" class="button button-primary">Escrever Log de Teste</button>
			</form>
			<?php
			if ( isset( $_POST['jep_write_test_log'] ) && check_admin_referer( 'jep_test_log', '_nonce_testlog' ) ) {
				$result = JEP_Logger::info( 'diagnostico', 'Log de teste gerado via pagina de diagnostico.' );
				if ( false === $result ) {
					echo '<p style="color:red"><strong>FALHA ao escrever log:</strong> ' . esc_html( $wpdb->last_error ) . '</p>';
				} else {
					echo '<p style="color:green"><strong>Log escrito com sucesso! ID:</strong> ' . esc_html( $result ) . '</p>';
				}
			}
			?>
		</div>
	<?php else : ?>
		<table class="widefat striped">
			<thead><tr><th>Data</th><th>Level</th><th>Evento</th><th>Mensagem</th></tr></thead>
			<tbody>
			<?php foreach ( $recent_logs as $log ) : ?>
				<tr>
					<td><?php echo esc_html( $log->created_at ); ?></td>
					<td><span style="color:<?php echo 'error' === $log->level ? 'red' : ( 'warning' === $log->level ? 'orange' : 'green' ); ?>"><?php echo esc_html( strtoupper( $log->level ) ); ?></span></td>
					<td><code><?php echo esc_html( $log->event ); ?></code></td>
					<td><?php echo esc_html( $log->message ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<hr>

	<h2>7. Teste AJAX (ao vivo)</h2>
	<p>
		<button class="button" id="jep-debug-test-ajax">Testar AJAX (jep_admin_nonce)</button>
		<span id="jep-debug-ajax-result" style="margin-left:10px"></span>
	</p>
	<p>
		<button class="button" id="jep-debug-write-log">Escrever Log via AJAX</button>
		<span id="jep-debug-log-result" style="margin-left:10px"></span>
	</p>

	<hr>

	<h2>8. Informacoes para Suporte</h2>
	<p>Copie e cole o bloco abaixo ao reportar um problema:</p>
	<textarea readonly rows="12" style="width:100%;font-family:monospace;font-size:12px"><?php
$info = array(
	'plugin_db_version_saved' => $db_version,
	'plugin_db_version_code'  => class_exists( 'JEP_Installer' ) ? JEP_Installer::DB_VERSION : '?',
	'php_version'             => $php_version,
	'wp_version'              => get_bloginfo( 'version' ),
	'tables'                  => array_map( fn( $t ) => array( 'exists' => $t['exists'], 'count' => $t['count'] ), $table_status ),
	'llm_providers_count'     => count( $llm_providers ),
	'cron_status'             => $cron_status,
	'log_counts'              => $log_counts,
	'telegram_token_set'      => ! empty( $tg_token_raw ),
	'telegram_chat_set'       => ! empty( $tg_chat ),
	'wp_debug'                => $wp_debug,
);
echo esc_textarea( wp_json_encode( $info, JSON_PRETTY_PRINT ) );
	?></textarea>
</div>

<script>
jQuery(document).ready(function($) {
	$('#jep-debug-test-ajax').on('click', function() {
		var $span = $('#jep-debug-ajax-result');
		$span.text('Testando...');
		$.post(ajaxurl, { action: 'jep_clear_logs', nonce: '<?php echo esc_js( wp_create_nonce( 'jep_admin_nonce' ) ); ?>', days: 9999999 }, function(r) {
			if (r && r.success !== undefined) {
				$span.css('color','green').text('✅ AJAX funcionando! (resposta: ' + JSON.stringify(r) + ')');
			} else {
				$span.css('color','red').text('❌ Resposta inesperada: ' + JSON.stringify(r));
			}
		}).fail(function(xhr) {
			$span.css('color','red').text('❌ Erro HTTP ' + xhr.status + ': ' + xhr.responseText.substring(0,200));
		});
	});

	$('#jep-debug-write-log').on('click', function() {
		var $span = $('#jep-debug-log-result');
		$span.text('Escrevendo...');
		$.post(ajaxurl, {
			action: 'jep_debug_write_log',
			nonce:  '<?php echo esc_js( wp_create_nonce( 'jep_admin_nonce' ) ); ?>',
		}, function(r) {
			if (r && r.success) {
				$span.css('color','green').text('✅ ' + r.data.message);
			} else {
				$span.css('color','red').text('❌ ' + (r && r.data ? r.data.message : 'Erro desconhecido'));
			}
		}).fail(function(xhr) {
			$span.css('color','red').text('❌ HTTP ' + xhr.status + ': ' + xhr.responseText.substring(0,200));
		});
	});
});
</script>
