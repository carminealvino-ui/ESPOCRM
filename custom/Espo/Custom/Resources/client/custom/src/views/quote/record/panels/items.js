// ========================================
// VERSIONE: 1.0.0
// DATA: 2026-05-26
// FILE: custom/Espo/Custom/Resources/client/custom/src/views/quote/record/panels/items.js
// ========================================

/* global define */

define('custom:views/quote/record/panels/items', ['sales:views/quote/record/panels/items'], function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

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
