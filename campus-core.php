<?php
/*
Plugin Name: Campus Core
Description: Core métier de la plateforme étudiante
Version: 1.2.0
Author: Guillaume Vinot
*/

defined('ABSPATH') or die('No script kiddies please');

define('CAMPUS_CORE_PATH', plugin_dir_path(__FILE__));
define('CAMPUS_CORE_URL', plugin_dir_url(__FILE__));
define('CAMPUS_DEV_MODE', false);

require_once CAMPUS_CORE_PATH . 'loader.php';

/*
|--------------------------------------------------------------------------
| Point d'entrée unique activation / désactivation
|--------------------------------------------------------------------------
*/
register_activation_hook(__FILE__, 'campus_activate');
register_deactivation_hook(__FILE__, 'campus_deactivate');

function campus_activate() {
    campus_register_roles();
    campus_create_social_table();          // wp_campus_friends
    campus_create_likes_table();           // wp_campus_likes
    campus_create_bonplan_votes_table();   // wp_campus_bonplan_votes (LOT 14)
    campus_register_post_types();
    flush_rewrite_rules();
}

function campus_deactivate() {
    flush_rewrite_rules();
}
