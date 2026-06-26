/* global define */

/**
 * NON registrare in clientDefs finche' non serve.
 * Uso: recordViews.editSmall in Appuntamento.json
 *
 * Applica durata 1h30 solo se diversa da quella attuale (evita loop UI).
 */
define('custom:views/appuntamento/record/edit-small', [
    'crm:views/meeting/record/edit-small',
    'custom:views/appuntamento/helpers/rifissato',
], function (MeetingEditSmallModule, RifissatoModule) {

    const Parent = MeetingEditSmallModule.default || MeetingEditSmallModule;
    const Rifissato = RifissatoModule.default || RifissatoModule;
    const DEFAULT_DURATION_SECONDS = 5400;

    return class AppuntamentoEditSmallView extends Parent {

        setup() {
            super.setup();

            Rifissato.setupRecordHandling(this);

            if (!this.model.isNew() || this.model.get('isAllDay')) {
                return;
            }

            this.once('after:render', () => {
                this.applyDefaultDurationOnce();
            });
        }

        applyDefaultDurationOnce() {
            if (this._defaultDurationApplied) {
                return;
            }

            if (!this.model.isNew() || this.model.get('isAllDay')) {
                return;
            }

            const dateStart = this.model.get('dateStart');

            if (!dateStart) {
                return;
            }

            const dateEnd = this.getExpectedDateEnd(dateStart);
            const currentEnd = this.model.get('dateEnd');

            if (currentEnd === dateEnd) {
                this._defaultDurationApplied = true;

                return;
            }

            this._defaultDurationApplied = true;

            this.model.set({
                dateEnd: dateEnd,
            }, {updatedByDuration: true});
        }

        getExpectedDateEnd(dateStart) {
            return this.getDateTime()
                .toMoment(dateStart)
                .add(DEFAULT_DURATION_SECONDS, 'seconds')
                .format(this.getDateTime().internalDateTimeFormat);
        }
    };
});
