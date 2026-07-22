<?php
defined('ABSPATH') or die('No direct access');

/*
|--------------------------------------------------------------------------
| Authentification Campus — règles métier d'inscription
|--------------------------------------------------------------------------
| Ce fichier n'implémente AUCUN mécanisme de sécurité : le hachage des mots
| de passe, les sessions, les nonces et le processus de connexion restent
| entièrement gérés par WordPress et Theme My Login.
|
| On se contente d'ajouter des règles métier via les points d'extension
| officiels de WordPress :
|   - registration_errors : valider le domaine email et exiger prénom/nom
|   - register_form       : afficher les champs prénom / nom
|   - user_register       : enregistrer prénom/nom et composer display_name
|--------------------------------------------------------------------------
*/

/*
| Domaine(s) email autorisé(s) à l'inscription.
| Sépare par des virgules pour en autoriser plusieurs.
*/
if (!defined('CAMPUS_ALLOWED_EMAIL_DOMAINS')) {
    define('CAMPUS_ALLOWED_EMAIL_DOMAINS', 'etu.univ-lehavre.fr');
}

/*
|--------------------------------------------------------------------------
| 1. Validation : domaine email étudiant + prénom/nom obligatoires
|--------------------------------------------------------------------------
*/
add_filter('registration_errors', 'campus_validate_registration', 10, 3);
function campus_validate_registration($errors, $sanitized_user_login, $user_email) {

    // --- Domaine email ---
    $allowed = array_map('trim', explode(',', CAMPUS_ALLOWED_EMAIL_DOMAINS));
    $domain  = strtolower(substr(strrchr((string) $user_email, '@'), 1));

    if ($domain && !in_array($domain, $allowed, true)) {
        $errors->add(
            'campus_email_domain',
            'Seules les adresses email étudiantes (@' . esc_html($allowed[0]) . ') sont autorisées.'
        );
    }

    // --- Prénom / Nom obligatoires ---
    if (empty($_POST['first_name'])) {
        $errors->add('campus_first_name', 'Le prénom est obligatoire.');
    }
    if (empty($_POST['last_name'])) {
        $errors->add('campus_last_name', 'Le nom est obligatoire.');
    }

    return $errors;
}

/*
|--------------------------------------------------------------------------
| 2. Champs Prénom + Nom sur le formulaire d'inscription
|--------------------------------------------------------------------------
*/
add_action('register_form', 'campus_registration_fields');
function campus_registration_fields() {
    $first = !empty($_POST['first_name']) ? esc_attr(wp_unslash($_POST['first_name'])) : '';
    $last  = !empty($_POST['last_name'])  ? esc_attr(wp_unslash($_POST['last_name']))  : '';
    ?>
    <div class="campus-name-fields">
        <p><label for="first_name">Prénom<br>
            <input type="text" name="first_name" id="first_name" class="input" value="<?php echo $first; ?>" size="25" required></label></p>
        <p><label for="last_name">Nom<br>
            <input type="text" name="last_name" id="last_name" class="input" value="<?php echo $last; ?>" size="25" required></label></p>
    </div>
    <?php
}

/*
|--------------------------------------------------------------------------
| 3. Enregistrer prénom / nom et composer display_name = "Prénom Nom"
|    (c'est ce nom qui s'affiche partout sur le site)
|--------------------------------------------------------------------------
*/
add_action('user_register', 'campus_save_registration_fields');
function campus_save_registration_fields($user_id) {
    $first = !empty($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '';
    $last  = !empty($_POST['last_name'])  ? sanitize_text_field(wp_unslash($_POST['last_name']))  : '';

    if ($first !== '') update_user_meta($user_id, 'first_name', $first);
    if ($last  !== '') update_user_meta($user_id, 'last_name', $last);

    $display = trim($first . ' ' . $last);
    if ($display !== '') {
        wp_update_user(['ID' => $user_id, 'display_name' => $display]);
    }
}
