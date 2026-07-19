<?php
defined('ABSPATH') or die('No direct access');

/*
|--------------------------------------------------------------------------
| Campus REST API — Profils utilisateurs
|--------------------------------------------------------------------------
| Endpoints :
|   GET  /users/{id}           → profil public d'un utilisateur
|   GET  /users/{id}/blogs     → blogs d'un utilisateur
|   GET  /users/me             → profil de l'utilisateur connecté
|   POST /users/me/avatar      → mettre à jour son avatar (URL)
|--------------------------------------------------------------------------
*/

add_action('rest_api_init', 'campus_register_user_routes');

function campus_register_user_routes() {

    // GET /users/me — doit être avant /users/{id} pour éviter que
    // WordPress interprète "me" comme un id numérique
    register_rest_route('campus/v1', '/users/me', [
        'methods'             => 'GET',
        'callback'            => 'campus_api_get_me',
        'permission_callback' => 'is_user_logged_in',
    ]);

    // POST /users/me/avatar
    register_rest_route('campus/v1', '/users/me/avatar', [
        'methods'             => 'POST',
        'callback'            => 'campus_api_update_avatar',
        'permission_callback' => 'is_user_logged_in',
    ]);

    // GET /users/{id}
    register_rest_route('campus/v1', '/users/(?P<id>\d+)', [
        'methods'             => 'GET',
        'callback'            => 'campus_api_get_user',
        'permission_callback' => 'is_user_logged_in', // profils réservés aux membres
        'args'                => [
            'id' => [
                'validate_callback' => function($v) {
                    return is_numeric($v) && $v > 0;
                },
            ],
        ],
    ]);

    // GET /users/{id}/blogs
    register_rest_route('campus/v1', '/users/(?P<id>\d+)/blogs', [
        'methods'             => 'GET',
        'callback'            => 'campus_api_get_user_blogs',
        'permission_callback' => '__return_true', // blogs publics
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
| GET /users/me
| Profil complet de l'utilisateur connecté (privé + public)
|--------------------------------------------------------------------------
*/
function campus_api_get_me() {
    $user_id = get_current_user_id();
    $profile = campus_format_user($user_id, true); // true = données privées incluses

    if (!$profile) {
        return new WP_REST_Response(['error' => 'Utilisateur introuvable.'], 404);
    }

    return new WP_REST_Response([
        'success' => true,
        'user'    => $profile,
    ], 200);
}

/*
| GET /users/{id}
| Profil public d'un membre
|--------------------------------------------------------------------------
*/
function campus_api_get_user($request) {
    $user_id    = absint($request['id']);
    $current_id = get_current_user_id();

    $profile = campus_format_user($user_id, $user_id === $current_id);

    if (!$profile) {
        return new WP_REST_Response(['error' => 'Utilisateur introuvable.'], 404);
    }

    // Enrichir avec le statut d'amitié par rapport à l'utilisateur connecté
    $profile['friendship'] = campus_get_friendship_status($current_id, $user_id);

    return new WP_REST_Response([
        'success' => true,
        'user'    => $profile,
    ], 200);
}

/*
| GET /users/{id}/blogs
| Blogs publiés par un utilisateur (public)
|--------------------------------------------------------------------------
*/
function campus_api_get_user_blogs($request) {
    $user_id  = absint($request['id']);
    $per_page = min(absint($request->get_param('per_page') ?: 10), 50);
    $page     = max(absint($request->get_param('page') ?: 1), 1);

    if (!get_user_by('id', $user_id)) {
        return new WP_REST_Response(['error' => 'Utilisateur introuvable.'], 404);
    }

        $coauthored = campus_get_coauthored_blog_ids($user_id);

    $query = new WP_Query([
        'post_type'      => 'campus_blog',
        'post_status'    => 'publish',
        'posts_per_page' => $per_page,
        'paged'          => $page,
        'orderby'        => 'date',
        'order'          => 'DESC',
        // Inclut : blogs dont il est auteur OU co-auteur
        'post__in'       => array_merge(
            get_posts([
                'post_type'   => 'campus_blog',
                'post_status' => 'publish',
                'author'      => $user_id,
                'fields'      => 'ids',
                'numberposts' => -1,
            ]),
            $coauthored
        ) ?: [0], // [0] évite de tout retourner si la liste est vide
    ]);

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
| POST /users/me/avatar
| Met à jour l'avatar de l'utilisateur connecté via une URL
| (solution simple — pour upload fichier, prévoir media endpoint)
|--------------------------------------------------------------------------
*/
function campus_api_update_avatar($request) {
    $user_id = get_current_user_id();

    // Option 1 : upload d'une image (base64) — réutilise le helper du LOT 1
    $image_data = $request->get_param('image_data');
    if (!empty($image_data)) {
        $attach_id = campus_handle_base64_image($image_data, 0);
        if (!$attach_id) {
            return new WP_REST_Response(['error' => 'Image invalide.'], 400);
        }
        $url = wp_get_attachment_url($attach_id);
        update_user_meta($user_id, 'campus_avatar_url', $url);
        return new WP_REST_Response(['success' => true, 'avatar_url' => $url], 200);
    }

    // Option 2 : URL directe (fallback)
    $avatar_url = sanitize_url($request->get_param('avatar_url'));
    if (empty($avatar_url) || !filter_var($avatar_url, FILTER_VALIDATE_URL)) {
        return new WP_REST_Response(['error' => 'Aucune image fournie.'], 400);
    }

    update_user_meta($user_id, 'campus_avatar_url', $avatar_url);
    return new WP_REST_Response(['success' => true, 'avatar_url' => $avatar_url], 200);
}

/*
|--------------------------------------------------------------------------
| Helper : formater un profil utilisateur
|
| $private = true → inclut email, statut, destination assignée
| $private = false → profil public uniquement
|--------------------------------------------------------------------------
*/
function campus_format_user($user_id, $private = false) {
    $user = get_user_by('id', $user_id);
    if (!$user) return null;

    // Avatar : priorité à la photo personnalisée, sinon avatar par défaut aux couleurs ISEL
    $custom_avatar = get_user_meta($user_id, 'campus_avatar_url', true);
    if ($custom_avatar) {
        $avatar_url = $custom_avatar;
    } else {
        // Avatar généré à partir des initiales (fond bleu marine, texte blanc)
        $initials   = urlencode($user->display_name);
        $avatar_url = campus_get_avatar_url($user_id, 80);
    }

    // Destination assignée
    $destination_id   = get_user_meta($user_id, 'campus_destination_id', true);
    $destination_name = '';
    if ($destination_id) {
        $dest = get_post(intval($destination_id));
        $destination_name = $dest ? $dest->post_title : '';
    }

    // Nombre de blogs publiés
    $blog_count = (int) count_user_posts($user_id, 'campus_blog', true);

    $profile = [
        'id'          => (int) $user_id,
        'name'        => $user->display_name,
        'avatar_url'  => $avatar_url,
        'role'        => campus_get_display_role($user_id),
        'blog_count'  => $blog_count,
        'bio'         => get_user_meta($user_id, 'campus_bio', true) ?: '',
        'destination' => [
            'id'   => (int) $destination_id,
            'name' => $destination_name,
        ],
    ];

    // Données privées (uniquement pour /users/me ou profil propre)
    if ($private) {
        $profile['email']  = $user->user_email;
        $profile['status'] = get_user_meta($user_id, CAMPUS_META_STATUS, true) ?: 'student';
    }

    return $profile;
}

/*
|--------------------------------------------------------------------------
| Helper : statut d'amitié entre deux utilisateurs
| Retourne : 'none' | 'pending_sent' | 'pending_received' | 'friends'
|--------------------------------------------------------------------------
*/
function campus_get_friendship_status($current_id, $other_id) {
    if ($current_id === $other_id) return 'self';

    global $wpdb;
    $table = CAMPUS_FRIENDS_TABLE;

    $row = $wpdb->get_row($wpdb->prepare(
        "SELECT user_id, status FROM $table
         WHERE (user_id = %d AND friend_id = %d)
            OR (user_id = %d AND friend_id = %d)
         LIMIT 1",
        $current_id, $other_id,
        $other_id, $current_id
    ));

    if (!$row) return 'none';

    if ($row->status === 'accepted') return 'friends';

    // Pending : distinguer envoyé vs reçu
    if ((int) $row->user_id === $current_id) return 'pending_sent';

    return 'pending_received';
}

/*
|--------------------------------------------------------------------------
| Helper : rôle lisible pour l'affichage
|--------------------------------------------------------------------------
*/
function campus_get_display_role($user_id) {
    $user = get_user_by('id', $user_id);

    if ($user && in_array('administrator', (array) $user->roles, true)) {
        return 'admin';
    }

    $status = get_user_meta($user_id, CAMPUS_META_STATUS, true);

    if ($status === 'banned') {
        return 'banned';
    }

    // Blogueur si le meta OU le rôle WordPress l'indique
    if ($status === 'blogger' || ($user && in_array('campus_blogger', (array) $user->roles, true))) {
        return 'blogger';
    }

    return 'student';
}

add_action('rest_api_init', function () {
    register_rest_route('campus/v1', '/stats', [
        'methods'             => 'GET',
        'callback'            => 'campus_api_stats',
        'permission_callback' => '__return_true', // public (page d'accueil)
    ]);
});

function campus_api_stats() {
    // Compte les étudiants + blogueurs (exclut les admins)
    $students = get_users(['role__in' => ['campus_student', 'campus_blogger'], 'fields' => 'ID']);

    $blogs = wp_count_posts('campus_blog');
    $destinations = wp_count_posts('campus_destination');

    return new WP_REST_Response([
        'etudiants'    => count($students),
        'blogs'        => (int) $blogs->publish,
        'destinations' => (int) $destinations->publish,
    ], 200);
}