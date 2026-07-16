<?php
defined('ABSPATH') or die('No direct access');

/*
|--------------------------------------------------------------------------
| Gestion des assets (scripts / styles)
|--------------------------------------------------------------------------
*/

add_action('wp_enqueue_scripts', 'campus_enqueue_assets');

function campus_enqueue_assets() {

    /*
    | 1. CampusData global — disponible partout pour les connectés.
    */
    if (is_user_logged_in()) {
        wp_register_script('campus-global', false, [], '1.0', true);
        wp_enqueue_script('campus-global');
        wp_localize_script('campus-global', 'CampusData', [
            'apiUrl' => rest_url('campus/v1'),
            'nonce'  => wp_create_nonce('wp_rest'),
            'userId' => get_current_user_id(),
        ]);
    }

    /*
    | 2. Bouton like — sur les pages d'un blog (LOT 10).
    |    Sans dépendance dure : pour un invité, le script redirige vers /login.
    */
    if (is_singular('campus_blog')) {
        wp_enqueue_script(
            'campus-likes',
            CAMPUS_CORE_URL . 'assets/js/likes.js',
            [],
            '1.0.0',
            true
        );
    }

    /*
    | 3. social.js — uniquement sur les pages contenant [campus_social].
    */
    if (!is_singular()) return;

    global $post;
    if (!isset($post->post_content) || !has_shortcode($post->post_content, 'campus_social')) {
        return;
    }

    wp_enqueue_script(
        'campus-social',
        CAMPUS_CORE_URL . 'assets/js/social.js',
        ['campus-global'],
        '2.0.0',
        true
    );
}
