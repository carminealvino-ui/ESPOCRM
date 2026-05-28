// ========================================
// VERSIONE: 1.1.0
// DATA: 2026-05-26
// FILE: custom/Espo/Custom/Resources/client/custom/src/views/quote/fields/item-list.js
// ========================================

/* global define */

define('custom:views/quote/fields/item-list', ['sales:views/quote/fields/item-list'], function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

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
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            if (this.mode !== 'edit') {
                return;
            }

            this.decorateNativeAddButton();
            this.injectCreateArticleButton();
            setTimeout(function () {
                this.decorateNativeAddButton();
                this.injectCreateArticleButton();
            }.bind(this), 200);

            this.bindButtonObserver();
        },

        bindButtonObserver: function () {
            if (this._buttonObserver) {
                return;
            }

            this._buttonObserver = new MutationObserver(function () {
                this.decorateNativeAddButton();
                this.injectCreateArticleButton();
            }.bind(this));

            this._buttonObserver.observe(this.el, { childList: true, subtree: true });
        },

        decorateNativeAddButton: function () {
            var $buttons = this.$el.find('button.btn');
            var done = false;

            $buttons.each(function (i, el) {
                var $btn = $(el);
                var text = ($btn.text() || '').replace(/\s+/g, ' ').trim();

                if (text === '+') {
                    $btn.addClass('btn-create-article-native');
                    $btn.text('+ Crea articolo');
                    $btn.attr('title', 'Crea articolo');
                    done = true;
                    return false;
                }
            });

            return done;
        },

        injectCreateArticleButton: function () {
            if (this.decorateNativeAddButton()) {
                return;
            }

            if (this.$el.find('.btn-create-article').length) {
                return;
            }

            var $anchorGroup = this.$el.find('.btn-group').first();
            var $container = $anchorGroup.length ? $anchorGroup.parent() : this.$el;

            var $button = $('<button>')
                .attr('type', 'button')
                .addClass('btn btn-default btn-sm btn-create-article')
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
