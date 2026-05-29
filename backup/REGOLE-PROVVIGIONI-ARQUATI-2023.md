# Regole provvigioni ARQUATI (PNC) — maggio 2023

Documento di riferimento per il motore in EspoCRM (`RegolaProvvigionale` + `ProvvigioneManager`).

## Calcolo base (tende / pergole / vetrate)

- **Margine %** = `(imponibile IVA escl. − prezzo listino IVA escl.) / prezzo listino × 100`
- In alternativa si usa il campo opportunità **`suPrezzoCodice`** se valorizzato.
- **Provvigione** = `% fascia` × **imponibile contratto** (tipo `PercentualeMargine`).
- Fascie per linea prodotto: vedi `database/2026-05-26-arquati-pnc-regole-provvigioni-seed.sql`.

## Linee prodotto (`ProductCategory.gruppoProvvigione`)

| Gruppo | Regime default |
|--------|----------------|
| Tende da Sole | ARQUATI_PNC |
| Pergole | ARQUATI_PNC |
| Vetrate | ARQUATI_PNC |
| Clima e altro | ARQUATI_PNC (9% su imponibile, senza fasce margine) |

## Integrazioni

- **Contatti personali**: flag `contattoPersonaleArquati` sul contratto → seconda provvigione **Plus Provvigionale** +5% sull’imponibile (regola `arqCpP5`).
- **PNC**: campo `integrazionePncPercentuale` sul contratto (fase successiva).

## Liquidazione / trattenute (fase 2)

Le condizioni dell’allegato (incassi 40%, finanziamenti, recessi, sospesi, interruzione rapporto) non sono ancora automatizzate nello stato provvigione; restano processo amministrativo.

## Deploy tecnico

1. Rebuild entità se nuovi campi Quote non compaiono.
2. Eseguire SQL:
   - `database/2026-05-26-quote-provvigioni-pricing.sql`
   - `database/2026-05-26-arquati-pnc-regole-provvigioni-seed.sql`
3. Valorizzare **`gruppoProvvigione`** su ogni `ProductCategory` (vedi `database/2026-05-24-product-category-gruppo-provvigione.sql`).
4. Clear cache.
5. Alla creazione contratto: copia listino da opportunità → hook crea **Provvigione Consolidata** (+ eventuale Plus 5%).

## File codice

- `Services/ProvvigioneManager.php` — contesto margine, consolidata, +5%
- `Services/RegolaProvvigionaleCalculator.php` — match fasce margine
- `Hooks/Quote/ProvvigioneConsolidata.php` — dopo salvataggio contratto
- `Actions/Opportunity/CreateContratto.php` — campi listino sul contratto
