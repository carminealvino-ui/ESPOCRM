# Layout Contratto — non sovrascrivere con deploy

Gli script **`deploy-contratto-prezzi-curl.sh`** e **`deploy-emergency-restore-crm-ui.sh`** **non** scaricano `layouts/Quote/*.json` da GitHub.

## Script che SOVRASCRIVONO il layout (evitare in produzione)

| Script | Rischio |
|--------|---------|
| `deploy-layout-minus-plus.sh` | Sostituisce `detail.json` (e Opportunità) con la versione nel repo |
| Deploy vecchi / branch `provvigioni-manuali-fase-a-9999` con layout nel curl | Stesso problema |

## Prima volta sul server (cartella `tools/` assente)

Gli script **non** sono nella installazione Espo: vanno scaricati da GitHub:

```bash
cd ~/public_html/crm/mec-group
curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/quote-prezzi-iva-inclusa-9999/tools/bootstrap-server-tools.sh?t=$(date +%s)" | bash
```

Poi `bash tools/backup-quote-layouts.sh` funziona.

**Importante:** il backup layout **non** salva i valori Cliente / Contraente / Contatto installazione sul record (sono colonne nel database). Se quei campi risultano «Nessuno», serve ripristino dati (`tools/ripristina-cliente-contratto-da-opportunita.php`) o backup MySQL. Per deploy contratto usare anche `bash tools/backup-contratto-prima-deploy.sh`.

## Backup prima di qualsiasi modifica

```bash
cd ~/public_html/crm/mec-group
bash tools/backup-quote-layouts.sh
ls -lt custom/backup-layouts/
```

Copia in `custom/backup-layouts/YYYYMMDD-HHMMSS/Quote/` (tutti i file: `detail.json`, `defaultSidePanel.json`, `detailBottomTotal.json`, ecc.).

## Ripristino da backup (sul server)

**Non** usare cartelle inventate tipo `ULTIMA_DATA` o `ULTIMO`: nella doc vecchia erano solo segnaposto.

1. Elenco backup reali:

```bash
ls -lt custom/backup-layouts/
ls -la custom/backup-layouts/20260529-123456/Quote/   # esempio con data reale
```

2. Ripristino automatico (chiede conferma):

```bash
bash tools/restore-quote-layouts.sh
# oppure con timestamp esatto:
bash tools/restore-quote-layouts.sh 20260529-123456
```

3. Oppure manuale:

```bash
cp -a custom/backup-layouts/20260529-123456/Quote/* custom/Espo/Custom/Resources/layouts/Quote/
php command.php rebuild
rm -rf data/cache/*
```

## Se NON esiste `custom/backup-layouts/`

Come nel tuo terminale (`ls: cannot access 'custom/backup-layouts/'`):

1. I file attuali in `custom/Espo/Custom/Resources/layouts/Quote/` sono l’unica copia — **non** cancellarli.
2. Subito: `bash tools/backup-quote-layouts.sh` per non perdere di nuovo.
3. Pannello laterale «Commerciale» / totali in fondo: **Admin → Layout Manager → Quote** (Detail, Side Panels, Bottom Panels).
4. Se manca solo il pannello prezzi in `detail.json` (non side panel):

```bash
curl -fsSL "https://raw.githubusercontent.com/carminealvino-ui/ESPOCRM/cursor/quote-prezzi-iva-inclusa-9999/tools/apply-quote-detail-prezzi-sample.sh?t=$(date +%s)" -o /tmp/apply-prezzi-layout.sh
bash /tmp/apply-prezzi-layout.sh
```

Questo applica il sample `tools/layouts-samples/Quote/detail-prezzi-minusplus.json` dopo un backup.

## Deploy sicuri (prezzi PHP, UI emergenza)

```bash
# Solo PHP prezzi — layout intatti
bash tools/deploy-contratto-prezzi-curl.sh

# Vista Contratto senza pagina bianca — layout intatti
bash tools/deploy-emergency-restore-crm-ui.sh

# Solo pulsante «Crea prodotto» in tabella articoli
bash tools/deploy-crea-prodotto-button.sh
```

## Esportare il layout di produzione nel repo (opzionale)

Solo con approvazione: copiare da server i JSON di `layouts/Quote/` in un branch dedicato, così non si perdono `defaultSidePanel.json` e `detailBottomTotal.json` che oggi non sono nel repository.
