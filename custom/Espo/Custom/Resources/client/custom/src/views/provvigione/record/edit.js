/* global define */

define('custom:views/provvigione/record/edit', ['views/record/edit'], function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.setFieldReadOnly('importo');
            this.setFieldReadOnly('importoConsolidato');
            this.setFieldReadOnly('importoBaseCalcolo');
            this.setFieldReadOnly('baseCalcolo');
            this.setFieldReadOnly('tassoProvvigioni');
            // Regola/regime/tipo devono restare selezionabili per gestione manuale regole.
        }
    });
});
