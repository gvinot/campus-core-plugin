<?php
defined('ABSPATH') or die('No direct access');

/*
| Utilitaires utilisateurs Campus
*/

// Redirection après connexion → accueil
add_filter('tml_action_url', function($url, $action) {
    if ($action === 'login') {
        return home_url('/');
    }
    return $url;
}, 10, 2);

add_filter('login_redirect', function($redirect_to, $request, $user) {
    if (isset($user->roles) && !empty($user->roles)) {
        return home_url('/');
    }
    return $redirect_to;
}, 10, 3);

/**
 * Retourne l'URL de l'avatar d'un utilisateur.
 * Priorité : photo personnalisée > avatar SVG à initiales généré localement.
 * Aucune dépendance externe (fonctionne hors ligne).
 */
function campus_get_avatar_url($user_id, $size = 80) {
    $custom = get_user_meta($user_id, 'campus_avatar_url', true);
    if ($custom) {
        return $custom;
    }

    $user = get_user_by('id', $user_id);
    $name = $user ? trim($user->display_name) : 'Membre';

    // Extraire les initiales (2 premières lettres des mots)
    $parts = preg_split('/\s+/', $name);
    $initials = '';
    foreach ($parts as $p) {
        if ($p !== '') $initials .= mb_strtoupper(mb_substr($p, 0, 1));
        if (mb_strlen($initials) >= 2) break;
    }
    if ($initials === '') $initials = '?';

    // Générer un SVG cercle bleu marine + initiales blanches
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 100 100">'
         . '<rect width="100" height="100" fill="#1B3A5C"/>'
         . '<text x="50" y="50" dy=".35em" fill="#ffffff" font-family="Arial, sans-serif" '
         . 'font-size="42" font-weight="bold" text-anchor="middle">' . esc_html($initials) . '</text>'
         . '</svg>';

    // Encoder en data URI (image directement intégrée, aucune requête réseau)
    return 'data:image/svg+xml;base64,' . base64_encode($svg);
}