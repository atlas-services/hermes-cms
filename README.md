# Hermes - CMS

## Introduction -FR

Hermes (V3) est un CMS basé sur Symfony8, Bootstrap5 et les standards du Web.
Il fournit une interface d'administration afin de créer des contenus riche pour votre site Web.
Il fournit une interface d'administration pour configurer les couleurs, largeur...des différentes partie de votre site Web.
Il fournit quelques templates de type folios, carousels, cards ainsi qu'une saisie « libre » avec **CKEditor 5** (Asset Mapper / importmap, sans FOSCKEditorBundle). **elFinder a été retiré** : la gestion des médias en administration (arborescence, dossiers, suppression) est gérée par Symfony ; **l’upload** repose sur **Uppy** déclaré dans l’importmap (`assets/admin_media_upload.js`, envoi XHR vers l’API Symfony).

## Introduction -EN

Hermes (V3) is a CMS  based on Symfony8 and Bootstrap5 and the standards of Web.
It provides an admin to create a complete web site.
It provides configuration to select the color, background-color, width...for the different parts of your Web site (Menu, Content, Footer)
It provides some templates like folios, carousels, cards or « free presentation » with **CKEditor 5** (Asset Mapper / importmap, not FOSCKEditorBundle). **elFinder has been removed**: media management in the admin (tree, folders, delete) is handled by Symfony; **uploads** use **Uppy** from the importmap (`assets/admin_media_upload.js`, XHR to the Symfony upload API).

## Documentation

Création :
symfony new hermes3
cd hermes3
composer install
composer require symfony/asset-mapper symfony/asset symfony/twig-packs
composer require symfony/routing
composer require symfony/orm-pack
composer require --dev symfony/maker-bundle
composer require liip/imagine-bundle
composer require stof/doctrine-extensions-bundle
composer require symfony/form
composer require symfony/http-client
composer require symfony/mailer
composer require symfony/monolog-bundle
composer require symfony/security-bundle
composer require symfony/expression-language
composer require symfony/stimulus-bundle
composer require symfony/translation
composer require symfony/ux-icons
composer require symfony/ux-twig-component
composer require symfony/validator
composer require twig/inky-extra
composer require twig/intl-extra
composer require twig/string-extra
composer require vich/uploader-bundle
composer require symfony/ux-autocomplete
composer require --dev phpstan/phpstan
composer require --dev squizlabs/php_codesniffer
composer require --dev friendsofphp/php-cs-fixer
composer require --dev doctrine/doctrine-fixtures-bundle
composer require --dev phpunit/phpunit
composer require --dev symfony/browser-kit
composer require --dev symfony/css-selector
composer require --dev symfony/debug-bundle
composer require --dev symfony/stopwatch
composer require --dev symfony/web-profiler-bundle

### Asset Mapper (importmap) — sans elFinder : Uppy pour l’upload, puis le reste du front

**elFinder** n’est plus dans le projet. Les envois de fichiers (et dossiers) côté administration passent par **Uppy** (`importmap.php`, entrée `admin_media_upload`, script `assets/admin_media_upload.js`). Les autres dépendances front (Bootstrap, Font Awesome, AOS, **CKEditor 5**, Tom Select, polices **@fontsource**, etc.) suivent le même mécanisme. La police du contenu (`font_family`) et celle du menu (`nav_font_family`) se règlent en admin ; le rendu passe par `configs` sur le `<body>` (sans classes utilitaires `.h-*` de l’ancienne version).

Après un `git clone` ou si les paquets ne sont pas encore présents localement, télécharger les fichiers dans `assets/vendor/` (non versionné) :

```
php bin/console importmap:install
```

Pour recréer les entrées à la main (équivalent du dépôt), commandes utiles :

**Uppy** (remplace l’upload elFinder ; code dans `assets/admin_media_upload.js`) — soit les modules importés dans le projet :

```
php bin/console importmap:require @uppy/core
php bin/console importmap:require @uppy/dashboard
php bin/console importmap:require @uppy/xhr-upload
php bin/console importmap:require @uppy/core/dist/style.min.css
php bin/console importmap:require @uppy/dashboard/dist/style.min.css
```

soit le méta-paquet `uppy`, qui tire une arborescence large de `@uppy/*` (état actuel du dépôt) :

```
php bin/console importmap:require uppy
```

**Bootstrap, Font Awesome, AOS** (`assets/app.js`) :

```
php bin/console importmap:require bootstrap
php bin/console importmap:require bootstrap/dist/css/bootstrap.min.css
php bin/console importmap:require @fortawesome/fontawesome-free/css/all.css
php bin/console importmap:require aos
php bin/console importmap:require aos/dist/aos.css
```

**CKEditor 5** (contenu libre, `assets/ckeditor5.js` + contrôleur Stimulus `ckeditor5`) :

```
php bin/console importmap:require ckeditor5
php bin/console importmap:require ckeditor5/dist/ckeditor5.min.css
php bin/console importmap:require ckeditor5/translations/fr.js
```

**Tom Select** (UX Autocomplete, `assets/controllers.json`) :

```
php bin/console importmap:require tom-select
php bin/console importmap:require tom-select/dist/css/tom-select.bootstrap5.css
```

**Polices du site** (choix admin `font_family` / `nav_font_family` ; chargement via `@fontsource/*`, pas de classes `.h-*` comme en Hermes 2.2.7) — fichiers importés dans `assets/styles/site-fonts.js`, appliqués sur `<body>` via `templates/_site_body_style.html.twig` :

```
php bin/console importmap:require @fontsource/bai-jamjuree/400.css
php bin/console importmap:require @fontsource/bai-jamjuree/700.css
php bin/console importmap:require @fontsource/oswald/400.css
php bin/console importmap:require @fontsource/oswald/700.css
php bin/console importmap:require @fontsource/sofia/400.css
php bin/console importmap:require @fontsource/snowburst-one/400.css
php bin/console importmap:require @fontsource/alfa-slab-one/400.css
```

Les polices système (Verdana, Comic Sans, Palatino, Impact, etc.) ne nécessitent pas d’entrée importmap. Pour en ajouter une webfont, l’inscrire dans `src/Form/ConfigType::FONT_FAMILY`, l’importer dans `assets/styles/site-fonts.js`, puis `importmap:install`.

## Prérequis techniques

- PHP **8.4** minimum (voir `composer.json`).
- **symfony/expression-language** : requis pour les expressions `allow_if` de `config/packages/security.yaml` (admin ouvert sans login en `APP_ENV=dev` ou `local`, `ROLE_ADMIN` en prod).

## Sécurité admin (dev / prod) — FR

Règle dans `config/packages/security.yaml` :

```yaml
access_control:
    - { path: ^/(fr|en)/admin, roles: PUBLIC_ACCESS, allow_if: "is_granted('ROLE_ADMIN') or '%kernel.environment%' in ['dev', 'local']" }
```

En **production** (`APP_ENV=prod`), seuls les utilisateurs `ROLE_ADMIN` accèdent à `/fr/admin` et `/en/admin`. En **dev** ou **local**, l’admin est accessible sans être connecté.

## Admin security (dev / prod) — EN

See `config/packages/security.yaml` : admin requires `ROLE_ADMIN` in production; in `dev` or `local` environments the admin area is reachable without logging in (`symfony/expression-language` for `allow_if`).

## Migration depuis Hermes 2.2.x (FR)

Import d’une base SQLite 2.2.x ([release/2.2.7](https://github.com/atlas-services/hermes/tree/release/2.2.7)) vers Hermes 3. Le **nom de base** (stem du fichier `.sqlite` passé à la commande, ex. `envolarchi`) sert à la réécriture des URLs dans le HTML : `/{nom}/uploads/` → `/uploads/{nom}/` (source = stem de `dataFrom`, cible = stem de `dataTo`). Prévoir `data/config/<nom>.sqlite` (même `<nom>` que la base source) et l’ancien répertoire uploads (`entity/`, `content/`). `DATABASE_URL` en SQLite pendant `app:migrate`. Aligner **`APP_NAME`** (`.env`) sur le stem de la base cible si vous utilisez les chemins par défaut (`APP_DB`, `public/uploads/${APP_NAME}`).

```bash
php bin/console app:migrate \
  /var/www/html/hermes/data/db/envolarchi.sqlite \
  data/db/envolarchi.sqlite --force
```

Puis `DATABASE_URL` vers `data/db/envolarchi.sqlite`. Optionnel :

```bash
php bin/console app:init-hermes
php bin/console app:init-mentions-legales
```

`app:init-mentions-legales` crée trois menus **inactifs** (non affichés dans la navbar) : `/fr/mentions-legales`, `/fr/confidentialite`, `/fr/cgu-cgv`, chacun avec une section **libre** et un post dont le HTML provient de l’[API Hermes](https://api.hermes-cms.org) (`API_HERMES_*` dans `.env`) : le **premier** modèle du catalogue dont le champ **`type`** vaut `mentions-legales`, `confidentialite` ou `cgu-cgv` (un modèle par page).

Modèle HTML pour la page d’accueil vitrine du site [hermes-cms.org](https://hermes-cms.org) : `templates/exemple/hermes_cms_accueil.html` (section libre ; styles dans `assets/styles/app.css`, préfixe `.hermes-cms-home`).

Les dossiers `content/` et `entity/Config/` se recopient sous `public/uploads/<nom>/` — `app:migrate-media` avec la base migrée, l’ancienne racine uploads et la cible :

```bash
php bin/console app:migrate-media data/db/envolarchi.sqlite \
  /var/www/html/hermes/public/envolarchi/uploads \
  public/uploads/envolarchi
```

(`--dry-run`, `--overwrite`.) Copie partielle possible : `rsync -a ancien/uploads/content/ public/uploads/content/` et idem pour `entity/Config/`.

## Migration from Hermes 2.2.x (EN)

Import a 2.2.x SQLite DB into Hermes 3. URL rewrite uses the **DB filename stem** from the command (`dataFrom` → `dataTo`), e.g. `/envolarchi/uploads/` → `/uploads/envolarchi/`. Also `data/config/<name>.sqlite` and the legacy uploads tree. Set **`APP_NAME`** in `.env` to match the target DB stem when using default paths.

```bash
php bin/console app:migrate /path/to/old/data/db/envolarchi.sqlite data/db/envolarchi.sqlite --force
php bin/console app:migrate-media data/db/envolarchi.sqlite /path/to/old/public/envolarchi/uploads public/uploads/envolarchi
```
php bin/console app:migrate /var/www/html/hermes/data/db/atlas.sqlite data/db/atlas.sqlite --force
php bin/console app:migrate-media data/db/atlas.sqlite /var/www/html/hermes/public/atlas/uploads public/uploads/atlas
---

## Show Room et modeles - FR

- Vous pouvez acceder au show-room de nos principaux templates : [modeles](http://modeles.atlas-services.fr)
- Vous pouvez aussi voir quelques modèles de sites : 
  - [modele1](http://modele1.atlas-services.fr)
  - [modele2](http://modele2.atlas-services.fr)
  - [modele3](http://modele3.atlas-services.fr)
  - [modele4](http://modele4.atlas-services.fr)

##  Show Room and modeles -EN

- You can see a show-room of our templates : [modeles](http://modeles.atlas-services.fr)
- You can see some modeles : 
  - [modele1](http://modele1.atlas-services.fr)
  - [modele2](http://modele2.atlas-services.fr)
  - [modele3](http://modele3.atlas-services.fr)
  - [modele4](http://modele4.atlas-services.fr)

## License

This CMS is released under the MIT license. See the included
[LICENSE](LICENSE) file for more information.

## Contribuer - FR

Contributeurs bienvenus! Hermes est un logiciel libre. Si vous souhaitez contribuer, n'hésitez pas à proposer une PR! Vous pouvez lire le fichier [CONTRIBUTING](/CONTRIBUTING.md) qui vous indiquera quelques directions de contributions .

## Contribute - EN

We love contributors! Hermes is an free software. If you'd like to contribute, feel free to propose a PR! You
can follow the [CONTRIBUTING](/CONTRIBUTING.md) file which will explain you some needs about contributing.

# Install : Plateform Linux

Get the Repository

```
cd /var/www/html
git clone git@github.com:atlas-services/hermes.git
or
git clone https://github.com/atlas-services/hermes.git    

cd hermes
git checkout master

git pull
```

Get php extensions and the vendors and post-install the project

```
sudo apt install phpversion-curl
sudo apt install phpversion-gd
sudo apt install phpversion-dom
sudo apt install phpversion-zip
sudo apt install phpversion-sqlite3
sudo apt install phpversion-mbstring
sudo apt install phpversion-intl

where phpversion = php8.4

composer install
```

Install assets (Importmap : Uppy à la place d’elFinder, Bootstrap, CKEditor 5, Tom Select, etc.)

```
php bin/console importmap:install
```

Pour la liste complète des `importmap:require` (Uppy à la place d’elFinder, CKEditor 5, Tom Select, polices @fontsource, etc.), voir la section **Asset Mapper (importmap) — sans elFinder** plus haut.

For the full `importmap:require` list (Uppy instead of elFinder, CKEditor 5, Tom Select, @fontsource fonts, etc.), see the **Asset Mapper (importmap)** section above.

Start Server on a terminal

```
symfony server:start
or
cd ~/public_html
php -S 127.0.0.1:8000
```

Admin interface

```
http://127.0.0.1:8000/fr/admin/
Admin User :
Login : set up value in in .env (APP_HERMES_EMAIL_ADMIN="contact@hermes-cms.org")
Password : mycmsishermes
```

# Install : Plateform != Linux

Hermes est un CMS qui devrait fonctionner sur toutes les plateformes.
Néanmois, il n'existe pas de documentation pour les autres plateformes que linux.
Contributeurs bienvenus : [contact@hermes-cms.org](mailto:contact@hermes-cms.org)