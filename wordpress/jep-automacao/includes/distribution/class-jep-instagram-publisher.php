<?php
/**
 * Instagram Publisher
 *
 * Handles image preparation, caption building, and publishing to Instagram
 * via the Meta Graph API. Supports optional editor approval flow through
 * Telegram before content is published.
 *
 * @package    JEP_Automacao_Editorial
 * @subpackage Distribution
 * @since      2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class JEP_Instagram_Publisher
 *
 * Manages the full lifecycle of an Instagram post: image generation,
 * caption construction, optional Telegram-based approval, and final
 * publication through the Meta Graph API.
 *
 * @since 2.0.0
 */
class JEP_Instagram_Publisher {

	/**
	 * Meta Graph API base URL (versioned).
	 *
	 * @since 2.0.0
	 * @var string
	 */
	private $api_base = 'https://graph.facebook.com/v19.0';

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
	 * Bootstraps dependencies from the main plugin container.
	 *
	 * @since 2.0.0
	 */
	public function __construct() {
		$this->settings = jep_automacao()->settings();
		$this->logger   = jep_automacao()->logger();
	}

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Prepare an Instagram post for the given WordPress post.
	 *
	 * Generates the square image, builds the caption, and either sends it
	 * to an editor for Telegram-based approval or publishes it immediately,
	 * depending on the plugin settings.
	 *
	 * @since 2.0.0
	 *
	 * @param int $post_id WordPress post ID.
	 * @return void
	 */
	public function prepare( int $post_id ): void {
		if ( ! $this->is_instagram_enabled() ) {
			$this->logger->info( 'Instagram: distribuição desativada, pulando.', [ 'post_id' => $post_id ] );
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			$this->logger->warning( 'Instagram: post inválido ou não publicado.', [ 'post_id' => $post_id ] );
			return;
		}

		// Generate square social image (1080×1080).
		$generator  = new JEP_Image_Generator_GD();
		$image_data = $generator->generate( JEP_Image_Generator_GD::FORMAT_INSTAGRAM_SQUARE, $post_id );

		if ( is_wp_error( $image_data ) || empty( $image_data ) ) {
			$this->logger->error( 'Instagram: falha ao gerar imagem quadrada.', [ 'post_id' => $post_id ] );
			return;
		}

		// $image_data is the public URL returned by generate().
		$image_url = $image_data;

		// Build caption from template.
		$caption = $this->build_caption( $post_id );

		$require_approval = (bool) $this->settings->get( 'instagram_require_caption_approval', false );

		if ( $require_approval ) {
			$this->send_approval_request( $post_id, $image_url, $caption );
		} else {
			$result = $this->publish_to_instagram( $post_id, $image_url, $caption );
			if ( is_wp_error( $result ) ) {
				$this->logger->error(
					'Instagram: publicação direta falhou.',
					[ 'post_id' => $post_id, 'error' => $result->get_error_message() ]
				);
			}
		}
	}

	/**
	 * Publish an image and caption to Instagram via the Meta Graph API.
	 *
	 * Two-step process: first create a media container, then publish it.
	 *
	 * @since 2.0.0
	 *
	 * @param int    $post_id   WordPress post ID (used for post-meta storage).
	 * @param string $image_url Publicly accessible URL of the image to upload.
	 * @param string $caption   Caption text (max 2 200 chars).
	 * @return int|WP_Error Instagram post ID on success, WP_Error on failure.
	 */
	public function publish_to_instagram( int $post_id, string $image_url, string $caption ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'jep_instagram_not_configured', __( 'Instagram não está configurado corretamente.', 'jep-automacao' ) );
		}

		// Validate that the image URL is publicly accessible.
		$head_response = wp_remote_head( $image_url, [ 'timeout' => 15 ] );
		if ( is_wp_error( $head_response ) ) {
			return new WP_Error(
				'jep_instagram_image_unreachable',
				sprintf( __( 'Imagem não acessível publicamente: %s', 'jep-automacao' ), $head_response->get_error_message() )
			);
		}
		$http_code = wp_remote_retrieve_response_code( $head_response );
		if ( $http_code < 200 || $http_code >= 300 ) {
			return new WP_Error(
				'jep_instagram_image_http_error',
				sprintf( __( 'Imagem retornou HTTP %d.', 'jep-automacao' ), $http_code )
			);
		}

		$account_id   = $this->settings->get( 'instagram_account_id', '' );
		$access_token = $this->settings->get( 'instagram_access_token', '' );

		// Step 1: Create media container.
		$container_url      = trailingslashit( $this->api_base ) . $account_id . '/media';
		$container_response = wp_remote_post(
			$container_url,
			[
				'timeout' => 30,
				'body'    => [
					'image_url'    => $image_url,
					'caption'      => $caption,
					'access_token' => $access_token,
				],
			]
		);

		if ( is_wp_error( $container_response ) ) {
			return new WP_Error(
				'jep_instagram_container_error',
				sprintf( __( 'Falha ao criar container de mídia: %s', 'jep-automacao' ), $container_response->get_error_message() )
			);
		}

		$container_body = json_decode( wp_remote_retrieve_body( $container_response ), true );
		if ( empty( $container_body['id'] ) ) {
			$api_error = $container_body['error']['message'] ?? __( 'Resposta inesperada da API.', 'jep-automacao' );
			return new WP_Error( 'jep_instagram_container_id_missing', $api_error );
		}

		$creation_id = sanitize_text_field( $container_body['id'] );

		// Step 2: Publish the container.
		$publish_url      = trailingslashit( $this->api_base ) . $account_id . '/media_publish';
		$publish_response = wp_remote_post(
			$publish_url,
			[
				'timeout' => 30,
				'body'    => [
					'creation_id'  => $creation_id,
					'access_token' => $access_token,
				],
			]
		);

		if ( is_wp_error( $publish_response ) ) {
			return new WP_Error(
				'jep_instagram_publish_error',
				sprintf( __( 'Falha ao publicar no Instagram: %s', 'jep-automacao' ), $publish_response->get_error_message() )
			);
		}

		$publish_body = json_decode( wp_remote_retrieve_body( $publish_response ), true );
		if ( empty( $publish_body['id'] ) ) {
			$api_error = $publish_body['error']['message'] ?? __( 'Resposta inesperada ao publicar.', 'jep-automacao' );
			return new WP_Error( 'jep_instagram_publish_id_missing', $api_error );
		}

		$instagram_post_id = sanitize_text_field( $publish_body['id'] );

		// Persist the Instagram post ID as post meta.
		update_post_meta( $post_id, '_jep_instagram_id', $instagram_post_id );

		$this->logger->info(
			'Instagram: publicação bem-sucedida.',
			[ 'post_id' => $post_id, 'instagram_post_id' => $instagram_post_id ]
		);

		return $instagram_post_id;
	}

	/**
	 * Build the Instagram caption for a post, using a template string.
	 *
	 * Replacements: {titulo}, {excerpt}, {url}, {hashtags}.
	 * Output is capped at 2 200 characters (Instagram's limit).
	 *
	 * @since 2.0.0
	 *
	 * @param int    $post_id  WordPress post ID.
	 * @param string $template Optional override template. Defaults to settings value.
	 * @return string Formatted caption.
	 */
	public function build_caption( int $post_id, string $template = '' ): string {
		if ( empty( $template ) ) {
			$template = $this->settings->get(
				'instagram_caption_template',
				"{titulo}\n\n{excerpt}\n\n{url}\n\n{hashtags}"
			);
		}

		$post      = get_post( $post_id );
		$title     = $post ? get_the_title( $post_id ) : '';
		$excerpt   = $post ? wp_strip_all_tags( get_the_excerpt( $post_id ) ) : '';
		$permalink = get_permalink( $post_id );
		$hashtags  = $this->get_hashtags( $post_id );

		$caption = str_replace(
			[ '{titulo}', '{excerpt}', '{url}', '{hashtags}' ],
			[ $title,     $excerpt,    $permalink, $hashtags ],
			$template
		);

		// Enforce Instagram's 2 200-character caption limit.
		if ( mb_strlen( $caption ) > 2200 ) {
			$caption = mb_substr( $caption, 0, 2197 ) . '...';
		}

		return trim( $caption );
	}

	/**
	 * Build a hashtag string from the post's tags or categories.
	 *
	 * Tags take priority. Falls back to categories. Returns up to 10 hashtags,
	 * each lowercased with spaces replaced by underscores.
	 *
	 * @since 2.0.0
	 *
	 * @param int $post_id WordPress post ID.
	 * @return string Hashtag string, e.g. "#periferia #direitoshumanos".
	 */
	public function get_hashtags( int $post_id ): string {
		$terms = get_the_tags( $post_id );

		// Fall back to categories if there are no tags.
		if ( ! $terms || is_wp_error( $terms ) ) {
			$terms = get_the_category( $post_id );
		}

		if ( ! $terms || is_wp_error( $terms ) ) {
			return '';
		}

		$hashtags = [];
		foreach ( array_slice( $terms, 0, 10 ) as $term ) {
			// Build a valid hashtag: lowercase, strip spaces (replace with _), remove non-alphanumeric.
			$slug = mb_strtolower( $term->name );
			$slug = preg_replace( '/\s+/', '_', $slug );
			$slug = preg_replace( '/[^\w]/u', '', $slug );

			if ( ! empty( $slug ) ) {
				$hashtags[] = '#' . $slug;
			}
		}

		return implode( ' ', $hashtags );
	}

	/**
	 * Check whether the Instagram integration is enabled in settings.
	 *
	 * @since 2.0.0
	 *
	 * @return bool True if Instagram distribution is enabled.
	 */
	public function is_instagram_enabled(): bool {
		return (bool) $this->settings->get( 'instagram_enabled', false );
	}

	/**
	 * Check whether the Instagram API credentials are fully configured.
	 *
	 * @since 2.0.0
	 *
	 * @return bool True if both account_id and access_token are non-empty.
	 */
	public function is_configured(): bool {
		$account_id   = $this->settings->get( 'instagram_account_id', '' );
		$access_token = $this->settings->get( 'instagram_access_token', '' );

		return ! empty( $account_id ) && ! empty( $access_token );
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Send a caption approval request to the editor via Telegram.
	 *
	 * Stores the pending post data in a transient so that the Telegram webhook
	 * handler can retrieve it when the editor responds.
	 *
	 * @since 2.0.0
	 *
	 * @param int    $post_id   WordPress post ID.
	 * @param string $image_url Public URL of the generated image.
	 * @param string $caption   Proposed caption text.
	 * @return void
	 */
	private function send_approval_request( int $post_id, string $image_url, string $caption ): void {
		// Store pending data so the Telegram callback handler can retrieve it.
		$transient_key = 'jep_ig_pending_' . $post_id;
		set_transient(
			$transient_key,
			[
				'post_id'   => $post_id,
				'image_url' => $image_url,
				'caption'   => $caption,
			],
			DAY_IN_SECONDS
		);

		// Prepare a safe preview of the caption (truncated for readability).
		$caption_preview = mb_substr( $caption, 0, 300 );
		if ( mb_strlen( $caption ) > 300 ) {
			$caption_preview .= '...';
		}

		$message = sprintf(
			"📸 *Pronto para Instagram*\n\n%s\n\nAprove a legenda:",
			$caption_preview
		);

		// Inline keyboard buttons sent to the Telegram editor chat.
		$keyboard = [
			'inline_keyboard' => [
				[
					[
						'text'          => '✅ Publicar Instagram',
						'callback_data' => 'ig_publish_' . $post_id,
					],
					[
						'text'          => '✏️ Editar Legenda',
						'callback_data' => 'ig_edit_' . $post_id,
					],
					[
						'text'          => '⏭️ Pular',
						'callback_data' => 'ig_skip_' . $post_id,
					],
				],
			],
		];

		/** @var JEP_Telegram_Bot $telegram */
		$telegram = jep_automacao()->telegram();
		if ( $telegram ) {
			$telegram->send_message( $message, [ 'reply_markup' => wp_json_encode( $keyboard ) ] );
		}

		$this->logger->info(
			'Instagram: solicitação de aprovação enviada ao editor via Telegram.',
			[ 'post_id' => $post_id ]
		);
	}
}
