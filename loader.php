<?php
defined('ABSPATH') or die('No direct access');

/*
|--------------------------------------------------------------------------
| Chargement du cœur métier
|--------------------------------------------------------------------------
*/

// --- Core ---
require_once CAMPUS_CORE_PATH . 'includes/roles.php';
require_once CAMPUS_CORE_PATH . 'includes/post-types.php';
require_once CAMPUS_CORE_PATH . 'includes/permissions.php';
require_once CAMPUS_CORE_PATH . 'includes/destinations.php';
require_once CAMPUS_CORE_PATH . 'includes/documents.php';
require_once CAMPUS_CORE_PATH . 'includes/bonplans.php';

// --- Réseau social ---
require_once CAMPUS_CORE_PATH . 'includes/social.php';
require_once CAMPUS_CORE_PATH . 'includes/likes.php';
require_once CAMPUS_CORE_PATH . 'includes/comments.php';
require_once CAMPUS_CORE_PATH . 'includes/badges.php';
require_once CAMPUS_CORE_PATH . 'includes/coauthors.php';

// --- API REST ---
require_once CAMPUS_CORE_PATH . 'includes/api.php';
require_once CAMPUS_CORE_PATH . 'includes/api-blogs.php';
require_once CAMPUS_CORE_PATH . 'includes/api-blog-create.php';
require_once CAMPUS_CORE_PATH . 'includes/api-destinations.php';
require_once CAMPUS_CORE_PATH . 'includes/api-users.php';
require_once CAMPUS_CORE_PATH . 'includes/api-social.php';
require_once CAMPUS_CORE_PATH . 'includes/api-admin.php';
require_once CAMPUS_CORE_PATH . 'includes/api-documents.php';
require_once CAMPUS_CORE_PATH . 'includes/api-bonplans.php';
require_once CAMPUS_CORE_PATH . 'includes/api-badges.php';     // Badges

// --- Affichage front ---
require_once CAMPUS_CORE_PATH . 'includes/single-display.php';
require_once CAMPUS_CORE_PATH . 'includes/member-profile.php';

// --- Admin & utilitaires ---
require_once CAMPUS_CORE_PATH . 'includes/admin.php';
require_once CAMPUS_CORE_PATH . 'includes/users.php';
require_once CAMPUS_CORE_PATH . 'includes/blogs.php';
require_once CAMPUS_CORE_PATH . 'includes/assets.php';
require_once CAMPUS_CORE_PATH . 'includes/menu.php';
require_once CAMPUS_CORE_PATH . 'includes/admin-bar.php';

// --- Templates ---
require_once CAMPUS_CORE_PATH . 'templates/social-front.php';
