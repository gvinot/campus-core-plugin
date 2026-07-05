# Campus Core

Plugin WordPress fournissant le cœur métier de **Glob'ISEL**, une plateforme
sociale destinée aux étudiants de l'ISEL pour partager leurs retours
d'expérience de mobilité internationale sous forme de blogs.

Le plugin expose une **API REST** consommée par le front (thème WordPress +
Elementor). Toute la logique métier est côté plugin ; l'affichage est géré
côté thème.

---

## Sommaire

- [Prérequis](#prérequis)
- [Installation](#installation)
- [Architecture](#architecture)
- [Rôles et permissions](#rôles-et-permissions)
- [API REST](#api-rest)
- [Base de données](#base-de-données)
- [Sécurité](#sécurité)
- [Désinstallation](#désinstallation)

---

## Prérequis

- WordPress 6.x
- PHP 7.4 ou supérieur
- Un thème avec un constructeur de page (le projet utilise Astra + Elementor)

Plugins tiers utilisés côté front (non requis par Campus Core lui-même) :
Theme My Login, Nav Menu Roles, Simple Local Avatars.

---

## Installation

1. Copier le dossier `campus-core/` dans `wp-content/plugins/`
2. Activer le plugin depuis **Extensions** dans l'admin WordPress
3. À l'activation, le plugin crée automatiquement :
   - les tables SQL (`wp_campus_friends`, `wp_campus_likes`)
   - les rôles `campus_student` et `campus_blogger`
   - les Custom Post Types (blogs, destinations, actualités)

---

## Architecture

```
campus-core/
├── campus-core.php          Bootstrap : constantes, hooks activation/désactivation
├── loader.php               Chargement ordonné de tous les modules
├── uninstall.php            Nettoyage complet à la suppression du plugin
│
├── includes/
│   ├── roles.php            Déclaration des rôles WordPress
│   ├── post-types.php       CPT : campus_blog, campus_destination, campus_news
│   ├── permissions.php      Statuts utilisateur, bannissement, restrictions d'accès
│   ├── destinations.php     Metabox coordonnées GPS des destinations
│   │
│   ├── social.php           Logique métier réseau social (table friends)
│   ├── likes.php            Logique métier likes (table likes)
│   │
│   ├── api.php              Endpoints REST : amis
│   ├── api-blogs.php        Endpoints REST : blogs (lecture) + likes
│   ├── api-blog-create.php  Endpoints REST : création/suppression blog, bio
│   ├── api-destinations.php Endpoints REST : destinations + assignation
│   ├── api-users.php        Endpoints REST : profils utilisateurs
│   ├── api-social.php       Endpoints REST : recherche users + blogs des amis
│   ├── api-admin.php        Endpoints REST : actualités + gestion admin
│   │
│   └── assets.php           Enqueue JS + injection du nonce (CampusData)
│
├── templates/
│   └── social-front.php     Shortcode [campus_social]
│
└── assets/
    └── js/
        └── social.js        Frontend réseau social (recherche, amis, demandes)
```

Le chargement est centralisé dans `loader.php`, lui-même inclus par
`campus-core.php`. L'ordre de chargement est important : les modules métier
(`social.php`, `likes.php`, `permissions.php`) sont chargés avant les modules
API qui en dépendent.

---

## Rôles et permissions

Le plugin utilise **deux mécanismes complémentaires** :

| Mécanisme | Stockage | Valeurs |
|---|---|---|
| Statut Campus | user meta `campus_status` | `student`, `blogger`, `banned` |
| Rôle WordPress | table roles | `campus_student`, `campus_blogger` |

Les deux sont synchronisés via `campus_sync_user_role()`. La gestion se fait
depuis **Utilisateurs → modifier → Statut Campus** (pas via le champ Rôle
standard de WordPress).

- **student** : lecture seule, réseau social
- **blogger** : peut publier des blogs et s'assigner à une destination
- **banned** : accès bloqué (front + API)

Les fonctions de vérification (`campus_is_blogger`, `campus_is_admin`,
`campus_is_banned`) sont définies dans `permissions.php` et vérifient à la fois
le meta et le rôle WordPress pour plus de robustesse.

---

## API REST

Namespace : `campus/v1` — base : `/wp-json/campus/v1`

### Amis
| Méthode | Endpoint | Auth | Description |
|---|---|---|---|
| POST | `/friends/request` | connecté | Envoyer une demande |
| POST | `/friends/accept` | connecté | Accepter une demande |
| POST | `/friends/remove` | connecté | Retirer un ami |
| GET | `/friends/list` | connecté | Liste de mes amis |
| GET | `/friends/requests` | connecté | Demandes reçues |

### Blogs
| Méthode | Endpoint | Auth | Description |
|---|---|---|---|
| GET | `/blogs` | public | Liste (filtres : `per_page`, `page`, `destination`, `author`) |
| GET | `/blogs/top` | public | Top blogs les plus likés |
| GET | `/blogs/{id}` | public | Détail d'un blog |
| GET | `/blogs/friends` | connecté | Blogs des amis |
| POST | `/blogs/{id}/like` | connecté | Liker / unliker |
| POST | `/blogs/create` | blogueur | Créer un blog (image base64 optionnelle) |
| DELETE | `/blogs/{id}` | auteur/admin | Supprimer son blog |

### Destinations
| Méthode | Endpoint | Auth | Description |
|---|---|---|---|
| GET | `/destinations` | public | Liste + coordonnées GPS |
| GET | `/destinations/{id}` | public | Détail + blogueurs assignés |
| POST | `/destinations/{id}/assign` | blogueur | S'assigner |
| POST | `/destinations/{id}/unassign` | blogueur | Se désassigner |

### Utilisateurs
| Méthode | Endpoint | Auth | Description |
|---|---|---|---|
| GET | `/users/me` | connecté | Mon profil (privé) |
| GET | `/users/{id}` | connecté | Profil public + statut d'amitié |
| GET | `/users/{id}/blogs` | public | Blogs d'un utilisateur |
| GET | `/users/search?q=` | connecté | Recherche (min. 2 caractères) |
| POST | `/users/me/bio` | connecté | Mettre à jour sa bio |
| POST | `/users/me/avatar` | connecté | Changer sa photo (base64) |

### Actualités & Admin
| Méthode | Endpoint | Auth | Description |
|---|---|---|---|
| GET | `/actualites` | public | Liste des actualités |
| GET | `/actualites/{id}` | public | Détail |
| POST | `/admin/users/{id}/status` | admin | Changer le statut |
| POST | `/admin/users/{id}/ban` | admin | Bannir |
| POST | `/admin/users/{id}/unban` | admin | Débannir |

Les requêtes authentifiées par cookie nécessitent l'en-tête `X-WP-Nonce`
(nonce `wp_rest`), injecté côté front via l'objet JS `CampusData`.

---

## Base de données

### `wp_campus_friends`
| Colonne | Type | Note |
|---|---|---|
| id | BIGINT | PK auto-increment |
| user_id | BIGINT | émetteur de la relation |
| friend_id | BIGINT | destinataire |
| status | VARCHAR(20) | `pending` / `accepted` |
| created_at | DATETIME | |

Contrainte d'unicité sur `(user_id, friend_id)`.

### `wp_campus_likes`
| Colonne | Type | Note |
|---|---|---|
| id | BIGINT | PK auto-increment |
| user_id | BIGINT | auteur du like |
| post_id | BIGINT | blog liké |
| created_at | DATETIME | |

Contrainte d'unicité sur `(user_id, post_id)`.

---

## Sécurité

- Toutes les requêtes SQL passent par `$wpdb->prepare()`
- Chaque route REST possède un `permission_callback` explicite
- Les entrées sont assainies (`sanitize_text_field`, `absint`, `wp_kses_post`)
- Les sorties sont échappées côté PHP et JS (protection XSS)
- Les uploads d'images (avatars, blogs) valident type MIME et taille (max 5 Mo)
- Contrôle de propriété : un utilisateur ne peut modifier/supprimer que ses
  propres données (sauf admin)
- Rate limiting par transients sur les actions sensibles (demandes d'amis,
  création de blog, likes)

---

## Désinstallation

La suppression du plugin depuis l'admin WordPress déclenche `uninstall.php`,
qui supprime les tables SQL, les user meta, les rôles personnalisés et les
transients. Les contenus (blogs, destinations, actualités) sont **conservés**
pour éviter toute perte de données accidentelle.

---

## Auteur

Guillaume Vinot — projet Glob'ISEL (ISEL, 2026)
