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

// --- Réseau social ---
require_once CAMPUS_CORE_PATH . 'includes/social.php';
require_once CAMPUS_CORE_PATH . 'includes/likes.php';

// --- API REST ---
require_once CAMPUS_CORE_PATH . 'includes/api.php';
require_once CAMPUS_CORE_PATH . 'includes/api-blogs.php';
require_once CAMPUS_CORE_PATH . 'includes/api-blog-create.php';
require_once CAMPUS_CORE_PATH . 'includes/api-destinations.php';
require_once CAMPUS_CORE_PATH . 'includes/api-users.php';
require_once CAMPUS_CORE_PATH . 'includes/api-social.php';
require_once CAMPUS_CORE_PATH . 'includes/api-admin.php';
require_once CAMPUS_CORE_PATH . 'includes/api-documents.php';

// --- Affichage front ---
require_once CAMPUS_CORE_PATH . 'includes/single-display.php';  // LOT 7

// --- Admin & utilitaires ---
require_once CAMPUS_CORE_PATH . 'includes/admin.php';
require_once CAMPUS_CORE_PATH . 'includes/users.php';
require_once CAMPUS_CORE_PATH . 'includes/blogs.php';
require_once CAMPUS_CORE_PATH . 'includes/assets.php';
require_once CAMPUS_CORE_PATH . 'includes/menu.php';

// --- Templates ---
require_once CAMPUS_CORE_PATH . 'templates/social-front.php';
