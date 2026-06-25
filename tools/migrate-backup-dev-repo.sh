#!/usr/bin/env bash
# Migra backup_dev (file flat → per entità). Eseguire dalla root repo.
set -euo pipefail
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
export CRM_ROOT="${REPO_ROOT}"
bash "${REPO_ROOT}/backup_dev/_scripts/migra-struttura-server.sh"
