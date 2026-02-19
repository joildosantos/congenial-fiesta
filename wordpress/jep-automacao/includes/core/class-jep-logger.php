<?php
/**
 * Registro de atividades do plugin.
 *
 * @package JEP_Automacao
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class JEP_Logger
 */
class JEP_Logger {

	const LEVEL_INFO    = 'info';
	const LEVEL_SUCCESS = 'success';
	const LEVEL_WARNING = 'warning';
	const LEVEL_ERROR   = 'error';

	/**
	 * Nome da tabela de logs.
	 *
	 * @var string
	 */
	private $table;

	/**
	 * Construtor.
	 */
	public function __construct() {
		global $wpdb;
		$this->table = $wpdb->prefix . 'jep_logs';
	}

	/**
	 * Registra uma mensagem de log.
	 *
	 * @param string $event   Identificador do evento.
	 * @param string $message Mensagem descritiva.
	 * @param string $level   Nivel: info, success, warning, error.
	 * @param int    $post_id ID do post relacionado (opcional).
	 * @param array  $context Dados adicionais (opcional).
	 * @return int|false ID do registro ou false em erro.
	 */
	public function log( $event, $message, $level = self::LEVEL_INFO, $post_id = null, $context = array() ) {
		global $wpdb;

		return $wpdb->insert(
			$this->table,
			array(
				'created_at' => current_time( 'mysql' ),
				'level'      => sanitize_text_field( $level ),
				'event'      => sanitize_text_field( $event ),
				'post_id'    => $post_id ? absint( $post_id ) : null,
				'message'    => sanitize_text_field( $message ),
				'context'    => ! empty( $context ) ? wp_json_encode( $context ) : null,
			),
			array( '%s', '%s', '%s', '%d', '%s', '%s' )
		);
	}

	/**
	 * Atalho para log de informacao.
	 *
	 * @param string $event   Evento.
	 * @param string $message Mensagem.
	 * @param int    $post_id ID do post.
	 * @param array  $context Contexto.
	 */
	public function info( $event, $message, $post_id = null, $context = array() ) {
		$this->log( $event, $message, self::LEVEL_INFO, $post_id, $context );
	}

	/**
	 * Atalho para log de sucesso.
	 *
	 * @param string $event   Evento.
	 * @param string $message Mensagem.
	 * @param int    $post_id ID do post.
	 * @param array  $context Contexto.
	 */
	public function success( $event, $message, $post_id = null, $context = array() ) {
		$this->log( $event, $message, self::LEVEL_SUCCESS, $post_id, $context );
	}

	/**
	 * Atalho para log de aviso.
	 *
	 * @param string $event   Evento.
	 * @param string $message Mensagem.
	 * @param int    $post_id ID do post.
	 * @param array  $context Contexto.
	 */
	public function warning( $event, $message, $post_id = null, $context = array() ) {
		$this->log( $event, $message, self::LEVEL_WARNING, $post_id, $context );
	}

	/**
	 * Atalho para log de erro.
	 *
	 * @param string $event   Evento.
	 * @param string $message Mensagem.
	 * @param int    $post_id ID do post.
	 * @param array  $context Contexto.
	 */
	public function error( $event, $message, $post_id = null, $context = array() ) {
		$this->log( $event, $message, self::LEVEL_ERROR, $post_id, $context );
	}

	/**
	 * Retorna logs paginados.
	 *
	 * @param int    $limit  Quantidade de registros.
	 * @param int    $offset Deslocamento.
	 * @param string $level  Filtrar por nivel (opcional).
	 * @return array
	 */
	public function get_logs( $limit = 50, $offset = 0, $level = '' ) {
		global $wpdb;

		$where = '';
		if ( $level ) {
			$where = $wpdb->prepare( 'WHERE level = %s', $level );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$this->table} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$limit,
				$offset
			)
		);
	}

	/**
	 * Retorna a contagem total de logs.
	 *
	 * @param string $level Filtrar por nivel (opcional).
	 * @return int
	 */
	public function count_logs( $level = '' ) {
		global $wpdb;

		if ( $level ) {
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->table} WHERE level = %s", // phpcs:ignore
					$level
				)
			);
		}

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table}" ); // phpcs:ignore
	}

	/**
	 * Remove logs mais antigos que N dias.
	 *
	 * @param int $days Numero de dias.
	 * @return int Numero de registros removidos.
	 */
	public function prune( $days = 30 ) {
		global $wpdb;

		return $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$this->table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)", // phpcs:ignore
				$days
			)
		);
	}

	/**
	 * Retorna resumo de contagens por nivel.
	 *
	 * @return array
	 */
	public function get_summary() {
		global $wpdb;

		$rows = $wpdb->get_results(
			"SELECT level, COUNT(*) as total FROM {$this->table} GROUP BY level" // phpcs:ignore
		);

		$summary = array(
			self::LEVEL_INFO    => 0,
			self::LEVEL_SUCCESS => 0,
			self::LEVEL_WARNING => 0,
			self::LEVEL_ERROR   => 0,
		);

		foreach ( $rows as $row ) {
			if ( isset( $summary[ $row->level ] ) ) {
				$summary[ $row->level ] = (int) $row->total;
			}
		}

		return $summary;
	}
}
