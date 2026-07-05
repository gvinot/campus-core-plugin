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