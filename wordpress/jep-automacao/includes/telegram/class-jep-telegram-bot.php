<?php
/**
 * JEP Telegram Bot - Low-level Telegram Bot API wrapper.
 *
 * @package JEP_Automacao_Editorial
 * @subpackage Telegram
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class JEP_Telegram_Bot
 *
 * Provides a low-level wrapper around the Telegram Bot API, handling all HTTP
 * communication, response parsing, and convenience methods for sending messages,
 * photos, and managing webhooks.
 *
 * @since 2.0.0
 */
class JEP_Telegram_Bot {

	/**
	 * Telegram Bot API token.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	private $token;

	/**
	 * Base URL for Telegram Bot API requests.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	private $api_base = 'https://api.telegram.org/bot';

	/**
	 * Constructor. Intentionally empty — token is loaded lazily to avoid
	 * circular initialization during plugin bootstrap (jep_automacao() may
	 * not be fully initialised when this object is constructed).
	 *
	 * @since 2.0.0
	 */
	public function __construct() {}

	/**
	 * Returns the bot token, loading it from settings on first access.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	private function get_token() {
		if ( null === $this->token ) {
			$this->token = jep_automacao()->settings()->get_telegram_bot_token();
		}
		return $this->token;
	}

	/**
	 * Performs a POST request to the Telegram Bot API.
	 *
	 * @since 2.0.0
	 *
	 * @param string $method The Telegram API method name (e.g. 'sendMessage').
	 * @param array  $params Associative array of parameters to send with the request.
	 *
	 * @throws RuntimeException When the HTTP request fails or Telegram returns an error.
	 *
	 * @return mixed The decoded 'result' field from the Telegram API response.
	 */
	public function api_call( $method, $params = [] ) {
		$url = $this->api_base . $this->get_token() . '/' . $method;

		$args = [
			'body'    => wp_json_encode( $params ),
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'timeout' => 30,
		];

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			$error_message = $response->get_error_message();
			jep_automacao()->logger()->error( 'telegram', sprintf( '[TelegramBot] HTTP error on method "%s": %s', $method, $error_message ) );
			throw new RuntimeException(
				sprintf( 'Telegram API HTTP error (%s): %s', $method, $error_message )
			);
		}

		$http_code = wp_remote_retrieve_response_code( $response );
		$body      = wp_remote_retrieve_body( $response );
		$decoded   = json_decode( $body, true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			jep_automacao()->logger()->error( 'telegram', sprintf( '[TelegramBot] Invalid JSON response on method "%s": %s', $method, $body ) );
			throw new RuntimeException(
				sprintf( 'Telegram API returned invalid JSON for method "%s".', $method )
			);
		}

		if ( empty( $decoded['ok'] ) ) {
			$tg_error = isset( $decoded['description'] ) ? $decoded['description'] : 'Unknown Telegram error';
			$tg_code  = isset( $decoded['error_code'] ) ? $decoded['error_code'] : $http_code;
			jep_automacao()->logger()->error( 'telegram', sprintf(
					'[TelegramBot] API error on method "%s" (code %d): %s',
					$method,
					$tg_code,
					$tg_error
				) );
			throw new RuntimeException(
				sprintf( 'Telegram API error [%d] on method "%s": %s', $tg_code, $method, $tg_error )
			);
		}

		return isset( $decoded['result'] ) ? $decoded['result'] : true;
	}

	/**
	 * Sends a text message to a Telegram chat.
	 *
	 * @since 2.0.0
	 *
	 * @param int|string $chat_id      Unique identifier of the target chat.
	 * @param string     $text         Text of the message to be sent.
	 * @param string     $parse_mode   Optional. Formatting mode ('Markdown', 'MarkdownV2', 'HTML'). Default 'Markdown'.
	 * @param array|null $reply_markup Optional. Inline keyboard markup array.
	 *
	 * @return mixed Telegram API result.
	 */
	public function send_message( $chat_id, $text, $parse_mode = 'Markdown', $reply_markup = null ) {
		$params = [
			'chat_id'    => $chat_id,
			'text'       => $text,
			'parse_mode' => $parse_mode,
		];

		if ( null !== $reply_markup ) {
			$params['reply_markup'] = wp_json_encode( $reply_markup );
		}

		return $this->api_call( 'sendMessage', $params );
	}

	/**
	 * Sends a photo to a Telegram chat with an optional caption.
	 *
	 * @since 2.0.0
	 *
	 * @param int|string $chat_id      Unique identifier of the target chat.
	 * @param string     $photo_url    URL of the photo to send.
	 * @param string     $caption      Optional. Caption for the photo. Default ''.
	 * @param string     $parse_mode   Optional. Formatting mode. Default 'Markdown'.
	 * @param array|null $reply_markup Optional. Inline keyboard markup array.
	 *
	 * @return mixed Telegram API result.
	 */
	public function send_photo( $chat_id, $photo_url, $caption = '', $parse_mode = 'Markdown', $reply_markup = null ) {
		$params = [
			'chat_id'    => $chat_id,
			'photo'      => $photo_url,
			'caption'    => $caption,
			'parse_mode' => $parse_mode,
		];

		if ( null !== $reply_markup ) {
			$params['reply_markup'] = wp_json_encode( $reply_markup );
		}

		return $this->api_call( 'sendPhoto', $params );
	}

	/**
	 * Edits the text of an existing message in a chat.
	 *
	 * @since 2.0.0
	 *
	 * @param int|string $chat_id      Unique identifier of the target chat.
	 * @param int        $message_id   Identifier of the message to edit.
	 * @param string     $text         New text for the message.
	 * @param string     $parse_mode   Optional. Formatting mode. Default 'Markdown'.
	 * @param array|null $reply_markup Optional. Inline keyboard markup array.
	 *
	 * @return mixed Telegram API result.
	 */
	public function edit_message_text( $chat_id, $message_id, $text, $parse_mode = 'Markdown', $reply_markup = null ) {
		$params = [
			'chat_id'    => $chat_id,
			'message_id' => $message_id,
			'text'       => $text,
			'parse_mode' => $parse_mode,
		];

		if ( null !== $reply_markup ) {
			$params['reply_markup'] = wp_json_encode( $reply_markup );
		}

		return $this->api_call( 'editMessageText', $params );
	}

	/**
	 * Sends an answer to a callback query from an inline keyboard.
	 *
	 * @since 2.0.0
	 *
	 * @param string $callback_query_id Unique identifier for the callback query.
	 * @param string $text              Optional. Notification text to show the user. Default ''.
	 * @param bool   $show_alert        Optional. If true, shows an alert instead of a notification. Default false.
	 *
	 * @return mixed Telegram API result.
	 */
	public function answer_callback_query( $callback_query_id, $text = '', $show_alert = false ) {
		$params = [
			'callback_query_id' => $callback_query_id,
			'text'              => $text,
			'show_alert'        => (bool) $show_alert,
		];

		return $this->api_call( 'answerCallbackQuery', $params );
	}

	/**
	 * Registers a webhook URL with the Telegram Bot API.
	 *
	 * @since 2.0.0
	 *
	 * @param string $url          HTTPS URL where Telegram will send updates.
	 * @param string $secret_token Optional. Secret token to validate webhook requests. Default ''.
	 *
	 * @return mixed Telegram API result.
	 */
	public function set_webhook( $url, $secret_token = '' ) {
		$params = [
			'url'             => $url,
			'allowed_updates' => [ 'message', 'callback_query' ],
		];

		if ( ! empty( $secret_token ) ) {
			$params['secret_token'] = $secret_token;
		}

		return $this->api_call( 'setWebhook', $params );
	}

	/**
	 * Builds a Telegram inline keyboard from a structured button array.
	 *
	 * @since 2.0.0
	 *
	 * @param array $buttons Two-dimensional array of button rows. Each row is an array of button
	 *                       definitions with 'text' and 'callback_data' keys. Example:
	 *                       [
	 *                           [ ['text' => 'Button A', 'callback_data' => 'btn_a'] ],
	 *                           [ ['text' => 'Button B', 'callback_data' => 'btn_b'] ],
	 *                       ]
	 *
	 * @return array Associative array formatted for Telegram's inline_keyboard reply markup.
	 */
	public function make_inline_keyboard( $buttons ) {
		return [
			'inline_keyboard' => $buttons,
		];
	}

	/**
	 * Builds a standardised approval keyboard for A/B title selection and rejection.
	 *
	 * @since 2.0.0
	 *
	 * @param int    $approval_id Unique identifier of the pending approval record.
	 * @param string $title_a     Full title for variant A.
	 * @param string $title_b     Full title for variant B.
	 *
	 * @return array Inline keyboard array with approve A, approve B, and reject buttons.
	 */
	public function build_approval_keyboard( $approval_id, $title_a, $title_b ) {
		$max_title_len = 20;

		$short_a = mb_strlen( $title_a ) > $max_title_len
			? mb_substr( $title_a, 0, $max_title_len ) . '…'
			: $title_a;

		$short_b = mb_strlen( $title_b ) > $max_title_len
			? mb_substr( $title_b, 0, $max_title_len ) . '…'
			: $title_b;

		$buttons = [
			[
				[
					'text'          => '✅ ' . $short_a . ' (A)',
					'callback_data' => 'approve_' . $approval_id . '_a',
				],
			],
			[
				[
					'text'          => '✅ ' . $short_b . ' (B)',
					'callback_data' => 'approve_' . $approval_id . '_b',
				],
			],
			[
				[
					'text'          => '❌ Rejeitar',
					'callback_data' => 'reject_' . $approval_id,
				],
			],
		];

		return $this->make_inline_keyboard( $buttons );
	}

	/**
	 * Sends a text message to the configured editor chat.
	 *
	 * Convenience shortcut wrapping send_message() with the editor's chat ID.
	 *
	 * @since 2.0.0
	 *
	 * @param string     $text         Message text.
	 * @param array|null $reply_markup Optional. Inline keyboard markup array.
	 *
	 * @return mixed Telegram API result.
	 */
	public function send_to_editor( $text, $reply_markup = null ) {
		$editor_chat_id = jep_automacao()->settings()->get_telegram_editor_chat_id();

		return $this->send_message( $editor_chat_id, $text, 'Markdown', $reply_markup );
	}

	/**
	 * Sends a text message to the configured Telegram channel.
	 *
	 * Convenience shortcut wrapping send_message() with the channel ID.
	 *
	 * @since 2.0.0
	 *
	 * @param string     $text         Message text.
	 * @param array|null $reply_markup Optional. Inline keyboard markup array.
	 *
	 * @return mixed Telegram API result.
	 */
	public function send_to_channel( $text, $reply_markup = null ) {
		$channel_id = jep_automacao()->settings()->get_telegram_channel_id();

		return $this->send_message( $channel_id, $text, 'Markdown', $reply_markup );
	}

	/**
	 * Sends a photo with caption to the configured Telegram channel.
	 *
	 * Convenience shortcut wrapping send_photo() with the channel ID.
	 *
	 * @since 2.0.0
	 *
	 * @param string     $photo_url    URL of the photo to send.
	 * @param string     $caption      Caption text.
	 * @param array|null $reply_markup Optional. Inline keyboard markup array.
	 *
	 * @return mixed Telegram API result.
	 */
	public function send_photo_to_channel( $photo_url, $caption, $reply_markup = null ) {
		$channel_id = jep_automacao()->settings()->get_telegram_channel_id();

		return $this->send_photo( $channel_id, $photo_url, $caption, 'Markdown', $reply_markup );
	}

	/**
	 * Calls the Telegram getMe API method to verify the bot token.
	 *
	 * @since 2.0.3
	 *
	 * @return array|WP_Error Bot info array on success, WP_Error on failure.
	 */
	public function get_me() {
		try {
			return $this->api_call( 'getMe' );
		} catch ( Exception $e ) {
			return new WP_Error( 'telegram_get_me_failed', $e->getMessage() );
		}
	}

	/**
	 * Checks whether the bot is fully configured with a token and editor chat ID.
	 *
	 * @since 2.0.0
	 *
	 * @return bool True if both the bot token and editor chat ID are non-empty, false otherwise.
	 */
	public function is_configured() {
		$editor_chat_id = jep_automacao()->settings()->get_telegram_editor_chat_id();

		return ! empty( $this->get_token() ) && ! empty( $editor_chat_id );
	}
}
