<?php
defined('ABSPATH') or die('No direct access');

/*
|--------------------------------------------------------------------------
| Module Likes — Campus
|--------------------------------------------------------------------------
| Table : wp_campus_likes
| Logique : like / unlike / comptage / top 10
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Création de la table likes
| Appelée depuis campus_activate() dans campus-core.php
|--------------------------------------------------------------------------
*/
function campus_create_likes_table() {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();
    $table           = $wpdb->prefix . 'campus_likes';

    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id    BIGINT UNSIGNED NOT NULL,
        post_id    BIGINT UNSIGNED NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_like (user_id, post_id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

/*
|--------------------------------------------------------------------------
| Définition de la constante (même pattern que CAMPUS_FRIENDS_TABLE)
|--------------------------------------------------------------------------
*/
add_action('init', 'campus_define_likes_table_constant', 1);
function campus_define_likes_table_constant() {
    global $wpdb;
    if (!defined('CAMPUS_LIKES_TABLE')) {
        define('CAMPUS_LIKES_TABLE', $wpdb->prefix . 'campus_likes');
    }
}

/*
|--------------------------------------------------------------------------
| Like un post
| Retourne : 'liked' | 'already_liked' | false
|--------------------------------------------------------------------------
*/
function campus_like_post($user_id, $post_id) {
    global $wpdb;

    $table = CAMPUS_LIKES_TABLE;

    // Vérifie si le like existe déjà
    if (campus_has_liked($user_id, $post_id)) {
        return 'already_liked';
    }

    $result = $wpdb->insert($table, [
        'user_id' => $user_id,
        'post_id' => $post_id,
    ]);

    return $result ? 'liked' : false;
}

/*
|--------------------------------------------------------------------------
| Unlike un post
| Retourne : 'unliked' | 'not_liked' | false
|--------------------------------------------------------------------------
*/
function campus_unlike_post($user_id, $post_id) {
    global $wpdb;

    $table = CAMPUS_LIKES_TABLE;

    if (!campus_has_liked($user_id, $post_id)) {
        return 'not_liked';
    }

    $result = $wpdb->delete($table, [
        'user_id' => $user_id,
        'post_id' => $post_id,
    ]);

    return $result ? 'unliked' : false;
}

/*
|--------------------------------------------------------------------------
| Toggle like (like si pas liké, unlike sinon)
| Retourne : 'liked' | 'unliked' | false
|--------------------------------------------------------------------------
*/
function campus_toggle_like($user_id, $post_id) {
    if (campus_has_liked($user_id, $post_id)) {
        return campus_unlike_post($user_id, $post_id);
    }
    return campus_like_post($user_id, $post_id);
}

/*
|--------------------------------------------------------------------------
| Vérifie si un utilisateur a déjà liké un post
|--------------------------------------------------------------------------
*/
function campus_has_liked($user_id, $post_id) {
    global $wpdb;

    $table = CAMPUS_LIKES_TABLE;

    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table WHERE user_id = %d AND post_id = %d",
        $user_id, $post_id
    ));

    return (bool) $exists;
}

/*
|--------------------------------------------------------------------------
| Nombre de likes d'un post
|--------------------------------------------------------------------------
*/
function campus_get_like_count($post_id) {
    global $wpdb;

    $table = CAMPUS_LIKES_TABLE;

    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE post_id = %d",
        $post_id
    ));
}

/*
|--------------------------------------------------------------------------
| Top N blogs les plus likés
| Retourne un tableau d'objets enrichis prêts pour l'API
|--------------------------------------------------------------------------
*/
function campus_get_top_blogs($limit = 10) {
    global $wpdb;

    $likes_table = CAMPUS_LIKES_TABLE;
    $limit       = absint($limit);

    // Récupère les post_id triés par nb de likes décroissant
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT post_id, COUNT(*) as like_count
         FROM $likes_table
         GROUP BY post_id
         ORDER BY like_count DESC
         LIMIT %d",
        $limit
    ));

    $blogs = [];
    foreach ($results as $row) {
        $post = get_post(intval($row->post_id));
        if (!$post || $post->post_status !== 'publish') continue;

        $blogs[] = campus_format_blog($post, (int) $row->like_count);
    }

    return $blogs;
}

/*
|--------------------------------------------------------------------------
| Helper : formater un post blog pour l'API
| Centralise la structure de réponse (utilisé par plusieurs endpoints)
|--------------------------------------------------------------------------
*/
function campus_format_blog($post, $like_count = null) {
    $author_id   = (int) $post->post_author;
    $author_data = get_userdata($author_id);
    $like_count  = $like_count ?? campus_get_like_count($post->ID);

    // Destination assignée au blogueur (user meta)
    $destination_id   = get_user_meta($author_id, 'campus_destination_id', true);
    $destination_name = '';
    if ($destination_id) {
        $destination = get_post(intval($destination_id));
        $destination_name = $destination ? $destination->post_title : '';
    }

    return [
        'id'               => (int) $post->ID,
        'title' => html_entity_decode($post->post_title, ENT_QUOTES, 'UTF-8'),
        'excerpt' => html_entity_decode(get_the_excerpt($post), ENT_QUOTES, 'UTF-8'),
        'permalink'        => get_permalink($post->ID),
        'thumbnail'        => get_the_post_thumbnail_url($post->ID, 'medium') ?: '',
        'date'             => $post->post_date,
        'like_count'       => $like_count,
        'author'           => [
            'id'           => $author_id,
            'name'         => $author_data ? $author_data->display_name : '',
            'avatar_url'   => campus_get_avatar_url($author_id, 40),
        ],
        'coauthors'        => campus_get_coauthors_data($post->ID),
        'destination'      => [
            'id'           => (int) $destination_id,
            'name'         => $destination_name,
        ],
    ];
}
