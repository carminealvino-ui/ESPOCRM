define('custom:views/quote/record/edit', ['sales:views/quote/record/edit'], function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'after:save', function () {
                if (!this.model.get('opportunityId')) {
                    return;
                }

                setTimeout(function () {
                    var bottomPanels = this.getView('bottomPanels');

                    if (bottomPanels) {
                        bottomPanels.reRender();
                    }
                }.bind(this), 400);
            });
        },
    });
});
