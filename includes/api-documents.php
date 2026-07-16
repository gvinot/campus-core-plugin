<?php
defined('ABSPATH') or die('No direct access');

/*
|--------------------------------------------------------------------------
| Campus REST API — Documents administratifs (LOT 5)
|--------------------------------------------------------------------------
| GET /documents  → liste des documents (réservé aux membres connectés)
|--------------------------------------------------------------------------
*/

add_action('rest_api_init', function () {
    register_rest_route('campus/v1', '/documents', [
        'methods'             => 'GET',
        'callback'            => 'campus_api_get_documents',
        'permission_callback' => 'is_user_logged_in',
    ]);
});

function campus_api_get_documents() {

    $posts = get_posts([
        'post_type'      => 'campus_document',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ]);

    $docs = [];
    foreach ($posts as $post) {
        $url = get_post_meta($post->ID, '_campus_doc_url', true);
        if (!$url) continue; // ignorer les documents sans fichier

        $docs[] = [
            'id'          => (int) $post->ID,
            'title'       => $post->post_title,
            'url'         => $url,
            'category'    => get_post_meta($post->ID, '_campus_doc_category', true) ?: 'autre',
            'description' => get_post_meta($post->ID, '_campus_doc_description', true),
        ];
    }

    return new WP_REST_Response(['success' => true, 'documents' => $docs], 200);
}
