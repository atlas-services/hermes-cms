#!/usr/bin/env bash

set -euo pipefail
cd "$(dirname "$0")"

HTTP_USER="${HTTP_USER:-www-data}"
HTTP_GROUP="${HTTP_GROUP:-www-data}"

composer install --no-dev --optimize-autoloader
composer run deploy-assets

# Répertoires inscriptibles : création uniquement s’ils n’existent pas encore
_ensure_dir() {
    if [[ ! -d "$1" ]]; then
        mkdir -p "$1"
    fi
}

for dir in var/cache var/log var/sessions data/db public/uploads public/media/cache; do
    _ensure_dir "${dir}"
done

_run_chown() {
    if [[ "${EUID:-$(id -u)}" -eq 0 ]]; then
        chown -R "${HTTP_USER}:${HTTP_GROUP}" "$@"
    elif command -v sudo >/dev/null 2>&1; then
        sudo chown -R "${HTTP_USER}:${HTTP_GROUP}" "$@"
    else
        echo "Avertissement : chown ignoré (lancez le script en root ou avec sudo)." >&2
        return 0
    fi
}

# Cache, logs, sessions, base SQLite, uploads Vich/Uppy, cache miniatures
for dir in var data public/uploads public/media/cache; do
    _run_chown "${dir}"
    chmod -R ug+rwx "${dir}"
    find "${dir}" -type d -exec chmod g+s {} + 2>/dev/null || true
done

# Assets compilés (asset-map:compile) et bundles Symfony : lecture par le serveur web
for dir in public/assets public/bundles; do
    if [[ -d "${dir}" ]]; then
        _run_chown "${dir}"
        chmod -R a+rX "${dir}"
    fi
done

echo "Déploiement terminé (propriétaire ${HTTP_USER}:${HTTP_GROUP})."
