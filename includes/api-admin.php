<?php
defined('ABSPATH') or die('No direct access');

/*
|--------------------------------------------------------------------------
| Campus REST API — Admin
|--------------------------------------------------------------------------
| Endpoints :
|   GET  /actualites                  → liste des actualités (public)
|   GET  /actualites/{id}             → détail d'une actualité (public)
|   POST /admin/users/{id}/status     → changer statut utilisateur (admin only)
|   POST /admin/users/{id}/ban        → bannir un utilisateur (admin only)
|   POST /admin/users/{id}/unban      → débannir un utilisateur (admin only)
|--------------------------------------------------------------------------
*/

add_action('rest_api_init', 'campus_register_admin_routes');

function campus_register_admin_routes() {

    /*
    |--------------------------------------------------------------------------
    | ACTUALITÉS — publiques
    |--------------------------------------------------------------------------
    */

    register_rest_route('campus/v1', '/actualites', [
        'methods'             => 'GET',
        'callback'            => 'campus_api_get_actualites',
        'permission_callback' => '__return_true',
    ]);

    register_rest_route('campus/v1', '/actualites/(?P<id>\d+)', [
        'methods'             => 'GET',
        'callback'            => 'campus_api_get_actualite',
        'permission_callback' => '__return_true',
        'args'                => [
            'id' => [
                'validate_callback' => function($v) {
                    return is_numeric($v) && $v > 0;
                },
            ],
        ],
    ]);

    /*
    |--------------------------------------------------------------------------
    | ADMIN — réservé aux administrateurs
    |--------------------------------------------------------------------------
    */

    // Changer le statut (student ↔ blogger)
    register_rest_route('campus/v1', '/admin/users/(?P<id>\d+)/status', [
        'methods'             => 'POST',
        'callback'            => 'campus_api_admin_set_status',
        'permission_callback' => 'campus_permission_admin_only',
        'args'                => [
            'id' => [
                'validate_callback' => function($v) {
                    return is_numeric($v) && $v > 0;
                },
            ],
        ],
    ]);

    // Bannir
    register_rest_route('campus/v1', '/admin/users/(?P<id>\d+)/ban', [
        'methods'             => 'POST',
        'callback'            => 'campus_api_admin_ban_user',
        'permission_callback' => 'campus_permission_admin_only',
        'args'                => [
            'id' => [
                'validate_callback' => function($v) {
                    return is_numeric($v) && $v > 0;
                },
            ],
        ],
    ]);

    // Débannir
    register_rest_route('campus/v1', '/admin/users/(?P<id>\d+)/unban', [
        'methods'             => 'POST',
        'callback'            => 'campus_api_admin_unban_user',
        'permission_callback' => 'campus_permission_admin_only',
        'args'                => [
            'id' => [
                'validate_callback' => function($v) {
                    return is_numeric($v) && $v > 0;
                },
            ],
        ],
    ]);
}

/*
|--------------------------------------------------------------------------
| Permission callback — admin uniquement
| Utilisé par tous les endpoints /admin/*
|--------------------------------------------------------------------------
*/
function campus_permission_admin_only() {
    return is_user_logged_in() && campus_is_admin();
}

/*
|--------------------------------------------------------------------------
| HANDLERS — Actualités
|--------------------------------------------------------------------------
*/

/*
| GET /actualites
| Paramètres optionnels : ?per_page=10 &page=1
|--------------------------------------------------------------------------
*/
function campus_api_get_actualites($request) {
    $per_page = min(absint($request->get_param('per_page') ?: 10), 50);
    $page     = max(absint($request->get_param('page') ?: 1), 1);

    $query = new WP_Query([
        'post_type'      => 'campus_news',
        'post_status'    => 'publish',
        'posts_per_page' => $per_page,
        'paged'          => $page,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ]);

    $actualites = [];
    foreach ($query->posts as $post) {
        $actualites[] = campus_format_actualite($post);
    }

    return new WP_REST_Response([
        'success'    => true,
        'actualites' => $actualites,
        'total'      => (int) $query->found_posts,
        'totalPages' => (int) $query->max_num_pages,
    ], 200);
}

/*
| GET /actualites/{id}
|--------------------------------------------------------------------------
*/
function campus_api_get_actualite($request) {
    $post_id = absint($request['id']);
    $post    = get_post($post_id);

    if (!$post || $post->post_type !== 'campus_news' || $post->post_status !== 'publish') {
        return new WP_REST_Response(['error' => 'Actualité introuvable.'], 404);
    }

    $actualite = campus_format_actualite($post);
    $actualite['content'] = apply_filters('the_content', $post->post_content);

    return new WP_REST_Response([
        'success'   => true,
        'actualite' => $actualite,
    ], 200);
}

/*
|--------------------------------------------------------------------------
| HANDLERS — Admin
|--------------------------------------------------------------------------
*/

/*
| POST /admin/users/{id}/status
| Body JSON : { "status": "student" | "blogger" }
| Change le statut d'un étudiant et synchronise son rôle WordPress
|--------------------------------------------------------------------------
*/
function campus_api_admin_set_status($request) {
    $target_id = absint($request['id']);
    $status    = sanitize_text_field($request->get_param('status'));

    $allowed = ['student', 'blogger'];
    if (!in_array($status, $allowed, true)) {
        return new WP_REST_Response([
            'error' => 'Statut invalide. Valeurs acceptées : student, blogger.',
        ], 400);
    }

    if (!get_user_by('id', $target_id)) {
        return new WP_REST_Response(['error' => 'Utilisateur introuvable.'], 404);
    }

    // Empêcher de modifier un autre administrateur
    if (campus_is_admin($target_id)) {
        return new WP_REST_Response([
            'error' => 'Impossible de modifier le statut d\'un administrateur.',
        ], 403);
    }

    // Empêcher de modifier un banni via cet endpoint (utiliser /unban)
    $current_status = get_user_meta($target_id, CAMPUS_META_STATUS, true);
    if ($current_status === 'banned') {
        return new WP_REST_Response([
            'error' => 'Cet utilisateur est banni. Utilisez /unban pour le réactiver d\'abord.',
        ], 409);
    }

    update_user_meta($target_id, CAMPUS_META_STATUS, $status);
    campus_sync_user_role($target_id);

    return new WP_REST_Response([
        'success' => true,
        'user_id' => $target_id,
        'status'  => $status,
    ], 200);
}

/*
| POST /admin/users/{id}/ban
| Bannit un utilisateur : statut 'banned' + retrait de tous les rôles campus
|--------------------------------------------------------------------------
*/
function campus_api_admin_ban_user($request) {
    $target_id = absint($request['id']);

    if (!get_user_by('id', $target_id)) {
        return new WP_REST_Response(['error' => 'Utilisateur introuvable.'], 404);
    }

    if (campus_is_admin($target_id)) {
        return new WP_REST_Response([
            'error' => 'Impossible de bannir un administrateur.',
        ], 403);
    }

    // Mettre à jour le statut
    update_user_meta($target_id, CAMPUS_META_STATUS, 'banned');

    // Retirer les rôles campus
    $user = new WP_User($target_id);
    $user->remove_role('campus_student');
    $user->remove_role('campus_blogger');

    return new WP_REST_Response([
        'success' => true,
        'action'  => 'banned',
        'user_id' => $target_id,
    ], 200);
}

/*
| POST /admin/users/{id}/unban
| Réactive un utilisateur banni (redevient 'student' par défaut)
|--------------------------------------------------------------------------
*/
function campus_api_admin_unban_user($request) {
    $target_id = absint($request['id']);

    if (!get_user_by('id', $target_id)) {
        return new WP_REST_Response(['error' => 'Utilisateur introuvable.'], 404);
    }

    $current_status = get_user_meta($target_id, CAMPUS_META_STATUS, true);
    if ($current_status !== 'banned') {
        return new WP_REST_Response([
            'error' => 'Cet utilisateur n\'est pas banni.',
        ], 409);
    }

    update_user_meta($target_id, CAMPUS_META_STATUS, 'student');
    campus_sync_user_role($target_id);

    return new WP_REST_Response([
        'success' => true,
        'action'  => 'unbanned',
        'user_id' => $target_id,
        'status'  => 'student',
    ], 200);
}

/*
|--------------------------------------------------------------------------
| Helper : formater une actualité pour l'API
|--------------------------------------------------------------------------
*/
function campus_format_actualite($post) {
    $author_id   = (int) $post->post_author;
    $author_data = get_userdata($author_id);

    return [
        'id'        => (int) $post->ID,
        'title'     => $post->post_title,
        'excerpt'   => get_the_excerpt($post),
        'thumbnail' => get_the_post_thumbnail_url($post->ID, 'medium') ?: '',
        'permalink' => get_permalink($post->ID),
        'date'      => $post->post_date,
        'author'    => [
            'id'   => $author_id,
            'name' => $author_data ? $author_data->display_name : '',
        ],
    ];
}
