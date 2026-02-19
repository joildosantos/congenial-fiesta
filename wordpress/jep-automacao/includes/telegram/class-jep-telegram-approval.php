<?php
/**
 * JEP Telegram Approval - Manages editorial approval workflow via Telegram.
 *
 * @package JEP_Automacao_Editorial
 * @subpackage Telegram
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class JEP_Telegram_Approval
 *
 * Handles the full lifecycle of a content approval record: creation, sending to the
 * editor via Telegram, processing approve/reject callbacks, and publishing approved
 * content as a WordPress post with social card generation.
 *
 * @since 2.0.0
 */
class JEP_Telegram_Approval {

	/**
	 * Name of the custom database table that stores pending approvals.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	private $table = 'wp_jep_pending_approvals';

	/**
	 * Telegram Bot API wrapper instance.
	 *
	 * @since 2.0.0
	 * @var JEP_Telegram_Bot
	 */
	private $bot;

	/**
	 * Constructor. Initialises the bot instance and resolves the correct table name.
	 *
	 * @since 2.0.0
	 */
	public function __construct() {
		global $wpdb;

		$this->bot   = new JEP_Telegram_Bot();
		$this->table = $wpdb->prefix . 'jep_pending_approvals';
	}

	/**
	 * Sends a pending approval record to the editor chat for review.
	 *
	 * Fetches the approval row from the database, builds the Telegram message text and
	 * keyboard, and dispatches either a photo (when an image URL exists) or a plain text
	 * message. Stores the resulting Telegram message ID back in the database so that
	 * subsequent edits can target the correct message.
	 *
	 * @since 2.0.0
	 *
	 * @param int $approval_id The primary key of the pending approval record.
	 *
	 * @return bool True on success, false if the record could not be found.
	 */
	public function send_for_approval( $approval_id ) {
		global $wpdb;

		$approval = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$this->table}` WHERE id = %d", $approval_id ),
			ARRAY_A
		);

		if ( empty( $approval ) ) {
			jep_automacao()->logger()->error(
				sprintf( '[TelegramApproval] send_for_approval: record %d not found.', $approval_id )
			);
			return false;
		}

		$excerpt = ! empty( $approval['excerpt'] )
			? wp_trim_words( $approval['excerpt'], 30, '…' )
			: '';

		$source_line = ! empty( $approval['source_url'] )
			? "\n\n🔗 Fonte: " . esc_url( $approval['source_url'] )
			: '';

		$message_text = sprintf(
			"📰 *%s*\n\n%s%s\n\nEscolha o título:",
			$this->escape_markdown( $approval['title_a'] ),
			$excerpt,
			$source_line
		);

		$keyboard = $this->bot->build_approval_keyboard(
			$approval_id,
			$approval['title_a'],
			$approval['title_b']
		);

		try {
			if ( ! empty( $approval['image_url'] ) ) {
				$result = $this->bot->send_photo(
					jep_automacao()->settings()->get_telegram_editor_chat_id(),
					$approval['image_url'],
					$message_text,
					'Markdown',
					$keyboard
				);
			} else {
				$result = $this->bot->send_to_editor( $message_text, $keyboard );
			}

			$telegram_message_id = isset( $result['message_id'] ) ? (int) $result['message_id'] : 0;

			if ( $telegram_message_id ) {
				$wpdb->update(
					$this->table,
					[ 'telegram_message_id' => $telegram_message_id ],
					[ 'id' => $approval_id ],
					[ '%d' ],
					[ '%d' ]
				);
			}

			jep_automacao()->logger()->info(
				sprintf(
					'[TelegramApproval] Sent approval %d to editor (msg_id: %d).',
					$approval_id,
					$telegram_message_id
				)
			);

			return true;

		} catch ( Exception $e ) {
			jep_automacao()->logger()->error(
				sprintf(
					'[TelegramApproval] Failed to send approval %d: %s',
					$approval_id,
					$e->getMessage()
				)
			);
			return false;
		}
	}

	/**
	 * Inserts a new pending approval record into the database.
	 *
	 * @since 2.0.0
	 *
	 * @param array $data {
	 *     Approval data to store.
	 *
	 *     @type string $title_a      Required. First title variant.
	 *     @type string $title_b      Required. Second title variant.
	 *     @type string $excerpt      Short summary / excerpt.
	 *     @type string $content_html Full HTML content for the post body.
	 *     @type string $source_url   Original source URL.
	 *     @type string $image_url    Featured image URL.
	 *     @type string $categories   Comma-separated category IDs or names.
	 *     @type string $tags         Comma-separated tag slugs.
	 *     @type string $status       Row status. Default 'pending'.
	 * }
	 *
	 * @return int|false The ID of the newly inserted row, or false on failure.
	 */
	public function create_approval( $data ) {
		global $wpdb;

		$defaults = [
			'title_a'      => '',
			'title_b'      => '',
			'excerpt'      => '',
			'content_html' => '',
			'source_url'   => '',
			'image_url'    => '',
			'categories'   => '',
			'tags'         => '',
			'status'       => 'pending',
			'created_at'   => current_time( 'mysql' ),
		];

		$insert_data = wp_parse_args( $data, $defaults );

		$result = $wpdb->insert(
			$this->table,
			$insert_data,
			[
				'%s', // title_a
				'%s', // title_b
				'%s', // excerpt
				'%s', // content_html
				'%s', // source_url
				'%s', // image_url
				'%s', // categories
				'%s', // tags
				'%s', // status
				'%s', // created_at
			]
		);

		if ( false === $result ) {
			jep_automacao()->logger()->error(
				sprintf( '[TelegramApproval] create_approval DB insert failed: %s', $wpdb->last_error )
			);
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Routes an incoming Telegram callback_query to the appropriate approve or reject handler.
	 *
	 * Expected callback_data patterns:
	 * - approve_{id}_a  → approve variant A
	 * - approve_{id}_b  → approve variant B
	 * - reject_{id}     → reject the approval
	 *
	 * @since 2.0.0
	 *
	 * @param array $callback_query The callback_query object from the Telegram update payload.
	 *
	 * @return void
	 */
	public function handle_callback( $callback_query ) {
		$callback_id   = $callback_query['id'];
		$callback_data = isset( $callback_query['data'] ) ? $callback_query['data'] : '';

		if ( empty( $callback_data ) ) {
			$this->bot->answer_callback_query( $callback_id, 'Dados inválidos.', true );
			return;
		}

		// Pattern: approve_{id}_{variant}
		if ( preg_match( '/^approve_(\d+)_([ab])$/', $callback_data, $matches ) ) {
			$approval_id = (int) $matches[1];
			$variant     = $matches[2];

			$this->approve( $approval_id, $variant );
			$this->bot->answer_callback_query( $callback_id, '✅ Conteúdo aprovado! Publicando…' );
			return;
		}

		// Pattern: reject_{id}
		if ( preg_match( '/^reject_(\d+)$/', $callback_data, $matches ) ) {
			$approval_id = (int) $matches[1];

			$this->reject( $approval_id );
			$this->bot->answer_callback_query( $callback_id, '❌ Conteúdo rejeitado.' );
			return;
		}

		// Unrecognised callback — delegate to publisher for edit callbacks.
		$publisher = new JEP_Telegram_Publisher();
		$publisher->handle_edit_callback( $callback_query );
	}

	/**
	 * Approves a pending approval record and triggers publication.
	 *
	 * Selects either title_a or title_b as the approved title, updates the database
	 * row to 'approved', triggers publish_approved(), and edits the original Telegram
	 * message to show a confirmation banner.
	 *
	 * @since 2.0.0
	 *
	 * @param int    $approval_id The primary key of the pending approval record.
	 * @param string $variant     Which title variant was selected: 'a' or 'b'. Default 'a'.
	 *
	 * @return bool True on success, false if the record was not found or already resolved.
	 */
	public function approve( $approval_id, $variant = 'a' ) {
		global $wpdb;

		$approval = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$this->table}` WHERE id = %d", $approval_id ),
			ARRAY_A
		);

		if ( empty( $approval ) ) {
			jep_automacao()->logger()->error(
				sprintf( '[TelegramApproval] approve: record %d not found.', $approval_id )
			);
			return false;
		}

		if ( 'pending' !== $approval['status'] ) {
			jep_automacao()->logger()->warning(
				sprintf(
					'[TelegramApproval] approve: record %d already has status "%s".',
					$approval_id,
					$approval['status']
				)
			);
			return false;
		}

		$approved_title = ( 'b' === strtolower( $variant ) ) ? $approval['title_b'] : $approval['title_a'];

		$wpdb->update(
			$this->table,
			[
				'status'         => 'approved',
				'approved_title' => $approved_title,
				'approved_at'    => current_time( 'mysql' ),
			],
			[ 'id' => $approval_id ],
			[ '%s', '%s', '%s' ],
			[ '%d' ]
		);

		jep_automacao()->logger()->info(
			sprintf(
				'[TelegramApproval] Approval %d approved with variant "%s": %s',
				$approval_id,
				$variant,
				$approved_title
			)
		);

		// Edit the Telegram message to show the confirmation banner.
		if ( ! empty( $approval['telegram_message_id'] ) ) {
			$confirmation_text = sprintf(
				"✅ *Aprovado!*\n\n*Título:* %s\n\n_Publicando…_",
				$this->escape_markdown( $approved_title )
			);

			try {
				$this->bot->edit_message_text(
					jep_automacao()->settings()->get_telegram_editor_chat_id(),
					(int) $approval['telegram_message_id'],
					$confirmation_text,
					'Markdown'
				);
			} catch ( Exception $e ) {
				// Non-fatal: log but continue with publication.
				jep_automacao()->logger()->warning(
					sprintf(
						'[TelegramApproval] Could not edit message for approval %d: %s',
						$approval_id,
						$e->getMessage()
					)
				);
			}
		}

		return $this->publish_approved( $approval_id );
	}

	/**
	 * Rejects a pending approval record and updates the Telegram message accordingly.
	 *
	 * @since 2.0.0
	 *
	 * @param int $approval_id The primary key of the pending approval record.
	 *
	 * @return bool True on success, false if the record was not found.
	 */
	public function reject( $approval_id ) {
		global $wpdb;

		$approval = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$this->table}` WHERE id = %d", $approval_id ),
			ARRAY_A
		);

		if ( empty( $approval ) ) {
			jep_automacao()->logger()->error(
				sprintf( '[TelegramApproval] reject: record %d not found.', $approval_id )
			);
			return false;
		}

		$wpdb->update(
			$this->table,
			[
				'status'      => 'rejected',
				'resolved_at' => current_time( 'mysql' ),
			],
			[ 'id' => $approval_id ],
			[ '%s', '%s' ],
			[ '%d' ]
		);

		jep_automacao()->logger()->info(
			sprintf( '[TelegramApproval] Approval %d rejected.', $approval_id )
		);

		// Edit the Telegram message to show the rejection banner.
		if ( ! empty( $approval['telegram_message_id'] ) ) {
			$rejection_text = sprintf(
				"❌ *Rejeitado*\n\n*Título A:* %s\n*Título B:* %s\n\n_Nenhuma ação foi tomada._",
				$this->escape_markdown( $approval['title_a'] ),
				$this->escape_markdown( $approval['title_b'] )
			);

			try {
				$this->bot->edit_message_text(
					jep_automacao()->settings()->get_telegram_editor_chat_id(),
					(int) $approval['telegram_message_id'],
					$rejection_text,
					'Markdown'
				);
			} catch ( Exception $e ) {
				jep_automacao()->logger()->warning(
					sprintf(
						'[TelegramApproval] Could not edit rejection message for approval %d: %s',
						$approval_id,
						$e->getMessage()
					)
				);
			}
		}

		do_action( 'jep_approval_rejected', $approval_id, $approval );

		return true;
	}

	/**
	 * Publishes an approved approval record as a WordPress post.
	 *
	 * Creates the post, assigns taxonomy terms, sets the featured image, generates
	 * a social card via JEP_Image_Generator_GD, broadcasts the card to the Telegram
	 * channel, and optionally triggers Instagram publication. Updates the approval
	 * record with the resulting post ID and resolved timestamp.
	 *
	 * @since 2.0.0
	 *
	 * @param int $approval_id The primary key of the approved record.
	 *
	 * @return int|false The ID of the newly created WordPress post, or false on failure.
	 */
	public function publish_approved( $approval_id ) {
		global $wpdb;

		$approval = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM `{$this->table}` WHERE id = %d", $approval_id ),
			ARRAY_A
		);

		if ( empty( $approval ) ) {
			jep_automacao()->logger()->error(
				sprintf( '[TelegramApproval] publish_approved: record %d not found.', $approval_id )
			);
			return false;
		}

		$approved_title = ! empty( $approval['approved_title'] ) ? $approval['approved_title'] : $approval['title_a'];
		$content_html   = ! empty( $approval['content_html'] ) ? $approval['content_html'] : '';
		$excerpt        = ! empty( $approval['excerpt'] ) ? $approval['excerpt'] : '';

		// ------------------------------------------------------------------
		// 1. Create the WordPress post.
		// ------------------------------------------------------------------
		$post_data = [
			'post_title'   => wp_strip_all_tags( $approved_title ),
			'post_content' => $content_html,
			'post_excerpt' => wp_strip_all_tags( $excerpt ),
			'post_status'  => 'publish',
			'post_type'    => 'post',
			'post_author'  => (int) jep_automacao()->settings()->get( 'default_author_id', get_current_user_id() ),
		];

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			jep_automacao()->logger()->error(
				sprintf(
					'[TelegramApproval] publish_approved: wp_insert_post failed for approval %d: %s',
					$approval_id,
					$post_id->get_error_message()
				)
			);
			return false;
		}

		// ------------------------------------------------------------------
		// 2. Assign categories.
		// ------------------------------------------------------------------
		if ( ! empty( $approval['categories'] ) ) {
			$category_ids = array_map( 'intval', array_filter( explode( ',', $approval['categories'] ) ) );
			if ( ! empty( $category_ids ) ) {
				wp_set_post_categories( $post_id, $category_ids );
			}
		}

		// ------------------------------------------------------------------
		// 3. Assign tags.
		// ------------------------------------------------------------------
		$tag_slugs = [];
		if ( ! empty( $approval['tags'] ) ) {
			$raw_tags  = array_map( 'trim', explode( ',', $approval['tags'] ) );
			$tag_slugs = array_filter( $raw_tags );
			wp_set_post_tags( $post_id, $tag_slugs, false );
		}

		// ------------------------------------------------------------------
		// 4. Set the featured image from image_url.
		// ------------------------------------------------------------------
		$attachment_id = 0;
		if ( ! empty( $approval['image_url'] ) ) {
			$attachment_id = $this->sideload_image( $approval['image_url'], $post_id, $approved_title );
			if ( $attachment_id && ! is_wp_error( $attachment_id ) ) {
				set_post_thumbnail( $post_id, $attachment_id );
			}
		}

		// ------------------------------------------------------------------
		// 5. Generate social card via JEP_Image_Generator_GD.
		// ------------------------------------------------------------------
		$social_card_url = '';
		if ( class_exists( 'JEP_Image_Generator_GD' ) ) {
			try {
				$image_generator = new JEP_Image_Generator_GD();
				$card_path       = $image_generator->generate_card( $post_id, $approved_title );

				if ( $card_path && file_exists( $card_path ) ) {
					$upload_dir      = wp_upload_dir();
					$social_card_url = str_replace(
						$upload_dir['basedir'],
						$upload_dir['baseurl'],
						$card_path
					);
				}
			} catch ( Exception $e ) {
				jep_automacao()->logger()->warning(
					sprintf(
						'[TelegramApproval] Social card generation failed for post %d: %s',
						$post_id,
						$e->getMessage()
					)
				);
			}
		}

		// ------------------------------------------------------------------
		// 6. Broadcast to Telegram channel.
		// ------------------------------------------------------------------
		$permalink    = get_permalink( $post_id );
		$excerpt_text = wp_trim_words( $excerpt ?: wp_strip_all_tags( $content_html ), 25, '…' );

		$tag_line = '';
		if ( ! empty( $tag_slugs ) ) {
			$hashtags = array_map(
				function ( $tag ) {
					return '#' . preg_replace( '/\s+/', '_', strtolower( $tag ) );
				},
				$tag_slugs
			);
			$tag_line = "\n\n" . implode( ' ', $hashtags );
		}

		$channel_caption = sprintf(
			"✅ *%s*\n\n%s\n%s%s",
			$this->escape_markdown( $approved_title ),
			$excerpt_text,
			$permalink,
			$tag_line
		);

		try {
			if ( ! empty( $social_card_url ) ) {
				$this->bot->send_photo_to_channel( $social_card_url, $channel_caption );
			} else {
				$this->bot->send_to_channel( $channel_caption );
			}
		} catch ( Exception $e ) {
			jep_automacao()->logger()->warning(
				sprintf(
					'[TelegramApproval] Failed to send post %d to channel: %s',
					$post_id,
					$e->getMessage()
				)
			);
		}

		// ------------------------------------------------------------------
		// 7. Trigger Instagram publication if enabled.
		// ------------------------------------------------------------------
		$instagram_enabled = (bool) jep_automacao()->settings()->get( 'instagram_enabled', false );
		if ( $instagram_enabled && class_exists( 'JEP_Instagram_Publisher' ) ) {
			try {
				JEP_Instagram_Publisher::prepare( $post_id, $approval_id );
			} catch ( Exception $e ) {
				jep_automacao()->logger()->warning(
					sprintf(
						'[TelegramApproval] Instagram prepare failed for post %d: %s',
						$post_id,
						$e->getMessage()
					)
				);
			}
		}

		// ------------------------------------------------------------------
		// 8. Update the approval record as resolved.
		// ------------------------------------------------------------------
		$wpdb->update(
			$this->table,
			[
				'post_id'     => $post_id,
				'status'      => 'published',
				'resolved_at' => current_time( 'mysql' ),
			],
			[ 'id' => $approval_id ],
			[ '%d', '%s', '%s' ],
			[ '%d' ]
		);

		jep_automacao()->logger()->info(
			sprintf(
				'[TelegramApproval] Approval %d published as post ID %d: "%s".',
				$approval_id,
				$post_id,
				$approved_title
			)
		);

		do_action( 'jep_approval_published', $approval_id, $post_id, $approval );

		return $post_id;
	}

	/**
	 * Retrieves a list of pending approval records ordered by creation date.
	 *
	 * @since 2.0.0
	 *
	 * @param int $limit Maximum number of records to return. Default 20.
	 *
	 * @return array Array of associative arrays, each representing an approval row.
	 */
	public function get_pending( $limit = 20 ) {
		global $wpdb;

		$limit = absint( $limit );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM `{$this->table}` WHERE status = 'pending' ORDER BY created_at DESC LIMIT %d",
				$limit
			),
			ARRAY_A
		);
	}

	/**
	 * Finds an approval record by its associated Telegram message ID.
	 *
	 * @since 2.0.0
	 *
	 * @param int $message_id The Telegram message_id to search for.
	 *
	 * @return array|null Associative array of the matching approval row, or null if not found.
	 */
	public function get_by_telegram_msg( $message_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM `{$this->table}` WHERE telegram_message_id = %d LIMIT 1",
				(int) $message_id
			),
			ARRAY_A
		);
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Downloads a remote image and adds it to the WordPress media library.
	 *
	 * @since 2.0.0
	 *
	 * @param string $image_url Remote URL of the image to sideload.
	 * @param int    $post_id   Post ID that the attachment will be associated with.
	 * @param string $title     Title used for the attachment post.
	 *
	 * @return int|WP_Error Attachment ID on success, WP_Error on failure.
	 */
	private function sideload_image( $image_url, $post_id, $title = '' ) {
		if ( ! function_exists( 'media_sideload_image' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$attachment_id = media_sideload_image( $image_url, $post_id, $title, 'id' );

		return $attachment_id;
	}

	/**
	 * Escapes special Markdown characters in a string for safe Telegram Markdown rendering.
	 *
	 * Only escapes characters that break Telegram's legacy Markdown parser.
	 *
	 * @since 2.0.0
	 *
	 * @param string $text Input text.
	 *
	 * @return string Escaped text safe for use in Telegram Markdown messages.
	 */
	private function escape_markdown( $text ) {
		// Escape backticks and underscores that could break formatting.
		return str_replace( [ '_', '*', '`', '[' ], [ '\_', '\*', '\`', '\[' ], $text );
	}
}
