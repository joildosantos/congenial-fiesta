<?php
/**
 * Source Discovery
 *
 * Analyses recent RSS-queue entries to discover new Brazilian journalistic
 * sources relevant to periferias, evaluates them via LLM, tests the candidate
 * feed URLs, and stores validated suggestions for editorial review.
 *
 * @package    JEP_Automacao_Editorial
 * @subpackage Quality
 * @since      2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class JEP_Source_Discovery
 *
 * Automates the discovery and validation of new RSS feed sources by mining
 * domains from recently ingested content and consulting an LLM to identify
 * journalistic outlets that match the editorial focus on periferias.
 *
 * @since 2.0.0
 */
class JEP_Source_Discovery {

	/**
	 * Database table: registered RSS feeds.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	private $table_feeds;

	/**
	 * Database table: ingestion queue items.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	private $table_queue;

	/**
	 * Database table: suggested (candidate) feeds awaiting editorial review.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	private $table_suggested;

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
		global $wpdb;
		$this->table_feeds     = $wpdb->prefix . 'jep_rss_feeds';
		$this->table_queue     = $wpdb->prefix . 'jep_rss_queue';
		$this->table_suggested = $wpdb->prefix . 'jep_suggested_feeds';
		$this->settings        = jep_automacao()->settings();
		$this->logger          = jep_automacao()->logger();
	}

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Run the discovery pipeline.
	 *
	 * Fetches recent queue domains, filters out known sources, queries the LLM,
	 * validates feed URLs, saves new suggestions, and notifies the editor.
	 *
	 * @since 2.0.0
	 *
	 * @return void
	 */
	public function run(): void {
		$recent_domains   = $this->get_recent_domains();
		$existing_domains = $this->get_existing_domains();

		// Remove domains that are already tracked or suggested.
		$new_domains = array_values( array_diff( $recent_domains, $existing_domains ) );

		if ( empty( $new_domains ) ) {
			$this->logger->info( 'Source Discovery: nenhum domínio novo encontrado neste ciclo.' );
			return;
		}

		$this->logger->info(
			'Source Discovery: avaliando domínios via LLM.',
			[ 'count' => count( $new_domains ), 'domains' => $new_domains ]
		);

		// Ask the LLM to evaluate the domains.
		$prompt   = $this->build_discovery_prompt( $new_domains );
		$llm      = jep_automacao()->llm();
		$response = $llm ? $llm->complete( $prompt ) : '';

		if ( empty( $response ) ) {
			$this->logger->warning( 'Source Discovery: LLM não retornou resposta.' );
			return;
		}

		// Parse the JSON array returned by the LLM.
		$suggestions = $this->parse_llm_response( $response );

		if ( empty( $suggestions ) ) {
			$this->logger->info( 'Source Discovery: LLM não identificou fontes qualificadas.' );
			return;
		}

		$new_count = 0;

		foreach ( $suggestions as $suggestion ) {
			$feed_url = $suggestion['url_feed'] ?? '';
			if ( empty( $feed_url ) ) {
				continue;
			}

			if ( ! $this->test_feed_url( $feed_url ) ) {
				$this->logger->info(
					'Source Discovery: feed URL inválida, ignorando.',
					[ 'url_feed' => $feed_url ]
				);
				continue;
			}

			$this->save_suggestion( $suggestion );
			$new_count++;
		}

		if ( $new_count > 0 ) {
			$this->logger->info(
				'Source Discovery: novas sugestões salvas.',
				[ 'count' => $new_count ]
			);

			// Notify the editorial team via Telegram.
			$message = sprintf(
				"🔍 *Novas fontes descobertas!*\n\n%d fonte(s) foram identificadas e aguardam revisão editorial.",
				$new_count
			);
			/** @var JEP_Telegram_Bot $telegram */
			$telegram = jep_automacao()->telegram();
			if ( $telegram ) {
				$telegram->send_message( $message );
			}
		} else {
			$this->logger->info( 'Source Discovery: nenhuma sugestão válida após teste de feeds.' );
		}
	}

	/**
	 * Retrieve domains from the 20 most-recent RSS queue items.
	 *
	 * @since 2.0.0
	 *
	 * @return string[] Unique domain strings, e.g. ["agenciaperiferica.com.br"].
	 */
	public function get_recent_domains(): array {
		global $wpdb;

		$table = esc_sql( $this->table_queue );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_col(
			"SELECT DISTINCT link FROM `{$table}` ORDER BY created_at DESC LIMIT 20"
		);
		// phpcs:enable

		$domains = [];
		foreach ( $rows as $link ) {
			$parsed = wp_parse_url( $link );
			$host   = $parsed['host'] ?? '';
			if ( ! empty( $host ) ) {
				// Strip leading "www." for consistency.
				$domains[] = preg_replace( '/^www\./i', '', strtolower( $host ) );
			}
		}

		return array_values( array_unique( $domains ) );
	}

	/**
	 * Retrieve domains already tracked in the feeds and suggested-feeds tables.
	 *
	 * @since 2.0.0
	 *
	 * @return string[] Flat array of known domain strings.
	 */
	public function get_existing_domains(): array {
		global $wpdb;

		$feeds_table     = esc_sql( $this->table_feeds );
		$suggested_table = esc_sql( $this->table_suggested );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$feed_urls      = $wpdb->get_col( "SELECT url FROM `{$feeds_table}`" );
		$suggested_urls = $wpdb->get_col( "SELECT url_feed FROM `{$suggested_table}`" );
		// phpcs:enable

		$all_urls = array_merge( $feed_urls, $suggested_urls );
		$domains  = [];

		foreach ( $all_urls as $url ) {
			$parsed = wp_parse_url( $url );
			$host   = $parsed['host'] ?? '';
			if ( ! empty( $host ) ) {
				$domains[] = preg_replace( '/^www\./i', '', strtolower( $host ) );
			}
		}

		return array_values( array_unique( $domains ) );
	}

	/**
	 * Build the LLM prompt for domain evaluation.
	 *
	 * @since 2.0.0
	 *
	 * @param string[] $domains Array of domain strings to evaluate.
	 * @return string Full prompt text.
	 */
	public function build_discovery_prompt( array $domains ): string {
		$domain_list = implode( "\n- ", $domains );

		return <<<PROMPT
Você é um especialista em jornalismo comunitário e periferias brasileiras.

Analise os domínios abaixo e identifique quais são veículos jornalísticos brasileiros relevantes para o tema de periferias, favelas, comunidades, direitos humanos ou movimentos sociais urbanos.

Domínios para avaliar:
- {$domain_list}

Para cada domínio qualificado, retorne um objeto JSON com os campos:
- nome: nome do veículo
- tipo: um de [agencia, jornal_regional, portal_comunitario, blog]
- regiao: uma de [nacional, SP, RJ, NE, N, CO, S]
- url_feed: URL completa do feed RSS ou Atom (ex: https://exemplo.com.br/feed)
- url_site: URL principal do site
- justificativa: motivo pelo qual o veículo é relevante (1-2 frases)

Retorne APENAS um array JSON válido. Se nenhum domínio se qualificar, retorne [].

Exemplo de resposta:
[
  {
    "nome": "Agência Periférica",
    "tipo": "agencia",
    "regiao": "SP",
    "url_feed": "https://agenciaperiferica.com.br/feed",
    "url_site": "https://agenciaperiferica.com.br",
    "justificativa": "Cobre exclusivamente pautas de periferia e movimentos sociais de São Paulo."
  }
]
PROMPT;
	}

	/**
	 * Test whether a feed URL returns a valid, non-empty feed.
	 *
	 * Uses WordPress's native fetch_feed() to honour caching and HTTP proxies.
	 *
	 * @since 2.0.0
	 *
	 * @param string $url Full URL of the RSS or Atom feed to test.
	 * @return bool True if the feed is valid and contains at least one item.
	 */
	public function test_feed_url( string $url ): bool {
		if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		if ( ! function_exists( 'fetch_feed' ) ) {
			require_once ABSPATH . WPINC . '/feed.php';
		}

		$feed = fetch_feed( $url );

		if ( is_wp_error( $feed ) ) {
			return false;
		}

		return $feed->get_item_quantity() > 0;
	}

	/**
	 * Save a feed suggestion to the suggested-feeds table with status "pending".
	 *
	 * @since 2.0.0
	 *
	 * @param array $data {
	 *     Associative array of suggestion fields.
	 *     @type string $nome          Display name of the outlet.
	 *     @type string $tipo          Outlet type (agencia|jornal_regional|portal_comunitario|blog).
	 *     @type string $regiao        Region code (nacional|SP|RJ|NE|N|CO|S).
	 *     @type string $url_feed      Feed URL.
	 *     @type string $url_site      Site homepage URL.
	 *     @type string $justificativa Short justification text.
	 * }
	 * @return int|false Inserted row ID, or false on failure.
	 */
	public function save_suggestion( array $data ) {
		global $wpdb;

		$table = esc_sql( $this->table_suggested );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->insert(
			$table,
			[
				'nome'          => sanitize_text_field( $data['nome'] ?? '' ),
				'tipo'          => sanitize_text_field( $data['tipo'] ?? '' ),
				'regiao'        => sanitize_text_field( $data['regiao'] ?? '' ),
				'url_feed'      => esc_url_raw( $data['url_feed'] ?? '' ),
				'url_site'      => esc_url_raw( $data['url_site'] ?? '' ),
				'justificativa' => sanitize_textarea_field( $data['justificativa'] ?? '' ),
				'status'        => 'pending',
				'created_at'    => current_time( 'mysql', true ),
			],
			[ '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
		);
		// phpcs:enable

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Retrieve all suggestions with status "pending", newest first.
	 *
	 * @since 2.0.0
	 *
	 * @return array[] Rows from the suggested-feeds table.
	 */
	public function get_pending_suggestions(): array {
		global $wpdb;

		$table = esc_sql( $this->table_suggested );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			"SELECT * FROM `{$table}` WHERE status = 'pending' ORDER BY created_at DESC",
			ARRAY_A
		);
		// phpcs:enable

		return $rows ?: [];
	}

	/**
	 * Return the total count of pending suggestions.
	 *
	 * @since 2.0.0
	 *
	 * @return int Number of pending suggestions.
	 */
	public function get_count_pending(): int {
		global $wpdb;

		$table = esc_sql( $this->table_suggested );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$count = $wpdb->get_var(
			"SELECT COUNT(*) FROM `{$table}` WHERE status = 'pending'"
		);
		// phpcs:enable

		return (int) $count;
	}

	/**
	 * Approve a pending suggestion and promote it to the active feeds table.
	 *
	 * @since 2.0.0
	 *
	 * @param int $id Row ID in the suggested-feeds table.
	 * @return int|WP_Error New feed ID from wp_jep_rss_feeds, or WP_Error.
	 */
	public function approve_suggestion( int $id ) {
		global $wpdb;

		$suggested_table = esc_sql( $this->table_suggested );
		$feeds_table     = esc_sql( $this->table_feeds );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$suggestion = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$suggested_table}` WHERE id = %d", $id ),
			ARRAY_A
		);
		// phpcs:enable

		if ( ! $suggestion ) {
			return new WP_Error( 'jep_suggestion_not_found', __( 'Sugestão não encontrada.', 'jep-automacao' ) );
		}

		// Insert into the active feeds table.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$inserted = $wpdb->insert(
			$feeds_table,
			[
				'nome'       => $suggestion['nome'],
				'url'        => $suggestion['url_feed'],
				'url_site'   => $suggestion['url_site'],
				'tipo'       => $suggestion['tipo'],
				'regiao'     => $suggestion['regiao'],
				'active'     => 1,
				'created_at' => current_time( 'mysql', true ),
			],
			[ '%s', '%s', '%s', '%s', '%s', '%d', '%s' ]
		);
		// phpcs:enable

		if ( ! $inserted ) {
			return new WP_Error( 'jep_feed_insert_failed', __( 'Falha ao inserir feed na tabela ativa.', 'jep-automacao' ) );
		}

		$new_feed_id = $wpdb->insert_id;

		// Mark the suggestion as approved.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->update(
			$suggested_table,
			[
				'status'      => 'approved',
				'resolved_at' => current_time( 'mysql', true ),
			],
			[ 'id' => $id ],
			[ '%s', '%s' ],
			[ '%d' ]
		);
		// phpcs:enable

		$this->logger->info(
			'Source Discovery: sugestão aprovada e adicionada às fontes ativas.',
			[ 'suggestion_id' => $id, 'new_feed_id' => $new_feed_id ]
		);

		return $new_feed_id;
	}

	/**
	 * Reject a pending suggestion.
	 *
	 * @since 2.0.0
	 *
	 * @param int $id Row ID in the suggested-feeds table.
	 * @return bool True on success, false on failure.
	 */
	public function reject_suggestion( int $id ): bool {
		global $wpdb;

		$table = esc_sql( $this->table_suggested );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$result = $wpdb->update(
			$table,
			[
				'status'      => 'rejected',
				'resolved_at' => current_time( 'mysql', true ),
			],
			[ 'id' => $id ],
			[ '%s', '%s' ],
			[ '%d' ]
		);
		// phpcs:enable

		if ( false !== $result ) {
			$this->logger->info( 'Source Discovery: sugestão rejeitada.', [ 'suggestion_id' => $id ] );
			return true;
		}

		return false;
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Parse the LLM's JSON response into an array of suggestion data.
	 *
	 * Handles responses that may contain markdown code fences around the JSON.
	 *
	 * @since 2.0.0
	 *
	 * @param string $response Raw text response from the LLM.
	 * @return array[] Array of suggestion associative arrays.
	 */
	private function parse_llm_response( string $response ): array {
		// Strip markdown code fences if present.
		$json_string = preg_replace( '/^```(?:json)?\s*/i', '', trim( $response ) );
		$json_string = preg_replace( '/\s*```$/', '', $json_string );

		// Find the first [ ... ] block.
		$start = strpos( $json_string, '[' );
		$end   = strrpos( $json_string, ']' );

		if ( false === $start || false === $end ) {
			$this->logger->warning( 'Source Discovery: não foi possível localizar JSON na resposta do LLM.' );
			return [];
		}

		$json_string = substr( $json_string, $start, $end - $start + 1 );
		$decoded     = json_decode( $json_string, true );

		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) ) {
			$this->logger->warning(
				'Source Discovery: resposta JSON inválida.',
				[ 'json_error' => json_last_error_msg() ]
			);
			return [];
		}

		return $decoded;
	}
}
