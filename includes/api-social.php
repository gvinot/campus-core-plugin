<?php
defined('ABSPATH') or die('No direct access');

/*
|--------------------------------------------------------------------------
| Campus REST API — Social (recherche + blogs des amis)
|--------------------------------------------------------------------------
*/

add_action('rest_api_init', function () {

    // GET /users/search?q=...  (LOT 2)
    register_rest_route('campus/v1', '/users/search', [
        'methods'             => 'GET',
        'callback'            => 'campus_api_search_users',
        'permission_callback' => 'is_user_logged_in',
    ]);

    // GET /blogs/friends  (LOT 3)
    register_rest_route('campus/v1', '/blogs/friends', [
        'methods'             => 'GET',
        'callback'            => 'campus_api_friends_blogs',
        'permission_callback' => 'is_user_logged_in',
    ]);
});

/*
|--------------------------------------------------------------------------
| Recherche d'utilisateurs
|--------------------------------------------------------------------------
*/
function campus_api_search_users($request) {
    $current_id = get_current_user_id();
    $q          = sanitize_text_field($request->get_param('q'));

    if (mb_strlen($q) < 2) {
        return new WP_REST_Response(['users' => []], 200);
    }

    $users = get_users([
        'fields'         => ['ID', 'display_name'],
        'exclude'        => [$current_id],
        'search'         => '*' . $q . '*',
        'search_columns' => ['display_name', 'user_login', 'user_nicename'],
        'number'         => 20,
    ]);

    $data = [];
    foreach ($users as $user) {
        $uid = (int) $user->ID;
        if (campus_is_banned($uid)) continue;

        $data[] = [
            'id'         => $uid,
            'name'       => $user->display_name,
            'avatar_url' => get_avatar_url($uid, ['size' => 40]),
            'friendship' => campus_get_friendship_status($current_id, $uid),
        ];
    }

    return new WP_REST_Response(['users' => $data], 200);
}

/*
|--------------------------------------------------------------------------
| Blogs des amis de l'utilisateur connecté
|--------------------------------------------------------------------------
*/
function campus_api_friends_blogs($request) {
    $user_id = get_current_user_id();
    $friends = campus_get_friends($user_id);

    // Extraire les IDs des amis (campus_get_friends renvoie des tableaux associatifs)
    $friend_ids = [];
    foreach ($friends as $f) {
        $friend_ids[] = is_array($f) ? (int) $f['friend_id'] : (int) $f->friend_id;
    }

    if (empty($friend_ids)) {
        return new WP_REST_Response(['success' => true, 'blogs' => [], 'total' => 0], 200);
    }

    $per_page = min(absint($request->get_param('per_page') ?: 50), 50);

    $query = new WP_Query([
        'post_type'      => 'campus_blog',
        'post_status'    => 'publish',
        'author__in'     => $friend_ids,
        'posts_per_page' => $per_page,
        'orderby'        => 'date',
        'order'          => 'DESC',
    ]);

    $blogs = [];
    foreach ($query->posts as $post) {
        $blogs[] = campus_format_blog($post);
    }

    return new WP_REST_Response([
        'success' => true,
        'blogs'   => $blogs,
        'total'   => (int) $query->found_posts,
    ], 200);
}
