#!/usr/bin/env bash
# Ripristina layout elenco Appuntamento (default MEC pre-modifiche larghezze/CSS).
# Sempre con backup prima.
#
#   cd ~/public_html/crm/mec-group
#   bash tools/ripristina-layout-appuntamento-list-default.sh
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-cursor/appuntamento-produzione-fruibile-9999}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"
REL="custom/Espo/Custom/Resources/layouts/Appuntamento/list.json"
DEST="${CRM_ROOT}/${REL}"

cd "${CRM_ROOT}" || exit 1

mkdir -p tools custom/backup-layouts
curl -fsSL "${BASE}/tools/backup-produzione.sh?t=$(date +%s)" -o tools/backup-produzione.sh 2>/dev/null || true
chmod +x tools/backup-produzione.sh 2>/dev/null || true

echo "=== 1/3 Backup layout attuale ==="
if [[ -f "${DEST}" ]]; then
  STAMP="$(date +%Y%m%d-%H%M%S)"
  BK="${CRM_ROOT}/custom/backup-layouts/${STAMP}"
  mkdir -p "${BK}/$(dirname "${REL}")"
  cp -a "${DEST}" "${BK}/${REL}"
  echo "backup=${BK}/${REL}"
else
  echo "SKIP (file assente)"
fi

echo ""
echo "=== 2/3 Ripristino list.json default ==="
mkdir -p "$(dirname "${DEST}")"
curl -fsSL "${BASE}/${REL}?t=$(date +%s)" -o "${DEST}"
echo "OK ${REL}"

echo ""
echo "=== 3/3 Cache (aggiorna anche custom-ui.css se modificato) ==="
curl -fsSL "${BASE}/client/custom/css/custom-ui.css?t=$(date +%s)" \
  -o "${CRM_ROOT}/client/custom/css/custom-ui.css" 2>/dev/null || true

php command.php clearCache 2>/dev/null || true
rm -rf data/cache/* 2>/dev/null || true

echo ""
echo "Fatto. Ctrl+F5 su elenco Appuntamenti."
echo "Colonne default: Data, Nome, Relazionato a, Città, CAP, Fornitore, Brand, Categoria, Stato, Sottostato, Opportunità (senza Esito)."
