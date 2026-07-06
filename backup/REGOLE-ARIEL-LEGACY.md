# Ariel Energia — scalette minus legacy (fino al 23/01/2026)

Riferimento documento **Nuove Scalette Minus** (promo settembre–dicembre 2025).

## Regime CRM

- Codice: `ARIEL_LEGACY`
- Attivo su contratti Ariel/GDL con **data contratto &lt; 24/01/2026**.
- Dal **24/01/2026** resta in vigore il regime `ARIEL_2026` (10+5%, plus/minus 35%).

## Base di calcolo

**Sconto su prezzo codice** (IVA escl.):

```
sconto% = (imponibile − prezzoCodice) / prezzoCodice × 100
```

- `0%` = vendita a prezzo codice
- Valori negativi = sotto codice (minus)
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

### Caldaie e Stufe

| Sconto su codice | Provvigione |
|------------------|-------------|
| 0% … −2,90%      | 13%         |
| −3% … −10%       | 7%          |
| oltre −10,10%    | 5%          |

### Eco Wind Easy

- Prezzo fisso pacchetto installato → **5%** fisso sull’imponibile (fascia 0%).

## Plus oltre listino

Se **imponibile &gt; prezzo listino** (IVA escl.):

- **Plus Provvigionale** = **50%** × (imponibile − listino)
- Non si applica a **Eco Wind Easy**

## Deploy

1. `database/2026-07-07-ariel-legacy-scalette-minus-seed.sql`
2. Deploy PHP (`ProvvigioneAccrual`, `ProvvigioneManager`)
3. `php tools/run-regola-provvigionale-seed.php`
4. Clear cache + rebuild
5. `php tools/backfill-quote-provvigioni.php` su contratti pre-02/2026

## Note non automatizzate

- Prezzo Clima + Caldaia Aurum al 50% non scontabile
- Oltre −15% su climatizzatori → valutazione manuale
