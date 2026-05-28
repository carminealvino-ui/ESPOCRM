// ========================================
// VERSIONE: 1.1.0
// DATA: 2026-05-26
// FILE: custom/Espo/Custom/Resources/client/custom/src/views/quote/fields/item-list.js
// ========================================

/* global define */

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

            this.actionList = this.actionList || [];

            var hasCreateProductAction = this.actionList.some(function (item) {
                return item && item.name === 'createProductDirect';
            });

            if (!hasCreateProductAction) {
                this.actionList.unshift({
                    name: 'createProductDirect',
                    label: 'Crea prodotto',
                });
            }

            var originalCreateView = this.createView.bind(this);

            this.createView = function (name, view, options, callback) {
                options = options || {};

                var viewStr = typeof view === 'string' ? view : '';
                var isSelectRecords = viewStr.indexOf('select-records') !== -1;

                // Nel contesto item-list del contratto, la modale select-records
                // viene usata per i prodotti: forziamo scope Product.
                if (isSelectRecords) {
                    options.entityType = 'Product';
                    options.scope = 'Product';
                    options.createButton = true;
                    view = 'custom:views/modals/select-product-for-quote';
                }

                return originalCreateView(name, view, options, callback);
            };

            this._onDocumentClick = function () {
                setTimeout(function () {
                    this.injectCreateProductMenuItemGlobal();
                }.bind(this), 0);
            }.bind(this);

            $(document).on('click.create-product-menu-' + this.cid, this._onDocumentClick);
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
            }.bind(this), 200);

            this.bindButtonObserver();
            this.injectCreateProductMenuItem();
            this.injectCreateProductMenuItemGlobal();
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
