<?php
/**
 * Classe principal do plugin JEP Automacao.
 *
 * @package JEP_Automacao
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class JEP_Automacao
 */
class JEP_Automacao {

	/**
	 * Instancia singleton.
	 *
	 * @var JEP_Automacao
	 */
	private static $instance = null;

	/**
	 * Modulos carregados.
	 *
	 * @var array
	 */
	public $modules = array();

	/**
	 * Retorna a instancia singleton.
	 *
	 * @return JEP_Automacao
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Construtor.
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
	}

	/**
	 * Carrega as classes de dependencia.
	 */
	private function load_dependencies() {
		require_once JEP_AUTOMACAO_PLUGIN_DIR . 'includes/class-jep-installer.php';
		require_once JEP_AUTOMACAO_PLUGIN_DIR . 'includes/class-jep-settings.php';
		require_once JEP_AUTOMACAO_PLUGIN_DIR . 'includes/class-jep-logger.php';
		require_once JEP_AUTOMACAO_PLUGIN_DIR . 'includes/class-jep-webhook-sender.php';
		require_once JEP_AUTOMACAO_PLUGIN_DIR . 'includes/class-jep-rest-api.php';
		require_once JEP_AUTOMACAO_PLUGIN_DIR . 'includes/class-jep-admin.php';
	}

	/**
	 * Registra os hooks principais.
	 */
	private function init_hooks() {
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Inicializa modulos.
		$this->modules['settings']        = new JEP_Settings();
		$this->modules['logger']          = new JEP_Logger();
		$this->modules['webhook_sender']  = new JEP_Webhook_Sender();
		$this->modules['rest_api']        = new JEP_Rest_Api();

		if ( is_admin() ) {
			$this->modules['admin'] = new JEP_Admin();
		}
	}

	/**
	 * Carrega as traducoes.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'jep-automacao',
			false,
			dirname( JEP_AUTOMACAO_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Acesso rapido ao modulo de configuracoes.
	 *
	 * @return JEP_Settings
	 */
	public function settings() {
		return $this->modules['settings'];
	}

	/**
	 * Acesso rapido ao logger.
	 *
	 * @return JEP_Logger
	 */
	public function logger() {
		return $this->modules['logger'];
	}
}
