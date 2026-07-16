<?php
defined('ABSPATH') or die('No direct access');

/*
|--------------------------------------------------------------------------
| Menu — Déconnexion directe (LOT 6)
|--------------------------------------------------------------------------
| Remplace dynamiquement tout lien de menu pointant vers "#campus-logout"
| par l'URL de déconnexion WordPress (avec nonce), qui déconnecte
| immédiatement puis redirige vers l'accueil — sans page de confirmation.
|
| Utilisation : dans Apparence → Menus, créer un lien personnalisé
|   URL   : #campus-logout
|   Texte : Se déconnecter
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
