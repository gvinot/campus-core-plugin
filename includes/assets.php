<?php
defined('ABSPATH') or die('No direct access');

/*
|--------------------------------------------------------------------------
| Gestion des assets (scripts / styles)
| LOT 3 : CampusData est désormais injecté sur TOUTES les pages pour les
| utilisateurs connectés, afin que les widgets (profil, blogs des amis…)
| disposent du nonce API où qu'ils soient.
|--------------------------------------------------------------------------
*/

add_action('wp_enqueue_scripts', 'campus_enqueue_assets');

function campus_enqueue_assets() {

    /*
    | 1. CampusData global — disponible partout pour les connectés.
    |    Un handle "vide" sert uniquement de support à wp_localize_script.
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
    | 2. social.js — uniquement sur les pages contenant le shortcode [campus_social]
    |    Dépend de campus-global pour avoir CampusData prêt avant exécution.
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
