# Regole operative — produzione MEC Group

**Leggere questa cartella prima di scrivere codice, script `tools/` o dare istruzioni al server.**

Ambiente: `~/public_html/crm/mec-group` (produzione EspoCRM).

---

## Regola 1 — Un’istruzione alla volta

- Si fornisce **una sola** azione per messaggio (un comando, un file, un deploy).
- Si passa al passo successivo **solo dopo** conferma visiva: **screenshot** del terminale o della schermata CRM che mostra l’esito atteso (`OK`, backup creato, rebuild completato, campo visibile, ecc.).
- Se lo screenshot non conferma il successo → **non** si prosegue; si corregge quel passo.

Dettaglio: [`01-UN-ISTRUZIONE-ALLA-VOLTA.md`](01-UN-ISTRUZIONE-ALLA-VOLTA.md)

---

## Regola 2 — Backup del fix (storico e rollback)

**Obbligatorio prima di ogni modifica.** Nessun codice nuovo in produzione senza backup verificato e rollback annotato.

Dettaglio completo (dashboard, Softaculous, hookVersion): [`09-NO-CODICE-SENZA-BACKUP-E-HOOKVERSION.md`](09-NO-CODICE-SENZA-BACKUP-E-HOOKVERSION.md)

- **Prima** di ogni modifica in produzione: backup con naming e cartella documentati.
- Due livelli complementari:

| Tipo | Dove | Quando |
|------|------|--------|
| **Fix mirato** (hook, layout, metadata, client) | `backup_dev/{Entità}/…` | Ogni modifica a file custom |
| **Layout deploy** (snapshot cartella) | `custom/backup-layouts/YYYYMMDD-HHMMSS/` | Prima di deploy layout / script che toccano UI |

- Nome file in `backup_dev`: `DATA_FIX_AGGIORNAMENTO_OBIETTIVO` (vedi `backup_dev/README.md`).

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

## Regola 5 — hookVersion su ogni fix hook

- Ogni hook su entità con campo `hookVersion` deve aggiornare **`$entity->set('hookVersion', 'x.y.z')`** con la stessa versione dell’header del file PHP.
- Incrementare la versione ad ogni modifica; verificare su un record in CRM dopo il deploy.

Dettaglio: [`09-NO-CODICE-SENZA-BACKUP-E-HOOKVERSION.md`](09-NO-CODICE-SENZA-BACKUP-E-HOOKVERSION.md)

---

## Ordine obbligatorio di lavoro

Vedi [`00-ORDINE-DI-LAVORO.md`](00-ORDINE-DI-LAVORO.md).

---

## Riferimenti collegati

| Documento | Contenuto |
|-----------|-----------|
| `tools/LEGGI-PRIMA.md` | Punto di ingresso per script |
| `tools/LAYOUT-NON-SOVRASCRIVERE.md` | Deploy che non toccano layout Contratto |
| `backup_dev/README.md` | Struttura backup per entità |
| `docs/PRODUZIONE-SYNC-LAYOUT-20260601.md` | Sync repo ↔ server (panoramica) |
| `05-SYNC-REPO-DAL-SERVER.md` | **Server → GitHub** (modifiche UI in prod): **export-delta sempre per primo** |
| `08-AVVIO-SYNC-CPANEL.md` | Checklist cPanel: export → apply su `~/ESPOCRM-git` → push |
| `12-ALLINEA-SERVER-DA-REPO.md` | **Solo deploy** GitHub → server (dopo sync); non per modifiche fatte in UI |
| `06-PUSH-GITHUB-DAL-SERVER.md` | Push delta su GitHub dal server (PAT) |
| `07-VERIFICA-SYNC-PRODUZIONE-GITHUB.md` | Verifica allineamento: `status --branch=main` |
| `09-NO-CODICE-SENZA-BACKUP-E-HOOKVERSION.md` | **Backup obbligatorio**, dashboard, Softaculous, **hookVersion** |
| `10-RIAPPLICA-VARIAZIONI-DOPO-RESTORE.md` | Piano step post-restore Softaculous |
| `11-I18N-IT-IT-SOLO-AGGIUNTE.md` | **it_IT: solo nuove chiavi**, mai modificare etichette esistenti |

---

*Ultimo aggiornamento regole: 2026-06-20*
