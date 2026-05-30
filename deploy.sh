#!/usr/bin/env bash
# Déploiement Hermes3 (dépendances, assets, cache, droits).
# Usage : ./deploy.sh   (ou : bash deploy.sh)
if [ -z "${BASH_VERSION:-}" ]; then
    exec /usr/bin/env bash "$0" "$@"
fi

set -euo pipefail
cd "$(dirname "$0")"

_ensure_dir() {
    if [[ ! -d "$1" ]]; then
        mkdir -p "$1"
    fi
}

for dir in var/cache var/log var/sessions data/db public/bundles public/uploads; do
    _ensure_dir "${dir}"
done

composer install --no-dev --optimize-autoloader
composer run deploy-assets

symfony console c:c && chmod -Rf 777 var/cache/ var/log/ var/sessions/ data/db/ public/bundles public/uploads

echo "Déploiement terminé."
