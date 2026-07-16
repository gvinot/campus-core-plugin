<?php
defined('ABSPATH') or die('No direct access');

/*
|--------------------------------------------------------------------------
| Affichage des articles (single) — LOT 7
|--------------------------------------------------------------------------
| - Affiche la photo à la une en haut des blogs et actualités
| - Fournit un shortcode [campus_recent_blogs] pour la sidebar
|--------------------------------------------------------------------------
*/

/*
| Photo à la une en haut du contenu (blogs + actualités)
| S'appuie sur le rendu natif WordPress : on injecte simplement l'image
| au début du contenu de l'article, sur les CPT concernés.
*/
add_filter('the_content', 'campus_prepend_featured_image');
function campus_prepend_featured_image($content) {
    if (is_admin()) return $content;
    if (!is_singular(['campus_blog', 'campus_news'])) return $content;
    if (!in_the_loop() || !is_main_query()) return $content;
    if (!has_post_thumbnail()) return $content;

    $img = get_the_post_thumbnail(get_the_ID(), 'large', ['class' => 'campus-featured-img']);

    return '<div class="campus-featured-wrap">' . $img . '</div>' . $content;
}

/*
| Shortcode [campus_recent_blogs count="5"]
| Liste les derniers blogs publiés — destiné à la sidebar.
*/
add_shortcode('campus_recent_blogs', 'campus_recent_blogs_shortcode');
function campus_recent_blogs_shortcode($atts) {
    $atts = shortcode_atts(['count' => 5], $atts);

    $posts = get_posts([
        'post_type'      => 'campus_blog',
        'post_status'    => 'publish',
        'posts_per_page' => absint($atts['count']),
    ]);

    if (empty($posts)) {
        return '<p class="campus-muted">Aucun blog pour le moment.</p>';
    }

    $out = '<ul class="campus-recent-blogs">';
    foreach ($posts as $p) {
        $out .= '<li><a href="' . esc_url(get_permalink($p)) . '">'
              . esc_html($p->post_title) . '</a></li>';
    }
    $out .= '</ul>';

    return $out;
}
