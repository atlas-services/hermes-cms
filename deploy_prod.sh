#!/usr/bin/env bash
# Déploiement production Hermes3 (dépendances, assets, droits fichiers).
# Usage : sudo HTTP_USER=www-data HTTP_GROUP=www-data ./deploy_prod.sh
set -euo pipefail
cd "$(dirname "$0")"

HTTP_USER="${HTTP_USER:-www-data}"
HTTP_GROUP="${HTTP_GROUP:-www-data}"

composer install --no-dev --optimize-autoloader
composer run deploy-assets

_ensure_dir() {
    if [[ ! -d "$1" ]]; then
        mkdir -p "$1"
    fi
}

for dir in var/cache var/log var/sessions data/db public/media/cache; do
    _ensure_dir "${dir}"
done

_run_as_root() {
    if [[ "${EUID:-$(id -u)}" -eq 0 ]]; then
        "$@"
    elif command -v sudo >/dev/null 2>&1; then
        sudo "$@"
    else
        echo "Avertissement : « $* » ignoré (root ou sudo requis)." >&2
        return 1
    fi
}

_run_as_web() {
    if [[ "${EUID:-$(id -u)}" -eq 0 ]] && command -v runuser >/dev/null 2>&1; then
        runuser -u "${HTTP_USER}" -- "$@"
    else
        sudo -u "${HTTP_USER}" "$@"
    fi
}

# Cache prod souvent possédé par PHP-FPM : impossible à chmod depuis l’utilisateur de déploiement
if [[ -d var/cache/prod ]]; then
    _run_as_root rm -rf var/cache/prod
fi

# public/uploads/<APP_NAME> : créé par composer create-upload-dir (APP_BASE_MEDIA_DATA)
UPLOAD_MEDIA_DIR="public/uploads"
if [[ -f .env ]]; then
    UPLOAD_MEDIA_DIR=$(php -r 'require "vendor/autoload.php"; (new Symfony\Component\Dotenv\Dotenv())->bootEnv(getcwd() . "/.env"); $b = $_ENV["APP_BASE_MEDIA_DATA"] ?? "uploads/app"; echo "public/" . trim(str_replace("\\", "/", $b), "/");')
fi

WRITABLE_DIRS=(var data public/media/cache)
if [[ -d "${UPLOAD_MEDIA_DIR}" ]]; then
    WRITABLE_DIRS+=("${UPLOAD_MEDIA_DIR}")
fi

for dir in "${WRITABLE_DIRS[@]}"; do
    _run_as_root chown -R "${HTTP_USER}:${HTTP_GROUP}" "${dir}"
    _run_as_root chmod -R ug+rwx "${dir}"
    if [[ "${dir}" != var ]]; then
        _run_as_root find "${dir}" -type d -exec chmod g+s {} + 2>/dev/null || true
    fi
done

for dir in public/assets public/bundles; do
    if [[ -d "${dir}" ]]; then
        _run_as_root chown -R "${HTTP_USER}:${HTTP_GROUP}" "${dir}"
        _run_as_root chmod -R a+rX "${dir}"
    fi
done

# Recréer le cache prod avec le propriétaire web (évite var/cache/prod/twig en root ou deploy)
_run_as_web php bin/console cache:warmup --env=prod

echo "Déploiement terminé (propriétaire ${HTTP_USER}:${HTTP_GROUP})."
