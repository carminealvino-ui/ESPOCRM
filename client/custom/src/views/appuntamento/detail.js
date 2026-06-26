/* global define */

define('custom:views/appuntamento/detail', [
    'crm:views/meeting/detail',
    'custom:views/appuntamento/helpers/rifissato',
], function (MeetingDetailModule, RifissatoModule) {

    const Parent = MeetingDetailModule.default || MeetingDetailModule;
    const Rifissato = RifissatoModule.default || RifissatoModule;

    return class AppuntamentoDetailView extends Parent {

        setup() {
            super.setup();

            Rifissato.setupModelHandling(this.model, this);
        }
    };
});