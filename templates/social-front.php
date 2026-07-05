<?php
defined('ABSPATH') or die('No direct access');

/*
|--------------------------------------------------------------------------
| Frontend — Réseau Social Campus (LOT 2)
| Refonte : recherche d'amis + sections dédiées demandes / amis
|--------------------------------------------------------------------------
*/

add_action('init', 'campus_register_shortcodes');

function campus_register_shortcodes() {
    add_shortcode('campus_social', 'campus_social_shortcode');
}

function campus_social_shortcode() {
    if (!is_user_logged_in()) {
        return '<p class="campus-notice">'
            . esc_html__('Vous devez être connecté pour accéder au réseau social.', 'campus-core')
            . '</p>';
    }

    ob_start();
    ?>
    <style>
      #campus-social-app { max-width: 900px; margin: 0 auto; }
      #campus-social-app .campus-grid {
        display: grid; grid-template-columns: 1fr 1fr; gap: 20px;
      }
      @media (max-width: 700px) {
        #campus-social-app .campus-grid { grid-template-columns: 1fr; }
      }
      #campus-social-app .campus-card {
        background: #fff; border-radius: 12px; padding: 24px;
        box-shadow: 0 4px 24px rgba(0,0,0,0.08); margin-bottom: 20px;
      }
      #campus-social-app .campus-card h3 {
        color: #1B3A5C; font-size: 16px; margin: 0 0 16px;
        text-transform: uppercase; letter-spacing: 1px;
      }
      #campus-social-app ul { list-style: none; margin: 0; padding: 0; }
      #campus-social-app .campus-person {
        display: flex; align-items: center; gap: 12px;
        padding: 10px 0; border-bottom: 1px solid #f0f0f0;
      }
      #campus-social-app .campus-person:last-child { border-bottom: none; }
      #campus-social-app .campus-person img {
        width: 40px; height: 40px; border-radius: 50%; object-fit: cover; flex-shrink: 0;
      }
      #campus-social-app .campus-person-name { flex: 1; color: #1B3A5C; font-size: 14px; }
      #campus-social-app .campus-btn {
        background: #E8621A; color: #fff; border: none; padding: 8px 16px;
        border-radius: 6px; font-size: 13px; font-weight: 600; cursor: pointer;
      }
      #campus-social-app .campus-btn:hover { background: #c9521a; }
      #campus-social-app .campus-btn.remove { background: #94a3b8; }
      #campus-social-app .campus-btn:disabled { opacity: .6; cursor: default; }
      #campus-social-app .campus-tag {
        font-size: 12px; color: #888; background: #f0f0f0;
        padding: 6px 12px; border-radius: 20px;
      }
      #campus-social-app .campus-muted { color: #999; font-size: 14px; padding: 8px 0; }
      #campus-social-app .campus-search-input {
        width: 100%; padding: 12px 16px; border: 1.5px solid #ddd;
        border-radius: 8px; font-size: 14px; box-sizing: border-box; margin-bottom: 12px;
      }
    </style>

    <div id="campus-social-app">

      <div class="campus-grid">
        <section class="campus-card">
          <h3><?php esc_html_e('Demandes d\'amis', 'campus-core'); ?></h3>
          <ul id="campus-requests"></ul>
        </section>

        <section class="campus-card">
          <h3><?php esc_html_e('Mes amis', 'campus-core'); ?></h3>
          <ul id="campus-friends"></ul>
        </section>
      </div>

      <section class="campus-card">
        <h3><?php esc_html_e('Rechercher une personne', 'campus-core'); ?></h3>
        <input id="campus-search-input" class="campus-search-input" type="text"
               placeholder="<?php esc_attr_e('Entrez un nom...', 'campus-core'); ?>" autocomplete="off">
        <ul id="campus-search-results"></ul>
      </section>

    </div>
    <?php
    return ob_get_clean();
}
