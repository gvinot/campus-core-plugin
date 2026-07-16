<?php
defined('ABSPATH') or die('No direct access');

/*
|--------------------------------------------------------------------------
| Documents administratifs (LOT 5)
|--------------------------------------------------------------------------
| CPT campus_document : documents à télécharger (bourse, visa, logement…)
| Chaque document a : un fichier (URL), une catégorie, une description.
|--------------------------------------------------------------------------
*/

add_action('init', 'campus_register_document_cpt');
function campus_register_document_cpt() {
    register_post_type('campus_document', [
        'labels' => [
            'name'          => 'Documents',
            'singular_name' => 'Document',
            'add_new'       => 'Ajouter un document',
            'add_new_item'  => 'Ajouter un document',
            'edit_item'     => 'Modifier le document',
            'menu_name'     => 'Documents',
            'not_found'     => 'Aucun document',
        ],
        'public'    => false,
        'show_ui'   => true,
        'menu_icon' => 'dashicons-media-document',
        'supports'  => ['title'],
    ]);
}

/*
|--------------------------------------------------------------------------
| Metabox : fichier + catégorie + description
|--------------------------------------------------------------------------
*/
add_action('add_meta_boxes', 'campus_document_metabox');
function campus_document_metabox() {
    add_meta_box(
        'campus_document_fields',
        'Détails du document',
        'campus_document_render',
        'campus_document',
        'normal',
        'default'
    );
}

function campus_document_render($post) {
    wp_nonce_field('campus_document_save', 'campus_document_nonce');

    $url  = get_post_meta($post->ID, '_campus_doc_url', true);
    $cat  = get_post_meta($post->ID, '_campus_doc_category', true);
    $desc = get_post_meta($post->ID, '_campus_doc_description', true);

    $categories = [
        'bourse'      => 'Bourse',
        'visa'        => 'Visa',
        'logement'    => 'Logement',
        'inscription' => 'Inscription',
        'autre'       => 'Autre',
    ];
    ?>
    <p>
        <label><strong>Fichier (URL)</strong></label><br>
        <input type="url" name="campus_doc_url" value="<?php echo esc_attr($url); ?>"
               style="width:100%" placeholder="https://...">
        <span class="description">Média → Ajouter un fichier → copiez l'URL du fichier ici.</span>
    </p>
    <p>
        <label><strong>Catégorie</strong></label><br>
        <select name="campus_doc_category">
            <?php foreach ($categories as $k => $v) : ?>
                <option value="<?php echo esc_attr($k); ?>" <?php selected($cat, $k); ?>>
                    <?php echo esc_html($v); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>
    <p>
        <label><strong>Description</strong></label><br>
        <textarea name="campus_doc_description" rows="2" style="width:100%"><?php echo esc_textarea($desc); ?></textarea>
    </p>
    <?php
}

add_action('save_post_campus_document', 'campus_document_save');
function campus_document_save($post_id) {
    if (!isset($_POST['campus_document_nonce'])) return;
    if (!wp_verify_nonce($_POST['campus_document_nonce'], 'campus_document_save')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    if (isset($_POST['campus_doc_url'])) {
        update_post_meta($post_id, '_campus_doc_url', esc_url_raw($_POST['campus_doc_url']));
    }

    if (isset($_POST['campus_doc_category'])) {
        $allowed = ['bourse', 'visa', 'logement', 'inscription', 'autre'];
        $cat     = sanitize_text_field($_POST['campus_doc_category']);
        if (in_array($cat, $allowed, true)) {
            update_post_meta($post_id, '_campus_doc_category', $cat);
        }
    }

    if (isset($_POST['campus_doc_description'])) {
        update_post_meta($post_id, '_campus_doc_description', sanitize_textarea_field($_POST['campus_doc_description']));
    }
}
