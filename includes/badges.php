<?php
defined('ABSPATH') or die('No direct access');

/*
|--------------------------------------------------------------------------
| Badges — système de gamification
|--------------------------------------------------------------------------
| Badges calculés à la volée (aucune table dédiée, toujours à jour).
| Chaque fonction "détecteur" renvoie true/false pour un utilisateur donné.
| campus_get_user_badges() assemble la liste des badges obtenus.
|--------------------------------------------------------------------------
*/

define('CAMPUS_LICORNE_SEUIL', 100);   // total likes + votes utiles pour la Licorne
define('CAMPUS_BACKPACKER_SEUIL', 5);  // bons plans pour le badge Backpacker
define('CAMPUS_LEGENDE_SEUIL', 5);     // nombre de badges pour Légende Glob'ISEL

/*
| Helpers de comptage
|--------------------------------------------------------------------------
*/

// Nombre de blogs publiés par un utilisateur
function campus_count_user_blogs($user_id) {
    return (int) count_user_posts($user_id, 'campus_blog', true);
}

// Nombre de bons plans publiés par un utilisateur
function campus_count_user_bonplans($user_id) {
    return (int) count_user_posts($user_id, 'campus_bonplan', true);
}

// Nombre total de likes reçus sur tous les blogs d'un utilisateur
function campus_count_likes_received($user_id) {
    global $wpdb;
    $likes = CAMPUS_LIKES_TABLE;

    $blog_ids = get_posts([
        'post_type'   => 'campus_blog',
        'post_status' => 'publish',
        'author'      => $user_id,
        'fields'      => 'ids',
        'numberposts' => -1,
    ]);
    if (empty($blog_ids)) return 0;

    $in = implode(',', array_map('intval', $blog_ids));
    return (int) $wpdb->get_var("SELECT COUNT(*) FROM $likes WHERE post_id IN ($in)");
}

// Nombre total de votes "utile" reçus sur tous les bons plans d'un utilisateur
function campus_count_votes_received($user_id) {
    global $wpdb;
    $votes = CAMPUS_BONPLAN_VOTES_TABLE;

    $bp_ids = get_posts([
        'post_type'   => 'campus_bonplan',
        'post_status' => 'publish',
        'author'      => $user_id,
        'fields'      => 'ids',
        'numberposts' => -1,
    ]);
    if (empty($bp_ids)) return 0;

    $in = implode(',', array_map('intval', $bp_ids));
    return (int) $wpdb->get_var("SELECT COUNT(*) FROM $votes WHERE bonplan_id IN ($in)");
}

// Nombre de commentaires approuvés d'un utilisateur
function campus_count_user_comments($user_id) {
    return (int) get_comments([
        'user_id' => $user_id,
        'status'  => 'approve',
        'count'   => true,
    ]);
}

/*
| Détecteurs "classement" — qui détient le record ?
| Renvoient l'ID de l'utilisateur en tête, ou 0.
|--------------------------------------------------------------------------
*/

// Utilisateur avec le plus de blogs
function campus_top_blogger_id() {
    global $wpdb;
    $row = $wpdb->get_row("
        SELECT post_author, COUNT(*) as c
        FROM {$wpdb->posts}
        WHERE post_type = 'campus_blog' AND post_status = 'publish'
        GROUP BY post_author ORDER BY c DESC LIMIT 1
    ");
    return $row ? (int) $row->post_author : 0;
}

// Utilisateur avec le plus de bons plans
function campus_top_bonplan_id() {
    global $wpdb;
    $row = $wpdb->get_row("
        SELECT post_author, COUNT(*) as c
        FROM {$wpdb->posts}
        WHERE post_type = 'campus_bonplan' AND post_status = 'publish'
        GROUP BY post_author ORDER BY c DESC LIMIT 1
    ");
    return $row ? (int) $row->post_author : 0;
}

// Auteur du blog le plus liké
function campus_top_liked_author_id() {
    global $wpdb;
    $likes = CAMPUS_LIKES_TABLE;
    $row = $wpdb->get_row("
        SELECT p.post_author, COUNT(l.id) as c
        FROM $likes l
        JOIN {$wpdb->posts} p ON p.ID = l.post_id
        WHERE p.post_type = 'campus_blog' AND p.post_status = 'publish'
        GROUP BY l.post_id ORDER BY c DESC LIMIT 1
    ");
    return $row ? (int) $row->post_author : 0;
}

// Auteur du bon plan le plus voté "utile"
function campus_top_voted_bonplan_author_id() {
    global $wpdb;
    $votes = CAMPUS_BONPLAN_VOTES_TABLE;
    $row = $wpdb->get_row("
        SELECT p.post_author, COUNT(v.id) as c
        FROM $votes v
        JOIN {$wpdb->posts} p ON p.ID = v.bonplan_id
        WHERE p.post_type = 'campus_bonplan' AND p.post_status = 'publish'
        GROUP BY v.bonplan_id ORDER BY c DESC LIMIT 1
    ");
    return $row ? (int) $row->post_author : 0;
}

// Utilisateur avec le plus de commentaires
function campus_top_commenter_id() {
    global $wpdb;
    $row = $wpdb->get_row("
        SELECT user_id, COUNT(*) as c
        FROM {$wpdb->comments}
        WHERE comment_approved = '1' AND user_id > 0
        GROUP BY user_id ORDER BY c DESC LIMIT 1
    ");
    return $row ? (int) $row->user_id : 0;
}

// L'Intrépide : l'user est-il assigné à une destination sans aucun bon plan ?
function campus_is_intrepid($user_id) {
    $dest_id = (int) get_user_meta($user_id, 'campus_destination_id', true);
    if (!$dest_id) return false;

    $bonplans = get_posts([
        'post_type'   => 'campus_bonplan',
        'post_status' => 'publish',
        'meta_key'    => '_campus_bp_destination',
        'meta_value'  => $dest_id,
        'fields'      => 'ids',
        'numberposts' => 1,
    ]);
    return empty($bonplans); // aucun bon plan sur cette destination
}

// Visionnaire : l'user a-t-il posté le 1er bon plan d'au moins une destination ?
function campus_is_visionary($user_id) {
    global $wpdb;
    // Premiers bons plans (le plus ancien) par destination
    $rows = $wpdb->get_results("
        SELECT pm.meta_value AS dest, p.post_author, p.post_date
        FROM {$wpdb->posts} p
        JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_campus_bp_destination'
        WHERE p.post_type = 'campus_bonplan' AND p.post_status = 'publish' AND pm.meta_value != '0'
        ORDER BY p.post_date ASC
    ");
    $seen = [];
    foreach ($rows as $r) {
        if (isset($seen[$r->dest])) continue; // déjà vu = pas le premier
        $seen[$r->dest] = true;
        if ((int) $r->post_author === $user_id) return true;
    }
    return false;
}

 // Éclaireur : a publié le 1er blog d'au moins une destination
    function campus_is_first_blogger($user_id) {
        global $wpdb;
        $blogs = $wpdb->get_results("
            SELECT ID, post_author, post_date
            FROM {$wpdb->posts}
            WHERE post_type = 'campus_blog' AND post_status = 'publish'
            ORDER BY post_date ASC
        ");
        $seen = [];
        foreach ($blogs as $b) {
            $dest = (int) get_user_meta($b->post_author, 'campus_destination_id', true);
            if (!$dest) continue;
            if (isset($seen[$dest])) continue;
            $seen[$dest] = true;
            if ((int) $b->post_author === $user_id) return true;
        }
        return false;
    }

    // Pionnier : assigné à une destination sans aucun blog publié
    function campus_is_pioneer($user_id) {
        $dest_id = (int) get_user_meta($user_id, 'campus_destination_id', true);
        if (!$dest_id) return false;

        $authors_on_dest = get_users([
            'meta_key'   => 'campus_destination_id',
            'meta_value' => $dest_id,
            'fields'     => 'ID',
        ]);
        if (empty($authors_on_dest)) return true;

        $blogs = get_posts([
            'post_type'   => 'campus_blog',
            'post_status' => 'publish',
            'author__in'  => $authors_on_dest,
            'fields'      => 'ids',
            'numberposts' => 1,
        ]);
        return empty($blogs);
    }
/*
| Assemblage : liste des badges obtenus par un utilisateur
|--------------------------------------------------------------------------
*/
function campus_get_user_badges($user_id) {
    $badges = [];

    $add = function($icon, $name, $desc) use (&$badges) {
        $badges[] = ['icon' => $icon, 'name' => $name, 'description' => $desc];
    };

    // --- Badges de contenu ---
    if (campus_count_user_blogs($user_id) >= 1) {
        $add('🚀', 'Explorateur', 'A publié son premier blog.');
    }
    if ($user_id === campus_top_blogger_id() && campus_count_user_blogs($user_id) > 0) {
        $add('🌍', 'Globe-Trotter', 'A publié le plus de blogs.');
    }
    if ($user_id === campus_top_liked_author_id()) {
        $add('💎', 'Plume d\'Or', 'Détient le blog le plus aimé.');
    }

    // --- Badges bons plans ---
    if ($user_id === campus_top_bonplan_id() && campus_count_user_bonplans($user_id) > 0) {
        $add('📍', 'Local Guide', 'A partagé le plus de bons plans.');
    }
    if ($user_id === campus_top_voted_bonplan_author_id()) {
        $add('🧭', 'Guide Ultime', 'Son bon plan est le plus jugé utile.');
    }
    if (campus_is_visionary($user_id)) {
        $add('🔮', 'Visionnaire', 'Premier à révéler une destination.');
    }
    if (campus_is_intrepid($user_id)) {
        $add('🛡️', 'L\'Intrépide', 'Explore une destination sans bon plan.');
    }

    // --- Badges communauté ---
    if ($user_id === campus_top_commenter_id() && campus_count_user_comments($user_id) > 0) {
        $add('💬', 'Ambassadeur', 'L\'étudiant qui commente le plus.');
    }
    if ((campus_count_likes_received($user_id) + campus_count_votes_received($user_id)) >= CAMPUS_LICORNE_SEUIL) {
        $add('🦄', 'Licorne', 'Plus de ' . CAMPUS_LICORNE_SEUIL . ' réactions positives.');
    }
    // Éclaireur : 1er blog d'une destination
    if (campus_is_first_blogger($user_id)) {
        $add('✈️', 'Éclaireur', 'Premier à bloger une destination.');
    }

    // Pionnier : destination sans aucun blog
    if (campus_is_pioneer($user_id)) {
        $add('🛰️', 'Pionnier', 'Explore une destination encore vierge de retours.');
    }

    // Backpacker : seuil de bons plans partagés
    if (campus_count_user_bonplans($user_id) >= CAMPUS_BACKPACKER_SEUIL) {
        $add('🎒', 'Backpacker', 'A partagé de nombreux bons plans.');
    }

    // --- Badge profil ---
    $has_bio    = !empty(get_user_meta($user_id, 'campus_bio', true));
    $has_avatar = !empty(get_user_meta($user_id, 'campus_avatar_url', true));
    $has_dest   = !empty(get_user_meta($user_id, 'campus_destination_id', true));
    if ($has_bio && $has_avatar && $has_dest) {
        $add('🎯', 'Perfectionniste', 'A complété entièrement son profil.');
    }

        // Légende Glob'ISEL — DOIT être en DERNIER (compte les badges ci-dessus)
    if (count($badges) >= CAMPUS_LEGENDE_SEUIL) {
        $add('👑', 'Légende Glob\'ISEL', 'Distinction ultime des membres les plus engagés.');
    }

    return $badges;
}
