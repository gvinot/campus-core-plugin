<?php
defined('ABSPATH') or die('No direct access');

/*
|--------------------------------------------------------------------------
| Bons plans — LOT 14
|--------------------------------------------------------------------------
| CPT campus_bonplan : bons plans étudiants (logement, resto, transport…)
| - Partagé par tout membre connecté
| - Modéré par l'admin avant publication (statut "pending")
| - Votes "utile" stockés dans wp_campus_bonplan_votes
| - Badge "valeur sûre" au-delà d'un seuil de votes
|--------------------------------------------------------------------------
*/

define('CAMPUS_BONPLAN_SEUIL', 5); // seuil de votes pour le badge "valeur sûre"

/*
| Table des votes (créée à l'activation via campus_activate)
*/
function campus_create_bonplan_votes_table() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table = $wpdb->prefix . 'campus_bonplan_votes';

    $sql = "CREATE TABLE IF NOT EXISTS $table (
        id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id    BIGINT UNSIGNED NOT NULL,
        bonplan_id BIGINT UNSIGNED NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_vote (user_id, bonplan_id)
    ) $charset_collate;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

add_action('init', 'campus_define_bonplan_table_constant', 1);
function campus_define_bonplan_table_constant() {
    global $wpdb;
    if (!defined('CAMPUS_BONPLAN_VOTES_TABLE')) {
        define('CAMPUS_BONPLAN_VOTES_TABLE', $wpdb->prefix . 'campus_bonplan_votes');
    }
}

/*
| CPT campus_bonplan
*/
add_action('init', 'campus_register_bonplan_cpt');
function campus_register_bonplan_cpt() {
    register_post_type('campus_bonplan', [
        'labels' => [
            'name'          => 'Bons plans',
            'singular_name' => 'Bon plan',
            'add_new'       => 'Ajouter un bon plan',
            'add_new_item'  => 'Ajouter un bon plan',
            'edit_item'     => 'Modifier le bon plan',
            'menu_name'     => 'Bons plans',
            'not_found'     => 'Aucun bon plan',
        ],
        'public'    => false,
        'show_ui'   => true,
        'menu_icon' => 'dashicons-lightbulb',
        'supports'  => ['title', 'editor', 'author'],
    ]);
}

/*
| Metabox admin : catégorie, destination, prix, lien
*/
add_action('add_meta_boxes', 'campus_bonplan_metabox');
function campus_bonplan_metabox() {
    add_meta_box('campus_bonplan_fields', 'Détails du bon plan', 'campus_bonplan_render', 'campus_bonplan', 'side', 'default');
}

function campus_bonplan_render($post) {
    wp_nonce_field('campus_bonplan_save', 'campus_bonplan_nonce');

    $cat   = get_post_meta($post->ID, '_campus_bp_category', true);
    $dest  = get_post_meta($post->ID, '_campus_bp_destination', true);
    $price = get_post_meta($post->ID, '_campus_bp_price', true);
    $link  = get_post_meta($post->ID, '_campus_bp_link', true);

    $categories = campus_bonplan_categories();
    $destinations = get_posts(['post_type' => 'campus_destination', 'post_status' => 'publish', 'posts_per_page' => -1]);
    ?>
    <p><label><strong>Catégorie</strong></label><br>
        <select name="campus_bp_category" style="width:100%">
            <?php foreach ($categories as $k => $v) : ?>
                <option value="<?php echo esc_attr($k); ?>" <?php selected($cat, $k); ?>><?php echo esc_html($v); ?></option>
            <?php endforeach; ?>
        </select>
    </p>
    <p><label><strong>Destination</strong></label><br>
        <select name="campus_bp_destination" style="width:100%">
            <option value="">— Aucune —</option>
            <?php foreach ($destinations as $d) : ?>
                <option value="<?php echo esc_attr($d->ID); ?>" <?php selected($dest, $d->ID); ?>><?php echo esc_html($d->post_title); ?></option>
            <?php endforeach; ?>
        </select>
    </p>
    <p><label><strong>Prix indicatif</strong></label><br>
        <input type="text" name="campus_bp_price" value="<?php echo esc_attr($price); ?>" style="width:100%" placeholder="ex: 15€/repas">
    </p>
    <p><label><strong>Lien externe</strong></label><br>
        <input type="url" name="campus_bp_link" value="<?php echo esc_attr($link); ?>" style="width:100%" placeholder="https://...">
    </p>
    <?php
}

add_action('save_post_campus_bonplan', 'campus_bonplan_save');
function campus_bonplan_save($post_id) {
    if (!isset($_POST['campus_bonplan_nonce'])) return;
    if (!wp_verify_nonce($_POST['campus_bonplan_nonce'], 'campus_bonplan_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    if (isset($_POST['campus_bp_category'])) {
        $allowed = array_keys(campus_bonplan_categories());
        $cat = sanitize_text_field($_POST['campus_bp_category']);
        if (in_array($cat, $allowed, true)) update_post_meta($post_id, '_campus_bp_category', $cat);
    }
    if (isset($_POST['campus_bp_destination'])) {
        update_post_meta($post_id, '_campus_bp_destination', absint($_POST['campus_bp_destination']));
    }
    if (isset($_POST['campus_bp_price'])) {
        update_post_meta($post_id, '_campus_bp_price', sanitize_text_field($_POST['campus_bp_price']));
    }
    if (isset($_POST['campus_bp_link'])) {
        update_post_meta($post_id, '_campus_bp_link', esc_url_raw($_POST['campus_bp_link']));
    }
}

/*
|--------------------------------------------------------------------------
| Fonctions métier
|--------------------------------------------------------------------------
*/

function campus_bonplan_categories() {
    return [
        'logement'   => 'Logement',
        'nourriture' => 'Nourriture',
        'transport'  => 'Transport',
        'sortie'     => 'Sortie',
        'demarches'  => 'Démarches',
        'autre'      => 'Autre',
    ];
}

function campus_bonplan_vote_count($bonplan_id) {
    global $wpdb;
    $table = CAMPUS_BONPLAN_VOTES_TABLE;
    return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE bonplan_id = %d", $bonplan_id));
}

function campus_bonplan_has_voted($user_id, $bonplan_id) {
    global $wpdb;
    $table = CAMPUS_BONPLAN_VOTES_TABLE;
    return (bool) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table WHERE user_id = %d AND bonplan_id = %d", $user_id, $bonplan_id
    ));
}

function campus_bonplan_toggle_vote($user_id, $bonplan_id) {
    global $wpdb;
    $table = CAMPUS_BONPLAN_VOTES_TABLE;

    if (campus_bonplan_has_voted($user_id, $bonplan_id)) {
        $wpdb->delete($table, ['user_id' => $user_id, 'bonplan_id' => $bonplan_id]);
        return 'unvoted';
    }
    $wpdb->insert($table, ['user_id' => $user_id, 'bonplan_id' => $bonplan_id]);
    return 'voted';
}

/*
| Formater un bon plan pour l'API
*/
function campus_format_bonplan($post) {
    $author_id   = (int) $post->post_author;
    $author_data = get_userdata($author_id);
    $votes       = campus_bonplan_vote_count($post->ID);
    $dest_id     = (int) get_post_meta($post->ID, '_campus_bp_destination', true);
    $dest        = $dest_id ? get_post($dest_id) : null;
    $categories  = campus_bonplan_categories();
    $cat_key     = get_post_meta($post->ID, '_campus_bp_category', true) ?: 'autre';

    $liked_by_me = is_user_logged_in() ? campus_bonplan_has_voted(get_current_user_id(), $post->ID) : false;

    return [
        'id'             => (int) $post->ID,
        'title'          => html_entity_decode($post->post_title, ENT_QUOTES, 'UTF-8'),
        'description'    => html_entity_decode(wp_strip_all_tags($post->post_content), ENT_QUOTES, 'UTF-8'),
        'category'       => $cat_key,
        'category_label' => $categories[$cat_key] ?? 'Autre',
        'price'          => get_post_meta($post->ID, '_campus_bp_price', true),
        'link'           => get_post_meta($post->ID, '_campus_bp_link', true),
        'votes'          => $votes,
        'voted_by_me'    => $liked_by_me,
        'is_safe'        => $votes >= CAMPUS_BONPLAN_SEUIL,
        'destination'    => [
            'id'   => $dest_id,
            'name' => $dest ? $dest->post_title : '',
        ],
        'author' => [
            'id'         => $author_id,
            'name'       => $author_data ? $author_data->display_name : '',
            'avatar_url' => campus_get_avatar_url($author_id, 40),
        ],
    ];
}
