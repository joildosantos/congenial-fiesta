<?php
/**
 * REST API handler for JEP Automação Editorial
 *
 * Registers all REST routes under the jep/v1 namespace.
 *
 * @package JEP_Automacao
 * @since   2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class JEP_Rest_Api {

    /**
     * REST namespace.
     *
     * @var string
     */
    const NAMESPACE = 'jep/v1';

    /**
     * Constructor — hooks REST route registration.
     */
    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
    }

    /**
     * Register all plugin REST routes.
     */
    public function register_routes() {

        // GET /jep/v1/status — public health check.
        register_rest_route(
            self::NAMESPACE,
            '/status',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_status' ],
                'permission_callback' => '__return_true',
            ]
        );

        // POST /jep/v1/telegram-webhook — Telegram update receiver.
        register_rest_route(
            self::NAMESPACE,
            '/telegram-webhook',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'handle_telegram_webhook' ],
                'permission_callback' => [ $this, 'verify_telegram_webhook' ],
            ]
        );

        // POST /jep/v1/posts — create a WP post via token auth.
        register_rest_route(
            self::NAMESPACE,
            '/posts',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'create_post' ],
                'permission_callback' => [ $this, 'verify_secret_token' ],
                'args'                => $this->get_create_post_args(),
            ]
        );

        // GET /jep/v1/logs — paginated log viewer (token auth).
        register_rest_route(
            self::NAMESPACE,
            '/logs',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [ $this, 'get_logs' ],
                'permission_callback' => [ $this, 'verify_secret_token' ],
                'args'                => [
                    'page'     => [
                        'default'           => 1,
                        'sanitize_callback' => 'absint',
                    ],
                    'per_page' => [
                        'default'           => 50,
                        'sanitize_callback' => 'absint',
                    ],
                    'level'    => [
                        'default'           => '',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                    'event'    => [
                        'default'           => '',
                        'sanitize_callback' => 'sanitize_text_field',
                    ],
                ],
            ]
        );

        // POST /jep/v1/trigger/{workflow} — manual workflow trigger.
        register_rest_route(
            self::NAMESPACE,
            '/trigger/(?P<workflow>[a-z_]+)',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [ $this, 'trigger_workflow' ],
                'permission_callback' => [ $this, 'verify_trigger_permission' ],
                'args'                => [
                    'workflow' => [
                        'required'          => true,
                        'sanitize_callback' => 'sanitize_key',
                    ],
                ],
            ]
        );
    }

    // -------------------------------------------------------------------------
    // Permission callbacks
    // -------------------------------------------------------------------------

    /**
     * Verify the X-JEP-Token header against the stored REST API secret.
     *
     * @param WP_REST_Request $request Incoming request.
     * @return bool|WP_Error
     */
    public function verify_secret_token( WP_REST_Request $request ) {
        $token    = $request->get_header( 'X-JEP-Token' );
        $expected = jep_automacao()->settings()->get_rest_api_secret();

        if ( empty( $expected ) ) {
            return new WP_Error(
                'jep_no_secret',
                __( 'REST API secret not configured.', 'jep-automacao' ),
                [ 'status' => 500 ]
            );
        }

        if ( ! hash_equals( $expected, (string) $token ) ) {
            return new WP_Error(
                'jep_forbidden',
                __( 'Invalid or missing API token.', 'jep-automacao' ),
                [ 'status' => 403 ]
            );
        }

        return true;
    }

    /**
     * Verify X-Telegram-Bot-Api-Secret-Token against the stored webhook secret.
     *
     * @param WP_REST_Request $request Incoming request.
     * @return bool|WP_Error
     */
    public function verify_telegram_webhook( WP_REST_Request $request ) {
        $token    = $request->get_header( 'X-Telegram-Bot-Api-Secret-Token' );
        $expected = jep_automacao()->settings()->get_telegram_webhook_secret();

        if ( empty( $expected ) ) {
            // If no secret is configured, allow through (development mode).
            return true;
        }

        if ( ! hash_equals( $expected, (string) $token ) ) {
            return new WP_Error(
                'jep_telegram_forbidden',
                __( 'Invalid Telegram webhook secret.', 'jep-automacao' ),
                [ 'status' => 403 ]
            );
        }

        return true;
    }

    /**
     * Verify trigger permission: either valid token OR WP admin user.
     *
     * @param WP_REST_Request $request Incoming request.
     * @return bool|WP_Error
     */
    public function verify_trigger_permission( WP_REST_Request $request ) {
        // Allow WP admins.
        if ( current_user_can( 'manage_options' ) ) {
            return true;
        }

        // Also allow valid token.
        return $this->verify_secret_token( $request );
    }

    // -------------------------------------------------------------------------
    // Route callbacks
    // -------------------------------------------------------------------------

    /**
     * GET /status — plugin health check.
     *
     * @param WP_REST_Request $request Incoming request.
     * @return WP_REST_Response
     */
    public function get_status( WP_REST_Request $request ) {
        $settings_obj = jep_automacao()->settings();
        $settings     = $settings_obj ? $settings_obj->get_all() : [];

        $modules = [
            'telegram'         => [
                'configured' => ! empty( $settings['telegram_bot_token'] ),
            ],
            'llm'              => [
                'configured' => class_exists( 'JEP_LLM_Manager' ) && ! empty( ( new JEP_LLM_Manager() )->get_active_providers() ),
            ],
            'rss'              => [
                'configured' => class_exists( 'JEP_RSS_Manager' ) && ! empty( ( new JEP_RSS_Manager() )->get_feeds( true ) ),
            ],
            'instagram'        => [
                'configured' => ! empty( $settings['instagram_enabled'] ) && ! empty( $settings['instagram_account_id'] ),
            ],
            'image_generation' => [
                'configured' => ! empty( $settings['ai_images_enabled'] ),
            ],
            'source_discovery' => [
                'configured' => class_exists( 'JEP_Source_Discovery' ),
            ],
        ];

        $log_summary = [];
        if ( class_exists( 'JEP_Logger' ) ) {
            $log_summary = JEP_Logger::get_summary();
        }

        $cron_status = [];
        if ( class_exists( 'JEP_Scheduler' ) ) {
            $cron_status = ( new JEP_Scheduler() )->get_cron_status();
        }

        $data = [
            'version'     => JEP_AUTOMACAO_VERSION,
            'modules'     => $modules,
            'log_summary' => $log_summary,
            'cron_status' => $cron_status,
        ];

        return new WP_REST_Response( $data, 200 );
    }

    /**
     * POST /telegram-webhook — receives and dispatches Telegram updates.
     *
     * @param WP_REST_Request $request Incoming request.
     * @return WP_REST_Response
     */
    public function handle_telegram_webhook( WP_REST_Request $request ) {
        $update = $request->get_json_params();

        if ( empty( $update ) ) {
            JEP_Logger::warning( 'telegram_webhook', 'Empty or non-JSON body received.' );
            return new WP_REST_Response( [ 'ok' => true ], 200 );
        }

        JEP_Logger::debug( 'telegram_webhook', 'Update received.', [
            'update_id' => $update['update_id'] ?? null,
        ] );

        if ( class_exists( 'JEP_Telegram_Publisher' ) ) {
            try {
                ( new JEP_Telegram_Publisher() )->handle_update( $update );
            } catch ( Exception $e ) {
                JEP_Logger::error( 'telegram_webhook', 'Exception while handling update: ' . $e->getMessage(), [
                    'trace' => $e->getTraceAsString(),
                ] );
            }
        }

        // Always return 200 to Telegram to prevent retries.
        return new WP_REST_Response( [ 'ok' => true ], 200 );
    }

    /**
     * POST /posts — create a WordPress post.
     *
     * @param WP_REST_Request $request Incoming request.
     * @return WP_REST_Response|WP_Error
     */
    public function create_post( WP_REST_Request $request ) {
        $params = $request->get_json_params();

        $title   = sanitize_text_field( $params['title'] ?? '' );
        $content = wp_kses_post( $params['content'] ?? '' );
        $excerpt = sanitize_textarea_field( $params['excerpt'] ?? '' );
        $status  = sanitize_key( $params['status'] ?? 'draft' );
        $author  = absint( $params['author'] ?? get_current_user_id() );

        if ( empty( $title ) ) {
            return new WP_Error(
                'jep_missing_title',
                __( 'Post title is required.', 'jep-automacao' ),
                [ 'status' => 400 ]
            );
        }

        // Validate post status.
        $allowed_statuses = [ 'draft', 'pending', 'publish', 'private' ];
        if ( ! in_array( $status, $allowed_statuses, true ) ) {
            $status = 'draft';
        }

        $post_data = [
            'post_title'   => $title,
            'post_content' => $content,
            'post_excerpt' => $excerpt,
            'post_status'  => $status,
            'post_author'  => $author,
            'post_type'    => 'post',
        ];

        // Optional category/tags.
        if ( ! empty( $params['categories'] ) && is_array( $params['categories'] ) ) {
            $post_data['post_category'] = array_map( 'absint', $params['categories'] );
        }

        if ( ! empty( $params['tags'] ) && is_array( $params['tags'] ) ) {
            $post_data['tags_input'] = array_map( 'sanitize_text_field', $params['tags'] );
        }

        $post_id = wp_insert_post( $post_data, true );

        if ( is_wp_error( $post_id ) ) {
            JEP_Logger::error( 'rest_create_post', 'Failed to insert post: ' . $post_id->get_error_message() );
            return new WP_Error(
                'jep_insert_failed',
                $post_id->get_error_message(),
                [ 'status' => 500 ]
            );
        }

        // Save custom meta if present.
        if ( ! empty( $params['meta'] ) && is_array( $params['meta'] ) ) {
            foreach ( $params['meta'] as $meta_key => $meta_value ) {
                $safe_key = sanitize_key( $meta_key );
                // Only allow jep_ prefixed meta from the API.
                if ( strpos( $safe_key, 'jep_' ) === 0 ) {
                    update_post_meta( $post_id, $safe_key, sanitize_text_field( $meta_value ) );
                }
            }
        }

        // Handle featured image from URL.
        if ( ! empty( $params['featured_image_url'] ) ) {
            $image_url = esc_url_raw( $params['featured_image_url'] );
            $this->set_featured_image_from_url( $post_id, $image_url );
        }

        JEP_Logger::info( 'rest_create_post', "Post #{$post_id} created via REST API.", [
            'post_id' => $post_id,
            'status'  => $status,
        ] );

        return new WP_REST_Response(
            [
                'id'        => $post_id,
                'title'     => $title,
                'status'    => $status,
                'link'      => get_permalink( $post_id ),
                'edit_link' => get_edit_post_link( $post_id, 'raw' ),
            ],
            201
        );
    }

    /**
     * GET /logs — paginated log retrieval.
     *
     * @param WP_REST_Request $request Incoming request.
     * @return WP_REST_Response
     */
    public function get_logs( WP_REST_Request $request ) {
        $page     = $request->get_param( 'page' );
        $per_page = min( $request->get_param( 'per_page' ), 200 );
        $level    = $request->get_param( 'level' );
        $event    = $request->get_param( 'event' );

        $filters = [];
        if ( ! empty( $level ) ) {
            $filters['level'] = sanitize_text_field( $level );
        }
        if ( ! empty( $event ) ) {
            $filters['event'] = sanitize_text_field( $event );
        }

        $result = JEP_Logger::get_paginated( $page, $per_page, $filters );

        return new WP_REST_Response(
            [
                'logs'       => $result['logs'],
                'total'      => $result['total'],
                'page'       => $page,
                'per_page'   => $per_page,
                'total_pages' => ceil( $result['total'] / $per_page ),
            ],
            200
        );
    }

    /**
     * POST /trigger/{workflow} — manually run a scheduled workflow.
     *
     * @param WP_REST_Request $request Incoming request.
     * @return WP_REST_Response|WP_Error
     */
    public function trigger_workflow( WP_REST_Request $request ) {
        $workflow = $request->get_param( 'workflow' );

        $allowed_workflows = [
            'daily_content',
            'cold_content',
            'topic_research',
            'source_discovery',
            'weekly_summary',
        ];

        if ( ! in_array( $workflow, $allowed_workflows, true ) ) {
            return new WP_Error(
                'jep_invalid_workflow',
                sprintf(
                    /* translators: %s: workflow name */
                    __( 'Unknown workflow: %s', 'jep-automacao' ),
                    esc_html( $workflow )
                ),
                [ 'status' => 400 ]
            );
        }

        if ( ! class_exists( 'JEP_Scheduler' ) ) {
            return new WP_Error(
                'jep_scheduler_missing',
                __( 'Scheduler class not available.', 'jep-automacao' ),
                [ 'status' => 500 ]
            );
        }

        JEP_Logger::info( 'rest_trigger_workflow', "Manual trigger requested for: {$workflow}" );

        $result = ( new JEP_Scheduler() )->run_now( $workflow );

        return new WP_REST_Response(
            [
                'workflow' => $workflow,
                'result'   => $result,
                'triggered_at' => current_time( 'c' ),
            ],
            200
        );
    }

    // -------------------------------------------------------------------------
    // Helper methods
    // -------------------------------------------------------------------------

    /**
     * Sideload an image from a URL and set it as the featured image for a post.
     *
     * @param int    $post_id   Post ID.
     * @param string $image_url Remote image URL.
     * @return int|false Attachment ID on success, false on failure.
     */
    private function set_featured_image_from_url( int $post_id, string $image_url ) {
        if ( ! function_exists( 'media_sideload_image' ) ) {
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $attachment_id = media_sideload_image( $image_url, $post_id, null, 'id' );

        if ( is_wp_error( $attachment_id ) ) {
            JEP_Logger::warning( 'rest_create_post', 'Failed to sideload featured image: ' . $attachment_id->get_error_message(), [
                'url'     => $image_url,
                'post_id' => $post_id,
            ] );
            return false;
        }

        set_post_thumbnail( $post_id, $attachment_id );

        return $attachment_id;
    }

    /**
     * Define argument schema for the create_post endpoint.
     *
     * @return array
     */
    private function get_create_post_args() {
        return [
            'title'              => [
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'content'            => [
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'wp_kses_post',
            ],
            'excerpt'            => [
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'sanitize_textarea_field',
            ],
            'status'             => [
                'type'    => 'string',
                'default' => 'draft',
                'enum'    => [ 'draft', 'pending', 'publish', 'private' ],
            ],
            'author'             => [
                'type'              => 'integer',
                'default'           => 0,
                'sanitize_callback' => 'absint',
            ],
            'categories'         => [
                'type'  => 'array',
                'items' => [ 'type' => 'integer' ],
            ],
            'tags'               => [
                'type'  => 'array',
                'items' => [ 'type' => 'string' ],
            ],
            'featured_image_url' => [
                'type'              => 'string',
                'format'            => 'uri',
                'sanitize_callback' => 'esc_url_raw',
            ],
            'meta'               => [
                'type' => 'object',
            ],
        ];
    }
}
