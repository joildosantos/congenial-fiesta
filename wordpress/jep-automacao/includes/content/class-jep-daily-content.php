<?php
/**
 * JEP Daily Content
 *
 * Orchestrates the automated daily content pipeline: selects the best RSS
 * items of the last 24 hours, rewrites them with the LLM pool, generates
 * cover images and dispatches A/B approval cards to Telegram. One summary
 * digest post is also published to WordPress directly.
 *
 * @package JEP_Automacao_Editorial
 * @subpackage Content
 * @since 2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class JEP_Daily_Content
 */
class JEP_Daily_Content {

	/**
	 * Option key that stores the pipeline configuration.
	 *
	 * @var string
	 */
	const OPTION_CONFIG = 'jep_daily_content_config';

	/**
	 * Maximum number of individual articles to rewrite per daily run.
	 *
	 * @var int
	 */
	const MAX_REWRITES_PER_RUN = 5;

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Execute the full daily content pipeline.
	 *
	 * Steps:
	 *  1. Fetch recent RSS items.
	 *  2. Score and select the best candidates.
	 *  3. Rewrite each candidate with the LLM.
	 *  4. Acquire a cover image for each rewritten piece.
	 *  5. Send A/B approval cards to Telegram.
	 *  6. Create a digest WordPress post.
	 *
	 * @return array {
	 *     @type int $rewritten   Articles sent to Telegram for approval.
	 *     @type int $digest_id   WordPress post ID of the digest (0 on failure).
	 * }
	 */
	public function run() {
		JEP_Logger::info( 'Daily Content: iniciando pipeline diário.', 'daily_content' );

		$config    = $this->get_config();
		$territory = isset( $config['territory'] ) ? $config['territory'] : '';

		// Step 1: Collect recent items.
		$rss   = new JEP_RSS_Manager();
		$items = $rss->get_recent_items( 24, $territory, 30 );

		if ( empty( $items ) ) {
			JEP_Logger::info( 'Daily Content: nenhum item RSS nas últimas 24h.', 'daily_content' );
			return array( 'rewritten' => 0, 'digest_id' => 0 );
		}

		// Step 2: Select best candidates.
		$candidates = $this->select_candidates( $items, self::MAX_REWRITES_PER_RUN );

		// Step 3-5: Rewrite and dispatch to Telegram.
		$rewritten = 0;
		$rewriter  = new JEP_Content_Rewriter();

		foreach ( $candidates as $item ) {
			try {
				$result = $rewriter->rewrite_news_item(
					$item['title'],
					$item['excerpt'],
					$item['territory'],
					$item['url']
				);

				$image_url = $this->acquire_image( $item, $result );

				$approval_data = array(
					'source_type'  => 'daily_content',
					'source_id'    => (int) $item['id'],
					'title_a'      => $result['titulo_a'],
					'title_b'      => $result['titulo_b'],
					'excerpt'      => $result['excerpt'],
					'content_html' => $result['conteudo_html'],
					'hashtags'     => wp_json_encode( $result['hashtags'] ),
					'categories'   => wp_json_encode( $result['categorias_wp'] ),
					'image_url'    => $image_url,
					'territory'    => $item['territory'],
					'status'       => 'pending',
					'created_at'   => current_time( 'mysql' ),
				);

				if ( class_exists( 'JEP_Telegram_Approval' ) ) {
					$telegram    = new JEP_Telegram_Approval();
					$approval_id = $telegram->create_approval( $approval_data );
					if ( $approval_id ) {
						$telegram->send_for_approval( $approval_id );
						$rss->mark_used( (int) $item['id'] );
						$rewritten++;
					}
				}
			} catch ( Exception $e ) {
				JEP_Logger::warning(
					sprintf( 'Daily Content: erro ao reescrever item #%d — %s', $item['id'], $e->getMessage() ),
					'daily_content'
				);
			}
		}

		// Step 6: Create digest post.
		$digest_id = $this->create_digest_post( $items, $config );

		JEP_Logger::info(
			sprintf(
				'Daily Content: %d artigos enviados ao Telegram, digest post #%d criado.',
				$rewritten,
				$digest_id
			),
			'daily_content'
		);

		return array( 'rewritten' => $rewritten, 'digest_id' => $digest_id );
	}

	/**
	 * Return the daily pipeline configuration.
	 *
	 * @return array
	 */
	public function get_config() {
		$defaults = array(
			'territory'           => '',
			'digest_category_id'  => 0,
			'digest_post_status'  => 'draft',
			'digest_author_id'    => 1,
			'digest_title_format' => 'Resumo do dia: {date}',
		);

		return array_merge( $defaults, (array) get_option( self::OPTION_CONFIG, array() ) );
	}

	/**
	 * Persist the daily pipeline configuration.
	 *
	 * @param array $config Configuration array.
	 *
	 * @return void
	 */
	public function save_config( $config ) {
		$allowed = array(
			'territory',
			'digest_category_id',
			'digest_post_status',
			'digest_author_id',
			'digest_title_format',
		);

		$clean = array();
		foreach ( $allowed as $key ) {
			if ( array_key_exists( $key, $config ) ) {
				$clean[ $key ] = in_array( $key, array( 'digest_category_id', 'digest_author_id' ), true )
					? (int) $config[ $key ]
					: sanitize_text_field( $config[ $key ] );
			}
		}

		update_option( self::OPTION_CONFIG, $clean );
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Score each item and return the top $limit candidates.
	 *
	 * Scoring criteria (higher = better):
	 *  - Has excerpt: +2
	 *  - Has image: +1
	 *  - Has source URL: +1
	 *  - Published in last 6 hours: +3; last 12 hours: +2; last 24 hours: +1
	 *
	 * @param array[] $items RSS item rows.
	 * @param int     $limit Maximum candidates to return.
	 *
	 * @return array[]
	 */
	private function select_candidates( $items, $limit ) {
		$now = time();

		foreach ( $items as &$item ) {
			$score = 0;

			if ( ! empty( $item['excerpt'] ) )   { $score += 2; }
			if ( ! empty( $item['image_url'] ) )  { $score += 1; }
			if ( ! empty( $item['url'] ) )        { $score += 1; }

			$pub = strtotime( $item['pub_date'] );
			if ( $pub && ( $now - $pub ) <= 6 * HOUR_IN_SECONDS )  { $score += 3; }
			elseif ( $pub && ( $now - $pub ) <= 12 * HOUR_IN_SECONDS ) { $score += 2; }
			else { $score += 1; }

			$item['_score'] = $score;
		}
		unset( $item );

		usort(
			$items,
			function ( $a, $b ) {
				return $b['_score'] - $a['_score'];
			}
		);

		return array_slice( $items, 0, $limit );
	}

	/**
	 * Attempt to obtain a cover image for a rewritten news item.
	 *
	 * @param array $item    RSS item row.
	 * @param array $rewritten Rewriter output.
	 *
	 * @return string Image URL or empty string.
	 */
	private function acquire_image( $item, $rewritten ) {
		// 1. Use image from the RSS item itself.
		if ( ! empty( $item['image_url'] ) ) {
			return $item['image_url'];
		}

		// 2. AI image generation.
		if ( class_exists( 'JEP_Image_AI' ) ) {
			try {
				if ( class_exists( 'JEP_Content_Rewriter' ) ) {
					$rewriter     = new JEP_Content_Rewriter();
					$image_prompt = $rewriter->generate_image_prompt(
						$rewritten['titulo_a'],
						$rewritten['excerpt'],
						$rewritten['categorias_wp']
					);
					$ai_image = JEP_Image_AI::generate( $image_prompt );
					if ( $ai_image ) {
						return $ai_image;
					}
				}
			} catch ( Exception $e ) {
				JEP_Logger::warning( 'Daily Content: geração de imagem AI falhou — ' . $e->getMessage(), 'daily_content' );
			}
		}

		// 3. GD placeholder.
		if ( class_exists( 'JEP_Image_GD' ) ) {
			try {
				return JEP_Image_GD::create_placeholder( $rewritten['titulo_a'] );
			} catch ( Exception $e ) {
				JEP_Logger::warning( 'Daily Content: placeholder GD falhou — ' . $e->getMessage(), 'daily_content' );
			}
		}

		return '';
	}

	/**
	 * Create a WordPress digest post listing the day's most relevant items.
	 *
	 * @param array[] $items  All RSS items collected today (not just candidates).
	 * @param array   $config Pipeline configuration.
	 *
	 * @return int WordPress post ID or 0 on failure.
	 */
	private function create_digest_post( $items, $config ) {
		$date  = wp_date( 'd/m/Y' );
		$title = str_replace( '{date}', $date, $config['digest_title_format'] );

		$html  = '<p>Confira as principais notícias das últimas 24 horas selecionadas automaticamente pela redação digital.</p>';
		$html .= '<ul>';

		foreach ( array_slice( $items, 0, 15 ) as $item ) {
			$link  = ! empty( $item['url'] ) ? esc_url( $item['url'] ) : '';
			$label = esc_html( $item['title'] );

			$html .= $link
				? sprintf( '<li><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></li>', $link, $label )
				: sprintf( '<li>%s</li>', $label );
		}

		$html .= '</ul>';

		$post_data = array(
			'post_title'   => sanitize_text_field( $title ),
			'post_content' => wp_kses_post( $html ),
			'post_status'  => in_array( $config['digest_post_status'], array( 'draft', 'publish', 'pending' ), true )
				? $config['digest_post_status']
				: 'draft',
			'post_author'  => (int) $config['digest_author_id'],
			'post_type'    => 'post',
		);

		if ( ! empty( $config['digest_category_id'] ) ) {
			$post_data['post_category'] = array( (int) $config['digest_category_id'] );
		}

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			JEP_Logger::error(
				'Daily Content: falha ao criar post digest — ' . $post_id->get_error_message(),
				'daily_content'
			);
			return 0;
		}

		return (int) $post_id;
	}
}
