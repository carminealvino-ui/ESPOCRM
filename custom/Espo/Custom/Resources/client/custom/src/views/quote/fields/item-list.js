// Contratto: lista articoli + solo pulsante «Crea articolo».
define('custom:views/quote/fields/item-list', ['sales:views/quote/fields/item-list'], function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

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

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            if (this.mode !== 'edit') {
                return;
            }

            this.injectCreateArticleButton();
            setTimeout(function () {
                this.injectCreateArticleButton();
            }.bind(this), 200);

            if (!this._buttonObserver) {
                this._buttonObserver = new MutationObserver(function () {
                    this.injectCreateArticleButton();
                }.bind(this));
                this._buttonObserver.observe(this.el, { childList: true, subtree: true });
            }
        },

        injectCreateArticleButton: function () {
            this.$el.find('.btn-create-article').remove();

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
                $container.prepend($button);
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
                        Espo.Ui.success('Articolo creato. Aggiungilo con + nella lista articoli.');
                    }, this);

                    view.render();
                }.bind(this)
            );
        },
    });
});
