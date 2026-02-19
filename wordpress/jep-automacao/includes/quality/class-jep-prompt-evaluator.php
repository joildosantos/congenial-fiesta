<?php
/**
 * Prompt Evaluator
 *
 * Samples LLM-generated outputs and submits them to a meta-evaluation LLM call
 * that scores the text against journalistic quality criteria. Scores are stored
 * for trend analysis and low-scoring outputs trigger Telegram alerts.
 *
 * @package    JEP_Automacao_Editorial
 * @subpackage Quality
 * @since      2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class JEP_Prompt_Evaluator
 *
 * Implements a sampled quality-assurance loop for LLM-generated journalistic
 * content. Every Nth call (configurable via $sample_rate) a meta-LLM evaluation
 * is triggered; results are persisted and surfaced to editors when scores fall
 * below an acceptable threshold.
 *
 * @since 2.0.0
 */
class JEP_Prompt_Evaluator {

	/**
	 * Database table that stores evaluation records.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	private $table = 'wp_jep_prompt_evaluations';

	/**
	 * Sampling rate: evaluate 1 in every N LLM calls.
	 *
	 * @since 2.0.0
	 * @var int
	 */
	private $sample_rate = 5;

	/**
	 * WordPress option key used to persist the call counter across requests.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	private $call_counter_option = 'jep_evaluator_call_counter';

	/**
	 * Plugin settings instance.
	 *
	 * @since 2.0.0
	 * @var JEP_Settings
	 */
	private $settings;

	/**
	 * Logger instance.
	 *
	 * @since 2.0.0
	 * @var JEP_Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @since 2.0.0
	 */
	public function __construct() {
		$this->settings = jep_automacao()->settings();
		$this->logger   = jep_automacao()->logger();
	}

	// -------------------------------------------------------------------------
	// Sampling gate
	// -------------------------------------------------------------------------

	/**
	 * Determine whether this invocation should be evaluated.
	 *
	 * Increments a persistent counter and returns true when the counter is a
	 * multiple of $sample_rate (i.e. every Nth call).
	 *
	 * @since 2.0.0
	 *
	 * @return bool True if the current call should trigger an evaluation.
	 */
	public function should_evaluate(): bool {
		$counter = (int) get_option( $this->call_counter_option, 0 );
		$counter++;
		update_option( $this->call_counter_option, $counter, false );

		return ( $counter % $this->sample_rate ) === 0;
	}

	// -------------------------------------------------------------------------
	// Core evaluation
	// -------------------------------------------------------------------------

	/**
	 * Evaluate an LLM output against journalistic quality criteria.
	 *
	 * Skips evaluation when the sampling gate returns false. Otherwise sends
	 * a meta-prompt to the LLM, parses the JSON score, persists the record,
	 * and alerts the editor via Telegram if the score is too low.
	 *
	 * @since 2.0.0
	 *
	 * @param string $prompt_type  Identifier for the type of prompt (e.g. "reescrita", "resumo").
	 * @param string $prompt       The original prompt that generated $output.
	 * @param string $output       The LLM-generated text to evaluate.
	 * @param int    $provider_id  Optional ID of the LLM provider that produced $output.
	 * @return array|null Evaluation record on success, null when skipped.
	 */
	public function evaluate_output( string $prompt_type, string $prompt, string $output, int $provider_id = 0 ): ?array {
		if ( ! $this->should_evaluate() ) {
			return null;
		}

		$meta_prompt = $this->build_meta_prompt( $prompt, $output );

		// Use lowest-priority provider to preserve main quota.
		$llm      = jep_automacao()->llm();
		$response = $llm ? $llm->complete( $meta_prompt, [ 'priority' => 'low' ] ) : '';

		if ( empty( $response ) ) {
			$this->logger->warning( 'Prompt Evaluator: LLM não retornou avaliação.' );
			return null;
		}

		$evaluation = $this->parse_evaluation_response( $response );
		if ( null === $evaluation ) {
			return null;
		}

		// Persist the evaluation record.
		$record_id = $this->insert_evaluation( $prompt_type, $prompt, $output, $provider_id, $evaluation );

		$score_total = (int) ( $evaluation['score_total'] ?? 0 );

		if ( $score_total < 6 ) {
			$this->logger->warning(
				sprintf( 'Prompt Evaluator: qualidade baixa detectada (score %d/10) em %s.', $score_total, $prompt_type ),
				[ 'record_id' => $record_id, 'prompt_type' => $prompt_type ]
			);

			$message = sprintf(
				"⚠️ Qualidade baixa detectada (score %d/10) em %s",
				$score_total,
				$prompt_type
			);
			/** @var JEP_Telegram_Bot $telegram */
			$telegram = jep_automacao()->telegram();
			if ( $telegram ) {
				$telegram->send_message( $message );
			}
		}

		$evaluation['record_id']   = $record_id;
		$evaluation['prompt_type'] = $prompt_type;

		return $evaluation;
	}

	// -------------------------------------------------------------------------
	// Reporting queries
	// -------------------------------------------------------------------------

	/**
	 * Get average quality scores per prompt type over the specified number of days.
	 *
	 * @since 2.0.0
	 *
	 * @param int $days Number of past days to include (default 30).
	 * @return array[] Associative array keyed by prompt_type, each containing avg scores.
	 */
	public function get_average_scores( int $days = 30 ): array {
		global $wpdb;

		$table     = esc_sql( $this->table );
		$date_from = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					prompt_type,
					AVG(score_total)    AS avg_score_total,
					AVG(score_clareza)  AS avg_clareza,
					AVG(score_precisao) AS avg_precisao,
					AVG(score_tom)      AS avg_tom,
					AVG(score_estrutura) AS avg_estrutura,
					AVG(score_seo)      AS avg_seo,
					COUNT(*)            AS total_evaluations
				FROM `{$table}`
				WHERE created_at >= %s
				GROUP BY prompt_type
				ORDER BY avg_score_total ASC",
				$date_from
			),
			ARRAY_A
		);
		// phpcs:enable

		$result = [];
		foreach ( $rows as $row ) {
			$result[ $row['prompt_type'] ] = [
				'avg_score_total'    => round( (float) $row['avg_score_total'], 2 ),
				'avg_clareza'        => round( (float) $row['avg_clareza'], 2 ),
				'avg_precisao'       => round( (float) $row['avg_precisao'], 2 ),
				'avg_tom'            => round( (float) $row['avg_tom'], 2 ),
				'avg_estrutura'      => round( (float) $row['avg_estrutura'], 2 ),
				'avg_seo'            => round( (float) $row['avg_seo'], 2 ),
				'total_evaluations'  => (int) $row['total_evaluations'],
			];
		}

		return $result;
	}

	/**
	 * Retrieve a paginated, optionally filtered list of evaluation records.
	 *
	 * @since 2.0.0
	 *
	 * @param array $args {
	 *     Optional query arguments.
	 *     @type string $prompt_type Filter by prompt type slug.
	 *     @type string $date_from   ISO date string lower bound (Y-m-d).
	 *     @type string $date_to     ISO date string upper bound (Y-m-d).
	 *     @type int    $per_page    Records per page (default 20).
	 *     @type int    $page        1-based page number (default 1).
	 * }
	 * @return array{items: array[], total: int}
	 */
	public function get_evaluations( array $args = [] ): array {
		global $wpdb;

		$table    = esc_sql( $this->table );
		$per_page = max( 1, (int) ( $args['per_page'] ?? 20 ) );
		$page     = max( 1, (int) ( $args['page'] ?? 1 ) );
		$offset   = ( $page - 1 ) * $per_page;

		$where_clauses = [];
		$where_values  = [];

		if ( ! empty( $args['prompt_type'] ) ) {
			$where_clauses[] = 'prompt_type = %s';
			$where_values[]  = $args['prompt_type'];
		}

		if ( ! empty( $args['date_from'] ) ) {
			$where_clauses[] = 'created_at >= %s';
			$where_values[]  = $args['date_from'] . ' 00:00:00';
		}

		if ( ! empty( $args['date_to'] ) ) {
			$where_clauses[] = 'created_at <= %s';
			$where_values[]  = $args['date_to'] . ' 23:59:59';
		}

		$where_sql = $where_clauses ? 'WHERE ' . implode( ' AND ', $where_clauses ) : '';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count_sql = "SELECT COUNT(*) FROM `{$table}` {$where_sql}";
		$total     = (int) ( $where_values
			? $wpdb->get_var( $wpdb->prepare( $count_sql, ...$where_values ) )
			: $wpdb->get_var( $count_sql )
		);

		$query_sql = "SELECT * FROM `{$table}` {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";
		$query_values = array_merge( $where_values, [ $per_page, $offset ] );
		$items = $wpdb->get_results( $wpdb->prepare( $query_sql, ...$query_values ), ARRAY_A );
		// phpcs:enable

		return [
			'items' => $items ?: [],
			'total' => $total,
		];
	}

	/**
	 * Fetch unique improvement suggestions generated by the LLM for low-scoring outputs.
	 *
	 * @since 2.0.0
	 *
	 * @param string $prompt_type Filter to a specific prompt type, or '' for all types.
	 * @param int    $limit       Maximum number of suggestions to return (default 10).
	 * @return string[] Array of unique suggestion strings.
	 */
	public function get_improvement_suggestions( string $prompt_type = '', int $limit = 10 ): array {
		global $wpdb;

		$table         = esc_sql( $this->table );
		$where_clauses = [ 'score_total < 7' ];
		$where_values  = [];

		if ( ! empty( $prompt_type ) ) {
			$where_clauses[] = 'prompt_type = %s';
			$where_values[]  = $prompt_type;
		}

		$where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query_sql    = "SELECT sugestao_melhoria_prompt FROM `{$table}` {$where_sql} ORDER BY created_at DESC LIMIT %d";
		$where_values[] = $limit * 3; // Fetch more to allow for deduplication.

		$rows = $wpdb->get_col( $wpdb->prepare( $query_sql, ...$where_values ) );
		// phpcs:enable

		// Deduplicate and return up to $limit entries.
		$unique = array_values( array_unique( array_filter( $rows ) ) );

		return array_slice( $unique, 0, $limit );
	}

	// -------------------------------------------------------------------------
	// Prompt version management
	// -------------------------------------------------------------------------

	/**
	 * Retrieve all stored versions of a given prompt type.
	 *
	 * @since 2.0.0
	 *
	 * @param string $prompt_type Prompt type identifier.
	 * @return array[] Array of version records, each containing version, content,
	 *                 created_at, avg_score_before, avg_score_after.
	 */
	public function get_prompt_versions( string $prompt_type ): array {
		$option_key = 'jep_prompt_versions_' . sanitize_key( $prompt_type );
		$versions   = get_option( $option_key, [] );

		return is_array( $versions ) ? $versions : [];
	}

	/**
	 * Save a new prompt version, retaining only the 10 most recent versions.
	 *
	 * @since 2.0.0
	 *
	 * @param string $prompt_type Prompt type identifier.
	 * @param string $content     Full text of the new prompt version.
	 * @return void
	 */
	public function save_prompt_version( string $prompt_type, string $content ): void {
		$option_key = 'jep_prompt_versions_' . sanitize_key( $prompt_type );
		$versions   = get_option( $option_key, [] );

		if ( ! is_array( $versions ) ) {
			$versions = [];
		}

		// Determine the next sequential version number.
		$next_version = count( $versions ) + 1;

		$versions[] = [
			'version'          => $next_version,
			'content'          => $content,
			'created_at'       => current_time( 'mysql', true ),
			'avg_score_before' => null,
			'avg_score_after'  => null,
		];

		// Keep only the latest 10 versions.
		if ( count( $versions ) > 10 ) {
			$versions = array_slice( $versions, -10, 10, true );
		}

		update_option( $option_key, $versions, false );

		$this->logger->info(
			sprintf( 'Prompt Evaluator: nova versão salva para tipo "%s".', $prompt_type ),
			[ 'version' => $next_version ]
		);
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Build the meta-evaluation prompt.
	 *
	 * @since 2.0.0
	 *
	 * @param string $prompt The original prompt sent to the LLM.
	 * @param string $output The LLM-generated text to assess.
	 * @return string Complete meta-prompt string.
	 */
	private function build_meta_prompt( string $prompt, string $output ): string {
		return <<<META
Você é um editor sênior de jornalismo comunitário brasileiro. Avalie o texto abaixo de 1 a 10 nos critérios abaixo. Retorne JSON: {score_total: int, criterios: {clareza: int, precisao: int, tom: int, estrutura: int, seo: int}, feedback_geral: string, sugestao_melhoria_prompt: string}

TEXTO AVALIADO:
{$output}

PROMPT USADO:
{$prompt}
META;
	}

	/**
	 * Parse the JSON evaluation response returned by the meta-LLM call.
	 *
	 * @since 2.0.0
	 *
	 * @param string $response Raw LLM response text.
	 * @return array|null Parsed evaluation array, or null on parse failure.
	 */
	private function parse_evaluation_response( string $response ): ?array {
		// Strip markdown code fences if present.
		$json_string = preg_replace( '/^```(?:json)?\s*/i', '', trim( $response ) );
		$json_string = preg_replace( '/\s*```$/', '', $json_string );

		// Find the outermost JSON object.
		$start = strpos( $json_string, '{' );
		$end   = strrpos( $json_string, '}' );

		if ( false === $start || false === $end ) {
			$this->logger->warning( 'Prompt Evaluator: JSON não encontrado na resposta da avaliação.' );
			return null;
		}

		$json_string = substr( $json_string, $start, $end - $start + 1 );
		$decoded     = json_decode( $json_string, true );

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
			$this->logger->warning(
				'Prompt Evaluator: resposta JSON inválida.',
				[ 'json_error' => json_last_error_msg() ]
			);
			return null;
		}

		return $decoded;
	}

	/**
	 * Persist an evaluation record in the database.
	 *
	 * @since 2.0.0
	 *
	 * @param string $prompt_type Prompt type identifier.
	 * @param string $prompt      Original prompt text.
	 * @param string $output      LLM-generated text that was evaluated.
	 * @param int    $provider_id LLM provider ID.
	 * @param array  $evaluation  Parsed evaluation data from the meta-LLM.
	 * @return int|false Inserted row ID, or false on DB error.
	 */
	private function insert_evaluation( string $prompt_type, string $prompt, string $output, int $provider_id, array $evaluation ) {
		global $wpdb;

		$table      = esc_sql( $this->table );
		$criterios  = $evaluation['criterios'] ?? [];

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->insert(
			$table,
			[
				'prompt_type'              => sanitize_text_field( $prompt_type ),
				'prompt'                   => $prompt,
				'output'                   => $output,
				'provider_id'              => $provider_id,
				'score_total'              => (int) ( $evaluation['score_total'] ?? 0 ),
				'score_clareza'            => (int) ( $criterios['clareza'] ?? 0 ),
				'score_precisao'           => (int) ( $criterios['precisao'] ?? 0 ),
				'score_tom'                => (int) ( $criterios['tom'] ?? 0 ),
				'score_estrutura'          => (int) ( $criterios['estrutura'] ?? 0 ),
				'score_seo'                => (int) ( $criterios['seo'] ?? 0 ),
				'feedback_geral'           => sanitize_textarea_field( $evaluation['feedback_geral'] ?? '' ),
				'sugestao_melhoria_prompt' => sanitize_textarea_field( $evaluation['sugestao_melhoria_prompt'] ?? '' ),
				'created_at'               => current_time( 'mysql', true ),
			],
			[ '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s' ]
		);
		// phpcs:enable

		return $result ? $wpdb->insert_id : false;
	}
}
