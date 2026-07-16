<?php
defined('ABSPATH') or die('No direct access');

/*
|--------------------------------------------------------------------------
| Campus REST API — Bons plans (LOT 14)
|--------------------------------------------------------------------------
| GET    /bonplans                filtres: destination, categorie, sort
| POST   /bonplans/create         partager (membre connecté) → en attente
| POST   /bonplans/{id}/vote      voter "utile" / retirer
| DELETE /bonplans/{id}           supprimer le sien
|--------------------------------------------------------------------------
*/

add_action('rest_api_init', 'campus_register_bonplan_routes');
function campus_register_bonplan_routes() {

    register_rest_route('campus/v1', '/bonplans', [
        'methods'             => 'GET',
        'callback'            => 'campus_api_get_bonplans',
        'permission_callback' => 'is_user_logged_in',
    ]);

    register_rest_route('campus/v1', '/bonplans/create', [
        'methods'             => 'POST',
        'callback'            => 'campus_api_create_bonplan',
        'permission_callback' => 'is_user_logged_in',
    ]);

    register_rest_route('campus/v1', '/bonplans/(?P<id>\d+)/vote', [
        'methods'             => 'POST',
        'callback'            => 'campus_api_vote_bonplan',
        'permission_callback' => 'is_user_logged_in',
        'args'                => ['id' => ['validate_callback' => function($v){ return is_numeric($v) && $v > 0; }]],
    ]);

    register_rest_route('campus/v1', '/bonplans/(?P<id>\d+)', [
        'methods'             => 'DELETE',
        'callback'            => 'campus_api_delete_bonplan',
        'permission_callback' => 'is_user_logged_in',
        'args'                => ['id' => ['validate_callback' => function($v){ return is_numeric($v) && $v > 0; }]],
    ]);
}

/*
| GET /bonplans
| Filtres : ?destination=ID &categorie=xxx &sort=votes|recent
|--------------------------------------------------------------------------
*/
function campus_api_get_bonplans($request) {
    $destination = absint($request->get_param('destination'));
    $categorie   = sanitize_text_field($request->get_param('categorie'));
    $sort        = sanitize_text_field($request->get_param('sort')) ?: 'votes';

    $args = [
        'post_type'      => 'campus_bonplan',
        'post_status'    => 'publish', // seuls les bons plans validés
        'posts_per_page' => 100,
    ];

    $meta_query = [];
    if ($destination) {
        $meta_query[] = ['key' => '_campus_bp_destination', 'value' => $destination];
    }
    if ($categorie && array_key_exists($categorie, campus_bonplan_categories())) {
        $meta_query[] = ['key' => '_campus_bp_category', 'value' => $categorie];
    }
    if ($meta_query) {
        $meta_query['relation'] = 'AND';
        $args['meta_query'] = $meta_query;
    }

    $posts    = get_posts($args);
    $bonplans = array_map('campus_format_bonplan', $posts);

    // Tri
    if ($sort === 'votes') {
        usort($bonplans, function($a, $b){ return $b['votes'] - $a['votes']; });
    } else { // recent : déjà par date desc via get_posts par défaut
        // get_posts retourne par date desc, on garde l'ordre
    }

    return new WP_REST_Response(['success' => true, 'bonplans' => $bonplans], 200);
}

/*
| POST /bonplans/create
| Body : { title, description, category, destination_id, price, link }
| → publié en statut "pending" (modération admin)
|--------------------------------------------------------------------------
*/
function campus_api_create_bonplan($request) {
    $user_id = get_current_user_id();

    if (campus_is_banned($user_id)) {
        return new WP_REST_Response(['error' => 'Votre compte est suspendu.'], 403);
    }

    $title       = sanitize_text_field($request->get_param('title'));
    $description = sanitize_textarea_field($request->get_param('description'));
    $category    = sanitize_text_field($request->get_param('category'));
    $dest_id     = absint($request->get_param('destination_id'));
    $price       = sanitize_text_field($request->get_param('price'));
    $link        = esc_url_raw($request->get_param('link'));

    if (empty($title) || empty($description)) {
        return new WP_REST_Response(['error' => 'Le titre et la description sont obligatoires.'], 400);
    }
    if (!array_key_exists($category, campus_bonplan_categories())) {
        $category = 'autre';
    }

    // Anti-spam
    $rate_key = 'campus_bp_create_' . $user_id;
    if (get_transient($rate_key)) {
        return new WP_REST_Response(['error' => 'Veuillez patienter avant de proposer un nouveau bon plan.'], 429);
    }
    set_transient($rate_key, 1, 30);

    // Statut "pending" → modération admin avant publication
    $post_id = wp_insert_post([
        'post_type'    => 'campus_bonplan',
        'post_title'   => $title,
        'post_content' => $description,
        'post_status'  => 'pending',
        'post_author'  => $user_id,
    ], true);

    if (is_wp_error($post_id)) {
        return new WP_REST_Response(['error' => 'Erreur lors de la création.'], 500);
    }

    if ($category)  update_post_meta($post_id, '_campus_bp_category', $category);
    if ($dest_id)   update_post_meta($post_id, '_campus_bp_destination', $dest_id);
    if ($price)     update_post_meta($post_id, '_campus_bp_price', $price);
    if ($link)      update_post_meta($post_id, '_campus_bp_link', $link);

    return new WP_REST_Response([
        'success' => true,
        'pending' => true, // signale au front que c'est en attente de validation
    ], 201);
}

/*
| POST /bonplans/{id}/vote  — toggle "utile"
|--------------------------------------------------------------------------
*/
function campus_api_vote_bonplan($request) {
    $user_id    = get_current_user_id();
    $bonplan_id = absint($request['id']);

    $post = get_post($bonplan_id);
    if (!$post || $post->post_type !== 'campus_bonplan' || $post->post_status !== 'publish') {
        return new WP_REST_Response(['error' => 'Bon plan introuvable.'], 404);
    }

    $rate_key = 'campus_bp_vote_' . $user_id;
    if (get_transient($rate_key)) {
        return new WP_REST_Response(['error' => 'Trop de requêtes.'], 429);
    }
    set_transient($rate_key, 1, 2);

    $action = campus_bonplan_toggle_vote($user_id, $bonplan_id);
    $votes  = campus_bonplan_vote_count($bonplan_id);

    return new WP_REST_Response([
        'success' => true,
        'action'  => $action,        // voted | unvoted
        'votes'   => $votes,
        'voted'   => $action === 'voted',
        'is_safe' => $votes >= CAMPUS_BONPLAN_SEUIL,
    ], 200);
}

/*
| DELETE /bonplans/{id}  — l'auteur ou un admin
|--------------------------------------------------------------------------
*/
function campus_api_delete_bonplan($request) {
    $user_id    = get_current_user_id();
    $bonplan_id = absint($request['id']);

    $post = get_post($bonplan_id);
    if (!$post || $post->post_type !== 'campus_bonplan') {
        return new WP_REST_Response(['error' => 'Bon plan introuvable.'], 404);
    }

    if ((int) $post->post_author !== $user_id && !campus_is_admin($user_id)) {
        return new WP_REST_Response(['error' => 'Vous ne pouvez supprimer que vos propres bons plans.'], 403);
    }

    wp_delete_post($bonplan_id, true);

    return new WP_REST_Response(['success' => true, 'action' => 'deleted'], 200);
}
