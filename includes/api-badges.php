<?php
defined('ABSPATH') or die('No direct access');

/*
|--------------------------------------------------------------------------
| Campus REST API — Badges
|--------------------------------------------------------------------------
| GET /users/{id}/badges  → badges obtenus par l'utilisateur (calculés live)
|--------------------------------------------------------------------------
*/

add_action('rest_api_init', function () {
    register_rest_route('campus/v1', '/users/(?P<id>\d+)/badges', [
        'methods'             => 'GET',
        'callback'            => 'campus_api_get_badges',
        'permission_callback' => 'is_user_logged_in',
        'args'                => ['id' => ['validate_callback' => function($v){ return is_numeric($v) && $v > 0; }]],
    ]);
});

function campus_api_get_badges($request) {
    $user_id = absint($request['id']);

    if (!get_user_by('id', $user_id)) {
        return new WP_REST_Response(['error' => 'Utilisateur introuvable.'], 404);
    }

    return new WP_REST_Response([
        'success' => true,
        'badges'  => campus_get_user_badges($user_id),
    ], 200);
}
