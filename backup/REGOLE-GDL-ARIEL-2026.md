# GDL / Ariel Energia — provvigioni e minus/plus (mail rete, vigore 01/02/2026)

Riferimento per il caso **DI MAGGIO** (partner **GDL**, brand **ARIEL**).

## Regime CRM

- Codice: `ARIEL_2026` — contratti con data **≥ 23/01/2026**
- Contratti Ariel/GDL **prima del 23/01/2026** → vedi `backup/REGOLE-ARIEL-LEGACY.md` (scalette minus)
- Attivazione automatica se `fornitorePartner` contiene **GDL** o `productBrand` contiene **ARIEL**

## Provvigioni su contratto (implementato)

| Componente | Regola | Calcolo |
|------------|--------|---------|
| **Base** | 10% + 5% imponibile | `arielBase105` — es. €4.500 → **€675** |
| **Ordine incompleto** | Solo 10% | Flag `ordineIncompletoAriel` → `arielBase10` → **€450** |
| **Plusvalenza** | 35% sulla plus € | Se imponibile > prezzo codice: `arielPlus35` su (imponibile − codice) |

**Importante (mail):** anche in sottocosto il consulente percepisce l’**aliquota piena sull’imponibile**; la minus si compensa con plus entro 30 giorni / €990 mese (contatori portale — **fase 2**).

## Minus / plus su contratto

- Campo **`minusPlus`** = imponibile − **prezzo codice IVA escl.** (da opportunità/contratto).
- Valore **positivo** = plusvalenza → provvigione **Plus Provvigionale** 35%.
- Valore **negativo** = minusvalenza (recupero/compensazione non ancora automatizzati).

## Non ancora automatizzato (fasi successive)

- Contatori **Minus plus venduto** / **in pagamento**
- Limiti €990 / 10% sotto codice / 30 giorni
- Premi produzione su plus mensile (€250 / €350 / €500)
- Addebiti, sospesi, retrocessione ordine
- Listino “tutto incluso” (impatto solo su prezzi, non su %)

## Deploy

1. `database/2026-05-26-gdl-ariel-2026-regole-provvigioni-seed.sql`
2. `database/2026-05-26-quote-ariel-ordine-incompleto.sql`
3. Deploy PHP + rebuild campi Quote/Opportunity
4. Clear cache
5. Su opportunità Ariel: valorizzare **prezzo codice** e **prezzo listino** IVA escl. → **Crea Contratto** → verificare pannello Provvigioni

## File codice

- `Services/ProvvigioneManager.php` — `createConsolidataAriel2026`
- `Services/ProvvigioneAccrual.php` — `resolveRegimeFromCommercial`
- `database/2026-05-26-gdl-ariel-2026-regole-provvigioni-seed.sql`
