# Analisi sync `custom` da server (29 maggio 2026)

## Fonte

| Elemento | Valore |
|----------|--------|
| File GitHub | `custom-export-20260529-033657.zip` (commit `a5e8fd0` su `main`) |
| Data export zip | 2026-05-29 03:36 |
| Backup utente citato | restore layout ~20:05 sera precedente (coerente con layout Quote nel zip) |

Struttura export EspoCRM:

```
custom/Espo/Custom/     → metadata PHP layout i18n (4488 file)
client/custom/          → JS/CSS frontend (157 file)
```

## Prima vs dopo (branch `cursor/quote-prezzi-iva-inclusa-9999`)

| Cartella | File prima | File dopo ( = server ) |
|----------|------------|-------------------------|
| `custom/` | 118 | **4488** |
| `client/` | 20 | **157** |

Il repository conteneva solo un **sottoinsieme** del custom di produzione (lavoro branch prezzi/provvigioni). L’export zip è l’immagine completa del server dopo il restore.

## Layout Quote (produzione nel repo)

Presenti tutti i file che mancavano nel branch:

- `detail.json` (2122 B — versione server)
- `defaultSidePanel.json`
- `bottomPanelsDetail.json`
- `detailBottomTotal.json`
- `list.json`, `listForAccount.json`

## Differenze risolte (34 file modificati su entrambi)

Sostituiti con la versione **server**:

- `QuotePricingCalculator.php` (47142 B server vs 47494 B branch — il branch aveva patch minusPlus 4200/4400 non ancora sul server al momento dell’export)
- `CreateContratto.php`, `GlobalLogic.php`, `Product/BeforeSave.php`
- Layout: Quote, Opportunity, Appuntamento, Lead, Product
- Metadata: `entityDefs/Quote.json`, `formula/Quote.json`, `clientDefs/*`, `app/actions.json`, `app/client.json`
- i18n it_IT: Quote, Opportunity, Product, Provvigione, …
- Client: `create-contratto.js`, `item-list.js` (path sotto `custom/.../Resources/client` e `client/custom`)

## Solo nel vecchio repo (rimossi — non sul server)

Entità / feature presenti solo sul branch git, **assenti** nell’export:

- **InvitoAFatturare** (entity, layout, hooks, services, actions)
- **RegolaProvvigionale** (entity parziale: sul server resta solo `clientDefs/RegolaProvvigionale.json`; niente entity/layout nel zip)
- **Quote/CalcolaProvvigioni** action, **ProductPrice** hooks
- **ProvvigioneManager**, **RegolaProvvigionaleCalculator**, **ProvvigioneAccrual**, **OpportunityPriceBookResolver**
- **SyncContractPricingAfterSave.php.disabled**
- Layout/metadata: `ProductPrice`, `ProductCategory` (solo repo)
- Client branch-only: `calcola-provvigioni.js`, viste calendar/appuntamento/invito estese nel repo ridotto

Se servono ancora, vanno reintrodotte **dopo** questa sync, a partire da backup git del branch precedente.

## Solo sul server (aggiunti al repo)

- Decine di entity custom (Appuntamento, Area, CAP, Zona, Prospect, …) con controller, repository, hook
- Tutte le lingue i18n in `Resources/i18n/*` (non solo `it_IT`)
- `Hooks/Quote/BeforeSave.php` (oltre a `SyncContractPricing.php`)
- `client/custom`: moduli, admin views, `custom-product-button.js`, `crea-prodotto-articoli.js`
- File di backup sul server (lasciati come in export): `*.save`, `*.bak`, `*_BACKUP.php`

## Coerenza verificata

```bash
diff -rq custom-export-unzipped/custom workspace/custom   # 0 differenze
diff -rq custom-export-unzipped/client workspace/client     # 0 differenze
```

## Prossimi passi consigliati

1. Non rieseguire deploy che sovrascrivono `layouts/Quote/*` da branch vecchi.
2. Se serve di nuovo il fix minusPlus 4200→4000: riapplicare patch su `QuotePricingCalculator.php` **partendo da questa base server**.
3. Valutare se re-importare **InvitoAFatturare** / regole provvigionali dal commit precedente del branch.

## Riferimento zip in repo

Il file resta su `main`: `custom-export-20260529-033657.zip` (non versionato nel branch sync per dimensione; scaricabile da GitHub).
