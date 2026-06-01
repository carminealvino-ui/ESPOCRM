# Produzione — punto situazione sync repo / layout / Sales Pack

**Data riferimento:** 1 giugno 2026  
**Ambiente:** `~/public_html/crm/mec-group` (produzione)

---

## 1. Tre “fonti verità” (possono divergere)

| Fonte | Cosa contiene | Ruolo |
|-------|----------------|--------|
| **Server produzione** | File effettivi + DB + layout modificati in Layout Manager | **Verità operativa** |
| **GitHub `main`** | Ultimo export massiccio (29/05) + fix mergiati (Appuntamento duplica, backup_dev) | **Verità codice versionato** |
| **Branch `cursor/*-9999`** | Fix mirati non sempre in `main` | **Deploy puntuale** |

Dopo il **29/05** (zip `custom-export-20260529`) su produzione sono possibili:

- aggiornamento **Sales Pack** (file sotto `custom/Espo/Modules/Sales/`);
- **layout** toccati a mano (Contratto, Opportunità, Appuntamento, Cliente, …);
- deploy **curl** di singoli file (hook, client, metadata) **senza** aggiornare tutto il repo.

→ **`main` non è garantito uguale alla produzione di oggi**, soprattutto per `layouts/**` e per il modulo Sales aggiornato.

---

## 2. Sincronizzazione repo ↔ server

### Tool: `php tools/sync-custom-prod-repo.php`

| Comando | Direzione | Uso |
|---------|-----------|-----|
| `status` | Confronto prod vs branch Git | Capire cosa è diverso |
| `export-delta` | **Prod → cartella/ZIP** | Portare in Git le modifiche fatte in produzione |
| `apply-delta` | Delta → working tree Git (PC) | Commit dopo export; **non** lanciare alla cieca su prod |

**Cartelle scansionate:** `custom/Espo/Custom`, `client/custom`  
**Esclusioni config:** backup `*.bak`, cache; i18n lingue diverse da `it_IT` (regex in config).  
**Layout:** *non* esclusi — le differenze layout compaiono in `status` / `export-delta`.

Confronto di default sul branch in `tools/sync-custom-prod-repo.config.json` → usare:

```bash
php tools/sync-custom-prod-repo.php status --branch=main
php tools/sync-custom-prod-repo.php export-delta --branch=main
```

### Cosa NON è sync automatico

- **Database** (cliente, contratti, opportunità)
- **`data/`** (config, upload)
- Deploy curl **non** allinea tutto il repo: solo i file elencati nello script

---

## 3. Layout — regole operative

Documento: `tools/LAYOUT-NON-SOVRASCRIVERE.md`

| Azione | Rischio layout |
|--------|----------------|
| `deploy-contratto-prezzi-curl.sh`, `deploy-emergency-restore-crm-ui.sh` | **Basso** (non toccano `layouts/Quote/*.json`) |
| `deploy-layout-minus-plus.sh`, `apply-quote-detail-prezzi-sample.sh` | **Alto** (sovrascrivono `detail.json`) |
| `export-delta` prod → Git | **Sicuro** (salva ciò che c’è in prod) |
| `apply-delta` da branch vecchio verso prod | **Alto** |

### Backup layout sul server

```bash
bash tools/backup-quote-layouts.sh      # Contratto → custom/backup-layouts/.../Quote/
bash tools/backup-account-layouts.sh  # Cliente + metadata Account/Appuntamento
bash tools/backup-contratto-prima-deploy.sh  # quote + metadata correlati
ls -lt custom/backup-layouts/
```

Snapshot **pre-upgrade** Espo: `backup/pre-upgrade-9.3.7/`

---

## 4. Sales Pack

- Modulo in repo: `custom/Espo/Modules/Sales/` (bundled, migliaia di file).
- Aggiornamento in produzione: **Administration → Extensions** (non sostituire tutta `custom/` dal pacchetto Espo core).
- Dopo upgrade Sales Pack: **Clear cache + Rebuild**; verificare Contratto, Opportunità, listini, `itemList`.
- Se il pack è stato aggiornato solo in prod, il **repo può essere indietro** sulla cartella `Sales/` → includere in un prossimo `export-delta` o export ZIP.

Non sovrascrivere in upgrade: `custom/Espo/Custom/`, `client/custom/`, `data/config*.php`.

---

## 5. Branch / fix recenti (stato vs `main`)

| Branch | Contenuto | In `main`? |
|--------|-----------|------------|
| `cursor/appuntamento-produzione-fruibile-9999` | Elenco Appuntamento, CSS, clientDefs | Parziale (duplica sì; elenco da verificare) |
| `cursor/fix-doppio-crea-prodotto-9999` | Un solo «Crea prodotto» | No |
| `cursor/account-subpanel-appuntamenti-contratti-9999` | Subpanel Cliente + hook account | No |
| `cursor/ripristina-cliente-contratto-9999` | Script ripristino cliente da opp. | No |
| `cursor/quote-prezzi-iva-inclusa-9999` | Prezzi / layout sample | Layout: attenzione |

Deploy produzione tipico: `curl` + `bash tools/....sh` + `php command.php rebuild`.

---

## 6. Checklist consigliata (1/6/26)

Sul server, in ordine:

```bash
cd ~/public_html/crm/mec-group

# 1) Backup layout + snapshot leggero
bash tools/backup-quote-layouts.sh
bash tools/backup-account-layouts.sh 2>/dev/null || true

# 2) Stato rispetto a main
php tools/sync-custom-prod-repo.php status --branch=main --limit=120

# 3) Se ci sono differenze volute in prod → esportare per Git
php tools/sync-custom-prod-repo.php export-delta --branch=main
# Scaricare exports/sync/delta-* sul PC, apply-delta, commit su main

# 4) Elenco backup
ls -lt custom/backup-layouts/
ls -lt backup_dev/ 2>/dev/null | head
```

Su PC: decidere quali PR/branch mergiare in `main`; poi in prod solo deploy **mirati** o `export-delta` inverso (prod → repo), non copia totale `custom/` da Git senza `status`.

---

## 7. Riferimenti

- Analisi sync 29/05: `docs/CUSTOM-SYNC-ANALYSIS-20260529.md`
- Pre-upgrade 9.3.7: `backup/pre-upgrade-9.3.7/README-AGGIORNAMENTO.md`
- Backup dev: `backup_dev/README.md`, `tools/BACKUP-DEV-GUIDA.md`
