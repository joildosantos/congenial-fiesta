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
 * Ponto de entrada central do plugin. Instancia e conecta todos os modulos
 * em tres fases para minimizar o uso de memoria durante plugins_loaded:
 *
 * Fase 1 — plugins_loaded: Settings + Logger (construtores triviais, sempre necessarios).
 * Fase 2 — init prioridade 1: todos os outros modulos. O WP Cron dispara em
 *           init prioridade 10, portanto o Scheduler ja esta registrado a tempo.
 * Fase 3 — admin_init prioridade 5: Admin. Todos os hooks de menu/AJAX do
 *           admin disparam apos admin_init.
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
	 * @return JEP_Automacao
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Construtor privado.
	 *
	 * Fase 1: carrega Settings e Logger imediatamente (construtores minimos,
	 * sem risco de OOM). Registra as fases 2 e 3 como callbacks de hooks.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Fase 1 — modulos sempre necessarios, construtores triviais.
		$this->modules['settings'] = new JEP_Settings();
		$this->modules['logger']   = new JEP_Logger();

		// Fase 2 — restante dos modulos diferido para init.
		add_action( 'init', array( $this, 'load_modules' ), 1 );

		// Fase 3 — modulo de admin diferido para admin_init.
		add_action( 'admin_init', array( $this, 'load_admin_module' ), 5 );
	}

	/**
	 * Fase 2: instancia todos os modulos nao-admin na ordem correta de
	 * dependencia. Executado em init prioridade 1.
	 *
	 * O WP Cron (wp_cron()) e chamado em init prioridade 10, portanto o
	 * JEP_Scheduler registra seus add_action() antes de qualquer evento
	 * de cron ser disparado.
	 */
	public function load_modules() {
		// --- Core ---
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

		// --- Image (classes utilitarias estaticas — carregadas via autoloader) ---
		class_exists( 'JEP_Image_GD' );
		class_exists( 'JEP_Image_AI' );

		// --- API ---
		$this->modules['rest_api'] = new JEP_Rest_Api();
	}

	/**
	 * Fase 3: instancia o modulo de administracao.
	 * Executado em admin_init prioridade 5.
	 */
	public function load_admin_module() {
		if ( is_admin() && ! isset( $this->modules['admin'] ) ) {
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
