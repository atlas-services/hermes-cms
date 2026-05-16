# AGENTS.md — Hermes3

Ce fichier résume le projet pour les humains et les outils d’assistance (Cursor, etc.). Le détail d’installation reste dans **`README.md`**.

## Produit

**Hermes (V3)** est un CMS pour **sites vitrines** : structure menu / pages / sections / posts, gabarits (folios, carousels, cards), contenu riche **CKEditor 5**, médias, et configuration visuelle (couleurs, fonds, largeurs des zones du site).

## Technique

| Domaine | Choix du projet |
|--------|------------------|
| Framework | Symfony **8**, PHP **8.4+** |
| UI | **Bootstrap 5** |
| Interactivité | **Stimulus** + bundles **Symfony UX** (icons, twig-component, autocomplete, …) — comportement UI : **`assets/controllers/`** en priorité ; scripts d’amorçage / libs dans `assets/*.js` (voir README) |
| Assets | **Asset Mapper + importmap** — pas de chaîne Node/npm obligatoire pour le front principal |
| Éditeur | **CKEditor 5** (importmap), pas FOSCKEditorBundle |
| Médias | **Sans elFinder** : admin Symfony + **Uppy** (XHR) pour l’upload |
| Persistance | Doctrine, VichUploader, LiipImagine, extensions Stof, etc. (voir `composer.json` / README) |

Règle Cursor plus structurée (toujours appliquée) : **`.cursor/rules/project-context.mdc`**.

## Périmètre fonctionnel

CMS vitrine : création de contenu riche, organisation par pages et sections, templates prédéfinis, médias et paramètres d’apparence (menu, contenu, footer, …).

## Évolutions récentes à respecter (mémo)

- Admin pages/sections : listes, médias en masse, suppressions ciblées ; requêtes avec jointures pour sections sans post.
- Présentation au niveau **Section** (transparent, couleur de fond, colonnes, filtre image sur gabarits liste) avec migrations et alignement admin + Twig + `HermesExtension`.
- Modales galerie : IDs par section, Stimulus `image_gallery_controller`, largeurs / plein écran responsive.
- Front : fond de section sur le wrapper `<section>` dans `templates/front/index.html.twig` ; cohérence transparent / `template_bgcolor` en base.
- Twig : `resolveSectionFromJson` vs `resolveListeSectionFromJson` selon le contexte liste.

## Où creuser dans le code

- `src/Controller/Admin/`, `templates/admin/`
- `templates/front/`, `src/Twig/`
- `assets/`, `importmap.php`, `assets/controllers.json`
- `src/Entity/`, `migrations/`, `tests/`

Pour les commandes `composer`, `importmap:require`, accès admin de démo : voir **`README.md`**.
