// Contratto: «Crea prodotto» a sinistra del + (come implementazione originale item-list).
define('custom:views/quote/fields/item-list', ['sales:views/quote/fields/item-list'], function (Dep) {

    return Dep.extend({

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

            if (!this._buttonObserver) {
                this._buttonObserver = new MutationObserver(function () {
                    this.injectCreateProductButton();
                    this.injectCreateProductMenuItem();
                }.bind(this));
                this._buttonObserver.observe(this.el, { childList: true, subtree: true });
            }
        },

        injectCreateProductButton: function () {
            if (this.$el.find('.btn-create-product').length) {
                return;
            }

            var $anchorGroup = this.$el.find('.btn-group').first();

            var $button = $('<button>')
                .attr('type', 'button')
                .addClass('btn btn-primary btn-sm btn-create-product')
                .css('margin-right', '8px')
                .append($('<span>').addClass('fas fa-plus'))
                .append(document.createTextNode(' Crea prodotto'));

            this.listenToDom($button, 'click', function (e) {
                e.preventDefault();
                this.openCreateProductModal();
            }.bind(this));

            if ($anchorGroup.length) {
                $anchorGroup.before($button);
            } else {
                var $bar = $('<div class="mec-item-list-actions" style="margin-bottom:8px;"></div>');
                $bar.append($button);
                this.$el.prepend($bar);
            }
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
