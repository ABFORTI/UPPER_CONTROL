#!/usr/bin/env bash
set -euo pipefail

# Ir a la raíz del repo (el script vive en scripts/)
cd "$(dirname "$0")/.."

MSG=${1:-}
if [[ -z "${MSG}" ]]; then
  read -r -p "Mensaje del commit: " MSG
fi

echo "-> Añadiendo cambios..."
git add -A

if ! git diff --cached --quiet; then
  echo "-> Creando commit..."
  git commit -m "${MSG}"
else
  echo "No hay cambios para commitear."
fi

branch=$(git rev-parse --abbrev-ref HEAD)
echo "-> Actualizando desde origin/${branch} (rebase)..."
git pull --rebase origin "${branch}"

echo "-> Enviando a origin/${branch}..."
git push origin "${branch}"

echo "Listo ✅"
