<?php
defined('ABSPATH') or die('No direct access');

/*
|--------------------------------------------------------------------------
| Campus REST API — Destinations
|--------------------------------------------------------------------------
| Endpoints :
|   GET  /destinations           → liste toutes les destinations
|   GET  /destinations/{id}      → détail + blogueurs assignés
|   POST /destinations/{id}/assign   → blogueur s'assigne à une destination
|   POST /destinations/{id}/unassign → blogueur se désassigne
|--------------------------------------------------------------------------
*/

add_action('rest_api_init', 'campus_register_destination_routes');

function campus_register_destination_routes() {

    // GET /destinations
    register_rest_route('campus/v1', '/destinations', [
        'methods'             => 'GET',
        'callback'            => 'campus_api_get_destinations',
        'permission_callback' => '__return_true', // destinations publiques
    ]);

    // GET /destinations/{id}
    register_rest_route('campus/v1', '/destinations/(?P<id>\d+)', [
        'methods'             => 'GET',
        'callback'            => 'campus_api_get_destination',
        'permission_callback' => '__return_true',
        'args'                => [
            'id' => [
                'validate_callback' => function($v) {
                    return is_numeric($v) && $v > 0;
                },
            ],
        ],
    ]);

    // POST /destinations/{id}/assign
    register_rest_route('campus/v1', '/destinations/(?P<id>\d+)/assign', [
        'methods'             => 'POST',
        'callback'            => 'campus_api_assign_destination',
        'permission_callback' => 'is_user_logged_in',
        'args'                => [
            'id' => [
                'validate_callback' => function($v) {
                    return is_numeric($v) && $v > 0;
                },
            ],
        ],
    ]);

    // POST /destinations/{id}/unassign
    register_rest_route('campus/v1', '/destinations/(?P<id>\d+)/unassign', [
        'methods'             => 'POST',
        'callback'            => 'campus_api_unassign_destination',
        'permission_callback' => 'is_user_logged_in',
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
| HANDLERS
|--------------------------------------------------------------------------
*/

/*
| GET /destinations
| Retourne toutes les destinations publiées avec leurs coordonnées GPS
| et le nombre de blogueurs assignés — prêt pour le globe interactif
|--------------------------------------------------------------------------
*/
function campus_api_get_destinations() {

    $posts = get_posts([
        'post_type'      => 'campus_destination',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ]);

    $destinations = [];
    foreach ($posts as $post) {
        $destinations[] = campus_format_destination($post);
    }

    return new WP_REST_Response([
        'success'      => true,
        'destinations' => $destinations,
    ], 200);
}

/*
| GET /destinations/{id}
| Retourne le détail + les blogueurs assignés avec leurs blogs
|--------------------------------------------------------------------------
*/
function campus_api_get_destination($request) {
    $post_id = absint($request['id']);
    $post    = get_post($post_id);

    if (!$post || $post->post_type !== 'campus_destination' || $post->post_status !== 'publish') {
        return new WP_REST_Response(['error' => 'Destination introuvable.'], 404);
    }

    $destination = campus_format_destination($post, true); // true = inclure les blogueurs

    return new WP_REST_Response([
        'success'     => true,
        'destination' => $destination,
    ], 200);
}

/*
| POST /destinations/{id}/assign
| Un blogueur s'assigne à une destination
| Règle : un blogueur ne peut être assigné qu'à UNE seule destination à la fois
|--------------------------------------------------------------------------
*/
function campus_api_assign_destination($request) {
    $user_id = get_current_user_id();
    $post_id = absint($request['id']);

    // Vérifier que l'utilisateur est bien blogueur
    if (!campus_is_blogger($user_id)) {
        return new WP_REST_Response([
            'error' => 'Seuls les blogueurs peuvent s\'assigner à une destination.',
        ], 403);
    }

    // Vérifier que la destination existe
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'campus_destination' || $post->post_status !== 'publish') {
        return new WP_REST_Response(['error' => 'Destination introuvable.'], 404);
    }

    // Assigner (écrase l'ancienne destination si elle existait)
    update_user_meta($user_id, 'campus_destination_id', $post_id);

    return new WP_REST_Response([
        'success'     => true,
        'action'      => 'assigned',
        'destination' => [
            'id'   => $post_id,
            'name' => $post->post_title,
        ],
    ], 200);
}

/*
| POST /destinations/{id}/unassign
| Un blogueur se retire d'une destination
|--------------------------------------------------------------------------
*/
function campus_api_unassign_destination($request) {
    $user_id = get_current_user_id();
    $post_id = absint($request['id']);

    $current = get_user_meta($user_id, 'campus_destination_id', true);

    if ((int) $current !== $post_id) {
        return new WP_REST_Response([
            'error' => 'Vous n\'êtes pas assigné à cette destination.',
        ], 409);
    }

    delete_user_meta($user_id, 'campus_destination_id');

    return new WP_REST_Response([
        'success' => true,
        'action'  => 'unassigned',
    ], 200);
}

/*
|--------------------------------------------------------------------------
| Helper : formater une destination pour l'API
|
| Coordonnées GPS stockées comme post meta :
|   _campus_lat  → latitude  (ex: 48.8566)
|   _campus_lng  → longitude (ex: 2.3522)
|
| Ces metas sont à renseigner depuis l'admin WordPress sur chaque destination.
| Le globe interactif consomme ces données directement.
|--------------------------------------------------------------------------
*/
function campus_format_destination($post, $with_bloggers = false) {

    $lat = (float) get_post_meta($post->ID, '_campus_lat', true);
    $lng = (float) get_post_meta($post->ID, '_campus_lng', true);

    // Blogueurs assignés à cette destination
    $assigned_users = get_users([
        'meta_key'   => 'campus_destination_id',
        'meta_value' => $post->ID,
        'fields'     => 'all',
    ]);

    $bloggers = [];
    foreach ($assigned_users as $user) {
        $blogger = [
            'id'         => (int) $user->ID,
            'name'       => $user->display_name,
            'avatar_url' => campus_get_avatar_url($user->ID, 40),
        ];

        // Si détail demandé, inclure les blogs de chaque blogueur
        if ($with_bloggers) {
            $blogs = get_posts([
                'post_type'      => 'campus_blog',
                'post_status'    => 'publish',
                'author'         => $user->ID,
                'posts_per_page' => 5,
            ]);

            $blogger['blogs'] = array_map(function($blog) {
                return [
                    'id'        => (int) $blog->ID,
                    'title'     => $blog->post_title,
                    'permalink' => get_permalink($blog->ID),
                    'thumbnail' => get_the_post_thumbnail_url($blog->ID, 'thumbnail') ?: '',
                ];
            }, $blogs);
        }

        $bloggers[] = $blogger;
    }

    $data = [
        'id'              => (int) $post->ID,
        'name'            => $post->post_title,
        'description'     => get_the_excerpt($post),
        'thumbnail'       => get_the_post_thumbnail_url($post->ID, 'medium') ?: '',
        'permalink'       => get_permalink($post->ID),
        'coordinates'     => [
            'lat' => $lat,
            'lng' => $lng,
        ],
        'bloggers_count'  => count($bloggers),
        'bloggers'        => $bloggers,
        'website'         => get_post_meta($post->ID, '_campus_website', true),
    ];

    return $data;
}
