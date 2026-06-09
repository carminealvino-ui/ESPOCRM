# Allineamento server ↔ repo — Google Calendar Appuntamento (9 giugno 2026)

**Stato:** fix mergeato su `main` (commit `aaa39f7` e precedenti).

---

## Cosa è su `main` ora

| Area | File chiave |
|------|-------------|
| Sync custom | `custom/Espo/Custom/Services/AppuntamentoGoogleSync.php` |
| Hook Appuntamento | `GoogleCalendarSync*.php`, `PreventDuplicate.php` v1.0.6 |
| Fix ghost | `custom/Espo/Modules/Google/Hooks/Common/GoogleCalendar.php` |
| Bonifica | `tools/bonifica-appuntamento-google-calendar.php` (v1.6.1) |
| Deploy | `tools/deploy-fix-appuntamento-google-sync.sh` |

---

## Passo 1 — Verifica allineamento (server)

```bash
cd ~/public_html/crm/mec-group
curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/main/tools/bootstrap-server-tools.sh?t=$(date +%s)" | bash
php tools/sync-custom-prod-repo.php clear-cache --branch=main
php tools/sync-custom-prod-repo.php status --branch=main --refresh-cache
```

**Interpretazione:**

| Esito | Significato |
|-------|-------------|
| `File indicizzati repo` < 100 | Cache GitHub incompleta → usare `clear-cache` + `--refresh-cache` |
| `Solo prod` ~2000+ con `advanced/google/outlook` | Normale: moduli estensione in prod, esclusi dal sync |
| `Identici` alto, `Solo prod` basso | Allineamento OK per custom MEC |

**Atteso dopo deploy corretto:** `Identici` nell’ordine delle centinaia, pochi **Diversi** / **Solo prod** (solo custom MEC).

---

## Passo 2 — Deploy da `main` (repo → produzione)

Se `status` mostra file **Solo in repo** per Google sync:

```bash
cd ~/public_html/crm/mec-group
curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/main/tools/deploy-fix-appuntamento-google-sync.sh?t=$(date +%s)" | bash
```

Poi bonifica (un comando per riga):

```bash
php tools/bonifica-appuntamento-google-calendar.php --apply --only-purge-ghosts --user-id=67c93e694705fde80
php tools/bonifica-appuntamento-google-calendar.php --apply --backfill-sync-flag --user-id=67c93e694705fde80
php tools/bonifica-appuntamento-google-calendar.php --apply --verbose --user-id=67c93e694705fde80
```

---

## Passo 3 — Export produzione → GitHub (se restano differenze)

Solo se `status` mostra ancora **Solo prod** o **Diversi** su file custom:

```bash
cd ~/public_html/crm/mec-group
php tools/sync-custom-prod-repo.php export-delta --branch=main
```

Poi sul clone Git (`~/ESPOCRM-git`):

```bash
cd ~/ESPOCRM-git
git pull origin main
bash ~/public_html/crm/mec-group/tools/sync-apply-delta-cpanel.sh delta-YYYYMMDD-HHMMSS
bash ~/public_html/crm/mec-group/tools/sync-push-github-cpanel.sh "sync: allineamento google sync $(date +%Y-%m-%d)"
```

---

## Passo 4 — Verifica finale

```bash
cd ~/public_html/crm/mec-group
php tools/sync-custom-prod-repo.php status --branch=main
php clear_cache.php && php rebuild.php
```

Ctrl+F5 in Espo + controllo Google Calendar Carmine.

---

## Non fare

- Non sovrascrivere tutto `custom/` con ZIP generici.
- Non riusare delta export vecchi senza rifare `export-delta`.
- Non disinstallare il modulo Google senza bonifica completa.

Vedi anche: [`05-SYNC-REPO-DAL-SERVER.md`](05-SYNC-REPO-DAL-SERVER.md), [`08-AVVIO-SYNC-CPANEL.md`](08-AVVIO-SYNC-CPANEL.md).
