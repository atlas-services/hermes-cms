#!/usr/bin/env bash
# Déploiement Hermes3 (dépendances, assets, cache, droits).
# Usage : ./deploy.sh   (ou : bash deploy.sh)
if [ -z "${BASH_VERSION:-}" ]; then
    exec /usr/bin/env bash "$0" "$@"
fi

set -euo pipefail
cd "$(dirname "$0")"

composer install --no-dev --optimize-autoloader
composer run deploy-assets

_ensure_dir() {
    if [[ ! -d "$1" ]]; then
        mkdir -p "$1"
    fi
}

for dir in var/cache var/log data/db public/bundles public/uploads; do
    _ensure_dir "${dir}"
done

symfony console c:c && chmod -Rf 777 var/cache/ var/log/ data/db/ public/bundles public/uploads

echo "Déploiement terminé."
