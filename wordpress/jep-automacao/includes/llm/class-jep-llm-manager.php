<?php
/**
 * JEP LLM Manager
 *
 * Manages a pool of LLM providers with priority-based fallback, usage tracking,
 * and monthly quota enforcement.
 *
 * @package JEP_Automacao_Editorial
 * @subpackage LLM
 * @since 2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class JEP_LLM_Manager
 *
 * Provides a unified interface to multiple LLM backends (OpenRouter, OpenAI-compatible,
 * Ollama). Providers are tried in ascending priority order; the first successful
 * response is returned and its usage is logged.
 */
class JEP_LLM_Manager {

	/**
	 * Fully-qualified table name for LLM providers.
	 *
	 * @var string
	 */
	private $table_providers;

	/**
	 * Fully-qualified table name for LLM usage log.
	 *
	 * @var string
	 */
	private $table_usage;

	/**
	 * In-memory cache of active providers for the current request.
	 *
	 * @var array|null
	 */
	private $active_providers = null;

	/**
	 * Constructor. Resolves table names against the current $wpdb prefix.
	 */
	public function __construct() {
		global $wpdb;
		$this->table_providers = $wpdb->prefix . 'jep_llm_providers';
		$this->table_usage     = $wpdb->prefix . 'jep_llm_usage';
	}

	// -------------------------------------------------------------------------
	// Public API
	// -------------------------------------------------------------------------

	/**
	 * Send a completion request, trying providers in priority order.
	 *
	 * @param string $prompt       The user-facing prompt text.
	 * @param string $system_prompt Optional system/context prompt.
	 * @param string $prompt_type  Logical category for usage logging (e.g. 'rewrite').
	 * @param int    $max_tokens   Maximum tokens requested from the model.
	 *
	 * @return string The model's text response.
	 *
	 * @throws Exception When all configured providers have been exhausted.
	 */
	public function complete( $prompt, $system_prompt = '', $prompt_type = 'general', $max_tokens = 2048 ) {
		$providers = $this->get_active_providers();

		if ( empty( $providers ) ) {
			throw new Exception( 'Nenhum provedor LLM ativo configurado.' );
		}

		foreach ( $providers as $provider ) {
			// Enforce monthly quota when set.
			if ( (int) $provider['monthly_limit'] > 0
				&& (int) $provider['used_this_month'] >= (int) $provider['monthly_limit'] ) {
				JEP_Logger::warning(
					sprintf(
						'LLM Manager: provedor "%s" atingiu o limite mensal (%d/%d). Pulando.',
						$provider['name'],
						(int) $provider['used_this_month'],
						(int) $provider['monthly_limit']
					),
					'llm'
				);
				continue;
			}

			$start_time = microtime( true );

			try {
				$result = $this->call_provider( $provider, $prompt, $system_prompt, $max_tokens );

				// Persist usage and update counters synchronously.
				$this->log_usage( $provider['id'], $prompt_type, $result, $start_time );
				$this->increment_used_this_month( $provider['id'] );
				$this->touch_last_used( $provider['id'] );

				return $result['text'];

			} catch ( Exception $e ) {
				JEP_Logger::warning(
					sprintf(
						'LLM Manager: provedor "%s" falhou (%s). Tentando próximo.',
						$provider['name'],
						$e->getMessage()
					),
					'llm'
				);
				// Continue to next provider.
			}
		}

		throw new Exception( 'Todos os provedores LLM falharam.' );
	}

	/**
	 * Return all active providers ordered by priority ASC. Results are cached
	 * for the lifetime of the current PHP request.
	 *
	 * @return array Array of provider row arrays.
	 */
	public function get_active_providers() {
		if ( null !== $this->active_providers ) {
			return $this->active_providers;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			"SELECT * FROM {$this->table_providers} WHERE is_active = 1 ORDER BY priority ASC",
			ARRAY_A
		);

		$this->active_providers = $rows ? $rows : array();

		return $this->active_providers;
	}

	/**
	 * Return all providers regardless of active status (for admin UI).
	 *
	 * @return array
	 */
	public function get_providers() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			"SELECT * FROM {$this->table_providers} ORDER BY priority ASC",
			ARRAY_A
		);
	}

	/**
	 * Insert a new provider row.
	 *
	 * @param array $data Associative array of column => value pairs.
	 *
	 * @return int|false New row ID or false on failure.
	 */
	public function add_provider( $data ) {
		global $wpdb;

		$defaults = array(
			'name'            => '',
			'provider_type'   => 'openrouter',
			'api_key'         => '',
			'base_url'        => '',
			'model'           => '',
			'priority'        => 10,
			'monthly_limit'   => 0,
			'used_this_month' => 0,
			'is_active'       => 1,
			'created_at'      => current_time( 'mysql' ),
		);

		$insert = array_merge( $defaults, array_intersect_key( $data, $defaults ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->insert( $this->table_providers, $insert );

		$this->clear_provider_cache();

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Update an existing provider row.
	 *
	 * @param int   $id   Provider ID.
	 * @param array $data Columns to update.
	 *
	 * @return bool
	 */
	public function update_provider( $id, $data ) {
		global $wpdb;

		// Prevent accidental reset of protected counters via this method.
		unset( $data['id'], $data['created_at'] );
		$data['updated_at'] = current_time( 'mysql' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->update(
			$this->table_providers,
			$data,
			array( 'id' => (int) $id )
		);

		$this->clear_provider_cache();

		return false !== $result;
	}

	/**
	 * Delete a provider row permanently.
	 *
	 * @param int $id Provider ID.
	 *
	 * @return bool
	 */
	public function delete_provider( $id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$result = $wpdb->delete(
			$this->table_providers,
			array( 'id' => (int) $id )
		);

		$this->clear_provider_cache();

		return false !== $result;
	}

	/**
	 * Run a smoke-test against a single provider.
	 *
	 * @param int $provider_id Provider ID.
	 *
	 * @return array {
	 *     @type bool   $success     Whether the call succeeded.
	 *     @type int    $latency_ms  Round-trip time in milliseconds.
	 *     @type string $response    Truncated model response or error message.
	 * }
	 */
	public function test_provider( $provider_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$provider = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table_providers} WHERE id = %d", (int) $provider_id ),
			ARRAY_A
		);

		if ( ! $provider ) {
			return array(
				'success'    => false,
				'latency_ms' => 0,
				'response'   => 'Provedor não encontrado.',
			);
		}

		$start = microtime( true );

		try {
			$result     = $this->call_provider( $provider, 'Responda apenas: OK', '', 16 );
			$latency_ms = (int) round( ( microtime( true ) - $start ) * 1000 );

			return array(
				'success'    => true,
				'latency_ms' => $latency_ms,
				'response'   => substr( $result['text'], 0, 200 ),
			);
		} catch ( Exception $e ) {
			$latency_ms = (int) round( ( microtime( true ) - $start ) * 1000 );

			return array(
				'success'    => false,
				'latency_ms' => $latency_ms,
				'response'   => $e->getMessage(),
			);
		}
	}

	/**
	 * Retrieve aggregated usage statistics for the last N days.
	 *
	 * @param int $days Number of days to include.
	 *
	 * @return array Rows grouped by provider_id with aggregated token/cost totals.
	 */
	public function get_usage_summary( $days = 30 ) {
		global $wpdb;

		$since = gmdate( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT
					u.provider_id,
					p.name            AS provider_name,
					p.provider_type,
					COUNT(*)          AS total_calls,
					SUM(u.input_tokens)  AS total_input_tokens,
					SUM(u.output_tokens) AS total_output_tokens,
					SUM(u.estimated_cost_usd) AS total_cost_usd
				FROM {$this->table_usage} u
				LEFT JOIN {$this->table_providers} p ON p.id = u.provider_id
				WHERE u.created_at >= %s
				GROUP BY u.provider_id
				ORDER BY total_calls DESC",
				$since
			),
			ARRAY_A
		);
	}

	/**
	 * Reset the monthly usage counter for all providers.
	 * Intended to be called by a WP-Cron event on the first of each month.
	 *
	 * @return void
	 */
	public function reset_monthly_usage() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			"UPDATE {$this->table_providers} SET used_this_month = 0"
		);

		$this->clear_provider_cache();

		JEP_Logger::info( 'LLM Manager: contadores mensais redefinidos para zero.', 'llm' );
	}

	// -------------------------------------------------------------------------
	// Internal helpers
	// -------------------------------------------------------------------------

	/**
	 * Dispatch an HTTP request to the given provider and return normalised data.
	 *
	 * @param array  $provider     Provider database row.
	 * @param string $prompt       User prompt.
	 * @param string $system_prompt System / context prompt.
	 * @param int    $max_tokens   Max tokens to generate.
	 *
	 * @return array {
	 *     @type string $text          Extracted text from the model.
	 *     @type array  $raw           Full decoded JSON response body.
	 *     @type int    $input_tokens  Reported input token count.
	 *     @type int    $output_tokens Reported output token count.
	 * }
	 *
	 * @throws Exception On HTTP transport error, non-2xx status, or malformed JSON.
	 */
	private function call_provider( $provider, $prompt, $system_prompt, $max_tokens ) {
		$type     = $provider['provider_type'];
		$api_key  = $provider['api_key'];
		$model    = $provider['model'];
		$base_url = rtrim( $provider['base_url'], '/' );

		switch ( $type ) {

			case 'openrouter':
				return $this->call_openrouter( $api_key, $model, $prompt, $system_prompt, $max_tokens );

			case 'openai':
				if ( empty( $base_url ) ) {
					$base_url = 'https://api.openai.com/v1';
				}
				return $this->call_openai_compatible( $base_url, $api_key, $model, $prompt, $system_prompt, $max_tokens );

			case 'ollama':
				if ( empty( $base_url ) ) {
					$base_url = 'http://localhost:11434';
				}
				return $this->call_ollama( $base_url, $model, $prompt, $system_prompt, $max_tokens );

			default:
				throw new Exception( "Tipo de provedor desconhecido: {$type}" );
		}
	}

	/**
	 * Call an OpenRouter endpoint.
	 *
	 * @param string $api_key      OpenRouter API key.
	 * @param string $model        Model identifier.
	 * @param string $prompt       User prompt.
	 * @param string $system_prompt System prompt.
	 * @param int    $max_tokens   Max tokens.
	 *
	 * @return array Normalised result array.
	 *
	 * @throws Exception On transport or API errors.
	 */
	private function call_openrouter( $api_key, $model, $prompt, $system_prompt, $max_tokens ) {
		$endpoint = 'https://openrouter.ai/api/v1/chat/completions';

		$messages = array();
		if ( ! empty( $system_prompt ) ) {
			$messages[] = array( 'role' => 'system', 'content' => $system_prompt );
		}
		$messages[] = array( 'role' => 'user', 'content' => $prompt );

		$body = array(
			'model'      => $model,
			'messages'   => $messages,
			'max_tokens' => (int) $max_tokens,
		);

		$headers = array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $api_key,
			'HTTP-Referer'  => site_url(),
			'X-Title'       => 'JEP Automacao Editorial',
		);

		$raw = $this->do_post( $endpoint, $body, $headers );

		return $this->parse_chat_completions_response( $raw );
	}

	/**
	 * Call any OpenAI-compatible chat completions endpoint.
	 *
	 * @param string $base_url     API base URL.
	 * @param string $api_key      Bearer token.
	 * @param string $model        Model identifier.
	 * @param string $prompt       User prompt.
	 * @param string $system_prompt System prompt.
	 * @param int    $max_tokens   Max tokens.
	 *
	 * @return array Normalised result array.
	 *
	 * @throws Exception On transport or API errors.
	 */
	private function call_openai_compatible( $base_url, $api_key, $model, $prompt, $system_prompt, $max_tokens ) {
		$endpoint = $base_url . '/chat/completions';

		$messages = array();
		if ( ! empty( $system_prompt ) ) {
			$messages[] = array( 'role' => 'system', 'content' => $system_prompt );
		}
		$messages[] = array( 'role' => 'user', 'content' => $prompt );

		$body = array(
			'model'      => $model,
			'messages'   => $messages,
			'max_tokens' => (int) $max_tokens,
		);

		$headers = array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $api_key,
		);

		$raw = $this->do_post( $endpoint, $body, $headers );

		return $this->parse_chat_completions_response( $raw );
	}

	/**
	 * Call an Ollama /api/generate endpoint.
	 *
	 * @param string $base_url     Ollama base URL.
	 * @param string $model        Model identifier.
	 * @param string $prompt       User prompt.
	 * @param string $system_prompt System prompt.
	 * @param int    $max_tokens   Max tokens (mapped to num_predict).
	 *
	 * @return array Normalised result array.
	 *
	 * @throws Exception On transport or API errors.
	 */
	private function call_ollama( $base_url, $model, $prompt, $system_prompt, $max_tokens ) {
		$endpoint = $base_url . '/api/generate';

		$body = array(
			'model'   => $model,
			'prompt'  => $prompt,
			'system'  => $system_prompt,
			'stream'  => false,
			'options' => array( 'num_predict' => (int) $max_tokens ),
		);

		$headers = array( 'Content-Type' => 'application/json' );

		$raw = $this->do_post( $endpoint, $body, $headers );

		if ( ! isset( $raw['response'] ) ) {
			throw new Exception( 'Resposta Ollama malformada: campo "response" ausente.' );
		}

		$text          = trim( $raw['response'] );
		$input_tokens  = isset( $raw['prompt_eval_count'] ) ? (int) $raw['prompt_eval_count'] : (int) ( strlen( $prompt ) / 4 );
		$output_tokens = isset( $raw['eval_count'] )        ? (int) $raw['eval_count']        : (int) ( strlen( $text ) / 4 );

		return array(
			'text'          => $text,
			'raw'           => $raw,
			'input_tokens'  => $input_tokens,
			'output_tokens' => $output_tokens,
		);
	}

	/**
	 * Execute a JSON POST via wp_remote_post and return the decoded body.
	 *
	 * @param string $url     Full URL.
	 * @param array  $body    Data to JSON-encode.
	 * @param array  $headers HTTP headers.
	 *
	 * @return array Decoded JSON body.
	 *
	 * @throws Exception On WP_Error, non-2xx response, or JSON decode failure.
	 */
	private function do_post( $url, $body, $headers ) {
		$args = array(
			'method'  => 'POST',
			'headers' => $headers,
			'body'    => wp_json_encode( $body ),
			'timeout' => 60,
		);

		$response = wp_remote_post( $url, $args );

		if ( is_wp_error( $response ) ) {
			throw new Exception( 'Erro HTTP: ' . $response->get_error_message() );
		}

		$http_code = wp_remote_retrieve_response_code( $response );
		$raw_body  = wp_remote_retrieve_body( $response );

		if ( $http_code < 200 || $http_code >= 300 ) {
			$error_hint = '';
			$decoded    = json_decode( $raw_body, true );
			if ( isset( $decoded['error']['message'] ) ) {
				$error_hint = ' — ' . $decoded['error']['message'];
			} elseif ( isset( $decoded['error'] ) && is_string( $decoded['error'] ) ) {
				$error_hint = ' — ' . $decoded['error'];
			}
			throw new Exception( "HTTP {$http_code}{$error_hint}" );
		}

		$decoded = json_decode( $raw_body, true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			throw new Exception( 'Falha ao decodificar JSON da resposta: ' . json_last_error_msg() );
		}

		return $decoded;
	}

	/**
	 * Extract text and token counts from an OpenAI-style chat completions response.
	 *
	 * @param array $raw Decoded JSON response body.
	 *
	 * @return array Normalised result array.
	 *
	 * @throws Exception When the expected response shape is absent.
	 */
	private function parse_chat_completions_response( $raw ) {
		if ( ! isset( $raw['choices'][0]['message']['content'] ) ) {
			$error = isset( $raw['error']['message'] ) ? $raw['error']['message'] : 'Estrutura de resposta inesperada.';
			throw new Exception( 'Resposta da API inválida: ' . $error );
		}

		$text          = trim( $raw['choices'][0]['message']['content'] );
		$input_tokens  = isset( $raw['usage']['prompt_tokens'] )     ? (int) $raw['usage']['prompt_tokens']     : (int) ( strlen( $text ) / 4 );
		$output_tokens = isset( $raw['usage']['completion_tokens'] ) ? (int) $raw['usage']['completion_tokens'] : (int) ( strlen( $text ) / 4 );

		return array(
			'text'          => $text,
			'raw'           => $raw,
			'input_tokens'  => $input_tokens,
			'output_tokens' => $output_tokens,
		);
	}

	/**
	 * Persist a usage record for billing/analytics.
	 *
	 * @param int   $provider_id   Provider row ID.
	 * @param string $prompt_type  Logical category.
	 * @param array  $result       Normalised result from call_provider().
	 * @param float  $start_time   microtime(true) value before the API call.
	 *
	 * @return void
	 */
	private function log_usage( $provider_id, $prompt_type, $result, $start_time ) {
		global $wpdb;

		$latency_ms    = (int) round( ( microtime( true ) - $start_time ) * 1000 );
		$input_tokens  = isset( $result['input_tokens'] )  ? (int) $result['input_tokens']  : 0;
		$output_tokens = isset( $result['output_tokens'] ) ? (int) $result['output_tokens'] : 0;

		// Rough cost estimate: $0.50 per 1 M tokens (adjust per provider if needed).
		$estimated_cost = ( $input_tokens + $output_tokens ) * 0.0000005;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->insert(
			$this->table_usage,
			array(
				'provider_id'        => (int) $provider_id,
				'prompt_type'        => sanitize_text_field( $prompt_type ),
				'input_tokens'       => $input_tokens,
				'output_tokens'      => $output_tokens,
				'latency_ms'         => $latency_ms,
				'estimated_cost_usd' => round( $estimated_cost, 8 ),
				'created_at'         => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Atomically increment the used_this_month counter for a provider.
	 *
	 * @param int $provider_id Provider ID.
	 *
	 * @return void
	 */
	private function increment_used_this_month( $provider_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->table_providers} SET used_this_month = used_this_month + 1 WHERE id = %d",
				(int) $provider_id
			)
		);
	}

	/**
	 * Update the last_used timestamp for a provider.
	 *
	 * @param int $provider_id Provider ID.
	 *
	 * @return void
	 */
	private function touch_last_used( $provider_id ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update(
			$this->table_providers,
			array( 'last_used' => current_time( 'mysql' ) ),
			array( 'id' => (int) $provider_id )
		);
	}

	/**
	 * Clear the in-memory provider cache so the next call re-queries the DB.
	 *
	 * @return void
	 */
	private function clear_provider_cache() {
		$this->active_providers = null;
	}
}
