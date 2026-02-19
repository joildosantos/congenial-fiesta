<?php
/**
 * JEP Image AI
 *
 * Generates editorial images via AI image-generation APIs (DALL-E 3 and
 * Stable Diffusion–compatible endpoints). Downloads the generated image and
 * imports it into the WordPress media library.
 *
 * @package JEP_Automacao_Editorial
 * @subpackage Image
 * @since 2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class JEP_Image_AI
 */
class JEP_Image_AI {

	/**
	 * WordPress option key for AI image settings.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'jep_image_ai_settings';

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Generate an image from a text prompt and return its WordPress URL.
	 *
	 * @param string $prompt Descriptive image prompt (in English for best results).
	 * @param string $title  Optional title used as the WordPress attachment title.
	 *
	 * @return string WordPress attachment URL or empty string on failure.
	 */
	public static function generate( $prompt, $title = '' ) {
		$settings = self::get_settings();

		if ( empty( $settings['provider'] ) || empty( $settings['api_key'] ) ) {
			JEP_Logger::warning( 'Image AI: provedor ou chave API não configurados.', 'image' );
			return '';
		}

		try {
			$image_url = self::call_provider( $prompt, $settings );
		} catch ( Exception $e ) {
			JEP_Logger::error( 'Image AI: ' . $e->getMessage(), 'image' );
			return '';
		}

		if ( empty( $image_url ) ) {
			return '';
		}

		$attachment_url = self::download_and_import( $image_url, $title ?: $prompt );

		if ( $attachment_url ) {
			JEP_Logger::info( 'Image AI: imagem gerada e importada — ' . $attachment_url, 'image' );
		}

		return $attachment_url;
	}

	/**
	 * Return the current AI image settings.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$defaults = array(
			'provider'    => '',   // 'dalle3' | 'stable_diffusion'
			'api_key'     => '',
			'base_url'    => '',   // custom endpoint for SD-compatible APIs
			'model'       => 'dall-e-3',
			'size'        => '1792x1024',
			'style'       => 'vivid',
			'quality'     => 'standard',
		);

		return array_merge( $defaults, (array) get_option( self::OPTION_KEY, array() ) );
	}

	/**
	 * Persist AI image settings.
	 *
	 * @param array $data Settings array.
	 *
	 * @return void
	 */
	public static function save_settings( $data ) {
		$allowed = array( 'provider', 'api_key', 'base_url', 'model', 'size', 'style', 'quality' );
		$clean   = array();

		foreach ( $allowed as $key ) {
			if ( array_key_exists( $key, $data ) ) {
				$clean[ $key ] = sanitize_text_field( $data[ $key ] );
			}
		}

		update_option( self::OPTION_KEY, $clean );
	}

	// -------------------------------------------------------------------------
	// Provider dispatchers
	// -------------------------------------------------------------------------

	/**
	 * Dispatch the prompt to the configured provider and return the raw image URL.
	 *
	 * @param string $prompt   Image prompt.
	 * @param array  $settings Settings array.
	 *
	 * @return string Temporary image URL returned by the API.
	 *
	 * @throws Exception On API or transport errors.
	 */
	private static function call_provider( $prompt, $settings ) {
		switch ( $settings['provider'] ) {
			case 'dalle3':
				return self::call_dalle3( $prompt, $settings );

			case 'stable_diffusion':
				return self::call_stable_diffusion( $prompt, $settings );

			default:
				throw new Exception( 'Provedor de imagem AI desconhecido: ' . $settings['provider'] );
		}
	}

	/**
	 * Call the DALL-E 3 images/generations endpoint.
	 *
	 * @param string $prompt   Image prompt.
	 * @param array  $settings Settings array.
	 *
	 * @return string Temporary image URL.
	 *
	 * @throws Exception On API errors.
	 */
	private static function call_dalle3( $prompt, $settings ) {
		$endpoint = 'https://api.openai.com/v1/images/generations';

		$body = array(
			'model'   => $settings['model'] ?: 'dall-e-3',
			'prompt'  => $prompt,
			'n'       => 1,
			'size'    => $settings['size'] ?: '1792x1024',
			'quality' => $settings['quality'] ?: 'standard',
			'style'   => $settings['style'] ?: 'vivid',
		);

		$response = wp_remote_post(
			$endpoint,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $settings['api_key'],
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => 60,
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new Exception( 'DALL-E 3 HTTP error: ' . $response->get_error_message() );
		}

		$http_code = wp_remote_retrieve_response_code( $response );
		$decoded   = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $http_code < 200 || $http_code >= 300 ) {
			$msg = isset( $decoded['error']['message'] ) ? $decoded['error']['message'] : "HTTP {$http_code}";
			throw new Exception( 'DALL-E 3 API error: ' . $msg );
		}

		if ( empty( $decoded['data'][0]['url'] ) ) {
			throw new Exception( 'DALL-E 3: URL de imagem ausente na resposta.' );
		}

		return $decoded['data'][0]['url'];
	}

	/**
	 * Call a Stable Diffusion–compatible text-to-image endpoint.
	 *
	 * @param string $prompt   Image prompt.
	 * @param array  $settings Settings array.
	 *
	 * @return string Temporary image URL.
	 *
	 * @throws Exception On API errors.
	 */
	private static function call_stable_diffusion( $prompt, $settings ) {
		$base_url = rtrim( $settings['base_url'], '/' );

		if ( empty( $base_url ) ) {
			throw new Exception( 'Stable Diffusion: base_url não configurada.' );
		}

		$endpoint = $base_url . '/v1/generation/text-to-image';

		$body = array(
			'text_prompts' => array(
				array( 'text' => $prompt, 'weight' => 1 ),
			),
			'cfg_scale'    => 7,
			'height'       => 640,
			'width'        => 1216,
			'samples'      => 1,
			'steps'        => 30,
		);

		$headers = array( 'Content-Type' => 'application/json' );

		if ( ! empty( $settings['api_key'] ) ) {
			$headers['Authorization'] = 'Bearer ' . $settings['api_key'];
		}

		$response = wp_remote_post(
			$endpoint,
			array(
				'headers' => $headers,
				'body'    => wp_json_encode( $body ),
				'timeout' => 120,
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new Exception( 'SD HTTP error: ' . $response->get_error_message() );
		}

		$http_code = wp_remote_retrieve_response_code( $response );
		$decoded   = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $http_code < 200 || $http_code >= 300 ) {
			throw new Exception( "SD API HTTP {$http_code}" );
		}

		// SD returns base64 images; decode and upload.
		if ( isset( $decoded['artifacts'][0]['base64'] ) ) {
			return self::save_base64_image( $decoded['artifacts'][0]['base64'], 'sd-generated' );
		}

		if ( isset( $decoded['output'][0] ) ) {
			// Some SD UIs return URLs directly.
			return $decoded['output'][0];
		}

		throw new Exception( 'SD: resposta inesperada da API.' );
	}

	// -------------------------------------------------------------------------
	// Media library helpers
	// -------------------------------------------------------------------------

	/**
	 * Download a remote image and import it into the WP media library.
	 *
	 * @param string $remote_url Remote image URL.
	 * @param string $title      Attachment title / alt text.
	 *
	 * @return string WordPress attachment URL or empty string on failure.
	 */
	private static function download_and_import( $remote_url, $title ) {
		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$tmp = download_url( $remote_url, 60 );

		if ( is_wp_error( $tmp ) ) {
			JEP_Logger::warning( 'Image AI: download_url falhou — ' . $tmp->get_error_message(), 'image' );
			return '';
		}

		$file_array = array(
			'name'     => 'jep-ai-' . time() . '.jpg',
			'tmp_name' => $tmp,
		);

		$attachment_id = media_handle_sideload( $file_array, 0, sanitize_text_field( substr( $title, 0, 120 ) ) );

		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

		if ( is_wp_error( $attachment_id ) ) {
			JEP_Logger::warning( 'Image AI: media_handle_sideload — ' . $attachment_id->get_error_message(), 'image' );
			return '';
		}

		return (string) wp_get_attachment_url( $attachment_id );
	}

	/**
	 * Decode a base64 image string, save it to a temp file and import it.
	 *
	 * @param string $base64 Base64-encoded image data.
	 * @param string $label  Descriptive label for the filename.
	 *
	 * @return string WordPress attachment URL or empty string.
	 */
	private static function save_base64_image( $base64, $label ) {
		$upload_dir = wp_upload_dir();

		if ( ! empty( $upload_dir['error'] ) ) {
			return '';
		}

		$filename = 'jep-ai-' . sanitize_file_name( $label ) . '-' . time() . '.jpg';
		$filepath = trailingslashit( $upload_dir['path'] ) . $filename;

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		$bytes = base64_decode( $base64 );

		if ( false === $bytes || ! file_put_contents( $filepath, $bytes ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			return '';
		}

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$file_array = array(
			'name'     => $filename,
			'tmp_name' => $filepath,
		);

		$attachment_id = media_handle_sideload( $file_array, 0, $label );

		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $filepath ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			return '';
		}

		return (string) wp_get_attachment_url( $attachment_id );
	}
}
