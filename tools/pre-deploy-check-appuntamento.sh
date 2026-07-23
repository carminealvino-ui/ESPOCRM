#!/usr/bin/env bash
# Check pre-deploy su PRODUZIONE: baseline Appuntamento (stati/esito + Ingestibile).
#
#   bash tools/pre-deploy-check-appuntamento.sh
#   bash tools/pre-deploy-check-appuntamento.sh /path/to/crm
#
# Exit 0 = OK, exit 1 = NON deployare.

set -euo pipefail

CRM_ROOT="${1:-${CRM_ROOT:-$HOME/public_html/crm/mec-group}}"
ENTITY="${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/entityDefs/Appuntamento.json"
HOOKS="${CRM_ROOT}/custom/Espo/Custom/Resources/metadata/hooks/Appuntamento.json"
MAP="${CRM_ROOT}/client/custom/src/helpers/appuntamento-sottostato-map.js"
RULES="${CRM_ROOT}/custom/Espo/Custom/Services/AppuntamentoStatiRules.php"

if [[ ! -f "${ENTITY}" ]]; then
  echo "ERRORE: entityDefs Appuntamento mancante" >&2
  exit 1
fi

if grep -q '"Non Confermato"' "${ENTITY}"; then
  echo "ERRORE PRE-DEPLOY: sottostato fuori modello \"Non Confermato\"" >&2
  exit 1
fi

for val in 'Infattibilità Tecnica' 'Solo Informazioni' 'Prodotto non Conforme' 'Fuori Target'; do
  if ! grep -q "\"${val}\"" "${ENTITY}"; then
    echo "ERRORE PRE-DEPLOY: manca sottostato Ingestibile \"${val}\" in entityDefs" >&2
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

if [[ -f "${MAP}" ]] && grep -qE "Ingestibile:[[:space:]]*\\[[[:space:]]*\\]" "${MAP}"; then
  echo "ERRORE PRE-DEPLOY: map JS con Ingestibile sottostati vuoti" >&2
  exit 1
fi

if [[ -f "${RULES}" ]] && ! grep -q 'Infattibilità Tecnica' "${RULES}"; then
  echo "ERRORE PRE-DEPLOY: AppuntamentoStatiRules senza sottostati Ingestibile" >&2
  exit 1
fi

echo "OK pre-deploy check Appuntamento"
