// VERSIONE: 1.3.0 — prezzi listino/codice + unitPrice IVA inclusa

define('custom:views/quote/record/item', [
    'sales:views/quote/record/item',
    'custom:handlers/quote/catalog-prices',
], function (Dep, CatalogPrices) {

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

            if (!productId || !this.parentModel.get('priceBookId')) {
                return;
            }

            var map = await CatalogPrices.fetchRows(this.parentModel, [productId]);
            var row = map[productId];

            if (!row) {
                row = CatalogPrices.buildRowFromProduct(
                    this.parentModel,
                    product.attributes || product
                );
            }

            var patch = CatalogPrices.patchFromRow(
                this.model.attributes,
                row,
                this.parentModel.get('amountCurrency'),
                this.parentModel
            );

            if (!Object.keys(patch).length) {
                Espo.Ui.warning('Nessun prezzo listino/codice trovato per questo prodotto nel listino selezionato.');

                return;
            }

            this.model.set(patch);
            this.calculationHandler.calculateItem(this.model);
            this.model.trigger('after-product-select');
        },
    });
});
