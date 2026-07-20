#!/usr/bin/env bash
# Fix nome Appuntamento sparito in calendario (formula API azzerava name).
#
#   cd ~/public_html/crm/mec-group
#   curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/COMMIT/tools/deploy-fix-appuntamento-nome-sparito.sh" \
#     -o tools/deploy-fix-appuntamento-nome-sparito.sh
#   bash tools/deploy-fix-appuntamento-nome-sparito.sh

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
COMMIT="${DEPLOY_COMMIT:-4367161bfe624d3943c632af0169bfc647993bea}"
REPO="carminealvino-ui/ESPOCRM"
BASE="https://raw.githubusercontent.com/${REPO}/${COMMIT}"
FIX_TAG="fix-appuntamento-nome-sparito"
STAMP=$(date +%Y%m%d-%H%M%S)

cd "${CRM_ROOT}"

echo "=== Deploy fix nome Appuntamento (calendario) ==="
echo "COMMIT=${COMMIT}"

FILES=(
  "custom/Espo/Custom/Services/AppuntamentoNameBuilder.php"
  "custom/Espo/Custom/Hooks/Appuntamento/GlobalLogic.php"
  "custom/Espo/Custom/Resources/metadata/formula/Appuntamento.json"
  "tools/bonifica-appuntamento-nome.php"
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

if ! grep -q "VERSIONE: 1.7.16" custom/Espo/Custom/Hooks/Appuntamento/GlobalLogic.php; then
  echo "ERRORE: GlobalLogic Appuntamento non è 1.7.16" >&2
  exit 1
fi

if ! grep -q 'beforeSaveApiScript": ""' custom/Espo/Custom/Resources/metadata/formula/Appuntamento.json; then
  echo "ERRORE: formula beforeSaveApiScript non disabilitata" >&2
  exit 1
fi

echo "=== Cache ==="
rm -rf data/cache/* 2>/dev/null || true
php clear_cache.php || true
php rebuild.php

echo "=== 1) Dry-run campione ==="
php tools/bonifica-appuntamento-nome.php --dry-run panfili || true
set +o pipefail
php tools/bonifica-appuntamento-nome.php --dry-run 2>/dev/null | head -n 15 || true
set -o pipefail

echo ""
echo "=== 2) Bonifica nomi vuoti ==="
php tools/bonifica-appuntamento-nome.php

echo ""
echo "=== Fine ==="
echo "HookVersion Appuntamento → 1.7.16"
echo "Calendario: blocchi con CAP - Cliente (Brand - Categoria), non solo orario."
