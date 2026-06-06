// ========================================
// VERSIONE: 1.1.0
// DATA: 2026-06-06
// Popola Prezzo di Listino e Prezzo Codice alla selezione prodotto.
// ========================================

/* global define */

define('custom:views/quote/record/item', ['sales:views/quote/record/item'], function (Dep) {

    return Dep.extend({

        async selectProduct(product) {
            await Dep.prototype.selectProduct.call(this, product);

            if (!this.isQuote()) {
                return;
            }

            await this.applyCatalogPrices(product);
        },

        applyCatalogPrices: async function (product) {
            var productId = product.id || this.model.get('productId');

            if (!productId) {
                return;
            }

            try {
                var response = await Espo.Ajax.postRequest('Quote/getItemCatalogPrices', {
                    priceBookId: this.parentModel.get('priceBookId'),
                    productIds: [productId],
                    isTaxInclusive: this.parentModel.get('isTaxInclusive'),
                    taxId: this.parentModel.get('taxId'),
                    dateQuoted: this.parentModel.get('dateQuoted'),
                    aliquotaIVA: this.parentModel.get('aliquotaIVA'),
                });

                var row = (response || [])[0];

                if (!row) {
                    return;
                }

                var patch = {};
                var currency = this.parentModel.get('amountCurrency');

                if (row.listPrice != null && row.listPrice > 0) {
                    patch.listPrice = row.listPrice;
                    patch.listPriceCurrency = currency;
                }

                if (row.prezzoCodice != null && row.prezzoCodice > 0) {
                    patch.prezzoCodice = row.prezzoCodice;
                    patch.prezzoCodiceCurrency = currency;
                }

                if (!Object.keys(patch).length) {
                    return;
                }

                this.model.set(patch);
                this.calculationHandler.calculateItem(this.model);
                this.model.trigger('after-product-select');
            } catch (error) {
                console.error('Catalog prices fetch failed', error);
                Espo.Ui.error('Impossibile caricare prezzi listino/codice dal listino selezionato.');
            }
        },
    });
});
