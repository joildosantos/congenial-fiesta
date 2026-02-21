<?php
/**
 * JEP Topic Research
 *
 * Discovers trending topics from Google Trends RSS, Twitter/X search proxies
 * and configurable keyword lists, then imports the results into the cold-content
 * bank for editorial review.
 *
 * @package JEP_Automacao_Editorial
 * @subpackage Content
 * @since 2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class JEP_Topic_Research
 */
class JEP_Topic_Research {

	/**
	 * WordPress option key that stores the configured keyword groups.
	 *
	 * @var string
	 */
	const OPTION_KEYWORDS = 'jep_topic_research_keywords';

	/**
	 * WordPress option key that stores the list of RSS sources.
	 *
	 * @var string
	 */
	const OPTION_RSS_SOURCES = 'jep_topic_research_rss_sources';

	/**
	 * Maximum items to import per research run.
	 *
	 * @var int
	 */
	const MAX_ITEMS_PER_RUN = 20;

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Run the full topic-discovery pipeline and import results into the
	 * cold-content bank.
	 *
	 * @return int Number of pautas imported.
	 */
	public function run() {
		JEP_Logger::info( 'topic_research', 'Topic Research: iniciando descoberta de tópicos.' );

		$topics = array();
		$topics = array_merge( $topics, $this->discover_from_rss() );
		$topics = array_merge( $topics, $this->discover_from_google_trends() );

		// Deduplicate by title.
		$topics = $this->deduplicate( $topics );

		// Trim to max.
		$topics = array_slice( $topics, 0, self::MAX_ITEMS_PER_RUN );

		if ( empty( $topics ) ) {
			JEP_Logger::info( 'topic_research', 'Topic Research: nenhum tópico novo encontrado.' );
			return 0;
		}

		// Enrich topics with LLM summaries when available.
		$topics = $this->enrich_with_llm( $topics );

		// Import into cold-content bank.
		$cold    = new JEP_Cold_Content();
		$count   = $cold->import_from_research( $topics );

		JEP_Logger::info( 'topic_research', sprintf( 'Topic Research: %d tópicos importados.', $count ) );

		return $count;
	}

	/**
	 * Return all configured RSS source URLs.
	 *
	 * @return string[]
	 */
	public function get_rss_sources() {
		$raw = get_option( self::OPTION_RSS_SOURCES, '' );
		if ( empty( $raw ) ) {
			return array();
		}

		$lines = preg_split( '/\r?\n/', $raw );
		return array_values(
			array_filter( array_map( 'trim', $lines ) )
		);
	}

	/**
	 * Persist the list of RSS source URLs.
	 *
	 * @param string[] $sources Array of URLs.
	 *
	 * @return void
	 */
	public function save_rss_sources( $sources ) {
		$clean = array_filter( array_map( 'esc_url_raw', (array) $sources ) );
		update_option( self::OPTION_RSS_SOURCES, implode( "\n", $clean ) );
	}

	/**
	 * Return the configured keyword groups.
	 *
	 * @return array[] Array of arrays, each with 'territory' and 'keywords' keys.
	 */
	public function get_keyword_groups() {
		return (array) get_option( self::OPTION_KEYWORDS, array() );
	}

	/**
	 * Persist keyword groups.
	 *
	 * @param array[] $groups Array of keyword group arrays.
	 *
	 * @return void
	 */
	public function save_keyword_groups( $groups ) {
		update_option( self::OPTION_KEYWORDS, (array) $groups );
	}

	// -------------------------------------------------------------------------
	// Discovery sources
	// -------------------------------------------------------------------------

	/**
	 * Discover topics from the configured RSS feeds.
	 *
	 * @return array[] Array of topic arrays.
	 */
	private function discover_from_rss() {
		$sources = $this->get_rss_sources();
		$topics  = array();

		if ( empty( $sources ) ) {
			return $topics;
		}

		// Ensure SimplePie is available.
		if ( ! class_exists( 'SimplePie' ) ) {
			require_once ABSPATH . WPINC . '/class-simplepie.php';
		}

		foreach ( $sources as $url ) {
			$feed = fetch_feed( $url );

			if ( is_wp_error( $feed ) ) {
				JEP_Logger::warning( 'topic_research', sprintf( 'Topic Research: erro ao buscar RSS %s — %s', $url, $feed->get_error_message() ) );
				continue;
			}

			$items = $feed->get_items( 0, 10 );

			foreach ( $items as $item ) {
				$title = $item->get_title();
				if ( empty( $title ) ) {
					continue;
				}

				$topics[] = array(
					'title'      => wp_strip_all_tags( $title ),
					'summary'    => wp_trim_words( wp_strip_all_tags( (string) $item->get_description() ), 60 ),
					'source_url' => $item->get_permalink(),
					'territory'  => '',
					'priority'   => 10,
				);
			}
		}

		return $topics;
	}

	/**
	 * Discover trending topics from the Google Trends daily RSS for Brazil.
	 *
	 * @return array[] Array of topic arrays.
	 */
	private function discover_from_google_trends() {
		$url      = 'https://trends.google.com/trending/rss?geo=BR';
		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 15,
				'user-agent' => 'JEP-Automacao/2.0 (WordPress; +' . site_url() . ')',
			)
		);

		if ( is_wp_error( $response ) ) {
			JEP_Logger::warning( 'topic_research', 'Topic Research: Google Trends inacessível — ' . $response->get_error_message() );
			return array();
		}

		$body   = wp_remote_retrieve_body( $response );
		$topics = array();

		if ( preg_match_all( '/<title><!\[CDATA\[(.+?)\]\]><\/title>/s', $body, $matches ) ) {
			foreach ( $matches[1] as $title ) {
				$title = trim( $title );
				if ( empty( $title ) || 'Google Trends' === $title ) {
					continue;
				}

				$topics[] = array(
					'title'      => sanitize_text_field( $title ),
					'summary'    => '',
					'source_url' => '',
					'territory'  => 'BR',
					'priority'   => 5,
				);
			}
		}

		return $topics;
	}

	// -------------------------------------------------------------------------
	// Enrichment
	// -------------------------------------------------------------------------

	/**
	 * Use the LLM to enrich topics with structured summaries and territory hints.
	 * Skips silently when no LLM provider is active.
	 *
	 * @param array[] $topics Raw topic arrays.
	 *
	 * @return array[] Enriched topic arrays.
	 */
	private function enrich_with_llm( $topics ) {
		if ( ! class_exists( 'JEP_LLM_Manager' ) ) {
			return $topics;
		}

		try {
			$llm = new JEP_LLM_Manager();

			foreach ( $topics as &$topic ) {
				if ( ! empty( $topic['summary'] ) ) {
					continue;
				}

				$prompt = sprintf(
					"Você é um editor de jornal popular brasileiro. Dado o título de pauta: \"%s\"\n"
					. "Escreva em 2-3 frases: (1) contexto do tema, (2) impacto para leitores populares.\n"
					. "Responda apenas o resumo, sem títulos ou marcadores.",
					$topic['title']
				);

				$summary = $llm->complete( $prompt, '', 'topic_research', 200 );

				if ( ! empty( $summary ) ) {
					$topic['summary'] = sanitize_textarea_field( trim( $summary ) );
				}
			}
			unset( $topic );

		} catch ( Exception $e ) {
			JEP_Logger::warning( 'topic_research', 'Topic Research: enriquecimento LLM falhou — ' . $e->getMessage() );
		}

		return $topics;
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Remove duplicate topics by normalized title.
	 *
	 * @param array[] $topics List of topic arrays.
	 *
	 * @return array[] Deduplicated list.
	 */
	private function deduplicate( $topics ) {
		$seen   = array();
		$result = array();

		foreach ( $topics as $topic ) {
			$key = strtolower( preg_replace( '/\s+/', ' ', trim( $topic['title'] ) ) );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$result[]     = $topic;
		}

		return $result;
	}
}
