<?php
defined('ABSPATH') or die('No direct access');

/*
|--------------------------------------------------------------------------
| Custom Post Types
|--------------------------------------------------------------------------
*/

function campus_register_post_types() {

    /*
    |------------------------------------------------
    | Blogs
    |------------------------------------------------
    */
    register_post_type('campus_blog', [
        'labels' => [
            'name' => 'Blogs',
            'singular_name' => 'Blog',
            'add_new' => 'Ajouter un blog',
            'add_new_item' => 'Ajouter un blog',
            'edit_item' => 'Modifier le blog',
            'new_item' => 'Nouveau blog',
            'view_item' => 'Voir le blog',
            'search_items' => 'Rechercher un blog',
            'not_found' => 'Aucun blog trouvé'
        ],
        'public' => true,
        'has_archive' => true,
        'menu_icon' => 'dashicons-welcome-write-blog',
        'supports' => ['title', 'editor', 'thumbnail', 'author', 'comments'],
        'capability_type' => 'post',
        'map_meta_cap' => true
    ]);

    /*
    |------------------------------------------------
    | Destinations
    |------------------------------------------------
    */
    register_post_type('campus_destination', [
        'labels' => [
            'name' => 'Destinations',
            'singular_name' => 'Destination',
            'add_new' => 'Ajouter une destination',
            'add_new_item' => 'Ajouter une destination',
            'edit_item' => 'Modifier la destination',
            'new_item' => 'Nouvelle destination',
            'view_item' => 'Voir la destination',
            'search_items' => 'Rechercher une destination',
            'not_found' => 'Aucune destination trouvée'
        ],
        'public' => true,
        'has_archive' => false,
        'menu_icon' => 'dashicons-location-alt',
        'supports' => ['title', 'editor', 'thumbnail'],
        'show_in_rest' => true
    ]);

    /*
    |------------------------------------------------
    | Actualités (admin)
    |------------------------------------------------
    */
    register_post_type('campus_news', [
        'labels' => [
            'name' => 'Actualités',
            'singular_name' => 'Actualité',
            'add_new' => 'Ajouter une actualité',
            'add_new_item' => 'Ajouter une actualité',
            'edit_item' => 'Modifier l’actualité',
            'new_item' => 'Nouvelle actualité',
            'view_item' => 'Voir l’actualité',
            'search_items' => 'Rechercher une actualité',
            'not_found' => 'Aucune actualité trouvée'
        ],
        'public' => true,
        'has_archive' => false,
        'menu_icon' => 'dashicons-megaphone',
        'supports' => ['title', 'editor', 'thumbnail'],
        'capability_type' => 'post',
        'map_meta_cap' => true
    ]);

}
add_action('init', 'campus_register_post_types');