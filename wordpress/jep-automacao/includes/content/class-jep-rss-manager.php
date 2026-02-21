<?php
/**
 * JEP RSS Manager
 *
 * Manages RSS feed subscriptions, periodic fetching and the generation of
 * a daily digest post that aggregates the most relevant items published in
 * the previous 24 hours.
 *
 * @package JEP_Automacao_Editorial
 * @subpackage Content
 * @since 2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class JEP_RSS_Manager
 */
class JEP_RSS_Manager {

	/**
	 * Database table that stores feed subscriptions.
	 *
	 * @var string
	 */
	private $table_feeds;

	/**
	 * Database table that stores individual fetched items.
	 *
	 * @var string
	 */
	private $table_items;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->table_feeds = $wpdb->prefix . 'jep_rss_feeds';
		$this->table_items = $wpdb->prefix . 'jep_rss_items';
	}

	// -------------------------------------------------------------------------
	// Feed management
	// -------------------------------------------------------------------------

	/**
	 * Add a new feed subscription.
	 *
	 * @param array $data {
	 *     @type string $url       Feed URL (required).
	 *     @type string $name      Human-readable label.
	 *     @type string $territory Territory tag.
	 *     @type string $category  Content category.
	 *     @type int    $priority  Lower = higher priority (default 10).
	 * }
	 *
	 * @return int|false New row ID or false on failure.
	 */
	public function add_feed( $data ) {
		global $wpdb;

		if ( empty( $data['url'] ) ) {
			return false;
		}

		$insert = array(
			'url'        => esc_url_raw( $data['url'] ),
			'name'       => isset( $data['name'] )      ? sanitize_text_field( $data['name'] )     : '',
			'territory'  => isset( $data['territory'] ) ? sanitize_text_field( $data['territory'] ) : '',
			'category'   => isset( $data['category'] )  ? sanitize_text_field( $data['category'] )  : '',
			'priority'   => isset( $data['priority'] )  ? (int) $data['priority']                   : 10,
			'is_active'  => 1,
			'created_at' => current_time( 'mysql' ),
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert( $this->table_feeds, $insert );

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Return all feed subscriptions.
	 *
	 * @param bool $active_only When true, return only is_active=1 rows.
	 *
	 * @return array[]
	 */
	public function get_feeds( $active_only = false ) {
		global $wpdb;

		$where = $active_only ? 'WHERE is_active = 1' : '';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			"SELECT * FROM {$this->table_feeds} {$where} ORDER BY priority ASC, name ASC",
			ARRAY_A
		) ?: array();
	}

	/**
	 * Toggle a feed's active status.
	 *
	 * @param int  $id     Feed ID.
	 * @param bool $active Desired state.
	 *
	 * @return bool
	 */
	public function toggle_feed( $id, $active ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return false !== $wpdb->update(
			$this->table_feeds,
			array( 'is_active' => $active ? 1 : 0 ),
			array( 'id' => (int) $id )
		);
	}

	/**
	 * Delete a feed subscription and all its items.
	 *
	 * @param int $id Feed ID.
	 *
	 * @return bool
	 */
	public function delete_feed( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( $this->table_items, array( 'feed_id' => (int) $id ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return false !== $wpdb->delete( $this->table_feeds, array( 'id' => (int) $id ) );
	}

	// -------------------------------------------------------------------------
	// Fetching
	// -------------------------------------------------------------------------

	/**
	 * Fetch new items from all active feeds and persist them.
	 *
	 * @return int Total items inserted.
	 */
	public function fetch_all() {
		$feeds    = $this->get_feeds( true );
		$inserted = 0;

		foreach ( $feeds as $feed ) {
			$inserted += $this->fetch_feed( $feed );
		}

		JEP_Logger::info( 'rss', sprintf( 'RSS Manager: %d novos itens captados em %d feeds.', $inserted, count( $feeds ) ) );

		return $inserted;
	}

	/**
	 * Fetch items from a single feed and store new ones.
	 *
	 * @param array $feed Feed database row.
	 *
	 * @return int Items inserted.
	 */
	private function fetch_feed( $feed ) {
		global $wpdb;

		if ( ! class_exists( 'SimplePie' ) ) {
			require_once ABSPATH . WPINC . '/class-simplepie.php';
		}

		$rss = fetch_feed( $feed['url'] );

		if ( is_wp_error( $rss ) ) {
			JEP_Logger::warning( 'rss', sprintf( 'RSS Manager: erro ao buscar feed "%s" — %s', $feed['name'], $rss->get_error_message() ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->update(
				$this->table_feeds,
				array(
					'last_error'    => $rss->get_error_message(),
					'last_fetch_at' => current_time( 'mysql' ),
				),
				array( 'id' => (int) $feed['id'] )
			);

			return 0;
		}

		$items    = $rss->get_items( 0, 30 );
		$inserted = 0;

		foreach ( $items as $item ) {
			$guid = $item->get_id();
			$url  = $item->get_permalink();

			if ( empty( $guid ) && empty( $url ) ) {
				continue;
			}

			$lookup = $guid ?: $url;

			// Skip already-stored items.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$this->table_items} WHERE guid = %s LIMIT 1",
					$lookup
				)
			);

			if ( $exists ) {
				continue;
			}

			$pub_date = $item->get_date( 'Y-m-d H:i:s' );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$result = $wpdb->insert(
				$this->table_items,
				array(
					'feed_id'     => (int) $feed['id'],
					'guid'        => substr( $lookup, 0, 512 ),
					'title'       => sanitize_text_field( (string) $item->get_title() ),
					'excerpt'     => wp_trim_words( wp_strip_all_tags( (string) $item->get_description() ), 60 ),
					'url'         => esc_url_raw( (string) $url ),
					'image_url'   => esc_url_raw( (string) $this->extract_image( $item ) ),
					'territory'   => sanitize_text_field( $feed['territory'] ),
					'category'    => sanitize_text_field( $feed['category'] ),
					'pub_date'    => $pub_date ?: current_time( 'mysql' ),
					'status'      => 'new',
					'created_at'  => current_time( 'mysql' ),
				)
			);

			if ( $result ) {
				$inserted++;
			}
		}

		// Update last_fetch_at.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			$this->table_feeds,
			array(
				'last_fetch_at' => current_time( 'mysql' ),
				'last_error'    => '',
			),
			array( 'id' => (int) $feed['id'] )
		);

		return $inserted;
	}

	// -------------------------------------------------------------------------
	// Items
	// -------------------------------------------------------------------------

	/**
	 * Return RSS items fetched in the last N hours.
	 *
	 * @param int    $hours     Hours to look back (default 24).
	 * @param string $territory Filter by territory ('') for all.
	 * @param int    $limit     Maximum rows to return.
	 *
	 * @return array[]
	 */
	public function get_recent_items( $hours = 24, $territory = '', $limit = 50 ) {
		global $wpdb;

		$since  = gmdate( 'Y-m-d H:i:s', time() - $hours * HOUR_IN_SECONDS );
		$where  = array( 'pub_date >= %s' );
		$params = array( $since );

		if ( ! empty( $territory ) ) {
			$where[]  = 'territory = %s';
			$params[] = $territory;
		}

		$where_sql = implode( ' AND ', $where );
		$params[]  = (int) $limit;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_items} WHERE {$where_sql} ORDER BY pub_date DESC LIMIT %d",
				$params
			),
			ARRAY_A
		) ?: array();
	}

	/**
	 * Mark an RSS item as used (e.g. included in a digest post).
	 *
	 * @param int $id Item ID.
	 *
	 * @return bool
	 */
	public function mark_used( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return false !== $wpdb->update(
			$this->table_items,
			array( 'status' => 'used' ),
			array( 'id' => (int) $id )
		);
	}

	/**
	 * Purge items older than $days days.
	 *
	 * @param int $days Retention window (default 30).
	 *
	 * @return int Rows deleted.
	 */
	public function purge_old_items( $days = 30 ) {
		global $wpdb;

		$cutoff = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$this->table_items} WHERE created_at < %s",
				$cutoff
			)
		);
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Attempt to extract an image URL from a SimplePie item.
	 *
	 * @param SimplePie_Item $item Feed item.
	 *
	 * @return string Image URL or empty string.
	 */
	private function extract_image( $item ) {
		// Try enclosure first.
		$enclosure = $item->get_enclosure();
		if ( $enclosure ) {
			$link = $enclosure->get_link();
			if ( $link && preg_match( '/\.(jpg|jpeg|png|webp|gif)/i', $link ) ) {
				return $link;
			}
		}

		// Try og:image in description HTML.
		$html = (string) $item->get_description();
		if ( preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/', $html, $m ) ) {
			return $m[1];
		}

		return '';
	}
}
