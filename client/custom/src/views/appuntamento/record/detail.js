/* global define */

define('custom:views/appuntamento/record/detail', [
    'views/record/detail',
    'custom:views/appuntamento/helpers/rifissato',
], function (DetailModule, RifissatoModule) {

    const Parent = DetailModule.default || DetailModule;
    const Rifissato = RifissatoModule.default || RifissatoModule;

    return class AppuntamentoDetailRecordView extends Parent {

        setup() {
            super.setup();

            Rifissato.setupRecordHandling(this);
        }
    };
});
