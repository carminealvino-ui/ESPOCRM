# Regole operative — produzione MEC Group

**Leggere questa cartella prima di scrivere codice, script `tools/` o dare istruzioni al server.**

Ambiente: `~/public_html/crm/mec-group` (produzione EspoCRM).

---

## Regola 0 — Server e repo sempre allineati (**OBBLIGATORIA**)

**Tutto ciò che si scrive o modifica sul server** (`custom/`, `client/custom/`, layout da Layout Manager, …) **deve essere allineato al repository GitHub** prima di considerare il lavoro concluso.

- Flusso preferito: **repo → server** (branch, PR, deploy).
- Se si modifica **direttamente in produzione**: obbligo di **export + commit + push** subito dopo (vedi sotto).

Dettaglio completo: **[`00-REGOLA-SERVER-REPO.md`](00-REGOLA-SERVER-REPO.md)**

Comandi rapidi:

```bash
# Sync completo custom (dopo ogni sessione con modifiche in prod)
php tools/sync-custom-prod-repo.php export-delta --branch=main
# poi apply-delta + git push (vedi 05-SYNC e 08-AVVIO-SYNC-CPANEL)

# Solo layout Contratto
bash tools/align-quote-layouts-prod-repo.sh
```

---

## Regola 1 — Un’istruzione alla volta

- Si fornisce **una sola** azione per messaggio (un comando, un file, un deploy).
- Si passa al passo successivo **solo dopo** conferma visiva: **screenshot** del terminale o della schermata CRM che mostra l’esito atteso (`OK`, backup creato, rebuild completato, campo visibile, ecc.).
- Se lo screenshot non conferma il successo → **non** si prosegue; si corregge quel passo.

Dettaglio: [`01-UN-ISTRUZIONE-ALLA-VOLTA.md`](01-UN-ISTRUZIONE-ALLA-VOLTA.md)

---

## Regola 2 — Backup del fix (storico e rollback) — **OBBLIGATORIO**

- **Prima** di ogni modifica in produzione: backup in `backup_dev/` — **passo 0, non saltare**.
- Procedura standard: [`00-PASSO-ZERO-BACKUP-OBBLIGATORIO.md`](00-PASSO-ZERO-BACKUP-OBBLIGATORIO.md)
- **Unica cartella:** `backup_dev/` — tutti i backup (hook, layout, snapshot, sessioni).

| Tipo | Dove in `backup_dev/` |
|------|------------------------|
| File singoli | `{Entità}/hooks/`, `layouts/`, `metadata/`, … |
| Sessione batch | `_sessions/YYYYMMDD-HHMMSS_FIX/` |
| Snapshot Quote | `Quote/layouts-snapshots/YYYYMMDD-HHMMSS/` |
| Snapshot Account | `Account/snapshots/YYYYMMDD-HHMMSS/` |
| Snapshot Appuntamento | `Appuntamento/snapshots/YYYYMMDD-HHMMSS/` |

- Nome file: `DATA_FIX_AGGIORNAMENTO_OBIETTIVO` (vedi `backup_dev/README.md`).
- **Non** usare `custom/backup-layouts/` (deprecato).

Dettaglio: [`02-BACKUP-FIX-E-ROLLBACK.md`](02-BACKUP-FIX-E-ROLLBACK.md)

---

## Regola 3 — Istruzioni complete

Ogni istruzione deve includere, quando applicabile:

1. **Dove** eseguire (`cd ~/public_html/crm/mec-group`).
2. **Cosa** fa il comando (in una riga).
3. **Backup** da fare prima (comando esatto).
4. **Comando** completo, copiabile, senza `...`.
5. **Verifica** attesa (cosa vedere a schermo).
6. **Rollback** se qualcosa va male (un comando o percorso cartella).

Se manca uno di questi punti → l’istruzione **non** è completa.

Dettaglio: [`03-ISTRUZIONI-COMPLETE.md`](03-ISTRUZIONI-COMPLETE.md)

---

## Regola 4 — Struttura `backup_dev/` (cartelle per entità)

- `backup_dev/` non è una copia generica di `custom/`: ha **sottocartelle per entità** (`Appuntamento/`, `Opportunity/`, `Quote/`, …) e tipi (`hooks/`, `layouts/`, `metadata/`, `client/`).
- La cartella **`backup_dev/client/`** (in root) serve solo per backup **globali** CSS/metadata — **non** sostituisce `mec-group/client/` live.
- I JS di dettaglio/handler vanno in `backup_dev/{Entità}/client/...`.

Dettaglio: [`04-STRUTTURA-BACKUP-DEV.md`](04-STRUTTURA-BACKUP-DEV.md) e [`backup_dev/STRUTTURA-CARTELLE.md`](../backup_dev/STRUTTURA-CARTELLE.md)

---

## Ordine obbligatorio di lavoro

1. **Passo 0 backup:** [`00-PASSO-ZERO-BACKUP-OBBLIGATORIO.md`](00-PASSO-ZERO-BACKUP-OBBLIGATORIO.md)
2. Flusso completo: [`00-ORDINE-DI-LAVORO.md`](00-ORDINE-DI-LAVORO.md)

---

## Riferimenti collegati

| Documento | Contenuto |
|-----------|-----------|
| **`00-REGOLA-SERVER-REPO.md`** | **Regola 0: tutto sul server va allineato al repo** |
| `tools/LEGGI-PRIMA.md` | Punto di ingresso per script |
| `tools/LAYOUT-NON-SOVRASCRIVERE.md` | Deploy che non toccano layout Contratto |
| `backup_dev/README.md` | Struttura backup per entità |
| `docs/PRODUZIONE-SYNC-LAYOUT-20260601.md` | Sync repo ↔ server (panoramica) |
| `05-SYNC-REPO-DAL-SERVER.md` | Prod → GitHub: **export-delta sempre per primo**, poi ZIP → PC → apply → push |
| `08-AVVIO-SYNC-CPANEL.md` | **Checklist cPanel** (token.txt, export → apply → push script) |
| `09-ALLINEAMENTO-GOOGLE-SYNC-20260609.md` | Allineamento post-fix Google Calendar Appuntamento |
| `06-PUSH-GITHUB-DAL-SERVER.md` | Push delta su GitHub dal server (PAT) |
| `07-VERIFICA-SYNC-PRODUZIONE-GITHUB.md` | Verifica allineamento: `status --branch=main` |

---

*Ultimo aggiornamento regole: 2026-06-17*
