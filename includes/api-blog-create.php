<?php
defined('ABSPATH') or die('No direct access');

/*
|--------------------------------------------------------------------------
| Campus REST API — Création de blog & Bio (Sprint 5 + LOT1 images)
|--------------------------------------------------------------------------
*/

add_action('rest_api_init', 'campus_register_blog_create_routes');

function campus_register_blog_create_routes() {

    register_rest_route('campus/v1', '/blogs/create', [
        'methods'             => 'POST',
        'callback'            => 'campus_api_create_blog',
        'permission_callback' => 'is_user_logged_in',
    ]);

    register_rest_route('campus/v1', '/blogs/(?P<id>\d+)', [
        'methods'             => 'DELETE',
        'callback'            => 'campus_api_delete_blog',
        'permission_callback' => 'is_user_logged_in',
        'args'                => [
            'id' => ['validate_callback' => function($v){ return is_numeric($v) && $v > 0; }],
        ],
    ]);

    register_rest_route('campus/v1', '/users/me/bio', [
        'methods'             => 'POST',
        'callback'            => 'campus_api_update_bio',
        'permission_callback' => 'is_user_logged_in',
    ]);
}

/*
|--------------------------------------------------------------------------
| POST /blogs/create  (avec image à la une optionnelle)
| Body JSON : { "title", "content", "image_data" (base64 optionnel) }
|--------------------------------------------------------------------------
*/
function campus_api_create_blog($request) {
    $user_id = get_current_user_id();

    if (!campus_is_blogger($user_id) && !campus_is_admin($user_id)) {
        return new WP_REST_Response(['error' => 'Seuls les blogueurs peuvent publier un blog.'], 403);
    }
    if (campus_is_banned($user_id)) {
        return new WP_REST_Response(['error' => 'Votre compte est suspendu.'], 403);
    }

    $title   = sanitize_text_field($request->get_param('title'));
    $content = wp_kses_post($request->get_param('content'));

    if (empty($title) || empty($content)) {
        return new WP_REST_Response(['error' => 'Le titre et le contenu sont obligatoires.'], 400);
    }

    $rate_key = 'campus_blog_create_' . $user_id;
    if (get_transient($rate_key)) {
        return new WP_REST_Response(['error' => 'Veuillez patienter avant de publier à nouveau.'], 429);
    }
    set_transient($rate_key, 1, 30);

    $post_id = wp_insert_post([
        'post_type'    => 'campus_blog',
        'post_title'   => $title,
        'post_content' => $content,
        'post_status'  => 'publish',
        'post_author'  => $user_id,
    ], true);

    if (is_wp_error($post_id)) {
        return new WP_REST_Response(['error' => 'Erreur lors de la création du blog.'], 500);
    }

    // Image à la une (optionnelle)
    $image_data = $request->get_param('image_data');
    if (!empty($image_data)) {
        $attach_id = campus_handle_base64_image($image_data, $post_id);
        if ($attach_id) {
            set_post_thumbnail($post_id, $attach_id);
        }
    }

    $post = get_post($post_id);

    return new WP_REST_Response([
        'success' => true,
        'blog'    => campus_format_blog($post, 0),
    ], 201);
}

/*
|--------------------------------------------------------------------------
| Helper : enregistrer une image base64 comme média WordPress
| Retourne l'ID de l'attachment ou false
|--------------------------------------------------------------------------
*/
function campus_handle_base64_image($base64, $post_id) {

    if (!preg_match('/^data:image\/(\w+);base64,/', $base64, $m)) {
        return false;
    }

    $ext     = strtolower($m[1]);
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($ext, $allowed, true)) return false;

    $data = base64_decode(substr($base64, strpos($base64, ',') + 1));
    if ($data === false) return false;

    // Limite ~5 Mo
    if (strlen($data) > 5 * 1024 * 1024) return false;

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $filename = 'blog-' . $post_id . '-' . time() . '.' . $ext;
    $upload   = wp_upload_bits($filename, null, $data);

    if (!empty($upload['error'])) return false;

    $filetype   = wp_check_filetype($upload['file']);
    $attachment = [
        'post_mime_type' => $filetype['type'],
        'post_title'     => sanitize_file_name($filename),
        'post_content'   => '',
        'post_status'    => 'inherit',
    ];

    $attach_id   = wp_insert_attachment($attachment, $upload['file'], $post_id);
    $attach_data = wp_generate_attachment_metadata($attach_id, $upload['file']);
    wp_update_attachment_metadata($attach_id, $attach_data);

    return $attach_id;
}

/*
|--------------------------------------------------------------------------
| DELETE /blogs/{id}
|--------------------------------------------------------------------------
*/
function campus_api_delete_blog($request) {
    $user_id = get_current_user_id();
    $post_id = absint($request['id']);

    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'campus_blog') {
        return new WP_REST_Response(['error' => 'Blog introuvable.'], 404);
    }

    if ((int) $post->post_author !== $user_id && !campus_is_admin($user_id)) {
        return new WP_REST_Response(['error' => 'Vous ne pouvez supprimer que vos propres blogs.'], 403);
    }

    wp_delete_post($post_id, true);

    return new WP_REST_Response(['success' => true, 'action' => 'deleted'], 200);
}

/*
|--------------------------------------------------------------------------
| POST /users/me/bio
|--------------------------------------------------------------------------
*/
function campus_api_update_bio($request) {
    $user_id = get_current_user_id();
    $bio     = sanitize_textarea_field($request->get_param('bio'));

    if (mb_strlen($bio) > 500) {
        return new WP_REST_Response(['error' => 'La bio ne peut pas dépasser 500 caractères.'], 400);
    }

    update_user_meta($user_id, 'campus_bio', $bio);

    return new WP_REST_Response(['success' => true, 'bio' => $bio], 200);
}
