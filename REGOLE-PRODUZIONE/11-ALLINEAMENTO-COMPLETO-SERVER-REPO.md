# Regola 11 — Allineamento completo server ↔ repository (obbligatorio)

**Principio:** produzione e GitHub devono restare **allineati sempre**.  
Ogni modifica fatta sul server **deve** finire nel repo; ogni deploy dal repo **deve** partire da un repo già aggiornato con il server.

Non esistono sync «facoltativi» o «a fine sprint»: l’allineamento completo è parte del lavoro, non un optional.

---

## Cosa significa «allineamento completo»

Ciclo **obbligatorio** (dettaglio operativo in [`05-SYNC-REPO-DAL-SERVER.md`](05-SYNC-REPO-DAL-SERVER.md)):

| # | Dove | Azione |
|---|------|--------|
| 1 | **Server** | `export-delta --branch=main` (sempre per primo, delta **nuovo**) |
| 2 | **PC** | Scarica ZIP `exports/sync/delta-…zip` |
| 3 | **PC** | `apply-delta` → `git commit` → `git push origin main` |
| 4 | **Server** | `status --branch=main` → atteso: **`Solo prod` ≈ 0**, **`Identici` alto** |

Se il passo 4 non è OK → **non** si considera chiuso il lavoro; si ripete export → apply → push.

---

## Quando è obbligatorio

| Situazione | Allineamento completo |
|------------|------------------------|
| Dopo **ogni** modifica in produzione (Admin, layout, hook, script, rebuild) | **Sì, sempre** |
| Dopo **ogni** sessione di lavoro con l’operatore (anche un solo fix) | **Sì, sempre** |
| **Prima** di deploy da branch GitHub verso produzione | **Sì** — prima export prod → `main` |
| Modifica fatta **solo** su branch feature in locale | Export prod → merge su `main` **prima** del deploy in prod |
| «Tanto lo rifacciamo dopo» / delta di ieri / «solo un file» | **Vietato** — delta vecchio o parziale **non** vale |

---

## Cosa NON va considerato allineamento

- Solo `status` senza export nella stessa sessione.
- Riutilizzare un ZIP `delta-…` di giorni precedenti.
- Commit manuali sul PC **senza** export dal server live.
- Deploy `curl | bash` da branch che **sovrascrivono** `custom/` senza aver prima portato prod su `main`.
- Modifiche fatte in Admin **senza** «Deploy to files» / rebuild: restano nel DB e **non** entrano nel delta.

---

## Perché esiste questa regola

Senza allineamento completo si perdono:

- campi custom creati in Admin (es. su Contratto) mai committati;
- etichette e layout modificati solo in Entity/Layout Manager;
- fix applicati solo in produzione e sovrascritti dal deploy successivo da GitHub;
- divergenze difficili da ricostruire (repo ≠ server ≠ DB).

---

## Prima di un deploy in produzione (checklist)

- [ ] Export delta **oggi** dal server (`export-delta --branch=main`)
- [ ] Apply + push su `main` completati
- [ ] `status --branch=main` sul server: allineato
- [ ] Backup produzione fatto (Regola 2)
- [ ] Solo **dopo**: deploy mirato da branch/script (non full overwrite di `custom/`)

---

## Dopo un deploy in produzione (checklist)

- [ ] Verifica funzionale in CRM (screenshot)
- [ ] **Nuovo** export delta dal server
- [ ] Apply + push su `main` (anche se il deploy veniva da branch: prod è la verità dei file live)
- [ ] `status --branch=main` di conferma

---

## Comando unico — export sul server (Passo 1)

**Dove:** `cd ~/public_html/crm/mec-group`

```bash
cd ~/public_html/crm/mec-group && php tools/sync-custom-prod-repo.php export-delta --branch=main
```

Poi: scarica ZIP → apply sul PC → push → verifica `status` (Passi 2–4 in [`05-SYNC-REPO-DAL-SERVER.md`](05-SYNC-REPO-DAL-SERVER.md)).

---

## Comando unico — verifica allineamento (Passo 4)

**Dove:** `cd ~/public_html/crm/mec-group`

```bash
cd ~/public_html/crm/mec-group && php tools/sync-custom-prod-repo.php status --branch=main
```

**Atteso:** pochi o zero file «Solo prod»; la maggior parte «Identici».

---

## Riferimenti

| Documento | Contenuto |
|-----------|-----------|
| [`05-SYNC-REPO-DAL-SERVER.md`](05-SYNC-REPO-DAL-SERVER.md) | Procedura export → apply → push |
| [`07-VERIFICA-SYNC-PRODUZIONE-GITHUB.md`](07-VERIFICA-SYNC-PRODUZIONE-GITHUB.md) | Interpretazione output `status` |
| [`08-AVVIO-SYNC-CPANEL.md`](08-AVVIO-SYNC-CPANEL.md) | Checklist cPanel |
| [`00-ORDINE-DI-LAVORO.md`](00-ORDINE-DI-LAVORO.md) | Dove inserire sync nel flusso di lavoro |
