// VERSIONE: 1.4.0 — prezzi listino/codice + Voce (name) da prodotto

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

            this.listenTo(this, 'after:render', this.ensureItemName, this);
            this.listenTo(this.model, 'change:productId', this.ensureItemName, this);
            this.listenTo(this.model, 'change:productName', this.ensureItemName, this);
        },

        ensureItemName: function () {
            var name = String(this.model.get('name') || '').trim();

            if (name) {
                return;
            }

            var fromProduct = String(this.model.get('productName') || '').trim();

            if (fromProduct) {
                this.model.set('name', fromProduct, {ui: true});

                return;
            }

            var productId = this.model.get('productId');

            if (!productId || this._ensureItemNamePending === productId) {
                return;
            }

            this._ensureItemNamePending = productId;

            Espo.Ajax.getRequest('Product/' + productId, {select: 'name'})
                .then(function (response) {
                    var productName = String((response && response.name) || '').trim();

                    if (productName && !String(this.model.get('name') || '').trim()) {
                        this.model.set('name', productName, {ui: true});
                    }
                }.bind(this))
                .always(function () {
                    if (this._ensureItemNamePending === productId) {
                        this._ensureItemNamePending = null;
                    }
                }.bind(this));
        },

        async selectProduct(product) {
            await Dep.prototype.selectProduct.call(this, product);

            if (!this.isQuote()) {
                return;
            }

            this.ensureItemName();
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
            this.ensureItemName();
            this.calculationHandler.calculateItem(this.model);
            this.model.trigger('after-product-select');
        },
    });
});
