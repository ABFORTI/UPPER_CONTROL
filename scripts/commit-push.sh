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

echo "-> Actualizando desde origin/master (rebase)..."
git pull --rebase origin master

echo "-> Enviando a origin/master..."
git push origin master

echo "Listo ✅"
