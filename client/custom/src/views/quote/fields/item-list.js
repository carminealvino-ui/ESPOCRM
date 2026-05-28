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

            this.injectCreateProductButton();
            setTimeout(function () {
                this.injectCreateProductButton();
                this.injectCreateProductMenuItem();
            }.bind(this), 200);
        },

        injectCreateProductButton: function () {
            if (this.$el.find('.btn-create-product').length) {
                return;
            }

            var $button = $('<button>')
                .attr('type', 'button')
                .addClass('btn btn-primary btn-sm btn-create-product')
                .text('Crea prodotto');

            this.listenToDom($button, 'click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                this.openCreateProductModal();
            }.bind(this));

            var $panel = this.$el.closest('.panel, .bottom-panel');

            if (!$panel.length) {
                var $bar = $('<div class="mec-crea-prodotto-toolbar" style="margin-bottom:10px;"></div>');
                $bar.append($button);
                this.$el.prepend($bar);

                return;
            }

            var $toolbar = $panel.children('.mec-crea-prodotto-toolbar').first();

            if (!$toolbar.length) {
                $toolbar = $('<div class="mec-crea-prodotto-toolbar" style="margin:0 0 12px 0;clear:both;"></div>');
                var $heading = $panel.children('.panel-heading, .panel-header').first();

                if ($heading.length) {
                    $heading.after($toolbar);
                } else {
                    var $body = $panel.children('.panel-body').first();
                    if ($body.length) {
                        $body.prepend($toolbar);
                    } else {
                        $panel.prepend($toolbar);
                    }
                }
            }

            $toolbar.empty().append($button);
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
