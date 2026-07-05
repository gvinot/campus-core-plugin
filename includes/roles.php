<?php
defined('ABSPATH') or die('No direct access');

/*
|--------------------------------------------------------------------------
| Rôles et capacités
| FIX LOG2 : register_activation_hook retiré d'ici → centralisé dans campus-core.php
| FIX LOG4 : remove_role conditionnel (ne pas détruire les rôles si déjà existants)
|--------------------------------------------------------------------------
*/

function campus_register_roles() {

    /*
    | FIX LOG4 : on ne supprime les rôles QUE s'ils n'existent pas encore.
    | Supprimer un rôle existant désenregistre tous les utilisateurs qui l'ont.
    | En production, on ne remove_role que si une migration le nécessite explicitement.
    */
    if (!get_role('campus_student')) {
        add_role('campus_student', 'Étudiant', [
            'read' => true,
        ]);
    }

    if (!get_role('campus_blogger')) {
        add_role('campus_blogger', 'Étudiant Blogueur', [
            'read'          => true,
            'edit_posts'    => true,
            'publish_posts' => true,
            'delete_posts'  => false,
        ]);
    }
}
