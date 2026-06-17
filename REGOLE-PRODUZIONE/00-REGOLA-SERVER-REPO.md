# Regola 0 — Tutto ciò che si scrive sul server va allineato al repo

**Questa regola vale per ogni intervento in produzione.**  
Nessuna modifica in `~/public_html/crm/mec-group` si considera **chiusa** finché non è riflessa su GitHub (`main` o branch di lavoro approvato).

---

## Principio

| Ambiente | Ruolo |
|----------|--------|
| **Repository GitHub** | Fonte di verità del codice e dei metadata versionati |
| **Server produzione** | Istanza live che **deve** coincidere con il repo (dopo deploy/sync) |

**Non** lasciare mai sul server file custom diversi dal repo senza averli esportati e committati.

---

## Cosa rientra nell’allineamento obbligatorio

Tutto ciò che modifica il comportamento o l’UI del CRM e vive sotto:

| Percorso | Esempi |
|----------|--------|
| `custom/Espo/Custom/` | hook PHP, `entityDefs`, `logicDefs`, `formula`, layout JSON, i18n `it_IT` |
| `client/custom/` | view JS, handler, CSS, template |
| `custom/Espo/Modules/Sales/Resources` | layout/metadata Sales customizzati |
| `custom/Espo/Modules/Sales/Hooks` e `Classes` | estensioni Sales |
| **Layout Manager** (Admin) | Scrive in `custom/.../layouts/` → va esportato come gli altri file |

**Non** vanno committati sul repo (restano solo sul server):

- `data/`, cache, log, upload utenti
- `exports/sync/token.txt` (segreto)
- `backup_dev/`, snapshot e cartelle di rollback
- Dati in database (record) — salvo script di migrazione versionati in `tools/`

---

## Due flussi ammessi

### A — Preferito: repo → server (deploy)

1. Modifica nel repo (branch + PR).
2. Merge su `main`.
3. Deploy in produzione da `main` (script `tools/deploy-*.sh` o file puntuali).
4. Verifica su CRM + screenshot.

Il server riceve ciò che è già su GitHub.

### B — Eccezione controllata: modifica diretta sul server

Ammessa solo se urgente (Layout Manager, hotfix da terminale, test in prod).

**Obbligo immediato dopo:**

1. Backup (`backup_dev/` — passo 0).
2. **Export produzione → repo** (vedi sotto).
3. `git commit` + `git push` su GitHub.
4. Verifica: `php tools/sync-custom-prod-repo.php status --branch=main` → differenze zero (o solo attese).

**Senza passo 2–3 il lavoro non è finito.**

---

## Come allineare server → repo

### Sync completo custom (default)

Sul server:

```bash
cd ~/public_html/crm/mec-group
php tools/sync-custom-prod-repo.php export-delta --branch=main
```

Poi sul clone Git (`~/ESPOCRM-git` o PC):

```bash
php tools/sync-custom-prod-repo.php apply-delta "/path/to/exports/sync/delta-YYYYMMDD-HHMMSS"
git add custom client/custom
git commit -m "sync: allineamento da produzione YYYY-MM-DD"
git push origin main
```

Checklist cPanel: [`08-AVVIO-SYNC-CPANEL.md`](08-AVVIO-SYNC-CPANEL.md)  
Dettaglio: [`05-SYNC-REPO-DAL-SERVER.md`](05-SYNC-REPO-DAL-SERVER.md)

### Solo layout Contratto (Quote)

Dopo modifiche da Layout Manager sul Contratto:

```bash
cd ~/public_html/crm/mec-group
bash tools/align-quote-layouts-prod-repo.sh
```

Vedi anche [`../tools/LAYOUT-NON-SOVRASCRIVERE.md`](../tools/LAYOUT-NON-SOVRASCRIVERE.md).

---

## Cosa è vietato

- Considerare «fatto» un fix solo perché funziona in produzione, **senza** push su GitHub.
- Deploy da branch GitHub che **sovrascrivono** layout/metadata modificati a mano in prod **senza** aver prima esportato la versione prod (es. `deploy-layout-minus-plus.sh` su layout custom).
- Riutilizzare un `delta-…` vecchio: **ogni sessione** richiede un **nuovo** `export-delta`.
- Tenere modifiche solo in `backup_dev/` senza portarle nel repo (il backup è rollback, non sostituto di Git).

---

## Chiusura sessione (checklist)

Prima di passare al task successivo o chiudere la giornata:

- [ ] Tutte le modifiche server sono state esportate (`export-delta` o script dedicato).
- [ ] Commit e push su GitHub eseguiti.
- [ ] `status --branch=main` senza file «solo produzione» imprevisti.
- [ ] PR/branch di sviluppo aggiornati se il lavoro non è ancora su `main`.

---

## Riferimenti

| Documento | Contenuto |
|-----------|-----------|
| [`00-ORDINE-DI-LAVORO.md`](00-ORDINE-DI-LAVORO.md) | Sequenza completa intervento (backup → fix → sync) |
| [`05-SYNC-REPO-DAL-SERVER.md`](05-SYNC-REPO-DAL-SERVER.md) | Export/apply delta |
| [`08-AVVIO-SYNC-CPANEL.md`](08-AVVIO-SYNC-CPANEL.md) | Token, clone, push da cPanel |
| [`07-VERIFICA-SYNC-PRODUZIONE-GITHUB.md`](07-VERIFICA-SYNC-PRODUZIONE-GITHUB.md) | Verifica allineamento |

*Regola adottata: 2026-06-17*
