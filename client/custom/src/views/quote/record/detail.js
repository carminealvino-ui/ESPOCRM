define('custom:views/quote/record/detail', ['sales:views/quote/record/detail'], function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'after:save', function () {
                setTimeout(function () {
                    if (this.getView('bottomPanels')) {
                        this.getView('bottomPanels').reRender();
                    }
                }.bind(this), 400);
            });
        },
    });
});
