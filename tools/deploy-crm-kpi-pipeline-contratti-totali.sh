#!/usr/bin/env bash
# Deploy mirato: pipeline KPI schiacciata + Contr. totali / % su app. totali.
# NON tocca Quote.json.
set -euo pipefail
ROOT="${1:-$HOME/public_html/crm/mec-group}"
BRANCH="cursor/crm-kpi-pipeline-contratti-totali-9999"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"
TMPDIR="${TMPDIR:-/tmp}/espo-deploy-kpi-pipeline-$$"

cd "$ROOT"
mkdir -p "$TMPDIR"
trap 'rm -rf "$TMPDIR"' EXIT

download_one() {
  local rel="$1"
  local tmp="$TMPDIR/$(basename "$rel")"
  local dest="$ROOT/$rel"
  echo ">> $rel"
  mkdir -p "$(dirname "$dest")"
  curl -fsSL "${BASE}/${rel}" -o "$tmp"
  cp -f "$tmp" "$dest"
  echo "   OK"
}

download_one "client/custom/css/crm-kpi-dashlet.css"
download_one "client/custom/src/views/dashlets/crm-kpi.js"
download_one "client/custom/res/templates/dashlets/crm-kpi.tpl"
download_one "custom/Espo/Custom/Tools/CrmKpi/FunnelBuilder.php"

php clear_cache.php
rm -rf data/cache
mkdir -p data/cache
php rebuild.php

echo
echo "Deploy OK. Hard refresh (Ctrl+Shift+R) sulla dashboard."
