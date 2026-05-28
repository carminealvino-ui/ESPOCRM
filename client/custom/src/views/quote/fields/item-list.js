// Contratto: «Crea prodotto» sotto il titolo Prodotti/Articoli (pulsante, a sinistra).
define('custom:views/quote/fields/item-list', ['sales:views/quote/fields/item-list'], function (Dep) {

    return Dep.extend({

        events: {
            'click .btn-group .dropdown-toggle': function () {
                setTimeout(function () {
                    this.injectCreateProductMenuItem();
                }.bind(this), 0);
            },
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            this.dropdownItemList = this.dropdownItemList || [];

            if (!this.dropdownItemList.some(function (item) {
                return item && item.name === 'createProductDirect';
            })) {
                var idx = this.dropdownItemList.findIndex(function (item) {
                    return item && item.name === 'addProducts';
                });

                var menuItem = {
                    name: 'createProductDirect',
                    label: 'Crea prodotto',
                };

                if (idx >= 0) {
                    this.dropdownItemList.splice(idx + 1, 0, menuItem);
                } else {
                    this.dropdownItemList.unshift(menuItem);
                }
            }

            var originalCreateView = this.createView.bind(this);

            this.createView = function (name, view, options, callback) {
                options = options || {};

                var viewStr = typeof view === 'string' ? view : '';

                if (viewStr.indexOf('select-records') !== -1) {
                    options.entityType = 'Product';
                    options.scope = 'Product';
                    options.createButton = true;
                    view = 'custom:views/modals/select-product-for-quote';
                }

                return originalCreateView(name, view, options, callback);
            };
        },

        actionCreateProductDirect: function () {
            this.openCreateProductModal();
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            if (this.mode === 'list' || this.mode === 'listLinked') {
                return;
            }

            this.injectCreateProductMenuItem();
            setTimeout(function () {
                this.injectCreateProductMenuItem();
            }.bind(this), 200);
        },

        injectCreateProductMenuItem: function () {
            var $menus = this.$el.find('.dropdown-menu:visible');

            if (!$menus.length) {
                return;
            }

            var self = this;

            $menus.each(function (i, menuEl) {
                var $menu = $(menuEl);

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
                    $menu.prepend($item);
                }

                self.listenToDom($link, 'click', function (e) {
                    e.preventDefault();
                    self.openCreateProductModal();
                });
            });
        },

        openCreateProductModal: function () {
            this.createView(
                'quickCreateProductModal',
                'views/modals/edit',
                {
                    scope: 'Product',
                    attributes: {},
                },
                function (view) {
                    this.listenToOnce(view, 'after:save', function () {
                        Espo.Ui.success('Prodotto creato. Aggiungilo con + nella lista articoli.');
                    }, this);

                    view.render();
                }.bind(this)
            );
        },
    });
});
