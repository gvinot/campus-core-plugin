<?php
defined('ABSPATH') or die('No direct access');

/*
|--------------------------------------------------------------------------
| Campus REST API
|--------------------------------------------------------------------------
*/

add_action('rest_api_init', function () {

    /*
    |--------------------------------------------------------------------------
    | FRIENDS SYSTEM
    |--------------------------------------------------------------------------
    */

    register_rest_route('campus/v1', '/friends/request', [
        'methods'             => 'POST',
        'callback'            => 'campus_api_friend_request',
        'permission_callback' => 'is_user_logged_in',
    ]);

    register_rest_route('campus/v1', '/friends/accept', [
        'methods'             => 'POST',
        'callback'            => 'campus_api_friend_accept',
        'permission_callback' => 'is_user_logged_in',
    ]);

    register_rest_route('campus/v1', '/friends/remove', [
        'methods'             => 'POST',
        'callback'            => 'campus_api_friend_remove',
        'permission_callback' => 'is_user_logged_in',
    ]);

    register_rest_route('campus/v1', '/friends/list', [
        'methods'             => 'GET',
        'callback'            => 'campus_api_friend_list',
        'permission_callback' => 'is_user_logged_in',
    ]);

    /*
    | FIX SEC1 : endpoint /users désormais réservé aux connectés
    | (ne pas exposer la liste des membres à des visiteurs anonymes)
    */
    register_rest_route('campus/v1', '/users', [
        'methods'             => 'GET',
        'callback'            => 'campus_api_users',
        'permission_callback' => 'is_user_logged_in',
    ]);

    register_rest_route('campus/v1', '/friends/requests', [
        'methods'             => 'GET',
        'callback'            => 'campus_api_friend_requests',
        'permission_callback' => 'is_user_logged_in',
    ]);
});

/*
|--------------------------------------------------------------------------
| API HANDLERS
|--------------------------------------------------------------------------
*/

/*
| FIX SEC2 : validation existence utilisateur cible
| FIX SEC3 : rate limiting par transient (1 demande / 10 s par utilisateur)
*/
function campus_api_friend_request($request) {
    $from = get_current_user_id();
    $to   = absint($request['user_id']);

    // Validation basique
    if (!$to || $from === $to) {
        return new WP_REST_Response(['error' => 'Utilisateur invalide.'], 400);
    }

    // FIX SEC2 : vérifier que l'utilisateur cible existe réellement
    if (!get_user_by('id', $to)) {
        return new WP_REST_Response(['error' => 'Utilisateur introuvable.'], 404);
    }

    // FIX SEC3 : anti-spam — 1 demande max toutes les 10 secondes par user
    $rate_key = 'campus_req_' . $from;
    if (get_transient($rate_key)) {
        return new WP_REST_Response(['error' => 'Trop de demandes. Attendez quelques secondes.'], 429);
    }
    set_transient($rate_key, 1, 10);

    $result = campus_send_friend_request($from, $to);

    if ($result === false) {
        return new WP_REST_Response(['error' => 'Demande déjà existante ou relation déjà établie.'], 409);
    }

    return new WP_REST_Response([
        'success' => true,
        'action'  => 'request_sent',
    ], 200);
}

function campus_api_friend_accept($request) {
    $acceptor  = get_current_user_id();
    $requester = absint($request['user_id']);

    if (!$requester || $acceptor === $requester) {
        return new WP_REST_Response(['error' => 'Utilisateur invalide.'], 400);
    }

    if (!get_user_by('id', $requester)) {
        return new WP_REST_Response(['error' => 'Utilisateur introuvable.'], 404);
    }

    campus_accept_friend_request($acceptor, $requester);

    return new WP_REST_Response([
        'success' => true,
        'action'  => 'friend_accepted',
    ], 200);
}

function campus_api_friend_remove($request) {
    $from = get_current_user_id();
    $to   = absint($request['user_id']);

    if (!$to) {
        return new WP_REST_Response(['error' => 'Utilisateur invalide.'], 400);
    }

    campus_remove_friend($from, $to);

    return new WP_REST_Response([
        'success' => true,
        'action'  => 'friend_removed',
    ], 200);
}

/*
| FIX BUG4 : les données enrichies (display_name, avatar_url) sont
| maintenant renvoyées directement depuis campus_get_friends()
*/
function campus_api_friend_list() {
    $user_id = get_current_user_id();
    $friends = campus_get_friends($user_id);

    return new WP_REST_Response([
        'success' => true,
        'friends' => $friends,
    ], 200);
}

function campus_api_users() {
    $current_id = get_current_user_id();

    $users = get_users([
        'fields'  => ['ID', 'display_name'],
        'exclude' => [$current_id], // ne pas retourner l'utilisateur connecté
    ]);

    $data = [];
    foreach ($users as $user) {
        $data[] = [
            'id'         => (int) $user->ID,
            'name'       => $user->display_name,
            'avatar_url' => get_avatar_url($user->ID, ['size' => 40]),
        ];
    }

    return new WP_REST_Response($data, 200);
}

/*
| FIX BUG4 : idem, enrichissement géré dans campus_get_pending_requests()
*/
function campus_api_friend_requests() {
    $user_id  = get_current_user_id();
    $requests = campus_get_pending_requests($user_id);

    return new WP_REST_Response([
        'success'  => true,
        'requests' => $requests,
    ], 200);
}
