<?php
/**
 * Classe principal do plugin JEP Automacao v2.
 *
 * @package JEP_Automacao
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class JEP_Automacao
 *
 * Ponto de entrada central do plugin. Instancia e conecta todos os modulos.
 */
class JEP_Automacao {

	/**
	 * Instancia singleton.
	 *
	 * @var JEP_Automacao|null
	 */
	private static $instance = null;

	/**
	 * Array de modulos carregados.
	 *
	 * @var array
	 */
	public $modules = array();

	/**
	 * Retorna a instancia singleton.
	 *
	 * Atribui self::$instance ANTES de chamar load_modules() para evitar
	 * recursao infinita quando modulos chamam jep_automacao() durante
	 * o proprio processo de inicializacao.
	 *
	 * @return JEP_Automacao
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->load_modules();
		}
		return self::$instance;
	}

	/**
	 * Construtor privado. Registra apenas hooks basicos; modulos sao
	 * carregados em instance() apos a atribuicao do singleton.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Instancia todos os modulos do plugin na ordem correta de dependencia.
	 */
	private function load_modules() {
		// --- Core ---
		$this->modules['settings']  = new JEP_Settings();
		$this->modules['logger']    = new JEP_Logger();
		$this->modules['scheduler'] = new JEP_Scheduler();

		// --- LLM ---
		$this->modules['llm']      = new JEP_LLM_Manager();
		$this->modules['rewriter'] = new JEP_Content_Rewriter();

		// --- Content ---
		$this->modules['cold_content']   = new JEP_Cold_Content();
		$this->modules['topic_research'] = new JEP_Topic_Research();
		$this->modules['rss_manager']    = new JEP_RSS_Manager();
		$this->modules['daily_content']  = new JEP_Daily_Content();

		// --- Telegram ---
		$this->modules['telegram_bot']       = new JEP_Telegram_Bot();
		$this->modules['telegram_approval']  = new JEP_Telegram_Approval();
		$this->modules['telegram_publisher'] = new JEP_Telegram_Publisher();

		// --- Distribution ---
		$this->modules['instagram'] = new JEP_Instagram_Publisher();

		// --- Quality ---
		$this->modules['source_discovery'] = new JEP_Source_Discovery();
		$this->modules['prompt_evaluator'] = new JEP_Prompt_Evaluator();

		// --- Image (static utility classes — loaded via autoloader, no instantiation needed) ---
		class_exists( 'JEP_Image_GD' );
		class_exists( 'JEP_Image_AI' );

		// --- API ---
		$this->modules['rest_api'] = new JEP_Rest_Api();

		// --- Admin (only in back-end context) ---
		if ( is_admin() ) {
			$this->modules['admin'] = new JEP_Admin();
		}
	}

	/**
	 * Carrega o textdomain do plugin para internacionalizacao.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'jep-automacao',
			false,
			dirname( JEP_AUTOMACAO_PLUGIN_BASENAME ) . '/languages'
		);
	}

	// -------------------------------------------------------------------------
	// Atalhos de acesso rapido aos modulos mais usados
	// -------------------------------------------------------------------------

	/**
	 * Retorna o modulo de configuracoes.
	 *
	 * @return JEP_Settings
	 */
	public function settings() {
		return $this->modules['settings'];
	}

	/**
	 * Retorna o modulo de logger.
	 *
	 * @return JEP_Logger
	 */
	public function logger() {
		return $this->modules['logger'];
	}

	/**
	 * Retorna o modulo gerenciador de LLMs.
	 *
	 * @return JEP_LLM_Manager
	 */
	public function llm() {
		return $this->modules['llm'];
	}

	/**
	 * Retorna o modulo de reescrita de conteudo.
	 *
	 * @return JEP_Content_Rewriter
	 */
	public function rewriter() {
		return $this->modules['rewriter'];
	}

	/**
	 * Retorna o modulo principal do bot Telegram.
	 *
	 * @return JEP_Telegram_Bot
	 */
	public function telegram() {
		return $this->modules['telegram_bot'];
	}

	/**
	 * Impede clonagem da instancia singleton.
	 */
	public function __clone() {}

	/**
	 * Impede desserializacao da instancia singleton.
	 */
	public function __wakeup() {}
}
