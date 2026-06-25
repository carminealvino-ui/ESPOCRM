// Pulsante «Crea prodotto» in testata contratto (detail/edit).
define('custom:action-handlers/quote/crea-prodotto', ['action-handler'], function (Dep) {

    return Dep.extend({

        creaProdotto: function () {
            this.view.createView(
                'quickCreateProductModal',
                'views/modals/edit',
                {
                    scope: 'Product',
                    attributes: {},
                },
                function (view) {
                    this.view.listenToOnce(view, 'after:save', function () {
                        Espo.Ui.success('Prodotto creato. Aggiungilo con + nella lista articoli.');
                    }, this);

                    view.render();
                }.bind(this)
            );
        },
    });
});
