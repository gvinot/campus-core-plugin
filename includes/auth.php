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
|   - registration_errors : valider le domaine email, exiger prénom/nom,
|                           exiger l'acceptation de la charte et des CGU
|   - register_form       : afficher les champs prénom / nom / acceptation
|   - user_register       : enregistrer les informations saisies et tracer
|                           l'acceptation (date + version)
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
| Slugs des pages légales (à créer dans WordPress).
| Laisser vide pour retirer le lien correspondant de la case à cocher.
*/
if (!defined('CAMPUS_PAGE_CGU')) {
    define('CAMPUS_PAGE_CGU', 'conditions-utilisation');
}
if (!defined('CAMPUS_PAGE_CHARTE')) {
    define('CAMPUS_PAGE_CHARTE', 'charte');
}

/*
| Version des textes légaux.
| À INCRÉMENTER à chaque modification de la charte ou des CGU : la version
| acceptée est enregistrée pour chaque membre, ce qui permet de savoir qui
| doit ré-accepter les nouveaux textes.
*/
if (!defined('CAMPUS_LEGAL_VERSION')) {
    define('CAMPUS_LEGAL_VERSION', '1.0');
}

/*
|--------------------------------------------------------------------------
| 1. Validation : domaine email, prénom/nom, acceptation des textes
|--------------------------------------------------------------------------
*/
add_filter('registration_errors', 'campus_validate_registration', 10, 3);
function campus_validate_registration($errors, $sanitized_user_login, $user_email) {

    // --- Domaine email étudiant ---
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

    // --- Acceptation de la charte et des conditions d'utilisation ---
    if (empty($_POST['campus_legal_accept'])) {
        $errors->add(
            'campus_legal_accept',
            'Vous devez accepter la charte et les conditions d\'utilisation pour créer un compte.'
        );
    }

    return $errors;
}

/*
|--------------------------------------------------------------------------
| 2. Champs supplémentaires sur le formulaire d'inscription
|--------------------------------------------------------------------------
*/
add_action('register_form', 'campus_registration_fields');
function campus_registration_fields() {
    $first    = !empty($_POST['first_name']) ? esc_attr(wp_unslash($_POST['first_name'])) : '';
    $last     = !empty($_POST['last_name'])  ? esc_attr(wp_unslash($_POST['last_name']))  : '';
    $accepted = !empty($_POST['campus_legal_accept']);
    ?>
    <div class="campus-name-fields">
        <p>
            <label for="first_name">Prénom<br>
                <input type="text" name="first_name" id="first_name" class="input"
                       value="<?php echo $first; ?>" size="25" required>
            </label>
        </p>
        <p>
            <label for="last_name">Nom<br>
                <input type="text" name="last_name" id="last_name" class="input"
                       value="<?php echo $last; ?>" size="25" required>
            </label>
        </p>
    </div>

    <p class="campus-legal-accept">
        <label for="campus_legal_accept">
            <input type="checkbox" name="campus_legal_accept" id="campus_legal_accept"
                   value="1" <?php checked($accepted); ?> required>
            <span><?php echo wp_kses_post(campus_legal_accept_label()); ?></span>
        </label>
    </p>
    <?php
}

/*
| Libellé de la case à cocher, avec liens vers les pages légales
| lorsqu'elles existent.
*/
function campus_legal_accept_label() {
    $links = [];

    if (CAMPUS_PAGE_CHARTE !== '') {
        $links[] = '<a href="' . esc_url(home_url('/' . CAMPUS_PAGE_CHARTE)) . '" target="_blank" rel="noopener">la charte d\'utilisation</a>';
    }
    if (CAMPUS_PAGE_CGU !== '') {
        $links[] = '<a href="' . esc_url(home_url('/' . CAMPUS_PAGE_CGU)) . '" target="_blank" rel="noopener">les conditions générales d\'utilisation</a>';
    }

    if (empty($links)) {
        return 'J\'accepte la charte et les conditions d\'utilisation de la plateforme.';
    }

    return 'J\'ai lu et j\'accepte ' . implode(' et ', $links) . '.';
}

/*
|--------------------------------------------------------------------------
| 3. Enregistrement des informations et traçabilité de l'acceptation
|--------------------------------------------------------------------------
*/
add_action('user_register', 'campus_save_registration_fields');
function campus_save_registration_fields($user_id) {
    $first = !empty($_POST['first_name']) ? sanitize_text_field(wp_unslash($_POST['first_name'])) : '';
    $last  = !empty($_POST['last_name'])  ? sanitize_text_field(wp_unslash($_POST['last_name']))  : '';

    if ($first !== '') update_user_meta($user_id, 'first_name', $first);
    if ($last  !== '') update_user_meta($user_id, 'last_name', $last);

    // display_name = "Prénom Nom" (nom affiché partout sur le site)
    $display = trim($first . ' ' . $last);
    if ($display !== '') {
        wp_update_user(['ID' => $user_id, 'display_name' => $display]);
    }

    // Traçabilité de l'acceptation : date et version des textes acceptés
    if (!empty($_POST['campus_legal_accept'])) {
        update_user_meta($user_id, 'campus_legal_accepted_at', current_time('mysql'));
        update_user_meta($user_id, 'campus_legal_version', CAMPUS_LEGAL_VERSION);
    }
}

/*
|--------------------------------------------------------------------------
| 4. Affichage de l'acceptation dans la fiche utilisateur (admin)
|--------------------------------------------------------------------------
| Permet à l'administration de vérifier quand un membre a accepté les textes
| et quelle version il a acceptée.
*/
add_action('show_user_profile', 'campus_show_legal_acceptance');
add_action('edit_user_profile', 'campus_show_legal_acceptance');
function campus_show_legal_acceptance($user) {
    if (!current_user_can('manage_options')) return;

    $date    = get_user_meta($user->ID, 'campus_legal_accepted_at', true);
    $version = get_user_meta($user->ID, 'campus_legal_version', true);
    ?>
    <h3>Charte et conditions d'utilisation</h3>
    <table class="form-table">
        <tr>
            <th>Acceptation</th>
            <td>
                <?php if ($date) : ?>
                    Acceptées le <strong><?php echo esc_html(mysql2date('d/m/Y à H:i', $date)); ?></strong>
                    (version <?php echo esc_html($version ?: 'non précisée'); ?>)
                <?php else : ?>
                    <em>Aucune acceptation enregistrée (compte créé avant la mise en place, ou créé manuellement).</em>
                <?php endif; ?>
            </td>
        </tr>
    </table>
    <?php
}
