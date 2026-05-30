// Fallback: stesso punto del + (a sinistra), se item-list custom non è caricata.
define('custom:handlers/quote/crea-prodotto-articoli', [], function () {

    return class {

        constructor(view) {
            this.view = view;
        }

        process() {
            if (this.view.scope !== 'Quote') {
                return;
            }

            var run = this.placeButton.bind(this);

            this.view.listenTo(this.view, 'after:render', run);

            var n = 0;
            var timer = setInterval(function () {
                n++;
                run();

                if (n >= 15 || this.view.$el.find('.field-itemList .btn-create-product, [data-name="itemList"] .btn-create-product').length) {
                    clearInterval(timer);
                }
            }.bind(this), 400);
        }

        placeButton() {
            this.view.$el.find('.mec-crea-prodotto-toolbar').remove();

            var $field = this.view.$el.find('.field-itemList, [data-name="itemList"]').first();

            if (!$field.length) {
                return;
            }

            // Legacy global script (custom-product-button.js) — rimuovi se ancora in cache browser
            $field.find('.custom-new-product-btn').remove();

            if ($field.find('.btn-create-product').length) {
                return;
            }

            var $anchorGroup = $field.find('.btn-group').first();
            var $btn = $('<button type="button" class="btn btn-primary btn-sm btn-create-product" style="margin-right:8px;">' +
                '<span class="fas fa-plus"></span> Crea prodotto</button>');

            var self = this;
            $btn.on('click', function (e) {
                e.preventDefault();
                self.openProductModal();
            });

            if ($anchorGroup.length) {
                $anchorGroup.before($btn);
            } else {
                $field.prepend($('<div style="margin-bottom:8px;"></div>').append($btn));
            }
        }

        openProductModal() {
            this.view.createView(
                'quickCreateProductModal',
                'views/modals/edit',
                { scope: 'Product', attributes: {} },
                function (view) {
                    view.render();
                    this.view.listenToOnce(view, 'after:save', function () {
                        Espo.Ui.success('Prodotto creato. Aggiungilo con + nella lista articoli.');
                    }, this);
                }.bind(this)
            );
        }
    };
});
