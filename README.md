Hermes - CMS
==================

Introduction -FR
----------------

Hermes (V3) est un CMS basé sur Symfony8, Bootstrap5 et les standards du Web.
Il fournit une interface d'administration afin de créer des contenus riche pour votre site Web.
Il fournit une interface d'administration pour configurer les couleurs, largeur...des différentes partie de votre site Web.
Il fournit quelques templates de type folios, carousels, cards ainsi qu'une saisie "libre" qui utilise CKEditor5 afin de créer de belles pages responsive.

Introduction -EN
----------------
Hermes (V3) is a CMS  based on Symfony8 and Bootstrap5 and the standards of Web.
It provides an admin to create a complete web site.
It provides configuration to select the color, background-color, width...for the different parts of your Web site (Menu, Content, Footer)
It provides some templates like folios, carousels, cards or "free presentation" using the [FOSCKEditorBundle](https://symfony.com/doc/master/bundles/FOSCKEditorBundle/index.html) to create nice and responsive pages.


Documentation
-------------

Création :
symfony new hermes3
cd hermes3
composer install
composer require symfony/asset-mapper symfony/asset symfony/twig-packs
composer require symfony/routing
composer require symfony/orm-pack
composer require --dev symfony/maker-bundle
composer require helios-ag/fm-elfinder-bundle
composer require liip/imagine-bundle
composer require stof/doctrine-extensions-bundle
composer require stof/doctrine-extensions-bundle
composer require symfony/form
composer require symfony/http-client
composer require symfony/mailer
composer require symfony/monolog-bundle
composer require symfony/security-bundle
composer require symfony/stimulus-bundle
composer require symfony/translation
composer require symfony/ux-icon
composer require symfony/ux-twig-component
composer require symfony/validator
composer require twig/inky-extra
composer require twig/intl-extra
composer require twig/string-extra
composer require vich/uploader-bundle
composer require twig/string-extra
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
composer require --dev web-profiler-bundle
<!-- composer require --dev symfony/panther -->
<!-- composer require symfonycasts/verify-email-bundle -->

php bin/console importmap:require bootstrap
php bin/console importmap:require bootstrap/dist/css/bootstrap.min.css
php bin/console importmap:require @fortawesome/fontawesome-free/css/all.css

Install AOS
php bin/console importmap:require aos
php bin/console importmap:require aos/dist/aos.css

Install Ckeditor
php bin/console importmap:require ckeditor5
php bin/console importmap:require ckeditor5/dist/ckeditor5.min.css
php bin/console importmap:require ckeditor5/translations/fr.js

Télécharger les assets dans `assets/vendor/` (dossier non versionné ; à lancer après un `git clone` ou si un paquet manque) :
php bin/console importmap:install

In progress.
    - require PHP8.4

Show Room et modeles - FR
------------------------

   - Vous pouvez acceder au show-room de nos principaux templates : [modeles](http://modeles.atlas-services.fr)
   - Vous pouvez aussi voir quelques modèles de sites : 
       - [modele1](http://modele1.atlas-services.fr)
       - [modele2](http://modele2.atlas-services.fr)
       - [modele3](http://modele3.atlas-services.fr)
       - [modele4](http://modele4.atlas-services.fr)
  
 Show Room and modeles -EN
 -------------------------      
   - You can see a show-room of our templates : [modeles](http://modeles.atlas-services.fr)
   - You can see some modeles : 
       - [modele1](http://modele1.atlas-services.fr)
       - [modele2](http://modele2.atlas-services.fr)
       - [modele3](http://modele3.atlas-services.fr)
       - [modele4](http://modele4.atlas-services.fr)

License
-------

This CMS is released under the MIT license. See the included
[LICENSE](LICENSE) file for more information.

Contribuer - FR
---------------

Contributeurs bienvenus! Hermes est un logiciel libre. Si vous souhaitez contribuer, n'hésitez pas à proposer une PR! Vous pouvez lire le fichier [CONTRIBUTING](/CONTRIBUTING.md) qui vous indiquera quelques directions de contributions .

Contribute - EN
---------------
We love contributors! Hermes is an free software. If you'd like to contribute, feel free to propose a PR! You
can follow the [CONTRIBUTING](/CONTRIBUTING.md) file which will explain you some needs about contributing.


Install : Plateform Linux
====================================

Get the Repository

    cd /var/www/html
    git clone git@github.com:atlas-services/hermes.git
    or
    git clone https://github.com/atlas-services/hermes.git    

    cd hermes
    git checkout master

    git pull

Get php extensions and the vendors and post-install the project

    sudo apt install phpversion-curl
    sudo apt install phpversion-gd
    sudo apt install phpversion-dom
    sudo apt install phpversion-zip
    sudo apt install phpversion-sqlite3
    sudo apt install phpversion-mbstring
    sudo apt install phpversion-intl

    where phpversion = php8.4

    composer install

Install assets (Importmap : Bootstrap, Font Awesome, AOS, CKEditor, etc.)
php bin/console importmap:install

Si un paquet n’est pas encore déclaré dans `importmap.php` :
php bin/console importmap:require bootstrap
php bin/console importmap:require bootstrap/dist/css/bootstrap.min.css
php bin/console importmap:require @fortawesome/fontawesome-free/css/all.css
php bin/console importmap:require aos
php bin/console importmap:require aos/dist/aos.css
php bin/console importmap:require ckeditor5
php bin/console importmap:require ckeditor5/dist/ckeditor5.min.css
php bin/console importmap:require ckeditor5/translations/fr.js

Start Server on a terminal

    symfony server:start
    or
    cd ~/public_html
    php -S 127.0.0.1:8000

Admin interface

    http://127.0.0.1:8000/fr/admin/
    Admin User :
    Login : set up value in in .env (APP_HERMES_EMAIL_ADMIN="contact@hermes-cms.org")
    Password : mycmsishermes

    Install : Plateform != Linux
====================================

Hermes est un CMS qui devrait fonctionner sur toutes les plateformes.
Néanmois, il n'existe pas de documentation pour les autres plateformes que linux.
Contributeurs bienvenus : contact@hermes-cms.org
