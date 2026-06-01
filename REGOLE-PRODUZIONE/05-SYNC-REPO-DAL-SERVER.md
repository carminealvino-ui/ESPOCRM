# Allineare GitHub al server (produzione → repo)

**Direzione:** ciò che è **oggi sul server** diventa base per un commit su `main`.  
**Non** si fa `apply-delta` in produzione (sarebbe repo → prod).

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

## Passo 1 — Bootstrap `tools/` sul server

**Dove:** `cd ~/public_html/crm/mec-group`

**Comando:**

```bash
curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/main/tools/bootstrap-server-tools.sh?t=$(date +%s)" | bash
```

**Verifica:** `ls tools/sync-custom-prod-repo.php`

→ Screenshot, poi Passo 2.

---

## Passo 2 — Stato differenze (solo lettura)

Vedi [`07-VERIFICA-SYNC-PRODUZIONE-GITHUB.md`](07-VERIFICA-SYNC-PRODUZIONE-GITHUB.md).

**Comando:**

```bash
php tools/sync-custom-prod-repo.php status --branch=main
```

Controllare se ci sono `layouts/` che volete portare su Git.  
→ Screenshot con i quattro totali, poi Passo 3.

---

## Passo 3 — Export delta (copia file prod)

**Comando:**

```bash
php tools/sync-custom-prod-repo.php export-delta --branch=main
```

**Verifica attesa:**

- `Export delta completato`
- Cartella `exports/sync/delta-YYYYMMDD-HHMMSS/`
- ZIP `exports/sync/delta-YYYYMMDD-HHMMSS.zip`

---

## Passo 4 — Portare il delta sul PC (Git)

Scaricare via SFTP la cartella o lo ZIP `exports/sync/delta-*`.

Sul clone locale del repo:

```bash
cd /percorso/ESPOCRM
git checkout main
git pull origin main
php tools/sync-custom-prod-repo.php apply-delta /percorso/delta-YYYYMMDD-HHMMSS
git status
git add custom client/custom
git commit -m "sync: allineamento da produzione $(date +%Y-%m-%d)"
git push origin main
```

**Verifica:** push su GitHub; in repo i layout/metadata coincidono con prod.

---

## Passi successivi in produzione (dopo push)

Solo deploy **mirati** (`curl` + script), non sovrascrivere tutto `custom/`.  
Vedi `tools/LAYOUT-NON-SOVRASCRIVERE.md`.

---

## Rollback

- Sul server, i file originali non sono cancellati dall’export.
- Su PC, `apply-delta` salva backup repo in `delta-*-repo-backup` dentro `exports/sync/` sul server (se apply fatto lì) o nella cartella delta sul PC.
