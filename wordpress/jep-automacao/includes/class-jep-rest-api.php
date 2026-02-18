<?php
/**
 * Endpoints REST API para integracao com o n8n.
 *
 * Permite que o n8n crie posts, envie midia e consulte status
 * sem precisar das credenciais padrao da REST API do WordPress.
 *
 * @package JEP_Automacao
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class JEP_Rest_Api
 */
class JEP_Rest_Api {

	/**
	 * Namespace da API.
	 */
	const NAMESPACE = 'jep/v1';

	/**
	 * Construtor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registra as rotas da REST API.
	 */
	public function register_routes() {
		if ( ! jep_automacao()->settings()->is_rest_api_enabled() ) {
			return;
		}

		// Status do plugin.
		register_rest_route(
			self::NAMESPACE,
			'/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_status' ),
				'permission_callback' => array( $this, 'check_token' ),
			)
		);

		// Criar/publicar um post.
		register_rest_route(
			self::NAMESPACE,
			'/posts',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'create_post' ),
				'permission_callback' => array( $this, 'check_token' ),
				'args'                => $this->get_post_args(),
			)
		);

		// Upload de midia por URL.
		register_rest_route(
			self::NAMESPACE,
			'/media/from-url',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'upload_media_from_url' ),
				'permission_callback' => array( $this, 'check_token' ),
				'args'                => array(
					'url'     => array(
						'required'          => true,
						'sanitize_callback' => 'esc_url_raw',
					),
					'title'   => array(
						'sanitize_callback' => 'sanitize_text_field',
						'default'           => '',
					),
					'post_id' => array(
						'sanitize_callback' => 'absint',
						'default'           => 0,
					),
				),
			)
		);

		// Consultar logs.
		register_rest_route(
			self::NAMESPACE,
			'/logs',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_logs' ),
				'permission_callback' => array( $this, 'check_token' ),
				'args'                => array(
					'limit'  => array(
						'default'           => 20,
						'sanitize_callback' => 'absint',
					),
					'offset' => array(
						'default'           => 0,
						'sanitize_callback' => 'absint',
					),
					'level'  => array(
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Receber log externo do n8n.
		register_rest_route(
			self::NAMESPACE,
			'/logs',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'add_log' ),
				'permission_callback' => array( $this, 'check_token' ),
				'args'                => array(
					'event'   => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'message' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'level'   => array(
						'default'           => 'info',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'post_id' => array(
						'sanitize_callback' => 'absint',
						'default'           => 0,
					),
					'context' => array(
						'default' => array(),
					),
				),
			)
		);

		// Disparo manual de workflow.
		register_rest_route(
			self::NAMESPACE,
			'/trigger/(?P<workflow>[a-z0-9\-]+)',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'trigger_workflow' ),
				'permission_callback' => array( $this, 'check_auth' ),
				'args'                => array(
					'workflow' => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					),
				),
			)
		);
	}

	/**
	 * Verifica o token secreto enviado pelo n8n no header X-JEP-Token.
	 *
	 * @param WP_REST_Request $request Objeto da requisicao.
	 * @return bool|WP_Error
	 */
	public function check_token( $request ) {
		$token          = $request->get_header( 'X-JEP-Token' );
		$expected_token = jep_automacao()->settings()->get_n8n_secret_token();

		if ( empty( $expected_token ) || ! hash_equals( $expected_token, (string) $token ) ) {
			return new WP_Error(
				'jep_unauthorized',
				__( 'Token invalido ou ausente.', 'jep-automacao' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * Verifica autenticacao WordPress padrao (para disparos manuais via admin).
	 *
	 * @param WP_REST_Request $request Objeto da requisicao.
	 * @return bool|WP_Error
	 */
	public function check_auth( $request ) {
		if ( current_user_can( 'edit_posts' ) ) {
			return true;
		}
		// Fallback para token.
		return $this->check_token( $request );
	}

	/**
	 * Endpoint GET /jep/v1/status
	 *
	 * @return WP_REST_Response
	 */
	public function get_status( $request ) {
		$settings = jep_automacao()->settings();
		$summary  = jep_automacao()->logger()->get_summary();

		return rest_ensure_response(
			array(
				'status'             => 'ok',
				'plugin_version'     => JEP_AUTOMACAO_VERSION,
				'site_url'           => get_bloginfo( 'url' ),
				'n8n_configured'     => $settings->is_n8n_configured(),
				'webhook_on_publish' => $settings->is_webhook_on_publish_enabled(),
				'log_summary'        => $summary,
				'timestamp'          => current_time( 'c' ),
			)
		);
	}

	/**
	 * Endpoint POST /jep/v1/posts - cria e publica um post vindo do n8n.
	 *
	 * @param WP_REST_Request $request Objeto da requisicao.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_post( $request ) {
		$title           = $request->get_param( 'title' );
		$content         = $request->get_param( 'content' );
		$excerpt         = $request->get_param( 'excerpt' );
		$status          = $request->get_param( 'status' );
		$categories      = $request->get_param( 'categories' );
		$tags            = $request->get_param( 'tags' );
		$meta_desc       = $request->get_param( 'meta_description' );
		$featured_image  = $request->get_param( 'featured_image_url' );
		$source_url      = $request->get_param( 'source_url' );
		$workflow        = $request->get_param( 'workflow' );

		// Resolve categorias (nomes -> IDs).
		$category_ids = array();
		if ( ! empty( $categories ) && is_array( $categories ) ) {
			foreach ( $categories as $cat_name ) {
				$term = get_term_by( 'name', $cat_name, 'category' );
				if ( $term ) {
					$category_ids[] = $term->term_id;
				} else {
					$new_term = wp_insert_term( $cat_name, 'category' );
					if ( ! is_wp_error( $new_term ) ) {
						$category_ids[] = $new_term['term_id'];
					}
				}
			}
		}

		// Monta o post.
		$post_data = array(
			'post_title'   => wp_strip_all_tags( $title ),
			'post_content' => wp_kses_post( $content ),
			'post_excerpt' => wp_strip_all_tags( $excerpt ),
			'post_status'  => in_array( $status, array( 'publish', 'draft', 'pending' ), true ) ? $status : 'draft',
			'post_author'  => get_current_user_id() ?: 1,
			'post_type'    => 'post',
			'post_category'=> $category_ids,
			'tags_input'   => is_array( $tags ) ? $tags : array(),
		);

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			jep_automacao()->logger()->error(
				'post_create_error',
				sprintf( 'Erro ao criar post: %s', $post_id->get_error_message() ),
				null,
				array( 'title' => $title )
			);
			return $post_id;
		}

		// Meta description (compativel com Yoast SEO, Rank Math, AIOSEO).
		if ( $meta_desc ) {
			$this->set_meta_description( $post_id, sanitize_text_field( $meta_desc ) );
		}

		// Meta dados adicionais.
		if ( $source_url ) {
			update_post_meta( $post_id, '_jep_source_url', esc_url_raw( $source_url ) );
		}
		if ( $workflow ) {
			update_post_meta( $post_id, '_jep_workflow', sanitize_text_field( $workflow ) );
		}
		update_post_meta( $post_id, '_jep_created_by_automacao', '1' );

		// Imagem destacada via URL.
		if ( $featured_image ) {
			$attachment_id = $this->sideload_image( $featured_image, $post_id );
			if ( $attachment_id && ! is_wp_error( $attachment_id ) ) {
				set_post_thumbnail( $post_id, $attachment_id );
			}
		}

		jep_automacao()->logger()->success(
			'post_created',
			sprintf( 'Post criado via n8n: "%s" (ID: %d)', $title, $post_id ),
			$post_id,
			array( 'workflow' => $workflow, 'status' => $status )
		);

		return rest_ensure_response(
			array(
				'post_id'   => $post_id,
				'permalink' => get_permalink( $post_id ),
				'status'    => get_post_status( $post_id ),
				'edit_link' => get_edit_post_link( $post_id, 'raw' ),
			)
		);
	}

	/**
	 * Endpoint POST /jep/v1/media/from-url - faz upload de imagem a partir de URL.
	 *
	 * @param WP_REST_Request $request Objeto da requisicao.
	 * @return WP_REST_Response|WP_Error
	 */
	public function upload_media_from_url( $request ) {
		$url     = $request->get_param( 'url' );
		$title   = $request->get_param( 'title' );
		$post_id = $request->get_param( 'post_id' );

		$attachment_id = $this->sideload_image( $url, $post_id, $title );

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		return rest_ensure_response(
			array(
				'attachment_id' => $attachment_id,
				'url'           => wp_get_attachment_url( $attachment_id ),
				'thumbnail_url' => get_the_post_thumbnail_url( $post_id, 'thumbnail' ),
			)
		);
	}

	/**
	 * Endpoint GET /jep/v1/logs - retorna logs da automacao.
	 *
	 * @param WP_REST_Request $request Objeto da requisicao.
	 * @return WP_REST_Response
	 */
	public function get_logs( $request ) {
		$limit  = $request->get_param( 'limit' );
		$offset = $request->get_param( 'offset' );
		$level  = $request->get_param( 'level' );

		$logs  = jep_automacao()->logger()->get_logs( $limit, $offset, $level );
		$total = jep_automacao()->logger()->count_logs( $level );

		return rest_ensure_response(
			array(
				'total'  => $total,
				'limit'  => $limit,
				'offset' => $offset,
				'logs'   => $logs,
			)
		);
	}

	/**
	 * Endpoint POST /jep/v1/logs - registra log externo vindo do n8n.
	 *
	 * @param WP_REST_Request $request Objeto da requisicao.
	 * @return WP_REST_Response
	 */
	public function add_log( $request ) {
		$event   = $request->get_param( 'event' );
		$message = $request->get_param( 'message' );
		$level   = $request->get_param( 'level' );
		$post_id = $request->get_param( 'post_id' ) ?: null;
		$context = $request->get_param( 'context' );

		jep_automacao()->logger()->log( 'n8n_' . $event, '[n8n] ' . $message, $level, $post_id, (array) $context );

		return rest_ensure_response( array( 'logged' => true ) );
	}

	/**
	 * Endpoint POST /jep/v1/trigger/{workflow} - dispara workflow manualmente.
	 *
	 * @param WP_REST_Request $request Objeto da requisicao.
	 * @return WP_REST_Response|WP_Error
	 */
	public function trigger_workflow( $request ) {
		$workflow = $request->get_param( 'workflow' );

		$allowed_workflows = array( 'conteudo-frio', 'conteudo-diario', 'resumo-semanal', 'auto-pesquisa-pautas' );
		if ( ! in_array( $workflow, $allowed_workflows, true ) ) {
			return new WP_Error(
				'jep_invalid_workflow',
				sprintf( __( 'Workflow "%s" nao reconhecido.', 'jep-automacao' ), $workflow ),
				array( 'status' => 400 )
			);
		}

		$response = jep_automacao()->modules['webhook_sender']->trigger_workflow( $workflow );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );

		return rest_ensure_response(
			array(
				'triggered'        => true,
				'workflow'         => $workflow,
				'n8n_status_code'  => $code,
			)
		);
	}

	/**
	 * Faz sideload de uma imagem a partir de URL para a biblioteca de midia.
	 *
	 * @param string $url     URL da imagem.
	 * @param int    $post_id Post ao qual associar (opcional).
	 * @param string $title   Titulo da imagem (opcional).
	 * @return int|WP_Error ID do attachment ou erro.
	 */
	private function sideload_image( $url, $post_id = 0, $title = '' ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = download_url( $url );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		$file_array = array(
			'name'     => $title ? sanitize_file_name( $title ) . '.jpg' : basename( wp_parse_url( $url, PHP_URL_PATH ) ),
			'tmp_name' => $tmp,
		);

		$attachment_id = media_handle_sideload( $file_array, $post_id, $title );

		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $tmp ); // phpcs:ignore
			return $attachment_id;
		}

		return $attachment_id;
	}

	/**
	 * Define a meta description compativel com os principais plugins de SEO.
	 *
	 * @param int    $post_id    ID do post.
	 * @param string $meta_desc  Descricao.
	 */
	private function set_meta_description( $post_id, $meta_desc ) {
		// Yoast SEO.
		update_post_meta( $post_id, '_yoast_wpseo_metadesc', $meta_desc );
		// Rank Math.
		update_post_meta( $post_id, 'rank_math_description', $meta_desc );
		// All in One SEO.
		update_post_meta( $post_id, '_aioseo_description', $meta_desc );
		// Generico.
		update_post_meta( $post_id, '_meta_description', $meta_desc );
	}

	/**
	 * Define os parametros aceitos no endpoint de criacao de posts.
	 *
	 * @return array
	 */
	private function get_post_args() {
		return array(
			'title'             => array(
				'required'          => true,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'content'           => array(
				'required'          => true,
				'sanitize_callback' => 'wp_kses_post',
			),
			'excerpt'           => array(
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'status'            => array(
				'default'           => 'draft',
				'sanitize_callback' => 'sanitize_key',
			),
			'categories'        => array(
				'default' => array(),
			),
			'tags'              => array(
				'default' => array(),
			),
			'meta_description'  => array(
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'featured_image_url'=> array(
				'default'           => '',
				'sanitize_callback' => 'esc_url_raw',
			),
			'source_url'        => array(
				'default'           => '',
				'sanitize_callback' => 'esc_url_raw',
			),
			'workflow'          => array(
				'default'           => '',
				'sanitize_callback' => 'sanitize_key',
			),
		);
	}
}
