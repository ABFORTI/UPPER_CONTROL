#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

BRANCH=${1:-}
if [[ -z "${BRANCH}" ]]; then
  read -r -p "Nombre de la rama a obtener (remote): " BRANCH
fi

echo "-> Obteniendo del remoto (fetch)..."
git fetch --prune origin

if git rev-parse --verify "${BRANCH}" >/dev/null 2>&1; then
  echo "-> Cambiando a la rama '${BRANCH}'..."
  git switch "${BRANCH}"
else
  echo "-> Creando rama local '${BRANCH}' rastreando origin/${BRANCH}..."
  git switch -c "${BRANCH}" --track "origin/${BRANCH}"
fi

echo "-> Actualizando '${BRANCH}' con rebase..."
git pull --rebase --autostash origin "${BRANCH}"

echo "Listo ✅"
