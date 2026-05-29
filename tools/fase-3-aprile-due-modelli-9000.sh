#!/usr/bin/env bash
# =============================================================================
# DEPRECATO — Listino aprile (e marzo) NON sono più attivi in produzione.
# Non eseguire sync su 69ce7c1fa73049580 / 69d4c2dce710dc14b.
#
# Per Falcon usare solo:
#   tools/fase-3-sync-listino-ariel-070526.sh  → listino 07/05/2026
#   tools/fase-3d-chiudi-mono-9000-dopo-aprile.sh
# =============================================================================
echo "ATTENZIONE: i listini ARIEL Marzo e Aprile 2026 non sono più attivi." >&2
echo "Non serve popolarli. Listino di riferimento: 07ce1b326cd314ca2 (07/05/2026)." >&2
echo "Vedi database/2026-05-27-falcon-plus-vigore-listini.sql" >&2
exit 1
