// VERSIONE: 1.4.0 — sync prezzi sulle righe live (non solo itemList del modello padre)

define('custom:views/quote/fields/item-list', [
    'sales:views/quote/fields/item-list',
    'custom:handlers/quote/catalog-prices',
], function (Dep, CatalogPrices) {

    return Dep.extend({

        customItemView: 'custom:views/quote/record/item',

        events: {
            'click .btn-group .dropdown-toggle': function () {
                setTimeout(function () {
                    this.injectCreateProductMenuItem();
                }.bind(this), 0);
            },
        },

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

            this._onDocumentClick = function () {
                setTimeout(function () {
                    this.injectCreateProductMenuItemGlobal();
                }.bind(this), 0);
            }.bind(this);

            $(document).on('click.create-product-menu-' + this.cid, this._onDocumentClick);
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
            this.refreshItemRowFields(itemView, Object.keys(patch));
        },

        refreshItemRowFields: function (itemView, fieldNames) {
            if (!itemView || typeof itemView.getFieldView !== 'function') {
                return;
            }

            fieldNames.forEach(function (field) {
                var fieldView = itemView.getFieldView(field);

                if (fieldView && typeof fieldView.reRender === 'function') {
                    fieldView.reRender();
                }
            });
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

        onRemove: function () {
            if (this._onDocumentClick) {
                $(document).off('click.create-product-menu-' + this.cid, this._onDocumentClick);
            }

            Dep.prototype.onRemove.call(this);
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

            if (itemListView && typeof itemListView.getItemListViews === 'function') {
                itemListView.getItemListViews().forEach(function (itemView) {
                    this.refreshItemRowFields(itemView, ['listPrice', 'prezzoCodice', 'unitPrice']);
                }.bind(this));
            }

            this.calculateAmount();
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            if (this.mode !== 'edit') {
                return;
            }

            this.injectCreateArticleButton();
            setTimeout(function () {
                this.injectCreateArticleButton();
                this.injectCreateProductMenuItem();
                this.injectCreateProductMenuItemGlobal();
                this.ensureItemListHooks();
            }.bind(this), 200);

            this.bindButtonObserver();
            this.injectCreateProductMenuItem();
            this.injectCreateProductMenuItemGlobal();
            this.ensureItemListHooks();
        },

        bindButtonObserver: function () {
            if (this._buttonObserver) {
                return;
            }

            this._buttonObserver = new MutationObserver(function () {
                this.injectCreateArticleButton();
                this.injectCreateProductMenuItem();
                this.injectCreateProductMenuItemGlobal();
            }.bind(this));

            this._buttonObserver.observe(this.el, { childList: true, subtree: true });
        },

        injectCreateArticleButton: function () {
            if (this.$el.find('.btn-create-article').length) {
                return;
            }

            var $anchorGroup = this.$el.find('.btn-group').first();
            var $container = $anchorGroup.length ? $anchorGroup.parent() : this.$el;

            var $button = $('<button>')
                .attr('type', 'button')
                .addClass('btn btn-primary btn-sm btn-create-article')
                .css('margin-left', '8px')
                .append($('<span>').addClass('fas fa-plus'))
                .append(document.createTextNode(' Crea articolo'));

            if ($anchorGroup.length) {
                $anchorGroup.after($button);
            } else {
                $container.append($button);
            }

            this.listenToDom($button, 'click', function () {
                this.openCreateArticleModal();
            }.bind(this));
        },

        injectCreateProductMenuItem: function () {
            var $menus = this.$el.find('.dropdown.open .dropdown-menu:visible');

            if (!$menus.length) {
                $menus = this.$el.find('.dropdown-menu');
            }

            if (!$menus.length) {
                return;
            }

            var self = this;

            $menus.each(function (i, menuEl) {
                var $menu = $(menuEl);

                if ($menu.find('.action[data-action="createProductDirect"]').length) {
                    return;
                }

                var $first = $menu.find('li, .list-group-item').first();
                var $item = $('<li>');
                var $link = $('<a>')
                    .attr('href', 'javascript:')
                    .addClass('action')
                    .attr('data-action', 'createProductDirect')
                    .text('Crea prodotto');

                $item.append($link);

                if ($first.length) {
                    $first.after($item);
                } else {
                    $menu.append($item);
                }

                self.listenToDom($link, 'click', function (e) {
                    e.preventDefault();
                    self.openCreateArticleModal();
                });
            });
        },

        injectCreateProductMenuItemGlobal: function () {
            var self = this;
            var $menus = $('.dropdown-menu:visible');

            $menus.each(function (i, menuEl) {
                var $menu = $(menuEl);

                if (!$menu.find('.action[data-action="addProducts"]').length) {
                    return;
                }

                if ($menu.find('.action[data-action="createProductDirect"]').length) {
                    return;
                }

                var $base = $menu.find('.action[data-action="addProducts"]').first().closest('li');
                var $item = $('<li>');
                var $link = $('<a>')
                    .attr('href', 'javascript:')
                    .addClass('action')
                    .attr('data-action', 'createProductDirect')
                    .text('Crea prodotto');

                $item.append($link);

                if ($base.length) {
                    $base.after($item);
                } else {
                    $menu.append($item);
                }

                self.listenToDom($link, 'click', function (e) {
                    e.preventDefault();
                    self.openCreateArticleModal();
                });
            });
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
