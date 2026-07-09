// VERSIONE: 1.4.1 — prezzi listino/codice; niente inject DOM su righe (solo menu …)

define('custom:views/quote/fields/item-list', [
    'sales:views/quote/fields/item-list',
    'custom:handlers/quote/catalog-prices',
], function (Dep, CatalogPrices) {

    return Dep.extend({

        customItemView: 'custom:views/quote/record/item',

        setup: function () {
            this.customItemView = 'custom:views/quote/record/item';

            var originalCreateView = this.createView.bind(this);

            this.createView = function (name, view, options, callback) {
                options = options || {};

                if (name === 'itemList') {
                    options.itemView = 'custom:views/quote/record/item';
                }

                var viewStr = typeof view === 'string' ? view : '';
                var isSelectRecords = viewStr.indexOf('select-records') !== -1;

                if (isSelectRecords) {
                    options.entityType = 'Product';
                    options.scope = 'Product';
                    options.createButton = true;
                    view = 'custom:views/modals/select-product-for-quote';
                }

                var self = this;
                var wrappedCallback = callback;

                if (name === 'itemList') {
                    wrappedCallback = function (itemListView) {
                        self.ensureItemListHooks(itemListView);

                        if (callback) {
                            callback(itemListView);
                        }
                    };
                }

                return originalCreateView(name, view, options, wrappedCallback);
            };

            Dep.prototype.setup.call(this);

            this.bindCatalogPriceRefresh();

            this.dropdownItemList = this.dropdownItemList || [];

            var hasCreateProductAction = this.dropdownItemList.some(function (item) {
                return item && item.name === 'createProductDirect';
            });

            if (!hasCreateProductAction) {
                var addProductsIndex = this.dropdownItemList.findIndex(function (item) {
                    return item && item.name === 'addProducts';
                });

                var menuItem = {
                    name: 'createProductDirect',
                    label: 'Crea prodotto',
                };

                if (addProductsIndex >= 0) {
                    this.dropdownItemList.splice(addProductsIndex + 1, 0, menuItem);
                } else {
                    this.dropdownItemList.unshift(menuItem);
                }
            }

        },

        getLiveItemList: function () {
            var itemListView = this.getItemListView && this.getItemListView();

            if (itemListView && typeof itemListView.fetch === 'function') {
                var fetched = itemListView.fetch();

                if (fetched && fetched.itemList) {
                    return Espo.Utils.cloneDeep(fetched.itemList);
                }
            }

            return Espo.Utils.cloneDeep(this.model.get(this.name) || []);
        },

        syncItemListFromViews: function () {
            var itemList = this.getLiveItemList();

            this.model.set(this.name, itemList, {ui: true});

            return itemList;
        },

        ensureItemListHooks: function (itemListView) {
            itemListView = itemListView || (this.getItemListView && this.getItemListView());

            if (!itemListView) {
                if (!this._itemListHookRetries) {
                    this._itemListHookRetries = 0;
                }

                if (this._itemListHookRetries < 20) {
                    this._itemListHookRetries++;
                    setTimeout(function () {
                        this.ensureItemListHooks();
                    }.bind(this), 250);
                }

                return;
            }

            if (this._itemListHooksReady) {
                this.hookItemRowModels(itemListView);

                return;
            }

            this._itemListHooksReady = true;

            this.listenTo(itemListView, 'change', function (data) {
                if (data && data.itemField === 'product') {
                    setTimeout(function () {
                        this.applyCatalogPricesToItemList();
                    }.bind(this), 80);
                }

                this.hookItemRowModels(itemListView);
            });

            this.hookItemRowModels(itemListView);
        },

        hookItemRowModels: function (itemListView) {
            if (!itemListView || typeof itemListView.getItemListViews !== 'function') {
                return;
            }

            itemListView.getItemListViews().forEach(function (itemView) {
                if (!itemView || !itemView.model || itemView._catalogPriceHook) {
                    return;
                }

                itemView._catalogPriceHook = true;

                this.listenTo(itemView.model, 'change:productId', function () {
                    if (!itemView.model.get('productId')) {
                        return;
                    }

                    setTimeout(function () {
                        this.applyCatalogPricesToItemRow(itemView);
                    }.bind(this), 80);
                });

                this.listenTo(itemView.model, 'after-product-select', function () {
                    setTimeout(function () {
                        this.applyCatalogPricesToItemRow(itemView);
                    }.bind(this), 80);
                });
            }.bind(this));
        },

        applyCatalogPricesToItemRow: async function (itemView) {
            if (!itemView || !itemView.model) {
                return;
            }

            var productId = itemView.model.get('productId');

            if (!productId || !this.model.get('priceBookId')) {
                return;
            }

            var map = await CatalogPrices.fetchRows(this.model, [productId]);
            var row = map[productId];

            if (!row) {
                return;
            }

            var patch = CatalogPrices.patchFromRow(
                itemView.model.attributes,
                row,
                this.model.get('amountCurrency'),
                this.model
            );

            if (!Object.keys(patch).length) {
                return;
            }

            itemView.model.set(patch);

            if (itemView.calculationHandler) {
                itemView.calculationHandler.calculateItem(itemView.model);
            }

            this.syncItemListFromViews();
        },

        bindCatalogPriceRefresh: function () {
            if (this._catalogPriceRefreshBound) {
                return;
            }

            this._catalogPriceRefreshBound = true;

            this.listenTo(this.model, 'change:priceBookId', function () {
                if (this.mode === 'edit') {
                    this.applyCatalogPricesToItemList();
                }
            });

            this.listenTo(this.model, 'change:isTaxInclusive', function () {
                if (this.mode === 'edit') {
                    this.applyCatalogPricesToItemList();
                }
            });
        },

        actionCreateProductDirect: function () {
            this.openCreateArticleModal();
        },

        actionAddProducts: async function () {
            await Dep.prototype.actionAddProducts.call(this);
            await this.applyCatalogPricesToItemList();
        },

        applyCatalogPricesToItemList: async function () {
            if (!this.model.get('priceBookId')) {
                return;
            }

            var itemListView = this.getItemListView && this.getItemListView();
            var itemList = this.getLiveItemList();
            var productIds = [];

            itemList.forEach(function (item) {
                if (item.productId) {
                    productIds.push(item.productId);
                }
            });

            if (!productIds.length) {
                return;
            }

            var pricesByProduct = await CatalogPrices.fetchRows(this.model, productIds);
            var currency = this.model.get('amountCurrency');
            var changed = false;

            itemList.forEach(function (item, index) {
                if (!item.productId || !pricesByProduct[item.productId]) {
                    return;
                }

                var patch = CatalogPrices.patchFromRow(
                    item,
                    pricesByProduct[item.productId],
                    currency,
                    this.model
                );

                Object.keys(patch).forEach(function (key) {
                    item[key] = patch[key];
                    changed = true;
                });

                if (itemListView && typeof itemListView.getItemListViews === 'function') {
                    var itemView = itemListView.getItemListViews()[index];

                    if (itemView && itemView.model) {
                        itemView.model.set(patch);

                        if (itemView.calculationHandler) {
                            itemView.calculationHandler.calculateItem(itemView.model);
                        }
                    }
                }
            });

            if (!changed) {
                return;
            }

            this.model.set(this.name, itemList, {ui: true});
            this.calculateAmount();
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            if (this.mode !== 'edit') {
                return;
            }

            setTimeout(function () {
                this.ensureItemListHooks();
            }.bind(this), 200);
        },

        openCreateArticleModal: function () {
            this.createView(
                'quickCreateProductModal',
                'views/modals/edit',
                {
                    scope: 'Product',
                    attributes: {},
                },
                function (view) {
                    this.listenToOnce(view, 'after:save', function () {
                        Espo.Ui.success('Articolo creato. Ora selezionalo con il tasto + nella lista articoli.');
                    }, this);

                    view.render();
                }.bind(this)
            );
        },
    });
});
