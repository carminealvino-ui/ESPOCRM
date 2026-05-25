#!/usr/bin/env bash
# ========================================
# VERSIONE: 1.0.0
# DATA: 2026-05-25
# Sposta export custom-export-* da tools/custom-exports/ a exports/custom/
# Eseguire dalla root EspoCRM: bash tools/migrate-custom-exports-folder.sh
# ========================================

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OLD="${ROOT}/tools/custom-exports"
NEW="${ROOT}/exports/custom"

if [ ! -d "$OLD" ]; then
  echo "Nessuna cartella vecchia: ${OLD}"
  exit 0
fi

mkdir -p "$NEW"

moved=0
skipped=0

shopt -s nullglob
for f in "${OLD}"/custom-export-*.zip "${OLD}"/custom-export-*.json; do
  base=$(basename "$f")
  dest="${NEW}/${base}"

  if [ -f "$dest" ]; then
    echo "Saltato (già in exports/custom): ${base}"
    skipped=$((skipped + 1))
    continue
  fi

  mv "$f" "$dest"
  echo "Spostato: ${base}"
  moved=$((moved + 1))
done

echo ""
echo "Completato. Spostati: ${moved}, saltati: ${skipped}"
echo "Nuova cartella export: ${NEW}"
