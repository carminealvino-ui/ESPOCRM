// Pulsante «Crea prodotto» nel pannello Articoli (bottom panel Sales Pack).
define('custom:handlers/quote/articoli-crea-prodotto-setup', [], function () {

    return class {

        constructor(recordView) {
            this.recordView = recordView;
        }

        process() {
            if (this.recordView.scope !== 'Quote') {
                return;
            }

            var run = this.inject.bind(this);

            this.recordView.listenTo(this.recordView, 'after:render', run);
            this.recordView.listenTo(this.recordView.model, 'sync', function () {
                setTimeout(run, 400);
            });
        }

        inject() {
            var fieldView = this.recordView.getFieldView('itemList');

            if (fieldView && typeof fieldView.injectCreateProductButton === 'function') {
                fieldView.injectCreateProductButton();

                return;
            }

            var itemsPanelView = this.findItemsPanelView();

            if (itemsPanelView) {
                fieldView = itemsPanelView.getView('itemList');

                if (fieldView && typeof fieldView.injectCreateProductButton === 'function') {
                    fieldView.injectCreateProductButton();

                    return;
                }
            }

            this.injectDomButton();
        }

        findItemsPanelView() {
            var views = this.recordView.nestedViews || this.recordView.getNestedViews?.() || [];

            for (var i = 0; i < views.length; i++) {
                var view = views[i];

                if (!view) {
                    continue;
                }

                if (view.name === 'items' || (view.$el && view.$el.hasClass('panel-items'))) {
                    return view;
                }

                if (view.getView && view.getView('itemList')) {
                    return view;
                }
            }

            return null;
        }

        injectDomButton() {
            var $root = this.recordView.$el;
            var $panel = $root.find('.bottom-panel').filter(function () {
                var $p = $(this);

                return $p.find('[data-name="itemList"]').length ||
                    $p.text().toLowerCase().indexOf('articoli') !== -1;
            }).first();

            if (!$panel.length) {
                $panel = $root.find('.field[data-name="itemList"]').closest('.panel, .bottom-panel').first();
            }

            if (!$panel.length) {
                return;
            }

            var $heading = $panel.find('.panel-heading, .panel-header').first();

            if (!$heading.length) {
                return;
            }

            if ($heading.find('.btn-create-product-articoli').length) {
                return;
            }

            var self = this;
            var $btn = $('<button type="button" class="btn btn-primary btn-sm btn-create-product-articoli">' +
                '<span class="fas fa-cube"></span> Crea prodotto</button>');

            $heading.append(
                $('<div class="pull-right mec-crea-prodotto-slot" style="margin-left:12px;"></div>').append($btn)
            );

            $btn.on('click', function (e) {
                e.preventDefault();
                self.openProductModal();
            });
        }

        openProductModal() {
            this.recordView.createView(
                'quickCreateProductModal',
                'views/modals/edit',
                {
                    scope: 'Product',
                    attributes: {},
                },
                function (view) {
                    view.render();
                    this.recordView.listenToOnce(view, 'after:save', function () {
                        Espo.Ui.success('Prodotto creato. Aggiungilo con + nella lista articoli.');
                    }, this);
                }.bind(this)
            );
        }
    };
});
