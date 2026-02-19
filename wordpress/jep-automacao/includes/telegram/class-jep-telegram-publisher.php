<?php
/**
 * JEP Telegram Publisher - Interactive bot for editorial management.
 *
 * @package JEP_Automacao_Editorial
 * @subpackage Telegram
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class JEP_Telegram_Publisher
 *
 * Processes incoming Telegram updates from the editor, routing commands, media
 * submissions, and inline-keyboard callbacks to the appropriate handlers. Also
 * manages multi-step conversational flows (e.g. editing a post title) via WordPress
 * transients.
 *
 * @since 2.0.0
 */
class JEP_Telegram_Publisher {

	/**
	 * Telegram Bot API wrapper instance.
	 *
	 * @since 2.0.0
	 * @var JEP_Telegram_Bot
	 */
	private $bot;

	/**
	 * The editor's Telegram chat ID. Only messages from this chat are processed.
	 *
	 * @since 2.0.0
	 * @var string|int
	 */
	private $editor_chat_id;

	/**
	 * Constructor. Initialises the bot and loads the editor chat ID from settings.
	 *
	 * @since 2.0.0
	 */
	public function __construct() {
		$this->bot            = new JEP_Telegram_Bot();
		$this->editor_chat_id = jep_automacao()->settings()->get_telegram_editor_chat_id();
	}

	/**
	 * Entry point for all incoming Telegram updates.
	 *
	 * Dispatches callback_query updates to the approval handler and regular message
	 * updates to the appropriate message handler based on content type and current
	 * conversation state.
	 *
	 * @since 2.0.0
	 *
	 * @param array $update The decoded Telegram update payload.
	 *
	 * @return void
	 */
	public function handle_update( $update ) {
		// ------------------------------------------------------------------
		// Callback queries originate from inline keyboard buttons.
		// ------------------------------------------------------------------
		if ( isset( $update['callback_query'] ) ) {
			$approval = new JEP_Telegram_Approval();
			$approval->handle_callback( $update['callback_query'] );
			return;
		}

		// ------------------------------------------------------------------
		// Plain message handling.
		// ------------------------------------------------------------------
		if ( ! isset( $update['message'] ) ) {
			return;
		}

		$message = $update['message'];
		$from_id = isset( $message['from']['id'] ) ? (string) $message['from']['id'] : '';

		// Security: only accept messages from the configured editor chat.
		if ( (string) $this->editor_chat_id !== $from_id ) {
			jep_automacao()->logger()->info(
				sprintf(
					'[TelegramPublisher] Ignored message from unknown sender %s (expected %s).',
					$from_id,
					$this->editor_chat_id
				)
			);
			return;
		}

		// Check for an active multi-step conversation state.
		$state_key = 'jep_tg_state_' . $from_id;
		$state     = get_transient( $state_key );

		if ( ! empty( $state ) ) {
			// Photo received while awaiting image replacement — handle separately.
			if ( isset( $message['photo'] ) && preg_match( '/^awaiting_image_(\d+)$/', $state, $m ) ) {
				delete_transient( $state_key );
				$this->handle_image_replacement( (int) $m[1], $message );
				return;
			}

			$this->handle_conversation_reply( $state, $message );
			return;
		}

		// Route by message content type.
		$text = isset( $message['text'] ) ? trim( $message['text'] ) : '';

		if ( isset( $message['photo'] ) ) {
			$this->handle_photo_submission( $message );
			return;
		}

		if ( '' !== $text && '/' === $text[0] ) {
			$this->handle_command( $message );
			return;
		}

		if ( '' !== $text && ( strncmp( $text, 'http://', 7 ) === 0 || strncmp( $text, 'https://', 8 ) === 0 ) ) {
			$this->handle_link_submission( $message );
			return;
		}

		if ( '' !== $text ) {
			$this->handle_text_submission( $message );
		}
	}

	/**
	 * Handles slash-command messages from the editor.
	 *
	 * Supported commands:
	 * - /posts        – list 10 most recent posts with edit/view buttons.
	 * - /pendentes    – list pending approval records.
	 * - /pauta {text} – save text to the cold content bank.
	 * - /status       – send a quick stats summary.
	 *
	 * @since 2.0.0
	 *
	 * @param array $message The Telegram message object.
	 *
	 * @return void
	 */
	public function handle_command( $message ) {
		$text    = trim( $message['text'] );
		$parts   = explode( ' ', $text, 2 );
		$command = strtolower( $parts[0] );
		$args    = isset( $parts[1] ) ? trim( $parts[1] ) : '';

		switch ( $command ) {

			// ----------------------------------------------------------------
			// /posts – list recent posts with inline edit/view buttons.
			// ----------------------------------------------------------------
			case '/posts':
				$recent_posts = get_posts(
					[
						'numberposts'      => 10,
						'post_status'      => [ 'publish', 'draft' ],
						'suppress_filters' => true,
					]
				);

				if ( empty( $recent_posts ) ) {
					$this->bot->send_to_editor( '📭 Nenhum post encontrado.' );
					break;
				}

				$lines = [ "📋 *Últimos posts:*\n" ];
				foreach ( $recent_posts as $index => $post ) {
					$status_icon = ( 'publish' === $post->post_status ) ? '🟢' : '⚫';
					$lines[]     = sprintf(
						'%d. %s %s',
						$index + 1,
						$status_icon,
						wp_strip_all_tags( $post->post_title )
					);
				}

				$this->bot->send_to_editor( implode( "\n", $lines ) );

				// Send individual buttons for each post.
				foreach ( $recent_posts as $post ) {
					$title_short = mb_strlen( $post->post_title ) > 30
						? mb_substr( $post->post_title, 0, 30 ) . '…'
						: $post->post_title;

					$keyboard = $this->bot->make_inline_keyboard( [
						[
							[
								'text'          => '✏️ Editar',
								'callback_data' => 'edit_post_' . $post->ID,
							],
							[
								'text'          => '👁 Ver',
								'url'           => get_permalink( $post->ID ),
							],
						],
					] );

					$this->bot->send_to_editor(
						sprintf( '📝 *%s*', wp_strip_all_tags( $title_short ) ),
						$keyboard
					);
				}
				break;

			// ----------------------------------------------------------------
			// /pendentes – list pending approval records.
			// ----------------------------------------------------------------
			case '/pendentes':
				$approval  = new JEP_Telegram_Approval();
				$pending   = $approval->get_pending( 10 );

				if ( empty( $pending ) ) {
					$this->bot->send_to_editor( '✅ Nenhuma aprovação pendente.' );
					break;
				}

				$lines = [ sprintf( "⏳ *%d aprovações pendentes:*\n", count( $pending ) ) ];
				foreach ( $pending as $index => $record ) {
					$created = date_i18n( 'd/m H:i', strtotime( $record['created_at'] ) );
					$lines[] = sprintf(
						'%d. [%s] %s',
						$index + 1,
						$created,
						wp_strip_all_tags( $record['title_a'] )
					);
				}

				$this->bot->send_to_editor( implode( "\n", $lines ) );
				break;

			// ----------------------------------------------------------------
			// /pauta {texto} – add to cold content bank.
			// ----------------------------------------------------------------
			case '/pauta':
				if ( empty( $args ) ) {
					$this->bot->send_to_editor(
						"📌 *Adicionar à pauta*\n\nUso: /pauta <texto da ideia>"
					);
					break;
				}

				$saved = $this->save_to_content_bank( $args );

				if ( $saved ) {
					$this->bot->send_to_editor(
						sprintf( "📌 *Ideia salva na pauta fria!*\n\n_%s_", $args )
					);
				} else {
					$this->bot->send_to_editor( '❌ Erro ao salvar na pauta. Tente novamente.' );
				}
				break;

			// ----------------------------------------------------------------
			// /status – quick stats overview.
			// ----------------------------------------------------------------
			case '/status':
				$published_count = wp_count_posts( 'post' )->publish;
				$draft_count     = wp_count_posts( 'post' )->draft;

				$approval     = new JEP_Telegram_Approval();
				$pending      = $approval->get_pending( 100 );
				$pending_count = count( $pending );

				$today_count = (int) ( new WP_Query( [
					'post_type'      => 'post',
					'post_status'    => 'publish',
					'date_query'     => [ [ 'after' => 'today midnight' ] ],
					'posts_per_page' => -1,
					'fields'         => 'ids',
				] ) )->found_posts;

				$status_text = sprintf(
					"📊 *Status da Redação*\n\n" .
					"🟢 Publicados: *%d*\n" .
					"⚫ Rascunhos: *%d*\n" .
					"⏳ Pendentes: *%d*\n" .
					"📅 Publicados hoje: *%d*\n\n" .
					"_Atualizado: %s_",
					$published_count,
					$draft_count,
					$pending_count,
					$today_count,
					current_time( 'd/m/Y H:i' )
				);

				$this->bot->send_to_editor( $status_text );
				break;

			default:
				$this->bot->send_to_editor(
					"❓ Comando não reconhecido: `{$command}`\n\n" .
					"Comandos disponíveis:\n" .
					"/posts – listar posts recentes\n" .
					"/pendentes – aprovações pendentes\n" .
					"/pauta <texto> – adicionar à pauta fria\n" .
					"/status – resumo de estatísticas"
				);
				break;
		}
	}

	/**
	 * Handles an incoming photo message from the editor.
	 *
	 * Downloads the highest-resolution version of the photo, saves it to the WP media
	 * library, rewrites any caption with the LLM, then sends an approval confirmation
	 * with publish/cancel buttons.
	 *
	 * @since 2.0.0
	 *
	 * @param array $message The Telegram message object containing 'photo' and optional 'caption'.
	 *
	 * @return void
	 */
	public function handle_photo_submission( $message ) {
		$this->bot->send_to_editor( '🖼️ Foto recebida, processando…' );

		// Telegram returns photos as an array sorted ascending by size; pick the largest.
		$photos    = $message['photo'];
		$largest   = end( $photos );
		$file_id   = $largest['file_id'];
		$caption   = isset( $message['caption'] ) ? trim( $message['caption'] ) : '';

		// ------------------------------------------------------------------
		// 1. Resolve the file path via getFile API.
		// ------------------------------------------------------------------
		try {
			$file_info = $this->bot->api_call( 'getFile', [ 'file_id' => $file_id ] );
		} catch ( Exception $e ) {
			$this->bot->send_to_editor( '❌ Não foi possível obter o arquivo da foto.' );
			jep_automacao()->logger()->error(
				sprintf( '[TelegramPublisher] getFile failed: %s', $e->getMessage() )
			);
			return;
		}

		$file_path   = isset( $file_info['file_path'] ) ? $file_info['file_path'] : '';
		$token       = jep_automacao()->settings()->get_telegram_bot_token();
		$download_url = sprintf( 'https://api.telegram.org/file/bot%s/%s', $token, $file_path );

		// ------------------------------------------------------------------
		// 2. Download and sideload the photo into the WP media library.
		// ------------------------------------------------------------------
		if ( ! function_exists( 'media_sideload_image' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		// Use a temporary post ID of 0; we will re-attach when the post is created.
		$attachment_id = media_sideload_image( $download_url, 0, $caption ?: 'Photo from Telegram', 'id' );

		if ( is_wp_error( $attachment_id ) ) {
			$this->bot->send_to_editor(
				'❌ Erro ao salvar a foto na biblioteca: ' . $attachment_id->get_error_message()
			);
			return;
		}

		$image_url = wp_get_attachment_url( $attachment_id );

		// ------------------------------------------------------------------
		// 3. Rewrite caption with LLM if available.
		// ------------------------------------------------------------------
		$draft_text = $caption;
		if ( ! empty( $draft_text ) && class_exists( 'JEP_Content_Rewriter' ) ) {
			try {
				$rewriter   = new JEP_Content_Rewriter();
				$draft_text = $rewriter->short_rewrite( $caption );
			} catch ( Exception $e ) {
				jep_automacao()->logger()->warning(
					sprintf( '[TelegramPublisher] LLM short_rewrite failed: %s', $e->getMessage() )
				);
			}
		}

		// ------------------------------------------------------------------
		// 4. Store the photo as a draft approval and send confirmation.
		// ------------------------------------------------------------------
		$approval_handler = new JEP_Telegram_Approval();
		$approval_id      = $approval_handler->create_approval( [
			'title_a'      => ! empty( $draft_text ) ? wp_trim_words( $draft_text, 10 ) : 'Foto enviada pelo editor',
			'title_b'      => ! empty( $draft_text ) ? wp_trim_words( $draft_text, 8 ) . ' [alt]' : 'Foto pelo editor',
			'excerpt'      => $draft_text,
			'content_html' => ! empty( $draft_text ) ? '<p>' . esc_html( $draft_text ) . '</p>' : '',
			'image_url'    => $image_url,
		] );

		if ( ! $approval_id ) {
			$this->bot->send_to_editor( '❌ Erro ao registrar aprovação. Tente novamente.' );
			return;
		}

		$keyboard = $this->bot->make_inline_keyboard( [
			[
				[
					'text'          => '✅ Publicar',
					'callback_data' => 'approve_' . $approval_id . '_a',
				],
				[
					'text'          => '❌ Cancelar',
					'callback_data' => 'reject_' . $approval_id,
				],
			],
		] );

		$preview_text = sprintf(
			"🖼️ *Foto salva na biblioteca.*\n\n*Texto:* %s\n\nO que deseja fazer?",
			! empty( $draft_text ) ? $draft_text : '_sem legenda_'
		);

		$this->bot->send_photo(
			$this->editor_chat_id,
			$image_url,
			$preview_text,
			'Markdown',
			$keyboard
		);
	}

	/**
	 * Handles a URL submitted as a plain text message.
	 *
	 * Fetches the page, extracts the title and description from meta tags, triggers
	 * LLM rewriting (if available), and sends an A/B approval message.
	 *
	 * @since 2.0.0
	 *
	 * @param array $message The Telegram message object whose text is a URL.
	 *
	 * @return void
	 */
	public function handle_link_submission( $message ) {
		$url = trim( $message['text'] );
		$this->bot->send_to_editor( "🔗 Processando link…\n`{$url}`" );

		// ------------------------------------------------------------------
		// 1. Fetch the remote page.
		// ------------------------------------------------------------------
		$response = wp_remote_get( $url, [ 'timeout' => 20 ] );

		if ( is_wp_error( $response ) ) {
			$this->bot->send_to_editor(
				'❌ Não foi possível acessar o link: ' . $response->get_error_message()
			);
			return;
		}

		$body = wp_remote_retrieve_body( $response );

		if ( empty( $body ) ) {
			$this->bot->send_to_editor( '❌ O link retornou uma página vazia.' );
			return;
		}

		// ------------------------------------------------------------------
		// 2. Parse title and og:description.
		// ------------------------------------------------------------------
		$title       = '';
		$description = '';
		$image_url   = '';

		// Extract <title>.
		if ( preg_match( '/<title[^>]*>(.*?)<\/title>/is', $body, $matches ) ) {
			$title = html_entity_decode( strip_tags( $matches[1] ), ENT_QUOTES, 'UTF-8' );
			$title = trim( preg_replace( '/\s+/', ' ', $title ) );
		}

		// Extract <meta> og:description or name=description.
		if ( preg_match(
			'/<meta[^>]+(?:property=["\']og:description["\']|name=["\']description["\'])[^>]+content=["\']([^"\']*)["\'][^>]*>/is',
			$body,
			$matches
		) ) {
			$description = html_entity_decode( strip_tags( $matches[1] ), ENT_QUOTES, 'UTF-8' );
		} elseif ( preg_match(
			'/<meta[^>]+content=["\']([^"\']*)["\'][^>]+(?:property=["\']og:description["\']|name=["\']description["\'])[^>]*>/is',
			$body,
			$matches
		) ) {
			$description = html_entity_decode( strip_tags( $matches[1] ), ENT_QUOTES, 'UTF-8' );
		}

		// Extract og:image.
		if ( preg_match(
			'/<meta[^>]+property=["\']og:image["\'][^>]+content=["\']([^"\']*)["\'][^>]*>/is',
			$body,
			$matches
		) ) {
			$image_url = esc_url_raw( $matches[1] );
		} elseif ( preg_match(
			'/<meta[^>]+content=["\']([^"\']*)["\'][^>]+property=["\']og:image["\'][^>]*>/is',
			$body,
			$matches
		) ) {
			$image_url = esc_url_raw( $matches[1] );
		}

		if ( empty( $title ) ) {
			$title = esc_url( $url );
		}

		// ------------------------------------------------------------------
		// 3. Build a pseudo RSS item and rewrite with LLM.
		// ------------------------------------------------------------------
		$rss_item = [
			'title'       => $title,
			'description' => $description,
			'link'        => $url,
			'image'       => $image_url,
		];

		$title_a = $title;
		$title_b = $title;

		if ( class_exists( 'JEP_Content_Rewriter' ) ) {
			try {
				$rewriter = new JEP_Content_Rewriter();
				$result   = $rewriter->rewrite_rss_item( $rss_item );

				$title_a = ! empty( $result['title_a'] ) ? $result['title_a'] : $title;
				$title_b = ! empty( $result['title_b'] ) ? $result['title_b'] : $title . ' – análise';
				$description = ! empty( $result['excerpt'] ) ? $result['excerpt'] : $description;
			} catch ( Exception $e ) {
				jep_automacao()->logger()->warning(
					sprintf( '[TelegramPublisher] rewrite_rss_item failed: %s', $e->getMessage() )
				);
				$title_b = $title . ' – análise';
			}
		} else {
			$title_b = $title . ' – análise';
		}

		// ------------------------------------------------------------------
		// 4. Create the approval record and send A/B message.
		// ------------------------------------------------------------------
		$approval_handler = new JEP_Telegram_Approval();
		$approval_id      = $approval_handler->create_approval( [
			'title_a'    => $title_a,
			'title_b'    => $title_b,
			'excerpt'    => $description,
			'source_url' => $url,
			'image_url'  => $image_url,
		] );

		if ( ! $approval_id ) {
			$this->bot->send_to_editor( '❌ Erro ao registrar aprovação para o link.' );
			return;
		}

		$approval_handler->send_for_approval( $approval_id );
	}

	/**
	 * Handles a plain text submission from the editor.
	 *
	 * Short texts (< 200 chars) are saved to the cold content bank. Longer texts are
	 * rewritten with the LLM and sent for A/B approval.
	 *
	 * @since 2.0.0
	 *
	 * @param array $message The Telegram message object containing plain text.
	 *
	 * @return void
	 */
	public function handle_text_submission( $message ) {
		$text = trim( $message['text'] );

		// Short texts → cold content bank.
		if ( mb_strlen( $text ) < 200 ) {
			$saved = $this->save_to_content_bank( $text );

			$this->bot->send_to_editor(
				$saved
					? "📌 *Ideia salva na pauta fria!*\n\n_{$text}_"
					: '❌ Erro ao salvar na pauta. Tente novamente.'
			);
			return;
		}

		// Longer texts → rewrite and send for approval.
		$this->bot->send_to_editor( '✍️ Texto longo recebido, reescrevendo com IA…' );

		$title_a     = wp_trim_words( $text, 10 );
		$title_b     = $title_a . ' – análise';
		$content_html = '<p>' . esc_html( $text ) . '</p>';
		$excerpt     = wp_trim_words( $text, 30, '…' );

		if ( class_exists( 'JEP_Content_Rewriter' ) ) {
			try {
				$rewriter = new JEP_Content_Rewriter();
				$result   = $rewriter->rewrite_rss_item( [
					'title'       => $title_a,
					'description' => $text,
					'link'        => '',
					'image'       => '',
				] );

				$title_a      = ! empty( $result['title_a'] ) ? $result['title_a'] : $title_a;
				$title_b      = ! empty( $result['title_b'] ) ? $result['title_b'] : $title_b;
				$content_html = ! empty( $result['content_html'] ) ? $result['content_html'] : $content_html;
				$excerpt      = ! empty( $result['excerpt'] ) ? $result['excerpt'] : $excerpt;
			} catch ( Exception $e ) {
				jep_automacao()->logger()->warning(
					sprintf( '[TelegramPublisher] Text rewrite failed: %s', $e->getMessage() )
				);
			}
		}

		$approval_handler = new JEP_Telegram_Approval();
		$approval_id      = $approval_handler->create_approval( [
			'title_a'      => $title_a,
			'title_b'      => $title_b,
			'excerpt'      => $excerpt,
			'content_html' => $content_html,
		] );

		if ( ! $approval_id ) {
			$this->bot->send_to_editor( '❌ Erro ao registrar aprovação para o texto.' );
			return;
		}

		$approval_handler->send_for_approval( $approval_id );
	}

	/**
	 * Handles a reply message that is part of an active multi-step conversation.
	 *
	 * Dispatches to the correct handler based on the conversation state string stored
	 * in the transient, then deletes the transient to end the conversation.
	 *
	 * @since 2.0.0
	 *
	 * @param string $state   The conversation state string (e.g. 'awaiting_title_42').
	 * @param array  $message The Telegram message object containing the user's reply.
	 *
	 * @return void
	 */
	public function handle_conversation_reply( $state, $message ) {
		$from_id   = isset( $message['from']['id'] ) ? (string) $message['from']['id'] : '';
		$text      = isset( $message['text'] ) ? trim( $message['text'] ) : '';
		$state_key = 'jep_tg_state_' . $from_id;

		// Allow the user to cancel any conversation.
		if ( in_array( strtolower( $text ), [ '/cancelar', 'cancelar', '/cancel', 'cancel' ], true ) ) {
			delete_transient( $state_key );
			$this->bot->send_to_editor( '↩️ Operação cancelada.' );
			return;
		}

		// Pattern: awaiting_title_{post_id}
		if ( preg_match( '/^awaiting_title_(\d+)$/', $state, $matches ) ) {
			$post_id = (int) $matches[1];

			$result = wp_update_post( [
				'ID'         => $post_id,
				'post_title' => wp_strip_all_tags( $text ),
			], true );

			delete_transient( $state_key );

			if ( is_wp_error( $result ) ) {
				$this->bot->send_to_editor(
					'❌ Erro ao atualizar o título: ' . $result->get_error_message()
				);
			} else {
				$this->bot->send_to_editor(
					sprintf( "✅ *Título atualizado!*\n\n_%s_", $text )
				);
				$this->send_post_edit_menu( $post_id );
			}
			return;
		}

		// Pattern: awaiting_excerpt_{post_id}
		if ( preg_match( '/^awaiting_excerpt_(\d+)$/', $state, $matches ) ) {
			$post_id = (int) $matches[1];

			$result = wp_update_post( [
				'ID'           => $post_id,
				'post_excerpt' => wp_strip_all_tags( $text ),
			], true );

			delete_transient( $state_key );

			if ( is_wp_error( $result ) ) {
				$this->bot->send_to_editor(
					'❌ Erro ao atualizar o resumo: ' . $result->get_error_message()
				);
			} else {
				$this->bot->send_to_editor(
					sprintf( "✅ *Resumo atualizado!*\n\n_%s_", wp_trim_words( $text, 20, '…' ) )
				);
				$this->send_post_edit_menu( $post_id );
			}
			return;
		}

		// Pattern: awaiting_instagram_caption_{approval_id}
		if ( preg_match( '/^awaiting_instagram_caption_(\d+)$/', $state, $matches ) ) {
			$approval_id = (int) $matches[1];

			delete_transient( $state_key );

			if ( class_exists( 'JEP_Instagram_Publisher' ) ) {
				try {
					JEP_Instagram_Publisher::publish_with_caption( $approval_id, $text );
					$this->bot->send_to_editor(
						sprintf( "✅ *Legenda do Instagram salva!*\n\n_%s_\n\n_Publicando…_", $text )
					);
				} catch ( Exception $e ) {
					$this->bot->send_to_editor(
						'❌ Erro ao publicar no Instagram: ' . $e->getMessage()
					);
				}
			} else {
				$this->bot->send_to_editor( '❌ Módulo do Instagram não está disponível.' );
			}
			return;
		}

		// Unknown state – clear it and notify.
		delete_transient( $state_key );
		$this->bot->send_to_editor(
			"⚠️ Estado de conversa inválido. Operação cancelada.\n\nDigite /ajuda para ver os comandos disponíveis."
		);
	}

	/**
	 * Sends the post editing menu to the editor with an inline keyboard.
	 *
	 * @since 2.0.0
	 *
	 * @param int $post_id The ID of the WordPress post to edit.
	 *
	 * @return void
	 */
	public function send_post_edit_menu( $post_id ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			$this->bot->send_to_editor( '❌ Post não encontrado.' );
			return;
		}

		$title_short = mb_strlen( $post->post_title ) > 40
			? mb_substr( $post->post_title, 0, 40 ) . '…'
			: $post->post_title;

		$menu_text = sprintf(
			"✏️ *Editando:* %s\n\nEscolha o campo:",
			wp_strip_all_tags( $title_short )
		);

		$keyboard = $this->bot->make_inline_keyboard( [
			[
				[
					'text'          => '📝 Título',
					'callback_data' => 'edit_title_' . $post_id,
				],
				[
					'text'          => '📄 Excerpt',
					'callback_data' => 'edit_excerpt_' . $post_id,
				],
			],
			[
				[
					'text'          => '🖼️ Imagem',
					'callback_data' => 'edit_image_' . $post_id,
				],
				[
					'text'          => '✅ Publicar',
					'callback_data' => 'publish_' . $post_id,
				],
			],
			[
				[
					'text'          => '📤 Despublicar',
					'callback_data' => 'unpublish_' . $post_id,
				],
				[
					'text'          => '↩️ Cancelar',
					'callback_data' => 'cancel_edit_' . $post_id,
				],
			],
		] );

		$this->bot->send_to_editor( $menu_text, $keyboard );
	}

	/**
	 * Handles an edit-menu callback_query from the post editing inline keyboard.
	 *
	 * For title/excerpt fields: sets a conversation-state transient and prompts the editor.
	 * For publish/unpublish/image/cancel: performs the action immediately.
	 *
	 * @since 2.0.0
	 *
	 * @param array $callback_query The callback_query object from the Telegram update.
	 *
	 * @return void
	 */
	public function handle_edit_callback( $callback_query ) {
		$callback_id   = $callback_query['id'];
		$callback_data = isset( $callback_query['data'] ) ? $callback_query['data'] : '';
		$from_id       = isset( $callback_query['from']['id'] )
			? (string) $callback_query['from']['id']
			: '';

		// ----------------------------------------------------------------
		// edit_title_{post_id} / edit_excerpt_{post_id}
		// ----------------------------------------------------------------
		if ( preg_match( '/^edit_(title|excerpt)_(\d+)$/', $callback_data, $matches ) ) {
			$field     = $matches[1];
			$post_id   = (int) $matches[2];
			$state_key = 'jep_tg_state_' . $from_id;
			$label     = ( 'title' === $field ) ? 'título' : 'resumo (excerpt)';

			set_transient( $state_key, 'awaiting_' . $field . '_' . $post_id, 10 * MINUTE_IN_SECONDS );

			$this->bot->answer_callback_query( $callback_id );
			$this->bot->send_to_editor(
				sprintf(
					"✏️ *Editando %s*\n\nDigite o novo %s abaixo.\nDigite /cancelar para abortar.",
					$label,
					$label
				)
			);
			return;
		}

		// ----------------------------------------------------------------
		// edit_image_{post_id}
		// ----------------------------------------------------------------
		if ( preg_match( '/^edit_image_(\d+)$/', $callback_data, $matches ) ) {
			$post_id   = (int) $matches[1];
			$state_key = 'jep_tg_state_' . $from_id;

			set_transient( $state_key, 'awaiting_image_' . $post_id, 10 * MINUTE_IN_SECONDS );

			$this->bot->answer_callback_query( $callback_id );
			$this->bot->send_to_editor(
				"🖼️ *Alterar imagem destacada*\n\n" .
				"Envie a nova foto nesta conversa.\n" .
				"Ela será automaticamente definida como imagem destacada do post ID *{$post_id}*.\n\n" .
				"Digite /cancelar para abortar."
			);
			return;
		}

		// ----------------------------------------------------------------
		// publish_{post_id}
		// ----------------------------------------------------------------
		if ( preg_match( '/^publish_(\d+)$/', $callback_data, $matches ) ) {
			$post_id = (int) $matches[1];

			$result = wp_update_post( [
				'ID'          => $post_id,
				'post_status' => 'publish',
			], true );

			$this->bot->answer_callback_query( $callback_id );

			if ( is_wp_error( $result ) ) {
				$this->bot->send_to_editor(
					'❌ Erro ao publicar: ' . $result->get_error_message()
				);
			} else {
				$this->bot->send_to_editor(
					sprintf(
						"✅ *Post publicado com sucesso!*\n\n🔗 %s",
						get_permalink( $post_id )
					)
				);
			}
			return;
		}

		// ----------------------------------------------------------------
		// unpublish_{post_id}
		// ----------------------------------------------------------------
		if ( preg_match( '/^unpublish_(\d+)$/', $callback_data, $matches ) ) {
			$post_id = (int) $matches[1];

			$result = wp_update_post( [
				'ID'          => $post_id,
				'post_status' => 'draft',
			], true );

			$this->bot->answer_callback_query( $callback_id );

			if ( is_wp_error( $result ) ) {
				$this->bot->send_to_editor(
					'❌ Erro ao despublicar: ' . $result->get_error_message()
				);
			} else {
				$this->bot->send_to_editor(
					sprintf(
						"📤 *Post movido para rascunho.*\n\n" .
						"ID: *%d*\n" .
						"🔗 %s",
						$post_id,
						get_edit_post_link( $post_id )
					)
				);
			}
			return;
		}

		// ----------------------------------------------------------------
		// cancel_edit_{post_id}
		// ----------------------------------------------------------------
		if ( preg_match( '/^cancel_edit_(\d+)$/', $callback_data, $matches ) ) {
			$this->bot->answer_callback_query( $callback_id, '↩️ Edição cancelada.' );
			$this->bot->send_to_editor( '↩️ Edição cancelada.' );
			return;
		}

		// ----------------------------------------------------------------
		// edit_post_{post_id} – open the edit menu for a specific post.
		// ----------------------------------------------------------------
		if ( preg_match( '/^edit_post_(\d+)$/', $callback_data, $matches ) ) {
			$post_id = (int) $matches[1];
			$this->bot->answer_callback_query( $callback_id );
			$this->send_post_edit_menu( $post_id );
			return;
		}

		// Unrecognised callback.
		$this->bot->answer_callback_query( $callback_id, 'Ação não reconhecida.', true );
		jep_automacao()->logger()->warning(
			sprintf(
				'[TelegramPublisher] Unrecognised edit callback: "%s"',
				$callback_data
			)
		);
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Downloads a Telegram photo and sets it as the featured image of a post.
	 *
	 * Called when the editor sends a photo while the conversation state is
	 * `awaiting_image_{post_id}`. Sideloads the highest-resolution variant of the
	 * photo into the WordPress media library, attaches it to the post, and shows
	 * the edit menu again.
	 *
	 * @since 2.0.0
	 *
	 * @param int   $post_id The ID of the WordPress post whose thumbnail should be replaced.
	 * @param array $message The Telegram message object containing the 'photo' array.
	 *
	 * @return void
	 */
	private function handle_image_replacement( $post_id, $message ) {
		$post = get_post( $post_id );

		if ( ! $post ) {
			$this->bot->send_to_editor( '❌ Post não encontrado.' );
			return;
		}

		$this->bot->send_to_editor( '🖼️ Foto recebida, salvando como imagem destacada…' );

		// Pick the highest-resolution photo (Telegram returns ascending array).
		$photos  = $message['photo'];
		$largest = end( $photos );
		$file_id = $largest['file_id'];

		// 1. Resolve the public file path via getFile.
		try {
			$file_info = $this->bot->api_call( 'getFile', [ 'file_id' => $file_id ] );
		} catch ( Exception $e ) {
			$this->bot->send_to_editor( '❌ Não foi possível obter o arquivo da foto.' );
			jep_automacao()->logger()->error(
				sprintf( '[TelegramPublisher] getFile failed for image replacement (post %d): %s', $post_id, $e->getMessage() )
			);
			return;
		}

		$file_path    = isset( $file_info['file_path'] ) ? $file_info['file_path'] : '';
		$token        = jep_automacao()->settings()->get_telegram_bot_token();
		$download_url = sprintf( 'https://api.telegram.org/file/bot%s/%s', $token, $file_path );

		// 2. Sideload the photo into the WP media library attached to the post.
		if ( ! function_exists( 'media_sideload_image' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$attachment_id = media_sideload_image( $download_url, $post_id, $post->post_title, 'id' );

		if ( is_wp_error( $attachment_id ) ) {
			$this->bot->send_to_editor(
				'❌ Erro ao salvar a foto na biblioteca: ' . $attachment_id->get_error_message()
			);
			return;
		}

		// 3. Set as the post featured image.
		set_post_thumbnail( $post_id, $attachment_id );

		jep_automacao()->logger()->info(
			sprintf(
				'[TelegramPublisher] Featured image replaced for post %d (attachment %d).',
				$post_id,
				$attachment_id
			)
		);

		$this->bot->send_to_editor(
			sprintf(
				"✅ *Imagem destacada atualizada!*\n\n📝 Post: *%s*",
				wp_strip_all_tags( $post->post_title )
			)
		);

		// Return to the edit menu.
		$this->send_post_edit_menu( $post_id );
	}

	/**
	 * Saves a piece of text to the cold content bank (post-meta driven bank).
	 *
	 * Inserts a new WordPress post of type 'jep_content_bank' with 'draft' status,
	 * or simply stores via options if the CPT does not exist.
	 *
	 * @since 2.0.0
	 *
	 * @param string $text The idea or text to save.
	 *
	 * @return bool True on success, false on failure.
	 */
	private function save_to_content_bank( $text ) {
		do_action( 'jep_before_save_content_bank', $text );

		if ( post_type_exists( 'jep_content_bank' ) ) {
			$post_id = wp_insert_post( [
				'post_title'   => wp_trim_words( $text, 10, '…' ),
				'post_content' => $text,
				'post_status'  => 'draft',
				'post_type'    => 'jep_content_bank',
			] );

			return ! is_wp_error( $post_id ) && $post_id > 0;
		}

		// Fallback: store in a serialised option array.
		$bank   = get_option( 'jep_content_bank', [] );
		$bank[] = [
			'text'       => $text,
			'created_at' => current_time( 'mysql' ),
		];

		return update_option( 'jep_content_bank', array_slice( $bank, -200 ) );
	}
}
