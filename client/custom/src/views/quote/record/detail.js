define('custom:views/quote/record/detail', ['sales:views/quote/record/detail'], function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'after:save', () => {
                setTimeout(() => {
                    if (this.getView('bottomPanels')) {
                        this.getView('bottomPanels').reRender();
                    }
                }, 500);
            });
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            this.bindProvvigioniTotaleRefresh();
        },

        bindProvvigioniTotaleRefresh: function () {
            const bottomPanels = this.getView('bottomPanels');

            if (!bottomPanels) {
                return;
            }

            const panel = bottomPanels.getView('provvigioni');

            if (!panel) {
                return;
            }

            const refreshQuote = () => {
                this.model.fetch().then(() => {
                    this.reRender();
                });
            };

            this.listenTo(panel, 'after:create after:remove', refreshQuote);

            const list = panel.getView('list');

            if (list) {
                this.listenTo(list, 'after:edit', refreshQuote);
            }
        },

    });

});
