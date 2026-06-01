# Etichette italiane (`it_IT`) — regole MEC Group

## Regola principale

**I file in `custom/Espo/Custom/Resources/i18n/it_IT/` non vanno mai sovrascritti da script di deploy, sync da GitHub o export ZIP.**

- Fonte ufficiale: **solo questo repository**, modifica manuale o commit dedicato `i18n(it_IT): ...`.
- Deploy tecnici (Appuntamento, Opportunity, Quote, …) toccano **metadata, client, hooks** — **mai** `i18n/` né `layouts/`.

## Perché compaiono etichette in inglese

Se manca una chiave in `it_IT/{Entità}.json` (es. `fields.dateEnd`), EspoCRM usa il fallback **en_US** → in scheda vedi "Date End", "Duration", ecc.

Non è un bug del layout: manca la traduzione nel file italiano.

## Dopo modifica etichette (solo manuale)

```bash
cd ~/public_html/crm/mec-group
php command.php clearCache
rm -rf data/cache/*
# Ctrl+F5 nel browser
```

## File sensibili

| File | Note |
|------|------|
| `it_IT/Appuntamento.json` | Etichette modulo Appuntamenti |
| `it_IT/Global.json` | Globali (non deployare da script) |

## Verifica rapida

```bash
grep -E '"dateEnd"|"duration"' custom/Espo/Custom/Resources/i18n/it_IT/Appuntamento.json
```

Atteso: `Data fine appuntamento`, `Durata`.
