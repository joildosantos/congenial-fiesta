<?php
/**
 * Webhook de publicacao - Jornal Espaco do Povo
 *
 * Adicione este codigo ao functions.php do tema ativo do WordPress
 * ou crie um plugin mu-plugin em wp-content/mu-plugins/
 *
 * Dispara um webhook para o n8n sempre que um post e publicado,
 * acionando o workflow de distribuicao multi-canal.
 */

add_action('transition_post_status', 'jep_notify_n8n_on_publish', 10, 3);

function jep_notify_n8n_on_publish($new_status, $old_status, $post) {
    // Apenas quando um post muda para 'publish' (nao em atualizacoes)
    if ($new_status !== 'publish' || $old_status === 'publish') {
        return;
    }

    // Apenas posts (nao paginas, etc)
    if ($post->post_type !== 'post') {
        return;
    }

    // URL do webhook do n8n - ALTERE para o endereco do seu servidor
    $webhook_url = defined('N8N_WEBHOOK_URL')
        ? N8N_WEBHOOK_URL
        : 'http://localhost:5678/webhook/wp-post-published';

    // Dados do post
    $featured_image = get_the_post_thumbnail_url($post->ID, 'full');
    $categories = wp_get_post_categories($post->ID, array('fields' => 'names'));
    $tags = wp_get_post_tags($post->ID, array('fields' => 'names'));

    $data = array(
        'ID'                  => $post->ID,
        'post_title'          => $post->post_title,
        'post_excerpt'        => $post->post_excerpt,
        'post_content'        => wp_trim_words($post->post_content, 80),
        'link'                => get_permalink($post->ID),
        'featured_image_url'  => $featured_image ?: '',
        'categories_names'    => $categories,
        'tags_names'          => $tags,
        'post_date'           => $post->post_date,
        'author_name'         => get_the_author_meta('display_name', $post->post_author),
    );

    // Envia o webhook de forma assincrona (nao bloqueia a publicacao)
    wp_remote_post($webhook_url, array(
        'body'      => wp_json_encode($data),
        'headers'   => array('Content-Type' => 'application/json'),
        'timeout'   => 15,
        'blocking'  => false,
    ));
}

/**
 * Opcional: Defina a URL do webhook no wp-config.php:
 * define('N8N_WEBHOOK_URL', 'https://seu-servidor.com/webhook/wp-post-published');
 */
