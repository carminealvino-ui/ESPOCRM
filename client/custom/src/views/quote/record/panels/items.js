// Pannello Articoli: forza item-list custom (prezzi listino/codice).
define('custom:views/quote/record/panels/items', ['sales:views/quote/record/panels/items'], function (Dep) {

    return Dep.extend({

        itemListView: 'custom:views/quote/fields/item-list',

        setup: function () {
            this.itemListView = 'custom:views/quote/fields/item-list';
            Dep.prototype.setup.call(this);
        },
    });
});
