#!/usr/bin/env bash
# Premier déploiement Hermes3 : admin + gabarits/config par défaut.
# À lancer une seule fois après composer install / deploy.sh (`.env` requis).
# Usage : ./first-deploy.sh   (ou : bash first-deploy.sh)
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

for dir in var/cache var/log var/sessions data/db; do
    _ensure_dir "${dir}"
done

composer run create-db-file

symfony console app:create-user
symfony console app:init-hermes

echo "Premier déploiement terminé (base SQLite, utilisateur admin, init Hermes)."
