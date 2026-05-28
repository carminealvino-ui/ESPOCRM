// Pulsante «Crea prodotto» sotto il titolo Prodotti/Articoli (sempre visibile).
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

            var attempts = 0;
            var timer = setInterval(function () {
                attempts++;
                run();

                if (attempts >= 20 || this.view.$el.find('.mec-crea-prodotto-toolbar .btn-create-product').length) {
                    clearInterval(timer);
                }
            }.bind(this), 400);
        }

        findItemsPanel() {
            var $found = null;

            this.view.$el.find('table').each(function () {
                var text = ($(this).text() || '').toLowerCase();

                if (text.indexOf('prezzo codice') !== -1 || text.indexOf('prezzo di listino') !== -1) {
                    $found = $(this).closest('.panel, .bottom-panel');

                    return false;
                }
            });

            if ($found && $found.length) {
                return $found;
            }

            return this.view.$el.find('.panel[data-key="items"], .bottom-panel').filter(function () {
                return $(this).find('[data-name="itemList"], .field-itemList').length;
            }).first();
        }

        placeButton() {
            if (this.view.$el.find('.mec-crea-prodotto-toolbar .btn-create-product').length) {
                return;
            }

            var $panel = this.findItemsPanel();

            if (!$panel || !$panel.length) {
                return;
            }

            var $toolbar = $panel.children('.mec-crea-prodotto-toolbar');

            if (!$toolbar.length) {
                $toolbar = $('<div class="mec-crea-prodotto-toolbar" style="margin:0 0 12px 0;clear:both;"></div>');
                var $heading = $panel.children('.panel-heading, .panel-header').first();

                if ($heading.length) {
                    $heading.after($toolbar);
                } else {
                    $panel.children('.panel-body').first().prepend($toolbar);
                }
            }

            var self = this;
            var $btn = $('<button type="button" class="btn btn-primary btn-sm btn-create-product">Crea prodotto</button>');

            $btn.on('click', function (e) {
                e.preventDefault();
                self.openProductModal();
            });

            $toolbar.empty().append($btn);
        }

        openProductModal() {
            this.view.createView(
                'quickCreateProductModal',
                'views/modals/edit',
                {
                    scope: 'Product',
                    attributes: {},
                },
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
