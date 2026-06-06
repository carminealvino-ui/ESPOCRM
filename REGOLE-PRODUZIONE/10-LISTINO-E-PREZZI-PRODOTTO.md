# Regola 10 — Listino prezzi e prezzi prodotto

## Dove si inseriscono i prezzi

| Livello | Entità | Ruolo |
|---------|--------|--------|
| **Fonte operativa** | **ProductPrice** (riga nel **Listino Prezzi** / PriceBook) | Prezzi ufficiali per listino e periodo |
| **Riepilogo** | **Product** (Prodotto) | `listPrice`, `prezzoCodice`, `unitPrice` — **copiati dal listino** dal hook `DualIvaPricing` |

**Non serve** (e non è consigliato) inserire a mano prezzi duplicati sul Prodotto se il prodotto è già nel listino.

## Flusso consigliato

### A) Caricamento massivo da CSV/PDF (listino Ariel, ecc.)

```bash
cd ~/public_html/crm/mec-group && \
php tools/sync-listino-prodotti.php \
  --csv=database/data/listino-ariel-climatizzatori-07052026.csv \
  --price-book-name='ARIEL' \
  --date-start=2026-05-07 \
  --dry-run
```

Rimuovere `--dry-run` per applicare. Lo script crea/aggiorna **Product**, **ProductPrice** e campi dual IVA.

### B) Prodotto nuovo singolo

1. Creare **Prodotto** (nome, brand, categoria) — **senza** prezzi obbligatori.
2. Aprire **Listino Prezzi** (es. ARIEL Energia) → subpanel **Prezzi** → **+** → selezionare prodotto.
3. Compilare **Listino (IVA inclusa)** *oppure* **Listino (IVA esclusa)** — l’altro si calcola da solo (con **Imposta - Codice** IVA10/IVA22 sul listino).
4. Opzionale: **Prezzo codice (IVA esclusa/inclusa)** sulla stessa riga ProductPrice.
5. Salvare → il **Prodotto** riceve `listPrice` / `prezzoCodice` in automatico.

### C) Dati già presenti solo nel campo legacy `price`

Dopo deploy dual IVA, eseguire backfill **una volta**:

```bash
cd ~/public_html/crm/mec-group && \
php tools/backfill-productprice-dual-iva-from-price.php
```

## Campi dual IVA (ProductPrice nel listino)

| Campo | Significato |
|-------|-------------|
| `prezzoListinoIvaInclusa` | Listino IVI (se listino «IVA inclusa» attivo) |
| `prezzoListinoIvaEsclusa` | Listino netto |
| `prezzoCodice` | Prezzo codice netto (provvigioni) |
| `prezzoCodiceIvaInclusa` | Prezzo codice IVI |
| `aliquotaIva` | Da **Imposta - Codice** del listino (solo lettura) |

## Etichette in italiano

Se compaiono nomi tecnici (`prezzoListinoIvaEsclusa`):

```bash
cd ~/public_html/crm/mec-group && \
php command.php rebuild && \
php command.php clear-cache && \
rm -rf data/cache/*
```

Poi **Ctrl+F5** nel browser.

## Checklist listino

- [ ] **Imposta - Codice** (IVA10 / IVA22) su ogni PriceBook attivo
- [ ] **IVA inclusa** spuntato se i prezzi listino sono IVI (B2C)
- [ ] Backfill eseguito su listini migrati da campo `price` legacy
- [ ] Prezzi modificati **sul listino**, non sul prodotto (salvo eccezioni)

---

## D) Migrazione contratti storici — backfill articoli (DA FARE A LAVORO FINITO)

Dopo aver inserito tutti i contratti vecchi (~100), **una volta sola**:

```bash
cd ~/public_html/crm/mec-group && \
php tools/backfill-quote-itemlist-catalog-prices.php --verbose
```

Prova prima con `--dry-run`. Dettaglio e checklist: [`12-COSE-DA-FARE-PENDENTI.md`](12-COSE-DA-FARE-PENDENTI.md).
