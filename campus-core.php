<?php
/*
Plugin Name: Campus Core
Description: Core métier de la plateforme étudiante
Version: 1.0.0
Author: Guillaume Vinot
*/

defined('ABSPATH') or die('No script kiddies please');

define('CAMPUS_CORE_PATH', plugin_dir_path(__FILE__));
define('CAMPUS_CORE_URL', plugin_dir_url(__FILE__));

// CORRECTION 1 : définir la constante AVANT le require_once
// (si loader.php ou un fichier inclus l'utilise, elle doit exister)
define('CAMPUS_DEV_MODE', false);

require_once CAMPUS_CORE_PATH . 'loader.php';

// CORRECTION 2 : hooks d'activation / désactivation
register_activation_hook(__FILE__, 'campus_activate');
register_deactivation_hook(__FILE__, 'campus_deactivate');

function campus_activate() {
    campus_register_roles();
    campus_create_social_table();   // table wp_campus_friends
    campus_create_likes_table();    // table wp_campus_likes (Sprint 1)
    flush_rewrite_rules();
}

function campus_deactivate() {
    flush_rewrite_rules();
}