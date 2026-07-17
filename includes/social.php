<?php
defined('ABSPATH') or die('No direct access');

/*
|------------------------------------------------------------------
| Module Réseau Social Interne - Campus
|------------------------------------------------------------------
*/

/*
| FIX LOG1 : ne plus utiliser global $wpdb à la racine du fichier.
| On définit la constante dans une fonction appelée sur 'init',
| ce qui garantit que $wpdb est initialisé.
|------------------------------------------------------------------
*/
add_action('init', 'campus_define_friends_table_constant', 1);
function campus_define_friends_table_constant() {
    global $wpdb;
    if (!defined('CAMPUS_FRIENDS_TABLE')) {
        define('CAMPUS_FRIENDS_TABLE', $wpdb->prefix . 'campus_friends');
    }
}

/*
|------------------------------------------------------------------
| Création table réseau social
| FIX LOG2 : register_activation_hook retiré d'ici, centralisé dans campus-core.php
|------------------------------------------------------------------
*/
function campus_create_social_table() {
    global $wpdb;

    $charset_collate = $wpdb->get_charset_collate();
    $table           = $wpdb->prefix . 'campus_friends'; // préfixe direct, constante pas encore dispo ici

    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id    BIGINT UNSIGNED NOT NULL,
        friend_id  BIGINT UNSIGNED NOT NULL,
        status     VARCHAR(20) NOT NULL DEFAULT 'pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_relation (user_id, friend_id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

/*
|------------------------------------------------------------------
| Envoi d'une demande d'ami
|------------------------------------------------------------------
*/
function campus_send_friend_request($from_user_id, $to_user_id) {
    global $wpdb;

    if ($from_user_id === $to_user_id) return false;

    $table = CAMPUS_FRIENDS_TABLE;

    // Vérifie relation dans les deux sens (A→B ou B→A déjà existante)
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table
         WHERE (user_id = %d AND friend_id = %d)
            OR (user_id = %d AND friend_id = %d)",
        $from_user_id, $to_user_id,
        $to_user_id, $from_user_id
    ));

    if ($exists) return false;

    return $wpdb->insert($table, [
        'user_id'   => $from_user_id,
        'friend_id' => $to_user_id,
        'status'    => 'pending',
    ]);
}

/*
|------------------------------------------------------------------
| Acceptation d'une demande d'ami
| FIX LOG5 : INSERT IGNORE pour éviter l'erreur SQL sur doublon
|------------------------------------------------------------------
*/
function campus_accept_friend_request($acceptor_id, $requester_id) {
    global $wpdb;

    $table = CAMPUS_FRIENDS_TABLE;

    // 1. Mettre la demande existante en 'accepted'
    $wpdb->update(
        $table,
        ['status' => 'accepted'],
        [
            'user_id'   => $requester_id,
            'friend_id' => $acceptor_id,
            'status'    => 'pending',   // sécurité : on ne met à jour que les pending
        ]
    );

    // 2. Créer la relation inverse uniquement si elle n'existe pas déjà
    $inverse_exists = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table WHERE user_id = %d AND friend_id = %d",
        $acceptor_id, $requester_id
    ));

    if (!$inverse_exists) {
        $wpdb->insert($table, [
            'user_id'   => $acceptor_id,
            'friend_id' => $requester_id,
            'status'    => 'accepted',
        ]);
    }

    return true;
}

/*
|------------------------------------------------------------------
| Suppression d'un ami (les deux sens)
|------------------------------------------------------------------
*/
function campus_remove_friend($user_id, $friend_id) {
    global $wpdb;

    $table = CAMPUS_FRIENDS_TABLE;

    $wpdb->delete($table, ['user_id' => $user_id,   'friend_id' => $friend_id]);
    $wpdb->delete($table, ['user_id' => $friend_id, 'friend_id' => $user_id]);

    return true;
}

/*
|------------------------------------------------------------------
| Récupération des amis avec display_name
| FIX BUG4 : enrichissement avec les données utilisateur
|------------------------------------------------------------------
*/
function campus_get_friends($user_id) {
    global $wpdb;

    $table = CAMPUS_FRIENDS_TABLE;

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT friend_id FROM $table WHERE user_id = %d AND status = 'accepted'",
        $user_id
    ));

    $friends = [];
    foreach ($rows as $row) {
        $user_data = get_userdata(intval($row->friend_id));
        if (!$user_data) continue;

        $friends[] = [
            'friend_id'    => (int) $row->friend_id,
            'display_name' => $user_data->display_name,
            'avatar_url'   => campus_get_avatar_url($row->friend_id, 40),
        ];
    }

    return $friends;
}

/*
|------------------------------------------------------------------
| Récupération des demandes en attente avec display_name
| FIX BUG4 : enrichissement avec les données utilisateur
|------------------------------------------------------------------
*/
function campus_get_pending_requests($user_id) {
    global $wpdb;

    $table = CAMPUS_FRIENDS_TABLE;

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT user_id FROM $table WHERE friend_id = %d AND status = 'pending'",
        $user_id
    ));

    $requests = [];
    foreach ($rows as $row) {
        $user_data = get_userdata(intval($row->user_id));
        if (!$user_data) continue;

        $requests[] = [
            'user_id'      => (int) $row->user_id,
            'display_name' => $user_data->display_name,
            'avatar_url'   => campus_get_avatar_url($row->user_id, 40),
        ];
    }

    return $requests;
}

/*
|------------------------------------------------------------------
| Vérification relation d'amitié
|------------------------------------------------------------------
*/
function campus_are_friends($user_id, $other_user_id) {
    global $wpdb;

    $table = CAMPUS_FRIENDS_TABLE;

    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table
         WHERE user_id = %d AND friend_id = %d AND status = 'accepted'",
        $user_id, $other_user_id
    ));

    return $count > 0;
}
