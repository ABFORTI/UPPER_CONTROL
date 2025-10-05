#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

FROM=${1:-}
if [[ -z "${FROM}" ]]; then
  read -r -p "Nombre de la rama a mezclar (desde): " FROM
fi

current=$(git rev-parse --abbrev-ref HEAD)
echo "-> Actualizando rama actual '${current}'..."
git pull --rebase --autostash origin "${current}"

echo "-> Obteniendo últimos cambios de '${FROM}'..."
git fetch origin "${FROM}"

echo "-> Haciendo merge de 'origin/${FROM}' en '${current}'..."
git merge --no-ff "origin/${FROM}"

echo "-> Empujando cambios..."
git push origin "${current}"

echo "Listo ✅"
