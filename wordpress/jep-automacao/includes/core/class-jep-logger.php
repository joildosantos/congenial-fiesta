<?php
/**
 * Registro de atividades do plugin.
 *
 * @package JEP_Automacao
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class JEP_Logger
 *
 * Todos os métodos são estáticos, permitindo tanto JEP_Logger::info()
 * quanto jep_automacao()->logger()->info().
 */
class JEP_Logger {

	const LEVEL_INFO    = 'info';
	const LEVEL_SUCCESS = 'success';
	const LEVEL_WARNING = 'warning';
	const LEVEL_ERROR   = 'error';

	/**
	 * Retorna o nome completo da tabela de logs.
	 *
	 * @return string
	 */
	private static function table() {
		global $wpdb;
		return $wpdb->prefix . 'jep_logs';
	}

	/**
	 * Registra uma mensagem de log.
	 *
	 * @param string     $event   Identificador do evento.
	 * @param string     $message Mensagem descritiva.
	 * @param string     $level   Nivel: info, success, warning, error.
	 * @param int|null   $post_id ID do post relacionado (opcional).
	 * @param array      $context Dados adicionais (opcional).
	 * @return int|false ID do registro ou false em erro.
	 */
	public static function log( $event, $message = '', $level = self::LEVEL_INFO, $post_id = null, $context = array() ) {
		global $wpdb;

		return $wpdb->insert(
			self::table(),
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
	 * @param string         $event   Evento.
	 * @param string         $message Mensagem.
	 * @param int|array|null $post_id ID do post ou array de contexto.
	 * @param array          $context Contexto.
	 */
	public static function info( $event, $message = '', $post_id = null, $context = array() ) {
		self::_normalize( $event, $message, $post_id, $context );
		self::log( $event, $message, self::LEVEL_INFO, $post_id, $context );
	}

	/**
	 * Atalho para log de sucesso.
	 *
	 * @param string         $event   Evento.
	 * @param string         $message Mensagem.
	 * @param int|array|null $post_id ID do post ou array de contexto.
	 * @param array          $context Contexto.
	 */
	public static function success( $event, $message = '', $post_id = null, $context = array() ) {
		self::_normalize( $event, $message, $post_id, $context );
		self::log( $event, $message, self::LEVEL_SUCCESS, $post_id, $context );
	}

	/**
	 * Atalho para log de aviso.
	 *
	 * @param string         $event   Evento.
	 * @param string         $message Mensagem.
	 * @param int|array|null $post_id ID do post ou array de contexto.
	 * @param array          $context Contexto.
	 */
	public static function warning( $event, $message = '', $post_id = null, $context = array() ) {
		self::_normalize( $event, $message, $post_id, $context );
		self::log( $event, $message, self::LEVEL_WARNING, $post_id, $context );
	}

	/**
	 * Atalho para log de erro.
	 *
	 * @param string         $event   Evento.
	 * @param string         $message Mensagem.
	 * @param int|array|null $post_id ID do post ou array de contexto.
	 * @param array          $context Contexto.
	 */
	public static function error( $event, $message = '', $post_id = null, $context = array() ) {
		self::_normalize( $event, $message, $post_id, $context );
		self::log( $event, $message, self::LEVEL_ERROR, $post_id, $context );
	}

	/**
	 * Alias de info() para compatibilidade com código que usa ::debug().
	 *
	 * @param string         $event   Evento.
	 * @param string         $message Mensagem.
	 * @param int|array|null $post_id ID do post ou array de contexto.
	 * @param array          $context Contexto.
	 */
	public static function debug( $event, $message = '', $post_id = null, $context = array() ) {
		self::info( $event, $message, $post_id, $context );
	}

	/**
	 * Normaliza argumentos para suportar chamadas legadas com assinatura incorreta:
	 *   info('mensagem')               -> event='plugin', message='mensagem'
	 *   info('mensagem', ['contexto']) -> event='plugin', message='mensagem', context=[...]
	 *   info('event', 'mensagem', ['contexto']) -> post_id=context
	 *
	 * @param string         $event   Passed by reference.
	 * @param string         $message Passed by reference.
	 * @param int|array|null $post_id Passed by reference.
	 * @param array          $context Passed by reference.
	 */
	private static function _normalize( &$event, &$message, &$post_id, &$context ) {
		// info('mensagem', ['contexto']) — second arg is array, treat as context.
		if ( is_array( $message ) ) {
			$context = $message;
			$message = $event;
			$event   = 'plugin';
		}

		// info('mensagem') — message omitted, treat first arg as message.
		if ( '' === $message ) {
			$message = $event;
			$event   = 'plugin';
		}

		// Third arg is array (context passed in post_id position).
		if ( is_array( $post_id ) ) {
			$context = $post_id;
			$post_id = null;
		}
	}

	/**
	 * Retorna logs paginados.
	 *
	 * @param int    $limit  Quantidade de registros.
	 * @param int    $offset Deslocamento.
	 * @param string $level  Filtrar por nivel (opcional).
	 * @return array
	 */
	public static function get_logs( $limit = 50, $offset = 0, $level = '' ) {
		global $wpdb;
		$table = self::table();

		$where = '';
		if ( $level ) {
			$where = $wpdb->prepare( 'WHERE level = %s', $level );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
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
	public static function count_logs( $level = '' ) {
		global $wpdb;
		$table = self::table();

		if ( $level ) {
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE level = %s", // phpcs:ignore
					$level
				)
			);
		}

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ); // phpcs:ignore
	}

	/**
	 * Retorna logs paginados com total — para uso via REST API.
	 *
	 * @param int   $page     Pagina (1-based).
	 * @param int   $per_page Itens por pagina.
	 * @param array $filters  Filtros opcionais: 'level', 'event'.
	 * @return array { logs: array, total: int, pages: int }
	 */
	public static function get_paginated( $page = 1, $per_page = 20, $filters = array() ) {
		global $wpdb;
		$table = self::table();

		$wheres = array();
		$values = array();

		if ( ! empty( $filters['level'] ) ) {
			$wheres[] = 'level = %s';
			$values[] = sanitize_text_field( $filters['level'] );
		}
		if ( ! empty( $filters['event'] ) ) {
			$wheres[] = 'event LIKE %s';
			$values[] = '%' . $wpdb->esc_like( sanitize_text_field( $filters['event'] ) ) . '%';
		}

		$where_sql = $wheres ? 'WHERE ' . implode( ' AND ', $wheres ) : '';
		$offset    = ( max( 1, (int) $page ) - 1 ) * (int) $per_page;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $values ) {
			$total = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT COUNT(*) FROM {$table} {$where_sql}", ...$values )
			);
			$logs = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d",
					...array_merge( $values, array( (int) $per_page, $offset ) )
				),
				ARRAY_A
			);
		} else {
			$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
			$logs  = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
					(int) $per_page,
					$offset
				),
				ARRAY_A
			);
		}
		// phpcs:enable

		return array(
			'logs'  => $logs,
			'total' => $total,
			'pages' => $per_page > 0 ? (int) ceil( $total / $per_page ) : 1,
		);
	}

	/**
	 * Remove logs mais antigos que N dias. 0 = remove todos.
	 *
	 * @param int $days Numero de dias.
	 * @return int Numero de registros removidos.
	 */
	public static function prune( $days = 30 ) {
		global $wpdb;
		$table = self::table();

		if ( 0 === (int) $days ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return (int) $wpdb->query( "DELETE FROM {$table}" );
		}

		return (int) $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)", // phpcs:ignore
				$days
			)
		);
	}

	/**
	 * Retorna resumo de contagens por nivel.
	 *
	 * @return array
	 */
	public static function get_summary() {
		global $wpdb;
		$table = self::table();

		$rows = $wpdb->get_results(
			"SELECT level, COUNT(*) as total FROM {$table} GROUP BY level" // phpcs:ignore
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
