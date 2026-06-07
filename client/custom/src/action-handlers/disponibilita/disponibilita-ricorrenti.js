/* global define, Espo */

define('custom:action-handlers/disponibilita/disponibilita-ricorrenti', ['action-handler'], function (Dep) {

    return Dep.extend({

        disponibilitaRicorrenti: function () {
            this.view.createView(
                'disponibilitaRicorrentiModal',
                'custom:views/modals/disponibilita-ricorrenti',
                {},
                function (modalView) {
                    modalView.render();
                }
            );
        }
    });
});
