<?php
/**
 * Désinstallation du plugin Campus Core
 *
 * Ce fichier est exécuté automatiquement par WordPress lorsque
 * l'administrateur SUPPRIME le plugin (pas à la désactivation).
 * Il nettoie l'ensemble des données créées par le plugin :
 *   - tables SQL personnalisées (amis, likes)
 *   - user meta (statut, bio, destination, avatar)
 *   - rôles personnalisés
 *
 * @package Campus_Core
 */

// Sécurité : ne s'exécute que dans le contexte de désinstallation WordPress
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

global $wpdb;

/*
|--------------------------------------------------------------------------
| 1. Suppression des tables SQL personnalisées
|--------------------------------------------------------------------------
*/
$tables = [
    $wpdb->prefix . 'campus_friends',
    $wpdb->prefix . 'campus_likes',
];

foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS `$table`");
}

/*
|--------------------------------------------------------------------------
| 2. Suppression des user meta créées par le plugin
|--------------------------------------------------------------------------
*/
$meta_keys = [
    'campus_status',
    'campus_bio',
    'campus_destination_id',
    'campus_avatar_url',
];

foreach ($meta_keys as $meta_key) {
    $wpdb->delete($wpdb->usermeta, ['meta_key' => $meta_key]);
}

/*
|--------------------------------------------------------------------------
| 3. Suppression des rôles personnalisés
|--------------------------------------------------------------------------
*/
remove_role('campus_student');
remove_role('campus_blogger');

/*
|--------------------------------------------------------------------------
| 4. Nettoyage des transients (rate limiting)
|--------------------------------------------------------------------------
*/
$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE '_transient_campus_%'
        OR option_name LIKE '_transient_timeout_campus_%'"
);

/*
| Note : les Custom Post Types (campus_blog, campus_destination, campus_news)
| et leurs contenus NE sont PAS supprimés automatiquement, pour éviter une
| perte de données accidentelle. Un administrateur peut les supprimer
| manuellement depuis l'interface WordPress si nécessaire.
*/
