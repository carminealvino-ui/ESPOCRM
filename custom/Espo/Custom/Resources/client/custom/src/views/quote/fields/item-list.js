// Contratto: lista articoli + pulsante «Crea prodotto» (anche in sola lettura).
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
            setTimeout(function () {
                this.injectCreateProductButton();
            }.bind(this), 800);

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

            var $button = $('<button>')
                .attr('type', 'button')
                .addClass('btn btn-primary btn-sm btn-create-product')
                .append($('<span>').addClass('fas fa-cube'))
                .append(document.createTextNode(' Crea prodotto'));

            this.listenToDom($button, 'click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                this.openCreateProductModal();
            }.bind(this));

            var $panel = this.$el.closest('.panel, .bottom-panel, .field[data-name="itemList"]');
            var $heading = $panel.find('.panel-heading, .panel-header').first();

            if (!$heading.length) {
                $panel.children().each(function () {
                    var $child = $(this);
                    var tag = (this.tagName || '').toLowerCase();

                    if (tag === 'div' && ($child.hasClass('panel-heading') || $child.find('.panel-title').length)) {
                        $heading = $child;
                        return false;
                    }
                });
            }

            if ($heading.length) {
                var $slot = $heading.find('.mec-crea-prodotto-slot').first();

                if (!$slot.length) {
                    $slot = $('<div class="pull-right mec-crea-prodotto-slot" style="margin-left:12px;"></div>');
                    var $title = $heading.find('.panel-title, .strong').first();

                    if ($title.length) {
                        $title.after($slot);
                    } else {
                        $heading.append($slot);
                    }
                }

                $slot.empty().append($button);

                return;
            }

            var $fieldLabel = this.$el.closest('.field').find('> .field-label, .field-header').first();

            if ($fieldLabel.length) {
                var $labelSlot = $fieldLabel.find('.mec-crea-prodotto-slot').first();

                if (!$labelSlot.length) {
                    $labelSlot = $('<span class="mec-crea-prodotto-slot" style="float:right;"></span>');
                    $fieldLabel.append($labelSlot);
                }

                $labelSlot.empty().append($button);

                return;
            }

            var $anchorGroup = this.$el.find('.btn-group').first();
            var $bar = $('<div class="mec-item-list-toolbar" style="margin-bottom:8px;"></div>');
            $bar.append($button);

            if ($anchorGroup.length) {
                $anchorGroup.before($bar);
            } else {
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
