<?php
defined('ABSPATH') or die('No direct access');

/*
|--------------------------------------------------------------------------
| Barre d'administration WordPress — LOT 13
|--------------------------------------------------------------------------
| Masque la barre noire WordPress (admin bar) pour tous les utilisateurs
| SAUF les administrateurs, qui la conservent pour accéder rapidement à
| l'interface d'administration.
|
| Note : la barre ne s'affiche jamais aux visiteurs déconnectés. Ce filtre
| ne concerne donc que les étudiants et blogueurs connectés.
|--------------------------------------------------------------------------
*/

add_filter('show_admin_bar', 'campus_admin_bar_visibility');
function campus_admin_bar_visibility($show) {
    if (current_user_can('manage_options')) {
        return $show; // administrateur : conserver la barre
    }
    return false;     // étudiants / blogueurs : masquer
}
