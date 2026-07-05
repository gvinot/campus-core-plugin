<?php
defined('ABSPATH') or die('No direct access');

/*
|--------------------------------------------------------------------------
| Campus REST API — Blogs & Likes
|--------------------------------------------------------------------------
*/

add_action('rest_api_init', 'campus_register_blog_routes');

function campus_register_blog_routes() {

    /*
    |--------------------------------------------------------------------------
    | BLOGS
    |--------------------------------------------------------------------------
    */

    // GET /blogs — liste complète des blogs publiés
    register_rest_route('campus/v1', '/blogs', [
        'methods'             => 'GET',
        'callback'            => 'campus_api_get_blogs',
        'permission_callback' => '__return_true', // blogs publics
    ]);

    // GET /blogs/top — top 10 les plus likés (page d'accueil)
    // ⚠ Doit être enregistré AVANT /blogs/(?P<id>\d+) sinon WordPress
    //   interprète "top" comme un id numérique et renvoie une 404
    register_rest_route('campus/v1', '/blogs/top', [
        'methods'             => 'GET',
        'callback'            => 'campus_api_get_top_blogs',
        'permission_callback' => '__return_true',
    ]);

    // GET /blogs/{id} — détail d'un blog
    register_rest_route('campus/v1', '/blogs/(?P<id>\d+)', [
        'methods'             => 'GET',
        'callback'            => 'campus_api_get_blog',
        'permission_callback' => '__return_true',
        'args'                => [
            'id' => [
                'validate_callback' => fn($v) => is_numeric($v) && $v > 0,
            ],
        ],
    ]);

    /*
    |--------------------------------------------------------------------------
    | LIKES
    |--------------------------------------------------------------------------
    */

    // POST /blogs/{id}/like — toggle like (connecté uniquement)
    register_rest_route('campus/v1', '/blogs/(?P<id>\d+)/like', [
        'methods'             => 'POST',
        'callback'            => 'campus_api_toggle_like',
        'permission_callback' => 'is_user_logged_in',
        'args'                => [
            'id' => [
                'validate_callback' => fn($v) => is_numeric($v) && $v > 0,
            ],
        ],
    ]);
}

/*
|--------------------------------------------------------------------------
| HANDLERS
|--------------------------------------------------------------------------
*/

/*
| GET /blogs
| Paramètres optionnels :
|   ?per_page=10   (défaut 12, max 50)
|   ?page=1
|   ?destination=ID
|   ?author=ID
|--------------------------------------------------------------------------
*/
function campus_api_get_blogs($request) {

    $per_page    = min(absint($request->get_param('per_page') ?: 12), 50);
    $page        = max(absint($request->get_param('page') ?: 1), 1);
    $destination = absint($request->get_param('destination'));
    $author      = absint($request->get_param('author'));

    $args = [
        'post_type'      => 'campus_blog',
        'post_status'    => 'publish',
        'posts_per_page' => $per_page,
        'paged'          => $page,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ];

    // Filtre par auteur (profil utilisateur)
    if ($author) {
        $args['author'] = $author;
    }

    // Filtre par destination (meta du blogueur)
    if ($destination) {
        $args['meta_query'] = [[
            'key'   => 'campus_destination_id',
            'value' => $destination,
        ]];
        // On filtre via les auteurs qui ont cette destination
        // (la meta est sur le user, pas sur le post)
        $users_with_dest = get_users([
            'meta_key'   => 'campus_destination_id',
            'meta_value' => $destination,
            'fields'     => 'ID',
        ]);
        if (empty($users_with_dest)) {
            return new WP_REST_Response(['success' => true, 'blogs' => [], 'total' => 0], 200);
        }
        $args['author__in'] = $users_with_dest;
        unset($args['meta_query']); // on filtre par auteur, pas par post meta
    }

    $query = new WP_Query($args);
    $blogs = [];

    foreach ($query->posts as $post) {
        $blogs[] = campus_format_blog($post);
    }

    return new WP_REST_Response([
        'success'    => true,
        'blogs'      => $blogs,
        'total'      => (int) $query->found_posts,
        'totalPages' => (int) $query->max_num_pages,
    ], 200);
}

/*
| GET /blogs/{id}
|--------------------------------------------------------------------------
*/
function campus_api_get_blog($request) {
    $post_id = absint($request['id']);
    $post    = get_post($post_id);

    if (!$post || $post->post_type !== 'campus_blog' || $post->post_status !== 'publish') {
        return new WP_REST_Response(['error' => 'Blog introuvable.'], 404);
    }

    $blog = campus_format_blog($post);

    // Pour le détail, on ajoute le contenu complet
    $blog['content'] = apply_filters('the_content', $post->post_content);

    // Si connecté, on indique si l'utilisateur courant a liké
    if (is_user_logged_in()) {
        $blog['liked_by_me'] = campus_has_liked(get_current_user_id(), $post_id);
    }

    return new WP_REST_Response([
        'success' => true,
        'blog'    => $blog,
    ], 200);
}

/*
| GET /blogs/top
|--------------------------------------------------------------------------
*/
function campus_api_get_top_blogs($request) {
    $limit = min(absint($request->get_param('limit') ?: 10), 20);
    $blogs = campus_get_top_blogs($limit);

    return new WP_REST_Response([
        'success' => true,
        'blogs'   => $blogs,
    ], 200);
}

/*
| POST /blogs/{id}/like
| Toggle : like si pas liké, unlike sinon
|--------------------------------------------------------------------------
*/
function campus_api_toggle_like($request) {
    $user_id = get_current_user_id();
    $post_id = absint($request['id']);

    // Vérifier que le post existe et est bien un blog publié
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'campus_blog' || $post->post_status !== 'publish') {
        return new WP_REST_Response(['error' => 'Blog introuvable.'], 404);
    }

    // Anti-spam : 1 toggle / 2 secondes par utilisateur
    $rate_key = 'campus_like_' . $user_id;
    if (get_transient($rate_key)) {
        return new WP_REST_Response(['error' => 'Trop de requêtes.'], 429);
    }
    set_transient($rate_key, 1, 2);

    $action     = campus_toggle_like($user_id, $post_id);
    $like_count = campus_get_like_count($post_id);

    return new WP_REST_Response([
        'success'    => true,
        'action'     => $action,    // 'liked' | 'unliked'
        'like_count' => $like_count,
        'liked'      => $action === 'liked',
    ], 200);
}
