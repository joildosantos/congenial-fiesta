<?php
/**
 * JEP Admin
 *
 * Registers all admin menus, sub-pages, assets and AJAX handlers for the
 * JEP Automacao Editorial plugin v2.0.
 *
 * @package JEP_Automacao_Editorial
 * @subpackage Admin
 * @since 2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class JEP_Admin
 */
class JEP_Admin {

	/**
	 * All registered sub-page slugs (used for asset enqueuing).
	 *
	 * @var string[]
	 */
	private $page_hooks = array();

	/**
	 * Constructor — wire up all WordPress hooks.
	 */
	public function __construct() {
		add_action( 'admin_menu',            array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'plugin_action_links_' . JEP_AUTOMACAO_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_jep_clear_logs',          array( $this, 'ajax_clear_logs' ) );
		add_action( 'wp_ajax_jep_run_pipeline',        array( $this, 'ajax_run_pipeline' ) );
		add_action( 'wp_ajax_jep_trigger_workflow',    array( $this, 'ajax_run_pipeline' ) ); // alias.
		add_action( 'wp_ajax_jep_test_llm_provider',  array( $this, 'ajax_test_llm_provider' ) );
		add_action( 'wp_ajax_jep_telegram_bot_info',  array( $this, 'ajax_telegram_bot_info' ) );
		add_action( 'wp_ajax_jep_rss_fetch_now',       array( $this, 'ajax_rss_fetch_now' ) );
		add_action( 'wp_ajax_jep_save_image_ai',        array( $this, 'ajax_save_image_ai' ) );
		add_action( 'wp_ajax_jep_test_webhook',         array( $this, 'ajax_test_webhook' ) );
		add_action( 'wp_ajax_jep_debug_write_log',      array( $this, 'ajax_debug_write_log' ) );
	}

	// -------------------------------------------------------------------------
	// Menu registration
	// -------------------------------------------------------------------------

	/**
	 * Register the top-level menu and all sub-pages.
	 *
	 * @return void
	 */
	public function register_menu() {
		$cap  = 'manage_options';
		$slug = 'jep-automacao';

		add_menu_page(
			__( 'JEP Automacao', 'jep-automacao' ),
			__( 'JEP Automacao', 'jep-automacao' ),
			$cap,
			$slug,
			array( $this, 'render_dashboard' ),
			'dashicons-rss',
			30
		);

		$subpages = array(
			array(
				'parent'   => $slug,
				'title'    => __( 'Dashboard', 'jep-automacao' ),
				'menu'     => __( 'Dashboard', 'jep-automacao' ),
				'slug'     => $slug,
				'callback' => array( $this, 'render_dashboard' ),
			),
			array(
				'parent'   => $slug,
				'title'    => __( 'Provedores LLM', 'jep-automacao' ),
				'menu'     => __( 'Provedores LLM', 'jep-automacao' ),
				'slug'     => 'jep-automacao-llm',
				'callback' => array( $this, 'render_llm' ),
			),
			array(
				'parent'   => $slug,
				'title'    => __( 'Pautas Frias', 'jep-automacao' ),
				'menu'     => __( 'Pautas Frias', 'jep-automacao' ),
				'slug'     => 'jep-automacao-cold-content',
				'callback' => array( $this, 'render_cold_content' ),
			),
			array(
				'parent'   => $slug,
				'title'    => __( 'Feeds RSS', 'jep-automacao' ),
				'menu'     => __( 'Feeds RSS', 'jep-automacao' ),
				'slug'     => 'jep-automacao-rss',
				'callback' => array( $this, 'render_rss' ),
			),
			array(
				'parent'   => $slug,
				'title'    => __( 'Conteudo Diario', 'jep-automacao' ),
				'menu'     => __( 'Conteudo Diario', 'jep-automacao' ),
				'slug'     => 'jep-automacao-daily',
				'callback' => array( $this, 'render_daily' ),
			),
			array(
				'parent'   => $slug,
				'title'    => __( 'Telegram', 'jep-automacao' ),
				'menu'     => __( 'Telegram', 'jep-automacao' ),
				'slug'     => 'jep-automacao-telegram',
				'callback' => array( $this, 'render_telegram' ),
			),
			array(
				'parent'   => $slug,
				'title'    => __( 'Distribuicao', 'jep-automacao' ),
				'menu'     => __( 'Distribuicao', 'jep-automacao' ),
				'slug'     => 'jep-automacao-distribution',
				'callback' => array( $this, 'render_distribution' ),
			),
			array(
				'parent'   => $slug,
				'title'    => __( 'Qualidade / Fontes', 'jep-automacao' ),
				'menu'     => __( 'Qualidade', 'jep-automacao' ),
				'slug'     => 'jep-automacao-quality',
				'callback' => array( $this, 'render_quality' ),
			),
			array(
				'parent'   => $slug,
				'title'    => __( 'Configuracoes', 'jep-automacao' ),
				'menu'     => __( 'Configuracoes', 'jep-automacao' ),
				'slug'     => 'jep-automacao-settings',
				'callback' => array( $this, 'render_settings' ),
			),
			array(
				'parent'   => $slug,
				'title'    => __( 'Logs de Atividade', 'jep-automacao' ),
				'menu'     => __( 'Logs', 'jep-automacao' ),
				'slug'     => 'jep-automacao-logs',
				'callback' => array( $this, 'render_logs' ),
			),
			array(
				'parent'   => $slug,
				'title'    => __( 'Diagnostico', 'jep-automacao' ),
				'menu'     => __( 'Diagnostico', 'jep-automacao' ),
				'slug'     => 'jep-automacao-debug',
				'callback' => array( $this, 'render_debug' ),
			),
		);

		foreach ( $subpages as $page ) {
			$hook = add_submenu_page(
				$page['parent'],
				$page['title'],
				$page['menu'],
				$cap,
				$page['slug'],
				$page['callback']
			);

			if ( $hook ) {
				$this->page_hooks[] = $hook;
			}
		}
	}

	// -------------------------------------------------------------------------
	// Page renderers
	// -------------------------------------------------------------------------

	/** @return void */
	public function render_dashboard() {
		require JEP_AUTOMACAO_PLUGIN_DIR . 'admin/views/page-dashboard.php';
	}

	/** @return void */
	public function render_llm() {
		require JEP_AUTOMACAO_PLUGIN_DIR . 'admin/views/page-llm.php';
	}

	/** @return void */
	public function render_cold_content() {
		require JEP_AUTOMACAO_PLUGIN_DIR . 'admin/views/page-cold-content.php';
	}

	/** @return void */
	public function render_rss() {
		require JEP_AUTOMACAO_PLUGIN_DIR . 'admin/views/page-rss.php';
	}

	/** @return void */
	public function render_daily() {
		require JEP_AUTOMACAO_PLUGIN_DIR . 'admin/views/page-daily.php';
	}

	/** @return void */
	public function render_telegram() {
		require JEP_AUTOMACAO_PLUGIN_DIR . 'admin/views/page-telegram.php';
	}

	/** @return void */
	public function render_distribution() {
		require JEP_AUTOMACAO_PLUGIN_DIR . 'admin/views/page-distribution.php';
	}

	/** @return void */
	public function render_quality() {
		require JEP_AUTOMACAO_PLUGIN_DIR . 'admin/views/page-quality.php';
	}

	/** @return void */
	public function render_settings() {
		require JEP_AUTOMACAO_PLUGIN_DIR . 'admin/views/page-settings.php';
	}

	/** @return void */
	public function render_logs() {
		require JEP_AUTOMACAO_PLUGIN_DIR . 'admin/views/page-logs.php';
	}

	// -------------------------------------------------------------------------
	// Assets
	// -------------------------------------------------------------------------

	/**
	 * Enqueue CSS and JS on plugin pages.
	 *
	 * @param string $hook Current admin page hook.
	 *
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		$is_plugin_page = 'toplevel_page_jep-automacao' === $hook
			|| in_array( $hook, $this->page_hooks, true );

		if ( ! $is_plugin_page ) {
			return;
		}

		wp_enqueue_style(
			'jep-admin',
			JEP_AUTOMACAO_PLUGIN_URL . 'admin/css/jep-admin.css',
			array(),
			JEP_AUTOMACAO_VERSION
		);

		wp_enqueue_script(
			'jep-admin',
			JEP_AUTOMACAO_PLUGIN_URL . 'admin/js/jep-admin.js',
			array( 'jquery' ),
			JEP_AUTOMACAO_VERSION,
			true
		);

		wp_localize_script(
			'jep-admin',
			'jepAdmin',
			array(
				'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'jep_admin_nonce' ),
				'restUrl'   => rest_url( 'jep/v1' ),
				'restNonce' => wp_create_nonce( 'wp_rest' ),
			)
		);
	}

	// -------------------------------------------------------------------------
	// AJAX handlers
	// -------------------------------------------------------------------------

	/**
	 * AJAX: clear activity logs.
	 *
	 * @return void
	 */
	public function ajax_clear_logs() {
		check_ajax_referer( 'jep_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permissao negada.', 'jep-automacao' ) ), 403 );
		}

		$days = absint( isset( $_POST['days'] ) ? $_POST['days'] : 0 );

		if ( $days > 0 ) {
			$deleted = JEP_Logger::prune( $days );
			wp_send_json_success( array( 'message' => sprintf( __( '%d logs removidos.', 'jep-automacao' ), $deleted ) ) );
		} else {
			global $wpdb;
			$table = $wpdb->prefix . 'jep_logs';
			$wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore
			wp_send_json_success( array( 'message' => __( 'Todos os logs removidos.', 'jep-automacao' ) ) );
		}
	}

	/**
	 * AJAX: manually trigger a pipeline (cold_content | daily | topic_research).
	 *
	 * @return void
	 */
	public function ajax_run_pipeline() {
		check_ajax_referer( 'jep_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permissao negada.', 'jep-automacao' ) ), 403 );
		}

		// Accept both 'pipeline' (dashboard inline JS) and 'workflow' (jep-admin.js global handler).
		$pipeline = sanitize_key( isset( $_POST['pipeline'] ) ? wp_unslash( $_POST['pipeline'] )
			: ( isset( $_POST['workflow'] ) ? wp_unslash( $_POST['workflow'] ) : '' ) );

		switch ( $pipeline ) {
			case 'cold_content':
				$obj    = new JEP_Cold_Content();
				$result = $obj->process_next();
				wp_send_json_success( array( 'message' => $result
					? __( 'Pauta processada e enviada ao Telegram.', 'jep-automacao' )
					: __( 'Nenhuma pauta pendente.', 'jep-automacao' ) ) );
				break;

			case 'daily':
				$obj    = new JEP_Daily_Content();
				$result = $obj->run();
				wp_send_json_success( array(
					'message' => sprintf(
						__( '%d artigos enviados ao Telegram. Digest post #%d criado.', 'jep-automacao' ),
						$result['rewritten'],
						$result['digest_id']
					),
				) );
				break;

			case 'topic_research':
				$obj   = new JEP_Topic_Research();
				$count = $obj->run();
				wp_send_json_success( array( 'message' => sprintf( __( '%d pautas importadas.', 'jep-automacao' ), $count ) ) );
				break;

			case 'rss_fetch':
				$obj   = new JEP_RSS_Manager();
				$count = $obj->fetch_all();
				wp_send_json_success( array( 'message' => sprintf( __( '%d novos itens captados.', 'jep-automacao' ), $count ) ) );
				break;

			default:
				wp_send_json_error( array( 'message' => __( 'Pipeline desconhecido.', 'jep-automacao' ) ) );
		}
	}

	/**
	 * AJAX: test a single LLM provider.
	 *
	 * @return void
	 */
	public function ajax_test_llm_provider() {
		check_ajax_referer( 'jep_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permissao negada.', 'jep-automacao' ) ), 403 );
		}

		$provider_id = absint( isset( $_POST['provider_id'] ) ? $_POST['provider_id'] : 0 );

		if ( ! $provider_id ) {
			wp_send_json_error( array( 'message' => __( 'ID do provedor invalido.', 'jep-automacao' ) ) );
		}

		$llm    = new JEP_LLM_Manager();
		$result = $llm->test_provider( $provider_id );

		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * AJAX: retrieve Telegram bot info (getMe).
	 *
	 * @return void
	 */
	public function ajax_telegram_bot_info() {
		check_ajax_referer( 'jep_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permissao negada.', 'jep-automacao' ) ), 403 );
		}

		if ( ! class_exists( 'JEP_Telegram_Bot' ) ) {
			wp_send_json_error( array( 'message' => __( 'Modulo Telegram nao disponivel.', 'jep-automacao' ) ) );
		}

		$bot    = new JEP_Telegram_Bot();
		$result = $bot->get_me();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: save AI image settings.
	 *
	 * @return void
	 */
	public function ajax_save_image_ai() {
		check_ajax_referer( 'jep_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permissao negada.', 'jep-automacao' ) ), 403 );
		}

		$data = array(
			'provider' => sanitize_text_field( isset( $_POST['provider'] ) ? wp_unslash( $_POST['provider'] ) : '' ),
			'api_key'  => sanitize_text_field( isset( $_POST['api_key'] )  ? wp_unslash( $_POST['api_key'] )  : '' ),
			'base_url' => esc_url_raw( isset( $_POST['base_url'] )         ? wp_unslash( $_POST['base_url'] ) : '' ),
			'model'    => sanitize_text_field( isset( $_POST['model'] )    ? wp_unslash( $_POST['model'] )    : '' ),
			'size'     => sanitize_text_field( isset( $_POST['size'] )     ? wp_unslash( $_POST['size'] )     : '' ),
			'style'    => sanitize_text_field( isset( $_POST['style'] )    ? wp_unslash( $_POST['style'] )    : '' ),
			'quality'  => sanitize_text_field( isset( $_POST['quality'] )  ? wp_unslash( $_POST['quality'] )  : '' ),
		);

		JEP_Image_AI::save_settings( $data );
		wp_send_json_success( array( 'message' => __( 'Configuracoes de imagem AI salvas.', 'jep-automacao' ) ) );
	}

	/**
	 * AJAX: trigger immediate RSS fetch.
	 *
	 * @return void
	 */
	public function ajax_rss_fetch_now() {
		check_ajax_referer( 'jep_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permissao negada.', 'jep-automacao' ) ), 403 );
		}

		$rss   = new JEP_RSS_Manager();
		$count = $rss->fetch_all();

		wp_send_json_success( array( 'message' => sprintf( __( '%d novos itens captados dos feeds RSS.', 'jep-automacao' ), $count ) ) );
	}

	/**
	 * Render: diagnostico do sistema.
	 *
	 * @return void
	 */
	public function render_debug() {
		require JEP_AUTOMACAO_PLUGIN_DIR . 'admin/views/page-debug.php';
	}

	/**
	 * AJAX: test Telegram webhook URL — verifica se o webhook esta configurado.
	 *
	 * @return void
	 */
	public function ajax_test_webhook() {
		check_ajax_referer( 'jep_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permissao negada.', 'jep-automacao' ) ), 403 );
		}

		if ( ! class_exists( 'JEP_Telegram_Bot' ) ) {
			wp_send_json_error( array( 'message' => __( 'Modulo Telegram nao disponivel.', 'jep-automacao' ) ) );
			return;
		}

		$bot    = new JEP_Telegram_Bot();
		$result = $bot->get_me();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			return;
		}

		$username = isset( $result['username'] ) ? '@' . $result['username'] : '?';
		wp_send_json_success( array( 'message' => sprintf( __( 'Bot conectado: %s', 'jep-automacao' ), $username ) ) );
	}

	/**
	 * AJAX: escreve um log de teste (usado pela pagina de diagnostico).
	 *
	 * @return void
	 */
	public function ajax_debug_write_log() {
		check_ajax_referer( 'jep_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Permissao negada.' ), 403 );
		}

		$result = JEP_Logger::info( 'diagnostico', 'Log de teste gerado via AJAX na pagina de diagnostico.' );

		if ( false === $result ) {
			global $wpdb;
			wp_send_json_error( array( 'message' => 'Falha ao escrever log: ' . $wpdb->last_error ) );
			return;
		}

		wp_send_json_success( array( 'message' => 'Log escrito com sucesso. ID: ' . $result ) );
	}

	// -------------------------------------------------------------------------
	// Misc
	// -------------------------------------------------------------------------

	/**
	 * Add a "Configuracoes" link on the plugin list page.
	 *
	 * @param string[] $links Existing action links.
	 *
	 * @return string[]
	 */
	public function plugin_action_links( $links ) {
		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				admin_url( 'admin.php?page=jep-automacao-settings' ),
				__( 'Configuracoes', 'jep-automacao' )
			)
		);

		return $links;
	}
}
