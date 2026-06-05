define('custom:views/quote/record/detail', ['views/record/detail'], function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'after:save', () => {

                setTimeout(() => {

                    // aggiorna solo il subpanel provvigioni
                    if (this.getView('bottomPanels')) {
                        this.getView('bottomPanels').reRender();
                    }

                }, 500);

            });
        }

    });

});