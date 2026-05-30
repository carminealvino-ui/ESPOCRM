#!/usr/bin/env bash
# Ripristina elenco Appuntamenti come “immagine 1”:
# - colonne: Data, Nome, CAP, Fornitore, Brand, Categoria, Stato, Sottostato, Esito, Opportunità
# - senza Relazionato a / Indirizzo città
# - Opportunità ~49% + CSS senza troncamento
# Sempre backup prima.
#
#   cd ~/public_html/crm/mec-group
#   bash tools/ripristina-layout-appuntamento-immagine1.sh
set -euo pipefail

CRM_ROOT="${CRM_ROOT:-$HOME/public_html/crm/mec-group}"
BRANCH="${BRANCH:-cursor/appuntamento-produzione-fruibile-9999}"
BASE="https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/${BRANCH}"

cd "${CRM_ROOT}" || exit 1

mkdir -p tools
curl -fsSL "${BASE}/tools/backup-produzione.sh?t=$(date +%s)" -o tools/backup-produzione.sh 2>/dev/null || true
chmod +x tools/backup-produzione.sh 2>/dev/null || true

echo "=== 1/4 Backup ==="
bash tools/backup-produzione.sh prima-ripristino-immagine1 2>/dev/null | tail -3 || true

FILES=(
  "custom/Espo/Custom/Resources/layouts/Appuntamento/list.json"
  "client/custom/css/custom-ui.css"
  "client/custom/src/views/appuntamento/record/list.js"
  "custom/Espo/Custom/Resources/metadata/clientDefs/Appuntamento.json"
)

echo ""
echo "=== 2/4 Ripristino file layout immagine 1 ==="
for rel in "${FILES[@]}"; do
  dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  curl -fsSL "${BASE}/${rel}?t=$(date +%s)" -o "${dest}"
  echo "OK ${rel}"
done

echo ""
echo "=== 3/4 Rebuild ==="
php command.php rebuild
php command.php clearCache 2>/dev/null || true
rm -rf data/cache/*

echo ""
echo "=== 4/4 Fatto ==="
echo "Ctrl+F5 su Appuntamenti."
