<?php
defined('ABSPATH') or die('No direct access');

/*
|--------------------------------------------------------------------------
| Profil public d'un membre — Feature "voir le profil"
|--------------------------------------------------------------------------
| - La page "Membre" (slug: membre) affiche le profil d'un utilisateur
|   passé en paramètre : /membre?id=123
| - Réservée aux utilisateurs connectés
| - Les archives auteur natives de WordPress sont redirigées vers cette page
|   (ainsi, cliquer sur le nom de l'auteur d'un blog mène à son profil)
|--------------------------------------------------------------------------
*/

/*
| Restreindre la page "membre" aux connectés
*/
add_action('template_redirect', 'campus_restrict_member_profile_page');
function campus_restrict_member_profile_page() {
    if (is_page('membre') && !is_user_logged_in()) {
        wp_safe_redirect(home_url('/login'));
        exit;
    }
}

/*
| Rediriger les archives auteur (/author/xxx) vers /membre?id=
| Cela fait pointer le lien "Par [Auteur]" d'un blog vers le profil du membre.
*/
add_action('template_redirect', 'campus_redirect_author_to_profile');
function campus_redirect_author_to_profile() {
    if (is_author()) {
        $author_id = get_queried_object_id();
        if ($author_id) {
            wp_safe_redirect(home_url('/membre?id=' . $author_id));
            exit;
        }
    }
}
