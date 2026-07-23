# Campus Core

Plugin WordPress fournissant le cœur métier de **Glob'ISEL**, plateforme
sociale des étudiants de l'ISEL dédiée au partage d'expériences de mobilité
internationale.

Le plugin expose une **API REST** consommée par le front (thème Astra +
Elementor). Toute la logique métier vit ici ; le front ne fait que consommer.

---

## Sommaire

- [Campus Core](#campus-core)
  - [Sommaire](#sommaire)
  - [Prérequis](#prérequis)
  - [Installation](#installation)
  - [Architecture](#architecture)
  - [Rôles et permissions](#rôles-et-permissions)
  - [Inscription](#inscription)
  - [API REST](#api-rest)
    - [Amis](#amis)
    - [Blogs](#blogs)
    - [Destinations](#destinations)
    - [Utilisateurs](#utilisateurs)
    - [Bons plans](#bons-plans)
    - [Documents, actualités et administration](#documents-actualités-et-administration)
  - [Base de données](#base-de-données)
    - [`wp_campus_friends`](#wp_campus_friends)
    - [`wp_campus_likes`](#wp_campus_likes)
    - [`wp_campus_bonplan_votes`](#wp_campus_bonplan_votes)
    - [Métadonnées utilisateur](#métadonnées-utilisateur)
    - [Métadonnées d'articles](#métadonnées-darticles)
  - [Front-end](#front-end)
    - [Shortcodes](#shortcodes)
    - [Conventions JavaScript](#conventions-javascript)
    - [Avatars](#avatars)
    - [Badges](#badges)
  - [Sécurité](#sécurité)
  - [Constantes de configuration](#constantes-de-configuration)
  - [Désinstallation](#désinstallation)
  - [Auteur](#auteur)

---

## Prérequis

- WordPress 6.x
- PHP 7.4 ou supérieur
- Un thème avec constructeur de page (le projet utilise Astra + Elementor)

Plugins tiers utilisés côté front, non requis par Campus Core lui-même :
Theme My Login, Nav Menu Roles, Loco Translate.

---

## Installation

1. Copier le dossier `campus-core/` dans `wp-content/plugins/`
2. Activer le plugin depuis **Extensions**
3. L'activation crée automatiquement :
   - les tables SQL (`wp_campus_friends`, `wp_campus_likes`, `wp_campus_bonplan_votes`)
   - les rôles `campus_student` et `campus_blogger`
   - les types de contenu personnalisés

---

## Architecture

```
campus-core/
├── campus-core.php          Bootstrap : constantes, activation, désactivation
├── loader.php               Chargement ordonné des modules
├── uninstall.php            Nettoyage complet à la suppression
│
├── includes/
│   ├── roles.php            Rôles WordPress personnalisés
│   ├── post-types.php       CPT blogs, destinations, actualités
│   ├── permissions.php      Statuts, bannissement, restriction des pages
│   ├── auth.php             Règles d'inscription (email, identité, charte)
│   ├── destinations.php     Metabox GPS + site de l'université
│   ├── documents.php        CPT documents administratifs
│   ├── bonplans.php         CPT bons plans, votes, badge « valeur sûre »
│   │
│   ├── social.php           Réseau d'amis
│   ├── likes.php            Likes et formatage des blogs
│   ├── comments.php         Commentaires : membres only + modération
│   ├── coauthors.php        Blogs groupés (co-auteurs)
│   ├── badges.php           Calcul des 14 badges
│   │
│   ├── api.php              Endpoints amis
│   ├── api-blogs.php        Endpoints blogs (lecture) + likes
│   ├── api-blog-create.php  Création / suppression de blog, bio
│   ├── api-destinations.php Endpoints destinations
│   ├── api-users.php        Endpoints profils
│   ├── api-social.php       Recherche de membres, blogs des amis
│   ├── api-admin.php        Actualités et administration
│   ├── api-documents.php    Endpoints documents
│   ├── api-bonplans.php     Endpoints bons plans
│   ├── api-badges.php       Endpoint badges
│   │
│   ├── single-display.php   Photo à la une, bouton like, shortcode sidebar
│   ├── member-profile.php   Page profil public, redirection archives auteur
│   ├── users.php            Redirections de connexion, avatars
│   ├── menu.php             Déconnexion dynamique + shortcode
│   ├── admin-bar.php        Masquage de la barre WordPress
│   └── assets.php           Chargement des scripts front
│
├── templates/
│   └── social-front.php     Shortcode [campus_social]
│
└── assets/js/
    ├── social.js            Réseau social (amis, demandes, recherche)
    ├── likes.js             Bouton « J'aime »
    ├── bonplans.js          Page Bons plans
    ├── badges.js            Affichage des badges
    ├── blog-publish.js      Formulaire de publication
    ├── account.js           Page Mon compte (photo, bio)
    └── my-blogs.js          Liste de ses blogs
```

L'ordre de chargement compte : les modules métier (`permissions.php`,
`social.php`, `likes.php`, `bonplans.php`, `coauthors.php`) sont chargés avant
les modules API qui en dépendent.

---

## Rôles et permissions

Deux mécanismes complémentaires, synchronisés par `campus_sync_user_role()` :

| Mécanisme | Stockage | Valeurs |
|---|---|---|
| Statut Campus | user meta `campus_status` | `student`, `blogger`, `banned` |
| Rôle WordPress | table des rôles | `campus_student`, `campus_blogger` |

La gestion se fait dans **Utilisateurs → modifier → Statut Campus**, jamais via
le champ « Rôle » standard de WordPress.

- **student** — lecture, réseau social, bons plans, commentaires
- **blogger** — publie des blogs, s'assigne une destination
- **banned** — accès bloqué côté front et côté API

Les fonctions `campus_is_blogger()`, `campus_is_admin()` et
`campus_is_banned()` (`includes/permissions.php`) vérifient à la fois la
métadonnée et le rôle WordPress, par robustesse.

---

## Inscription

`includes/auth.php` ajoute trois règles métier via les hooks officiels
WordPress, **sans réimplémenter aucun mécanisme de sécurité** :

1. **Domaine email restreint** : seules les adresses du domaine défini par
   `CAMPUS_ALLOWED_EMAIL_DOMAINS` sont acceptées
2. **Prénom et nom obligatoires** : `display_name` devient « Prénom Nom »
3. **Acceptation de la charte et des CGU** : case à cocher obligatoire, avec
   enregistrement de la date et de la version acceptée

La date et la version d'acceptation sont visibles dans la fiche de chaque
utilisateur (section « Charte et conditions d'utilisation »).

> **En cas de modification des textes légaux**, incrémenter
> `CAMPUS_LEGAL_VERSION` : les acceptations passées restent tracées avec leur
> version d'origine.

La connexion accepte l'adresse email à la place de l'identifiant : c'est le
comportement natif de WordPress, aucun code n'est nécessaire.

---

## API REST

Namespace `campus/v1`, base `/wp-json/campus/v1`

### Amis
| Méthode | Endpoint | Accès |
|---|---|---|
| POST | `/friends/request` | connecté |
| POST | `/friends/accept` | connecté |
| POST | `/friends/remove` | connecté |
| GET | `/friends/list` | connecté |
| GET | `/friends/requests` | connecté |

### Blogs
| Méthode | Endpoint | Accès |
|---|---|---|
| GET | `/blogs` | public |
| GET | `/blogs/top` | public |
| GET | `/blogs/{id}` | public |
| GET | `/blogs/friends` | connecté |
| POST | `/blogs/{id}/like` | connecté |
| POST | `/blogs/create` | blogueur |
| DELETE | `/blogs/{id}` | auteur ou admin |

`POST /blogs/create` exige une image (base64) et accepte un tableau
`coauthors` d'identifiants, validés comme amis de l'auteur côté serveur. Le
blog est créé en statut `pending` pour les non-administrateurs.

### Destinations
| Méthode | Endpoint | Accès |
|---|---|---|
| GET | `/destinations` | public |
| GET | `/destinations/{id}` | public |
| POST | `/destinations/{id}/assign` | blogueur |
| POST | `/destinations/{id}/unassign` | blogueur |

### Utilisateurs
| Méthode | Endpoint | Accès |
|---|---|---|
| GET | `/users/me` | connecté |
| GET | `/users/{id}` | connecté |
| GET | `/users/{id}/blogs` | public |
| GET | `/users/{id}/badges` | connecté |
| GET | `/users/search?q=` | connecté |
| POST | `/users/me/bio` | connecté |
| POST | `/users/me/avatar` | connecté |

### Bons plans
| Méthode | Endpoint | Accès |
|---|---|---|
| GET | `/bonplans` | connecté |
| POST | `/bonplans/create` | connecté |
| POST | `/bonplans/{id}/vote` | connecté |
| DELETE | `/bonplans/{id}` | auteur ou admin |

Filtres disponibles sur `GET /bonplans` : `destination`, `categorie`, `sort`
(`votes` ou `recent`).

### Documents, actualités et administration
| Méthode | Endpoint | Accès |
|---|---|---|
| GET | `/documents` | connecté |
| GET | `/actualites` | public |
| GET | `/actualites/{id}` | public |
| GET | `/stats` | public |
| POST | `/admin/users/{id}/status` | admin |
| POST | `/admin/users/{id}/ban` | admin |
| POST | `/admin/users/{id}/unban` | admin |

Les requêtes authentifiées par cookie nécessitent l'en-tête `X-WP-Nonce`,
fourni côté front par l'objet JavaScript `CampusData` (injecté sur toutes les
pages pour les utilisateurs connectés).

---

## Base de données

### `wp_campus_friends`
| Colonne | Type | Note |
|---|---|---|
| id | BIGINT | clé primaire |
| user_id | BIGINT | émetteur |
| friend_id | BIGINT | destinataire |
| status | VARCHAR(20) | `pending` ou `accepted` |
| created_at | DATETIME | |

Unicité sur `(user_id, friend_id)`.

### `wp_campus_likes`
| Colonne | Type |
|---|---|
| id | BIGINT |
| user_id | BIGINT |
| post_id | BIGINT |
| created_at | DATETIME |

Unicité sur `(user_id, post_id)`.

### `wp_campus_bonplan_votes`
| Colonne | Type |
|---|---|
| id | BIGINT |
| user_id | BIGINT |
| bonplan_id | BIGINT |
| created_at | DATETIME |

Unicité sur `(user_id, bonplan_id)`.

### Métadonnées utilisateur
`campus_status`, `campus_bio`, `campus_destination_id`, `campus_avatar_url`,
`campus_legal_accepted_at`, `campus_legal_version`

### Métadonnées d'articles
`_campus_coauthors`, `_campus_website`, `_campus_doc_url`,
`_campus_doc_category`, `_campus_doc_description`, `_campus_bp_category`,
`_campus_bp_destination`, `_campus_bp_price`, `_campus_bp_link`

---

## Front-end

### Shortcodes

| Shortcode | Rôle |
|---|---|
| `[campus_social]` | Réseau social : amis, demandes, recherche |
| `[campus_recent_blogs count="5"]` | Derniers blogs (sidebar) |
| `[campus_logout_button]` | Bouton de déconnexion (URL avec nonce) |

> Dans Elementor, les shortcodes doivent être placés dans un widget
> **Shortcode**, jamais dans un widget HTML qui ne les exécute pas.

### Conventions JavaScript

Le widget HTML d'Elementor casse tout `<script>` contenant une balise
fermante dans une chaîne (`'</div>'`). **Tout le JavaScript vit donc dans
`assets/js/`**, chargé par `includes/assets.php` ; les widgets Elementor ne
contiennent que du markup.

Chaque script s'auto-désactive si son conteneur est absent, et gère le
chargement différé d'Elementor :

```javascript
function init() {
  if (!document.getElementById('mon-conteneur')) return;
  // …
}
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init);
} else {
  init();
}
```

### Avatars

Ne jamais appeler `get_avatar_url()` directement : utiliser
`campus_get_avatar_url($user_id, $size)` (`includes/users.php`), qui génère un
avatar SVG local à initiales lorsque le membre n'a pas de photo. Aucune
dépendance réseau.

### Badges

Les 14 badges sont **calculés à la volée**, sans table dédiée : ils sont donc
toujours à jour. Dans `campus_get_user_badges()`, le badge **Légende
Glob'ISEL doit rester en dernier** car il compte les badges attribués
au-dessus de lui.

---

## Sécurité

- Requêtes SQL systématiquement préparées (`$wpdb->prepare`)
- `permission_callback` explicite sur chaque route REST
- Entrées assainies (`sanitize_text_field`, `absint`, `wp_kses_post`)
- Sorties échappées côté PHP et JavaScript
- Uploads d'images : type MIME vérifié, taille plafonnée à 5 Mo, contenu réel
  validé par `getimagesize()`
- Contrôle de propriété : un membre ne modifie que ses propres contenus
- Limitation de fréquence par transients sur les actions sensibles
- Modération administrateur sur les blogs, les bons plans et les commentaires

Le masquage d'entrées de menu (Nav Menu Roles) est **cosmétique** : la
protection réelle des pages est assurée par
`campus_restrict_member_pages()` dans `includes/permissions.php`. Toute
nouvelle page réservée aux membres doit y être ajoutée.

---

## Constantes de configuration

Définies en tête des fichiers concernés, surchargeables depuis
`wp-config.php` si besoin.

| Constante | Fichier | Défaut | Rôle |
|---|---|---|---|
| `CAMPUS_ALLOWED_EMAIL_DOMAINS` | auth.php | `etu.univ-lehavre.fr` | Domaines email autorisés |
| `CAMPUS_PAGE_CHARTE` | auth.php | `charte` | Slug de la page charte |
| `CAMPUS_PAGE_CGU` | auth.php | `conditions-utilisation` | Slug de la page CGU |
| `CAMPUS_LEGAL_VERSION` | auth.php | `1.0` | Version des textes légaux |
| `CAMPUS_BONPLAN_SEUIL` | bonplans.php | `5` | Votes pour le badge « valeur sûre » |
| `CAMPUS_LICORNE_SEUIL` | badges.php | `100` | Réactions pour le badge Licorne |
| `CAMPUS_BACKPACKER_SEUIL` | badges.php | `5` | Bons plans pour le badge Backpacker |
| `CAMPUS_LEGENDE_SEUIL` | badges.php | `5` | Badges pour Légende Glob'ISEL |

---

## Désinstallation

La suppression du plugin depuis l'administration déclenche `uninstall.php`,
qui supprime les tables SQL, les métadonnées utilisateur, les rôles
personnalisés et les transients.

Les contenus (blogs, destinations, actualités, documents, bons plans) sont
**volontairement conservés** pour éviter toute perte accidentelle.

---

## Auteur

Guillaume Vinot — projet Glob'ISEL, ISEL, 2026
