<?php
/**
 * Instalador do plugin JEP Automacao v2.
 *
 * Responsavel por criar/atualizar tabelas do banco de dados,
 * inicializar opcoes padrao e gerenciar seeds iniciais.
 *
 * @package JEP_Automacao
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class JEP_Installer
 */
class JEP_Installer {

	/**
	 * Versao atual do schema do banco de dados.
	 * Incremente ao adicionar/modificar tabelas.
	 *
	 * @var string
	 */
	const DB_VERSION = '2.0.1';

	/**
	 * Nome da opcao que armazena a versao do DB instalado.
	 *
	 * @var string
	 */
	const DB_VERSION_OPTION = 'jep_automacao_db_version';

	// -------------------------------------------------------------------------
	// Metodos de ciclo de vida do plugin
	// -------------------------------------------------------------------------

	/**
	 * Executado na ativacao do plugin.
	 * Cria tabelas, inicializa opcoes, faz seed e agenda crons.
	 */
	public static function activate() {
		self::migrate_llm_usage_columns();
		self::create_tables();
		self::set_default_options();
		self::seed_default_feeds();
		JEP_Scheduler::register_schedules();
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
		flush_rewrite_rules();
	}

	/**
	 * Executado na desativacao do plugin.
	 * Remove todos os cron jobs do JEP.
	 */
	public static function deactivate() {
		JEP_Scheduler::clear_schedules();
		flush_rewrite_rules();
	}

	/**
	 * Verifica se o schema precisa ser atualizado e executa a migracao.
	 * Deve ser chamado em cada carregamento do plugin (ex: 'plugins_loaded').
	 */
	public static function maybe_upgrade() {
		$installed_version = get_option( self::DB_VERSION_OPTION, '0.0.0' );
		if ( version_compare( $installed_version, self::DB_VERSION, '<' ) ) {
			self::migrate_llm_usage_columns();
			self::create_tables();
			self::set_default_options();
			update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
		}
	}

	/**
	 * Renomeia colunas legadas da tabela jep_llm_usage para os nomes usados pelo codigo v2.
	 *
	 * tokens_in  -> input_tokens
	 * tokens_out -> output_tokens
	 * cost_usd   -> estimated_cost_usd
	 * (adiciona)    latency_ms
	 *
	 * @since 2.0.1
	 * @return void
	 */
	private static function migrate_llm_usage_columns() {
		global $wpdb;

		$table = $wpdb->prefix . 'jep_llm_usage';

		// Check if old column name exists.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$has_tokens_in = $wpdb->get_var(
			"SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
			 WHERE TABLE_SCHEMA = DATABASE()
			   AND TABLE_NAME   = '{$table}'
			   AND COLUMN_NAME  = 'tokens_in'"
		);

		if ( $has_tokens_in ) {
			$wpdb->query( "ALTER TABLE `{$table}` CHANGE COLUMN `tokens_in`  `input_tokens`       INT(11) NOT NULL DEFAULT 0" );
			$wpdb->query( "ALTER TABLE `{$table}` CHANGE COLUMN `tokens_out` `output_tokens`      INT(11) NOT NULL DEFAULT 0" );
			$wpdb->query( "ALTER TABLE `{$table}` CHANGE COLUMN `cost_usd`   `estimated_cost_usd` DECIMAL(10,8) NOT NULL DEFAULT 0.00000000" );
		}

		// Add latency_ms if missing.
		$has_latency = $wpdb->get_var(
			"SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
			 WHERE TABLE_SCHEMA = DATABASE()
			   AND TABLE_NAME   = '{$table}'
			   AND COLUMN_NAME  = 'latency_ms'"
		);

		if ( ! $has_latency ) {
			$wpdb->query( "ALTER TABLE `{$table}` ADD COLUMN `latency_ms` INT(11) NOT NULL DEFAULT 0 AFTER `output_tokens`" );
		}
		// phpcs:enable
	}

	// -------------------------------------------------------------------------
	// Criacao de tabelas
	// -------------------------------------------------------------------------

	/**
	 * Cria ou atualiza todas as tabelas do plugin via dbDelta.
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		// ------------------------------------------------------------------
		// 1. wp_jep_logs
		// ------------------------------------------------------------------
		$sql_logs = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}jep_logs (
			id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			level       VARCHAR(20)         NOT NULL DEFAULT 'info',
			event       VARCHAR(100)        NOT NULL DEFAULT '',
			post_id     BIGINT(20) UNSIGNED          DEFAULT NULL,
			message     TEXT                NOT NULL,
			context     LONGTEXT                     DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY idx_level      (level),
			KEY idx_event      (event),
			KEY idx_post_id    (post_id),
			KEY idx_created_at (created_at)
		) {$charset_collate};";

		// ------------------------------------------------------------------
		// 2. wp_jep_llm_providers
		// ------------------------------------------------------------------
		$sql_llm_providers = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}jep_llm_providers (
			id              BIGINT(20) UNSIGNED  NOT NULL AUTO_INCREMENT,
			name            VARCHAR(100)         NOT NULL DEFAULT '',
			api_key         TEXT                          DEFAULT NULL,
			base_url        VARCHAR(500)                  DEFAULT NULL,
			model           VARCHAR(200)         NOT NULL DEFAULT '',
			provider_type   ENUM('openai','openrouter','ollama') NOT NULL DEFAULT 'openai',
			priority        INT(11)              NOT NULL DEFAULT 10,
			monthly_limit   INT(11)              NOT NULL DEFAULT 0,
			used_this_month INT(11)              NOT NULL DEFAULT 0,
			active          TINYINT(1)           NOT NULL DEFAULT 1,
			last_used       DATETIME                      DEFAULT NULL,
			created_at      DATETIME             NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_provider_type (provider_type),
			KEY idx_priority      (priority),
			KEY idx_active        (active)
		) {$charset_collate};";

		// ------------------------------------------------------------------
		// 3. wp_jep_cold_content
		// ------------------------------------------------------------------
		$sql_cold_content = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}jep_cold_content (
			id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			title         VARCHAR(500)        NOT NULL DEFAULT '',
			summary       TEXT                         DEFAULT NULL,
			territory     VARCHAR(200)                 DEFAULT NULL,
			source_url    VARCHAR(2000)                DEFAULT NULL,
			status        ENUM('pending','processing','published','discarded') NOT NULL DEFAULT 'pending',
			priority      INT(11)             NOT NULL DEFAULT 5,
			research_data LONGTEXT                     DEFAULT NULL COMMENT 'JSON com dados de pesquisa',
			post_id       BIGINT(20) UNSIGNED          DEFAULT NULL,
			created_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			processed_at  DATETIME                     DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY idx_status     (status),
			KEY idx_priority   (priority),
			KEY idx_post_id    (post_id),
			KEY idx_created_at (created_at)
		) {$charset_collate};";

		// ------------------------------------------------------------------
		// 4. wp_jep_rss_feeds
		// ------------------------------------------------------------------
		$sql_rss_feeds = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}jep_rss_feeds (
			id                   BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name                 VARCHAR(200)        NOT NULL DEFAULT '',
			url                  VARCHAR(2000)       NOT NULL DEFAULT '',
			type                 ENUM('agencia','jornal_regional','portal_comunitario','blog','nacional') NOT NULL DEFAULT 'agencia',
			region               VARCHAR(200)                 DEFAULT NULL,
			active               TINYINT(1)          NOT NULL DEFAULT 1,
			last_fetched         DATETIME                     DEFAULT NULL,
			fetch_interval_hours INT(11)             NOT NULL DEFAULT 6,
			created_at           DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_type    (type),
			KEY idx_active  (active),
			KEY idx_region  (region)
		) {$charset_collate};";

		// ------------------------------------------------------------------
		// 5. wp_jep_rss_queue
		// ------------------------------------------------------------------
		$sql_rss_queue = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}jep_rss_queue (
			id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			feed_id      BIGINT(20) UNSIGNED NOT NULL,
			guid         VARCHAR(2000)       NOT NULL DEFAULT '',
			title        VARCHAR(500)        NOT NULL DEFAULT '',
			link         VARCHAR(2000)                DEFAULT NULL,
			pub_date     DATETIME                     DEFAULT NULL,
			content_raw  LONGTEXT                     DEFAULT NULL,
			relevance_score DECIMAL(5,2)              DEFAULT NULL,
			status       ENUM('pending','processing','approved','rejected','published') NOT NULL DEFAULT 'pending',
			post_id      BIGINT(20) UNSIGNED          DEFAULT NULL,
			created_at   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			processed_at DATETIME                     DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY idx_feed_id    (feed_id),
			KEY idx_status     (status),
			KEY idx_post_id    (post_id),
			KEY idx_pub_date   (pub_date),
			KEY idx_created_at (created_at)
		) {$charset_collate};";

		// ------------------------------------------------------------------
		// 6. wp_jep_pending_approvals
		// ------------------------------------------------------------------
		$sql_pending_approvals = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}jep_pending_approvals (
			id                 BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			type               ENUM('daily_rss','cold_content') NOT NULL DEFAULT 'daily_rss',
			source_id          BIGINT(20) UNSIGNED          DEFAULT NULL,
			title_a            VARCHAR(500)                  DEFAULT NULL,
			title_b            VARCHAR(500)                  DEFAULT NULL,
			content_html       LONGTEXT                      DEFAULT NULL,
			excerpt            TEXT                          DEFAULT NULL,
			image_url          VARCHAR(2000)                 DEFAULT NULL,
			telegram_message_id BIGINT(20)                  DEFAULT NULL,
			status             ENUM('pending','approved_a','approved_b','rejected') NOT NULL DEFAULT 'pending',
			approved_title     VARCHAR(500)                  DEFAULT NULL,
			post_id            BIGINT(20) UNSIGNED           DEFAULT NULL,
			created_at         DATETIME            NOT NULL  DEFAULT CURRENT_TIMESTAMP,
			resolved_at        DATETIME                      DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY idx_type        (type),
			KEY idx_status      (status),
			KEY idx_source_id   (source_id),
			KEY idx_post_id     (post_id),
			KEY idx_created_at  (created_at)
		) {$charset_collate};";

		// ------------------------------------------------------------------
		// 7. wp_jep_prompt_evaluations
		// ------------------------------------------------------------------
		$sql_prompt_evaluations = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}jep_prompt_evaluations (
			id               BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			prompt_type      ENUM('rss_rewrite','cold_rewrite','image_prompt','weekly_summary') NOT NULL DEFAULT 'rss_rewrite',
			provider_id      BIGINT(20) UNSIGNED          DEFAULT NULL,
			score_total      DECIMAL(5,2)                 DEFAULT NULL,
			score_clareza    DECIMAL(5,2)                 DEFAULT NULL,
			score_precisao   DECIMAL(5,2)                 DEFAULT NULL,
			score_tom        DECIMAL(5,2)                 DEFAULT NULL,
			score_estrutura  DECIMAL(5,2)                 DEFAULT NULL,
			score_seo        DECIMAL(5,2)                 DEFAULT NULL,
			feedback         TEXT                         DEFAULT NULL,
			sugestao_prompt  TEXT                         DEFAULT NULL,
			created_at       DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_prompt_type  (prompt_type),
			KEY idx_provider_id  (provider_id),
			KEY idx_created_at   (created_at)
		) {$charset_collate};";

		// ------------------------------------------------------------------
		// 8. wp_jep_suggested_feeds
		// ------------------------------------------------------------------
		$sql_suggested_feeds = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}jep_suggested_feeds (
			id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name         VARCHAR(200)        NOT NULL DEFAULT '',
			url_feed     VARCHAR(2000)                DEFAULT NULL,
			url_site     VARCHAR(2000)                DEFAULT NULL,
			type         VARCHAR(100)                 DEFAULT NULL,
			region       VARCHAR(200)                 DEFAULT NULL,
			justificativa TEXT                         DEFAULT NULL,
			status       ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
			created_at   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			resolved_at  DATETIME                     DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY idx_status     (status),
			KEY idx_type       (type),
			KEY idx_created_at (created_at)
		) {$charset_collate};";

		// ------------------------------------------------------------------
		// 9. wp_jep_llm_usage
		// ------------------------------------------------------------------
		$sql_llm_usage = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}jep_llm_usage (
			id                  BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			provider_id         BIGINT(20) UNSIGNED          DEFAULT NULL,
			prompt_type         VARCHAR(100)        NOT NULL DEFAULT '',
			input_tokens        INT(11)             NOT NULL DEFAULT 0,
			output_tokens       INT(11)             NOT NULL DEFAULT 0,
			latency_ms          INT(11)             NOT NULL DEFAULT 0,
			estimated_cost_usd  DECIMAL(10,8)       NOT NULL DEFAULT 0.00000000,
			created_at          DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_provider_id (provider_id),
			KEY idx_prompt_type (prompt_type),
			KEY idx_created_at  (created_at)
		) {$charset_collate};";

		dbDelta( $sql_logs );
		dbDelta( $sql_llm_providers );
		dbDelta( $sql_cold_content );
		dbDelta( $sql_rss_feeds );
		dbDelta( $sql_rss_queue );
		dbDelta( $sql_pending_approvals );
		dbDelta( $sql_prompt_evaluations );
		dbDelta( $sql_suggested_feeds );
		dbDelta( $sql_llm_usage );
	}

	// -------------------------------------------------------------------------
	// Opcoes padrao
	// -------------------------------------------------------------------------

	/**
	 * Inicializa todas as opcoes padrao do plugin.
	 * Nao sobrescreve valores ja existentes.
	 */
	public static function set_default_options() {
		$defaults = array(
			// Telegram
			'telegram_bot_token'       => '',
			'telegram_editor_chat_id'  => '',
			'telegram_channel_id'      => '',
			'telegram_webhook_secret'  => wp_generate_password( 32, false ),

			// Instagram / Facebook
			'instagram_account_id'               => '',
			'facebook_page_access_token'         => '',
			'enable_instagram_publishing'        => '0',
			'instagram_require_caption_approval' => '1',
			'instagram_caption_template'         => "{titulo}\n\n{excerpt}\n\n{url}\n\n{hashtags}",

			// Imagens
			'image_logo_url'    => '',
			'image_accent_color' => '#E63027',
			'image_badge_text'   => 'DESDE 2007',
			'enable_ai_images'   => '0',
			'ai_image_provider'  => 'pollinations',

			// LLM / OpenAI
			'openai_api_key' => '',
			'llm_providers'  => '',

			// Crons - horarios
			'cron_daily_time'     => '06:00',
			'cron_cold_time'      => '08:00',
			'cron_research_day'   => 'monday',
			'cron_discovery_time' => '03:00',
			'cron_summary_time'   => '20:00',

			// Crons - habilitados
			'enable_cron_daily'     => '1',
			'enable_cron_cold'      => '1',
			'enable_cron_research'  => '1',
			'enable_cron_discovery' => '1',
			'enable_cron_summary'   => '1',

			// REST API
			'rest_api_secret' => wp_generate_password( 32, false ),
		);

		foreach ( $defaults as $key => $value ) {
			$option_name = 'jep_automacao_' . $key;
			// add_option nao sobrescreve se a opcao ja existir.
			add_option( $option_name, $value, '', 'no' );
		}
	}

	// -------------------------------------------------------------------------
	// Seeds
	// -------------------------------------------------------------------------

	/**
	 * Insere feeds RSS padrao se a tabela estiver vazia.
	 */
	public static function seed_default_feeds() {
		global $wpdb;

		$table = $wpdb->prefix . 'jep_rss_feeds';

		// Evita duplicar seeds em reativacoes.
		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore
		if ( $count > 0 ) {
			return;
		}

		$feeds = array(
			array(
				'name'                 => 'Agência Brasil',
				'url'                  => 'https://agenciabrasil.ebc.com.br/rss/geral/feed.xml',
				'type'                 => 'agencia',
				'region'               => 'nacional',
				'active'               => 1,
				'fetch_interval_hours' => 6,
				'created_at'           => current_time( 'mysql' ),
			),
			array(
				'name'                 => 'Ponte Jornalismo',
				'url'                  => 'https://ponte.org/feed/',
				'type'                 => 'portal_comunitario',
				'region'               => 'nacional',
				'active'               => 1,
				'fetch_interval_hours' => 6,
				'created_at'           => current_time( 'mysql' ),
			),
			array(
				'name'                 => 'Agência Pública',
				'url'                  => 'https://apublica.org/feed/',
				'type'                 => 'agencia',
				'region'               => 'nacional',
				'active'               => 1,
				'fetch_interval_hours' => 6,
				'created_at'           => current_time( 'mysql' ),
			),
		);

		foreach ( $feeds as $feed ) {
			$wpdb->insert(
				$table,
				$feed,
				array( '%s', '%s', '%s', '%s', '%d', '%d', '%s' )
			);
		}
	}
}
