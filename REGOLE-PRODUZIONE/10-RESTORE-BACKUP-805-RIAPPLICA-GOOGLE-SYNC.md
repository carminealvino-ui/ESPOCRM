# Restore backup 08:05 → riapplica Google Sync Appuntamento

**Quando:** dopo restore hosting del CRM alle ~08:05 del 9 giugno 2026.  
**Obiettivo:** ripristinare solo il fix **sincronizzazione Appuntamento ↔ Google Calendar** (e bonifica), poi riallineare server ↔ GitHub.

**Non includere in questo flusso:** modifiche UI Prospect/Appuntamento (form create) — branch separato `cursor/prospect-appuntamento-form-ui-9999`.

---

## Fase 0 — Restore backup (hosting)

1. Ripristina backup **08:05** da pannello hosting (file + DB se incluso).
2. Verifica che Espo risponda (login OK).
3. Se **Internal server error** su `slim-routes.php`:

```bash
cd ~/public_html/crm/mec-group
rm -rf data/cache/*
php command.php rebuild
php -l data/cache/application/slim-routes.php
```

**Non interrompere** il rebuild (niente Ctrl+C).

---

## Fase 1 — Snapshot stato post-restore (prod → repo)

Prima di toccare codice, esporta lo stato attuale:

```bash
cd ~/public_html/crm/mec-group
curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/main/tools/bootstrap-server-tools.sh?t=$(date +%s)" | bash
php tools/sync-custom-prod-repo.php export-delta --branch=main
```

Salva il nome delta (es. `delta-20260609-080500-restore`).

Diagnosi opzionale:

```bash
php tools/sync-custom-prod-repo.php status --branch=main --refresh-cache
```

---

## Fase 2 — Deploy Google Sync da `main` (repo → prod)

Un solo comando scarica i file del fix da GitHub `main`:

```bash
cd ~/public_html/crm/mec-group
curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/main/tools/deploy-fix-appuntamento-google-sync.sh?t=$(date +%s)" | bash
```

### File deployati

| File | Ruolo |
|------|--------|
| `AppuntamentoGoogleSync.php` | Servizio sync, reconcile, purge duplicati |
| `GoogleCalendarSync*.php` | Hook push/remove su save |
| `PreventDuplicate.php` | Blocco ghost senza prospect |
| `GlobalLogic.php` | Logica appuntamento (durata, prospect) |
| `Google/Hooks/Common/GoogleCalendar.php` | No dummy su cambio assegnatario |
| `entityDefs/Appuntamento.json` | Campo `syncConGoogle` |
| `hooks/Appuntamento.json` | Registrazione hook |
| `bonifica-appuntamento-google-calendar.php` | CLI bonifica v1.7.0 |

Attendi fine **clear_cache + rebuild** (non interrompere).

---

## Fase 3 — Bonifica Google (un comando per riga)

Consulente: **Alvino Carmine** — `user-id=67c93e694705fde80`

```bash
cd ~/public_html/crm/mec-group

# 1) Ghost senza prospect
php tools/bonifica-appuntamento-google-calendar.php --dry-run --only-purge-ghosts --user-id=67c93e694705fde80
php tools/bonifica-appuntamento-google-calendar.php --apply --only-purge-ghosts --user-id=67c93e694705fde80

# 2) Flag syncConGoogle=true su appuntamenti sincronizzabili (migrazione opt-out)
php tools/bonifica-appuntamento-google-calendar.php --dry-run --backfill-sync-flag --user-id=67c93e694705fde80
php tools/bonifica-appuntamento-google-calendar.php --apply --backfill-sync-flag --user-id=67c93e694705fde80

# 3) Bonifica completa (rimuove orfani, allinea agenda)
php tools/bonifica-appuntamento-google-calendar.php --dry-run --user-id=67c93e694705fde80
php tools/bonifica-appuntamento-google-calendar.php --apply --verbose --user-id=67c93e694705fde80

# 4) Duplicati Google (stesso codice + slot) — dry-run prima
php tools/bonifica-appuntamento-google-calendar.php --dry-run --only-purge-duplicates --from-date=2026-04-20 --to-date=2026-04-27 --user-id=67c93e694705fde80
php tools/bonifica-appuntamento-google-calendar.php --apply --only-purge-duplicates --from-date=2026-04-20 --to-date=2026-04-27 --user-id=67c93e694705fde80
```

Ctrl+F5 in Espo + controllo Google Calendar.

---

## Fase 4 — Allineamento server ↔ GitHub

### 4a — Verifica

```bash
cd ~/public_html/crm/mec-group
php tools/sync-custom-prod-repo.php status --branch=main
```

### 4b — Se restano file **solo prod** o **diversi** (custom MEC)

```bash
php tools/sync-custom-prod-repo.php export-delta --branch=main
```

Sul clone Git:

```bash
cd ~/ESPOCRM-git
git pull origin main
bash ~/public_html/crm/mec-group/tools/sync-apply-delta-cpanel.sh delta-YYYYMMDD-HHMMSS
bash ~/public_html/crm/mec-group/tools/sync-push-github-cpanel.sh "sync: post-restore google sync $(date +%Y-%m-%d)"
```

### 4c — Verifica finale

```bash
cd ~/public_html/crm/mec-group
php tools/sync-custom-prod-repo.php status --branch=main
```

Atteso: molti **Identici**, pochi **Solo prod** (moduli extension client esclusi).

---

## Cosa NON fare

| ❌ | Motivo |
|----|--------|
| `rsync -av ~/ESPOCRM-git/custom/ ~/public_html/.../custom/` | Sovrascrive tutto (Sales, CSS, …) |
| `rsync -av ~/ESPOCRM-git/client/custom/ ...` | Idem — solo deploy mirato o script |
| Interrompere `rebuild.php` | Corrompe `slim-routes.php` → Internal server error |
| Riusare delta export vecchi | Sempre `export-delta` fresco per sessione |

---

## Commit di riferimento su `main` (Google Sync)

```
ddbfa8f  Bonifica duplicati Google
1df071e  Allineamento produzione 2026-06-09
aaa39f7  Stop ghost: dummy Google su cambio assegnatario
316b1ab  Non pushare appuntamenti admin/altro consulente
07b095c  syncConGoogle opt-out (default attivo)
9da0970  Campo syncConGoogle
```

Vedi anche: [`09-ALLINEAMENTO-GOOGLE-SYNC-20260609.md`](09-ALLINEAMENTO-GOOGLE-SYNC-20260609.md), [`05-SYNC-REPO-DAL-SERVER.md`](05-SYNC-REPO-DAL-SERVER.md).
