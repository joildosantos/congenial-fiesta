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
 * em duas fases para minimizar o uso de memoria durante plugins_loaded:
 *
 * Fase 1 — plugins_loaded: Settings + Logger (construtores triviais, sempre necessarios).
 * Fase 2 — init prioridade 1: todos os outros modulos, incluindo Admin quando
 *           is_admin(). init dispara ANTES de admin_menu (prioridade 10), portanto
 *           todos os add_action() do JEP_Admin chegam a tempo. O WP Cron tambem
 *           dispara em init prioridade 10, portanto o Scheduler esta registrado antes.
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
	 * sem risco de OOM). Registra a fase 2 como callback de init.
	 *
	 * Nota sobre a ordem dos hooks do WordPress:
	 *   plugins_loaded → init → admin_menu → admin_bar_menu → admin_init
	 *
	 * load_modules() dispara em init prioridade 1, portanto antes de
	 * admin_menu (prioridade 10). Isso garante que JEP_Admin registre
	 * add_action('admin_menu', ...) no momento certo.
	 * Os handlers wp_ajax_* tambem sao registrados antes de wp_loaded,
	 * quando esses hooks efetivamente disparam.
	 */
	private function __construct() {
		// Garante que o schema do banco esta atualizado antes de usar qualquer modulo.
		JEP_Installer::maybe_upgrade();

		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Fase 1 — modulos sempre necessarios, construtores triviais.
		$this->modules['settings'] = new JEP_Settings();
		$this->modules['logger']   = new JEP_Logger();

		// Fase 2 — restante dos modulos diferido para init prioridade 1.
		add_action( 'init', array( $this, 'load_modules' ), 1 );
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

		// --- Admin (apenas no contexto de back-end ou AJAX) ---
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
