// Pannello righe contratto: view item-list custom (Sales Pack).
define('custom:views/quote/record/panels/items', ['sales:views/quote/record/panels/items'], function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            if (this.getView('itemList')) {
                this.clearView('itemList');
            }

            this.createView('itemList', 'custom:views/quote/fields/item-list', {
                model: this.model,
                el: this.options.el + ' .field-itemList',
                defs: {
                    name: 'itemList',
                },
                mode: this.mode,
            });
        },
    });
});
