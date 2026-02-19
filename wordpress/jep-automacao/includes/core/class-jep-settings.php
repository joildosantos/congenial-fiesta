<?php
/**
 * Gerenciador de configuracoes do plugin JEP Automacao v2.
 *
 * Centraliza a leitura e escrita de todas as opcoes do plugin,
 * com cache interno para evitar multiplas chamadas ao banco de dados.
 *
 * @package JEP_Automacao
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class JEP_Settings
 */
class JEP_Settings {

	/**
	 * Prefixo padrao de todas as opcoes do plugin no wp_options.
	 *
	 * @var string
	 */
	const OPTION_PREFIX = 'jep_automacao_';

	/**
	 * Cache interno de opcoes lidas nesta requisicao.
	 *
	 * @var array
	 */
	private $cache = array();

	// -------------------------------------------------------------------------
	// Metodos genericos
	// -------------------------------------------------------------------------

	/**
	 * Retorna o valor de uma opcao do plugin.
	 *
	 * @param string $key     Chave sem o prefixo 'jep_automacao_'.
	 * @param mixed  $default Valor padrao caso a opcao nao exista.
	 * @return mixed
	 */
	public function get( $key, $default = '' ) {
		if ( array_key_exists( $key, $this->cache ) ) {
			return $this->cache[ $key ];
		}

		$value = get_option( self::OPTION_PREFIX . $key, $default );
		$this->cache[ $key ] = $value;
		return $value;
	}

	/**
	 * Salva o valor de uma opcao do plugin e limpa o cache interno.
	 *
	 * @param string $key   Chave sem o prefixo 'jep_automacao_'.
	 * @param mixed  $value Valor a ser armazenado.
	 * @return bool True se o valor foi atualizado, false caso contrario.
	 */
	public function set( $key, $value ) {
		unset( $this->cache[ $key ] );
		return update_option( self::OPTION_PREFIX . $key, $value );
	}

	// -------------------------------------------------------------------------
	// Telegram
	// -------------------------------------------------------------------------

	/**
	 * Retorna o token do bot Telegram.
	 *
	 * @return string
	 */
	public function get_telegram_bot_token() {
		return $this->get( 'telegram_bot_token', '' );
	}

	/**
	 * Retorna o Chat ID do editor no Telegram.
	 *
	 * @return string
	 */
	public function get_telegram_editor_chat_id() {
		return $this->get( 'telegram_editor_chat_id', '' );
	}

	/**
	 * Retorna o ID do canal Telegram de publicacao.
	 *
	 * @return string
	 */
	public function get_telegram_channel_id() {
		return $this->get( 'telegram_channel_id', '' );
	}

	/**
	 * Retorna o secret do webhook Telegram.
	 *
	 * @return string
	 */
	public function get_telegram_webhook_secret() {
		return $this->get( 'telegram_webhook_secret', '' );
	}

	/**
	 * Verifica se o Telegram esta minimamente configurado.
	 * Requer bot_token e editor_chat_id preenchidos.
	 *
	 * @return bool
	 */
	public function is_telegram_configured() {
		return ! empty( $this->get_telegram_bot_token() )
			&& ! empty( $this->get_telegram_editor_chat_id() );
	}

	// -------------------------------------------------------------------------
	// Instagram / Facebook
	// -------------------------------------------------------------------------

	/**
	 * Retorna o Account ID do Instagram.
	 *
	 * @return string
	 */
	public function get_instagram_account_id() {
		return $this->get( 'instagram_account_id', '' );
	}

	/**
	 * Retorna o Page Access Token do Facebook.
	 *
	 * @return string
	 */
	public function get_facebook_page_access_token() {
		return $this->get( 'facebook_page_access_token', '' );
	}

	/**
	 * Verifica se a publicacao no Instagram esta habilitada.
	 *
	 * @return bool
	 */
	public function is_instagram_enabled() {
		return '1' === $this->get( 'enable_instagram_publishing', '0' );
	}

	/**
	 * Verifica se a aprovacao de legenda para Instagram e obrigatoria.
	 *
	 * @return bool
	 */
	public function is_instagram_caption_approval_required() {
		return '1' === $this->get( 'instagram_require_caption_approval', '1' );
	}

	/**
	 * Retorna o template de legenda para o Instagram.
	 *
	 * @return string
	 */
	public function get_instagram_caption_template() {
		$default = "{titulo}\n\n{excerpt}\n\n{url}\n\n{hashtags}";
		return $this->get( 'instagram_caption_template', $default );
	}

	// -------------------------------------------------------------------------
	// Imagens
	// -------------------------------------------------------------------------

	/**
	 * Retorna a URL do logotipo usado na geracao de imagens.
	 *
	 * @return string
	 */
	public function get_image_logo_url() {
		return $this->get( 'image_logo_url', '' );
	}

	/**
	 * Retorna a cor de destaque usada nas imagens geradas.
	 *
	 * @return string Cor no formato hex (ex: #E63027).
	 */
	public function get_image_accent_color() {
		return $this->get( 'image_accent_color', '#E63027' );
	}

	/**
	 * Retorna o texto do badge exibido nas imagens geradas.
	 *
	 * @return string
	 */
	public function get_image_badge_text() {
		return $this->get( 'image_badge_text', 'DESDE 2007' );
	}

	/**
	 * Verifica se a geracao de imagens via IA esta habilitada.
	 *
	 * @return bool
	 */
	public function is_ai_images_enabled() {
		return '1' === $this->get( 'enable_ai_images', '0' );
	}

	/**
	 * Retorna o provedor de imagens IA selecionado.
	 *
	 * @return string Slug do provedor (ex: 'pollinations', 'openai').
	 */
	public function get_ai_image_provider() {
		return $this->get( 'ai_image_provider', 'pollinations' );
	}

	// -------------------------------------------------------------------------
	// LLM / OpenAI
	// -------------------------------------------------------------------------

	/**
	 * Retorna a chave de API do OpenAI.
	 *
	 * @return string
	 */
	public function get_openai_api_key() {
		return $this->get( 'openai_api_key', '' );
	}

	// -------------------------------------------------------------------------
	// REST API
	// -------------------------------------------------------------------------

	/**
	 * Retorna o secret da REST API interna do plugin.
	 *
	 * @return string
	 */
	public function get_rest_api_secret() {
		return $this->get( 'rest_api_secret', '' );
	}

	// -------------------------------------------------------------------------
	// Crons
	// -------------------------------------------------------------------------

	/**
	 * Mapa de nomes de cron para chaves de opcao.
	 *
	 * @var array
	 */
	private static $cron_time_map = array(
		'daily'     => 'cron_daily_time',
		'cold'      => 'cron_cold_time',
		'research'  => 'cron_research_day',
		'discovery' => 'cron_discovery_time',
		'summary'   => 'cron_summary_time',
	);

	/**
	 * Mapa de nomes de cron para chaves de habilitacao.
	 *
	 * @var array
	 */
	private static $cron_enable_map = array(
		'daily'     => 'enable_cron_daily',
		'cold'      => 'enable_cron_cold',
		'research'  => 'enable_cron_research',
		'discovery' => 'enable_cron_discovery',
		'summary'   => 'enable_cron_summary',
	);

	/**
	 * Retorna o horario/dia configurado para um cron especifico.
	 *
	 * @param string $cron_name Nome do cron: daily, cold, research, discovery, summary.
	 * @return string Horario (HH:MM) ou dia da semana, dependendo do cron.
	 */
	public function get_cron_time( $cron_name ) {
		if ( ! isset( self::$cron_time_map[ $cron_name ] ) ) {
			return '';
		}
		$option_key = self::$cron_time_map[ $cron_name ];
		$default    = ( 'research' === $cron_name ) ? 'monday' : '06:00';
		return $this->get( $option_key, $default );
	}

	/**
	 * Verifica se um cron especifico esta habilitado.
	 *
	 * @param string $cron_name Nome do cron: daily, cold, research, discovery, summary.
	 * @return bool
	 */
	public function is_cron_enabled( $cron_name ) {
		if ( ! isset( self::$cron_enable_map[ $cron_name ] ) ) {
			return false;
		}
		$option_key = self::$cron_enable_map[ $cron_name ];
		return '1' === $this->get( $option_key, '1' );
	}

	// -------------------------------------------------------------------------
	// Utilitarios
	// -------------------------------------------------------------------------

	/**
	 * Mascara uma string sensivel para exibicao segura na interface.
	 * Exibe os primeiros 4 e os ultimos 4 caracteres, restante com '*'.
	 *
	 * @param string $value Valor a ser mascarado.
	 * @return string Valor mascarado.
	 */
	public function mask( $value ) {
		if ( empty( $value ) ) {
			return '';
		}

		$length = strlen( $value );

		if ( $length <= 8 ) {
			// String muito curta: oculta tudo.
			return str_repeat( '*', $length );
		}

		$visible_start = substr( $value, 0, 4 );
		$visible_end   = substr( $value, -4 );
		$masked_middle = str_repeat( '*', max( 1, $length - 8 ) );

		return $visible_start . $masked_middle . $visible_end;
	}

	/**
	 * Limpa o cache interno de opcoes.
	 * Util em testes ou apos multiplos set() encadeados.
	 */
	public function flush_cache() {
		$this->cache = array();
	}

	/**
	 * Retorna todas as opcoes do plugin como array associativo (sem prefixo nas chaves).
	 * Util para exportacao de diagnostico.
	 *
	 * @return array
	 */
	public function get_all() {
		global $wpdb;

		$prefix  = self::OPTION_PREFIX;
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( $prefix ) . '%'
			),
			ARRAY_A
		);

		$options = array();
		foreach ( $results as $row ) {
			$key             = substr( $row['option_name'], strlen( $prefix ) );
			$options[ $key ] = $row['option_value'];
		}

		return $options;
	}
}
