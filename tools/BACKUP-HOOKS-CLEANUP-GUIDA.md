# Guida backup `backup/hooks_cleanup` — cosa ripristinare

Cartella sul server: `~/public_html/crm/mec-group/backup/hooks_cleanup/` (evidenziata in giallo nel file manager).

## Problema attuale: non crei appuntamenti (pagina bianca)

| Causa | File coinvolti |
|--------|------------------|
| **Schermata bianca Crea/Duplica** | `clientDefs/Appuntamento.json` + assenza `client/modules/crm` |
| **NON** risolto da GlobalLogic | Hook PHP al salvataggio |

I backup del **26/05** in `hooks_cleanup` **non contengono** `clientDefs` Appuntamento. Ripristinare solo quei file **non basta** per Crea/Duplica.

---

## File nella cartella — cosa fare

| File backup (esempio) | Ripristinare? | Destinazione | Note |
|----------------------|---------------|--------------|------|
| `backup-appuntamento-globallogic-2026-05-26-2042.php` | **Sì** (preferito) | `custom/Espo/Custom/Hooks/Appuntamento/GlobalLogic.php` | Più recente del 1955 |
| `backup-appuntamento-globallogic-2026-05-26-1955-pre-leadprospectsync.php` | Solo se manca il 2042 | stesso | Versione precedente |
| `backup-appuntamento-logicdefs-2026-05-26-0629-pre-v1.3.0.json` | **Sì** | `.../logicDefs/Appuntamento.json` | Regole dinamiche form |
| `backup-appuntamento-entitydefs-2026-05-26-2014.json` | **Sì, con attenzione** | `.../entityDefs/Appuntamento.json` | Contiene view `crm:meeting` → vanno **rimosse** dopo copia |
| `backup-create-contratto-pre-2.1.0-stabile.php` | **No** (per ora) | — | Opportunità / Contratto |
| `AutoCreateQuote_*_BACKUP.php` | **No** | — | Opportunità |
| `backup-client-product-category-by-brand-*.js` | **No** | — | Prodotti / categorie |

Nel repo Git ci sono anche versioni `*-stabile.php` (es. `backup-appuntamento-globallogic-1.7.0-category-cascade-stabile.php`): sul server usa i file **datati 26/05** se sono quelli che avevi quando tutto funzionava.

---

## Comando unico (backup + ripristino + fix Crea)

```bash
cd ~/public_html/crm/mec-group
curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/main/tools/restore-appuntamento-da-backup-hooks-cleanup.sh?t=$(date +%s)" | bash
```

1. Salva copia in `custom/backup-layouts/YYYYMMDD-HHMMSS/`
2. Ripristina GlobalLogic + logicDefs (+ entityDefs senza view meeting)
3. Imposta `clientDefs` con **views/record/edit** da GitHub
4. `rebuild` + cache

## Rollback

```bash
bash tools/rollback-produzione.sh
```

---

## Cosa NON confondere

- **`custom/backup-layouts/`** — backup layout Quote / deploy recenti (restore ore 20:05)
- **`backup/hooks_cleanup/`** — snapshot PHP/metadata del 26/05 e file `*-stabile`

Per il **layout Contratto** del restore serale non usare `hooks_cleanup`; usa `custom/backup-layouts/`.
