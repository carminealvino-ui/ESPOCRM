#!/usr/bin/env bash
# Classifica OGNI file in root di backup_dev → cartella entità/tipo (seconda passata).
#
#   cd ~/public_html/crm/mec-group
#   bash backup_dev/_scripts/organizza-file-legacy-root.sh
#   bash backup_dev/_scripts/organizza-file-legacy-root.sh --dry-run
#
# Aggiornare lo script da GitHub (se la root ha ancora file):
#   curl -fsSL -o backup_dev/_scripts/organizza-file-legacy-root.sh \
#     'https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/main/backup_dev/_scripts/organizza-file-legacy-root.sh'
set -euo pipefail

DRY_RUN=0
for arg in "$@"; do
  case "${arg}" in
    --dry-run|-n) DRY_RUN=1 ;;
  esac
done

CRM_ROOT="${CRM_ROOT:-$(pwd)}"
ROOT="${CRM_ROOT}/backup_dev"

if [[ ! -d "${ROOT}" ]]; then
  echo "ERRORE: ${ROOT} non trovata"
  exit 1
fi

cd "${ROOT}"

ENTITIES=(Appuntamento Opportunity Prospect Lead Quote Product ProductBrand Provvigione InvitoAFatturare FornitorePartner)
for e in "${ENTITIES[@]}"; do
  mkdir -p "${e}/hooks" "${e}/layouts" "${e}/metadata/entityDefs" "${e}/metadata/logicDefs" "${e}/metadata/clientDefs"
  mkdir -p "${e}/client/detail" "${e}/client/handlers" "${e}/client/runtime"
done
mkdir -p _archives _archives/sql _archives/client _archives/hooks-legacy client/css client/metadata _flat-legacy _misc

classify_dest() {
  local base="$1"
  local lower
  lower="$(echo "${base}" | tr '[:upper:]' '[:lower:]')"

  # Archivi compressi
  if [[ "${base}" == *.tar.gz || "${base}" == *.tgz ]]; then
    echo "_archives"
    return
  fi

  # SQL (migrazioni DB, linea, categoria)
  if [[ "${base}" == *.sql ]]; then
    echo "_archives/sql"
    return
  fi

  # CSS / UI client globale
  if [[ "${base}" == *.css || "${lower}" == *custom-ui* ]]; then
    echo "client/css"
    return
  fi

  # GlobalLogic molto vecchio (pre-split entità)
  if [[ "${lower}" == *globallogic* && "${lower}" == *old* ]]; then
    echo "_archives/hooks-legacy"
    return
  fi

  # Debug / testo generico
  if [[ "${base}" == debug.txt || "${base}" == *.log ]]; then
    echo "_misc"
    return
  fi

  # Invito a fatturare
  if [[ "${lower}" == *invito* ]]; then
    echo "InvitoAFatturare/hooks"
    return
  fi

  # Provvigione
  if [[ "${lower}" == *provvigione* ]]; then
    echo "Provvigione/hooks"
    return
  fi

  # Lead (entity JSON / linker)
  if [[ "${base}" == Lead.json ]]; then
    echo "Lead/metadata/entityDefs"
    return
  fi
  if [[ "${lower}" == *leadlinker* || "${lower}" == *lead-linker* ]]; then
    echo "Lead/hooks"
    return
  fi
  if [[ "${lower}" == *lead* && "${base}" != *opportunity* ]]; then
    if [[ "${base}" == *.json ]]; then
      echo "Lead/metadata"
      return
    fi
    echo "Lead/hooks"
    return
  fi

  # Prospect
  if [[ "${lower}" == *prospect* ]]; then
    if [[ "${base}" == *.js ]]; then
      echo "Prospect/client"
      return
    fi
    echo "Prospect/metadata"
    return
  fi

  # Product / categoria / brand (JS campi)
  if [[ "${lower}" == *product-category* || "${lower}" == *product-brand* || "${lower}" == *category-by-brand* ]]; then
    if [[ "${base}" == *.js ]]; then
      echo "Product/client"
      return
    fi
    if [[ "${base}" == *.json ]]; then
      echo "Product/metadata"
      return
    fi
  fi
  if [[ "${lower}" == *product* && "${lower}" != *opportunity* ]]; then
    echo "Product/client"
    return
  fi

  # Appuntamento (prima di opportunity se entrambi no - appuntamento esplicito)
  if [[ "${lower}" == *appuntamento* ]]; then
    if [[ "${base}" == *.json && "${base}" != *clientdefs* ]]; then
      echo "Appuntamento/metadata/entityDefs"
      return
    fi
    echo "Appuntamento/hooks"
    return
  fi

  # Create contratto / AutoCreateQuote
  if [[ "${lower}" == *create-contratto* || "${lower}" == *autocreatequote* ]]; then
    if [[ "${base}" == *.js ]]; then
      echo "Opportunity/client/handlers"
      return
    fi
    echo "Opportunity/hooks"
    return
  fi

  # Opportunity — detail JS
  if [[ "${lower}" == *opportunity* && "${lower}" == *detail* && "${base}" == *.js ]]; then
    echo "Opportunity/client/detail"
    return
  fi

  # Opportunity — clientDefs
  if [[ "${lower}" == *opportunity* && "${lower}" == *clientdefs* ]]; then
    echo "Opportunity/metadata/clientDefs"
    return
  fi

  # Opportunity — entityDefs
  if [[ "${lower}" == *opportunity* && "${lower}" == *entitydefs* ]]; then
    echo "Opportunity/metadata/entityDefs"
    return
  fi

  # Opportunity — logicDefs
  if [[ "${lower}" == *opportunity* && "${lower}" == *logicdefs* ]]; then
    echo "Opportunity/metadata/logicDefs"
    return
  fi

  # Linea prodotto / categoria (metadata opportunità)
  if [[ "${lower}" == *linea* ]]; then
    echo "Opportunity/metadata"
    return
  fi

  # Opportunity globallogic / generico opportunity
  if [[ "${lower}" == *opportunity* ]]; then
    if [[ "${base}" == *.js ]]; then
      echo "Opportunity/client/detail"
      return
    fi
    if [[ "${base}" == *.json ]]; then
      echo "Opportunity/metadata"
      return
    fi
    echo "Opportunity/hooks"
    return
  fi

  # GlobalLogic senza entità nel nome: versione 2.x → Opportunity, 1.6.x → Appuntamento
  if [[ "${lower}" == *globallogic* ]]; then
    if [[ "${lower}" == *2.0* || "${lower}" == *2.1* || "${lower}" == *2.2* ]]; then
      echo "Opportunity/hooks"
      return
    fi
    echo "Appuntamento/hooks"
    return
  fi

  # Snapshot client / app
  if [[ "${lower}" == *app-client* || "${lower}" == *custom-client* ]]; then
    if [[ "${base}" == *.json ]]; then
      echo "client/metadata"
      return
    fi
    echo "_archives/client"
    return
  fi

  # Quote
  if [[ "${lower}" == *quote* ]]; then
    echo "Quote/metadata"
    return
  fi

  echo "_flat-legacy"
}

moved=0
left=0

shopt -s nullglob
for f in *; do
  [[ -f "${f}" ]] || continue
  dest="$(classify_dest "${f}")"
  if [[ "${DRY_RUN}" -eq 1 ]]; then
    printf '  %s → %s/\n' "${f}" "${dest}"
    moved=$((moved + 1))
    continue
  fi
  mkdir -p "${dest}"
  if [[ -e "${dest}/${f}" ]]; then
    dest_file="${dest}/$(basename "${f}" .*)_dup_$(date +%H%M%S).${f##*.}"
    if [[ "${f}" == *.* ]]; then
      ext="${f##*.}"
      name="${f%.*}"
      dest_file="${dest}/${name}_dup_$(date +%H%M%S).${ext}"
    else
      dest_file="${dest}/${f}_dup_$(date +%H%M%S)"
    fi
    mv -v "${f}" "${dest_file}"
  else
    mv -v "${f}" "${dest}/"
  fi
  moved=$((moved + 1))
done
shopt -u nullglob

echo ""
if [[ "${DRY_RUN}" -eq 1 ]]; then
  echo "Anteprima: ${moved} file da spostare (nessuna modifica)."
else
  echo "Spostati: ${moved} file"
fi
echo "File rimasti in root:"
left=$(find . -maxdepth 1 -type f | wc -l)
echo "${left}"
find . -maxdepth 1 -type f 2>/dev/null | head -20
