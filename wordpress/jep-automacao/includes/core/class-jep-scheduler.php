<?php
/**
 * Gerenciador de cron jobs do plugin JEP Automacao v2.
 *
 * Registra, remove e despacha os 5 jobs periodicos do plugin.
 * Tambem expoe status e execucao manual via painel administrativo.
 *
 * @package JEP_Automacao
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class JEP_Scheduler
 */
class JEP_Scheduler {

	// -------------------------------------------------------------------------
	// Constantes de hooks WP-Cron
	// -------------------------------------------------------------------------

	const HOOK_DAILY_CONTENT   = 'jep_cron_daily_content';
	const HOOK_COLD_CONTENT    = 'jep_cron_cold_content';
	const HOOK_TOPIC_RESEARCH  = 'jep_cron_topic_research';
	const HOOK_SOURCE_DISCOVERY = 'jep_cron_source_discovery';
	const HOOK_WEEKLY_SUMMARY  = 'jep_cron_weekly_summary';

	/**
	 * Todos os hooks gerenciados por esta classe.
	 *
	 * @var string[]
	 */
	private static $all_hooks = array(
		self::HOOK_DAILY_CONTENT,
		self::HOOK_COLD_CONTENT,
		self::HOOK_TOPIC_RESEARCH,
		self::HOOK_SOURCE_DISCOVERY,
		self::HOOK_WEEKLY_SUMMARY,
	);

	// -------------------------------------------------------------------------
	// Construtor e registro de acoes
	// -------------------------------------------------------------------------

	/**
	 * Registra os callbacks dos cron jobs no WordPress.
	 */
	public function __construct() {
		add_action( self::HOOK_DAILY_CONTENT,    array( $this, 'dispatch_daily_content' ) );
		add_action( self::HOOK_COLD_CONTENT,     array( $this, 'dispatch_cold_content' ) );
		add_action( self::HOOK_TOPIC_RESEARCH,   array( $this, 'dispatch_topic_research' ) );
		add_action( self::HOOK_SOURCE_DISCOVERY, array( $this, 'dispatch_source_discovery' ) );
		add_action( self::HOOK_WEEKLY_SUMMARY,   array( $this, 'send_weekly_summary' ) );
	}

	// -------------------------------------------------------------------------
	// Dispatchers individuais
	// -------------------------------------------------------------------------

	/**
	 * Despacha o cron de conteudo diario (RSS + reescrita).
	 */
	public function dispatch_daily_content() {
		update_option( 'jep_automacao_last_run_daily', current_time( 'mysql' ) );
		try {
			JEP_Daily_Content::instance()->run();
		} catch ( Exception $e ) {
			jep_automacao()->logger()->error(
				'scheduler.daily_content',
				'Erro no cron de conteudo diario: ' . $e->getMessage()
			);
		}
	}

	/**
	 * Despacha o cron de conteudo frio (processamento da fila cold_content).
	 */
	public function dispatch_cold_content() {
		update_option( 'jep_automacao_last_run_cold', current_time( 'mysql' ) );
		try {
			JEP_Cold_Content::instance()->process_next();
		} catch ( Exception $e ) {
			jep_automacao()->logger()->error(
				'scheduler.cold_content',
				'Erro no cron de conteudo frio: ' . $e->getMessage()
			);
		}
	}

	/**
	 * Despacha o cron de pesquisa de topicos.
	 */
	public function dispatch_topic_research() {
		update_option( 'jep_automacao_last_run_research', current_time( 'mysql' ) );
		try {
			JEP_Topic_Research::instance()->run();
		} catch ( Exception $e ) {
			jep_automacao()->logger()->error(
				'scheduler.topic_research',
				'Erro no cron de pesquisa de topicos: ' . $e->getMessage()
			);
		}
	}

	/**
	 * Despacha o cron de descoberta de fontes.
	 */
	public function dispatch_source_discovery() {
		update_option( 'jep_automacao_last_run_discovery', current_time( 'mysql' ) );
		try {
			JEP_Source_Discovery::instance()->run();
		} catch ( Exception $e ) {
			jep_automacao()->logger()->error(
				'scheduler.source_discovery',
				'Erro no cron de descoberta de fontes: ' . $e->getMessage()
			);
		}
	}

	// -------------------------------------------------------------------------
	// Registro e remocao de crons (estaticos — usados pelo Installer)
	// -------------------------------------------------------------------------

	/**
	 * Agenda todos os 5 cron jobs no WordPress.
	 * Chamado em JEP_Installer::activate().
	 *
	 * Os horarios sao lidos das opcoes do plugin; se ainda nao existirem,
	 * usa valores padrao seguros.
	 */
	public static function register_schedules() {
		// Cron: conteudo diario — diario no horario configurado.
		self::maybe_schedule(
			self::HOOK_DAILY_CONTENT,
			self::next_timestamp_for_time(
				get_option( 'jep_automacao_cron_daily_time', '06:00' )
			),
			'daily',
			'enable_cron_daily'
		);

		// Cron: conteudo frio — duas vezes ao dia.
		self::maybe_schedule(
			self::HOOK_COLD_CONTENT,
			self::next_timestamp_for_time(
				get_option( 'jep_automacao_cron_cold_time', '08:00' )
			),
			'twicedaily',
			'enable_cron_cold'
		);

		// Cron: pesquisa de topicos — semanal (dia configurado).
		self::maybe_schedule(
			self::HOOK_TOPIC_RESEARCH,
			self::next_timestamp_for_weekday(
				get_option( 'jep_automacao_cron_research_day', 'monday' ),
				'09:00'
			),
			'weekly',
			'enable_cron_research'
		);

		// Cron: descoberta de fontes — diario de madrugada.
		self::maybe_schedule(
			self::HOOK_SOURCE_DISCOVERY,
			self::next_timestamp_for_time(
				get_option( 'jep_automacao_cron_discovery_time', '03:00' )
			),
			'daily',
			'enable_cron_discovery'
		);

		// Cron: resumo semanal — semanal, sexta a noite.
		self::maybe_schedule(
			self::HOOK_WEEKLY_SUMMARY,
			self::next_timestamp_for_time(
				get_option( 'jep_automacao_cron_summary_time', '20:00' )
			),
			'weekly',
			'enable_cron_summary'
		);
	}

	/**
	 * Remove todos os cron jobs do plugin do WordPress.
	 * Chamado em JEP_Installer::deactivate().
	 */
	public static function clear_schedules() {
		foreach ( self::$all_hooks as $hook ) {
			wp_clear_scheduled_hook( $hook );
		}
	}

	// -------------------------------------------------------------------------
	// Status e execucao manual
	// -------------------------------------------------------------------------

	/**
	 * Retorna o status de todos os 5 crons.
	 *
	 * @return array[] Array de arrays com name, hook, next_run, last_run, status.
	 */
	public function get_cron_status() {
		$crons = array(
			array(
				'name'           => __( 'Conteudo Diario (RSS)', 'jep-automacao' ),
				'hook'           => self::HOOK_DAILY_CONTENT,
				'last_run_option' => 'jep_automacao_last_run_daily',
				'enabled_option' => 'jep_automacao_enable_cron_daily',
			),
			array(
				'name'           => __( 'Conteudo Frio', 'jep-automacao' ),
				'hook'           => self::HOOK_COLD_CONTENT,
				'last_run_option' => 'jep_automacao_last_run_cold',
				'enabled_option' => 'jep_automacao_enable_cron_cold',
			),
			array(
				'name'           => __( 'Pesquisa de Topicos', 'jep-automacao' ),
				'hook'           => self::HOOK_TOPIC_RESEARCH,
				'last_run_option' => 'jep_automacao_last_run_research',
				'enabled_option' => 'jep_automacao_enable_cron_research',
			),
			array(
				'name'           => __( 'Descoberta de Fontes', 'jep-automacao' ),
				'hook'           => self::HOOK_SOURCE_DISCOVERY,
				'last_run_option' => 'jep_automacao_last_run_discovery',
				'enabled_option' => 'jep_automacao_enable_cron_discovery',
			),
			array(
				'name'           => __( 'Resumo Semanal Telegram', 'jep-automacao' ),
				'hook'           => self::HOOK_WEEKLY_SUMMARY,
				'last_run_option' => 'jep_automacao_last_run_summary',
				'enabled_option' => 'jep_automacao_enable_cron_summary',
			),
		);

		$result = array();

		foreach ( $crons as $cron ) {
			$next_timestamp = wp_next_scheduled( $cron['hook'] );
			$last_run_raw   = get_option( $cron['last_run_option'], '' );
			$enabled        = '1' === get_option( $cron['enabled_option'], '1' );

			// Formata o proximo disparo de forma legivel.
			if ( $next_timestamp ) {
				$diff    = $next_timestamp - time();
				$next_run = sprintf(
					// translators: %s = tempo humano legivel.
					__( 'em %s', 'jep-automacao' ),
					human_time_diff( time(), $next_timestamp )
				) . ' (' . wp_date( 'd/m/Y H:i', $next_timestamp ) . ')';
			} else {
				$next_run = __( 'Nao agendado', 'jep-automacao' );
			}

			// Formata o ultimo disparo.
			if ( $last_run_raw ) {
				$last_run = wp_date( 'd/m/Y H:i', strtotime( $last_run_raw ) );
			} else {
				$last_run = __( 'Nunca executado', 'jep-automacao' );
			}

			// Determina status visual.
			if ( ! $enabled ) {
				$status = 'disabled';
			} elseif ( $next_timestamp ) {
				$status = 'scheduled';
			} else {
				$status = 'unscheduled';
			}

			$result[] = array(
				'name'     => $cron['name'],
				'hook'     => $cron['hook'],
				'next_run' => $next_run,
				'last_run' => $last_run,
				'status'   => $status,
				'enabled'  => $enabled,
			);
		}

		return $result;
	}

	/**
	 * Executa imediatamente o callback de um cron pelo nome curto.
	 * Usado pelo painel administrativo para testes manuais.
	 *
	 * @param string $cron_name Nome curto: daily, cold, research, discovery, summary.
	 * @return bool True em sucesso, false se o nome for invalido.
	 */
	public function run_now( $cron_name ) {
		$map = array(
			'daily'     => array( $this, 'dispatch_daily_content' ),
			'cold'      => array( $this, 'dispatch_cold_content' ),
			'research'  => array( $this, 'dispatch_topic_research' ),
			'discovery' => array( $this, 'dispatch_source_discovery' ),
			'summary'   => array( $this, 'send_weekly_summary' ),
		);

		if ( ! isset( $map[ $cron_name ] ) ) {
			return false;
		}

		call_user_func( $map[ $cron_name ] );
		return true;
	}

	// -------------------------------------------------------------------------
	// Resumo semanal via Telegram
	// -------------------------------------------------------------------------

	/**
	 * Coleta estatisticas dos ultimos 7 dias e envia resumo via Telegram.
	 */
	public function send_weekly_summary() {
		update_option( 'jep_automacao_last_run_summary', current_time( 'mysql' ) );

		if ( ! jep_automacao()->settings()->is_telegram_configured() ) {
			return;
		}

		global $wpdb;

		$since = gmdate( 'Y-m-d H:i:s', strtotime( '-7 days' ) );

		// --- Posts publicados nos ultimos 7 dias ---
		$posts_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts}
				WHERE post_status = 'publish'
				AND post_date >= %s
				AND post_type = 'post'",
				$since
			)
		);

		// --- Total de chamadas LLM (tokens) ---
		$llm_table     = $wpdb->prefix . 'jep_llm_usage';
		$llm_summary   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) AS total_calls,
					SUM(tokens_in)  AS total_tokens_in,
					SUM(tokens_out) AS total_tokens_out,
					SUM(cost_usd)   AS total_cost
				FROM {$llm_table}
				WHERE created_at >= %s",
				$since
			)
		);

		$total_calls      = $llm_summary ? (int) $llm_summary->total_calls : 0;
		$total_tokens_in  = $llm_summary ? (int) $llm_summary->total_tokens_in : 0;
		$total_tokens_out = $llm_summary ? (int) $llm_summary->total_tokens_out : 0;
		$total_cost       = $llm_summary ? (float) $llm_summary->total_cost : 0.0;

		// --- Top 3 provedores por uso ---
		$providers_table = $wpdb->prefix . 'jep_llm_providers';
		$top_providers   = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.name, COUNT(u.id) AS calls, SUM(u.cost_usd) AS cost
				FROM {$llm_table} u
				LEFT JOIN {$providers_table} p ON p.id = u.provider_id
				WHERE u.created_at >= %s
				GROUP BY u.provider_id
				ORDER BY calls DESC
				LIMIT 3",
				$since
			)
		);

		// --- Erros nos ultimos 7 dias ---
		$logs_table   = $wpdb->prefix . 'jep_logs';
		$error_count  = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$logs_table} WHERE level = 'error' AND created_at >= %s",
				$since
			)
		);

		// --- Monta mensagem ---
		$site_name = get_bloginfo( 'name' );
		$date_from = wp_date( 'd/m', strtotime( '-7 days' ) );
		$date_to   = wp_date( 'd/m/Y' );

		$message  = "\xF0\x9F\x93\x8A *Resumo Semanal JEP Automacao*\n";
		$message .= "_" . esc_html( $site_name ) . " — {$date_from} a {$date_to}_\n\n";

		$message .= "\xF0\x9F\x93\x9D *Conteudo Publicado*\n";
		$message .= "Posts publicados: *{$posts_count}*\n\n";

		$message .= "\xF0\x9F\xA4\x96 *Uso de LLM*\n";
		$message .= "Chamadas: *{$total_calls}*\n";
		$message .= "Tokens enviados: *" . number_format( $total_tokens_in ) . "*\n";
		$message .= "Tokens recebidos: *" . number_format( $total_tokens_out ) . "*\n";
		$message .= "Custo estimado: *\$" . number_format( $total_cost, 4 ) . " USD*\n\n";

		if ( ! empty( $top_providers ) ) {
			$message .= "\xF0\x9F\x8F\x86 *Top Provedores*\n";
			foreach ( $top_providers as $prov ) {
				$prov_name  = $prov->name ?: __( 'Desconhecido', 'jep-automacao' );
				$prov_calls = (int) $prov->calls;
				$prov_cost  = number_format( (float) $prov->cost, 4 );
				$message   .= "• {$prov_name}: {$prov_calls} chamadas (\${$prov_cost})\n";
			}
			$message .= "\n";
		}

		$message .= "\xE2\x9A\xA0 *Erros Registrados*: {$error_count}\n";
		$message .= "\n_Gerado automaticamente em " . wp_date( 'd/m/Y H:i' ) . "_";

		// --- Envia via Telegram ---
		try {
			jep_automacao()->telegram()->send_message(
				jep_automacao()->settings()->get_telegram_editor_chat_id(),
				$message,
				array( 'parse_mode' => 'Markdown' )
			);

			jep_automacao()->logger()->success(
				'scheduler.weekly_summary',
				'Resumo semanal enviado via Telegram.'
			);
		} catch ( Exception $e ) {
			jep_automacao()->logger()->error(
				'scheduler.weekly_summary',
				'Falha ao enviar resumo semanal: ' . $e->getMessage()
			);
		}
	}

	// -------------------------------------------------------------------------
	// Helpers internos (privados/estaticos)
	// -------------------------------------------------------------------------

	/**
	 * Agenda um hook se ele ainda nao estiver agendado e a opcao estiver habilitada.
	 *
	 * @param string $hook           Hook WP-Cron.
	 * @param int    $first_run      Timestamp do primeiro disparo.
	 * @param string $recurrence     Recorrencia (daily, twicedaily, weekly).
	 * @param string $enabled_option Chave da opcao de habilitacao (sem prefixo).
	 */
	private static function maybe_schedule( $hook, $first_run, $recurrence, $enabled_option ) {
		$is_enabled = '1' === get_option( 'jep_automacao_' . $enabled_option, '1' );

		if ( ! $is_enabled ) {
			return;
		}

		if ( ! wp_next_scheduled( $hook ) ) {
			wp_schedule_event( $first_run, $recurrence, $hook );
		}
	}

	/**
	 * Calcula o proximo timestamp para um horario no formato HH:MM.
	 * Se o horario ja passou hoje, retorna para amanha.
	 *
	 * @param string $time_string Horario no formato HH:MM (ex: '06:00').
	 * @return int Timestamp Unix.
	 */
	private static function next_timestamp_for_time( $time_string ) {
		$parts  = explode( ':', $time_string );
		$hour   = isset( $parts[0] ) ? (int) $parts[0] : 6;
		$minute = isset( $parts[1] ) ? (int) $parts[1] : 0;

		// Usa o timezone do WordPress.
		$tz       = wp_timezone();
		$now      = new DateTimeImmutable( 'now', $tz );
		$today_at = ( new DateTimeImmutable( 'today', $tz ) )
			->setTime( $hour, $minute, 0 );

		if ( $now >= $today_at ) {
			$target = $today_at->modify( '+1 day' );
		} else {
			$target = $today_at;
		}

		return $target->getTimestamp();
	}

	/**
	 * Calcula o proximo timestamp para um dia da semana e horario especificos.
	 *
	 * @param string $weekday    Dia em ingles, minusculo (ex: 'monday').
	 * @param string $time_string Horario HH:MM.
	 * @return int Timestamp Unix.
	 */
	private static function next_timestamp_for_weekday( $weekday, $time_string ) {
		$parts  = explode( ':', $time_string );
		$hour   = isset( $parts[0] ) ? (int) $parts[0] : 9;
		$minute = isset( $parts[1] ) ? (int) $parts[1] : 0;

		$tz  = wp_timezone();
		$now = new DateTimeImmutable( 'now', $tz );

		// 'next monday', etc. pode retornar o mesmo dia se ja for segunda muito cedo;
		// usamos 'next <weekday>' a partir de ontem para cobrir todos os casos.
		$next = new DateTimeImmutable( 'next ' . $weekday, $tz );
		$next = $next->setTime( $hour, $minute, 0 );

		// Se o proximo disparo calculado ja passou (mesmo dia, horario anterior), avanca 7 dias.
		if ( $next <= $now ) {
			$next = $next->modify( '+7 days' );
		}

		return $next->getTimestamp();
	}
}
