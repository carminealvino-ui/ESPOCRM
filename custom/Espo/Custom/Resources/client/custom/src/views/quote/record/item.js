// VERSIONE: 1.5.1 — prezzi listino/codice; Voce opzionale; prodotto orfano

define('custom:views/quote/record/item', [
    'sales:views/quote/record/item',
    'custom:handlers/quote/catalog-prices',
], function (Dep, CatalogPrices) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            if (!this.isQuote()) {
                return;
            }

            if (this.model) {
                this.model.setFieldParam('name', 'required', false);
            }

            this.verifyProductLink();
        },

        verifyProductLink: function () {
            var productId = this.model.get('productId');

            if (!productId || this._verifyProductLinkPending === productId) {
                return;
            }

            this._verifyProductLinkPending = productId;

            Espo.Ajax.getRequest('Product/' + productId, {select: 'id'})
                .catch(function () {
                    this.model.set({
                        productId: null,
                        productName: null,
                    }, {ui: true});

                    Espo.Ui.warning('Prodotto collegato non più disponibile: la riga resta come voce manuale.');
                }.bind(this))
                .always(function () {
                    if (this._verifyProductLinkPending === productId) {
                        this._verifyProductLinkPending = null;
                    }
                }.bind(this));
        },

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
