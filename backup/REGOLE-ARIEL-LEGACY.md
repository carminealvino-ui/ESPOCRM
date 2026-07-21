# Ariel Energia — scalette minus legacy (contratti prima del 23/01/2026)

Riferimento documento **Nuove Scalette Minus** (promo 01.09.2025–31.12.2025).

## Regime CRM

- Codice: `ARIEL_LEGACY`
- Attivo su contratti Ariel/GDL con **data contratto &lt; 23/01/2026**
- Dal **23/01/2026** (incluso) → regime `ARIEL_2026` (10+5%, plus 35%, minus 100%)
- Costante: `ProvvigioneAccrual::ARIEL_2026_CUTOVER = '2026-01-23'`

## Base di calcolo

**Sconto su prezzo codice** (IVA escl.):

```
sconto% = (imponibile − prezzoCodice) / prezzoCodice × 100
```

- `0%` = vendita a prezzo codice
- Valori negativi = sotto codice (minus)
- Vendita sopra codice → fascia 0% in base + **Plus 50%** sul surplus
- La fascia determina la **% provvigionale sull’imponibile**

## Scalette per categoria

### Climatizzatori

| Sconto su codice | Provvigione |
|------------------|-------------|
| 0% … −2,99%      | 15%         |
| −3% … −4,99%     | 13%         |
| −5% … −6,99%     | 12%         |
| −7% … −9,99%     | 10%         |
| −10% … −12,99%   | 6%          |
| −13% … −14,99%   | 3%          |
| oltre −15%       | valutazione ufficio (nessuna regola automatica) |

### Caldaie e Stufe (inclusa Biomasse)

| Sconto su codice | Provvigione |
|------------------|-------------|
| 0% … −2,90%      | 13%         |
| −3% … −10%       | 7%          |
| oltre −10,10%    | 5%          |

### Eco Wind Easy

- Prezzo fisso pacchetto installato → **5%** fisso sull’imponibile (fascia 0%).
- Nessun plus automatico.

## Plus oltre prezzo codice

Se **imponibile &gt; prezzo codice** (IVA escl.):

- **Plus Provvigionale** = **50%** × (imponibile − codice)
- Non si applica a **Eco Wind Easy**

## Deploy

1. Deploy PHP + metadata (`ProvvigioneAccrual`, `ProvvigioneManager`, entityDefs)
2. `database/2026-07-07-ariel-legacy-scalette-minus-seed.sql`
3. `php tools/run-regola-provvigionale-seed.php`
4. `php clear_cache.php` (+ rebuild se richiesto)
5. Ricalcolo: `php tools/migrate-ricalcola-provvigioni-contratti.php` (contratti pre-23/01)

## Note non automatizzate

- Prezzo Clima + Caldaia Aurum al 50% non scontabile
- Oltre −15% su climatizzatori → valutazione manuale
