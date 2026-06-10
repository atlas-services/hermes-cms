#!/usr/bin/env bash
# Déploiement Hermes3 : dépendances, base, schéma, assets, cache, admin, init métier.
# Usage : ./deploy.sh [dev|prod|test]
#         APP_ENV=dev ./deploy.sh
# Défaut : prod
# Prérequis : .env / .env.local (APP_DB, ADMIN_EMAIL, ADMIN_PASSWORD, APP_NAME, …).
if [ -z "${BASH_VERSION:-}" ]; then
    exec /usr/bin/env bash "$0" "$@"
fi

set -euo pipefail
cd "$(dirname "$0")"

HERMES_ENV="${1:-${APP_ENV:-prod}}"

usage() {
    echo "Usage: $0 [dev|prod|test]" >&2
    echo "       APP_ENV=dev $0" >&2
    exit 1
}

case "${HERMES_ENV}" in
    dev|prod|test) ;;
    *) usage ;;
esac

export APP_ENV="${HERMES_ENV}"
case "${HERMES_ENV}" in
    prod) export APP_DEBUG=0 ;;
    dev|test) export APP_DEBUG=1 ;;
esac

# prod + --no-dev : pas de MakerBundle ; éviter un cache dev/test obsolète.
if [[ "${HERMES_ENV}" == "prod" ]]; then
    rm -rf var/cache/dev var/cache/test 2>/dev/null || true
fi

_ensure_dir() {
    if [[ ! -d "$1" ]]; then
        mkdir -p "$1"
    fi
}

for dir in var/cache var/log var/sessions data/db public/bundles public/uploads; do
    _ensure_dir "${dir}"
done

console() {
    symfony console --env="${HERMES_ENV}" --no-interaction "$@"
}

# --no-scripts : les commandes Symfony tournent avec APP_ENV ci-dessus (pas via .env.local).
# prod : sans dépendances dev (MakerBundle, PHPUnit, …).
if [[ "${HERMES_ENV}" == "prod" ]]; then
    composer install --no-dev --optimize-autoloader --no-scripts
else
    composer install --optimize-autoloader --no-scripts
fi

composer run deploy-assets
console assets:install public

composer run create-db-file
composer run create-upload-dir

console doctrine:schema:update --force
console cache:clear
chmod -Rf 777 var/cache/ var/log/ var/sessions/ data/db/ public/bundles public/uploads

console app:create-user
console app:init-hermes

echo "Déploiement terminé (env=${HERMES_ENV})."
