#!/usr/bin/env bash
# Migra backup/hooks_cleanup nel repo Git (flat → per entità). Eseguire dalla root repo.
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
export CRM_ROOT="${REPO_ROOT}"
bash "${REPO_ROOT}/backup/hooks_cleanup/_scripts/migra-struttura-server.sh"

# Rinomina file *-stabile con nomi più corti (opzionale, solo se ancora con prefisso backup-)
rename_stabile() {
  local dir="$1"
  [[ -d "${dir}" ]] || return 0
  find "${dir}" -maxdepth 3 -type f -name 'backup-*' | while read -r f; do
    local base
    base="$(basename "${f}")"
    local new="${base#backup-}"
    local dest_dir
    dest_dir="$(dirname "${f}")"
    if [[ "${base}" != "${new}" ]] && [[ ! -f "${dest_dir}/${new}" ]]; then
      mv "${f}" "${dest_dir}/${new}"
      echo "  rename ${base} → ${new}"
    fi
  done
}

cd "${REPO_ROOT}/backup/hooks_cleanup"
echo "=== Rinomina prefisso backup- (repo) ==="
for entity in Appuntamento Opportunity Prospect; do
  rename_stabile "${entity}"
done

echo "OK repo migrato."
