<?php
/**
 * Interface administrativa do plugin.
 *
 * @package JEP_Automacao
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class JEP_Admin
 */
class JEP_Admin {

	/**
	 * Construtor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_jep_trigger_workflow', array( $this, 'ajax_trigger_workflow' ) );
		add_action( 'wp_ajax_jep_clear_logs', array( $this, 'ajax_clear_logs' ) );
		add_action( 'wp_ajax_jep_test_webhook', array( $this, 'ajax_test_webhook' ) );
		add_filter( 'plugin_action_links_' . JEP_AUTOMACAO_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );
	}

	/**
	 * Registra o menu no painel WordPress.
	 */
	public function register_menu() {
		$icon = 'dashicons-rss';

		add_menu_page(
			__( 'JEP Automacao', 'jep-automacao' ),
			__( 'JEP Automacao', 'jep-automacao' ),
			'manage_options',
			'jep-automacao',
			array( $this, 'render_dashboard' ),
			$icon,
			30
		);

		add_submenu_page(
			'jep-automacao',
			__( 'Dashboard', 'jep-automacao' ),
			__( 'Dashboard', 'jep-automacao' ),
			'manage_options',
			'jep-automacao',
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			'jep-automacao',
			__( 'Configuracoes', 'jep-automacao' ),
			__( 'Configuracoes', 'jep-automacao' ),
			'manage_options',
			'jep-automacao-settings',
			array( $this, 'render_settings' )
		);

		add_submenu_page(
			'jep-automacao',
			__( 'Logs de Atividade', 'jep-automacao' ),
			__( 'Logs', 'jep-automacao' ),
			'manage_options',
			'jep-automacao-logs',
			array( $this, 'render_logs' )
		);
	}

	/**
	 * Registra as configuracoes via Settings API.
	 */
	public function register_settings() {
		$fields = $this->get_settings_fields();

		foreach ( $fields as $section_id => $section ) {
			add_settings_section(
				$section_id,
				$section['title'],
				null,
				'jep-automacao-settings'
			);

			foreach ( $section['fields'] as $field_id => $field ) {
				$option_name = 'jep_automacao_' . $field_id;

				register_setting(
					'jep_automacao_settings',
					$option_name,
					array(
						'sanitize_callback' => isset( $field['sanitize'] ) ? $field['sanitize'] : 'sanitize_text_field',
					)
				);

				add_settings_field(
					$field_id,
					$field['label'],
					array( $this, 'render_field' ),
					'jep-automacao-settings',
					$section_id,
					array_merge(
						$field,
						array(
							'id'   => $field_id,
							'name' => $option_name,
						)
					)
				);
			}
		}
	}

	/**
	 * Retorna a definicao de todos os campos de configuracao.
	 *
	 * @return array
	 */
	private function get_settings_fields() {
		return array(
			'section_n8n'       => array(
				'title'  => __( 'Integracao n8n', 'jep-automacao' ),
				'fields' => array(
					'n8n_webhook_url'           => array(
						'label'       => __( 'URL Webhook (Post Publicado)', 'jep-automacao' ),
						'type'        => 'url',
						'placeholder' => 'http://seu-servidor:5678/webhook/wp-post-published',
						'description' => __( 'URL do webhook no n8n que recebe notificacoes de posts publicados.', 'jep-automacao' ),
					),
					'n8n_secret_token'          => array(
						'label'       => __( 'Token Secreto', 'jep-automacao' ),
						'type'        => 'text',
						'description' => __( 'Token enviado nos headers para autenticar requisicoes. Mantenha igual no n8n.', 'jep-automacao' ),
					),
					'enable_webhook_on_publish' => array(
						'label'    => __( 'Webhook ao Publicar', 'jep-automacao' ),
						'type'     => 'checkbox',
						'description' => __( 'Envia webhook ao n8n quando um post e publicado.', 'jep-automacao' ),
						'sanitize' => 'absint',
					),
					'enable_rest_api'           => array(
						'label'    => __( 'Habilitar REST API', 'jep-automacao' ),
						'type'     => 'checkbox',
						'description' => __( 'Expoe endpoints /jep/v1/* para o n8n criar posts e enviar logs.', 'jep-automacao' ),
						'sanitize' => 'absint',
					),
				),
			),
			'section_llm'       => array(
				'title'  => __( 'OpenRouter (LLM)', 'jep-automacao' ),
				'fields' => array(
					'openrouter_api_key' => array(
						'label'       => __( 'API Key OpenRouter', 'jep-automacao' ),
						'type'        => 'password',
						'placeholder' => 'sk-or-...',
						'description' => __( 'Chave de acesso ao OpenRouter para modelos LLM gratuitos. Usada pelo n8n.', 'jep-automacao' ),
					),
				),
			),
			'section_telegram'  => array(
				'title'  => __( 'Telegram (Aprovacao)', 'jep-automacao' ),
				'fields' => array(
					'telegram_bot_token'      => array(
						'label'       => __( 'Bot Token', 'jep-automacao' ),
						'type'        => 'password',
						'placeholder' => '123456789:ABCdefGHI...',
						'description' => __( 'Token obtido via @BotFather no Telegram.', 'jep-automacao' ),
					),
					'telegram_editor_chat_id' => array(
						'label'       => __( 'Chat ID do Editor', 'jep-automacao' ),
						'type'        => 'text',
						'placeholder' => '123456789',
						'description' => __( 'ID do chat do editor (ou grupo) para envio de aprovacoes.', 'jep-automacao' ),
					),
					'telegram_channel_id'     => array(
						'label'       => __( 'ID do Canal', 'jep-automacao' ),
						'type'        => 'text',
						'placeholder' => '@jornalespacodopovo',
						'description' => __( 'Username ou ID numerico do canal Telegram para distribuicao.', 'jep-automacao' ),
					),
				),
			),
			'section_facebook'  => array(
				'title'  => __( 'Facebook / Instagram', 'jep-automacao' ),
				'fields' => array(
					'facebook_page_id'          => array(
						'label'       => __( 'Page ID', 'jep-automacao' ),
						'type'        => 'text',
						'placeholder' => '123456789',
					),
					'facebook_page_access_token'=> array(
						'label'       => __( 'Page Access Token', 'jep-automacao' ),
						'type'        => 'password',
						'description' => __( 'Token de longa duracao da pagina Facebook.', 'jep-automacao' ),
					),
					'instagram_account_id'      => array(
						'label'       => __( 'Instagram Business Account ID', 'jep-automacao' ),
						'type'        => 'text',
						'placeholder' => '123456789',
					),
				),
			),
			'section_whatsapp'  => array(
				'title'  => __( 'WhatsApp (Evolution API)', 'jep-automacao' ),
				'fields' => array(
					'evolution_server_url'  => array(
						'label'       => __( 'URL do Servidor Evolution', 'jep-automacao' ),
						'type'        => 'url',
						'placeholder' => 'http://localhost:8080',
					),
					'evolution_api_key'     => array(
						'label' => __( 'API Key Evolution', 'jep-automacao' ),
						'type'  => 'password',
					),
					'evolution_instance_name' => array(
						'label'       => __( 'Nome da Instancia', 'jep-automacao' ),
						'type'        => 'text',
						'placeholder' => 'jornal-whatsapp',
					),
					'whatsapp_group_id'     => array(
						'label'       => __( 'ID do Grupo/Lista', 'jep-automacao' ),
						'type'        => 'text',
						'placeholder' => '5511999999999-group',
					),
				),
			),
			'section_misc'      => array(
				'title'  => __( 'Outros Servicos', 'jep-automacao' ),
				'fields' => array(
					'unsplash_access_key' => array(
						'label'       => __( 'Unsplash Access Key', 'jep-automacao' ),
						'type'        => 'password',
						'description' => __( 'Para busca de imagens de fallback.', 'jep-automacao' ),
					),
					'google_sheets_id'    => array(
						'label'       => __( 'Google Sheets ID (Banco de Pautas)', 'jep-automacao' ),
						'type'        => 'text',
						'placeholder' => '1aBcDeFgHiJkLmNoPqRsTuVwXyZ',
					),
				),
			),
		);
	}

	/**
	 * Renderiza um campo de configuracao generico.
	 *
	 * @param array $args Argumentos do campo.
	 */
	public function render_field( $args ) {
		$id    = $args['id'];
		$name  = $args['name'];
		$type  = $args['type'] ?? 'text';
		$value = get_option( $name, '' );
		$desc  = $args['description'] ?? '';
		$ph    = $args['placeholder'] ?? '';

		if ( 'checkbox' === $type ) {
			printf(
				'<label><input type="checkbox" id="%s" name="%s" value="1" %s> %s</label>',
				esc_attr( $id ),
				esc_attr( $name ),
				checked( 1, (int) $value, false ),
				esc_html( $desc )
			);
		} else {
			printf(
				'<input type="%s" id="%s" name="%s" value="%s" placeholder="%s" class="regular-text">',
				esc_attr( $type ),
				esc_attr( $id ),
				esc_attr( $name ),
				esc_attr( $value ),
				esc_attr( $ph )
			);
			if ( $desc && 'checkbox' !== $type ) {
				printf( '<p class="description">%s</p>', esc_html( $desc ) );
			}
		}
	}

	/**
	 * Registra assets CSS e JS para as paginas do plugin.
	 *
	 * @param string $hook Hook da pagina atual.
	 */
	public function enqueue_assets( $hook ) {
		$plugin_pages = array(
			'toplevel_page_jep-automacao',
			'jep-automacao_page_jep-automacao-settings',
			'jep-automacao_page_jep-automacao-logs',
		);

		if ( ! in_array( $hook, $plugin_pages, true ) ) {
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

	/**
	 * Renderiza a pagina de Dashboard.
	 */
	public function render_dashboard() {
		require JEP_AUTOMACAO_PLUGIN_DIR . 'admin/views/page-dashboard.php';
	}

	/**
	 * Renderiza a pagina de Configuracoes.
	 */
	public function render_settings() {
		require JEP_AUTOMACAO_PLUGIN_DIR . 'admin/views/page-settings.php';
	}

	/**
	 * Renderiza a pagina de Logs.
	 */
	public function render_logs() {
		require JEP_AUTOMACAO_PLUGIN_DIR . 'admin/views/page-logs.php';
	}

	/**
	 * AJAX: dispara um workflow manualmente.
	 */
	public function ajax_trigger_workflow() {
		check_ajax_referer( 'jep_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permissao negada.', 'jep-automacao' ) ), 403 );
		}

		$workflow = sanitize_key( wp_unslash( $_POST['workflow'] ?? '' ) );
		if ( ! $workflow ) {
			wp_send_json_error( array( 'message' => __( 'Workflow nao informado.', 'jep-automacao' ) ) );
		}

		$response = jep_automacao()->modules['webhook_sender']->trigger_workflow( $workflow );

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		$code = wp_remote_retrieve_response_code( $response );
		wp_send_json_success(
			array(
				'message'     => sprintf( __( 'Workflow "%s" disparado!', 'jep-automacao' ), $workflow ),
				'status_code' => $code,
			)
		);
	}

	/**
	 * AJAX: testa o webhook do n8n enviando um payload de exemplo.
	 */
	public function ajax_test_webhook() {
		check_ajax_referer( 'jep_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permissao negada.', 'jep-automacao' ) ), 403 );
		}

		$settings    = jep_automacao()->settings();
		$webhook_url = $settings->get_n8n_webhook_url();

		if ( empty( $webhook_url ) ) {
			wp_send_json_error( array( 'message' => __( 'URL do webhook nao configurada.', 'jep-automacao' ) ) );
		}

		$response = wp_remote_post(
			$webhook_url,
			array(
				'body'    => wp_json_encode(
					array(
						'event'      => 'test',
						'message'    => 'Teste de conexao do plugin JEP Automacao',
						'site_url'   => get_bloginfo( 'url' ),
						'timestamp'  => current_time( 'c' ),
					)
				),
				'headers' => array(
					'Content-Type' => 'application/json',
					'X-JEP-Token'  => $settings->get_n8n_secret_token(),
					'X-JEP-Event'  => 'test',
				),
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ) );
		}

		$code = wp_remote_retrieve_response_code( $response );
		wp_send_json_success(
			array(
				'message'     => sprintf( __( 'Webhook respondeu com codigo HTTP %d.', 'jep-automacao' ), $code ),
				'status_code' => $code,
			)
		);
	}

	/**
	 * AJAX: limpa os logs.
	 */
	public function ajax_clear_logs() {
		check_ajax_referer( 'jep_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permissao negada.', 'jep-automacao' ) ), 403 );
		}

		$days = absint( $_POST['days'] ?? 0 );

		if ( $days > 0 ) {
			$deleted = jep_automacao()->logger()->prune( $days );
			wp_send_json_success(
				array(
					'message' => sprintf( __( '%d logs removidos.', 'jep-automacao' ), $deleted ),
				)
			);
		} else {
			global $wpdb;
			$table   = $wpdb->prefix . 'jep_logs';
			$deleted = $wpdb->query( "TRUNCATE TABLE {$table}" ); // phpcs:ignore
			wp_send_json_success( array( 'message' => __( 'Todos os logs foram removidos.', 'jep-automacao' ) ) );
		}
	}

	/**
	 * Adiciona link de configuracoes na lista de plugins.
	 *
	 * @param array $links Links existentes.
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			admin_url( 'admin.php?page=jep-automacao-settings' ),
			__( 'Configuracoes', 'jep-automacao' )
		);
		array_unshift( $links, $settings_link );
		return $links;
	}
}
