<?php
defined('ABSPATH') or die('No direct access');

/*
|--------------------------------------------------------------------------
| Blogs groupés (co-auteurs) — feature
|--------------------------------------------------------------------------
| Un blogueur peut ajouter des CO-AUTEURS (uniquement parmi ses amis).
| - Stockés en post meta _campus_coauthors (tableau d'IDs)
| - Le blog apparaît aussi dans les blogs des co-auteurs
| - Seul l'auteur principal (post_author) peut supprimer le blog
|--------------------------------------------------------------------------
*/

/*
| Récupère les IDs des co-auteurs d'un blog
*/
function campus_get_coauthors($post_id) {
    $ids = get_post_meta($post_id, '_campus_coauthors', true);
    return is_array($ids) ? array_map('intval', $ids) : [];
}

/*
| Enregistre les co-auteurs d'un blog, en validant que ce sont bien
| des amis de l'auteur (sécurité côté serveur).
*/
function campus_set_coauthors($post_id, $author_id, $candidate_ids) {
    $valid = [];
    foreach ((array) $candidate_ids as $cid) {
        $cid = (int) $cid;
        if ($cid <= 0 || $cid === $author_id) continue;
        // Uniquement les amis de l'auteur
        if (campus_are_friends($author_id, $cid)) {
            $valid[] = $cid;
        }
    }
    $valid = array_values(array_unique($valid));

    if ($valid) {
        update_post_meta($post_id, '_campus_coauthors', $valid);
    } else {
        delete_post_meta($post_id, '_campus_coauthors');
    }
    return $valid;
}

/*
| Retourne les données d'affichage des co-auteurs (nom + avatar + id)
*/
function campus_get_coauthors_data($post_id) {
    $out = [];
    foreach (campus_get_coauthors($post_id) as $uid) {
        $u = get_userdata($uid);
        if (!$u) continue;
        $out[] = [
            'id'         => $uid,
            'name'       => $u->display_name,
            'avatar_url' => campus_get_avatar_url($uid, 40),
        ];
    }
    return $out;
}

/*
| Récupère les IDs des blogs où un utilisateur est CO-AUTEUR.
| Utilisé pour faire apparaître ces blogs dans "ses blogs".
*/
function campus_get_coauthored_blog_ids($user_id) {
    global $wpdb;
    // Recherche l'ID sérialisé dans la meta (les métas array sont sérialisées)
    $needle = '"' . (int) $user_id . '"'; // pas parfait mais on affine ci-dessous
    $rows = $wpdb->get_col($wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_campus_coauthors' AND meta_value LIKE %s",
        '%' . $wpdb->esc_like('i:') . '%'  // toutes les métas coauthors
    ));

    // Filtrage fiable côté PHP (désérialisation)
    $ids = [];
    foreach ($rows as $pid) {
        $co = campus_get_coauthors($pid);
        if (in_array((int) $user_id, $co, true)) {
            $ids[] = (int) $pid;
        }
    }
    return $ids;
}
