<?php
/**
 * Envio de webhooks para o n8n.
 *
 * @package JEP_Automacao
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class JEP_Webhook_Sender
 */
class JEP_Webhook_Sender {

	/**
	 * Construtor - registra os hooks do WordPress.
	 */
	public function __construct() {
		add_action( 'transition_post_status', array( $this, 'on_post_published' ), 10, 3 );
	}

	/**
	 * Disparado quando um post muda de status.
	 * Envia webhook ao n8n quando um post e publicado pela primeira vez.
	 *
	 * @param string  $new_status Novo status.
	 * @param string  $old_status Status anterior.
	 * @param WP_Post $post       Objeto do post.
	 */
	public function on_post_published( $new_status, $old_status, $post ) {
		$settings = jep_automacao()->settings();

		if ( ! $settings->is_webhook_on_publish_enabled() ) {
			return;
		}

		// Apenas na transicao para 'publish'.
		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return;
		}

		// Apenas tipos de post monitorados.
		if ( ! in_array( $post->post_type, $settings->get_post_types_to_watch(), true ) ) {
			return;
		}

		// Evita disparos em saves automaticos ou revisoes.
		if ( wp_is_post_revision( $post->ID ) || wp_is_post_autosave( $post->ID ) ) {
			return;
		}

		$this->send_post_published_webhook( $post );
	}

	/**
	 * Envia o webhook de post publicado para o n8n.
	 *
	 * @param WP_Post $post Objeto do post.
	 * @return bool Sucesso ou falha.
	 */
	public function send_post_published_webhook( $post ) {
		$settings    = jep_automacao()->settings();
		$webhook_url = $settings->get_n8n_webhook_url();

		if ( empty( $webhook_url ) ) {
			jep_automacao()->logger()->warning(
				'webhook_skipped',
				'Webhook nao enviado: URL do n8n nao configurada.',
				$post->ID
			);
			return false;
		}

		$payload = $this->build_post_payload( $post );

		$response = wp_remote_post(
			$webhook_url,
			array(
				'body'     => wp_json_encode( $payload ),
				'headers'  => array(
					'Content-Type'    => 'application/json',
					'X-JEP-Token'     => $settings->get_n8n_secret_token(),
					'X-JEP-Event'     => 'post_published',
					'X-WP-Site'       => get_bloginfo( 'url' ),
				),
				'timeout'  => 15,
				'blocking' => false, // Assincrono: nao bloqueia a publicacao.
			)
		);

		if ( is_wp_error( $response ) ) {
			jep_automacao()->logger()->error(
				'webhook_error',
				sprintf( 'Erro ao enviar webhook: %s', $response->get_error_message() ),
				$post->ID
			);
			return false;
		}

		jep_automacao()->logger()->success(
			'webhook_sent',
			sprintf( 'Webhook enviado para n8n: post "%s"', $post->post_title ),
			$post->ID,
			array( 'url' => $webhook_url )
		);

		return true;
	}

	/**
	 * Envia um webhook de trigger manual de workflow.
	 *
	 * @param string $workflow Identificador do workflow (ex: 'conteudo-frio', 'conteudo-diario').
	 * @return array|WP_Error Resposta da requisicao.
	 */
	public function trigger_workflow( $workflow ) {
		$settings    = jep_automacao()->settings();
		$base_url    = trailingslashit( rtrim( $settings->get_n8n_webhook_url(), '/wp-post-published' ) );
		$webhook_url = $base_url . 'webhook/trigger-' . sanitize_key( $workflow );

		$response = wp_remote_post(
			$webhook_url,
			array(
				'body'    => wp_json_encode(
					array(
						'source'     => 'wordpress_plugin',
						'workflow'   => $workflow,
						'triggered_at' => current_time( 'c' ),
						'site_url'   => get_bloginfo( 'url' ),
					)
				),
				'headers' => array(
					'Content-Type' => 'application/json',
					'X-JEP-Token'  => $settings->get_n8n_secret_token(),
					'X-JEP-Event'  => 'manual_trigger',
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			jep_automacao()->logger()->error(
				'workflow_trigger_error',
				sprintf( 'Erro ao disparar workflow "%s": %s', $workflow, $response->get_error_message() )
			);
			return $response;
		}

		jep_automacao()->logger()->success(
			'workflow_triggered',
			sprintf( 'Workflow "%s" disparado manualmente.', $workflow )
		);

		return $response;
	}

	/**
	 * Monta o payload de um post para envio ao n8n.
	 *
	 * @param WP_Post $post Objeto do post.
	 * @return array
	 */
	private function build_post_payload( $post ) {
		$featured_image_url = get_the_post_thumbnail_url( $post->ID, 'full' );
		$categories         = wp_get_post_categories( $post->ID, array( 'fields' => 'names' ) );
		$tags               = wp_get_post_tags( $post->ID, array( 'fields' => 'names' ) );
		$author_name        = get_the_author_meta( 'display_name', $post->post_author );

		return array(
			'event'               => 'post_published',
			'post_id'             => $post->ID,
			'post_title'          => $post->post_title,
			'post_excerpt'        => $post->post_excerpt ?: wp_trim_words( $post->post_content, 55 ),
			'post_content_short'  => wp_trim_words( $post->post_content, 80 ),
			'post_status'         => $post->post_status,
			'post_type'           => $post->post_type,
			'post_date'           => $post->post_date,
			'permalink'           => get_permalink( $post->ID ),
			'featured_image_url'  => $featured_image_url ?: '',
			'categories'          => $categories,
			'tags'                => $tags,
			'author_name'         => $author_name,
			'site_name'           => get_bloginfo( 'name' ),
			'site_url'            => get_bloginfo( 'url' ),
		);
	}
}
