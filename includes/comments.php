<?php
defined('ABSPATH') or die('No direct access');

/*
|--------------------------------------------------------------------------
| Commentaires — LOT 8
|--------------------------------------------------------------------------
| - Commentaires activés UNIQUEMENT sur les blogs
| - Réservés aux membres connectés
| - Modération obligatoire : tout commentaire d'un non-admin est mis en
|   attente de validation par l'administrateur
| - Un utilisateur banni ne peut pas commenter
|
| Ces règles sont appliquées par le plugin (via filtres) pour être
| indépendantes des réglages globaux de WordPress.
|--------------------------------------------------------------------------
*/

/*
| 1. Réserver les commentaires aux utilisateurs connectés.
|    Force l'option "comment_registration" sans modifier le réglage stocké.
*/
add_filter('pre_option_comment_registration', '__return_true');

/*
| 2. Commentaires ouverts uniquement sur les blogs (campus_blog).
*/
add_filter('comments_open', 'campus_filter_comments_open', 10, 2);
function campus_filter_comments_open($open, $post_id) {
    $post = get_post($post_id);
    if (!$post) return $open;

    if ($post->post_type === 'campus_blog') {
        return true;
    }

    if (in_array($post->post_type, ['campus_destination', 'campus_news', 'campus_document', 'page'], true)) {
        return false;
    }

    return $open;
}

/*
| 3. Modération : tout commentaire d'un non-administrateur passe "en attente".
|    Les administrateurs sont auto-approuvés.
*/
add_filter('pre_comment_approved', 'campus_moderate_comments', 10, 2);
function campus_moderate_comments($approved, $commentdata) {
    if (current_user_can('moderate_comments')) {
        return 1; // administrateur : approuvé directement
    }
    return 0;     // membre : en attente de validation
}

/*
| 4. Un utilisateur banni ne peut pas publier de commentaire.
*/
add_filter('preprocess_comment', 'campus_block_banned_comment');
function campus_block_banned_comment($commentdata) {
    if (is_user_logged_in() && campus_is_banned()) {
        wp_die(
            esc_html__('Votre compte est suspendu, vous ne pouvez pas commenter.', 'campus-core'),
            esc_html__('Commentaire refusé', 'campus-core'),
            ['response' => 403, 'back_link' => true]
        );
    }
    return $commentdata;
}
