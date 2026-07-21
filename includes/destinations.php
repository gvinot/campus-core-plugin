<?php
defined('ABSPATH') or die('No direct access');

/*
|--------------------------------------------------------------------------
| Destinations — Champs admin (coordonnées GPS)
|--------------------------------------------------------------------------
| Ajoute deux champs dans l'écran d'édition d'une destination WordPress :
|   Latitude  → post meta _campus_lat
|   Longitude → post meta _campus_lng
|
| Ces coordonnées sont consommées par l'API /destinations
| et le globe interactif côté front.
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| Ajout de la metabox "Coordonnées GPS"
|--------------------------------------------------------------------------
*/
add_action('add_meta_boxes', 'campus_destination_add_metabox');

function campus_destination_add_metabox() {
    add_meta_box(
        'campus_destination_coords',
        'Coordonnées GPS',
        'campus_destination_coords_render',
        'campus_destination',
        'side',       // colonne de droite
        'default'
    );
}

function campus_destination_coords_render($post) {
    // Nonce de sécurité
    wp_nonce_field('campus_destination_coords_save', 'campus_destination_coords_nonce');

    $lat = get_post_meta($post->ID, '_campus_lat', true);
    $lng = get_post_meta($post->ID, '_campus_lng', true);
    ?>
    <p>
        <label for="campus_lat"><strong>Latitude</strong></label><br>
        <input
            type="number"
            step="0.000001"
            id="campus_lat"
            name="campus_lat"
            value="<?php echo esc_attr($lat); ?>"
            style="width:100%"
            placeholder="ex: 48.856600"
        >
    </p>
    <p>
        <label for="campus_lng"><strong>Longitude</strong></label><br>
        <input
            type="number"
            step="0.000001"
            id="campus_lng"
            name="campus_lng"
            value="<?php echo esc_attr($lng); ?>"
            style="width:100%"
            placeholder="ex: 2.352200"
        >
    </p>
    <p style="color:#666;font-size:12px;">
        Trouve les coordonnées sur
        <a href="https://www.latlong.net" target="_blank">latlong.net</a>
    </p>
    <p>
        <label for="campus_website"><strong>Site officiel de l'université</strong></label><br>
        <input
            type="url"
            id="campus_website"
            name="campus_website"
            value="<?php echo esc_attr(get_post_meta($post->ID, '_campus_website', true)); ?>"
            style="width:100%"
            placeholder="https://universite-exemple.edu"
        >
    </p>
    <?php
}

/*
|--------------------------------------------------------------------------
| Sauvegarde des coordonnées GPS
|--------------------------------------------------------------------------
*/
add_action('save_post_campus_destination', 'campus_destination_save_coords');

function campus_destination_save_coords($post_id) {

    // Vérifications de sécurité standard
    if (!isset($_POST['campus_destination_coords_nonce'])) return;
    if (!wp_verify_nonce($_POST['campus_destination_coords_nonce'], 'campus_destination_coords_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    // Latitude
    if (isset($_POST['campus_lat'])) {
        $lat = floatval($_POST['campus_lat']);
        // Validation : latitude entre -90 et 90
        if ($lat >= -90 && $lat <= 90) {
            update_post_meta($post_id, '_campus_lat', $lat);
        }
    }

    // Longitude
    if (isset($_POST['campus_lng'])) {
        $lng = floatval($_POST['campus_lng']);
        // Validation : longitude entre -180 et 180
        if ($lng >= -180 && $lng <= 180) {
            update_post_meta($post_id, '_campus_lng', $lng);
        }
    }
    
    if (isset($_POST['campus_website'])) {
        update_post_meta($post_id, '_campus_website', esc_url_raw($_POST['campus_website']));
    }
}
