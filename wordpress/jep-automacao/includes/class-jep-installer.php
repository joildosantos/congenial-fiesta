<?php
/**
 * Instalacao e ativacao do plugin.
 *
 * @package JEP_Automacao
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class JEP_Installer
 */
class JEP_Installer {

	/**
	 * Executado na ativacao do plugin.
	 */
	public static function activate() {
		self::create_tables();
		self::set_default_options();
		flush_rewrite_rules();
	}

	/**
	 * Executado na desativacao do plugin.
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Cria as tabelas no banco de dados.
	 */
	public static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . 'jep_logs';

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			level       VARCHAR(20) NOT NULL DEFAULT 'info',
			event       VARCHAR(100) NOT NULL,
			post_id     BIGINT(20) UNSIGNED NULL,
			message     TEXT NOT NULL,
			context     LONGTEXT NULL,
			PRIMARY KEY (id),
			KEY level (level),
			KEY event (event),
			KEY post_id (post_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'jep_automacao_db_version', JEP_AUTOMACAO_VERSION );
	}

	/**
	 * Define opcoes padrao se ainda nao existirem.
	 */
	public static function set_default_options() {
		$defaults = array(
			'n8n_webhook_url'           => '',
			'n8n_secret_token'          => wp_generate_password( 32, false ),
			'openrouter_api_key'        => '',
			'telegram_bot_token'        => '',
			'telegram_editor_chat_id'   => '',
			'telegram_channel_id'       => '',
			'facebook_page_id'          => '',
			'facebook_page_access_token'=> '',
			'instagram_account_id'      => '',
			'evolution_server_url'      => '',
			'evolution_api_key'         => '',
			'evolution_instance_name'   => '',
			'whatsapp_group_id'         => '',
			'unsplash_access_key'       => '',
			'google_sheets_id'          => '',
			'enable_webhook_on_publish' => '1',
			'enable_rest_api'           => '1',
			'post_types_to_watch'       => array( 'post' ),
		);

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( 'jep_automacao_' . $key ) ) {
				update_option( 'jep_automacao_' . $key, $value );
			}
		}
	}
}
