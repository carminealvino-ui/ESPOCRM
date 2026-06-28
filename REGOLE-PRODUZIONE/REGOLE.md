# REGOLE — produzione MEC Group (unico documento)

> **Leggere solo questo file.** Agent, operatori e script devono seguire **esclusivamente** `REGOLE-PRODUZIONE/REGOLE.md`.  
> I vecchi file numerati (`00-…`, `01-…`, `README.md`, ecc.) sono stati **rimossi** per evitare che venga applicata solo una parte delle istruzioni.

**Ambiente:** `~/public_html/crm/mec-group` (EspoCRM produzione)  
**Repository:** `https://github.com/carminealvino-ui/ESPOCRM` — branch `main` = produzione testata (dopo export-delta)

---

## Indice

1. [Regola d’oro — produzione comanda](#1-regola-doro--produzione-comanda)
2. [Ordine di lavoro](#2-ordine-di-lavoro)
3. [Un’istruzione alla volta](#3-unistruzione-alla-volta)
4. [Backup e rollback](#4-backup-e-rollback)
5. [Struttura backup_dev](#5-struttura-backup_dev)
6. [Istruzioni complete (template)](#6-istruzioni-complete-template)
7. [Allineamento produzione → GitHub](#7-allineamento-produzione--github)
8. [Verifica allineamento](#8-verifica-allineamento)
9. [Deploy produzione (repo → server)](#9-deploy-produzione-repo--server)
10. [Agent Cursor — vincoli](#10-agent-cursor--vincoli)
11. [Installazione regole sul server](#11-installazione-regole-sul-server)

---

## 1. Regola d’oro — produzione comanda

Ciò che **funziona ed è testato in produzione** non deve essere sovrascritto da file vecchi nel repository.

### Tre livelli (non confonderli)

| Livello | File | «Eliminato» significa |
|---------|------|------------------------|
| Layout | `layouts/{Entità}/*.json` | Non visibile nel form |
| Entità | `metadata/entityDefs/{Entità}.json` | Campo non esiste (né UI né API) |
| Etichette | `i18n/it_IT/{Entità}.json` | Testo UI |

Se in produzione un campo è stato rimosso **dall’entità**, ma nel repo Git è ancora in `entityDefs`, un deploy **repo → prod** lo **ricrea**.

### Direzione del flusso

```
Produzione (testata)  ──export-delta──►  GitHub (main)
                              ▲
                              │
                    SOLO hotfix mirati (whitelist file)
```

| Direzione | Quando |
|-----------|--------|
| **Prod → GitHub** | Dopo ogni intervento OK in produzione |
| **GitHub → Prod** | Solo emergenza, **solo file elencati** nel task |
| **Mai** | «Allineare il server al repo» senza export prima |

**Branch `cursor/*-9999`:** sperimentazione / PR — **non** deployare l’intero branch in produzione.

---

## 2. Ordine di lavoro

```
1. Leggere REGOLE-PRODUZIONE/REGOLE.md (questo file)
2. Backup (backup_dev + layout se UI)
3. UNA istruzione → esecuzione
4. Screenshot / verifica esito
   └─ KO → rollback dal backup
   └─ OK → passo successivo
5. A fine sessione OK: export-delta prod → push su main
```

**Checklist rapida**

- [ ] Backup fatto, percorso annotato
- [ ] Un solo comando / un solo deploy
- [ ] Output o screenshot verificato
- [ ] Se deploy in prod: solo file in whitelist
- [ ] Dopo OK: export-delta (altrimenti il repo resta indietro)

---

## 3. Un’istruzione alla volta

- Un messaggio = **un passo** (un comando o un deploy).
- Passo successivo **solo** dopo screenshot che conferma `OK`, backup creato, rebuild completato, o schermata CRM corretta.
- **Vietato:** «fai backup, curl deploy, rebuild e backfill» in un solo messaggio.

**Frase per andare avanti:** «Passo N completato» + screenshot.

---

## 4. Backup e rollback

### Prima di qualsiasi modifica

**File singoli (hook, PHP, JS, metadata):**

```bash
cd ~/public_html/crm/mec-group
bash tools/backup-dev-save.sh ENTITA FIX_TIPO NOME_FILE
```

Esempio: `bash tools/backup-dev-save.sh Appuntamento elenco-produzione hooks GlobalLogic.php`  
→ `backup_dev/Appuntamento/hooks/YYYYMMDD-HHMMSS_…`

**Layout (cartella intera entità):**

```bash
bash tools/backup-quote-layouts.sh
bash tools/backup-account-layouts.sh
```

**Emergenza layout:**

```bash
STAMP=$(date +%Y%m%d-%H%M%S)
mkdir -p "custom/backup-layouts/${STAMP}/Quote"
cp -a custom/Espo/Custom/Resources/layouts/Quote/. "custom/backup-layouts/${STAMP}/Quote/"
```

**Se manca `tools/`:**

```bash
curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/main/tools/bootstrap-server-tools.sh?t=$(date +%s)" | bash
```

### Rollback

```bash
# Layout Contratto
bash tools/restore-quote-layouts.sh YYYYMMDD-HHMMSS
php command.php rebuild && rm -rf data/cache/*

# File da backup_dev (adattare percorso)
cp -a backup_dev/Quote/layouts/…/detail.json custom/Espo/Custom/Resources/layouts/Quote/detail.json
php command.php rebuild && rm -rf data/cache/*
```

Il backup **database** è separato (export MySQL / snapshot hosting).

---

## 5. Struttura backup_dev

| Path live | Backup in |
|-----------|-----------|
| `custom/Espo/Custom/Hooks/{Entità}/` | `backup_dev/{Entità}/hooks/` |
| `custom/Espo/Custom/Resources/layouts/{Entità}/` | `backup_dev/{Entità}/layouts/` |
| `client/custom/src/…` | `backup_dev/{Entità}/client/…` |

**Attenzione:** `backup_dev/client/` ≠ `mec-group/client/` (il primo è solo archivio).

Naming: `DATA_FIX_AGGIORNAMENTO_OBIETTIVO` — vedi `backup_dev/README.md` e `backup_dev/STRUTTURA-CARTELLE.md`.

---

## 6. Istruzioni complete (template)

Ogni passo deve avere (se applicabile):

1. **Dove:** `cd ~/public_html/crm/mec-group`
2. **Backup** (comando esatto)
3. **Comando** completo, senza `...`
4. **Verifica attesa** (terminale o CRM)
5. **Rollback** se fallisce

---

## 7. Allineamento produzione → GitHub

**Direzione:** ciò che è sul server diventa base per `main`.  
**Non** si esegue `apply-delta` in produzione (sarebbe repo → prod).

### Regola: export sempre per primo

1. `export-delta` — **obbligatorio**, primo passo operativo
2. Non riusare un delta vecchio nella stessa sessione
3. `status` è solo diagnosi; non sostituisce l’export

### Cosa viene confrontato

| Cartella | Contenuto |
|----------|-----------|
| `custom/Espo/Custom/` | Hook, layout, metadata, i18n it_IT |
| `client/custom/` | Front-end JS/CSS |
| `custom/Espo/Modules/Sales/Resources` | Layout/metadata Sales |
| `custom/Espo/Modules/Sales/Hooks` | Hook Sales |
| `custom/Espo/Modules/Sales/Classes` | Classi Sales |

**Esclusi:** vendor, `*.bak`, i18n non it_IT, cache. **Non inclusi:** `backup_dev/`, `data/`, database.

### A) Flusso PC (consigliato)

**Passo 1 — Export sul server**

```bash
cd ~/public_html/crm/mec-group
php tools/sync-custom-prod-repo.php export-delta --branch=main
```

Atteso: `Export delta completato`, cartella `exports/sync/delta-YYYYMMDD-HHMMSS/` + ZIP.

**Passo 2 — Scarica ZIP sul PC** (SFTP / file manager).

**Passo 3 — Apply sul clone Git**

```bash
cd /percorso/ESPOCRM
git checkout main && git pull origin main
php tools/sync-custom-prod-repo.php apply-delta /percorso/delta-YYYYMMDD-HHMMSS
git add custom client/custom
git commit -m "sync: allineamento da produzione $(date +%Y-%m-%d)"
git push origin main
```

### B) Flusso cPanel (server con clone Git)

| Percorso | Uso |
|----------|-----|
| `~/public_html/crm/mec-group` | CRM produzione |
| `~/ESPOCRM-git` | Clone per push |
| `exports/sync/token.txt` | PAT GitHub (`chmod 600`), **mai su GitHub** |

**Setup una tantum:**

```bash
cd ~ && git clone https://github.com/carminealvino-ui/ESPOCRM.git ESPOCRM-git
cd ~/ESPOCRM-git && git config user.email "sync@mec-group.local" && git config user.name "MEC Sync"
# Token in exports/sync/token.txt (modello: exports/sync/token.txt.example)
```

**Ogni sessione:**

```bash
# 1. Export
cd ~/public_html/crm/mec-group
php tools/sync-custom-prod-repo.php export-delta --branch=main

# 2. Apply (sostituire DELTA)
cd ~/ESPOCRM-git && git checkout main && git pull origin main
php tools/sync-custom-prod-repo.php apply-delta "/home/telcalli/public_html/crm/mec-group/exports/sync/DELTA"
# oppure: bash ~/public_html/crm/mec-group/tools/sync-apply-delta-cpanel.sh DELTA

# 3. Push
bash ~/public_html/crm/mec-group/tools/sync-push-github-cpanel.sh "sync: allineamento da produzione $(date +%Y-%m-%d)"
```

### C) Push dal server con PAT (alternativa)

```bash
export GITHUB_TOKEN="ghp_…"
export GITHUB_REPOSITORY="carminealvino-ui/ESPOCRM"
export GITHUB_BRANCH="main"
php tools/sync-custom-prod-repo.php push-delta exports/sync/delta-YYYYMMDD-HHMMSS
```

Opzione repo più leggero: `--exclude-client-modules` (esclude pacchetti advanced/google/outlook in client).

---

## 8. Verifica allineamento

```bash
cd ~/public_html/crm/mec-group
php tools/sync-custom-prod-repo.php status --branch=main
```

| Voce | Atteso se allineati |
|------|---------------------|
| Identici | Numero alto |
| Diversi | 0 o pochi |
| Solo prod | 0 o pochi |
| Solo repo | 0 o pochi |

Manifest: `exports/sync/status-YYYYMMDD-HHMMSS.json` — screenshot prima di deploy o dopo sync.

---

## 9. Deploy produzione (repo → server)

Solo **dopo** backup e **solo** file del task.

### File ad alto rischio (vietati senza conferma esplicita)

```
custom/Espo/Custom/Resources/layouts/**
custom/Espo/Custom/Resources/metadata/entityDefs/**
custom/Espo/Custom/Resources/metadata/logicDefs/**
custom/Espo/Custom/Resources/metadata/clientDefs/**
custom/Espo/Custom/Resources/i18n/**
```

### Checklist pre-deploy

- [ ] `status --branch=main` (screenshot)
- [ ] Backup `backup_dev`
- [ ] Aperto lo script deploy: array `FILES=(…)` ⊆ task
- [ ] Se include layout/entityDefs → **stop**, conferma umana
- [ ] Dopo deploy: verifica CRM + **export-delta**

### Deploy da NON fare «per allineare»

- Intero branch `cursor/opportunity-globallogic-9999` o altri branch feature
- `curl | bash` su script sconosciuti
- Deploy stati/layout completi senza export prod prima

### Esempio hotfix corretto (whitelist)

```bash
FILES=(
  "custom/Espo/Custom/Actions/Opportunity/CreateContratto.php"
  "custom/Espo/Custom/Resources/metadata/formula/Quote.json"
)
# curl solo quei file, poi clear_cache + rebuild
```

### Dopo ogni hotfix OK

1. Verifica CRM  
2. `export-delta` → push su `main`  
3. Così il repo non ripresenta regressioni al deploy successivo

---

## 10. Agent Cursor — vincoli

**Default (senza richiesta esplicita nel task): non modificare e non deployare**

- `entityDefs`, `layouts`, `i18n`, `clientDefs`
- Deploy interi da branch feature
- Più obiettivi nello stesso commit/deploy

**Ogni intervento:** un obiettivo, whitelist file, verifica post-deploy.

Le regressioni (layout, etichette, campi fantasma) consumano turni agent inutili — la prevenzione è **questo processo**, non altri fix a catena.

---

## 11. Installazione regole sul server

La cartella sul CRM:

```
~/public_html/crm/mec-group/REGOLE-PRODUZIONE/REGOLE.md   ← solo questo file
```

**Da GitHub `main` (dopo merge):**

```bash
cd ~/public_html/crm/mec-group
mkdir -p REGOLE-PRODUZIONE
curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/main/REGOLE-PRODUZIONE/REGOLE.md?t=$(date +%s)" \
  -o REGOLE-PRODUZIONE/REGOLE.md
```

**Verifica:**

```bash
test -f ~/public_html/crm/mec-group/REGOLE-PRODUZIONE/REGOLE.md && echo OK
```

---

*Ultimo aggiornamento: 2026-06-28 — documento unico, sostituisce tutti i file precedenti in `REGOLE-PRODUZIONE/`.*
