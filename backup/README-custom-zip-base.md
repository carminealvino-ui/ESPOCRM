# Base custom da `custom (4).zip` (mattina 12-05-2026)

Per le correzioni su contratto/opportunità si usa come **riferimento originale** l'export ZIP,
non una riscrittura parziale del solo branch Git.

## File ripristinati dal ZIP e poi aggiornati a 1.7.0

- `custom/Espo/Custom/Actions/Opportunity/CreateContratto.php` (da v1.6.0 zip)
- `custom/Espo/Custom/Controllers/Opportunity.php` (v1.1.4 zip — `new CreateContratto($entityManager)`)
- `custom/Espo/Custom/Resources/metadata/formula/Quote.json`
- `custom/Espo/Custom/Resources/layouts/Quote/detail.json` (+ righe partner/brand/categoria)
- `custom/Espo/Custom/Hooks/Quote/BeforeSave.php`

## Restano dal branch (non nel vecchio zip)

- `client/custom/src/views/fields/*.js` (cascade partner/brand/categoria)
- `Opportunity` entityDefs con fornitorePartner / productBrand / productCategory
- Moduli Fase 2+ (InvitoAFatturare, RegoleProvvigionali, …)

## Deploy

Dopo pull: caricare i file sopra + **Rebuild** + **Clear cache**.
