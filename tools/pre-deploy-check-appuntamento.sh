#!/usr/bin/env bash
# Check pre-deploy su PRODUZIONE: blocca se entityDefs Appuntamento ha enum legacy.
#
#   bash tools/pre-deploy-check-appuntamento.sh
#   bash tools/pre-deploy-check-appuntamento.sh /path/to/crm
#
# Exit 0 = OK, exit 1 = NON deployare (produzione o repo corrotti).

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
ENTITY="${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json"
HOOKS="${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/hooks/Appuntamento.json"

if [[ ! -f "${ENTITY}" ]]; then
  echo "ERRORE: entityDefs Appuntamento mancante" >&2
  exit 1
fi

LEGACY=(
  'Fuori Target'
  'Solo Informazioni'
  'Infattibilità Tecnica'
  'Prodotto non Conforme'
)

for val in "${LEGACY[@]}"; do
  if grep -q "\"${val}\"" "${ENTITY}"; then
    echo "ERRORE PRE-DEPLOY: enum legacy \"${val}\" in entityDefs" >&2
    echo "  → NON deployare. Ripristinare da backup_dev o deploy-appuntamento-stati-esito-ui.sh" >&2
    exit 1
  fi
done

if ! grep -q 'appuntamento-esito' "${ENTITY}"; then
  echo "ERRORE PRE-DEPLOY: esito senza view custom in entityDefs" >&2
  exit 1
fi

if [[ -f "${HOOKS}" ]] && ! grep -q 'SyncStatiEsito' "${HOOKS}"; then
  echo "ERRORE PRE-DEPLOY: hook SyncStatiEsito mancante" >&2
  exit 1
fi

echo "OK pre-deploy check Appuntamento"
