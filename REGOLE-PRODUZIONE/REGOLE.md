# REGOLE — produzione MEC Group (unico documento)

> **Leggere solo questo file.** Agent, operatori e script devono seguire **esclusivamente** `REGOLE-PRODUZIONE/REGOLE.md`.  
> I vecchi file numerati (`00-…`, `01-…`, `README.md`, ecc.) sono stati **rimossi** per evitare che venga applicata solo una parte delle istruzioni.

**Ambiente:** `~/public_html/crm/mec-group` (EspoCRM produzione)  
**Repository:** `https://github.com/carminealvino-ui/ESPOCRM` — branch `main` = copia ufficiale del custom (allineata alla produzione testata)

---

## Indice

1. [Regola d’oro — repo prima, poi produzione](#1-regola-doro--repo-prima-poi-produzione)
2. [Ordine di lavoro](#2-ordine-di-lavoro)
3. [Un’istruzione alla volta](#3-unistruzione-alla-volta)
4. [Backup e rollback](#4-backup-e-rollback)
5. [Struttura backup_dev](#5-struttura-backup_dev)
6. [Istruzioni complete (template)](#6-istruzioni-complete-template)
7. [Allineamento produzione → GitHub](#7-allineamento-produzione--github)
8. [Verifica allineamento](#8-verifica-allineamento)
9. [Analisi e approvazione (prima del deploy)](#9-analisi-e-approvazione-prima-del-deploy)
10. [Deploy produzione (solo dopo OK)](#10-deploy-produzione-solo-dopo-ok)
11. [Agent Cursor — vincoli](#11-agent-cursor--vincoli)
12. [File obsoleti in tutto `custom/`](#12-file-obsoleti-in-tutto-custom)
13. [Installazione regole sul server](#13-installazione-regole-sul-server)

---

## 1. Regola d’oro — repo prima, poi produzione

### Regola obbligatoria (da giugno 2026)

**Nessun file va in produzione se non esiste già la stessa versione su GitHub (`main`).**

Flusso standard per ogni modifica:

```
1. Scrivere il codice nel repository (branch → PR → merge su main, oppure commit diretto su main)
2. Push su GitHub (commit visibile su main)
3. Solo allora: deploy in produzione (script whitelist che scarica DA GitHub)
4. Verifica CRM + status sync (Identici, Solo prod 0)
```

| Vietato | Perché |
|---------|--------|
| Modificare file solo sul server e «sistemare il repo dopo» | Crea drift, regressioni, lavoro doppio |
| `curl \| bash` verso file non ancora pushati | La produzione non avrebbe copia su repo |
| Deploy da branch feature senza merge su `main` | Il repo ufficiale resterebbe incompleto |

**Eccezione (solo emergenza):** hotfix fatto direttamente in produzione → subito dopo **export-delta + push** (§7), così `main` torna identico al server.

### Produzione testata non si sovrascrive con repo vecchio

Ciò che **funziona ed è testato in produzione** non deve essere sostituito da file obsoleti nel repository.

| Livello | File | «Eliminato» significa |
|---------|------|------------------------|
| Layout | `layouts/{Entità}/*.json` | Non visibile nel form |
| Entità | `metadata/entityDefs/{Entità}.json` | Campo non esiste (né UI né API) |
| Etichette | `i18n/it_IT/{Entità}.json` | Testo UI |

Se in produzione un campo è stato rimosso **dall’entità**, ma nel repo Git è ancora in `entityDefs`, un deploy **repo → prod** lo **ricrea**.

### Direzione del flusso (riassunto)

```
                    ┌── commit + push (SEMPRE prima del deploy)
                    ▼
Sviluppo / PR ──► GitHub (main) ──deploy whitelist──► Produzione
                    ▲                                      │
                    └──────── export-delta (solo se hotfix diretto in prod)
```

| Direzione | Quando |
|-----------|--------|
| **Repo → Prod** | **Default:** ogni deploy dopo push su `main` |
| **Prod → GitHub** | Solo se si è toccato il server a mano; subito dopo verifica OK |
| **Mai** | Deploy in prod senza copia identica già su `main` |
| **Mai** | «Allineare tutto il server al repo» senza export prima |

**Branch `cursor/*-9999`:** sperimentazione / PR — merge su `main` **prima** del deploy; **non** deployare l’intero branch in produzione.

---

## 2. Ordine di lavoro

```
1. Leggere REGOLE-PRODUZIONE/REGOLE.md (questo file)
2. Backup (backup_dev + layout se UI)
3. Analisi scritta (cosa cambia / cosa si rimuove) — §9
4. Approvazione esplicita («OK deploy»)
5. Commit + push su GitHub (main) — file della whitelist già sul repo
6. UNA istruzione → deploy in produzione (script che scarica DA GitHub)
7. Screenshot / verifica esito
   └─ KO → rollback dal backup
   └─ OK → passo successivo
8. status --branch=main (Solo prod 0, Solo repo 0)
   └─ Se hotfix fatto solo in prod: export-delta → push (§7)
```

**Checklist rapida**

- [ ] Analisi consegnata e approvata (§9) prima di qualsiasi deploy
- [ ] **Codice pushato su `main` prima del deploy in produzione** (§1)
- [ ] Backup fatto, percorso annotato
- [ ] Un solo comando / un solo deploy
- [ ] Output o screenshot verificato
- [ ] Se deploy in prod: solo file in whitelist, URL GitHub punta a commit già su `main`
- [ ] Dopo OK: `status` allineato; export-delta solo se il server è stato modificato a mano

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

**Uso principale:** recuperare su `main` ciò che è stato modificato **direttamente** in produzione (eccezione §1).  
**Flusso normale:** repo → deploy → produzione; in quel caso `status` resta già allineato.  
**Non** si esegue `apply-delta` **in** produzione (sarebbe repo → prod senza deploy controllato).

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

## 9. Analisi e approvazione (prima del deploy)

**Vietato** creare subito `deploy-*.sh` o dare `curl | bash` al server.

### Sequenza obbligatoria

```
1. Analisi (testo per l'operatore)
2. Approvazione esplicita («OK deploy» / «procedi»)
3. Commit + push su GitHub (main) — whitelist già sul repo
4. Solo allora: script deploy + esecuzione sul server (curl da URL GitHub)
```

L’agent **non** salta i passi 1–3, anche per «hotfix urgenti».

### Contenuto minimo dell’analisi

Consegnare **prima** del deploy un documento breve (messaggio o commento PR) con:

| Voce | Contenuto |
|------|-----------|
| **Problema** | Cosa non funziona o cosa si vuole ottenere |
| **Causa** | Perché succede (file, hook, layout, repo indietro, …) |
| **File da modificare** | Elenco completo path (whitelist) |
| **File da rimuovere** | Hook / PHP / JS obsoleti (con `rm` esplicito) |
| **Cosa NON si tocca** | layout, entityDefs, i18n, altre entità |
| **Effetto atteso** | Cosa deve cambiare in CRM dopo il deploy |
| **Rischi** | Regressioni possibili |
| **Rollback** | Comando o cartella backup |
| **Verifica** | Comando grep / screenshot / test manuale |

### Template analisi (copiare)

```markdown
## Analisi — [titolo breve]

**Problema:** …
**Causa:** …

### File coinvolti
- MODIFICA: `path/file1`
- MODIFICA: `path/file2`
- RIMUOVI: `path/vecchio.php`

### Non toccare
- …

### Effetto atteso
- …

### Rollback
- …

### Verifica post-deploy
- …

**In attesa di approvazione prima di produrre lo script deploy.**
```

### Approvazione

- Deploy sul server **solo** dopo risposta esplicita dell’operatore.
- Se l’analisi cambia (file in più/meno) → **nuova** analisi o conferma.

### Dopo approvazione

1. Commit + push su `main` (o merge PR) con i file della whitelist.
2. Scrivere `tools/deploy-….sh` con array `FILES=(…)` che scarica **solo** da `main` (o branch indicato e già pushato).
3. Includere nella PR analisi + script nello stesso commit o PR.
4. Un passo alla volta in produzione (§3).

---

## 10. Deploy produzione (solo dopo OK)

Solo **dopo** analisi approvata (§9), backup (§4), **push su `main`** (§1) e whitelist file.

### Prerequisito repo

Prima di `curl | bash` sul server, verificare che ogni path della whitelist esista su GitHub:

```bash
# Esempio: commit atteso su main
git log origin/main -1 --oneline
# oppure aprire il file su raw.githubusercontent.com/.../main/...
```

Se il file non è su `main` → **stop**: commit/push prima del deploy.

### File ad alto rischio (vietati senza conferma esplicita nell’analisi)

```
custom/Espo/Custom/Resources/layouts/**
custom/Espo/Custom/Resources/metadata/entityDefs/**
custom/Espo/Custom/Resources/metadata/logicDefs/**
custom/Espo/Custom/Resources/metadata/clientDefs/**
custom/Espo/Custom/Resources/i18n/**
```

### Checklist pre-deploy

- [ ] Analisi §9 approvata per iscritto
- [ ] **File della whitelist già committati e pushati su `main`**
- [ ] `status --branch=main` (screenshot)
- [ ] Backup `backup_dev`
- [ ] Script deploy: `FILES=(…)` = whitelist analisi, URL = `raw.githubusercontent.com/.../main/...`
- [ ] Elenco `rm` per file obsoleti incluso nello script
- [ ] Dopo deploy: verifica CRM + `status` (Solo prod 0)

### Deploy da NON fare «per allineare»

- Intero branch `cursor/*-9999` o feature branch **senza merge su `main`**
- `curl | bash` verso file non ancora su GitHub
- `curl | bash` senza aver letto l’analisi e lo script
- Deploy stati/layout completi senza export prod prima (se il server era avanti al repo)
- Modifiche **solo** in cPanel/File Manager senza passaggio da repo

### Esempio script (solo dopo approvazione)

```bash
FILES=(
  "custom/Espo/Custom/Actions/Opportunity/CreateContratto.php"
  "custom/Espo/Custom/Resources/metadata/formula/Quote.json"
)
# + rm file obsoleti se previsto dall'analisi
# + clear_cache + rebuild
```

### Dopo ogni deploy OK

1. Verifica CRM (screenshot)  
2. `php tools/sync-custom-prod-repo.php status --branch=main` — atteso **Solo prod 0**, **Solo repo 0**  
3. Se **Solo prod > 0** (hotfix solo server): `export-delta` → push su `main` (§7)

---

## 11. Agent Cursor — vincoli

**Default (senza richiesta esplicita): non modificare e non deployare**

- `entityDefs`, `layouts`, `i18n`, `clientDefs`
- Deploy interi da branch feature
- Più obiettivi nello stesso commit/deploy
- **Script `deploy-*.sh` senza analisi approvata (§9)**
- **Deploy in produzione senza commit + push su `main` prima (§1)**

**Ogni intervento:**

1. Analisi scritta (§9)  
2. Attesa approvazione  
3. Modifica codice nel repo + commit + push su `main` (o PR merge)  
4. Script deploy (whitelist) che scarica da GitHub  
5. Un obiettivo, verifica post-deploy + `status`  

**Se un file viene sostituito:** cancellare il vecchio nello **stesso** intervento (§12).

Le regressioni consumano turni agent inutili — **analisi prima, deploy dopo**.

---

## 12. File obsoleti in tutto `custom/`

Vale per **tutto** il custom sul server, non solo `Hooks/Quote/`.

### Ambito (audit completo)

| Area | Percorso tipico |
|------|-----------------|
| Hook | `custom/Espo/Custom/Hooks/**` |
| Actions / Services / Controllers | `custom/Espo/Custom/{Actions,Services,Controllers}/**` |
| Metadata / layout / i18n | `custom/Espo/Custom/Resources/**` |
| Front-end | `client/custom/**` |
| Sales custom | `custom/Espo/Modules/Sales/{Hooks,Classes,Resources}/**` |

File `.php`, `.json`, `.js`, `.css` **orfani o duplicati** creano conflitti: hook multipli, layout sovrascritti, comandi API imprevedibili.

### Perché è critico (hook in particolare)

EspoCRM **carica automaticamente** ogni `.php` in `Hooks/{Entità}/`. Stesso principio per classi autoloadate in `Actions/`, `Services/`, ecc.

Un file **dimenticato** continua a eseguirsi anche se non è in Git o non è più nel layout.

### Regola

| Quando | Azione |
|--------|--------|
| Si sostituisce logica (hook, action, service) | **Cancellare** il file vecchio |
| Si abbandona una feature | **Cancellare** tutti i file collegati (hook, formula, layout voce, client JS) |
| Deploy da repo | File in prod **non** nel repo → decidere: export o `rm` (in analisi §9) |
| Backup `.bak` in cartelle attive | **Non** lasciarli — spostare in `backup_dev/` o eliminare |

### Inventario completo server

```bash
cd ~/public_html/crm/mec-group
php tools/audit-custom-server.php
```

Solo hook:

```bash
php tools/audit-custom-server.php --hooks-only
# oppure (alias): php tools/audit-custom-hooks.php
```

Export JSON (per confronto con repo):

```bash
php tools/audit-custom-server.php --json > exports/audit-custom-$(date +%Y%m%d).json
```

Confrontare con `php tools/sync-custom-prod-repo.php status --branch=main`.

### Pulizia (un file o una voce analisi alla volta)

```bash
cd ~/public_html/crm/mec-group
bash tools/backup-dev-save.sh ENTITA rimozione TIPO NomeFile.php
rm path/completo/dal/file
php clear_cache.php && php rebuild.php
```

`TIPO` = `hooks` | `layouts` | `metadata` | `client` (come da `backup-dev-save.sh`).

### Esempio — `Hooks/Quote/` (da rivedere in analisi)

| File | Ruolo |
|------|--------|
| `BeforeSave.php` | Prezzo codice righe |
| `ProvvigioneConsolidata.php` | Provvigioni auto |
| `SyncContractPricing.php` | Ricalcolo prezzi |
| `SyncFinanziamentoFromOpportunity.php` | Finanziamento da opportunità |
| `SetPresentedWhenNumeroContratto.php` | Stato da numero contratto |
| `SyncProductContratti.php` | Prodotti ↔ contratto |

Nella **analisi §9**: per ogni file, TENERE o RIMUOVERE con motivo.

### Agent

1. `audit-custom-server.php` (o `status`) **prima** di proporre modifiche.  
2. Nell’analisi: elenco **MODIFICA** + **RIMUOVI**.  
3. Script deploy con `rm` espliciti per ogni rimozione approvata.  
4. Mai aggiungere file «temporanei» senza piano di rimozione del vecchio.

---

## 13. Installazione regole sul server

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

*Ultimo aggiornamento: 2026-06-28 — §9 analisi prima deploy; §12 audit tutto custom.*
