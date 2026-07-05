<?php
defined('ABSPATH') or die('No direct access');

/*
|--------------------------------------------------------------------------
| Permissions métier Campus
|--------------------------------------------------------------------------
*/

define('CAMPUS_META_STATUS', 'campus_status');
// valeurs possibles : 'student' | 'blogger' | 'banned'

/*
|--------------------------------------------------------------------------
| Initialisation statut utilisateur à l'inscription
|--------------------------------------------------------------------------
*/
function campus_init_user_status($user_id) {
    if (!get_user_meta($user_id, CAMPUS_META_STATUS, true)) {
        update_user_meta($user_id, CAMPUS_META_STATUS, 'student');
    }
}
add_action('user_register', 'campus_init_user_status');

/*
|--------------------------------------------------------------------------
| Helpers de vérification
|--------------------------------------------------------------------------
*/
function campus_is_admin($user_id = null) {
    $user = $user_id ? get_user_by('id', $user_id) : wp_get_current_user();
    if (!$user || !$user->ID) return false;
    return in_array('administrator', (array) $user->roles, true);
}

/*
| FIX LOT1 : détection robuste du blogueur.
| On considère un utilisateur comme blogueur si :
|   - son meta campus_status vaut 'blogger', OU
|   - il possède le rôle WordPress campus_blogger
| Cela évite le blocage si le rôle a été assigné sans passer par
| le sélecteur "Statut Campus".
*/
function campus_is_blogger($user_id = null) {
    $user_id = $user_id ?: get_current_user_id();

    if (get_user_meta($user_id, CAMPUS_META_STATUS, true) === 'blogger') {
        return true;
    }

    $user = get_user_by('id', $user_id);
    return $user && in_array('campus_blogger', (array) $user->roles, true);
}

function campus_is_banned($user_id = null) {
    $user_id = $user_id ?: get_current_user_id();
    return get_user_meta($user_id, CAMPUS_META_STATUS, true) === 'banned';
}

/*
|--------------------------------------------------------------------------
| Blocage des utilisateurs bannis
|--------------------------------------------------------------------------
*/
add_filter('rest_authentication_errors', 'campus_block_banned_rest_access');
function campus_block_banned_rest_access($result) {
    if ($result !== null) return $result;

    if (is_user_logged_in() && campus_is_banned()) {
        return new WP_Error('campus_banned', 'Votre compte a été suspendu.', ['status' => 403]);
    }
    return $result;
}

add_action('template_redirect', 'campus_block_banned_frontend');
function campus_block_banned_frontend() {
    if (is_admin()) return;
    if (!is_user_logged_in()) return;

    if (campus_is_banned() && is_page()) {
        wp_die(
            esc_html__('Votre compte a été suspendu. Contactez l\'administration.', 'campus-core'),
            esc_html__('Compte suspendu', 'campus-core'),
            ['response' => 403, 'back_link' => false]
        );
    }
}

/*
|--------------------------------------------------------------------------
| Restriction des pages réservées aux membres connectés
|--------------------------------------------------------------------------
*/
add_action('template_redirect', 'campus_restrict_member_pages');
function campus_restrict_member_pages() {
    if (is_user_logged_in()) return;

    $restricted = ['blogs', 'mon-profil', 'administratif'];
    if (is_page($restricted)) {
        wp_safe_redirect(home_url('/login'));
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Blocage publication non autorisée
|--------------------------------------------------------------------------
*/
function campus_block_blog_creation($post_id, $post, $update) {

    if ($post->post_type !== 'campus_blog') return;
    if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) return;
    if ($post->post_status === 'auto-draft') return;

    if (campus_is_admin()) return;

    if (campus_is_banned()) {
        wp_die(
            esc_html__('Votre compte a été suspendu.', 'campus-core'),
            esc_html__('Accès refusé', 'campus-core'),
            ['response' => 403]
        );
    }

    if (!campus_is_blogger()) {
        wp_die(
            esc_html__('Vous n\'êtes pas autorisé à publier des blogs.', 'campus-core'),
            esc_html__('Accès refusé', 'campus-core'),
            ['response' => 403]
        );
    }
}
add_action('wp_insert_post', 'campus_block_blog_creation', 10, 3);

/*
|--------------------------------------------------------------------------
| Admin UI — gestion du statut utilisateur
|--------------------------------------------------------------------------
*/
function campus_user_status_field($user) {
    if (!campus_is_admin()) return;

    $status = get_user_meta($user->ID, CAMPUS_META_STATUS, true);
    ?>
    <h3><?php esc_html_e('Statut Campus', 'campus-core'); ?></h3>
    <table class="form-table">
        <tr>
            <th><label for="campus_status"><?php esc_html_e('Statut du compte', 'campus-core'); ?></label></th>
            <td>
                <select name="campus_status" id="campus_status">
                    <option value="student" <?php selected($status, 'student'); ?>><?php esc_html_e('Étudiant', 'campus-core'); ?></option>
                    <option value="blogger" <?php selected($status, 'blogger'); ?>><?php esc_html_e('Blogueur autorisé', 'campus-core'); ?></option>
                    <option value="banned" <?php selected($status, 'banned'); ?>><?php esc_html_e('⛔ Banni / Suspendu', 'campus-core'); ?></option>
                </select>
                <p class="description"><?php esc_html_e('Utilisez CE sélecteur (pas le champ Rôle ci-dessus) pour gérer les droits Campus.', 'campus-core'); ?></p>
            </td>
        </tr>
    </table>
    <?php
}
add_action('show_user_profile', 'campus_user_status_field');
add_action('edit_user_profile', 'campus_user_status_field');

function campus_save_user_status($user_id) {
    if (!campus_is_admin()) return;
    if (!isset($_POST['campus_status'])) return;

    $allowed = ['student', 'blogger', 'banned'];
    $value   = sanitize_text_field($_POST['campus_status']);

    if (!in_array($value, $allowed, true)) return;

    update_user_meta($user_id, CAMPUS_META_STATUS, $value);
}
add_action('personal_options_update',  'campus_save_user_status');
add_action('edit_user_profile_update', 'campus_save_user_status');

/*
|--------------------------------------------------------------------------
| Synchronisation statut → rôle WordPress
|--------------------------------------------------------------------------
*/
function campus_sync_user_role($user_id) {
    $status = get_user_meta($user_id, CAMPUS_META_STATUS, true);
    $user   = new WP_User($user_id);

    $user->remove_role('campus_student');
    $user->remove_role('campus_blogger');

    if ($status === 'blogger') {
        $user->add_role('campus_blogger');
    } elseif ($status === 'student') {
        $user->add_role('campus_student');
    }
}
add_action('personal_options_update',  'campus_sync_user_role');
add_action('edit_user_profile_update', 'campus_sync_user_role');
