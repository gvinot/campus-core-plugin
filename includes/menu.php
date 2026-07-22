<?php
defined('ABSPATH') or die('No direct access');

/*
|--------------------------------------------------------------------------
| Menu & déconnexion
|--------------------------------------------------------------------------
| - Remplace tout lien de menu "#campus-logout" par l'URL de déconnexion
|   WordPress (avec nonce) : déconnexion immédiate, sans confirmation.
| - Fournit le shortcode [campus_logout_button] pour placer un bouton de
|   déconnexion dans une page (l'URL contient un nonce dynamique, un lien
|   HTML figé ne fonctionnerait pas).
|--------------------------------------------------------------------------
*/

add_filter('wp_nav_menu_objects', 'campus_dynamic_logout_link');
function campus_dynamic_logout_link($items) {
    foreach ($items as $item) {
        if (isset($item->url) && strpos($item->url, '#campus-logout') !== false) {
            $item->url = wp_logout_url(home_url('/'));
        }
    }
    return $items;
}

/*
| Shortcode [campus_logout_button]
| Attributs optionnels :
|   text     : libellé du bouton      (défaut : "Se déconnecter")
|   redirect : URL de redirection     (défaut : accueil)
*/
add_shortcode('campus_logout_button', 'campus_logout_button_shortcode');
function campus_logout_button_shortcode($atts) {

    if (!is_user_logged_in()) {
        return '';
    }

    $atts = shortcode_atts([
        'text'     => 'Se déconnecter',
        'redirect' => home_url('/'),
    ], $atts);

    $url = wp_logout_url($atts['redirect']);

    return '<a href="' . esc_url($url) . '" class="campus-logout-btn">'
         . esc_html($atts['text'])
         . '</a>';
}
