# Allineare GitHub al server (produzione → repo)

**Direzione:** ciò che è **oggi sul server** diventa base per un commit su `main`.  
**Non** si fa `apply-delta` in produzione (sarebbe repo → prod).

---

## Regola: export sempre per primo

Per **ogni** allineamento produzione → GitHub:

1. Si esegue **sempre** `export-delta` come **primo passo operativo** (dopo eventuale bootstrap `tools/`).
2. **Non** si riusa un delta vecchio senza aver rifatto l’export nella stessa sessione.
3. `status` è **opzionale** (solo diagnosi); **non** sostituisce l’export.

Poi: scarica ZIP sul PC → `apply-delta` → `git commit` → `git push` (flusso abituale, credenziali Git sul PC).

---

## Cosa viene confrontato

| Cartella | Contenuto |
|----------|-----------|
| `custom/Espo/Custom/` | Custom MEC (hook, layout, metadata, i18n it_IT, …) |
| `client/custom/` | Front-end JS/CSS |
| `custom/Espo/Modules/Sales/Resources` | Layout/metadata Sales Pack (customizzati) |
| `custom/Espo/Modules/Sales/Hooks` | Hook Sales |
| `custom/Espo/Modules/Sales/Classes` | Classi Sales custom |

**Esclusi:** `vendor` Sales, backup `*.bak`, i18n non `it_IT`, cache.

**Non inclusi:** `backup_dev/`, `REGOLE-PRODUZIONE/`, `data/`, database.

---

## Passo 0 — Bootstrap `tools/` (solo se manca lo script)

**Dove:** `cd ~/public_html/crm/mec-group`

**Comando:**

```bash
curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/main/tools/bootstrap-server-tools.sh?t=$(date +%s)" | bash
ls -la tools/sync-custom-prod-repo.php
```

→ Screenshot, poi **Passo 1 (export)**.

---

## Passo 1 — Export delta (obbligatorio, sempre per primo)

**Dove:** `cd ~/public_html/crm/mec-group`

**Cosa fa:** esporta in `exports/sync/delta-…/` tutto ciò che in produzione non coincide con `main` (cartella + ZIP).

**Comando:**

```bash
cd ~/public_html/crm/mec-group
php tools/sync-custom-prod-repo.php export-delta --branch=main
```

**Verifica attesa:**

- `Export delta completato`
- `File: N` (numero file)
- Cartella `exports/sync/delta-YYYYMMDD-HHMMSS/`
- ZIP `exports/sync/delta-YYYYMMDD-HHMMSS.zip`

**Rollback:** nessuno su `custom/` live; per rifare, eliminare solo la cartella `delta-…` appena creata.

→ Screenshot con path `delta-…` e ZIP, poi Passo 2.

---

## Passo 2 — Scarica il delta sul PC

**Dove:** SFTP / FileZilla / file manager hosting.

**File sul server:**

```text
~/public_html/crm/mec-group/exports/sync/delta-YYYYMMDD-HHMMSS.zip
```

**Verifica:** ZIP sul PC, dimensione plausibile (non quasi vuoto).

→ Poi Passo 3.

---

## Passo 3 — Apply delta sul clone Git (PC)

**Dove:** clone locale `ESPOCRM`.

```bash
cd /percorso/ESPOCRM
git checkout main
git pull origin main
```

Estrarre lo ZIP, poi:

```bash
php tools/sync-custom-prod-repo.php apply-delta /percorso/delta-YYYYMMDD-HHMMSS
git status
git add custom client/custom
git commit -m "sync: allineamento da produzione $(date +%Y-%m-%d)"
git push origin main
```

**Verifica:** commit su https://github.com/carminealvino-ui/ESPOCRM/commits/main  
(`apply-delta` crea backup repo in `exports/sync/apply-backup-…`).

---

## Passo 4 — Verifica (opzionale, dopo il push)

**Dove:** server CRM.

```bash
cd ~/public_html/crm/mec-group
php tools/sync-custom-prod-repo.php status --branch=main
```

**Atteso:** `Solo prod` basso o 0; `Identici` alto.

Vedi anche [`07-VERIFICA-SYNC-PRODUZIONE-GITHUB.md`](07-VERIFICA-SYNC-PRODUZIONE-GITHUB.md).

---

## Diagnosi prima dell’export (opzionale)

Solo se serve vedere i totali **prima** dell’export; non salta il Passo 1.

```bash
php tools/sync-custom-prod-repo.php status --branch=main
```

---

## Push dal server con PAT (alternativa al PC)

Vedi [`06-PUSH-GITHUB-DAL-SERVER.md`](06-PUSH-GITHUB-DAL-SERVER.md).  
Flusso consigliato MEC: **export sul server → apply + push sul PC**.

---

## Dopo il push su `main`

Solo deploy **mirati** in produzione, non sovrascrivere tutto `custom/`.  
Vedi `tools/LAYOUT-NON-SOVRASCRIVERE.md`.

---

## Rollback

- Produzione: l’export non modifica i file live.
- PC: backup in `exports/sync/apply-backup-…` creato da `apply-delta`.
- GitHub: revert del commit di sync.
