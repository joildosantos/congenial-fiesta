<?php
/**
 * Gerenciamento de configuracoes do plugin.
 *
 * @package JEP_Automacao
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class JEP_Settings
 */
class JEP_Settings {

	/**
	 * Prefixo das opcoes no banco.
	 */
	const PREFIX = 'jep_automacao_';

	/**
	 * Cache interno das opcoes.
	 *
	 * @var array
	 */
	private $cache = array();

	/**
	 * Retorna o valor de uma configuracao.
	 *
	 * @param string $key     Chave da opcao (sem prefixo).
	 * @param mixed  $default Valor padrao.
	 * @return mixed
	 */
	public function get( $key, $default = '' ) {
		if ( isset( $this->cache[ $key ] ) ) {
			return $this->cache[ $key ];
		}

		$value               = get_option( self::PREFIX . $key, $default );
		$this->cache[ $key ] = $value;

		return $value;
	}

	/**
	 * Salva o valor de uma configuracao.
	 *
	 * @param string $key   Chave da opcao (sem prefixo).
	 * @param mixed  $value Valor a salvar.
	 * @return bool
	 */
	public function set( $key, $value ) {
		unset( $this->cache[ $key ] );
		return update_option( self::PREFIX . $key, $value );
	}

	/**
	 * Retorna a URL do webhook do n8n.
	 *
	 * @return string
	 */
	public function get_n8n_webhook_url() {
		return $this->get( 'n8n_webhook_url' );
	}

	/**
	 * Retorna o token secreto para validar requisicoes do n8n.
	 *
	 * @return string
	 */
	public function get_n8n_secret_token() {
		return $this->get( 'n8n_secret_token' );
	}

	/**
	 * Verifica se o webhook ao publicar esta habilitado.
	 *
	 * @return bool
	 */
	public function is_webhook_on_publish_enabled() {
		return (bool) $this->get( 'enable_webhook_on_publish', '1' );
	}

	/**
	 * Verifica se a REST API esta habilitada.
	 *
	 * @return bool
	 */
	public function is_rest_api_enabled() {
		return (bool) $this->get( 'enable_rest_api', '1' );
	}

	/**
	 * Retorna os tipos de post monitorados.
	 *
	 * @return array
	 */
	public function get_post_types_to_watch() {
		$types = $this->get( 'post_types_to_watch', array( 'post' ) );
		return is_array( $types ) ? $types : array( 'post' );
	}

	/**
	 * Verifica se a configuracao do n8n esta completa.
	 *
	 * @return bool
	 */
	public function is_n8n_configured() {
		return ! empty( $this->get_n8n_webhook_url() );
	}

	/**
	 * Retorna todas as configuracoes para exibicao no admin (mascara segredos).
	 *
	 * @return array
	 */
	public function get_all_for_display() {
		return array(
			'n8n'        => array(
				'webhook_url'  => $this->get( 'n8n_webhook_url' ),
				'secret_token' => $this->mask( $this->get( 'n8n_secret_token' ) ),
			),
			'openrouter' => array(
				'api_key' => $this->mask( $this->get( 'openrouter_api_key' ) ),
			),
			'telegram'   => array(
				'bot_token'      => $this->mask( $this->get( 'telegram_bot_token' ) ),
				'editor_chat_id' => $this->get( 'telegram_editor_chat_id' ),
				'channel_id'     => $this->get( 'telegram_channel_id' ),
			),
			'facebook'   => array(
				'page_id'      => $this->get( 'facebook_page_id' ),
				'access_token' => $this->mask( $this->get( 'facebook_page_access_token' ) ),
			),
			'instagram'  => array(
				'account_id' => $this->get( 'instagram_account_id' ),
			),
			'evolution'  => array(
				'server_url'    => $this->get( 'evolution_server_url' ),
				'api_key'       => $this->mask( $this->get( 'evolution_api_key' ) ),
				'instance_name' => $this->get( 'evolution_instance_name' ),
				'group_id'      => $this->get( 'whatsapp_group_id' ),
			),
			'unsplash'   => array(
				'access_key' => $this->mask( $this->get( 'unsplash_access_key' ) ),
			),
			'google'     => array(
				'sheets_id' => $this->get( 'google_sheets_id' ),
			),
		);
	}

	/**
	 * Mascara um valor sensivel para exibicao.
	 *
	 * @param string $value Valor a mascarar.
	 * @return string
	 */
	private function mask( $value ) {
		if ( empty( $value ) ) {
			return '';
		}
		$len = strlen( $value );
		if ( $len <= 8 ) {
			return str_repeat( '*', $len );
		}
		return substr( $value, 0, 4 ) . str_repeat( '*', $len - 8 ) . substr( $value, -4 );
	}
}
