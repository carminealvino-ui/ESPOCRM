#!/usr/bin/env bash
# Genera/collega Appuntamento per Opportunità senza appuntamento_id.
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/COMMIT/tools/deploy-genera-appuntamento-da-opportunita.sh" \
#     -o tools/deploy-genera-appuntamento-da-opportunita.sh
#   bash tools/deploy-genera-appuntamento-da-opportunita.sh

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
COMMIT="${DEPLOY_COMMIT:-HEAD}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${COMMIT}"
FIX_TAG="genera-appuntamento-da-opportunita"
STAMP=$(date +%Y%m%d-%H%M%S)

cd "${CRM_ROOT}"

echo "=== Deploy genera Appuntamento da Opportunità ==="
echo "COMMIT=${COMMIT}"

FILES=(
  "custom/Espo/Custom/Services/OpportunityAppuntamentoGenerator.php"
  "custom/Espo/Custom/Services/AppuntamentoNameBuilder.php"
  "tools/genera-appuntamento-da-opportunita.php"
)

mkdir -p tools/backup-manifests
printf '%s\n' "${FILES[@]}" > "tools/backup-manifests/${FIX_TAG}.files"

LOCAL_BACKUP="${CRM_ROOT}/backup/${FIX_TAG}/server-${STAMP}"
mkdir -p "${LOCAL_BACKUP}"

for rel in "${FILES[@]}"; do
  src="${CRM_ROOT}/${rel}"
  if [[ -f "${src}" ]]; then
    mkdir -p "${LOCAL_BACKUP}/$(dirname "${rel}")"
    cp -a "${src}" "${LOCAL_BACKUP}/${rel}"
    echo "BACKUP ${rel}"
  else
    echo "SKIP backup (nuovo) ${rel}"
  fi
done

echo "=== Download ==="
for rel in "${FILES[@]}"; do
  dest="${CRM_ROOT}/${rel}"
  mkdir -p "$(dirname "${dest}")"
  curl -fsSL "${BASE}/${rel}?t=${STAMP}" -o "${dest}"
  echo "OK ${rel}"
done

if ! grep -q "class OpportunityAppuntamentoGenerator" custom/Espo/Custom/Services/OpportunityAppuntamentoGenerator.php; then
  echo "ERRORE: OpportunityAppuntamentoGenerator mancante" >&2
  exit 1
fi

echo "=== Cache ==="
rm -rf data/cache/* 2>/dev/null || true
php clear_cache.php || true
php rebuild.php

echo ""
echo "=== 1) Dry-run link-only (campione) ==="
php tools/genera-appuntamento-da-opportunita.php --dry-run --link-only --limit=10 || true

echo ""
echo "=== 2) Dry-run completo (campione 20) ==="
php tools/genera-appuntamento-da-opportunita.php --dry-run --limit=20 || true

echo ""
echo "=== Fine deploy ==="
echo "Esegui in produzione:"
echo "  php tools/genera-appuntamento-da-opportunita.php --dry-run --link-only"
echo "  php tools/genera-appuntamento-da-opportunita.php --dry-run --limit=50"
echo "  php tools/genera-appuntamento-da-opportunita.php --limit=50"
echo "  php tools/genera-appuntamento-da-opportunita.php"
