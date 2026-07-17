define('custom:handlers/quote/refresh-provvigioni-on-save', [], function () {

    return class {

        constructor(view) {
            this.view = view;
        }

        process() {
            if (this.view.scope !== 'Quote') {
                return;
            }

            var model = this.view.model;

            this.view.listenTo(model, 'after:save', function () {
                if (!model.get('opportunityId')) {
                    return;
                }

                setTimeout(function () {
                    this.refreshProvvigioniUi();
                }.bind(this), 400);
            }.bind(this));

            this.view.listenTo(this.view, 'after:render', function () {
                this.bindProvvigioniPanel();
            }.bind(this));
        }

        refreshProvvigioniUi() {
            var view = this.view;
            var model = view.model;
            var bottomPanels = view.getView('bottomPanels');

            model.fetch().then(function () {
                if (bottomPanels) {
                    var provPanel = bottomPanels.getView('provvigioni');

                    if (provPanel) {
                        if (provPanel.collection && typeof provPanel.collection.fetch === 'function') {
                            provPanel.collection.fetch();
                        }

                        if (typeof provPanel.actionRefresh === 'function') {
                            provPanel.actionRefresh();
                        }
                    }

                    bottomPanels.reRender();
                }

                view.reRender();
            });
        }

        bindProvvigioniPanel() {
            var view = this.view;
            var bottomPanels = view.getView('bottomPanels');

            if (!bottomPanels || bottomPanels._provvigioniRefreshBound) {
                return;
            }

            bottomPanels._provvigioniRefreshBound = true;

            var panel = bottomPanels.getView('provvigioni');

            if (!panel) {
                return;
            }

            var refreshQuote = function () {
                view.model.fetch().then(function () {
                    view.reRender();
                });
            };

            view.listenTo(panel, 'after:create after:remove', refreshQuote);

            var list = panel.getView('list');

            if (list) {
                view.listenTo(list, 'after:edit', refreshQuote);
            }
        }
    };
});
