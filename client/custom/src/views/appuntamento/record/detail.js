/* global define */

define('custom:views/appuntamento/record/detail', [
    'crm:views/meeting/record/detail',
    'custom:views/appuntamento/helpers/rifissato',
], function (MeetingDetailModule, RifissatoModule) {

    const Parent = MeetingDetailModule.default || MeetingDetailModule;
    const Rifissato = RifissatoModule.default || RifissatoModule;

    return class AppuntamentoDetailView extends Parent {

        setup() {
            super.setup();

            Rifissato.setupRecordHandling(this);
        }
    };
});
