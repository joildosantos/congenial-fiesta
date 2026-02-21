<?php
/**
 * JEP Image GD
 *
 * Generates placeholder images using PHP's bundled GD extension.
 * Produces branded cover images with title text overlaid on a solid
 * background, suitable for Telegram approval cards when no real photo
 * is available.
 *
 * @package JEP_Automacao_Editorial
 * @subpackage Image
 * @since 2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class JEP_Image_GD
 */
class JEP_Image_GD {

	/**
	 * Output image width in pixels.
	 *
	 * @var int
	 */
	const WIDTH = 1200;

	/**
	 * Output image height in pixels.
	 *
	 * @var int
	 */
	const HEIGHT = 630;

	/**
	 * JPEG quality (0-100).
	 *
	 * @var int
	 */
	const JPEG_QUALITY = 85;

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Generate a branded placeholder image for the given title text.
	 *
	 * Uploads the generated JPEG to the WordPress media library and returns
	 * its public URL. Falls back to returning a data: URI when the upload
	 * cannot be completed.
	 *
	 * @param string $title     Headline to overlay on the image.
	 * @param string $territory Optional territory label shown as a badge.
	 *
	 * @return string Public URL of the generated image, or empty string on failure.
	 */
	public static function create_placeholder( $title, $territory = '' ) {
		if ( ! extension_loaded( 'gd' ) ) {
			JEP_Logger::warning( 'image', 'Image GD: extensão GD não disponível.' );
			return '';
		}

		try {
			$img = self::build_image( $title, $territory );

			if ( ! $img ) {
				return '';
			}

			$url = self::upload_to_media_library( $img, $title );
			imagedestroy( $img );

			return $url;

		} catch ( Exception $e ) {
			JEP_Logger::error( 'image', 'Image GD: ' . $e->getMessage() );
			return '';
		}
	}

	// -------------------------------------------------------------------------
	// Private helpers
	// -------------------------------------------------------------------------

	/**
	 * Build the GD image resource.
	 *
	 * @param string $title     Headline text.
	 * @param string $territory Territory badge text.
	 *
	 * @return resource|GdImage|false GD image resource or false.
	 */
	private static function build_image( $title, $territory ) {
		$img = imagecreatetruecolor( self::WIDTH, self::HEIGHT );

		if ( ! $img ) {
			return false;
		}

		// Brand colours: dark-red background, white text.
		$bg_color   = imagecolorallocate( $img, 139, 0,   0   ); // #8B0000
		$text_color = imagecolorallocate( $img, 255, 255, 255 ); // #FFFFFF
		$badge_bg   = imagecolorallocate( $img, 200, 30,  30  ); // lighter red for badge
		$line_color = imagecolorallocate( $img, 255, 200, 0   ); // #FFC800 accent line

		// Fill background.
		imagefilledrectangle( $img, 0, 0, self::WIDTH, self::HEIGHT, $bg_color );

		// Accent line at the bottom.
		imagefilledrectangle( $img, 0, self::HEIGHT - 8, self::WIDTH, self::HEIGHT, $line_color );

		// Territory badge (top-right).
		if ( ! empty( $territory ) ) {
			$badge_text = strtoupper( $territory );
			$bx         = self::WIDTH - 20 - strlen( $badge_text ) * 9;
			$by         = 20;
			imagefilledrectangle( $img, $bx - 8, $by - 4, $bx + strlen( $badge_text ) * 9, $by + 20, $badge_bg );
			imagestring( $img, 4, $bx, $by, $badge_text, $text_color );
		}

		// Title text — wrap at ~55 chars per line, max 5 lines.
		$lines    = self::wrap_text( $title, 55 );
		$lines    = array_slice( $lines, 0, 5 );
		$line_h   = 40;
		$start_y  = (int) ( ( self::HEIGHT - count( $lines ) * $line_h ) / 2 ) - 20;
		$margin_x = 60;

		// Logotype label at top-left.
		imagestring( $img, 3, $margin_x, 30, 'JEP Automacao Editorial', $line_color );

		foreach ( $lines as $i => $line ) {
			imagettftext_or_imagestring( $img, $line, $margin_x, $start_y + $i * $line_h, $text_color );
		}

		return $img;
	}

	/**
	 * Render text with imagettftext when a font is available, otherwise use
	 * the built-in bitmap font via imagestring.
	 *
	 * @param resource|GdImage $img   GD image.
	 * @param string           $text  Text to draw.
	 * @param int              $x     Left offset.
	 * @param int              $y     Top offset.
	 * @param int              $color GD colour resource.
	 *
	 * @return void
	 */
	private static function imagettftext_or_imagestring( $img, $text, $x, $y, $color ) {
		// Try bundled Ubuntu font (common on Ubuntu servers).
		$font_candidates = array(
			'/usr/share/fonts/truetype/ubuntu/Ubuntu-B.ttf',
			'/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
			'/usr/share/fonts/TTF/DejaVuSans-Bold.ttf',
		);

		$font = null;
		foreach ( $font_candidates as $path ) {
			if ( file_exists( $path ) ) {
				$font = $path;
				break;
			}
		}

		if ( $font && function_exists( 'imagettftext' ) ) {
			imagettftext( $img, 28, 0, $x, $y + 28, $color, $font, $text );
		} else {
			// Fallback: built-in font, size 5.
			imagestring( $img, 5, $x, $y, $text, $color );
		}
	}

	/**
	 * Wrap a long string into lines of at most $max_chars characters,
	 * breaking on word boundaries.
	 *
	 * @param string $text      Input string.
	 * @param int    $max_chars Maximum characters per line.
	 *
	 * @return string[]
	 */
	private static function wrap_text( $text, $max_chars ) {
		$words  = explode( ' ', $text );
		$lines  = array();
		$line   = '';

		foreach ( $words as $word ) {
			$candidate = $line ? $line . ' ' . $word : $word;

			if ( mb_strlen( $candidate ) > $max_chars ) {
				if ( $line ) {
					$lines[] = $line;
				}
				$line = $word;
			} else {
				$line = $candidate;
			}
		}

		if ( $line ) {
			$lines[] = $line;
		}

		return $lines;
	}

	/**
	 * Save the GD image to a temporary file and import it into the WP media
	 * library, then return the attachment URL.
	 *
	 * @param resource|GdImage $img   GD image resource.
	 * @param string           $title Post title for the attachment.
	 *
	 * @return string Attachment URL or empty string on failure.
	 */
	private static function upload_to_media_library( $img, $title ) {
		$upload_dir = wp_upload_dir();

		if ( ! empty( $upload_dir['error'] ) ) {
			throw new Exception( 'WP upload dir error: ' . $upload_dir['error'] );
		}

		$filename = 'jep-placeholder-' . sanitize_file_name( substr( sanitize_title( $title ), 0, 40 ) ) . '-' . time() . '.jpg';
		$filepath = trailingslashit( $upload_dir['path'] ) . $filename;

		if ( ! imagejpeg( $img, $filepath, self::JPEG_QUALITY ) ) {
			throw new Exception( 'imagejpeg falhou ao salvar em ' . $filepath );
		}

		$file_array = array(
			'name'     => $filename,
			'tmp_name' => $filepath,
		);

		require_once ABSPATH . 'wp-admin/includes/image.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';

		$attachment_id = media_handle_sideload(
			$file_array,
			0,
			sanitize_text_field( $title )
		);

		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $filepath ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			throw new Exception( 'media_handle_sideload: ' . $attachment_id->get_error_message() );
		}

		return wp_get_attachment_url( $attachment_id );
	}
}
