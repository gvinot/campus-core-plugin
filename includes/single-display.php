<?php
defined('ABSPATH') or die('No direct access');

/*
|--------------------------------------------------------------------------
| Affichage des articles (single) — LOT 7 + LOT 10
|--------------------------------------------------------------------------
| - Photo à la une en haut des blogs et actualités (LOT 7)
| - Shortcode [campus_recent_blogs] pour la sidebar (LOT 7)
| - Bouton "J'aime" en bas des blogs (LOT 10)
|--------------------------------------------------------------------------
*/

/*
| Photo à la une en haut du contenu (priorité 10)
*/
add_filter('the_content', 'campus_prepend_featured_image', 10);
function campus_prepend_featured_image($content) {
    if (is_admin()) return $content;
    if (!is_singular(['campus_blog', 'campus_news'])) return $content;
    if (!in_the_loop() || !is_main_query()) return $content;
    if (!has_post_thumbnail()) return $content;

    $img = get_the_post_thumbnail(get_the_ID(), 'large', ['class' => 'campus-featured-img']);
    return '<div class="campus-featured-wrap">' . $img . '</div>' . $content;
}

/*
| Bouton "J'aime" en bas du contenu des blogs (priorité 20, après l'image)
| L'état initial (compteur + déjà aimé) est rendu côté serveur ;
| le clic est géré par assets/js/likes.js.
*/
add_filter('the_content', 'campus_append_like_button', 20);
function campus_append_like_button($content) {
    if (is_admin()) return $content;
    if (!is_singular('campus_blog')) return $content;
    if (!in_the_loop() || !is_main_query()) return $content;

    $post_id = get_the_ID();
    $count   = campus_get_like_count($post_id);
    $logged  = is_user_logged_in();
    $liked   = $logged ? campus_has_liked(get_current_user_id(), $post_id) : false;

    $bg    = $liked ? '#E8621A' : '#fff';
    $fg    = $liked ? '#fff'    : '#E8621A';
    $heart = $liked ? '❤️'     : '🤍';
    $label = $liked ? 'Aimé'    : "J'aime";

    ob_start();
    ?>
    <div class="campus-like-wrap" style="margin:36px 0;">
      <button class="campus-like-btn"
              data-post="<?php echo esc_attr($post_id); ?>"
              data-liked="<?php echo $liked ? '1' : '0'; ?>"
              <?php echo $logged ? '' : 'data-guest="1"'; ?>
              style="display:inline-flex;align-items:center;gap:10px;background:<?php echo $bg; ?>;color:<?php echo $fg; ?>;border:2px solid #E8621A;border-radius:30px;padding:12px 28px;font-size:15px;font-weight:600;cursor:pointer;transition:all .15s;">
        <span class="campus-like-heart"><?php echo $heart; ?></span>
        <span class="campus-like-count"><?php echo esc_html($count); ?></span>
        <span class="campus-like-label"><?php echo $label; ?></span>
      </button>
    </div>
    <?php
    return $content . ob_get_clean();
}

/*
| Shortcode [campus_recent_blogs count="5"] — sidebar
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
