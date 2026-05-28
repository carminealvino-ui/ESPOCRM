// Pulsante «Crea prodotto» nel pannello Articoli (bottom panel Sales Pack).
define('custom:handlers/quote/articoli-crea-prodotto-setup', [], function () {

    return class {

        constructor(view) {
            this.recordView = view;
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

            var attempts = 0;
            var timer = setInterval(function () {
                attempts++;
                run();

                if (attempts >= 12 || $('.btn-create-product-articoli').length) {
                    clearInterval(timer);
                }
            }, 500);
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
            var recordView = this.recordView;

            if (recordView.getView) {
                var direct = recordView.getView('items');

                if (direct) {
                    return direct;
                }
            }

            var views = recordView.nestedViews || [];

            if (typeof recordView.getNestedViews === 'function') {
                views = recordView.getNestedViews();
            }

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

        findArticoliPanel() {
            var $root = this.recordView.$el;
            var $panel = $root.find('.panel[data-key="items"], .panel.panel-items').first();

            if ($panel.length) {
                return $panel;
            }

            $root.find('table').each(function () {
                var text = ($(this).text() || '').toLowerCase();

                if (text.indexOf('prezzo codice') !== -1 || text.indexOf('prezzo di listino') !== -1) {
                    $panel = $(this).closest('.panel, .bottom-panel, .tab-pane');

                    return false;
                }
            });

            if ($panel && $panel.length) {
                return $panel;
            }

            return $root.find('.bottom-panel').filter(function () {
                var $p = $(this);

                return $p.find('[data-name="itemList"]').length ||
                    $p.text().toLowerCase().indexOf('articoli') !== -1;
            }).first();
        }

        injectDomButton() {
            if (this.recordView.$el.find('.btn-create-product-articoli').length) {
                return;
            }

            var $panel = this.findArticoliPanel();

            if (!$panel || !$panel.length) {
                return;
            }

            var self = this;
            var $btn = $('<button type="button" class="btn btn-primary btn-sm btn-create-product-articoli">' +
                '<span class="fas fa-cube"></span> Crea prodotto</button>');

            $btn.on('click', function (e) {
                e.preventDefault();
                self.openProductModal();
            });

            var $heading = $panel.find('.panel-heading, .panel-header, .panel-title-container').first();

            if ($heading.length) {
                $heading.append(
                    $('<div class="pull-right mec-crea-prodotto-slot" style="margin-left:12px;"></div>').append($btn)
                );

                return;
            }

            var $table = $panel.find('table').first();

            if ($table.length) {
                $table.before(
                    $('<div class="mec-item-list-toolbar" style="margin-bottom:8px;text-align:right;"></div>').append($btn)
                );
            }
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
