<?php
defined('ABSPATH') or die('No direct access');

/*
|--------------------------------------------------------------------------
| Gestion des assets (scripts / styles)
|--------------------------------------------------------------------------
| Tous les scripts front s'auto-désactivent si leur conteneur HTML est
| absent de la page : on peut donc les charger sur l'ensemble des pages
| sans effet de bord.
|--------------------------------------------------------------------------
*/

add_action('wp_enqueue_scripts', 'campus_enqueue_assets');

function campus_enqueue_assets() {

    // 1. CampusData global (API + nonce) — pour les utilisateurs connectés
    if (is_user_logged_in()) {
        wp_register_script('campus-global', false, [], '1.0', true);
        wp_enqueue_script('campus-global');
        wp_localize_script('campus-global', 'CampusData', [
            'apiUrl' => rest_url('campus/v1'),
            'nonce'  => wp_create_nonce('wp_rest'),
            'userId' => get_current_user_id(),
        ]);
    }

    // 2. Bouton "J'aime" — pages d'un blog
    if (is_singular('campus_blog')) {
        wp_enqueue_script('campus-likes', CAMPUS_CORE_URL . 'assets/js/likes.js', [], '1.0.0', true);
    }

    // 3. Scripts des pages membres (connectés uniquement)
    if (is_user_logged_in() && is_page()) {
        $deps = ['campus-global'];
        wp_enqueue_script('campus-bonplans',     CAMPUS_CORE_URL . 'assets/js/bonplans.js',     $deps, '1.0.0', true);
        wp_enqueue_script('campus-badges',       CAMPUS_CORE_URL . 'assets/js/badges.js',       $deps, '1.0.0', true);
        wp_enqueue_script('campus-account',      CAMPUS_CORE_URL . 'assets/js/account.js',      $deps, '1.0.0', true); // Mon compte
        wp_enqueue_script('campus-my-blogs',     CAMPUS_CORE_URL . 'assets/js/my-blogs.js',     $deps, '1.0.0', true); // Mes blogs
        wp_enqueue_script('campus-blog-publish', CAMPUS_CORE_URL . 'assets/js/blog-publish.js', $deps, '1.0.0', true); // Publication
    }

    // 4. social.js — pages contenant le shortcode [campus_social]
    if (!is_singular()) return;

    global $post;
    if (!isset($post->post_content) || !has_shortcode($post->post_content, 'campus_social')) {
        return;
    }

    wp_enqueue_script('campus-social', CAMPUS_CORE_URL . 'assets/js/social.js', ['campus-global'], '2.0.0', true);
}
