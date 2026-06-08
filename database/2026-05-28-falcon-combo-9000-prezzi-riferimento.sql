-- Valori listino PDF promo 1+1 = COMBO 9000+9000 (solo riferimento; inserire da UI se preferito).
-- listino 5200 IVI, codice 4400 IVI → netti 4727.27 / 4000.00
-- Eseguire solo se i campi custom esistono già su product.

UPDATE product
SET
    prezzo_codice = 4000.00,
    prezzo_codice_iva_inclusa = 4400.00,
    list_price = 4727.27,
    unit_price = 4727.27,
    prezzo_listino_iva_inclusa = 5200.00,
    modified_at = NOW()
WHERE deleted = 0
  AND (
    denominazione LIKE '%COMBO 9000+9000%'
    OR name LIKE '%COMBO 9000+9000%'
  );
