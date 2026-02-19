<?php
/**
 * JEP Cold Content Manager
 *
 * Manages the cold-content bank (banco de pautas frias): CRUD operations,
 * filtering, territory management and the automated processing pipeline that
 * rewrites a pauta, generates an image and sends it to Telegram for approval.
 *
 * @package JEP_Automacao_Editorial
 * @subpackage Content
 * @since 2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class JEP_Cold_Content
 */
class JEP_Cold_Content {

	/**
	 * Fully-qualified table name.
	 *
	 * @var string
	 */
	private $table;

	/**
	 * Constructor. Resolves the table name.
	 */
	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'jep_cold_content';
	}

	// -------------------------------------------------------------------------
	// CRUD
	// -------------------------------------------------------------------------

	/**
	 * Insert a new pauta fria into the bank.
	 *
	 * @param array $data {
	 *     @type string $title      Topic headline (required).
	 *     @type string $summary    Brief summary / research notes.
	 *     @type string $territory  Territory label (e.g. 'SP-Capital').
	 *     @type string $source_url Source URL for background reading.
	 *     @type int    $priority   Lower = higher priority (default 10).
	 *     @type string $status     Initial status (default 'pending').
	 * }
	 *
	 * @return int|false New row ID or false on failure.
	 */
	public function add( $data ) {
		global $wpdb;

		if ( empty( $data['title'] ) ) {
			return false;
		}

		$insert = array(
			'title'      => sanitize_text_field( $data['title'] ),
			'summary'    => isset( $data['summary'] )    ? sanitize_textarea_field( $data['summary'] )  : '',
			'territory'  => isset( $data['territory'] )  ? sanitize_text_field( $data['territory'] )    : '',
			'source_url' => isset( $data['source_url'] ) ? esc_url_raw( $data['source_url'] )           : '',
			'priority'   => isset( $data['priority'] )   ? (int) $data['priority']                      : 10,
			'status'     => isset( $data['status'] )     ? sanitize_text_field( $data['status'] )       : 'pending',
			'created_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert( $this->table, $insert );

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Update a pauta fria.
	 *
	 * @param int   $id   Row ID.
	 * @param array $data Columns to update (same keys as add()).
	 *
	 * @return bool
	 */
	public function update( $id, $data ) {
		global $wpdb;

		$allowed = array( 'title', 'summary', 'territory', 'source_url', 'priority', 'status', 'research_data' );
		$update  = array();

		foreach ( $allowed as $field ) {
			if ( ! array_key_exists( $field, $data ) ) {
				continue;
			}
			switch ( $field ) {
				case 'title':
					$update['title'] = sanitize_text_field( $data['title'] );
					break;
				case 'summary':
					$update['summary'] = sanitize_textarea_field( $data['summary'] );
					break;
				case 'territory':
					$update['territory'] = sanitize_text_field( $data['territory'] );
					break;
				case 'source_url':
					$update['source_url'] = esc_url_raw( $data['source_url'] );
					break;
				case 'priority':
					$update['priority'] = (int) $data['priority'];
					break;
				case 'status':
					$update['status'] = sanitize_text_field( $data['status'] );
					break;
				case 'research_data':
					$update['research_data'] = is_array( $data['research_data'] )
						? wp_json_encode( $data['research_data'] )
						: sanitize_textarea_field( $data['research_data'] );
					break;
			}
		}

		if ( empty( $update ) ) {
			return false;
		}

		$update['updated_at'] = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->update(
			$this->table,
			$update,
			array( 'id' => (int) $id )
		);

		return false !== $result;
	}

	/**
	 * Soft-delete a pauta fria by setting its status to 'discarded'.
	 *
	 * @param int $id Row ID.
	 *
	 * @return bool
	 */
	public function delete( $id ) {
		return $this->update( (int) $id, array( 'status' => 'discarded' ) );
	}

	/**
	 * Retrieve a single pauta fria by ID.
	 *
	 * @param int $id Row ID.
	 *
	 * @return array|null Row data or null when not found.
	 */
	public function get( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table} WHERE id = %d", (int) $id ),
			ARRAY_A
		);

		if ( $row && ! empty( $row['research_data'] ) ) {
			$row['research_data'] = json_decode( $row['research_data'], true );
		}

		return $row ?: null;
	}

	/**
	 * Return a paginated, filtered list of pautas frias.
	 *
	 * @param array $args {
	 *     @type string $status    Filter by status ('pending', 'processing', 'done', 'discarded').
	 *     @type string $territory Filter by territory label.
	 *     @type string $search    Full-text search against title and summary.
	 *     @type string $orderby   Column to sort by (default 'priority').
	 *     @type string $order     ASC or DESC (default 'ASC').
	 *     @type int    $per_page  Items per page (default 20).
	 *     @type int    $page      1-based page number (default 1).
	 * }
	 *
	 * @return array Array of row arrays.
	 */
	public function get_all( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status'    => '',
			'territory' => '',
			'search'    => '',
			'orderby'   => 'priority',
			'order'     => 'ASC',
			'per_page'  => 20,
			'page'      => 1,
		);

		$args = array_merge( $defaults, $args );

		$allowed_orderby = array( 'id', 'title', 'priority', 'territory', 'status', 'created_at', 'updated_at' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'priority';
		$order           = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';

		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}

		if ( ! empty( $args['territory'] ) ) {
			$where[]  = 'territory = %s';
			$params[] = $args['territory'];
		}

		if ( ! empty( $args['search'] ) ) {
			$where[]  = '(title LIKE %s OR summary LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$params[] = $like;
			$params[] = $like;
		}

		$per_page = max( 1, (int) $args['per_page'] );
		$page     = max( 1, (int) $args['page'] );
		$offset   = ( $page - 1 ) * $per_page;

		$where_sql = implode( ' AND ', $where );

		$sql = "SELECT * FROM {$this->table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";

		$params[] = $per_page;
		$params[] = $offset;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->prepare( $sql, $params ),
			ARRAY_A
		);

		if ( $rows ) {
			foreach ( $rows as &$row ) {
				if ( ! empty( $row['research_data'] ) ) {
					$row['research_data'] = json_decode( $row['research_data'], true );
				}
			}
			unset( $row );
		}

		return $rows ? $rows : array();
	}

	/**
	 * Count pautas frias matching the given filters.
	 *
	 * Accepts the same filter keys as get_all() (status, territory, search).
	 *
	 * @param array $args Filter arguments.
	 *
	 * @return int
	 */
	public function count( $args = array() ) {
		global $wpdb;

		$where  = array( '1=1' );
		$params = array();

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = $args['status'];
		}

		if ( ! empty( $args['territory'] ) ) {
			$where[]  = 'territory = %s';
			$params[] = $args['territory'];
		}

		if ( ! empty( $args['search'] ) ) {
			$where[]  = '(title LIKE %s OR summary LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$params[] = $like;
			$params[] = $like;
		}

		$where_sql = implode( ' AND ', $where );
		$sql       = "SELECT COUNT(*) FROM {$this->table} WHERE {$where_sql}";

		if ( ! empty( $params ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
			return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		return (int) $wpdb->get_var( $sql );
	}

	// -------------------------------------------------------------------------
	// Processing pipeline
	// -------------------------------------------------------------------------

	/**
	 * Pick the oldest pending pauta, rewrite it, generate an image and send to
	 * Telegram for editorial approval.
	 *
	 * @return bool True when an item was processed, false when the queue is empty
	 *              or when a critical error prevents processing.
	 */
	public function process_next() {
		global $wpdb;

		// Fetch oldest pending item.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$item = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table} WHERE status = %s ORDER BY priority ASC, created_at ASC LIMIT 1",
				'pending'
			),
			ARRAY_A
		);

		if ( ! $item ) {
			JEP_Logger::info( 'Cold Content: nenhuma pauta pendente na fila.', 'cold_content' );
			return false;
		}

		// Mark as processing immediately to prevent duplicate workers.
		$this->update( (int) $item['id'], array( 'status' => 'processing' ) );

		JEP_Logger::info(
			sprintf( 'Cold Content: processando pauta #%d "%s".', $item['id'], $item['title'] ),
			'cold_content'
		);

		try {
			// Decode research_data if stored as JSON.
			$research_data = array();
			if ( ! empty( $item['research_data'] ) ) {
				$research_data = is_array( $item['research_data'] )
					? $item['research_data']
					: json_decode( $item['research_data'], true );
			}

			// --- Step 1: LLM rewrite.
			$rewriter = new JEP_Content_Rewriter();
			$rewritten = $rewriter->rewrite_cold_content(
				$item['title'],
				$item['summary'],
				$item['territory'],
				is_array( $research_data ) ? $research_data : array()
			);

			// --- Step 2: Image acquisition.
			$image_url = $this->acquire_image( $item, $rewritten );

			// --- Step 3: Build the approval payload.
			$approval_data = array(
				'source_type'   => 'cold_content',
				'source_id'     => (int) $item['id'],
				'title_a'       => $rewritten['titulo_a'],
				'title_b'       => $rewritten['titulo_b'],
				'excerpt'       => $rewritten['excerpt'],
				'content_html'  => $rewritten['conteudo_html'],
				'hashtags'      => wp_json_encode( $rewritten['hashtags'] ),
				'categories'    => wp_json_encode( $rewritten['categorias_wp'] ),
				'image_url'     => $image_url,
				'territory'     => $item['territory'],
				'status'        => 'pending',
				'created_at'    => current_time( 'mysql' ),
			);

			// --- Step 4: Persist and dispatch to Telegram.
			if ( class_exists( 'JEP_Telegram_Approval' ) ) {
				$telegram  = new JEP_Telegram_Approval();
				$approval_id = $telegram->create_approval( $approval_data );

				if ( $approval_id ) {
					$telegram->send_for_approval( $approval_id );
					JEP_Logger::info(
						sprintf( 'Cold Content: aprovação #%d criada e enviada ao Telegram.', $approval_id ),
						'cold_content'
					);
				}
			}

			// --- Step 5: Mark as done.
			$this->update( (int) $item['id'], array( 'status' => 'done' ) );

			return true;

		} catch ( Exception $e ) {
			JEP_Logger::error(
				sprintf( 'Cold Content: erro ao processar pauta #%d — %s', $item['id'], $e->getMessage() ),
				'cold_content'
			);
			// Revert to pending so it can be retried.
			$this->update( (int) $item['id'], array( 'status' => 'pending' ) );
			return false;
		}
	}

	// -------------------------------------------------------------------------
	// Utility methods
	// -------------------------------------------------------------------------

	/**
	 * Return the distinct territory values present in the cold-content bank.
	 *
	 * @return string[]
	 */
	public function get_territories() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$results = $wpdb->get_col(
			"SELECT DISTINCT territory FROM {$this->table} WHERE territory != '' ORDER BY territory ASC"
		);

		return $results ? $results : array();
	}

	/**
	 * Bulk-insert pautas from automated topic research.
	 *
	 * Silently skips items that are missing a title or already exist (same title
	 * and territory combination with status 'pending').
	 *
	 * @param array $research_items Array of associative arrays, each with at minimum
	 *                              'title', 'summary', 'territory' and 'source_url'.
	 *
	 * @return int Number of rows successfully inserted.
	 */
	public function import_from_research( $research_items ) {
		if ( empty( $research_items ) || ! is_array( $research_items ) ) {
			return 0;
		}

		global $wpdb;
		$inserted = 0;

		foreach ( $research_items as $item ) {
			if ( empty( $item['title'] ) ) {
				continue;
			}

			// Deduplication: skip if an identical title + territory is already pending.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$this->table} WHERE title = %s AND territory = %s AND status = 'pending' LIMIT 1",
					sanitize_text_field( $item['title'] ),
					sanitize_text_field( isset( $item['territory'] ) ? $item['territory'] : '' )
				)
			);

			if ( $exists ) {
				continue;
			}

			$id = $this->add(
				array(
					'title'      => $item['title'],
					'summary'    => isset( $item['summary'] )    ? $item['summary']    : '',
					'territory'  => isset( $item['territory'] )  ? $item['territory']  : '',
					'source_url' => isset( $item['source_url'] ) ? $item['source_url'] : '',
					'priority'   => isset( $item['priority'] )   ? (int) $item['priority'] : 10,
					'status'     => 'pending',
				)
			);

			if ( $id ) {
				$inserted++;
			}
		}

		JEP_Logger::info(
			sprintf( 'Cold Content: %d pautas importadas da pesquisa automática.', $inserted ),
			'cold_content'
		);

		return $inserted;
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Try to obtain an image for a cold-content item.
	 *
	 * Attempts, in order:
	 *  1. Source URL og:image (if source_url is set).
	 *  2. AI image generation (when JEP_Image_Generator_AI is available and enabled).
	 *  3. GD placeholder image (always available).
	 *
	 * @param array $item      Cold-content DB row.
	 * @param array $rewritten Rewritten content data.
	 *
	 * @return string Image URL or local path.
	 */
	private function acquire_image( $item, $rewritten ) {
		// 1. Try og:image from source URL.
		if ( ! empty( $item['source_url'] ) ) {
			$og_image = $this->fetch_og_image( $item['source_url'] );
			if ( $og_image ) {
				return $og_image;
			}
		}

		// 2. AI-generated image.
		if ( class_exists( 'JEP_Image_Generator_AI' ) ) {
			try {
				$rewriter     = new JEP_Content_Rewriter();
				$image_prompt = $rewriter->generate_image_prompt(
					$rewritten['titulo_a'],
					$rewritten['excerpt'],
					$rewritten['categorias_wp']
				);
				$ai_image = JEP_Image_Generator_AI::generate( $image_prompt );
				if ( $ai_image ) {
					return $ai_image;
				}
			} catch ( Exception $e ) {
				JEP_Logger::warning( 'Cold Content: geração de imagem AI falhou — ' . $e->getMessage(), 'cold_content' );
			}
		}

		// 3. GD placeholder.
		if ( class_exists( 'JEP_Image_Generator_GD' ) ) {
			try {
				return JEP_Image_Generator_GD::create_placeholder( $rewritten['titulo_a'] );
			} catch ( Exception $e ) {
				JEP_Logger::warning( 'Cold Content: placeholder GD falhou — ' . $e->getMessage(), 'cold_content' );
			}
		}

		return '';
	}

	/**
	 * Attempt to extract the og:image meta tag from a URL.
	 *
	 * @param string $url Source article URL.
	 *
	 * @return string|false Image URL or false when not found.
	 */
	private function fetch_og_image( $url ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 15,
				'user-agent' => 'JEP-Automacao/2.0 (WordPress; +' . site_url() . ')',
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );

		if ( preg_match( '/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']+)["\'][^>]*>/i', $body, $m ) ) {
			return esc_url_raw( $m[1] );
		}

		// Alternative attribute order.
		if ( preg_match( '/<meta[^>]+content=["\']([^"\']+)["\'][^>]+property=["\']og:image["\'][^>]*>/i', $body, $m ) ) {
			return esc_url_raw( $m[1] );
		}

		return false;
	}
}
